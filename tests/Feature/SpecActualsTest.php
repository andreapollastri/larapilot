<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Larapilot\Services\EconomicsService;
use Larapilot\Services\MetricsService;
use Larapilot\Services\PlanService;
use Larapilot\Services\SpecActualsService;
use Larapilot\Services\SpecService;
use Larapilot\Services\UsageService;

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * Move a spec through its statuses at the given times, the way `spec-start`,
 * `spec-review`, and `spec-approve` do. A `rework` step sends it back.
 *
 * @param  list<array{0: string, 1: string}>  $steps
 */
function walkSpec(string $code, array $steps): void
{
    foreach ($steps as [$status, $at]) {
        Carbon::setTestNow($at);

        $status === 'rework'
            ? app(SpecService::class)->requestChanges($code, 'Not there yet.')
            : app(SpecService::class)->setStatus($code, $status);
    }

    Carbon::setTestNow();
}

function actualsBacklog(): void
{
    test()->artisan('larapilot:install')->assertSuccessful();

    $spec = fn (string $code, int $points): array => [
        'code' => $code,
        'title' => 'Story '.$code,
        'priority' => 'HIGH',
        'points' => $points,
        'status' => 'TODO',
        'body' => validSpecBody(),
    ];

    test()->artisan('larapilot:spec-add', ['--file' => payloadFile(['specs' => [
        $spec('US-001', 3),
        $spec('US-002', 5),
        $spec('US-003', 2),
        $spec('US-004', 1),
        $spec('US-005', 8),
    ]])])->assertSuccessful();

    $task = fn (string $id, float $hours): array => [
        'id' => $id,
        'title' => 'Task '.$id,
        'body' => "## Description\nx",
        'type' => 'Impl',
        'status' => 'DONE',
        'estimate_hours' => $hours,
        'dependencies' => [],
    ];

    app(PlanService::class)->save('US-001', [
        'plan_body' => 'Plan.',
        'tasks' => [$task('TASK-01', 2), $task('TASK-02', 4)],
    ]);

    // Built in one go: 90 minutes in progress, two hours waiting for review.
    walkSpec('US-001', [
        ['IN PROGRESS', '2026-03-02 09:00:00'],
        ['REVIEW', '2026-03-02 10:30:00'],
        ['DONE', '2026-03-02 12:30:00'],
    ]);

    // Sent back once: 60 + 30 minutes of build, 60 + 30 of review.
    walkSpec('US-002', [
        ['IN PROGRESS', '2026-03-03 09:00:00'],
        ['REVIEW', '2026-03-03 10:00:00'],
        ['rework', '2026-03-03 11:00:00'],
        ['IN PROGRESS', '2026-03-03 13:00:00'],
        ['REVIEW', '2026-03-03 13:30:00'],
        ['DONE', '2026-03-03 14:00:00'],
    ]);

    // Started over: it left IN PROGRESS for PLANNED before it reached review.
    walkSpec('US-003', [
        ['IN PROGRESS', '2026-03-04 09:00:00'],
        ['PLANNED', '2026-03-04 09:20:00'],
        ['IN PROGRESS', '2026-03-04 09:20:00'],
        ['REVIEW', '2026-03-04 09:50:00'],
        ['DONE', '2026-03-04 10:00:00'],
    ]);

    // Done with no build on record.
    walkSpec('US-004', [['DONE', '2026-03-01 08:00:00']]);

    $usage = app(UsageService::class);
    $usage->log(['category' => 'implementation', 'spec' => 'US-001', 'tokens' => 1200, 'minutes' => 20, 'user' => 'git:Test']);
    $usage->log(['category' => 'review', 'spec' => 'us-001', 'tokens' => 800, 'minutes' => 5, 'user' => 'git:Test']);
    $usage->log(['category' => 'analysis', 'tokens' => 5000, 'minutes' => 30, 'user' => 'git:Test']);
}

it('times every delivered spec from its status history, beside its estimate', function (): void {
    actualsBacklog();

    $actuals = app(UsageService::class)->actuals();
    $rows = collect($actuals['specs'])->keyBy('code');

    expect($rows->keys()->all())->toBe(['US-003', 'US-002', 'US-001', 'US-004'])
        // planned: the hours of its tasks
        ->and($rows['US-001']['estimate_hours'])->toBe(6.0)
        ->and($rows['US-001']['estimate_from'])->toBe('plan')
        ->and($rows['US-001']['build_minutes'])->toBe(90.0)
        ->and($rows['US-001']['build_display'])->toBe('1 h 30 min')
        ->and($rows['US-001']['ratio'])->toBe(4.0)
        ->and($rows['US-001']['ratio_display'])->toBe('4×')
        ->and($rows['US-001']['review_minutes'])->toBe(120.0)
        ->and($rows['US-001']['reworks'])->toBe(0)
        ->and($rows['US-001']['tokens'])->toBe(2000)
        ->and($rows['US-001']['delivered_at'])->toBe('2026-03-02T12:30:00+00:00')
        // no plan: story points at the hours of the effort setting
        ->and($rows['US-002']['estimate_hours'])->toBe(20.0)
        ->and($rows['US-002']['estimate_from'])->toBe('points')
        ->and($rows['US-002']['build_minutes'])->toBe(90.0)
        ->and($rows['US-002']['review_minutes'])->toBe(90.0)
        ->and($rows['US-002']['reworks'])->toBe(1)
        ->and($rows['US-002']['restarts'])->toBe(0)
        ->and($rows['US-002']['tokens'])->toBeNull()
        ->and($rows['US-003']['build_minutes'])->toBe(50.0)
        ->and($rows['US-003']['restarts'])->toBe(1)
        ->and($rows['US-003']['reworks'])->toBe(0)
        // delivered, but never IN PROGRESS: nothing to compare
        ->and($rows['US-004']['timed'])->toBeFalse()
        ->and($rows['US-004']['build_minutes'])->toBeNull()
        ->and($rows['US-004']['ratio'])->toBeNull();

    expect($actuals['totals'])->toMatchArray([
        'backlog' => 5,
        'delivered' => 4,
        'timed' => 3,
        // 6 + 20 + 8: the spec with no build time stays out of the comparison
        'estimate_hours' => 34.0,
        'build_hours' => 3.8,
        'build_display' => '3 h 50 min',
        'ratio' => 8.9,
        'ratio_display' => '8.9×',
        'tokens' => 2000,
        'specs_with_tokens' => 1,
        'reworks' => 1,
        'restarts' => 1,
    ]);
});

