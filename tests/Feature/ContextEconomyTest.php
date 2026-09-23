<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Larapilot\Services\CodeQualityService;

it('keeps every runtime file under 15 KB', function (): void {
    $root = dirname(__DIR__, 2).'/resources/larapilot';
    $files = array_merge(
        glob($root.'/runtime-*.md') ?: [],
        [$root.'/shared-runtime.md']
    );

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        expect(filesize($file))->toBeLessThanOrEqual(15000, basename($file).' is over 15 KB');
    }

    expect(file_get_contents($root.'/shared-runtime.md'))->toContain('## Read protocol');
});

it('omits personas from config-show unless a slice asks for them', function (): void {
    expect(Artisan::call('larapilot:config-show'))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data'])->not->toHaveKey('personas')
        ->and($envelope['data'])->toHaveKey('settings')
        ->and($envelope['data'])->toHaveKey('paths');

    expect(Artisan::call('larapilot:config-show', ['--only' => 'settings,personas']))->toBe(0);

    $slice = json_decode(Artisan::output(), true);

    expect($slice['data'])->toHaveKey('settings')
        ->and($slice['data'])->toHaveKey('personas')
        ->and($slice['data'])->toHaveKey('project_root')
        ->and($slice['data'])->not->toHaveKey('paths');

    expect(Artisan::call('larapilot:config-show', ['--only' => 'nope']))->toBe(2);
});

it('filters spec-show to one task and to named fields', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    planSpec();

    expect(Artisan::call('larapilot:spec-show', [
        'code' => 'US-001',
        '--fields' => 'title,status,dependencies',
    ]))->toBe(0);

    $listed = json_decode(Artisan::output(), true);

    expect($listed['data']['tasks'])->toHaveCount(2)
        ->and($listed['data']['tasks'][0])->toHaveKeys(['id', 'title', 'status'])
        ->and($listed['data']['tasks'][0])->not->toHaveKey('body')
        ->and($listed['data']['spec'])->toHaveKey('body');

    expect(Artisan::call('larapilot:spec-show', [
        'code' => 'US-001',
        '--task' => 'TASK-02',
    ]))->toBe(0);

    $one = json_decode(Artisan::output(), true);

    expect($one['data']['tasks'])->toHaveCount(1)
        ->and($one['data']['tasks'][0]['id'])->toBe('TASK-02')
        ->and($one['data']['tasks'][0]['body'])->toContain('Write the tests');

    expect(Artisan::call('larapilot:spec-show', [
        'code' => 'US-001',
        '--task' => 'TASK-99',
    ]))->toBe(4);
});

it('filters spec-next with the same task and field options as spec-show', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    planSpec();

    expect(Artisan::call('larapilot:spec-next', [
        '--status' => 'PLANNED',
        '--fields' => 'title,status',
    ]))->toBe(0);

    $listed = json_decode(Artisan::output(), true);

    expect($listed['data']['spec']['code'])->toBe('US-001')
        ->and($listed['data']['tasks'])->toHaveCount(2)
        ->and($listed['data']['tasks'][0])->toHaveKeys(['id', 'title', 'status'])
        ->and($listed['data']['tasks'][0])->not->toHaveKey('body');

    expect(Artisan::call('larapilot:spec-next', [
        '--status' => 'PLANNED',
        '--task' => 'TASK-01',
    ]))->toBe(0);

    $one = json_decode(Artisan::output(), true);

    expect($one['data']['tasks'])->toHaveCount(1)
        ->and($one['data']['tasks'][0]['id'])->toBe('TASK-01');
});

it('strips the pint progress matrix from quality findings', function (): void {
    $presented = app(CodeQualityService::class)->present([
        'ok' => true,
        'pint' => [
            'ok' => true,
            'exit_code' => 0,
            'output' => "  ............\n\n  ──────────────────────────────── Laravel\n    PASS   ................................ 12 files",
        ],
        'analyse' => [
            'ok' => true,
            'exit_code' => 0,
            'output' => "Note: Using configuration file phpstan.neon.\n\n [OK] No errors",
        ],
    ]);

    expect($presented['ok'])->toBeTrue()
        ->and($presented['pint'])->not->toHaveKey('output')
        ->and($presented['pint']['summary'])->toBe('pass')
        ->and($presented['pint']['findings'])->toContain('PASS   ................................ 12 files')
        ->and($presented['pint']['findings'])->not->toContain('............')
        ->and($presented['analyse']['findings'])->toContain('[OK] No errors');

    $verbose = app(CodeQualityService::class)->present([
        'ok' => false,
        'pint' => ['ok' => false, 'exit_code' => 1, 'output' => "  ..\n  FAIL  app/Foo.php"],
        'analyse' => ['ok' => true, 'exit_code' => 0, 'output' => '[OK] No errors'],
    ], true);

    expect($verbose['ok'])->toBeFalse()
        ->and($verbose['pint']['output'])->toContain('FAIL  app/Foo.php')
        ->and($verbose['pint']['findings'])->toContain('FAIL  app/Foo.php');
});

it('delegates autopilot plan and implement to one spec worker at a time', function (): void {
    $root = dirname(__DIR__, 2);
    $subagents = file_get_contents($root.'/resources/larapilot/runtime-core-subagents.md');
    $autopilot = file_get_contents($root.'/resources/boost/skills/larapilot-autopilot/SKILL.md');
    $guideline = file_get_contents($root.'/resources/boost/guidelines/core.blade.php');

    expect($subagents)->toContain('### Spec worker (autopilot)')
        ->and($subagents)->toContain('Never parallelize specs')
        ->and($subagents)->toContain('no AskQuestion')
        ->and($subagents)->not->toContain('does not fork implement/plan');

    expect($autopilot)->toContain('One spec worker at a time')
        ->and($autopilot)->toContain('`effort` is `ECO`')
        ->and($autopilot)->not->toContain('Never spawn sub-agents in autopilot');

    expect($guideline)->toContain('Spec worker')
        ->and($guideline)->toContain('does not read `runtime-delivery`')
        ->and($guideline)->not->toContain('Sub-agents are readonly and never run');

    $index = file_get_contents($root.'/resources/larapilot/shared-runtime.md');

    expect($index)->toContain('delegating autopilot parent does not read')
        ->and($autopilot)->toContain('Do not read `runtime-delivery`')
        ->and($subagents)->toContain('Do not return the diff')
        ->and($subagents)->toContain('at most 8 bullets');
});
