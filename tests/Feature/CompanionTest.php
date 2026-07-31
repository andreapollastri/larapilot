<?php

declare(strict_types=1);

use Larapilot\Services\CompanionService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\PrdService;

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

it('builds a companion artifact bundle for sync', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();
    $config->ensureDirectories();

    app(PrdService::class)->write(validPrd()."\n\n**Frontend Topology:** API + external frontend\n**External frontend stack:** React\n");

    $bundle = app(CompanionService::class)->bundle();

    expect($bundle)->toBeArray()
        ->and($bundle['artifacts']['frontend_topology']['mode'] ?? null)->toBe('api_external_frontend')
        ->and($bundle['artifacts']['prd']['content'] ?? null)->toContain('Frontend Topology');
});
