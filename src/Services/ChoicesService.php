<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\AtomicFile;
use Symfony\Component\Yaml\Yaml;

class ChoicesService
{
    public function __construct(
        protected ConfigService $config,
        protected PrdService $prd,
    ) {}

    public function path(): string
    {
        $config = $this->config->resolve();

        return $this->config->absolutePath($config['paths']['choices'] ?? '.larapilot/choices.yaml');
    }

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return $this->fromPrd() + [
                'settings' => $this->config->settings(),
                'source' => 'prd+settings',
                'updated_at' => null,
            ];
        }

        $parsed = Yaml::parseFile($path);

        if (! is_array($parsed)) {
            return [
                'settings' => $this->config->settings(),
                'source' => 'empty',
                'updated_at' => null,
            ];
        }

        $parsed['settings'] = array_merge(
            $this->config->settings(),
            is_array($parsed['settings'] ?? null) ? $parsed['settings'] : []
        );

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $choices
     * @return array<string, mixed>
     */
    public function write(array $choices): array
    {
        $payload = array_replace_recursive($this->read(), $choices);
        $payload['settings'] = $this->config->settings();
        $payload['updated_at'] = (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM);
        $payload['source'] = 'choices.yaml';

        AtomicFile::write(
            $this->path(),
            Yaml::dump($payload, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
        );

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function syncFromPrd(): array
    {
        return $this->write($this->fromPrd() + ['source' => 'prd']);
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $choices = $this->read();
        $settings = $this->config->settings();
        $allowed = [
            'effort' => $this->config->allowedEfforts(),
            'backlog' => $this->config->allowedBacklogModes(),
            'git_mode' => $this->config->allowedGitModes(),
            'testing' => $this->config->allowedTestingModes(),
            'auto_approve' => $this->config->allowedAutoApproveModes(),
            'lucille' => $this->config->allowedLucilleModes(),
            'dashboard_auth' => $this->config->allowedDashboardAuthModes(),
            'api_auth' => $this->config->allowedApiAuthModes(),
            'security_scan' => $this->config->allowedSecurityScanModes(),
            'github' => $this->config->allowedGithubModes(),
            'gitlab' => $this->config->allowedGitlabModes(),
            'bitbucket' => $this->config->allowedBitbucketModes(),
            'azure' => $this->config->allowedAzureModes(),
            'notifications' => $this->config->allowedNotificationsModes(),
            'notify_slack' => $this->config->allowedNotifyChannelModes(),
            'notify_discord' => $this->config->allowedNotifyChannelModes(),
            'notify_telegram' => $this->config->allowedNotifyChannelModes(),
        ];

        $inception = [
            'Project Kind' => $choices['project_kind'] ?? null,
            'Website Type' => $choices['website_type'] ?? null,
            'Package Origin' => $choices['package_origin'] ?? null,
            'Package path' => $choices['package_path'] ?? null,
            'Package git' => $choices['package_git'] ?? null,
            'Project Origin' => $choices['project_origin'] ?? null,
            'Delivery Target' => $choices['delivery_target'] ?? null,
            'Budget Sensitivity' => $choices['budget_sensitivity'] ?? null,
            'Frontend Topology' => $choices['frontend_topology'] ?? null,
            'Admin panel' => $choices['admin_panel'] ?? null,
            'Data store' => $choices['data_store'] ?? null,
            'Hierarchy pattern' => $choices['hierarchy_pattern'] ?? null,
            'Search' => $choices['search'] ?? null,
            'CLI tooling' => $choices['cli_tooling'] ?? null,
            'Deadlines' => $choices['deadlines'] ?? null,
            'Local dev' => $choices['local_dev'] ?? null,
            'Deploy platform' => $choices['deploy_platform'] ?? null,
        ];

        return [
            'settings' => [
                'current' => $settings,
                'options' => $allowed,
            ],
            'inception' => array_filter(
                $inception,
                static fn (mixed $value): bool => $value !== null && $value !== ''
            ),
            'raw' => $choices,
            'path' => $this->config->relativePath($this->path()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fromPrd(): array
    {
        $content = $this->prd->read() ?? '';

        return array_filter([
            'project_kind' => $this->matchField($content, 'Project Kind'),
            'website_type' => $this->matchField($content, 'Website Type'),
            'package_origin' => $this->matchField($content, 'Package Origin'),
            'package_path' => $this->matchField($content, 'Package path'),
            'package_git' => $this->matchField($content, 'Package git'),
            'project_origin' => $this->matchField($content, 'Project Origin'),
            'delivery_target' => $this->matchField($content, 'Delivery Target'),
            'budget_sensitivity' => $this->matchField($content, 'Budget Sensitivity'),
            'frontend_topology' => $this->matchField($content, 'Frontend Topology'),
            'deadlines' => $this->matchField($content, 'Deadlines'),
            'data_store' => $this->matchField($content, 'Data store'),
            'hierarchy_pattern' => $this->matchField($content, 'Hierarchy'),
            'search' => $this->matchField($content, 'Search'),
            'admin_panel' => $this->matchField($content, 'Admin panel'),
            'local_dev' => $this->matchField($content, 'Local dev'),
            'deploy_platform' => $this->matchField($content, 'Deploy'),
            'cli_tooling' => $this->matchField($content, 'CLI tooling'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    protected function matchField(string $content, string $label): ?string
    {
        $pattern = '/\*\*'.preg_quote($label, '/').':\*\*\s*(.+)$/mi';

        if (preg_match($pattern, $content, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }
}
