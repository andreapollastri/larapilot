<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Larapilot\Services\ConfigService;
use Larapilot\Services\PrdService;
use Larapilot\Services\SpecService;
use Larapilot\Services\ValidationService;
use Larapilot\Support\PrdIds;

function revisionPrd(): string
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
**Persona:** Freelancer · **FRs:** FR-001, FR-002 · **MoSCoW:** Must

### J-002: Hand the books to the accountant
**Persona:** Freelancer · **FRs:** FR-003 · **MoSCoW:** Should

## Domain Model
| Entity | What it is | Key states / lifecycle | Relations | Owner persona |
| --- | --- | --- | --- | --- |
| Invoice | A bill | Draft → Sent → Paid | belongs to Client | Freelancer |

## Functional Requirements
### FR-001: Issue an invoice
**MoSCoW:** Must · **Journey:** J-001 · **Persona:** Freelancer
**Done means:**
- a Sent invoice cannot be edited
**Depends on:** NFR-001 · Q-001

### FR-002: Send the invoice by email
**MoSCoW:** Must · **Journey:** J-001 · **Persona:** Freelancer

### FR-003: Export invoices as CSV
**MoSCoW:** Should · **Journey:** J-002 · **Persona:** Freelancer

## Non-Functional Requirements
| ID | Category | Target | Applies to | Verified by |
| --- | --- | --- | --- | --- |
| NFR-001 | Performance | p95 < 300 ms | J-001 | k6 |

## MVP Scope
**Project Kind:** Application
**Delivery Target:** MVP

## Risks & Assumptions
**Kill condition:** fewer than 5 sign-ups

### Open questions
| ID | Question | Blocks | Owner | Needed by |
| --- | --- | --- | --- | --- |
| Q-001 | Which tax regimes at launch? | FR-001 | Client | 2026-10-15 |

## Technical Architecture
**Budget Sensitivity:** Tracked

## PRD Revision History
| Date | Trigger | Summary |
| --- | --- | --- |
| 2026-09-01 | larapilot-inception | Initial PRD |
MD;
}

function tracedSpecBody(string $traces): string
{
    return "**Traces to:** {$traces}\n\n".validSpecBody();
}

function prepareRevisionProject(): void
{
    test()->artisan('larapilot:install')->assertSuccessful();
    app(PrdService::class)->write(revisionPrd());
}

it('reads the ids a prd defines with their type, title and moscow', function (): void {
    $defined = PrdIds::defined(revisionPrd());

    expect(array_keys($defined))->toBe(['FR-001', 'FR-002', 'FR-003', 'J-001', 'J-002', 'NFR-001', 'Q-001'])
        ->and($defined['FR-001'])->toBe(['type' => 'FR', 'title' => 'Issue an invoice', 'moscow' => 'Must', 'count' => 1])
        ->and($defined['FR-003']['moscow'])->toBe('Should')
        ->and($defined['J-001']['title'])->toBe('Issue an invoice')
        ->and($defined['J-001']['type'])->toBe('J')
        ->and($defined['NFR-001'])->toBe(['type' => 'NFR', 'title' => 'Performance', 'moscow' => null, 'count' => 1])
        ->and($defined['Q-001']['type'])->toBe('Q');

    expect(PrdIds::cited('Covers FR-001, nfr-001 and J-002; not XFR-9 or FR-ABC.'))
        ->toBe(['FR-001', 'NFR-001', 'J-002'])
        ->and(PrdIds::isValid('NFR-12'))->toBeTrue()
        ->and(PrdIds::isValid('US-001'))->toBeFalse();
});

it('finds no id problem in a consistent prd', function (): void {
    $result = app(ValidationService::class)->validatePrd(revisionPrd());

    expect($result['ok'])->toBeTrue()
        ->and($result['findings'])->toBe([]);
});

