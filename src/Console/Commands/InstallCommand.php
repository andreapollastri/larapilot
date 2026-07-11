<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;
use Larapilot\Support\SharedRuntime;

class InstallCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:install
                            {--connector=file : Active connector (file only in v1)}
                            {--force : Overwrite existing project config}';

    protected $description = 'Initialize Larapilot in the current Laravel project';

    public function handle(ConfigService $config): int
    {
        if ($config->hasProjectConfig() && ! $this->option('force')) {
            return $this->failure(
                'E_PRECONDITION',
                'Larapilot is already installed.',
                $this->exitForCode('E_PRECONDITION'),
                'Run php artisan larapilot:update after a package upgrade, or larapilot:install --force to overwrite .larapilot/config.yaml.'
            );
        }

        SharedRuntime::refresh();

        $config->writeProjectConfig([
            'connector' => $this->option('connector'),
        ]);

        $this->components->info('Larapilot installed successfully.');
        $this->line('  - .larapilot/config.yaml');
        $this->line('  - .larapilot/shared-runtime.md');
        $this->line('  - .larapilot/task-templates.md');
        $this->line('  - .larapilot/client-materials/');
        $this->line('  - .larapilot/legacy/');
        $this->line('  - .larapilot/research/');
        $this->line('  - .larapilot/design-systems/ (Filament + Starter Kit references for mockups)');
        $this->newLine();
        $this->line('Next: run php artisan boost:install (or boost:update --discover) to publish AI skills and guidelines.');

        return self::SUCCESS;
    }
}
