<?php

declare(strict_types=1);

use Larapilot\Services\ChoicesService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\EconomicsMarketService;
use Larapilot\Services\EconomicsService;
use Larapilot\Services\PlanService;
use Larapilot\Services\PrdService;
use Larapilot\Support\ArtifactLanguage;
use Larapilot\Support\TaxCatalog;
use Symfony\Component\Yaml\Yaml;

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
        ->assertSee('Where the hours come from', false)
        ->assertSee('What the client pays and what you keep', false)
        ->assertSee('Hourly rate', false)
        ->assertSee('Commercial discount', false)
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

it('simulates a price without writing the profile or the snapshot', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    addSpec(['points' => 8]);

    $this->artisan('larapilot:economics-set', [
        '--country' => 'IT',
        '--hourly-rate' => '55',
        '--margin' => '30',
    ])->assertSuccessful();

    $service = app(EconomicsService::class);
    $saved = $service->snapshot();
    $storedBefore = $service->readStoredSnapshot();

    $simulated = $service->snapshot(['hourly_rate' => 90, 'discount_pct' => 15]);

    expect($simulated['simulation']['active'])->toBeTrue()
        ->and($simulated['simulation']['overrides'])->toBe(['hourly_rate' => 90.0, 'discount_pct' => 15.0])
        ->and($simulated['simulation']['command'])->toContain('--hourly-rate=90')
        ->and($simulated['simulation']['command'])->toContain('--discount=15')
        ->and($simulated['quote']['hourly_rate'])->toBe(90.0)
        ->and($simulated['quote']['gross'])->toBeGreaterThan($saved['quote']['gross'])
        // the profile on disk and the stored cost board still describe the saved rate
        ->and($service->read()['hourly_rate'])->toBe(55.0)
        ->and($service->readStoredSnapshot()['quote']['gross'])->toBe($storedBefore['quote']['gross'])
        ->and($service->snapshot()['simulation']['active'])->toBeFalse();
});

it('takes the discount out of the margin, not out of the cost', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    addSpec(['points' => 8]);
    $this->artisan('larapilot:economics-set', ['--country' => 'IT', '--hourly-rate' => '55', '--margin' => '30'])->assertSuccessful();

    $service = app(EconomicsService::class);
    $quote = $service->snapshot(['discount_pct' => 20])['quote'];

    expect($quote['list_price'])->toBe(round($quote['direct'] + $quote['margin'], 2))
        ->and($quote['discount'])->toBe(round($quote['list_price'] * 0.2, 2))
        ->and($quote['gross'])->toBe(round($quote['list_price'] - $quote['discount'], 2))
        ->and($quote['margin_after_discount'])->toBeLessThan($quote['margin'])
        ->and($quote['below_cost'])->toBeFalse();

    // A discount deeper than the margin prices the project under its own cost.
    $deep = $service->snapshot(['discount_pct' => 40])['quote'];

    expect($deep['below_cost'])->toBeTrue()
        ->and($deep['margin_after_discount'])->toBeLessThan(0);
});

it('lets the team size move the calendar but never the price', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    addSpec(['points' => 21]);
    $this->artisan('larapilot:economics-set', ['--country' => 'IT', '--hourly-rate' => '55'])->assertSuccessful();

    $service = app(EconomicsService::class);
    $solo = $service->snapshot();
    $team = $service->snapshot(['team_size' => 3]);

    expect($team['effort']['billable_hours'])->toBe($solo['effort']['billable_hours'])
        ->and($team['effort']['person_months'])->toBe($solo['effort']['person_months'])
        ->and($team['effort']['calendar_months'])->toBeLessThan($solo['effort']['calendar_months'])
        ->and($team['quote']['overhead'])->toBe($solo['quote']['overhead'])
        ->and($team['quote']['gross'])->toBe($solo['quote']['gross'])
        ->and($team['effort']['team_size'])->toBe(3.0);
});

