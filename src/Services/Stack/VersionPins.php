<?php

declare(strict_types=1);

namespace Larapilot\Services\Stack;

/**
 * The places outside the code that fix a version of the stack: the PHP a
 * Dockerfile starts from, the MySQL image of a compose file, the Node of a
 * CI job, the runtime of `vapor.yml`. An upgrade that changes the code and
 * forgets one of them ships an application its servers cannot run.
 *
 * Each pin is one line of one file, with what it pins (`php`, `node`,
 * `mysql`, `mariadb`, `pgsql`) and the version as written.
 */
class VersionPins
{
    public const KINDS = ['php', 'node', 'mysql', 'mariadb', 'pgsql'];

    private const LIMIT = 200;

    /**
     * Files read at the root of the project, besides the folders below.
     *
     * @var list<string>
     */
    private const ROOT_FILES = [
        'composer.json', 'package.json', '.php-version', '.nvmrc', '.node-version', '.tool-versions',
        'Dockerfile', 'docker-compose.yml', 'docker-compose.yaml', 'compose.yml', 'compose.yaml',
        'docker-compose.override.yml', 'docker-compose.prod.yml', 'vapor.yml', 'herd.yml', 'fly.toml',
        'nixpacks.toml', '.gitlab-ci.yml', 'bitbucket-pipelines.yml', 'azure-pipelines.yml',
        'phpstan.neon', 'phpstan.neon.dist', 'phpstan.dist.neon', 'rector.php', 'phpunit.xml', 'phpunit.xml.dist',
        '.env.example', 'netlify.toml', 'render.yaml', 'app.yaml', 'Procfile',
    ];

    /**
     * Folders whose YAML and Docker files are read too.
     *
     * @var list<string>
     */
    private const FOLDERS = ['.github/workflows', 'docker', '.docker', '.devcontainer', 'deploy', '.circleci', 'infra'];

    /**
     * @param  list<string>|null  $kinds
     * @return list<array{file: string, line: int, kind: string, value: string, text: string}>
     */
    public function scan(string $root, ?array $kinds = null): array
    {
        $kinds = $kinds === null ? self::KINDS : array_values(array_intersect(self::KINDS, $kinds));
        $pins = [];

        foreach ($this->files($root) as $relative) {
            $path = $root.'/'.$relative;

            if (! is_file($path) || filesize($path) > 512 * 1024) {
                continue;
            }

            $lines = preg_split('/\R/', (string) file_get_contents($path)) ?: [];
            $base = strtolower(basename($relative));

            foreach ($lines as $index => $line) {
                foreach ($this->match($base, $relative, $line) as [$kind, $value]) {
                    if (! in_array($kind, $kinds, true)) {
                        continue;
                    }

                    $pins[] = [
                        'file' => $relative,
                        'line' => $index + 1,
                        'kind' => $kind,
                        'value' => $value,
                        'text' => mb_substr(trim($line), 0, 160),
                    ];

                    if (count($pins) >= self::LIMIT) {
                        return $pins;
                    }
                }
            }
        }

        return $pins;
    }

