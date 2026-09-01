<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DecisionService;
use Larapilot\Services\PlanService;
use Larapilot\Services\SpecService;

it('installs the project scaffolding', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    expect(base_path('.larapilot/config.yaml'))->toBeFile()
        ->and(base_path('.larapilot/shared-runtime.md'))->toBeFile()
        ->and(base_path('.larapilot/integrations.md'))->toBeFile()
        ->and(base_path('.larapilot/task-templates.md'))->toBeFile()
        ->and(base_path('.larapilot/runtime-discovery.md'))->toBeFile()
        ->and(base_path('.larapilot/runtime-delivery.md'))->toBeFile()
        ->and(base_path('.larapilot/runtime-ux.md'))->toBeFile()
        ->and(base_path('.larapilot/runtime-ship.md'))->toBeFile()
        ->and(base_path('.larapilot/runtime-ops.md'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/filament/tokens.css'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/filament/html/index.html'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/filament/figma-sources.md'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/filament/html/widgets-dashboard.html'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/starter-kit/tokens.css'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/starter-kit/html/index.html'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/starter-kit/sources.md'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/starter-kit/html/dashboard.html'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/bootstrap-5/tokens.css'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/bootstrap-5/html/index.html'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/tailwind/html/index.html'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/tailwind/html/landing.html'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/tailwind/html/settings.html'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/adminlte/tokens.css'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/adminlte/html/index.html'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/adminlte/html/dashboard.html'))->toBeFile()
        ->and(base_path('.larapilot/specs/.gitkeep'))->toBeFile()
        ->and(base_path('.larapilot/plans/.gitkeep'))->toBeFile()
        ->and(base_path('.larapilot/mockups/.gitkeep'))->toBeFile()
        ->and(base_path('.larapilot/docs/test-results/.gitkeep'))->toBeFile()
        ->and(base_path('.larapilot/research/reference-products/.gitkeep'))->toBeFile()
        ->and(base_path('phpstan.neon.dist'))->toBeFile()
        ->and(base_path('pint.json'))->toBeFile();
});

it('refuses to reinstall without force', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:install')
        ->assertExitCode(4)
        ->expectsOutputToContain('larapilot:update');
    $this->artisan('larapilot:install', ['--force' => true])->assertSuccessful();
});

it('refuses to update before install', function (): void {
    $this->artisan('larapilot:update')
        ->assertExitCode(4)
        ->expectsOutputToContain('larapilot:install');
});

it('refreshes the shared runtime via update', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    file_put_contents(base_path('.larapilot/shared-runtime.md'), 'stale copy from an older release');
    file_put_contents(base_path('.larapilot/design-systems/filament/tokens.css'), 'stale tokens');

    $this->artisan('larapilot:update', ['--skip-boost' => true])->assertSuccessful();

    expect(file_get_contents(base_path('.larapilot/shared-runtime.md')))
        ->toBe(file_get_contents(dirname(__DIR__, 2).'/resources/larapilot/shared-runtime.md'))
        ->and(file_get_contents(base_path('.larapilot/task-templates.md')))
        ->toBe(file_get_contents(dirname(__DIR__, 2).'/resources/larapilot/task-templates.md'))
        ->and(file_get_contents(base_path('.larapilot/runtime-ops.md')))
        ->toBe(file_get_contents(dirname(__DIR__, 2).'/resources/larapilot/runtime-ops.md'))
        ->and(file_get_contents(base_path('.larapilot/design-systems/filament/tokens.css')))
        ->toBe(file_get_contents(dirname(__DIR__, 2).'/resources/larapilot/design-systems/filament/tokens.css'));
});

it('keeps project config untouched during update', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    file_put_contents(base_path('.larapilot/config.yaml'), "connector: file\ncustom: kept\n");

    $this->artisan('larapilot:update', ['--skip-boost' => true])->assertSuccessful();

    expect(file_get_contents(base_path('.larapilot/config.yaml')))->toContain('custom: kept');
});

it('fails update when boost:update is unavailable', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:update')
        ->assertExitCode(4)
        ->expectsOutputToContain('boost:install');
});

it('reports installation health via doctor', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:doctor')
        ->assertSuccessful()
        ->expectsOutputToContain('"healthy":true');
});

it('writes and validates a prd', function (): void {
    $this->artisan('larapilot:prd-write', ['--content' => validPrd()])->assertSuccessful();

    $this->artisan('larapilot:validate-prd')->assertSuccessful();
});

