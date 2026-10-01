<?php

declare(strict_types=1);

namespace Larapilot\Services\Frontend;

/**
 * What kind of frontend workspace a repository is, and the projects in it:
 * an Nx workspace, an Angular CLI workspace, pnpm / yarn / npm / bun
 * workspaces (with or without Turborepo or Lerna), Rush, or one app.
 *
 * Every project carries its root, its type, its tags, its targets, the stack
 * it is written in with the version installed, and the projects it depends
 * on. With Nx installed the graph comes from `nx graph`, which also knows
 * the targets plugins infer; otherwise it is read from the files.
 */
final class WorkspaceInspector
{
    /**
     * The package whose version is the version of the framework. Order
     * matters: a meta-framework is tested before the library under it.
     *
     * @var array<string, string>
     */
    public const FRAMEWORK_PACKAGES = [
        'Angular' => '@angular/core',
        'Next.js' => 'next',
        'Nuxt' => 'nuxt',
        'Remix' => '@remix-run/react',
        'React Router' => 'react-router',
        'React Native' => 'react-native',
        'SvelteKit' => '@sveltejs/kit',
        'Astro' => 'astro',
        'Qwik' => '@builder.io/qwik',
        'Solid' => 'solid-js',
        'Preact' => 'preact',
        'Vue' => 'vue',
        'Svelte' => 'svelte',
        'React' => 'react',
        'Lit' => 'lit',
    ];

    /**
     * The playbook (`runtime-frontend-{name}.md`) each stack reads.
     *
     * @var array<string, string>
     */
    public const PLAYBOOKS = [
        'Angular' => 'angular',
        'React' => 'react',
        'Next.js' => 'react',
        'Remix' => 'react',
        'React Router' => 'react',
        'React Native' => 'react',
        'Preact' => 'react',
        'Vue' => 'vue',
        'Nuxt' => 'vue',
        'Svelte' => 'svelte',
        'SvelteKit' => 'svelte',
    ];

    /**
     * Nx plugins that add targets for a config file in the project, with
     * the option that renames each target and its default name.
     *
     * @var array<string, array{files: list<string>, targets: array<string, string>}>
     */
    public const NX_PLUGINS = [
        '@nx/vite/plugin' => ['files' => ['vite.config', 'vitest.config'], 'targets' => ['buildTargetName' => 'build', 'serveTargetName' => 'serve', 'previewTargetName' => 'preview', 'testTargetName' => 'test', 'typecheckTargetName' => 'typecheck']],
        '@nx/vitest' => ['files' => ['vitest.config', 'vite.config'], 'targets' => ['testTargetName' => 'test']],
        '@nx/jest/plugin' => ['files' => ['jest.config'], 'targets' => ['targetName' => 'test']],
        '@nx/eslint/plugin' => ['files' => ['eslint.config', '.eslintrc'], 'targets' => ['targetName' => 'lint']],
        '@nx/playwright/plugin' => ['files' => ['playwright.config'], 'targets' => ['targetName' => 'e2e']],
        '@nx/cypress/plugin' => ['files' => ['cypress.config'], 'targets' => ['targetName' => 'e2e', 'componentTestingTargetName' => 'component-test']],
        '@nx/next/plugin' => ['files' => ['next.config'], 'targets' => ['buildTargetName' => 'build', 'devTargetName' => 'dev', 'startTargetName' => 'start']],
        '@nx/nuxt/plugin' => ['files' => ['nuxt.config'], 'targets' => ['buildTargetName' => 'build', 'serveTargetName' => 'serve']],
        '@nx/remix/plugin' => ['files' => ['remix.config'], 'targets' => ['buildTargetName' => 'build', 'devTargetName' => 'dev', 'startTargetName' => 'start', 'typecheckTargetName' => 'typecheck']],
        '@nx/react/router-plugin' => ['files' => ['react-router.config'], 'targets' => ['buildTargetName' => 'build', 'devTargetName' => 'dev', 'startTargetName' => 'start', 'typecheckTargetName' => 'typecheck']],
        '@nx/webpack/plugin' => ['files' => ['webpack.config'], 'targets' => ['buildTargetName' => 'build', 'serveTargetName' => 'serve', 'previewTargetName' => 'preview']],
        '@nx/rspack/plugin' => ['files' => ['rspack.config'], 'targets' => ['buildTargetName' => 'build', 'serveTargetName' => 'serve', 'previewTargetName' => 'preview']],
        '@nx/rollup/plugin' => ['files' => ['rollup.config'], 'targets' => ['buildTargetName' => 'build']],
        '@nx/storybook/plugin' => ['files' => ['.storybook/main'], 'targets' => ['serveStorybookTargetName' => 'storybook', 'buildStorybookTargetName' => 'build-storybook']],
        '@nx/js/typescript' => ['files' => ['tsconfig'], 'targets' => ['typecheck' => 'typecheck']],
    ];

    /**
     * Source files read to find which project imports which.
     */
    protected const IMPORT_SCAN_FILES = 20000;

    protected const IMPORT_SCAN_PER_PROJECT = 800;

    /**
     * Source files one walk of a project folder keeps, the most any caller
     * asks for: the profile of a target project.
     */
    protected const SOURCE_WALK_FILES = 1500;

    /**
     * The source walks already done, by folder.
     *
     * @var array<string, array{files: list<string>, truncated: bool, limit: int}>
     */
    protected array $sourceWalks = [];

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $rootPackage;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $pnpmWorkspace;

    public function __construct(
        protected string $root,
        protected RepoIndex $index,
        protected ?NxGraph $nx = null,
    ) {
        $this->rootPackage = RepoFiles::json($root.'/package.json');
        $this->pnpmWorkspace = RepoFiles::yaml($root.'/pnpm-workspace.yaml');
    }