it('warns about duplicate ids and dangling references without failing', function (): void {
    $prd = str_replace(
        '## Non-Functional Requirements',
        "### FR-002: Send reminders\n**MoSCoW:** Could · **Depends on:** FR-009, NFR-004\n\n## Non-Functional Requirements",
        revisionPrd()
    );

    $result = app(ValidationService::class)->validatePrd($prd);

    $byCode = [];

    foreach ($result['findings'] as $finding) {
        $byCode[$finding['code']][] = $finding['path'];
    }

    expect($result['ok'])->toBeTrue()
        ->and($byCode['PRD_DUPLICATE_ID'])->toBe(['FR-002'])
        ->and($byCode['PRD_DANGLING_REFERENCE'])->toBe(['FR-009', 'NFR-004'])
        ->and(array_unique(array_column($result['findings'], 'severity')))->toBe(['warning']);
});

it('does not count ids named in the revision history as dangling', function (): void {
    $prd = revisionPrd()."| 2026-10-02 | larapilot-prd — Re-scope | FR-007 merged into FR-003 before it was written |\n";

    expect(PrdIds::dangling($prd))->toBe([])
        ->and(PrdIds::dangling(str_replace('## PRD Revision History', '## Cronologia revisioni PRD', $prd)))->toBe([])
        ->and(PrdIds::dangling($prd."\n## Notes\nSee FR-007.\n"))->toBe(['FR-007']);
});

it('keeps a retired requirement defined so its citations stay valid', function (): void {
    $prd = str_replace(
        "**MoSCoW:** Should · **Journey:** J-002 · **Persona:** Freelancer\n",
        "**MoSCoW:** Won't · **Journey:** J-002 · **Persona:** Freelancer\n**Retired:** 2026-10-02 — replaced by the accountant integration\n",
        revisionPrd()
    );

    $result = app(ValidationService::class)->validatePrd($prd);

    expect($result['findings'])->toBe([])
        ->and(PrdIds::defined($prd)['FR-003']['moscow'])->toBe("Won't");
});

it('traces the ids of a revision to the specs that cite them with an action per status', function (): void {
    prepareRevisionProject();

    addSpec(['code' => 'US-001', 'title' => 'Issue an invoice', 'body' => tracedSpecBody('J-001 · FR-001 (MoSCoW: Must) · NFR-001')]);
    addSpec(['code' => 'US-002', 'title' => 'Email the invoice', 'body' => tracedSpecBody('J-001 · FR-002 (MoSCoW: Must)')]);
    addSpec(['code' => 'US-003', 'title' => 'CSV export for FR-003', 'body' => validSpecBody()]);
    addSpec(['code' => 'US-004', 'title' => 'Dashboard', 'body' => tracedSpecBody('FR-001')]);
    addSpec(['code' => 'US-005', 'title' => 'Unrelated', 'body' => validSpecBody()]);

    $config = app(ConfigService::class);
    $specs = app(SpecService::class);
    $specs->setStatus('US-001', $config->status('done'));
    $specs->setStatus('US-002', $config->status('review'));
    $specs->setStatus('US-004', $config->status('planned'));

    expect(Artisan::call('larapilot:prd-impact', ['--ids' => 'FR-001, fr-003,NFR-001,FR-099']))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);
    $data = $envelope['data'];
    $actions = array_column($data['specs'], 'action', 'code');
    $matches = array_column($data['specs'], 'matches', 'code');

    expect($envelope['kind'])->toBe('prd_impact')
        ->and($data['scope'])->toBe('ids')
        ->and($actions)->toBe([
            'US-001' => 'new_spec',
            'US-003' => 'update_spec',
            'US-004' => 'update_and_replan',
        ])
        ->and($matches['US-001'])->toBe(['FR-001', 'NFR-001'])
        ->and($matches['US-003'])->toBe(['FR-003'])
        ->and($data['unknown'])->toBe(['FR-099'])
        ->and($data['untraced'])->toBe([])
        ->and($data['summary'])->toBe([
            'ids' => 4,
            'specs' => 3,
            'by_action' => ['new_spec' => 1, 'update_and_replan' => 1, 'update_spec' => 1],
        ])
        ->and($data['specs'][0]['hint'])->toContain('never reopened');

    expect(Artisan::call('larapilot:prd-impact', ['--ids' => 'FR-002']))->toBe(0);

    $review = json_decode(Artisan::output(), true)['data'];

    expect($review['specs'])->toHaveCount(1)
        ->and($review['specs'][0]['action'])->toBe('rework')
        ->and($review['specs'][0]['hint'])->toContain('spec-request-changes US-002');
});

