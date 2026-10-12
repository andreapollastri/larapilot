<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Larapilot\Services\Hackly\HacklyClient;
use Larapilot\Services\Hackly\HacklyException;
use Larapilot\Services\Hackly\HacklyLedger;
use Larapilot\Support\AtomicFile;

/**
 * The findings of Hackly for this repository — and for the domains linked
 * to it there — set against the backlog.
 *
 * Hackly (https://hackly.web.ap.it) is the team's own scanner: SAST and SCA
 * on a clone of the repository (Semgrep, Trivy, Gitleaks, Composer audit,
 * Checkpoint, Laravel checks), DAST on the verified domains (TLS, headers,
 * exposed paths, Nuclei, ZAP). Larapilot runs none of it: it reads what
 * Hackly found, says what was decided about each finding, and gives the
 * ship gate a verdict scoped to this project — Hackly's own CI gate looks
 * at the whole workspace.
 *
 * A decision taken here is told to Hackly, so both say the same: a spec or
 * a waiver acknowledges the finding there, a false positive is marked so
 * with its reason, and forgetting a decision opens the finding again.
 */
class HacklyService
{
    /**
     * From the most to the least severe: Hackly normalizes to three.
     *
     * @var list<string>
     */
    public const SEVERITIES = ['high', 'medium', 'low'];

    /**
     * Hackly's own states of a finding.
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        'open' => 'Open',
        'ack' => 'Acknowledged',
        'fixed' => 'Fixed',
        'false_positive' => 'False positive',
    ];

    /**
     * The scan profiles a repository scan takes.
     *
     * @var list<string>
     */
    public const PROFILES = ['quick', 'standard', 'deep'];

    protected const PER_PAGE = 100;

    protected const MAX_PAGES = 10;

    protected const NAME_THE_REPOSITORY = 'Say which repository of Hackly this project is: php artisan larapilot:security-repos lists them, and php artisan larapilot:security-repos --use={id} keeps the choice in .larapilot/hackly.yaml. LARAPILOT_HACKLY_REPOSITORY in .env names it for one machine only. A repository that is not in the list has to be added in Hackly first (Repositories → New, with a GitHub token that reads it).';

    public function __construct(
        protected ConfigService $config,
        protected GitService $git,
        protected SpecService $specs,
        protected HacklyClient $client,
        protected HacklyLedger $ledger,
    ) {}

    /**
     * Whether Hackly can be read, and what is missing when it cannot.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $enabled = $this->config->hacklyEnabled();
        $configured = $this->client->configured();
        $hints = [];
        $repository = null;
        $authenticated = false;
        $user = null;
        $error = null;

        if (! $enabled) {
            $hints[] = 'Enable with: php artisan larapilot:settings-set --security-provider=hackly';
        }

        if (! $configured) {
            $hints[] = 'Set LARAPILOT_HACKLY_URL (the address of your Hackly, such as https://hackly.example.com — not the documentation site) and LARAPILOT_HACKLY_TOKEN (Profile → API tokens in Hackly, or php artisan hackly:token you@example.com --ability=write) in .env.';
        } elseif ($this->client->pointsAtTheDocs()) {
            $hints[] = 'LARAPILOT_HACKLY_URL points at hackly.web.ap.it, the documentation of Hackly. Hackly is self-hosted: set the address of your own instance.';
        }

        if ($configured) {
            try {
                $me = $this->client->get('/me');
                $who = is_array($me['data'] ?? null) ? $me['data'] : $me;
                $user = trim((string) ($who['email'] ?? $who['name'] ?? ($who['user']['email'] ?? '')));
                $authenticated = true;
                $repository = $this->repository();

                if ($repository === null) {
                    $hints[] = 'No repository of Hackly matches this project'.($this->wanted() !== '' ? ' ("'.$this->wanted().'")' : '').'. '.self::NAME_THE_REPOSITORY;
                } elseif (($repository['last_scanned_at'] ?? null) === null) {
                    $hints[] = 'Hackly has not scanned this repository yet. Ask for a scan: php artisan larapilot:security-scan';
                }
            } catch (HacklyException $e) {
                $error = $e->getMessage();
                $authenticated = ! in_array($e->status(), [0, 401], true);
                $hints[] = trim($e->getMessage().' '.($e->hint() ?? ''));
            }
        }

        return [
            'provider' => 'hackly',
            'enabled' => $enabled,
            'configured' => $configured,
            'authenticated' => $authenticated,
            'host' => $this->client->host(),
            'user' => $user !== '' ? $user : null,
            'repository' => $repository,
            'repository_source' => $repository !== null ? $this->repositorySource() : null,
            'needs_repository' => $configured && $authenticated && $error === null && $repository === null,
            'origin' => $this->git->originUrl(),
            'fail_on' => $this->failOn(),
            'push_decisions' => $this->pushesDecisions(),
            'include_targets' => $this->includesTargets(),
            'ledger' => $this->ledger->relativePath(),
            'ready' => $enabled && $configured && $authenticated && $repository !== null,
            'error' => $error,
            'hints' => $hints,
        ];
    }

    /**
     * The open findings of the repository and of its linked domains, the
     * most severe first, each with what was decided about it, and the
     * verdict of the gate.
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
            $issues[] = $this->decided($issue, $decisions[$issue['id']] ?? null);
        }

        $open = array_column($issues, 'id');
        $closed = [];

        foreach ($decisions as $id => $decision) {
            if (! in_array((string) $id, $open, true)) {
                $closed[] = array_merge(['id' => (string) $id], $decision);
            }
        }

        $unsent = [];

        foreach ($issues as $issue) {
            if ($issue['state'] !== 'new' && ! $this->told($decisions[$issue['id']] ?? [])) {
                $unsent[] = $issue['id'];
            }
        }

        return [
            'provider' => 'hackly',
            'repository' => $data['repository'],
            'targets' => $data['targets'],
            'fetched_at' => $data['fetched_at'],
            'truncated' => $data['truncated'],
            'severities' => self::SEVERITIES,
            'counts' => $this->counts($issues),
            'states' => [
                'new' => count(array_filter($issues, static fn (array $issue): bool => $issue['state'] === 'new')),
                'in_backlog' => count(array_filter($issues, static fn (array $issue): bool => $issue['state'] === HacklyLedger::IN_BACKLOG)),
                'waived' => count(array_filter($issues, static fn (array $issue): bool => in_array($issue['state'], [HacklyLedger::WAIVED, HacklyLedger::FALSE_POSITIVE], true))),
            ],
            'gate' => $this->gate($issues),
            'total' => count($issues),
            'issues' => $this->filter($issues, $filters),
            'closed' => $closed,
            'unsent' => $this->pushesDecisions() ? $unsent : [],
            'push_decisions' => $this->pushesDecisions(),
            'ledger' => $this->ledger->relativePath(),
        ];
    }

    /**
     * One finding as Hackly writes it for a person, and as a prompt for an
     * AI to fix it — what triage hands over.
     *
     * @return array{id: string, markdown: string, prompt: string}
     */
    public function detail(string $id): array
    {
        $id = $this->ids([$id])[0];

        return [
            'id' => $id,
            'markdown' => trim($this->client->text('/findings/'.rawurlencode($id).'/markdown')),
            'prompt' => trim($this->client->text('/findings/'.rawurlencode($id).'/prompt')),
        ];
    }

