<?php

declare(strict_types=1);

namespace Larapilot\Support;

/**
 * `composer.json` and `composer.lock` of a project, read once.
 *
 * The lock is the truth about what is installed — every package, the
 * transitive ones too, with the version, the license, what it requires, and
 * whether it was abandoned when the lock was written. `composer.json` says
 * which of them the project asked for, and with which constraint.
 */
final class ComposerFiles
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $json;

    /**
     * The lock is read the first time something asks for it: it is the
     * heavy file, and the name of the project does not need it.
     *
     * @var array<string, mixed>|null|false false until read
     */
    private array|null|false $lock = false;

    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $installed = null;

    public function __construct(private readonly string $root)
    {
        $this->json = self::decode($root.'/composer.json');
    }

    public function root(): string
    {
        return $this->root;
    }

    public function hasJson(): bool
    {
        return $this->json !== null;
    }

    public function hasLock(): bool
    {
        return $this->lock() !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lock(): ?array
    {
        if ($this->lock === false) {
            $this->lock = self::decode($this->root.'/composer.lock');
        }

        return $this->lock;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        return $this->json ?? [];
    }

    public function name(): ?string
    {
        $name = $this->json['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    public function description(): ?string
    {
        $description = $this->json['description'] ?? null;

        return is_string($description) && trim($description) !== '' ? trim($description) : null;
    }

    /**
     * The PHP the project asks for in `require`.
     */
    public function phpConstraint(): ?string
    {
        $php = $this->json['require']['php'] ?? null;

        return is_string($php) && $php !== '' ? $php : null;
    }

    /**
     * The PHP version Composer resolves for, whatever runs it
     * (`config.platform.php`).
     */
    public function platformPhp(): ?string
    {
        $php = $this->json['config']['platform']['php'] ?? null;

        return is_string($php) && $php !== '' ? $php : null;
    }

    /**
     * The PHP the lock was written for: `platform-overrides`, else `platform`.
     */
    public function lockPlatformPhp(): ?string
    {
        $php = $this->lock()['platform-overrides']['php'] ?? $this->lock()['platform']['php'] ?? null;

        return is_string($php) && $php !== '' ? $php : null;
    }

    /**
     * The packages the project asked for, without PHP, extensions, and the
     * other platform packages.
     *
     * @return array<string, array{constraint: string, dev: bool}>
     */
    public function direct(): array
    {
        $direct = [];

        foreach (['require' => false, 'require-dev' => true] as $section => $dev) {
            foreach (is_array($this->json[$section] ?? null) ? $this->json[$section] : [] as $name => $constraint) {
                if (! is_string($name) || self::isPlatform($name)) {
                    continue;
                }

                $direct[strtolower($name)] = ['constraint' => (string) $constraint, 'dev' => $dev];
            }
        }

        return $direct;
    }

    /**
     * Every package the lock holds, by lower-case name.
     *
     * @return array<string, array{name: string, version: string, normalized: string, dev: bool, type: string, license: list<string>, description: string, homepage: string|null, source: string|null, require: array<string, string>, abandoned: bool|string, time: string|null}>
     */
    public function installed(): array
    {
        if ($this->installed !== null) {
            return $this->installed;
        }

        $installed = [];

        foreach (['packages' => false, 'packages-dev' => true] as $section => $dev) {
            foreach (is_array($this->lock()[$section] ?? null) ? $this->lock()[$section] : [] as $package) {
                if (! is_array($package) || ! is_string($package['name'] ?? null)) {
                    continue;
                }

                $version = (string) ($package['version'] ?? '');
                $abandoned = $package['abandoned'] ?? false;

                $installed[strtolower($package['name'])] = [
                    'name' => $package['name'],
                    'version' => self::display($version),
                    'normalized' => (string) ($package['version_normalized'] ?? $version),
                    'dev' => $dev,
                    'type' => (string) ($package['type'] ?? 'library'),
                    'license' => array_values(array_map('strval', is_array($package['license'] ?? null) ? $package['license'] : [])),
                    'description' => trim((string) ($package['description'] ?? '')),
                    'homepage' => is_string($package['homepage'] ?? null) && $package['homepage'] !== '' ? $package['homepage'] : null,
                    'source' => is_string($package['source']['url'] ?? null) ? self::cleanUrl($package['source']['url']) : null,
                    'require' => array_map('strval', is_array($package['require'] ?? null) ? $package['require'] : []),
                    'abandoned' => is_string($abandoned) && $abandoned !== '' ? $abandoned : (bool) $abandoned,
                    'time' => is_string($package['time'] ?? null) ? $package['time'] : null,
                ];
            }
        }

        return $this->installed = $installed;
    }

    /**
     * One installed package, or null.
     *
     * @return array<string, mixed>|null
     */
    public function package(string $name): ?array
    {
        return $this->installed()[strtolower($name)] ?? null;
    }

    /**
     * The installed version of a package, without the leading `v`.
     */
    public function version(string $name): ?string
    {
        $package = $this->package($name);

        return $package !== null ? (string) $package['version'] : null;
    }

    /**
     * The packages that require this one, from the lock.
     *
     * @return list<string>
     */
    public function dependents(string $name): array
    {
        $name = strtolower($name);
        $dependents = [];

        foreach ($this->installed() as $key => $package) {
            foreach (array_keys($package['require']) as $required) {
                if (strtolower((string) $required) === $name) {
                    $dependents[] = (string) $package['name'];
                    break;
                }
            }
        }

        sort($dependents);

        return $dependents;
    }

    /**
     * Repositories declared in `composer.json` besides Packagist: a package
     * served from one of them (Nova, Spark, a private Satis) is not on
     * Packagist.
     *
     * @return list<array{type: string, url: string}>
     */
    public function repositories(): array
    {
        $repositories = [];

        foreach (is_array($this->json['repositories'] ?? null) ? $this->json['repositories'] : [] as $repository) {
            if (! is_array($repository)) {
                continue;
            }

            $repositories[] = [
                'type' => (string) ($repository['type'] ?? ''),
                'url' => self::cleanUrl((string) ($repository['url'] ?? '')),
            ];
        }

        return $repositories;
    }

    public static function isPlatform(string $name): bool
    {
        $name = strtolower($name);

        return $name === 'php'
            || $name === 'php-64bit'
            || $name === 'hhvm'
            || str_starts_with($name, 'ext-')
            || str_starts_with($name, 'lib-')
            || str_starts_with($name, 'composer-')
            || $name === 'composer';
    }

    /**
     * A version as people read it: `v13.2.0` → `13.2.0`; a branch stays as
     * it is.
     */
    public static function display(string $version): string
    {
        return preg_match('/^v\d/', $version) === 1 ? substr($version, 1) : $version;
    }

    /**
     * A URL without the credentials an auth-protected repository puts in it.
     */
    public static function cleanUrl(string $url): string
    {
        return (string) preg_replace('#^([a-z][a-z0-9+.-]*://)[^/@\s]+@#i', '$1', trim($url));
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decode(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }
}
