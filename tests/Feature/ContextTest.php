<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Larapilot\Services\ConfigService;
use Larapilot\Services\ContextService;
use Larapilot\Support\ContextManifest;
use Larapilot\Support\EnvWriter;
use Larapilot\Support\SharedRuntime;

/**
 * Run `larapilot:context` and return its `data`.
 *
 * @param  array<string, mixed>  $parameters
 * @return array<string, mixed>
 */
function skillContext(string $skill, array $parameters = []): array
{
    expect(Artisan::call('larapilot:context', ['skill' => $skill] + $parameters))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['kind'])->toBe('context');

    return $envelope['data'];
}

/**
 * The files an envelope asks the session to read.
 *
 * @param  array<string, mixed>  $data
 * @return list<string>
 */
function contextReads(array $data): array
{
    return array_column($data['runtime']['read'], 'file');
}

/**
 * A compiled runtime file, as the agent reads it.
 *
 * @param  array<string, mixed>  $data
 */
function compiledPack(array $data, string $file): string
{
    return (string) file_get_contents($data['runtime']['dir'].'/'.$file);
}

// Other suites link an external frontend in the sandbox `.env` and may leave
// it there: these tests measure a project with none.
beforeEach(function (): void {
    EnvWriter::set('LARAPILOT_FRONTEND_REPO_PATH', '');
});

function packagedSkills(): array
{
    return array_map(
        static fn (string $folder): string => substr(basename($folder), strlen('larapilot-')),
        glob(dirname(__DIR__, 2).'/resources/boost/skills/larapilot-*', GLOB_ONLYDIR) ?: []
    );
}

it('answers settings, paths, and the files a skill reads in one call', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $data = skillContext('implement');

    expect($data['skill'])->toBe('larapilot-implement')
        ->and($data['session'])->toMatch('/^[a-z0-9]{6}$/')
        ->and($data['settings']['git_mode'])->toBe('GITFLOW')
        ->and($data['paths'])->toHaveKeys(['prd', 'planning', 'review', 'dev_docs'])
        ->and($data['paths'])->not->toHaveKey('economics')
        ->and($data['project'])->toBe(['prd' => false, 'specs' => 0])
        ->and($data['dev_docs']['documented'])->toBeFalse()
        ->and($data['frontend'])->toHaveKeys(['repo_path', 'stack', 'configured'])
        ->and($data['usage_log'])->toBe('php artisan larapilot:usage-log --category=implementation --skill=larapilot-implement --tokens={N} --minutes={M} --estimated');

    expect(contextReads($data))->toBe([
        'core-cli.md',
        'core-settings.md',
        'core-economy.md',
        'core-subagents.md',
        'delivery-1.md',
        'delivery-2.md',
        'dev-docs.md',
        'dev-docs-catchup.md',
    ])
        ->and($data['runtime']['loaded'])->toBe([])
        ->and(array_column($data['runtime']['on_demand'], 'file'))->toContain('delivery-3.md', 'ux-1.md', 'task-templates.md', 'spec-worker.md');

    // Every file named is on disk, whole, and small enough for one read.
    foreach (array_merge($data['runtime']['read'], $data['runtime']['on_demand']) as $file) {
        $path = $data['runtime']['dir'].'/'.$file['file'];

        expect($path)->toBeFile()
            ->and(filesize($path))->toBeLessThanOrEqual(15000, $file['file'])
            ->and($file['tokens'])->toBe(ContextService::tokens((string) file_get_contents($path)));
    }

    $tokens = $data['runtime']['tokens'];

    expect($tokens['read'])->toBe(array_sum(array_column($data['runtime']['read'], 'tokens')))
        ->and($tokens['total'])->toBe($tokens['skill'] + $tokens['read'] + $tokens['loaded'])
        ->and($tokens['skill'])->toBeGreaterThan(0);

    // The cache is derived: it keeps itself out of git.
    expect(file_get_contents(base_path('.larapilot/cache/.gitignore')))->toBe("*\n");
});