it('traces the whole prd and lists the must requirements no spec covers', function (): void {
    prepareRevisionProject();

    addSpec(['code' => 'US-001', 'title' => 'Issue an invoice', 'body' => tracedSpecBody('J-001 · FR-001 · NFR-001')]);

    expect(Artisan::call('larapilot:prd-impact'))->toBe(0);

    $data = json_decode(Artisan::output(), true)['data'];
    $ids = array_column($data['ids'], 'specs', 'id');

    expect($data['scope'])->toBe('prd')
        ->and(array_keys($ids))->toBe(['FR-001', 'FR-002', 'FR-003', 'J-001', 'J-002', 'NFR-001'])
        ->and($ids['FR-001'])->toBe(['US-001'])
        ->and($data['untraced'])->toBe(['FR-002', 'FR-003', 'J-002'])
        ->and($data['uncovered_must'])->toBe(['FR-002'])
        ->and($data['unknown'])->toBe([]);
});

it('rejects prd-impact without a prd or with a malformed id', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:prd-impact')->assertExitCode(4);

    app(PrdService::class)->write(revisionPrd());

    $this->artisan('larapilot:prd-impact', ['--ids' => 'US-001'])
        ->assertExitCode(2)
        ->expectsOutputToContain('Invalid PRD id');
});

it('re-issues an open spec without touching its status', function (): void {
    prepareRevisionProject();
    addSpec(['code' => 'US-001', 'title' => 'Issue an invoice', 'body' => tracedSpecBody('FR-001')]);

    $config = app(ConfigService::class);
    app(SpecService::class)->setStatus('US-001', $config->status('planned'));

    $payload = [
        'specs' => [[
            'code' => 'US-001',
            'title' => 'Issue an invoice',
            'body' => tracedSpecBody('FR-001 · NFR-001')."\n**PRD revision:** 2026-10-02 — NFR-001 target added\n",
        ]],
    ];

    $this->artisan('larapilot:spec-add', ['--file' => payloadFile($payload, 'tmp-payload-specs.yaml')])
        ->assertSuccessful();

    $spec = app(SpecService::class)->find('US-001');

    expect($spec['status'])->toBe($config->status('planned'))
        ->and($spec['body'])->toContain('**PRD revision:** 2026-10-02');
});

