<?php

declare(strict_types=1);

namespace Larapilot\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Larapilot\Support\AtomicFile;
use Larapilot\Support\SpecBlockers;
use Symfony\Component\Yaml\Yaml;

class UsageService
{
    /**
     * @var list<string>
     */
    public const CATEGORIES = [
        'analysis',
        'planning',
        'implementation',
        'support',
        'feature',
        'review',
        'ship',
        'other',
    ];

    /**
     * Hours of work in a delivery day, and the hours a story point stands for
     * while its spec has no plan.
     */
    protected const HOURS_PER_DAY = 6.0;

    protected const HOURS_PER_POINT = 4.0;

    public function __construct(
        protected ConfigService $config,
        protected SpecService $specs,
        protected PlanService $plans,
        protected ReleaseService $releases,
    ) {}

    public function usageDirectory(): string
    {
        $config = $this->config->resolve();

        return $this->config->absolutePath($config['paths']['usage'] ?? '.larapilot/usage/');
    }

    public function ledgerPath(): string
    {
        return rtrim($this->usageDirectory(), '/\\').DIRECTORY_SEPARATOR.'ledger.jsonl';
    }

    public function schedulePath(): string
    {
        $config = $this->config->resolve();

        return $this->config->absolutePath($config['paths']['schedule'] ?? '.larapilot/usage/schedule.yaml');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function log(array $attributes): array
    {
        $category = strtolower(trim((string) ($attributes['category'] ?? 'other')));

        if (! in_array($category, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException(
                'Invalid category. Allowed: '.implode(', ', self::CATEGORIES)
            );
        }

        $entry = [
            'id' => bin2hex(random_bytes(8)),
            'ts' => $this->normalizeTimestamp($attributes['ts'] ?? null),
            'user' => trim((string) ($attributes['user'] ?? $this->detectUser())),
            'category' => $category,
            'tokens' => max(0, (int) ($attributes['tokens'] ?? 0)),
            'minutes' => max(0.0, (float) ($attributes['minutes'] ?? 0)),
            'skill' => trim((string) ($attributes['skill'] ?? '')) ?: null,
            'spec' => $this->normalizeSpec($attributes['spec'] ?? null),
            'note' => trim((string) ($attributes['note'] ?? '')) ?: null,
            'estimated' => (bool) ($attributes['estimated'] ?? false),
        ];

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($line === false) {
            throw new \RuntimeException('Unable to encode usage ledger entry.');
        }

        $path = $this->ledgerPath();
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Unable to create directory {$directory}.");
        }

        if (@file_put_contents($path, $line.PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException("Unable to append usage ledger at {$path}.");
        }

        return $entry;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function entries(): array
    {
        $path = $this->ledgerPath();

        if (! is_file($path)) {
            return [];
        }

        $entries = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $entries[] = $decoded;
            }
        }

        return $entries;
    }

    /**
     * Filter ledger entries for Lucille queries.
     *
     * @param  array{
     *     category?: string|null,
     *     user?: string|null,
     *     skill?: string|null,
     *     spec?: string|null,
     *     from?: string|null,
     *     to?: string|null,
     *     limit?: int|null
     * }  $filters
     * @return list<array<string, mixed>>
     */
    public function query(array $filters = []): array
    {
        $category = isset($filters['category']) && is_string($filters['category']) && trim($filters['category']) !== ''
            ? strtolower(trim($filters['category']))
            : null;
        $userNeedle = isset($filters['user']) && is_string($filters['user']) && trim($filters['user']) !== ''
            ? strtolower(trim($filters['user']))
            : null;
        $skillNeedle = isset($filters['skill']) && is_string($filters['skill']) && trim($filters['skill']) !== ''
            ? strtolower(trim($filters['skill']))
            : null;
        $spec = isset($filters['spec']) ? $this->normalizeSpec($filters['spec']) : null;
        $from = $this->normalizeDateBoundary($filters['from'] ?? null, false);
        $to = $this->normalizeDateBoundary($filters['to'] ?? null, true);
        $limit = isset($filters['limit']) ? max(0, (int) $filters['limit']) : 0;

        $entries = array_values(array_filter(
            $this->entries(),
            function (array $entry) use ($category, $userNeedle, $skillNeedle, $spec, $from, $to): bool {
                if ($category !== null && strtolower((string) ($entry['category'] ?? '')) !== $category) {
                    return false;
                }

                if ($userNeedle !== null && ! str_contains(strtolower((string) ($entry['user'] ?? '')), $userNeedle)) {
                    return false;
                }

                if ($skillNeedle !== null && ! str_contains(strtolower((string) ($entry['skill'] ?? '')), $skillNeedle)) {
                    return false;
                }

                if ($spec !== null && strtoupper((string) ($entry['spec'] ?? '')) !== $spec) {
                    return false;
                }

                $ts = (string) ($entry['ts'] ?? '');

                if ($from !== null && ($ts === '' || $ts < $from)) {
                    return false;
                }

                if ($to !== null && ($ts === '' || $ts > $to)) {
                    return false;
                }

                return true;
            }
        ));

        if ($limit > 0 && count($entries) > $limit) {
            $entries = array_slice($entries, -$limit);
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters = []): array
    {
        $entries = $filters === [] ? $this->entries() : $this->query($filters);
        $byCategory = [];
        $byUser = [];
        $bySkill = [];
        $bySpec = [];
        $byDay = [];
        $totalTokens = 0;
        $totalMinutes = 0.0;
        $estimatedCount = 0;

        foreach (self::CATEGORIES as $category) {
            $byCategory[$category] = ['entries' => 0, 'tokens' => 0, 'minutes' => 0.0];
        }

        foreach ($entries as $entry) {
            $category = (string) ($entry['category'] ?? 'other');
            $tokens = (int) ($entry['tokens'] ?? 0);
            $minutes = (float) ($entry['minutes'] ?? 0);
            $user = (string) ($entry['user'] ?? 'unknown');
            $skill = (string) ($entry['skill'] ?? '—');
            $specCode = (string) ($entry['spec'] ?? '—');
            $day = substr((string) ($entry['ts'] ?? ''), 0, 10) ?: 'unknown';

            if (! isset($byCategory[$category])) {
                $byCategory[$category] = ['entries' => 0, 'tokens' => 0, 'minutes' => 0.0];
            }

            $byCategory[$category]['entries']++;
            $byCategory[$category]['tokens'] += $tokens;
            $byCategory[$category]['minutes'] += $minutes;

            $this->accumulateBucket($byUser, $user, $tokens, $minutes);
            $this->accumulateBucket($bySkill, $skill, $tokens, $minutes);
            $this->accumulateBucket($bySpec, $specCode, $tokens, $minutes);
            $this->accumulateBucket($byDay, $day, $tokens, $minutes);

            if (($entry['estimated'] ?? false) === true) {
                $estimatedCount++;
            }

            $totalTokens += $tokens;
            $totalMinutes += $minutes;
        }

        ksort($byDay);

        return [
            'entry_count' => count($entries),
            'total_tokens' => $totalTokens,
            'total_minutes' => round($totalMinutes, 2),
            'total_hours' => round($totalMinutes / 60, 2),
            'avg_minutes_per_entry' => count($entries) > 0 ? round($totalMinutes / count($entries), 2) : 0.0,
            'estimated_entry_count' => $estimatedCount,
            'by_category' => $byCategory,
            'by_user' => $byUser,
            'by_skill' => $bySkill,
            'by_spec' => $bySpec,
            'by_day' => $byDay,
            'filters' => array_filter($filters, static fn (mixed $value): bool => $value !== null && $value !== ''),
            'ledger_path' => $this->config->relativePath($this->ledgerPath()),
            'schedule_path' => $this->config->relativePath($this->schedulePath()),
        ];
    }

    /**
     * High-signal answers for Lucille's interrogation skill.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function insights(array $filters = []): array
    {
        $summary = $this->summary($filters);
        $schedule = $this->schedule();
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');

        $topCategories = [];

        foreach ($summary['by_category'] as $category => $row) {
            if (($row['entries'] ?? 0) === 0) {
                continue;
            }

            $topCategories[] = [
                'category' => (string) $category,
                'minutes' => round((float) $row['minutes'], 2),
                'tokens' => (int) $row['tokens'],
                'entries' => (int) $row['entries'],
                'share_minutes' => $summary['total_minutes'] > 0
                    ? round(((float) $row['minutes'] / $summary['total_minutes']) * 100, 1)
                    : 0.0,
            ];
        }

        usort($topCategories, static fn (array $a, array $b): int => $b['minutes'] <=> $a['minutes']);

        $deadlineViews = [];

        foreach ($schedule['deadlines'] as $deadline) {
            if (! is_array($deadline) || empty($deadline['date'])) {
                continue;
            }

            $date = (string) $deadline['date'];
            $days = (new DateTimeImmutable($date))->diff(new DateTimeImmutable($today))->days;
            $past = $date < $today;

            $deadlineViews[] = [
                'label' => (string) ($deadline['label'] ?? 'Deadline'),
                'date' => $date,
                'status' => (string) ($deadline['status'] ?? 'on_track'),
                'note' => $deadline['note'] ?? null,
                'days_until' => $past ? -$days : $days,
                'overdue' => $past && ($deadline['status'] ?? '') !== 'done',
            ];
        }

        usort($deadlineViews, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        $hotSpecs = [];

        foreach ($summary['by_spec'] as $code => $row) {
            if ($code === '—' || ($row['entries'] ?? 0) === 0) {
                continue;
            }

            $hotSpecs[] = [
                'spec' => (string) $code,
                'minutes' => round((float) $row['minutes'], 2),
                'tokens' => (int) $row['tokens'],
                'entries' => (int) $row['entries'],
            ];
        }

        usort($hotSpecs, static fn (array $a, array $b): int => $b['minutes'] <=> $a['minutes']);
        $hotSpecs = array_slice($hotSpecs, 0, 5);

        $gantt = $this->gantt();

        return [
            'summary' => $summary,
            'top_categories' => $topCategories,
            'hot_specs' => $hotSpecs,
            'deadlines' => $deadlineViews,
            'schedule_notes' => $schedule['notes'],
            'at_risk_or_delayed' => array_values(array_filter(
                $deadlineViews,
                static fn (array $row): bool => in_array($row['status'], ['at_risk', 'delayed'], true) || $row['overdue'] === true
            )),
            'criticality' => $this->criticality($gantt),
            'zoey' => $this->zoeyReconciliation(),
            'gantt' => $gantt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function schedule(): array
    {
        $path = $this->schedulePath();

        if (! is_file($path)) {
            return [
                'deadlines' => [],
                'notes' => [],
                'updated_at' => null,
            ];
        }

        $parsed = Yaml::parseFile($path);

        if (! is_array($parsed)) {
            return [
                'deadlines' => [],
                'notes' => [],
                'updated_at' => null,
            ];
        }

        return [
            'deadlines' => array_values(array_filter(
                $parsed['deadlines'] ?? [],
                static fn (mixed $row): bool => is_array($row)
            )),
            'notes' => array_values(array_filter(
                $parsed['notes'] ?? [],
                static fn (mixed $row): bool => is_array($row)
            )),
            'updated_at' => $parsed['updated_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function setDeadline(array $attributes): array
    {
        $schedule = $this->schedule();
        $label = trim((string) ($attributes['label'] ?? 'Deadline'));
        $date = trim((string) ($attributes['deadline'] ?? ''));

        if ($date === '' || preg_match('/^\d{4}-\d{2}-\d{2}/', $date) !== 1) {
            throw new \InvalidArgumentException('deadline must be a date (YYYY-MM-DD).');
        }

        $deadline = [
            'id' => bin2hex(random_bytes(6)),
            'label' => $label !== '' ? $label : 'Deadline',
            'date' => substr($date, 0, 10),
            'status' => $this->normalizeStatus($attributes['status'] ?? 'on_track'),
            'note' => trim((string) ($attributes['note'] ?? '')) ?: null,
        ];

        if (trim((string) ($attributes['release'] ?? '')) !== '') {
            $deadline['release'] = $this->releaseVersion((string) $attributes['release']);
        }

        $schedule['deadlines'][] = $deadline;
        $this->writeSchedule($schedule);

        return $deadline;
    }

    /**
     * The version of a release as the release plan writes it.
     *
     * @throws \InvalidArgumentException When no release has it.
     */
    public function releaseVersion(string $version): string
    {
        $release = $this->releases->find($version);

        if ($release === null) {
            throw new \InvalidArgumentException("No release {$version} in the release plan.");
        }

        return (string) $release['version'];
    }

    /**
     * The day the last spec of a release is forecast to be done. Null when
     * the release is unknown, or the chart has none of its specs.
     *
     * @param  array<string, mixed>  $gantt
     */
    public function releaseForecast(string $version, array $gantt): ?string
    {
        try {
            $release = $this->releases->find($version);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $codes = array_flip(array_map('strval', is_array($release['specs'] ?? null) ? $release['specs'] : []));
        $end = null;

        foreach ($gantt['bars'] ?? [] as $bar) {
            if (($bar['type'] ?? '') !== 'epic' && isset($codes[explode('·', (string) $bar['id'], 2)[0]])) {
                $end = max($end ?? $bar['end'], $bar['end']);
            }
        }

        return $end;
    }

    /**
     * The milestones as a re-plan leaves them: moved, added, removed. The
     * notes stay as they are.
     *
     * @param  list<array<string, mixed>>  $deadlines
     */
    public function replaceDeadlines(array $deadlines): void
    {
        $schedule = $this->schedule();
        $schedule['deadlines'] = array_values($deadlines);

        $this->writeSchedule($schedule);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function addScheduleNote(array $attributes): array
    {
        $schedule = $this->schedule();
        $note = [
            'id' => bin2hex(random_bytes(6)),
            'ts' => $this->normalizeTimestamp(null),
            'status' => $this->normalizeStatus($attributes['status'] ?? 'on_track'),
            'message' => trim((string) ($attributes['note'] ?? $attributes['message'] ?? '')),
        ];

        if ($note['message'] === '') {
            throw new \InvalidArgumentException('Schedule note message is required.');
        }

        $schedule['notes'][] = $note;
        $this->writeSchedule($schedule);

        return $note;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function reportMarkdown(array $filters = []): string
    {
        $summary = $this->summary($filters);
        $schedule = $this->schedule();
        $generated = (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM);

        $filterLine = $summary['filters'] === []
            ? '_No filters — full ledger._'
            : 'Filters: `'.json_encode($summary['filters'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'`';

        $lines = [
            '# Larapilot usage report',
            '',
            'Generated: '.$generated,
            '',
            $filterLine,
            '',
            '## Totals',
            '',
            '- **Entries:** '.$summary['entry_count'],
            '- **Tokens:** '.$this->formatTokens((int) $summary['total_tokens']).' ('.$summary['total_tokens'].')',
            '- **Hours:** '.$summary['total_hours'],
            '- **Estimated entries:** '.$summary['estimated_entry_count'],
            '',
            '## Zoey vs Lucille',
            '',
        ];

        $zoey = $this->zoeyReconciliation();
        $lines[] = '- **Ledger tokens:** '.$zoey['ledger_tokens_display'].' (estimated '.$zoey['estimated_tokens_display'].' · measured '.$zoey['measured_tokens_display'].')';

        foreach ($zoey['why_they_differ'] as $reason) {
            $lines[] = '- '.$reason;
        }

        $lines[] = '';
        $lines[] = '## By category';
        $lines[] = '';
        $lines[] = '| Category | Entries | Tokens | Hours |';
        $lines[] = '| -------- | ------- | ------ | ----- |';

        foreach ($summary['by_category'] as $category => $row) {
            if (($row['entries'] ?? 0) === 0) {
                continue;
            }

            $lines[] = sprintf(
                '| %s | %d | %s | %s |',
                $category,
                (int) $row['entries'],
                $this->formatTokens((int) $row['tokens']),
                rtrim(rtrim(number_format(((float) $row['minutes']) / 60, 2, '.', ''), '0'), '.')
            );
        }

        $lines[] = '';
        $lines[] = '## By user';
        $lines[] = '';
        $lines[] = '| User | Entries | Tokens | Hours |';
        $lines[] = '| ---- | ------- | ------ | ----- |';

        foreach ($summary['by_user'] as $user => $row) {
            $lines[] = sprintf(
                '| %s | %d | %s | %s |',
                str_replace('|', '\\|', (string) $user),
                (int) $row['entries'],
                $this->formatTokens((int) $row['tokens']),
                rtrim(rtrim(number_format(((float) $row['minutes']) / 60, 2, '.', ''), '0'), '.')
            );
        }

        $lines[] = '';
        $lines[] = '## Schedule';
        $lines[] = '';

        if ($schedule['deadlines'] === []) {
            $lines[] = '_No deadlines recorded._';
        } else {
            foreach ($schedule['deadlines'] as $deadline) {
                $lines[] = sprintf(
                    '- **%s** — %s (%s)%s',
                    (string) ($deadline['label'] ?? 'Deadline'),
                    (string) ($deadline['date'] ?? ''),
                    (string) ($deadline['status'] ?? 'on_track'),
                    isset($deadline['note']) && (string) $deadline['note'] !== ''
                        ? ' — '.(string) $deadline['note']
                        : ''
                );
            }
        }

        if ($schedule['notes'] !== []) {
            $lines[] = '';
            $lines[] = '### Notes';
            $lines[] = '';

            foreach ($schedule['notes'] as $note) {
                $lines[] = sprintf(
                    '- `%s` [%s] %s',
                    (string) ($note['ts'] ?? ''),
                    (string) ($note['status'] ?? ''),
                    (string) ($note['message'] ?? '')
                );
            }
        }

        $lines[] = '';
        $lines[] = '## Ledger entries';
        $lines[] = '';

        foreach ($this->query($filters) as $entry) {
            $hours = rtrim(rtrim(number_format(((float) ($entry['minutes'] ?? 0)) / 60, 2, '.', ''), '0'), '.');
            $lines[] = sprintf(
                '- `%s` · %s · %s · tokens=%s · hours=%s%s%s',
                (string) ($entry['ts'] ?? ''),
                (string) ($entry['category'] ?? ''),
                (string) ($entry['user'] ?? ''),
                $this->formatTokens((int) ($entry['tokens'] ?? 0)),
                $hours !== '' ? $hours : '0',
                isset($entry['skill']) && $entry['skill'] ? ' · '.$entry['skill'] : '',
                isset($entry['spec']) && $entry['spec'] ? ' · '.$entry['spec'] : ''
            );
        }

        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param  array<string, array{entries: int, tokens: int, minutes: float}>  $bucket
     */
    protected function accumulateBucket(array &$bucket, string $key, int $tokens, float $minutes): void
    {
        if (! isset($bucket[$key])) {
            $bucket[$key] = ['entries' => 0, 'tokens' => 0, 'minutes' => 0.0];
        }

        $bucket[$key]['entries']++;
        $bucket[$key]['tokens'] += $tokens;
        $bucket[$key]['minutes'] += $minutes;
    }

    protected function normalizeDateBoundary(mixed $value, bool $endOfDay): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $raw = trim($value);

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
                $date = new DateTimeImmutable($raw.($endOfDay ? ' 23:59:59' : ' 00:00:00'));
            } else {
                $date = new DateTimeImmutable($raw);
            }
        } catch (\Exception) {
            throw new \InvalidArgumentException('Invalid date filter. Use YYYY-MM-DD or an ISO timestamp.');
        }

        return $date->format(DateTimeInterface::ATOM);
    }

    /**
     * Format token counts for display (1000+ → K).
     */
    public function formatTokens(int $tokens): string
    {
        if ($tokens < 1000) {
            return (string) $tokens;
        }

        $k = $tokens / 1000;

        if (abs($k - round($k)) < 0.05) {
            return ((int) round($k)).'K';
        }

        return rtrim(rtrim(number_format($k, 1, '.', ''), '0'), '.').'K';
    }

    /**
     * Explain Zoey context estimates vs Lucille ledger totals.
     *
     * @return array<string, mixed>
     */
    public function zoeyReconciliation(): array
    {
        $entries = $this->entries();
        $ledgerTokens = 0;
        $estimatedTokens = 0;
        $measuredTokens = 0;
        $estimatedEntries = 0;

        foreach ($entries as $entry) {
            $tokens = (int) ($entry['tokens'] ?? 0);
            $ledgerTokens += $tokens;

            if (($entry['estimated'] ?? false) === true) {
                $estimatedTokens += $tokens;
                $estimatedEntries++;
            } else {
                $measuredTokens += $tokens;
            }
        }

        return [
            'ledger_tokens' => $ledgerTokens,
            'ledger_tokens_display' => $this->formatTokens($ledgerTokens),
            'estimated_tokens' => $estimatedTokens,
            'estimated_tokens_display' => $this->formatTokens($estimatedTokens),
            'measured_tokens' => $measuredTokens,
            'measured_tokens_display' => $this->formatTokens($measuredTokens),
            'estimated_entry_count' => $estimatedEntries,
            'entry_count' => count($entries),
            'why_they_differ' => [
                'Zoey `context ≈ Nk` measures loaded chat context (chars÷4), not provider billing tokens.',
                'Lucille ledger stores session work tokens/time — often seeded from Zoey’s end line with `--estimated`.',
                'They will not match 1:1: Zoey counts prompt/context size; Lucille counts committed session spend.',
            ],
        ];
    }

    /**
     * Forecast remaining effort against project and epic deadlines.
     *
     * @param  array<string, mixed>|null  $gantt
     * @param  array{specs?: list<array<string, mixed>>, plans?: array<string, array<string, mixed>|null>, schedule?: array<string, mixed>}|null  $inputs  See {@see gantt()}.
     * @return array<string, mixed>
     */
    public function criticality(?array $gantt = null, ?array $inputs = null): array
    {
        $gantt ??= $this->gantt($inputs);
        $schedule = $inputs['schedule'] ?? $this->schedule();
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $alerts = [];
        $remainingPoints = 0;

        foreach ($inputs['specs'] ?? $this->specs->allSpecs() as $spec) {
            if (! is_array($spec) || strtoupper((string) ($spec['status'] ?? 'TODO')) === 'DONE') {
                continue;
            }

            $remainingPoints += max(0, (int) ($spec['points'] ?? 0));
        }

        // The forecast is the one the Gantt draws: the day its last open bar ends.
        $remainingHours = (float) ($gantt['remaining_hours'] ?? 0.0);
        $forecastDays = $remainingHours / self::HOURS_PER_DAY;
        $forecastEnd = (string) ($gantt['forecast_end'] ?? $today);

        foreach ($schedule['deadlines'] as $deadline) {
            if (! is_array($deadline) || empty($deadline['date'])) {
                continue;
            }

            $date = (string) $deadline['date'];
            $status = (string) ($deadline['status'] ?? 'on_track');

            if ($status === 'done') {
                continue;
            }

            // A milestone that names a release waits for that release, not for the whole backlog.
            $release = trim((string) ($deadline['release'] ?? ''));
            $releaseEnd = $release !== '' ? $this->releaseForecast($release, $gantt) : null;
            $target = $releaseEnd ?? $forecastEnd;

            $overdue = $date < $today;
            $slipDays = $target > $date
                ? (new DateTimeImmutable($date))->diff(new DateTimeImmutable($target))->days
                : 0;

            $level = match (true) {
                $overdue, $slipDays > 0, $status === 'delayed' => 'critical',
                $status === 'at_risk' => 'warning',
                default => 'ok',
            };

            if ($level === 'ok') {
                $bufferDays = (new DateTimeImmutable($target))->diff(new DateTimeImmutable($date))->days;

                // A release whose last spec is behind today has nothing left to slip.
                if ($bufferDays >= 2 || $remainingPoints === 0 || ($releaseEnd !== null && $releaseEnd < $today)) {
                    continue;
                }

                $level = 'warning';
            }

            $alerts[] = [
                'level' => $level,
                'scope' => 'deadline',
                'label' => (string) ($deadline['label'] ?? 'Deadline'),
                'date' => $date,
                'message' => $overdue
                    ? 'Overdue vs today — remaining ~'.round($forecastDays, 1).' work-days still open.'
                    : ($slipDays > 0
                        ? ($releaseEnd !== null
                            ? 'Release '.$release.' is forecast for '.$target.': '.$slipDays.' day(s) past this deadline.'
                            : 'Forecast end '.$forecastEnd.' slips '.$slipDays.' day(s) past this deadline.')
                        : ($releaseEnd !== null
                            ? 'Thin buffer before '.$date.': release '.$release.' is forecast for '.$target.'.'
                            : 'Thin buffer before '.$date.' (~'.round($forecastDays, 1).' work-days left in backlog).')),
            ];
        }

        foreach ($gantt['epics'] ?? [] as $epic) {
            if (! is_array($epic) || empty($epic['deadline']) || empty($epic['forecast_end']) || ! empty($epic['done'])) {
                continue;
            }

            $deadline = (string) $epic['deadline'];
            $end = (string) $epic['forecast_end'];

            if ($end <= $deadline) {
                continue;
            }

            $slip = (new DateTimeImmutable($deadline))->diff(new DateTimeImmutable($end))->days;
            $alerts[] = [
                'level' => 'critical',
                'scope' => 'epic',
                'label' => (string) ($epic['code'] ?? 'EP').' — '.(string) ($epic['title'] ?? ''),
                'date' => $deadline,
                'message' => 'Epic forecast '.$end.' exceeds objective deadline by '.$slip.' day(s).',
            ];
        }

        usort($alerts, static function (array $a, array $b): int {
            $rank = ['critical' => 0, 'warning' => 1, 'ok' => 2];

            return ($rank[$a['level']] ?? 9) <=> ($rank[$b['level']] ?? 9);
        });

        return [
            'remaining_points' => $remainingPoints,
            'remaining_hours' => round($remainingHours, 1),
            'forecast_work_days' => round($forecastDays, 1),
            'forecast_end' => $forecastEnd,
            'project_end' => $gantt['project_end'] ?? $forecastEnd,
            'alerts' => $alerts,
            'on_track' => $alerts === [],
        ];
    }

    /**
     * Build the delivery forecast from the backlog, the plans, and the ledger.
     *
     * Done work sits in the past, on the days the ledger recorded it. Open
     * work is queued from today, one spec at a time in delivery order, so a
     * bar starts where the one before it ends.
     *
     * `$inputs` replaces what is on disk — the backlog, a plan by spec code,
     * the schedule — so a re-plan can be forecast before any of it is written.
     *
     * @param  array{specs?: list<array<string, mixed>>, plans?: array<string, array<string, mixed>|null>, schedule?: array<string, mixed>}|null  $inputs
     * @return array{
     *     today: string,
     *     project_start: ?string,
     *     project_end: ?string,
     *     forecast_end: ?string,
     *     remaining_hours: float,
     *     assumptions: array{hours_per_day: float, hours_per_point: float},
     *     queue: list<string>,
     *     bars: list<array<string, mixed>>,
     *     epics: list<array<string, mixed>>,
     *     milestones: list<array<string, mixed>>,
     *     assignees: list<string>,
     *     legend: list<array{key: string, label: string, kind: string}>
     * }
     */
    public function gantt(?array $inputs = null): array
    {
        $schedule = $inputs['schedule'] ?? $this->schedule();
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        // Open work is forecast from the first working day, today included.
        $anchor = $this->workdayOnOrAfter(new DateTimeImmutable('today'));

        $dates = [];
        $activity = [];

        foreach ($this->entries() as $entry) {
            $day = substr((string) ($entry['ts'] ?? ''), 0, 10);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
                continue;
            }

            $dates[] = $day;
            $code = (string) ($entry['spec'] ?? '');

            if ($code !== '') {
                $activity[$code] = [
                    min($activity[$code][0] ?? $day, $day),
                    max($activity[$code][1] ?? $day, $day),
                ];
            }
        }

        $projectStart = $dates !== [] ? min($dates) : null;

        foreach ($schedule['deadlines'] as $deadline) {
            if (! empty($deadline['date'])) {
                $dates[] = (string) $deadline['date'];
            }
        }

        $nodes = $this->specWork($inputs);

        // Done work the ledger has no dates for is laid end to end up to today,
        // and squeezed when it would start before the project did.
        $undated = [];

        foreach (['done', 'active', 'queued'] as $stage) {
            foreach ($nodes as $code => $node) {
                if ($node['stage'] === $stage && $node['done_length'] > 0 && ! isset($activity[$code])) {
                    $undated[$code] = $node['done_length'];
                }
            }
        }

        $undatedDays = array_sum($undated);
        $room = $projectStart !== null
            ? $this->workdaysBetween(new DateTimeImmutable($projectStart), $anchor)
            : 0;
        $squeeze = $room > 0 && $undatedDays > $room ? $room / $undatedDays : 1.0;
        $position = -$undatedDays * $squeeze;
        $pastStart = [];

        foreach ($undated as $code => $length) {
            $pastStart[$code] = $position;
            $position += $length * $squeeze;
        }

        $cursor = 0.0;
        $queueStart = [];
        $remainingHours = 0.0;

        $queue = $this->deliveryOrder($nodes);

        foreach ($queue as $code) {
            $queueStart[$code] = $cursor;
            $cursor += $nodes[$code]['open_length'];
            $remainingHours += $nodes[$code]['open_hours'];
        }

        $forecastEnd = $cursor > 0 ? $this->workSpan($anchor, 0.0, $cursor)[1] : null;

        $rows = [];
        $assignees = [];

        foreach ($nodes as $code => $node) {
            $code = (string) $code;
            $recorded = $activity[$code] ?? null;

            $past = function (float $from, float $to) use ($node, $code, $recorded, $today, $anchor, $pastStart, $squeeze): array {
                if ($recorded === null) {
                    $base = $pastStart[$code] ?? 0.0;

                    return $this->workSpan($anchor, $base + $from * $squeeze, $base + $to * $squeeze);
                }

                $until = $node['stage'] === 'done' ? $recorded[1] : max($recorded[0], $today);

                return $this->recordedSpan($recorded[0], $until, $from, $to, $node['done_length']);
            };

            $future = fn (float $from, float $to): array => $this->workSpan(
                $anchor,
                ($queueStart[$code] ?? 0.0) + $from,
                ($queueStart[$code] ?? 0.0) + $to
            );

            $specBars = [];

            if ($node['tasks'] === []) {
                [$start, $end] = $node['stage'] === 'done'
                    ? $past(0.0, $node['done_length'])
                    : $future(0.0, $node['open_length']);

                // A spec that is being worked on began the day the ledger first saw it.
                if ($node['stage'] === 'active' && $recorded !== null) {
                    $start = min($start, $recorded[0]);
                }

                $specBars[] = [
                    'id' => $code,
                    'label' => $code.' — '.$node['title'],
                    'type' => 'spec',
                    'status' => $node['status'],
                    'start' => $start,
                    'end' => $end,
                    'progress' => $node['progress'],
                    'points' => $node['points'],
                    'assignee' => null,
                    'parallel' => false,
                    'depends_on' => $node['blocked_by'],
                    'epic' => $node['epic']['code'] ?? null,
                    'remaining_hours' => round($node['open_hours'], 1),
                ];
            }

            foreach ($node['tasks'] as $task) {
                $id = (string) $task['id'];
                $done = isset($node['done_slots'][$id]);
                [$start, $end] = $done
                    ? $past(...$node['done_slots'][$id])
                    : $future(...$node['open_slots'][$id]);
                $assignee = trim((string) ($task['assignee'] ?? '')) ?: null;

                if ($assignee !== null) {
                    $assignees[] = $assignee;
                }

                $specBars[] = [
                    'id' => $code.'·'.$id,
                    'task_id' => $id,
                    'label' => $code.' / '.$id.' — '.(string) ($task['title'] ?? $id),
                    'type' => 'task',
                    'status' => $done ? 'DONE' : $node['status'],
                    'start' => $start,
                    'end' => $end,
                    'progress' => $done ? 1.0 : (str_contains($node['status'], 'PROGRESS') ? 0.45 : 0.0),
                    'points' => null,
                    'assignee' => $assignee,
                    'parallel' => in_array($id, $node['parallel'], true),
                    'depends_on' => $this->taskDependencies($task),
                    'epic' => null,
                    'spec_title' => $node['title'] !== '' ? $node['title'] : null,
                    'spec_status' => $node['status'],
                    'spec_depends_on' => $node['blocked_by'],
                    'estimate_hours' => round($this->taskHours($task, $node['default_hours']), 1),
                ];
            }

            // Tasks read top to bottom in the order they are worked.
            usort($specBars, static fn (array $a, array $b): int => strcmp($a['start'], $b['start']));

            $rows[] = [
                'node' => $node,
                'start' => min(array_column($specBars, 'start')),
                'end' => max(array_column($specBars, 'end')),
                'bars' => $specBars,
            ];
        }

        // Specs, and the epics they open, read in the order they are delivered.
        usort($rows, static fn (array $a, array $b): int => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]
            ?: strnatcmp($a['node']['code'], $b['node']['code']));

        $bars = [];
        $epicBuckets = [];

        foreach ($rows as $row) {
            $node = $row['node'];
            $dates[] = $row['start'];
            $dates[] = $row['end'];
            array_push($bars, ...$row['bars']);

            $epic = $node['epic'];

            if (! is_array($epic) || empty($epic['code'])) {
                continue;
            }

            $epicCode = (string) $epic['code'];

            if (! isset($epicBuckets[$epicCode])) {
                $epicBuckets[$epicCode] = [
                    'id' => $epicCode,
                    'code' => $epicCode,
                    'title' => (string) ($epic['title'] ?? $epicCode),
                    'objective' => trim((string) ($epic['objective'] ?? '')) ?: null,
                    'deadline' => ! empty($epic['deadline']) ? substr((string) $epic['deadline'], 0, 10) : null,
                    'start' => $row['start'],
                    'forecast_end' => $row['end'],
                    'spec_codes' => [],
                    'points' => 0,
                    'done_points' => 0,
                    'done' => $node['stage'] === 'done',
                ];
            }

            $epicBuckets[$epicCode]['start'] = min($epicBuckets[$epicCode]['start'], $row['start']);
            $epicBuckets[$epicCode]['forecast_end'] = max($epicBuckets[$epicCode]['forecast_end'], $row['end']);
            $epicBuckets[$epicCode]['spec_codes'][] = $node['code'];
            $epicBuckets[$epicCode]['points'] += $node['points'];
            $epicBuckets[$epicCode]['done_points'] += $node['stage'] === 'done' ? $node['points'] : 0;
            $epicBuckets[$epicCode]['done'] = $epicBuckets[$epicCode]['done'] && $node['stage'] === 'done';

            if (! empty($epic['objective'])) {
                $epicBuckets[$epicCode]['objective'] = (string) $epic['objective'];
            }

            if (! empty($epic['deadline'])) {
                $epicBuckets[$epicCode]['deadline'] = substr((string) $epic['deadline'], 0, 10);
            }

            if (! empty($epic['title'])) {
                $epicBuckets[$epicCode]['title'] = (string) $epic['title'];
            }
        }

        $epicBars = [];

        foreach ($epicBuckets as $epic) {
            if ($epic['deadline'] !== null) {
                $dates[] = $epic['deadline'];
            }

            $epicBars[] = [
                'id' => $epic['code'],
                'label' => $epic['code'].' — '.$epic['title'],
                'type' => 'epic',
                'status' => match (true) {
                    $epic['done'] => 'DONE',
                    $epic['deadline'] !== null && $epic['forecast_end'] > $epic['deadline'] => 'AT RISK',
                    default => 'PLANNED',
                },
                'start' => $epic['start'],
                'end' => $epic['forecast_end'],
                'progress' => round($epic['done_points'] / max(1, $epic['points']), 2),
                'objective' => $epic['objective'],
                'deadline' => $epic['deadline'],
                'points' => $epic['points'],
                'assignee' => null,
                'parallel' => false,
                'depends_on' => [],
                'epic' => $epic['code'],
            ];
        }

        $milestones = [];

        foreach ($schedule['deadlines'] as $deadline) {
            $milestones[] = [
                'id' => (string) ($deadline['id'] ?? ''),
                'label' => (string) ($deadline['label'] ?? 'Deadline'),
                'date' => (string) ($deadline['date'] ?? ''),
                'status' => (string) ($deadline['status'] ?? 'on_track'),
                'note' => $deadline['note'] ?? null,
                'release' => $deadline['release'] ?? null,
            ];
        }

        sort($dates);
        $dates = array_values(array_filter(array_unique($dates)));
        $assignees = array_values(array_unique($assignees));
        sort($assignees);

        // Epics first, then task/spec bars (stable for reading).
        $bars = array_merge($epicBars, $bars);

        return [
            'today' => $today,
            'project_start' => $dates[0] ?? null,
            'project_end' => $dates !== [] ? $dates[array_key_last($dates)] : null,
            'forecast_end' => $forecastEnd,
            'remaining_hours' => round($remainingHours, 1),
            'assumptions' => [
                'hours_per_day' => self::HOURS_PER_DAY,
                'hours_per_point' => self::HOURS_PER_POINT,
            ],
            'queue' => $queue,
            'bars' => $bars,
            'epics' => array_values($epicBuckets),
            'milestones' => $milestones,
            'assignees' => $assignees,
            'legend' => [
                ['key' => 'epic', 'label' => 'Epic (objective window)', 'kind' => 'type'],
                ['key' => 'spec', 'label' => 'Spec (no plan yet)', 'kind' => 'type'],
                ['key' => 'task', 'label' => 'Task (dependency-aware)', 'kind' => 'type'],
                ['key' => 'parallel', 'label' => 'Parallelizable (no blocking deps between them)', 'kind' => 'flag'],
                ['key' => 'todo', 'label' => 'TODO', 'kind' => 'status'],
                ['key' => 'planned', 'label' => 'PLANNED', 'kind' => 'status'],
                ['key' => 'progress', 'label' => 'IN PROGRESS', 'kind' => 'status'],
                ['key' => 'review', 'label' => 'REVIEW', 'kind' => 'status'],
                ['key' => 'done', 'label' => 'DONE', 'kind' => 'status'],
                ['key' => 'milestone', 'label' => 'Deadline / milestone', 'kind' => 'type'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $summary = $this->summary();
        $gantt = $this->gantt();
        $entries = array_reverse($this->entries());
        $users = array_values(array_unique(array_filter(array_map(
            static fn (array $e): string => (string) ($e['user'] ?? ''),
            $entries
        ))));
        sort($users);

        return [
            'summary' => $summary,
            'schedule' => $this->schedule(),
            'gantt' => $gantt,
            'criticality' => $this->criticality($gantt),
            'zoey' => $this->zoeyReconciliation(),
            'entries' => $entries,
            'entry_users' => $users,
            'entry_categories' => self::CATEGORIES,
            'report_markdown' => $this->reportMarkdown(),
        ];
    }

    /**
     * What every spec has delivered and what it still asks for, in working
     * days, with its tasks placed on a timeline that starts when the spec does.
     *
     * @param  array{specs?: list<array<string, mixed>>, plans?: array<string, array<string, mixed>|null>, schedule?: array<string, mixed>}|null  $inputs
     * @return array<string, array<string, mixed>>
     */
    protected function specWork(?array $inputs = null): array
    {
        $specs = [];
        $known = [];

        foreach ($inputs['specs'] ?? $this->specs->allSpecs() as $spec) {
            $code = is_array($spec) ? (string) ($spec['code'] ?? '') : '';

            if ($code !== '' && ! isset($specs[$code])) {
                $specs[$code] = $spec;
                $known[strtoupper($code)] = $code;
            }
        }

        $nodes = [];

        foreach ($specs as $code => $spec) {
            $code = (string) $code;
            $status = strtoupper((string) ($spec['status'] ?? 'TODO'));
            $stage = match (true) {
                $status === 'DONE' => 'done',
                str_contains($status, 'PROGRESS'), str_contains($status, 'REVIEW') => 'active',
                default => 'queued',
            };
            $points = max(1, (int) ($spec['points'] ?? 1));
            $plan = array_key_exists($code, $inputs['plans'] ?? [])
                ? $inputs['plans'][$code]
                : $this->plans->read($code);
            $planned = array_values(array_filter(
                is_array($plan['tasks'] ?? null) ? $plan['tasks'] : [],
                static fn (mixed $task): bool => is_array($task)
            ));
            $doneTasks = count(array_filter(
                $planned,
                static fn (array $task): bool => strtoupper((string) ($task['status'] ?? '')) === 'DONE'
            ));
            $doneRatio = $planned !== []
                ? $doneTasks / count($planned)
                : ($status === 'DONE' ? 1.0 : ($status === 'REVIEW' ? 0.85 : ($status === 'IN PROGRESS' ? 0.45 : 0.0)));

            $blockedBy = [];

            foreach (SpecBlockers::read((string) ($spec['body'] ?? '')) ?? [] as $blocker) {
                if (isset($known[$blocker]) && $known[$blocker] !== $code) {
                    $blockedBy[] = $known[$blocker];
                }
            }

            $tasks = array_values(array_filter(
                $planned,
                static fn (array $task): bool => ! empty($task['id'])
            ));
            $hours = max(2.0, $points * self::HOURS_PER_POINT);

            $node = [
                'code' => $code,
                'title' => (string) ($spec['title'] ?? ''),
                'status' => $status,
                'stage' => $stage,
                'points' => $points,
                'priority' => strtoupper((string) ($spec['priority'] ?? 'MEDIUM')),
                'epic' => is_array($spec['epic'] ?? null) ? $spec['epic'] : null,
                'progress' => round(min(1, max(0, $doneRatio)), 2),
                'blocked_by' => $blockedBy,
                'tasks' => [],
                'parallel' => [],
                'default_hours' => $hours,
                'done_slots' => [],
                'done_length' => 0.0,
                'open_slots' => [],
                'open_length' => 0.0,
                'open_hours' => 0.0,
            ];

            if ($tasks === []) {
                // Without a plan the story points are the estimate, less what the status says is behind.
                if ($stage === 'done') {
                    $node['done_length'] = $hours / self::HOURS_PER_DAY;
                } else {
                    $node['open_hours'] = max(1.0, $hours * (1 - $node['progress']));
                    $node['open_length'] = $node['open_hours'] / self::HOURS_PER_DAY;
                }

                $nodes[$code] = $node;

                continue;
            }

            $tasks = $this->topoSortTasks($tasks);
            $defaultHours = max(2.0, $hours / count($tasks));
            $done = [];
            $open = [];
            $sameGate = [];

            foreach ($tasks as $task) {
                if ($stage === 'done' || strtoupper((string) ($task['status'] ?? '')) === 'DONE') {
                    $done[] = $task;
                } else {
                    $open[] = $task;
                    $node['open_hours'] += $this->taskHours($task, $defaultHours);
                }

                $gate = $this->taskDependencies($task);
                sort($gate);
                $sameGate[implode('|', $gate)][] = (string) $task['id'];
            }

            // Tasks that wait for the same dependencies do not block each other.
            foreach ($sameGate as $ids) {
                if (count($ids) > 1) {
                    array_push($node['parallel'], ...$ids);
                }
            }

            [$node['done_slots'], $node['done_length']] = $this->layoutTasks($done, $defaultHours);
            [$node['open_slots'], $node['open_length']] = $this->layoutTasks($open, $defaultHours);
            $node['tasks'] = $tasks;
            $node['default_hours'] = $defaultHours;
            $nodes[$code] = $node;
        }

        return $nodes;
    }

    /**
     * The order the open specs are delivered in: work already started first,
     * then priority and code as `spec-next` picks them, and never before the
     * specs a story is blocked by. A spec that blocks a more urgent one is as
     * urgent as it: the urgent story cannot be delivered without it.
     *
     * @param  array<string, array<string, mixed>>  $nodes
     * @return list<string>
     */
    protected function deliveryOrder(array $nodes): array
    {
        $priorities = ['CRITICAL' => 0, 'HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3];
        $waiting = [];

        foreach ($nodes as $code => $node) {
            if ($node['stage'] !== 'done') {
                $waiting[(string) $code] = [$node['stage'] === 'active' ? 0 : 1, $priorities[$node['priority']] ?? 2];
            }
        }

        do {
            $raised = false;

            foreach ($waiting as $code => $rank) {
                foreach ($nodes[$code]['blocked_by'] as $blocker) {
                    if (isset($waiting[$blocker]) && $waiting[$blocker][1] > $rank[1]) {
                        $waiting[$blocker][1] = $rank[1];
                        $raised = true;
                    }
                }
            }
        } while ($raised);

        uksort($waiting, static fn (mixed $a, mixed $b): int => $waiting[$a] <=> $waiting[$b]
            ?: strnatcmp((string) $a, (string) $b));

        $order = [];

        while ($waiting !== []) {
            // Blockers that name each other leave nothing ready: the first in rank goes.
            $next = array_key_first($waiting);

            foreach (array_keys($waiting) as $code) {
                $blockers = $nodes[$code]['stage'] === 'active' ? [] : $nodes[$code]['blocked_by'];

                if (array_intersect_key(array_flip($blockers), $waiting) === []) {
                    $next = $code;

                    break;
                }
            }

            $order[] = (string) $next;
            unset($waiting[$next]);
        }

        return $order;
    }

    /**
     * Place tasks on a timeline counted in working days from the moment
     * their spec starts. A task waits for its dependencies and for its
     * assignee, so only work given to different people overlaps.
     *
     * @param  list<array<string, mixed>>  $tasks  in dependency order
     * @return array{0: array<string, array{0: float, 1: float}>, 1: float} the slot of each task, and the length of them all
     */
    protected function layoutTasks(array $tasks, float $defaultHours): array
    {
        $slots = [];
        $free = [];
        $length = 0.0;

        foreach ($tasks as $task) {
            $person = strtolower(trim((string) ($task['assignee'] ?? '')));
            $start = $free[$person] ?? 0.0;

            foreach ($this->taskDependencies($task) as $dependency) {
                $start = max($start, $slots[$dependency][1] ?? 0.0);
            }

            $end = $start + $this->taskHours($task, $defaultHours) / self::HOURS_PER_DAY;
            $slots[(string) $task['id']] = [$start, $end];
            $free[$person] = $end;
            $length = max($length, $end);
        }

        return [$slots, $length];
    }

    /**
     * @param  array<string, mixed>  $task
     * @return list<string>
     */
    protected function taskDependencies(array $task): array
    {
        return array_values(array_filter(
            is_array($task['dependencies'] ?? null) ? $task['dependencies'] : [],
            static fn (mixed $dependency): bool => is_string($dependency) && $dependency !== ''
        ));
    }

    /**
     * @param  array<string, mixed>  $task
     */
    protected function taskHours(array $task, float $defaultHours): float
    {
        return max(1.0, (float) ($task['estimate_hours'] ?? $defaultHours));
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @return list<array<string, mixed>>
     */
    protected function topoSortTasks(array $tasks): array
    {
        $byId = [];

        foreach ($tasks as $task) {
            $byId[(string) $task['id']] = $task;
        }

        $visited = [];
        $stack = [];

        $visit = function (string $id) use (&$visit, &$visited, &$stack, $byId): void {
            if (isset($visited[$id])) {
                return;
            }

            $visited[$id] = true;
            $task = $byId[$id] ?? null;

            if ($task === null) {
                return;
            }

            $deps = is_array($task['dependencies'] ?? null) ? $task['dependencies'] : [];

            foreach ($deps as $dep) {
                if (is_string($dep) && isset($byId[$dep])) {
                    $visit($dep);
                }
            }

            $stack[] = $task;
        };

        foreach (array_keys($byId) as $id) {
            $visit($id);
        }

        return $stack;
    }

    public function detectUser(): string
    {
        $name = trim((string) @shell_exec('git config user.name 2>/dev/null'));
        $email = trim((string) @shell_exec('git config user.email 2>/dev/null'));

        if ($name !== '' && $email !== '') {
            return 'git:'.$name.' <'.$email.'>';
        }

        if ($name !== '') {
            return 'git:'.$name;
        }

        $who = trim((string) @shell_exec('whoami 2>/dev/null'));

        return $who !== '' ? 'local:'.$who : 'unknown';
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    protected function writeSchedule(array $schedule): void
    {
        $payload = [
            'deadlines' => array_values($schedule['deadlines'] ?? []),
            'notes' => array_values($schedule['notes'] ?? []),
            'updated_at' => $this->normalizeTimestamp(null),
        ];

        AtomicFile::write(
            $this->schedulePath(),
            Yaml::dump($payload, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
        );
    }

    protected function normalizeTimestamp(mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            try {
                return (new DateTimeImmutable($value))->format(DateTimeInterface::ATOM);
            } catch (\Exception) {
                // fall through
            }
        }

        return (new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get() ?: 'UTC')))
            ->format(DateTimeInterface::ATOM);
    }

    protected function normalizeSpec(mixed $value): ?string
    {
        $spec = strtoupper(trim((string) $value));

        return $spec !== '' ? $spec : null;
    }

    protected function normalizeStatus(mixed $value): string
    {
        $status = strtolower(trim((string) $value));

        return in_array($status, ['on_track', 'at_risk', 'delayed', 'done'], true)
            ? $status
            : 'on_track';
    }

    protected function workdayOnOrAfter(DateTimeImmutable $day): DateTimeImmutable
    {
        $weekday = (int) $day->format('N');

        return $weekday > 5 ? $day->modify('+'.(8 - $weekday).' days') : $day;
    }

    /**
     * The working day that many working days after — or, below zero, before —
     * an anchor that is itself a working day.
     */
    protected function shiftWorkdays(DateTimeImmutable $anchor, int $offset): DateTimeImmutable
    {
        $weekday = (int) $anchor->format('N') - 1;
        $weeks = (int) floor(($weekday + $offset) / 5);
        $days = $weeks * 7 + ($weekday + $offset - $weeks * 5) - $weekday;

        return $anchor->modify(sprintf('%+d days', $days));
    }

    /**
     * Working days from one date up to, and not including, another.
     */
    protected function workdaysBetween(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        $count = 0;

        for ($day = $from; $day < $to; $day = $day->modify('+1 day')) {
            if ((int) $day->format('N') <= 5) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * First and last day of work that runs between two positions counted in
     * working days from the anchor. Work that ends within a day leaves the
     * rest of that day to what follows.
     *
     * @return array{0: string, 1: string}
     */
    protected function workSpan(DateTimeImmutable $anchor, float $from, float $to): array
    {
        $first = (int) floor($from + 1e-6);
        $last = max($first, (int) ceil($to - 1e-6) - 1);

        return [
            $this->shiftWorkdays($anchor, $first)->format('Y-m-d'),
            $this->shiftWorkdays($anchor, $last)->format('Y-m-d'),
        ];
    }

    /**
     * First and last day of a slice of done work inside the window the
     * ledger recorded for its spec: the ledger says when a spec was worked
     * on, not task by task, so each task takes its share of the window.
     *
     * @return array{0: string, 1: string}
     */
    protected function recordedSpan(string $from, string $to, float $start, float $end, float $length): array
    {
        $first = new DateTimeImmutable($from);
        $days = $first->diff(new DateTimeImmutable(max($from, $to)))->days + 1;
        $head = $length > 0 ? min($days - 1, (int) floor($start / $length * $days + 1e-6)) : 0;
        $tail = $length > 0 ? max($head, (int) ceil($end / $length * $days - 1e-6) - 1) : $days - 1;

        return [
            $first->modify('+'.$head.' days')->format('Y-m-d'),
            $first->modify('+'.$tail.' days')->format('Y-m-d'),
        ];
    }
}
