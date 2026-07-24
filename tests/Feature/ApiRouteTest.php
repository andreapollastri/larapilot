<?php

declare(strict_types=1);

use Larapilot\Services\ConfigService;
use Larapilot\Services\PrdService;

it('serves the board API in local environment', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    $this->getJson('/larapilot/api/board')
        ->assertOk()
        ->assertJsonStructure([
            'metrics',
            'status_order',
            'columns' => [
                'TODO' => [
                    ['code', 'mockups', 'feedback'],
                ],
            ],
            'workflow',
        ])
        ->assertJsonPath('columns.TODO.0.code', 'US-001')
        ->assertJsonPath('columns.TODO.0.title', 'Login');
});

it('lists all specs via the API', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    addSpec(['code' => 'US-002', 'title' => 'Logout', 'status' => 'DONE']);

    $this->getJson('/larapilot/api/specs')
        ->assertOk()
        ->assertJsonPath('count', 2)
        ->assertJsonCount(2, 'items')
        ->assertJsonStructure([
            'status',
            'count',
            'items' => [
                ['code', 'title', 'status', 'task_progress', 'mockups', 'feedback'],
            ],
            'summary',
        ]);
});

it('filters specs by status via the API', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['status' => 'TODO']);
    addSpec(['code' => 'US-002', 'title' => 'Logout', 'status' => 'DONE']);

    $this->getJson('/larapilot/api/specs?status=TODO')
        ->assertOk()
        ->assertJsonPath('status', 'TODO')
        ->assertJsonPath('count', 1)
        ->assertJsonPath('items.0.code', 'US-001');

    $this->getJson('/larapilot/api/specs?status=done')
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('items.0.code', 'US-002');
});

it('shows a spec with plan and tasks via the API', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    planSpec();

    $this->getJson('/larapilot/api/specs/US-001')
        ->assertOk()
        ->assertJsonPath('spec.code', 'US-001')
        ->assertJsonPath('spec.title', 'Login')
        ->assertJsonPath('plan.code', 'US-001')
        ->assertJsonCount(2, 'tasks')
        ->assertJsonPath('tasks.0.id', 'TASK-01')
        ->assertJsonPath('task_progress.total', 2)
        ->assertJsonPath('task_progress.done', 0)
        ->assertJsonPath('mockups.available', false)
        ->assertJsonPath('mockups.screens', [])
        ->assertJsonPath('spec.mockups.available', false)
        ->assertJsonPath('spec.feedback.entries', []);
});

it('includes mockup metadata in the API when HTML exists', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    addMockup('US-001', [
        'index.html' => '<html><body>Mockup</body></html>',
        'desktop.html' => '<html><body>Desktop</body></html>',
    ]);

    $this->getJson('/larapilot/api/specs/US-001')
        ->assertOk()
        ->assertJsonPath('mockups.entry', 'index.html')
        ->assertJsonPath('mockups.entry_url', url(route('larapilot.mockups.show', ['spec' => 'US-001'], absolute: false)))
        ->assertJsonCount(2, 'mockups.screens')
        ->assertJsonPath('mockups.screens.1.file', 'index.html')
        ->assertJsonPath('mockups.screens.1.url', url(route('larapilot.mockups.show', ['spec' => 'US-001'], absolute: false)))
        ->assertJsonPath('spec.mockups.screen_count', 2)
        ->assertJsonCount(2, 'spec.mockups.screens');

    $this->getJson('/larapilot/api/board')
        ->assertOk()
        ->assertJsonPath('columns.TODO.0.mockups.available', true)
        ->assertJsonPath('columns.TODO.0.mockups.screen_count', 2)
        ->assertJsonCount(2, 'columns.TODO.0.mockups.screens')
        ->assertJsonPath('columns.TODO.0.task_progress.total', 0);

    $this->getJson('/larapilot/api/specs')
        ->assertOk()
        ->assertJsonPath('items.0.mockups.screen_count', 2)
        ->assertJsonCount(2, 'items.0.mockups.screens');
});

it('returns the PRD via the API', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();
    $config->ensureDirectories();

    app(PrdService::class)->write(validPrd());

    $this->getJson('/larapilot/api/prd')
        ->assertOk()
        ->assertJsonStructure(['content', 'headings'])
        ->assertJsonFragment(['title' => 'Elevator Pitch']);
});

