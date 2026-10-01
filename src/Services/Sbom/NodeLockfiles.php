<?php

declare(strict_types=1);

namespace Larapilot\Services\Sbom;

use Symfony\Component\Yaml\Yaml;

/**
 * The packages a JavaScript lockfile resolved, whatever wrote it: npm
 * (`package-lock.json` v1 to v3), pnpm (`pnpm-lock.yaml` v5 to v9), Yarn
 * (classic and Berry `yarn.lock`), and Bun (the text `bun.lock`; the binary
 * `bun.lockb` cannot be read).
 *
 * Each package comes once per version, with whether it is a dev dependency
 * when the lockfile says so, whether the project asked for it directly, and
 * its license when the lockfile — or the installed `node_modules` — carries
 * it.
 */
class NodeLockfiles
{
    /**
     * Lockfiles in the order they are looked for.
     *
     * @var array<string, string>
     */
    public const FILES = [
        'package-lock.json' => 'npm',
        'npm-shrinkwrap.json' => 'npm',
        'pnpm-lock.yaml' => 'pnpm',
        'yarn.lock' => 'yarn',
        'bun.lock' => 'bun',
        'bun.lockb' => 'bun',
    ];

    private const LICENSE_READS = 4000;

    /**
     * The lockfile of a folder, if any.
     *
     * @return array{file: string, manager: string}|null
     */
    public function find(string $root): ?array
    {
        foreach (self::FILES as $file => $manager) {
            if (is_file($root.'/'.$file)) {
                return ['file' => $file, 'manager' => $manager];
            }
        }

        return null;
    }

    /**
     * @return array{file: string|null, manager: string|null, readable: bool, error: string|null, packages: list<array{name: string, version: string, dev: bool|null, direct: bool, license: list<string>, constraint?: string|null}>}
     */
    public function read(string $root): array
    {
        $found = $this->find($root);
        $result = ['file' => $found['file'] ?? null, 'manager' => $found['manager'] ?? null, 'readable' => false, 'error' => null, 'packages' => []];

        if ($found === null) {
            return $result;
        }

        $path = $root.'/'.$found['file'];
        $manifest = $this->manifest($root);

        try {
            $packages = match ($found['file']) {
                'package-lock.json', 'npm-shrinkwrap.json' => $this->npm($path),
                'pnpm-lock.yaml' => $this->pnpm($path),
                'yarn.lock' => $this->yarn($path),
                'bun.lock' => $this->bun($path),
                default => throw new \RuntimeException('bun.lockb is binary: run `bun install --save-text-lockfile` (Bun 1.1.39+) to write bun.lock.'),
            };
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();

            return $result;
        }

        // What package.json asks for is direct, whatever the lockfile says.
        foreach ($packages as $index => $package) {
            $packages[$index]['constraint'] = null;

            if (isset($manifest['prod'][$package['name']])) {
                $packages[$index]['direct'] = true;
                $packages[$index]['dev'] ??= false;
                $packages[$index]['constraint'] = (string) $manifest['prod'][$package['name']];
            } elseif (isset($manifest['dev'][$package['name']])) {
                $packages[$index]['direct'] = true;
                $packages[$index]['dev'] ??= true;
                $packages[$index]['constraint'] = (string) $manifest['dev'][$package['name']];
            }
        }

        $result['readable'] = true;
        $result['packages'] = $this->withLicenses($root, $this->unique($packages));

        return $result;
    }

    /**
     * @return array{prod: array<string, string>, dev: array<string, string>}
     */
    protected function manifest(string $root): array
    {
        $package = is_file($root.'/package.json') ? json_decode((string) file_get_contents($root.'/package.json'), true) : null;

        return [
            'prod' => array_merge(
                is_array($package['dependencies'] ?? null) ? $package['dependencies'] : [],
                is_array($package['optionalDependencies'] ?? null) ? $package['optionalDependencies'] : []
            ),
            'dev' => is_array($package['devDependencies'] ?? null) ? $package['devDependencies'] : [],
        ];
    }