it('lists a file once for a session, whatever skill asks for it', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $triage = skillContext('triage');
    $session = $triage['session'];

    expect(contextReads($triage))->toBe(['core-cli.md', 'core-settings.md', 'core-economy.md']);

    // The skill triage hands off to pays only for what triage did not read.
    $bug = skillContext('bug', ['--session' => $session]);

    expect($bug['session'])->toBe($session)
        ->and(contextReads($bug))->toBe(['core-language.md', 'ops-1.md', 'ops-2.md'])
        ->and($bug['runtime']['loaded'])->toBe(['core-cli.md', 'core-settings.md', 'core-economy.md'])
        ->and($bug['runtime']['tokens']['loaded'])->toBe($triage['runtime']['tokens']['read']);

    // The same skill again: nothing left to read.
    $again = skillContext('bug', ['--session' => $session]);

    expect(contextReads($again))->toBe([])
        ->and($again['runtime']['loaded'])->toHaveCount(6);

    // After a compaction the rules are gone: everything is read again.
    $fresh = skillContext('bug', ['--session' => $session, '--fresh' => true]);

    expect($fresh['session'])->not->toBe($session)
        ->and(contextReads($fresh))->toHaveCount(6)
        ->and($fresh['runtime']['loaded'])->toBe([]);

    // A token nobody issued, or a mangled one, starts from nothing.
    foreach (['zzzzzz', '../../x', 'ABC'] as $token) {
        $unknown = skillContext('bug', ['--session' => $token]);

        expect($unknown['session'])->not->toBe($token)
            ->and(contextReads($unknown))->toHaveCount(6);
    }
});

it('compiles the packs for the settings of the project', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $data = skillContext('plan');
    $git = compiledPack($data, 'delivery-2.md');
    $settings = compiledPack($data, 'core-settings.md');
    $templates = compiledPack($data, 'task-templates.md');

    // Defaults: GITFLOW, NORMAL, STANDARD — and none of the other values.
    expect($git)->toContain('`git_mode` is **`GITFLOW`**', 'TASK-00 bootstrap', 'A missing remote push or PR is **not** a reject reason.')
        ->not->toContain('`git_mode` is **`GITFLOW_PUSH`**', '`git_mode` is **`NO_GITFLOW`**', '<!--', 'Effort gate — `ECO` docs deferral')
        ->and($settings)->toContain('- **`STANDARD`** — Normal Larapilot behavior', '- **`NORMAL`** — Standard feature/unit/policy/API/queue tests', 'the `usage_log` command')
        ->not->toContain('- **`ECO`** —', '- **`MAX`** —', '- **`BEST`** —', '- **`GRANULAR`** —', '<!--', 'Defaults when unset')
        ->and($templates)->toContain('### `GITFLOW` (no automatic push)', '### `MINIMAL` / `NORMAL`')
        ->not->toContain('### `GITFLOW_PUSH`', 'Release branch variant', '### `BEST`', 'Frontend task — external repo', '<!--');

    // No opt-in toggle is on: its file has nothing to say and is not listed.
    expect(contextReads($data))->not->toContain('core-settings-2.md')
        ->and($data['runtime']['dir'].'/core-settings-2.md')->not->toBeFile();

    $session = $data['session'];

    $this->artisan('larapilot:settings-set', [
        '--git-mode' => 'NO_GITFLOW',
        '--testing' => 'BEST',
        '--aikido' => 'YES',
        '--github' => 'YES',
    ])->assertSuccessful();

    // Only what the change touched is read again; the rest stays loaded.
    $changed = skillContext('plan', ['--session' => $session]);

    expect(contextReads($changed))->toContain('core-settings.md', 'core-settings-2.md', 'delivery-1.md', 'delivery-2.md', 'task-templates.md')
        ->not->toContain('core-cli.md', 'dev-docs.md', 'core-language.md')
        ->and($changed['runtime']['loaded'])->toContain('core-cli.md', 'dev-docs.md');

    expect(compiledPack($changed, 'delivery-2.md'))->toContain('`git_mode` is **`NO_GITFLOW`**', '**Remote forge:**')
        ->not->toContain('TASK-00 bootstrap', '| `develop`')
        ->and(compiledPack($changed, 'delivery-1.md'))->toContain('- **`BEST`** — Full bar', 'Viewport matrix')
        ->and(compiledPack($changed, 'task-templates.md'))->toContain('### `BEST`')
        ->not->toContain('TASK-00 — Git bootstrap', '### `MINIMAL` / `NORMAL`')
        ->and(compiledPack($changed, 'core-settings-2.md'))->toContain('### Aikido (`settings.aikido`)', '- **`github`** — `gh` CLI', 'Only the user waives a finding')
        ->not->toContain('### Notifications', '- **`gitlab`**', 'larapilot:aikido-status', 'Dashboard auth');
});

