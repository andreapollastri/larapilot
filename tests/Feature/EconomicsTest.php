<?php

declare(strict_types=1);

use Larapilot\Services\ChoicesService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\EconomicsService;
use Larapilot\Services\PlanService;
use Larapilot\Services\PrdService;
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
        ->assertSee('Download quote', false)
        ->assertDontSee('Economics is off', false);

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