    /**
     * @return list<string>
     */
    protected function files(string $root): array
    {
        $files = [];

        foreach (self::ROOT_FILES as $file) {
            if (is_file($root.'/'.$file)) {
                $files[] = $file;
            }
        }

        foreach (glob($root.'/Dockerfile*') ?: [] as $path) {
            $files[] = basename($path);
        }

        foreach (self::FOLDERS as $folder) {
            if (! is_dir($root.'/'.$folder)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root.'/'.$folder, \FilesystemIterator::SKIP_DOTS)
            );

            $count = 0;

            foreach ($iterator as $file) {
                if (! $file instanceof \SplFileInfo || ! $file->isFile() || ++$count > 200) {
                    continue;
                }

                $name = strtolower($file->getFilename());

                if (preg_match('/\.(ya?ml|toml|json|ini|cnf|conf|env)$/', $name) === 1 || str_starts_with($name, 'dockerfile') || str_ends_with($name, '.dockerfile')) {
                    $files[] = ltrim(substr($file->getPathname(), strlen($root)), '/');
                }
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * What one line pins, if anything.
     *
     * @return list<array{0: string, 1: string}>
     */
    protected function match(string $base, string $relative, string $line): array
    {
        $found = [];
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '//')) {
            return [];
        }

        if ($base === 'composer.json') {
            if (preg_match('/"php"\s*:\s*"([^"]+)"/', $line, $m) === 1) {
                $found[] = ['php', $m[1]];
            }

            return $found;
        }

        if ($base === 'package.json') {
            if (preg_match('/"node"\s*:\s*"([^"]+)"/', $line, $m) === 1) {
                $found[] = ['node', $m[1]];
            }

            return $found;
        }

        if ($base === '.php-version') {
            return preg_match('/^(\d+\.\d+(?:\.\d+)?)/', $trimmed, $m) === 1 ? [['php', $m[1]]] : [];
        }

        if ($base === '.nvmrc' || $base === '.node-version') {
            return preg_match('/^v?(\d+(?:\.\d+){0,2}|lts\/[a-z*]+)/i', $trimmed, $m) === 1 ? [['node', $m[1]]] : [];
        }

        if ($base === '.tool-versions') {
            if (preg_match('/^php\s+(\S+)/', $trimmed, $m) === 1) {
                $found[] = ['php', $m[1]];
            }

            if (preg_match('/^(?:nodejs|node)\s+(\S+)/', $trimmed, $m) === 1) {
                $found[] = ['node', $m[1]];
            }

            return $found;
        }

        if (in_array($base, ['phpstan.neon', 'phpstan.neon.dist', 'phpstan.dist.neon'], true)) {
            return preg_match('/phpVersion\s*:\s*(\d{5}|\d+\.\d+)/', $line, $m) === 1 ? [['php', self::phpId($m[1])]] : [];
        }

        if ($base === 'rector.php') {
            if (preg_match('/withPhpSets\(\s*php(\d)(\d+)\s*:/', $line, $m) === 1) {
                $found[] = ['php', $m[1].'.'.$m[2]];
            } elseif (preg_match('/(?:UP_TO_PHP_|PhpVersion::PHP_|PHP_)(\d)(\d+)\b/', $line, $m) === 1) {
                $found[] = ['php', $m[1].'.'.$m[2]];
            }

            return $found;
        }

        if ($base === 'vapor.yml') {
            return preg_match('/runtime\s*:\s*[\'"]?php-(\d+\.\d+)/', $line, $m) === 1 ? [['php', $m[1]]] : [];
        }

        if ($base === 'herd.yml') {
            return preg_match('/^\s*php\s*:\s*[\'"]?(\d+\.\d+)/', $line, $m) === 1 ? [['php', $m[1]]] : [];
        }

        if (in_array($base, ['phpunit.xml', 'phpunit.xml.dist', '.env.example'], true)) {
            // Only the engine a test run or a fresh checkout is told to use.
            return [];
        }

        // Docker, compose, CI and platform files: images, setup actions, versions.
        if (preg_match('/^\s*FROM\s+(?:--platform=\S+\s+)?([^\s]+)/i', $line, $m) === 1) {
            $found = array_merge($found, $this->image($m[1]));
        }

        if (preg_match('/\bimage\s*:\s*[\'"]?([^\s\'"]+)/i', $line, $m) === 1) {
            $found = array_merge($found, $this->image($m[1]));
        }

        if (preg_match('#vendor/laravel/sail/runtimes/(\d+\.\d+)#', $line, $m) === 1) {
            $found[] = ['php', $m[1]];
        }

        if (preg_match('/\bphp-version\s*:\s*\[?\s*[\'"]?(\d+\.\d+)/i', $line, $m) === 1) {
            $found[] = ['php', $m[1]];
        }

        if (preg_match('/^\s*-?\s*php\s*:\s*\[\s*[\'"]?(\d+\.\d+)/i', $line, $m) === 1) {
            $found[] = ['php', $m[1]];
        }

        if (preg_match('/\bnode-version\s*:\s*\[?\s*[\'"]?(\d+(?:\.\d+)*|lts\/\*)/i', $line, $m) === 1) {
            $found[] = ['node', $m[1]];
        }

        if (preg_match('/\b(?:PHP_VERSION|NIXPACKS_PHP_VERSION)\s*[=:]\s*[\'"]?(\d+\.\d+)/', $line, $m) === 1) {
            $found[] = ['php', $m[1]];
        }

        if (preg_match('/\bNODE_VERSION\s*[=:]\s*[\'"]?(\d+(?:\.\d+)*)/', $line, $m) === 1) {
            $found[] = ['node', $m[1]];
        }

        return $this->unique($found);
    }