it('derives three price lines and runs the forecast on the selected one', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    addSpec(['points' => 5]);
    addSpec(['code' => 'US-002', 'title' => 'Billing', 'points' => 5]);
    addSpec(['code' => 'US-003', 'title' => 'Reporting', 'points' => 8]);

    $this->artisan('larapilot:economics-set', [
        '--country' => 'IT',
        '--hourly-rate' => '55',
        '--product-model' => 'saas',
        '--price-monthly' => '29',
    ])->assertSuccessful();

    $service = app(EconomicsService::class);
    $snapshot = $service->snapshot();
    $tiers = collect($snapshot['packaging']['tiers'])->keyBy('id');

    expect($tiers->keys()->all())->toBe(['base', 'pro', 'premium'])
        ->and($tiers['pro']['price_monthly'])->toBe(29.0)
        ->and($tiers['base']['price_monthly'])->toBeLessThan(29.0)
        ->and($tiers['premium']['price_monthly'])->toBeGreaterThan(29.0)
        ->and($tiers['base']['features'])->not->toBeEmpty()
        ->and($snapshot['packaging']['source'])->toBe('derived')
        ->and($snapshot['business_plan']['price_monthly'])->toBe(29.0);

    $premium = $service->snapshot(['tier' => 'premium']);

    expect($premium['business_plan']['price_monthly'])->toBe($tiers['premium']['price_monthly'])
        ->and($premium['saas']['price_monthly'])->toBe($tiers['premium']['price_monthly'])
        ->and($premium['business_plan']['lines'])->toHaveCount(3);

    // Optimistic must never read worse than pessimistic on the same price line.
    $lines = collect($premium['business_plan']['lines'])->keyBy('id');

    expect($lines['optimistic']['arr_m36'])->toBeGreaterThan($lines['pessimistic']['arr_m36'])
        ->and($lines['optimistic']['churn_monthly_pct'])->toBeLessThan($lines['pessimistic']['churn_monthly_pct']);
});

it('prices against the researched market when the research file exists', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    addSpec(['points' => 8]);
    $this->artisan('larapilot:economics-set', [
        '--country' => 'IT',
        '--hourly-rate' => '55',
        '--product-model' => 'saas',
        '--price-monthly' => '49',
    ])->assertSuccessful();

    $research = base_path('.larapilot/market.yaml');
    file_put_contents($research, Yaml::dump([
        'sector' => 'Field service management',
        'segment' => 'SMB installers',
        'summary' => 'Crowded mid-market, thin at the low end.',
        'competitors' => [
            ['name' => 'Alpha', 'price_monthly' => 39, 'trend' => 'up', 'change_pct' => 12, 'url' => 'https://alpha.test'],
            ['name' => 'Beta', 'price_monthly' => 99, 'trend' => 'flat'],
            ['name' => 'Gamma', 'price_monthly' => 149, 'trend' => 'up', 'change_pct' => 6],
        ],
        'demand' => [
            'pessimistic' => ['customers' => 20, 'growth_monthly_pct' => 3, 'churn_monthly_pct' => 9, 'conversion_pct' => 1],
            'realistic' => ['customers' => 120, 'growth_monthly_pct' => 10, 'churn_monthly_pct' => 4, 'conversion_pct' => 2.5],
            'optimistic' => ['customers' => 400, 'growth_monthly_pct' => 18, 'churn_monthly_pct' => 2, 'conversion_pct' => 4],
        ],
        'tiers' => [
            ['id' => 'base', 'name' => 'STARTER', 'price_monthly' => 25, 'share_pct' => 60, 'features' => ['Jobs', 'Scheduling']],
            ['id' => 'pro', 'price_monthly' => 59, 'share_pct' => 30],
            ['id' => 'premium', 'price_monthly' => 129, 'share_pct' => 10],
        ],
        'risks' => ['Alpha is bundling scheduling for free.'],
        'sources' => ['https://alpha.test/pricing'],
    ], 6, 2));

    $this->artisan('larapilot:economics-market-write', ['--file' => $research])->assertSuccessful();

    $snapshot = app(EconomicsService::class)->snapshot();
    $market = $snapshot['market'];
    $tiers = collect($snapshot['packaging']['tiers'])->keyBy('id');

    expect($market['available'])->toBeTrue()
        ->and($market['sector'])->toBe('Field service management')
        ->and($market['competitors'])->toHaveCount(3)
        ->and($market['competitors'][0]['name'])->toBe('Alpha')
        ->and($market['trend']['direction'])->toBe('up')
        ->and($market['trend']['average_change_pct'])->toBe(9.0)
        ->and($market['stale'])->toBeFalse()
        ->and($tiers['base']['name'])->toBe('STARTER')
        ->and($tiers['base']['price_monthly'])->toBe(25.0)
        ->and($tiers['base']['features'])->toBe(['Jobs', 'Scheduling'])
        ->and($snapshot['packaging']['source'])->toBe('research')
        ->and($snapshot['packaging']['positioning']['median'])->toBe(99.0)
        ->and($snapshot['packaging']['positioning']['cheaper_than_us'])->toBe(1)
        ->and($snapshot['business_plan']['source'])->toBe('research');

    $lines = collect($snapshot['business_plan']['lines'])->keyBy('id');

    expect($lines['realistic']['target_customers'])->toBe(120)
        ->and($lines['realistic']['churn_monthly_pct'])->toBe(4.0)
        ->and($lines['optimistic']['target_customers'])->toBe(400)
        ->and($lines['realistic']['source'])->toBe('research');
});

