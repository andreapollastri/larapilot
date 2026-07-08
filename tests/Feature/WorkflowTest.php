<?php

declare(strict_types=1);

use Larapilot\Services\ConfigService;
use Larapilot\Services\SpecService;
use Larapilot\Services\ValidationService;

it('validates spec payload structure', function (): void {
    $validation = app(ValidationService::class);

    $result = $validation->validateSpecPayload(specsPayload());

    expect($result['ok'])->toBeTrue();
});

it('adds and lists specs via services', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();

    $specs = app(SpecService::class);
    $specs->add(specsPayload()['specs']);

    $list = $specs->list();

    expect($list['summary']['codes'])->toContain('US-001');
});

it('emits json envelope from config-show command', function (): void {
    $this->artisan('larapilot:config-show')
        ->assertSuccessful()
        ->expectsOutputToContain('"schema":"larapilot/v1"');
});

it('walks a spec through the whole workflow', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    addSpec();
    planSpec();

    $specs = app(SpecService::class);
    expect($specs->find('US-001')['status'])->toBe('PLANNED');

    $this->artisan('larapilot:spec-start', ['code' => 'US-001'])->assertSuccessful();
    expect($specs->find('US-001')['status'])->toBe('IN PROGRESS');

    $this->artisan('larapilot:task-done', ['code' => 'US-001', 'taskId' => 'TASK-01'])->assertSuccessful();
    $this->artisan('larapilot:task-done', ['code' => 'US-001', 'taskId' => 'TASK-02'])->assertSuccessful();

    $this->artisan('larapilot:spec-review', ['code' => 'US-001'])->assertSuccessful();
    expect($specs->find('US-001')['status'])->toBe('REVIEW');

    $this->artisan('larapilot:spec-approve', ['code' => 'US-001'])->assertSuccessful();

    $spec = $specs->find('US-001');
    expect($spec['status'])->toBe('DONE')
        ->and($spec['status_history'])->toHaveCount(4);
});

it('sends a spec back to todo with rework feedback', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    addSpec();
    planSpec();

    $this->artisan('larapilot:spec-start', ['code' => 'US-001'])->assertSuccessful();
    $this->artisan('larapilot:spec-review', ['code' => 'US-001'])->assertSuccessful();

    $feedback = payloadFile(['markdown' => 'Please handle the empty-password case.'], 'tmp-feedback.yaml');

    $this->artisan('larapilot:spec-request-changes', ['code' => 'US-001', '--file' => $feedback])
        ->assertSuccessful();

    $spec = app(SpecService::class)->find('US-001');

    expect($spec['status'])->toBe('TODO')
        ->and($spec['rework'])->toBeTrue()
        ->and($spec['body'])->toContain('## Rework Feedback')
        ->and($spec['body'])->toContain('empty-password');
});