    /**
     * What a container image pins.
     *
     * @return list<array{0: string, 1: string}>
     */
    protected function image(string $image): array
    {
        $image = strtolower(trim($image, '\'"'));

        if (! str_contains($image, ':')) {
            return [];
        }

        [$name, $tag] = explode(':', $image, 2);
        $name = (string) preg_replace('#^(?:docker\.io/|library/)#', '', $name);
        $short = basename($name);

        if (preg_match('/^(\d+(?:\.\d+){0,2})/', $tag, $m) !== 1) {
            // `sail-8.3/app`: the version is in the name.
            if (preg_match('#^sail-(\d+\.\d+)/app$#', $name, $sail) === 1) {
                return [['php', $sail[1]]];
            }

            return [];
        }

        $version = $m[1];

        return match (true) {
            in_array($short, ['php', 'php-fpm', 'php-cli', 'php-apache', 'php-nginx'], true),
            str_starts_with($name, 'serversideup/php'),
            str_starts_with($name, 'bitnami/php'),
            str_starts_with($name, 'webdevops/php') => [['php', $version]],
            str_starts_with($short, 'node') => [['node', $version]],
            $short === 'mysql' || $short === 'mysql-server' || $name === 'bitnami/mysql' || $name === 'percona/percona-server' => [['mysql', $version]],
            $short === 'mariadb' || $name === 'bitnami/mariadb' => [['mariadb', $version]],
            in_array($short, ['postgres', 'postgresql'], true), str_starts_with($name, 'postgis/'), $name === 'pgvector/pgvector', $name === 'bitnami/postgresql' => [['pgsql', $this->postgresTag($tag)]],
            (bool) preg_match('#^sail-(\d+\.\d+)/app$#', $name, $sail) => [['php', $sail[1]]],
            default => [],
        };
    }

    protected function postgresTag(string $tag): string
    {
        // `pg16`, `16-3.4` (postgis): the major comes first.
        return preg_match('/(\d+)/', $tag, $m) === 1 ? $m[1] : $tag;
    }

    protected static function phpId(string $value): string
    {
        if (preg_match('/^(\d)(\d{2})\d{2}$/', $value, $m) === 1) {
            return $m[1].'.'.(int) $m[2];
        }

        return $value;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $found
     * @return list<array{0: string, 1: string}>
     */
    protected function unique(array $found): array
    {
        $seen = [];

        foreach ($found as $pin) {
            $seen[$pin[0].'|'.$pin[1]] = $pin;
        }

        return array_values($seen);
    }

    /**
     * The pins of one kind that disagree with a version: a PHP 8.2 image
     * left behind by an upgrade to 8.4.
     *
     * @param  list<array{file: string, line: int, kind: string, value: string, text: string}>  $pins
     * @return list<array{file: string, line: int, kind: string, value: string, text: string}>
     */
    public static function behind(array $pins, string $kind, string $version): array
    {
        return array_values(array_filter($pins, static function (array $pin) use ($kind, $version): bool {
            if ($pin['kind'] !== $kind) {
                return false;
            }

            if (preg_match('/(\d+)(?:\.(\d+))?/', $pin['value'], $m) !== 1 || preg_match('/(\d+)(?:\.(\d+))?/', $version, $t) !== 1) {
                return false;
            }

            // Compare at the precision the pin is written with: `node:20` is a major.
            $precise = isset($m[2]) && isset($t[2]);
            $pinned = $m[1].($precise ? '.'.$m[2] : '');
            $target = $t[1].($precise ? '.'.$t[2] : '');

            // A constraint like `^8.2` in composer.json is a floor, not a pin.
            if (str_contains($pin['value'], '^') || str_contains($pin['value'], '>') || str_contains($pin['value'], '|')) {
                return version_compare($pinned, $target, '>');
            }

            return version_compare($pinned, $target, '!=');
        }));
    }
}
