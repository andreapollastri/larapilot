<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Larapilot\Services\ChoicesService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\ValidationService;

function richPrd(): string
{
    return <<<'MD'
# Product

## Elevator Pitch
Invoicing for freelancers.

## Vision
Freelancers get paid on time.

## User Personas
### Freelancer
- **Role:** solo professional

## User Journeys
### J-001: Issue an invoice _(core journey)_
**Persona:** Freelancer · **FRs:** FR-001 · **MoSCoW:** Must

## Domain Model
| Entity | What it is | Key states / lifecycle | Relations | Owner persona |
| --- | --- | --- | --- | --- |
| Invoice | A bill | Draft → Sent → Paid | belongs to Client | Freelancer |

## Functional Requirements
### FR-001: Issue an invoice
**MoSCoW:** Must · **Journey:** J-001 · **Persona:** Freelancer
**Done means:**
- a Sent invoice cannot be edited

## Non-Functional Requirements
| ID | Category | Target | Applies to | Verified by |
| --- | --- | --- | --- | --- |
| NFR-001 | Performance | p95 < 300 ms | J-001 | k6 |

## MVP Scope
**Project Kind:** Application
**Delivery Target:** MVP
**Business Model:** SaaS subscription
**Prior Art:** Build anyway
**Success signal:** 20 paying freelancers in 90 days

## Risks & Assumptions
**Riskiest assumption:** freelancers pay for invoicing
**Kill condition:** fewer than 5 sign-ups after launch month

## Technical Architecture
**Budget Sensitivity:** Tracked
MD;
}

it('warns about missing recommended prd sections without failing validation', function (): void {
    $result = app(ValidationService::class)->validatePrd(validPrd());

    $codes = array_column($result['findings'], 'code');
    $paths = array_column($result['findings'], 'path');

    expect($result['ok'])->toBeTrue()
        ->and(array_unique($codes))->toBe(['PRD_RECOMMENDED_SECTION'])
        ->and($paths)->toBe(['User Journeys', 'Domain Model', 'Non-Functional Requirements', 'Risks & Assumptions'])
        ->and(array_unique(array_column($result['findings'], 'severity')))->toBe(['warning']);
});

it('reports no findings on a prd with every inception section', function (): void {
    $result = app(ValidationService::class)->validatePrd(richPrd());

    expect($result['ok'])->toBeTrue()
        ->and($result['findings'])->toBe([]);
});

it('recognizes localized recommended sections', function (): void {
    $italian = <<<'MD'
# Prodotto

## Sintesi
Fatture.

## Visione
Pagamenti puntuali.

## Personas utente
Freelance.

## Percorsi utente
### J-001: Emettere una fattura

## Modello di dominio
| Entità | Cos'è |
| --- | --- |
| Fattura | Un documento |

## Requisiti funzionali
### FR-001: Emettere una fattura
**MoSCoW:** Must

## Requisiti non funzionali
| ID | Categoria | Target |
| --- | --- | --- |

## Ambito MVP
**Project Kind:** Application

## Rischi e assunzioni
**Kill condition:** nessuna iscrizione

## Architettura tecnica
Monolite Laravel.
MD;

    $result = app(ValidationService::class)->validatePrd($italian);

    expect($result['ok'])->toBeTrue()
        ->and($result['findings'])->toBe([]);
});

it('warns about functional requirements without a moscow line', function (): void {
    $prd = str_replace(
        "**MoSCoW:** Must · **Journey:** J-001 · **Persona:** Freelancer\n",
        '',
        richPrd()
    );
    $prd = str_replace(
        '## Non-Functional Requirements',
        "### FR-002: Send reminders\n**MoSCoW:** Should\n\n### FR-003: Export CSV\n**Done means:**\n- a file downloads\n\n## Non-Functional Requirements",
        $prd
    );

    $result = app(ValidationService::class)->validatePrd($prd);

    $moscow = array_values(array_filter(
        $result['findings'],
        static fn (array $finding): bool => $finding['code'] === 'PRD_FR_MISSING_MOSCOW'
    ));

    expect($result['ok'])->toBeTrue()
        ->and(array_column($moscow, 'path'))->toBe(['FR-001', 'FR-003'])
        ->and($moscow[0]['severity'])->toBe('warning');
});