    /**
     * `$dependencies` false skips the import scan that draws the graph from
     * the files (a name lookup needs no graph); `$stacks` false skips the
     * framework and target checks of every project.
     *
     * @return array<string, mixed>
     */
    public function inspect(bool $useCli = true, bool $fresh = false, bool $dependencies = true, bool $stacks = true): array
    {
        $kind = $this->kind();
        $nxJson = $kind === 'nx' ? (RepoFiles::json($this->root.'/nx.json') ?? []) : [];
        $graph = ['source' => 'files', 'cached' => false, 'error' => null, 'targets_complete' => true];
        $projects = null;
        $withDependencies = $dependencies;
        $dependencies = [];

        if ($kind === 'nx' && $useCli && $this->nx !== null) {
            $read = $this->nx->read($this->root, $this->fingerprint(), $fresh);

            if ($read['ok'] === true) {
                $projects = $read['projects'] ?? [];
                $dependencies = $read['dependencies'] ?? [];
                $graph = ['source' => 'nx-cli', 'cached' => (bool) ($read['cached'] ?? false), 'error' => null, 'targets_complete' => true];
            } else {
                $graph['error'] = $read['error'] ?? 'nx graph failed.';
            }
        } elseif ($kind === 'nx' && ! $useCli) {
            $graph['error'] = 'Skipped on request (--no-cli).';
        }

        if ($projects === null) {
            $projects = match ($kind) {
                'nx' => $this->nxProjects($nxJson),
                'nx-project' => $this->detachedProject(),
                'angular-cli' => $this->angularProjects(),
                'single' => $this->singleProject(),
                default => $this->workspacePackages(),
            };

            if ($kind === 'nx') {
                [$projects, $complete] = $this->inferNxTargets($projects, $nxJson);
                $graph['targets_complete'] = $complete;
            }

            $dependencies = $withDependencies ? $this->staticDependencies($projects) : [];
            $graph['source'] = in_array($kind, ['single', 'nx-project'], true) ? 'none' : 'files';
        }

        if ($kind === 'turborepo') {
            $projects = $this->turboTasks($projects);
        }

        ksort($projects);

        $rootDependencies = self::dependencies($this->rootPackage);

        foreach ($projects as $name => $project) {
            $projects[$name]['stack'] = $stacks ? $this->stack($project, $rootDependencies, false) : null;
            $projects[$name]['unavailable'] = $stacks ? $this->unavailableTargets($project, $rootDependencies) : [];
        }

        return [
            'kind' => $kind,
            'monorepo' => ! in_array($kind, ['single', 'nx-project'], true),
            'tool' => $this->tool($kind),
            'package_manager' => $this->packageManager(),
            'node' => $this->node(),
            'workspace_globs' => $this->workspaceGlobs(),
            'nx' => $kind === 'nx' ? [
                'plugins' => $this->nxPluginNames($nxJson),
                'default_base' => $this->nxDefaultBase($nxJson),
                'generators' => is_array($nxJson['generators'] ?? null) ? $nxJson['generators'] : [],
                'layout' => [
                    'apps' => (string) ($nxJson['workspaceLayout']['appsDir'] ?? 'apps'),
                    'libs' => (string) ($nxJson['workspaceLayout']['libsDir'] ?? 'libs'),
                ],
            ] : null,
            'graph' => $graph,
            'projects' => $projects,
            'dependencies' => $dependencies,
            'truncated' => $this->index->truncated,
            'has_package_json' => $this->rootPackage !== null,
        ];
    }

    public function kind(): string
    {
        if (is_file($this->root.'/nx.json')) {
            return 'nx';
        }

        if (is_file($this->root.'/angular.json')) {
            return 'angular-cli';
        }

        if (is_file($this->root.'/rush.json')) {
            return 'rush';
        }

        // One Nx project whose workspace is not here — with or without a
        // package.json of its own (per-app dependencies, or just a name).
        if (is_file($this->root.'/project.json') && ($this->rootPackage === null || $this->workspaceGlobs() === [])) {
            return 'nx-project';
        }

        if ($this->workspaceGlobs() === []) {
            return 'single';
        }

        if (is_file($this->root.'/turbo.json')) {
            return 'turborepo';
        }

        if (is_file($this->root.'/lerna.json')) {
            return 'lerna';
        }

        return match ($this->packageManager()['name']) {
            'pnpm' => 'pnpm-workspaces',
            'yarn' => 'yarn-workspaces',
            'bun' => 'bun-workspaces',
            default => 'npm-workspaces',
        };
    }

    /**
     * @return array{name: string, version: string|null, source: string, lockfile: string|null, exec: string, run: string, install: string, installed: bool}
     */
    public function packageManager(): array
    {
        $field = is_string($this->rootPackage['packageManager'] ?? null) ? $this->rootPackage['packageManager'] : null;
        $name = null;
        $version = null;
        $source = 'default';
        $lockfile = null;

        if ($field !== null && preg_match('/^(npm|pnpm|yarn|bun)@([0-9][^+\s]*)/', $field, $matches) === 1) {
            $name = $matches[1];
            $version = $matches[2];
            $source = 'packageManager';
        }

        foreach (['pnpm-lock.yaml' => 'pnpm', 'yarn.lock' => 'yarn', 'bun.lock' => 'bun', 'bun.lockb' => 'bun', 'package-lock.json' => 'npm', 'npm-shrinkwrap.json' => 'npm'] as $file => $manager) {
            if (is_file($this->root.'/'.$file) && ($name === null || $name === $manager)) {
                $lockfile = $file;
                $name ??= $manager;
                $source = $source === 'default' ? 'lockfile' : $source;

                break;
            }
        }

        $name ??= 'npm';
        $berry = $name === 'yarn' && (is_file($this->root.'/.yarnrc.yml') || ($version !== null && (int) $version >= 2));

        return [
            'name' => $name,
            'version' => $version,
            'source' => $source,
            'lockfile' => $lockfile,
            'exec' => match ($name) {
                'pnpm' => 'pnpm exec',
                'yarn' => 'yarn',
                'bun' => 'bunx',
                default => 'npx',
            },
            'run' => match ($name) {
                'pnpm' => 'pnpm run',
                'yarn' => 'yarn run',
                'bun' => 'bun run',
                default => 'npm run',
            },
            'install' => match ($name) {
                'pnpm' => 'pnpm install --frozen-lockfile',
                'yarn' => $berry ? 'yarn install --immutable' : 'yarn install --frozen-lockfile',
                'bun' => 'bun install --frozen-lockfile',
                default => $lockfile !== null ? 'npm ci' : 'npm install',
            },
            'installed' => is_dir($this->root.'/node_modules'),
        ];
    }

    /**
     * @return array{version: string|null, source: string|null}
     */
    public function node(): array
    {
        foreach (['.nvmrc', '.node-version'] as $file) {
            $content = RepoFiles::read($this->root.'/'.$file, 200);

            if ($content !== null) {
                return ['version' => trim(strtok($content, "\n") ?: ''), 'source' => $file];
            }
        }

        $tools = RepoFiles::read($this->root.'/.tool-versions', 4000);

        if ($tools !== null && preg_match('/^nodejs\s+(\S+)/m', $tools, $matches) === 1) {
            return ['version' => $matches[1], 'source' => '.tool-versions'];
        }

        if (is_string($this->rootPackage['volta']['node'] ?? null)) {
            return ['version' => $this->rootPackage['volta']['node'], 'source' => 'package.json volta'];
        }

        if (is_string($this->rootPackage['engines']['node'] ?? null)) {
            return ['version' => $this->rootPackage['engines']['node'], 'source' => 'package.json engines'];
        }

        return ['version' => null, 'source' => null];
    }

