<?php

declare(strict_types=1);

use Larapilot\Services\ForecastService;
use Larapilot\Services\PlanService;
use Larapilot\Services\UsageService;

/**
 * @param  array<string, mixed>|null  $epic
 * @return array<string, mixed>
 */
function forecastSpec(string $code, int $points, string $status, ?array $epic = null): array
{
    $spec = [
        'code' => $code,
        'title' => 'Story '.$code,
        'priority' => 'HIGH',
        'points' => $points,
        'status' => $status,
        'body' => validSpecBody(),
    ];

    if ($epic !== null) {
        $spec['epic'] = $epic;
    }

    return $spec;
}

it('forecasts the backlog from its story points, the effort setting, and a six-hour day', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:spec-add', ['--file' => payloadFile(['specs' => [
        forecastSpec('US-001', 3, 'DONE', ['code' => 'EP-001', 'title' => 'Core']),
        forecastSpec('US-002', 5, 'IN PROGRESS', ['code' => 'EP-001', 'title' => 'Core']),
        forecastSpec('US-003', 2, 'TODO', ['code' => 'EP-002', 'title' => 'Billing']),
        forecastSpec('US-004', 0, 'TODO'),
    ]])])->assertSuccessful();

    $forecast = app(ForecastService::class)->snapshot();

    expect($forecast['points'])->toBe(['total' => 10, 'done' => 3, 'wip' => 5, 'todo' => 2, 'remaining' => 7])
        ->and($forecast['specs'])->toBe(['total' => 4, 'done' => 1, 'wip' => 1, 'todo' => 2, 'unsized' => 1])
        ->and($forecast['hours_per_point']['value'])->toBe(4.0)
        ->and($forecast['hours_per_point']['source'])->toBe('settings')
        ->and($forecast['hours_per_point']['effort'])->toBe('STANDARD')
        ->and($forecast['sprint'])->toBe(['hours_per_day' => 6.0, 'days_per_week' => 5, 'weeks' => 2, 'hours' => 60.0, 'source' => 'default'])
        // 10 SP × 4 h = 40 h, 7 SP open = 28 h; a sprint holds 60 h.
        ->and($forecast['human'])->toBe(['hours_total' => 40.0, 'hours_remaining' => 28.0, 'sprints_total' => 1, 'sprints_remaining' => 1])
        ->and($forecast['measured'])->toBeNull()
        ->and($forecast['ai']['basis'])->toBe('model');

    // Realistic: 40 h ÷ 5 + 3 sized specs × 1 h; open: 28 h ÷ 5 + 2 × 1 h.
    expect($forecast['ai']['realistic'])->toBe(['hours_total' => 11.0, 'hours_remaining' => 7.6, 'sprints_total' => 1, 'sprints_remaining' => 1, 'speedup' => 3.6])
        // Optimistic: 40 h ÷ 10 + 3 × 0.5 h; open: 28 h ÷ 10 + 2 × 0.5 h.
        ->and($forecast['ai']['optimistic'])->toBe(['hours_total' => 5.5, 'hours_remaining' => 3.8, 'sprints_total' => 1, 'sprints_remaining' => 1, 'speedup' => 7.3]);

    expect($forecast['epics'])->toHaveCount(3)
        ->and($forecast['epics'][0])->toBe(['code' => 'EP-001', 'title' => 'Core', 'specs' => 2, 'points' => 8, 'points_done' => 3, 'points_remaining' => 5, 'human_hours' => 32.0, 'ai_hours' => 8.4, 'sprints' => 1])
        ->and($forecast['epics'][1])->toBe(['code' => 'EP-002', 'title' => 'Billing', 'specs' => 1, 'points' => 2, 'points_done' => 0, 'points_remaining' => 2, 'human_hours' => 8.0, 'ai_hours' => 2.6, 'sprints' => 1])
        ->and($forecast['epics'][2])->toBe(['code' => null, 'title' => 'No epic', 'specs' => 1, 'points' => 0, 'points_done' => 0, 'points_remaining' => 0, 'human_hours' => 0.0, 'ai_hours' => 0.0, 'sprints' => 0]);

    $assumptions = implode("\n", $forecast['assumptions']);

    expect($assumptions)->toContain('assumed to be 4 h', 'STANDARD effort setting', '6 h a day × 5 days × 2 weeks = 60 h', 'six-hour delivery day', '10× faster (optimistic) to 5× faster (realistic)', '1 spec carries no story points');
});