it('keeps validate-prd successful when only warnings are present', function (): void {
    $this->artisan('larapilot:prd-write', ['--content' => validPrd()])->assertSuccessful();

    expect(Artisan::call('larapilot:validate-prd'))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['ok'])->toBeTrue()
        ->and($envelope['data']['findings'])->toHaveCount(4)
        ->and($envelope['data']['findings'][0]['code'])->toBe('PRD_RECOMMENDED_SECTION');
});

it('persists prior art, success signal and kill condition through choices-set flags', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:choices-set', [
        '--prior-art' => 'Integrate as dependency',
        '--success-signal' => '20 paying freelancers in 90 days',
        '--kill-condition' => 'fewer than 5 sign-ups after launch month',
    ])->assertSuccessful();

    $choices = app(ChoicesService::class);
    $raw = $choices->read();

    expect($raw['prior_art'])->toBe('Integrate as dependency')
        ->and($raw['success_signal'])->toBe('20 paying freelancers in 90 days')
        ->and($raw['kill_condition'])->toBe('fewer than 5 sign-ups after launch month');

    $dashboard = $choices->dashboard();

    expect($dashboard['inception']['Prior Art'])->toBe('Integrate as dependency')
        ->and($dashboard['inception']['Success signal'])->toBe('20 paying freelancers in 90 days')
        ->and($dashboard['inception']['Kill condition'])->toBe('fewer than 5 sign-ups after launch month')
        ->and($dashboard['settings']['options'])->toHaveKey('prior_art')
        ->and($dashboard['settings']['current']['prior_art'])->toBe('YES');
});

it('defaults the prior art setting to on and lets settings-set switch it off', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $config = app(ConfigService::class);

    expect($config->priorArtEnabled())->toBeTrue()
        ->and($config->settings()['prior_art'])->toBe('YES');

    $this->artisan('larapilot:settings-set', ['--prior-art' => 'NO'])->assertSuccessful();

    expect(app(ConfigService::class)->priorArtEnabled())->toBeFalse();

    $this->artisan('larapilot:settings-set', ['--prior-art' => 'maybe'])->assertExitCode(2);

    $this->get('/larapilot/settings')
        ->assertOk()
        ->assertSee('Prior art check');
});

it('ships the discovery parts for prior art and requirement quality', function (): void {
    $root = dirname(__DIR__, 2).'/resources';
    $index = file_get_contents($root.'/larapilot/runtime-discovery.md');
    $six = file_get_contents($root.'/larapilot/runtime-discovery-6.md');
    $seven = file_get_contents($root.'/larapilot/runtime-discovery-7.md');
    $inception = file_get_contents($root.'/boost/skills/larapilot-inception/SKILL.md');
    $spec = file_get_contents($root.'/boost/skills/larapilot-spec/SKILL.md');
    $feature = file_get_contents($root.'/boost/skills/larapilot-feature/SKILL.md');

    expect($index)->toContain('runtime-discovery-6.md')
        ->and($index)->toContain('runtime-discovery-7.md')
        ->and($six)->toContain('## Prior Art & Open-Source Alternatives')
        ->and($six)->toContain('## Domain Model & User Journeys')
        ->and($six)->toContain('`Build anyway` | `Adopt / fork` | `Integrate as dependency` | `Not checked`')
        ->and($seven)->toContain('## Requirement Quality')
        ->and($seven)->toContain('## Non-Functional Requirements')
        ->and($seven)->toContain('## Risks & Assumptions')
        ->and($seven)->toContain('## Definition of Ready & Readback');

    expect($inception)->toContain('🔎 Tom')
        ->and($inception)->toContain('**Prior Art** (Sebastian)')
        ->and($inception)->toContain('`data.settings.prior_art`')
        ->and($inception)->toContain('## User Journeys')
        ->and($inception)->toContain('## Domain Model')
        ->and($inception)->toContain('## Non-Functional Requirements')
        ->and($inception)->toContain('## Risks & Assumptions')
        ->and($inception)->toContain('**Prior Art:** Build anyway | Adopt / fork | Integrate as dependency | Not checked')
        ->and($inception)->toContain('**Done means:**')
        ->and($inception)->toContain('`Write the PRD` | `Revise`');

    expect($spec)->toContain('## User Journeys')
        ->and($spec)->toContain('Done means')
        ->and($spec)->toContain('**Traces to:** J-XXX');

    expect($feature)->toContain('Prior art at feature level');
});
