<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Larapilot\Services\ConfigService;
use Larapilot\Services\HookService;
use Larapilot\Services\PrdService;
use Larapilot\Services\SpecService;
use Symfony\Component\Yaml\Yaml;

/**
 * Write `.larapilot/hooks.yaml`.
 *
 * @param  array<string, mixed>  $hooks
 */
function writeHooks(array $hooks): void
{
    file_put_contents(base_path('.larapilot/hooks.yaml'), Yaml::dump(['hooks' => $hooks], 6, 2));
}

function enableHooks(): void
{
    test()->artisan('larapilot:settings-set', ['--hooks' => 'YES'])->assertSuccessful();
}

/**
 * Run a command and return its envelope, success or error.
 *
 * @param  array<string, mixed>  $parameters
 * @return array{0: int, 1: array<string, mixed>}
 */
function hookCall(string $command, array $parameters = []): array
{
    $exit = Artisan::call($command, $parameters);
    $decoded = json_decode(trim(Artisan::output()), true);

    return [$exit, is_array($decoded) ? $decoded : []];
}

function specStatus(string $code = 'US-001'): string
{
    app()->forgetInstance(SpecService::class);

    return (string) (app(SpecService::class)->find($code)['status'] ?? '');
}

it('scaffolds a hooks file that runs nothing, and leaves every answer as it was while hooks are off', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $scaffold = (string) file_get_contents(base_path('.larapilot/hooks.yaml'));

    expect($scaffold)->toContain("hooks:\n#  task.done:")
        ->and(app(HookService::class)->definitions())->toMatchArray(['exists' => true, 'ok' => true, 'hooks' => []]);

    foreach (array_keys(HookService::EVENTS) as $event) {
        expect($scaffold)->toContain($event);
    }

    // A team's file survives an update.
    writeHooks(['task.done' => ['before' => [['run' => 'exit 1']]]]);
    $this->artisan('larapilot:update', ['--skip-boost' => true])->assertSuccessful();

    expect((string) file_get_contents(base_path('.larapilot/hooks.yaml')))->toContain('exit 1');

    // Off by default: the failing hook never runs, the answer has no `hooks`.
    expect(app(ConfigService::class)->settings()['hooks'])->toBe('NO');

    addSpec();
    planSpec();
    $this->artisan('larapilot:spec-start', ['code' => 'US-001'])->assertSuccessful();

    [$exit, $envelope] = hookCall('larapilot:task-done', ['code' => 'US-001', 'taskId' => 'TASK-01']);

    expect($exit)->toBe(0)
        ->and($envelope['data'])->not->toHaveKey('hooks');
});

it('blocks a transition when a before hook fails, and writes nothing', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableHooks();
    addSpec();
    planSpec();

    writeHooks(['spec.started' => ['before' => [
        ['name' => 'Tests', 'run' => 'echo "3 tests failed"; exit 3'],
        ['name' => 'Never reached', 'run' => 'touch .larapilot/reached'],
    ]]]);

    [$exit, $envelope] = hookCall('larapilot:spec-start', ['code' => 'US-001']);

    expect($exit)->toBe(4)
        ->and($envelope['kind'])->toBe('error')
        ->and($envelope['error']['code'])->toBe('E_PRECONDITION')
        ->and($envelope['error']['message'])->toBe('Hook "Tests" (before spec.started) failed with exit code 3.')
        ->and($envelope['error']['hint'])->toContain('never edit it, or turn hooks off')
        ->and($envelope['error']['details']['hooks']['event'])->toBe('spec.started');

    $ran = $envelope['error']['details']['hooks']['before']['ran'];

    expect($ran)->toHaveCount(1)
        ->and($ran[0])->toMatchArray(['name' => 'Tests', 'ok' => false, 'exit_code' => 3, 'timed_out' => false, 'blocking' => true])
        ->and($ran[0]['output'])->toBe('3 tests failed')
        ->and((string) file_get_contents(base_path($ran[0]['log'])))->toContain('3 tests failed')
        ->and(base_path('.larapilot/reached'))->not->toBeFile()
        ->and(specStatus())->toBe('PLANNED')
        ->and((string) file_get_contents(base_path('.larapilot/cache/.gitignore')))->toBe("*\n");

    // Fixed: the same command goes through.
    writeHooks(['spec.started' => ['before' => [['name' => 'Tests', 'run' => 'echo ok']]]]);

    [$exit, $envelope] = hookCall('larapilot:spec-start', ['code' => 'US-001']);

    expect($exit)->toBe(0)
        ->and($envelope['data']['hooks']['before']['ran'][0]['ok'])->toBeTrue()
        ->and(specStatus())->toBe('IN PROGRESS');
});