it('measures what a story point took on the ledger of the delivered specs', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:spec-add', ['--file' => payloadFile(['specs' => [
        forecastSpec('US-001', 3, 'DONE'),
        forecastSpec('US-002', 5, 'DONE'),
        forecastSpec('US-003', 2, 'TODO'),
    ]])])->assertSuccessful();

    $usage = app(UsageService::class);
    $usage->log(['category' => 'implementation', 'spec' => 'US-001', 'minutes' => 90, 'user' => 'git:Test']);
    // An open spec says nothing about what a delivered point took.
    $usage->log(['category' => 'planning', 'spec' => 'US-003', 'minutes' => 60, 'user' => 'git:Test']);

    $forecast = app(ForecastService::class)->snapshot();

    // 90 min on 3 SP: 0.5 h a point, read on one spec.
    expect($forecast['measured'])->toBe(['hours_per_point' => 0.5, 'specs' => 1, 'points' => 3, 'hours' => 1.5, 'thin' => true])
        ->and($forecast['human']['hours_total'])->toBe(40.0)
        ->and($forecast['ai']['basis'])->toBe('measured')
        // 10 SP × 0.5 h; 2 SP open × 0.5 h; 40 h ÷ 5 h.
        ->and($forecast['ai']['realistic'])->toBe(['hours_total' => 5.0, 'hours_remaining' => 1.0, 'sprints_total' => 1, 'sprints_remaining' => 1, 'speedup' => 8.0])
        ->and($forecast['ai']['optimistic'])->toBe($forecast['ai']['realistic'])
        ->and($forecast['epics'][0]['ai_hours'])->toBe(5.0)
        ->and(implode("\n", $forecast['assumptions']))->toContain('a story point took 0.5 h, measured on the 1 delivered spec', 'a first reading, not a rate');

    // A second delivered spec with hours in the ledger joins the measure.
    $usage->log(['category' => 'implementation', 'spec' => 'us-002', 'minutes' => 300, 'user' => 'git:Test']);

    $forecast = app(ForecastService::class)->snapshot();

    // 390 min on 8 SP.
    expect($forecast['measured'])->toBe(['hours_per_point' => 0.81, 'specs' => 2, 'points' => 8, 'hours' => 6.5, 'thin' => true])
        ->and($forecast['ai']['realistic']['hours_total'])->toBe(8.1);
});

it('calibrates the hours per point on the plans once enough of the backlog is planned', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:spec-add', ['--file' => payloadFile(['specs' => [
        forecastSpec('US-001', 3, 'TODO'),
        forecastSpec('US-002', 5, 'TODO'),
    ]])])->assertSuccessful();

    $plan = fn (float $hours): array => [
        'plan_body' => 'Build it.',
        'tasks' => [['id' => 'TASK-01', 'title' => 'Build', 'status' => 'TODO', 'estimate_hours' => $hours]],
    ];

    app(PlanService::class)->save('US-001', $plan(6));
    app(PlanService::class)->save('US-002', $plan(10));

    $forecast = app(ForecastService::class)->snapshot();

    // 16 h of plans on 8 SP: the rate the plans imply replaces the setting.
    expect($forecast['hours_per_point'])->toBe(['value' => 2.0, 'source' => 'plans', 'effort' => 'STANDARD', 'planned_specs' => 2])
        ->and($forecast['human']['hours_total'])->toBe(16.0)
        ->and(implode("\n", $forecast['assumptions']))->toContain('the rate the 2 planned specs imply');
});

