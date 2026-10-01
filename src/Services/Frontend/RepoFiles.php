<?php

declare(strict_types=1);

namespace Larapilot\Services\Frontend;

use Symfony\Component\Yaml\Yaml;

/**
 * Reading a frontend repository without running anything in it: a bounded
 * walk that never enters dependency or build folders, JSON that tolerates
 * the comments and trailing commas of `tsconfig.json`, and the glob dialect
 * the editors use in their rule files.
 */
final class RepoFiles
{
    /**
     * Folders that hold installed, generated, or cached files — never the
     * code or the rules of the team.
     *
     * @var list<string>
     */
    public const SKIP_DIRS = [
        'node_modules', '.git', '.nx', '.angular', '.next', '.nuxt', '.output', '.svelte-kit',
        '.turbo', '.cache', '.parcel-cache', '.vercel', '.netlify', '.yarn', '.pnpm-store',
        '.expo', '.docusaurus', '.astro', '.idea', 'dist', 'build', 'out', 'coverage', 'tmp',
        'temp', 'vendor', 'storybook-static', 'bower_components', 'jspm_packages',
    ];

    /**
     * Entries a walk visits before it stops and says so.
     */
    public const WALK_LIMIT = 150000;

    /**
     * Every file under the root a callback accepts, as paths relative to the
     * root with forward slashes, in a stable order.
     *
     * @param  callable(string): bool  $accept  Receives the relative path of a file.
     * @return array{files: list<string>, truncated: bool}
     */
    public static function walk(string $root, callable $accept, int $maxDepth = 10, int $limit = self::WALK_LIMIT, int $maxFiles = PHP_INT_MAX): array
    {
        $root = rtrim($root, '/\\');
        $files = [];
        $visited = 0;
        $truncated = false;
        $stack = [['', 0]];

        while ($stack !== []) {
            [$relative, $depth] = array_pop($stack);
            $absolute = $relative === '' ? $root : $root.'/'.$relative;
            $entries = @scandir($absolute);

            if ($entries === false) {
                continue;
            }

            $directories = [];

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                if (++$visited > $limit) {
                    $truncated = true;
                    break 2;
                }

                $path = $relative === '' ? $entry : $relative.'/'.$entry;
                $full = $root.'/'.$path;

                if (is_dir($full)) {
                    if ($depth < $maxDepth && ! in_array($entry, self::SKIP_DIRS, true) && ! is_link($full)) {
                        $directories[] = $path;
                    }

                    continue;
                }

                if ($accept($path)) {
                    $files[] = $path;

                    if (count($files) >= $maxFiles) {
                        $truncated = true;
                        break 2;
                    }
                }
            }

            foreach (array_reverse($directories) as $directory) {
                $stack[] = [$directory, $depth + 1];
            }
        }

        sort($files);