it('tells a run hook the event in its environment and as JSON on stdin', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableHooks();
    addSpec();
    planSpec();
    $this->artisan('larapilot:spec-start', ['code' => 'US-001'])->assertSuccessful();

    writeHooks(['task.done' => ['after' => [[
        'run' => 'printf "%s|%s|%s|%s|%s" "$LARAPILOT_HOOK_EVENT" "$LARAPILOT_HOOK_PHASE" "$LARAPILOT_HOOK_SPEC" "$LARAPILOT_HOOK_TASK" "$LARAPILOT_HOOK_SPEC_TITLE" > .larapilot/hook-env.txt; cat > .larapilot/hook-stdin.json',
    ]]]]);

    [$exit, $envelope] = hookCall('larapilot:task-done', ['code' => 'US-001', 'taskId' => 'TASK-01']);

    expect($exit)->toBe(0)
        ->and($envelope['data']['status'])->toBe('DONE')
        ->and($envelope['data']['hooks']['after']['ran'][0]['ok'])->toBeTrue()
        ->and((string) file_get_contents(base_path('.larapilot/hook-env.txt')))->toBe('task.done|after|US-001|TASK-01|Login');

    $stdin = json_decode((string) file_get_contents(base_path('.larapilot/hook-stdin.json')), true);

    expect($stdin['schema'])->toBe('larapilot/v1')
        ->and($stdin['kind'])->toBe('hook_event')
        ->and($stdin['data'])->toMatchArray(['event' => 'task.done', 'phase' => 'after', 'task' => 'TASK-01'])
        ->and($stdin['data']['spec'])->toMatchArray(['code' => 'US-001', 'title' => 'Login', 'status' => 'IN PROGRESS', 'priority' => 'HIGH'])
        ->and($stdin['data'])->toHaveKey('commit');

    // A task the plan does not have fails as it always did, and runs no hook.
    writeHooks(['task.done' => ['before' => [['run' => 'touch .larapilot/reached']]]]);

    [$exit] = hookCall('larapilot:task-done', ['code' => 'US-001', 'taskId' => 'TASK-99']);

    expect($exit)->toBe(4)
        ->and(base_path('.larapilot/reached'))->not->toBeFile();
});

it('reports an after hook that fails, and a before hook that only warns, without blocking', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableHooks();
    addSpec();
    planSpec();
    $this->artisan('larapilot:spec-start', ['code' => 'US-001'])->assertSuccessful();
    completeTasks();

    writeHooks([
        'spec.review' => ['before' => [['name' => 'Lint', 'run' => 'exit 1', 'blocking' => false]]],
        'spec.approved' => ['after' => [['name' => 'Deploy to staging', 'run' => 'exit 2']]],
    ]);

    [$exit, $envelope] = hookCall('larapilot:spec-review', ['code' => 'US-001']);

    expect($exit)->toBe(0)
        ->and(specStatus())->toBe('REVIEW')
        ->and($envelope['data']['hooks']['before']['warnings'])->toBe(['Hook "Lint" (before spec.review) failed with exit code 1; it does not block.']);

    [$exit, $envelope] = hookCall('larapilot:spec-approve', ['code' => 'US-001']);

    expect($exit)->toBe(0)
        ->and(specStatus())->toBe('DONE')
        ->and($envelope['data']['hooks']['after']['ran'][0]['exit_code'])->toBe(2)
        ->and($envelope['data']['hooks']['after']['warnings'])->toBe(['Hook "Deploy to staging" (after spec.approved) failed with exit code 2; the transition stands.']);
});

