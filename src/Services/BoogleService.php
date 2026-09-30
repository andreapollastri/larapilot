<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Larapilot\Services\Boogle\BoogleException;
use Larapilot\Services\Boogle\BoogleLedger;
use Larapilot\Services\Errors\ErrorTrackerDriver;
use Larapilot\Services\Errors\ErrorTrackerException;
use Larapilot\Services\Errors\ErrorTrackerManager;
use Larapilot\Support\AtomicFile;

/**
 * The errors of production, set against the backlog.
 *
 * One tracker at a time records what the running application throws —
 * Boogle, Sentry, Bugsnag, Flare, Datadog, Rollbar, Honeybadger, or the
 * logs on CloudWatch — and a driver reads it back. Larapilot puts the
 * occurrences together into bugs — the same exception at the same line,
 * or the group the tracker made — and says what was already decided
 * about each. Turning a bug into work is the job of `/larapilot-boogle`,
 * which hands it to triage.
 *
 * What an occurrence says about a person stays in the tracker: the
 * user, the query string, and the payload of the request are never kept,
 * written, or shown, and an address in a message is masked.
 */
class BoogleService
{
    /** Thrown by the code. */
    public const ERROR = 'error';

    /** Recorded by the uptime monitor: the application did not answer. */
    public const OUTAGE = 'outage';

    /**
     * The words an error is closed with in Boogle; another tracker has
     * one way to close, and the word is not sent.
     *
     * @var list<string>
     */
    public const CLOSED = ['FIXED', 'DONE'];

    public function __construct(
        protected ConfigService $config,
        protected SpecService $specs,
        protected ErrorTrackerManager $manager,
        protected BoogleLedger $ledger,
    ) {}

    /**
     * The driver of the tracker the project chose. Without one, the
     * failure says how to pick it.
     */
    protected function driver(): ErrorTrackerDriver
    {
        try {
            return $this->manager->driver();
        } catch (ErrorTrackerException $e) {
            throw new BoogleException($e->getMessage(), $e->hint(), $e->status());
        }
    }

    /**
     * Whether the tracker can be read, and what is missing when it cannot.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $enabled = $this->config->errorsEnabled();
        $provider = $this->manager->configured();
        $driver = $this->manager->tryDriver();
        $configured = $driver?->configured() ?? false;
        $hints = [];
        $project = null;
        $authenticated = false;
        $error = null;

        if (! $enabled) {
            $hints[] = 'Enable with: php artisan larapilot:settings-set --errors=YES --errors-provider='.($provider !== '' ? $provider : 'boogle')
                .' (one of '.implode(', ', $this->manager->available()).')';
        }

        if ($enabled && $driver === null) {
            $hints[] = ($provider === '' ? 'No errors provider is set.' : 'Unknown errors provider "'.$provider.'".')
                .' Pick one of '.implode(', ', $this->manager->available()).': php artisan larapilot:settings-set --errors-provider=boogle';
        }

        if ($driver !== null) {
            foreach ($driver->missingConfig() as $hint) {
                $hints[] = $hint;
            }
        }

        // A tracker is asked only while the errors are on.
        if ($enabled && $configured && $driver !== null) {
            try {
                $project = $this->project();

                if ($project === null) {
                    $hints[] = 'No project of '.$driver->label().' matches this application. '.$driver->projectHint();
                } elseif (! $driver->readsProjectFromTracker()) {
                    // The project was read from .env: asking for the errors
                    // is what proves the credentials are taken. What is
                    // answered is kept for the read that follows.
                    $this->cached();
                }

                $authenticated = true;
            } catch (BoogleException $e) {
                $error = $e->getMessage();
                // An answer that is not a refusal means the credentials were taken.
                $authenticated = ! in_array($e->status(), [0, 401, 403], true);
                $hints[] = trim($e->getMessage().' '.($e->hint() ?? ''));
            }
        }

        return [
            'enabled' => $enabled,
            'provider' => $provider,
            'provider_label' => $driver?->label(),
            'configured' => $configured,
            'authenticated' => $authenticated,
            'host' => $driver?->host() ?? '',
            'project' => $project,
            'granularity' => $driver?->granularity(),
            'remote_resolve' => $driver?->supportsRemoteResolve() ?? false,
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
        $granularity = (string) ($data['granularity'] ?? ErrorTrackerDriver::OCCURRENCE);
        $decisions = $this->ledger->all();
        $errors = [];

        foreach ($data['errors'] as $error) {
            $errors[] = $this->decided($error, $decisions[$error['key']] ?? null);
        }

        $open = array_column($errors, 'key');
        $closed = [];

        // Decided here, and no longer open in the tracker: fixed, or closed there.
        foreach ($decisions as $key => $decision) {
            if (! in_array($key, $open, true)) {
                $closed[] = array_merge(['key' => $key], $decision);
            }
        }

        $bugs = array_values(array_filter($errors, static fn (array $error): bool => $error['kind'] === self::ERROR));
        $outages = array_values(array_filter($errors, static fn (array $error): bool => $error['kind'] === self::OUTAGE));

        return [
            'project' => $data['project'],
            'provider' => (string) ($data['project']['provider'] ?? $this->manager->configured()),
            'provider_label' => $this->manager->label(),
            'granularity' => $granularity,
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
            // A tracker that answers one row for each bug says when it was
            // last thrown, not each time: the days are left to the ones
            // that record every throw.
            'days' => $granularity === ErrorTrackerDriver::OCCURRENCE ? $this->days($bugs) : [],
            'total' => count($errors),
            'errors' => $this->filter($errors, $filters),
            'closed' => $closed,
            'ledger' => $this->ledger->relativePath(),
        ];
    }

    /**
     * Record that errors are in the backlog as a spec.
     *
     * @param  list<string>  $names  codes (`#BUG12`) or keys of errors
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
     * Tell the tracker the errors are fixed: every open occurrence of each
     * one is closed there, with a line that says what fixed it. This
     * writes to the tracker, so it is never done without being asked for,
     * and only where the tracker allows it.
     *
     * @param  list<string>  $names
     * @return array<string, mixed>
     */
    public function resolve(array $names, string $status = 'FIXED', ?string $comment = null): array
    {
        $status = strtoupper(trim($status));

        if (! in_array($status, self::CLOSED, true)) {
            throw new InvalidArgumentException('An error is closed as FIXED or DONE.');
        }

        $driver = $this->driver();

        if (! $driver->supportsRemoteResolve()) {
            throw new BoogleException(
                $driver->label().' cannot close an error from Larapilot.',
                'Close it in '.$driver->label().' itself; what was decided here stays in the ledger.'
            );
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
                try {
                    $driver->resolveOccurrence($occurrence, $status, $line, $project);
                } catch (ErrorTrackerException $e) {
                    throw new BoogleException($e->getMessage(), $e->hint(), $e->status());
                }

                $closed[] = $occurrence['code'] ?? $occurrence['id'];
            }

            if ($decision !== []) {
                $this->ledger->note($error['key'], ['resolved_at' => now()->toIso8601String()]);
            }
        }

