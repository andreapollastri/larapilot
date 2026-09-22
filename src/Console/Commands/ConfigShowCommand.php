<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\BackstageService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\Tracker\TrackerLinkStore;
use Larapilot\Services\Tracker\TrackerManager;
use Larapilot\Support\LarapilotCommand;

class ConfigShowCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:config-show
                            {--only= : Comma-separated slices: settings, paths, frontend, tracker, dev_docs, backstage, workflow, personas. Omit for every slice except personas}';

    protected $description = 'Show Larapilot project configuration and metadata';

    /**
     * @var list<string>
     */
    private const SLICES = [
        'settings',
        'paths',
        'frontend',
        'tracker',
        'dev_docs',
        'backstage',
        'workflow',
        'personas',
    ];

    public function handle(
        ConfigService $config,
        BackstageService $backstage,
        TrackerManager $tracker,
        TrackerLinkStore $links,
    ): int {
        $info = $config->setupInfo() + [
            'backstage' => $this->backstageInfo($config, $backstage),
            'tracker' => $this->trackerInfo($config, $tracker, $links),
        ];

        $only = $this->option('only');

        if ($only === null || trim((string) $only) === '') {
            unset($info['personas']);

            return $this->success('setup', $info);
        }

        $requested = array_values(array_filter(array_map(
            static fn (string $slice): string => trim($slice),
            explode(',', (string) $only)
        ), static fn (string $slice): bool => $slice !== ''));

        $unknown = array_values(array_diff($requested, self::SLICES));

        if ($unknown !== []) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Unknown config-show slice: '.implode(', ', $unknown).'.',
                $this->exitForCode('E_INVALID_INPUT'),
                'Slices: '.implode(', ', self::SLICES).'.'
            );
        }

        $data = [
            'project_root' => $info['project_root'],
            'connector' => $info['connector'],
        ];

        foreach ($requested as $slice) {
            $data[$slice] = $info[$slice] ?? null;
        }

        return $this->success('setup', $data);
    }

    /**
     * Tracker wiring without the credentials — `configured` reports whether
     * the secrets are present, never what they are.
     *
     * @return array<string, mixed>
     */
    protected function trackerInfo(ConfigService $config, TrackerManager $tracker, TrackerLinkStore $links): array
    {
        $provider = $tracker->provider();

        return [
            'enabled' => $tracker->enabled(),
            'provider' => $provider,
            'available_providers' => $tracker->available(),
            'configured' => $tracker->ready(),
            'missing_config' => $tracker->missingConfig(),
            'sync_tasks' => $tracker->syncTasks(),
            'pull' => [
                'statuses' => $tracker->pullStatuses(),
                'comments' => $tracker->pullComments(),
            ],
            'status_map' => $tracker->statusMap(),
            'link_file' => $config->relativePath($links->path()),
            'linked_specs' => $provider === null ? 0 : count($links->all($provider)),
        ];
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
