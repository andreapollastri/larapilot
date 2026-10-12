<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\CodeQualityService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\PackagedSkillService;
use Larapilot\Support\LarapilotCommand;
use Larapilot\Support\SharedRuntime;

class InstallCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:install
                            {--connector=file : Active connector (`file` is the only one shipped)}
                            {--force : Overwrite existing project config}
                            {--skip-composer : Scaffold quality files only; do not run composer require}';

    protected $description = 'Initialize Larapilot in the current Laravel project';

    public function handle(ConfigService $config, CodeQualityService $quality, PackagedSkillService $packagedSkills): int
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
        $config->ignoreTemporaryFiles();

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
        $this->line('  - .larapilot/design-systems/ (Filament, Nova, AdminLTE, Starter Kit, shadcn/ui, Bootstrap 5, Tailwind, Material 3, Nordic SaaS, Fluent 2, Modern, Bold California, Berlin, New York, Zen references for mockups)');
        $this->line('  - .larapilot/skills/ (.gitkeep)');
        $this->line('  - .larapilot/docs/handbook/ (living handbook, filled when project_docs=YES)');

        foreach ($qualityResult['written'] as $file) {
            $this->line('  - '.$file);
        }

        if (($qualityResult['composer']['merged'] ?? false) === true) {
            $declared = $qualityResult['composer']['require_dev'] ?? [];
            $this->line('  - composer.json ('.($declared !== [] ? implode(', ', $declared).' declared for the next composer update; ' : '').'lint/analyse scripts)');
        } elseif (($qualityResult['composer']['scripts'] ?? []) !== []) {
            $this->line('  - composer.json scripts: '.implode(', ', $qualityResult['composer']['scripts']));
        }

        if (($qualityResult['packages']['installed'] ?? false) === true) {
            $this->components->info('Larastan + Pint installed via Composer.');
        } elseif (($qualityResult['packages']['error'] ?? null) !== null) {
            $this->components->warn($qualityResult['packages']['error']);
        } elseif (($qualityResult['packages']['present'] ?? false) === true) {
            $this->line('Larastan and Pint were already in the project: Composer was not run.');
        }

        $this->newLine();
        $this->line('Code quality gate: Larastan level '.CodeQualityService::MIN_LARASTAN_LEVEL.'+ and Laravel Pint (larapilot:quality).');
        $this->newLine();
        $this->nextForBoost($packagedSkills);
        $this->line('Then: /larapilot-inception for a new product, or /larapilot-adopt to reverse-engineer a PRD from this existing codebase.');

        return self::SUCCESS;
    }

    /**
     * What publishes the guidelines and the skills depends on the Boost the
     * project has: Boost 2 publishes both, Boost 1 the guidelines alone.
     */
    protected function nextForBoost(PackagedSkillService $packagedSkills): void
    {
        if (! $packagedSkills->boostInstalled()) {
            $this->line('Next: composer require laravel/boost --dev, then php artisan boost:install to publish the AI guidelines and skills.');

            return;
        }

        $listed = $packagedSkills->listInBoostJson();

        if ($listed !== null) {
            $this->line('andreapollastri/larapilot added to "'.$listed.'" in boost.json: Boost publishes the guidelines only of the packages listed there.');
        }

        if ($packagedSkills->boostPublishesSkills()) {
            $this->line('Next: run php artisan boost:install to publish the AI guidelines and skills — or, if Boost is already set up, php artisan boost:update.');

            return;
        }

        $published = $packagedSkills->publish();

        $this->line($published['folders'] !== []
            ? 'Boost 1.x publishes guidelines but no skills: Larapilot copied its skills into '.implode(', ', $published['folders']).'.'
            : 'Boost 1.x publishes guidelines but no skills: once boost:install has chosen your agents, php artisan larapilot:update copies the Larapilot skills into their folders.');
        $this->line('Next: run php artisan boost:install for the guidelines, and select andreapollastri/larapilot among the third-party guidelines.');
    }
}
