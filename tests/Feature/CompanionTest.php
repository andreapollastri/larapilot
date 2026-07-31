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
        ->and($topology['mode'])->toBe('spa_in_laravel')
        ->and($topology['in_repo_stack'])->toBe('Vite + Vue')
        ->and($topology['sync_mode'])->toBe('N/A');
});