it('fails prd validation when sections are missing', function (): void {
    $this->artisan('larapilot:prd-write', ['--content' => '# Just a title'])->assertSuccessful();

    $this->artisan('larapilot:validate-prd')
        ->assertExitCode(2)
        ->expectsOutputToContain('PRD_MISSING_SECTION');
});

it('adds specs and lists them', function (): void {
    addSpec();

    $this->artisan('larapilot:spec-list')
        ->assertSuccessful()
        ->expectsOutputToContain('US-001');

    $this->artisan('larapilot:spec-list', ['--status' => 'DONE'])
        ->assertSuccessful()
        ->expectsOutputToContain('"count":0');
});

it('rejects an invalid specs payload with exit code 2', function (): void {
    $file = payloadFile(specsPayload(['body' => 'Just prose mentioning User Story in passing.']));

    $this->artisan('larapilot:spec-add', ['--file' => $file])->assertExitCode(2);

    expect(app(SpecService::class)->allSpecs())->toBeEmpty();
});

it('defaults spec status to todo when missing', function (): void {
    addSpec(['status' => null]);

    expect(app(SpecService::class)->find('US-001')['status'])->toBe('TODO');
});

it('shows a spec and 404s on unknown codes', function (): void {
    addSpec();

    $this->artisan('larapilot:spec-show', ['code' => 'US-001'])
        ->assertSuccessful()
        ->expectsOutputToContain('"spec_detail"');

    $this->artisan('larapilot:spec-show', ['code' => 'US-999'])->assertExitCode(4);
});

it('selects the next spec by priority then code', function (): void {
    addSpec(['code' => 'US-002', 'priority' => 'HIGH']);
    addSpec(['code' => 'US-003', 'priority' => 'CRITICAL']);
    addSpec(['code' => 'US-001', 'priority' => 'CRITICAL']);

    $this->artisan('larapilot:spec-next')
        ->assertSuccessful()
        ->expectsOutputToContain('"code":"US-001"');
});

it('fails spec-next when nothing is eligible', function (): void {
    $this->artisan('larapilot:spec-next')->assertExitCode(4);
});

it('validates spec and plan payloads without persisting', function (): void {
    $this->artisan('larapilot:validate-spec', ['--file' => payloadFile(specsPayload())])
        ->assertSuccessful();

    $this->artisan('larapilot:validate-plan', ['code' => 'US-001', '--file' => payloadFile(planPayload(), 'tmp-plan.yaml')])
        ->assertSuccessful();

    expect(app(SpecService::class)->allSpecs())->toBeEmpty();
});

it('exits non-zero when validating an invalid payload', function (): void {
    $invalid = payloadFile(['specs' => [['code' => 'US-001']]]);

    $this->artisan('larapilot:validate-spec', ['--file' => $invalid])->assertExitCode(2);

    $noTasks = payloadFile(['plan_body' => 'x', 'tasks' => []], 'tmp-plan.yaml');

    $this->artisan('larapilot:validate-plan', ['code' => 'US-001', '--file' => $noTasks])->assertExitCode(2);
});

it('rejects plan payloads with unknown task dependencies', function (): void {
    $payload = planPayload();
    $payload['tasks'][1]['dependencies'] = ['TASK-99'];

    $this->artisan('larapilot:validate-plan', ['code' => 'US-001', '--file' => payloadFile($payload, 'tmp-plan.yaml')])
        ->assertExitCode(2)
        ->expectsOutputToContain('TASK_UNKNOWN_DEPENDENCY');
});

it('plans a spec and stores the plan file', function (): void {
    addSpec();
    planSpec();

    $specs = app(SpecService::class);

    expect($specs->find('US-001')['status'])->toBe('PLANNED')
        ->and(app(PlanService::class)->read('US-001')['tasks'])->toHaveCount(2);
});

it('keeps spec status untouched when the plan payload is invalid', function (): void {
    addSpec();

    $this->artisan('larapilot:spec-plan', ['code' => 'US-001', '--file' => payloadFile(['plan_body' => '', 'tasks' => []], 'tmp-plan.yaml')])
        ->assertExitCode(2);

    expect(app(SpecService::class)->find('US-001')['status'])->toBe('TODO');
});

it('enforces workflow transitions', function (): void {
    addSpec();

    $this->artisan('larapilot:spec-start', ['code' => 'US-001'])->assertExitCode(4);
    $this->artisan('larapilot:spec-review', ['code' => 'US-001'])->assertExitCode(4);
    $this->artisan('larapilot:spec-approve', ['code' => 'US-001'])->assertExitCode(4);

    $feedback = payloadFile(['markdown' => 'nope'], 'tmp-feedback.yaml');
    $this->artisan('larapilot:spec-request-changes', ['code' => 'US-001', '--file' => $feedback])->assertExitCode(4);

    expect(app(SpecService::class)->find('US-001')['status'])->toBe('TODO');
});

