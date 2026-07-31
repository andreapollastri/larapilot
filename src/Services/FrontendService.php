<?php

declare(strict_types=1);

namespace Larapilot\Services;

class FrontendService
{
    public function __construct(
        protected ConfigService $config,
    ) {}

    /**
     * @return array{repo_path: string|null, stack: string|null, configured: bool}
     */
    public function info(): array
    {
        $frontend = $this->config->frontend();

        return [
            'repo_path' => $frontend['repo_path'],
            'stack' => $frontend['stack'],
            'configured' => $this->configured(),
        ];
    }

    public function configured(): bool
    {
        $path = $this->config->frontendRepoPath();

        return is_string($path) && $path !== '' && is_dir($path);
    }

    /**
     * @return array{valid: bool, path: string, errors: list<string>}
     */
    public function validatePath(string $path): array
    {
        $normalized = rtrim(trim($path), '/\\');
        $errors = [];

        if ($normalized === '') {
            $errors[] = 'Path is empty.';
        } elseif (! is_dir($normalized)) {
            $errors[] = 'Directory does not exist or is not readable.';
        } elseif (! is_readable($normalized)) {
            $errors[] = 'Directory is not readable.';
        }

        return [
            'valid' => $errors === [],
            'path' => $normalized,
            'errors' => $errors,
        ];
    }

    /**
     * Lightweight codebase scan for inception and planning.
     *
     * @return array<string, mixed>
     */
    public function scan(?string $path = null): array
    {
        $repoPath = $path ?? $this->config->frontendRepoPath();

        if (! is_string($repoPath) || $repoPath === '') {
            return [
                'ok' => false,
                'error' => 'No frontend repo path configured. Run larapilot:frontend-set --path=/absolute/path.',
            ];
        }

        $validation = $this->validatePath($repoPath);

        if (! $validation['valid']) {
            return [
                'ok' => false,
                'path' => $validation['path'],
                'errors' => $validation['errors'],
            ];
        }

        $root = $validation['path'];
        $packageJson = $this->readJson($root.'/package.json');
        $dependencies = $this->mergeDependencies($packageJson);
        $detectedStack = $this->detectStack($dependencies, $root);
        $stack = $this->config->frontend()['stack'] ?? $detectedStack;

        return [
            'ok' => true,
            'path' => $root,
            'git' => is_dir($root.'/.git'),
            'package' => is_array($packageJson) ? [
                'name' => $packageJson['name'] ?? null,
                'private' => $packageJson['private'] ?? null,
            ] : null,
            'stack' => [
                'configured' => $this->config->frontend()['stack'],
                'detected' => $detectedStack,
                'resolved' => $stack,
            ],
            'tooling' => [
                'vite' => is_file($root.'/vite.config.ts') || is_file($root.'/vite.config.js'),
                'next' => is_file($root.'/next.config.js') || is_file($root.'/next.config.mjs') || is_file($root.'/next.config.ts'),
                'nuxt' => is_file($root.'/nuxt.config.ts') || is_file($root.'/nuxt.config.js'),
                'angular' => is_file($root.'/angular.json'),
            ],
            'structure' => [
                'src' => is_dir($root.'/src'),
                'app' => is_dir($root.'/app'),
                'pages' => is_dir($root.'/pages') || is_dir($root.'/src/pages'),
                'components' => is_dir($root.'/components') || is_dir($root.'/src/components'),
                'larapilot_docs' => is_dir($root.'/.larapilot/docs'),
            ],
            'entrypoints' => $this->detectEntrypoints($root, $packageJson),
            'dependencies' => array_values(array_intersect(
                array_keys($dependencies),
                ['react', 'react-dom', 'vue', '@angular/core', 'svelte', '@sveltejs/kit', 'next', 'nuxt', '@inertiajs/react', '@inertiajs/vue3']
            )),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $packageJson
     * @return list<string>
     */
    protected function detectEntrypoints(string $root, ?array $packageJson): array
    {
        $candidates = [
            'src/main.ts',
            'src/main.tsx',
            'src/main.js',
            'src/main.jsx',
            'src/index.tsx',
            'src/index.ts',
            'src/App.tsx',
            'src/App.vue',
            'app/page.tsx',
            'app/page.jsx',
            'pages/index.tsx',
            'pages/index.vue',
        ];

        $found = [];

        foreach ($candidates as $relative) {
            if (is_file($root.'/'.$relative)) {
                $found[] = $relative;
            }
        }

        if ($found === [] && is_array($packageJson)) {
            $main = $packageJson['main'] ?? null;

            if (is_string($main) && is_file($root.'/'.$main)) {
                $found[] = $main;
            }
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>|null  $packageJson
     * @return array<string, string>
     */
    protected function mergeDependencies(?array $packageJson): array
    {
        if (! is_array($packageJson)) {
            return [];
        }

        $deps = [];

        foreach (['dependencies', 'devDependencies', 'peerDependencies'] as $key) {
            if (! is_array($packageJson[$key] ?? null)) {
                continue;
            }

            foreach ($packageJson[$key] as $name => $version) {
                if (is_string($name)) {
                    $deps[$name] = is_string($version) ? $version : '';
                }
            }
        }

        return $deps;
    }

    /**
     * @param  array<string, string>  $dependencies
     */
    protected function detectStack(array $dependencies, string $root): ?string
    {
        if (isset($dependencies['next'])) {
            return 'Next.js';
        }

        if (isset($dependencies['nuxt']) || is_file($root.'/nuxt.config.ts') || is_file($root.'/nuxt.config.js')) {
            return 'Nuxt';
        }

        if (isset($dependencies['@angular/core']) || is_file($root.'/angular.json')) {
            return 'Angular';
        }

        if (isset($dependencies['@sveltejs/kit']) || isset($dependencies['svelte'])) {
            return 'Svelte';
        }

        if (isset($dependencies['vue'])) {
            return 'Vue';
        }

        if (isset($dependencies['react'])) {
            return 'React';
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function readJson(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);

        if ($content === false || trim($content) === '') {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }
}