    /**
     * Record that findings are in the backlog as a spec, and acknowledge
     * them in Hackly.
     *
     * @param  list<int|string>  $ids
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
            $this->ledger->record([$id], $this->facts($known[$id] ?? []) + ['state' => HacklyLedger::IN_BACKLOG, 'spec' => (string) $found['code']]);
        }

        $this->forgetRegister();

        return [
            'issues' => $ids,
            'state' => HacklyLedger::IN_BACKLOG,
            'spec' => (string) $found['code'],
            'ledger' => $this->ledger->relativePath(),
            'hackly' => $this->tell($ids, $push),
        ];
    }

    /**
     * Record that findings are accepted as they are, and why — or that they
     * are false positives — and tell Hackly.
     *
     * @param  list<int|string>  $ids
     * @return array<string, mixed>
     */
    public function waive(array $ids, string $reason, bool $push = true, bool $falsePositive = false): array
    {
        $ids = $this->ids($ids);
        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('Say why the finding is accepted, in a sentence: a waiver without a reason cannot be reviewed.');
        }

        if (mb_strlen($reason) > 2000) {
            throw new InvalidArgumentException('Keep the reason under 2000 characters: Hackly keeps no longer note.');
        }

        $known = $this->known($ids);
        $state = $falsePositive ? HacklyLedger::FALSE_POSITIVE : HacklyLedger::WAIVED;

        foreach ($ids as $id) {
            $this->ledger->record([$id], $this->facts($known[$id] ?? []) + ['state' => $state, 'reason' => $reason]);
        }

        $this->forgetRegister();