it('refuses a transition until its before skill hooks are reported done, and lists the after ones', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableHooks();
    addSpec();
    planSpec();
    $this->artisan('larapilot:spec-start', ['code' => 'US-001'])->assertSuccessful();
    completeTasks();

    writeHooks([
        'spec.review' => ['before' => [
            ['skill' => '/acme-a11y-check'],
            ['run' => 'touch .larapilot/reached'],
        ]],
        'spec.approved' => ['after' => [['skill' => 'acme-release-notes']]],
    ]);

    [$exit, $envelope] = hookCall('larapilot:spec-review', ['code' => 'US-001']);

    expect($exit)->toBe(4)
        ->and($envelope['error']['message'])->toBe('The project runs /acme-a11y-check before spec.review, and it was not reported done.')
        ->and($envelope['error']['hint'])->toContain('--skill-hooks-done=acme-a11y-check')
        ->and($envelope['error']['details']['hooks']['before']['skills'][0])->toMatchArray([
            'skill' => 'acme-a11y-check',
            'instruction' => 'Run /acme-a11y-check now (before spec.review, US-001), then go on.',
        ])
        ->and(base_path('.larapilot/reached'))->not->toBeFile()
        ->and(specStatus())->toBe('IN PROGRESS');

    [$exit, $envelope] = hookCall('larapilot:spec-review', ['code' => 'US-001', '--skill-hooks-done' => 'acme-a11y-check']);

    expect($exit)->toBe(0)
        ->and(base_path('.larapilot/reached'))->toBeFile()
        ->and(specStatus())->toBe('REVIEW');

    [$exit, $envelope] = hookCall('larapilot:spec-approve', ['code' => 'US-001']);

    expect($exit)->toBe(0)
        ->and($envelope['data']['hooks']['after']['skills'][0]['instruction'])->toBe('Run /acme-release-notes now (after spec.approved, US-001), then go on.');
});

it('stops a hook at its timeout', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableHooks();
    addSpec();
    planSpec();
    $this->artisan('larapilot:spec-start', ['code' => 'US-001'])->assertSuccessful();

    writeHooks(['task.done' => ['before' => [['name' => 'Slow', 'run' => 'sleep 3', 'timeout' => 1]]]]);

    [$exit, $envelope] = hookCall('larapilot:task-done', ['code' => 'US-001', 'taskId' => 'TASK-01']);

    expect($exit)->toBe(4)
        ->and($envelope['error']['message'])->toBe('Hook "Slow" (before task.done) did not finish within 1s.')
        ->and($envelope['error']['details']['hooks']['before']['ran'][0])->toMatchArray(['timed_out' => true, 'exit_code' => null]);
});

it('refuses every transition while the hooks file has errors, and says so in hook-list and doctor', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    planSpec();

    writeHooks([
        'task_done' => ['before' => [['run' => 'exit 0']]],
        'spec.review' => ['after' => [['run' => 'echo hi', 'blocking' => true, 'comand' => 'x'], ['skill' => 'nobody-wrote-this']]],
        'spec.approved' => ['before' => [['run' => 'a', 'skill' => 'b'], ['run' => 'echo', 'timeout' => 99999]]],
    ]);

    [$exit, $listing] = hookCall('larapilot:hook-list');
    $codes = array_column($listing['data']['findings'], 'code');

    expect($exit)->toBe(2)
        ->and($listing['data']['ok'])->toBeFalse()
        ->and($codes)->toContain('HOOKS_UNKNOWN_EVENT', 'HOOKS_NO_ACTION', 'HOOKS_BAD_TIMEOUT', 'HOOKS_BLOCKING_AFTER', 'HOOKS_UNKNOWN_KEY', 'HOOKS_UNKNOWN_SKILL');

    // Off: the file is not read by the transitions, and doctor does not mind.
    [$exit, $doctor] = hookCall('larapilot:doctor');

    expect($doctor['data']['checks']['hooks'])->toBeTrue();
    $this->artisan('larapilot:spec-start', ['code' => 'US-001'])->assertSuccessful();

    enableHooks();

    [$exit, $envelope] = hookCall('larapilot:task-done', ['code' => 'US-001', 'taskId' => 'TASK-01']);

    expect($exit)->toBe(4)
        ->and($envelope['error']['message'])->toBe('.larapilot/hooks.yaml has errors, so no hook of it can be trusted.')
        ->and(array_column($envelope['error']['details']['hooks']['before']['findings'], 'code'))->toContain('HOOKS_UNKNOWN_EVENT')
        ->and(array_column($envelope['error']['details']['hooks']['before']['findings'], 'code'))->not->toContain('HOOKS_UNKNOWN_SKILL');

    [$exit, $doctor] = hookCall('larapilot:doctor');

    expect($doctor['data']['checks']['hooks'])->toBeFalse();

    // Not valid YAML at all.
    file_put_contents(base_path('.larapilot/hooks.yaml'), "hooks:\n  task.done: [unclosed\n");

    expect(app(HookService::class)->definitions()['findings'][0]['code'])->toBe('HOOKS_PARSE_ERROR');
});

