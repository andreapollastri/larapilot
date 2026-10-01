<?php

declare(strict_types=1);

namespace Larapilot\Services\Frontend;

/**
 * How the frontend talks to the Laravel API: a client generated from an
 * OpenAPI document (orval, openapi-generator, ng-openapi-gen, hey-api,
 * openapi-typescript, kubb, RTK Query codegen, …) or calls written by hand.
 * A generated client is regenerated from the product's OpenAPI after the
 * backend changes — never edited, never written beside it by hand.
 */
final class ApiClientDetector
{
    /**
     * @var array<string, array{packages: list<string>, configs: list<string>, bins: list<string>}>
     */
    public const GENERATORS = [
        'orval' => ['packages' => ['orval'], 'configs' => ['orval.config'], 'bins' => ['orval']],
        'openapi-generator' => ['packages' => ['@openapitools/openapi-generator-cli'], 'configs' => ['openapitools.json'], 'bins' => ['openapi-generator-cli', 'openapi-generator']],
        'ng-openapi-gen' => ['packages' => ['ng-openapi-gen'], 'configs' => ['ng-openapi-gen.json'], 'bins' => ['ng-openapi-gen']],
        'hey-api' => ['packages' => ['@hey-api/openapi-ts'], 'configs' => ['openapi-ts.config'], 'bins' => ['openapi-ts', '@hey-api/openapi-ts']],
        'openapi-typescript' => ['packages' => ['openapi-typescript'], 'configs' => [], 'bins' => ['openapi-typescript']],
        'openapi-typescript-codegen' => ['packages' => ['openapi-typescript-codegen'], 'configs' => [], 'bins' => ['openapi-typescript-codegen', 'openapi --input']],
        'swagger-typescript-api' => ['packages' => ['swagger-typescript-api'], 'configs' => [], 'bins' => ['swagger-typescript-api', 'sta ']],
        'kubb' => ['packages' => ['@kubb/core', '@kubb/cli'], 'configs' => ['kubb.config'], 'bins' => ['kubb']],
        'rtk-query-codegen' => ['packages' => ['@rtk-query/codegen-openapi'], 'configs' => ['openapi-config'], 'bins' => ['rtk-query-codegen-openapi']],
        'ng-swagger-gen' => ['packages' => ['ng-swagger-gen'], 'configs' => ['ng-swagger-gen.json'], 'bins' => ['ng-swagger-gen']],
        'openapi-zod-client' => ['packages' => ['openapi-zod-client'], 'configs' => [], 'bins' => ['openapi-zod-client']],
    ];