    /**
     * The globs of the package-manager workspaces, from `pnpm-workspace.yaml`,
     * `package.json` `workspaces`, or `lerna.json`.
     *
     * @return list<string>
     */
    public function workspaceGlobs(): array
    {
        $globs = [];

        if (is_array($this->pnpmWorkspace['packages'] ?? null)) {
            $globs = $this->pnpmWorkspace['packages'];
        } elseif (is_array($this->rootPackage['workspaces'] ?? null)) {
            $workspaces = $this->rootPackage['workspaces'];
            $globs = array_is_list($workspaces) ? $workspaces : ($workspaces['packages'] ?? []);
        } elseif (is_file($this->root.'/lerna.json')) {
            $lerna = RepoFiles::json($this->root.'/lerna.json');
            $globs = is_array($lerna['packages'] ?? null) ? $lerna['packages'] : ['packages/*'];
        }

        return array_values(array_filter(
            is_array($globs) ? $globs : [],
            static fn (mixed $glob): bool => is_string($glob) && trim($glob) !== ''
        ));
    }

    /**
     * What a project depends on: the merged dependency maps of a package.json.
     *
     * @param  array<string, mixed>|null  $package
     * @return array<string, string>
     */
    public static function dependencies(?array $package): array
    {
        if (! is_array($package)) {
            return [];
        }

        $dependencies = [];

        foreach (['dependencies', 'devDependencies', 'peerDependencies', 'optionalDependencies'] as $key) {
            foreach (is_array($package[$key] ?? null) ? $package[$key] : [] as $name => $version) {
                if (is_string($name)) {
                    $dependencies[$name] = is_string($version) ? $version : '';
                }
            }
        }

        return $dependencies;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{executor: string|null, source: string, command?: string, script?: string}
     */
    public static function target(array $definition, string $source): array
    {
        $executor = $definition['executor'] ?? $definition['builder'] ?? null;
        $options = is_array($definition['options'] ?? null) ? $definition['options'] : [];
        $command = $definition['command'] ?? $options['command'] ?? null;

        if ($command === null && is_array($options['commands'] ?? null)) {
            $first = $options['commands'][0] ?? null;
            $command = is_string($first) ? $first : (is_array($first) ? ($first['command'] ?? null) : null);
        }

        $target = [
            'executor' => is_string($executor) ? $executor : (is_string($command) ? 'nx:run-commands' : null),
            'source' => $source,
        ];

        if (is_string($command)) {
            $target['command'] = mb_substr($command, 0, 240);
        }

        if (is_string($options['script'] ?? null)) {
            $target['script'] = $options['script'];
        }

        if (is_string($options['karmaConfig'] ?? null)) {
            $target['config'] = $options['karmaConfig'];
        }

        return $target;
    }

    /**
     * Builders a version of the Angular CLI no longer ships. A target that
     * still names one fails before it starts.
     *
     * @var array<string, array{package: string, major: int, reason: string}>
     */
    public const REMOVED_BUILDERS = [
        '@angular-devkit/build-angular:tslint' => ['package' => '@angular-devkit/build-angular', 'major' => 13, 'reason' => 'TSLint support left the Angular CLI in v13'],
        '@angular-devkit/build-angular:protractor' => ['package' => '@angular-devkit/build-angular', 'major' => 19, 'reason' => 'Protractor support left the Angular CLI in v19'],
    ];

    /**
     * Runners a target needs installed, by a piece of its executor.
     *
     * @var array<string, string>
     */
    public const RUNNERS = [
        ':karma' => 'karma',
        ':protractor' => 'protractor',
        ':tslint' => 'tslint',
        '@nx/jest:' => 'jest',
        '@nx/cypress:' => 'cypress',
        '@nx/playwright:' => '@playwright/test',
    ];

    /**
     * Targets that cannot run, with the reason: a builder the installed
     * version no longer has, or a runner the project does not depend on.
     *
     * @param  array<string, mixed>  $project
     * @param  array<string, string>  $rootDependencies
     * @return array<string, string>
     */
    public function unavailableTargets(array $project, array $rootDependencies): array
    {
        $own = $project['root'] !== '.' ? self::dependencies(RepoFiles::json($this->index->absolute((string) $project['root']).'/package.json')) : [];
        $dependencies = $own + $rootDependencies;
        $known = $dependencies !== [];
        $unavailable = [];

        foreach ($project['targets'] ?? [] as $name => $target) {
            $executor = is_array($target) ? (string) ($target['executor'] ?? '') : '';

            if ($executor === '' || ! str_contains($executor, ':')) {
                continue;
            }

            [$package, $builder] = explode(':', $executor, 2);
            $removed = self::REMOVED_BUILDERS[$executor] ?? null;
            $installedAt = null;

            foreach (array_unique([$this->index->absolute((string) $project['root']), $this->root]) as $directory) {
                if (is_file($directory.'/node_modules/'.$package.'/package.json')) {
                    $installedAt = $directory.'/node_modules/'.$package;

                    break;
                }
            }

            $installed = $installedAt !== null ? RepoFiles::json($installedAt.'/package.json') : null;

            if (is_array($installed)) {
                // The package's own catalog says which builders it ships.
                $catalog = $installed['builders'] ?? $installed['executors'] ?? null;
                $manifest = is_string($catalog) ? RepoFiles::json($installedAt.'/'.(string) preg_replace('#^\./#', '', $catalog)) : null;
                $entries = $manifest['builders'] ?? $manifest['executors'] ?? null;

                if (is_array($entries) && ! array_key_exists($builder, $entries)) {
                    $unavailable[(string) $name] = sprintf('%s %s has no %s builder%s', $package, (string) ($installed['version'] ?? ''), $builder, $removed !== null ? ' — '.$removed['reason'] : '');

                    continue;
                }
            } elseif ($removed !== null) {
                $major = self::major($this->version($removed['package'], (string) $project['root']));

                if ($major !== null && $major >= $removed['major']) {
                    $unavailable[(string) $name] = sprintf('%s; this project is on %s %d', $removed['reason'], $removed['package'], $major);

                    continue;
                }
            }

            if (! $known) {
                continue;
            }

            foreach (self::RUNNERS as $needle => $runner) {
                if (str_contains($executor, $needle) && ! isset($dependencies[$runner])) {
                    $unavailable[(string) $name] = sprintf('the %s target needs %s, which the project does not depend on', $name, $runner);

                    break;
                }
            }
        }

        return $unavailable;
    }

    /**
     * The framework of one project. `deep` also counts the files under its
     * source root when nothing cheaper tells.
     *
     * @param  array<string, mixed>  $project
     * @param  array<string, string>  $rootDependencies
     */
    public function stack(array $project, array $rootDependencies, bool $deep): ?string
    {
        $executors = '';

        foreach ($project['targets'] ?? [] as $target) {
            $executors .= ' '.($target['executor'] ?? '').' '.($target['command'] ?? '');
        }

        foreach ([
            'Angular' => ['@angular-devkit/build-angular', '@angular/build', '@nx/angular', '@nrwl/angular', '@angular-builders/', '@analogjs/', 'ng build', 'ng serve'],
            'Next.js' => ['@nx/next', '@nrwl/next', 'next build', 'next dev'],
            'Nuxt' => ['@nx/nuxt', 'nuxt build', 'nuxt dev', 'nuxi '],
            'Remix' => ['@nx/remix', 'remix build', 'remix vite:'],
            'React Native' => ['@nx/expo', '@nx/react-native', 'expo start'],
            'SvelteKit' => ['svelte-kit '],
            'Astro' => ['astro build', 'astro dev'],
            'React' => ['@nx/react', '@nrwl/react'],
            'Vue' => ['@nx/vue'],
        ] as $stack => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($executors, $needle)) {
                    return $stack;
                }
            }
        }