it('runs no hook on a machine that turned them off', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableHooks();
    addSpec();
    planSpec();

    writeHooks(['spec.started' => ['before' => [['run' => 'exit 1']]]]);
    config(['larapilot.hooks.enabled' => false]);

    [$exit, $envelope] = hookCall('larapilot:spec-start', ['code' => 'US-001']);

    expect($exit)->toBe(0)
        ->and($envelope['data'])->not->toHaveKey('hooks');

    [, $listing] = hookCall('larapilot:hook-list');

    expect($listing['data'])->toMatchArray(['active' => false, 'setting' => 'YES', 'machine_enabled' => false, 'count' => 1]);
});

it('runs the hooks of the PRD, the backlog, and a release', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableHooks();

    writeHooks([
        'prd.written' => ['before' => [['run' => 'exit 1']]],
        'spec.added' => ['after' => [['run' => 'printf "%s" "$LARAPILOT_HOOK_SPEC" > .larapilot/hook-specs.txt']]],
        'release.shipped' => ['before' => [['name' => 'Changelog', 'run' => 'exit 5']]],
    ]);

    [$exit] = hookCall('larapilot:prd-write', ['--content' => validPrd()]);

    expect($exit)->toBe(4)
        ->and(app(PrdService::class)->exists())->toBeFalse();

    addSpec();

    expect((string) file_get_contents(base_path('.larapilot/hook-specs.txt')))->toBe('US-001');

    // The before hook answers ahead of any Git step.
    $this->artisan('larapilot:settings-set', ['--release-mode' => 'YES'])->assertSuccessful();

    [$exit, $envelope] = hookCall('larapilot:release-ship', ['--semver' => '1.0.0']);

    expect($exit)->toBe(4)
        ->and($envelope['error']['message'])->toBe('Hook "Changelog" (before release.shipped) failed with exit code 5.');
});

