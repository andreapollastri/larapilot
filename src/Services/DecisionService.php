<?php

declare(strict_types=1);

namespace Larapilot\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Larapilot\Support\AtomicFile;
use Symfony\Component\Yaml\Yaml;

/**
 * Append-only journal of explicit user decisions across every Larapilot phase,
 * plus a regression guard: when a new value is recorded for a topic that already
 * carries a decision, the earlier choice(s) are surfaced for confirmation.
 *
 * Persisted to `.larapilot/decisions.yaml`. Never rewritten in place — a reversal
 * is a new entry pointing at the one it supersedes.
 */
class DecisionService
{
    /**
     * @var list<string>
     */
    public const SOURCES = ['chat', 'askquestion'];

    public function __construct(
        protected ConfigService $config,
    ) {}

    public function path(): string
    {
        $config = $this->config->resolve();

        return $this->config->absolutePath($config['paths']['decisions'] ?? '.larapilot/decisions.yaml');
    }

    /**
     * @return array{decisions: list<array<string, mixed>>, updated_at: string|null}
     */
    public function read(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return ['decisions' => [], 'updated_at' => null];
        }

        $parsed = Yaml::parseFile($path);

        if (! is_array($parsed)) {
            return ['decisions' => [], 'updated_at' => null];
        }