it('gives the settings skill every value on demand, and the others only theirs', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $data = skillContext('settings');
    $onDemand = array_column($data['runtime']['on_demand'], 'file');

    expect(contextReads($data))->toBe(['core-cli.md', 'core-settings.md', 'core-economy.md'])
        ->and($onDemand)->toContain('core-settings.full.md', 'core-settings-2.full.md', 'economics-1.md');

    expect(compiledPack($data, 'core-settings.full.md'))->toContain('- **`ECO`** —', '- **`MAX`** —', '- **`GITFLOW_PUSH`** —', 'Defaults when unset')
        ->not->toContain('<!--')
        ->and(compiledPack($data, 'core-settings-2.full.md'))->toContain('### Dashboard auth', '### Notifications', 'larapilot:aikido-status', 'larapilot:errors-plan')
        ->not->toContain('<!--');

    $this->artisan('larapilot:settings-set', ['--account' => 'FREELANCE'])->assertSuccessful();

    expect(contextReads(skillContext('settings')))->toContain('economics-1.md');
});

it('resolves what depends on the state of the project', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    // A project with code and no domain docs owes the catch-up to its first change.
    expect(contextReads(skillContext('implement')))->toContain('dev-docs-catchup.md');

    file_put_contents(base_path('.larapilot/docs/devs/billing.md'), '# Billing');
    app()->forgetInstance(ConfigService::class);
    app()->forgetInstance(ContextService::class);

    $documented = skillContext('implement');

    expect(contextReads($documented))->not->toContain('dev-docs-catchup.md')
        ->and(array_column($documented['runtime']['on_demand'], 'file'))->toContain('dev-docs-catchup.md')
        ->and($documented['dev_docs']['domains'])->toBe(['billing']);

    // Client documents and legacy snapshots bring their rules with them.
    expect(contextReads(skillContext('spec')))->not->toContain('discovery-3.md');

    file_put_contents(base_path('.larapilot/client-materials/brief.md'), '# Brief');

    expect(contextReads(skillContext('spec')))->toContain('discovery-3.md');
});

