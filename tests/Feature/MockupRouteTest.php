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

it('rewrites parent-relative tokens.css in mockup html to design-system assets', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $mockupDir = base_path('.larapilot/mockups/US-001');
    mkdir($mockupDir, 0755, true);
    file_put_contents($mockupDir.'/index.html', <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="stylesheet" href="../tokens.css">
</head>
<body class="fi-mockup"><p>Dashboard</p></body>
</html>
HTML);

    $this->get('/mockups/US-001')
        ->assertOk()
        ->assertSee('/mockup-assets/design-systems/filament/tokens.css', false);

    $this->get('/mockup-assets/design-systems/filament/tokens.css')
        ->assertOk()
        ->assertHeader('content-type', 'text/css; charset=UTF-8');
});

it('serves orphan tokens.css requests resolved from design systems', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->get('/mockups/tokens.css')
        ->assertOk()
        ->assertHeader('content-type', 'text/css; charset=UTF-8');
});

it('hides mockup asset routes in production environment', function (): void {
    $this->app['env'] = 'production';
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->get('/mockup-assets/design-systems/filament/tokens.css')->assertNotFound();
});

it('rewrites filament-tokens.css in mockup html to design-system assets', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $mockupDir = base_path('.larapilot/mockups/US-001');
    mkdir($mockupDir, 0755, true);
    file_put_contents($mockupDir.'/index.html', <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="stylesheet" href="filament-tokens.css">
</head>
<body class="fi-mockup"><p>Dashboard</p></body>
</html>
HTML);

    $this->get('/mockups/US-001')
        ->assertOk()
        ->assertSee('/mockup-assets/design-systems/filament/tokens.css', false);
});

it('serves orphan filament-tokens.css requests resolved from design systems', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->get('/mockups/filament-tokens.css')
        ->assertOk()
        ->assertHeader('content-type', 'text/css; charset=UTF-8');
});

it('rewrites logo.svg references and serves nested brand assets', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();

    $mockupDir = base_path('.larapilot/mockups/US-001');
    $assetsDir = $mockupDir.'/assets';
    mkdir($assetsDir, 0755, true);
    file_put_contents($assetsDir.'/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
    file_put_contents($mockupDir.'/index.html', <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<body>
  <img src="logo.svg" alt="Logo">
</body>
</html>
HTML);

    $this->get('/mockups/US-001')
        ->assertOk()
        ->assertSee('/mockups/US-001/assets/logo.svg', false);

    $this->get('/mockups/US-001/assets/logo.svg')
        ->assertOk()
        ->assertHeader('content-type', 'image/svg+xml');
});

it('serves orphan logo.svg requests from nested mockup folders', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();

    $mockupDir = base_path('.larapilot/mockups/US-001/assets');
    mkdir($mockupDir, 0755, true);
    file_put_contents($mockupDir.'/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    $this->get('/mockups/logo.svg')
        ->assertOk()
        ->assertHeader('content-type', 'image/svg+xml');
});

it('rewrites url() references inside mockup css files', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();

    $mockupDir = base_path('.larapilot/mockups/US-001');
    mkdir($mockupDir, 0755, true);
    file_put_contents($mockupDir.'/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
    file_put_contents($mockupDir.'/app.css', <<<'CSS'
.brand {
  background-image: url("logo.svg");
}
CSS);
    file_put_contents($mockupDir.'/index.html', '<link rel="stylesheet" href="app.css">');

    $this->get('/mockups/US-001/app.css')
        ->assertOk()
        ->assertSee('url("/mockups/US-001/logo.svg")', false);
});
