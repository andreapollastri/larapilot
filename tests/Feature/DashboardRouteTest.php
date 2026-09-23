<?php

declare(strict_types=1);

use Larapilot\Services\ConfigService;
use Larapilot\Services\DecisionService;
use Larapilot\Services\PrdService;
use Larapilot\Services\SpecService;

it('serves the workflow dashboard in local environment', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    $this->get('/larapilot')
        ->assertOk()
        ->assertSee('Larapilot')
        ->assertDontSee('Project settings')
        ->assertSee('US-001')
        ->assertSee('Login');
});

it('shows every spec in a kanban column without collapsing', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    foreach (range(1, 6) as $index) {
        addSpec([
            'code' => sprintf('US-%03d', $index),
            'title' => "Story {$index}",
            'status' => 'TODO',
        ]);
    }

    $this->get('/larapilot')
        ->assertOk()
        ->assertDontSee('Show 1 more', false)
        ->assertSee('US-001', false)
        ->assertSee('US-006', false);
});

it('serves the inception and docs dashboard pages', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->get('/larapilot/inception')
        ->assertOk()
        ->assertSee('Inception choices');

    $this->get('/larapilot/docs')
        ->assertOk()
        ->assertSee('How Larapilot works')
        ->assertSee('/larapilot-inception')
        ->assertSee('/larapilot-economics')
        ->assertSee('Personas');

    $html = $this->get('/larapilot')
        ->assertOk()
        ->assertSee('>Plan</a>', false)
        ->assertSee('>Usage</a>', false)
        ->assertSee('>Economics</a>', false)
        ->assertSee('>Design</a>', false)
        ->assertSee('>Docs</a>', false)
        ->getContent();

    expect(strpos($html, '>Plan</a>'))->toBeLessThan(strpos($html, '>Design</a>'))
        ->and(strrpos($html, '>Docs</a>'))->toBeGreaterThan(strpos($html, '>Economics</a>'));
});

it('serves the skills dashboard page with custom skill descriptions', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->get('/larapilot/skills')
        ->assertOk()
        ->assertSee('Custom skills')
        ->assertSee('/larapilot-custom-skill');

    $this->artisan('larapilot:custom-skill-add', [
        '--name' => 'staging-gate',
        '--content' => "---\nname: staging-gate\ndescription: Confirm staging is green before promote.\n---\n\n# Staging gate\n",
    ])->assertSuccessful();

    $this->get('/larapilot/skills')
        ->assertOk()
        ->assertSee('/staging-gate')
        ->assertSee('Confirm staging is green before promote.')
        ->assertSee('Registered');
});

it('hides the dashboard in production environment', function (): void {
    $this->app['env'] = 'production';

    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    $this->get('/larapilot')->assertNotFound();
    $this->get('/larapilot/prd')->assertNotFound();
    $this->get('/larapilot/settings')->assertNotFound();
    $this->get('/larapilot/inception')->assertNotFound();
    $this->get('/larapilot/docs')->assertNotFound();
    $this->get('/larapilot/skills')->assertNotFound();
    $this->get('/larapilot/git')->assertNotFound();
    $this->get('/larapilot/usage')->assertNotFound();
    $this->get('/larapilot/plan')->assertNotFound();
    $this->get('/larapilot/economics')->assertNotFound();
    $this->get('/larapilot/economics/panel')->assertNotFound();
    $this->get('/larapilot/design')->assertNotFound();
    $this->get('/larapilot/specs/US-001')->assertNotFound();
});

it('hides the dashboard when the route is disabled by config', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    config()->set('larapilot.dashboard_route.enabled', false);

    $this->get('/larapilot')->assertNotFound();
});

