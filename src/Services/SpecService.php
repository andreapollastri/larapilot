<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\AtomicFile;
use Larapilot\Support\Checklist;
use Larapilot\Support\SpecCode;
use Symfony\Component\Yaml\Yaml;

class SpecService
{
    public function __construct(
        protected ConfigService $config,
        protected GitService $git,
    ) {}

    public function backlogPath(): string
    {
        $config = $this->config->resolve();

        return $this->config->absolutePath($config['file']['backlog'] ?? '.larapilot/backlog.yaml');
    }

    public function specsDirectory(): string
    {
        $config = $this->config->resolve();

        return $this->config->absolutePath($config['file']['specs'] ?? '.larapilot/specs/');
    }

    public function specPath(string $code): string
    {
        return rtrim($this->specsDirectory(), '/').'/'.SpecCode::ensure($code).'.yaml';
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function list(?string $status = null): array
    {
        $items = $this->allSpecs();

        if ($status !== null) {
            $items = array_values(array_filter(
                $items,
                fn (array $spec): bool => strtoupper((string) ($spec['status'] ?? '')) === strtoupper($status)
            ));
        }

        return [
            'items' => $items,
            'summary' => $this->summary($items),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allSpecs(): array
    {
        if (! is_file($this->backlogPath())) {
            return [];
        }

        $parsed = Yaml::parseFile($this->backlogPath());

        if (! is_array($parsed)) {
            return [];
        }

        $specs = $parsed['specs'] ?? [];

        return is_array($specs) ? array_values($specs) : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    protected function summary(array $items): array
    {
        $codes = array_map(fn (array $spec): string => (string) ($spec['code'] ?? ''), $items);
        $codes = array_values(array_filter($codes));

        $epics = [];
        foreach ($items as $spec) {
            $epic = $spec['epic'] ?? null;
            if (is_array($epic) && isset($epic['code'])) {
                $epics[(string) $epic['code']] = (string) ($epic['title'] ?? $epic['code']);
            }
        }

        return [
            'count' => count($items),
            'codes' => $codes,
            'last_code' => $codes === [] ? null : end($codes),
            'epics' => $epics,
            'titles' => array_combine(
                $codes,
                array_map(fn (array $spec): string => (string) ($spec['title'] ?? ''), $items)
            ) ?: [],
        ];
    }

    /**
     * @return array{spec: array<string, mixed>, tasks: array<int, array<string, mixed>>, workdir: string}|null
     */
    public function show(string $code): ?array
    {
        $spec = $this->find($code);

        if ($spec === null) {
            return null;
        }

        $tasks = [];
        $planPath = $this->planPath($code);

        if (is_file($planPath)) {
            $plan = Yaml::parseFile($planPath);
            if (is_array($plan)) {
                $tasks = is_array($plan['tasks'] ?? null) ? $plan['tasks'] : [];
            }
        }

        return [
            'spec' => $spec,
            'tasks' => $tasks,
            'workdir' => $this->workdir($spec),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $code): ?array
    {
        foreach ($this->allSpecs() as $spec) {
            if (($spec['code'] ?? null) === $code) {
                return $spec;
            }
        }

        return null;
    }

    /**
     * @return array{spec: array<string, mixed>, tasks: array<int, array<string, mixed>>, workdir: string}|null
     */
    public function next(?string $status = null): ?array
    {
        $status = $status ?? $this->config->status('todo');
        $items = $this->list($status)['items'];

        if ($items === []) {
            return null;
        }

        usort($items, function (array $a, array $b): int {
            $priorityOrder = ['CRITICAL' => 0, 'HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3];
            $pa = $priorityOrder[strtoupper((string) ($a['priority'] ?? 'MEDIUM'))] ?? 2;
            $pb = $priorityOrder[strtoupper((string) ($b['priority'] ?? 'MEDIUM'))] ?? 2;

            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return strcmp((string) ($a['code'] ?? ''), (string) ($b['code'] ?? ''));
        });

        $code = (string) ($items[0]['code'] ?? '');

        return $this->show($code);
    }

    /**
     * @param  array<int, array<string, mixed>>  $specs
     */
    public function add(array $specs): void
    {
        $existing = $this->allSpecs();
        $indexed = [];

        foreach ($existing as $spec) {
            if (isset($spec['code'])) {
                $indexed[(string) $spec['code']] = $spec;
            }
        }

        foreach ($specs as $spec) {
            $code = (string) ($spec['code'] ?? '');
            if (! SpecCode::isValid($code)) {
                continue;
            }

            $indexed[$code] = array_merge($indexed[$code] ?? [], $spec);

            if (trim((string) ($indexed[$code]['status'] ?? '')) === '') {
                $indexed[$code]['status'] = $this->config->status('todo');
            }

            $this->writeSpecFile($code, $indexed[$code]);
        }

        $this->writeBacklog(array_values($indexed));
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    public function update(string $code, array $spec): void
    {
        $existing = $this->find($code);

        if ($existing === null) {
            throw new \RuntimeException("Spec {$code} not found.");
        }

        $merged = array_replace_recursive($existing, $spec, ['code' => $code]);
        $this->persistSpec($merged);
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    public function persistSpec(array $spec): void
    {
        $code = (string) ($spec['code'] ?? '');

        if ($code === '') {
            throw new \RuntimeException('Spec code is required.');
        }

        $specs = $this->allSpecs();
        $found = false;

        foreach ($specs as $index => $item) {
            if (($item['code'] ?? null) === $code) {
                $specs[$index] = $spec;
                $found = true;
                break;
            }
        }

        if (! $found) {
            $specs[] = $spec;
        }

        $this->writeBacklog($specs);
        $this->writeSpecFile($code, $spec);
    }

    public function tickBodyChecklist(string $code): void
    {
        $spec = $this->find($code);

        if ($spec === null) {
            throw new \RuntimeException("Spec {$code} not found.");
        }

        $body = (string) ($spec['body'] ?? '');
        $ticked = Checklist::tick($body);

        if ($ticked !== $body) {
            $spec['body'] = $ticked;
            $this->persistSpec($spec);
        }
    }

    public function setStatus(string $code, string $status): void
    {
        $spec = $this->find($code);

        if ($spec === null) {
            throw new \RuntimeException("Spec {$code} not found.");
        }

        $spec['status'] = $status;
        $spec['status_history'] = array_merge(
            is_array($spec['status_history'] ?? null) ? $spec['status_history'] : [],
            [['status' => $status, 'at' => now()->toIso8601String()]]
        );

        if ($status !== $this->config->status('review')) {
            $spec['rework'] = false;
        }

        $this->persistSpec($spec);
    }

    /**
     * @return array{sha: string, short_sha: string, subject: string, committed_at: string, url: string|null}|null
     */
    public function approve(string $code, ?string $commitSha = null): ?array
    {
        $spec = $this->find($code);

        if ($spec === null) {
            throw new \RuntimeException("Spec {$code} not found.");
        }

        $this->tickBodyChecklist($code);

        $spec = $this->find($code);

        if ($spec === null) {
            throw new \RuntimeException("Spec {$code} not found.");
        }

        $commit = $this->git->resolveMergeCommit($code, $commitSha);
        $doneStatus = $this->config->status('done');

        $spec['status'] = $doneStatus;
        $spec['status_history'] = array_merge(
            is_array($spec['status_history'] ?? null) ? $spec['status_history'] : [],
            [['status' => $doneStatus, 'at' => now()->toIso8601String()]]
        );
        $spec['rework'] = false;

        if ($commit !== null) {
            $spec['merge_commit'] = $commit;
        } else {
            unset($spec['merge_commit']);
        }

        $this->persistSpec($spec);

        return $commit;
    }

    public function delete(string $code): void
    {
        if ($this->find($code) === null) {
            throw new \RuntimeException("Spec {$code} not found.");
        }

        $remaining = array_values(array_filter(
            $this->allSpecs(),
            fn (array $spec): bool => ($spec['code'] ?? null) !== $code
        ));

        $this->writeBacklog($remaining);

        foreach ([$this->specPath($code), $this->planPath($code)] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $specs
     */
    protected function writeBacklog(array $specs): void
    {
        AtomicFile::write(
            $this->backlogPath(),
            Yaml::dump(['specs' => array_values($specs)], 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
        );
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    protected function writeSpecFile(string $code, array $spec): void
    {
        AtomicFile::write(
            $this->specPath($code),
            Yaml::dump($spec, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
        );
    }

    protected function planPath(string $code): string
    {
        $config = $this->config->resolve();
        $planning = $this->config->absolutePath($config['file']['planning'] ?? '.larapilot/plans/');

        return rtrim($planning, '/').'/'.SpecCode::ensure($code).'-plan.yaml';
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    protected function workdir(array $spec): string
    {
        $worktree = $spec['worktree'] ?? null;

        if (is_string($worktree) && $worktree !== '') {
            $absolute = $this->config->absolutePath($worktree);

            if (is_dir($absolute)) {
                return $absolute;
            }
        }

        return $this->config->projectRoot();
    }

    /**
     * @return array<string, mixed>
     */
    public function metrics(): array
    {
        $items = $this->allSpecs();
        $statuses = $this->config->resolve()['workflow']['statuses'] ?? [];

        $byStatus = [];
        foreach ($items as $spec) {
            $status = (string) ($spec['status'] ?? 'TODO');
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
        }

        $doneStatus = strtoupper((string) ($statuses['done'] ?? 'DONE'));
        $done = $byStatus[$statuses['done'] ?? 'DONE'] ?? 0;
        $total = count($items);
        $totalPoints = 0;
        $donePoints = 0;

        foreach ($items as $spec) {
            $points = max(0, (int) ($spec['points'] ?? 0));
            $totalPoints += $points;

            if (strtoupper((string) ($spec['status'] ?? '')) === $doneStatus) {
                $donePoints += $points;
            }
        }

        return [
            'total' => $total,
            'done' => $done,
            'completion_rate' => $total > 0 ? round($done / $total * 100, 1) : 0.0,
            'by_status' => $byStatus,
            'wip' => ($byStatus[$statuses['in_progress'] ?? 'IN PROGRESS'] ?? 0)
                + ($byStatus[$statuses['review'] ?? 'REVIEW'] ?? 0),
            'total_points' => $totalPoints,
            'done_points' => $donePoints,
            'points_completion_rate' => $totalPoints > 0 ? round($donePoints / $totalPoints * 100, 1) : 0.0,
        ];
    }
}
