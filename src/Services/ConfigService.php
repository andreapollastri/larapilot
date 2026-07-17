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
                'backlog' => $this->absolutePath($config['file']['backlog'] ?? '.larapilot/backlog.yaml'),
                'planning' => $this->absolutePath($config['file']['planning'] ?? '.larapilot/plans/'),
            ],
            'workflow' => $config['workflow'] ?? config('larapilot.workflow'),
            'settings' => $this->settings(),
            'personas' => config('larapilot.personas'),
        ];
    }

    /**
     * Agent-facing settings. `auto_approve` is always the string YES|NO
     * (YAML stores a boolean — YES/NO are YAML 1.1 bool tokens).
     *
     * @return array{effort: string, git_mode: string, testing: string, auto_approve: string}
     */
    public function settings(): array
    {
        $config = $this->resolve();
        $raw = is_array($config['settings'] ?? null) ? $config['settings'] : [];
        $merged = array_replace($this->defaultSettings(), array_intersect_key($raw, $this->defaultSettings()));

        return [
            'effort' => (string) $merged['effort'],
            'git_mode' => (string) $merged['git_mode'],
            'testing' => (string) $merged['testing'],
            'auto_approve' => $this->normalizeAutoApprove($merged['auto_approve'] ?? false),
        ];
    }

    /**
     * @return array{effort: string, git_mode: string, testing: string, auto_approve: bool}
     */
    public function defaultSettings(): array
    {
        $defaults = config('larapilot.settings', []);

        return [
            'effort' => (string) ($defaults['effort'] ?? 'STANDARD'),
            'git_mode' => (string) ($defaults['git_mode'] ?? 'GITFLOW'),
            'testing' => (string) ($defaults['testing'] ?? 'NORMAL'),
            'auto_approve' => $this->autoApproveToBool($defaults['auto_approve'] ?? false),
        ];
    }

    /**
     * Persist one or more project settings into `.larapilot/config.yaml` without
     * wiping unrelated top-level keys the user may have added.
     *
     * @param  array<string, string|bool>  $partial
     * @return array{effort: string, git_mode: string, testing: string, auto_approve: string}
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
        $settings['auto_approve'] = $this->autoApproveToBool($settings['auto_approve'] ?? false);

        foreach ($partial as $key => $value) {
            if (! array_key_exists($key, $this->defaultSettings())) {
                continue;
            }

            $settings[$key] = $key === 'auto_approve'
                ? $this->autoApproveToBool($value)
                : $value;
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

    protected function normalizeAutoApprove(mixed $value): string
    {
        return $this->autoApproveToBool($value) ? 'YES' : 'NO';
    }

    protected function autoApproveToBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtoupper(trim((string) $value));

        return in_array($normalized, ['YES', 'SI', 'TRUE', 'ON', '1'], true);
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
    public function allowedAutoApproveModes(): array
    {
        return ['YES', 'NO'];
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