it('downloads a functional analysis summary from the PRD page', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();
    $config->ensureDirectories();

    app(PrdService::class)->write(<<<'MD'
# Gestionale

**Data:** 2026-09-23

## Sintesi
Un gestionale per le officine che tengono i lavori e le fatture.

## Personas utente
### Marco
- **Ruolo:** Titolare

## Requisiti funzionali
### Accesso
#### FR-002: Report
**MoSCoW:** Could

Il titolare esporta il mese.

### FR-010: Magazzino
**MoSCoW:** Won't

Rimandato.

### FR-001: Ingresso
**MoSCoW:** Must

L'operatore entra con email.
- Recupero password

## Ambito MVP
### In ambito
- Officina singola

### Fuori ambito
- Multi-sede
MD);

    $this->get('/larapilot/prd')
        ->assertOk()
        ->assertSee('Sintesi di analisi funzionale', false)
        ->assertSee('href="'.route('larapilot.dashboard.prd.summary').'"', false);

    $download = $this->get('/larapilot/prd/functional-summary.md')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

    $body = $download->getContent();

    expect($download->headers->get('Content-Disposition'))->toContain('gestionale-sintesi-funzionale.md')
        ->and($body)->toContain('# Sintesi di analisi funzionale')
        ->and($body)->toContain('## In una frase')
        ->and($body)->toContain('**Marco** — Titolare')
        ->and($body)->toContain('### Indispensabile')
        ->and($body)->toContain('1. **FR-001 — Ingresso**')
        ->and($body)->toContain('Recupero password')
        ->and($body)->toContain('### Utile, non necessario')
        ->and($body)->toContain('2. **Accesso — FR-002 — Report**')
        ->and($body)->toContain('### Non in questa versione')
        ->and($body)->toContain('3. **FR-010 — Magazzino**')
        ->and($body)->toContain('## Dentro questa versione')
        ->and($body)->toContain('- Officina singola')
        ->and($body)->toContain('## Fuori da questa versione')
        ->and($body)->not->toContain('**MoSCoW:**');

    app(PrdService::class)->write(validPrd());

    $this->get('/larapilot/prd')
        ->assertOk()
        ->assertSee('Functional analysis summary', false);

    $this->get('/larapilot/prd/functional-summary.md')
        ->assertOk()
        ->assertSee('1. **Login**', false)
        ->assertDontSee('Technical Architecture', false);
});

it('renders the PRD with section headings', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();
    $config->ensureDirectories();

    app(PrdService::class)->write(validPrd());

    $this->get('/larapilot/prd')
        ->assertOk()
        ->assertSee('Elevator Pitch')
        ->assertSee('Technical Architecture');
});

it('renders the decision journal below the PRD grouped by user story', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();
    $config->ensureDirectories();

    app(PrdService::class)->write(validPrd());
    addSpec(['code' => 'US-001', 'title' => 'Login']);

    app(DecisionService::class)->log([
        'topic' => 'framework',
        'value' => 'Laravel',
        'skill' => 'larapilot-inception',
    ]);
    app(DecisionService::class)->log([
        'topic' => 'auth provider',
        'value' => 'Sanctum',
        'spec' => 'US-001',
    ]);

    $this->get('/larapilot/prd')
        ->assertOk()
        ->assertSee('Decision journal', false)
        ->assertSee('Project / discovery', false)
        ->assertSee('US-001 — Login', false)
        ->assertSee('Sanctum', false)
        ->assertSee('href="#decision-journal"', false);
});

it('shows user-story decisions on the spec detail page', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['code' => 'US-001', 'title' => 'Login']);

    app(DecisionService::class)->log([
        'topic' => 'framework',
        'value' => 'Laravel',
    ]);
    app(DecisionService::class)->log([
        'topic' => 'auth provider',
        'value' => 'Sanctum',
        'spec' => 'US-001',
        'rationale' => 'SPA-friendly token auth',
    ]);

    $this->get('/larapilot/specs/US-001')
        ->assertOk()
        ->assertSee('Decision journal', false)
        ->assertSee('Sanctum', false)
        ->assertSee('SPA-friendly token auth', false)
        ->assertDontSee('Project / discovery');
});

it('links the PRD table of contents to heading anchors', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();
    $config->ensureDirectories();

    app(PrdService::class)->write(validPrd());

    $this->get('/larapilot/prd')
        ->assertOk()
        ->assertSee('href="#elevator-pitch"', false)
        ->assertSee('id="elevator-pitch"', false);
});

it('shows specs whose status is outside the configured workflow', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['status' => 'BLOCKED']);

    $this->get('/larapilot')
        ->assertOk()
        ->assertSee('BLOCKED')
        ->assertSee('US-001');
});

it('shows story points and subtask progress on the board', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['points' => 5]);
    planSpec();

    $this->get('/larapilot')
        ->assertOk()
        ->assertDontSee('Story points')
        ->assertDontSee('Subtasks')
        ->assertDontSee('% delivered')
        ->assertDontSee('% complete')
        ->assertDontSee('0/2 tasks')
        ->assertSee('priority-high', false)
        ->assertSee('board-scroll', false)
        ->assertSee('5 SP')
        ->assertSee('0/2');
});

