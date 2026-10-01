<?php

declare(strict_types=1);

use Larapilot\Support\ContextManifest;
use Larapilot\Support\SupportPolicy;

beforeEach(function (): void {
    $this->stackComposer = is_file(base_path('composer.json')) ? (string) file_get_contents(base_path('composer.json')) : null;
});

afterEach(function (): void {
    if ($this->stackComposer !== null) {
        file_put_contents(base_path('composer.json'), $this->stackComposer);
    }

    if (is_file(base_path('composer.lock'))) {
        unlink(base_path('composer.lock'));
    }
});

it('serves the About page with the stack and its support windows', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    file_put_contents(base_path('composer.lock'), json_encode([
        'packages' => [
            ['name' => 'laravel/framework', 'version' => 'v11.45.0', 'type' => 'library', 'require' => ['php' => '^8.2']],
            ['name' => 'filament/filament', 'version' => 'v3.3.0', 'type' => 'library', 'require' => []],
        ],
        'packages-dev' => [],
    ]));

    $html = $this->get('/larapilot/about')
        ->assertOk()
        ->assertSee('About')
        ->assertSee('Packages that shape an upgrade')
        ->assertSee('Filament')
        ->assertSee('11.45.0')
        ->assertSee('Files that pin a version')
        ->assertSee('php artisan larapilot:upgrade-check --laravel='.SupportPolicy::latest('laravel'))
        ->getContent();

    // Laravel 11 is past its end of life on the day of the table and after.
    expect($html)->toContain('Laravel 11 is past its end of life');
});

it('puts About, SBOM, and the plan download in the dashboard', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $html = $this->get('/larapilot')->assertOk()->getContent();

    expect($html)->toContain('>SBOM</a>', '>About</a>')
        ->and(strrpos($html, '>About</a>'))->toBeGreaterThan(strrpos($html, '>Docs</a>'))
        ->and(strpos($html, '>SBOM</a>'))->toBeGreaterThan(strpos($html, '>Security</a>'));

    $this->get('/larapilot/plan')->assertOk()->assertSee('Download plan (.md)');
});

it('exports the plan — epics, stories, tasks, milestones, forecast — as Markdown', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    addSpec(['code' => 'US-001', 'title' => 'Login', 'priority' => 'HIGH', 'points' => 3, 'epic' => ['code' => 'EP-001', 'title' => 'Auth', 'objective' => 'People can sign in'], 'body' => validSpecBody()."\n\n**Release:** 0.1.0"]);
    addSpec(['code' => 'US-002', 'title' => 'Profile', 'priority' => 'MEDIUM', 'points' => 2, 'epic' => ['code' => 'EP-001', 'title' => 'Auth'], 'body' => validSpecBody()."\n\n**Blocked by:** US-001"]);
    addSpec(['code' => 'US-003', 'title' => 'Footer', 'priority' => 'LOW', 'points' => 1]);
    planSpec('US-001');
    $this->artisan('larapilot:schedule-set', ['--label' => 'Beta', '--deadline' => '2030-01-15'])->assertSuccessful();

    $response = $this->get('/larapilot/plan/plan.md');
    $response->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

    $markdown = (string) $response->getContent();

    expect($response->headers->get('Content-Disposition'))->toContain('-plan-')
        ->and($markdown)->toContain(
            '# Plan',
            '| User stories | 3 (0 done) |',
            '| Story points | 6 (0 done) |',
            '## Milestones',
            '| Beta | 2030-01-15 |',
            '## Delivery order',
            '## EP-001 — Auth',
            'People can sign in',
            '| US-001 — Login | PLANNED | HIGH | 3 | 0.1.0 | — | 0 of 2 |',
            '| US-002 — Profile | TODO | MEDIUM | 2 | — | US-001 |',
            '### US-001 — Login',
            '| TASK-01 | Create model | TODO | implementation |',
            '| TASK-02 | Write tests | TODO | test |',
            '## Stories without an epic',
            'US-003 — Footer',
        );

    // US-002 waits for US-001: it comes after it in the delivery order.
    expect(strpos($markdown, '| 1 | US-001 — Login |'))->not->toBeFalse()
        ->and(strpos($markdown, '| 2 | US-002 — Profile |'))->toBeGreaterThan((int) strpos($markdown, '| 1 | US-001 — Login |'));
});

it('ships the upgrade and vendor skills with their context and their rules', function (): void {
    $root = dirname(__DIR__, 2).'/resources';

    foreach (['laravel-upgrade' => 'larapilot:upgrade-check --laravel=', 'php-upgrade' => 'larapilot:upgrade-check --php=', 'db-upgrade' => 'larapilot:upgrade-check --db=', 'vendor-check' => 'larapilot:vendor-audit'] as $skill => $command) {
        $body = (string) file_get_contents("{$root}/boost/skills/larapilot-{$skill}/SKILL.md");

        expect($body)->toStartWith("---\nname: larapilot-{$skill}\n")
            ->toContain("php artisan larapilot:context {$skill}", $command, 'Italian:')
            ->and(strlen($body))->toBeLessThanOrEqual(11000, "larapilot-{$skill} is over 11 KB")
            ->and(ContextManifest::skills())->toHaveKey($skill);
    }

    foreach (['laravel-upgrade', 'php-upgrade', 'db-upgrade'] as $skill) {
        $body = (string) file_get_contents("{$root}/boost/skills/larapilot-{$skill}/SKILL.md");

        // Nothing changes before the user chooses; the protocol is cited, not restated.
        expect($body)->toContain('No file changes before the user picks `now`', 'Upgrade Protocol', '`upgrade-1.md`');
    }

    expect((string) file_get_contents("{$root}/boost/skills/larapilot-db-upgrade/SKILL.md"))->toContain('Never connect to, migrate, copy from, or change a production or shared database')
        ->and((string) file_get_contents("{$root}/boost/skills/larapilot-vendor-check/SKILL.md"))->toContain('Never waive on your own')
        ->and((string) file_get_contents("{$root}/boost/guidelines/core.blade.php"))->toContain('`larapilot-laravel-upgrade`', '`larapilot-php-upgrade`', '`larapilot-db-upgrade`', '`larapilot-vendor-check`')
        ->and((string) file_get_contents("{$root}/boost/skills/larapilot-review/SKILL.md"))->toContain('php artisan larapilot:checkpoint-scan')
        ->and((string) file_get_contents("{$root}/boost/skills/larapilot-ship/SKILL.md"))->toContain('larapilot:vendor-audit --gate', 'larapilot:checkpoint-scan');
});
