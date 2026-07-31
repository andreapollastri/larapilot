<?php

declare(strict_types=1);

namespace Larapilot\Tests;

use Larapilot\LarapilotServiceProvider;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LarapilotServiceProvider::class,
            McpServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('larapilot.enabled', true);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (is_dir(base_path('.larapilot'))) {
            $this->deleteDirectory(base_path('.larapilot'));
        }

        foreach (['phpstan.neon', 'phpstan.neon.dist', 'pint.json'] as $file) {
            $path = base_path($file);

            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->ensureProjectComposerJson();
    }

    protected function ensureProjectComposerJson(): void
    {
        $path = base_path('composer.json');

        if (is_file($path)) {
            return;
        }

        file_put_contents($path, json_encode([
            'name' => 'larapilot/testbench-app',
            'require-dev' => [
                'laravel/pint' => '^1.27',
                'larastan/larastan' => '^3.0',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    protected function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if (in_array($item, ['.', '..'], true)) {
                continue;
            }

            $path = $dir.'/'.$item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
