<?php

declare(strict_types=1);

use Larapilot\Services\ConfigService;

it('serves mockups via dynamic route in local environment', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();
    $config->ensureDirectories();

    $mockupDir = base_path('.larapilot/mockups/US-001');
    mkdir($mockupDir, 0755, true);
    file_put_contents($mockupDir.'/index.html', '<html><body>Mockup</body></html>');

    $this->get('/mockups/US-001/index.html')
        ->assertOk()
        ->assertHeader('content-type', 'text/html; charset=UTF-8');
});

it('hides mockups in production environment', function (): void {
    $this->app['env'] = 'production';

    $config = app(ConfigService::class);
    $config->writeProjectConfig();
    $config->ensureDirectories();

    $mockupDir = base_path('.larapilot/mockups/US-001');
    mkdir($mockupDir, 0755, true);
    file_put_contents($mockupDir.'/index.html', '<html><body>Mockup</body></html>');

    $this->get('/mockups/US-001')->assertNotFound();
});

it('blocks path traversal in mockup route', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();
    $config->ensureDirectories();

    $this->get('/mockups/US-001/../../.env')->assertNotFound();
});
