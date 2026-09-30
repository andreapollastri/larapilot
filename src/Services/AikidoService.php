<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Larapilot\Services\Aikido\AikidoClient;
use Larapilot\Services\Aikido\AikidoException;
use Larapilot\Services\Aikido\AikidoLedger;
use Larapilot\Support\AtomicFile;

/**
 * The findings of Aikido for this repository, set against the backlog.
 *
 * Aikido scans the repository on its side, through the connection to the
 * git provider. Larapilot runs no scanner: it reads what Aikido found, says
 * what was already decided about each finding, and gives the ship gate a
 * verdict. Turning a finding into work is the job of `/larapilot-aikido`,
 * which hands it to triage.
 *
 * A decision the user takes here is told to Aikido, so the workspace says
 * the same as the project: a waiver ignores the finding there, with its
 * reason, and a spec leaves a note on it.
 */
class AikidoService
{
    /**
     * From the most to the least severe.
     *
     * @var list<string>
     */
    public const SEVERITIES = ['critical', 'high', 'medium', 'low'];

    /**
     * What each kind of finding is, in words.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'open_source' => 'Vulnerable dependency',
        'leaked_secret' => 'Leaked secret',
        'sast' => 'Weakness in the code',
        'iac' => 'Infrastructure as code',
        'cloud' => 'Cloud configuration',
        'cloud_instance' => 'Cloud instance',
        'docker_container' => 'Container image',
        'surface_monitoring' => 'Exposed surface',
        'malware' => 'Malware in a dependency',
        'eol' => 'End-of-life runtime',
        'mobile' => 'Mobile application',
        'scm_security' => 'Repository settings',
        'ai_pentest' => 'Pentest finding',
        'license' => 'License risk',
    ];

    protected const PAGE = 100;

    protected const MAX_PAGES = 5;

    protected const REPOSITORY_PAGE = 200;

    protected const REPOSITORY_PAGES = 3;

    protected const EXPORT_PAGE = 1000;

    /**
     * Aikido answered, and would answer the same to the next call: no
     * credentials, no right to write, too many calls, or no network.
     *
     * @var list<int>
     */
    protected const STOPS = [0, 401, 403, 429];

    protected const NAME_THE_REPOSITORY = 'Say which repository of Aikido this project is: php artisan larapilot:aikido-repos lists them, and php artisan larapilot:aikido-repos --use={id} keeps the choice in .larapilot/aikido.yaml. LARAPILOT_AIKIDO_REPOSITORY in .env names it for one machine only. A repository that is not in the list has to be connected in Aikido first.';

    public function __construct(
        protected ConfigService $config,
        protected GitService $git,
        protected SpecService $specs,
        protected AikidoClient $client,
        protected AikidoLedger $ledger,
    ) {}

