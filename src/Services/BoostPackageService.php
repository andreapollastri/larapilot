<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class BoostPackageService
{
    public const PACKAGE = 'laravel/boost';

    /** The oldest Laravel any Boost 2 installs on, with PHP 8.2. */
    public const BOOST_TWO_MIN_LARAVEL = '11.45.3';

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

        $blocked = $this->boostTwoBlockedBy();

        if ($blocked !== null) {
            return [
                'ok' => true,
                'skipped' => true,
                'output' => 'laravel/boost '.$this->installedVersion().' kept, composer.lock untouched: Boost 2 '.$blocked.'. boost:update still refreshes the guidelines, and Larapilot publishes its skills itself.',
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

    /**
     * Why Composer cannot bring Boost 2 to a project still on Boost 1, or
     * null when it can (or Boost 2 is already there). `composer update
     * laravel/boost --with-dependencies` would otherwise stay on 1.x and
     * still move its dependencies, `laravel/mcp` among them.
     */
    public function boostTwoBlockedBy(): ?string
    {
        $installed = $this->installedVersion();

        if ($installed === null || ! str_starts_with(ltrim($installed, 'v'), '1.')) {
            return null;
        }

        if (PHP_VERSION_ID < 80200) {
            return 'needs PHP 8.2 (this is PHP '.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.')';
        }

        if (version_compare($this->app->version(), self::BOOST_TWO_MIN_LARAVEL, '<')) {
            return 'needs Laravel '.self::BOOST_TWO_MIN_LARAVEL.' or later (this is '.$this->app->version().')';
        }

        $constraint = $this->projectConstraint();

        if ($constraint !== null) {
            try {
                $parser = new VersionParser;

                if (! $parser->parseConstraints($constraint)->matches($parser->parseConstraints('>=2.0.0'))) {
                    return 'is outside the constraint composer.json sets ('.$constraint.')';
                }
            } catch (\UnexpectedValueException) {
                return null;
            }
        }

        return null;
    }

    public function installedVersion(): ?string
    {
        if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled(self::PACKAGE)) {
            return null;
        }

        return InstalledVersions::getPrettyVersion(self::PACKAGE);
    }

    /**
     * The constraint the project itself puts on laravel/boost, if any.
     */
    protected function projectConstraint(): ?string
    {
        $path = base_path('composer.json');
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        foreach (['require', 'require-dev'] as $section) {
            $constraint = is_array($decoded) ? ($decoded[$section][self::PACKAGE] ?? null) : null;

            if (is_string($constraint) && trim($constraint) !== '') {
                return trim($constraint);
            }
        }

        return null;
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
