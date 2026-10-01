<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Larapilot\Services\ConfigService;
use Larapilot\Services\PlanService;
use Larapilot\Services\ReleaseService;
use Larapilot\Services\SpecService;
use Larapilot\Services\UsageService;
use Larapilot\Support\ContextManifest;
use Larapilot\Support\SpecBlockers;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function scheduleSpec(string $code, array $overrides = []): array
{
    return array_merge([
        'code' => $code,
        'title' => 'Story '.$code,
        'epic' => ['code' => 'EP-001', 'title' => 'Core', 'objective' => 'Ship the core'],
        'priority' => 'HIGH',
        'points' => 3,
        'status' => 'TODO',
        'body' => "**Epic:** EP-001 | **Priority:** HIGH | **Points:** 3 | **Status:** TODO\n**Blocked by:** -\n\n".validSpecBody(),
    ], $overrides);
}

/**
 * @param  list<array<string, mixed>>  $specs
 */
function scheduleBacklog(array $specs): void
{
    test()->artisan('larapilot:install')->assertSuccessful();
    test()->artisan('larapilot:spec-add', ['--file' => payloadFile(['specs' => $specs])])->assertSuccessful();
}

/**
 * @param  array<string, mixed>  $parameters
 * @return array<string, mixed>
 */
function scheduleRun(string $command, array $parameters = []): array
{
    $exit = Artisan::call($command, $parameters);
    $envelope = json_decode(Artisan::output(), true);

    return ['exit' => $exit, 'data' => $envelope['data'] ?? [], 'error' => $envelope['error'] ?? null];
}

it('shows the queue in delivery order with what the forecast could not read', function (): void {
    scheduleBacklog([
        scheduleSpec('US-001', ['priority' => 'LOW']),
        scheduleSpec('US-002', ['priority' => 'CRITICAL', 'body' => "**Blocked by:** US-001, US-009\n\n".validSpecBody()]),
        scheduleSpec('US-003', ['body' => validSpecBody(), 'priority' => '', 'points' => 0]),
    ]);

    $show = scheduleRun('larapilot:schedule-show');
    $data = $show['data'];
    $codes = array_column($data['findings'], 'code');

    // US-001 is LOW and blocks the CRITICAL US-002: it is delivered first.
    expect($show['exit'])->toBe(0)
        ->and(array_column($data['queue'], 'code'))->toBe(['US-001', 'US-002', 'US-003'])
        ->and($data['queue'][1]['blocked_by'])->toBe(['US-001'])
        ->and($data['queue'][0]['estimate'])->toBe('points')
        ->and($data['queue'][0]['hours'])->toEqual(12)
        ->and($data['forecast_end'])->toBe($data['queue'][2]['end'])
        ->and($data['remaining']['specs'])->toBe(3)
        ->and($data['epics'][0]['code'])->toBe('EP-001')
        ->and($codes)->toContain('SCHEDULE_UNKNOWN_BLOCKER', 'SCHEDULE_NO_BLOCKED_BY', 'SCHEDULE_NO_PRIORITY', 'SCHEDULE_NO_POINTS', 'SCHEDULE_EPIC_NO_DEADLINE', 'SCHEDULE_NO_DATES');

    $only = scheduleRun('larapilot:schedule-show', ['--only' => 'alerts,findings'])['data'];

    expect($only)->toHaveKeys(['forecast_end', 'remaining', 'alerts', 'findings'])
        ->and($only)->not->toHaveKey('queue');

    expect(scheduleRun('larapilot:schedule-show', ['--only' => 'gantt'])['exit'])->toBe(2);
});