it('reads the heavy packs up front only under MAX, and spawns nothing under ECO', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $standard = skillContext('plan');

    expect(contextReads($standard))->not->toContain('delivery-3.md')
        ->and(array_column($standard['runtime']['on_demand'], 'file'))->toContain('delivery-3.md', 'delivery-4.md', 'delivery-5.md');

    $this->artisan('larapilot:settings-set', ['--effort' => 'MAX'])->assertSuccessful();

    $max = skillContext('plan');

    expect(contextReads($max))->toContain('delivery-3.md', 'delivery-4.md', 'delivery-5.md')
        ->and(array_column($max['runtime']['on_demand'], 'file'))->not->toContain('delivery-3.md');

    $this->artisan('larapilot:settings-set', ['--effort' => 'ECO'])->assertSuccessful();

    // ECO turns Lucille off: no command to log with.
    $eco = skillContext('plan');

    expect($eco)->not->toHaveKey('usage_log')
        ->and(contextReads($eco))->not->toContain('core-subagents.md');

    // An ECO autopilot plans and implements inline, so it reads what they read.
    $autopilot = skillContext('autopilot');

    expect(contextReads($autopilot))->toContain('delivery-1.md', 'delivery-2.md', 'task-templates.md', 'dev-docs.md')
        ->not->toContain('spec-worker.md');

    expect(compiledPack($autopilot, 'core-subagents.md'))->toContain('**no sub-agent is spawned**', '### Review handoff')
        ->not->toContain('### Where sub-agents are used');

    $this->artisan('larapilot:settings-set', ['--effort' => 'STANDARD'])->assertSuccessful();

    // A delegating parent reads the worker contract, not what the worker reads.
    expect(contextReads(skillContext('autopilot')))->toBe([
        'core-cli.md', 'core-settings.md', 'core-economy.md', 'core-subagents.md', 'spec-worker.md',
    ]);
});

it('tells what the PRD answers without opening it', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:prd-write', ['--content' => richPrdForContext()])->assertSuccessful();
    addSpec();

    $project = skillContext('review')['project'];

    expect($project['prd'])->toBeTrue()
        ->and($project['prd_tokens'])->toBeGreaterThan(50)
        ->and($project['specs'])->toBe(1)
        ->and($project['by_status'])->toBe(['TODO' => 1])
        ->and($project['kind'])->toBe('Application')
        ->and($project['delivery_target'])->toBe('V1 Complete')
        ->and($project['budget_sensitivity'])->toBe('Tracked')
        ->and($project)->not->toHaveKey('admin_panel');
});

function richPrdForContext(): string
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

## Functional Requirements
### FR-001: Issue an invoice
**MoSCoW:** Must · **Journey:** J-001 · **Persona:** Freelancer
**Done means:**
- a Sent invoice cannot be edited

### FR-002: Send reminders
**MoSCoW:** Should
**Done means:**
- a reminder leaves three days after the due date

## Non-Functional Requirements
| ID | Category | Target | Applies to | Verified by |
| --- | --- | --- | --- | --- |
| NFR-001 | Performance | p95 < 300 ms | J-001 | k6 |
| NFR-002 | Security | 2FA for admins | all | Lars gate |

## MVP Scope
**Project Kind:** Application
**Delivery Target:** V1 Complete

### In Scope
- Invoices

## Technical Architecture
**Budget Sensitivity:** Tracked

### Stack
- Laravel 13
MD;
}

