<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Larapilot\Services\Boogle\BoogleClient;
use Larapilot\Services\Boogle\BoogleException;
use Larapilot\Services\Boogle\BoogleLedger;
use Larapilot\Support\AtomicFile;

/**
 * The errors Boogle recorded for the running application, set against the
 * backlog.
 *
 * Boogle keeps one row for each time an exception was thrown. Larapilot
 * reads the open ones, puts together the ones that are one bug — the same
 * exception at the same line — and says what was already decided about
 * each bug. Turning a bug into work is the job of `/larapilot-boogle`,
 * which hands it to triage.
 *
 * What an occurrence says about a person stays in Boogle: the user, the
 * query string, and the payload of the request are never kept, written, or
 * shown, and an address in a message is masked.
 */
class BoogleService
{
    /** Thrown by the code. */
    public const ERROR = 'error';

    /** Recorded by the uptime monitor: the application did not answer. */
    public const OUTAGE = 'outage';

    /**
     * What Boogle calls open: nobody fixed it, whether or not it was seen.
     *
     * @var list<string>
     */
    public const OPEN = ['OPEN', 'READ'];

    /**
     * @var list<string>
     */
    public const CLOSED = ['FIXED', 'DONE'];

    protected const MAX_PAGES = 6;

    /**
     * Where the code of an application starts, in a path of the server.
     */
    protected const ROOTS = 'app|bootstrap|config|database|lang|modules|packages|public|resources|routes|src|storage|tests|vendor';

    public function __construct(
        protected ConfigService $config,
        protected SpecService $specs,
        protected BoogleClient $client,
        protected BoogleLedger $ledger,
    ) {}

    /**
     * Whether Boogle can be read, and what is missing when it cannot.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $enabled = $this->config->boogleEnabled();
        $configured = $this->client->configured();
        $hints = [];
        $project = null;
        $authenticated = false;
        $error = null;

        if (! $enabled) {
            $hints[] = 'Enable with: php artisan larapilot:settings-set --boogle=YES';
        }

        if ($this->client->host() === '') {
            $hints[] = 'Set LARAPILOT_BOOGLE_URL in .env: the address of the Boogle of the team, such as https://boogle.example.com.';
        }

        if (! $this->client->hasToken()) {
            $hints[] = 'Set LARAPILOT_BOOGLE_TOKEN in .env. Create it in Boogle, as an admin user, in the profile under API tokens.';
        }

        if ($configured) {
            try {
                $project = $this->project();
                $authenticated = true;

                if ($project === null) {
                    $hints[] = 'No project of Boogle matches this application'.($this->wanted() !== '' ? ' ("'.$this->wanted().'")' : '').'. Name it with LARAPILOT_BOOGLE_PROJECT (its id or its title), or set BOOGLE_PROJECT_KEY as the client package asks.';
                }
            } catch (BoogleException $e) {
                $error = $e->getMessage();
                $authenticated = ! in_array($e->status(), [0, 401, 403], true);
                $hints[] = trim($e->getMessage().' '.($e->hint() ?? ''));
            }
        }

        return [
            'enabled' => $enabled,
            'configured' => $configured,
            'authenticated' => $authenticated,
            'host' => $this->client->host(),
            'project' => $project,
            'ledger' => $this->ledger->relativePath(),
            'ready' => $enabled && $configured && $authenticated && $project !== null,
            'error' => $error,
            'hints' => $hints,
        ];
    }

    /**
     * The open errors of the project, the ones that happen the most first,
     * each with what was decided about it.
     *
     * @param  array{new?: bool, kind?: string|null, limit?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function errors(array $filters = [], bool $fresh = true): array
    {
        $data = $fresh ? $this->download() : $this->cached();
        $decisions = $this->ledger->all();
        $errors = [];

        foreach ($data['errors'] as $error) {
            $errors[] = $this->decided($error, $decisions[$error['key']] ?? null);
        }

        $open = array_column($errors, 'key');
        $closed = [];

        // Decided here, and no longer open in Boogle: fixed, or closed there.
        foreach ($decisions as $key => $decision) {
            if (! in_array($key, $open, true)) {
                $closed[] = array_merge(['key' => $key], $decision);
            }
        }

        $bugs = array_values(array_filter($errors, static fn (array $error): bool => $error['kind'] === self::ERROR));
        $outages = array_values(array_filter($errors, static fn (array $error): bool => $error['kind'] === self::OUTAGE));

        return [
            'project' => $data['project'],
            'fetched_at' => $data['fetched_at'],
            'truncated' => $data['truncated'],
            'counts' => [
                'errors' => count($bugs),
                'occurrences' => array_sum(array_column($bugs, 'count')),
                'new' => count(array_filter($bugs, static fn (array $error): bool => $error['state'] === 'new')),
                'in_backlog' => count(array_filter($bugs, static fn (array $error): bool => $error['state'] === BoogleLedger::IN_BACKLOG)),
                'ignored' => count(array_filter($bugs, static fn (array $error): bool => $error['state'] === BoogleLedger::IGNORED)),
                'returned' => count(array_filter($bugs, static fn (array $error): bool => $error['returned'])),
                'outages' => array_sum(array_column($outages, 'count')),
            ],
            'summary' => $this->summary($bugs, $outages),
            'days' => $this->days($bugs),
            'total' => count($errors),
            'errors' => $this->filter($errors, $filters),
            'closed' => $closed,
            'ledger' => $this->ledger->relativePath(),
        ];
    }

    /**
     * Record that errors are in the backlog as a spec.
     *
     * @param  list<string>  $names  codes of Boogle (`#BUG12`) or keys of errors
     * @return array<string, mixed>
     */
    public function link(array $names, string $spec): array
    {
        $found = $this->specs->find($spec);

        if ($found === null) {
            throw new InvalidArgumentException("No spec {$spec} in the backlog.");
        }

        $errors = $this->known($names);

        foreach ($errors as $error) {
            $this->ledger->record($error['key'], $this->facts($error) + [
                'state' => BoogleLedger::IN_BACKLOG,
                'spec' => (string) $found['code'],
                'reason' => null,
                'resolved_at' => null,
            ], $this->projectRef());
        }

        return ['errors' => array_column($errors, 'key'), 'codes' => $this->codes($errors), 'state' => BoogleLedger::IN_BACKLOG, 'spec' => (string) $found['code'], 'ledger' => $this->ledger->relativePath()];
    }

