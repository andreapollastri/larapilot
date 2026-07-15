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

it('links the PRD table of contents to heading anchors', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();
    $config->ensureDirectories();

    app(PrdService::class)->write(validPrd());

    $this->get('/larapilot/prd')
        ->assertOk()
        ->assertSee('href="#elevator-pitch"', false)
        ->assertSee('id="elevator-pitch"', false);
});

it('shows specs whose status is outside the configured workflow', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['status' => 'BLOCKED']);

    $this->get('/larapilot')
        ->assertOk()
        ->assertSee('BLOCKED')
        ->assertSee('US-001');
});

it('shows story points and subtask progress on the board', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['points' => 5]);
    planSpec();

    $this->get('/larapilot')
        ->assertOk()
        ->assertDontSee('Story points')
        ->assertDontSee('Subtasks')
        ->assertDontSee('% delivered')
        ->assertDontSee('% complete')
        ->assertDontSee('0/2 tasks')
        ->assertSee('priority-high', false)
        ->assertSee('board-scroll', false)
        ->assertSee('5 SP')
        ->assertSee('0/2');
});

it('shows spec detail with tasks', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    planSpec();
    initTestGitRepository('feat(US-001): TASK-01 Create model');
    $this->artisan('larapilot:task-done', ['code' => 'US-001', 'taskId' => 'TASK-01'])->assertSuccessful();

    $this->get('/larapilot/specs/US-001')
        ->assertOk()
        ->assertSee('US-001')
        ->assertSee('3 SP')
        ->assertSee('User story')
        ->assertSee('TASK-01')
        ->assertSee('Create model')
        ->assertSee('feat(US-001): TASK-01 Create model')
        ->assertSee('data-exclusive-accordion', false)
        ->assertSee('task-accordion', false);
});

it('links mockups to spec detail when HTML exists', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    addMockup('US-001', [
        'index.html' => '<html><body>Login mockup</body></html>',
        'dark.html' => '<html><body>Dark login</body></html>',
    ]);

    $this->get('/larapilot/specs/US-001')
        ->assertOk()
        ->assertSee('Mockups', false)
        ->assertSee('<iframe', false)
        ->assertSee('/mockups/US-001', false)
        ->assertSee('/mockups/US-001/dark.html', false)
        ->assertSee('.larapilot/mockups/US-001/', false);
});

it('shows mockup indicator on board cards', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    addMockup('US-001', ['index.html' => '<html><body>Mockup</body></html>']);

    $this->get('/larapilot')
        ->assertOk()
        ->assertSee('mockup-indicator', false)
        ->assertSee('Mockup');
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
