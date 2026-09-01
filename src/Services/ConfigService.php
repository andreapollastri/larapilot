<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Support\Arr;
use Larapilot\Support\AtomicFile;
use Symfony\Component\Yaml\Yaml;

class ConfigService
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $resolved = null;

    public function projectRoot(): string
    {
        return base_path();
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(): array
    {
        return $this->resolved ??= $this->resolveFresh();
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveFresh(): array
    {
        $configPath = $this->configPath();

        if (! is_file($configPath)) {
            return $this->defaults();
        }

        $parsed = Yaml::parseFile($configPath);

        if (! is_array($parsed)) {
            return $this->defaults();
        }

        return array_replace_recursive($this->defaults(), $parsed);
    }

    public function configPath(): string
    {
        return base_path('.larapilot/config.yaml');
    }

    public function hasProjectConfig(): bool
    {
        return is_file($this->configPath());
    }

    /**
     * @return array<string, mixed>
     */
    public function setupInfo(): array
    {
        $config = $this->resolve();

        return [
            'project_root' => $this->projectRoot(),
            'connector' => $config['connector'] ?? 'file',
            'paths' => [
                'prd' => $this->absolutePath($config['paths']['prd'] ?? '.larapilot/docs/PRD.md'),
                'mockups' => $this->absolutePath($config['paths']['mockups'] ?? '.larapilot/mockups/'),
                'test_results' => $this->absolutePath($config['paths']['test_results'] ?? '.larapilot/docs/test-results/'),
                'review' => $this->absolutePath($config['paths']['review'] ?? '.larapilot/docs/review/'),
                'security' => $this->absolutePath($config['paths']['security'] ?? '.larapilot/docs/security/'),
                'launch' => $this->absolutePath($config['paths']['launch'] ?? '.larapilot/docs/launch/'),
                'support' => $this->absolutePath($config['paths']['support'] ?? '.larapilot/docs/support/'),
                'client_materials' => $this->absolutePath($config['paths']['client_materials'] ?? '.larapilot/client-materials/'),
                'legacy' => $this->absolutePath($config['paths']['legacy'] ?? '.larapilot/legacy/'),
                'research' => $this->absolutePath($config['paths']['research'] ?? '.larapilot/research/'),
                'design_systems' => $this->absolutePath($config['paths']['design_systems'] ?? '.larapilot/design-systems/'),
                'internal_feedback' => $this->absolutePath($config['paths']['internal_feedback'] ?? '.larapilot/internal-feedback/'),
                'usage' => $this->absolutePath($config['paths']['usage'] ?? '.larapilot/usage/'),
                'choices' => $this->absolutePath($config['paths']['choices'] ?? '.larapilot/choices.yaml'),
                'schedule' => $this->absolutePath($config['paths']['schedule'] ?? '.larapilot/usage/schedule.yaml'),
                'decisions' => $this->absolutePath($config['paths']['decisions'] ?? '.larapilot/decisions.yaml'),
                'code_history' => $this->absolutePath($config['paths']['code_history'] ?? '.larapilot/code-history.yaml'),
                'backlog' => $this->absolutePath($config['file']['backlog'] ?? '.larapilot/backlog.yaml'),
                'planning' => $this->absolutePath($config['file']['planning'] ?? '.larapilot/plans/'),
            ],
            'workflow' => $config['workflow'] ?? config('larapilot.workflow'),
            'settings' => $this->settings(),
            'frontend' => $this->frontend(),
            'personas' => config('larapilot.personas'),
        ];
    }

    /**
     * External frontend repository wiring for split-repo topology.
     *
     * @return array{repo_path: string|null, stack: string|null, configured: bool}
     */
    public function frontend(): array
    {
        $config = $this->resolve();
        $raw = is_array($config['frontend'] ?? null) ? $config['frontend'] : [];
        $defaults = $this->defaultFrontend();
        $merged = array_replace($defaults, array_intersect_key($raw, $defaults));

        $repoPath = is_string($merged['repo_path'] ?? null) && trim($merged['repo_path']) !== ''
            ? rtrim(trim($merged['repo_path']), '/\\')
            : null;

        $stack = is_string($merged['stack'] ?? null) && trim($merged['stack']) !== ''
            ? trim($merged['stack'])
            : null;

        return [
            'repo_path' => $repoPath,
            'stack' => $stack,
            'configured' => is_string($repoPath) && is_dir($repoPath),
        ];
    }

    public function frontendRepoPath(): ?string
    {
        return $this->frontend()['repo_path'];
    }

    public function hasFrontendRepo(): bool
    {
        return $this->frontend()['configured'];
    }

    /**
     * @return array{repo_path: string|null, stack: string|null}
     */
    public function defaultFrontend(): array
    {
        $defaults = config('larapilot.frontend', []);

        return [
            'repo_path' => is_string($defaults['repo_path'] ?? null) && $defaults['repo_path'] !== ''
                ? $defaults['repo_path']
                : null,
            'stack' => is_string($defaults['stack'] ?? null) && $defaults['stack'] !== ''
                ? $defaults['stack']
                : null,
        ];
    }

    /**
     * Persist external frontend repo settings into `.larapilot/config.yaml`.
     *
     * @param  array<string, string|null>  $partial
     * @return array{repo_path: string|null, stack: string|null, configured: bool}
     */
    public function updateFrontend(array $partial): array
    {
        $path = $this->configPath();

        if (! is_file($path)) {
            $this->writeProjectConfig();
        }

        $parsed = Yaml::parseFile($path);
        $existing = is_array($parsed) ? $parsed : [];

        $current = is_array($existing['frontend'] ?? null) ? $existing['frontend'] : [];
        $frontend = array_replace($this->defaultFrontend(), array_intersect_key($current, $this->defaultFrontend()));

        foreach ($partial as $key => $value) {
            if (! array_key_exists($key, $this->defaultFrontend())) {
                continue;
            }

            if ($value === null || $value === '') {
                $frontend[$key] = null;

                continue;
            }

            $frontend[$key] = $key === 'repo_path'
                ? rtrim(trim((string) $value), '/\\')
                : trim((string) $value);
        }

        $existing['frontend'] = $frontend;

        AtomicFile::write(
            $path,
            Yaml::dump($existing, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
        );

        $this->resolved = null;

        return $this->frontend();
    }

    /**
     * Agent-facing settings. Boolean settings are always the string YES|NO
     * (YAML stores booleans — YES/NO are YAML 1.1 bool tokens).
     *
     * @return array{
     *     effort: string,
     *     backlog: string,
     *     git_mode: string,
     *     testing: string,
     *     auto_approve: string,
     *     lucille: string,
     *     decision_log: string,
     *     code_history: string,
     *     dashboard_auth: string,
     *     api_auth: string,
     *     security_scan: string,
     *     github: string,
     *     gitlab: string,
     *     bitbucket: string,
     *     azure: string,
     *     notifications: string,
     *     notify_slack: string,
     *     notify_discord: string,
     *     notify_telegram: string
     * }
     */
    public function settings(): array
    {
        $config = $this->resolve();
        $raw = is_array($config['settings'] ?? null) ? $config['settings'] : [];
        $merged = array_replace($this->defaultSettings(), array_intersect_key($raw, $this->defaultSettings()));
        $boolDefaults = $this->booleanSettingDefaults();

        $settings = [
            'effort' => (string) $merged['effort'],
            'backlog' => (string) $merged['backlog'],
            'git_mode' => (string) $merged['git_mode'],
            'testing' => (string) $merged['testing'],
        ];

        foreach ($boolDefaults as $key => $default) {
            $settings[$key] = $this->normalizeYesNo($merged[$key] ?? $default, $default);
        }

        return $settings;
    }

    /**
     * @return array{
     *     effort: string,
     *     backlog: string,
     *     git_mode: string,
     *     testing: string,
     *     auto_approve: bool,
     *     lucille: bool,
     *     decision_log: bool,
     *     code_history: bool,
     *     dashboard_auth: bool,
     *     api_auth: bool,
     *     security_scan: bool,
     *     github: bool,
     *     gitlab: bool,
     *     bitbucket: bool,
     *     azure: bool,
     *     notifications: bool,
     *     notify_slack: bool,
     *     notify_discord: bool,
     *     notify_telegram: bool
     * }
     */
    public function defaultSettings(): array
    {
        $defaults = config('larapilot.settings', []);
        $boolDefaults = $this->booleanSettingDefaults();

        $settings = [
            'effort' => (string) ($defaults['effort'] ?? 'STANDARD'),
            'backlog' => (string) ($defaults['backlog'] ?? 'STANDARD'),
            'git_mode' => (string) ($defaults['git_mode'] ?? 'GITFLOW'),
            'testing' => (string) ($defaults['testing'] ?? 'NORMAL'),
        ];

        foreach ($boolDefaults as $key => $default) {
            $settings[$key] = $this->yesNoToBool($defaults[$key] ?? $default, $default);
        }

        return $settings;
    }

    /**
     * Boolean settings and their default when unset / empty.
     *
     * @return array<string, bool>
     */
    public function booleanSettingDefaults(): array
    {
        return [
            'auto_approve' => false,
            'lucille' => true,
            'decision_log' => true,
            'code_history' => false,
            'dashboard_auth' => false,
            'api_auth' => false,
            'security_scan' => false,
            'github' => false,
            'gitlab' => false,
            'bitbucket' => false,
            'azure' => false,
            'notifications' => false,
            'notify_slack' => false,
            'notify_discord' => false,
            'notify_telegram' => false,
        ];
    }

    /**
     * Persist one or more project settings into `.larapilot/config.yaml` without
     * wiping unrelated top-level keys the user may have added.
     *
     * @param  array<string, string|bool>  $partial
     * @return array{
     *     effort: string,
     *     backlog: string,
     *     git_mode: string,
     *     testing: string,
     *     auto_approve: string,
     *     lucille: string,
     *     decision_log: string,
     *     code_history: string,
     *     dashboard_auth: string,
     *     api_auth: string,
     *     security_scan: string,
     *     github: string,
     *     gitlab: string,
     *     bitbucket: string,
     *     azure: string,
     *     notifications: string,
     *     notify_slack: string,
     *     notify_discord: string,
     *     notify_telegram: string
     * }
     */
    public function updateSettings(array $partial): array
    {
        $path = $this->configPath();

        if (! is_file($path)) {
            $this->writeProjectConfig();
        }

        $parsed = Yaml::parseFile($path);
        $existing = is_array($parsed) ? $parsed : [];

        $current = is_array($existing['settings'] ?? null) ? $existing['settings'] : [];
        $settings = array_replace($this->defaultSettings(), array_intersect_key($current, $this->defaultSettings()));
        $boolDefaults = $this->booleanSettingDefaults();

        foreach ($boolDefaults as $key => $default) {
            $settings[$key] = $this->yesNoToBool($settings[$key] ?? $default, $default);
        }

        // Selecting ECO turns Lucille off unless the caller also sets lucille
        // explicitly (re-enable with settings-set --lucille=YES while staying on ECO).
        if (($partial['effort'] ?? null) === 'ECO' && ! array_key_exists('lucille', $partial)) {
            $partial['lucille'] = false;
        }

        foreach ($partial as $key => $value) {
            if (! array_key_exists($key, $this->defaultSettings())) {
                continue;
            }

            if (array_key_exists($key, $boolDefaults)) {
                $settings[$key] = $this->yesNoToBool($value, $boolDefaults[$key]);

                continue;
            }

            $settings[$key] = $value;
        }

        $existing['settings'] = $settings;

        AtomicFile::write(
            $path,
            Yaml::dump($existing, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
        );

        $this->resolved = null;

        return $this->settings();
    }

    public function autoApproveEnabled(): bool
    {
        return $this->settings()['auto_approve'] === 'YES';
    }

    /**
     * Lucille · Project tracking is ON by default at every skill level. Only an explicit
     * `settings.lucille: false` / `NO` excludes her.
     */
    public function lucilleEnabled(): bool
    {
        return $this->settings()['lucille'] === 'YES';
    }

    /**
     * Decision journal + regression guard (`.larapilot/decisions.yaml`) — ON by default.
     * Only an explicit `settings.decision_log: false` / `NO` disables it.
     */
    public function decisionLogEnabled(): bool
    {
        return $this->settings()['decision_log'] === 'YES';
    }

    /**
     * Per spec/task code change history (`.larapilot/code-history.yaml`) — OFF by default.
     */
    public function codeHistoryEnabled(): bool
    {
        return $this->settings()['code_history'] === 'YES';
    }

    /**
     * Optional HTTP Basic Auth on the dashboard UI — OFF by default. Never
     * gates the JSON API (`larapilot.api.token`) or the MCP server.
     */
    public function dashboardAuthEnabled(): bool
    {
        return $this->settings()['dashboard_auth'] === 'YES';
    }

    /**
     * Require `LARAPILOT_API_TOKEN` on every `/larapilot/api/*` request — OFF by
     * default. When ON the JSON API fails closed (HTTP 503) until the token env
     * var is configured. Never affects the dashboard UI or the MCP server.
     */
    public function apiAuthEnabled(): bool
    {
        return $this->settings()['api_auth'] === 'YES';
    }

    /**
     * Fold a static security scan (andreapollastri/checkpoint) into
     * `/larapilot-review` and the pre-ship gate — OFF by default. When ON the
     * review skill runs `php artisan checkpoint:scan` if the package is present
     * and treats FAIL findings as review blockers; when the package is missing
     * it reminds the user to `composer require --dev andreapollastri/checkpoint`.
     */
    public function securityScanEnabled(): bool
    {
        return $this->settings()['security_scan'] === 'YES';
    }

    /**
     * Optional GitHub remote integration via `gh` — OFF by default.
     */
    public function githubEnabled(): bool
    {
        return $this->settings()['github'] === 'YES';
    }

    /**
     * Optional GitLab remote integration via `glab` — OFF by default.
     */
    public function gitlabEnabled(): bool
    {
        return $this->settings()['gitlab'] === 'YES';
    }

    /**
     * Optional Bitbucket remote integration — OFF by default.
     */
    public function bitbucketEnabled(): bool
    {
        return $this->settings()['bitbucket'] === 'YES';
    }

    /**
     * Optional Azure DevOps (Azure Repos) remote integration — OFF by default.
     */
    public function azureEnabled(): bool
    {
        return $this->settings()['azure'] === 'YES';
    }

    /**
     * Master switch for chat notifications — OFF by default.
     */
    public function notificationsEnabled(): bool
    {
        return $this->settings()['notifications'] === 'YES';
    }

    public function notifySlackEnabled(): bool
    {
        return $this->notificationsEnabled() && $this->settings()['notify_slack'] === 'YES';
    }

    public function notifyDiscordEnabled(): bool
    {
        return $this->notificationsEnabled() && $this->settings()['notify_discord'] === 'YES';
    }

    public function notifyTelegramEnabled(): bool
    {
        return $this->notificationsEnabled() && $this->settings()['notify_telegram'] === 'YES';
    }

    /**
     * Settings keys that exist in the package defaults but are missing from
     * the on-disk `.larapilot/config.yaml` — typically after a package
     * upgrade introduced a new setting. Runtime falls back to defaults, but
     * doctor/update surface the drift so the file can be refreshed.
     *
     * @return list<string>
     */
    public function missingSettingKeys(): array
    {
        if (! $this->hasProjectConfig()) {
            return [];
        }

        $parsed = Yaml::parseFile($this->configPath());
        $settings = is_array($parsed) && is_array($parsed['settings'] ?? null) ? $parsed['settings'] : [];

        return array_values(array_diff(
            array_keys($this->defaultSettings()),
            array_keys($settings)
        ));
    }

    /**
     * Whether every persisted setting value is within its allowed list.
     */
    public function settingsValid(): bool
    {
        $settings = $this->settings();

        $ok = in_array($settings['effort'], $this->allowedEfforts(), true)
            && in_array($settings['backlog'], $this->allowedBacklogModes(), true)
            && in_array($settings['git_mode'], $this->allowedGitModes(), true)
            && in_array($settings['testing'], $this->allowedTestingModes(), true);

        foreach (array_keys($this->booleanSettingDefaults()) as $key) {
            if (! in_array($settings[$key] ?? null, $this->allowedYesNoModes(), true)) {
                return false;
            }
        }

        return $ok;
    }

    /**
     * @param  bool  $defaultWhenEmpty  Fallback when the value is empty/unknown
     */
    protected function normalizeYesNo(mixed $value, bool $defaultWhenEmpty): string
    {
        return $this->yesNoToBool($value, $defaultWhenEmpty) ? 'YES' : 'NO';
    }

    /**
     * @param  bool  $defaultWhenEmpty  Fallback when the value is empty/unknown
     */
    protected function yesNoToBool(mixed $value, bool $defaultWhenEmpty): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtoupper(trim((string) $value));

        if ($normalized === '') {
            return $defaultWhenEmpty;
        }

        if (in_array($normalized, ['YES', 'SI', 'TRUE', 'ON', '1', 'ACTIVE', 'ENABLED'], true)) {
            return true;
        }

        if (in_array($normalized, ['NO', 'FALSE', 'OFF', '0', 'EXCLUDED', 'EXCLUDE', 'DISABLED'], true)) {
            return false;
        }

        return $defaultWhenEmpty;
    }

    /** @deprecated Use yesNoToBool() */
    protected function autoApproveToBool(mixed $value): bool
    {
        return $this->yesNoToBool($value, false);
    }

    /**
     * @return list<string>
     */
    public function allowedEfforts(): array
    {
        return ['ECO', 'STANDARD', 'MAX'];
    }

    /**
     * @return list<string>
     */
    public function allowedBacklogModes(): array
    {
        return ['LEAN', 'STANDARD', 'GRANULAR'];
    }

    /**
     * @return list<string>
     */
    public function allowedGitModes(): array
    {
        return ['NO_GITFLOW', 'GITFLOW', 'GITFLOW_PUSH'];
    }

    /**
     * @return list<string>
     */
    public function allowedTestingModes(): array
    {
        return ['MINIMAL', 'NORMAL', 'BEST'];
    }

    /**
     * @return list<string>
     */
    public function allowedYesNoModes(): array
    {
        return ['YES', 'NO'];
    }

    /**
     * @return list<string>
     */
    public function allowedAutoApproveModes(): array
    {
        return $this->allowedYesNoModes();
    }

    /**
     * @return list<string>
     */
    public function allowedLucilleModes(): array
    {
        return $this->allowedYesNoModes();
    }

    /**
     * @return list<string>
     */
    public function allowedDecisionLogModes(): array
    {
        return $this->allowedYesNoModes();
    }

    /**
     * @return list<string>
     */
    public function allowedCodeHistoryModes(): array
    {
        return $this->allowedYesNoModes();
    }

    /**
     * @return list<string>
     */
    public function allowedDashboardAuthModes(): array
    {
        return $this->allowedYesNoModes();
    }

    /**
     * @return list<string>
     */
    public function allowedApiAuthModes(): array
    {
        return $this->allowedYesNoModes();
    }

    /**
     * @return list<string>
     */
    public function allowedSecurityScanModes(): array
    {
        return $this->allowedYesNoModes();
    }

    /**
     * @return list<string>
     */
    public function allowedGithubModes(): array
    {
        return $this->allowedYesNoModes();
    }

    /**
     * @return list<string>
     */
    public function allowedGitlabModes(): array
    {
        return $this->allowedYesNoModes();
    }

    /**
     * @return list<string>
     */
    public function allowedBitbucketModes(): array
    {
        return $this->allowedYesNoModes();
    }

    /**
     * @return list<string>
     */
    public function allowedAzureModes(): array
    {
        return $this->allowedYesNoModes();
    }

    /**
     * @return list<string>
     */
    public function allowedNotificationsModes(): array
    {
        return $this->allowedYesNoModes();
    }

    /**
     * @return list<string>
     */
    public function allowedNotifyChannelModes(): array
    {
        return $this->allowedYesNoModes();
    }

    public function absolutePath(string $relative): string
    {
        if (str_starts_with($relative, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $relative) === 1) {
            return $relative;
        }

        return rtrim($this->projectRoot(), '/').'/'.ltrim($relative, '/');
    }

    public function relativePath(string $absolute): string
    {
        $root = rtrim($this->projectRoot(), '/').'/';

        return str_starts_with($absolute, $root)
            ? substr($absolute, strlen($root))
            : $absolute;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'connector' => config('larapilot.connector', 'file'),
            'settings' => $this->defaultSettings(),
            'frontend' => $this->defaultFrontend(),
            'paths' => config('larapilot.paths'),
            'workflow' => config('larapilot.workflow'),
            'file' => config('larapilot.file'),
        ];
    }

    /**
     * @return list<string> Absolute workspace directory paths created on install.
     */
    public function workspaceDirectoryPaths(): array
    {
        $config = $this->resolve();

        return array_values(array_unique([
            dirname($this->configPath()),
            dirname($this->absolutePath($config['file']['backlog'] ?? '.larapilot/backlog.yaml')),
            $this->absolutePath($config['file']['specs'] ?? '.larapilot/specs/'),
            $this->absolutePath($config['file']['planning'] ?? '.larapilot/plans/'),
            $this->absolutePath($config['paths']['mockups'] ?? '.larapilot/mockups/'),
            $this->absolutePath($config['paths']['test_results'] ?? '.larapilot/docs/test-results/'),
            $this->absolutePath($config['paths']['review'] ?? '.larapilot/docs/review/'),
            $this->absolutePath($config['paths']['security'] ?? '.larapilot/docs/security/'),
            $this->absolutePath($config['paths']['launch'] ?? '.larapilot/docs/launch/'),
            $this->absolutePath($config['paths']['support'] ?? '.larapilot/docs/support/'),
            $this->absolutePath($config['paths']['client_materials'] ?? '.larapilot/client-materials/'),
            $this->absolutePath($config['paths']['legacy'] ?? '.larapilot/legacy/'),
            $this->absolutePath($config['paths']['research'] ?? '.larapilot/research/'),
            $this->absolutePath($config['paths']['research'] ?? '.larapilot/research/').'/reference-products',
            $this->absolutePath($config['paths']['design_systems'] ?? '.larapilot/design-systems/'),
            $this->absolutePath($config['paths']['design_systems'] ?? '.larapilot/design-systems/').'/filament',
            $this->absolutePath($config['paths']['design_systems'] ?? '.larapilot/design-systems/').'/filament/html',
            $this->absolutePath($config['paths']['design_systems'] ?? '.larapilot/design-systems/').'/starter-kit',
            $this->absolutePath($config['paths']['design_systems'] ?? '.larapilot/design-systems/').'/starter-kit/html',
            $this->absolutePath($config['paths']['design_systems'] ?? '.larapilot/design-systems/').'/bootstrap-5',
            $this->absolutePath($config['paths']['design_systems'] ?? '.larapilot/design-systems/').'/bootstrap-5/html',
            $this->absolutePath($config['paths']['design_systems'] ?? '.larapilot/design-systems/').'/tailwind',
            $this->absolutePath($config['paths']['design_systems'] ?? '.larapilot/design-systems/').'/tailwind/html',
            $this->absolutePath($config['paths']['design_systems'] ?? '.larapilot/design-systems/').'/adminlte',
            $this->absolutePath($config['paths']['design_systems'] ?? '.larapilot/design-systems/').'/adminlte/html',
            $this->absolutePath($config['paths']['internal_feedback'] ?? '.larapilot/internal-feedback/'),
            $this->absolutePath($config['paths']['usage'] ?? '.larapilot/usage/'),
            dirname($this->absolutePath($config['paths']['prd'] ?? '.larapilot/docs/PRD.md')),
            $this->absolutePath('.larapilot/brand/'),
        ]));
    }

    public function ensureDirectories(): void
    {
        foreach ($this->workspaceDirectoryPaths() as $path) {
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }

        $this->ensureIntakeReadmes();
        $this->ensureGitkeeps();
    }

    public function ensureGitkeeps(): void
    {
        foreach ($this->workspaceDirectoryPaths() as $directory) {
            $gitkeep = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.'.gitkeep';

            if (! is_file($gitkeep)) {
                AtomicFile::write($gitkeep, '');
            }
        }
    }

    public function ensureIntakeReadmes(): void
    {
        $intakeReadmes = [
            '.larapilot/client-materials/README.md' => 'client-materials/README.md',
            '.larapilot/legacy/README.md' => 'legacy/README.md',
            '.larapilot/research/README.md' => 'research/README.md',
            '.larapilot/internal-feedback/README.md' => 'internal-feedback/README.md',
            '.larapilot/usage/README.md' => 'usage/README.md',
        ];

        foreach ($intakeReadmes as $projectRelative => $packageRelative) {
            $target = $this->absolutePath($projectRelative);
            $source = dirname(__DIR__, 2).'/resources/larapilot/'.$packageRelative;

            if (! is_file($target) && is_file($source)) {
                AtomicFile::write($target, (string) file_get_contents($source));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function writeProjectConfig(array $overrides = []): void
    {
        $config = array_replace_recursive($this->defaults(), $overrides);
        $this->ensureDirectories();

        AtomicFile::write(
            $this->configPath(),
            Yaml::dump($config, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
        );

        $this->resolved = null;
    }

    public function status(string $key): string
    {
        $config = $this->resolve();

        return Arr::get($config, "workflow.statuses.{$key}", strtoupper($key));
    }

    /**
     * Whether the mockup preview route may serve files in the current
     * environment. Never true in production.
     */
    public function mockupsBrowsable(): bool
    {
        return $this->devRouteBrowsable('mockups_route');
    }

    /**
     * Whether the workflow dashboard may be browsed in the current
     * environment. Never true in production.
     */
    public function dashboardBrowsable(): bool
    {
        return $this->devRouteBrowsable('dashboard_route');
    }

    /**
     * Whether mockup design-system assets may be served. Requires the
     * mockups route to be browsable and honors the dedicated
     * `mockup_assets_route` toggle/environments.
     */
    public function mockupAssetsBrowsable(): bool
    {
        return $this->mockupsBrowsable() && $this->devRouteBrowsable('mockup_assets_route');
    }

    protected function devRouteBrowsable(string $routeKey): bool
    {
        if (! config('larapilot.enabled', true) || ! config("larapilot.{$routeKey}.enabled", true)) {
            return false;
        }

        if (app()->environment('production')) {
            return false;
        }

        $allowed = config("larapilot.{$routeKey}.environments");

        if (is_array($allowed) && $allowed !== []) {
            return app()->environment($allowed);
        }

        return true;
    }

    public function commentsEnabled(): bool
    {
        if (! config('larapilot.enabled', true)) {
            return false;
        }

        return (bool) config('larapilot.comments.enabled', true);
    }
}