it('gives no project ratio under three timed specs', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['points' => 2]);

    expect(app(UsageService::class)->actuals()['specs'])->toBe([]);

    $this->get('/larapilot/usage')
        ->assertOk()
        ->assertSee('Estimate vs build')
        ->assertSee('No spec delivered yet');

    walkSpec('US-001', [
        ['IN PROGRESS', '2026-03-02 09:00:00'],
        ['REVIEW', '2026-03-02 09:30:00'],
        ['DONE', '2026-03-02 09:40:00'],
    ]);

    $actuals = app(UsageService::class)->actuals();

    expect($actuals['specs'][0]['ratio'])->toBe(16.0)
        ->and($actuals['totals']['timed'])->toBe(1)
        ->and($actuals['totals']['ratio'])->toBeNull();

    $this->get('/larapilot/usage')
        ->assertOk()
        ->assertSee('Given from 3 specs with a build time')
        ->assertSee('16×');
});

it('prices the same estimate Economics does', function (): void {
    actualsBacklog();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();

    $effort = app(EconomicsService::class)->snapshot()['effort'];
    $quoted = collect($effort['breakdown'])->pluck('hours', 'code');
    $estimates = app(SpecActualsService::class)->estimates()['specs'];

    foreach ($estimates as $code => $estimate) {
        expect(round($estimate['hours'], 1))->toBe((float) $quoted[$code]);
    }

    expect($effort['built'])->toMatchArray([
        'specs' => 3,
        'estimate_hours' => 34.0,
        'quoted_hours' => round(34.0 * EconomicsService::PM_QA_BUFFER, 1),
        'build_display' => '3 h 50 min',
    ]);

    $this->get('/larapilot/economics')
        ->assertOk()
        ->assertSee('What the delivered work took')
        ->assertSee('3 h 50 min');

    // An internal figure: the document for the client never carries it.
    expect(app(EconomicsService::class)->quoteMarkdown())->not->toContain('3 h 50 min')
        ->and(app(EconomicsService::class)->reportMarkdown())->toContain('built in 3 h 50 min');
});

it('shows the delivered specs on the usage page, in the report, the insights, and the metrics', function (): void {
    actualsBacklog();

    $this->get('/larapilot/usage')
        ->assertOk()
        ->assertSee('Estimate vs build')
        ->assertSee('4 of 5 specs delivered')
        ->assertSee('3 with a build time')
        ->assertSee('8.9×')
        ->assertSee('3 h 50 min')
        ->assertSee('sent back ×1')
        ->assertSee('started over ×1')
        ->assertSee('from story points')
        ->assertSee('no build time')
        ->assertSee('Logged for 1 of 4 delivered specs');

    $this->get('/larapilot/usage/report.md')
        ->assertOk()
        ->assertSee('## Estimate vs build', false)
        ->assertSee('| US-002 | 20 h | 1 h 30 min | 13× | 1 h 30 min | — | sent back ×1 · estimate from story points |', false);

    // A filtered ledger holds a part of the tokens: the section stays out of it.
    expect(app(UsageService::class)->reportMarkdown(['spec' => 'US-001']))->not->toContain('Estimate vs build');

    expect(Artisan::call('larapilot:usage-report', ['--format' => 'json', '--insights' => true, '--spec' => 'US-002']))->toBe(0);
    $insight = json_decode(Artisan::output(), true)['data']['insights']['actuals'];

    expect($insight['totals']['timed'])->toBe(3)
        ->and($insight['specs'])->toHaveCount(1)
        ->and($insight['specs'][0])->toBe([
            'code' => 'US-002',
            'estimate_hours' => 20,
            'build' => '1 h 30 min',
            'ratio' => 13.3,
            'in_review' => '1 h 30 min',
            'reworks' => 1,
            'restarts' => 0,
            'tokens' => null,
        ]);

    expect(app(MetricsService::class)->snapshot()['build'])->toBe([
        'specs_delivered' => 4,
        'specs_timed' => 3,
        'estimate_hours' => 34.0,
        'build_hours' => 3.8,
        'estimate_to_build' => 8.9,
        'review_wait_hours' => 3.7,
        'reworks' => 1,
        'restarts' => 1,
        'tokens' => 2000,
        'specs_with_tokens' => 1,
    ]);
});

it('writes a duration and a ratio the way they are read', function (): void {
    expect(SpecActualsService::duration(0.4))->toBe('< 1 min')
        ->and(SpecActualsService::duration(51))->toBe('51 min')
        ->and(SpecActualsService::duration(97))->toBe('1 h 37 min')
        ->and(SpecActualsService::duration(420))->toBe('7 h')
        ->and(SpecActualsService::duration(7440))->toBe('124 h')
        ->and(SpecActualsService::ratio(28.2))->toBe('28×')
        ->and(SpecActualsService::ratio(2.2))->toBe('2.2×')
        ->and(SpecActualsService::ratio(7.0))->toBe('7×')
        ->and(SpecActualsService::ratio(0.8))->toBe('0.8×');
});
