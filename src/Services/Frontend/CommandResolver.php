<?php

declare(strict_types=1);

namespace Larapilot\Services\Frontend;

/**
 * The commands that verify a project, written the way its workspace runs
 * them: `nx run portal:test` in Nx, `ng test portal` in an Angular CLI
 * workspace, `turbo run test --filter=web` with Turborepo, `pnpm --filter`
 * in pnpm workspaces, `npm run test` in a single app. Every command runs
 * from the frontend root and never waits for input.
 */
final class CommandResolver
{
    /**
     * The names a workspace gives each verification step, in preference order.
     *
     * @var array<string, list<string>>
     */
    public const STEPS = [
        'lint' => ['lint', 'eslint', 'lint:check'],
        'typecheck' => ['typecheck', 'type-check', 'check-types', 'tsc', 'check'],
        'test' => ['test', 'test:unit', 'unit', 'test:ci', 'vitest', 'jest'],
        'build' => ['build'],
        'e2e' => ['e2e', 'test:e2e', 'playwright', 'cypress:run'],
        'serve' => ['serve', 'dev', 'start'],
    ];

    /**
     * Nx plugins and Angular collections that come with generators.
     *
     * @var list<string>
     */
    public const GENERATOR_COLLECTIONS = [
        '@nx/angular', '@nx/react', '@nx/vue', '@nx/next', '@nx/nuxt', '@nx/remix', '@nx/js', '@nx/web',
        '@nx/storybook', '@nx/expo', '@nx/react-native', '@nx/workspace', '@nrwl/angular', '@nrwl/react',
        '@schematics/angular', '@angular/material', '@ngrx/schematics', '@analogjs/platform',
    ];

    /**
     * @param  array<string, mixed>  $workspace  What WorkspaceInspector::inspect() answered.
     * @param  array{branch: string, ref: string, source: string}  $base
     */
    public function __construct(
        protected array $workspace,
        protected array $base,
        protected RepoIndex $index,
    ) {}

    /**
     * @param  array<string, mixed>  $project
     * @return array<string, string>
     */
    public function forProject(array $project): array
    {
        $commands = [];

        foreach (self::STEPS as $step => $names) {
            $target = $this->targetFor($project, $names);

            if ($target === null) {
                continue;
            }

            $definition = $project['targets'][$target] ?? [];
            $commands[$step] = $this->run($project, $target, is_array($definition) ? $definition : []);
        }

        return $commands;
    }

    /**
     * Workspace-wide commands: install, what a change affects, format.
     *
     * @param  list<array<string, mixed>>  $targets
     * @return array<string, string>
     */
    public function forWorkspace(array $targets): array
    {
        $manager = $this->workspace['package_manager'];
        $exec = (string) $manager['exec'];
        $ref = $this->base['ref'];
        $kind = (string) $this->workspace['kind'];
        $commands = ($this->workspace['has_package_json'] ?? true) === true ? ['install' => (string) $manager['install']] : [];
        $steps = $this->presentSteps(['lint', 'test', 'build', 'typecheck']);

        if ($steps !== []) {
            $affected = match ($kind) {
                'nx' => $this->nxAffected($exec, $steps, $ref),
                'turborepo' => sprintf('%s turbo run %s --filter=...[%s]', $exec, implode(' ', $steps), $ref),
                'pnpm-workspaces' => implode(' && ', array_map(static fn (string $step): string => sprintf('pnpm --filter "...[%s]" run %s', $ref, $step), $steps)),
                'lerna' => implode(' && ', array_map(static fn (string $step): string => sprintf('%s lerna run %s --since=%s', $exec, $step, $ref), $steps)),
                default => null,
            };

            if ($affected !== null) {
                $commands['affected'] = $affected;
            }
        }

        if ($kind === 'nx') {
            $dependencies = WorkspaceInspector::dependencies(RepoFiles::json($this->index->root.'/package.json'));

            if (isset($dependencies['prettier'])) {
                $commands['format_check'] = sprintf('%s nx format:check --base=%s', $exec, $ref);
                $commands['format'] = sprintf('%s nx format:write --base=%s', $exec, $ref);
            }

            foreach ($targets as $project) {
                if (($this->workspace['graph']['targets_complete'] ?? true) === false || ($this->workspace['graph']['source'] ?? '') !== 'nx-cli') {
                    $commands['show_project'] = sprintf('%s nx show project %s --json', $exec, $project['name']);

                    break;
                }
            }
        } else {
            $scripts = is_array(RepoFiles::json($this->index->root.'/package.json')['scripts'] ?? null)
                ? RepoFiles::json($this->index->root.'/package.json')['scripts']
                : [];

            foreach (['format:check', 'prettier:check', 'format'] as $script) {
                if (isset($scripts[$script])) {
                    $commands['format_check'] = sprintf('%s %s', $manager['run'], $script);

                    break;
                }
            }
        }

        return $commands;
    }

