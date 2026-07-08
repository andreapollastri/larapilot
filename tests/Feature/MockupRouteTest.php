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
    $this->get('/mockups/US-001/..%2F..%2F.env')->assertNotFound();
});

it('serves index.html when no path is given', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();

    $mockupDir = base_path('.larapilot/mockups/US-001');
    mkdir($mockupDir, 0755, true);
    file_put_contents($mockupDir.'/index.html', '<html><body>Home</body></html>');

    $this->get('/mockups/US-001')
        ->assertOk()
        ->assertHeader('content-type', 'text/html; charset=UTF-8');
});

it('serves nested assets with their mime type', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();

    $assetDir = base_path('.larapilot/mockups/US-001/css');
    mkdir($assetDir, 0755, true);
    file_put_contents($assetDir.'/app.css', 'body { color: red; }');

    $this->get('/mockups/US-001/css/app.css')
        ->assertOk()
        ->assertHeader('content-type', 'text/css; charset=UTF-8');
});

it('hides mockups when the route is disabled by config', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();

    $mockupDir = base_path('.larapilot/mockups/US-001');
    mkdir($mockupDir, 0755, true);
    file_put_contents($mockupDir.'/index.html', '<html><body>Mockup</body></html>');

    config()->set('larapilot.mockups_route.enabled', false);

    $this->get('/mockups/US-001')->assertNotFound();
});
