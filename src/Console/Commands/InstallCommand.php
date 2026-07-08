<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Illuminate\Support\Facades\File;
use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;

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
                'Run php artisan larapilot:install --force to overwrite .larapilot/config.yaml.'
            );
        }

        $config->writeProjectConfig([
            'connector' => $this->option('connector'),
        ]);

        $sharedRuntime = File::get(dirname(__DIR__, 3).'/resources/larapilot/shared-runtime.md');
        File::put(base_path('.larapilot/shared-runtime.md'), $sharedRuntime);

        $this->components->info('Larapilot installed successfully.');
        $this->line('  - .larapilot/config.yaml');
        $this->line('  - .larapilot/shared-runtime.md');
        $this->newLine();
        $this->line('Next: run php artisan boost:install (or boost:update --discover) to publish AI skills and guidelines.');

        return self::SUCCESS;
    }
}
