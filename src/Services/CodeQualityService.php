<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\AtomicFile;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class CodeQualityService
{
    public const MIN_LARASTAN_LEVEL = 5;

    public const LARASTAN_PACKAGE = 'larastan/larastan';

    public const PINT_PACKAGE = 'laravel/pint';

    public function __construct(
        protected ConfigService $config,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function install(bool $force = false, bool $runComposer = true): array
    {
        $written = [];
        $skipped = [];

        foreach ([
            'phpstan.neon.dist' => $this->stubPath('phpstan.neon.dist'),
            'pint.json' => $this->stubPath('pint.json'),
        ] as $target => $source) {
            $destination = base_path($target);

            if (is_file($destination) && ! $force) {
                $skipped[] = $target;

                continue;
            }

            AtomicFile::write($destination, (string) file_get_contents($source));
            $written[] = $target;
        }

        $composer = $this->mergeComposerManifest();

        $packages = ['installed' => false, 'output' => null, 'error' => null];

        if ($runComposer) {
            $packages = $this->ensureComposerPackages();
        }

        return [
            'ok' => true,
            'written' => $written,
            'skipped' => $skipped,
            'composer' => $composer,
            'packages' => $packages,
            'min_larastan_level' => self::MIN_LARASTAN_LEVEL,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $composer = $this->readComposerManifest();
        $larastanLevel = $this->detectLarastanLevel();

        return [
            'pint_config' => is_file(base_path('pint.json')),
            'larastan_config' => $this->larastanConfigPath() !== null,
            'larastan_level' => $larastanLevel,
            'larastan_level_ok' => $larastanLevel !== null && $larastanLevel >= self::MIN_LARASTAN_LEVEL,
            'pint_binary' => is_file(base_path('vendor/bin/pint')),
            'phpstan_binary' => is_file(base_path('vendor/bin/phpstan')),
            'composer_require_dev' => [
                self::PINT_PACKAGE => $this->composerRequiresDev($composer, self::PINT_PACKAGE),
                self::LARASTAN_PACKAGE => $this->composerRequiresDev($composer, self::LARASTAN_PACKAGE),
            ],
            'min_larastan_level' => self::MIN_LARASTAN_LEVEL,
        ];
    }

    public function healthy(): bool
    {
        $status = $this->status();

        return $status['pint_config']
            && $status['larastan_config']
            && $status['larastan_level_ok']
            && $status['composer_require_dev'][self::PINT_PACKAGE]
            && $status['composer_require_dev'][self::LARASTAN_PACKAGE];
    }

    /**
     * @return array{ok: bool, pint: array<string, mixed>, analyse: array<string, mixed>}
     */
    public function run(bool $fixPint = false): array
    {
        $pint = $this->runBinary('pint', $fixPint ? [] : ['--test']);
        $analyse = $this->runBinary('phpstan', ['analyse', '--no-progress', '--memory-limit=1G']);

        return [
            'ok' => ($pint['ok'] ?? false) && ($analyse['ok'] ?? false),
            'pint' => $pint,
            'analyse' => $analyse,
        ];
    }

    /**
     * @return array{merged: bool, scripts: list<string>, require_dev: list<string>}
     */
    protected function mergeComposerManifest(): array
    {
        $path = base_path('composer.json');

        if (! is_file($path)) {
            return ['merged' => false, 'scripts' => [], 'require_dev' => []];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return ['merged' => false, 'scripts' => [], 'require_dev' => []];
        }

        $mergedScripts = [];
        $mergedRequireDev = [];

        $decoded['scripts'] ??= [];
        $decoded['require-dev'] ??= [];

        foreach ([
            'lint' => 'pint',
            'lint:check' => 'pint --test',
            'analyse' => 'phpstan analyse --memory-limit=1G',
        ] as $name => $command) {
            if (isset($decoded['scripts'][$name])) {
                continue;
            }

            $decoded['scripts'][$name] = $command;
            $mergedScripts[] = $name;
        }

        foreach ([
            self::PINT_PACKAGE => '^1.27',
            self::LARASTAN_PACKAGE => '^3.0',
        ] as $package => $constraint) {
            if (isset($decoded['require-dev'][$package])) {
                continue;
            }

            $decoded['require-dev'][$package] = $constraint;
            $mergedRequireDev[] = $package;
        }

        if ($mergedScripts === [] && $mergedRequireDev === []) {
            return ['merged' => false, 'scripts' => [], 'require_dev' => []];
        }

        ksort($decoded['require-dev']);
        ksort($decoded['scripts']);

        AtomicFile::write($path, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        return [
            'merged' => true,
            'scripts' => $mergedScripts,
            'require_dev' => $mergedRequireDev,
        ];
    }

    /**
     * @return array{installed: bool, output: string|null, error: string|null}
     */
    protected function ensureComposerPackages(): array
    {
        if (! is_file(base_path('composer.json'))) {
            return [
                'installed' => false,
                'output' => null,
                'error' => 'composer.json not found.',
            ];
        }

        $composer = $this->readComposerManifest();
        $missing = [];

        if (! $this->composerRequiresDev($composer, self::PINT_PACKAGE)) {
            $missing[] = self::PINT_PACKAGE.':^1.27';
        }

        if (! $this->composerRequiresDev($composer, self::LARASTAN_PACKAGE)) {
            $missing[] = self::LARASTAN_PACKAGE.':^3.0';
        }

        if ($missing === []) {
            return ['installed' => true, 'output' => 'Packages already present.', 'error' => null];
        }

        $binary = $this->composerBinary();

        if ($binary === null) {
            return [
                'installed' => false,
                'output' => null,
                'error' => 'Composer binary not found. Run: composer require --dev '.implode(' ', $missing),
            ];
        }

        $process = new Process(array_merge([$binary, 'require', '--dev', '--no-interaction', '--no-progress'], $missing), base_path());
        $process->setTimeout(600);

        try {
            $process->mustRun();

            return [
                'installed' => true,
                'output' => trim($process->getOutput()),
                'error' => null,
            ];
        } catch (ProcessFailedException $exception) {
            return [
                'installed' => false,
                'output' => trim($process->getOutput()),
                'error' => trim($process->getErrorOutput()) ?: $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  list<string>  $arguments
     * @return array{ok: bool, exit_code: int, output: string}
     */
    protected function runBinary(string $tool, array $arguments = []): array
    {
        $binary = base_path('vendor/bin/'.$tool);

        if (! is_file($binary)) {
            return [
                'ok' => false,
                'exit_code' => 127,
                'output' => $tool.' binary not found. Run composer install after larapilot:install.',
            ];
        }

        $process = new Process(array_merge([$binary], $arguments), base_path());
        $process->setTimeout(600);
        $process->run();

        return [
            'ok' => $process->isSuccessful(),
            'exit_code' => $process->getExitCode() ?? 1,
            'output' => trim($process->getOutput()."\n".$process->getErrorOutput()),
        ];
    }

    protected function stubPath(string $file): string
    {
        return dirname(__DIR__, 2).'/resources/stubs/'.$file;
    }

    protected function larastanConfigPath(): ?string
    {
        foreach (['phpstan.neon', 'phpstan.neon.dist'] as $candidate) {
            if (is_file(base_path($candidate))) {
                return base_path($candidate);
            }
        }

        return null;
    }

    protected function detectLarastanLevel(): ?int
    {
        $path = $this->larastanConfigPath();

        if ($path === null) {
            return null;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return null;
        }

        if (preg_match('/^\s*level:\s*(\d+)\s*$/m', $content, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function readComposerManifest(): ?array
    {
        $path = base_path('composer.json');

        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>|null  $composer
     */
    protected function composerRequiresDev(?array $composer, string $package): bool
    {
        return is_array($composer['require-dev'] ?? null)
            && array_key_exists($package, $composer['require-dev']);
    }

    protected function composerBinary(): ?string
    {
        $candidates = ['composer', 'composer.phar'];

        foreach ($candidates as $candidate) {
            $process = new Process([$candidate, '--version']);
            $process->run();

            if ($process->isSuccessful()) {
                return $candidate;
            }
        }

        return null;
    }
}