it('forecasts a re-plan without writing it, then writes it in one batch', function (): void {
    scheduleBacklog([
        scheduleSpec('US-001'),
        scheduleSpec('US-002'),
        scheduleSpec('US-003', ['priority' => 'LOW', 'body' => validSpecBody()]),
    ]);

    app(PlanService::class)->save('US-001', planPayload());
    $this->artisan('larapilot:spec-start', ['code' => 'US-001'])->assertSuccessful();
    $this->artisan('larapilot:schedule-set', ['--deadline' => '2030-01-15', '--label' => 'Go-live'])->assertSuccessful();

    $usage = app(UsageService::class);
    $goLive = $usage->schedule()['deadlines'][0]['id'];
    $backlog = (string) file_get_contents(app(SpecService::class)->backlogPath());

    $file = payloadFile([
        'specs' => [
            ['code' => 'US-003', 'priority' => 'CRITICAL', 'points' => 8, 'blocked_by' => ['us-002']],
            ['code' => 'US-002', 'blocked_by' => []],
        ],
        'tasks' => [
            ['spec' => 'US-001', 'id' => 'TASK-01', 'estimate_hours' => 6, 'assignee' => 'Alex'],
            ['spec' => 'US-001', 'id' => 'TASK-02', 'estimate_hours' => 3, 'dependencies' => []],
        ],
        'epics' => [['code' => 'EP-001', 'deadline' => '2030-02-01']],
        'deadlines' => [
            ['id' => $goLive, 'date' => '2030-03-01', 'status' => 'at_risk'],
            ['label' => 'Beta', 'date' => '2029-12-01'],
        ],
        'note' => 'Re-planned on the forecast',
    ], 'tmp-payload-schedule.yaml');

    $dry = scheduleRun('larapilot:schedule-apply', ['--file' => $file, '--dry-run' => true]);

    // US-002 blocks the CRITICAL US-003 now, so it goes before it; US-001 is in progress and stays first.
    expect($dry['exit'])->toBe(0)
        ->and($dry['data']['dry_run'])->toBeTrue()
        ->and($dry['data']['changed'])->toBe(['specs' => 3, 'tasks' => 2, 'epics' => 1, 'deadlines' => 2, 'note' => true])
        ->and(array_column($dry['data']['after']['queue'], 'code'))->toBe(['US-001', 'US-002', 'US-003'])
        ->and($dry['data']['after']['queue'][0]['hours'])->toEqual(9)
        ->and($dry['data']['after']['epics'][0]['deadline'])->toBe('2030-02-01')
        ->and(file_get_contents(app(SpecService::class)->backlogPath()))->toBe($backlog)
        ->and($usage->schedule()['deadlines'])->toHaveCount(1)
        ->and($usage->schedule()['notes'])->toBe([]);

    $applied = scheduleRun('larapilot:schedule-apply', ['--file' => $file]);
    $specs = app(SpecService::class);
    $third = $specs->find('US-003');
    $tasks = collect(app(PlanService::class)->read('US-001')['tasks'])->keyBy('id');
    $schedule = $usage->schedule();

    expect($applied['exit'])->toBe(0)
        ->and($applied['data']['dry_run'])->toBeFalse()
        ->and($third['priority'])->toBe('CRITICAL')
        ->and($third['points'])->toBe(8)
        ->and(SpecBlockers::read($third['body']))->toBe(['US-002'])
        ->and($third['body'])->toContain('**User Story**')
        ->and($third['epic']['deadline'])->toBe('2030-02-01')
        ->and($specs->find('US-002')['body'])->toContain('**Epic:** EP-001 | **Priority:** HIGH | **Points:** 3')
        ->and($specs->find('US-001')['status'])->toBe('IN PROGRESS')
        ->and($tasks['TASK-01']['estimate_hours'])->toEqual(6)
        ->and($tasks['TASK-01']['assignee'])->toBe('Alex')
        ->and($tasks['TASK-02']['dependencies'])->toBe([])
        ->and(array_column($schedule['deadlines'], 'date', 'label'))->toBe(['Go-live' => '2030-03-01', 'Beta' => '2029-12-01'])
        ->and($schedule['deadlines'][0]['status'])->toBe('at_risk')
        ->and($schedule['notes'][0]['message'])->toBe('Re-planned on the forecast');

    $removed = scheduleRun('larapilot:schedule-apply', [
        '--file' => payloadFile(['deadlines' => [['id' => $goLive, 'remove' => true]]], 'tmp-payload-schedule.yaml'),
    ]);

    expect($removed['exit'])->toBe(0)
        ->and(array_column($usage->schedule()['deadlines'], 'label'))->toBe(['Beta']);
});