        return [
            'issues' => $ids,
            'state' => $state,
            'reason' => $reason,
            'ledger' => $this->ledger->relativePath(),
            'hackly' => $this->tell($ids, $push),
        ];
    }

    /**
     * Drop what was decided about findings, and open them again in Hackly
     * when Hackly was told.
     *
     * @param  list<int|string>  $ids
     * @return array<string, mixed>
     */
    public function forget(array $ids, bool $push = true): array
    {
        $ids = $this->ids($ids);
        $forgotten = [];
        $told = [];

        foreach ($ids as $id) {
            $decision = $this->ledger->find($id) ?? [];

            if ($push && $this->pushesDecisions() && in_array($decision['sent'] ?? null, ['ack', 'false_positive'], true)) {
                try {
                    $this->client->patch('/findings/'.rawurlencode($id), ['status' => 'open']);
                    $told[] = ['id' => $id, 'sent' => true, 'action' => 'opened'];
                } catch (HacklyException $e) {
                    $told[] = $this->refused($id, $e) + ['kept' => true];

                    continue;
                }
            }

            $forgotten[] = $id;
        }

        if ($forgotten !== []) {
            $this->ledger->forget($forgotten);
            Cache::forget($this->cacheKey());
            $this->forgetRegister();
        }

        return ['issues' => $forgotten, 'state' => 'new', 'ledger' => $this->ledger->relativePath(), 'hackly' => $told];
    }

    /**
     * Tell Hackly every decision about an open finding it was not told yet.
     *
     * @return array<string, mixed>
     */
    public function push(): array
    {
        $findings = $this->findings();
        $told = $this->tell($findings['unsent'], true);
        $sent = array_column(array_filter($told, static fn (array $result): bool => (bool) $result['sent']), 'id');

        return ['hackly' => $told, 'left' => array_values(array_diff($findings['unsent'], $sent)), 'ledger' => $this->ledger->relativePath()];
    }

    public function pushesDecisions(): bool
    {
        return (bool) config('larapilot.hackly.push_decisions', true);
    }

    public function includesTargets(): bool
    {
        return (bool) config('larapilot.hackly.include_targets', true);
    }

    /**
     * Resolution groups for findings the user confirmed: the same kind and
     * the same title go together; a leaked secret never merges.
     *
     * @param  list<int|string>  $ids
     * @return array<string, mixed>
     */
    public function resolutionPlan(array $ids): array
    {
        $ids = $this->ids($ids);
        $known = $this->known($ids);
        $issues = array_map(static fn (string $id): array => $known[$id], $ids);

        usort($issues, $this->bySeverity());

        $buckets = [];

        foreach ($issues as $issue) {
            $signature = $this->isSecret($issue)
                ? 'secret|'.$issue['id']
                : $issue['type'].'|'.$issue['origin'].'|'.$this->key((string) $issue['title']);
            $buckets[$signature][] = $issue;
        }

        $groups = [];
        $domains = [];

        foreach ($buckets as $signature => $of) {
            $lead = $of[0];
            $where = array_values(array_unique(array_merge(...array_map(static fn (array $issue): array => $issue['where'], $of))));
            $cves = array_values(array_unique(array_merge(...array_map(static fn (array $issue): array => $issue['cves'], $of))));
            $group = [
                'key' => substr(sha1($signature), 0, 8),
                'ids' => array_column($of, 'id'),
                'type' => (string) $lead['type'],
                'type_label' => (string) $lead['type_label'],
                'severity' => (string) $lead['severity'],
                'score' => (int) $lead['score'],
                'title' => count($of) === 1 ? (string) $lead['title'] : $lead['title'].' (+'.(count($of) - 1).' more)',
                'where' => $where,
                'cves' => $cves,
                'how_to_fix' => (string) $lead['how_to_fix'],
                'reason' => count($of) === 1
                    ? ($this->isSecret($lead) ? 'A leaked secret — never merged: each one is rotated by itself.' : 'One finding — not merged with others.')
                    : 'Same kind ('.$lead['type_label'].') and the same finding — one triage handoff avoids duplicate specs.',
            ];
            $groups[] = $group;

            $domains[$group['type_label']] ??= ['type' => $group['type'], 'type_label' => $group['type_label'], 'group_keys' => [], 'ids' => []];
            $domains[$group['type_label']]['group_keys'][] = $group['key'];
            $domains[$group['type_label']]['ids'] = array_values(array_unique([...$domains[$group['type_label']]['ids'], ...$group['ids']]));
        }

        return ['ids' => $ids, 'domains' => array_values($domains), 'groups' => $groups];
    }

    /**
     * Ask Hackly to scan the repository again — and the linked domains
     * with it, when asked. The scan is queued on Hackly's side and takes
     * minutes: the findings change when it is done.
     *
     * @return array<string, mixed>
     */
    public function scan(?string $profile = null, ?bool $includeTargets = null): array
    {
        $repository = $this->repository();

        if ($repository === null) {
            throw new HacklyException('No repository of Hackly matches this project.', self::NAME_THE_REPOSITORY);
        }

        $profile = strtolower(trim((string) ($profile ?? config('larapilot.hackly.scan_profile', 'standard'))));

        if (! in_array($profile, self::PROFILES, true)) {
            throw new InvalidArgumentException('A Hackly scan profile is quick, standard, or deep.');
        }

        $answer = $this->client->post('/repositories/'.rawurlencode($repository['id']).'/scans', array_filter([
            'profile' => $profile,
            'include_targets' => $includeTargets,
        ], static fn (mixed $value): bool => $value !== null));

        Cache::forget($this->cacheKey());

        $scan = is_array($answer['data'] ?? null) ? $answer['data'] : $answer;

        return [
            'repository' => $repository,
            'profile' => $profile,
            'scan' => ['id' => $scan['id'] ?? null, 'status' => $scan['status'] ?? null],
            'requested_at' => now()->toIso8601String(),
        ];
    }

    /**
     * The findings as a document of the project: what is open, and what
     * was decided about it.
     *
     * @param  array<string, mixed>  $findings
     */
    public function report(array $findings): string
    {
        $repository = $findings['repository'];
        $gate = $findings['gate'];
        $lines = ['# Hackly findings'.(($repository['name'] ?? '') !== '' ? ' — '.$repository['name'] : ''), ''];
        $lines[] = 'Open findings as Hackly reported them on '.Carbon::parse($findings['fetched_at'])->format('Y-m-d H:i')
            .' for the repository'.($findings['targets'] !== [] ? ' and its linked domains ('.implode(', ', array_column($findings['targets'], 'domain')).')' : '')
            .', with what was decided about each one.';
        $lines[] = '';
        $lines[] = '**Gate:** '.$gate['verdict'].' — '.$gate['summary'];
        $lines[] = '';
        $lines[] = '| Severity | Open | New | In the backlog | Waived |';
        $lines[] = '| --- | ---: | ---: | ---: | ---: |';

        foreach (self::SEVERITIES as $severity) {
            $of = array_filter($findings['issues'], static fn (array $issue): bool => $issue['severity'] === $severity);
            $lines[] = '| '.ucfirst($severity).' | '.count($of)
                .' | '.count(array_filter($of, static fn (array $issue): bool => $issue['state'] === 'new'))
                .' | '.count(array_filter($of, static fn (array $issue): bool => $issue['state'] === HacklyLedger::IN_BACKLOG))
                .' | '.count(array_filter($of, static fn (array $issue): bool => in_array($issue['state'], [HacklyLedger::WAIVED, HacklyLedger::FALSE_POSITIVE], true))).' |';
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
                $lines[] = '### '.$this->inline((string) $issue['title']);
                $lines[] = '';
                $lines[] = '- **Id:** `'.$issue['id'].'`';
                $lines[] = '- **Kind:** '.$issue['type_label'].($issue['source'] !== '' ? ' — '.$issue['source'] : '');
                $lines[] = '- **Status in Hackly:** '.(self::STATUSES[$issue['status']] ?? $issue['status']);
                $lines[] = '- **Decision:** '.match ($issue['state']) {
                    HacklyLedger::IN_BACKLOG => 'in the backlog as '.$issue['spec'].($issue['spec_status'] ? ' ('.$issue['spec_status'].')' : ''),
                    HacklyLedger::WAIVED => 'waived — '.$this->inline((string) $issue['reason']),
                    HacklyLedger::FALSE_POSITIVE => 'false positive — '.$this->inline((string) $issue['reason']),
                    default => 'none yet',
                };

                if ($issue['where'] !== []) {
                    $lines[] = '- **Where:** '.$this->inline(implode(', ', $issue['where']));
                }

                if ($issue['cves'] !== []) {
                    $lines[] = '- **CVE:** '.implode(', ', $issue['cves']);
                }

                if ($issue['reachability'] !== null) {
                    $lines[] = '- **Reachability:** '.$issue['reachability'];
                }

                if ($issue['first_detected_at'] !== null) {
                    $lines[] = '- **First seen:** '.Carbon::parse($issue['first_detected_at'])->format('Y-m-d');
                }

                if ($issue['description'] !== '') {
                    $lines[] = '';
                    $lines[] = $this->inline($issue['description']);
                }
            }
        }

        if ($findings['closed'] !== []) {
            $lines[] = '';
            $lines[] = '## No longer open in Hackly';
            $lines[] = '';
            $lines[] = '| Finding | Was | Decision |';
            $lines[] = '| --- | --- | --- |';

            foreach ($findings['closed'] as $closed) {
                $lines[] = '| '.str_replace('|', '\\|', $this->inline((string) ($closed['title'] ?? $closed['id']))).' | '
                    .($closed['severity'] ?? '—').' | '
                    .(in_array($closed['state'] ?? '', [HacklyLedger::WAIVED, HacklyLedger::FALSE_POSITIVE], true)
                        ? str_replace('_', ' ', (string) $closed['state']).' — '.str_replace('|', '\\|', $this->inline((string) ($closed['reason'] ?? '')))
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
        $directory = $this->config->absolutePath(rtrim($this->config->resolve()['paths']['security'] ?? '.larapilot/docs/security/', '/\\'));

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.'security-report.md';
        AtomicFile::write($path, $this->report($findings));

        return ['path' => $path, 'relative' => str_replace('\\', '/', $this->config->relativePath($path))];
    }

    /**
     * Every finding of the repository and its domains, whatever its state:
     * open (and acknowledged), fixed, and false positive or waived with the
     * reason — what a client or an auditor asks to see.
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
        $decisions = $this->ledger->all();
        $truncated = (bool) $findings['truncated'];
        $lists = ['open' => [], 'resolved' => [], 'ignored' => []];
        $placed = [];

        $place = static function (string $list, array $entry) use (&$lists, &$placed): void {
            if (! isset($placed[$entry['id']])) {
                $placed[$entry['id']] = true;
                $lists[$list][] = $entry;
            }
        };

        foreach ($findings['issues'] as $issue) {
            $waived = in_array($issue['state'], [HacklyLedger::WAIVED, HacklyLedger::FALSE_POSITIVE], true);
            $place($waived ? 'ignored' : 'open', $issue + [
                'ignored_at' => $waived ? $issue['decided_at'] : null,
                'ignored_by' => $waived ? 'larapilot' : null,
            ]);
        }

        foreach (['fixed' => 'resolved', 'false_positive' => 'ignored'] as $status => $list) {
            [$issues, $cut] = $this->fetch($findings['repository'], $findings['targets'], $status);
            $truncated = $truncated || $cut;

            foreach ($issues as $issue) {
                $decided = $this->decided($issue, $decisions[$issue['id']] ?? null);
                $place($list, array_merge($decided, match ($list) {
                    'ignored' => [
                        'reason' => $decided['reason'] ?? ($issue['false_positive_note'] !== '' ? $issue['false_positive_note'] : null),
                        'ignored_at' => $issue['false_positive_noted_at'] ?? $issue['updated_at'],
                        'ignored_by' => ($decisions[$issue['id']] ?? null) !== null ? 'larapilot' : 'user',
                    ],
                    default => ['closed_at' => $issue['updated_at']],
                }));
            }
        }

        foreach ($decisions as $id => $decision) {
            $waived = in_array($decision['state'] ?? null, [HacklyLedger::WAIVED, HacklyLedger::FALSE_POSITIVE], true);
            $severity = strtolower((string) ($decision['severity'] ?? 'low'));

            $place($waived ? 'ignored' : 'resolved', [
                'id' => (string) $id,
                'title' => (string) ($decision['title'] ?? 'Finding '.$id),
                'type' => (string) ($decision['type'] ?? ''),
                'type_label' => (string) ($decision['type_label'] ?? ucfirst(str_replace('_', ' ', (string) ($decision['type'] ?? 'finding')))),
                'severity' => in_array($severity, self::SEVERITIES, true) ? $severity : 'low',
                'score' => 0,
                'cves' => [],
                'where' => [],
                'first_detected_at' => $decision['first_seen'] ?? null,
                'state' => $waived ? (string) $decision['state'] : HacklyLedger::IN_BACKLOG,
                'spec' => $waived ? null : ($decision['spec'] ?? null),
                'reason' => $waived ? (string) ($decision['reason'] ?? '') : null,
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
            'scanner' => 'hackly',
            'severities' => self::SEVERITIES,
            'repository' => [
                'name' => $findings['repository']['name'],
                'url' => $findings['repository']['url'],
                'branch' => $findings['repository']['branch'],
                'last_scanned_at' => $findings['repository']['last_scanned_at'],
            ],
            'generated_at' => now()->toIso8601String(),
            'truncated' => $truncated,
            'counts' => $counts,
        ] + $lists;

        Cache::put($key, $register, max(30, (int) config('larapilot.hackly.cache_seconds', 300)));

        return $register;
    }

    public function failOn(): string
    {
        $value = strtolower(trim((string) config('larapilot.hackly.fail_on', 'high')));

        return match ($value) {
            'critical' => 'high',
            'high', 'medium', 'low', 'none' => $value,
            default => 'high',
        };
    }

    /**
     * The repository of Hackly that is this project: named in `.env`,
     * chosen and kept in the ledger, or found from the git remote.
     *
     * @return array{id: string, name: string, url: string, branch: string, status: string, last_scanned_at: string|null, open_findings: int|null, targets: list<array{id: string, domain: string}>}|null
     */
    public function repository(): ?array
    {
        $wanted = $this->wanted();

        if ($this->isUuid($wanted)) {
            try {
                $found = $this->client->get('/repositories/'.rawurlencode($wanted));

                return $this->repositoryRow(is_array($found['data'] ?? null) ? $found['data'] : $found);
            } catch (HacklyException $e) {
                if ($e->status() !== 404) {
                    throw $e;
                }

                return null;
            }
        }

        foreach ($this->allRepositories() as $repository) {
            if ($this->isThisProject($repository)) {
                return $repository;
            }
        }

        return null;
    }

    public function repositorySource(): string
    {
        return match (true) {
            $this->named() !== '' => 'env',
            $this->ledger->chosenRepository() !== null => 'ledger',
            default => 'git remote',
        };
    }

    /**
     * The repositories of Hackly, the ones that may be this project first.
     *
     * @return array<string, mixed>
     */
    public function repositories(string $search = ''): array
    {
        $all = $this->allRepositories($search);

        return [
            'repositories' => array_map(fn (array $repository): array => $repository + ['matches_origin' => $this->matchesOrigin($repository)], $all),
            'origin' => $this->git->originUrl(),
            'chosen' => $this->ledger->chosenRepository(),
            'ledger' => $this->ledger->relativePath(),
        ];
    }

    /**
     * Keep the repository of Hackly the user says this project is.
     *
     * @return array<string, mixed>
     */
    public function useRepository(string $named): array
    {
        $named = trim($named);

        foreach ($this->allRepositories() as $repository) {
            if ($repository['id'] === $named || strtolower($repository['name']) === strtolower($named)) {
                $this->ledger->chooseRepository(['id' => $repository['id'], 'full_name' => $repository['name']]);
                Cache::forget($this->cacheKey());

                return $repository;
            }
        }

        throw new InvalidArgumentException('Hackly has no repository "'.$named.'". php artisan larapilot:security-repos lists them.');
    }

    public function forgetRepository(): void
    {
        $this->ledger->chooseRepository(null);
        Cache::forget($this->cacheKey());
    }

    // ---- reading ----

    /**
     * @return array{repository: array<string, mixed>, targets: list<array{id: string, domain: string}>, issues: list<array<string, mixed>>, fetched_at: string, truncated: bool}
     */
    protected function download(): array
    {
        $repository = $this->repository();

        if ($repository === null) {
            throw new HacklyException('No repository of Hackly matches this project.', self::NAME_THE_REPOSITORY);
        }

        $targets = $this->includesTargets() ? $repository['targets'] : [];
        [$open, $cutOpen] = $this->fetch($repository, $targets, 'open');
        [$ack, $cutAck] = $this->fetch($repository, $targets, 'ack');
        $issues = [...$open, ...$ack];
        usort($issues, $this->bySeverity());

        $data = [
            'repository' => $repository,
            'targets' => $targets,
            'issues' => $issues,
            'fetched_at' => now()->toIso8601String(),
            'truncated' => $cutOpen || $cutAck,
        ];

        Cache::put($this->cacheKey(), $data, max(30, (int) config('larapilot.hackly.cache_seconds', 300)));

        return $data;
    }

    /**
     * @return array{repository: array<string, mixed>, targets: list<array{id: string, domain: string}>, issues: list<array<string, mixed>>, fetched_at: string, truncated: bool}
     */
    protected function cached(): array
    {
        $cached = Cache::get($this->cacheKey());

        return is_array($cached) && isset($cached['issues'], $cached['repository'], $cached['fetched_at'], $cached['targets'])
            ? $cached
            : $this->download();
    }

    /**
     * The findings in one state of Hackly, of the repository and of its
     * linked domains, without duplicates.
     *
     * @param  array<string, mixed>  $repository
     * @param  list<array{id: string, domain: string}>  $targets
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    protected function fetch(array $repository, array $targets, string $status): array
    {
        $issues = [];
        $truncated = false;
        $scopes = [['repository_id' => (string) $repository['id'], 'origin' => 'repository']];

        foreach ($targets as $target) {
            $scopes[] = ['target_id' => $target['id'], 'origin' => 'target'];
        }

        foreach ($scopes as $scope) {
            $origin = $scope['origin'];
            unset($scope['origin']);

            for ($page = 1; $page <= self::MAX_PAGES; $page++) {
                $answer = $this->client->get('/findings', $scope + ['status' => $status, 'per_page' => self::PER_PAGE, 'page' => $page, 'issues' => 1]);
                $batch = is_array($answer['data'] ?? null) ? $answer['data'] : [];

                foreach ($batch as $finding) {
                    if (is_array($finding) && is_string($finding['id'] ?? null)) {
                        $issues[$finding['id']] ??= $this->normalize($finding, $origin);
                    }
                }

                $last = (int) ($answer['meta']['last_page'] ?? $page);

                if ($page >= $last || count($batch) < self::PER_PAGE) {
                    break;
                }

                $truncated = $truncated || $page === self::MAX_PAGES;
            }
        }

        return [array_values($issues), $truncated];
    }

    /**
     * @return list<array{id: string, name: string, url: string, branch: string, status: string, last_scanned_at: string|null, open_findings: int|null, targets: list<array{id: string, domain: string}>}>
     */
    protected function allRepositories(string $search = ''): array
    {
        $repositories = [];

        for ($page = 1; $page <= 5; $page++) {
            $answer = $this->client->get('/repositories', ['q' => trim($search) !== '' ? trim($search) : null, 'per_page' => self::PER_PAGE, 'page' => $page]);
            $batch = is_array($answer['data'] ?? null) ? $answer['data'] : [];

            foreach ($batch as $repository) {
                if (is_array($repository) && is_string($repository['id'] ?? null)) {
                    $repositories[] = $this->repositoryRow($repository);
                }
            }

            if ($page >= (int) ($answer['meta']['last_page'] ?? $page) || count($batch) < self::PER_PAGE) {
                break;
            }
        }

        usort($repositories, fn (array $a, array $b): int => [! $this->matchesOrigin($a), $a['name']] <=> [! $this->matchesOrigin($b), $b['name']]);

        return $repositories;
    }

    /**
     * @param  array<array-key, mixed>  $repository
     * @return array{id: string, name: string, url: string, branch: string, status: string, last_scanned_at: string|null, open_findings: int|null, targets: list<array{id: string, domain: string}>}
     */
    protected function repositoryRow(array $repository): array
    {
        $targets = [];

        foreach (is_array($repository['targets'] ?? null) ? $repository['targets'] : [] as $target) {
            if (is_array($target) && is_string($target['id'] ?? null)) {
                $targets[] = ['id' => $target['id'], 'domain' => (string) ($target['domain'] ?? '')];
            }
        }

        return [
            'id' => (string) ($repository['id'] ?? ''),
            'name' => (string) ($repository['full_name'] ?? trim(($repository['owner'] ?? '').'/'.($repository['name'] ?? ''), '/')),
            'url' => (string) ($repository['html_url'] ?? ''),
            'branch' => (string) ($repository['default_branch'] ?? ''),
            'status' => (string) ($repository['status'] ?? ''),
            'last_scanned_at' => is_string($repository['last_scanned_at'] ?? null) ? $repository['last_scanned_at'] : null,
            'open_findings' => isset($repository['open_findings']) ? (int) $repository['open_findings'] : null,
            'targets' => $targets,
        ];
    }

    /**
     * @param  array<string, mixed>  $repository
     */
    protected function isThisProject(array $repository): bool
    {
        $wanted = $this->wanted();

        if ($wanted !== '') {
            return $repository['id'] === $wanted || strtolower((string) $repository['name']) === strtolower($wanted);
        }

        return $this->matchesOrigin($repository);
    }

    /**
     * @param  array<string, mixed>  $repository
     */
    protected function matchesOrigin(array $repository): bool
    {
        $slug = strtolower((string) $this->git->originRepoSlug());
        $origin = $this->address((string) $this->git->originUrl());

        return ($origin !== '' && $this->address((string) $repository['url']) === $origin)
            || ($slug !== '' && strtolower((string) $repository['name']) === $slug);
    }

    /**
     * @param  array<string, mixed>  $finding
     * @return array<string, mixed>
     */
    protected function normalize(array $finding, string $origin): array
    {
        $severity = strtolower((string) ($finding['severity'] ?? 'low'));
        $severity = in_array($severity, self::SEVERITIES, true) ? $severity : ($severity === 'critical' ? 'high' : 'low');
        $category = trim((string) ($finding['category'] ?? ''));
        $source = trim((string) ($finding['source'] ?? ''));
        $where = [];

        foreach ([$finding['subject'] ?? null, $finding['repository']['full_name'] ?? null, $finding['asset']['domain'] ?? null] as $place) {
            if (is_string($place) && trim($place) !== '') {
                $where[] = trim($place);
            }
        }

        foreach (array_slice(is_array($finding['evidence_lines'] ?? null) ? $finding['evidence_lines'] : [], 0, 2) as $line) {
            if (is_string($line) && trim($line) !== '' && mb_strlen(trim($line)) <= 160) {
                $where[] = trim($line);
            }
        }

        $cves = preg_split('/[\s,;]+/', strtoupper(trim((string) ($finding['cve'] ?? '')))) ?: [];
        $reachability = is_string($finding['reachability'] ?? null) && $finding['reachability'] !== '' ? $finding['reachability'] : null;

        return [
            'id' => (string) $finding['id'],
            'title' => trim((string) ($finding['title'] ?? 'Finding')),
            'description' => trim((string) ($finding['description'] ?? '')),
            'type' => $category !== '' ? $category : ($source !== '' ? $source : 'finding'),
            'type_label' => trim((string) ($finding['category_label'] ?? '')) !== '' ? trim((string) $finding['category_label']) : ucfirst(str_replace('_', ' ', $category !== '' ? $category : 'finding')),
            'severity' => $severity,
            // Reachable code first among the same severity.
            'score' => (['high' => 30, 'medium' => 20, 'low' => 10][$severity]) + ($reachability === 'reachable' ? 5 : 0) + (int) round(((float) ($finding['confidence'] ?? 0)) * 4),
            'status' => (string) ($finding['status'] ?? 'open'),
            'source' => $source,
            'origin' => $origin,
            'where' => array_values(array_unique($where)),
            'how_to_fix' => '',
            'cves' => array_values(array_unique(array_filter($cves, static fn (string $cve): bool => preg_match('/^(CVE|GHSA)-/', $cve) === 1))),
            'reachability' => $reachability,
            'noise' => (bool) ($finding['noise_filtered'] ?? false),
            'false_positive_note' => trim((string) ($finding['false_positive_note'] ?? '')),
            'false_positive_noted_at' => is_string($finding['false_positive_noted_at'] ?? null) ? $finding['false_positive_noted_at'] : null,
            'first_detected_at' => is_string($finding['created_at'] ?? null) ? $finding['created_at'] : null,
            'updated_at' => is_string($finding['updated_at'] ?? null) ? $finding['updated_at'] : null,
            'minutes_to_fix' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $issue
     * @param  array<string, mixed>|null  $decision
     * @return array<string, mixed>
     */
    protected function decided(array $issue, ?array $decision): array
    {
        $state = in_array($decision['state'] ?? null, [HacklyLedger::IN_BACKLOG, HacklyLedger::WAIVED, HacklyLedger::FALSE_POSITIVE], true)
            ? (string) $decision['state']
            : 'new';
        $spec = $state === HacklyLedger::IN_BACKLOG ? (string) ($decision['spec'] ?? '') : '';
        $found = $spec !== '' ? $this->specs->find($spec) : null;

        if ($state === HacklyLedger::IN_BACKLOG && $found === null) {
            $state = 'new';
            $spec = '';
        }

        return array_merge($issue, [
            'state' => $state,
            'spec' => $spec !== '' ? $spec : null,
            'spec_status' => $found !== null ? (string) ($found['status'] ?? '') : null,
            'reason' => in_array($state, [HacklyLedger::WAIVED, HacklyLedger::FALSE_POSITIVE], true) ? (string) ($decision['reason'] ?? '') : null,
            'decided_at' => $state !== 'new' ? ($decision['at'] ?? null) : null,
        ]);
    }

    /**
     * What the ship gate makes of the findings. A waived finding and a
     * false positive were decided about. One in the backlog is still open:
     * it stops the gate until Hackly no longer reports it.
     *
     * @param  list<array<string, mixed>>  $issues
     * @return array<string, mixed>
     */
    protected function gate(array $issues): array
    {
        $threshold = $this->failOn();
        $rank = array_flip(self::SEVERITIES);
        $standing = array_values(array_filter($issues, static fn (array $issue): bool => ! in_array($issue['state'], [HacklyLedger::WAIVED, HacklyLedger::FALSE_POSITIVE], true)));
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

        return [
            'verdict' => $verdict,
            'fail_on' => $threshold,
            'summary' => match ($verdict) {
                'FAIL' => $count.' open '.($count === 1 ? 'finding is' : 'findings are').' '.$threshold.' or above'
                    .($undecided > 0 ? ': '.$undecided.' with no decision yet' : ', all in the backlog and not fixed yet').'.',
                'WARN' => count($standing).' open '.(count($standing) === 1 ? 'finding' : 'findings').' below '.$threshold.'.',
                default => $issues === [] ? 'Hackly reports nothing open.' : 'Every open finding was waived, or called a false positive, with a reason.',
            },
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
            $counts[$issue['severity']] = ($counts[$issue['severity']] ?? 0) + 1;
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
        $severity = $severity === 'critical' ? 'high' : $severity;
        $type = strtolower(trim((string) ($filters['type'] ?? '')));

        $issues = array_values(array_filter($issues, static function (array $issue) use ($filters, $rank, $severity, $type): bool {
            if ($severity !== '' && isset($rank[$severity]) && $rank[$issue['severity']] > $rank[$severity]) {
                return false;
            }

            if ($type !== '' && strtolower((string) $issue['type']) !== $type) {
                return false;
            }

            return ! (($filters['new'] ?? false) && $issue['state'] !== 'new');
        }));

        $limit = (int) ($filters['limit'] ?? 0);

        return $limit > 0 ? array_slice($issues, 0, $limit) : $issues;
    }

    /**
     * The findings named, as read: an id Hackly does not list as open is refused.
     *
     * @param  list<string>  $ids
     * @return array<string, array<string, mixed>>
     */
    protected function known(array $ids): array
    {
        $index = [];

        foreach ($this->cached()['issues'] as $issue) {
            $index[$issue['id']] = $issue;
        }

        $missing = array_values(array_filter($ids, static fn (string $id): bool => ! isset($index[$id])));

        if ($missing !== []) {
            foreach ($this->download()['issues'] as $issue) {
                $index[$issue['id']] = $issue;
            }

            $missing = array_values(array_filter($ids, static fn (string $id): bool => ! isset($index[$id])));
        }

        if ($missing !== []) {
            throw new InvalidArgumentException('Hackly lists no open finding '.implode(', ', $missing).' for this project.');
        }

        return $index;
    }

    /**
     * @param  list<string>  $ids
     * @return list<array<string, mixed>>
     */
    protected function tell(array $ids, bool $push): array
    {
        if (! $push || ! $this->pushesDecisions() || $ids === []) {
            return [];
        }

        $told = [];
        $byStatus = [];

        foreach ($ids as $id) {
            $decision = $this->ledger->find($id) ?? [];
            $status = ($decision['state'] ?? null) === HacklyLedger::FALSE_POSITIVE ? 'false_positive' : 'ack';
            $note = $status === 'false_positive' ? (string) ($decision['reason'] ?? '') : '';
            $byStatus[$status.'|'.$note][] = $id;
        }

        foreach ($byStatus as $key => $group) {
            [$status, $note] = explode('|', $key, 2);

            try {
                $this->client->post('/findings/triage', array_filter(['ids' => $group, 'status' => $status, 'note' => $note !== '' ? $note : null], static fn (mixed $value): bool => $value !== null));

                foreach ($group as $id) {
                    $this->ledger->annotate($id, ['sent' => $status, 'sent_at' => now()->toIso8601String()]);
                    $told[] = ['id' => $id, 'sent' => true, 'action' => $status === 'ack' ? 'acknowledged' : 'false positive'];
                }
            } catch (HacklyException $e) {
                foreach ($group as $id) {
                    $told[] = $this->refused($id, $e);
                }

                if (in_array($e->status(), [0, 401, 403, 429], true)) {
                    break;
                }
            }
        }

        Cache::forget($this->cacheKey());

        return $told;
    }

    /**
     * @param  array<string, mixed>  $decision
     */
    protected function told(array $decision): bool
    {
        return match ($decision['state'] ?? null) {
            HacklyLedger::FALSE_POSITIVE => ($decision['sent'] ?? null) === 'false_positive',
            HacklyLedger::IN_BACKLOG, HacklyLedger::WAIVED => ($decision['sent'] ?? null) === 'ack',
            default => true,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function refused(string $id, HacklyException $e): array
    {
        return [
            'id' => $id,
            'sent' => false,
            'error' => $e->getMessage(),
            'hint' => $e->status() === 403
                ? 'Give the Hackly token the write ability, then tell Hackly with: php artisan larapilot:security-push'
                : trim(($e->hint() ?? '').' The decision is kept. Tell Hackly later with: php artisan larapilot:security-push'),
            'status' => $e->status(),
        ];
    }

    /**
     * @param  array<string, mixed>  $issue
     * @return array<string, mixed>
     */
    protected function facts(array $issue): array
    {
        return array_filter([
            'title' => $issue['title'] ?? null,
            'severity' => $issue['severity'] ?? null,
            'type' => $issue['type'] ?? null,
            'type_label' => $issue['type_label'] ?? null,
            'first_seen' => $issue['first_detected_at'] ?? null,
            'spec' => null,
            'reason' => null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    protected function isSecret(array $issue): bool
    {
        $text = strtolower($issue['type'].' '.$issue['source'].' '.$issue['title']);

        return str_contains($text, 'secret') || str_contains($text, 'gitleaks') || str_contains($text, 'leaked') || str_contains($text, 'credential');
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<string>
     */
    protected function ids(array $ids): array
    {
        $clean = [];

        foreach ($ids as $id) {
            foreach (preg_split('/[\s,]+/', trim((string) $id)) ?: [] as $part) {
                $part = strtolower(trim($part, " \t#"));

                if ($part === '') {
                    continue;
                }

                if (! $this->isUuid($part)) {
                    throw new InvalidArgumentException('"'.$part.'" is not the id of a Hackly finding: Hackly names them with a UUID.');
                }

                $clean[] = $part;
            }
        }

        if ($clean === []) {
            throw new InvalidArgumentException('Name at least one finding by its id.');
        }

        return array_values(array_unique($clean));
    }

    /**
     * @return \Closure(array<string, mixed>, array<string, mixed>): int
     */
    protected function bySeverity(): \Closure
    {
        $rank = array_flip(self::SEVERITIES);

        return static fn (array $a, array $b): int => [$rank[$a['severity']] ?? 9, -($a['score'] ?? 0), (string) $a['id']] <=> [$rank[$b['severity']] ?? 9, -($b['score'] ?? 0), (string) $b['id']];
    }

    protected function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }

    protected function wanted(): string
    {
        $named = $this->named();

        return $named !== '' ? $named : (string) ($this->ledger->chosenRepository()['id'] ?? '');
    }

    protected function named(): string
    {
        return trim((string) config('larapilot.hackly.repository', ''));
    }

    protected function address(string $url): string
    {
        $url = strtolower(trim($url));

        if ($url === '') {
            return '';
        }

        $url = (string) preg_replace('#^(?:https?://|ssh://)?(?:[^@/]+@)?#', '', $url);

        return trim((string) preg_replace('#\.git$#', '', str_replace(':', '/', $url)), '/');
    }

    protected function key(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strtolower(trim($text))));
    }

    protected function inline(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    protected function forgetRegister(): void
    {
        Cache::forget($this->cacheKey().'.register');
    }

    protected function cacheKey(): string
    {
        return 'larapilot.hackly.findings.'.sha1($this->client->host().'|'.$this->config->projectRoot().'|'.$this->wanted().'|'.($this->includesTargets() ? 't' : ''));
    }
}
