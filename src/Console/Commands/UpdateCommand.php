<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;
use Larapilot\Support\SharedRuntime;

class UpdateCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:update
                            {--skip-boost : Refresh the shared runtime only, without republishing Boost guidelines and skills}';

    protected $description = 'Refresh Larapilot assets after a package upgrade (shared runtime + Boost guidelines and skills)';

    public function handle(ConfigService $config): int
    {
        if (! $config->hasProjectConfig()) {
            return $this->failure(
                'E_PRECONDITION',
                'Larapilot is not installed in this project.',
                $this->exitForCode('E_PRECONDITION'),
                'Run php artisan larapilot:install first.'
            );
        }

        SharedRuntime::refresh();
        $this->components->info('Larapilot docs refreshed (.larapilot/shared-runtime.md, .larapilot/task-templates.md).');

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
