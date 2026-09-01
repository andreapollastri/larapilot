<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;

class SettingsSetCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:settings-set
                            {--effort= : Effort mode: ECO, STANDARD, or MAX}
                            {--backlog= : Backlog granularity: LEAN, STANDARD, or GRANULAR}
                            {--git-mode= : Git mode: NO_GITFLOW, GITFLOW, or GITFLOW_PUSH}
                            {--testing= : Testing mode: MINIMAL, NORMAL, or BEST}
                            {--auto-approve= : Auto-approve after implement: YES or NO}
                            {--lucille= : Lucille usage tracking: YES (default) or NO to exclude explicitly}
                            {--decision-log= : Decision journal + regression guard: YES (default) or NO}
                            {--code-history= : Per spec/task code change history: YES or NO (default NO)}
                            {--dashboard-auth= : HTTP Basic Auth on the /larapilot dashboard: YES or NO (default NO)}
                            {--github= : GitHub remote via gh CLI: YES or NO (default NO)}
                            {--gitlab= : GitLab remote via glab CLI: YES or NO (default NO)}
                            {--bitbucket= : Bitbucket Cloud remote via API tokens: YES or NO (default NO)}
                            {--azure= : Azure DevOps remote via az CLI / PAT: YES or NO (default NO)}
                            {--notifications= : Master chat notifications switch: YES or NO (default NO)}
                            {--notify-slack= : Slack notifications: YES or NO (default NO)}
                            {--notify-discord= : Discord notifications: YES or NO (default NO)}
                            {--notify-telegram= : Telegram notifications: YES or NO (default NO)}';

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

        $backlog = $this->normalizeOption('backlog');
        if ($backlog !== null) {
            if (! in_array($backlog, $config->allowedBacklogModes(), true)) {
                return $this->failure(
                    'E_INVALID_INPUT',
                    "Invalid --backlog value: {$backlog}.",
                    $this->exitForCode('E_INVALID_INPUT'),
                    'Allowed: '.implode(', ', $config->allowedBacklogModes()).'.'
                );
            }
            $partial['backlog'] = $backlog;
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

        foreach ([
            'auto-approve' => ['auto_approve', $config->allowedAutoApproveModes()],
            'lucille' => ['lucille', $config->allowedLucilleModes()],
            'decision-log' => ['decision_log', $config->allowedDecisionLogModes()],
            'code-history' => ['code_history', $config->allowedCodeHistoryModes()],
            'dashboard-auth' => ['dashboard_auth', $config->allowedDashboardAuthModes()],
            'github' => ['github', $config->allowedGithubModes()],
            'gitlab' => ['gitlab', $config->allowedGitlabModes()],
            'bitbucket' => ['bitbucket', $config->allowedBitbucketModes()],
            'azure' => ['azure', $config->allowedAzureModes()],
            'notifications' => ['notifications', $config->allowedNotificationsModes()],
            'notify-slack' => ['notify_slack', $config->allowedNotifyChannelModes()],
            'notify-discord' => ['notify_discord', $config->allowedNotifyChannelModes()],
            'notify-telegram' => ['notify_telegram', $config->allowedNotifyChannelModes()],
        ] as $option => [$key, $allowed]) {
            $value = $this->normalizeOption($option);
            if ($value === null) {
                continue;
            }

            if (! in_array($value, $allowed, true)) {
                return $this->failure(
                    'E_INVALID_INPUT',
                    "Invalid --{$option} value: {$value}.",
                    $this->exitForCode('E_INVALID_INPUT'),
                    'Allowed: '.implode(', ', $allowed).'.'
                );
            }

            $partial[$key] = $value;
        }

        if ($partial === []) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Provide at least one of --effort, --backlog, --git-mode, --testing, --auto-approve, --lucille, --decision-log, --code-history, --dashboard-auth, --github, --gitlab, --bitbucket, --azure, --notifications, --notify-slack, --notify-discord, or --notify-telegram.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        $lucilleDisabledByEco = ($partial['effort'] ?? null) === 'ECO'
            && ! array_key_exists('lucille', $partial);

        $settings = $config->updateSettings($partial);

        $updated = array_keys($partial);
        if ($lucilleDisabledByEco && ! in_array('lucille', $updated, true)) {
            $updated[] = 'lucille';
        }

        return $this->success('settings', [
            'settings' => $settings,
            'updated' => $updated,
            'lucille_disabled_by_eco' => $lucilleDisabledByEco,
            'config_path' => $config->configPath(),
            'hint' => $lucilleDisabledByEco
                ? 'ECO disables Lucille automatically. Re-enable with: php artisan larapilot:settings-set --lucille=YES'
                : null,
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
            'ACTIVE' => 'YES',
            'ENABLED' => 'YES',
            'FALSE' => 'NO',
            'OFF' => 'NO',
            '0' => 'NO',
            'EXCLUDE' => 'NO',
            'EXCLUDED' => 'NO',
            'DISABLED' => 'NO',
        ];

        return $aliases[$normalized] ?? $normalized;
    }
}
