<?php

declare(strict_types=1);

use Larapilot\Services\DashboardExportService;
use Larapilot\Services\PrdService;

function exportPrd(): string
{
    return <<<'MD'
# Fjord Invoices

## Elevator Pitch
Invoicing for small studios.

## Vision
A calm billing tool.

## User Personas
Ingrid, studio owner.

## Functional Requirements
### FR-001: Sign in
Ingrid signs in.

## MVP Scope
One studio per account.

## Technical Architecture
Laravel monolith.
MD;
}

it('downloads the board as it stands, status by status', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    app(PrdService::class)->write(exportPrd());

    $this->artisan('larapilot:spec-add', ['--file' => payloadFile(['specs' => [
        ['code' => 'US-001', 'title' => 'Sign in | with email', 'priority' => 'HIGH', 'points' => 3, 'status' => 'TODO', 'body' => validSpecBody()],
        ['code' => 'US-002', 'title' => 'Reset the password', 'priority' => 'LOW', 'points' => 5, 'status' => 'TODO', 'body' => validSpecBody()],
    ]])])->assertSuccessful();

    $this->artisan('larapilot:spec-plan', ['code' => 'US-001', '--file' => payloadFile(planPayload(), 'tmp-plan.yaml')])
        ->assertSuccessful();

    $this->get('/larapilot')
        ->assertOk()
        ->assertSee('/larapilot/board.md', false)
        ->assertSee('Download status (.md)', false);

    $response = $this->get('/larapilot/board.md')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

    expect($response->headers->get('Content-Disposition'))
        ->toBe('attachment; filename="fjord-invoices-board-'.now()->format('Y-m-d').'.md"');

    $markdown = $response->getContent();

    expect($markdown)->toStartWith("# Board — Fjord Invoices\n")
        ->toContain('grouped by workflow status.')
        ->toContain('| Specs | 2 |')
        ->toContain('| Done | 0 |')
        ->toContain('| Completion | 0% |')
        ->toContain('| Story points | 8 (0 done) |')
        ->toContain('| Tasks | 2 (0 done) |')
        ->toContain('## By status')
        ->toContain('| PLANNED | 1 | 3 |')
        ->toContain('| TODO | 1 | 5 |')
        ->toContain('## PLANNED (1)')
        ->toContain('## TODO (1)')
        ->toContain('## DONE (0)')
        ->toContain('_No specs._')
        // a pipe in a title stays a character, not a column
        ->toContain('| US-001 | Sign in \\| with email | HIGH | 3 | — | 0 of 2 done | — |')
        ->toContain('| US-002 | Reset the password | LOW | 5 | — | — | — |')
        ->not->toContain('**Filtered by:**');
});

it('downloads the board the filters leave on screen', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:spec-add', ['--file' => payloadFile(['specs' => [
        ['code' => 'US-001', 'title' => 'Sign in', 'priority' => 'HIGH', 'points' => 3, 'status' => 'TODO', 'body' => validSpecBody()],
        ['code' => 'US-002', 'title' => 'Reset the password', 'priority' => 'LOW', 'points' => 5, 'status' => 'TODO', 'body' => validSpecBody()],
    ]])])->assertSuccessful();

    $byPriority = $this->get('/larapilot/board.md?priority=high')->assertOk()->getContent();

    expect($byPriority)->toContain('**Filtered by:** priority “HIGH”.')
        ->toContain('| Specs | 1 |')
        ->toContain('| US-001 | Sign in |')
        ->not->toContain('US-002');

    $byText = $this->get('/larapilot/board.md?q=RESET&status=TODO')->assertOk()->getContent();

    expect($byText)->toContain('**Filtered by:** search “reset”, status “TODO”.')
        ->toContain('| US-002 | Reset the password |')
        ->not->toContain('US-001')
        // the other statuses are not part of what is on screen
        ->not->toContain('## DONE');

    // A filter that is not text is ignored, not an error.
    $this->get('/larapilot/board.md?q[]=x&priority[a]=b')->assertOk();

    expect(app(DashboardExportService::class)->boardFilename())
        ->toBe('larapilot-board-'.now()->format('Y-m-d').'.md');
});

it('offers no board download while the backlog is empty', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->get('/larapilot')
        ->assertOk()
        ->assertDontSee('id="board-download"', false);

    $this->get('/larapilot/board.md')
        ->assertOk()
        ->assertSee('| Specs | 0 |', false);
});

