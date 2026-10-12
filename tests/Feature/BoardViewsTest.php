<?php

declare(strict_types=1);

use Larapilot\Services\ReleaseService;

it('keeps the board on status until a version or the sprints are on', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    $this->get('/larapilot')
        ->assertOk()
        ->assertSee('grouped by workflow status', false)
        ->assertDontSee('Group the board', false)
        ->assertSee('US-001', false);

    $this->getJson('/larapilot/api/specs')
        ->assertOk()
        ->assertJsonPath('items.0.code', 'US-001')
        ->assertJsonPath('items.0.sprint', null)
        ->assertJsonPath('items.0.sprint_proposed', false)
        ->assertJsonPath('items.0.release', null);
});

it('groups the board and the plan by version', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    addSpec(['code' => 'US-002', 'title' => 'Logout', 'status' => 'TODO']);

    $this->artisan('larapilot:settings-set', ['--release-mode' => 'YES'])->assertSuccessful();
    app(ReleaseService::class)->add('1.0.0', 'Initial release', 'planned', ['US-001']);

    $this->get('/larapilot')
        ->assertOk()
        ->assertSee('Group the board', false)
        ->assertSee('>Version</a>', false)
        ->assertDontSee('>Sprint</a>', false);

    $this->get('/larapilot?group=version')
        ->assertOk()
        ->assertSee('grouped by version', false)
        ->assertSee('v1.0.0', false)
        ->assertSee('No version yet', false)
        ->assertSee('US-001', false)
        ->assertSee('US-002', false);

    $this->get('/larapilot/plan?group=version')
        ->assertOk()
        ->assertSee('Grouped by version.', false)
        ->assertSee('Group the plan by', false)
        ->assertSee('v1.0.0', false)
        ->assertSee('No version yet', false);

    $this->getJson('/larapilot/api/specs')
        ->assertOk()
        ->assertJsonPath('items.0.release', '1.0.0')
        ->assertJsonPath('items.0.sprint', null)
        ->assertJsonPath('items.0.sprint_proposed', false);
});

it('proposes a sprint, keeps one that was chosen, and hides the switch when sprints are off', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    addSpec(['code' => 'US-002', 'title' => 'Logout', 'status' => 'TODO']);
    $this->artisan('larapilot:settings-set', ['--sprints' => 'YES'])->assertSuccessful();

    $this->get('/larapilot?group=sprint')
        ->assertOk()
        ->assertSee('>Sprint</a>', false)
        ->assertSee('grouped by two-week sprint', false)
        ->assertSee('Sprint 1', false)
        ->assertSee('Proposed', false);

    $this->get('/larapilot/plan?group=sprint')
        ->assertOk()
        ->assertSee('Grouped by two-week sprint.', false)
        ->assertSee('Sprint 1', false);

    $this->getJson('/larapilot/api/specs')
        ->assertOk()
        ->assertJsonPath('items.0.sprint', 1)
        ->assertJsonPath('items.0.sprint_proposed', true);
});

it('keeps a chosen sprint on the spec after the sprint switch is turned off', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    $this->artisan('larapilot:spec-sprint', ['code' => 'US-001', 'sprint' => '2'])->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--sprints' => 'YES'])->assertSuccessful();

    $this->get('/larapilot?group=sprint')
        ->assertOk()
        ->assertSee('Sprint 2', false);

    $this->artisan('larapilot:settings-set', ['--sprints' => 'NO'])->assertSuccessful();

    $this->get('/larapilot')
        ->assertOk()
        ->assertDontSee('>Sprint</a>', false);

    $this->getJson('/larapilot/api/specs')
        ->assertOk()
        ->assertJsonPath('items.0.sprint', 2)
        ->assertJsonPath('items.0.sprint_proposed', false);
});