it('refuses to re-plan a spec that is in review or done', function (): void {
    addSpec(['status' => 'REVIEW']);

    $this->artisan('larapilot:spec-plan', ['code' => 'US-001', '--file' => payloadFile(planPayload(), 'tmp-plan.yaml')])
        ->assertExitCode(4);
});

it('marks plan tasks as done', function (): void {
    addSpec();
    planSpec();

    initTestGitRepository('feat(US-001): TASK-01 Create model');

    $this->artisan('larapilot:task-done', ['code' => 'US-001', 'taskId' => 'TASK-01'])->assertSuccessful();

    $tasks = app(PlanService::class)->read('US-001')['tasks'];

    expect($tasks[0]['status'])->toBe('DONE')
        ->and($tasks[0]['commit']['subject'] ?? null)->toBe('feat(US-001): TASK-01 Create model')
        ->and($tasks[1]['status'])->toBe('TODO');

    $this->artisan('larapilot:task-done', ['code' => 'US-001', 'taskId' => 'TASK-99'])->assertExitCode(4);
});

it('links an explicit commit when marking a task done', function (): void {
    addSpec();
    planSpec();

    $sha = initTestGitRepository('chore: unrelated commit');

    $this->artisan('larapilot:task-done', [
        'code' => 'US-001',
        'taskId' => 'TASK-02',
        '--commit' => $sha,
    ])->assertSuccessful();

    $task = app(PlanService::class)->read('US-001')['tasks'][1];

    expect($task['status'])->toBe('DONE')
        ->and($task['commit']['sha'])->toBe($sha);
});

it('links the merge commit when approving a spec', function (): void {
    addSpec();
    planSpec();

    $this->artisan('larapilot:spec-start', ['code' => 'US-001'])->assertSuccessful();
    completeTasks();
    $this->artisan('larapilot:spec-review', ['code' => 'US-001'])->assertSuccessful();

    initTestGitRepository('Merge pull request #99 from user/feature/US-001-login');

    $this->artisan('larapilot:spec-approve', ['code' => 'US-001'])->assertSuccessful();

    $spec = app(SpecService::class)->find('US-001');

    expect($spec['status'])->toBe('DONE')
        ->and($spec['merge_commit']['subject'] ?? null)->toBe('Merge pull request #99 from user/feature/US-001-login');
});

it('deletes a spec with its files', function (): void {
    addSpec();
    planSpec();

    $specs = app(SpecService::class);
    $specFile = $specs->specPath('US-001');
    $planFile = app(PlanService::class)->path('US-001');

    expect($specFile)->toBeFile()->and($planFile)->toBeFile();

    $this->artisan('larapilot:spec-delete', ['code' => 'US-001'])->assertSuccessful();

    expect($specs->find('US-001'))->toBeNull()
        ->and(is_file($specFile))->toBeFalse()
        ->and(is_file($planFile))->toBeFalse();

    $this->artisan('larapilot:spec-delete', ['code' => 'US-001'])->assertExitCode(4);
});

it('reports metrics for the backlog', function (): void {
    addSpec(['code' => 'US-001', 'status' => 'DONE']);
    addSpec(['code' => 'US-002', 'status' => 'TODO']);

    $this->artisan('larapilot:metrics')
        ->assertSuccessful()
        ->expectsOutputToContain('"total":2,"done":1');
});

it('installs default project settings into config.yaml', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $yaml = file_get_contents(base_path('.larapilot/config.yaml'));

    expect($yaml)->toContain('effort: STANDARD')
        ->and($yaml)->toContain('backlog: STANDARD')
        ->and($yaml)->toContain('git_mode: GITFLOW')
        ->and($yaml)->toContain('testing: NORMAL')
        ->and($yaml)->toContain('auto_approve: false')
        ->and($yaml)->toContain('lucille: true')
        ->and($yaml)->toContain('dashboard_auth: false')
        ->and($yaml)->toContain('api_auth: false')
        ->and($yaml)->toContain('security_scan: false')
        ->and($yaml)->toContain('github: false')
        ->and($yaml)->toContain('gitlab: false')
        ->and($yaml)->toContain('bitbucket: false')
        ->and($yaml)->toContain('azure: false')
        ->and($yaml)->toContain('notifications: false')
        ->and($yaml)->toContain('notify_slack: false')
        ->and($yaml)->toContain('notify_discord: false')
        ->and($yaml)->toContain('notify_telegram: false');
});