it('refuses a re-plan with a wrong row and writes none of it', function (): void {
    scheduleBacklog([
        scheduleSpec('US-001', ['body' => "**Blocked by:** US-002\n\n".validSpecBody()]),
        scheduleSpec('US-002'),
    ]);

    $backlog = (string) file_get_contents(app(SpecService::class)->backlogPath());

    $result = scheduleRun('larapilot:schedule-apply', ['--file' => payloadFile([
        'specs' => [
            ['code' => 'US-001', 'priority' => 'HIGH'],
            ['code' => 'US-002', 'blocked_by' => ['US-001']],
            ['code' => 'US-009', 'priority' => 'LOW'],
            ['code' => 'US-002', 'priority' => 'URGENT'],
        ],
        'tasks' => [['spec' => 'US-001', 'id' => 'TASK-01', 'estimate_hours' => 2]],
        'epics' => [['code' => 'EP-404', 'deadline' => '2030-01-01'], ['code' => 'EP-001', 'deadline' => '2030-13-01']],
        'deadlines' => [['id' => 'nope', 'date' => '2030-01-01'], ['label' => 'No date'], ['label' => 'Scoped', 'date' => '2030-01-01', 'release' => '9.9.9']],
    ], 'tmp-payload-schedule.yaml')]);

    expect($result['exit'])->toBe(2)
        ->and(array_column($result['error']['details']['findings'], 'code'))->toBe([
            'SCHEDULE_UNKNOWN_SPEC',
            'SCHEDULE_INVALID_PRIORITY',
            'SCHEDULE_UNKNOWN_EPIC',
            'SCHEDULE_INVALID_DATE',
            'SCHEDULE_UNKNOWN_TASK',
            'SCHEDULE_UNKNOWN_DEADLINE',
            'SCHEDULE_INVALID_DATE',
            'SCHEDULE_UNKNOWN_RELEASE',
            'SCHEDULE_BLOCKER_CYCLE',
        ])
        ->and(file_get_contents(app(SpecService::class)->backlogPath()))->toBe($backlog);

    expect(scheduleRun('larapilot:schedule-apply', ['--file' => payloadFile([], 'tmp-payload-schedule.yaml')])['exit'])->toBe(2);
});

it('measures a milestone that names a release against that release', function (): void {
    scheduleBacklog([
        scheduleSpec('US-001', ['priority' => 'CRITICAL']),
        scheduleSpec('US-002', ['points' => 40]),
    ]);

    app(ConfigService::class)->updateSettings(['release_mode' => true]);
    app(ReleaseService::class)->add('0.1.0', 'First', 'planned', ['US-001']);

    $first = scheduleRun('larapilot:schedule-show')['data']['queue'][0]['end'];
    $date = date('Y-m-d', strtotime($first.' +3 days'));

    $this->artisan('larapilot:schedule-set', ['--deadline' => $date, '--label' => 'Whole backlog'])->assertSuccessful();
    $this->artisan('larapilot:schedule-set', ['--deadline' => $date, '--label' => 'First release', '--release' => 'v0.1.0'])->assertSuccessful();
    $this->artisan('larapilot:schedule-set', ['--deadline' => $date, '--label' => 'Ghost', '--release' => '9.9.9'])->assertExitCode(2);

    $data = scheduleRun('larapilot:schedule-show')['data'];
    $deadlines = collect($data['deadlines'])->keyBy('label');

    expect($data['releases'][0])->toMatchArray(['version' => '0.1.0', 'specs' => 1, 'forecast_end' => $first])
        ->and($deadlines['First release']['release'])->toBe('0.1.0')
        ->and($deadlines['First release']['forecast_end'])->toBe($first)
        ->and($deadlines['First release']['slip_days'])->toBe(0)
        ->and($deadlines['Whole backlog']['slip_days'])->toBeGreaterThan(0)
        ->and(array_column($data['alerts'], 'label'))->toBe(['Whole backlog'])
        ->and(array_column($data['findings'], 'path', 'code')['SCHEDULE_STALE_STATUS'])->toBe('Whole backlog');
});