it('refuses market research it cannot read', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:economics-market-write', ['--content' => 'sector: X'])
        ->assertExitCode(4);

    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();

    $this->artisan('larapilot:economics-market-write', ['--content' => "- one\n- two"])
        ->assertExitCode(2)
        ->expectsOutputToContain('YAML mapping');

    $this->artisan('larapilot:economics-market-write', ['--content' => 'notes: nothing useful'])
        ->assertExitCode(2);

    expect(app(EconomicsMarketService::class)->exists())->toBeFalse();
});

it('keeps an out-of-range simulation out of the engine', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    addSpec(['points' => 5]);
    $this->artisan('larapilot:economics-set', ['--country' => 'IT', '--hourly-rate' => '55'])->assertSuccessful();

    $service = app(EconomicsService::class);
    $overrides = $service->normalizeOverrides([
        'hourly_rate' => '-40',
        'discount_pct' => '250',
        'team_size' => 'due',
        'regime' => 'invented_regime',
        'country' => 'ZZ',
        'tier' => 'gold',
        'scenario' => 'hopeful',
        'margin_target_pct' => '45',
        'unknown_key' => '1',
    ]);

    expect($overrides)->toBe(['margin_target_pct' => 45.0, 'regime' => 'invented_regime']);

    // An unknown regime still cannot reach the catalogue: it falls back to the
    // country default instead of throwing.
    $snapshot = $service->snapshot($overrides);

    expect($snapshot['regime']['id'])->toBe('forfettario_15')
        ->and($snapshot['quote']['margin_pct'])->toBe(45.0);
});

it('recomputes the economics panel from the query string', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    addSpec(['points' => 8]);
    $this->artisan('larapilot:economics-set', [
        '--country' => 'IT',
        '--hourly-rate' => '55',
        '--product-model' => 'saas',
        '--price-monthly' => '29',
    ])->assertSuccessful();

    $saved = app(EconomicsService::class)->snapshot();

    // The panel is the page without the shell, so a changed dropdown can swap it.
    $this->get('/larapilot/economics/panel')
        ->assertOk()
        ->assertSee('Hourly rate', false)
        ->assertDontSee('<!DOCTYPE html>', false)
        ->assertSee('Saved profile', false);

    $this->get('/larapilot/economics/panel?hourly_rate=90&discount_pct=10&tier=premium')
        ->assertOk()
        ->assertSee('This is a simulation', false)
        ->assertSee('--hourly-rate=90', false)
        ->assertSee('forecast runs on this', false);

    // The download follows the simulation, and the stored snapshot does not.
    $this->get('/larapilot/economics/quote.md?hourly_rate=90')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

    expect(app(EconomicsService::class)->readStoredSnapshot()['quote']['gross'])
        ->toBe($saved['quote']['gross']);

    $this->getJson('/larapilot/api/economics?hourly_rate=90&tier=premium')
        ->assertOk()
        ->assertJsonPath('simulation.active', true)
        ->assertJsonPath('quote.hourly_rate', 90)
        ->assertJsonPath('packaging.selected_tier', 'premium');
});

