<?php

declare(strict_types=1);

use Larapilot\Services\EffortEstimateService;

it('derives hourly phases from story points', function (): void {
    $estimate = app(EffortEstimateService::class)->forSpec(['points' => 3]);

    expect($estimate['source'])->toBe('points')
        ->and($estimate['total'])->toBe(6.0)
        ->and($estimate['implement'])->toBe(4.5)
        ->and($estimate['label_total'])->toBe('6h')
        ->and($estimate['label_breakdown'])->toContain('Plan')
        ->and($estimate['label_breakdown'])->toContain('Impl');
});

it('rolls up plan task estimate hours into the implement phase', function (): void {
    $plan = [
        'tasks' => [
            ['estimate_hours' => 4],
            ['estimate_hours' => 2],
        ],
    ];

    $estimate = app(EffortEstimateService::class)->forSpec(['points' => 5], $plan);

    expect($estimate['source'])->toBe('tasks')
        ->and($estimate['implement'])->toBe(6.0)
        ->and($estimate['total'])->toBe(8.0);
});

it('never lets story points inflate a planned spec', function (): void {
    $plan = [
        'tasks' => [
            ['estimate_hours' => 1],
            ['estimate_hours' => 0.5],
        ],
    ];

    // 13 points alone would suggest 26h; the 1.5h of planned work must win.
    $estimate = app(EffortEstimateService::class)->forSpec(['points' => 13], $plan);

    expect($estimate['source'])->toBe('tasks')
        ->and($estimate['implement'])->toBe(1.5)
        ->and($estimate['total'])->toBe(2.0);
});

it('clamps runaway inputs to keep estimates sane', function (): void {
    $service = app(EffortEstimateService::class);

    // Points beyond the split threshold are capped (21 × 2h = 42h max).
    $fromPoints = $service->forSpec(['points' => 500]);

    // A task worth "90 hours" is treated as a unit mistake and capped at 16h.
    $fromTasks = $service->forSpec(['points' => 0], [
        'tasks' => [['estimate_hours' => 90]],
    ]);

    expect($fromPoints['total'])->toBe(42.0)
        ->and($fromTasks['implement'])->toBe(16.0);
});

it('honours explicit estimate_hours on the spec', function (): void {
    $estimate = app(EffortEstimateService::class)->forSpec([
        'points' => 3,
        'estimate_hours' => [
            'plan' => 1,
            'implement' => 5,
            'review' => 0.5,
            'rework' => 1,
            'deploy' => 0.5,
        ],
    ]);

    expect($estimate['source'])->toBe('explicit')
        ->and($estimate['total'])->toBe(8.0);
});

it('shows hourly estimates on the dashboard board', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['points' => 3, 'status' => 'TODO']);

    $this->get('/larapilot')
        ->assertOk()
        ->assertSee('6h', false)
        ->assertSee('Est. remaining', false)
        ->assertSee('Plan', false);
});

it('shows the delivery estimate breakdown on the spec page', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['points' => 3]);

    $this->get('/larapilot/specs/US-001')
        ->assertOk()
        ->assertSee('Delivery estimate', false)
        ->assertSee('Implement', false)
        ->assertSee('6h', false);
});
