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
                    $hints[] = 'No repository of the Aikido workspace matches this project'.($this->wanted() !== '' ? ' ("'.$this->wanted().'")' : '').'. Connect the repository in Aikido, or name it with LARAPILOT_AIKIDO_REPOSITORY (its id or its name).';
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
            'origin' => $this->git->originUrl(),
            'fail_on' => $this->failOn(),
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
            'ledger' => $this->ledger->relativePath(),
        ];
    }

    /**
     * Record that findings are in the backlog as a spec.
     *
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    public function link(array $ids, string $spec): array
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
            ], static fn (mixed $value): bool => $value !== null), $this->repositoryRef());
        }

        return ['issues' => $ids, 'state' => AikidoLedger::IN_BACKLOG, 'spec' => (string) $found['code'], 'ledger' => $this->ledger->relativePath()];
    }

    /**
     * Record that findings are accepted as they are, and why.
     *
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    public function waive(array $ids, string $reason): array
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
            ], static fn (mixed $value): bool => $value !== null), $this->repositoryRef());
        }

        return ['issues' => $ids, 'state' => AikidoLedger::WAIVED, 'reason' => $reason, 'ledger' => $this->ledger->relativePath()];
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    public function forget(array $ids): array
    {
        $ids = $this->ids($ids);
        $this->ledger->forget($ids);

        return ['issues' => $ids, 'state' => 'new', 'ledger' => $this->ledger->relativePath()];
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
            throw new AikidoException(
                'No repository of the Aikido workspace matches this project.',
                'Connect the repository in Aikido, or name it with LARAPILOT_AIKIDO_REPOSITORY.'
            );
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
                    .(($closed['state'] ?? '') === AikidoLedger::WAIVED ? 'waived' : 'fixed by '.($closed['spec'] ?? '—')).' |';
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

    public function failOn(): string
    {
        $value = strtolower(trim((string) config('larapilot.aikido.fail_on', 'high')));

        return in_array($value, array_merge(self::SEVERITIES, ['none']), true) ? $value : 'high';
    }

    /**
     * The repository of the workspace this project is: the one named in
     * the configuration, or the one whose address or name is the remote's.
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
            $found = $this->client->get('/repositories/code/'.$wanted);

            if (isset($found['id'])) {
                $candidates[(int) $found['id']] = $found;
            }
        }

        $origin = $this->address((string) $this->git->originUrl());
        $picked = null;

        foreach ($candidates as $repository) {
            $name = strtolower((string) ($repository['name'] ?? ''));

            $match = match (true) {
                ctype_digit($wanted) => (int) $repository['id'] === (int) $wanted,
                $wanted !== '' => $name === strtolower($wanted),
                $origin !== '' && $this->address((string) ($repository['url'] ?? '')) === $origin => true,
                default => $slug !== '' && in_array($name, [strtolower($slug), strtolower($short)], true),
            };

            if ($match) {
                $picked = $repository;
                break;
            }
        }

        if ($picked === null) {
            return null;
        }

        $scanned = (int) ($picked['last_scanned_at'] ?? -1);

        return [
            'id' => (int) $picked['id'],
            'name' => (string) ($picked['name'] ?? ''),
            'provider' => (string) ($picked['provider'] ?? ''),
            'branch' => (string) ($picked['branch'] ?? ''),
            'url' => (string) ($picked['url'] ?? ''),
            'connectivity' => (string) ($picked['connectivity'] ?? 'unknown'),
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
            throw new AikidoException(
                'No repository of the Aikido workspace matches this project.',
                'Connect the repository in Aikido, or name it with LARAPILOT_AIKIDO_REPOSITORY (its id or its name).'
            );
        }

        $issues = [];
        $truncated = false;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $batch = $this->client->get('/open-issue-groups', [
                'filter_code_repo_id' => $repository['id'],
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
        $rank = array_flip(self::SEVERITIES);

        usort($issues, static fn (array $a, array $b): int => [$rank[$a['severity']], -$a['score'], $a['id']] <=> [$rank[$b['severity']], -$b['score'], $b['id']]);

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

    protected function wanted(): string
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
}