it('ships the prd revision skill, its runtime part and the routing to it', function (): void {
    $root = dirname(__DIR__, 2).'/resources';
    $skill = file_get_contents($root.'/boost/skills/larapilot-prd/SKILL.md');
    $rules = file_get_contents($root.'/larapilot/runtime-ops-3.md');
    $index = file_get_contents($root.'/larapilot/runtime-ops.md');
    $shared = file_get_contents($root.'/larapilot/shared-runtime.md');
    $economy = file_get_contents($root.'/larapilot/runtime-core-economy.md');
    $living = file_get_contents($root.'/larapilot/runtime-ops-1.md');
    $guideline = file_get_contents($root.'/boost/guidelines/core.blade.php');
    $triage = file_get_contents($root.'/boost/skills/larapilot-triage/SKILL.md');
    $inception = file_get_contents($root.'/boost/skills/larapilot-inception/SKILL.md');
    $spec = file_get_contents($root.'/boost/skills/larapilot-spec/SKILL.md');

    expect($skill)->toStartWith("---\nname: larapilot-prd\n")
        ->and($skill)->toContain('## Not this skill')
        ->and($skill)->toContain('The PRD was edited by hand')
        ->and($skill)->toContain('`Apply` | `Revise` | `Cancel`')
        ->and($skill)->toContain('larapilot:prd-impact')
        ->and($skill)->toContain('**no `status` key**')
        ->and($skill)->toContain('_(derived — confirm)_')
        ->and($skill)->toContain('Do not renumber or reuse an id')
        ->and($skill)->toContain('Do not reopen a `DONE` spec');

    foreach (['Editorial', 'Sharpen', 'Re-scope', 'Re-model', 'Re-decide', 'Upgrade', 'Pivot'] as $kind) {
        expect($rules)->toContain("| **{$kind}** |")
            ->and($skill)->toContain("**{$kind}**");
    }

    expect($rules)->toContain('## PRD Revision')
        ->and($rules)->toContain('### Identifier stability')
        ->and($rules)->toContain('Never renumber, never reuse')
        ->and($rules)->toContain('### Impact on the backlog')
        ->and($rules)->toContain('`spec-request-changes` accepts a spec in `REVIEW` only');

    foreach (['update_spec', 'update_and_replan', 'coordinate', 'rework', 'new_spec'] as $action) {
        expect($rules)->toContain("`{$action}`")
            ->and($skill)->toContain("`{$action}`");
    }

    expect($index)->toContain('runtime-ops-3.md')
        ->and($index)->toContain('**`larapilot-prd`**')
        ->and($shared)->toContain('Feature, bug, prd, ship')
        ->and($economy)->toContain('**`larapilot-prd`**')
        ->and($living)->toContain('`/larapilot-prd`')
        ->and($guideline)->toContain('`larapilot-prd`')
        ->and($triage)->toContain('`/larapilot-prd`')
        ->and($inception)->toContain('`Revise it — /larapilot-prd`')
        ->and($spec)->toContain('larapilot:prd-impact');
});

it('routes bug fixes by spec status and quotes the promise broken', function (): void {
    $root = dirname(__DIR__, 2).'/resources';
    $bug = file_get_contents($root.'/boost/skills/larapilot-bug/SKILL.md');
    $feature = file_get_contents($root.'/boost/skills/larapilot-feature/SKILL.md');

    expect($bug)->toContain('| Maps to a spec in `REVIEW` |')
        ->and($bug)->toContain('| Maps to a spec `DONE` | New fix spec')
        ->and($bug)->toContain('the only status that command accepts')
        ->and($bug)->not->toContain('or recently `DONE`')
        ->and($bug)->toContain("```yaml\nmarkdown: |")
        ->and($bug)->not->toContain("```yaml\nfeedback: |")
        ->and($bug)->toContain('**Promise broken**')
        ->and($bug)->toContain('**Already reported?**')
        ->and($bug)->toContain('Breaches an NFR target')
        ->and($bug)->toContain('`/larapilot-prd`');

    expect($feature)->toContain('### 2b. Ready check and readback')
        ->and($feature)->toContain('`Add to backlog` | `Revise`')
        ->and($feature)->toContain('**Change request**')
        ->and($feature)->toContain('edit the FR **in place**')
        ->and($feature)->toContain('hand over to `/larapilot-prd`')
        ->and($feature)->toContain('larapilot:prd-impact');
});

it('keeps every askquestion round of bug and feature at three questions', function (): void {
    $root = dirname(__DIR__, 2).'/resources';

    foreach (['larapilot-bug', 'larapilot-feature'] as $name) {
        $skill = file_get_contents($root."/boost/skills/{$name}/SKILL.md");

        preg_match_all('/^\*\*Round \d+ — [^\n]*\n\n(?:[^\n]+\n\n)?((?:- [^\n]*\n)+)/m', $skill, $rounds, PREG_SET_ORDER);

        expect($rounds)->not->toBeEmpty();

        foreach ($rounds as $round) {
            expect(substr_count($round[1], "\n"))->toBeLessThanOrEqual(3, "{$name}: ".strtok($round[0], "\n"));
        }
    }
});