it('persists project settings via settings-set', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:settings-set', [
        '--effort' => 'ECO',
        '--backlog' => 'LEAN',
        '--git-mode' => 'GITFLOW_PUSH',
        '--testing' => 'BEST',
        '--auto-approve' => 'YES',
        '--lucille' => 'NO',
        '--decision-log' => 'NO',
        '--code-history' => 'YES',
        '--api-auth' => 'YES',
        '--security-scan' => 'YES',
        '--github' => 'YES',
        '--gitlab' => 'YES',
        '--bitbucket' => 'NO',
        '--azure' => 'YES',
        '--notifications' => 'YES',
        '--notify-slack' => 'YES',
        '--notify-discord' => 'NO',
        '--notify-telegram' => 'YES',
    ])->assertSuccessful();

    $settings = app(ConfigService::class)->settings();

    expect($settings)->toBe([
        'effort' => 'ECO',
        'backlog' => 'LEAN',
        'git_mode' => 'GITFLOW_PUSH',
        'testing' => 'BEST',
        'auto_approve' => 'YES',
        'lucille' => 'NO',
        'decision_log' => 'NO',
        'code_history' => 'YES',
        'dashboard_auth' => 'NO',
        'api_auth' => 'YES',
        'security_scan' => 'YES',
        'github' => 'YES',
        'gitlab' => 'YES',
        'bitbucket' => 'NO',
        'azure' => 'YES',
        'notifications' => 'YES',
        'notify_slack' => 'YES',
        'notify_discord' => 'NO',
        'notify_telegram' => 'YES',
    ])
        ->and(app(ConfigService::class)->setupInfo()['settings'])->toBe($settings)
        ->and(app(ConfigService::class)->autoApproveEnabled())->toBeTrue()
        ->and(app(ConfigService::class)->lucilleEnabled())->toBeFalse()
        ->and(app(ConfigService::class)->githubEnabled())->toBeTrue()
        ->and(app(ConfigService::class)->gitlabEnabled())->toBeTrue()
        ->and(app(ConfigService::class)->bitbucketEnabled())->toBeFalse()
        ->and(app(ConfigService::class)->azureEnabled())->toBeTrue()
        ->and(app(ConfigService::class)->notifySlackEnabled())->toBeTrue()
        ->and(app(ConfigService::class)->notifyTelegramEnabled())->toBeTrue();
});

it('treats missing lucille setting as enabled and accepts exclude aliases', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    expect(app(ConfigService::class)->lucilleEnabled())->toBeTrue();

    $this->artisan('larapilot:settings-set', ['--lucille' => 'EXCLUDE'])
        ->assertSuccessful()
        ->expectsOutputToContain('"lucille":"NO"');

    expect(app(ConfigService::class)->lucilleEnabled())->toBeFalse();

    $this->artisan('larapilot:usage-log', [
        '--category' => 'analysis',
        '--tokens' => 10,
        '--minutes' => 1,
    ])->assertExitCode(4)
        ->expectsOutputToContain('E_PRECONDITION');
});

it('journals decisions and flags a regression against an earlier choice', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:decision-log', [
        '--topic' => 'background color',
        '--value' => 'orange',
        '--source' => 'askquestion',
        '--skill' => 'larapilot-inception',
    ])->assertSuccessful()
        ->expectsOutputToContain('"has_regression":false');

    $this->artisan('larapilot:decision-check', [
        '--topic' => 'background color',
        '--value' => 'red',
    ])->assertSuccessful()
        ->expectsOutputToContain('"has_regression":true');

    $entries = app(DecisionService::class)->entries();
    expect($entries[0]['value'])->toBe('orange');

    // Recording the reversal with --supersedes clears the regression.

    $this->artisan('larapilot:decision-log', [
        '--topic' => 'background color',
        '--value' => 'red',
        '--supersedes' => $entries[0]['id'],
    ])->assertSuccessful();

    $this->artisan('larapilot:decision-check', ['--topic' => 'background color', '--value' => 'red'])
        ->assertSuccessful()
        ->expectsOutputToContain('"has_regression":false');
});

it('refuses decision-log when the journal is disabled', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--decision-log' => 'NO'])->assertSuccessful();

    $this->artisan('larapilot:decision-log', ['--topic' => 'x', '--value' => 'y'])
        ->assertExitCode(4)
        ->expectsOutputToContain('E_PRECONDITION');
});

