<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class BoostPackageService
{
    public const PACKAGE = 'laravel/boost';

    public function __construct(
        protected Application $app,
    ) {}

    /**
     * Pull the latest stable laravel/boost (and its own dependencies) into the
     * consuming project. No-ops inside Composer scripts (avoids recursion) and
     * during the package test suite.
     *
     * @return array{ok: bool, skipped: bool, output: string|null, error: string|null}
     */
    public function updateToLatest(): array
    {
        if ($this->app->runningUnitTests()) {
            return [
                'ok' => true,
                'skipped' => true,
                'output' => null,
                'error' => null,
            ];
        }

        if ($this->runningInsideComposer()) {
            return [
                'ok' => true,
                'skipped' => true,
                'output' => 'Skipped Composer Boost update (already inside a Composer script).',
                'error' => null,
            ];
        }

        $binary = $this->composerBinary();

        if ($binary === null) {
            return [
                'ok' => false,
                'skipped' => false,
                'output' => null,
                'error' => 'Composer binary not found. Run: composer update '.self::PACKAGE.' --with-dependencies',
            ];
        }

        $process = new Process([
            $binary,
            'update',
            self::PACKAGE,
            '--with-dependencies',
            '--no-interaction',
            '--no-progress',
            '--no-scripts',
            '--prefer-stable',
        ], base_path());
        $process->setTimeout(600);

        try {
            $process->mustRun();

            return [
                'ok' => true,
                'skipped' => false,
                'output' => trim($process->getOutput()."\n".$process->getErrorOutput()),
                'error' => null,
            ];
        } catch (ProcessFailedException $exception) {
            return [
                'ok' => false,
                'skipped' => false,
                'output' => trim($process->getOutput()),
                'error' => trim($process->getErrorOutput()) ?: $exception->getMessage(),
            ];
        }
    }

    public function runningInsideComposer(): bool
    {
        $binary = getenv('COMPOSER_BINARY');

        return is_string($binary) && $binary !== '';
    }

    protected function composerBinary(): ?string
    {
        foreach (['composer', 'composer.phar'] as $candidate) {
            $process = new Process([$candidate, '--version']);
            $process->run();

            if ($process->isSuccessful()) {
                return $candidate;
            }
        }

        return null;
    }
}