it('shows spec detail with tasks', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    planSpec();
    initTestGitRepository('feat(US-001): TASK-01 Create model');
    $this->artisan('larapilot:task-done', ['code' => 'US-001', 'taskId' => 'TASK-01'])->assertSuccessful();

    $this->get('/larapilot/specs/US-001')
        ->assertOk()
        ->assertSee('US-001')
        ->assertSee('3 SP')
        ->assertSee('User story')
        ->assertSee('TASK-01')
        ->assertSee('Create model')
        ->assertSee('feat(US-001): TASK-01 Create model')
        ->assertSee('data-exclusive-accordion', false)
        ->assertSee('task-accordion', false);
});

it('links mockups to spec detail when HTML exists', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    addMockup('US-001', [
        'index.html' => '<html><body>Login mockup</body></html>',
        'dark.html' => '<html><body>Dark login</body></html>',
    ]);

    $this->get('/larapilot/specs/US-001')
        ->assertOk()
        ->assertSee('Mockups', false)
        ->assertSee('<iframe', false)
        ->assertSee('/mockups/US-001', false)
        ->assertSee('/mockups/US-001/dark.html', false)
        ->assertSee('.larapilot/mockups/US-001/', false);
});

it('keeps the merge link outside the spec card and offers board filters', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec([
        'epic' => ['code' => 'EP-01', 'title' => 'Foundations'],
        'status' => 'DONE',
    ]);

    app(SpecService::class)->update('US-001', [
        'merge_commit' => [
            'sha' => '28c2b10dbf4c1a65c0c7d31f739c1e1a77549350',
            'short_sha' => '28c2b10',
            'subject' => 'docs(US-001): record the pass',
            'committed_at' => '2026-09-23T08:00:00+00:00',
            'url' => 'https://github.com/example/app/commit/28c2b10dbf4c1a65c0c7d31f739c1e1a77549350',
        ],
    ]);

    $html = $this->get('/larapilot')
        ->assertOk()
        ->assertSee('id="board-q"', false)
        ->assertSee('id="board-priority"', false)
        ->assertSee('id="board-epic"', false)
        ->assertSee('id="board-status"', false)
        ->assertSee('Foundations', false)
        ->assertSee('MR 28c2b10', false)
        ->assertDontSee('<a class="spec-card"', false)
        ->getContent();

    expect($html)->toContain('class="spec-card-hit"')
        ->and($html)->toContain('class="merge-commit-link"')
        ->and($html)->not->toContain('<a class="spec-card"');

    $start = strpos($html, 'class="spec-card"');
    $end = strpos($html, '</article>', is_int($start) ? $start : 0);
    $markup = is_int($start) && is_int($end) ? substr($html, $start, $end - $start) : '';
    $hitClose = strpos($markup, '</a>');
    $merge = strpos($markup, 'class="merge-commit-link"');

    expect($markup)->toContain('MR 28c2b10')
        ->and(substr_count($markup, '<a '))->toBe(2)
        ->and($hitClose)->toBeInt()
        ->and($merge)->toBeInt()
        ->and($hitClose)->toBeLessThan($merge);
});

it('shows mockup indicator on board cards', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    addMockup('US-001', ['index.html' => '<html><body>Mockup</body></html>']);

    $this->get('/larapilot')
        ->assertOk()
        ->assertSee('mockup-indicator', false)
        ->assertSee('Mockup');
});

it('returns 404 for unknown specs', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->get('/larapilot/specs/US-999')->assertNotFound();
});

it('shows empty states when artifacts are missing', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();

    $this->get('/larapilot')
        ->assertOk()
        ->assertSee('No backlog specs yet');

    $this->get('/larapilot/prd')
        ->assertOk()
        ->assertSee('No PRD found');
});

it('renders the git contribution heatmap filtered by author', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $recent = (new DateTimeImmutable('-3 weeks'))->format('Y-m-d').'T12:00:00+00:00';
    $ada = 'ada-'.bin2hex(random_bytes(4)).'@example.test';
    $grace = 'grace-'.bin2hex(random_bytes(4)).'@example.test';
    commitTestGitChange('feat: ada heatmap', $recent, 'Ada Lovelace', $ada);
    commitTestGitChange('feat: grace heatmap', $recent, 'Grace Hopper', $grace);

    $this->get('/larapilot/git')
        ->assertOk()
        ->assertSee('Git history', false)
        ->assertSee('Contribution graph', false)
        ->assertSee('All developers', false)
        ->assertSee('Ada Lovelace', false)
        ->assertSee('Grace Hopper', false)
        ->assertSee('heatmap-cell', false)
        ->assertSee($ada, false)
        ->assertSee($grace, false);

    $this->get('/larapilot/git?author='.urlencode($ada))
        ->assertOk()
        ->assertSee('1 contribution in the last year', false)
        ->assertSee($ada, false);
});
