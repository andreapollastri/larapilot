<?php

declare(strict_types=1);

use Larapilot\Services\ChoicesService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\EconomicsService;
use Larapilot\Services\PlanService;
use Larapilot\Services\PrdService;
use Larapilot\Support\ArtifactLanguage;
use Larapilot\Support\TaxCatalog;

it('defaults account mode to NONE and rejects unknown values', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $config = app(ConfigService::class);

    expect($config->settings()['account'])->toBe('NONE')
        ->and($config->accountEnabled())->toBeFalse()
        ->and($config->accountMode())->toBe('NONE')
        ->and($config->allowedAccountModes())->toBe(['NONE', 'FREELANCE', 'COMPANY']);

    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    expect(app(ConfigService::class)->accountMode())->toBe('FREELANCE')
        ->and(app(ConfigService::class)->accountEnabled())->toBeTrue();

    $this->artisan('larapilot:settings-set', ['--account' => 'SRL'])->assertSuccessful();
    expect(app(ConfigService::class)->accountMode())->toBe('COMPANY');

    $this->artisan('larapilot:settings-set', ['--account' => 'OFF'])->assertSuccessful();
    expect(app(ConfigService::class)->accountMode())->toBe('NONE');

    $this->artisan('larapilot:settings-set', ['--account' => 'TURBO'])
        ->assertExitCode(2);
});

it('refuses economics-set while account is NONE', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:economics-set', ['--country' => 'IT'])
        ->assertExitCode(4)
        ->expectsOutputToContain('Account mode is NONE');
});

it('persists the computed snapshot to economics.snapshot.yaml', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'COMPANY'])->assertSuccessful();

    addSpec(['points' => 8]);

    $this->artisan('larapilot:economics-set', [
        '--country' => 'IT',
        '--regime' => 'srl',
        '--hourly-rate' => '60',
        '--overhead-monthly' => '800',
    ])->assertSuccessful();

    $service = app(EconomicsService::class);
    $snapshot = $service->snapshot();
    $stored = $service->readStoredSnapshot();

    expect($snapshot['snapshot_saved_at'])->not->toBeNull()
        ->and(is_file($service->snapshotPath()))->toBeTrue()
        ->and($stored)->toBeArray()
        ->and($stored['computed_at'] ?? null)->toBe($snapshot['snapshot_saved_at'])
        ->and($stored['quote']['gross'] ?? null)->toBe($snapshot['quote']['gross'])
        ->and($stored)->not->toHaveKey('countries');
});

it('persists an economics profile and builds an Italian freelance quote', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();

    addSpec(['points' => 8]);
    app(PlanService::class)->save('US-001', [
        'plan_body' => 'Build it.',
        'tasks' => [
            ['id' => 'TASK-01', 'title' => 'Model', 'status' => 'TODO', 'estimate_hours' => 10],
            ['id' => 'TASK-02', 'title' => 'UI', 'status' => 'TODO', 'estimate_hours' => 14],
        ],
    ]);

    app(ChoicesService::class)->write([
        'project_kind' => 'Application',
        'delivery_target' => 'MVP',
        'website_type' => 'SaaS dashboard',
        'deploy_platform' => 'Laravel Forge',
    ]);

    $this->artisan('larapilot:economics-set', [
        '--country' => 'IT',
        '--regime' => 'forfettario_15',
        '--hourly-rate' => '55',
        '--margin' => '30',
        '--product-model' => 'saas',
        '--price-monthly' => '29',
        '--churn' => '4',
        '--target-customers' => '80',
    ])->assertSuccessful();

    $snapshot = app(EconomicsService::class)->snapshot();

    expect($snapshot['enabled'])->toBeTrue()
        ->and($snapshot['account'])->toBe('FREELANCE')
        ->and($snapshot['country']['code'])->toBe('IT')
        ->and($snapshot['regime']['id'])->toBe('forfettario_15')
        ->and($snapshot['effort']['source'])->toBe('plan_hours')
        ->and($snapshot['effort']['plan_hours'])->toBe(24.0)
        ->and($snapshot['quote']['hourly_rate'])->toBe(55.0)
        ->and($snapshot['quote']['gross'])->toBeGreaterThan(1000)
        ->and($snapshot['quote']['vat'])->toBe(0.0)
        ->and($snapshot['quote']['net_to_owner'])->toBeGreaterThan(0)
        ->and($snapshot['tax']['effective_rate_pct'])->toBeGreaterThan(10)
        ->and($snapshot['product']['model'])->toBe('saas')
        ->and($snapshot['saas']['break_even_customers'])->toBeGreaterThan(0)
        ->and($snapshot['saas']['customers_to_recover_12m'])->toBeGreaterThan($snapshot['saas']['break_even_customers'])
        ->and($snapshot['saas']['arr_at_planning'])->toBe(27840.0)
        ->and($snapshot['saas']['forecast'])->toHaveCount(36)
        ->and($snapshot['alternate']['account'])->toBe('COMPANY');
});

