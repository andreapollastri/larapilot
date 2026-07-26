<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\BackstageService;
use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;

class ConfigShowCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:config-show';

    protected $description = 'Show Larapilot project configuration and metadata';

    public function handle(ConfigService $config, BackstageService $backstage): int
    {
        return $this->success('setup', $config->setupInfo() + [
            'backstage' => $this->backstageInfo($config, $backstage),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function backstageInfo(ConfigService $config, BackstageService $backstage): array
    {
        $catalogPath = $backstage->catalogPath();
        $mkdocsPath = $backstage->mkdocsPath();

        return [
            'enabled' => $backstage->enabled(),
            'entity_ref' => $backstage->entityRef(),
            'title' => $backstage->title(),
            'owner' => $backstage->owner(),
            'system' => $backstage->system(),
            'lifecycle' => $backstage->lifecycle(),
            'component_type' => $backstage->componentType(),
            'workflow_api' => $backstage->workflowApiEnabled(),
            'techdocs' => [
                'enabled' => $backstage->techdocsEnabled(),
                'docs_dir' => $backstage->techdocsDir(),
                'mkdocs_path' => $config->relativePath($mkdocsPath),
                'mkdocs_exists' => is_file($mkdocsPath),
            ],
            'catalog_path' => $config->relativePath($catalogPath),
            'catalog_exists' => is_file($catalogPath),
        ];
    }
}
