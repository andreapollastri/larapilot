<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ChoicesService;
use Larapilot\Support\LarapilotCommand;
use Symfony\Component\Yaml\Yaml;

class ChoicesSetCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:choices-set
                            {--from-prd : Scrape key fields from the PRD into choices.yaml}
                            {--file= : YAML file of choices to merge}
                            {--project-kind=}
                            {--website-type=}
                            {--package-origin=}
                            {--package-path=}
                            {--package-git=}
                            {--delivery-target=}
                            {--budget-sensitivity=}
                            {--frontend-topology=}
                            {--data-store=}
                            {--hierarchy=}
                            {--search=}
                            {--cli-tooling=}
                            {--deadlines=}
                            {--admin-panel=}
                            {--local-dev=}
                            {--deploy-platform=}';

    protected $description = 'Persist inception/settings choices for the Larapilot dashboard';

    public function handle(ChoicesService $choices): int
    {
        if ((bool) $this->option('from-prd')) {
            $payload = $choices->syncFromPrd();

            return $this->success('choices', [
                'choices' => $payload,
                'path' => $choices->path(),
            ]);
        }

        $merge = [];

        if ($file = $this->option('file')) {
            if (! is_file((string) $file)) {
                return $this->failure(
                    'E_NOT_FOUND',
                    "Choices file not found: {$file}",
                    $this->exitForCode('E_NOT_FOUND')
                );
            }

            $parsed = Yaml::parseFile((string) $file);

            if (! is_array($parsed)) {
                return $this->failure(
                    'E_INVALID_INPUT',
                    'Choices file must contain a YAML mapping.',
                    $this->exitForCode('E_INVALID_INPUT')
                );
            }

            $merge = $parsed;
        }

        $map = [
            'project_kind' => 'project-kind',
            'website_type' => 'website-type',
            'package_origin' => 'package-origin',
            'package_path' => 'package-path',
            'package_git' => 'package-git',
            'delivery_target' => 'delivery-target',
            'budget_sensitivity' => 'budget-sensitivity',
            'frontend_topology' => 'frontend-topology',
            'data_store' => 'data-store',
            'hierarchy_pattern' => 'hierarchy',
            'search' => 'search',
            'cli_tooling' => 'cli-tooling',
            'deadlines' => 'deadlines',
            'admin_panel' => 'admin-panel',
            'local_dev' => 'local-dev',
            'deploy_platform' => 'deploy-platform',
        ];

        foreach ($map as $key => $option) {
            $value = $this->option($option);

            if (is_string($value) && trim($value) !== '') {
                $merge[$key] = trim($value);
            }
        }

        if ($merge === []) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Provide --from-prd, --file=, or at least one choice flag.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        $payload = $choices->write($merge);

        return $this->success('choices', [
            'choices' => $payload,
            'path' => $choices->path(),
        ]);
    }
}
