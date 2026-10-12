<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Larapilot\Services\ReleaseService;

/**
 * A backlog of four stories over two versions, one story left with none.
 */
function versionedBacklog(): void
{
    test()->artisan('larapilot:install')->assertSuccessful();
    addSpec(['code' => 'US-001', 'title' => 'Login', 'status' => 'DONE', 'points' => 3]);
    addSpec(['code' => 'US-002', 'title' => 'Logout', 'status' => 'TODO', 'points' => 2]);
    addSpec(['code' => 'US-003', 'title' => 'Profile', 'status' => 'TODO', 'points' => 5]);
    addSpec(['code' => 'US-004', 'title' => 'Avatar', 'status' => 'TODO', 'points' => 1]);
    test()->artisan('larapilot:settings-set', ['--release-mode' => 'YES'])->assertSuccessful();

    $releases = app(ReleaseService::class);
    $releases->add('1.0.0', 'MVP', 'planned', ['US-001', 'US-002']);
    $releases->add('1.1.0', 'Profiles', 'planned', ['US-003']);
}

it('lists every version with what it has delivered', function (): void {
    versionedBacklog();

    $this->get('/larapilot/versions')
        ->assertOk()
        ->assertSee('v1.0.0', false)
        ->assertSee('v1.1.0', false)
        ->assertSee('MVP', false)
        ->assertSee('Version scope', false)
        ->assertSee('1 open story targets no version.', false)
        ->assertSee('US-004', false);

    $this->get('/larapilot/versions/1.0.0')
        ->assertOk()
        ->assertSee('v1.0.0 — MVP', false)
        ->assertSee('60%', false)
        ->assertSee('Logout', false)
        ->assertSee('Not delivered yet', false);

    $this->get('/larapilot/versions/9.9.9')->assertNotFound();

    $notes = $this->get('/larapilot/versions/1.0.0/notes')->assertOk();
    expect($notes->getContent())->toContain('# v1.0.0 — MVP', '- Login (US-001)', '## Not delivered yet');
});

it('keeps the version scope across the board, the plan, and the forecast', function (): void {
    versionedBacklog();

    $this->get('/larapilot?version=1.0.0')
        ->assertOk()
        ->assertSee('Open v1.0.0', false)
        ->assertSee('US-002', false)
        ->assertDontSee('US-003', false);

    // No version asked: the one in the session holds.
    $this->get('/larapilot/plan')
        ->assertOk()
        ->assertSee('Open v1.0.0', false);

    $this->get('/larapilot/forecast')
        ->assertOk()
        ->assertSee('By version', false)
        ->assertSee('Versions in scope', false);

    // Several versions, and the stories with none.
    $this->get('/larapilot?version=1.1.0,none')
        ->assertOk()
        ->assertSee('US-003', false)
        ->assertSee('US-004', false)
        ->assertDontSee('Logout', false);

    $this->get('/larapilot?version=all')
        ->assertOk()
        ->assertSee('US-001', false)
        ->assertSee('US-004', false);

    $board = $this->get('/larapilot/board.md?version=1.1.0')->assertOk()->getContent();
    expect($board)->toContain('version v1.1.0', 'US-003')->not->toContain('US-002');
});

/**
 * @return array<string, mixed>
 */
function versionsCommand(string $command, array $parameters = [], int $exit = 0): array
{
    expect(Artisan::call($command, $parameters))->toBe($exit);

    return json_decode(Artisan::output(), true);
}

