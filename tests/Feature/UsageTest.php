<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Larapilot\Services\ChoicesService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\PrdService;
use Larapilot\Services\UsageService;

it('appends usage ledger entries and reports totals', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:usage-log', [
        '--category' => 'analysis',
        '--tokens' => 1200,
        '--minutes' => 30,
        '--skill' => 'larapilot-inception',
        '--user' => 'git:Test <test@example.com>',
        '--estimated' => true,
    ])->assertSuccessful();

    $usage = app(UsageService::class);
    $summary = $usage->summary();

    expect($summary['entry_count'])->toBe(1)
        ->and($summary['total_tokens'])->toBe(1200)
        ->and($summary['total_minutes'])->toBe(30.0)
        ->and($summary['by_category']['analysis']['tokens'])->toBe(1200)
        ->and(is_file($usage->ledgerPath()))->toBeTrue();

    $this->artisan('larapilot:usage-report', ['--format' => 'json'])
        ->assertSuccessful()
        ->expectsOutputToContain('usage_report');
});

it('records deadlines and schedule notes', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:schedule-set', [
        '--deadline' => '2026-09-01',
        '--label' => 'Go-live',
        '--status' => 'on_track',
        '--note' => 'Client demo',
    ])->assertSuccessful();

    $this->artisan('larapilot:schedule-set', [
        '--note-only' => true,
        '--status' => 'at_risk',
        '--note' => 'Waiting on content',
    ])->assertSuccessful();

    $schedule = app(UsageService::class)->schedule();

    expect($schedule['deadlines'])->toHaveCount(1)
        ->and($schedule['deadlines'][0]['label'])->toBe('Go-live')
        ->and($schedule['notes'])->toHaveCount(1)
        ->and($schedule['notes'][0]['status'])->toBe('at_risk');
});

it('persists choices from the PRD for the settings dashboard', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();
    $config->ensureDirectories();

    app(PrdService::class)->write(<<<'MD'
# Product

## Elevator Pitch
A package.

## Vision
Ship it.

## User Personas
Developers.

## Functional Requirements
- Publish

## MVP Scope
**Project Kind:** Package
**Package Origin:** New
**Delivery Target:** V1 Complete
**Deadlines:** 2026-10-01 go-live

## Technical Architecture
**Budget Sensitivity:** Relaxed
**Data store:** PostgreSQL
**Hierarchy:** Adjacency List
MD);

    $this->artisan('larapilot:choices-set', ['--from-prd' => true])->assertSuccessful();

    $choices = app(ChoicesService::class)->read();

    expect($choices['project_kind'])->toBe('Package')
        ->and($choices['package_origin'])->toBe('New')
        ->and($choices['delivery_target'])->toBe('V1 Complete')
        ->and($choices['data_store'])->toBe('PostgreSQL');

    $this->get('/larapilot/settings')
        ->assertOk()
        ->assertSee('Project settings')
        ->assertSee('Package')
        ->assertSee('Inception choices');
});

it('renders the usage dashboard with gantt and report download', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec([
        'points' => 3,
        'epic' => [
            'code' => 'EP-001',
            'title' => 'Core',
            'objective' => 'Ship authentication',
            'deadline' => '2026-11-01',
        ],
    ]);

    $this->artisan('larapilot:usage-log', [
        '--category' => 'planning',
        '--tokens' => 2500,
        '--minutes' => 20,
        '--spec' => 'US-001',
        '--user' => 'git:Test',
        '--estimated' => true,
    ])->assertSuccessful();

    $this->artisan('larapilot:schedule-set', [
        '--deadline' => '2026-12-01',
        '--label' => 'Launch',
    ])->assertSuccessful();

    $this->get('/larapilot/usage')
        ->assertOk()
        ->assertSee('Lucille')
        ->assertSee('Project tracking')
        ->assertSee('Project Gantt')
        ->assertSee('US-001')
        ->assertSee('Launch')
        ->assertSee('2.5K')
        ->assertSee('Zoey vs Lucille')
        ->assertSee('Ledger history')
        ->assertSee('Hours');


    $this->get('/larapilot/settings')
        ->assertOk()
        ->assertSee('Lucille · Project tracking');

    $this->get('/larapilot/usage/report.md')
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename="larapilot-usage-report.md"')
        ->assertSee('Larapilot usage report')
        ->assertSee('Zoey vs Lucille');
});

