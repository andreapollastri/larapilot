<?php

declare(strict_types=1);

namespace Larapilot\Services\Frontend;

/**
 * How the code of one project is actually written, measured on its files:
 * the libraries it builds on, the conventions it follows (counted, never
 * assumed), and recent files to use as models. Rules written by the team
 * say what they want; this says what the code already does.
 */
final class ProjectProfiler
{
    /**
     * Libraries worth knowing before writing a line, by what they decide.
     *
     * @var array<string, list<string>>
     */
    public const LIBRARIES = [
        'ui' => [
            '@angular/material', '@angular/cdk', 'primeng', '@taiga-ui/core', 'ng-zorro-antd', '@ionic/angular', '@ng-bootstrap/ng-bootstrap', '@spartan-ng/brain',
            '@mui/material', '@chakra-ui/react', '@mantine/core', 'antd', '@radix-ui/react-slot', '@headlessui/react', 'react-bootstrap', '@ionic/react', '@shadcn/ui', 'react-aria-components',
            'vuetify', 'quasar', 'primevue', 'element-plus', 'naive-ui', '@nuxt/ui', 'ant-design-vue', '@headlessui/vue', 'radix-vue', 'reka-ui',
            '@skeletonlabs/skeleton', 'flowbite-svelte', 'bits-ui', 'daisyui',
        ],
        'state' => [
            '@ngrx/store', '@ngrx/signals', '@ngrx/component-store', '@ngxs/store', '@ngneat/elf', '@datorama/akita',
            '@reduxjs/toolkit', 'redux', 'zustand', 'jotai', 'mobx', 'recoil', 'valtio', 'xstate',
            'pinia', 'vuex',
        ],
        'data' => [
            '@tanstack/react-query', '@tanstack/vue-query', '@tanstack/angular-query-experimental', '@tanstack/svelte-query', 'swr', '@apollo/client', 'apollo-angular', 'urql', '@trpc/client',
        ],
        'http' => ['axios', 'ky', 'ofetch', 'graphql-request'],
        'forms' => ['@ngx-formly/core', 'react-hook-form', 'formik', '@tanstack/react-form', 'vee-validate', '@vuelidate/core', 'zod', 'yup', 'valibot', 'superforms', 'sveltekit-superforms'],
        'routing' => ['react-router', 'react-router-dom', '@tanstack/react-router', 'vue-router', 'wouter'],
        'i18n' => ['@ngx-translate/core', '@jsverse/transloco', '@ngneat/transloco', '@angular/localize', 'i18next', 'react-i18next', 'react-intl', 'next-intl', 'vue-i18n', '@nuxtjs/i18n', 'svelte-i18n', '@inlang/paraglide-js'],
        'styling' => ['tailwindcss', 'styled-components', '@emotion/react', '@vanilla-extract/css', 'sass', 'less', 'unocss', 'bootstrap', '@pandacss/dev', 'clsx', 'class-variance-authority'],
        'testing' => ['jest', 'vitest', 'karma', 'jasmine-core', '@testing-library/angular', '@testing-library/react', '@testing-library/vue', '@testing-library/svelte', '@vue/test-utils', '@playwright/test', 'cypress', '@web/test-runner', 'msw', 'ng-mocks', '@ngneat/spectator', 'jest-preset-angular', '@analogjs/vitest-angular'],
        'docs' => ['@storybook/angular', '@storybook/react', '@storybook/vue3', '@storybook/svelte', 'storybook', '@compodoc/compodoc'],
    ];

    protected const CONTENT_SAMPLE = 300;

    /**
     * @var array<string, string>
     */
    protected array $contents = [];

    public function __construct(
        protected string $root,
        protected WorkspaceInspector $inspector,
    ) {}