it('counts the sprint on the billable day of the Economics profile when the project keeps one', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    $this->artisan('larapilot:economics-set', [
        '--country' => 'IT',
        '--regime' => 'forfettario_5',
        '--hourly-rate' => '50',
        '--hours-per-day' => '8',
    ])->assertSuccessful();

    addSpec(['points' => 20]);

    $forecast = app(ForecastService::class)->snapshot();

    // 20 SP × 4 h = 80 h: one sprint of 80 h, where a 60 h sprint would take two.
    expect($forecast['sprint'])->toBe(['hours_per_day' => 8.0, 'days_per_week' => 5, 'weeks' => 2, 'hours' => 80.0, 'source' => 'economics'])
        ->and($forecast['human']['hours_total'])->toBe(80.0)
        ->and($forecast['human']['sprints_total'])->toBe(1)
        ->and(implode("\n", $forecast['assumptions']))->toContain('8 h a day × 5 days × 2 weeks = 80 h', 'billable day of your Economics profile');
});

it('serves the forecast page before Usage in the Insights group, with its report', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['points' => 5, 'epic' => ['code' => 'EP-001', 'title' => 'Core']]);

    $html = $this->get('/larapilot/forecast')
        ->assertOk()
        ->assertSee('Forecast')
        ->assertSee('Story points')
        ->assertSee('5 SP')
        ->assertSee('Sprints · 1 person')
        ->assertSee('Hours per story point')
        ->assertSee('Assumed · effort STANDARD')
        ->assertSee('Hours · 1 person')
        ->assertSee('20 h')
        ->assertSee('Human vs AI')
        ->assertSee('AI optimistic')
        ->assertSee('AI realistic')
        ->assertSee('By epic')
        ->assertSee('EP-001')
        ->assertSee('Download report.md')
        ->assertSee('id="forecast-assumptions"', false)
        ->assertDontSee('No specs in the backlog yet')
        ->getContent();

    expect(strpos($html, '>Forecast</a>'))->toBeGreaterThan(strpos($html, '>Git</a>'))
        ->and(strpos($html, '>Forecast</a>'))->toBeLessThan(strpos($html, '>Usage</a>'))
        ->and($html)->toContain('aria-current="page"');

    $this->get('/larapilot/forecast/report.md')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
        ->assertHeader('content-disposition', 'attachment; filename="larapilot-forecast-report.md"')
        ->assertSee('# Larapilot forecast', false)
        ->assertSee('- **Story points:** 5 (0 done · 0 in progress or review · 5 to do · 5 remaining)', false)
        ->assertSee('- **Hours per story point:** 4 h (assumed, effort STANDARD)', false)
        ->assertSee('## Human vs AI', false)
        ->assertSee('| Hours | 20 h | 2.5 h | 5 h |', false)
        ->assertSee('| EP-001 — Core | 1 | 5 | 0 | 20 h | 5 h | 1 |', false)
        ->assertSee('## Assumptions', false);

    // The ledger turns the model into a measure.
    app(UsageService::class)->log(['category' => 'implementation', 'spec' => 'US-001', 'minutes' => 120, 'user' => 'git:Test']);
    $this->artisan('larapilot:spec-add', ['--file' => payloadFile(['specs' => [forecastSpec('US-001', 5, 'DONE')]])])->assertSuccessful();

    $this->get('/larapilot/forecast')
        ->assertOk()
        ->assertSee('With Larapilot:')
        ->assertSee('measured on 1 delivered spec')
        ->assertDontSee('AI optimistic');

    $this->get('/larapilot/forecast/report.md')
        ->assertOk()
        ->assertSee('| Hours | 20 h | 2 h |', false);
});

it('shows an empty state when the backlog has no specs', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->get('/larapilot/forecast')
        ->assertOk()
        ->assertSee('No specs in the backlog yet')
        ->assertDontSee('Human vs AI');

    $this->get('/larapilot/forecast/report.md')
        ->assertOk()
        ->assertSee('No specs in the backlog yet', false);
});

it('hides the forecast in production', function (): void {
    $this->app['env'] = 'production';

    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    $this->get('/larapilot/forecast')->assertNotFound();
    $this->get('/larapilot/forecast/report.md')->assertNotFound();
});

it('serves the forecast in production when the dashboard is forced there, behind the sign-in', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    dashboardUser('andrea');
    forcedProduction();

    $this->get('/larapilot/forecast')->assertRedirect('/larapilot/login');
});
