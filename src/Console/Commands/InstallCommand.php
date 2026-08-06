<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\CodeQualityService;
use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;
use Larapilot\Support\SharedRuntime;

class InstallCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:install
                            {--connector=file : Active connector (file only in v1)}
                            {--force : Overwrite existing project config}
                            {--skip-composer : Scaffold quality files only; do not run composer require}';

    protected $description = 'Initialize Larapilot in the current Laravel project';

    public function handle(ConfigService $config, CodeQualityService $quality): int
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

        $qualityResult = $quality->install(
            (bool) $this->option('force'),
            ! $this->option('skip-composer') && ! $this->laravel->runningUnitTests()
        );

        $this->components->info('Larapilot installed successfully.');
        $this->line('  - .larapilot/config.yaml');
        $this->line('  - .larapilot/shared-runtime.md');
        $this->line('  - .larapilot/integrations.md');
        $this->line('  - .larapilot/task-templates.md');
        $this->line('  - .larapilot/client-materials/');
        $this->line('  - .larapilot/legacy/');
        $this->line('  - .larapilot/research/');
        $this->line('  - .larapilot/design-systems/ (Filament, Starter Kit, Bootstrap 5, Tailwind, AdminLTE references for mockups)');

        foreach ($qualityResult['written'] as $file) {
            $this->line('  - '.$file);
        }

        if (($qualityResult['composer']['merged'] ?? false) === true) {
            $this->line('  - composer.json (lint/analyse scripts + dev dependencies)');
        }

        if (($qualityResult['packages']['installed'] ?? false) === true) {
            $this->components->info('Larastan + Pint installed via Composer.');
        } elseif (($qualityResult['packages']['error'] ?? null) !== null) {
            $this->components->warn($qualityResult['packages']['error']);
        }

        $this->newLine();
        $this->line('Code quality gate: Larastan level '.CodeQualityService::MIN_LARASTAN_LEVEL.'+ and Laravel Pint (larapilot:quality).');
        $this->newLine();
        $this->line('Next: run php artisan boost:install (or boost:update --discover) to publish AI skills and guidelines.');

        return self::SUCCESS;
    }
}
