<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Larapilot\Services\CodeQualityService;

it('scaffolds pint and larastan config on install', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    expect(base_path('phpstan.neon.dist'))->toBeFile()
        ->and(file_get_contents(base_path('phpstan.neon.dist')))->toContain('larastan/larastan/extension.neon')
        ->and(file_get_contents(base_path('phpstan.neon.dist')))->toContain('level: 5')
        ->and(base_path('pint.json'))->toBeFile();

    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect($composer['require-dev']['laravel/pint'] ?? null)->not->toBeNull()
        ->and($composer['require-dev']['larastan/larastan'] ?? null)->not->toBeNull()
        ->and($composer['scripts']['lint'] ?? null)->toBe('pint')
        ->and($composer['scripts']['analyse'] ?? null)->toContain('phpstan analyse');
});

it('reports code quality checks on doctor after install', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    Artisan::call('larapilot:doctor');
    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['checks']['quality_pint'] ?? null)->toBeTrue()
        ->and($envelope['data']['checks']['quality_larastan'] ?? null)->toBeTrue()
        ->and($envelope['data']['checks']['quality_packages'] ?? null)->toBeTrue()
        ->and($envelope['data']['quality']['min_larastan_level'] ?? null)->toBe(CodeQualityService::MIN_LARASTAN_LEVEL);
});

it('rejects larastan levels below the minimum on quality', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    file_put_contents(base_path('phpstan.neon.dist'), "includes:\n    - vendor/larastan/larastan/extension.neon\nparameters:\n    level: 3\n    paths:\n        - app\n");

    $this->artisan('larapilot:quality')
        ->assertExitCode(2)
        ->expectsOutputToContain('Larastan level must be at least 5');
});
