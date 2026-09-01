<?php

declare(strict_types=1);

namespace Larapilot\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Larapilot\Support\AtomicFile;
use Symfony\Component\Yaml\Yaml;

/**
 * Append-only history of where in the codebase work happened: per spec/task,
 * the files and line ranges touched, derived from the task's git commit.
 *
 * Persisted to `.larapilot/code-history.yaml`. Opt-in via `settings.code_history`.
 */
class CodeHistoryService
{
    public function __construct(
        protected ConfigService $config,
        protected GitService $git,
    ) {}

    public function path(): string
    {
        $config = $this->config->resolve();

        return $this->config->absolutePath($config['paths']['code_history'] ?? '.larapilot/code-history.yaml');
    }

    /**
     * @return array{entries: list<array<string, mixed>>, updated_at: string|null}
     */
    public function read(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return ['entries' => [], 'updated_at' => null];
        }

        $parsed = Yaml::parseFile($path);

        if (! is_array($parsed)) {
            return ['entries' => [], 'updated_at' => null];
        }

        return [
            'entries' => array_values(array_filter(
                $parsed['entries'] ?? [],
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
        return $this->read()['entries'];
    }

    /**
     * Resolve the diff for a spec/task (or an explicit commit/range) and append
     * one code-history entry.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function log(array $attributes): array
    {
        $spec = $this->normalizeSpec($attributes['spec'] ?? null);
        $task = trim((string) ($attributes['task'] ?? '')) ?: null;
        $explicitRange = trim((string) ($attributes['range'] ?? '')) ?: null;
        $explicitCommit = trim((string) ($attributes['commit'] ?? '')) ?: null;

        [$range, $commit] = $this->resolveRange($spec, $task, $explicitRange, $explicitCommit);

        $files = $this->git->changeSet($range === 'working-tree' ? null : $range);

        $totals = [
            'files' => count($files),
            'added' => array_sum(array_map(static fn (array $f): int => (int) $f['added'], $files)),
            'removed' => array_sum(array_map(static fn (array $f): int => (int) $f['removed'], $files)),
        ];

        $entry = [
            'id' => bin2hex(random_bytes(8)),
            'ts' => $this->normalizeTimestamp($attributes['ts'] ?? null),
            'user' => trim((string) ($attributes['user'] ?? $this->detectUser())),
            'phase' => trim((string) ($attributes['skill'] ?? $attributes['phase'] ?? '')) ?: null,
            'spec' => $spec,
            'task' => $task,
            'commit' => $commit,
            'range' => $range,
            'files' => $files,
            'totals' => $totals,
            'note' => trim((string) ($attributes['note'] ?? '')) ?: null,
        ];

        $data = $this->read();
        $data['entries'][] = $entry;

        $this->write($data['entries']);

        return $entry;
    }

    /**
     * @return array{0: string, 1: string|null} [range, short commit sha|null]
     */
    protected function resolveRange(?string $spec, ?string $task, ?string $explicitRange, ?string $explicitCommit): array
    {
        if ($explicitRange !== null) {
            return [$explicitRange, null];
        }

        if ($explicitCommit !== null) {
            $details = $this->git->commitDetails($explicitCommit);
            $sha = $details['sha'] ?? $explicitCommit;

            return [$sha.'~1..'.$sha, $details['short_sha'] ?? substr($explicitCommit, 0, 7)];
        }

        if ($spec !== null && $task !== null) {
            $details = $this->git->resolveTaskCommit($spec, $task);

            if ($details !== null) {
                return [$details['sha'].'~1..'.$details['sha'], $details['short_sha']];
            }
        }

        return ['working-tree', null];
    }

    /**
     * @param  array{file?: string|null, spec?: string|null, limit?: int|null}  $filters
     * @return list<array<string, mixed>>
     */
    public function query(array $filters = []): array
    {
        $file = isset($filters['file']) && is_string($filters['file']) && trim($filters['file']) !== ''
            ? trim($filters['file'])
            : null;
        $spec = isset($filters['spec']) ? $this->normalizeSpec($filters['spec']) : null;
        $limit = isset($filters['limit']) ? max(0, (int) $filters['limit']) : 0;

        $entries = array_values(array_filter(
            $this->entries(),
            function (array $entry) use ($file, $spec): bool {
                if ($spec !== null && strtoupper((string) ($entry['spec'] ?? '')) !== $spec) {
                    return false;
                }

                if ($file !== null) {
                    $paths = array_map(
                        static fn (array $f): string => (string) ($f['path'] ?? ''),
                        is_array($entry['files'] ?? null) ? $entry['files'] : []
                    );

                    foreach ($paths as $path) {
                        if ($path === $file || str_contains($path, $file)) {
                            return true;
                        }
                    }

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
     * Touchpoints grouped by file path and by spec — a map of where the code has
     * been worked on over time.
     *
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $byFile = [];
        $bySpec = [];

        foreach ($this->entries() as $entry) {
            $spec = (string) ($entry['spec'] ?? '—');
            $task = (string) ($entry['task'] ?? '');
            $commit = $entry['commit'] ?? null;
            $ts = (string) ($entry['ts'] ?? '');

            foreach (is_array($entry['files'] ?? null) ? $entry['files'] : [] as $file) {
                $path = (string) ($file['path'] ?? '');

                if ($path === '') {
                    continue;
                }

                $byFile[$path] ??= ['path' => $path, 'touch_count' => 0, 'touchpoints' => []];
                $byFile[$path]['touch_count']++;
                $byFile[$path]['touchpoints'][] = [
                    'spec' => $spec,
                    'task' => $task ?: null,
                    'commit' => $commit,
                    'ts' => $ts,
                    'hunks' => $file['hunks'] ?? [],
                    'added' => (int) ($file['added'] ?? 0),
                    'removed' => (int) ($file['removed'] ?? 0),
                ];
            }

            $bySpec[$spec] ??= ['spec' => $spec, 'entries' => 0, 'files' => 0];
            $bySpec[$spec]['entries']++;
            $bySpec[$spec]['files'] += (int) ($entry['totals']['files'] ?? 0);
        }

        ksort($byFile);
        ksort($bySpec);

        return [
            'by_file' => array_values($byFile),
            'by_spec' => array_values($bySpec),
            'entry_count' => count($this->entries()),
            'path' => $this->config->relativePath($this->path()),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    protected function write(array $entries): void
    {
        $payload = [
            'entries' => array_values($entries),
            'updated_at' => $this->normalizeTimestamp(null),
        ];

        AtomicFile::write(
            $this->path(),
            Yaml::dump($payload, 8, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
        );
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
