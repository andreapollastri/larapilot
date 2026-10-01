<?php

declare(strict_types=1);

namespace Larapilot\Services\Frontend;

/**
 * Where the workspace of a frontend repository is.
 *
 * Usually the repository is its own workspace. Some teams keep each app in a
 * repository of its own and build it inside a monorepo they share: the app
 * repository holds a `project.json` with no `nx.json`, and its `tsconfig`
 * extends a `tsconfig.base.json` two folders up. Its toolchain, its shared
 * libraries, and often its agent rules live in that monorepo. The locator
 * finds it among the parent folders (the app cloned inside the monorepo), or
 * takes the one the user linked.
 */
final class WorkspaceLocator
{
    public const ANCESTOR_LEVELS = 6;

    /**
     * @return array{
     *     root: string,
     *     source: string,
     *     detached: bool,
     *     project_root: string|null,
     *     expected_root: string|null,
     *     signals: list<string>
     * }
     */
    public static function locate(string $repository, ?string $configured = null): array
    {
        $repository = rtrim($repository, '/\\');
        $signals = self::detachedSignals($repository);
        $here = [
            'root' => $repository,
            'source' => 'repository',
            'detached' => false,
            'project_root' => null,
            'expected_root' => null,
            'signals' => [],
        ];

        if ($signals === [] || self::isWorkspace($repository, false)) {
            return $here;
        }

        $found = null;

        if (is_string($configured) && $configured !== '' && is_dir($configured)) {
            $found = [realpath($configured) ?: rtrim($configured, '/\\'), 'configured'];
        } else {
            foreach (self::ancestors($repository) as $ancestor) {
                if (self::isWorkspace($ancestor, false)) {
                    $found = [$ancestor, 'ancestor'];

                    break;
                }
            }
        }

        // A repository with its own package.json still builds by itself when
        // no workspace is around it.
        if ($found === null && is_file($repository.'/package.json')) {
            return $here;
        }

        $expected = self::expectedRoot($repository);

        if ($found === null) {
            return [
                'root' => $repository,
                'source' => 'missing',
                'detached' => true,
                'project_root' => null,
                'expected_root' => $expected,
                'signals' => $signals,
            ];
        }

        [$root, $source] = $found;
        $root = rtrim($root, '/');
        $inside = str_starts_with($repository.'/', $root.'/');

        return [
            'root' => $root,
            'source' => $source,
            'detached' => true,
            'project_root' => $inside ? substr($repository, strlen($root) + 1) : null,
            'expected_root' => $expected,
            'signals' => $signals,
        ];
    }

    /**
     * The workspace around a repository that is a workspace of its own — an
     * app cloned inside a monorepo checkout. Its agent rules govern the app
     * too, as they do for an editor opened on the monorepo.
     */
    public static function enclosing(string $repository): ?string
    {
        foreach (self::ancestors(rtrim($repository, '/\\')) as $ancestor) {
            if (self::isWorkspace($ancestor, false)) {
                return $ancestor;
            }
        }

        return null;
    }

    /**
     * A folder that holds a workspace: Nx, Angular CLI, Rush, pnpm, Lerna,
     * or package-manager workspaces. `$anyPackage` also counts a plain
     * `package.json`, since a single app is its own workspace.
     */
    public static function isWorkspace(string $directory, bool $anyPackage): bool
    {
        foreach (['nx.json', 'angular.json', 'rush.json', 'pnpm-workspace.yaml', 'lerna.json'] as $marker) {
            if (is_file($directory.'/'.$marker)) {
                return true;
            }
        }

        $package = RepoFiles::json($directory.'/package.json');

        if ($package === null) {
            return false;
        }

        return $anyPackage || isset($package['workspaces']);
    }

    /**
     * What says a repository is one project of a workspace kept elsewhere.
     *
     * @return list<string>
     */
    public static function detachedSignals(string $repository): array
    {
        $signals = [];

        if (is_file($repository.'/project.json') && ! is_file($repository.'/nx.json')) {
            $signals[] = 'project.json without nx.json';
        }

        $project = RepoFiles::json($repository.'/project.json');

        if (is_string($project['$schema'] ?? null) && self::leavesRepository((string) $project['$schema'])) {
            $signals[] = 'project.json $schema points outside the repository';
        }

        foreach (['tsconfig.json', 'tsconfig.app.json'] as $file) {
            $tsconfig = RepoFiles::json($repository.'/'.$file);
            $extends = $tsconfig['extends'] ?? null;
            $baseUrl = $tsconfig['compilerOptions']['baseUrl'] ?? null;

            if (is_string($extends) && self::leavesRepository($extends)) {
                $signals[] = $file.' extends '.$extends;

                break;
            }

            if (is_string($baseUrl) && self::leavesRepository($baseUrl)) {
                $signals[] = $file.' baseUrl '.$baseUrl;

                break;
            }
        }

        return $signals;
    }

    /**
     * Where the project expects to sit inside its workspace, read from its
     * own `project.json` (`sourceRoot: apps/web/src` with `src/` at the root
     * of the repository → `apps/web`).
     */
    public static function expectedRoot(string $repository): ?string
    {
        $project = RepoFiles::json($repository.'/project.json');
        $sourceRoot = is_string($project['sourceRoot'] ?? null) ? trim($project['sourceRoot'], '/') : null;

        if ($sourceRoot === null || is_dir($repository.'/'.$sourceRoot)) {
            return null;
        }

        $segments = explode('/', $sourceRoot);

        for ($i = 1; $i < count($segments); $i++) {
            $tail = implode('/', array_slice($segments, $i));

            if (is_dir($repository.'/'.$tail)) {
                return implode('/', array_slice($segments, 0, $i));
            }
        }

        return null;
    }

    /**
     * A path from the project's own config that the project sees under its
     * real folder: `apps/web/src` read from `apps/web/project.json` in a
     * repository that holds only `src/` → `src`.
     */
    public static function localPath(string $repository, ?string $path, ?string $expectedRoot): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = trim($path, '/');

        if ($expectedRoot !== null && RepoFiles::isUnder($path, $expectedRoot) && $path !== $expectedRoot) {
            return substr($path, strlen($expectedRoot) + 1);
        }

        return $path;
    }

    protected static function leavesRepository(string $path): bool
    {
        return str_starts_with(str_replace('\\', '/', trim($path)), '../');
    }

    /**
     * @return list<string>
     */
    protected static function ancestors(string $directory): array
    {
        $home = rtrim((string) (getenv('HOME') ?: ''), '/');
        $ancestors = [];
        $current = $directory;

        for ($level = 0; $level < self::ANCESTOR_LEVELS; $level++) {
            $parent = dirname($current);

            if ($parent === $current || $parent === '/' || ($home !== '' && $parent === $home)) {
                break;
            }

            $ancestors[] = $parent;
            $current = $parent;
        }

        return $ancestors;
    }
}