it('applies IRES-style company tax for an Italian SRL', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'COMPANY'])->assertSuccessful();

    $this->artisan('larapilot:economics-set', [
        '--country' => 'IT',
        '--regime' => 'srl',
        '--hourly-rate' => '75',
        '--product-model' => 'fixed',
    ])->assertSuccessful();

    $snapshot = app(EconomicsService::class)->snapshot();

    expect($snapshot['regime']['id'])->toBe('srl')
        ->and($snapshot['tax']['model'])->toBe('corporate')
        ->and($snapshot['tax']['social'])->toBeGreaterThan(0)
        ->and($snapshot['tax']['dividend_tax'])->toBeGreaterThan(0)
        ->and($snapshot['tax']['legal_reserve'])->toBeGreaterThan(0)
        ->and($snapshot['quote']['net_to_owner'])->toBeLessThan(40600)
        ->and($snapshot['quote']['vat'])->toBeGreaterThan(0)
        ->and($snapshot['product']['model'])->toBe('fixed');
});

it('serves the economics dashboard and API', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->get('/larapilot/economics')
        ->assertOk()
        ->assertSee('Economics is off', false);

    $this->getJson('/larapilot/api/economics')
        ->assertOk()
        ->assertJsonPath('enabled', false)
        ->assertJsonPath('account', 'NONE');

    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    $this->artisan('larapilot:economics-set', [
        '--country' => 'IT',
        '--regime' => 'forfettario_5',
        '--hourly-rate' => '50',
    ])->assertSuccessful();

    $this->get('/larapilot/economics')
        ->assertOk()
        ->assertSee('Economics', false)
        ->assertSee('Forfettario 5%', false)
        ->assertSee('Client price', false)
        ->assertSee('Scope &amp; effort', false)
        ->assertSee('What the client pays', false)
        ->assertSee('What you keep', false)
        ->assertSee('Download quote', false)
        ->assertDontSee('Economics is off', false);

    // No PRD and no written document: the template renders in its default
    // language. Tax residency says nothing about what language to write in.
    $this->get('/larapilot/economics/quote.md')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
        ->assertSee('# Commercial proposal', false)
        ->assertSee('Investment', false)
        ->assertSee('```mermaid', false);

    $this->get('/larapilot/economics/report.md')
        ->assertOk()
        ->assertSee('# Larapilot Economics', false);

    $this->getJson('/larapilot/api/economics')
        ->assertOk()
        ->assertJsonPath('enabled', true)
        ->assertJsonPath('account', 'FREELANCE')
        ->assertJsonPath('regime.id', 'forfettario_5');
});

it('does not inflate story-point hours with delivery multipliers', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'COMPANY'])->assertSuccessful();

    for ($i = 1; $i <= 10; $i++) {
        addSpec(['code' => 'US-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'points' => 63]);
    }

    app(ChoicesService::class)->write([
        'project_kind' => 'Application',
        'delivery_target' => 'Full product',
        'website_type' => 'SaaS dashboard',
    ]);

    $this->artisan('larapilot:economics-set', [
        '--country' => 'IT',
        '--regime' => 'srl',
        '--hourly-rate' => '60',
        '--overhead-monthly' => '800',
        '--product-model' => 'fixed',
    ])->assertSuccessful();

    $effort = app(EconomicsService::class)->snapshot()['effort'];

    expect($effort['source'])->toBe('story_points')
        ->and($effort['scope_multiplier'])->toBe(1.0)
        ->and($effort['billable_hours'])->toBe(2898.0);
});

