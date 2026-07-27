<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Services\Tracker\RemoteRef;
use Larapilot\Services\Tracker\StoryPayload;
use Larapilot\Services\Tracker\TaskPayload;
use Larapilot\Services\Tracker\TrackerDriver;
use Larapilot\Services\Tracker\TrackerException;
use Larapilot\Services\Tracker\TrackerLinkStore;
use Larapilot\Services\Tracker\TrackerManager;
use Larapilot\Support\SpecCode;

/**
 * Syncs the backlog with an external project tracker.
 *
 * Direction is deliberately asymmetric. Push is authoritative: `.larapilot/`
 * decides what a story says and where it sits. Pull is a *report* — it reads
 * remote state and describes the drift, and only writes back when the caller
 * explicitly asks for it.
 *
 * DONE is never applied from a tracker: reaching DONE is a human review gate
 * that also records the merge commit, so it stays with `larapilot:spec-approve`.
 */
class TrackerService
{
    public function __construct(
        protected ConfigService $config,
        protected TrackerManager $manager,
        protected TrackerLinkStore $links,
        protected SpecService $specs,
        protected PlanService $plans,
        protected InternalFeedbackService $feedback,
    ) {}

    public function enabled(): bool
    {
        return $this->manager->enabled();
    }

    /* ---------------------------------------------------------------------
     | Status
     |--------------------------------------------------------------------- */

    /**
     * Configuration and link state. `$ping` adds one live call to the
     * provider, so it stays opt-in.
     *
     * @return array<string, mixed>
     */
    public function status(bool $ping = false): array
    {
        $provider = $this->manager->provider();
        $linked = $provider === null ? [] : $this->links->all($provider);
        $codes = [];

        foreach ($this->syncableSpecs() as $spec) {
            $codes[] = (string) $spec['code'];
        }

        $status = [
            'enabled' => $this->manager->enabled(),
            'provider' => $provider,
            'available_providers' => $this->manager->available(),
            'ready' => $this->manager->ready(),
            'missing_config' => $this->manager->missingConfig(),
            'sync_tasks' => $this->manager->syncTasks(),
            'pull' => [
                'statuses' => $this->manager->pullStatuses(),
                'comments' => $this->manager->pullComments(),
            ],
            'status_map' => $this->manager->statusMap(),
            'link_file' => $this->config->relativePath($this->links->path()),
            'specs' => [
                'total' => count($codes),
                'linked' => count(array_intersect($codes, array_keys($linked))),
                'unlinked' => array_values(array_diff($codes, array_keys($linked))),
            ],
        ];

        if ($ping && $provider !== null) {
            $status['connection'] = $this->manager->driver()->ping();
        }

        return $status;
    }

    /* ---------------------------------------------------------------------
     | Push — Larapilot to the tracker
     |--------------------------------------------------------------------- */

    /**
     * @param  array{codes?: list<string>, dry_run?: bool, force?: bool}  $options
     * @return array<string, mixed>
     */
    public function push(array $options = []): array
    {
        $provider = $this->requireProvider();
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $force = (bool) ($options['force'] ?? false);
        $specs = $this->selectSpecs($options['codes'] ?? null);

        if ($dryRun) {
            return $this->plannedPush($provider, $specs, $force);
        }

        return $this->links->transaction(
            $provider,
            fn (): array => $this->runPush($provider, $specs, $force)
        );
    }

