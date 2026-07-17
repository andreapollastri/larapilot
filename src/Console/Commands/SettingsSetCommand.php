<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;

class SettingsSetCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:settings-set
                            {--effort= : Effort mode: ECO, STANDARD, or MAX}
                            {--git-mode= : Git mode: NO_GITFLOW, GITFLOW, or GITFLOW_PUSH}
                            {--testing= : Testing mode: MINIMAL, NORMAL, or BEST}
                            {--auto-approve= : Auto-approve after implement: YES or NO}';

    protected $description = 'Persist Larapilot project settings into .larapilot/config.yaml';

    public function handle(ConfigService $config): int
    {
        $partial = [];

        $effort = $this->normalizeOption('effort');
        if ($effort !== null) {
            if (! in_array($effort, $config->allowedEfforts(), true)) {
                return $this->failure(
                    'E_INVALID_INPUT',
                    "Invalid --effort value: {$effort}.",
                    $this->exitForCode('E_INVALID_INPUT'),
                    'Allowed: '.implode(', ', $config->allowedEfforts()).'.'
                );
            }
            $partial['effort'] = $effort;
        }

        $gitMode = $this->normalizeOption('git-mode');
        if ($gitMode !== null) {
            if (! in_array($gitMode, $config->allowedGitModes(), true)) {
                return $this->failure(
                    'E_INVALID_INPUT',
                    "Invalid --git-mode value: {$gitMode}.",
                    $this->exitForCode('E_INVALID_INPUT'),
                    'Allowed: '.implode(', ', $config->allowedGitModes()).'.'
                );
            }
            $partial['git_mode'] = $gitMode;
        }

        $testing = $this->normalizeOption('testing');
        if ($testing !== null) {
            if (! in_array($testing, $config->allowedTestingModes(), true)) {
                return $this->failure(
                    'E_INVALID_INPUT',
                    "Invalid --testing value: {$testing}.",
                    $this->exitForCode('E_INVALID_INPUT'),
                    'Allowed: '.implode(', ', $config->allowedTestingModes()).'.'
                );
            }
            $partial['testing'] = $testing;
        }

        $autoApprove = $this->normalizeOption('auto-approve');
        if ($autoApprove !== null) {
            if (! in_array($autoApprove, $config->allowedAutoApproveModes(), true)) {
                return $this->failure(
                    'E_INVALID_INPUT',
                    "Invalid --auto-approve value: {$autoApprove}.",
                    $this->exitForCode('E_INVALID_INPUT'),
                    'Allowed: '.implode(', ', $config->allowedAutoApproveModes()).'.'
                );
            }
            $partial['auto_approve'] = $autoApprove;
        }

        if ($partial === []) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Provide at least one of --effort, --git-mode, --testing, or --auto-approve.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        $settings = $config->updateSettings($partial);

        return $this->success('settings', [
            'settings' => $settings,
            'updated' => array_keys($partial),
            'config_path' => $config->configPath(),
        ]);
    }

    protected function normalizeOption(string $name): ?string
    {
        $raw = $this->option($name);

        if ($raw === null || $raw === false || $raw === '') {
            return null;
        }

        $normalized = strtoupper(trim((string) $raw));
        $normalized = str_replace(['+', '/', ' '], ['_', '_', '_'], $normalized);
        $normalized = (string) preg_replace('/_+/', '_', $normalized);
        $normalized = trim($normalized, '_');

        $aliases = [
            'NOGITFLOW' => 'NO_GITFLOW',
            'GITFLOW_PUSH' => 'GITFLOW_PUSH',
            'GITFLOWPUSH' => 'GITFLOW_PUSH',
            'PUSH' => 'GITFLOW_PUSH',
            'SI' => 'YES',
            'TRUE' => 'YES',
            'ON' => 'YES',
            '1' => 'YES',
            'FALSE' => 'NO',
            'OFF' => 'NO',
            '0' => 'NO',
        ];

        return $aliases[$normalized] ?? $normalized;
    }
}
