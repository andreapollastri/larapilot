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
            'gantt' => $this->gantt(),
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
            '- **Tokens:** '.$summary['total_tokens'],
            '- **Time:** '.$summary['total_minutes'].' minutes ('.$summary['total_hours'].' hours)',
            '- **Avg minutes / entry:** '.$summary['avg_minutes_per_entry'],
            '- **Estimated entries:** '.$summary['estimated_entry_count'],
            '',
            '## By category',
            '',
            '| Category | Entries | Tokens | Minutes |',
            '| -------- | ------- | ------ | ------- |',
        ];

        foreach ($summary['by_category'] as $category => $row) {
            if (($row['entries'] ?? 0) === 0) {
                continue;
            }

            $lines[] = sprintf(
                '| %s | %d | %d | %s |',
                $category,
                (int) $row['entries'],
                (int) $row['tokens'],
                rtrim(rtrim(number_format((float) $row['minutes'], 2, '.', ''), '0'), '.')
            );
        }

        $lines[] = '';
        $lines[] = '## By user';
        $lines[] = '';
        $lines[] = '| User | Entries | Tokens | Minutes |';
        $lines[] = '| ---- | ------- | ------ | ------- |';

        foreach ($summary['by_user'] as $user => $row) {
            $lines[] = sprintf(
                '| %s | %d | %d | %s |',
                str_replace('|', '\\|', (string) $user),
                (int) $row['entries'],
                (int) $row['tokens'],
                rtrim(rtrim(number_format((float) $row['minutes'], 2, '.', ''), '0'), '.')
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
            $lines[] = sprintf(
                '- `%s` · %s · %s · tokens=%d · minutes=%s%s%s',
                (string) ($entry['ts'] ?? ''),
                (string) ($entry['category'] ?? ''),
                (string) ($entry['user'] ?? ''),
                (int) ($entry['tokens'] ?? 0),
                rtrim(rtrim(number_format((float) ($entry['minutes'] ?? 0), 2, '.', ''), '0'), '.'),
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
     * Build a realistic Gantt-oriented timeline from schedule + backlog + usage.
     *
     * @return array{project_start: ?string, project_end: ?string, bars: list<array<string, mixed>>, milestones: list<array<string, mixed>>}
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

        foreach ($specs as $spec) {
            if (! is_array($spec)) {
                continue;
            }

            $code = (string) ($spec['code'] ?? '');
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

            $estimatedDays = max(0.5, $points * 0.5 + ($usageMinutes / (60 * 6)));
            $start = $this->inferSpecStart($code, $entries, $dates);
            $end = $this->addDays($start, $estimatedDays);

            $dates[] = $start;
            $dates[] = $end;

            $bars[] = [
                'id' => $code,
                'label' => $code.' — '.(string) ($spec['title'] ?? ''),
                'type' => 'spec',
                'status' => $status,
                'start' => $start,
                'end' => $end,
                'progress' => round(min(1, max(0, $doneRatio)), 2),
                'points' => $points,
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
        $dates = array_values(array_filter($dates));

        return [
            'project_start' => $dates[0] ?? null,
            'project_end' => $dates !== [] ? $dates[array_key_last($dates)] : null,
            'bars' => $bars,
            'milestones' => $milestones,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        return [
            'summary' => $this->summary(),
            'schedule' => $this->schedule(),
            'gantt' => $this->gantt(),
            'entries' => array_slice(array_reverse($this->entries()), 0, 50),
            'report_markdown' => $this->reportMarkdown(),
        ];
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