    /**
     * Whether Aikido can be read, and what is missing when it cannot.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $enabled = $this->config->aikidoEnabled();
        $configured = $this->client->configured();
        $hints = [];
        $repository = null;
        $authenticated = false;
        $error = null;

        if (! $enabled) {
            $hints[] = 'Enable with: php artisan larapilot:settings-set --aikido=YES';
        }

        if (! $configured) {
            $hints[] = 'Set LARAPILOT_AIKIDO_CLIENT_ID and LARAPILOT_AIKIDO_CLIENT_SECRET in .env. Create them in Aikido under Settings → Integrations → Public REST API, with the issues:read and repositories:read scopes.';
        }

        if ($configured) {
            try {
                $repository = $this->repository();
                $authenticated = true;

                if ($repository === null) {
                    $hints[] = 'No repository of the Aikido workspace matches this project'.($this->wanted() !== '' ? ' ("'.$this->wanted().'")' : '').'. '.self::NAME_THE_REPOSITORY;
                } elseif (($repository['last_scanned_at'] ?? null) === null) {
                    $hints[] = 'Aikido has not scanned this repository yet. Ask for a scan: php artisan larapilot:aikido-scan';
                }
            } catch (AikidoException $e) {
                $error = $e->getMessage();
                $authenticated = ! in_array($e->status(), [0, 400, 401], true);
                $hints[] = trim($e->getMessage().' '.($e->hint() ?? ''));
            }
        }

        return [
            'enabled' => $enabled,
            'configured' => $configured,
            'authenticated' => $authenticated,
            'region' => $this->client->region(),
            'host' => $this->client->host(),
            'repository' => $repository,
            'repository_source' => $repository !== null ? $this->repositorySource() : null,
            // The workspace answered and none of its repositories is this
            // project: the user has to say which one it is.
            'needs_repository' => $configured && $authenticated && $error === null && $repository === null,
            'origin' => $this->git->originUrl(),
            'fail_on' => $this->failOn(),
            'push_decisions' => $this->pushesDecisions(),
            'ledger' => $this->ledger->relativePath(),
            'ready' => $enabled && $configured && $authenticated && $repository !== null,
            'error' => $error,
            'hints' => $hints,
        ];
    }

    /**
     * The open findings of the repository, the most severe first, each with
     * what was decided about it, and the verdict of the gate.
     *
     * @param  array{severity?: string|null, type?: string|null, new?: bool, limit?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function findings(array $filters = [], bool $fresh = true): array
    {
        $data = $fresh ? $this->download() : $this->cached();
        $decisions = $this->ledger->all();
        $issues = [];

        foreach ($data['issues'] as $issue) {
            $issues[] = $this->decided($issue, $decisions[(string) $issue['id']] ?? null);
        }

        $open = array_column($issues, 'id');
        $closed = [];

        // Decided here, and no longer open in Aikido: fixed, or closed there.
        foreach ($decisions as $id => $decision) {
            if (! in_array((int) $id, $open, true)) {
                $closed[] = array_merge(['id' => (int) $id], $decision);
            }
        }

        $gate = $this->gate($issues);
        $listed = $this->filter($issues, $filters);
        $unsent = [];

        foreach ($issues as $issue) {
            if ($issue['state'] !== 'new' && ! $this->told($decisions[(string) $issue['id']] ?? [])) {
                $unsent[] = $issue['id'];
            }
        }

        return [
            'repository' => $data['repository'],
            'fetched_at' => $data['fetched_at'],
            'truncated' => $data['truncated'],
            'counts' => $this->counts($issues),
            'states' => [
                'new' => count(array_filter($issues, static fn (array $issue): bool => $issue['state'] === 'new')),
                'in_backlog' => count(array_filter($issues, static fn (array $issue): bool => $issue['state'] === AikidoLedger::IN_BACKLOG)),
                'waived' => count(array_filter($issues, static fn (array $issue): bool => $issue['state'] === AikidoLedger::WAIVED)),
            ],
            'gate' => $gate,
            'total' => count($issues),
            'issues' => $listed,
            'closed' => $closed,
            // Decided here about a finding that is open, and not told to Aikido yet.
            'unsent' => $this->pushesDecisions() ? $unsent : [],
            'push_decisions' => $this->pushesDecisions(),
            'ledger' => $this->ledger->relativePath(),
        ];
    }

    /**
     * Record that findings are in the backlog as a spec, and leave a note
     * about it on each finding in Aikido.
     *
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    public function link(array $ids, string $spec, bool $push = true): array
    {
        $ids = $this->ids($ids);
        $found = $this->specs->find($spec);

        if ($found === null) {
            throw new InvalidArgumentException("No spec {$spec} in the backlog.");
        }

        $known = $this->known($ids);

        foreach ($ids as $id) {
            $this->ledger->record([$id], array_filter([
                'state' => AikidoLedger::IN_BACKLOG,
                'spec' => (string) $found['code'],
                'reason' => null,
                'title' => $known[$id]['title'] ?? null,
                'severity' => $known[$id]['severity'] ?? null,
                'type' => $known[$id]['type'] ?? null,
                'first_seen' => $known[$id]['first_detected_at'] ?? null,
            ], static fn (mixed $value): bool => $value !== null), $this->repositoryRef());
        }

        // A decision changes the register: what was kept of it is stale.
        Cache::forget($this->cacheKey().'.register');

        return [
            'issues' => $ids,
            'state' => AikidoLedger::IN_BACKLOG,
            'spec' => (string) $found['code'],
            'ledger' => $this->ledger->relativePath(),
            'aikido' => $this->tell($ids, $push),
        ];
    }

    /**
     * Record that findings are accepted as they are, and why, and ignore
     * them in Aikido with the same reason.
     *
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    public function waive(array $ids, string $reason, bool $push = true): array
    {
        $ids = $this->ids($ids);
        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('Say why the finding is accepted, in a sentence: a waiver without a reason cannot be reviewed.');
        }

        $known = $this->known($ids);

        foreach ($ids as $id) {
            $this->ledger->record([$id], array_filter([
                'state' => AikidoLedger::WAIVED,
                'reason' => $reason,
                'spec' => null,
                'title' => $known[$id]['title'] ?? null,
                'severity' => $known[$id]['severity'] ?? null,
                'type' => $known[$id]['type'] ?? null,
                'first_seen' => $known[$id]['first_detected_at'] ?? null,
            ], static fn (mixed $value): bool => $value !== null), $this->repositoryRef());
        }

        Cache::forget($this->cacheKey().'.register');

        return [
            'issues' => $ids,
            'state' => AikidoLedger::WAIVED,
            'reason' => $reason,
            'ledger' => $this->ledger->relativePath(),
            'aikido' => $this->tell($ids, $push),
        ];
    }

    /**
     * Drop what was decided about findings. A waiver that was told to
     * Aikido is taken back there first: a finding that stayed ignored in
     * Aikido would never come back to be decided about again.
     *
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    public function forget(array $ids, bool $push = true): array
    {
        $ids = $this->ids($ids);
        $forgotten = [];
        $told = [];

        foreach ($ids as $id) {
            $decision = $this->ledger->find($id) ?? [];

            if ($push && ($decision['sent'] ?? null) === 'ignored') {
                try {
                    $told[] = $this->unignore($id, $decision);
                } catch (AikidoException $e) {
                    // Still ignored in Aikido: the decision stays, so the two keep saying the same.
                    $told[] = $this->refused($id, $e) + ['kept' => true];

                    continue;
                }
            }

            $forgotten[] = $id;
        }

        if ($forgotten !== []) {
            $this->ledger->forget($forgotten);
            Cache::forget($this->cacheKey().'.register');
        }

        return ['issues' => $forgotten, 'state' => 'new', 'ledger' => $this->ledger->relativePath(), 'aikido' => $told];
    }

    /**
     * Tell Aikido every decision about an open finding it was not told
     * yet: the ones taken before the credentials could write, while Aikido
     * was away, or with an earlier version.
     *
     * @return array<string, mixed>
     */
    public function push(): array
    {
        $findings = $this->findings();
        $told = [];
        $left = $findings['unsent'];

        foreach ($findings['unsent'] as $id) {
            $result = $this->transmit($id);
            $told[] = $result;

            if ($result['sent']) {
                $left = array_values(array_diff($left, [$id]));
            } elseif (in_array($result['status'] ?? 0, self::STOPS, true)) {
                break;
            }
        }

        return ['aikido' => $told, 'left' => $left, 'ledger' => $this->ledger->relativePath()];
    }

    public function pushesDecisions(): bool
    {
        return (bool) config('larapilot.aikido.push_decisions', true);
    }

    /**
     * Resolution groups for findings the user confirmed: same kind and the
     * same fix go together; leaked secrets never merge with anything else.
     *
     * @param  list<int|string>  $ids
     * @return array<string, mixed>
     */
    public function resolutionPlan(array $ids): array
    {
        $ids = $this->ids($ids);
        $known = $this->known($ids);
        $issues = [];

        foreach ($ids as $id) {
            $issues[] = $known[$id];
        }

        $rank = array_flip(self::SEVERITIES);

        usort($issues, static fn (array $a, array $b): int => [$rank[$a['severity']], -$a['score'], $a['id']] <=> [$rank[$b['severity']], -$b['score'], $b['id']]);

        $groups = $this->resolutionGroups($issues);
        $domains = [];

        foreach ($groups as $group) {
            $label = (string) $group['type_label'];

            if (! isset($domains[$label])) {
                $domains[$label] = [
                    'type' => $group['type'],
                    'type_label' => $label,
                    'group_keys' => [],
                    'ids' => [],
                ];
            }

            $domains[$label]['group_keys'][] = $group['key'];
            $domains[$label]['ids'] = array_values(array_unique([...$domains[$label]['ids'], ...$group['ids']]));
        }

        return [
            'ids' => $ids,
            'domains' => array_values($domains),
            'groups' => $groups,
        ];
    }