        return [
            'decisions' => array_values(array_filter(
                $parsed['decisions'] ?? [],
                static fn (mixed $row): bool => is_array($row)
            )),
            'updated_at' => is_string($parsed['updated_at'] ?? null) ? $parsed['updated_at'] : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function entries(): array
    {
        return $this->read()['decisions'];
    }

    /**
     * Append one decision entry.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function log(array $attributes): array
    {
        $label = trim((string) ($attributes['label'] ?? $attributes['topic'] ?? ''));
        $topic = $this->normalizeTopic($attributes['topic'] ?? $label);
        $value = trim((string) ($attributes['value'] ?? ''));

        if ($topic === '') {
            throw new \InvalidArgumentException('A decision requires a --topic.');
        }

        if ($value === '') {
            throw new \InvalidArgumentException('A decision requires a --value.');
        }

        $source = strtolower(trim((string) ($attributes['source'] ?? 'chat')));

        if (! in_array($source, self::SOURCES, true)) {
            throw new \InvalidArgumentException('Invalid source. Allowed: '.implode(', ', self::SOURCES));
        }

        $entry = [
            'id' => bin2hex(random_bytes(8)),
            'ts' => $this->normalizeTimestamp($attributes['ts'] ?? null),
            'user' => trim((string) ($attributes['user'] ?? $this->detectUser())),
            'phase' => trim((string) ($attributes['skill'] ?? $attributes['phase'] ?? '')) ?: null,
            'topic' => $topic,
            'label' => $label !== '' ? $label : $topic,
            'value' => $value,
            'question' => trim((string) ($attributes['question'] ?? '')) ?: null,
            'rationale' => trim((string) ($attributes['rationale'] ?? '')) ?: null,
            'source' => $source,
            'spec' => $this->normalizeSpec($attributes['spec'] ?? null),
            'supersedes' => trim((string) ($attributes['supersedes'] ?? '')) ?: null,
        ];

        $data = $this->read();
        $data['decisions'][] = $entry;

        $this->write($data['decisions']);

        return $entry;
    }

    /**
     * Prior decisions on the same topic, newest first. Match is case-insensitive
     * on the normalized topic, exact or substring either way.
     *
     * @return list<array<string, mixed>>
     */
    public function history(string $topic): array
    {
        $needle = $this->normalizeTopic($topic);

        if ($needle === '') {
            return [];
        }

        $matches = array_filter(
            $this->entries(),
            static function (array $entry) use ($needle): bool {
                $candidate = (string) ($entry['topic'] ?? '');

                return $candidate !== ''
                    && ($candidate === $needle
                        || str_contains($candidate, $needle)
                        || str_contains($needle, $candidate));
            }
        );

        usort($matches, static fn (array $a, array $b): int => strcmp((string) ($b['ts'] ?? ''), (string) ($a['ts'] ?? '')));

        return $matches;
    }

    /**
     * Recorded decisions on this topic whose value differs from the candidate
     * and have not already been superseded — the regression signal. Newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function conflicts(string $topic, string $value): array
    {
        $candidate = $this->normalizeValue($value);

        if ($candidate === '') {
            return [];
        }

        $history = $this->history($topic);
        $supersededIds = [];

        foreach ($this->entries() as $entry) {
            $ref = trim((string) ($entry['supersedes'] ?? ''));

            if ($ref !== '') {
                $supersededIds[$ref] = true;
            }
        }

        $conflicts = array_filter(
            $history,
            fn (array $entry): bool => ! isset($supersededIds[(string) ($entry['id'] ?? '')])
                && $this->normalizeValue((string) ($entry['value'] ?? '')) !== $candidate
        );

        return array_values($conflicts);
    }

    /**
     * @param  array{
     *     topic?: string|null,
     *     phase?: string|null,
     *     spec?: string|null,
     *     user?: string|null,
     *     from?: string|null,
     *     to?: string|null,
     *     limit?: int|null
     * }  $filters
     * @return list<array<string, mixed>>
     */
    public function query(array $filters = []): array
    {
        $topic = isset($filters['topic']) && is_string($filters['topic']) && trim($filters['topic']) !== ''
            ? $this->normalizeTopic($filters['topic'])
            : null;
        $phase = isset($filters['phase']) && is_string($filters['phase']) && trim($filters['phase']) !== ''
            ? strtolower(trim($filters['phase']))
            : null;
        $spec = isset($filters['spec']) ? $this->normalizeSpec($filters['spec']) : null;
        $user = isset($filters['user']) && is_string($filters['user']) && trim($filters['user']) !== ''
            ? strtolower(trim($filters['user']))
            : null;
        $from = $this->normalizeDateBoundary($filters['from'] ?? null, false);
        $to = $this->normalizeDateBoundary($filters['to'] ?? null, true);
        $limit = isset($filters['limit']) ? max(0, (int) $filters['limit']) : 0;

        $entries = array_values(array_filter(
            $this->entries(),
            function (array $entry) use ($topic, $phase, $spec, $user, $from, $to): bool {
                if ($topic !== null) {
                    $candidate = (string) ($entry['topic'] ?? '');

                    if ($candidate === '' || (! str_contains($candidate, $topic) && ! str_contains($topic, $candidate))) {
                        return false;
                    }
                }

                if ($phase !== null && strtolower((string) ($entry['phase'] ?? '')) !== $phase) {
                    return false;
                }

                if ($spec !== null && strtoupper((string) ($entry['spec'] ?? '')) !== $spec) {
                    return false;
                }

                if ($user !== null && ! str_contains(strtolower((string) ($entry['user'] ?? '')), $user)) {
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
     * Topics grouped with their full timeline and a `changed` flag when the
     * topic has held more than one distinct (non-superseded) value.
     *
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $supersededIds = [];

        foreach ($this->entries() as $entry) {
            $ref = trim((string) ($entry['supersedes'] ?? ''));

            if ($ref !== '') {
                $supersededIds[$ref] = true;
            }
        }

        $topics = [];

        foreach ($this->entries() as $entry) {
            $topic = (string) ($entry['topic'] ?? '');

            if ($topic === '') {
                continue;
            }

            if (! isset($topics[$topic])) {
                $topics[$topic] = [
                    'topic' => $topic,
                    'label' => (string) ($entry['label'] ?? $topic),
                    'timeline' => [],
                    'values' => [],
                ];
            }

            $topics[$topic]['label'] = (string) ($entry['label'] ?? $topics[$topic]['label']);
            $topics[$topic]['timeline'][] = $entry;

            if (! isset($supersededIds[(string) ($entry['id'] ?? '')])) {
                $topics[$topic]['values'][$this->normalizeValue((string) ($entry['value'] ?? ''))] = (string) ($entry['value'] ?? '');
            }
        }

        $out = [];

        foreach ($topics as $topic => $bucket) {
            usort(
                $bucket['timeline'],
                static fn (array $a, array $b): int => strcmp((string) ($a['ts'] ?? ''), (string) ($b['ts'] ?? ''))
            );

            $last = $bucket['timeline'][count($bucket['timeline']) - 1] ?? [];

            $out[$topic] = [
                'topic' => $topic,
                'label' => $bucket['label'],
                'current_value' => (string) ($last['value'] ?? ''),
                'changed' => count($bucket['values']) > 1,
                'entry_count' => count($bucket['timeline']),
                'timeline' => $bucket['timeline'],
            ];
        }

        ksort($out);

        return [
            'topics' => array_values($out),
            'regressions' => array_values(array_filter(
                $out,
                static fn (array $row): bool => $row['changed'] === true
            )),
            'entry_count' => count($this->entries()),
            'path' => $this->config->relativePath($this->path()),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $decisions
     */
    protected function write(array $decisions): void
    {
        $payload = [
            'decisions' => array_values($decisions),
            'updated_at' => $this->normalizeTimestamp(null),
        ];

        AtomicFile::write(
            $this->path(),
            Yaml::dump($payload, 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
        );
    }

    public function normalizeTopic(mixed $value): string
    {
        $topic = strtolower(trim((string) $value));
        $topic = (string) preg_replace('/\s+/', ' ', $topic);

        return $topic;
    }

    protected function normalizeValue(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    protected function normalizeSpec(mixed $value): ?string
    {
        $spec = strtoupper(trim((string) $value));

        return $spec !== '' ? $spec : null;
    }

    protected function normalizeTimestamp(mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            try {
                return (new DateTimeImmutable($value))->format(DateTimeInterface::ATOM);
            } catch (\Exception) {
                // fall through to now
            }
        }

        return (new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get() ?: 'UTC')))
            ->format(DateTimeInterface::ATOM);
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

    protected function detectUser(): string
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
}