        // What was read is no longer what the tracker holds.
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
        $label = $this->manager->label();
        $lines = ['# '.$label.' errors'.(($project['title'] ?? '') !== '' ? ' — '.$project['title'] : ''), ''];
        $lines[] = 'Open errors as '.$label.' held them on '.Carbon::parse($errors['fetched_at'])->format('Y-m-d H:i').', one entry for each bug, with what was decided about it.';
        $lines[] = 'The user, the query string, and the payload of a request stay in '.$label.'.';
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
            $lines[] = '## No longer open in '.$label;
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

        $path = $directory.DIRECTORY_SEPARATOR.'errors.md';
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
        try {
            return $this->driver()->project();
        } catch (ErrorTrackerException $e) {
            throw new BoogleException($e->getMessage(), $e->hint(), $e->status());
        }
    }

    /**
     * @return array{project: array<string, mixed>, errors: list<array<string, mixed>>, fetched_at: string, truncated: bool}
     */
    protected function download(): array
    {
        try {
            $data = $this->driver()->download();
        } catch (ErrorTrackerException $e) {
            throw new BoogleException($e->getMessage(), $e->hint(), $e->status());
        }

        Cache::put(
            $this->cacheKey(),
            $data,
            max(30, (int) config('larapilot.errors.cache_seconds', 300))
        );

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
            return $this->manager->label().' holds no open error for this application.'.$outage;
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
     * The open errors named, by their code or by their key. A decision is
     * about an error the tracker holds: a name it does not know is a slip
     * of the hand.
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
        // morning: before refusing a name, the tracker is asked again.
        if ($missing !== []) {
            [$found, $missing] = $find($this->download());
        }

        if ($missing !== []) {
            throw new InvalidArgumentException($this->manager->label().' holds no open error '.implode(', ', $missing).' for this project.');
        }

        $decisions = $this->ledger->all();

        return array_map(
            fn (array $error): array => $this->decided($error, $decisions[$error['key']] ?? null),
            $found
        );
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
                throw new InvalidArgumentException('An error is named by its code, such as #BUG12, or by its key.');
            }

            $clean[] = $name;
        }

        if ($clean === []) {
            throw new InvalidArgumentException('Name at least one error, by its code such as #BUG12.');
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

    protected function inline(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Resolution groups for bugs the user confirmed: the same exception in
     * the same folder of the application go together, so one spec fixes
     * them; outages and errors in a package never merge with application
     * code or with each other, and a bug with no place in the code stays
     * alone.
     *
     * @param  list<string>  $names  codes or keys
     * @return array<string, mixed>
     */
    public function resolutionPlan(array $names): array
    {
        $errors = $this->known($names);

        usort($errors, static fn (array $a, array $b): int => [$b['returned'], $b['count'], $b['last_seen']] <=> [$a['returned'], $a['count'], $a['last_seen']]);

        $groups = $this->resolutionGroups($errors);
        $domains = [];

        foreach ($groups as $group) {
            $label = (string) $group['domain_label'];

            if (! isset($domains[$label])) {
                $domains[$label] = [
                    'domain' => $group['domain'],
                    'domain_label' => $label,
                    'group_keys' => [],
                    'codes' => [],
                ];
            }

            $domains[$label]['group_keys'][] = $group['key'];
            $domains[$label]['codes'] = array_values(array_unique([...$domains[$label]['codes'], ...$group['codes']]));
        }

        return [
            'provider' => $this->config->errorsProvider(),
            'codes' => $this->codes($errors),
            'domains' => array_values($domains),
            'groups' => $groups,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $errors
     * @return list<array<string, mixed>>
     */
    protected function resolutionGroups(array $errors): array
    {
        $buckets = [];

        foreach ($errors as $error) {
            $buckets[$this->resolutionSignature($error)][] = $error;
        }

        $groups = [];

        foreach ($buckets as $signature => $of) {
            usort($of, static fn (array $a, array $b): int => [$b['count'], $b['last_seen']] <=> [$a['count'], $a['last_seen']]);

            $lead = $of[0];
            $codes = [];
            $keys = [];
            $where = [];
            $requests = [];
            $thrown = 0;
            $returned = false;

            foreach ($of as $error) {
                $keys[] = $error['key'];
                $codes = [...$codes, ...$error['codes']];
                $thrown += (int) $error['count'];
                $returned = $returned || ($error['returned'] ?? false);

                if (($error['where'] ?? '') !== '') {
                    $where[] = $error['where'];
                }

                if (($error['request'] ?? '') !== '') {
                    $requests[] = $error['request'];
                }
            }

            $codes = array_values(array_unique($codes));
            usort($codes, 'strnatcasecmp');
            $where = array_values(array_unique($where));
            $requests = array_values(array_unique($requests));

            $title = count($of) === 1
                ? (string) $lead['short'].' — '.$this->inline((string) $lead['message'])
                : (string) $lead['short'].' (+'.(count($of) - 1).' more in the same area)';

            $groups[] = [
                'key' => substr(sha1($signature), 0, 8),
                'keys' => array_values(array_unique($keys)),
                'codes' => $codes,
                'kind' => (string) $lead['kind'],
                'domain' => $this->domain($lead),
                'domain_label' => $this->domainLabel($lead),
                'class' => (string) $lead['class'],
                'short' => (string) $lead['short'],
                'message' => (string) $lead['message'],
                'where' => $where,
                'requests' => $requests,
                'thrown' => $thrown,
                'returned' => $returned,
                'title' => $title,
                'reason' => count($of) === 1
                    ? 'One bug — not merged with others.'
                    : 'Same exception in the same part of the codebase — one triage handoff avoids duplicate specs.',
            ];
        }

        usort($groups, static fn (array $a, array $b): int => [$b['returned'], $b['thrown'], $a['codes'][0] ?? ''] <=> [$a['returned'], $a['thrown'], $b['codes'][0] ?? '']);

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $error
     */
    protected function resolutionSignature(array $error): string
    {
        if ($error['kind'] === self::OUTAGE) {
            return 'outage|'.(string) $error['class'];
        }

        if ($error['in_vendor'] ?? false) {
            return 'vendor|'.(string) $error['class'].'|'.(string) ($error['file'] ?? '').'|'.(string) ($error['line'] ?? '');
        }

        $file = (string) ($error['file'] ?? '');

        // A tracker that gives no place in the code gives nothing to put
        // two bugs together by: each one stays alone.
        if ($file === '') {
            return 'app|'.(string) $error['class'].'|#'.(string) $error['key'];
        }

        $dir = dirname($file);

        return 'app|'.(string) $error['class'].'|'.($dir === '.' ? '_root' : $dir);
    }

    /**
     * @param  array<string, mixed>  $error
     */
    protected function domain(array $error): string
    {
        if ($error['kind'] === self::OUTAGE) {
            return 'outage';
        }

        return ($error['in_vendor'] ?? false) ? 'package' : 'application';
    }

    /**
     * @param  array<string, mixed>  $error
     */
    protected function domainLabel(array $error): string
    {
        return match ($this->domain($error)) {
            'outage' => 'Outage',
            'package' => 'Package',
            default => 'Application code',
        };
    }

    protected function cacheKey(): string
    {
        return $this->driver()->cacheKey($this->config->projectRoot());
    }
}