it('prices the maintenance retainer from the inception answers', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    addSpec(['points' => 8]);

    $service = app(EconomicsService::class);

    // Nothing asked yet: the retainer says so instead of inventing a number.
    $blank = $service->snapshot()['maintenance'];

    expect($blank['gaps'])->not->toBeEmpty()
        ->and($blank['adopted'] ?? false)->toBeTrue()
        ->and($blank['configured_pct'])->toBe($blank['recommended_pct']);

    $this->artisan('larapilot:choices-set', [
        '--delivery-target' => 'Enterprise',
        '--business-model' => 'SaaS subscription',
        '--budget-sensitivity' => 'Relaxed',
        '--server-management' => 'Self-managed VPS',
        '--ops-owner' => 'Me / my team',
        '--support-window' => '24/7',
    ])->assertSuccessful();

    $answered = app(EconomicsService::class)->snapshot();
    $maintenance = $answered['maintenance'];
    $reasons = implode(' ', array_column($maintenance['drivers'], 'reason'));

    expect($maintenance['recommended_pct'])->toBeGreaterThan($blank['recommended_pct'])
        ->and($maintenance['gaps'])->toBeEmpty()
        ->and($reasons)->toContain('Enterprise delivery target')
        ->and($reasons)->toContain('You operate the server')
        ->and($reasons)->toContain('Round-the-clock support')
        ->and(implode(' ', $maintenance['covers']))->toContain('backup verification')
        // the business model the user stated decides how the product is priced
        ->and($answered['product']['model'])->toBe('saas');

    // A client's own ops team takes the machine back out of the retainer.
    $this->artisan('larapilot:choices-set', [
        '--server-management' => "Client's own infrastructure",
        '--ops-owner' => 'Client team',
        '--support-window' => 'Business hours',
    ])->assertSuccessful();

    $lighter = app(EconomicsService::class)->snapshot()['maintenance'];

    expect($lighter['recommended_pct'])->toBeLessThan($maintenance['recommended_pct'])
        ->and(implode(' ', array_column($lighter['drivers'], 'reason')))->toContain("client's own team");

    // An explicit figure always wins, and the dashboard says it diverges.
    $this->artisan('larapilot:economics-set', ['--country' => 'IT', '--maintenance' => '8'])->assertSuccessful();

    $chosen = app(EconomicsService::class)->snapshot();

    expect($chosen['maintenance']['configured_pct'])->toBe(8.0)
        ->and($chosen['maintenance']['adopted'] ?? false)->toBeFalse()
        ->and($chosen['maintenance']['follows_inception'])->toBeFalse()
        ->and($chosen['quote']['maintenance_year'])->toBe(round($chosen['quote']['gross'] * 0.08, 2));
});

it('writes the infrastructure chapter only once the project decided one', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    addSpec(['points' => 8]);
    $this->artisan('larapilot:economics-set', ['--country' => 'IT', '--hourly-rate' => '55'])->assertSuccessful();

    $economics = app(EconomicsService::class);

    // Nothing decided about hosting: the quote promises no arrangement.
    expect($economics->quoteMarkdown())->not->toContain('Infrastructure and hosting');

    $this->artisan('larapilot:choices-set', [
        '--deploy-platform' => 'Laravel Forge on Hetzner',
        '--server-management' => 'Self-managed VPS',
        '--ops-owner' => 'Me / my team',
        '--support-window' => 'Business hours',
    ])->assertSuccessful();

    $ours = app(EconomicsService::class)->quoteMarkdown();

    expect($ours)->toContain('## Infrastructure and hosting')
        ->toContain('| **Hosting platform** | Laravel Forge on Hetzner |')
        ->toContain('| **Server management** | Self-managed VPS |')
        ->toContain('Automated daily backups with periodic restore testing')
        ->toContain('Uptime checks and alerting')
        ->toContain('TLS certificate renewed automatically')
        ->toContain('| **Support window** | Business hours |')
        ->toContain('per month')
        ->toContain('billed directly by the hosting provider')
        ->toContain('stay in the client\'s name')
        // the chapter sits before the price, where value belongs
        ->and(strpos($ours, '## Infrastructure and hosting'))->toBeLessThan(strpos($ours, '## Investment'));

    // The client's own IT takes the operational promises back out.
    $this->artisan('larapilot:choices-set', [
        '--server-management' => "Client's own infrastructure",
        '--ops-owner' => 'Client team',
    ])->assertSuccessful();

    $theirs = app(EconomicsService::class)->quoteMarkdown();

    expect($theirs)->toContain("Owned by the client's IT team")
        ->not->toContain('Automated daily backups with periodic restore testing');
});