it('returns diagnostics via the API', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->getJson('/larapilot/api/diagnostics?no_logs=1')
        ->assertOk()
        ->assertJsonStructure([
            'collected_at',
            'app' => ['name', 'env', 'debug', 'laravel_version', 'php_version'],
            'checks' => [
                'storage_writable' => ['ok', 'detail'],
                'cache' => ['ok', 'detail'],
                'database' => ['ok', 'detail'],
                'queue' => ['ok', 'detail'],
                'log_file' => ['ok', 'detail'],
            ],
            'healthy',
        ])
        ->assertJsonMissingPath('logs');
});

it('hides diagnostics via the API when disabled', function (): void {
    config(['larapilot.diagnostics.enabled' => false]);
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->getJson('/larapilot/api/diagnostics')->assertNotFound();
});

it('returns 404 for unknown specs via the API', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->getJson('/larapilot/api/specs/US-999')->assertNotFound();
});

it('returns 404 for missing PRD via the API', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();

    $this->getJson('/larapilot/api/prd')->assertNotFound();
});

it('serves the OpenAPI document', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->getJson('/larapilot/api/openapi.json')
        ->assertOk()
        ->assertJsonPath('openapi', '3.1.0')
        ->assertJsonPath('info.title', 'Larapilot Workflow API')
        ->assertJsonStructure([
            'paths' => [
                '/board',
                '/specs',
                '/specs/{code}',
                '/specs/{code}/comments',
                '/prd',
                '/diagnostics',
            ],
        ]);
});

it('serves the Swagger UI page from the dashboard', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->get('/larapilot/api/docs')
        ->assertOk()
        ->assertSee('swagger-ui', false)
        ->assertSee(route('larapilot.api.openapi'), false);
});

it('links the API docs from the dashboard navigation', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->get('/larapilot')
        ->assertOk()
        ->assertSee(route('larapilot.api.docs'), false)
        ->assertSee('>API</a>', false);
});

it('hides the API in production environment', function (): void {
    $this->app['env'] = 'production';

    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    $this->getJson('/larapilot/api/board')->assertNotFound();
    $this->getJson('/larapilot/api/specs')->assertNotFound();
    $this->getJson('/larapilot/api/specs/US-001')->assertNotFound();
    $this->getJson('/larapilot/api/prd')->assertNotFound();
    $this->getJson('/larapilot/api/diagnostics')->assertNotFound();
    $this->getJson('/larapilot/api/openapi.json')->assertNotFound();
    $this->get('/larapilot/api/docs')->assertNotFound();
});

it('hides the API when the dashboard route is disabled by config', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    config()->set('larapilot.dashboard_route.enabled', false);

    $this->getJson('/larapilot/api/board')->assertNotFound();
});

it('includes specs with unknown statuses on the board API', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['status' => 'BLOCKED']);

    $this->getJson('/larapilot/api/board')
        ->assertOk()
        ->assertJsonPath('columns.BLOCKED.0.code', 'US-001');
});

it('accepts internal feedback comments via the API', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['status' => 'PLANNED']);

    $this->postJson('/larapilot/api/specs/US-001/comments', [
        'author' => 'PM',
        'message' => 'Clarified acceptance criteria in chat.',
    ])
        ->assertCreated()
        ->assertJsonPath('code', 'US-001')
        ->assertJsonPath('entry_count', 1)
        ->assertJsonPath('feedback.entries.0.author', 'PM')
        ->assertJsonPath('feedback.entries.0.body', 'Clarified acceptance criteria in chat.')
        ->assertJsonStructure(['mockups' => ['screens']]);

    expect(file_get_contents(base_path('.larapilot/internal-feedback/US-001.md')))
        ->toContain('Clarified acceptance criteria in chat.');
});

it('rejects invalid API comment submissions', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['status' => 'PLANNED']);

    $this->postJson('/larapilot/api/specs/US-001/comments', [
        'author' => '',
        'message' => '',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['author', 'message']);
});

it('rejects API comments on done specs', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['status' => 'DONE']);

    $this->postJson('/larapilot/api/specs/US-001/comments', [
        'author' => 'PM',
        'message' => 'Too late.',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Comments are closed for this user story.');
});

it('hides API comment endpoint when comments are disabled', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['status' => 'PLANNED']);

    config()->set('larapilot.comments.enabled', false);

    $this->postJson('/larapilot/api/specs/US-001/comments', [
        'author' => 'PM',
        'message' => 'Should not work.',
    ])->assertNotFound();
});