        $directory = $this->index->absolute((string) $project['root']);
        $own = $project['root'] !== '.' ? self::dependencies(RepoFiles::json($directory.'/package.json')) : [];
        $all = $own + $rootDependencies;

        foreach ([
            'next.config' => 'Next.js',
            'nuxt.config' => 'Nuxt',
            'react-router.config' => 'React Router',
            'remix.config' => 'Remix',
            'astro.config' => 'Astro',
            'quasar.config' => 'Vue',
            'svelte.config' => isset($all['@sveltejs/kit']) ? 'SvelteKit' : 'Svelte',
        ] as $stem => $stack) {
            if (RepoFiles::firstExisting($directory, RepoFiles::configNames($stem)) !== null) {
                return $stack;
            }
        }

        if ($project['root'] === '.' && is_file($directory.'/angular.json')) {
            return 'Angular';
        }

        $vite = RepoFiles::firstExisting($directory, RepoFiles::configNames('vite.config'));

        if ($vite !== null) {
            $content = (string) RepoFiles::read($directory.'/'.$vite, 16000);

            foreach ([
                '@analogjs/' => 'Angular',
                'sveltekit(' => 'SvelteKit',
                '@sveltejs/vite-plugin-svelte' => 'Svelte',
                '@vitejs/plugin-vue' => 'Vue',
                '@preact/preset-vite' => 'Preact',
                'vite-plugin-solid' => 'Solid',
                '@builder.io/qwik' => 'Qwik',
                '@vitejs/plugin-react' => 'React',
                '@react-router/dev' => 'React Router',
            ] as $needle => $stack) {
                if (str_contains($content, $needle)) {
                    return $stack;
                }
            }
        }

        if ($own !== []) {
            $fromOwn = self::frameworkFrom($own);

            if ($fromOwn !== null) {
                return $fromOwn;
            }
        }

        $families = self::frameworkFamilies($rootDependencies);

        if (count($families) === 1) {
            return $this->forProject(self::frameworkFrom($rootDependencies), $project);
        }

        if ($deep || $families !== []) {
            $census = $this->census($project);

            if ($census !== null) {
                return $census;
            }
        }

