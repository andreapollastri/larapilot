<?php

declare(strict_types=1);

namespace Larapilot\Services\Upgrade;

use Illuminate\Support\Facades\Http;
use Larapilot\Services\ConfigService;

/**
 * The releases of a package as Packagist publishes them for Composer 2
 * (`https://repo.packagist.org/p2/{vendor}/{package}.json`): every tagged
 * version with what it requires. The file is minified — each version lists
 * only what changed from the one before — and is expanded here the way
 * Composer does.
 *
 * Answers are kept for a day in `.larapilot/cache/packagist/`, so a second
 * check costs nothing. A package Packagist does not know (a private
 * repository: Nova, Spark, a Satis) answers null.
 */
class PackagistClient
{
    public const BASE_URL = 'https://repo.packagist.org/p2/';

    private const TTL = 86400;

    /**
     * @var array<string, list<array<string, mixed>>|null>
     */
    protected array $memory = [];

    /**
     * The message of the first connection failure of this run, when there was one.
     */
    protected ?string $unreachable = null;

    public function __construct(protected ConfigService $config) {}

    /**
     * Stable releases, newest first. Null when Packagist does not know the
     * package; an exception when it cannot be reached.
     *
     * @return list<array{version: string, normalized: string, require: array<string, string>, time: string|null, abandoned: bool|string}>|null
     *
     * @throws \RuntimeException
     */
    public function releases(string $package): ?array
    {
        $package = strtolower(trim($package));

        if (preg_match('#^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]?|-{0,2})[a-z0-9]+)*$#', $package) !== 1) {
            return null;
        }

        if (array_key_exists($package, $this->memory)) {
            return $this->memory[$package];
        }

        $cached = $this->fromCache($package);

        if ($cached !== false) {
            return $this->memory[$package] = $cached;
        }

        // One failed connection is enough: the other packages of the run
        // would each wait for the same timeout to learn the same thing.
        if ($this->unreachable !== null) {
            throw new \RuntimeException('Packagist could not be reached: '.$this->unreachable);
        }

        try {
            $response = Http::acceptJson()
                ->timeout(20)
                ->connectTimeout(8)
                ->withUserAgent('larapilot (+https://github.com/andreapollastri/larapilot)')
                ->get(self::BASE_URL.$package.'.json');
        } catch (\Throwable $e) {
            $this->unreachable = $e->getMessage();

            throw new \RuntimeException('Packagist could not be reached: '.$e->getMessage(), 0, $e);
        }

        if ($response->status() === 404) {
            $this->toCache($package, null);

            return $this->memory[$package] = null;
        }

        if (! $response->successful()) {
            throw new \RuntimeException('Packagist answered '.$response->status().' for '.$package.'.');
        }

        // Not `json('packages.'.$package)`: a name with a dot in it
        // (`mtdowling/jmespath.php`) would be read as a deeper path.
        $decoded = $response->json();
        $versions = is_array($decoded) ? ($decoded['packages'][$package] ?? null) : null;
        $releases = is_array($versions) ? $this->stable(self::expand($versions)) : [];

        $this->toCache($package, $releases);

        return $this->memory[$package] = $releases;
    }

    /**
     * Composer's metadata minifier, in reverse: each entry holds only the
     * keys that changed since the previous one, and `__unset` removes a key.
     *
     * @param  list<array<string, mixed>>  $versions
     * @return list<array<string, mixed>>
     */
    public static function expand(array $versions): array
    {
        $expanded = [];
        $current = null;

        foreach ($versions as $version) {
            if (! is_array($version)) {
                continue;
            }

            if ($current === null) {
                $current = $version;
                $expanded[] = $current;

                continue;
            }

            foreach ($version as $key => $value) {
                if ($value === '__unset') {
                    unset($current[$key]);
                } else {
                    $current[$key] = $value;
                }
            }

            $expanded[] = $current;
        }

        return $expanded;
    }

    /**
     * @param  list<array<string, mixed>>  $versions
     * @return list<array{version: string, normalized: string, require: array<string, string>, time: string|null, abandoned: bool|string}>
     */
    protected function stable(array $versions): array
    {
        $releases = [];

        foreach ($versions as $version) {
            $normalized = (string) ($version['version_normalized'] ?? '');

            // Branches, and alpha / beta / RC tags, are not what an upgrade lands on.
            if ($normalized === '' || str_starts_with($normalized, 'dev-') || str_ends_with($normalized, '-dev') || preg_match('/-(alpha|beta|rc|a|b)\d*/i', $normalized) === 1) {
                continue;
            }

            $abandoned = $version['abandoned'] ?? false;

            $releases[] = [
                'version' => ltrim((string) ($version['version'] ?? $normalized), 'v'),
                'normalized' => $normalized,
                'require' => array_map('strval', is_array($version['require'] ?? null) ? $version['require'] : []),
                'time' => is_string($version['time'] ?? null) ? $version['time'] : null,
                'abandoned' => is_string($abandoned) && $abandoned !== '' ? $abandoned : (bool) $abandoned,
            ];
        }

        usort($releases, static fn (array $a, array $b): int => version_compare($b['normalized'], $a['normalized']));

        return $releases;
    }

    protected function cachePath(string $package): string
    {
        return $this->config->absolutePath('.larapilot/cache/packagist/'.str_replace('/', '~', $package).'.json');
    }

    /**
     * @return list<array<string, mixed>>|null|false false when nothing fresh is cached
     */
    protected function fromCache(string $package): array|null|false
    {
        $path = $this->cachePath($package);

        if (! is_file($path) || filemtime($path) < time() - self::TTL) {
            return false;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded) || ! array_key_exists('releases', $decoded)) {
            return false;
        }

        return is_array($decoded['releases']) ? $decoded['releases'] : null;
    }

    /**
     * @param  list<array<string, mixed>>|null  $releases
     */
    protected function toCache(string $package, ?array $releases): void
    {
        $path = $this->cachePath($package);
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return;
        }

        $cache = dirname($directory);

        if (! is_file($cache.'/.gitignore')) {
            @file_put_contents($cache.'/.gitignore', "*\n");
        }

        @file_put_contents($path, json_encode(['package' => $package, 'releases' => $releases], JSON_UNESCAPED_SLASHES));
    }
}
