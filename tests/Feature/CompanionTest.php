<?php

declare(strict_types=1);

use Larapilot\Services\CompanionService;

it('extracts frontend topology fields from the PRD', function (): void {
    $topology = app(CompanionService::class)->extractFrontendTopology(<<<'MD'
## Technical Architecture

**Frontend Topology:** SPA-in-Laravel
**Frontend stack (in-repo):** Vite + Vue
**Companion sync:** N/A
MD);

    expect($topology)->toBeArray()
        ->and($topology['projects'])->toBe([])
        ->and($topology['delivery'])->toBeNull()
        ->and($topology['mode'])->toBe('spa_in_laravel')
        ->and($topology['in_repo_stack'])->toBe('Vite + Vue')
        ->and($topology['sync_mode'])->toBe('N/A');
});

it('reads the projects and the delivery mode of a monorepo frontend from the PRD', function (): void {
    $topology = app(CompanionService::class)->extractFrontendTopology(<<<'MD'
**Frontend Topology:** API + external frontend
**External frontend repo:** acme/frontend
**External frontend stack:** Angular
**Frontend projects:** `portal`, admin
**Frontend delivery:** handoff — the frontend team builds from a brief
MD);

    expect($topology['mode'])->toBe('api_external_frontend')
        ->and($topology['projects'])->toBe(['portal', 'admin'])
        ->and($topology['delivery'])->toBe('handoff');
});