it('sells security and quality from what the project actually does', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--testing' => 'MINIMAL'])->assertSuccessful();
    addSpec(['points' => 8]);
    $this->artisan('larapilot:economics-set', ['--country' => 'IT', '--hourly-rate' => '55'])->assertSuccessful();

    $lean = app(EconomicsService::class)->quoteMarkdown();

    expect($lean)->toContain('## Security and quality: what protects this investment')
        ->toContain('independent review')
        ->toContain('acceptance criteria written before it is built')
        ->toContain('Automated tests on the critical paths')
        ->toContain('No lock-in')
        // claims the project does not earn are simply absent
        ->not->toContain('automated security scan')
        ->and($lean)->not->toContain('broad automated test suite')
        ->and($lean)->not->toContain('Numbered releases')
        ->and($lean)->not->toContain('Personal data handled to GDPR');

    $this->artisan('larapilot:settings-set', ['--testing' => 'BEST'])->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--security-scan' => 'YES'])->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--release-mode' => 'YES'])->assertSuccessful();
    $this->artisan('larapilot:prd-write', ['--content' => str_replace(
        'Laravel monolith.',
        'Laravel monolith. The service stores personal data and follows GDPR.',
        validPrd()
    )])->assertSuccessful();

    $full = app(EconomicsService::class)->quoteMarkdown();

    expect($full)->toContain('A broad automated test suite')
        ->toContain('An automated security scan on every change')
        ->toContain('Numbered releases with a list of what changed')
        ->toContain('Personal data handled to GDPR')
        ->toContain('OWASP')
        // still a sales document: no engineering artefacts leak into it
        ->not->toContain('story point')
        ->and($full)->not->toContain('US-001');
});

it('renders the economics page and its warnings in the PRD language', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    $this->artisan('larapilot:economics-set', [
        '--country' => 'IT',
        '--regime' => 'forfettario_15',
        '--hourly-rate' => '55',
    ])->assertSuccessful();

    addSpec(['title' => 'Anagrafica clienti']);
    app(PrdService::class)->write(<<<'MD'
# Gestionale Star Service

## Panoramica
Il gestionale serve a gestire clienti, interventi e fatturazione per l'azienda.

## Obiettivi di business
- Ridurre il tempo di inserimento degli interventi
- Avere uno storico consultabile dei clienti

## Funzionalità principali
- Anagrafica clienti
- Gestione interventi con allegati

## Architettura
Laravel 12, MySQL, deploy su VPS.
MD);

    $this->get('/larapilot/economics')
        ->assertOk()
        // The narrative follows the PRD …
        ->assertSee('Da dove vengono le ore', false)
        ->assertSee('Prezzo al cliente', false)
        ->assertSee('Cosa paga il cliente e cosa ti resta', false)
        ->assertSee('Scarica il preventivo', false)
        ->assertDontSee('Where the hours come from', false)
        ->assertDontSee('Client price', false)
        // … while the pricing console stays in English: those are operator controls.
        ->assertSee('Hourly rate', false)
        ->assertSee('Commercial discount', false);

    $snapshot = app(EconomicsService::class)->snapshot();

    expect($snapshot['language'])->toBe('it')
        ->and($snapshot['effort']['notes'])->toContain('Direttamente dal backlog')
        ->and($snapshot['effort']['source_label'])->toBe('Story point')
        ->and($snapshot['product']['label'])->not->toContain('delivery');
});

it('keeps the page in English when the PRD is', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    app(PrdService::class)->write(validPrd());

    $this->get('/larapilot/economics')
        ->assertOk()
        ->assertSee('Where the hours come from', false)
        ->assertDontSee('Da dove vengono le ore', false);

    expect(app(EconomicsService::class)->snapshot()['language'])->toBe('en');
});

it('says the market research is skipped by default on a one-off delivery', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    $this->artisan('larapilot:economics-set', ['--product-model' => 'fixed'])->assertSuccessful();

    $economics = app(EconomicsService::class);
    $fixed = $economics->snapshot()['market'];

    expect($fixed['available'])->toBeFalse()
        ->and($fixed['hint'])->toContain('one-off client delivery')
        ->and($fixed['hint'])->toContain('ask for the market research explicitly');

    $this->artisan('larapilot:economics-set', ['--product-model' => 'saas'])->assertSuccessful();

    $saas = $economics->snapshot()['market'];

    expect($saas['hint'])->toContain('Run /larapilot-economics')
        ->and($saas['hint'])->not->toContain('one-off client delivery');
});

