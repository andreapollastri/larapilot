<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\CodeQualityService;
use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;
use Larapilot\Support\SharedRuntime;

class UpdateCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:update
                            {--skip-boost : Refresh the shared runtime only, without republishing Boost guidelines and skills}
                            {--preserve-design-systems : Keep the project design-systems folder untouched (customizations survive)}';

    protected $description = 'Refresh Larapilot assets after a package upgrade (shared runtime + Boost guidelines and skills)';

    public function handle(ConfigService $config, CodeQualityService $quality): int
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
            $this->line('Boost publishing skipped. Run php artisan boost:update to refresh guidelines and skills.');

            return self::SUCCESS;
        }

        if ($this->getApplication()?->has('boost:update') !== true) {
            return $this->failure(
                'E_PRECONDITION',
                'boost:update is not available, so guidelines and skills were not republished.',
                $this->exitForCode('E_PRECONDITION'),
                'Install Laravel Boost and run php artisan boost:install, or rerun with --skip-boost.'
            );
        }

        if ($this->call('boost:update') !== self::SUCCESS) {
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
}