it('reads the PRD by the piece', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    expect(Artisan::call('larapilot:prd-show'))->toBe(4);

    $this->artisan('larapilot:prd-write', ['--content' => richPrdForContext()])->assertSuccessful();

    expect(Artisan::call('larapilot:prd-show'))->toBe(0);

    $outline = json_decode(Artisan::output(), true);

    expect($outline['kind'])->toBe('prd_outline')
        ->and(array_column($outline['data']['sections'], 'title'))->toContain('Functional Requirements', 'MVP Scope', 'Technical Architecture')
        ->and(collect($outline['data']['sections'])->firstWhere('title', 'MVP Scope')['parts'])->toBe(['In Scope'])
        ->and(collect($outline['data']['sections'])->firstWhere('title', 'Functional Requirements'))->not->toHaveKey('parts')
        ->and(array_column($outline['data']['ids'], 'id'))->toBe(['FR-001', 'FR-002', 'J-001', 'NFR-001', 'NFR-002'])
        ->and($outline['data']['ids'][0])->toBe(['id' => 'FR-001', 'title' => 'Issue an invoice', 'moscow' => 'Must'])
        ->and(json_encode($outline))->not->toContain('a Sent invoice cannot be edited');

    expect(Artisan::call('larapilot:prd-show', ['--ids' => 'fr-002,NFR-002,J-001,FR-099', '--section' => 'Technical Architecture,In Scope,Nope']))->toBe(0);

    $slice = json_decode(Artisan::output(), true)['data'];
    $blocks = array_column($slice['blocks'], 'markdown', 'id');

    expect(array_keys($blocks))->toBe(['FR-002', 'NFR-002', 'J-001'])
        ->and($blocks['FR-002'])->toStartWith('### FR-002: Send reminders')
        ->toContain('three days after the due date')
        ->not->toContain('FR-001', 'Non-Functional')
        // an NFR is its row, under the header of its table
        ->and($blocks['NFR-002'])->toBe("| ID | Category | Target | Applies to | Verified by |\n| --- | --- | --- | --- | --- |\n| NFR-002 | Security | 2FA for admins | all | Lars gate |")
        ->and($blocks['J-001'])->toContain('**Persona:** Freelancer')
        ->not->toContain('## Functional Requirements')
        ->and(array_column($slice['sections'], 'title'))->toBe(['Technical Architecture', 'In Scope'])
        ->and($slice['sections'][0]['markdown'])->toContain('**Budget Sensitivity:** Tracked', '### Stack')
        ->and($slice['unknown'])->toBe(['FR-099', 'Nope']);

    expect(Artisan::call('larapilot:prd-show', ['--ids' => 'FR-1;rm']))->toBe(2);

    // A section in the language of the PRD answers to its English name.
    $this->artisan('larapilot:prd-write', ['--content' => "# Prodotto\n\n## Architettura tecnica\nMonolite Laravel.\n"])->assertSuccessful();

    expect(Artisan::call('larapilot:prd-show', ['--section' => 'Technical Architecture']))->toBe(0)
        ->and(json_decode(Artisan::output(), true)['data']['sections'][0]['markdown'])->toContain('Monolite Laravel.');

    // More than one answer carries is refused, never cut.
    $this->artisan('larapilot:prd-write', ['--content' => "# Product\n\n## Vision\n".str_repeat("A long paragraph of the vision.\n", 900)])->assertSuccessful();

    expect(Artisan::call('larapilot:prd-show', ['--section' => 'Vision']))->toBe(2);
});

it('lists the backlog without the bodies', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['body' => validSpecBody()."\n\n**Traces to:** J-001 · FR-004 (MoSCoW: Must) · NFR-002", 'epic' => ['code' => 'EP-001', 'title' => 'Auth']]);

    expect(Artisan::call('larapilot:spec-list'))->toBe(0);

    $lean = json_decode(Artisan::output(), true)['data'];

    expect($lean['items'])->toBe([[
        'code' => 'US-001',
        'title' => 'Login',
        'status' => 'TODO',
        'priority' => 'HIGH',
        'points' => 3,
        'epic' => 'EP-001',
        'cites' => ['J-001', 'FR-004', 'NFR-002'],
    ]])
        ->and($lean['summary'])->toBe([
            'count' => 1,
            'codes' => ['US-001'],
            'last_code' => 'US-001',
            'epics' => ['EP-001' => 'Auth'],
            'by_status' => ['TODO' => 1],
        ]);

    expect(Artisan::call('larapilot:spec-list', ['--full' => true]))->toBe(0);

    $full = json_decode(Artisan::output(), true)['data'];

    expect($full['items'][0]['body'])->toContain('**Acceptance Criteria**')
        ->and($full['summary']['titles'])->toBe(['US-001' => 'Login']);

    expect(Artisan::call('larapilot:spec-list', ['--status' => 'DONE']))->toBe(0)
        ->and(json_decode(Artisan::output(), true)['data']['items'])->toBe([]);
});

