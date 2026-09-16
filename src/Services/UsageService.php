<?php

declare(strict_types=1);

namespace Larapilot\Services;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Larapilot\Support\AtomicFile;
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

    public function __construct(
        protected ConfigService $config,
        protected SpecService $specs,
        protected PlanService $plans,
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

        $schedule['deadlines'][] = $deadline;
        $this->writeSchedule($schedule);

        return $deadline;
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
     * @return array<string, mixed>
     */
    public function criticality(?array $gantt = null): array
    {
        $gantt ??= $this->gantt();
        $schedule = $this->schedule();
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $alerts = [];
        $remainingPoints = 0;
        $remainingHours = 0.0;

        foreach ($this->specs->allSpecs() as $spec) {
            if (! is_array($spec)) {
                continue;
            }

            $status = strtoupper((string) ($spec['status'] ?? 'TODO'));

            if ($status === 'DONE') {
                continue;
            }

            $points = max(0, (int) ($spec['points'] ?? 0));
            $remainingPoints += $points;
            $code = (string) ($spec['code'] ?? '');
            $plan = $code !== '' ? $this->plans->read($code) : null;
            $tasks = is_array($plan['tasks'] ?? null) ? $plan['tasks'] : [];

            if ($tasks === []) {
                $remainingHours += max(2.0, $points * 4.0);

                continue;
            }

            foreach ($tasks as $task) {
                if (! is_array($task) || strtoupper((string) ($task['status'] ?? '')) === 'DONE') {
                    continue;
                }

                $remainingHours += max(1.0, (float) ($task['estimate_hours'] ?? max(2.0, ($points * 4.0) / max(1, count($tasks)))));
            }
        }

        $forecastDays = max(0.5, $remainingHours / 6.0);
        $forecastEnd = $this->addDays($today, $forecastDays);
        $projectEnd = $gantt['project_end'] ?? $forecastEnd;

        foreach ($schedule['deadlines'] as $deadline) {
            if (! is_array($deadline) || empty($deadline['date'])) {
                continue;
            }

            $date = (string) $deadline['date'];
            $status = (string) ($deadline['status'] ?? 'on_track');

            if ($status === 'done') {
                continue;
            }

            $slipDays = 0;

            if ($projectEnd > $date) {
                $slipDays = (new DateTimeImmutable($date))->diff(new DateTimeImmutable($projectEnd))->days;
            }

            $overdue = $date < $today;
            $level = $overdue ? 'critical' : ($slipDays > 0 || $status === 'delayed' ? 'critical' : ($status === 'at_risk' || ($slipDays === 0 && $forecastEnd >= $date) ? 'warning' : 'ok'));

            if ($level === 'ok' && ! $overdue && $slipDays === 0 && $status === 'on_track') {
                $daysLeft = (new DateTimeImmutable($today))->diff(new DateTimeImmutable($date))->days;
                $bufferDays = max(0, $daysLeft) - (int) ceil($forecastDays);

                if ($bufferDays < 2 && $remainingPoints > 0) {
                    $level = 'warning';
                } else {
                    continue;
                }
            }

            $alerts[] = [
                'level' => $level,
                'scope' => 'deadline',
                'label' => (string) ($deadline['label'] ?? 'Deadline'),
                'date' => $date,
                'message' => $overdue
                    ? 'Overdue vs today — remaining ~'.round($forecastDays, 1).' work-days still open.'
                    : ($slipDays > 0
                        ? 'Forecast end '.$projectEnd.' slips '.$slipDays.' day(s) past this deadline.'
                        : 'Thin buffer before '.$date.' (~'.round($forecastDays, 1).' work-days left in backlog).'),
            ];
        }

        foreach ($gantt['epics'] ?? [] as $epic) {
            if (! is_array($epic) || empty($epic['deadline']) || empty($epic['forecast_end'])) {
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
            'project_end' => $projectEnd,
            'alerts' => $alerts,
            'on_track' => $alerts === [],
        ];
    }

    /**
     * Build a realistic Gantt from schedule + epics + task dependencies + usage.
     *
     * @return array{
     *     project_start: ?string,
     *     project_end: ?string,
     *     bars: list<array<string, mixed>>,
     *     epics: list<array<string, mixed>>,
     *     milestones: list<array<string, mixed>>,
     *     assignees: list<string>,
     *     legend: list<array{key: string, label: string, kind: string}>
     * }
     */
    public function gantt(): array
    {
        $entries = $this->entries();
        $schedule = $this->schedule();
        $specs = $this->specs->allSpecs();

        $dates = [];

        foreach ($entries as $entry) {
            $ts = (string) ($entry['ts'] ?? '');

            if ($ts !== '') {
                $dates[] = substr($ts, 0, 10);
            }
        }

        foreach ($schedule['deadlines'] as $deadline) {
            if (! empty($deadline['date'])) {
                $dates[] = (string) $deadline['date'];
            }
        }

        $bars = [];
        $epicBuckets = [];
        $assignees = [];

        foreach ($specs as $spec) {
            if (! is_array($spec)) {
                continue;
            }

            $code = (string) ($spec['code'] ?? '');

            if ($code === '') {
                continue;
            }

            $status = strtoupper((string) ($spec['status'] ?? 'TODO'));
            $points = max(1, (int) ($spec['points'] ?? 1));
            $progress = $this->plans->taskProgress($code);
            $doneRatio = ($progress['total'] ?? 0) > 0
                ? ($progress['done'] / max(1, $progress['total']))
                : ($status === 'DONE' ? 1.0 : ($status === 'REVIEW' ? 0.85 : ($status === 'IN PROGRESS' ? 0.45 : 0.0)));

            $usageMinutes = 0.0;

            foreach ($entries as $entry) {
                if (($entry['spec'] ?? null) === $code) {
                    $usageMinutes += (float) ($entry['minutes'] ?? 0);
                }
            }

            $specStart = $this->inferSpecStart($code, $entries, $dates);
            $plan = $this->plans->read($code);
            $tasks = is_array($plan['tasks'] ?? null) ? $plan['tasks'] : [];
            $scheduled = $this->schedulePlanTasks($tasks, $specStart, $points, $code, $status);

            if ($scheduled === []) {
                $estimatedDays = max(0.5, $points * 0.5 + ($usageMinutes / (60 * 6)));
                $end = $this->addDays($specStart, $estimatedDays);
                $dates[] = $specStart;
                $dates[] = $end;

                $bars[] = [
                    'id' => $code,
                    'label' => $code.' — '.(string) ($spec['title'] ?? ''),
                    'type' => 'spec',
                    'status' => $status,
                    'start' => $specStart,
                    'end' => $end,
                    'progress' => round(min(1, max(0, $doneRatio)), 2),
                    'points' => $points,
                    'assignee' => null,
                    'parallel' => false,
                    'depends_on' => [],
                    'epic' => is_array($spec['epic'] ?? null) ? ($spec['epic']['code'] ?? null) : null,
                ];

                $specEnd = $end;
            } else {
                $specEnd = $specStart;

                foreach ($scheduled as $taskBar) {
                    $dates[] = $taskBar['start'];
                    $dates[] = $taskBar['end'];
                    $bars[] = $taskBar;
                    $specEnd = max($specEnd, (string) $taskBar['end']);

                    if (! empty($taskBar['assignee'])) {
                        $assignees[] = (string) $taskBar['assignee'];
                    }
                }
            }

            $epic = is_array($spec['epic'] ?? null) ? $spec['epic'] : null;

            if (is_array($epic) && ! empty($epic['code'])) {
                $epicCode = (string) $epic['code'];

                if (! isset($epicBuckets[$epicCode])) {
                    $epicBuckets[$epicCode] = [
                        'id' => $epicCode,
                        'code' => $epicCode,
                        'title' => (string) ($epic['title'] ?? $epicCode),
                        'objective' => trim((string) ($epic['objective'] ?? '')) ?: null,
                        'deadline' => ! empty($epic['deadline']) ? substr((string) $epic['deadline'], 0, 10) : null,
                        'start' => $specStart,
                        'forecast_end' => $specEnd,
                        'spec_codes' => [],
                        'points' => 0,
                    ];
                }

                $epicBuckets[$epicCode]['start'] = min((string) $epicBuckets[$epicCode]['start'], $specStart);
                $epicBuckets[$epicCode]['forecast_end'] = max((string) $epicBuckets[$epicCode]['forecast_end'], $specEnd);
                $epicBuckets[$epicCode]['spec_codes'][] = $code;
                $epicBuckets[$epicCode]['points'] += $points;

                if (! empty($epic['objective'])) {
                    $epicBuckets[$epicCode]['objective'] = (string) $epic['objective'];
                }

                if (! empty($epic['deadline'])) {
                    $epicBuckets[$epicCode]['deadline'] = substr((string) $epic['deadline'], 0, 10);
                    $dates[] = $epicBuckets[$epicCode]['deadline'];
                }

                if (! empty($epic['title'])) {
                    $epicBuckets[$epicCode]['title'] = (string) $epic['title'];
                }
            }
        }

        $epicBars = [];

        foreach ($epicBuckets as $epic) {
            $dates[] = $epic['start'];
            $dates[] = $epic['forecast_end'];

            if (! empty($epic['deadline'])) {
                $dates[] = $epic['deadline'];
            }

            $epicBars[] = [
                'id' => $epic['code'],
                'label' => $epic['code'].' — '.$epic['title'],
                'type' => 'epic',
                'status' => ! empty($epic['deadline']) && $epic['forecast_end'] > $epic['deadline'] ? 'AT RISK' : 'PLANNED',
                'start' => $epic['start'],
                'end' => $epic['forecast_end'],
                'progress' => 0,
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
            ];
        }

        sort($dates);
        $dates = array_values(array_filter(array_unique($dates)));
        $assignees = array_values(array_unique($assignees));
        sort($assignees);

        // Epics first, then task/spec bars (stable for reading).
        $bars = array_merge($epicBars, $bars);

        return [
            'project_start' => $dates[0] ?? null,
            'project_end' => $dates !== [] ? $dates[array_key_last($dates)] : null,
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
     * Schedule plan tasks with dependency-aware starts and parallel flags.
     *
     * @param  list<array<string, mixed>>  $tasks
     * @return list<array<string, mixed>>
     */
    protected function schedulePlanTasks(array $tasks, string $specStart, int $points, string $specCode, string $specStatus): array
    {
        $normalized = [];

        foreach ($tasks as $task) {
            if (! is_array($task) || empty($task['id'])) {
                continue;
            }

            $normalized[] = $task;
        }

        if ($normalized === []) {
            return [];
        }

        $ordered = $this->topoSortTasks($normalized);
        $ends = [];
        $starts = [];
        $bars = [];
        $defaultHours = max(2.0, ($points * 4.0) / max(1, count($normalized)));

        foreach ($ordered as $task) {
            $id = (string) $task['id'];
            $deps = array_values(array_filter(
                is_array($task['dependencies'] ?? null) ? $task['dependencies'] : [],
                static fn (mixed $dep): bool => is_string($dep) && $dep !== ''
            ));

            $start = $specStart;

            foreach ($deps as $dep) {
                if (isset($ends[$dep])) {
                    $candidate = $this->addDays($ends[$dep], 0);
                    if ($candidate > $start) {
                        $start = $candidate;
                    }
                }
            }

            $hours = max(1.0, (float) ($task['estimate_hours'] ?? $defaultHours));
            $days = max(0.5, $hours / 6.0);
            $end = $this->addDays($start, $days);
            $taskStatus = strtoupper((string) ($task['status'] ?? 'TODO'));

            if ($taskStatus === 'DONE') {
                $progress = 1.0;
            } elseif (str_contains(strtoupper($specStatus), 'PROGRESS')) {
                $progress = 0.45;
            } else {
                $progress = 0.0;
            }

            $assignee = trim((string) ($task['assignee'] ?? '')) ?: null;
            $starts[$id] = $start;
            $ends[$id] = $end;

            $bars[] = [
                'id' => $specCode.'·'.$id,
                'task_id' => $id,
                'label' => $specCode.' / '.$id.' — '.(string) ($task['title'] ?? $id),
                'type' => 'task',
                'status' => $taskStatus === 'DONE' ? 'DONE' : $specStatus,
                'start' => $start,
                'end' => $end,
                'progress' => $progress,
                'points' => null,
                'assignee' => $assignee,
                'parallel' => false,
                'depends_on' => $deps,
                'epic' => null,
                'estimate_hours' => round($hours, 1),
            ];
        }

        // Mark tasks that share a start date and do not depend on each other as parallel.
        $byStart = [];

        foreach ($bars as $index => $bar) {
            $byStart[$bar['start']][] = $index;
        }

        foreach ($byStart as $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            foreach ($indexes as $index) {
                $bars[$index]['parallel'] = true;
            }
        }

        return $bars;
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

    /**
     * @param  list<array<string, mixed>>  $entries
     * @param  list<string>  $dates
     */
    protected function inferSpecStart(string $code, array $entries, array $dates): string
    {
        foreach ($entries as $entry) {
            if (($entry['spec'] ?? null) === $code && ! empty($entry['ts'])) {
                return substr((string) $entry['ts'], 0, 10);
            }
        }

        if ($dates !== []) {
            sort($dates);

            return $dates[0];
        }

        return (new DateTimeImmutable('today'))->format('Y-m-d');
    }

    protected function addDays(string $start, float $days): string
    {
        $date = new DateTimeImmutable($start);
        $whole = (int) floor($days);
        $fraction = $days - $whole;

        if ($whole > 0) {
            $date = $date->add(new DateInterval('P'.$whole.'D'));
        }

        if ($fraction >= 0.5) {
            $date = $date->add(new DateInterval('P1D'));
        }

        return $date->format('Y-m-d');
    }
}