it('marks alternate freelance regime inapplicable above the revenue cap', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'COMPANY'])->assertSuccessful();

    for ($i = 1; $i <= 20; $i++) {
        addSpec(['code' => 'US-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'points' => 100]);
    }

    $this->artisan('larapilot:economics-set', [
        '--country' => 'IT',
        '--regime' => 'srl',
        '--hourly-rate' => '60',
    ])->assertSuccessful();

    $alternate = app(EconomicsService::class)->snapshot()['alternate'];

    expect($alternate['account'])->toBe('FREELANCE')
        ->and($alternate['applicable'])->toBeFalse()
        ->and($alternate['reason'])->toContain('85,000');
});

it('lists FY-2026 catalogue countries and default Italian regimes', function (): void {
    expect(TaxCatalog::YEAR)->toBe(2026)
        ->and(TaxCatalog::countryCodes())->toContain('IT', 'DE', 'US', 'GB', 'SI', 'HR', 'NO', 'SE')
        ->and(TaxCatalog::defaultRegime('IT', 'FREELANCE'))->toBe('forfettario_15')
        ->and(TaxCatalog::defaultRegime('IT', 'COMPANY'))->toBe('srl')
        ->and(TaxCatalog::defaultHourlyRate('IT', 'FREELANCE'))->toBe(55.0);

    $regime = TaxCatalog::regime('IT', 'FREELANCE', 'forfettario');

    expect($regime['id'])->toBe('forfettario_15')
        ->and($regime['income_tax_rate'])->toBe(0.15)
        ->and($regime['vat_exempt'])->toBeTrue();
});

it('writes the client quote in the PRD language', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    addSpec(['title' => 'Accesso']);
    app(PrdService::class)->write(<<<'MD'
# Catalogo interno

## Sintesi
Un catalogo per il team commerciale.

## Visione
Vendere meglio.

## Personas utente
Agenti.

## Requisiti funzionali
- Login

## Ambito MVP
Solo catalogo.

### In ambito
- Elenco prodotti

### Fuori ambito
- App nativa

## Architettura tecnica
Laravel e PostgreSQL.
MD);

    $this->artisan('larapilot:economics-set', [
        '--country' => 'IT',
        '--regime' => 'forfettario_15',
        '--hourly-rate' => '55',
        '--product-model' => 'fixed',
    ])->assertSuccessful();

    $markdown = app(EconomicsService::class)->quoteMarkdown();

    expect($markdown)->toContain('# Offerta commerciale')
        ->toContain('Catalogo interno')
        ->toContain('Cosa è compreso')
        ->toContain('Cosa non è compreso')
        ->toContain('Cosa deve fornire il cliente')
        ->toContain('Manutenzione')
        ->toContain('gantt')
        ->toContain('App nativa')
        ->and(app(EconomicsService::class)->quoteFilename())->toBe('catalogo-interno-preventivo.md');

    $this->artisan('larapilot:economics-show', ['--format' => 'quote'])
        ->expectsOutputToContain('Offerta commerciale')
        ->assertSuccessful();
});

it('reads the PRD language even when the headings are not the template ones', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    addSpec(['title' => 'Anagrafica clienti']);

    // Italian PRD with its own headings and English technical vocabulary — the
    // old presence-based detector scored this as English.
    app(PrdService::class)->write(<<<'MD'
# Gestionale Star Service

## Panoramica
Il gestionale serve a gestire clienti, interventi e fatturazione per l'azienda.

## Obiettivi di business
- Ridurre il tempo di inserimento degli interventi
- Avere uno storico consultabile dei clienti

## Utenti e ruoli
Amministratore, operatore e tecnico con permessi diversi.

## Funzionalità principali
- Anagrafica clienti
- Gestione interventi con allegati

## Architettura
Laravel 12, MySQL, deploy su VPS. Requirements: PHP 8.3.
Acceptance criteria per ogni user story del backlog.
MD);

    $this->artisan('larapilot:economics-set', [
        '--country' => 'IT',
        '--regime' => 'forfettario_15',
        '--hourly-rate' => '55',
    ])->assertSuccessful();

    $economics = app(EconomicsService::class);

    expect(ArtifactLanguage::detect(app(PrdService::class)->read()))->toBe('it')
        ->and($economics->quoteLanguage())->toBe('it')
        ->and($economics->quoteMarkdown())->toContain('# Offerta commerciale')
        ->toContain('Obiettivi')
        ->toContain('Ridurre il tempo di inserimento degli interventi')
        ->toContain('Cosa realizziamo')
        ->toContain('Anagrafica clienti')
        ->and($economics->quoteFilename())->toBe('gestionale-star-service-preventivo.md');
});