it('serves a custom skill the core and the packs it names', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:custom-skill-add', [
        '--name' => 'deploy-checklist',
        '--content' => "---\nname: deploy-checklist\ndescription: Run the team deploy checklist.\n---\n\n# Deploy checklist\n",
    ])->assertSuccessful();

    $data = skillContext('deploy-checklist', ['--with' => 'dev-docs,ship']);

    expect($data['skill'])->toBe('deploy-checklist')
        ->and(contextReads($data))->toBe(['core-cli.md', 'core-settings.md', 'core-economy.md', 'dev-docs.md', 'ship-1.md', 'ship-2.md', 'ship-3.md'])
        // a skill of the project may use any path
        ->and($data['paths'])->toHaveKeys(['prd', 'economics', 'custom_skills'])
        ->and($data['usage_log'])->toContain('--category=other --skill=deploy-checklist');

    expect(Artisan::call('larapilot:context', ['skill' => 'implment']))->toBe(2)
        ->and(Artisan::call('larapilot:context', ['skill' => 'plan', '--with' => 'nope']))->toBe(2);

    // The name works as the slash command writes it.
    expect(skillContext('/larapilot-plan')['skill'])->toBe('larapilot-plan')
        ->and(skillContext('LARAPILOT-PLAN')['skill'])->toBe('larapilot-plan');
});

it('evaluates the conditions a pack and the manifest may carry', function (): void {
    $facts = ['git_mode' => 'GITFLOW', 'testing' => 'NORMAL', 'release_mode' => 'NO'];

    expect(ContextService::evaluate('git_mode=GITFLOW', $facts))->toBeTrue()
        ->and(ContextService::evaluate('git_mode=GITFLOW|GITFLOW_PUSH', $facts))->toBeTrue()
        ->and(ContextService::evaluate('git_mode!=NO_GITFLOW', $facts))->toBeTrue()
        ->and(ContextService::evaluate('git_mode=NO_GITFLOW', $facts))->toBeFalse()
        ->and(ContextService::evaluate('release_mode=YES and git_mode!=NO_GITFLOW', $facts))->toBeFalse()
        ->and(ContextService::evaluate('release_mode=YES or testing=NORMAL', $facts))->toBeTrue()
        ->and(ContextService::evaluate('release_mode=YES or testing=BEST', $facts))->toBeFalse()
        // an unknown key reads as empty
        ->and(ContextService::evaluate('nope=YES', $facts))->toBeFalse()
        ->and(ContextService::evaluate('nope!=YES', $facts))->toBeTrue()
        ->and(ContextService::evaluate('not a condition', $facts))->toBeFalse();

    $markdown = "Intro\n\n<!-- when: git_mode!=NO_GITFLOW -->\nGitflow\n<!-- when: git_mode=GITFLOW_PUSH -->\nPush\n<!-- end -->\n<!-- end -->\n<!-- when: git_mode=NO_GITFLOW -->\nPlain\n<!-- end -->\n\n\n\nOutro\n";

    expect(ContextService::filter($markdown, $facts))->toBe("Intro\n\nGitflow\n\nOutro\n")
        // with no facts every block is kept, and no marker is
        ->and(ContextService::filter($markdown, null))->toBe("Intro\n\nGitflow\nPush\nPlain\n\nOutro\n");
});

it('ships packs whose markers are balanced and name real settings', function (): void {
    $context = app(ContextService::class);
    $config = app(ConfigService::class);
    $settings = $config->defaultSettings();

    $allowed = [
        'effort' => $config->allowedEfforts(),
        'backlog' => $config->allowedBacklogModes(),
        'git_mode' => $config->allowedGitModes(),
        'testing' => $config->allowedTestingModes(),
        'account' => $config->allowedAccountModes(),
    ] + array_intersect_key(ContextManifest::FACTS, array_flip(ContextManifest::MARKER_FACTS));

    $checked = 0;

    foreach ($context->sources() as $pack => $path) {
        $source = (string) file_get_contents($path);

        expect(substr_count($source, '<!-- end -->'))->toBe(count(ContextService::markers($source)), $pack)
            ->and(strlen($source))->toBeLessThanOrEqual(15000, $pack);

        foreach (ContextService::markers($source) as $condition) {
            foreach (preg_split('/\s+(?:and|or)\s+/i', $condition) ?: [] as $clause) {
                expect(preg_match('/^([a-z_]+)(!=|=)(.+)$/', trim($clause), $match))->toBe(1, "{$pack}: {$condition}");

                $key = $match[1];
                $values = explode('|', $match[3]);

                expect(array_key_exists($key, $settings) || array_key_exists($key, $allowed))->toBeTrue("{$pack}: unknown key {$key}");

                foreach ($values as $value) {
                    expect(in_array($value, $allowed[$key] ?? ['YES', 'NO'], true))->toBeTrue("{$pack}: {$key}={$value}");
                }

                $checked++;
            }
        }
    }

    expect($checked)->toBeGreaterThan(60);
});