        return $families === [] ? null : $this->forProject(self::frameworkFrom($rootDependencies), $project);
    }

    /**
     * A library shares the dependencies of the workspace root, so the root
     * names the meta-framework of the apps; the library is written in the
     * framework under it.
     *
     * @param  array<string, mixed>  $project
     */
    protected function forProject(?string $stack, array $project): ?string
    {
        if (($project['type'] ?? null) !== 'library') {
            return $stack;
        }

        return match ($stack) {
            'Next.js', 'Remix', 'React Router' => 'React',
            'Nuxt' => 'Vue',
            'SvelteKit' => 'Svelte',
            default => $stack,
        };
    }

    /**
     * @param  array<string, string>  $dependencies
     */
    public static function frameworkFrom(array $dependencies): ?string
    {
        foreach (self::FRAMEWORK_PACKAGES as $stack => $package) {
            if (isset($dependencies[$package])) {
                return $stack;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $dependencies
     * @return list<string>
     */
    public static function frameworkFamilies(array $dependencies): array
    {
        $families = [];

        foreach (['@angular/core' => 'angular', 'react' => 'react', 'vue' => 'vue', 'svelte' => 'svelte', 'solid-js' => 'solid', 'astro' => 'astro', '@builder.io/qwik' => 'qwik', 'lit' => 'lit'] as $package => $family) {
            if (isset($dependencies[$package])) {
                $families[$family] = true;
            }
        }

        return array_keys($families);
    }

    /**
     * The installed version of a package for a project, or the version its
     * package.json asks for when nothing is installed.
     */
    public function version(string $package, string $projectRoot = '.'): ?string
    {
        $directories = array_values(array_unique([$this->index->absolute($projectRoot), $this->root]));

        foreach ($directories as $directory) {
            $installed = RepoFiles::json($directory.'/node_modules/'.$package.'/package.json');

            if (is_string($installed['version'] ?? null)) {
                return $installed['version'];
            }
        }

        foreach ($directories as $directory) {
            $declared = self::dependencies(RepoFiles::json($directory.'/package.json'))[$package] ?? null;

            if (! is_string($declared)) {
                continue;
            }

            $declared = $this->resolveCatalog($package, $declared);

            if (preg_match('/(\d+(?:\.\d+){0,2})/', $declared, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    public static function major(?string $version): ?int
    {
        return is_string($version) && preg_match('/^(\d+)/', $version, $matches) === 1 ? (int) $matches[1] : null;
    }

    /**
     * Source files of a project, under its source root, for the census and
     * for the profile of a target project.
     *
     * @param  array<string, mixed>  $project
     * @return list<string>
     */
    public function sourceFiles(array $project, int $limit = 1500): array
    {
        $base = is_string($project['source_root'] ?? null) && $project['source_root'] !== ''
            ? (string) $project['source_root']
            : (string) $project['root'];

        $absolute = $this->index->absolute($base);

        if (! is_dir($absolute)) {
            $base = (string) $project['root'];
            $absolute = $this->index->absolute($base);
        }

        // One walk per folder, in the order the walk found the files, serves
        // every limit asked for it: the first N of that order, sorted, is
        // exactly what a walk capped at N would have returned.
        $cached = $this->sourceWalks[$base] ?? null;

        if ($cached === null || ($cached['limit'] < $limit && $cached['truncated'])) {
            $walk = RepoFiles::walk(
                $absolute,
                static fn (string $path): bool => preg_match('/\.(ts|tsx|js|jsx|mjs|cjs|mts|cts|vue|svelte|astro|html|scss|sass|less|css|styl)$/', $path) === 1,
                8,
                40000,
                max($limit, self::SOURCE_WALK_FILES),
                false
            );
            $cached = $this->sourceWalks[$base] = ['files' => $walk['files'], 'truncated' => $walk['truncated'], 'limit' => max($limit, self::SOURCE_WALK_FILES)];
        }

        $files = array_slice($cached['files'], 0, $limit);
        sort($files);

        return array_map(
            static fn (string $file): string => $base === '.' ? $file : $base.'/'.$file,
            $files
        );
    }

    /**
     * The alias → project map of `compilerOptions.paths`.
     *
     * @param  array<string, array<string, mixed>>  $projects
     * @return array<string, string>
     */
    public function aliases(array $projects): array
    {
        $tsconfig = null;

        foreach (['tsconfig.base.json', 'tsconfig.json'] as $file) {
            $candidate = RepoFiles::json($this->root.'/'.$file);

            if (is_array($candidate['compilerOptions']['paths'] ?? null)) {
                $tsconfig = $candidate;

                break;
            }
        }

        if ($tsconfig === null) {
            return [];
        }

        $roots = [];

        foreach ($projects as $name => $project) {
            if ($project['root'] !== '.') {
                $roots[(string) $project['root']] = $name;
            }
        }

        uksort($roots, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $aliases = [];

        foreach ($tsconfig['compilerOptions']['paths'] as $alias => $targets) {
            $first = is_array($targets) ? ($targets[0] ?? null) : null;

            if (! is_string($alias) || ! is_string($first)) {
                continue;
            }

            $path = (string) preg_replace('#^\./#', '', str_replace('\\', '/', $first));

            foreach ($roots as $root => $name) {
                if (RepoFiles::isUnder($path, $root)) {
                    $aliases[$alias] = $name;

                    break;
                }
            }
        }

        return $aliases;
    }

    /**
     * Projects from `project.json` files and from the packages of the
     * workspaces that have none.
     *
     * @param  array<string, mixed>  $nxJson
     * @return array<string, array<string, mixed>>
     */
    protected function nxProjects(array $nxJson): array
    {
        $appsDir = trim((string) ($nxJson['workspaceLayout']['appsDir'] ?? 'apps'), '/');
        $libsDir = trim((string) ($nxJson['workspaceLayout']['libsDir'] ?? 'libs'), '/');
        $projects = [];
        $withProjectJson = [];

        foreach ($this->index->named('project.json') as $file) {
            $directory = RepoFiles::dirname($file);
            $json = RepoFiles::json($this->root.'/'.$file);

            if (! is_array($json) || $this->insideFixture($directory)) {
                continue;
            }

            $package = $directory === '.' ? $this->rootPackage : RepoFiles::json($this->index->absolute($directory).'/package.json');
            $name = is_string($json['name'] ?? null) && $json['name'] !== ''
                ? $json['name']
                : (is_string($package['name'] ?? null) ? $package['name'] : str_replace('/', '-', $directory));

            $targets = [];

            foreach (is_array($json['targets'] ?? null) ? $json['targets'] : (is_array($json['architect'] ?? null) ? $json['architect'] : []) as $target => $definition) {
                if (is_string($target) && is_array($definition)) {
                    $targets[$target] = self::target($definition, 'project.json');
                }
            }

            $targets += $this->scriptTargets($package);
            $withProjectJson[$directory] = true;

            $projects[$name] = [
                'name' => $name,
                'root' => $directory,
                'source_root' => is_string($json['sourceRoot'] ?? null) ? trim($json['sourceRoot'], '/') : (is_dir($this->index->absolute($directory).'/src') ? ($directory === '.' ? 'src' : $directory.'/src') : $directory),
                'type' => $this->nxType($json['projectType'] ?? null, $directory, $name, $targets, $appsDir, $libsDir),
                'tags' => array_values(array_unique(array_merge(
                    array_filter(is_array($json['tags'] ?? null) ? $json['tags'] : [], 'is_string'),
                    array_filter(is_array($package['nx']['tags'] ?? null) ? $package['nx']['tags'] : [], 'is_string')
                ))),
                'targets' => $targets,
                'implicit' => array_values(array_filter(is_array($json['implicitDependencies'] ?? null) ? $json['implicitDependencies'] : [], 'is_string')),
                'package' => is_string($package['name'] ?? null) && $directory !== '.' ? $package['name'] : null,
                'prefix' => is_string($json['prefix'] ?? null) ? $json['prefix'] : null,
                'generators' => is_array($json['generators'] ?? null) ? $json['generators'] : [],
            ];
        }

        foreach ($this->workspacePackages() as $name => $project) {
            if (isset($withProjectJson[$project['root']]) || isset($projects[$name])) {
                continue;
            }

            $project['type'] = $this->nxType(null, (string) $project['root'], $name, $project['targets'], $appsDir, $libsDir, $project['type']);
            $projects[$name] = $project;
        }

        return $projects;
    }

    /**
     * @param  array<string, mixed>  $targets
     */
    protected function nxType(mixed $declared, string $directory, string $name, array $targets, string $appsDir, string $libsDir, ?string $fallback = null): string
    {
        if (str_ends_with($name, '-e2e') && (isset($targets['e2e']) || $targets === [])) {
            return 'e2e';
        }

        if ($declared === 'application' || $declared === 'library') {
            return $declared;
        }

        if ($appsDir !== '' && RepoFiles::isUnder($directory, $appsDir)) {
            return 'application';
        }

        if ($libsDir !== '' && RepoFiles::isUnder($directory, $libsDir)) {
            return 'library';
        }

        if ($fallback !== null) {
            return $fallback;
        }

        return isset($targets['serve']) || isset($targets['dev']) || isset($targets['start']) ? 'application' : 'library';
    }

    /**
     * @param  array<string, mixed>  $nxJson
     * @return list<string>
     */
    protected function nxPluginNames(array $nxJson): array
    {
        $names = [];

        foreach (is_array($nxJson['plugins'] ?? null) ? $nxJson['plugins'] : [] as $entry) {
            $name = is_string($entry) ? $entry : (is_array($entry) ? ($entry['plugin'] ?? null) : null);

            if (is_string($name)) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  array<string, mixed>  $nxJson
     */
    protected function nxDefaultBase(array $nxJson): ?string
    {
        $base = $nxJson['defaultBase'] ?? $nxJson['affected']['defaultBase'] ?? null;

        return is_string($base) && $base !== '' ? $base : null;
    }

    /**
     * Targets Nx plugins add from config files, as the files suggest them.
     * A plugin this table does not know makes the list incomplete — the
     * scan says so and names `nx show project`.
     *
     * @param  array<string, array<string, mixed>>  $projects
     * @param  array<string, mixed>  $nxJson
     * @return array{0: array<string, array<string, mixed>>, 1: bool}
     */
    protected function inferNxTargets(array $projects, array $nxJson): array
    {
        $complete = true;
        $rootEslint = $this->hasConfig($this->root, ['eslint.config', '.eslintrc']);

        foreach (is_array($nxJson['plugins'] ?? null) ? $nxJson['plugins'] : [] as $entry) {
            $plugin = is_string($entry) ? $entry : (is_array($entry) ? ($entry['plugin'] ?? null) : null);
            $options = is_array($entry) && is_array($entry['options'] ?? null) ? $entry['options'] : [];
            $include = is_array($entry) && is_array($entry['include'] ?? null) ? $entry['include'] : [];
            $exclude = is_array($entry) && is_array($entry['exclude'] ?? null) ? $entry['exclude'] : [];

            if (! is_string($plugin)) {
                continue;
            }

            $definition = self::NX_PLUGINS[$plugin] ?? null;

            if ($definition === null) {
                if (! in_array($plugin, ['@nx/angular/plugin', '@nx/workspace', '@nx/js'], true)) {
                    $complete = false;
                }

                continue;
            }

            foreach ($projects as $name => $project) {
                $directory = $this->index->absolute((string) $project['root']);
                $config = $this->configFile($directory, $definition['files']);

                if ($config === null && $plugin === '@nx/eslint/plugin' && $rootEslint && $project['root'] !== '.') {
                    $config = 'eslint.config.js';
                }

                if ($config === null) {
                    continue;
                }

                $relative = $project['root'] === '.' ? $config : $project['root'].'/'.$config;

                if ($include !== [] && ! $this->anyGlob($include, $relative)) {
                    continue;
                }

                if ($exclude !== [] && $this->anyGlob($exclude, $relative)) {
                    continue;
                }

                foreach ($definition['targets'] as $option => $default) {
                    $value = $options[$option] ?? $default;

                    if ($value === false) {
                        continue;
                    }

                    $target = is_string($value) ? $value : (is_array($value) && is_string($value['targetName'] ?? null) ? $value['targetName'] : $default);

                    if (! $this->pluginAppliesTo($plugin, $default, $project, $directory, $config)) {
                        continue;
                    }

                    if (! isset($projects[$name]['targets'][$target])) {
                        $projects[$name]['targets'][$target] = ['executor' => $plugin, 'source' => 'inferred'];
                    }
                }
            }
        }

        return [$projects, $complete];
    }

    /**
     * @param  array<string, mixed>  $project
     */
    protected function pluginAppliesTo(string $plugin, string $target, array $project, string $directory, string $config): bool
    {
        if (in_array($target, ['serve', 'preview', 'dev', 'start'], true) && ($project['type'] ?? null) === 'library') {
            return false;
        }

        if ($plugin === '@nx/vite/plugin' && $target === 'test') {
            if (str_starts_with($config, 'vitest.config')) {
                return true;
            }

            return preg_match('/\btest\s*:/', (string) RepoFiles::read($directory.'/'.$config, 16000)) === 1;
        }

        if ($plugin === '@nx/vite/plugin' && $target === 'typecheck') {
            return is_file($directory.'/tsconfig.json');
        }

        return true;
    }

    /**
     * @param  list<string>  $stems
     */
    protected function configFile(string $directory, array $stems): ?string
    {
        foreach ($stems as $stem) {
            $names = RepoFiles::configNames($stem);

            if (str_starts_with($stem, '.')) {
                $names = array_merge($names, [$stem, $stem.'.yml', $stem.'.yaml']);
            }

            $found = RepoFiles::firstExisting($directory, $names);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $stems
     */
    protected function hasConfig(string $directory, array $stems): bool
    {
        return $this->configFile($directory, $stems) !== null;
    }

    /**
     * @param  array<array-key, mixed>  $globs
     */
    protected function anyGlob(array $globs, string $path): bool
    {
        foreach ($globs as $glob) {
            if (is_string($glob) && RepoFiles::matches('/'.ltrim($glob, '/'), $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function angularProjects(): array
    {
        $angular = RepoFiles::json($this->root.'/angular.json') ?? [];
        $projects = [];

        foreach (is_array($angular['projects'] ?? null) ? $angular['projects'] : [] as $name => $definition) {
            if (! is_string($name) || ! is_array($definition)) {
                continue;
            }

            $root = trim((string) ($definition['root'] ?? ''), '/');
            $root = $root === '' ? '.' : $root;
            $targets = [];

            foreach (is_array($definition['architect'] ?? null) ? $definition['architect'] : (is_array($definition['targets'] ?? null) ? $definition['targets'] : []) as $target => $config) {
                if (is_string($target) && is_array($config)) {
                    $targets[$target] = self::target($config, 'angular.json');
                }
            }

            $projects[$name] = [
                'name' => $name,
                'root' => $root,
                'source_root' => is_string($definition['sourceRoot'] ?? null) ? trim($definition['sourceRoot'], '/') : ($root === '.' ? 'src' : $root.'/src'),
                'type' => ($definition['projectType'] ?? 'application') === 'library' ? 'library' : 'application',
                'tags' => [],
                'targets' => $targets,
                'implicit' => [],
                'package' => null,
                'prefix' => is_string($definition['prefix'] ?? null) ? $definition['prefix'] : null,
                'generators' => array_replace(
                    is_array($angular['schematics'] ?? null) ? $angular['schematics'] : [],
                    is_array($definition['schematics'] ?? null) ? $definition['schematics'] : []
                ),
            ];
        }

        return $projects;
    }

    /**
     * One project per package of the workspaces (or of `rush.json`).
     *
     * @return array<string, array<string, mixed>>
     */
    protected function workspacePackages(): array
    {
        $directories = [];

        if (is_file($this->root.'/rush.json')) {
            $rush = RepoFiles::json($this->root.'/rush.json') ?? [];

            foreach (is_array($rush['projects'] ?? null) ? $rush['projects'] : [] as $entry) {
                if (is_array($entry) && is_string($entry['projectFolder'] ?? null)) {
                    $directories[] = trim($entry['projectFolder'], '/');
                }
            }
        } else {
            $globs = $this->workspaceGlobs();

            foreach ($this->index->named('package.json') as $file) {
                $directory = RepoFiles::dirname($file);

                if ($directory !== '.' && RepoFiles::inWorkspace($directory, $globs)) {
                    $directories[] = $directory;
                }
            }
        }

        $projects = [];

        foreach ($directories as $directory) {
            $package = RepoFiles::json($this->index->absolute($directory).'/package.json');

            if (! is_array($package)) {
                continue;
            }

            $name = is_string($package['nx']['name'] ?? null) ? $package['nx']['name'] : (is_string($package['name'] ?? null) ? $package['name'] : str_replace('/', '-', $directory));
            $targets = $this->scriptTargets($package);

            $projects[$name] = [
                'name' => $name,
                'root' => $directory,
                'source_root' => is_dir($this->index->absolute($directory).'/src') ? $directory.'/src' : $directory,
                'type' => $this->packageType($directory, $package, $targets),
                'tags' => array_values(array_filter(is_array($package['nx']['tags'] ?? null) ? $package['nx']['tags'] : [], 'is_string')),
                'targets' => $targets,
                'implicit' => [],
                'package' => is_string($package['name'] ?? null) ? $package['name'] : null,
                'prefix' => null,
                'generators' => [],
            ];
        }

        return $projects;
    }

    /**
     * The one project of a repository that is a project of a workspace kept
     * elsewhere. Its `project.json` names paths as the workspace sees them;
     * they are read back under the repository.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function detachedProject(): array
    {
        $projects = $this->nxProjects([]);
        $expected = WorkspaceLocator::expectedRoot($this->root);

        foreach ($projects as $name => $project) {
            if ($project['root'] !== '.') {
                continue;
            }

            $sourceRoot = WorkspaceLocator::localPath($this->root, is_string($project['source_root'] ?? null) ? $project['source_root'] : null, $expected);
            $projects[$name]['source_root'] = $sourceRoot !== null && is_dir($this->root.'/'.$sourceRoot) ? $sourceRoot : (is_dir($this->root.'/src') ? 'src' : '.');
        }

        return $projects;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function singleProject(): array
    {
        $name = is_string($this->rootPackage['name'] ?? null) ? $this->rootPackage['name'] : basename($this->root);
        $targets = $this->scriptTargets($this->rootPackage);

        return [
            $name => [
                'name' => $name,
                'root' => '.',
                'source_root' => is_dir($this->root.'/src') ? 'src' : '.',
                'type' => 'application',
                'tags' => [],
                'targets' => $targets,
                'implicit' => [],
                'package' => is_string($this->rootPackage['name'] ?? null) ? $this->rootPackage['name'] : null,
                'prefix' => null,
                'generators' => [],
            ],
        ];
    }

    /**
     * Turborepo runs the scripts of each package by task name; the tasks of
     * `turbo.json` say which ones the pipeline knows.
     *
     * @param  array<string, array<string, mixed>>  $projects
     * @return array<string, array<string, mixed>>
     */
    protected function turboTasks(array $projects): array
    {
        $turbo = RepoFiles::json($this->root.'/turbo.json') ?? [];
        $tasks = is_array($turbo['tasks'] ?? null) ? $turbo['tasks'] : (is_array($turbo['pipeline'] ?? null) ? $turbo['pipeline'] : []);

        foreach ($projects as $name => $project) {
            foreach (array_keys($tasks) as $task) {
                $task = (string) $task;

                if (str_contains($task, '#')) {
                    [$owner, $task] = explode('#', $task, 2);

                    if ($owner !== $name) {
                        continue;
                    }
                }

                if (isset($project['targets'][$task])) {
                    $projects[$name]['targets'][$task]['executor'] = 'turbo';
                }
            }
        }

        return $projects;
    }

    /**
     * @param  array<string, mixed>|null  $package
     * @return array<string, array{executor: string|null, source: string, command?: string, script?: string}>
     */
    protected function scriptTargets(?array $package): array
    {
        $scripts = is_array($package['scripts'] ?? null) ? $package['scripts'] : [];
        $included = is_array($package['nx']['includedScripts'] ?? null) ? $package['nx']['includedScripts'] : null;
        $targets = [];

        foreach ($scripts as $script => $command) {
            if (! is_string($script) || ! is_string($command)) {
                continue;
            }

            if ($included !== null && ! in_array($script, $included, true)) {
                continue;
            }

            $targets[$script] = ['executor' => 'nx:run-script', 'source' => 'package.json', 'command' => mb_substr($command, 0, 240), 'script' => $script];
        }

        return $targets;
    }

    /**
     * @param  array<string, mixed>  $package
     * @param  array<string, mixed>  $targets
     */
    protected function packageType(string $directory, array $package, array $targets): string
    {
        if (RepoFiles::isUnder($directory, 'apps')) {
            return 'application';
        }

        if (RepoFiles::isUnder($directory, 'packages') || RepoFiles::isUnder($directory, 'libs')) {
            return 'library';
        }

        $publishes = isset($package['main']) || isset($package['module']) || isset($package['exports']);

        return ! $publishes && (isset($targets['dev']) || isset($targets['start']) || isset($targets['serve'])) ? 'application' : 'library';
    }

    /**
     * Which project depends on which, without Nx: workspace package
     * dependencies, `implicitDependencies`, and the imports that go through
     * a `compilerOptions.paths` alias.
     *
     * @param  array<string, array<string, mixed>>  $projects
     * @return array<string, list<string>>
     */
    protected function staticDependencies(array $projects): array
    {
        if (count($projects) < 2) {
            return [];
        }

        $edges = [];
        $byPackage = [];

        foreach ($projects as $name => $project) {
            if (is_string($project['package'] ?? null)) {
                $byPackage[$project['package']] = $name;
            }
        }

        foreach ($projects as $name => $project) {
            $edges[$name] = [];

            if ($project['root'] !== '.') {
                foreach (array_keys(self::dependencies(RepoFiles::json($this->index->absolute((string) $project['root']).'/package.json'))) as $dependency) {
                    if (isset($byPackage[$dependency]) && $byPackage[$dependency] !== $name) {
                        $edges[$name][$byPackage[$dependency]] = true;
                    }
                }
            }

            foreach ($project['implicit'] ?? [] as $implicit) {
                if (is_string($implicit) && isset($projects[$implicit]) && $implicit !== $name) {
                    $edges[$name][$implicit] = true;
                }
            }
        }

        $aliases = $this->aliases($projects);

        if ($aliases !== []) {
            $exact = [];
            $wildcards = [];

            foreach ($aliases as $alias => $project) {
                if (str_ends_with($alias, '/*')) {
                    $wildcards[substr($alias, 0, -1)] = $project;
                } else {
                    $exact[$alias] = $project;
                }
            }

            uksort($wildcards, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
            $budget = self::IMPORT_SCAN_FILES;

            foreach ($projects as $name => $project) {
                if ($budget <= 0) {
                    break;
                }

                $files = array_filter(
                    $this->sourceFiles($project, min(self::IMPORT_SCAN_PER_PROJECT, $budget)),
                    static fn (string $file): bool => preg_match('/\.(ts|tsx|js|jsx|mjs|cjs|mts|cts|vue|svelte|astro)$/', $file) === 1
                );

                foreach ($files as $file) {
                    $budget--;
                    $content = RepoFiles::read($this->root.'/'.$file, 48000);

                    if ($content === null) {
                        continue;
                    }

                    preg_match_all('/(?:\bfrom\s*|\bimport\s*\(\s*|\bimport\s+|\brequire\s*\(\s*)[\'"]([^\'"\n]+)[\'"]/', $content, $matches);

                    foreach ($matches[1] as $specifier) {
                        $target = $exact[$specifier] ?? null;

                        if ($target === null) {
                            foreach ($exact as $alias => $candidate) {
                                if (str_starts_with($specifier, $alias.'/')) {
                                    $target = $candidate;

                                    break;
                                }
                            }
                        }

                        if ($target === null) {
                            foreach ($wildcards as $prefix => $candidate) {
                                if (str_starts_with($specifier, $prefix)) {
                                    $target = $candidate;

                                    break;
                                }
                            }
                        }

                        if ($target !== null && $target !== $name) {
                            $edges[$name][$target] = true;
                        }
                    }
                }
            }
        }

        $dependencies = [];

        foreach ($edges as $name => $targets) {
            $list = array_keys($targets);
            sort($list);
            $dependencies[$name] = $list;
        }

        return $dependencies;
    }

    /**
     * Which framework the files of a project are written in.
     *
     * @param  array<string, mixed>  $project
     */
    protected function census(array $project): ?string
    {
        $counts = ['vue' => 0, 'svelte' => 0, 'astro' => 0, 'jsx' => 0, 'angular' => 0];
        $typescript = [];

        foreach ($this->sourceFiles($project, 400) as $file) {
            if (str_ends_with($file, '.vue')) {
                $counts['vue']++;
            } elseif (str_ends_with($file, '.svelte')) {
                $counts['svelte']++;
            } elseif (str_ends_with($file, '.astro')) {
                $counts['astro']++;
            } elseif (preg_match('/\.(tsx|jsx)$/', $file) === 1) {
                $counts['jsx']++;
            } elseif (preg_match('/\.(component|directive|pipe)\.ts$/', $file) === 1) {
                $counts['angular']++;
            } elseif (str_ends_with($file, '.ts') && count($typescript) < 40) {
                $typescript[] = $file;
            }
        }

        if ($counts['angular'] === 0) {
            foreach ($typescript as $file) {
                if (preg_match('/@(Component|Directive|NgModule|Injectable)\s*\(/', (string) RepoFiles::read($this->root.'/'.$file, 16000)) === 1) {
                    $counts['angular']++;
                }
            }
        }

        arsort($counts);
        $winner = (string) array_key_first($counts);

        if ($counts[$winner] === 0) {
            return null;
        }

        return match ($winner) {
            'vue' => 'Vue',
            'svelte' => 'Svelte',
            'astro' => 'Astro',
            'angular' => 'Angular',
            default => 'React',
        };
    }

    protected function resolveCatalog(string $package, string $declared): string
    {
        if (! str_starts_with($declared, 'catalog:')) {
            return $declared;
        }

        $catalog = trim(substr($declared, strlen('catalog:')));
        $resolved = $catalog === '' || $catalog === 'default'
            ? ($this->pnpmWorkspace['catalog'][$package] ?? $this->pnpmWorkspace['catalogs']['default'][$package] ?? null)
            : ($this->pnpmWorkspace['catalogs'][$catalog][$package] ?? null);

        return is_string($resolved) ? $resolved : $declared;
    }

    /**
     * @return array{name: string, version: string|null}|null
     */
    protected function tool(string $kind): ?array
    {
        $package = match ($kind) {
            'nx' => 'nx',
            'angular-cli' => '@angular/cli',
            'turborepo' => 'turbo',
            'lerna' => 'lerna',
            'rush' => null,
            default => null,
        };

        if ($kind === 'rush') {
            $rush = RepoFiles::json($this->root.'/rush.json') ?? [];

            return ['name' => 'rush', 'version' => is_string($rush['rushVersion'] ?? null) ? $rush['rushVersion'] : null];
        }

        if ($package === null) {
            return null;
        }

        return ['name' => $package === '@angular/cli' ? 'angular-cli' : $package, 'version' => $this->version($package)];
    }

    /**
     * A fixture or a template inside a test folder is not a project.
     */
    protected function insideFixture(string $directory): bool
    {
        return preg_match('#(^|/)(__fixtures__|fixtures|__mocks__|templates?/files)(/|$)#', $directory) === 1;
    }

    /**
     * What makes a cached graph stale: the manifests of the workspace and
     * the commit checked out.
     */
    protected function fingerprint(): string
    {
        $parts = [];
        $files = ['nx.json', 'tsconfig.base.json', 'pnpm-workspace.yaml', 'pnpm-lock.yaml', 'yarn.lock', 'package-lock.json', 'bun.lock'];

        // The manifests, and the tool configs the Nx plugins infer targets
        // from: a vitest.config.ts added in the working tree is a new target.
        foreach ($this->index->files as $file) {
            $base = basename($file);

            if ($base === 'project.json' || $base === 'package.json' || preg_match('/^(vite|vitest|jest|playwright|cypress|next|nuxt|remix|webpack|rspack|rollup|eslint|tsconfig|storybook)[.\w-]*\.(ts|mts|cts|js|mjs|cjs|json)$/', $base) === 1) {
                $files[] = $file;
            }
        }

        foreach ($files as $file) {
            $path = $this->root.'/'.$file;
            $mtime = @filemtime($path);

            if ($mtime !== false) {
                $parts[] = $file.':'.$mtime.':'.(int) @filesize($path);
            }
        }

        $parts[] = RepoGit::head($this->root) ?? 'no-git';

        return sha1(implode('|', $parts));
    }
}
