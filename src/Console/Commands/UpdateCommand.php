<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\BoostPackageService;
use Larapilot\Services\CodeQualityService;
use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;
use Larapilot\Support\SharedRuntime;
use Symfony\Component\Process\Process;

class UpdateCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:update
                            {--skip-boost : Refresh the shared runtime only, without updating laravel/boost or republishing Boost guidelines and skills}
                            {--preserve-design-systems : Keep the project design-systems folder untouched (customizations survive)}';

    protected $description = 'Refresh Larapilot assets after a package upgrade (shared runtime + latest Boost + guidelines and skills)';

    public function handle(ConfigService $config, CodeQualityService $quality, BoostPackageService $boostPackage): int
    {
        if (! $config->hasProjectConfig()) {
            return $this->failure(
                'E_PRECONDITION',
                'Larapilot is not installed in this project.',
                $this->exitForCode('E_PRECONDITION'),
                'Run php artisan larapilot:install first.'
            );
        }

        $preserveDesignSystems = (bool) $this->option('preserve-design-systems');

        SharedRuntime::refresh(! $preserveDesignSystems);
        $quality->install(false, false);
        $this->components->info('Larapilot docs refreshed (.larapilot/shared-runtime.md, .larapilot/task-templates.md).');

        $preserveDesignSystems
            ? $this->line('Design systems preserved (.larapilot/design-systems/ untouched).')
            : $this->line('Design systems refreshed — local customizations in .larapilot/design-systems/ are overwritten. Use --preserve-design-systems to keep them.');

        $missingSettings = $config->missingSettingKeys();

        if ($missingSettings !== []) {
            $this->components->warn(
                'config.yaml is missing setting keys introduced by this version (defaults apply): '
                .implode(', ', $missingSettings)
                .'. Persist them with larapilot:settings-set.'
            );
        }

        if ($this->option('skip-boost')) {
            $this->line('Boost package update and publishing skipped. Run php artisan larapilot:update (without --skip-boost), or composer update laravel/boost --with-dependencies && php artisan boost:update.');

            return self::SUCCESS;
        }

        $package = $boostPackage->updateToLatest();
        $boostPackageRefreshed = $package['ok'] === true && $package['skipped'] !== true;

        if ($package['skipped'] === true && is_string($package['output']) && $package['output'] !== '') {
            $this->line($package['output']);
        } elseif ($boostPackageRefreshed) {
            $this->components->info('laravel/boost updated to the latest stable release.');
        } elseif ($package['ok'] !== true) {
            $this->components->warn($package['error'] ?? 'Could not update laravel/boost.');
            $this->line('Continuing with the currently installed Boost. To bump it yourself: composer update laravel/boost --with-dependencies');
        }

        if ($this->getApplication()?->has('boost:update') !== true) {
            return $this->failure(
                'E_PRECONDITION',
                'boost:update is not available, so guidelines and skills were not republished.',
                $this->exitForCode('E_PRECONDITION'),
                'Install Laravel Boost and run php artisan boost:install, or rerun with --skip-boost.'
            );
        }

        if ($this->republishBoostGuidelines($boostPackageRefreshed) !== self::SUCCESS) {
            return $this->failure(
                'E_PRECONDITION',
                'boost:update failed, so guidelines and skills were not republished.',
                $this->exitForCode('E_PRECONDITION'),
                'Run php artisan boost:install once; afterwards larapilot:update keeps everything current.'
            );
        }

        $this->components->info('Larapilot is up to date.');

        return self::SUCCESS;
    }

    /**
     * After a Composer bump, Boost classes in this process may be stale — run
     * boost:update in a fresh PHP process so the newly installed package loads.
     */
    protected function republishBoostGuidelines(bool $useFreshProcess): int
    {
        $artisan = base_path('artisan');

        if ($useFreshProcess && is_file($artisan) && ! $this->laravel->runningUnitTests()) {
            $process = new Process([PHP_BINARY, $artisan, 'boost:update', '--no-interaction'], base_path());
            $process->setTimeout(600);
            $process->run(function (string $_, string $buffer): void {
                $this->output->write($buffer);
            });

            return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
        }

        return $this->call('boost:update');
    }
}