it('shows a version, its open stories, and its release notes from the shell', function (): void {
    versionedBacklog();

    $list = versionsCommand('larapilot:release-list')['data'];

    expect($list['releases'][0]['progress']['progress'])->toBe(60)
        ->and($list['releases'][0]['progress']['specs'])->toBe(['total' => 2, 'done' => 1, 'wip' => 0, 'todo' => 1])
        ->and($list['current'])->toBe(['version' => '1.0.0', 'why' => 'oldest_planned'])
        ->and($list['unassigned'])->toBe(['count' => 1, 'points' => 1, 'specs' => ['US-004']]);

    $show = versionsCommand('larapilot:release-show', ['--semver' => '1.0.0'])['data'];

    expect($show['version'])->toBe('1.0.0')
        ->and(array_column($show['stories'], 'code'))->toBe(['US-002', 'US-001'])
        ->and(array_column($show['open'], 'code'))->toBe(['US-002'])
        ->and($show)->not->toHaveKey('notes');

    // No version named: the one the work is on.
    expect(versionsCommand('larapilot:release-show')['data']['version'])->toBe('1.0.0');

    versionsCommand('larapilot:release-show', ['--semver' => '7.0.0'], 4);

    $notes = versionsCommand('larapilot:release-notes', ['--semver' => '1.0.0'])['data'];

    expect($notes['markdown'])->toContain('## Added', '- Login (US-001)', '## Not delivered yet', '- Logout (US-002) — TODO')
        ->and($notes['written'])->toBeFalse()
        ->and($notes['path'])->not->toBeFile();

    $written = versionsCommand('larapilot:release-notes', ['--semver' => '1.0.0', '--write' => true])['data'];

    expect($written['written'])->toBeTrue()
        ->and(base_path('.larapilot/docs/releases/v1.0.0.md'))->toBeFile();

    // The team's words, once kept, are what every reader gets.
    file_put_contents(base_path('.larapilot/tmp-notes.md'), "# v1.0.0\n\nSign in, and out.\n");
    versionsCommand('larapilot:release-notes', ['--semver' => '1.0.0', '--file' => '.larapilot/tmp-notes.md']);
    unlink(base_path('.larapilot/tmp-notes.md'));

    expect(versionsCommand('larapilot:release-notes', ['--semver' => '1.0.0'])['data']['markdown'])->toContain('Sign in, and out.');
    expect($this->get('/larapilot/versions/1.0.0/notes')->assertOk()->getContent())->toContain('Sign in, and out.');
});

it('moves a story between versions and lists the backlog by version', function (): void {
    versionedBacklog();

    $moved = versionsCommand('larapilot:release-set', ['--semver' => '1.1.0', '--add-spec' => 'US-002'])['data']['release'];

    expect($moved['moved_from'])->toBe(['1.0.0'])
        ->and(app(ReleaseService::class)->find('1.0.0')['specs'])->toBe(['US-001']);

    versionsCommand('larapilot:release-set', ['--semver' => '1.1.0', '--remove-spec' => 'US-003']);

    $list = versionsCommand('larapilot:spec-list', ['--release' => 'none'])['data'];

    expect(array_column($list['items'], 'code'))->toBe(['US-003', 'US-004'])
        ->and($list['items'][0]['release'])->toBeNull()
        ->and($list['release'])->toBe(['versions' => ['none'], 'count' => 2])
        // The summary stays the backlog's: it numbers the next spec.
        ->and($list['summary']['last_code'])->toBe('US-004');

    expect(array_column(versionsCommand('larapilot:spec-list', ['--release' => '1.1.0'])['data']['items'], 'code'))->toBe(['US-002']);

    versionsCommand('larapilot:spec-list', ['--release' => '4.0.0'], 4);
});

it('picks the next story from the version the work is on', function (): void {
    versionedBacklog();

    // US-003 is HIGH like the others and in 1.1.0; US-002 is in 1.0.0, the version the work is on.
    addSpec(['code' => 'US-003', 'title' => 'Profile', 'status' => 'TODO', 'points' => 5, 'priority' => 'CRITICAL']);

    $next = versionsCommand('larapilot:spec-next')['data'];

    expect($next['spec']['code'])->toBe('US-002')
        ->and($next['release'])->toBe(['version' => '1.0.0', 'current' => '1.0.0']);

    expect(versionsCommand('larapilot:spec-next', ['--release' => '1.1.0'])['data']['spec']['code'])->toBe('US-003')
        ->and(versionsCommand('larapilot:spec-next', ['--release' => 'none'])['data']['spec']['code'])->toBe('US-004');

    // Without release mode the order is the backlog's: priority first.
    $this->artisan('larapilot:settings-set', ['--release-mode' => 'NO'])->assertSuccessful();

    expect(versionsCommand('larapilot:spec-next')['data']['spec']['code'])->toBe('US-003');
});

