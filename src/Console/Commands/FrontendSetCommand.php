<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\Frontend\WorkspaceLocator;
use Larapilot\Services\FrontendService;
use Larapilot\Support\LarapilotCommand;

class FrontendSetCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:frontend-set
                            {--path= : Absolute path to the external frontend repository}
                            {--workspace= : Absolute path to the workspace (monorepo) the repository builds in, when it is neither the repository nor one of its parent folders}
                            {--clear-workspace : Forget the workspace path}
                            {--stack= : Frontend stack label (React, Vue, Angular, Svelte, Next.js, …)}
                            {--project=* : Workspace project of this product in a monorepo (repeat, or comma-separated); replaces the list}
                            {--clear-projects : Forget the target projects}
                            {--mode= : driven (Larapilot writes the frontend) or handoff (the frontend team builds from a brief)}
                            {--skip-check : Keep project names the files of the workspace do not show}';

    protected $description = 'Link the external frontend repository: path (.env), stack, target projects, and mode (.larapilot/config.yaml)';

    public function handle(ConfigService $config, FrontendService $frontend): int
    {
        $path = $this->option('path');
        $stack = $this->option('stack');
        $mode = $this->option('mode');
        $projects = $this->projects();
        $clearProjects = (bool) $this->option('clear-projects');

        $workspace = $this->option('workspace');
        $hasWorkspace = is_string($workspace) && trim($workspace) !== '';
        $clearWorkspace = (bool) $this->option('clear-workspace');
        $hasPath = is_string($path) && trim($path) !== '';
        $hasStack = is_string($stack) && trim($stack) !== '';
        $hasMode = is_string($mode) && trim($mode) !== '';

        if (! $hasPath && ! $hasStack && ! $hasMode && ! $hasWorkspace && ! $clearWorkspace && $projects === [] && ! $clearProjects) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Provide at least one of --path, --workspace, --stack, --project, --clear-projects, --clear-workspace, or --mode.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        if ($hasMode && ! in_array(strtolower(trim((string) $mode)), FrontendService::MODES, true)) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Unknown mode: '.trim((string) $mode).'.',
                $this->exitForCode('E_INVALID_INPUT'),
                'Modes: driven (this workspace writes the frontend), handoff (the frontend team builds from larapilot:frontend-brief).'
            );
        }

        $partial = [];
        $root = $config->frontendRepoPath();

        if ($hasPath) {
            $validation = $frontend->validatePath(trim((string) $path));

            if (! $validation['valid']) {
                return $this->failure(
                    'E_INVALID_INPUT',
                    'Invalid frontend repo path.',
                    $this->exitForCode('E_INVALID_INPUT'),
                    implode(' ', $validation['errors'])
                );
            }

            $partial['repo_path'] = $validation['path'];
            $root = $validation['path'];
        }

        $workspacePath = $config->frontendWorkspacePath();

        if ($hasWorkspace) {
            $validation = $frontend->validatePath(trim((string) $workspace));

            if (! $validation['valid'] || ! WorkspaceLocator::isWorkspace($validation['path'], false)) {
                return $this->failure(
                    'E_INVALID_INPUT',
                    'Invalid frontend workspace path.',
                    $this->exitForCode('E_INVALID_INPUT'),
                    $validation['valid'] ? 'The folder holds no workspace: no nx.json, angular.json, pnpm-workspace.yaml, rush.json, lerna.json, or package.json workspaces.' : implode(' ', $validation['errors'])
                );
            }

            $partial['workspace_path'] = $validation['path'];
            $workspacePath = $validation['path'];
        } elseif ($clearWorkspace) {
            $partial['workspace_path'] = null;
            $workspacePath = null;
        }

        $workspaceKind = null;

        if ($projects !== []) {
            if (! is_string($root) || ! is_dir($root)) {
                return $this->failure(
                    'E_PRECONDITION',
                    'Link the frontend repository before naming its projects.',
                    $this->exitForCode('E_PRECONDITION'),
                    'Run larapilot:frontend-set --path=/absolute/path --project=<name>.'
                );
            }

            $known = $frontend->projectsOf($root, $workspacePath);
            $workspaceKind = $known['kind'];
            $resolved = [];
            $unknown = [];

            foreach ($projects as $given) {
                $name = FrontendService::matchProject($given, $known['projects']);

                if ($name !== null) {
                    $resolved[] = $name;
                } elseif ($this->option('skip-check')) {
                    $resolved[] = $given;
                } else {
                    $unknown[] = $given;
                }
            }

            if ($unknown !== []) {
                $applications = array_keys(array_filter($known['projects'], static fn (array $project): bool => $project['type'] === 'application'));

                return $this->failure(
                    'E_INVALID_INPUT',
                    'Unknown project: '.implode(', ', $unknown).'.',
                    $this->exitForCode('E_INVALID_INPUT'),
                    'Applications in this '.$known['kind'].' workspace: '.(implode(', ', array_slice($applications, 0, 30)) ?: 'none').'. A project inferred only by an Nx plugin can be kept with --skip-check.',
                    ['projects' => array_slice(array_keys($known['projects']), 0, 100)]
                );
            }

            $partial['projects'] = array_values(array_unique($resolved));
        } elseif ($clearProjects) {
            $partial['projects'] = [];
        }

        if ($hasStack) {
            $partial['stack'] = trim((string) $stack);
        }

        if ($hasMode) {
            $partial['mode'] = strtolower(trim((string) $mode));
        }

        $saved = $config->updateFrontend($partial);

        return $this->success('frontend-set', [
            'frontend' => $saved,
            'updated' => array_keys($partial),
            'workspace' => $workspaceKind,
            'config_path' => $config->configPath(),
            'env_key' => array_key_exists('repo_path', $partial) ? 'LARAPILOT_FRONTEND_REPO_PATH' : (array_key_exists('workspace_path', $partial) ? 'LARAPILOT_FRONTEND_WORKSPACE_PATH' : null),
            'hint' => array_key_exists('repo_path', $partial)
                ? 'Frontend repo path is stored in LARAPILOT_FRONTEND_REPO_PATH (.env), not in config.yaml. Run larapilot:frontend-scan next.'
                : null,
        ]);
    }

    /**
     * @return list<string>
     */
    protected function projects(): array
    {
        $projects = [];

        foreach ((array) $this->option('project') as $value) {
            foreach (explode(',', (string) $value) as $name) {
                if (trim($name) !== '') {
                    $projects[] = trim($name);
                }
            }
        }

        return array_values(array_unique($projects));
    }
}
