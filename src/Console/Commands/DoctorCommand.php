<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\CodeQualityService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\HookService;
use Larapilot\Services\PrdService;
use Larapilot\Services\SpecService;
use Larapilot\Support\LarapilotCommand;
use Larapilot\Support\SharedRuntime;
use Laravel\Boost\BoostServiceProvider;

class DoctorCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:doctor
                            {--human : Print a readable table instead of the JSON envelope}';

    protected $description = 'Diagnose Larapilot installation and project setup';

    public function handle(ConfigService $config, CodeQualityService $quality, PrdService $prd, SpecService $specs, HookService $hooks): int
    {
        $designSystems = SharedRuntime::designSystemsProjectPath();
        $qualityStatus = $quality->status();

        $runtimePacks = collect(SharedRuntime::packagedDocs())
            ->filter(fn (string $file): bool => str_starts_with($file, 'runtime-'));

        $checks = [
            'config' => $config->hasProjectConfig(),
            'shared_runtime' => is_file(base_path('.larapilot/shared-runtime.md')),
            'task_templates' => is_file(base_path('.larapilot/task-templates.md')),
            'runtime_packs' => $runtimePacks->isNotEmpty() && $runtimePacks->every(
                fn (string $file): bool => is_file(SharedRuntime::projectDocPath($file))
            ),
            'design_systems' => is_dir($designSystems) && count(glob($designSystems.'/*') ?: []) > 0,
            'dev_docs_scaffold' => is_file(base_path('.larapilot/docs/devs/README.md'))
                && is_file(base_path('.larapilot/docs/devs/TEMPLATE.md')),
            'handbook_scaffold' => is_dir($config->projectDocsDirectory()),
            'backlog' => is_file($specs->backlogPath()),
            'prd' => $prd->exists(),
            'boost' => class_exists(BoostServiceProvider::class),
            'settings_valid' => $config->settingsValid(),
            // Off, or a hooks.yaml that reads: with errors every transition is refused.
            'hooks' => $hooks->healthy(),
            'quality_pint' => $qualityStatus['pint_config'],
            'quality_larastan' => $qualityStatus['larastan_config'] && $qualityStatus['larastan_level_ok'],
            'quality_packages' => $qualityStatus['composer_require_dev'][CodeQualityService::PINT_PACKAGE]
                && $qualityStatus['composer_require_dev'][CodeQualityService::LARASTAN_PACKAGE],
        ];

        $missingSettings = $config->missingSettingKeys();
        $legacyProjectDocs = is_dir($config->absolutePath(ConfigService::LEGACY_PROJECT_DOCS_PATH));
        $healthy = $checks['config']
            && $checks['shared_runtime']
            && $checks['boost']
            && $checks['settings_valid']
            && $checks['hooks']
            && $checks['quality_pint']
            && $checks['quality_larastan']
            && $checks['quality_packages'];

        if ((bool) $this->option('human')) {
            $this->table(['Check', 'Status'], collect($checks)
                ->map(fn (bool $ok, string $key): array => [$key, $ok ? 'OK' : 'MISSING'])
                ->values()
                ->all());

            if ($missingSettings !== []) {
                $this->components->warn(
                    'config.yaml is missing setting keys (package defaults apply): '.implode(', ', $missingSettings)
                    .'. Persist them with larapilot:settings-set.'
                );
            }

            if ($legacyProjectDocs) {
                $this->components->warn(
                    'A '.ConfigService::LEGACY_PROJECT_DOCS_PATH.' folder is still at the project root. Run larapilot:update to move it into '
                    .$config->relativePath($config->projectDocsDirectory()).'/.'
                );
            }

            $healthy
                ? $this->components->info('Larapilot installation is healthy.')
                : $this->components->error('Larapilot installation has problems. Run larapilot:install / boost:install.');

            return $healthy ? self::SUCCESS : self::FAILURE;
        }

        return $this->success('doctor', [
            'healthy' => $healthy,
            'checks' => $checks,
            'dev_docs' => $config->devDocsStatus(),
            'quality' => $qualityStatus,
            'settings_missing_keys' => $missingSettings,
            'legacy_project_docs' => $legacyProjectDocs,
            'project_root' => $config->projectRoot(),
        ]);
    }
}