it('keeps the manifest, the skills, and the packs in step', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $context = app(ContextService::class);
    $manifest = ContextManifest::skills();
    $packs = array_keys($context->sources());
    $paths = array_keys(app(ConfigService::class)->setupInfo()['paths']);
    $root = dirname(__DIR__, 2).'/resources';

    // One entry for each packaged skill, and none for a skill that is gone.
    expect(array_keys($manifest))->toEqualCanonicalizing(packagedSkills());

    $named = ContextManifest::CORE;

    foreach ($manifest as $skill => $definition) {
        foreach (array_merge($definition['read'] ?? [], array_keys($definition['on_demand'] ?? []), $definition['deep'] ?? []) as $entry) {
            [$pack, $condition] = ContextManifest::entry($entry);
            $pack = str_ends_with($pack, '.full') ? substr($pack, 0, -5) : $pack;
            $named[] = $pack;

            expect(in_array($pack, $packs, true))->toBeTrue("{$skill}: unknown pack {$pack}");

            if ($condition !== null) {
                foreach (preg_split('/\s+(?:and|or)\s+/i', $condition) ?: [] as $clause) {
                    $key = (string) preg_replace('/(!=|=).*$/', '', trim($clause));

                    expect(array_key_exists($key, $context->facts()))->toBeTrue("{$skill}: unknown fact {$key}");
                }
            }
        }

        foreach ($definition['deep'] ?? [] as $pack) {
            expect($definition['on_demand'])->toHaveKey($pack);
        }

        foreach ($definition['paths'] ?? [] as $key) {
            expect(in_array($key, $paths, true))->toBeTrue("{$skill}: unknown path {$key}");
        }

        $body = (string) file_get_contents("{$root}/boost/skills/larapilot-{$skill}/SKILL.md");

        // Every skill opens with the one call, and no longer walks the index.
        expect($body)->toContain("php artisan larapilot:context {$skill}")
            ->not->toContain('## Shared Runtime', 'Read protocol', '.larapilot/shared-runtime.md');

        // A path a skill names is a path its envelope carries.
        preg_match_all('/\{?paths\.([a-z_]+)\}?/', $body, $matches);

        foreach (array_unique($matches[1]) as $key) {
            expect(in_array($key, $definition['paths'] ?? [], true))->toBeTrue("{$skill}: paths.{$key} is not in its envelope");
        }

        // A compiled file a skill names is one its envelope can hand it.
        $reachable = array_map(
            static fn (string $entry): string => ContextManifest::entry($entry)[0],
            array_merge(ContextManifest::CORE, $definition['read'] ?? [], array_keys($definition['on_demand'] ?? []))
        );

        foreach ($definition['include'] ?? [] as $included => $condition) {
            $reachable = array_merge($reachable, array_map(
                static fn (string $entry): string => ContextManifest::entry($entry)[0],
                $manifest[$included]['read'] ?? []
            ));
        }

        preg_match_all('/`((?:core|delivery|discovery|ops|ux|ship|economics|dev-docs|spec-worker|release|project-docs|custom-skills|task-templates|hooks)[a-z0-9-]*(?:\.full)?)\.md`/', $body, $files);

        foreach (array_unique($files[1]) as $pack) {
            expect(in_array($pack, $reachable, true))->toBeTrue("{$skill}: {$pack}.md is not in its envelope");
        }
    }

    // No pack is shipped that nothing reads.
    expect(array_values(array_diff($packs, array_unique($named))))->toBe([]);

    // The index for people lists every file a session may fall back on.
    $index = (string) file_get_contents("{$root}/larapilot/shared-runtime.md");

    foreach (array_keys(SharedRuntime::packagedDocs()) as $file) {
        if ($file === 'shared-runtime.md' || $file === 'integrations.md') {
            continue;
        }

        $listed = str_contains($index, "`.larapilot/{$file}`");
        $group = (string) preg_replace('/-\d+\.md$/', '.md', $file);
        $inGroup = $group !== $file && str_contains((string) file_get_contents("{$root}/larapilot/{$group}"), "`.larapilot/{$file}`");

        expect($listed || $inGroup)->toBeTrue("{$file} is in no index");
    }

    expect($index)->toContain('## Context protocol', '## Read protocol', 'php artisan larapilot:context {skill}');
});

