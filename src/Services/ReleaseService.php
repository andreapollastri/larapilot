<?php

declare(strict_types=1);

namespace Larapilot\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Larapilot\Support\AtomicFile;
use Symfony\Component\Yaml\Yaml;

/**
 * Release ledger persisted to `.larapilot/releases.yaml`.
 */
class ReleaseService
{
    /**
     * @var list<string>
     */
    public const STATUSES = ['planned', 'in_progress', 'shipped'];

    public function __construct(
        protected ConfigService $config,
    ) {}

    public function path(): string
    {
        $config = $this->config->resolve();

        return $this->config->absolutePath($config['paths']['releases'] ?? '.larapilot/releases.yaml');
    }

    /**
     * @return array{releases: list<array<string, mixed>>, updated_at: string|null}
     */
    public function read(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return ['releases' => [], 'updated_at' => null];
        }

        $parsed = Yaml::parseFile($path);

        if (! is_array($parsed)) {
            return ['releases' => [], 'updated_at' => null];
        }

        return [
            'releases' => array_values(array_filter(
                $parsed['releases'] ?? [],
                static fn (mixed $row): bool => is_array($row)
            )),
            'updated_at' => is_string($parsed['updated_at'] ?? null) ? $parsed['updated_at'] : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $status = null): array
    {
        $releases = $this->read()['releases'];

        if ($status === null || trim($status) === '') {
            return $this->sortReleases($releases);
        }

        $needle = strtolower(trim($status));

        return $this->sortReleases(array_values(array_filter(
            $releases,
            static fn (array $release): bool => strtolower((string) ($release['status'] ?? '')) === $needle
        )));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $version): ?array
    {
        $normalized = $this->normalizeVersion($version);

        foreach ($this->read()['releases'] as $release) {
            if ($this->normalizeVersion((string) ($release['version'] ?? '')) === $normalized) {
                return $release;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $specs
     * @return array<string, mixed>
     */
    public function add(string $version, string $title, string $status = 'planned', array $specs = [], ?string $branch = null): array
    {
        if (! $this->config->releaseModeEnabled()) {
            throw new \InvalidArgumentException('Release mode is disabled (settings.release_mode = NO).');
        }

        $version = $this->normalizeVersion($version);
        $title = trim($title);
        $status = $this->normalizeStatus($status);

        if ($title === '') {
            throw new \InvalidArgumentException('A release requires a --title.');
        }

        if ($this->find($version) !== null) {
            throw new \InvalidArgumentException("Release {$version} already exists.");
        }

        $entry = [
            'version' => $version,
            'title' => $title,
            'status' => $status,
            'branch' => $branch ?? 'release/'.$version,
            'specs' => $this->normalizeSpecs($specs),
            'created_at' => $this->now(),
            'shipped_at' => $status === 'shipped' ? $this->now() : null,
        ];

        $data = $this->read();
        $data['releases'][] = $entry;
        $this->write($data['releases']);

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $partial
     * @return array<string, mixed>
     */
    public function set(string $version, array $partial): array
    {
        if (! $this->config->releaseModeEnabled()) {
            throw new \InvalidArgumentException('Release mode is disabled (settings.release_mode = NO).');
        }

        $needle = $this->normalizeVersion($version);
        $data = $this->read();
        $found = false;

        foreach ($data['releases'] as $index => $release) {
            if ($this->normalizeVersion((string) ($release['version'] ?? '')) !== $needle) {
                continue;
            }

            $found = true;
            $current = $release;

            if (isset($partial['title'])) {
                $title = trim((string) $partial['title']);

                if ($title === '') {
                    throw new \InvalidArgumentException('Release title cannot be empty.');
                }

                $current['title'] = $title;
            }

            if (isset($partial['status'])) {
                $status = $this->normalizeStatus((string) $partial['status']);
                $current['status'] = $status;

                if ($status === 'shipped' && empty($current['shipped_at'])) {
                    $current['shipped_at'] = $this->normalizeTimestamp($partial['shipped_at'] ?? null);
                }
            }

            if (array_key_exists('branch', $partial)) {
                $branch = trim((string) $partial['branch']);
                $current['branch'] = $branch !== '' ? $branch : 'release/'.$needle;
            }

            if (isset($partial['specs'])) {
                $current['specs'] = $this->normalizeSpecs(is_array($partial['specs']) ? $partial['specs'] : []);
            }

            if (isset($partial['add_spec'])) {
                $specs = $this->normalizeSpecs($current['specs'] ?? []);
                $specs[] = strtoupper(trim((string) $partial['add_spec']));
                $current['specs'] = array_values(array_unique($specs));
            }

            if (isset($partial['shipped_at'])) {
                $current['shipped_at'] = $this->normalizeTimestamp($partial['shipped_at']);
            }

            $data['releases'][$index] = $current;
            $this->write($data['releases']);

            return $current;
        }

        if (! $found) {
            throw new \InvalidArgumentException("Release {$needle} not found.");
        }

        throw new \RuntimeException('Release update failed.');
    }

    /**
     * Import semver tags from Git history as shipped releases.
     *
     * @param  list<array{version: string, tagged_at: string|null, subject: string|null}>  $tags
     * @return array{imported: list<array<string, mixed>>, skipped: list<string>}
     */
    public function importFromTags(array $tags, bool $dryRun = false): array
    {
        if (! $this->config->releaseModeEnabled()) {
            throw new \InvalidArgumentException('Release mode is disabled (settings.release_mode = NO).');
        }

        $data = $this->read();
        $existing = array_map(
            fn (array $release): string => $this->normalizeVersion((string) ($release['version'] ?? '')),
            $data['releases']
        );
        $imported = [];
        $skipped = [];

        foreach ($tags as $tag) {
            $version = $this->normalizeVersion((string) ($tag['version'] ?? ''));

            if ($version === '' || in_array($version, $existing, true)) {
                $skipped[] = $version !== '' ? $version : '(invalid)';

                continue;
            }

            $title = trim((string) ($tag['subject'] ?? ''));

            if ($title === '') {
                $title = 'Release '.$version;
            }

            $entry = [
                'version' => $version,
                'title' => $title,
                'status' => 'shipped',
                'branch' => 'release/'.$version,
                'specs' => [],
                'created_at' => $this->normalizeTimestamp($tag['tagged_at'] ?? null),
                'shipped_at' => $this->normalizeTimestamp($tag['tagged_at'] ?? null),
            ];

            if (! $dryRun) {
                $data['releases'][] = $entry;
            }

            $imported[] = $entry;
            $existing[] = $version;
        }

        if (! $dryRun && $imported !== []) {
            $this->write($data['releases']);
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openReleases(): array
    {
        return $this->sortReleases(array_values(array_filter(
            $this->read()['releases'],
            static fn (array $release): bool => in_array(
                strtolower((string) ($release['status'] ?? '')),
                ['planned', 'in_progress'],
                true
            )
        )));
    }

    public function normalizeVersion(string $version): string
    {
        $normalized = ltrim(trim($version), 'vV');

        if ($normalized === '') {
            throw new \InvalidArgumentException('Release version is required.');
        }

        if (preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/', $normalized) !== 1) {
            throw new \InvalidArgumentException("Invalid semver version: {$version}. Expected X.Y.Z.");
        }

        return $normalized;
    }

    protected function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim(str_replace('-', '_', $status)));

        if (! in_array($normalized, self::STATUSES, true)) {
            throw new \InvalidArgumentException(
                'Invalid release status. Allowed: '.implode(', ', self::STATUSES).'.'
            );
        }

        return $normalized;
    }

    /**
     * @param  list<string>|list<mixed>  $specs
     * @return list<string>
     */
    protected function normalizeSpecs(array $specs): array
    {
        $normalized = [];

        foreach ($specs as $spec) {
            if (! is_string($spec) && ! is_numeric($spec)) {
                continue;
            }

            $code = strtoupper(trim((string) $spec));

            if ($code === '') {
                continue;
            }

            $normalized[] = $code;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  list<array<string, mixed>>  $releases
     */
    protected function write(array $releases): void
    {
        $path = $this->path();
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        AtomicFile::write(
            $path,
            Yaml::dump([
                'releases' => $this->sortReleases($releases),
                'updated_at' => $this->now(),
            ], 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
        );
    }

    /**
     * @param  list<array<string, mixed>>  $releases
     * @return list<array<string, mixed>>
     */
    protected function sortReleases(array $releases): array
    {
        usort($releases, function (array $a, array $b): int {
            return version_compare(
                (string) ($a['version'] ?? '0.0.0'),
                (string) ($b['version'] ?? '0.0.0')
            );
        });

        return $releases;
    }

    protected function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
    }

    protected function normalizeTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }

        return $date->format(DateTimeInterface::ATOM);
    }
}
