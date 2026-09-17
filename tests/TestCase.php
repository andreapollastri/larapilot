<?php

declare(strict_types=1);

namespace Larapilot\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Testing\PendingCommand;
use Larapilot\LarapilotServiceProvider;
use Larapilot\Tests\Support\PendingCommandWithCleanup;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Call artisan through a PendingCommand that removes the mocked
     * OutputStyle container binding once it has run. Laravel 11+ does this
     * cleanup itself; on Laravel 10 the stale binding would otherwise swallow
     * the output of any later Artisan::call() in the same test, leaving
     * Artisan::output() empty.
     *
     * @param  string  $command
     * @param  array<string, mixed>  $parameters
     * @return PendingCommand|int
     */
    public function artisan($command, $parameters = [])
    {
        if (! $this->mockConsoleOutput) {
            return $this->app[Kernel::class]->call($command, $parameters);
        }

        return new PendingCommandWithCleanup($this, $this->app, $command, $parameters);
    }

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
        // The API route group throttles per IP via the cache; keep the suite off
        // any store that would need a migrated table.
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (is_dir(base_path('.larapilot'))) {
            $this->deleteDirectory(base_path('.larapilot'));
        }

        if (is_dir(base_path('_project_docs'))) {
            $this->deleteDirectory(base_path('_project_docs'));
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