it('downloads a whole spec with its plan, tasks, decisions, and comments', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableComments();
    addSpec(['title' => 'Sign in with email']);

    $plan = planPayload();
    $plan['plan_body'] = "# Approach\nFortify, one guard.\n\n## Tests\nFeature tests first.";
    $plan['tasks'][0]['estimate_hours'] = 2.5;
    $plan['tasks'][0]['body'] = "## Description\nCreate the model.\n\n```php\n# not a heading\n```";

    $this->artisan('larapilot:spec-plan', ['code' => 'US-001', '--file' => payloadFile($plan, 'tmp-plan.yaml')])
        ->assertSuccessful();
    $this->artisan('larapilot:spec-start', ['code' => 'US-001'])->assertSuccessful();
    $this->artisan('larapilot:task-done', ['code' => 'US-001', 'taskId' => 'TASK-01'])->assertSuccessful();
    $this->artisan('larapilot:spec-comment', [
        'code' => 'US-001',
        '--author' => 'PM',
        '--message' => 'Please confirm the Safari scope.',
    ])->assertSuccessful();

    $this->get('/larapilot/specs/US-001')
        ->assertOk()
        ->assertSee('/larapilot/specs/US-001/spec.md', false)
        ->assertSee('Download spec (.md)', false);

    $response = $this->get('/larapilot/specs/US-001/spec.md')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

    expect($response->headers->get('Content-Disposition'))
        ->toBe('attachment; filename="US-001-sign-in-with-email.md"');

    $markdown = $response->getContent();

    expect($markdown)->toStartWith("# US-001 — Sign in with email\n")
        ->toContain('| Status | IN PROGRESS |')
        ->toContain('| Priority | HIGH |')
        ->toContain('| Story points | 3 |')
        ->toContain('| Tasks | 1 of 2 done |')
        ->toContain('| Estimated hours | 2.5 h |')
        ->toContain("## User story\n\n**User Story**")
        ->toContain('- [ ] Happy path')
        // the plan's own headings move under the heading of the file
        ->toContain("## Technical plan\n\n### Approach\nFortify, one guard.\n\n#### Tests")
        ->toContain('## Tasks (1 of 2 done)')
        ->toContain("### TASK-01 — Create model\n\n- **Status:** DONE")
        ->toContain('- **Type:** implementation')
        ->toContain('- **Estimate:** 2.5 h')
        ->toContain("#### Description\nCreate the model.")
        // a line in a code block is code, whatever it starts with
        ->toContain("```php\n# not a heading\n```")
        ->toContain("### TASK-02 — Write tests\n\n- **Status:** TODO")
        ->toContain('- **Depends on:** TASK-01')
        ->toContain('## Internal feedback')
        ->toContain('Remove this section before sending the file to a client.')
        ->toContain('### PM — ')
        ->toContain('Please confirm the Safari scope.');
});

it('downloads a spec that has no plan yet', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    $markdown = $this->get('/larapilot/specs/US-001/spec.md')->assertOk()->getContent();

    expect($markdown)->toContain('| Tasks | Not planned yet |')
        ->toContain('## Tasks (0 of 0 done)')
        ->toContain('_No plan tasks yet. Run `/larapilot-plan US-001`._')
        ->not->toContain('## Technical plan')
        ->not->toContain('## Internal feedback');
});

it('answers 404 for the download of a spec that does not exist', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    $this->get('/larapilot/specs/US-999/spec.md')->assertNotFound();
    $this->get('/larapilot/specs/..%2Fconfig/spec.md')->assertNotFound();
});

it('downloads the PRD exactly as it is on disk', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    app(PrdService::class)->write(exportPrd());

    $this->get('/larapilot/prd')
        ->assertOk()
        ->assertSee('/larapilot/prd/prd.md', false)
        ->assertSee('Download PRD (.md)', false);

    $response = $this->get('/larapilot/prd/prd.md')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

    expect($response->headers->get('Content-Disposition'))
        ->toBe('attachment; filename="fjord-invoices-prd.md"')
        ->and($response->getContent())->toBe((string) file_get_contents(base_path('.larapilot/docs/PRD.md')));
});

it('answers 404 for the PRD download while there is no PRD', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->get('/larapilot/prd/prd.md')->assertNotFound();

    $this->get('/larapilot/prd')
        ->assertOk()
        ->assertDontSee('/larapilot/prd/prd.md', false)
        ->assertDontSee('id="prd-search"', false);
});

it('searches the PRD, and only the PRD', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    app(PrdService::class)->write(exportPrd());

    $html = $this->get('/larapilot/prd')
        ->assertOk()
        ->assertSee('id="prd-search"', false)
        ->assertSee('Search the PRD', false)
        ->assertSee('role="combobox"', false)
        ->assertSee('id="prd-search-results"', false)
        ->assertSee('Type to search the PRD — and only the PRD', false)
        ->getContent();

    // The index is read from the article that holds the document, which sits
    // apart from the menu, the section list, and the decision journal.
    expect($html)->toContain("var article = document.getElementById('prd-content');")
        ->toContain('document.createTreeWalker(article,')
        ->toContain('<article class="card prd-content markdown" id="prd-content">')
        ->and(substr_count($html, 'id="prd-content"'))->toBe(1)
        ->and(strpos($html, 'data-prd-search'))->toBeLessThan(strpos($html, 'id="prd-content"'));
});

it('keeps the downloads behind the dashboard gate', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    app(PrdService::class)->write(exportPrd());
    addSpec();

    config()->set('larapilot.dashboard_route.enabled', false);

    $this->get('/larapilot/board.md')->assertNotFound();
    $this->get('/larapilot/prd/prd.md')->assertNotFound();
    $this->get('/larapilot/specs/US-001/spec.md')->assertNotFound();
});
