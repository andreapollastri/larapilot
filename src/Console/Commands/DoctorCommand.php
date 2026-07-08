<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\PrdService;
use Larapilot\Services\SpecService;
use Larapilot\Support\LarapilotCommand;
use Laravel\Boost\BoostServiceProvider;

class DoctorCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:doctor';

    protected $description = 'Diagnose Larapilot installation and project setup';

    public function handle(ConfigService $config, PrdService $prd, SpecService $specs): int
    {
        $checks = [
            'config' => $config->hasProjectConfig(),
            'shared_runtime' => is_file(base_path('.larapilot/shared-runtime.md')),
            'backlog' => is_file($specs->backlogPath()),
            'prd' => $prd->exists(),
            'boost' => class_exists(BoostServiceProvider::class),
        ];

        $healthy = $checks['config'] && $checks['shared_runtime'] && $checks['boost'];

        return $this->success('doctor', [
            'healthy' => $healthy,
            'checks' => $checks,
            'project_root' => $config->projectRoot(),
        ]);
    }
}