it('refuses to ship a version with open stories until they are settled', function (): void {
    versionedBacklog();

    $refused = versionsCommand('larapilot:release-ship', ['--semver' => '1.0.0'], 4);

    expect($refused['error']['code'])->toBe('E_PRECONDITION')
        ->and($refused['error']['message'])->toContain('US-002')
        ->and(array_column($refused['error']['details']['open'], 'code'))->toBe(['US-002']);

    // Only another open version, or the backlog, takes them.
    versionsCommand('larapilot:release-ship', ['--semver' => '1.0.0', '--defer-to' => '1.0.0'], 2);
    versionsCommand('larapilot:release-ship', ['--semver' => '1.0.0', '--defer-to' => '9.9.9'], 2);

    expect(app(ReleaseService::class)->find('1.0.0')['status'])->toBe('planned');
});

it('keeps the record of what a version moved out, and where to', function (): void {
    versionedBacklog();

    app(ReleaseService::class)->defer('1.0.0', ['US-002'], '1.1.0');

    expect(app(ReleaseService::class)->find('1.1.0')['specs'])->toBe(['US-003', 'US-002']);

    $notes = versionsCommand('larapilot:release-notes', ['--semver' => '1.0.0'])['data']['markdown'];

    expect($notes)->toContain('## Moved to a later version', '- US-002 → v1.1.0')
        ->not->toContain('Not delivered yet');

    // Back to the backlog: a `**Release:** 1.0.0` line in the body no longer claims it.
    addSpec(['code' => 'US-005', 'title' => 'Export', 'status' => 'TODO', 'body' => validSpecBody()."\n\n**Release:** 1.0.0\n"]);

    expect(versionsCommand('larapilot:spec-list', ['--release' => '1.0.0'])['data']['items'])->toHaveCount(2);

    app(ReleaseService::class)->defer('1.0.0', ['US-005'], null);

    expect(array_column(versionsCommand('larapilot:spec-list', ['--release' => '1.0.0'])['data']['items'], 'code'))->toBe(['US-001']);
});

it('serves the versions to the API and to every skill', function (): void {
    versionedBacklog();

    $this->getJson('/larapilot/api/versions')
        ->assertOk()
        ->assertJsonPath('enabled', true)
        ->assertJsonPath('versions.0.version', '1.0.0')
        ->assertJsonPath('versions.0.progress', 60)
        ->assertJsonPath('unassigned.specs', ['US-004']);

    $this->getJson('/larapilot/api/versions/v1.1.0')
        ->assertOk()
        ->assertJsonPath('stories.0.code', 'US-003');

    $this->getJson('/larapilot/api/versions/3.0.0')->assertNotFound();

    $this->getJson('/larapilot/api/specs?version=1.1.0,none')
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('version', ['1.1.0', 'none']);

    expect(versionsCommand('larapilot:context', ['skill' => 'implement'])['data']['release'])->toMatchArray([
        'current' => '1.0.0',
        'current_why' => 'oldest_planned',
        'last_shipped' => null,
        'unassigned' => 1,
    ]);

    $read = array_column(versionsCommand('larapilot:context', ['skill' => 'bug'])['data']['runtime']['read'], 'file');

    expect($read)->toContain('versions.md');

    $this->artisan('larapilot:settings-set', ['--release-mode' => 'NO'])->assertSuccessful();

    expect(versionsCommand('larapilot:context', ['skill' => 'implement'])['data'])->not->toHaveKey('release');
});

it('opens the versions to every role, and the branch only to the builders', function (): void {
    versionedBacklog();
    sharedHost();
    dashboardUser('ext', 'correct-horse-battery', 'ext');
    signInDashboard('ext');

    $this->get('/larapilot/versions')
        ->assertOk()
        ->assertSee('v1.0.0', false)
        ->assertDontSee('release/1.0.0', false)
        ->assertDontSee('>Forecast</a>', false);

    $this->get('/larapilot/versions/1.0.0')
        ->assertOk()
        ->assertDontSee('release/1.0.0', false);
});

it('says how to turn versions on when release mode is off', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    $this->get('/larapilot/versions')
        ->assertOk()
        ->assertSee('Release mode is off.', false);

    $this->get('/larapilot')
        ->assertOk()
        ->assertDontSee('Version scope', false);
});
