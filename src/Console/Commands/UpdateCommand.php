<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\BoostPackageService;
use Larapilot\Services\CodeQualityService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\CustomSkillService;
use Larapilot\Services\ScheduleService;
use Larapilot\Support\LarapilotCommand;
use Larapilot\Support\SharedRuntime;
use Symfony\Component\Process\Process;

class UpdateCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:update
                            {--skip-boost : Refresh the shared runtime only, without updating laravel/boost or republishing Boost guidelines and skills}
                            {--preserve-design-systems : Keep the project design-systems folder untouched (customizations survive)}';

    protected $description = 'Refresh Larapilot assets after a package upgrade (shared runtime + latest Boost + guidelines and skills)';

    public function handle(ConfigService $config, CodeQualityService $quality, BoostPackageService $boostPackage, CustomSkillService $customSkills, ScheduleService $schedule): int
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

        $this->moveLegacyProjectDocs($config);
        $config->ensureDirectories();
        $this->realignSchedule($schedule);

        $missingSettings = $config->missingSettingKeys();

        if ($missingSettings !== []) {
            $this->components->warn(
                'config.yaml is missing setting keys introduced by this version (defaults apply): '
                .implode(', ', $missingSettings)
                .'. Persist them with larapilot:settings-set.'
            );
        }

        $customSkills->registerAll();

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
     * The handbook used to live in `_project_docs/` at the project root; an
     * upgrade carries what it holds into `.larapilot/docs/handbook/`.
     */
    protected function moveLegacyProjectDocs(ConfigService $config): void
    {
        $migration = $config->migrateLegacyProjectDocs();

        if ($migration === null) {
            return;
        }

        if ($migration['moved'] !== []) {
            $this->components->info(sprintf(
                'Handbook moved: %d file(s) from %s to %s.',
                count($migration['moved']),
                $migration['from'],
                $migration['to']
            ));
        }

        if ($migration['kept'] !== []) {
            $this->components->warn(sprintf(
                'Left in %s because %s already holds a file with the same name: %s. Reconcile them by hand.',
                $migration['from'],
                $migration['to'],
                implode(', ', $migration['kept'])
            ));
        } elseif ($migration['removed']) {
            $this->line('Removed the empty '.$migration['from'].' folder.');
        }
    }

    /**
     * A project planned on an earlier version keeps what that version wrote,
     * and its dates were agreed against a chart where every spec started on
     * the first day. The upgrade puts right what takes no decision, and says
     * what the forecast now misses: that part is for `/larapilot-schedule`.
     */
    protected function realignSchedule(ScheduleService $schedule): void
    {
        try {
            $repair = $schedule->repair();
            $forecast = $schedule->show();
        } catch (\Throwable $e) {
            // The backlog or the schedule cannot be read: the forecast pages report it themselves.
            $this->components->warn('The delivery forecast could not be checked: '.$e->getMessage());

            return;
        }

        if ($repair['fixes'] !== []) {
            $this->components->info(sprintf('Delivery forecast realigned: %d fix(es).', count($repair['fixes'])));

            foreach ($repair['fixes'] as $fix) {
                $this->line('  '.$fix['message']);
            }
        }

        if ($forecast['remaining']['specs'] === 0) {
            return;
        }

        $unread = array_filter(
            $forecast['findings'],
            static fn (array $finding): bool => $finding['severity'] !== 'info'
        );
        $undated = in_array('SCHEDULE_NO_DATES', array_column($forecast['findings'], 'code'), true);
        // Open specs whose tasks are all done leave nothing to forecast: they end today.
        $ends = 'Delivery forecast: the open specs end on '.($forecast['forecast_end'] ?? $forecast['today']);

        if ($forecast['alerts'] !== [] || $unread !== []) {
            $this->components->warn(sprintf(
                '%s — %d date(s) do not hold and %d finding(s) need a decision. Re-plan with /larapilot-schedule; php artisan larapilot:schedule-show lists them.',
                $ends,
                count($forecast['alerts']),
                count($unread)
            ));
        } elseif ($undated) {
            $this->line($ends.', and no milestone or epic deadline is set to measure it against. /larapilot-schedule proposes them.');
        } else {
            $this->line($ends.'; every date holds.');
        }
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
