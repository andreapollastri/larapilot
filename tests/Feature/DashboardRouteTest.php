<?php

declare(strict_types=1);

use Larapilot\Services\ConfigService;
use Larapilot\Services\PrdService;

it('serves the workflow dashboard in local environment', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    $this->get('/larapilot')
        ->assertOk()
        ->assertSee('Larapilot')
        ->assertSee('US-001')
        ->assertSee('Login');
});

it('hides the dashboard in production environment', function (): void {
    $this->app['env'] = 'production';

    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    $this->get('/larapilot')->assertNotFound();
    $this->get('/larapilot/prd')->assertNotFound();
    $this->get('/larapilot/specs/US-001')->assertNotFound();
});

it('hides the dashboard when the route is disabled by config', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    config()->set('larapilot.dashboard_route.enabled', false);

    $this->get('/larapilot')->assertNotFound();
});

it('renders the PRD with section headings', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();
    $config->ensureDirectories();

    app(PrdService::class)->write(validPrd());

    $this->get('/larapilot/prd')
        ->assertOk()
        ->assertSee('Elevator Pitch')
        ->assertSee('Technical Architecture');
});

it('shows spec detail with tasks', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    planSpec();

    $this->get('/larapilot/specs/US-001')
        ->assertOk()
        ->assertSee('US-001')
        ->assertSee('User Story')
        ->assertSee('TASK-01')
        ->assertSee('Create model');
});

it('returns 404 for unknown specs', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->get('/larapilot/specs/US-999')->assertNotFound();
});

it('shows empty states when artifacts are missing', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();

    $this->get('/larapilot')
        ->assertOk()
        ->assertSee('No backlog specs yet');

    $this->get('/larapilot/prd')
        ->assertOk()
        ->assertSee('No PRD found');
});