    /**
     * Ask Aikido to scan the repository again. The scan runs on its side
     * and takes minutes: the findings change when it is done.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        $repository = $this->repository();

        if ($repository === null) {
            throw new AikidoException('No repository of the Aikido workspace matches this project.', self::NAME_THE_REPOSITORY);
        }

        $this->client->post('/repositories/code/'.$repository['id'].'/scan', [
            'include_sast_scan' => 'true',
            'include_iac_scan' => 'true',
            'include_secrets_scan' => 'true',
        ]);

        // What was read is about to change.
        Cache::forget($this->cacheKey());

        return ['repository' => $repository, 'requested_at' => now()->toIso8601String()];
    }

    /**
     * The findings as a document of the project: what is open, and what
     * was decided about it. Give it findings no filter has narrowed.
     *
     * @param  array<string, mixed>  $findings
     */
    public function report(array $findings): string
    {
        $repository = $findings['repository'];
        $gate = $findings['gate'];
        $lines = ['# Aikido findings'.(($repository['name'] ?? '') !== '' ? ' — '.$repository['name'] : ''), ''];
        $lines[] = 'Open findings as Aikido reported them on '.Carbon::parse($findings['fetched_at'])->format('Y-m-d H:i').', with what was decided about each one.';
        $lines[] = '';
        $lines[] = '**Gate:** '.$gate['verdict'].' — '.$gate['summary'];
        $lines[] = '';
        $lines[] = '| Severity | Open | New | In the backlog | Waived |';
        $lines[] = '| --- | ---: | ---: | ---: | ---: |';

        foreach (self::SEVERITIES as $severity) {
            $of = array_filter($findings['issues'], static fn (array $issue): bool => $issue['severity'] === $severity);
            $lines[] = '| '.ucfirst($severity).' | '.count($of)
                .' | '.count(array_filter($of, static fn (array $issue): bool => $issue['state'] === 'new'))
                .' | '.count(array_filter($of, static fn (array $issue): bool => $issue['state'] === AikidoLedger::IN_BACKLOG))
                .' | '.count(array_filter($of, static fn (array $issue): bool => $issue['state'] === AikidoLedger::WAIVED)).' |';
        }

        foreach (self::SEVERITIES as $severity) {
            $of = array_values(array_filter($findings['issues'], static fn (array $issue): bool => $issue['severity'] === $severity));

            if ($of === []) {
                continue;
            }

            $lines[] = '';
            $lines[] = '## '.ucfirst($severity).' ('.count($of).')';

            foreach ($of as $issue) {
                $lines[] = '';
                $lines[] = '### #'.$issue['id'].' — '.$this->inline($issue['title']);
                $lines[] = '';
                $lines[] = '- **Kind:** '.$issue['type_label'];
                $lines[] = '- **Decision:** '.match ($issue['state']) {
                    AikidoLedger::IN_BACKLOG => 'in the backlog as '.$issue['spec'].($issue['spec_status'] ? ' ('.$issue['spec_status'].')' : ''),
                    AikidoLedger::WAIVED => 'waived — '.$this->inline((string) $issue['reason']),
                    default => 'none yet',
                };

                if ($issue['where'] !== []) {
                    $lines[] = '- **Where:** '.$this->inline(implode(', ', $issue['where']));
                }

                if ($issue['cves'] !== []) {
                    $lines[] = '- **CVE:** '.implode(', ', $issue['cves']);
                }

                if ($issue['first_detected_at'] !== null) {
                    $lines[] = '- **First seen:** '.Carbon::parse($issue['first_detected_at'])->format('Y-m-d');
                }

                if ($issue['description'] !== '') {
                    $lines[] = '';
                    $lines[] = $this->inline($issue['description']);
                }

                if ($issue['how_to_fix'] !== '') {
                    $lines[] = '';
                    $lines[] = '**How to fix:** '.$this->inline($issue['how_to_fix']);
                }
            }
        }

        if ($findings['closed'] !== []) {
            $lines[] = '';
            $lines[] = '## No longer open in Aikido';
            $lines[] = '';
            $lines[] = '| Finding | Was | Decision |';
            $lines[] = '| --- | --- | --- |';

            foreach ($findings['closed'] as $closed) {
                $lines[] = '| #'.$closed['id'].' '.str_replace('|', '\\|', $this->inline((string) ($closed['title'] ?? ''))).' | '
                    .($closed['severity'] ?? '—').' | '
                    .(($closed['state'] ?? '') === AikidoLedger::WAIVED
                        ? 'waived — '.str_replace('|', '\\|', $this->inline((string) ($closed['reason'] ?? '')))
                        : 'fixed by '.($closed['spec'] ?? '—')).' |';
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, mixed>  $findings
     * @return array{path: string, relative: string}
     */
    public function writeReport(array $findings): array
    {
        $directory = rtrim($this->config->resolve()['paths']['security'] ?? $this->config->absolutePath('.larapilot/docs/security/'), '/\\');
        $directory = $this->config->absolutePath($directory);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.'aikido.md';
        AtomicFile::write($path, $this->report($findings));

        return ['path' => $path, 'relative' => str_replace('\\', '/', $this->config->relativePath($path))];
    }

    /**
     * Every finding of the repository, whatever its state: what is open,
     * what was resolved, and what was ignored with the reason. It is what
     * a client, or an auditor, asks to see.
     *
     * The states are Aikido's. The reason of a waiver is the one kept
     * here: Aikido does not give back the reason a finding was ignored
     * with, so one ignored there by hand has none in this list.
     *
     * @return array<string, mixed>
     */
    public function register(bool $fresh = false): array
    {
        $key = $this->cacheKey().'.register';
        $cached = $fresh ? null : Cache::get($key);

        if (is_array($cached) && isset($cached['open'], $cached['resolved'], $cached['ignored'])) {
            return $cached;
        }

        $findings = $this->findings([], $fresh);
        $repository = (int) $findings['repository']['id'];
        $decisions = $this->ledger->all();
        $dates = $this->dates($repository);
        $truncated = (bool) $findings['truncated'];
        $placed = [];
        $lists = ['open' => [], 'resolved' => [], 'ignored' => []];

        $place = static function (string $list, array $entry) use (&$lists, &$placed): void {
            if (! isset($placed[$entry['id']])) {
                $placed[$entry['id']] = true;
                $lists[$list][] = $entry;
            }
        };

        // A finding waived here is an accepted risk, whether or not Aikido was told yet.
        foreach ($findings['issues'] as $issue) {
            $waived = $issue['state'] === AikidoLedger::WAIVED;

            $place($waived ? 'ignored' : 'open', $issue + [
                'status' => $waived ? 'ignored' : 'open',
                'ignored_at' => $waived ? $issue['decided_at'] : null,
                'ignored_by' => $waived ? 'larapilot' : null,
            ]);
        }

        foreach (['snoozed' => 'open', 'ignored' => 'ignored', 'closed' => 'resolved'] as $status => $list) {
            [$groups, $cut] = $this->groups($repository, $status);
            $truncated = $truncated || $cut;

            foreach ($groups as $group) {
                $decision = $decisions[(string) $group['id']] ?? [];
                $when = $dates[$group['id']] ?? [];

                $place($list, $this->decided($group, $decision) + match ($status) {
                    'snoozed' => ['status' => 'snoozed', 'snoozed_until' => $when['snoozed_until'] ?? null],
                    'ignored' => [
                        'status' => 'ignored',
                        'ignored_at' => $when['ignored_at'] ?? ($decision['sent_at'] ?? null),
                        'ignored_by' => ($decision['state'] ?? null) === AikidoLedger::WAIVED ? 'larapilot' : ($when['ignored_by'] ?? null),
                    ],
                    default => ['status' => 'resolved', 'closed_at' => $when['closed_at'] ?? null],
                });
            }
        }

        // Decided here, and in none of the lists of Aikido: the decision is
        // what is left of the finding.
        foreach ($decisions as $id => $decision) {
            $waived = ($decision['state'] ?? null) === AikidoLedger::WAIVED;
            $severity = strtolower((string) ($decision['severity'] ?? 'low'));
            $type = (string) ($decision['type'] ?? '');

            $place($waived ? 'ignored' : 'resolved', [
                'id' => (int) $id,
                'title' => (string) ($decision['title'] ?? 'Finding '.$id),
                'type' => $type,
                'type_label' => self::TYPES[$type] ?? ucfirst(str_replace('_', ' ', $type)),
                'severity' => in_array($severity, self::SEVERITIES, true) ? $severity : 'low',
                'score' => 0,
                'cves' => [],
                'where' => [],
                'first_detected_at' => $decision['first_seen'] ?? null,
                'state' => $waived ? AikidoLedger::WAIVED : AikidoLedger::IN_BACKLOG,
                'spec' => $waived ? null : ($decision['spec'] ?? null),
                'spec_status' => null,
                'reason' => $waived ? (string) ($decision['reason'] ?? '') : null,
                'decided_at' => $decision['at'] ?? null,
                'status' => $waived ? 'ignored' : 'resolved',
                'ignored_at' => $waived ? ($decision['at'] ?? null) : null,
                'ignored_by' => $waived ? 'larapilot' : null,
                'closed_at' => null,
            ]);
        }

        $counts = [];

        foreach ($lists as $list => $entries) {
            usort($entries, $this->bySeverity());
            $lists[$list] = $entries;
            $counts[$list] = $this->counts($entries);
        }

        $register = [
            'repository' => $findings['repository'],
            'generated_at' => now()->toIso8601String(),
            'truncated' => $truncated,
            'counts' => $counts,
        ] + $lists;

        Cache::put($key, $register, max(30, (int) config('larapilot.aikido.cache_seconds', 300)));

        return $register;
    }

    /**
     * When the issues of each finding were closed, ignored, or snoozed, in
     * this repository. The list of findings carries no date but the first.
     *
     * @return array<int, array{closed_at: string|null, ignored_at: string|null, ignored_by: string|null, snoozed_until: string|null}>
     */
    protected function dates(int $repository): array
    {
        $dates = [];
        $iso = static fn (int $stamp): ?string => $stamp > 0 ? Carbon::createFromTimestamp($stamp)->toIso8601String() : null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $batch = $this->client->get('/issues/export', [
                'filter_code_repo_id' => $repository,
                'filter_status' => 'all',
                'page' => $page,
                'per_page' => self::EXPORT_PAGE,
            ]);

            foreach ($batch as $issue) {
                if (! is_array($issue) || ! isset($issue['group_id'])) {
                    continue;
                }

                $group = (int) $issue['group_id'];
                $seen = $dates[$group] ?? ['closed' => 0, 'ignored' => 0, 'by' => null, 'snoozed' => 0];

                $seen['closed'] = max($seen['closed'], (int) ($issue['closed_at'] ?? 0));
                $seen['snoozed'] = max($seen['snoozed'], (int) ($issue['snooze_until'] ?? 0));

                if ((int) ($issue['ignored_at'] ?? 0) >= $seen['ignored'] && (int) ($issue['ignored_at'] ?? 0) > 0) {
                    $seen['ignored'] = (int) $issue['ignored_at'];
                    $seen['by'] = is_string($issue['ignored_by'] ?? null) ? $issue['ignored_by'] : null;
                }

                $dates[$group] = $seen;
            }

            if (count($batch) < self::EXPORT_PAGE) {
                break;
            }
        }

        return array_map(static fn (array $seen): array => [
            'closed_at' => $iso($seen['closed']),
            'ignored_at' => $iso($seen['ignored']),
            'ignored_by' => $seen['by'],
            'snoozed_until' => $iso($seen['snoozed']),
        ], $dates);
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    protected function tell(array $ids, bool $push): array
    {
        if (! $push || ! $this->pushesDecisions()) {
            return [];
        }

        $told = [];

        foreach ($ids as $id) {
            $told[] = $result = $this->transmit($id);

            // What refused this one refuses the next: the rest is left for `aikido-push`.
            if (! $result['sent'] && in_array($result['status'] ?? -1, self::STOPS, true)) {
                break;
            }
        }

        return $told;
    }

    /**
     * Tell Aikido what was decided about one finding. Aikido refusing, or
     * being away, undoes nothing: the decision stays in the ledger as not
     * told, and `aikido-push` tells it later.
     *
     * @return array<string, mixed>
     */
    protected function transmit(int $id): array
    {
        $decision = $this->ledger->find($id) ?? [];

        try {
            return match ($decision['state'] ?? null) {
                AikidoLedger::WAIVED => $this->ignore($id, $decision),
                AikidoLedger::IN_BACKLOG => $this->note($id, $decision),
                default => ['id' => $id, 'sent' => false, 'error' => 'Nothing was decided about this finding.'],
            };
        } catch (AikidoException $e) {
            return $this->refused($id, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function refused(int $id, AikidoException $e): array
    {
        return [
            'id' => $id,
            'sent' => false,
            'error' => $e->getMessage(),
            'hint' => $e->status() === 403
                ? 'Give the Aikido credentials the issues:write scope, then tell Aikido with: php artisan larapilot:aikido-push'
                : trim(($e->hint() ?? '').' The decision is kept. Tell Aikido later with: php artisan larapilot:aikido-push'),
            'status' => $e->status(),
        ];
    }

    /**
     * Ignore in Aikido the issues of a finding that are in this repository,
     * with the reason of the waiver. A finding can be in several
     * repositories of the workspace: when it is, each issue of this one is
     * ignored by itself, and the others stay open for their project.
     *
     * @param  array<string, mixed>  $decision
     * @return array<string, mixed>
     */
    protected function ignore(int $id, array $decision): array
    {
        $repository = (int) $this->cached()['repository']['id'];
        $reason = (string) ($decision['reason'] ?? '');
        $here = [];
        $elsewhere = 0;

        foreach ($this->client->get('/issues/export', ['filter_issue_group_id' => $id, 'filter_status' => 'open', 'per_page' => self::EXPORT_PAGE]) as $issue) {
            if (! is_array($issue) || ! isset($issue['id']) || (int) ($issue['group_id'] ?? $id) !== $id) {
                continue;
            }

            if ((int) ($issue['code_repo_id'] ?? 0) === $repository) {
                $here[] = (int) $issue['id'];
            } else {
                $elsewhere++;
            }
        }

        if ($here === []) {
            return ['id' => $id, 'sent' => false, 'error' => "Aikido lists no open issue of finding #{$id} in this repository: nothing was ignored."];
        }

        if ($elsewhere === 0) {
            $this->client->put('/issues/groups/'.$id.'/ignore', ['reason' => $reason]);
            $this->ledger->annotate($id, ['sent' => 'ignored', 'sent_group' => true, 'sent_issues' => null, 'sent_spec' => null, 'sent_at' => now()->toIso8601String()]);
        } else {
            $done = array_map('intval', is_array($decision['sent_issues'] ?? null) ? $decision['sent_issues'] : []);

            try {
                foreach ($here as $issue) {
                    $this->client->put('/issues/'.$issue.'/ignore', ['reason' => $reason]);
                    $done[] = $issue;
                }
            } finally {
                // Kept even when a call in the middle fails: taking the
                // waiver back has to find every issue that was ignored.
                $this->ledger->annotate($id, ['sent_issues' => array_values(array_unique($done))]);
            }

            $this->ledger->annotate($id, ['sent' => 'ignored', 'sent_group' => null, 'sent_spec' => null, 'sent_at' => now()->toIso8601String()]);
        }

        $this->closeInCache($id);

        return ['id' => $id, 'sent' => true, 'action' => 'ignored', 'issues' => count($here), 'whole_finding' => $elsewhere === 0];
    }

    /**
     * Leave a note on the finding in Aikido: the spec that fixes it here.
     *
     * @param  array<string, mixed>  $decision
     * @return array<string, mixed>
     */
    protected function note(int $id, array $decision): array
    {
        $spec = (string) ($decision['spec'] ?? '');
        $title = trim((string) ($this->specs->find($spec)['title'] ?? ''));
        $repository = (string) $this->cached()['repository']['name'];

        $this->client->post('/issues/groups/'.$id.'/notes', [], [
            'note' => 'Larapilot: the fix'.($repository !== '' ? ' for '.$repository : '').' is in the backlog as '.$spec.($title !== '' ? ' — '.$title : '').'.',
        ]);

        $this->ledger->annotate($id, ['sent' => 'noted', 'sent_spec' => $spec, 'sent_group' => null, 'sent_issues' => null, 'sent_at' => now()->toIso8601String()]);

        return ['id' => $id, 'sent' => true, 'action' => 'noted', 'spec' => $spec];
    }

    /**
     * Take back in Aikido what a waiver ignored there.
     *
     * @param  array<string, mixed>  $decision
     * @return array<string, mixed>
     */
    protected function unignore(int $id, array $decision): array
    {
        $reason = 'The waiver was taken back in Larapilot.';

        if (($decision['sent_group'] ?? false) === true) {
            $this->client->put('/issues/groups/'.$id.'/unignore', ['reason' => $reason]);
        } else {
            foreach (is_array($decision['sent_issues'] ?? null) ? $decision['sent_issues'] : [] as $issue) {
                $this->client->put('/issues/'.(int) $issue.'/unignore', ['reason' => $reason]);
            }
        }

        // What was read says nothing about this finding any more.
        Cache::forget($this->cacheKey());

        return ['id' => $id, 'sent' => true, 'action' => 'unignored'];
    }

    /**
     * Whether Aikido was told the decision as it stands now.
     *
     * @param  array<string, mixed>  $decision
     */
    protected function told(array $decision): bool
    {
        return match ($decision['state'] ?? null) {
            AikidoLedger::WAIVED => ($decision['sent'] ?? null) === 'ignored',
            AikidoLedger::IN_BACKLOG => ($decision['sent'] ?? null) === 'noted' && ($decision['sent_spec'] ?? null) === ($decision['spec'] ?? ''),
            default => true,
        };
    }

    /**
     * A finding ignored in Aikido is not open any more: it leaves what was
     * read, so the next decision does not ask Aikido for the whole list.
     */
    protected function closeInCache(int $id): void
    {
        $cached = Cache::get($this->cacheKey());

        if (! is_array($cached) || ! is_array($cached['issues'] ?? null)) {
            return;
        }

        $cached['issues'] = array_values(array_filter($cached['issues'], static fn (array $issue): bool => (int) $issue['id'] !== $id));

        Cache::put($this->cacheKey(), $cached, max(30, (int) config('larapilot.aikido.cache_seconds', 300)));
        Cache::forget($this->cacheKey().'.register');
    }

    public function failOn(): string
    {
        $value = strtolower(trim((string) config('larapilot.aikido.fail_on', 'high')));

        return in_array($value, array_merge(self::SEVERITIES, ['none']), true) ? $value : 'high';
    }

    /**
     * The repository of the workspace this project is: the one named in
     * `.env`, the one the user chose, or the one whose address or name is
     * the remote's.
     *
     * @return array<string, mixed>|null
     */
    public function repository(): ?array
    {
        $wanted = $this->wanted();
        $slug = (string) $this->git->originRepoSlug();
        $short = $slug !== '' ? (string) substr((string) strrchr('/'.$slug, '/'), 1) : '';
        $candidates = [];

        foreach (array_unique(array_filter([ctype_digit($wanted) ? '' : $wanted, $short])) as $name) {
            foreach ($this->client->get('/repositories/code', ['filter_name' => $name, 'per_page' => 50, 'include_inactive' => 'false']) as $repository) {
                if (is_array($repository) && isset($repository['id'])) {
                    $candidates[(int) $repository['id']] = $repository;
                }
            }
        }

        if (ctype_digit($wanted) && ! isset($candidates[(int) $wanted])) {
            $found = $this->byId((int) $wanted);

            if ($found !== null) {
                $candidates[(int) $found['id']] = $found;
            }
        }

        foreach ($candidates as $repository) {
            if ($this->isThisProject($repository)) {
                return $this->repositoryRow($repository);
            }
        }

        return null;
    }

    /**
     * Where the repository comes from: `env` when `.env` names it, `chosen`
     * when the user named it, `remote` when the git remote found it.
     */
    public function repositorySource(): string
    {
        return match (true) {
            $this->named() !== '' => 'env',
            $this->ledger->chosenRepository() !== null => 'chosen',
            default => 'remote',
        };
    }

    /**
     * The repositories of the workspace, by name, for the user to say
     * which one this project is.
     *
     * @return array<string, mixed>
     */
    public function repositories(string $search = ''): array
    {
        $rows = [];
        $truncated = false;

        for ($page = 0; $page < self::REPOSITORY_PAGES; $page++) {
            $batch = $this->client->get('/repositories/code', [
                'filter_name' => trim($search),
                'page' => $page,
                'per_page' => self::REPOSITORY_PAGE,
                'include_inactive' => 'false',
            ]);

            foreach ($batch as $repository) {
                if (is_array($repository) && isset($repository['id'])) {
                    $rows[(int) $repository['id']] = $this->repositoryRow($repository) + ['current' => $this->isThisProject($repository)];
                }
            }

            if (count($batch) < self::REPOSITORY_PAGE) {
                break;
            }

            $truncated = $page === self::REPOSITORY_PAGES - 1;
        }

        $rows = array_values($rows);

        usort($rows, static fn (array $a, array $b): int => [strtolower($a['name']), $a['id']] <=> [strtolower($b['name']), $b['id']]);

        return [
            'repositories' => $rows,
            'total' => count($rows),
            'truncated' => $truncated,
            'search' => trim($search) !== '' ? trim($search) : null,
            'origin' => $this->git->originUrl(),
            'chosen' => $this->ledger->chosenRepository(),
            'named_in_env' => $this->named() !== '' ? $this->named() : null,
            'ledger' => $this->ledger->relativePath(),
        ];
    }

    /**
     * Keep the repository of the workspace the user says this project is,
     * named by its id or by its exact name.
     *
     * @return array<string, mixed>
     */
    public function useRepository(string $named): array
    {
        $named = trim($named);

        if ($named === '') {
            throw new InvalidArgumentException('Name the repository by its id or its name in Aikido.');
        }

        if (ctype_digit($named)) {
            $found = $this->byId((int) $named);
            $matches = $found !== null ? [$found] : [];
        } else {
            $matches = array_values(array_filter(
                $this->client->get('/repositories/code', ['filter_name' => $named, 'per_page' => 50, 'include_inactive' => 'false']),
                static fn (mixed $repository): bool => is_array($repository) && isset($repository['id']) && strtolower((string) ($repository['name'] ?? '')) === strtolower($named)
            ));
        }

        if ($matches === []) {
            throw new InvalidArgumentException("Aikido holds no repository “{$named}”. List them with: php artisan larapilot:aikido-repos");
        }

        if (count($matches) > 1) {
            throw new InvalidArgumentException("More than one repository of the workspace is named “{$named}” (ids ".implode(', ', array_column($matches, 'id')).'): name it by its id.');
        }

        $repository = $this->repositoryRow($matches[0]);
        $this->ledger->chooseRepository(['id' => $repository['id'], 'name' => $repository['name']]);

        return $repository;
    }

    /**
     * Drop the choice: the repository is found from the git remote again.
     */
    public function forgetRepository(): void
    {
        $this->ledger->chooseRepository(null);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function byId(int $id): ?array
    {
        try {
            $found = $this->client->get('/repositories/code/'.$id);
        } catch (AikidoException $e) {
            // A repository that left the workspace is one that is not found.
            if ($e->status() !== 404) {
                throw $e;
            }

            return null;
        }

        return isset($found['id']) ? $found : null;
    }

    /**
     * @param  array<array-key, mixed>  $repository
     */
    protected function isThisProject(array $repository): bool
    {
        $wanted = $this->wanted();
        $slug = (string) $this->git->originRepoSlug();
        $short = $slug !== '' ? (string) substr((string) strrchr('/'.$slug, '/'), 1) : '';
        $origin = $this->address((string) $this->git->originUrl());
        $name = strtolower((string) ($repository['name'] ?? ''));

        return match (true) {
            ctype_digit($wanted) => (int) $repository['id'] === (int) $wanted,
            $wanted !== '' => $name === strtolower($wanted),
            $origin !== '' && $this->address((string) ($repository['url'] ?? '')) === $origin => true,
            default => $slug !== '' && in_array($name, [strtolower($slug), strtolower($short)], true),
        };
    }

    /**
     * @param  array<array-key, mixed>  $repository
     * @return array{id: int, name: string, provider: string, branch: string, url: string, connectivity: string, last_scanned_at: string|null}
     */
    protected function repositoryRow(array $repository): array
    {
        $scanned = (int) ($repository['last_scanned_at'] ?? -1);

        return [
            'id' => (int) $repository['id'],
            'name' => (string) ($repository['name'] ?? ''),
            'provider' => (string) ($repository['provider'] ?? ''),
            'branch' => (string) ($repository['branch'] ?? ''),
            'url' => (string) ($repository['url'] ?? ''),
            'connectivity' => (string) ($repository['connectivity'] ?? 'unknown'),
            'last_scanned_at' => $scanned > 0 ? Carbon::createFromTimestamp($scanned)->toIso8601String() : null,
        ];
    }

    /**
     * @return array{repository: array<string, mixed>, issues: list<array<string, mixed>>, fetched_at: string, truncated: bool}
     */
    protected function download(): array
    {
        $repository = $this->repository();

        if ($repository === null) {
            throw new AikidoException('No repository of the Aikido workspace matches this project.', self::NAME_THE_REPOSITORY);
        }

        [$issues, $truncated] = $this->groups((int) $repository['id']);

        $data = [
            'repository' => $repository,
            'issues' => $issues,
            'fetched_at' => now()->toIso8601String(),
            'truncated' => $truncated,
        ];

        Cache::put($this->cacheKey(), $data, max(30, (int) config('larapilot.aikido.cache_seconds', 300)));

        return $data;
    }

    /**
     * The findings of the repository in one state, the most severe first.
     * Aikido answers the open ones unless it is asked for another state.
     *
     * @param  'open'|'closed'|'ignored'|'snoozed'  $status
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    protected function groups(int $repository, string $status = 'open'): array
    {
        $issues = [];
        $truncated = false;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $batch = $this->client->get('/open-issue-groups', [
                'filter_code_repo_id' => $repository,
                'filter_status' => $status === 'open' ? null : $status,
                'page' => $page,
                'per_page' => self::PAGE,
            ]);

            foreach ($batch as $issue) {
                if (is_array($issue) && isset($issue['id'])) {
                    $issues[(int) $issue['id']] = $this->normalize($issue);
                }
            }

            if (count($batch) < self::PAGE) {
                break;
            }

            $truncated = $page === self::MAX_PAGES - 1;
        }

        $issues = array_values($issues);
        usort($issues, $this->bySeverity());

        return [$issues, $truncated];
    }

    /**
     * @return \Closure(array<string, mixed>, array<string, mixed>): int
     */
    protected function bySeverity(): \Closure
    {
        $rank = array_flip(self::SEVERITIES);

        return static fn (array $a, array $b): int => [$rank[$a['severity']], -$a['score'], $a['id']] <=> [$rank[$b['severity']], -$b['score'], $b['id']];
    }

    /**
     * @return array{repository: array<string, mixed>, issues: list<array<string, mixed>>, fetched_at: string, truncated: bool}
     */
    protected function cached(): array
    {
        $cached = Cache::get($this->cacheKey());

        return is_array($cached) && isset($cached['issues'], $cached['repository'], $cached['fetched_at'])
            ? $cached
            : $this->download();
    }

    /**
     * @param  array<string, mixed>  $issue
     * @return array<string, mixed>
     */
    protected function normalize(array $issue): array
    {
        $severity = strtolower((string) ($issue['severity'] ?? 'low'));
        $type = (string) ($issue['type'] ?? '');
        $where = [];

        foreach (is_array($issue['locations'] ?? null) ? $issue['locations'] : [] as $location) {
            if (is_array($location) && trim((string) ($location['name'] ?? '')) !== '') {
                $where[] = trim((string) $location['name']);
            }
        }

        $detected = (int) ($issue['first_detected_at'] ?? 0);

        return [
            'id' => (int) $issue['id'],
            'title' => trim((string) ($issue['title'] ?? 'Finding '.$issue['id'])),
            'description' => trim((string) ($issue['description'] ?? '')),
            'type' => $type,
            'type_label' => self::TYPES[$type] ?? ucfirst(str_replace('_', ' ', $type)),
            'severity' => in_array($severity, self::SEVERITIES, true) ? $severity : 'low',
            'score' => (int) ($issue['severity_score'] ?? $issue['priority'] ?? 0),
            'status' => (string) ($issue['group_status'] ?? ''),
            'minutes_to_fix' => (int) ($issue['time_to_fix_minutes'] ?? 0),
            'where' => array_values(array_unique($where)),
            'how_to_fix' => trim((string) ($issue['how_to_fix'] ?? '')),
            'cves' => array_values(array_unique(array_filter(array_map('strval', is_array($issue['related_cve_ids'] ?? null) ? $issue['related_cve_ids'] : [])))),
            'first_detected_at' => $detected > 0 ? Carbon::createFromTimestamp($detected)->toIso8601String() : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $issue
     * @param  array<string, mixed>|null  $decision
     * @return array<string, mixed>
     */
    protected function decided(array $issue, ?array $decision): array
    {
        $state = in_array($decision['state'] ?? null, [AikidoLedger::IN_BACKLOG, AikidoLedger::WAIVED], true)
            ? (string) $decision['state']
            : 'new';
        $spec = $state === AikidoLedger::IN_BACKLOG ? (string) ($decision['spec'] ?? '') : '';
        $found = $spec !== '' ? $this->specs->find($spec) : null;

        // A spec deleted from the backlog decides nothing any more.
        if ($state === AikidoLedger::IN_BACKLOG && $found === null) {
            $state = 'new';
            $spec = '';
        }

        return array_merge($issue, [
            'state' => $state,
            'spec' => $spec !== '' ? $spec : null,
            'spec_status' => $found !== null ? (string) ($found['status'] ?? '') : null,
            'reason' => $state === AikidoLedger::WAIVED ? (string) ($decision['reason'] ?? '') : null,
            'decided_at' => $state !== 'new' ? ($decision['at'] ?? null) : null,
        ]);
    }

    /**
     * What the ship gate makes of the findings. A finding that is waived
     * was decided about. One that is in the backlog is still open: it stops
     * the gate until Aikido no longer reports it.
     *
     * @param  list<array<string, mixed>>  $issues
     * @return array<string, mixed>
     */
    protected function gate(array $issues): array
    {
        $threshold = $this->failOn();
        $rank = array_flip(self::SEVERITIES);
        $standing = array_values(array_filter($issues, static fn (array $issue): bool => $issue['state'] !== AikidoLedger::WAIVED));
        $blocking = $threshold === 'none'
            ? []
            : array_values(array_filter($standing, static fn (array $issue): bool => $rank[$issue['severity']] <= $rank[$threshold]));

        $verdict = match (true) {
            $blocking !== [] => 'FAIL',
            $standing !== [] => 'WARN',
            default => 'PASS',
        };

        $undecided = count(array_filter($blocking, static fn (array $issue): bool => $issue['state'] === 'new'));
        $count = count($blocking);

        $summary = match ($verdict) {
            'FAIL' => $count.' open '.($count === 1 ? 'finding is' : 'findings are').' '.$threshold.' or above'
                .($undecided > 0 ? ': '.$undecided.' with no decision yet' : ', all in the backlog and not fixed yet').'.',
            'WARN' => count($standing).' open '.(count($standing) === 1 ? 'finding' : 'findings').' below '.$threshold.'.',
            default => $issues === [] ? 'Aikido reports nothing open.' : 'Every open finding was waived with a reason.',
        };

        return [
            'verdict' => $verdict,
            'fail_on' => $threshold,
            'summary' => $summary,
            'blocking' => array_column($blocking, 'id'),
            'undecided' => $undecided,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     * @return array<string, int>
     */
    protected function counts(array $issues): array
    {
        $counts = array_fill_keys(self::SEVERITIES, 0);

        foreach ($issues as $issue) {
            $counts[$issue['severity']]++;
        }

        $counts['all'] = count($issues);

        return $counts;
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     * @param  array{severity?: string|null, type?: string|null, new?: bool, limit?: int|null}  $filters
     * @return list<array<string, mixed>>
     */
    protected function filter(array $issues, array $filters): array
    {
        $rank = array_flip(self::SEVERITIES);
        $severity = strtolower(trim((string) ($filters['severity'] ?? '')));
        $type = strtolower(trim((string) ($filters['type'] ?? '')));

        if ($severity !== '' && ! isset($rank[$severity])) {
            throw new InvalidArgumentException('Unknown severity "'.$severity.'". Use one of: '.implode(', ', self::SEVERITIES).'.');
        }

        $issues = array_filter($issues, static function (array $issue) use ($filters, $rank, $severity, $type): bool {
            if ($severity !== '' && $rank[$issue['severity']] > $rank[$severity]) {
                return false;
            }

            if ($type !== '' && $issue['type'] !== $type) {
                return false;
            }

            return empty($filters['new']) || $issue['state'] === 'new';
        });

        $limit = (int) ($filters['limit'] ?? 0);

        return array_values($limit > 0 ? array_slice($issues, 0, $limit) : $issues);
    }

    /**
     * The open findings named, by id. A decision is about a finding Aikido
     * reports: a number it does not know is a slip of the hand, and would
     * later read as a finding that was fixed.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    protected function known(array $ids): array
    {
        $index = static function (array $data): array {
            $known = [];

            foreach ($data['issues'] as $issue) {
                $known[(int) $issue['id']] = $issue;
            }

            return $known;
        };

        $known = $index($this->cached());

        // What was read a few minutes ago may not hold a finding of this
        // morning: before refusing a number, Aikido is asked again.
        if (array_diff($ids, array_keys($known)) !== []) {
            $known = $index($this->download());
        }

        foreach ($ids as $id) {
            if (! isset($known[$id])) {
                throw new InvalidArgumentException("Aikido reports no open finding #{$id} for this repository.");
            }
        }

        return $known;
    }

    /**
     * @return array<string, mixed>
     */
    protected function repositoryRef(): array
    {

        $cached = Cache::get($this->cacheKey());
        $repository = is_array($cached['repository'] ?? null) ? $cached['repository'] : [];

        return $repository === [] ? [] : ['id' => $repository['id'], 'name' => $repository['name']];
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    protected function ids(array $ids): array
    {
        $clean = [];

        foreach ($ids as $id) {
            if (! is_numeric($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A finding is named by its number in Aikido, such as 24.');
            }

            $clean[] = (int) $id;
        }

        if ($clean === []) {
            throw new InvalidArgumentException('Name at least one finding by its number in Aikido.');
        }

        return array_values(array_unique($clean));
    }

    /**
     * The repository to look for: the one `.env` names, by id or by name,
     * or the id of the one the user chose. Empty: the git remote decides.
     */
    protected function wanted(): string
    {
        if ($this->named() !== '') {
            return $this->named();
        }

        $chosen = $this->ledger->chosenRepository();

        return $chosen !== null ? (string) $chosen['id'] : '';
    }

    protected function named(): string
    {
        return trim((string) config('larapilot.aikido.repository', ''));
    }

    /**
     * An address reduced to host and path, so the same repository reads the
     * same over https and over ssh.
     */
    protected function address(string $url): string
    {
        $url = strtolower(trim($url));

        if ($url === '') {
            return '';
        }

        $url = (string) preg_replace('#^(?:https?://|ssh://)?(?:[^@/]+@)?#', '', $url);
        $url = str_replace(':', '/', $url);

        return trim((string) preg_replace('#\.git$#', '', $url), '/');
    }

    protected function inline(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    protected function cacheKey(): string
    {
        return 'larapilot.aikido.findings.'.sha1($this->client->host().'|'.$this->config->projectRoot().'|'.$this->wanted());
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     * @return list<array<string, mixed>>
     */
    protected function resolutionGroups(array $issues): array
    {
        $buckets = [];

        foreach ($issues as $issue) {
            $buckets[$this->resolutionSignature($issue)][] = $issue;
        }

        $rank = array_flip(self::SEVERITIES);
        $groups = [];

        foreach ($buckets as $signature => $of) {
            usort($of, static fn (array $a, array $b): int => [$rank[$a['severity']], -$a['score'], $a['id']] <=> [$rank[$b['severity']], -$b['score'], $b['id']]);

            $lead = $of[0];
            $ids = array_column($of, 'id');
            $cves = [];
            $where = [];

            foreach ($of as $issue) {
                $cves = [...$cves, ...$issue['cves']];
                $where = [...$where, ...$issue['where']];
            }

            $cves = array_values(array_unique($cves));
            $where = array_values(array_unique($where));
            $title = count($of) === 1
                ? (string) $lead['title']
                : (string) $lead['title'].' (+'.(count($of) - 1).' more)';

            $groups[] = [
                'key' => substr(sha1($signature), 0, 8),
                'ids' => $ids,
                'type' => (string) $lead['type'],
                'type_label' => (string) $lead['type_label'],
                'severity' => (string) $lead['severity'],
                'score' => (int) $lead['score'],
                'title' => $title,
                'where' => $where,
                'cves' => $cves,
                'how_to_fix' => (string) $lead['how_to_fix'],
                'reason' => count($of) === 1
                    ? 'One finding — not merged with others.'
                    : 'Same kind ('.$lead['type_label'].') and the same fix — one triage handoff avoids duplicate specs.',
            ];
        }

        usort($groups, static fn (array $a, array $b): int => [$rank[$a['severity']], -$a['score'], min($a['ids'])] <=> [$rank[$b['severity']], -$b['score'], min($b['ids'])]);

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    protected function resolutionSignature(array $issue): string
    {
        $type = (string) $issue['type'];

        if ($type === 'leaked_secret') {
            return 'leaked_secret|'.$issue['id'];
        }

        $fix = $this->resolutionKey((string) $issue['how_to_fix']);
        $title = $this->resolutionKey((string) $issue['title']);

        return $type.'|'.$title.'|'.$fix;
    }

    protected function resolutionKey(string $text): string
    {
        $text = strtolower(trim($text));

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