    /**
     * @param  array<string, mixed>  $project
     * @param  array<string, string>  $dependencies  The project's own, then the root's.
     * @return array{libraries: array<string, list<string>>, observed: list<string>, exemplars: list<array{kind: string, path: string, related?: list<string>}>, tests: array{spec_files: int, generators_skip: bool}}
     */
    public function profile(array $project, array $dependencies): array
    {
        $files = $this->inspector->sourceFiles($project, 1500);
        $stack = is_string($project['stack'] ?? null) ? $project['stack'] : null;
        $family = WorkspaceInspector::PLAYBOOKS[$stack ?? ''] ?? 'generic';

        return [
            'libraries' => $this->libraries($dependencies, $project),
            'observed' => array_values(array_filter(array_merge(
                $this->common($files),
                match ($family) {
                    'angular' => $this->angular($files, $project),
                    'react' => $this->react($files),
                    'vue' => $this->vue($files),
                    'svelte' => $this->svelte($files),
                    default => [],
                }
            ))),
            'exemplars' => $this->exemplars($family, $files, (string) $project['root'], is_string($project['git_root'] ?? null) ? $project['git_root'] : null),
            'tests' => [
                'spec_files' => count(array_filter($files, static fn (string $file): bool => preg_match('/\.(spec|test)\.[cm]?[jt]sx?$/', $file) === 1)),
                'generators_skip' => in_array('skipTests=true', array_map(
                    static fn (string $default): string => (string) substr($default, (int) strrpos($default, ' ') + 1),
                    array_filter($this->generatorDefaults($project), static fn (string $default): bool => str_contains($default, ':component '))
                ), true),
            ],
        ];
    }

    /**
     * @param  array<string, string>  $dependencies
     * @param  array<string, mixed>  $project
     * @return array<string, list<string>>
     */
    protected function libraries(array $dependencies, array $project): array
    {
        $found = [];

        foreach (self::LIBRARIES as $category => $packages) {
            foreach ($packages as $package) {
                if (isset($dependencies[$package])) {
                    $found[$category][] = $package;
                }
            }
        }

        foreach (array_keys($dependencies) as $package) {
            if (str_starts_with($package, '@radix-ui/') && ! in_array('@radix-ui/react-slot', $found['ui'] ?? [], true)) {
                $found['ui'][] = '@radix-ui/*';

                break;
            }
        }

        $directory = $project['root'] === '.' ? $this->root : $this->root.'/'.$project['root'];

        if (is_file($directory.'/components.json') || is_file($this->root.'/components.json')) {
            $found['ui'][] = 'shadcn (components.json)';
        }

        foreach ($found as $category => $packages) {
            $found[$category] = array_values(array_unique($packages));
        }

        return $found;
    }