it('keeps the built-in quote commercial rather than technical', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    addSpec(['title' => 'Login']);
    planSpec();
    app(PrdService::class)->write(validPrd());

    $this->artisan('larapilot:economics-set', [
        '--country' => 'GB',
        '--hourly-rate' => '70',
    ])->assertSuccessful();

    expect(app(EconomicsService::class)->read()['country'])->toBe('GB');

    $markdown = app(EconomicsService::class)->quoteMarkdown();

    expect($markdown)->toContain('# Commercial proposal')
        ->toContain('What you get')
        ->toContain('working days')
        ->toContain('Payment terms')
        // no engineering internals in a client document
        ->not->toContain('Story points')
        ->not->toContain('Create model')
        ->not->toContain('Frontend topology')
        ->not->toContain('Technical aspects');
});

it('stores an agent-written quote in any language and serves it as the download', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    addSpec();
    $this->artisan('larapilot:economics-set', ['--country' => 'DE', '--hourly-rate' => '80'])->assertSuccessful();

    $german = base_path('.larapilot/tmp-quote.md');
    file_put_contents($german, <<<'MD'
# Angebot

## Zusammenfassung
Dieses Angebot beschreibt die Umsetzung des Kundenportals. Der Aufwand ist auf
rund 40 Arbeitstage geschätzt, die Investition beträgt EUR 32.000 netto.

## Was wir liefern
- Kundenverwaltung mit Rollen und Rechten
- Auftragsverwaltung mit Anhängen

## Investition
| Position | Betrag |
| --- | ---: |
| Umsetzung | EUR 32.000 |
| Umsatzsteuer 19% | EUR 6.080 |

## Zahlungsbedingungen
40% bei Auftragserteilung, 40% zum Testbeginn, 20% zum Livegang.
MD);

    $this->artisan('larapilot:economics-quote-write', ['--file' => $german, '--lang' => 'de'])
        ->assertSuccessful();

    $economics = app(EconomicsService::class);
    $meta = $economics->quoteMeta();

    expect($meta['source'])->toBe('document')
        ->and($meta['lang'])->toBe('de')
        ->and($meta['stale'])->toBeFalse()
        ->and($meta['filename'])->toBe('laravel-angebot.md')
        ->and($economics->quoteMarkdown())->toContain('# Angebot')
        ->toContain('Kundenverwaltung mit Rollen und Rechten')
        ->and(app(EconomicsService::class)->snapshot()['quote_document']['source'])->toBe('document');

    $this->get('/larapilot/economics/quote.md')
        ->assertOk()
        ->assertSee('# Angebot', false)
        ->assertDontSee('# Commercial proposal', false);

    $this->get('/larapilot/economics')
        ->assertOk()
        ->assertSee('written document', false);

    // A backlog change marks the stored document as behind the numbers.
    addSpec(['code' => 'US-002', 'title' => 'Auftragsverwaltung', 'points' => 13]);

    expect(app(EconomicsService::class)->quoteMeta()['stale'])->toBeTrue();

    $this->get('/larapilot/economics')
        ->assertOk()
        ->assertSee('predates the current backlog', false);
});

it('rejects a quote document that is not a client document', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:economics-quote-write', ['--content' => '# Angebot'])
        ->assertExitCode(4)
        ->expectsOutputToContain('Account mode is NONE');

    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();

    $this->artisan('larapilot:economics-quote-write', ['--content' => 'no heading here, and far too short'])
        ->assertExitCode(2);

    $this->artisan('larapilot:economics-quote-write', ['--content' => '# Angebot'])
        ->assertExitCode(2);

    expect(is_file(app(EconomicsService::class)->quotePath()))->toBeFalse();
});