it('refuses code-log when code history is disabled and records it when enabled', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:code-log', ['--spec' => 'US-001'])
        ->assertExitCode(4)
        ->expectsOutputToContain('E_PRECONDITION');

    initTestGitRepository('chore: bootstrap');
    $name = 'code-log-cmd-'.bin2hex(random_bytes(6)).'.php';
    file_put_contents(base_path($name), "<?php\n");
    shell_exec('git -C '.escapeshellarg(base_path()).' add '.escapeshellarg($name).' 2>/dev/null');
    shell_exec('git -C '.escapeshellarg(base_path()).' commit -m '.escapeshellarg('feat: x').' 2>/dev/null');

    $this->artisan('larapilot:settings-set', ['--code-history' => 'YES'])->assertSuccessful();

    $this->artisan('larapilot:code-log', ['--spec' => 'US-001', '--range' => 'HEAD~1..HEAD'])
        ->assertSuccessful()
        ->expectsOutputToContain($name);
});

it('disables lucille automatically when switching to ECO', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    expect(app(ConfigService::class)->lucilleEnabled())->toBeTrue();

    $this->artisan('larapilot:settings-set', ['--effort' => 'ECO'])
        ->assertSuccessful()
        ->expectsOutputToContain('"lucille_disabled_by_eco":true');

    expect(app(ConfigService::class)->settings()['effort'])->toBe('ECO')
        ->and(app(ConfigService::class)->settings()['lucille'])->toBe('NO')
        ->and(app(ConfigService::class)->lucilleEnabled())->toBeFalse();

    $this->artisan('larapilot:settings-set', ['--lucille' => 'YES'])
        ->assertSuccessful();

    expect(app(ConfigService::class)->settings()['effort'])->toBe('ECO')
        ->and(app(ConfigService::class)->lucilleEnabled())->toBeTrue();
});

it('keeps lucille on when ECO is set together with lucille YES', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:settings-set', [
        '--effort' => 'ECO',
        '--lucille' => 'YES',
    ])->assertSuccessful()
        ->expectsOutputToContain('"lucille_disabled_by_eco":false');

    expect(app(ConfigService::class)->settings()['effort'])->toBe('ECO')
        ->and(app(ConfigService::class)->settings()['lucille'])->toBe('YES')
        ->and(app(ConfigService::class)->lucilleEnabled())->toBeTrue();
});

it('accepts SI alias for auto-approve yes', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:settings-set', ['--auto-approve' => 'SI'])
        ->assertSuccessful()
        ->expectsOutputToContain('"auto_approve":"YES"');
});

it('accepts friendly git-mode aliases on settings-set', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:settings-set', ['--git-mode' => 'GITFLOW + PUSH'])
        ->assertSuccessful()
        ->expectsOutputToContain('"git_mode":"GITFLOW_PUSH"');
});

it('rejects invalid settings-set values', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:settings-set', ['--effort' => 'TURBO'])
        ->assertExitCode(2)
        ->expectsOutputToContain('E_INVALID_INPUT');

    $this->artisan('larapilot:settings-set', ['--backlog' => 'HUGE'])
        ->assertExitCode(2)
        ->expectsOutputToContain('E_INVALID_INPUT');

    $this->artisan('larapilot:settings-set')
        ->assertExitCode(2)
        ->expectsOutputToContain('at least one');
});

it('preserves unrelated config keys when updating settings', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    file_put_contents(base_path('.larapilot/config.yaml'), "connector: file\ncustom: kept\nsettings:\n  effort: STANDARD\n");

    $this->artisan('larapilot:settings-set', ['--testing' => 'MINIMAL'])->assertSuccessful();

    $yaml = file_get_contents(base_path('.larapilot/config.yaml'));

    expect($yaml)->toContain('custom: kept')
        ->and($yaml)->toContain('testing: MINIMAL');
});

it('includes plan metrics in the metrics envelope', function (): void {
    addSpec();
    planSpec();

    expect(Artisan::call('larapilot:metrics'))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data'])->toHaveKeys(['total', 'done', 'total_tasks', 'done_tasks', 'specs_with_plans'])
        ->and($envelope['data']['total_tasks'])->toBe(2);
});

it('preserves custom design systems on update when requested', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $custom = base_path('.larapilot/design-systems/filament/README.md');
    file_put_contents($custom, 'CUSTOMIZED');

    $this->artisan('larapilot:update', ['--skip-boost' => true, '--preserve-design-systems' => true])
        ->assertSuccessful();

    expect(file_get_contents($custom))->toBe('CUSTOMIZED');

    $this->artisan('larapilot:update', ['--skip-boost' => true])->assertSuccessful();

    expect(file_get_contents($custom))->not->toBe('CUSTOMIZED');
});