    /**
     * The generators the workspace has: the plugins installed and the ones
     * the team wrote, which come first because they encode its conventions.
     *
     * @param  list<array<string, mixed>>  $targets
     * @return array<string, mixed>|null
     */
    public function generators(array $targets): ?array
    {
        $kind = (string) $this->workspace['kind'];

        if (! in_array($kind, ['nx', 'angular-cli'], true)) {
            return null;
        }

        $dependencies = WorkspaceInspector::dependencies(RepoFiles::json($this->index->root.'/package.json'));
        $collections = array_values(array_filter(
            self::GENERATOR_COLLECTIONS,
            static fn (string $collection): bool => isset($dependencies[$collection]) || ($collection === '@schematics/angular' && isset($dependencies['@angular/cli']))
        ));

        $local = [];

        foreach ($this->index->named('generators.json') as $file) {
            $directory = RepoFiles::dirname($file);
            $json = RepoFiles::json($this->index->absolute($file));
            $package = RepoFiles::json($this->index->absolute($directory).'/package.json')
                ?? RepoFiles::json($this->index->absolute(RepoFiles::dirname($directory)).'/package.json');
            $plugin = is_string($package['name'] ?? null) ? $package['name'] : $directory;

            foreach (array_merge(
                is_array($json['generators'] ?? null) ? $json['generators'] : [],
                is_array($json['schematics'] ?? null) ? $json['schematics'] : []
            ) as $name => $definition) {
                if (is_string($name) && ! (is_array($definition) && ($definition['hidden'] ?? false) === true)) {
                    $local[] = array_filter([
                        'name' => $plugin.':'.$name,
                        'description' => is_array($definition) && is_string($definition['description'] ?? null) ? $definition['description'] : null,
                    ]);
                }
            }
        }

        $exec = (string) $this->workspace['package_manager']['exec'];
        $project = $targets[0]['name'] ?? '<project>';

        return [
            'local' => array_slice($local, 0, 30),
            'collections' => $collections,
            'defaults' => $kind === 'nx'
                ? (is_array($this->workspace['nx']['generators'] ?? null) ? $this->workspace['nx']['generators'] : [])
                : (is_array(RepoFiles::json($this->index->root.'/angular.json')['schematics'] ?? null) ? RepoFiles::json($this->index->root.'/angular.json')['schematics'] : []),
            'run' => $kind === 'nx'
                ? sprintf('%s nx g <collection>:<generator> <path/name> --dry-run', $exec)
                : sprintf('%s ng generate <schematic> <name> --project=%s --dry-run', $exec, $project),
            'help' => $kind === 'nx'
                ? sprintf('%s nx g <collection>:<generator> --help', $exec)
                : sprintf('%s ng generate <schematic> --help', $exec),
        ];
    }