    /**
     * @return list<array{name: string, version: string, dev: bool|null, direct: bool, license: list<string>}>
     */
    protected function npm(string $path): array
    {
        $lock = json_decode((string) file_get_contents($path), true);

        if (! is_array($lock)) {
            throw new \RuntimeException(basename($path).' is not valid JSON.');
        }

        $packages = [];

        // v2 and v3: a flat map keyed by the install path.
        if (is_array($lock['packages'] ?? null)) {
            foreach ($lock['packages'] as $key => $entry) {
                if ($key === '' || ! is_array($entry) || ! empty($entry['link'])) {
                    continue;
                }

                $position = strrpos((string) $key, 'node_modules/');

                if ($position === false) {
                    // A workspace package of this repository, not a dependency.
                    continue;
                }

                $name = is_string($entry['name'] ?? null) ? $entry['name'] : substr((string) $key, $position + strlen('node_modules/'));
                $version = (string) ($entry['version'] ?? '');

                if ($name === '' || $version === '') {
                    continue;
                }

                $packages[] = [
                    'name' => $name,
                    'version' => $version,
                    'dev' => (bool) ($entry['dev'] ?? false) || (bool) ($entry['devOptional'] ?? false),
                    // Hoisting puts transitive packages at the top too: package.json decides.
                    'direct' => false,
                    'license' => self::licenses($entry['license'] ?? null),
                ];
            }

            return $packages;
        }

        // v1: a tree of dependencies.
        $walk = function (array $dependencies, bool $top) use (&$walk, &$packages): void {
            foreach ($dependencies as $name => $entry) {
                if (! is_array($entry) || ! is_string($entry['version'] ?? null)) {
                    continue;
                }

                $packages[] = ['name' => (string) $name, 'version' => $entry['version'], 'dev' => (bool) ($entry['dev'] ?? false), 'direct' => false, 'license' => []];

                if (is_array($entry['dependencies'] ?? null)) {
                    $walk($entry['dependencies'], false);
                }
            }
        };

        $walk(is_array($lock['dependencies'] ?? null) ? $lock['dependencies'] : [], true);

        return $packages;
    }

    /**
     * @return list<array{name: string, version: string, dev: bool|null, direct: bool, license: list<string>}>
     */
    protected function pnpm(string $path): array
    {
        $lock = Yaml::parse((string) file_get_contents($path));

        if (! is_array($lock)) {
            throw new \RuntimeException('pnpm-lock.yaml could not be read.');
        }

        $packages = [];

        foreach (is_array($lock['packages'] ?? null) ? $lock['packages'] : [] as $key => $entry) {
            [$name, $version] = self::pnpmKey((string) $key);

            if ($name === null || $version === null) {
                continue;
            }

            $entry = is_array($entry) ? $entry : [];

            $packages[] = [
                'name' => is_string($entry['name'] ?? null) ? $entry['name'] : $name,
                'version' => is_string($entry['version'] ?? null) ? $entry['version'] : $version,
                // v6 says it; v9 leaves it to the importers.
                'dev' => array_key_exists('dev', $entry) ? (bool) $entry['dev'] : null,
                'direct' => false,
                'license' => [],
            ];
        }

        return $packages;
    }

    /**
     * `/name@1.2.3`, `name@1.2.3(peer@1.0.0)`, `/@scope/name/1.2.3` (v5).
     *
     * @return array{0: string|null, 1: string|null}
     */
    public static function pnpmKey(string $key): array
    {
        $key = ltrim($key, '/');
        $key = (string) preg_replace('/\(.*$/', '', $key);

        // v5: `name/1.2.3`, with an optional `_peer@1.0.0` suffix.
        if (preg_match('#^((?:@[^/@]+/)?[^/@]+)/(\d[^/_]*)(?:_.*)?$#', $key, $match) === 1) {
            return [$match[1], $match[2]];
        }

        // v6 and v9: `name@1.2.3`; v6 may add `_peer@1.0.0`.
        if (preg_match('#^((?:@[^/@]+/)?[^/@]+)@([^@_(]+)#', $key, $match) === 1) {
            return [$match[1], $match[2]];
        }

        return [null, null];
    }

    /**
     * Classic (`version "1.2.3"`) and Berry (`version: 1.2.3`) alike.
     *
     * @return list<array{name: string, version: string, dev: bool|null, direct: bool, license: list<string>}>
     */
    protected function yarn(string $path): array
    {
        $packages = [];
        $descriptor = null;

        foreach (preg_split('/\R/', (string) file_get_contents($path)) ?: [] as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! str_starts_with($line, ' ') && str_ends_with(rtrim($line), ':')) {
                $descriptor = trim(rtrim(rtrim($line), ':'));

                continue;
            }

            if ($descriptor === null || preg_match('/^\s+version:?\s+"?([^"\s]+)"?\s*$/', $line, $m) !== 1) {
                continue;
            }

            $name = self::yarnName($descriptor);

            if ($name !== null) {
                $packages[] = ['name' => $name, 'version' => $m[1], 'dev' => null, 'direct' => false, 'license' => []];
            }

            $descriptor = null;
        }

