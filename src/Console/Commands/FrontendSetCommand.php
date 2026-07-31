<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\FrontendService;
use Larapilot\Support\LarapilotCommand;

class FrontendSetCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:frontend-set
                            {--path= : Absolute path to the external frontend repository}
                            {--stack= : Frontend stack label (React, Vue, Angular, Svelte, Next.js, …)}';

    protected $description = 'Persist the external frontend repository path and stack into .larapilot/config.yaml';

    public function handle(ConfigService $config, FrontendService $frontend): int
    {
        $path = $this->option('path');
        $stack = $this->option('stack');

        if (($path === null || $path === false || $path === '') && ($stack === null || $stack === false || $stack === '')) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Provide at least one of --path or --stack.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        $partial = [];

        if (is_string($path) && trim($path) !== '') {
            $validation = $frontend->validatePath(trim($path));

            if (! $validation['valid']) {
                return $this->failure(
                    'E_INVALID_INPUT',
                    'Invalid frontend repo path.',
                    $this->exitForCode('E_INVALID_INPUT'),
                    implode(' ', $validation['errors'])
                );
            }

            $partial['repo_path'] = $validation['path'];
        }

        if (is_string($stack) && trim($stack) !== '') {
            $partial['stack'] = trim($stack);
        }

        $saved = $config->updateFrontend($partial);

        return $this->success('frontend-set', [
            'frontend' => $saved,
            'updated' => array_keys($partial),
            'config_path' => $config->configPath(),
        ]);
    }
}