    /**
     * Record that errors are left as they are, and why.
     *
     * @param  list<string>  $names
     * @return array<string, mixed>
     */
    public function ignore(array $names, string $reason): array
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('Say why the error is left as it is, in a sentence: a decision without a reason cannot be reviewed.');
        }

        $errors = $this->known($names);

        foreach ($errors as $error) {
            $this->ledger->record($error['key'], $this->facts($error) + [
                'state' => BoogleLedger::IGNORED,
                'reason' => $reason,
                'spec' => null,
                'resolved_at' => null,
            ], $this->projectRef());
        }

        return ['errors' => array_column($errors, 'key'), 'codes' => $this->codes($errors), 'state' => BoogleLedger::IGNORED, 'reason' => $reason, 'ledger' => $this->ledger->relativePath()];
    }

    /**
     * Drop what was decided: by the code of an occurrence that is open, or
     * by the key of the error, which is all that is left of a closed one.
     *
     * @param  list<string>  $names
     * @return array<string, mixed>
     */
    public function forget(array $names): array
    {
        $names = $this->names($names);
        $decisions = $this->ledger->all();
        $keys = [];

        foreach ($names as $index => $name) {
            if (isset($decisions[strtolower($name)])) {
                $keys[] = strtolower($name);
                unset($names[$index]);
            }
        }

        if ($names !== []) {
            $keys = array_merge($keys, array_column($this->known(array_values($names)), 'key'));
        }

        foreach (array_unique($keys) as $key) {
            $this->ledger->forget($key);
        }

        return ['errors' => array_values(array_unique($keys)), 'state' => 'new', 'ledger' => $this->ledger->relativePath()];
    }

    /**
     * Tell Boogle the errors are fixed: every open occurrence of each one
     * is closed there, with a line that says what fixed it. This writes to
     * Boogle, so it is never done without being asked for.
     *
     * @param  list<string>  $names
     * @return array<string, mixed>
     */
    public function resolve(array $names, string $status = 'FIXED', ?string $comment = null): array
    {
        $status = strtoupper(trim($status));

        if (! in_array($status, self::CLOSED, true)) {
            throw new InvalidArgumentException('An error is closed in Boogle as FIXED or DONE.');
        }

        $errors = $this->known($names);
        $project = $this->cached()['project'];
        $closed = [];

        foreach ($errors as $error) {
            $decision = $this->ledger->all()[$error['key']] ?? [];
            $line = trim((string) $comment) !== ''
                ? trim((string) $comment)
                : (($decision['spec'] ?? null) !== null ? 'Fixed by '.$decision['spec'].'.' : 'Closed from Larapilot.');

            foreach ($error['occurrences'] as $occurrence) {
                $this->client->patch('/projects/'.rawurlencode((string) $project['id']).'/exceptions/'.rawurlencode($occurrence['id']).'/status', [
                    'status' => $status,
                    'comment' => mb_substr($line, 0, 2000),
                ]);

                $closed[] = $occurrence['code'] ?? $occurrence['id'];
            }

            if ($decision !== []) {
                $this->ledger->note($error['key'], ['resolved_at' => now()->toIso8601String()]);
            }
        }

        // What was read is no longer what Boogle holds.
        Cache::forget($this->cacheKey());

        return ['errors' => array_column($errors, 'key'), 'closed' => $closed, 'status' => $status, 'project' => $project['title']];
    }

    /**
     * The errors as a document of the project: what is open, and what was
     * decided about it. Give it errors no filter has narrowed.
     *
     * @param  array<string, mixed>  $errors
     */
    public function report(array $errors): string
    {
        $project = $errors['project'];
        $lines = ['# Boogle errors'.(($project['title'] ?? '') !== '' ? ' — '.$project['title'] : ''), ''];
        $lines[] = 'Open errors as Boogle held them on '.Carbon::parse($errors['fetched_at'])->format('Y-m-d H:i').', one entry for each bug, with what was decided about it.';
        $lines[] = 'The user, the query string, and the payload of a request stay in Boogle.';
        $lines[] = '';
        $lines[] = '**In short:** '.$errors['summary'];
        $lines[] = '';
        $lines[] = '| Errors | Times thrown | No decision | In the backlog | Left as they are | Back after a fix | Outages |';
        $lines[] = '| ---: | ---: | ---: | ---: | ---: | ---: | ---: |';
        $lines[] = '| '.implode(' | ', [
            $errors['counts']['errors'],
            $errors['counts']['occurrences'],
            $errors['counts']['new'],
            $errors['counts']['in_backlog'],
            $errors['counts']['ignored'],
            $errors['counts']['returned'],
            $errors['counts']['outages'],
        ]).' |';

        foreach ([self::ERROR => 'Errors', self::OUTAGE => 'Outages'] as $kind => $title) {
            $of = array_values(array_filter($errors['errors'], static fn (array $error): bool => $error['kind'] === $kind));

            if ($of === []) {
                continue;
            }

            $lines[] = '';
            $lines[] = '## '.$title.' ('.count($of).')';

            foreach ($of as $error) {
                $lines[] = '';
                $lines[] = '### '.implode(', ', array_slice($error['codes'], 0, 6)).(count($error['codes']) > 6 ? ', …' : '').' — '.$this->inline($error['class']);
                $lines[] = '';
                $lines[] = '- **Decision:** '.match ($error['state']) {
                    BoogleLedger::IN_BACKLOG => 'in the backlog as '.$error['spec'].($error['spec_status'] ? ' ('.$error['spec_status'].')' : '').($error['returned'] ? ' — **back after the fix**' : ''),
                    BoogleLedger::IGNORED => 'left as it is — '.$this->inline((string) $error['reason']),
                    default => 'none yet',
                };
                $lines[] = '- **Thrown:** '.$error['count'].' '.($error['count'] === 1 ? 'time' : 'times')
                    .', first '.Carbon::parse($error['first_seen'])->format('Y-m-d H:i')
                    .', last '.Carbon::parse($error['last_seen'])->format('Y-m-d H:i');

                if ($error['where'] !== null) {
                    $lines[] = '- **Where:** `'.$error['where'].'`'.($error['in_vendor'] ? ' (in a package)' : '');
                }

                if ($error['request'] !== null) {
                    $lines[] = '- **Request:** `'.$error['request'].'`';
                }

                $lines[] = '- **Key:** `'.$error['key'].'`';

                if ($error['message'] !== '') {
                    $lines[] = '';
                    $lines[] = '> '.$this->inline($error['message']);
                }
            }
        }

        if ($errors['closed'] !== []) {
            $lines[] = '';
            $lines[] = '## No longer open in Boogle';
            $lines[] = '';
            $lines[] = '| Error | Where | Decision |';
            $lines[] = '| --- | --- | --- |';

            foreach ($errors['closed'] as $closed) {
                $lines[] = '| '.str_replace('|', '\\|', $this->inline((string) ($closed['class'] ?? $closed['key']))).' | '
                    .(($closed['where'] ?? '') !== '' ? '`'.$closed['where'].'`' : '—').' | '
                    .(($closed['state'] ?? '') === BoogleLedger::IGNORED ? 'left as it was' : 'fixed by '.($closed['spec'] ?? '—')).' |';
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, mixed>  $errors
     * @return array{path: string, relative: string}
     */
    public function writeReport(array $errors): array
    {
        $directory = rtrim($this->config->resolve()['paths']['support'] ?? '.larapilot/docs/support/', '/\\');
        $directory = $this->config->absolutePath($directory);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.'boogle.md';
        AtomicFile::write($path, $this->report($errors));

        return ['path' => $path, 'relative' => str_replace('\\', '/', $this->config->relativePath($path))];
    }

    /**
     * The project of Boogle this application is: the one named in the
     * configuration, the one the client package sends to, or the one at
     * the address of the application.
     *
     * What Boogle answers holds the key and the token the application
     * sends its exceptions with. They are compared here and dropped.
     *
     * @return array<string, mixed>|null
     */
    public function project(): ?array
    {
        $wanted = $this->wanted();
        $sendsWith = trim((string) config('larapilot.boogle.project_key', ''));
        $address = $this->address((string) config('app.url', ''));
        $projects = $this->client->pages('/projects', [], 8)['rows'];
        $picked = null;

        foreach ([
            static fn (array $project): bool => $wanted !== '' && (string) ($project['id'] ?? '') === $wanted,
            static fn (array $project): bool => $wanted !== '' && strcasecmp(trim((string) ($project['title'] ?? '')), $wanted) === 0,
            static fn (array $project): bool => $wanted === '' && $sendsWith !== '' && hash_equals($sendsWith, (string) ($project['key'] ?? '')),
            fn (array $project): bool => $wanted === '' && $address !== '' && ! in_array($address, ['localhost', '127.0.0.1'], true)
                && $this->address((string) ($project['url'] ?? '')) === $address,
        ] as $matches) {
            foreach ($projects as $project) {
                if (isset($project['id']) && $matches($project)) {
                    $picked = $project;

                    break 2;
                }
            }
        }

        if ($picked === null) {
            return null;
        }

        return [
            'id' => (string) $picked['id'],
            'title' => trim((string) ($picked['title'] ?? '')),
            'url' => trim((string) ($picked['url'] ?? '')),
            'group' => is_array($picked['group'] ?? null) ? trim((string) ($picked['group']['title'] ?? '')) : '',
            'uptime' => (bool) ($picked['uptime_enabled'] ?? false),
        ];
    }

    /**
     * @return array{project: array<string, mixed>, errors: list<array<string, mixed>>, fetched_at: string, truncated: bool}
     */
    protected function download(): array
    {
        $project = $this->project();

        if ($project === null) {
            throw new BoogleException(
                'No project of Boogle matches this application.',
                'Name it with LARAPILOT_BOOGLE_PROJECT (its id or its title), or set BOOGLE_PROJECT_KEY as the client package asks.'
            );
        }

        $occurrences = [];
        $truncated = false;

        foreach (self::OPEN as $status) {
            $read = $this->client->pages('/projects/'.rawurlencode($project['id']).'/exceptions', ['status' => $status], self::MAX_PAGES);
            $truncated = $truncated || $read['truncated'];

            foreach ($read['rows'] as $row) {
                if (isset($row['id'])) {
                    $occurrences[(string) $row['id']] = $this->normalize($row);
                }
            }
        }

        $data = [
            'project' => $project,
            'errors' => $this->group(array_values($occurrences)),
            'fetched_at' => now()->toIso8601String(),
            'truncated' => $truncated,
        ];

        Cache::put($this->cacheKey(), $data, max(30, (int) config('larapilot.boogle.cache_seconds', 300)));

        return $data;
    }

    /**
     * @return array{project: array<string, mixed>, errors: list<array<string, mixed>>, fetched_at: string, truncated: bool}
     */
    protected function cached(): array
    {
        $cached = Cache::get($this->cacheKey());

        return is_array($cached) && isset($cached['errors'], $cached['project'], $cached['fetched_at'])
            ? $cached
            : $this->download();
    }

    /**
     * One time an exception was thrown, without what it says about a
     * person.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function normalize(array $row): array
    {
        $class = trim((string) ($row['exception'] ?? '')) ?: 'Unknown exception';
        $code = trim((string) ($row['issue_code'] ?? ''));
        $prefix = strtoupper(trim((string) ($row['issue_prefix'] ?? '')));
        $http = is_array($row['http'] ?? null) ? $row['http'] : [];
        $file = $this->place((string) ($row['file'] ?? ''));
        $line = (int) ($row['line'] ?? 0);

        $outage = $prefix === 'OUT'
            || str_starts_with(strtoupper($code), '#OUT')
            || str_ends_with($class, 'ProjectOfflineException');

        $method = strtoupper(trim((string) ($http['method'] ?? '')));
        $path = $this->path((string) ($http['url'] ?? $http['fullUrl'] ?? $http['full_url'] ?? ''));

        return [
            'id' => (string) $row['id'],
            'code' => $code !== '' ? $code : null,
            'number' => (int) ($row['issue_number'] ?? 0),
            'kind' => $outage ? self::OUTAGE : self::ERROR,
            'class' => $class,
            'message' => $this->scrub((string) ($row['error'] ?? '')),
            'file' => $file,
            'line' => $line > 0 ? $line : null,
            'status' => strtoupper((string) ($row['status'] ?? 'OPEN')),
            'request' => $path !== '' ? trim(($method !== '' && preg_match('/^[A-Z]{3,7}$/', $method) === 1 ? $method : '').' '.$path) : null,
            'at' => $this->moment($row['created_at'] ?? null),
        ];
    }

    /**
     * The occurrences that are one bug, put together: the same exception
     * at the same line. An outage is one thing, however many times the
     * monitor found the application down.
     *
     * @param  list<array<string, mixed>>  $occurrences
     * @return list<array<string, mixed>>
     */
    protected function group(array $occurrences): array
    {
        $groups = [];

        foreach ($occurrences as $occurrence) {
            $signature = $occurrence['kind'] === self::OUTAGE
                ? self::OUTAGE.'|'.$occurrence['class']
                : self::ERROR.'|'.$occurrence['class'].'|'.$occurrence['file'].'|'.$occurrence['line'];

            $groups[substr(sha1($signature), 0, 10)][] = $occurrence;
        }

        $errors = [];

        foreach ($groups as $key => $of) {
            usort($of, static fn (array $a, array $b): int => strcmp((string) $b['at'], (string) $a['at']));

            $latest = $of[0];
            $requests = array_count_values(array_filter(array_column($of, 'request')));
            arsort($requests);

            $codes = array_values(array_filter(array_column($of, 'code')));
            usort($codes, 'strnatcasecmp');

            $errors[] = [
                'key' => (string) $key,
                'kind' => $latest['kind'],
                'class' => $latest['class'],
                'short' => (string) substr((string) strrchr('\\'.$latest['class'], '\\'), 1),
                'message' => $latest['message'],
                'file' => $latest['file'],
                'line' => $latest['line'],
                'where' => $latest['file'] !== '' ? $latest['file'].($latest['line'] !== null ? ':'.$latest['line'] : '') : null,
                'in_vendor' => str_starts_with($latest['file'], 'vendor/'),
                'request' => $requests === [] ? null : (string) array_key_first($requests),
                'requests' => count($requests),
                'codes' => $codes,
                'count' => count($of),
                'unseen' => count(array_filter($of, static fn (array $occurrence): bool => $occurrence['status'] === 'OPEN')),
                'first_seen' => end($of)['at'],
                'last_seen' => $latest['at'],
                'occurrences' => array_map(static fn (array $occurrence): array => [
                    'id' => $occurrence['id'],
                    'code' => $occurrence['code'],
                    'status' => $occurrence['status'],
                    'at' => $occurrence['at'],
                ], $of),
            ];
        }

        usort($errors, static fn (array $a, array $b): int => [$a['kind'] === self::OUTAGE ? 1 : 0, -$a['count'], $b['last_seen']]
            <=> [$b['kind'] === self::OUTAGE ? 1 : 0, -$b['count'], $a['last_seen']]);

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $error
     * @param  array<string, mixed>|null  $decision
     * @return array<string, mixed>
     */
    protected function decided(array $error, ?array $decision): array
    {
        $state = in_array($decision['state'] ?? null, [BoogleLedger::IN_BACKLOG, BoogleLedger::IGNORED], true)
            ? (string) $decision['state']
            : 'new';
        $spec = $state === BoogleLedger::IN_BACKLOG ? (string) ($decision['spec'] ?? '') : '';
        $found = $spec !== '' ? $this->specs->find($spec) : null;

        // A spec deleted from the backlog decides nothing any more.
        if ($state === BoogleLedger::IN_BACKLOG && $found === null) {
            $state = 'new';
            $spec = '';
        }

        // Closed in Boogle as fixed, and thrown again since: the fix did
        // not hold.
        $resolved = $state === BoogleLedger::IN_BACKLOG ? ($decision['resolved_at'] ?? null) : null;
        $returned = is_string($resolved) && $resolved !== ''
            && Carbon::parse($error['last_seen'])->greaterThan(Carbon::parse($resolved));

        return array_merge($error, [
            'state' => $state,
            'spec' => $spec !== '' ? $spec : null,
            'spec_status' => $found !== null ? (string) ($found['status'] ?? '') : null,
            'reason' => $state === BoogleLedger::IGNORED ? (string) ($decision['reason'] ?? '') : null,
            'decided_at' => $state !== 'new' ? ($decision['at'] ?? null) : null,
            'resolved_at' => $resolved,
            'returned' => $returned,
        ]);
    }

    /**
     * How many times the open errors were thrown on each of the last days,
     * the oldest day first. A day with nothing is a day too.
     *
     * @param  list<array<string, mixed>>  $bugs
     * @return list<array{date: string, count: int}>
     */
    protected function days(array $bugs, int $span = 14): array
    {
        $days = [];

        for ($back = $span - 1; $back >= 0; $back--) {
            $days[now()->subDays($back)->format('Y-m-d')] = 0;
        }

        foreach ($bugs as $error) {
            foreach ($error['occurrences'] as $occurrence) {
                $day = Carbon::parse($occurrence['at'])->format('Y-m-d');

                if (isset($days[$day])) {
                    $days[$day]++;
                }
            }
        }

        $list = [];

        foreach ($days as $date => $count) {
            $list[] = ['date' => (string) $date, 'count' => $count];
        }

        return $list;
    }

    /**
     * What is open, in a sentence.
     *
     * @param  list<array<string, mixed>>  $bugs
     * @param  list<array<string, mixed>>  $outages
     */
    protected function summary(array $bugs, array $outages): string
    {
        $down = array_sum(array_column($outages, 'count'));
        $outage = $down === 0 ? '' : ' The monitor found the application down '.$down.' '.($down === 1 ? 'time' : 'times').'.';

        if ($bugs === []) {
            return 'Boogle holds no open error for this application.'.$outage;
        }

        $new = count(array_filter($bugs, static fn (array $error): bool => $error['state'] === 'new'));
        $returned = count(array_filter($bugs, static fn (array $error): bool => $error['returned']));
        $thrown = array_sum(array_column($bugs, 'count'));

        $sentence = count($bugs).' open '.(count($bugs) === 1 ? 'error' : 'errors').', thrown '.$thrown.' '.($thrown === 1 ? 'time' : 'times').': '
            .($new === 0 ? 'a decision was taken about every one' : $new.' with no decision yet').'.';

        if ($returned > 0) {
            $sentence .= ' '.$returned.' came back after '.($returned === 1 ? 'its' : 'their').' fix.';
        }

        return $sentence.$outage;
    }

    /**
     * @param  list<array<string, mixed>>  $errors
     * @param  array{new?: bool, kind?: string|null, limit?: int|null}  $filters
     * @return list<array<string, mixed>>
     */
    protected function filter(array $errors, array $filters): array
    {
        $kind = strtolower(trim((string) ($filters['kind'] ?? '')));

        if ($kind !== '' && ! in_array($kind, [self::ERROR, self::OUTAGE], true)) {
            throw new InvalidArgumentException('Unknown kind "'.$kind.'". Use error or outage.');
        }

        $errors = array_filter($errors, static function (array $error) use ($filters, $kind): bool {
            if ($kind !== '' && $error['kind'] !== $kind) {
                return false;
            }

            // An error that came back is to be looked at again, whatever
            // was decided.
            return empty($filters['new']) || $error['state'] === 'new' || $error['returned'];
        });

        $limit = (int) ($filters['limit'] ?? 0);

        return array_values($limit > 0 ? array_slice($errors, 0, $limit) : $errors);
    }

    /**
     * The open errors named, by a code of Boogle or by their key. A
     * decision is about an error Boogle holds: a name it does not know is
     * a slip of the hand.
     *
     * @param  list<string>  $names
     * @return list<array<string, mixed>>
     */
    protected function known(array $names): array
    {
        $names = $this->names($names);

        $find = static function (array $data) use ($names): array {
            $found = [];
            $missing = [];

            foreach ($names as $name) {
                $match = null;

                foreach ($data['errors'] as $error) {
                    $codes = array_map('strtoupper', $error['codes']);

                    if ($error['key'] === strtolower($name) || in_array(strtoupper($name), $codes, true) || in_array('#'.strtoupper($name), $codes, true)) {
                        $match = $error;

                        break;
                    }
                }

                if ($match === null) {
                    $missing[] = $name;
                } else {
                    $found[$match['key']] = $match;
                }
            }

            return [array_values($found), $missing];
        };

        [$found, $missing] = $find($this->cached());

        // What was read a few minutes ago may not hold an error of this
        // morning: before refusing a name, Boogle is asked again.
        if ($missing !== []) {
            [$found, $missing] = $find($this->download());
        }

        if ($missing !== []) {
            throw new InvalidArgumentException('Boogle holds no open error '.implode(', ', $missing).' for this project.');
        }

        return $found;
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    protected function names(array $names): array
    {
        $clean = [];

        foreach ($names as $name) {
            $name = trim((string) $name);

            if ($name === '') {
                continue;
            }

            if (preg_match('/^#?[A-Za-z0-9][A-Za-z0-9_-]{0,40}$/', $name) !== 1) {
                throw new InvalidArgumentException('An error is named by a code of Boogle, such as #BUG12, or by its key.');
            }

            $clean[] = $name;
        }

        if ($clean === []) {
            throw new InvalidArgumentException('Name at least one error, by a code of Boogle such as #BUG12.');
        }

        return array_values(array_unique($clean));
    }

    /**
     * What the ledger keeps of an error: enough to know it again, nothing
     * an occurrence says.
     *
     * @param  array<string, mixed>  $error
     * @return array<string, mixed>
     */
    protected function facts(array $error): array
    {
        return [
            'kind' => $error['kind'],
            'class' => $error['class'],
            'where' => $error['where'],
            'codes' => array_slice($error['codes'], 0, 20),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $errors
     * @return list<string>
     */
    protected function codes(array $errors): array
    {
        $codes = [];

        foreach ($errors as $error) {
            foreach ($error['codes'] as $code) {
                $codes[$code] = true;
            }
        }

        return array_keys($codes);
    }

    /**
     * @return array<string, mixed>
     */
    protected function projectRef(): array
    {
        $cached = Cache::get($this->cacheKey());
        $project = is_array($cached['project'] ?? null) ? $cached['project'] : [];

        return $project === [] ? [] : ['id' => $project['id'], 'title' => $project['title']];
    }

    /**
     * A file of the server as a file of the repository. What comes before
     * the application in the path is where it was deployed: the longest
     * end of the path that is a file here is the file that was meant.
     */
    protected function place(string $file): string
    {
        $file = trim(str_replace('\\', '/', trim($file)), '/');

        if ($file === '') {
            return '';
        }

        $segments = explode('/', $file);

        if (in_array('..', $segments, true) || in_array('.', $segments, true)) {
            return basename($file);
        }

        $root = rtrim($this->config->projectRoot(), '/\\');

        foreach (array_keys($segments) as $from) {
            $end = implode('/', array_slice($segments, $from));

            if (is_file($root.'/'.$end)) {
                return $end;
            }
        }

        // Not a file of this checkout: a package that is not installed
        // here, or code that was since moved. Cut at the first folder an
        // application has.
        if (preg_match('#(?:^|/)(vendor/.+)$#', $file, $match) === 1
            || preg_match('#(?:^|/)((?:'.self::ROOTS.')/.+)$#', $file, $match) === 1) {
            return $match[1];
        }

        return basename($file);
    }

    /**
     * The path of a request, without the host and without the query
     * string, where a token or an address can be.
     */
    protected function path(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $path = parse_url($url, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        // What identifies a person or a record is not needed to know the route.
        $path = (string) preg_replace('#/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}(?=/|$)#i', '/{id}', $path);
        $path = (string) preg_replace('#/[A-Za-z0-9_-]{32,}(?=/|$)#', '/{token}', $path);
        $path = (string) preg_replace('#/[^/]*@[^/]*(?=/|$)#', '/{address}', $path);

        return mb_substr($path, 0, 200);
    }

    /**
     * A message without the addresses and the long secrets it may quote.
     */
    protected function scrub(string $message): string
    {
        $message = trim((string) preg_replace('/\s+/', ' ', $message));
        $message = (string) preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '[address]', $message);
        $message = (string) preg_replace('/\b(?:Bearer\s+)?[A-Za-z0-9_\-]{40,}\b/', '[secret]', $message);

        return mb_strlen($message) > 400 ? mb_substr($message, 0, 400).'…' : $message;
    }

    protected function moment(mixed $value): string
    {
        try {
            return Carbon::parse(is_string($value) && $value !== '' ? $value : 'now')->toIso8601String();
        } catch (\Throwable) {
            return now()->toIso8601String();
        }
    }

    protected function wanted(): string
    {
        return trim((string) config('larapilot.boogle.project', ''));
    }

    /**
     * An address reduced to its host, so the same application reads the
     * same with and without `www.`, over http and https.
     */
    protected function address(string $url): string
    {
        $host = parse_url(str_contains($url, '://') ? $url : 'https://'.$url, PHP_URL_HOST);

        return is_string($host) ? (string) preg_replace('/^www\./', '', strtolower($host)) : '';
    }

    protected function inline(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    protected function cacheKey(): string
    {
        return 'larapilot.boogle.errors.'.sha1($this->client->host().'|'.$this->config->projectRoot().'|'.$this->wanted());
    }
}