        return ['files' => $files, 'truncated' => $truncated];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function json(string $path): ?array
    {
        $content = self::read($path);

        if ($content === null) {
            return null;
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            $decoded = json_decode(self::stripJsonComments($content), true);
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function yaml(string $path): ?array
    {
        $content = self::read($path);

        if ($content === null) {
            return null;
        }

        try {
            $parsed = Yaml::parse($content);
        } catch (\Throwable) {
            return null;
        }

        return is_array($parsed) ? $parsed : null;
    }

    public static function read(string $path, ?int $maxBytes = null): ?string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $content = $maxBytes === null
            ? @file_get_contents($path)
            : @file_get_contents($path, false, null, 0, $maxBytes);

        if ($content === false || trim($content) === '') {
            return null;
        }

        return $content;
    }

    /**
     * JSON with comments (`//`, `/* … *\/`) and trailing commas, as
     * `tsconfig.json` and `rush.json` allow it, made strict.
     */
    public static function stripJsonComments(string $content): string
    {
        $out = '';
        $length = strlen($content);
        $inString = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $content[$i];
            $next = $content[$i + 1] ?? '';

            if ($inString) {
                $out .= $char;

                if ($char === '\\') {
                    $out .= $next;
                    $i++;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
                $out .= $char;

                continue;
            }

            if ($char === '/' && $next === '/') {
                while ($i < $length && $content[$i] !== "\n") {
                    $i++;
                }

                $out .= "\n";

                continue;
            }

            if ($char === '/' && $next === '*') {
                $end = strpos($content, '*/', $i + 2);
                $i = $end === false ? $length : $end + 1;

                continue;
            }

            $out .= $char;
        }

        return (string) preg_replace('/,(\s*[}\]])/', '$1', $out);
    }

    /**
     * Whether a path relative to the base matches one glob. A glob without a
     * slash matches the file name at any depth, as in `.gitignore`.
     */
    public static function matches(string $glob, string $path): bool
    {
        $glob = trim(str_replace('\\', '/', $glob));

        if ($glob === '') {
            return false;
        }

        if (str_starts_with($glob, './')) {
            $glob = substr($glob, 2);
        }

        if (str_starts_with($glob, '/')) {
            $glob = substr($glob, 1);
        } elseif (! str_contains($glob, '/')) {
            $glob = '**/'.$glob;
        }

        return preg_match(self::globToRegex($glob), $path) === 1;
    }

    /**
     * `**` crosses folders, `*` and `?` stay inside one, `{a,b}` is a choice
     * and `[…]` a class (`[!…]` negated).
     */
    public static function globToRegex(string $glob): string
    {
        $regex = '';
        $length = strlen($glob);
        $groups = 0;

        for ($i = 0; $i < $length; $i++) {
            $char = $glob[$i];

            if ($char === '*') {
                if (($glob[$i + 1] ?? '') === '*') {
                    $i++;

                    if (($glob[$i + 1] ?? '') === '/') {
                        $i++;
                        $regex .= '(?:.*/)?';
                    } else {
                        $regex .= '.*';
                    }
                } else {
                    $regex .= '[^/]*';
                }

                continue;
            }

            if ($char === '?') {
                $regex .= '[^/]';

                continue;
            }

            if ($char === '{') {
                $groups++;
                $regex .= '(?:';

                continue;
            }

            if ($char === '}' && $groups > 0) {
                $groups--;
                $regex .= ')';

                continue;
            }

            if ($char === ',' && $groups > 0) {
                $regex .= '|';

                continue;
            }

            if ($char === '[') {
                $close = strpos($glob, ']', $i + 1);

                if ($close !== false) {
                    $class = substr($glob, $i + 1, $close - $i - 1);

                    if (str_starts_with($class, '!')) {
                        $class = '^'.substr($class, 1);
                    }

                    $regex .= '['.str_replace(['#', '/'], ['\#', ''], $class).']';
                    $i = $close;

                    continue;
                }
            }

            $regex .= preg_quote($char, '#');
        }

        $regex .= str_repeat(')', $groups);

        return '#^'.$regex.'$#';
    }

    /**
     * The workspace globs (`packages/*`, `apps/**`, `!packages/legacy`) a
     * folder belongs to.
     *
     * @param  list<string>  $globs
     */
    public static function inWorkspace(string $directory, array $globs): bool
    {
        $included = false;

        foreach ($globs as $glob) {
            $glob = rtrim(trim($glob), '/');

            if ($glob === '') {
                continue;
            }

            if (str_starts_with($glob, '!')) {
                if (self::directoryMatches(substr($glob, 1), $directory)) {
                    return false;
                }

                continue;
            }

            $included = $included || self::directoryMatches($glob, $directory);
        }

        return $included;
    }

    protected static function directoryMatches(string $glob, string $directory): bool
    {
        $glob = rtrim(trim($glob), '/');

        if (str_starts_with($glob, './')) {
            $glob = substr($glob, 2);
        }

        return preg_match(self::globToRegex($glob), $directory) === 1;
    }

    /**
     * `apps/portal/src/main.ts` is under `apps/portal`; everything is under `.`.
     */
    public static function isUnder(string $path, string $directory): bool
    {
        $directory = trim($directory, '/');

        if ($directory === '' || $directory === '.') {
            return true;
        }

        return $path === $directory || str_starts_with($path, $directory.'/');
    }

    public static function dirname(string $relative): string
    {
        $directory = dirname($relative);

        return $directory === '.' || $directory === '' ? '.' : $directory;
    }

    public static function shortHash(string $content): string
    {
        return substr(sha1($content), 0, 12);
    }

    /**
     * The first `{name}.{ext}` that exists in the folder, for config files
     * that come in many extensions (`vite.config.ts`, `vite.config.mjs`, …).
     *
     * @param  list<string>  $names
     */
    public static function firstExisting(string $directory, array $names): ?string
    {
        foreach ($names as $name) {
            if (is_file($directory.'/'.$name)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * `vite.config` → `vite.config.ts`, `vite.config.mts`, … in that order.
     *
     * @return list<string>
     */
    public static function configNames(string $stem): array
    {
        return array_map(
            static fn (string $extension): string => $stem.'.'.$extension,
            ['ts', 'mts', 'cts', 'js', 'mjs', 'cjs', 'json']
        );
    }
}