it('builds effort from the backlog and calibrates hours per point on the plans', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();

    for ($i = 1; $i <= 6; $i++) {
        addSpec(['code' => 'US-00'.$i, 'title' => 'Spec '.$i, 'points' => 5]);
    }

    // Two specs planned at 2h per point; the other four still on points only.
    foreach (['US-001', 'US-002'] as $code) {
        app(PlanService::class)->save($code, [
            'plan_body' => 'Build it.',
            'tasks' => [
                ['id' => 'TASK-01', 'title' => 'Model', 'status' => 'TODO', 'estimate_hours' => 6],
                ['id' => 'TASK-02', 'title' => 'UI', 'status' => 'TODO', 'estimate_hours' => 4],
            ],
        ]);
    }

    $this->artisan('larapilot:economics-set', ['--country' => 'IT', '--hourly-rate' => '55'])->assertSuccessful();

    $effort = app(EconomicsService::class)->snapshot()['effort'];

    expect($effort['source'])->toBe('mixed')
        ->and($effort['hours_per_point'])->toBe(2.0)
        ->and($effort['hours_per_point_source'])->toBe('plans')
        ->and($effort['plan_hours'])->toBe(20.0)
        ->and($effort['base_hours'])->toBe(60.0)
        ->and($effort['billable_hours'])->toBe(69.0)
        ->and($effort['scope_multiplier'])->toBe(1.0)
        ->and($effort['breakdown'])->toHaveCount(6)
        ->and($effort['breakdown'][0]['from'])->toBe('plan')
        ->and($effort['breakdown'][0]['hours'])->toBe(10.0)
        ->and($effort['breakdown'][5]['from'])->toBe('points')
        ->and($effort['warnings'])->not->toBeEmpty();
});

it('counts unsized specs and flags them', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();

    addSpec(['code' => 'US-001', 'points' => 0]);
    addSpec(['code' => 'US-002', 'points' => 0]);

    $effort = app(EconomicsService::class)->snapshot()['effort'];

    expect($effort['unsized_specs'])->toBe(2)
        ->and($effort['billable_hours'])->toBe(27.6)
        ->and(implode(' ', $effort['warnings']))->toContain('neither a plan nor story points');
});

it('applies delivery, kind, and type multipliers exactly once to a heuristic estimate', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();

    app(ChoicesService::class)->write([
        'project_kind' => 'Application',
        'delivery_target' => 'Enterprise',
        'website_type' => 'SaaS dashboard',
    ]);

    $effort = app(EconomicsService::class)->snapshot()['effort'];

    // 100h floor × 1.6 kind × 2.8 delivery × 1.25 type × 1.15 buffer = 644h.
    // The delivery target used to be applied twice (1,777h).
    expect($effort['source'])->toBe('heuristic')
        ->and($effort['base_hours'])->toBe(100.0)
        ->and($effort['scope_multiplier'])->toBe(5.6)
        ->and($effort['billable_hours'])->toBe(644.0)
        ->and(implode(' ', $effort['warnings']))->toContain('scope heuristic');
});

it('refreshes the stored snapshot as soon as the backlog changes', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    $this->artisan('larapilot:economics-set', ['--country' => 'IT', '--hourly-rate' => '55'])->assertSuccessful();

    $economics = app(EconomicsService::class);
    $before = $economics->readStoredSnapshot();

    expect($economics->isStale())->toBeFalse()
        ->and($before['effort']['source'])->toBe('heuristic');

    addSpec(['code' => 'US-050', 'points' => 21]);

    // spec-add rewrote the snapshot itself — no manual economics-show needed.
    $stored = $economics->readStoredSnapshot();

    expect($economics->isStale())->toBeFalse()
        ->and($stored['effort']['source'])->toBe('story_points')
        ->and((float) $stored['effort']['billable_hours'])->toBe(96.6)
        ->and((float) $stored['effort']['billable_hours'])->not->toBe((float) $before['effort']['billable_hours'])
        ->and($stored['effort']['billable_hours'])->toBe($economics->snapshot()['effort']['billable_hours'])
        ->and($stored['inputs'] ?? null)->toBe($economics->inputsFingerprint());

    // …and planning that spec moves it again, still without a manual refresh.
    $this->artisan('larapilot:spec-plan', [
        'code' => 'US-050',
        '--file' => payloadFile([
            'plan_body' => 'Technical solution and test strategy.',
            'tasks' => [
                ['id' => 'TASK-01', 'title' => 'Model', 'type' => 'implementation', 'status' => 'TODO', 'estimate_hours' => 8, 'body' => "## Description\nModel."],
                ['id' => 'TASK-02', 'title' => 'Tests', 'type' => 'test', 'status' => 'TODO', 'estimate_hours' => 4, 'body' => "## Description\nTests."],
            ],
        ], 'tmp-plan-estimates.yaml'),
    ])->assertSuccessful();

    $planned = $economics->readStoredSnapshot();

    expect($economics->isStale())->toBeFalse()
        ->and($planned['effort']['source'])->toBe('plan_hours')
        ->and((float) $planned['effort']['plan_hours'])->toBe(12.0)
        ->and((float) $planned['effort']['billable_hours'])->toBe(13.8);
});