    /**
     * @param  list<string>  $files
     * @return list<string>
     */
    protected function common(array $files): array
    {
        $code = array_values(array_filter($files, static fn (string $file): bool => preg_match('/\.(ts|tsx|js|jsx|mjs|cjs|mts|cts|vue|svelte|astro)$/', $file) === 1));

        if ($code === []) {
            return ['No source files found under the project.'];
        }

        $typescript = count(array_filter($code, static fn (string $file): bool => preg_match('/\.(ts|tsx|mts|cts)$/', $file) === 1));
        $specs = count(array_filter($code, static fn (string $file): bool => preg_match('/\.spec\.[cm]?[jt]sx?$/', $file) === 1));
        $tests = count(array_filter($code, static fn (string $file): bool => preg_match('/\.test\.[cm]?[jt]sx?$/', $file) === 1));
        $testFolders = count(array_filter($code, static fn (string $file): bool => str_contains($file, '/__tests__/')));
        $styles = [];

        foreach ($files as $file) {
            if (preg_match('/\.(scss|sass|less|css|styl)$/', $file, $matches) === 1) {
                $extension = str_contains($file, '.module.') ? 'CSS modules ('.$matches[1].')' : $matches[1];
                $styles[$extension] = ($styles[$extension] ?? 0) + 1;
            }
        }

        arsort($styles);
        $observed = [];
        $observed[] = sprintf('Language: %s (%d of %d source files in TypeScript).', $typescript * 2 >= count($code) ? 'TypeScript' : 'JavaScript', $typescript, count($code));

        if ($specs + $tests > 0) {
            $observed[] = sprintf(
                'Tests are named *.%s.* (%d spec, %d test)%s.',
                $specs >= $tests ? 'spec' : 'test',
                $specs,
                $tests,
                $testFolders > 0 ? sprintf(', %d inside __tests__ folders', $testFolders) : ', next to the file they test'
            );
        } else {
            $observed[] = 'No unit tests under the project yet.';
        }

        if ($styles !== []) {
            $observed[] = 'Style files: '.implode(', ', array_map(
                static fn (string $kind, int $count): string => $kind.' '.$count,
                array_keys($styles),
                $styles
            )).'.';
        }

        $naming = ['kebab-case' => 0, 'PascalCase' => 0, 'camelCase' => 0];

        foreach ($code as $file) {
            $stem = (string) preg_replace('/\..*$/', '', basename($file));

            if ($stem === '' || in_array($stem, ['index', 'main', 'app', 'App', 'router', 'routes'], true) || str_starts_with($stem, '+')) {
                continue;
            }

            if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)+$/', $stem) === 1) {
                $naming['kebab-case']++;
            } elseif (preg_match('/^[A-Z][A-Za-z0-9]+$/', $stem) === 1) {
                $naming['PascalCase']++;
            } elseif (preg_match('/^[a-z]+[A-Z][A-Za-z0-9]*$/', $stem) === 1) {
                $naming['camelCase']++;
            }
        }

        arsort($naming);
        $top = (string) array_key_first($naming);

        if ($naming[$top] > 0) {
            $observed[] = sprintf('File names: %s (%s).', $top, implode(', ', array_map(
                static fn (string $style, int $count): string => $style.' '.$count,
                array_keys($naming),
                $naming
            )));
        }

        return $observed;
    }

    /**
     * @param  list<string>  $files
     * @param  array<string, mixed>  $project
     * @return list<string>
     */
    protected function angular(array $files, array $project): array
    {
        $typescript = array_values(array_filter($files, static fn (string $file): bool => str_ends_with($file, '.ts') && ! str_ends_with($file, '.spec.ts')));
        $templates = array_values(array_filter($files, static fn (string $file): bool => str_ends_with($file, '.html')));
        $major = WorkspaceInspector::major($this->inspector->version('@angular/core', (string) $project['root']));

        $components = 0;
        $suffixed = 0;
        $standaloneFalse = 0;
        $standaloneTrue = 0;
        $ngModules = 0;
        $onPush = 0;
        $inlineTemplates = 0;
        $signalApis = 0;
        $decoratorApis = 0;
        $injectFn = 0;
        $constructorInjection = 0;
        $zoneless = false;
        $controlFlow = 0;
        $structural = 0;
        $prefixes = [];

        foreach (array_slice($typescript, 0, self::CONTENT_SAMPLE) as $file) {
            $content = $this->content($file);

            if ($content === '') {
                continue;
            }

            if (str_contains($content, '@NgModule(')) {
                $ngModules++;
            }

            if (preg_match('/provide(Experimental)?ZonelessChangeDetection\s*\(/', $content) === 1) {
                $zoneless = true;
            }

            $injectFn += preg_match_all('/\binject\s*[(<]/', $content);
            $constructorInjection += preg_match_all('/constructor\s*\(\s*(?:@\w+\([^)]*\)\s*)?(?:private|protected|public|readonly)\s/', $content);
            $signalApis += preg_match_all('/\b(?:input|output|model|viewChild|viewChildren|contentChild|contentChildren)(?:\.required)?\s*[(<]/', $content);
            $decoratorApis += preg_match_all('/@(?:Input|Output|ViewChild|ViewChildren|ContentChild|ContentChildren)\s*\(/', $content);

            if (! str_contains($content, '@Component(')) {
                continue;
            }

            $components++;

            if (str_ends_with($file, '.component.ts')) {
                $suffixed++;
            }

            $standaloneFalse += preg_match('/standalone\s*:\s*false/', $content);
            $standaloneTrue += preg_match('/standalone\s*:\s*true/', $content);
            $onPush += preg_match('/ChangeDetectionStrategy\.OnPush/', $content);

            if (preg_match('/\btemplate\s*:\s*[`\'"]/', $content) === 1) {
                $inlineTemplates++;
                $controlFlow += preg_match_all('/@(?:if|for|switch|defer)\s*[({]/', $content);
                $structural += preg_match_all('/\*ng(?:If|For|Switch)\b/', $content);
            }

            if (preg_match('/selector\s*:\s*[\'"]\[?([a-z][a-z0-9]*)-/', $content, $matches) === 1) {
                $prefixes[$matches[1]] = ($prefixes[$matches[1]] ?? 0) + 1;
            }
        }

        foreach (array_slice($templates, 0, self::CONTENT_SAMPLE) as $file) {
            $content = $this->content($file);
            $controlFlow += preg_match_all('/@(?:if|for|switch|defer)\s*[({]/', $content);
            $structural += preg_match_all('/\*ng(?:If|For|Switch)\b/', $content);
        }

        $observed = [];

        if ($components > 0) {
            $declaredInModules = $major !== null && $major >= 19
                ? $standaloneFalse
                : $components - $standaloneTrue;
            $standalone = $components - $declaredInModules;

            $observed[] = sprintf(
                'Components: %d standalone, %d declared in NgModules (%d NgModule files)%s.',
                max(0, $standalone),
                max(0, $declaredInModules),
                $ngModules,
                $major === null ? '' : ($major >= 19
                    ? sprintf('; on Angular %d components are standalone by default', $major)
                    : sprintf('; components become standalone by default only from Angular 19, this project is on %d', $major))
            );

            $observed[] = sprintf('Change detection: OnPush on %d of %d components%s.', $onPush, $components, $zoneless ? '; the app is zoneless' : '');
            $observed[] = sprintf('Component files: %d use the .component.ts suffix, %d do not.', $suffixed, $components - $suffixed);
            $observed[] = sprintf('Templates: %d inline, %d in separate files.', $inlineTemplates, $components - $inlineTemplates);
        }

        if ($controlFlow + $structural > 0) {
            $observed[] = sprintf('Template control flow: %d built-in blocks (@if/@for/@switch/@defer), %d structural directives (*ngIf/*ngFor).', $controlFlow, $structural);
        }

        if ($signalApis + $decoratorApis > 0) {
            $observed[] = sprintf('Inputs and queries: %d signal functions (input()/output()/viewChild()), %d decorators (@Input/@Output/@ViewChild).', $signalApis, $decoratorApis);
        }

        if ($injectFn + $constructorInjection > 0) {
            $observed[] = sprintf('Dependency injection: %d inject() calls, %d constructor parameters.', $injectFn, $constructorInjection);
        }

        $configured = is_string($project['prefix'] ?? null) ? $project['prefix'] : null;
        arsort($prefixes);
        $used = array_key_first($prefixes);

        if ($configured !== null || $used !== null) {
            $observed[] = sprintf('Selector prefix: %s.', $configured !== null ? $configured.' (project config)' : $used.' (from the selectors)');
        }

        $defaults = $this->generatorDefaults($project);

        if ($defaults !== []) {
            $observed[] = 'Generator defaults: '.implode(', ', $defaults).'.';
        }

        return $observed;
    }

    /**
     * @param  array<string, mixed>  $project
     * @return list<string>
     */
    protected function generatorDefaults(array $project): array
    {
        $generators = is_array($project['generators'] ?? null) ? $project['generators'] : [];
        $nxJson = RepoFiles::json($this->root.'/nx.json');
        $angularJson = RepoFiles::json($this->root.'/angular.json');

        $merged = array_replace_recursive(
            is_array($nxJson['generators'] ?? null) ? $nxJson['generators'] : [],
            is_array($angularJson['schematics'] ?? null) ? $angularJson['schematics'] : [],
            $generators
        );

        // Both `"@nx/angular:component": {…}` and `"@nx/angular": {"component": {…}}` are valid.
        $flat = [];

        foreach ($merged as $generator => $options) {
            if (! is_string($generator) || ! is_array($options)) {
                continue;
            }

            if (str_contains($generator, ':')) {
                $flat[$generator] = $options;

                continue;
            }

            foreach ($options as $sub => $values) {
                if (is_string($sub) && is_array($values)) {
                    $flat[$generator.':'.$sub] = $values;
                }
            }
        }

        $out = [];

        foreach ($flat as $generator => $options) {
            if (preg_match('/:(component|application|library)$/', $generator) !== 1) {
                continue;
            }

            foreach (['style', 'changeDetection', 'standalone', 'inlineStyle', 'inlineTemplate', 'skipTests', 'type', 'unitTestRunner', 'prefix'] as $key) {
                if (array_key_exists($key, $options) && is_scalar($options[$key])) {
                    $value = is_bool($options[$key]) ? ($options[$key] ? 'true' : 'false') : (string) $options[$key];
                    $out[] = $generator.' '.$key.'='.$value;
                }
            }
        }

        return array_values(array_unique(array_slice($out, 0, 12)));
    }

    /**
     * @param  list<string>  $files
     * @return list<string>
     */
    protected function react(array $files): array
    {
        $components = array_values(array_filter($files, static fn (string $file): bool => preg_match('/\.(tsx|jsx)$/', $file) === 1 && preg_match('/\.(test|spec|stories)\./', $file) !== 1));
        $classes = 0;
        $defaultExports = 0;
        $namedExports = 0;
        $useClient = 0;
        $serverActions = 0;

        foreach (array_slice($components, 0, self::CONTENT_SAMPLE) as $file) {
            $content = $this->content($file);
            $classes += preg_match('/extends\s+(?:React\.)?(?:Pure)?Component\b/', $content);
            $defaultExports += preg_match('/export\s+default\s/', $content);
            $namedExports += preg_match('/export\s+(?:function|const)\s+[A-Z]/', $content);
            $useClient += preg_match('/^\s*[\'"]use client[\'"]/m', $content);
            $serverActions += preg_match('/^\s*[\'"]use server[\'"]/m', $content);
        }

        $hooks = count(array_filter($files, static fn (string $file): bool => preg_match('/(^|\/)use[A-Z][A-Za-z0-9]*\.(ts|tsx|js|jsx)$/', $file) === 1));
        $appRouter = count(array_filter($files, static fn (string $file): bool => preg_match('#(^|/)app/(.+/)?(page|layout)\.(tsx|jsx|ts|js)$#', $file) === 1));
        $pagesRouter = count(array_filter($files, static fn (string $file): bool => preg_match('#(^|/)pages/.+\.(tsx|jsx)$#', $file) === 1));

        $observed = [];

        if ($components !== []) {
            $observed[] = sprintf('Components: %d files, %d class components; %d default exports, %d named exports.', count($components), $classes, $defaultExports, $namedExports);
        }

        if ($hooks > 0) {
            $observed[] = sprintf('Custom hooks: %d use*.ts(x) files.', $hooks);
        }

        if ($appRouter > 0 || $pagesRouter > 0) {
            $observed[] = sprintf('Routing files: %d App Router (page/layout), %d Pages Router; %d "use client" components, %d "use server" modules.', $appRouter, $pagesRouter, $useClient, $serverActions);
        }

        return $observed;
    }

    /**
     * @param  list<string>  $files
     * @return list<string>
     */
    protected function vue(array $files): array
    {
        $components = array_values(array_filter($files, static fn (string $file): bool => str_ends_with($file, '.vue')));
        $setup = 0;
        $options = 0;
        $typed = 0;
        $scoped = 0;

        foreach (array_slice($components, 0, self::CONTENT_SAMPLE) as $file) {
            $content = $this->content($file);
            $setup += preg_match('/<script[^>]*\bsetup\b/', $content);
            $options += preg_match('/export\s+default\s+(?:defineComponent\s*\(\s*)?\{/', $content);
            $typed += preg_match('/<script[^>]*lang=["\']ts["\']/', $content);
            $scoped += preg_match('/<style[^>]*\bscoped\b/', $content);
        }

        $setupStores = 0;
        $optionStores = 0;

        foreach (array_slice(array_values(array_filter($files, static fn (string $file): bool => preg_match('/\.(ts|js)$/', $file) === 1)), 0, self::CONTENT_SAMPLE) as $file) {
            $content = $this->content($file);
            $setupStores += preg_match_all('/defineStore\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*\(\s*\)\s*=>/', $content);
            $optionStores += preg_match_all('/defineStore\s*\(\s*(?:[\'"][^\'"]+[\'"]\s*,\s*)?\{/', $content);
        }

        $composables = count(array_filter($files, static fn (string $file): bool => preg_match('/(^|\/)use[A-Z][A-Za-z0-9]*\.(ts|js)$/', $file) === 1));
        $observed = [];

        if ($components !== []) {
            $observed[] = sprintf('Single-file components: %d, %d with <script setup>, %d with the Options API or defineComponent; %d in TypeScript; %d with scoped styles.', count($components), $setup, $options, $typed, $scoped);
        }

        if ($setupStores + $optionStores > 0) {
            $observed[] = sprintf('Pinia stores: %d setup stores, %d option stores.', $setupStores, $optionStores);
        }

        if ($composables > 0) {
            $observed[] = sprintf('Composables: %d use*.ts files.', $composables);
        }

        return $observed;
    }

    /**
     * @param  list<string>  $files
     * @return list<string>
     */
    protected function svelte(array $files): array
    {
        $components = array_values(array_filter($files, static fn (string $file): bool => str_ends_with($file, '.svelte')));
        $runes = 0;
        $legacy = 0;

        foreach (array_slice($components, 0, self::CONTENT_SAMPLE) as $file) {
            $content = $this->content($file);
            $runes += preg_match('/\$(state|derived|props|effect|bindable)\s*[(<.]/', $content);
            $legacy += preg_match('/export\s+let\s+\w/', $content);
        }

        $routes = count(array_filter($files, static fn (string $file): bool => preg_match('/(^|\/)\+(page|layout)(\.server)?\.(svelte|ts|js)$/', $file) === 1));
        $observed = [];

        if ($components !== []) {
            $observed[] = sprintf('Components: %d, %d with runes ($state/$props), %d with export let.', count($components), $runes, $legacy);
        }

        if ($routes > 0) {
            $observed[] = sprintf('SvelteKit route files: %d.', $routes);
        }

        return $observed;
    }

    /**
     * Recent files of each kind to model new code on: the newest commit
     * that touched the project first, the folder walk when git knows nothing.
     *
     * @param  list<string>  $files
     * @return list<array{kind: string, path: string, related?: list<string>}>
     */
    protected function exemplars(string $family, array $files, string $root, ?string $gitRoot = null): array
    {
        $known = array_flip($files);
        $candidates = [];

        // A project that is a repository of its own is ignored by the
        // workspace's git: its history is read where it lives, and the
        // paths are put back under the project root.
        $recent = $gitRoot !== null
            ? array_map(static fn (string $file): string => ($root === '.' ? '' : $root.'/').$file, RepoGit::recentFiles($gitRoot, '.'))
            : RepoGit::recentFiles($this->root, $root);

        foreach ($recent as $file) {
            if (isset($known[$file])) {
                $candidates[$file] = true;
            }
        }

        foreach ($files as $file) {
            $candidates[$file] ??= true;
        }

        $kinds = $this->exemplarKinds($family);
        $picked = [];
        $read = 0;

        foreach (array_keys($candidates) as $file) {
            if ($kinds === [] || $read > 400) {
                break;
            }

            foreach ($kinds as $kind => $test) {
                if (! $test['path']($file)) {
                    continue;
                }

                if ($test['content'] !== null) {
                    $read++;

                    if (! $test['content']($this->content($file))) {
                        continue;
                    }
                }

                $entry = ['kind' => $kind, 'path' => $file];
                $related = $this->related($file, $known);

                if ($related !== []) {
                    $entry['related'] = $related;
                }

                $picked[] = $entry;
                unset($kinds[$kind]);

                break;
            }
        }

        return $picked;
    }

    /**
     * @return array<string, array{path: callable(string): bool, content: (callable(string): bool)|null}>
     */
    protected function exemplarKinds(string $family): array
    {
        $test = static fn (string $file): bool => preg_match('/\.(spec|test)\.[cm]?[jt]sx?$/', $file) === 1;
        $code = static fn (string $file): bool => preg_match('/\.[cm]?[jt]sx?$/', $file) === 1 && preg_match('/\.(spec|test|stories|d)\.[cm]?[jt]sx?$/', $file) !== 1;

        return match ($family) {
            'angular' => [
                'component' => ['path' => static fn (string $file): bool => $code($file) && str_ends_with($file, '.ts'), 'content' => static fn (string $content): bool => str_contains($content, '@Component(')],
                'service' => ['path' => static fn (string $file): bool => $code($file) && str_ends_with($file, '.ts'), 'content' => static fn (string $content): bool => str_contains($content, '@Injectable(') && ! str_contains($content, '@Component(')],
                'state' => ['path' => static fn (string $file): bool => $code($file) && str_ends_with($file, '.ts'), 'content' => static fn (string $content): bool => preg_match('/signalStore\(|createReducer\(|createFeature\(|createEffect\(|@State\(|ComponentStore/', $content) === 1],
                'routes' => ['path' => static fn (string $file): bool => preg_match('/routes\.ts$/', $file) === 1, 'content' => null],
                'test' => ['path' => static fn (string $file): bool => str_ends_with($file, '.spec.ts'), 'content' => null],
            ],
            'react' => [
                'component' => ['path' => static fn (string $file): bool => $code($file) && preg_match('/\.(tsx|jsx)$/', $file) === 1 && preg_match('/(^|\/)(page|layout|route)\.(tsx|jsx)$/', $file) !== 1, 'content' => static fn (string $content): bool => preg_match('/export\s+(default\s+)?(function|const)\s+[A-Z]/', $content) === 1],
                'route' => ['path' => static fn (string $file): bool => preg_match('#(^|/)(app/(.+/)?(page|layout)|pages/.+|routes/.+)\.(tsx|jsx)$#', $file) === 1, 'content' => null],
                'hook' => ['path' => static fn (string $file): bool => $code($file) && preg_match('/(^|\/)use[A-Z][A-Za-z0-9]*\.(ts|tsx|js|jsx)$/', $file) === 1, 'content' => null],
                'data' => ['path' => $code, 'content' => static fn (string $content): bool => preg_match('/useQuery\(|createApi\(|createSlice\(|from [\'"]zustand[\'"]|useSWR\(/', $content) === 1],
                'test' => ['path' => $test, 'content' => null],
            ],
            'vue' => [
                'component' => ['path' => static fn (string $file): bool => str_ends_with($file, '.vue') && preg_match('#(^|/)(pages|views)/#', $file) !== 1, 'content' => null],
                'page' => ['path' => static fn (string $file): bool => str_ends_with($file, '.vue') && preg_match('#(^|/)(pages|views)/#', $file) === 1, 'content' => null],
                'composable' => ['path' => static fn (string $file): bool => preg_match('/(^|\/)use[A-Z][A-Za-z0-9]*\.(ts|js)$/', $file) === 1, 'content' => null],
                'store' => ['path' => $code, 'content' => static fn (string $content): bool => str_contains($content, 'defineStore(')],
                'test' => ['path' => $test, 'content' => null],
            ],
            'svelte' => [
                'component' => ['path' => static fn (string $file): bool => str_ends_with($file, '.svelte') && ! str_starts_with(basename($file), '+'), 'content' => null],
                'route' => ['path' => static fn (string $file): bool => preg_match('/(^|\/)\+page(\.server)?\.(svelte|ts|js)$/', $file) === 1, 'content' => null],
                'state' => ['path' => static fn (string $file): bool => preg_match('/\.svelte\.(ts|js)$/', $file) === 1 || preg_match('/(^|\/)stores?\//', $file) === 1, 'content' => null],
                'test' => ['path' => $test, 'content' => null],
            ],
            default => [
                'module' => ['path' => $code, 'content' => null],
                'test' => ['path' => $test, 'content' => null],
            ],
        };
    }

    /**
     * The template, styles, and spec that sit next to a file.
     *
     * @param  array<string, int>  $known
     * @return list<string>
     */
    protected function related(string $file, array $known): array
    {
        $stem = (string) preg_replace('/\.(ts|tsx|js|jsx|vue|svelte)$/', '', $file);
        $related = [];

        foreach (['.html', '.scss', '.css', '.less', '.module.css', '.module.scss', '.spec.ts', '.test.ts', '.spec.tsx', '.test.tsx', '.stories.tsx', '.stories.ts'] as $suffix) {
            if (isset($known[$stem.$suffix]) && $stem.$suffix !== $file) {
                $related[] = $stem.$suffix;
            }
        }

        return $related;
    }

    protected function content(string $file): string
    {
        return $this->contents[$file] ??= (string) RepoFiles::read($this->root.'/'.$file, 32000);
    }
}