    /**
     * @param  array<string, mixed>  $workspace
     */
    public function __construct(
        protected string $root,
        protected array $workspace,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $targets
     * @param  list<string>  $sample  Source files of the target projects.
     * @return array<string, mixed>
     */
    public function detect(array $targets, array $sample): array
    {
        $rootPackage = RepoFiles::json($this->root.'/package.json');
        $dependencies = WorkspaceInspector::dependencies($rootPackage);
        $directories = ['.' => true];

        foreach ($targets as $project) {
            $directories[(string) $project['root']] = true;
        }

        foreach ($this->workspace['projects'] as $project) {
            if (preg_match('/api|client|sdk|openapi|swagger|backend|data-access/i', (string) $project['name']) === 1) {
                $directories[(string) $project['root']] = true;
            }
        }

        $found = [];

        foreach (array_keys($directories) as $directory) {
            $absolute = $directory === '.' ? $this->root : $this->root.'/'.$directory;
            $own = $directory === '.' ? [] : WorkspaceInspector::dependencies(RepoFiles::json($absolute.'/package.json'));

            foreach (self::GENERATORS as $name => $generator) {
                $config = null;

                foreach ($generator['configs'] as $stem) {
                    $config = str_contains($stem, '.json')
                        ? (is_file($absolute.'/'.$stem) ? $stem : null)
                        : RepoFiles::firstExisting($absolute, RepoFiles::configNames($stem));

                    if ($config !== null) {
                        break;
                    }
                }

                $installed = false;

                foreach ($generator['packages'] as $package) {
                    $installed = $installed || isset($dependencies[$package]) || isset($own[$package]);
                }

                if ($config === null && ! ($installed && $directory === '.')) {
                    continue;
                }

                $relative = $config === null ? null : ($directory === '.' ? $config : $directory.'/'.$config);
                [$inputs, $outputs] = $relative === null ? [[], []] : $this->paths($this->root.'/'.$relative);

                $key = $name.'|'.($relative ?? '');
                $found[$key] = array_filter([
                    'name' => $name,
                    'config' => $relative,
                    'inputs' => $inputs === [] ? null : $inputs,
                    'outputs' => $outputs === [] ? null : $outputs,
                ], static fn (mixed $value): bool => $value !== null);
            }
        }

        $regenerate = $this->regenerateCommands();

        return [
            'generated' => $found !== [] || $regenerate !== [],
            'generators' => array_values($found),
            'regenerate' => $regenerate,
            'handwritten' => $this->handwritten($sample),
        ];
    }

    /**
     * Spec sources and output folders a generator config names.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    protected function paths(string $config): array
    {
        $content = RepoFiles::read($config, 64000);

        if ($content === null) {
            return [[], []];
        }

        $inputs = [];
        $outputs = [];

        if (str_ends_with($config, '.json')) {
            $json = RepoFiles::json($config) ?? [];
            array_walk_recursive($json, function (mixed $value, mixed $key) use (&$inputs, &$outputs): void {
                if (! is_string($value) || ! is_string($key)) {
                    return;
                }

                if (in_array($key, ['inputSpec', 'input', 'spec', 'schema', 'schemaFile'], true)) {
                    $inputs[] = $value;
                } elseif (in_array($key, ['output', 'outputFile', 'outputDir', 'target'], true)) {
                    $outputs[] = $value;
                }
            });
        } else {
            preg_match_all('/\b(input|inputSpec|output|target|path|schemaFile|outputFile|schemas|workspace)\s*:\s*[\'"`]([^\'"`]+)[\'"`]/', $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $value = $match[2];

                if (preg_match('#^https?://#', $value) === 1 || preg_match('/\.(json|ya?ml)$/i', $value) === 1) {
                    $inputs[] = $value;
                } elseif (in_array($match[1], ['output', 'target', 'outputFile', 'schemas'], true)) {
                    $outputs[] = $value;
                }
            }
        }

        return [array_values(array_unique($inputs)), array_values(array_unique($outputs))];
    }

    /**
     * Scripts and Nx targets that run a generator.
     *
     * @return list<string>
     */
    protected function regenerateCommands(): array
    {
        $manager = $this->workspace['package_manager'];
        $kind = (string) $this->workspace['kind'];
        $commands = [];
        $bins = [];

        foreach (self::GENERATORS as $generator) {
            foreach ($generator['bins'] as $bin) {
                $bins[] = $bin;
            }
        }

        $rootScripts = RepoFiles::json($this->root.'/package.json')['scripts'] ?? [];

        foreach (is_array($rootScripts) ? $rootScripts : [] as $script => $command) {
            if (is_string($script) && is_string($command) && $this->runsGenerator($command, $bins)) {
                $commands[] = sprintf('%s %s', $manager['run'], $script);
            }
        }

        foreach ($this->workspace['projects'] as $project) {
            if ($project['root'] === '.') {
                continue;
            }

            foreach ($project['targets'] ?? [] as $target => $definition) {
                $command = is_array($definition) ? (string) ($definition['command'] ?? '') : '';
                $executor = is_array($definition) ? (string) ($definition['executor'] ?? '') : '';

                if (! $this->runsGenerator($command.' '.$executor, $bins)) {
                    continue;
                }

                $commands[] = match ($kind) {
                    'nx' => sprintf('%s nx run %s:%s', $manager['exec'], $project['name'], $target),
                    'angular-cli' => sprintf('%s ng run %s:%s', $manager['exec'], $project['name'], $target),
                    'pnpm-workspaces' => sprintf('pnpm --filter %s run %s', $project['package'] ?? $project['name'], $target),
                    'yarn-workspaces' => sprintf('yarn workspace %s run %s', $project['package'] ?? $project['name'], $target),
                    'npm-workspaces' => sprintf('npm run %s --workspace=%s', $target, $project['package'] ?? $project['name']),
                    'turborepo' => sprintf('%s turbo run %s --filter=%s', $manager['exec'], $target, $project['package'] ?? $project['name']),
                    default => sprintf('cd %s && %s %s', $project['root'], $manager['run'], $target),
                };
            }
        }

        return array_values(array_unique($commands));
    }

    /**
     * @param  list<string>  $bins
     */
    protected function runsGenerator(string $command, array $bins): bool
    {
        foreach ($bins as $bin) {
            if ($command !== '' && preg_match('/(^|[\s\/&;|])'.preg_quote($bin, '/').'/', $command) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calls written by hand, counted on the target projects.
     *
     * @param  list<string>  $sample
     * @return array<string, int>
     */
    protected function handwritten(array $sample): array
    {
        $counts = ['HttpClient' => 0, 'axios' => 0, 'fetch' => 0, 'ofetch/$fetch' => 0, 'ky' => 0];
        $read = 0;

        foreach ($sample as $file) {
            if ($read >= 400 || preg_match('/\.(ts|tsx|js|jsx|vue|svelte)$/', $file) !== 1 || preg_match('/\.(spec|test)\./', $file) === 1) {
                continue;
            }

            $read++;
            $content = (string) RepoFiles::read($this->root.'/'.$file, 32000);
            $counts['HttpClient'] += preg_match_all('/\bHttpClient\b/', $content);
            $counts['axios'] += preg_match_all('/\baxios\s*[.(]/', $content);
            $counts['fetch'] += preg_match_all('/(?<![\w$.])fetch\s*\(/', $content);
            $counts['ofetch/$fetch'] += preg_match_all('/\$fetch\s*[(<]|\bofetch\s*\(/', $content);
            $counts['ky'] += preg_match_all('/\bky\s*[.(]/', $content);
        }

        return array_filter($counts);
    }
}