it('refuses nonsense economics numbers instead of storing zeros', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    $this->artisan('larapilot:economics-set', ['--country' => 'IT', '--hourly-rate' => '55'])->assertSuccessful();

    $this->artisan('larapilot:economics-set', ['--hourly-rate' => 'sessanta'])
        ->assertExitCode(2)
        ->expectsOutputToContain('--hourly-rate must be a number');

    $this->artisan('larapilot:economics-set', ['--hourly-rate' => '-40'])
        ->assertExitCode(2)
        ->expectsOutputToContain('--hourly-rate must be between');

    $this->artisan('larapilot:economics-set', ['--churn' => '900'])
        ->assertExitCode(2)
        ->expectsOutputToContain('--churn must be between');

    $this->artisan('larapilot:economics-set', ['--currency' => 'euri!'])
        ->assertExitCode(2)
        ->expectsOutputToContain('Invalid --currency');

    // The valid profile from the first call survived every refusal.
    expect(app(EconomicsService::class)->read()['hourly_rate'])->toBe(55.0);

    $this->artisan('larapilot:economics-set', ['--price-monthly' => '29.9', '--target-customers' => '80.7'])
        ->assertSuccessful();

    $saas = app(EconomicsService::class)->read()['saas'];

    expect($saas['price_monthly'])->toBe(29.9)
        ->and($saas['target_customers'])->toBe(80);
});

it('moves the regime with the country and keeps the quote computable', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'COMPANY'])->assertSuccessful();
    $this->artisan('larapilot:economics-set', ['--country' => 'IT', '--regime' => 'srl'])->assertSuccessful();

    // Italy's `srl` means nothing in Germany: the regime follows the move.
    $this->artisan('larapilot:economics-set', ['--country' => 'DE'])->assertSuccessful();

    $snapshot = app(EconomicsService::class)->snapshot();

    expect($snapshot['country']['code'])->toBe('DE')
        ->and($snapshot['regime']['id'])->toBe('gmbh')
        ->and($snapshot['country']['currency'])->toBe('EUR')
        ->and($snapshot['quote']['gross'])->toBeGreaterThan(0);
});

it('reports capacity honestly for a project bigger than a working year', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();

    for ($i = 1; $i <= 12; $i++) {
        addSpec(['code' => 'US-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'points' => 55]);
    }

    $this->artisan('larapilot:economics-set', ['--country' => 'IT', '--hourly-rate' => '55'])->assertSuccessful();

    $snapshot = app(EconomicsService::class)->snapshot();
    $payback = $snapshot['payback'];

    expect($snapshot['effort']['person_years'])->toBeGreaterThan(1.0)
        ->and($payback['utilization_pct'])->toBeGreaterThan(100.0)
        ->and($payback['over_capacity'])->toBeTrue()
        ->and($payback['projects_per_year'])->toBeLessThan(1.0)
        ->and($payback['annual_gross_at_capacity'])->toBeLessThan($snapshot['quote']['gross']);
});

it('prices a licence from the configured annual price when there is one', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    addSpec(['points' => 8]);

    $this->artisan('larapilot:economics-set', [
        '--country' => 'IT',
        '--hourly-rate' => '55',
        '--product-model' => 'package',
    ])->assertSuccessful();

    $heuristic = app(EconomicsService::class)->snapshot();

    expect($heuristic['sales']['one_shot']['license_price_source'])->toBe('heuristic_build_fraction');

    $this->artisan('larapilot:economics-set', ['--price-annual' => '990'])->assertSuccessful();

    $configured = app(EconomicsService::class)->snapshot();

    expect($configured['sales']['one_shot']['license_price_source'])->toBe('configured_annual_price')
        ->and($configured['sales']['one_shot']['suggested_license_price'])->toBe(990.0)
        ->and($configured['payback']['suggested_license_price'])->toBe(990.0)
        ->and($configured['sales']['one_shot']['units_to_recover_build'])
        ->toBe((int) ceil($configured['quote']['gross'] / 990));
});
