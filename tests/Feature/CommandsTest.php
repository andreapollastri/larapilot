<?php

declare(strict_types=1);

use Larapilot\Services\PlanService;
use Larapilot\Services\SpecService;

it('installs the project scaffolding', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    expect(base_path('.larapilot/config.yaml'))->toBeFile()
        ->and(base_path('.larapilot/shared-runtime.md'))->toBeFile()
        ->and(base_path('.larapilot/task-templates.md'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/filament/tokens.css'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/filament/html/index.html'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/filament/figma-sources.md'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/filament/html/widgets-dashboard.html'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/starter-kit/tokens.css'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/starter-kit/html/index.html'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/starter-kit/sources.md'))->toBeFile()
        ->and(base_path('.larapilot/design-systems/starter-kit/html/dashboard.html'))->toBeFile()
        ->and(base_path('.larapilot/specs/.gitkeep'))->toBeFile()
        ->and(base_path('.larapilot/plans/.gitkeep'))->toBeFile()
        ->and(base_path('.larapilot/mockups/.gitkeep'))->toBeFile()
        ->and(base_path('.larapilot/docs/test-results/.gitkeep'))->toBeFile()
        ->and(base_path('.larapilot/research/reference-products/.gitkeep'))->toBeFile();
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