it('builds dependency-aware gantt bars and formats tokens as K', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec([
        'points' => 5,
        'epic' => [
            'code' => 'EP-010',
            'title' => 'Billing',
            'objective' => 'Invoices paid online',
            'deadline' => '2026-10-01',
        ],
    ]);

    app(\Larapilot\Services\PlanService::class)->save('US-001', [
        'plan_body' => "## Technical Solution\nTest\n\n## Git & Branching\nNO_GITFLOW\n\n## Test Data Strategy\nN/A\n\n## Test Strategy\nMINIMAL",
        'tasks' => [
            [
                'id' => 'TASK-00',
                'title' => 'Bootstrap',
                'body' => "## Description\nx\n\n## Git Deliverables\n- Commit: chore\n\n## Test Data\nN/A\n\n## Completion Criteria\n- [ ] done",
                'type' => 'Impl',
                'status' => 'TODO',
                'assignee' => 'Alex',
                'estimate_hours' => 2,
                'dependencies' => [],
            ],
            [
                'id' => 'TASK-01',
                'title' => 'Model',
                'body' => "## Description\nx\n\n## Git Deliverables\n- Commit: feat\n\n## Test Data\nN/A\n\n## Completion Criteria\n- [ ] done",
                'type' => 'Impl',
                'status' => 'TODO',
                'assignee' => 'Alex',
                'estimate_hours' => 4,
                'dependencies' => ['TASK-00'],
            ],
            [
                'id' => 'TASK-02',
                'title' => 'UI',
                'body' => "## Description\nx\n\n## Git Deliverables\n- Commit: feat\n\n## Test Data\nN/A\n\n## Completion Criteria\n- [ ] done",
                'type' => 'Impl',
                'status' => 'TODO',
                'assignee' => 'Joe',
                'estimate_hours' => 3,
                'dependencies' => ['TASK-00'],
            ],
        ],
    ]);

    $usage = app(UsageService::class);

    expect($usage->formatTokens(999))->toBe('999')
        ->and($usage->formatTokens(1000))->toBe('1K')
        ->and($usage->formatTokens(2500))->toBe('2.5K');

    $gantt = $usage->gantt();

    expect($gantt['epics'])->not->toBeEmpty()
        ->and($gantt['epics'][0]['code'])->toBe('EP-010')
        ->and($gantt['epics'][0]['objective'])->toBe('Invoices paid online')
        ->and(collect($gantt['bars'])->where('type', 'task')->count())->toBe(3)
        ->and(collect($gantt['bars'])->where('parallel', true)->count())->toBeGreaterThan(0)
        ->and($gantt['legend'])->not->toBeEmpty();

    $criticality = $usage->criticality($gantt);
    $zoey = $usage->zoeyReconciliation();

    expect($criticality)->toHaveKeys(['remaining_points', 'forecast_end', 'alerts'])
        ->and($zoey['why_they_differ'])->not->toBeEmpty();
});

it('rejects invalid usage categories', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:usage-log', [
        '--category' => 'not-a-category',
        '--tokens' => 1,
    ])->assertExitCode(2);
});

it('filters the usage report and returns lucille insights', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:usage-log', [
        '--category' => 'analysis',
        '--tokens' => 100,
        '--minutes' => 10,
        '--skill' => 'larapilot-inception',
        '--user' => 'git:Alice',
        '--ts' => '2026-08-01T10:00:00+00:00',
    ])->assertSuccessful();

    $this->artisan('larapilot:usage-log', [
        '--category' => 'implementation',
        '--tokens' => 400,
        '--minutes' => 40,
        '--skill' => 'larapilot-implement',
        '--spec' => 'US-001',
        '--user' => 'git:Bob',
        '--ts' => '2026-08-05T10:00:00+00:00',
    ])->assertSuccessful();

    $this->artisan('larapilot:schedule-set', [
        '--deadline' => '2026-07-01',
        '--label' => 'Past demo',
        '--status' => 'delayed',
    ])->assertSuccessful();

    expect(Artisan::call('larapilot:usage-report', [
        '--format' => 'json',
        '--insights' => true,
        '--category' => 'implementation',
        '--from' => '2026-08-01',
        '--to' => '2026-08-31',
    ]))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['kind'] ?? null)->toBe('usage_report')
        ->and($envelope['data']['summary']['entry_count'] ?? null)->toBe(1)
        ->and($envelope['data']['summary']['total_minutes'] ?? null)->toEqual(40)
        ->and($envelope['data']['summary']['by_category']['implementation']['tokens'] ?? null)->toBe(400)
        ->and($envelope['data']['insights']['top_categories'][0]['category'] ?? null)->toBe('implementation')
        ->and($envelope['data']['insights']['at_risk_or_delayed'])->not->toBeEmpty();

    $filtered = app(UsageService::class)->query([
        'spec' => 'US-001',
    ]);

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]['category'])->toBe('implementation');
});
