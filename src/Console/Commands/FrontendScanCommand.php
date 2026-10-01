<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\FrontendService;
use Larapilot\Support\LarapilotCommand;

class FrontendScanCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:frontend-scan
                            {--path= : Scan this absolute path instead of the configured frontend repo}
                            {--project=* : Target project (repeat, or comma-separated) instead of the configured ones}
                            {--full : List every project of the workspace with its targets and dependencies}
                            {--no-cli : Do not run the nx installed in the workspace; read the files only}
                            {--fresh : Rebuild the cached Nx graph}';

    protected $description = 'Scan the external frontend repository: workspace and projects, agent rules, observed conventions, commands, API client';

    public function handle(FrontendService $frontend): int
    {
        $path = $this->option('path');
        $scanPath = is_string($path) && trim($path) !== '' ? trim($path) : null;

        if ($scanPath !== null) {
            $validation = $frontend->validatePath($scanPath);

            if (! $validation['valid']) {
                return $this->failure(
                    'E_INVALID_INPUT',
                    'Invalid frontend repo path.',
                    $this->exitForCode('E_INVALID_INPUT'),
                    implode(' ', $validation['errors'])
                );
            }
        }

        $projects = $this->projects();

        $scan = $frontend->scan(
            $scanPath,
            $projects === [] ? null : $projects,
            ! (bool) $this->option('no-cli'),
            (bool) $this->option('fresh'),
            (bool) $this->option('full'),
        );

        if (($scan['ok'] ?? false) !== true) {
            return $this->failure(
                'E_PRECONDITION',
                (string) ($scan['error'] ?? 'Frontend scan failed.'),
                $this->exitForCode('E_PRECONDITION'),
                is_array($scan['errors'] ?? null) ? implode(' ', $scan['errors']) : null
            );
        }

        return $this->success('frontend-scan', $scan);
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
