<?php

declare(strict_types=1);

use Larapilot\Services\ConfigService;
use Larapilot\Services\SpecService;
use Larapilot\Services\ValidationService;

it('validates spec payload structure', function (): void {
    $validation = app(ValidationService::class);

    $result = $validation->validateSpecPayload([
        'specs' => [
            [
                'code' => 'US-001',
                'title' => 'User login',
                'body' => "User Story\nAs a user\n\nDemonstrates\nLogin works\n\nAcceptance Criteria\n- [ ] ok",
            ],
        ],
    ]);

    expect($result['ok'])->toBeTrue();
});

it('adds and lists specs via services', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();
    $config->ensureDirectories();

    $specs = app(SpecService::class);
    $specs->add([
        [
            'code' => 'US-001',
            'title' => 'Login',
            'priority' => 'HIGH',
            'points' => 3,
            'status' => 'TODO',
            'body' => 'User Story\nDemonstrates\nAcceptance Criteria',
        ],
    ]);

    $list = $specs->list();

    expect($list['summary']['codes'])->toContain('US-001');
});

it('emits json envelope from config-show command', function (): void {
    $this->artisan('larapilot:config-show')
        ->assertSuccessful()
        ->expectsOutputToContain('"schema":"larapilot/v1"');
});