it('explains the four ways a project is sold and reads payback the right way for each', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    addSpec(['title' => 'Catalogue']);

    // The explainer lists every option the dropdown carries and marks the live one.
    $this->artisan('larapilot:economics-set', ['--product-model' => 'fixed'])->assertSuccessful();
    $this->get('/larapilot/economics')
        ->assertOk()
        ->assertSee('What “Sold as” changes', false)
        ->assertSee('One shot — fixed price', false)
        ->assertSee('SaaS — subscription', false)
        ->assertSee('E-commerce', false)
        ->assertSee('Licensed package', false)
        ->assertSee('The client pays once for the build', false)
        // A one-off delivery has neither of the two model-specific payback cards.
        ->assertDontSee('Orders / month to repay', false)
        ->assertDontSee('Licences to repay the build', false);

    // E-commerce keeps the one-shot price but reads payback in orders.
    $this->artisan('larapilot:economics-set', ['--product-model' => 'ecommerce'])->assertSuccessful();
    $this->get('/larapilot/economics')
        ->assertOk()
        ->assertSee('Orders / month to repay', false)
        ->assertSee('order of magnitude', false);

    // A licensed package reads it in licences, and says where the price came from.
    $this->artisan('larapilot:economics-set', ['--product-model' => 'package'])->assertSuccessful();
    $this->get('/larapilot/economics')
        ->assertOk()
        ->assertSee('Licences to repay the build', false)
        ->assertSee('No annual price is configured', false);

    $payback = app(EconomicsService::class)->snapshot()['payback'];

    expect($payback['model'])->toBe('package')
        ->and($payback['licenses_to_recover'])->toBeGreaterThan(0)
        ->and($payback['license_price_source'])->toBe('heuristic_build_fraction')
        ->and($payback)->not->toHaveKey('orders_per_month_to_recover_12m');
});

it('keeps the build price identical whichever way the project is sold', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    addSpec(['title' => 'Catalogue']);
    planSpec();

    $economics = app(EconomicsService::class);
    $prices = [];

    foreach (['fixed', 'saas', 'ecommerce', 'package'] as $model) {
        $this->artisan('larapilot:economics-set', ['--product-model' => $model])->assertSuccessful();
        $snapshot = $economics->snapshot();
        $prices[$model] = [$snapshot['quote']['gross'], $snapshot['effort']['billable_hours']];
    }

    expect(array_unique(array_map('serialize', $prices)))->toHaveCount(1);
});

it('renders the page and the quote in the same language for a German PRD', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();
    $this->artisan('larapilot:economics-set', [
        '--country' => 'DE',
        '--hourly-rate' => '85',
    ])->assertSuccessful();

    addSpec(['title' => 'Kundenstammdaten']);
    app(PrdService::class)->write(<<<'MD'
# Verwaltungssystem Star Service

## Überblick
Das System verwaltet Kunden, Einsätze und die Rechnungsstellung für das Unternehmen.
Jeder Benutzer kann nur die Daten sehen, die ihm zugewiesen sind.

## Geschäftsziele
- Die Zeit für die Erfassung der Einsätze reduzieren
- Eine durchsuchbare Historie der Kunden haben

## Funktionen
- Kundenstammdaten mit Filtern und Export
- Verwaltung der Einsätze mit Anhängen

## Architektur
Laravel 12, MySQL, Deployment auf einem VPS. Requirements: PHP 8.3.
MD);

    $economics = app(EconomicsService::class);

    // The page and the quote now agree: a language reaching one reaches both.
    expect($economics->language())->toBe('de')
        ->and($economics->quoteLanguage())->toBe('de')
        ->and($economics->quoteFilename())->toBe('verwaltungssystem-star-service-angebot.md')
        ->and($economics->quoteMarkdown())->toContain('# Angebot')
        ->toContain('Zusammenfassung des Angebots')
        ->toContain('Zahlungsbedingungen');

    $this->get('/larapilot/economics')
        ->assertOk()
        ->assertSee('Woher die Stunden kommen', false)
        ->assertSee('Was der Kunde zahlt und was Ihnen bleibt', false)
        ->assertSee('Angebot herunterladen', false)
        ->assertDontSee('Where the hours come from', false);

    expect($economics->snapshot()['language'])->toBe('de');
});
