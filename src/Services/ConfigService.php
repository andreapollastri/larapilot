<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Support\Arr;
use Symfony\Component\Yaml\Yaml;

class ConfigService
{
    public function projectRoot(): string
    {
        return base_path();
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(): array
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
                'backlog' => $this->absolutePath($config['file']['backlog'] ?? '.larapilot/backlog.yaml'),
                'planning' => $this->absolutePath($config['file']['planning'] ?? '.larapilot/plans/'),
            ],
            'workflow' => $config['workflow'] ?? config('larapilot.workflow'),
            'personas' => config('larapilot.personas'),
        ];
    }

    public function absolutePath(string $relative): string
    {
        if (str_starts_with($relative, '/')) {
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
            'paths' => config('larapilot.paths'),
            'workflow' => config('larapilot.workflow'),
            'file' => config('larapilot.file'),
        ];
    }

    public function ensureDirectories(): void
    {
        $config = $this->resolve();
        $paths = [
            dirname($this->configPath()),
            dirname($this->absolutePath($config['file']['backlog'] ?? '.larapilot/backlog.yaml')),
            $this->absolutePath($config['file']['specs'] ?? '.larapilot/specs/'),
            $this->absolutePath($config['file']['planning'] ?? '.larapilot/plans/'),
            $this->absolutePath($config['paths']['mockups'] ?? '.larapilot/mockups/'),
            $this->absolutePath($config['paths']['test_results'] ?? '.larapilot/docs/test-results/'),
            dirname($this->absolutePath($config['paths']['prd'] ?? '.larapilot/docs/PRD.md')),
        ];

        foreach (array_unique($paths) as $path) {
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
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

        file_put_contents(
            $this->configPath(),
            Yaml::dump($config, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
        );
    }

    public function status(string $key): string
    {
        $config = $this->resolve();

        return Arr::get($config, "workflow.statuses.{$key}", strtoupper($key));
    }
}