    /**
     * @param  array<string, mixed>  $project
     * @param  list<string>  $names
     */
    protected function targetFor(array $project, array $names): ?string
    {
        $targets = is_array($project['targets'] ?? null) ? $project['targets'] : [];
        $unavailable = is_array($project['unavailable'] ?? null) ? $project['unavailable'] : [];

        foreach ($names as $name) {
            if (isset($targets[$name]) && ! isset($unavailable[$name])) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $project
     * @param  array<string, mixed>  $definition
     */
    protected function run(array $project, string $target, array $definition): string
    {
        $manager = $this->workspace['package_manager'];
        $exec = (string) $manager['exec'];
        $package = is_string($project['package'] ?? null) ? $project['package'] : (string) $project['name'];
        $executor = (string) ($definition['executor'] ?? '');
        $command = (string) ($definition['command'] ?? '');
        $isTest = in_array($target, self::STEPS['test'], true) || in_array($target, self::STEPS['e2e'], true);
        $karma = str_contains($executor, 'karma') || preg_match('/\bng\s+test\b/', $command) === 1;
        $prefix = $isTest ? 'CI=true ' : '';
        $suffix = $karma || str_contains($executor, ':unit-test') ? ' --watch=false' : '';

        if ($karma) {
            $browser = $this->headlessBrowser($project, $definition);
            $suffix .= $browser !== null ? ' --browsers='.$browser : '';
        }

        if (in_array($target, self::STEPS['serve'], true)) {
            $prefix = '';
        }

        return match ((string) $this->workspace['kind']) {
            'nx', 'nx-project' => sprintf('%s%s nx run %s:%s%s', $prefix, $exec, $project['name'], $target, $suffix),
            'angular-cli' => in_array($target, ['test', 'lint', 'build', 'e2e', 'serve'], true)
                ? sprintf('%s%s ng %s %s%s', $prefix, $exec, $target, $project['name'], $suffix)
                : sprintf('%s%s ng run %s:%s', $prefix, $exec, $project['name'], $target),
            'turborepo' => sprintf('%s%s turbo run %s --filter=%s', $prefix, $exec, $target, $package),
            'lerna' => sprintf('%s%s lerna run %s --scope=%s', $prefix, $exec, $target, $package),
            'pnpm-workspaces' => sprintf('%spnpm --filter %s run %s%s', $prefix, $package, $target, $suffix !== '' ? ' --'.$suffix : ''),
            'yarn-workspaces' => sprintf('%syarn workspace %s run %s%s', $prefix, $package, $target, $suffix),
            'bun-workspaces' => sprintf('%sbun run --filter %s %s', $prefix, $package, $target),
            'npm-workspaces' => sprintf('%snpm run %s --workspace=%s%s', $prefix, $target, $package, $suffix !== '' ? ' --'.$suffix : ''),
            'rush' => sprintf('cd %s && %srushx %s', $project['root'], $prefix, $target),
            default => sprintf('%s%s %s%s', $prefix, $manager['run'], $target, $suffix !== '' ? ($manager['name'] === 'yarn' ? $suffix : ' --'.$suffix) : ''),
        };
    }

    /**
     * Karma opens a real browser and waits; an agent has no screen. The
     * headless launcher the karma config defines wins, then the one of the
     * launcher package the project installed.
     *
     * @param  array<string, mixed>  $project
     * @param  array<string, mixed>  $definition
     */
    protected function headlessBrowser(array $project, array $definition): ?string
    {
        $root = $this->index->root;
        $directory = $this->index->absolute((string) $project['root']);
        $candidates = [];

        if (is_string($definition['config'] ?? null)) {
            $config = (string) preg_replace('#^\./#', '', $definition['config']);
            $candidates[] = $root.'/'.$config;
            $candidates[] = $directory.'/'.$config;
        }

        foreach (['karma.conf.js', 'karma.conf.cjs', 'karma.conf.ts'] as $file) {
            $candidates[] = $directory.'/'.$file;
            $candidates[] = $root.'/'.$file;
        }

        foreach (array_unique($candidates) as $file) {
            $content = RepoFiles::read($file, 32000);

            if ($content === null) {
                continue;
            }

            if (preg_match('/customLaunchers\s*:\s*\{.*?[\'"]?([A-Za-z0-9_]*Headless[A-Za-z0-9_]*)[\'"]?\s*:\s*\{/s', $content, $matches) === 1) {
                return $matches[1];
            }

            break;
        }

        $dependencies = WorkspaceInspector::dependencies(RepoFiles::json($directory.'/package.json'))
            + WorkspaceInspector::dependencies(RepoFiles::json($root.'/package.json'));

        return match (true) {
            isset($dependencies['karma-chrome-launcher']) => 'ChromeHeadless',
            isset($dependencies['karma-firefox-launcher']) => 'FirefoxHeadless',
            default => null,
        };
    }

    /**
     * @param  list<string>  $steps
     */
    protected function nxAffected(string $exec, array $steps, string $ref): string
    {
        $major = WorkspaceInspector::major($this->workspace['tool']['version'] ?? null);

        if ($major !== null && $major < 16) {
            return implode(' && ', array_map(
                static fn (string $step): string => sprintf('CI=true %s nx affected --target=%s --base=%s', $exec, $step, $ref),
                $steps
            ));
        }

        return sprintf('CI=true %s nx affected -t %s --base=%s', $exec, implode(' ', $steps), $ref);
    }

    /**
     * The verification steps some project of the workspace has.
     *
     * @param  list<string>  $steps
     * @return list<string>
     */
    protected function presentSteps(array $steps): array
    {
        $present = [];

        foreach ($steps as $step) {
            foreach ($this->workspace['projects'] as $project) {
                if (isset($project['targets'][$step])) {
                    $present[] = $step;

                    break;
                }
            }
        }

        return $present;
    }
}