it('keeps what a skill loads at activation within its budget', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    // Tokens of the runtime files read on a project with default settings,
    // from a session that holds nothing. Raise a ceiling only on purpose.
    $ceilings = [
        'triage' => 4100,
        'aikido' => 4100,
        'error' => 4100,
        'vendor-check' => 4100,
        'laravel-upgrade' => 8600,
        'php-upgrade' => 8900,
        'db-upgrade' => 8900,
        'settings' => 4100,
        'backstage' => 4900,
        'tracker' => 4900,
        'project-docs' => 5000,
        'frontend-companion' => 5100,
        'schedule' => 4100,
        'usage' => 5500,
        'release' => 6200,
        'custom-skill' => 6500,
        'autopilot' => 6700,
        'bug' => 6700,
        'review' => 7500,
        'prd' => 7700,
        'economics' => 9100,
        'design' => 8800,
        'ship' => 9200,
        'spec' => 10200,
        'feature' => 11300,
        'implement' => 12300,
        'plan' => 14100,
        'inception' => 13700,
        'adopt' => 16500,
    ];

    expect(array_keys($ceilings))->toEqualCanonicalizing(packagedSkills());

    foreach ($ceilings as $skill => $ceiling) {
        app()->forgetInstance(ContextService::class);

        $tokens = skillContext($skill)['runtime']['tokens'];

        expect($tokens['read'])->toBeLessThanOrEqual($ceiling, "{$skill} reads {$tokens['read']} tokens of runtime");
    }

    // Every skill of a chain after the first pays only for its own packs.
    $session = skillContext('triage')['session'];
    $chain = 0;

    foreach (['bug', 'plan', 'implement', 'review'] as $skill) {
        $chain += skillContext($skill, ['--session' => $session])['runtime']['tokens']['read'];
    }

    expect($chain)->toBeLessThan(14500)
        ->and(skillContext('review', ['--session' => $session])['runtime']['tokens']['read'])->toBe(0);
});

it('drops the runtime files the package no longer ships', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    file_put_contents(base_path('.larapilot/runtime-core-old.md'), 'a rule that no longer holds');
    file_put_contents(base_path('.larapilot/notes.md'), 'a file of the project');

    $data = skillContext('triage');
    file_put_contents($data['runtime']['dir'].'/stale.md', 'left by an older version');

    $this->artisan('larapilot:update', ['--skip-boost' => true])->assertSuccessful();

    expect(base_path('.larapilot/runtime-core-old.md'))->not->toBeFile()
        ->and(base_path('.larapilot/notes.md'))->toBeFile()
        ->and(base_path('.larapilot/runtime-spec-worker.md'))->toBeFile()
        ->and(base_path('.larapilot/runtime-discovery-8.md'))->toBeFile();

    app()->forgetInstance(ContextService::class);
    skillContext('triage');

    expect($data['runtime']['dir'].'/stale.md')->not->toBeFile();
});