it('realigns the schedule of an existing project when Larapilot is updated', function (): void {
    scheduleBacklog([
        scheduleSpec('US-001', ['epic' => ['code' => 'EP-001', 'title' => 'Core', 'objective' => 'Ship the core', 'deadline' => '2030-06-30']]),
        scheduleSpec('US-002'),
    ]);

    app(ConfigService::class)->updateSettings(['release_mode' => true]);
    app(ReleaseService::class)->add('0.1.0', 'First', 'planned', ['US-001']);

    // What an earlier version, or a hand, left: no id, a date YAML reads as a timestamp, a release only in the label.
    $usage = app(UsageService::class);
    $path = $usage->schedulePath();
    file_put_contents($path, "deadlines:\n  -\n    label: 'Release 0.1.0'\n    date: 2030-01-15\n    status: on_track\n");
    $written = (string) file_get_contents($path);

    $dry = scheduleRun('larapilot:schedule-apply', ['--repair' => true, '--dry-run' => true]);

    expect($dry['exit'])->toBe(0)
        ->and(array_column($dry['data']['fixes'], 'code'))->toBe([
            'SCHEDULE_FIX_DEADLINE_ID',
            'SCHEDULE_FIX_DEADLINE_DATE',
            'SCHEDULE_FIX_DEADLINE_RELEASE',
            'SCHEDULE_FIX_EPIC_DEADLINE',
        ])
        ->and(file_get_contents($path))->toBe($written);

    $this->artisan('larapilot:update', ['--skip-boost' => true])
        ->expectsOutputToContain('Delivery forecast realigned: 4 fix(es).')
        ->expectsOutputToContain('every date holds')
        ->assertSuccessful();

    $deadline = $usage->schedule()['deadlines'][0];

    expect($deadline['id'])->not->toBe('')
        ->and($deadline['date'])->toBe('2030-01-15')
        ->and($deadline['release'])->toBe('0.1.0')
        ->and(app(SpecService::class)->find('US-002')['epic']['deadline'])->toBe('2030-06-30')
        ->and(scheduleRun('larapilot:schedule-apply', ['--repair' => true])['data']['fixes'])->toBe([]);
});

it('says at the update what the forecast of an existing project misses', function (): void {
    scheduleBacklog([scheduleSpec('US-001', ['points' => 40])]);

    $this->artisan('larapilot:schedule-set', ['--deadline' => date('Y-m-d', strtotime('+2 days')), '--label' => 'Go-live'])->assertSuccessful();

    $this->artisan('larapilot:update', ['--skip-boost' => true])
        ->expectsOutputToContain('1 date(s) do not hold and 1 finding(s) need a decision. Re-plan with /larapilot-schedule')
        ->assertSuccessful();
});

it('writes the blockers of a spec where its body keeps them', function (): void {
    expect(SpecBlockers::read('No line here'))->toBeNull()
        ->and(SpecBlockers::read("**Blocked by:** -\nText"))->toBe([])
        ->and(SpecBlockers::read('- **Blocked by:** us-004 (Invoice detail), US-010'))->toBe(['US-004', 'US-010'])
        ->and(SpecBlockers::write("**Blocked by:** US-001\nText", []))->toBe("**Blocked by:** -\nText")
        ->and(SpecBlockers::write("Title\n**Epic:** EP-001 | **Priority:** HIGH\nText", ['US-002', 'US-003']))
        ->toBe("Title\n**Epic:** EP-001 | **Priority:** HIGH\n**Blocked by:** US-002, US-003\nText")
        ->and(SpecBlockers::write('Text', ['US-002']))->toBe("**Blocked by:** US-002\n\nText");
});

it('ships the schedule skill with the commands it runs', function (): void {
    $skill = (string) file_get_contents(dirname(__DIR__, 2).'/resources/boost/skills/larapilot-schedule/SKILL.md');

    expect($skill)->toStartWith("---\nname: larapilot-schedule\n")
        ->and($skill)->toContain('larapilot:context schedule', 'larapilot:schedule-show', 'larapilot:schedule-apply', '--dry-run')
        ->and(strlen($skill))->toBeLessThanOrEqual(12000)
        ->and(ContextManifest::skills())->toHaveKey('schedule');
});