        return $packages;
    }

    /**
     * The package a Yarn descriptor names: `"@scope/name@^1.0.0", "@scope/name@^1.1.0"`
     * or `"name@npm:^1.0.0"`. Workspaces, patches, and the metadata block are
     * not packages from the registry.
     */
    public static function yarnName(string $descriptor): ?string
    {
        $first = trim(explode(',', $descriptor)[0], " \"'");

        if ($first === '__metadata' || str_contains($first, '@workspace:') || str_contains($first, '@patch:') || str_contains($first, '@link:') || str_contains($first, '@portal:')) {
            return null;
        }

        $at = strrpos($first, '@');

        if ($at === false || $at === 0) {
            return null;
        }

        $name = substr($first, 0, $at);

        // An alias, `alias@npm:real@^1.0.0`: the package is the real one.
        if (str_contains($name, '@npm:')) {
            $name = substr($name, (int) strpos($name, '@npm:') + 5);
        }

        // Berry: `name@npm:^1.0.0` leaves `name@npm` when the range has no `@`.
        return (string) preg_replace('/@npm$/', '', $name);
    }

    /**
     * `bun.lock`: JSON with trailing commas. Each package is
     * `"name": ["name@1.2.3", "", {…}, "sha512-…"]`.
     *
     * @return list<array{name: string, version: string, dev: bool|null, direct: bool, license: list<string>}>
     */
    protected function bun(string $path): array
    {
        $text = (string) preg_replace('/,(\s*[}\]])/', '$1', (string) file_get_contents($path));
        $lock = json_decode($text, true);

        if (! is_array($lock)) {
            throw new \RuntimeException('bun.lock could not be read.');
        }

        $packages = [];

        foreach (is_array($lock['packages'] ?? null) ? $lock['packages'] : [] as $entry) {
            $spec = is_array($entry) ? (string) ($entry[0] ?? '') : '';
            $at = strrpos($spec, '@');

            if ($at === false || $at === 0 || str_contains($spec, '@workspace:')) {
                continue;
            }

            $packages[] = ['name' => substr($spec, 0, $at), 'version' => (string) preg_replace('/^npm:/', '', substr($spec, $at + 1)), 'dev' => null, 'direct' => false, 'license' => []];
        }

        return $packages;
    }

    /**
     * One row per name and version; a package that is a dev dependency on
     * one path and a production one on another is a production one.
     *
     * @param  list<array{name: string, version: string, dev: bool|null, direct: bool, license: list<string>}>  $packages
     * @return list<array{name: string, version: string, dev: bool|null, direct: bool, license: list<string>}>
     */
    protected function unique(array $packages): array
    {
        $unique = [];

        foreach ($packages as $package) {
            $key = $package['name'].'@'.$package['version'];

            if (! isset($unique[$key])) {
                $unique[$key] = $package;

                continue;
            }

            $unique[$key]['direct'] = $unique[$key]['direct'] || $package['direct'];
            $unique[$key]['license'] = $unique[$key]['license'] !== [] ? $unique[$key]['license'] : $package['license'];

            if ($unique[$key]['dev'] === null || $package['dev'] === null) {
                $unique[$key]['dev'] = $unique[$key]['dev'] ?? $package['dev'];
            } else {
                $unique[$key]['dev'] = $unique[$key]['dev'] && $package['dev'];
            }
        }

        $unique = array_values($unique);
        usort($unique, static fn (array $a, array $b): int => [$a['name'], $a['version']] <=> [$b['name'], $b['version']]);

        return $unique;
    }

    /**
     * Licenses the lockfile left out, read from `node_modules` when the
     * packages are installed.
     *
     * @param  list<array{name: string, version: string, dev: bool|null, direct: bool, license: list<string>}>  $packages
     * @return list<array{name: string, version: string, dev: bool|null, direct: bool, license: list<string>}>
     */
    protected function withLicenses(string $root, array $packages): array
    {
        if (! is_dir($root.'/node_modules')) {
            return $packages;
        }

        $reads = 0;

        foreach ($packages as $index => $package) {
            if ($package['license'] !== [] || ++$reads > self::LICENSE_READS) {
                continue;
            }

            $manifest = $root.'/node_modules/'.$package['name'].'/package.json';

            if (! is_file($manifest)) {
                continue;
            }

            $data = json_decode((string) file_get_contents($manifest), true);

            if (is_array($data) && ($data['version'] ?? null) === $package['version']) {
                $packages[$index]['license'] = self::licenses($data['license'] ?? ($data['licenses'] ?? null));
            }
        }

        return $packages;
    }

    /**
     * @return list<string>
     */
    public static function licenses(mixed $license): array
    {
        if (is_string($license) && trim($license) !== '') {
            return [trim($license)];
        }

        if (is_array($license)) {
            if (is_string($license['type'] ?? null)) {
                return [$license['type']];
            }

            $list = [];

            foreach ($license as $item) {
                $list = array_merge($list, self::licenses($item));
            }

            return array_values(array_unique($list));
        }

        return [];
    }
}
