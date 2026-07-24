<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Larapilot\Services\CompanionService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\PrdService;

it('exports a companion bundle via artisan', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();
    $config->ensureDirectories();

    app(PrdService::class)->write(validPrd()."\n\n**Frontend Topology:** API + external frontend\n**External frontend stack:** React\n**Companion sync:** skill + API pull\n");

    expect(Artisan::call('larapilot:companion-export'))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope)->toBeArray()
        ->and($envelope['kind'] ?? null)->toBe('companion-export')
        ->and($envelope['data']['artifacts']['frontend_topology']['mode'] ?? null)->toBe('api_external_frontend')
        ->and($envelope['data']['artifacts']['prd']['content'] ?? null)->toContain('Frontend Topology');
});

it('writes a companion bundle file when --file is set', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();
    $config->ensureDirectories();

    app(PrdService::class)->write(validPrd());

    $path = base_path('companion-bundle.json');

    expect(Artisan::call('larapilot:companion-export', ['--file' => $path]))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['kind'] ?? null)->toBe('companion-export')
        ->and($envelope['data']['path'] ?? null)->toBe($path)
        ->and(is_file($path))->toBeTrue();

    $decoded = json_decode((string) file_get_contents($path), true);

    expect($decoded['source'] ?? null)->toBe('larapilot')
        ->and($decoded['skill'] ?? null)->toBe('larapilot-frontend-companion')
        ->and($decoded['artifacts']['prd']['content'] ?? null)->toContain('Elevator Pitch');
});

it('extracts frontend topology fields from the PRD', function (): void {
    $topology = app(CompanionService::class)->extractFrontendTopology(<<<'MD'
## Technical Architecture

**Frontend Topology:** SPA-in-Laravel
**Frontend stack (in-repo):** Vite + Vue
**Companion sync:** N/A
MD);

    expect($topology)->toBeArray()
        ->and($topology['mode'])->toBe('spa_in_laravel')
        ->and($topology['in_repo_stack'])->toBe('Vite + Vue')
        ->and($topology['sync_mode'])->toBe('N/A');
});