    /**
     * @param  list<array<string, mixed>>  $specs
     * @return array<string, mixed>
     */
    protected function runPush(string $provider, array $specs, bool $force): array
    {
        $driver = $this->manager->driver();
        $results = [];
        $errors = [];

        foreach ($specs as $spec) {
            $code = (string) $spec['code'];

            try {
                $results[] = $this->pushSpec($provider, $code, $spec, $force);
            } catch (TrackerException $exception) {
                $errors[] = ['code' => $code, 'message' => $exception->getMessage()];
            }
        }

        return [
            'provider' => $provider,
            'label' => $driver->label(),
            'dry_run' => false,
            'stories' => $results,
            'summary' => $this->summarize($results),
            'errors' => $errors,
            'link_file' => $this->config->relativePath($this->links->path()),
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    protected function pushSpec(string $provider, string $code, array $spec, bool $force): array
    {
        $driver = $this->manager->driver();
        $link = $this->links->find($provider, $code) ?? [];
        $ref = RemoteRef::fromState($link);
        $payload = $this->storyPayload($spec);
        $fingerprint = $payload->fingerprint();

        if ($ref === null) {
            $ref = $driver->createStory($payload);
            $action = 'created';
        } elseif ($force || (string) ($link['fingerprint'] ?? '') !== $fingerprint) {
            $ref = $this->updateOrRecreate($provider, $code, $ref, $payload);
            $action = 'updated';
        } else {
            $action = 'unchanged';
        }

        $tasks = $this->manager->syncTasks()
            ? $this->pushTasks($driver, $ref, $code, $link, $force)
            : ['links' => is_array($link['tasks'] ?? null) ? $link['tasks'] : [], 'summary' => $this->emptyTaskSummary()];

        $this->links->put($provider, $code, array_filter([
            'id' => $ref->id,
            'key' => $ref->key,
            'url' => $ref->url,
            'fingerprint' => $fingerprint,
            'pushed_at' => now()->toIso8601String(),
            'comments_synced_at' => $link['comments_synced_at'] ?? null,
            'tasks' => $tasks['links'],
        ], fn (mixed $value): bool => $value !== null && $value !== []));

        return [
            'code' => $code,
            'action' => $action,
            'ref' => $ref->label(),
            'url' => $ref->url,
            'tasks' => $tasks['summary'],
        ];
    }

    /**
     * Update a story, recovering from a link that points at a record somebody
     * deleted in the tracker by creating a fresh one.
     */
    protected function updateOrRecreate(string $provider, string $code, RemoteRef $ref, StoryPayload $payload): RemoteRef
    {
        try {
            return $this->manager->driver()->updateStory($ref, $payload);
        } catch (TrackerException $exception) {
            if ($this->manager->driver()->readStory($ref) !== null) {
                throw $exception;
            }

            $this->links->forget($provider, $code);

            return $this->manager->driver()->createStory($payload);
        }
    }

    /**
     * Reconcile plan tasks against their native subtasks: create the new,
     * update the changed, delete the ones whose plan task is gone.
     *
     * @param  array<string, mixed>  $link
     * @return array{links: array<string, array<string, mixed>>, summary: array<string, int>}
     */
    protected function pushTasks(
        TrackerDriver $driver,
        RemoteRef $story,
        string $code,
        array $link,
        bool $force,
    ): array {
        $existing = is_array($link['tasks'] ?? null) ? $link['tasks'] : [];
        $summary = $this->emptyTaskSummary();
        $state = [];

        foreach ($this->taskPayloads($code) as $task) {
            $current = is_array($existing[$task->id] ?? null) ? $existing[$task->id] : null;
            $ref = $current === null ? null : RemoteRef::fromState($current);
            $fingerprint = $task->fingerprint();

            if ($ref === null) {
                $ref = $driver->createTask($story, $task);
                $summary['created']++;
            } elseif ($force || (string) ($current['fingerprint'] ?? '') !== $fingerprint) {
                $ref = $driver->updateTask($story, $ref, $task);
                $summary['updated']++;
            } else {
                $summary['unchanged']++;
            }

            $state[$task->id] = $ref->toState() + ['fingerprint' => $fingerprint];

            unset($existing[$task->id]);
        }

        // Whatever is left was linked to a plan task that no longer exists.
        foreach ($existing as $taskId => $orphan) {
            $ref = is_array($orphan) ? RemoteRef::fromState($orphan) : null;

            if ($ref === null) {
                continue;
            }

            $driver->removeTask($story, $ref);
            $summary['removed']++;
        }

        return ['links' => $state, 'summary' => $summary];
    }

    /**
     * What a push would do, without touching the network.
     *
     * @param  list<array<string, mixed>>  $specs
     * @return array<string, mixed>
     */
    protected function plannedPush(string $provider, array $specs, bool $force): array
    {
        $results = [];

        foreach ($specs as $spec) {
            $code = (string) $spec['code'];
            $link = $this->links->find($provider, $code) ?? [];
            $payload = $this->storyPayload($spec);
            $linkedTasks = is_array($link['tasks'] ?? null) ? $link['tasks'] : [];
            $summary = $this->emptyTaskSummary();

            foreach ($this->taskPayloads($code) as $task) {
                $current = is_array($linkedTasks[$task->id] ?? null) ? $linkedTasks[$task->id] : null;

                if ($current === null) {
                    $summary['created']++;
                } elseif ($force || (string) ($current['fingerprint'] ?? '') !== $task->fingerprint()) {
                    $summary['updated']++;
                } else {
                    $summary['unchanged']++;
                }

                unset($linkedTasks[$task->id]);
            }

            $summary['removed'] = count($linkedTasks);

            $results[] = [
                'code' => $code,
                'action' => match (true) {
                    RemoteRef::fromState($link) === null => 'created',
                    $force || (string) ($link['fingerprint'] ?? '') !== $payload->fingerprint() => 'updated',
                    default => 'unchanged',
                },
                'ref' => RemoteRef::fromState($link)?->label(),
                'url' => $link['url'] ?? null,
                'tasks' => $this->manager->syncTasks() ? $summary : $this->emptyTaskSummary(),
            ];
        }

        return [
            'provider' => $provider,
            'label' => $this->manager->driver()->label(),
            'dry_run' => true,
            'stories' => $results,
            'summary' => $this->summarize($results),
            'errors' => [],
            'link_file' => $this->config->relativePath($this->links->path()),
        ];
    }

    /* ---------------------------------------------------------------------
     | Pull — the tracker back to Larapilot
     |--------------------------------------------------------------------- */

    /**
     * Read remote state and report drift. Without `apply`, nothing in
     * `.larapilot/` changes.
     *
     * @param  array{codes?: list<string>, apply?: bool}  $options
     * @return array<string, mixed>
     */
    public function pull(array $options = []): array
    {
        $provider = $this->requireProvider();
        $apply = (bool) ($options['apply'] ?? false);
        $driver = $this->manager->driver();
        $specs = $this->selectSpecs($options['codes'] ?? null);

        $stories = [];
        $errors = [];
        $applied = 0;
        $imported = 0;

        foreach ($specs as $spec) {
            $code = (string) $spec['code'];
            $ref = $this->links->ref($provider, $code);

            if ($ref === null) {
                continue;
            }

            try {
                $report = $this->pullSpec($provider, $code, $spec, $ref, $apply);
            } catch (TrackerException $exception) {
                $errors[] = ['code' => $code, 'message' => $exception->getMessage()];

                continue;
            }

            $applied += $report['applied'] ? 1 : 0;
            $imported += (int) $report['imported_comments'];
            $stories[] = $report;
        }

        if ($apply) {
            $this->links->save($provider);
        }

        $drifted = array_values(array_filter($stories, fn (array $story): bool => $story['drift']));

        return [
            'provider' => $provider,
            'label' => $driver->label(),
            'apply' => $apply,
            'stories' => $stories,
            'summary' => [
                'checked' => count($stories),
                'in_sync' => count($stories) - count($drifted),
                'drifted' => count($drifted),
                'applied' => $applied,
                'imported_comments' => $imported,
                'missing' => count(array_filter($stories, fn (array $story): bool => $story['remote_status'] === null && $story['missing'])),
            ],
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    protected function pullSpec(string $provider, string $code, array $spec, RemoteRef $ref, bool $apply): array
    {
        $driver = $this->manager->driver();
        $localStatus = (string) ($spec['status'] ?? '');
        $remote = $driver->readStory($ref);

        if ($remote === null) {
            return [
                'code' => $code,
                'ref' => $ref->label(),
                'url' => $ref->url,
                'local_status' => $localStatus,
                'remote_status' => null,
                'suggested_status' => null,
                'drift' => true,
                'missing' => true,
                'applied' => false,
                'blocked' => 'The linked record no longer exists in '.$driver->label().'. Re-run tracker-push to recreate it.',
                'imported_comments' => 0,
            ];
        }

        $inSync = ! $this->manager->pullStatuses() || $this->manager->inSync($localStatus, $remote->status);
        $suggested = $inSync || $remote->status === null ? null : $this->manager->localFor($remote->status);
        $blocked = null;
        $applied = false;

        if (! $inSync && $suggested !== null && $suggested !== $localStatus) {
            if ($this->manager->isDoneStatus($suggested)) {
                // DONE carries a review gate and a merge commit; a tracker
                // cannot grant it.
                $blocked = 'Remote status maps to DONE. Approve through /larapilot-review or larapilot:spec-approve so the merge commit is recorded.';
            } elseif ($apply) {
                $this->specs->setStatus($code, $suggested);
                $applied = true;
            }
        }

        $imported = $this->manager->pullComments() && $apply
            ? $this->importComments($provider, $code, $ref, $localStatus)
            : 0;

        return [
            'code' => $code,
            'ref' => $ref->label(),
            'url' => $ref->url,
            'local_status' => $localStatus,
            'remote_status' => $remote->status,
            'suggested_status' => $suggested,
            'drift' => ! $inSync,
            'missing' => false,
            'applied' => $applied,
            'blocked' => $blocked ?? ($suggested === null && ! $inSync
                ? 'Remote status "'.(string) $remote->status.'" is not in the status map, so no Larapilot status can be inferred.'
                : null),
            'imported_comments' => $imported,
        ];
    }

    /**
     * Append remote comments as internal feedback, newest cursor first so a
     * repeated pull does not duplicate them. Never blocking — a tracker
     * comment is context, not a merge gate.
     */
    protected function importComments(string $provider, string $code, RemoteRef $ref, string $localStatus): int
    {
        $link = $this->links->find($provider, $code) ?? [];
        $cursor = is_string($link['comments_synced_at'] ?? null) ? $link['comments_synced_at'] : null;
        $driver = $this->manager->driver();
        $imported = 0;
        $latest = $cursor;

        foreach ($driver->readComments($ref) as $comment) {
            if (! $comment->isNewerThan($cursor)) {
                continue;
            }

            try {
                $this->feedback->append(
                    $code,
                    $driver->label().' · '.$comment->author,
                    trim($comment->body),
                    $localStatus,
                    false,
                );
            } catch (\RuntimeException|\InvalidArgumentException) {
                // Comments disabled, or the spec is DONE and closed for
                // feedback — skip rather than fail the pull.
                continue;
            }

            $imported++;

            if ($comment->createdAt !== null && ($latest === null || strtotime($comment->createdAt) > strtotime($latest))) {
                $latest = $comment->createdAt;
            }
        }

        if ($imported > 0) {
            $link['comments_synced_at'] = $latest ?? now()->toIso8601String();
            $this->links->put($provider, $code, $link);
        }

        return $imported;
    }

    /* ---------------------------------------------------------------------
     | Payload building
     |--------------------------------------------------------------------- */

    /**
     * @param  array<string, mixed>  $spec
     */
    protected function storyPayload(array $spec): StoryPayload
    {
        $code = (string) ($spec['code'] ?? '');
        $status = (string) ($spec['status'] ?? '');

        return new StoryPayload(
            $code,
            (string) ($spec['title'] ?? ''),
            $this->description($spec),
            $this->manager->remoteFor($status),
            trim((string) ($spec['priority'] ?? '')) ?: null,
            max(0, (int) ($spec['points'] ?? 0)),
            $this->taskPayloads($code),
            $this->manager->isDoneStatus($status),
        );
    }

    /**
     * The card body: the spec as written, plus a short provenance footer so
     * whoever reads it in the tracker knows where to make changes.
     *
     * @param  array<string, mixed>  $spec
     */
    protected function description(array $spec): string
    {
        $lines = [];
        $body = trim((string) ($spec['body'] ?? ''));

        if ($body !== '') {
            $lines[] = $body;
            $lines[] = '';
        }

        $meta = [];

        if (($priority = trim((string) ($spec['priority'] ?? ''))) !== '') {
            $meta[] = 'Priority: '.$priority;
        }

        if (($points = (int) ($spec['points'] ?? 0)) > 0) {
            $meta[] = 'Points: '.$points;
        }

        $epic = $spec['epic'] ?? null;

        if (is_array($epic) && isset($epic['code'])) {
            $meta[] = 'Epic: '.(string) $epic['code'].' — '.(string) ($epic['title'] ?? $epic['code']);
        }

        if ($meta !== []) {
            $lines[] = implode(' · ', $meta);
            $lines[] = '';
        }

        $lines[] = '---';
        $lines[] = 'Managed by Larapilot — spec `'.(string) ($spec['code'] ?? '').'`. '
            .'Edit the spec in `.larapilot/`, then re-run `php artisan larapilot:tracker-push`; '
            .'edits made here are overwritten on the next push.';

        return implode("\n", $lines);
    }

    /**
     * @return list<TaskPayload>
     */
    protected function taskPayloads(string $code): array
    {
        $plan = $this->plans->read($code);

        if ($plan === null) {
            return [];
        }

        $tasks = is_array($plan['tasks'] ?? null) ? $plan['tasks'] : [];
        $payloads = [];

        foreach ($tasks as $task) {
            if (! is_array($task)) {
                continue;
            }

            $id = trim((string) ($task['id'] ?? ''));

            if ($id === '') {
                continue;
            }

            $payloads[] = new TaskPayload(
                $id,
                (string) ($task['title'] ?? ''),
                trim((string) ($task['body'] ?? '')),
                strtoupper((string) ($task['status'] ?? '')) === 'DONE',
                trim((string) ($task['type'] ?? '')) ?: null,
            );
        }

        return $payloads;
    }

    /* ---------------------------------------------------------------------
     | Helpers
     |--------------------------------------------------------------------- */

    protected function requireProvider(): string
    {
        if (! $this->manager->enabled()) {
            throw new TrackerException('The project-tracker integration is disabled. Set LARAPILOT_TRACKER_ENABLED=true.');
        }

        $provider = $this->manager->provider();

        if ($provider === null) {
            throw new TrackerException(
                'No project tracker selected. Set LARAPILOT_TRACKER_PROVIDER to one of: '
                .implode(', ', $this->manager->available()).'.'
            );
        }

        $missing = $this->manager->missingConfig();

        if ($missing !== []) {
            throw new TrackerException(
                ucfirst($provider).' is selected but not configured. Set: '.implode(', ', $missing).'.'
            );
        }

        return $provider;
    }

    /**
     * Specs safe to sync: a valid code is required because it identifies the
     * remote record for the rest of the project's life.
     *
     * @return list<array<string, mixed>>
     */
    protected function syncableSpecs(): array
    {
        $specs = [];

        foreach ($this->specs->allSpecs() as $spec) {
            $code = (string) ($spec['code'] ?? '');

            if ($code !== '' && SpecCode::isValid($code)) {
                $specs[] = $spec;
            }
        }

        return $specs;
    }

    /**
     * @param  list<string>|null  $codes
     * @return list<array<string, mixed>>
     */
    protected function selectSpecs(?array $codes): array
    {
        $specs = $this->syncableSpecs();

        if ($codes === null || $codes === []) {
            return $specs;
        }

        $wanted = array_map(fn (string $code): string => strtoupper(trim($code)), $codes);
        $selected = array_values(array_filter(
            $specs,
            fn (array $spec): bool => in_array(strtoupper((string) $spec['code']), $wanted, true)
        ));

        if ($selected === []) {
            throw new TrackerException('No backlog spec matches: '.implode(', ', $codes).'.');
        }

        return $selected;
    }

    /**
     * @return array<string, int>
     */
    protected function emptyTaskSummary(): array
    {
        return ['created' => 0, 'updated' => 0, 'removed' => 0, 'unchanged' => 0];
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @return array<string, int>
     */
    protected function summarize(array $results): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'tasks_created' => 0, 'tasks_updated' => 0, 'tasks_removed' => 0];

        foreach ($results as $result) {
            $action = (string) $result['action'];

            if (isset($summary[$action])) {
                $summary[$action]++;
            }

            $tasks = is_array($result['tasks'] ?? null) ? $result['tasks'] : [];
            $summary['tasks_created'] += (int) ($tasks['created'] ?? 0);
            $summary['tasks_updated'] += (int) ($tasks['updated'] ?? 0);
            $summary['tasks_removed'] += (int) ($tasks['removed'] ?? 0);
        }

        return $summary;
    }
}