it('lists the hooks of the project and fires one event by hand', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    writeHooks([
        'ship' => [
            'before' => [['skill' => 'larapilot-review'], ['name' => 'Smoke', 'run' => 'printf "%s" "$LARAPILOT_HOOK_RELEASE" > .larapilot/hook-ship.txt']],
            'after' => ['echo deployed'],
        ],
        'task.done' => ['before' => [['run' => 'php artisan test']]],
    ]);

    [$exit, $listing] = hookCall('larapilot:hook-list');

    expect($exit)->toBe(0)
        ->and($listing['kind'])->toBe('hook_list')
        ->and($listing['data'])->toMatchArray(['active' => false, 'ok' => true, 'count' => 4, 'path' => '.larapilot/hooks.yaml', 'findings' => []])
        ->and(array_keys($listing['data']['events']))->toBe(['task.done', 'ship'])
        ->and($listing['data']['events']['ship']['after'][0])->toMatchArray(['kind' => 'run', 'run' => 'echo deployed', 'name' => 'echo deployed', 'blocking' => false, 'timeout' => 300])
        ->and($listing['data']['catalog'])->toBe(HookService::EVENTS);

    [, $one] = hookCall('larapilot:hook-list', ['--event' => 'spec.review']);

    expect($one['data']['events'])->toBe(['spec.review' => ['fired_by' => 'larapilot:spec-review', 'before' => [], 'after' => []]]);
    expect(hookCall('larapilot:hook-list', ['--event' => 'nope'])[0])->toBe(2);

    // Off: hook-run runs nothing, and the dry run says what would.
    [$exit, $off] = hookCall('larapilot:hook-run', ['event' => 'ship', '--release' => '2.0.0']);

    expect($exit)->toBe(0)
        ->and($off['data'])->toMatchArray(['active' => false, 'ran' => []])
        ->and(base_path('.larapilot/hook-ship.txt'))->not->toBeFile();

    [, $dry] = hookCall('larapilot:hook-run', ['event' => 'ship', '--release' => '2.0.0', '--spec' => 'US-001', '--dry-run' => true]);

    expect($dry['data']['dry_run'])->toBeTrue()
        ->and($dry['data']['would_run'])->toHaveCount(2)
        ->and($dry['data']['environment'])->toMatchArray(['LARAPILOT_HOOK_EVENT' => 'ship', 'LARAPILOT_HOOK_RELEASE' => '2.0.0', 'LARAPILOT_HOOK_SPEC' => 'US-001'])
        ->and(base_path('.larapilot/hook-ship.txt'))->not->toBeFile();

    enableHooks();

    // A skill hook is listed, never enforced, by hook-run: the skill runs it.
    [$exit, $run] = hookCall('larapilot:hook-run', ['event' => 'ship', '--release' => '2.0.0']);

    expect($exit)->toBe(0)
        ->and($run['data']['skills'][0]['skill'])->toBe('larapilot-review')
        ->and($run['data']['ran'][0]['ok'])->toBeTrue()
        ->and((string) file_get_contents(base_path('.larapilot/hook-ship.txt')))->toBe('2.0.0')
        ->and(specStatus())->toBe('TODO');

    expect(hookCall('larapilot:hook-run', ['event' => 'ship', '--phase' => 'after'])[1]['data']['ran'][0]['output'])->toBe('deployed')
        ->and(hookCall('larapilot:hook-run', ['event' => 'nope'])[0])->toBe(2)
        ->and(hookCall('larapilot:hook-run', ['event' => 'ship', '--phase' => 'during'])[0])->toBe(2);

    writeHooks(['ship' => ['before' => [['run' => 'exit 7']]]]);

    expect(hookCall('larapilot:hook-run', ['event' => 'ship'])[0])->toBe(4);
});

it('persists the hooks setting and shows the hooks on the dashboard', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    expect(hookCall('larapilot:settings-set', ['--hooks' => 'maybe'])[0])->toBe(2);

    enableHooks();

    expect(app(ConfigService::class)->hooksEnabled())->toBeTrue()
        ->and(app(ConfigService::class)->setupInfo()['paths']['hooks'])->toBe(base_path('.larapilot/hooks.yaml'));

    writeHooks(['spec.approved' => ['after' => [['name' => 'Deploy to staging', 'run' => 'curl -fsS -X POST "$DEPLOY_URL"']]]]);

    $this->get('/larapilot/settings')
        ->assertOk()
        ->assertSee('Workflow hooks', false)
        ->assertSee('id="settings-automation"', false)
        ->assertSee('spec.approved', false)
        ->assertSee('Deploy to staging', false)
        ->assertSee('larapilot:hook-list', false);
});

it('gives the hooks pack to the skills that fire events, only while hooks are on', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $reads = static function (string $skill): array {
        expect(Artisan::call('larapilot:context', ['skill' => $skill, '--fresh' => true]))->toBe(0);
        $data = json_decode(Artisan::output(), true)['data'];

        return [array_column($data['runtime']['read'], 'file'), array_column($data['runtime']['on_demand'], 'file')];
    };

    expect($reads('implement')[0])->not->toContain('hooks.md')
        ->and($reads('settings')[1])->toContain('hooks.md')
        ->and($reads('custom-skill')[1])->toContain('hooks.md');

    enableHooks();

    foreach (['inception', 'feature', 'bug', 'prd', 'spec', 'plan', 'implement', 'review', 'autopilot', 'ship', 'release'] as $skill) {
        expect($reads($skill)[0])->toContain('hooks.md');
    }

    expect($reads('triage')[0])->not->toContain('hooks.md');
});
