<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Larapilot\Services\SpecService;
use Larapilot\Services\Tracker\TrackerManager;
use Larapilot\Services\TrackerService;
use Symfony\Component\Yaml\Yaml;

/**
 * Point Larapilot at a provider with credentials present, so the driver gets
 * past its missingConfig() gate.
 *
 * @param  array<string, mixed>  $overrides
 */
function useTracker(string $provider, array $overrides = []): void
{
    config()->set('larapilot.tracker.enabled', true);
    config()->set('larapilot.tracker.provider', $provider);

    $credentials = [
        'linear' => ['api_key' => 'lin_api_test', 'team' => 'ENG'],
        'jira' => [
            'base_url' => 'https://acme.atlassian.net',
            'email' => 'dev@acme.test',
            'api_key' => 'jira_token',
            'project' => 'LP',
        ],
        'asana' => ['api_key' => 'asana_pat', 'project' => '1200'],
        'trello' => ['api_key' => 'trello_key', 'token' => 'trello_token', 'board' => 'board1'],
        'clickup' => ['api_key' => 'pk_test', 'list' => '900'],
        'monday' => ['api_key' => 'monday_token', 'board' => '77'],
    ][$provider];

    foreach (array_merge($credentials, $overrides) as $key => $value) {
        config()->set('larapilot.tracker.providers.'.$provider.'.'.$key, $value);
    }
}

/**
 * @return array<string, mixed>
 */
function trackerLinks(): array
{
    $path = base_path('.larapilot/tracker.yaml');

    return is_file($path) ? (array) Yaml::parseFile($path) : [];
}

/**
 * The decoded body of the nth recorded request.
 *
 * @return array<string, mixed>
 */
function sentBody(int $index): array
{
    /** @var array<int, array{0: Request}> $recorded */
    $recorded = Http::recorded();
    $decoded = json_decode($recorded[$index][0]->body(), true);

    return is_array($decoded) ? $decoded : [];
}

beforeEach(function (): void {
    $path = base_path('.larapilot/tracker.yaml');

    if (is_file($path)) {
        unlink($path);
    }

    // A driver bug or an incomplete fake must fail the test, never reach the
    // real Linear/Jira/Asana/Trello/ClickUp/Monday APIs.
    Http::preventStrayRequests();
});

/**
 * Config drives which driver is built, and both the manager and the service
 * are singletons that cache it — so switching providers mid-test needs both
 * dropped from the container.
 */
function forgetTracker(): void
{
    app()->forgetInstance(TrackerManager::class);
    app()->forgetInstance(TrackerService::class);
}

/* -------------------------------------------------------------------------
 | Configuration and status
 |------------------------------------------------------------------------- */

it('reports the tracker as disabled until a provider is configured', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    expect(Artisan::call('larapilot:tracker-status'))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['kind'])->toBe('tracker-status')
        ->and($envelope['data']['enabled'])->toBeFalse()
        ->and($envelope['data']['provider'])->toBeNull()
        ->and($envelope['data']['ready'])->toBeFalse()
        ->and($envelope['data']['available_providers'])
        ->toBe(['linear', 'asana', 'jira', 'trello', 'clickup', 'monday'])
        ->and($envelope['data']['specs']['total'])->toBe(1)
        ->and($envelope['data']['specs']['unlinked'])->toBe(['US-001']);
});

it('names the missing env vars when a provider is selected but not configured', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    config()->set('larapilot.tracker.enabled', true);
    config()->set('larapilot.tracker.provider', 'jira');

    expect(Artisan::call('larapilot:tracker-status'))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['ready'])->toBeFalse()
        ->and($envelope['data']['missing_config'])->toBe([
            'LARAPILOT_JIRA_BASE_URL',
            'LARAPILOT_JIRA_EMAIL',
            'LARAPILOT_JIRA_API_TOKEN',
            'LARAPILOT_JIRA_PROJECT',
        ]);
});

it('refuses to push when the integration is disabled', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    expect(Artisan::call('larapilot:tracker-push'))->toBe(3);

    expect(Artisan::output())->toContain('LARAPILOT_TRACKER_ENABLED');
});

it('exposes tracker configuration on config-show without leaking credentials', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    useTracker('linear');

    expect(Artisan::call('larapilot:config-show'))->toBe(0);

    $output = Artisan::output();
    $envelope = json_decode($output, true);

    expect($envelope['data']['tracker']['provider'])->toBe('linear')
        ->and($envelope['data']['tracker']['configured'])->toBeTrue()
        ->and($envelope['data']['tracker']['link_file'])->toBe('.larapilot/tracker.yaml')
        ->and($envelope['data']['tracker']['status_map']['IN PROGRESS'])->toBe('In Progress')
        ->and($output)->not->toContain('lin_api_test');
});

/* -------------------------------------------------------------------------
 | Push
 |------------------------------------------------------------------------- */

it('creates a linear issue with a sub-issue per plan task', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    planSpec();

    Http::fake([
        'api.linear.app/*' => Http::sequence()
            ->push(['data' => ['teams' => ['nodes' => [[
                'id' => 'team-1',
                'key' => 'ENG',
                'name' => 'Engineering',
                'states' => ['nodes' => [
                    ['id' => 'state-todo', 'name' => 'Todo', 'type' => 'unstarted', 'position' => 1],
                    ['id' => 'state-done', 'name' => 'Done', 'type' => 'completed', 'position' => 9],
                ]],
            ]]]]])
            ->push(['data' => ['issueCreate' => ['success' => true, 'issue' => [
                'id' => 'issue-1', 'identifier' => 'ENG-42', 'url' => 'https://linear.app/acme/issue/ENG-42',
            ]]]])
            ->push(['data' => ['issueCreate' => ['success' => true, 'issue' => [
                'id' => 'sub-1', 'identifier' => 'ENG-43', 'url' => 'https://linear.app/acme/issue/ENG-43',
            ]]]])
            ->push(['data' => ['issueCreate' => ['success' => true, 'issue' => [
                'id' => 'sub-2', 'identifier' => 'ENG-44', 'url' => 'https://linear.app/acme/issue/ENG-44',
            ]]]]),
    ]);

    useTracker('linear');

    expect(Artisan::call('larapilot:tracker-push'))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['kind'])->toBe('tracker-push')
        ->and($envelope['data']['provider'])->toBe('linear')
        ->and($envelope['data']['stories'][0]['action'])->toBe('created')
        ->and($envelope['data']['stories'][0]['ref'])->toBe('ENG-42')
        ->and($envelope['data']['summary']['created'])->toBe(1)
        ->and($envelope['data']['summary']['tasks_created'])->toBe(2);

    // The story carries the spec code, its body, and the mapped state.
    $story = sentBody(1)['variables']['input'];

    expect($story['teamId'])->toBe('team-1')
        ->and($story['title'])->toBe('US-001 — Login')
        ->and($story['description'])->toContain('I want to log in')
        ->and($story['description'])->toContain('Managed by Larapilot')
        ->and($story['stateId'])->toBe('state-todo')
        ->and($story['estimate'])->toBe(3)
        ->and($story['priority'])->toBe(2);

    // Plan tasks are native sub-issues, not a checklist in the body.
    $subtask = sentBody(2)['variables']['input'];

    expect($subtask['parentId'])->toBe('issue-1')
        ->and($subtask['title'])->toBe('TASK-01 — Create model')
        ->and($story['description'])->not->toContain('TASK-01');

    $links = trackerLinks();

    expect($links['provider'])->toBe('linear')
        ->and($links['providers']['linear']['US-001']['id'])->toBe('issue-1')
        ->and($links['providers']['linear']['US-001']['key'])->toBe('ENG-42')
        ->and($links['providers']['linear']['US-001']['tasks']['TASK-01']['id'])->toBe('sub-1')
        ->and($links['providers']['linear']['US-001']['tasks']['TASK-02']['id'])->toBe('sub-2');
});

it('skips unchanged stories on a second push and updates changed ones', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    Http::fake([
        'api.linear.app/*' => Http::sequence()
            ->push(['data' => ['teams' => ['nodes' => [[
                'id' => 'team-1', 'key' => 'ENG', 'name' => 'Engineering',
                'states' => ['nodes' => [['id' => 'state-todo', 'name' => 'Todo', 'type' => 'unstarted', 'position' => 1]]],
            ]]]]])
            ->push(['data' => ['issueCreate' => ['success' => true, 'issue' => [
                'id' => 'issue-1', 'identifier' => 'ENG-42', 'url' => 'https://linear.app/x',
            ]]]])
            ->whenEmpty(Http::response(['data' => ['issueUpdate' => ['success' => true, 'issue' => [
                'id' => 'issue-1', 'identifier' => 'ENG-42', 'url' => 'https://linear.app/x',
            ]]]])),
    ]);

    useTracker('linear');

    expect(Artisan::call('larapilot:tracker-push'))->toBe(0);

    $before = count(Http::recorded());

    // Nothing changed — the second push must not call the provider again.
    expect(Artisan::call('larapilot:tracker-push'))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['stories'][0]['action'])->toBe('unchanged')
        ->and(count(Http::recorded()))->toBe($before);

    // A title change makes it dirty again.
    $this->artisan('larapilot:spec-add', ['--file' => payloadFile(specsPayload(['title' => 'Sign in']))])
        ->assertSuccessful();

    expect(Artisan::call('larapilot:tracker-push'))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['stories'][0]['action'])->toBe('updated')
        ->and(count(Http::recorded()))->toBeGreaterThan($before);
});

it('deletes the subtask of a plan task that no longer exists', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    planSpec();

    Http::fake([
        'api.clickup.com/*' => function (Request $request) {
            if ($request->method() === 'DELETE') {
                return Http::response([]);
            }

            if (str_contains($request->url(), '/list/900/task')) {
                return Http::response([
                    'id' => 'task-'.substr(md5($request->body()), 0, 4),
                    'url' => 'https://app.clickup.com/t/x',
                ]);
            }

            if (str_contains($request->url(), '/list/900')) {
                return Http::response(['name' => 'Delivery', 'statuses' => [
                    ['status' => 'to do'], ['status' => 'in progress'], ['status' => 'complete'],
                ]]);
            }

            return Http::response(['id' => 'task-1', 'url' => 'https://app.clickup.com/t/x']);
        },
    ]);

    useTracker('clickup');

    expect(Artisan::call('larapilot:tracker-push'))->toBe(0);
    expect(trackerLinks()['providers']['clickup']['US-001']['tasks'])->toHaveCount(2);

    // Re-plan with a single task; the orphaned subtask must be removed.
    $this->artisan('larapilot:spec-plan', [
        'code' => 'US-001',
        '--file' => payloadFile([
            'plan_body' => 'Simplified.',
            'tasks' => [[
                'id' => 'TASK-01',
                'title' => 'Create model',
                'type' => 'implementation',
                'status' => 'TODO',
                'body' => "## Description\nCreate the model.",
            ]],
        ], 'tmp-plan-2.yaml'),
    ])->assertSuccessful();

    expect(Artisan::call('larapilot:tracker-push'))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['summary']['tasks_removed'])->toBe(1)
        ->and(trackerLinks()['providers']['clickup']['US-001']['tasks'])->toHaveCount(1);

    $deletes = collect(Http::recorded())->filter(fn (array $pair): bool => $pair[0]->method() === 'DELETE');

    expect($deletes)->toHaveCount(1);
});

it('creates a jira issue, a native subtask, and transitions the status', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    planSpec();
    $this->artisan('larapilot:spec-start', ['code' => 'US-001'])->assertSuccessful();

    // Jira issues are addressed by their numeric id, which is stable across
    // a project-key rename; the key is kept only for display.
    Http::fake([
        '*/rest/api/2/issue/*/transitions' => Http::response([
            'transitions' => [['id' => '31', 'name' => 'Start', 'to' => ['name' => 'In Progress']]],
        ]),
        '*/rest/api/2/issue/1000*' => Http::response(['fields' => ['status' => ['name' => 'To Do']]]),
        '*/rest/api/2/issue' => Http::sequence()
            ->push(['id' => '10001', 'key' => 'LP-1'])
            ->push(['id' => '10002', 'key' => 'LP-2'])
            ->push(['id' => '10003', 'key' => 'LP-3']),
        '*' => Http::response([]),
    ]);

    useTracker('jira');

    expect(Artisan::call('larapilot:tracker-push'))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['stories'][0]['ref'])->toBe('LP-1')
        ->and($envelope['data']['stories'][0]['url'])->toBe('https://acme.atlassian.net/browse/LP-1');

    $create = sentBody(0)['fields'];

    expect($create['project']['key'])->toBe('LP')
        ->and($create['summary'])->toBe('US-001 — Login')
        ->and($create['issuetype']['name'])->toBe('Task');

    // Subtasks carry the parent key and the configured subtask issue type.
    $subtask = collect(Http::recorded())
        ->map(fn (array $pair): array => json_decode($pair[0]->body(), true) ?: [])
        ->first(fn (array $body): bool => ($body['fields']['issuetype']['name'] ?? null) === 'Sub-task');

    expect($subtask['fields']['parent']['key'])->toBe('LP-1');

    // IN PROGRESS is reached through a workflow transition, not a field write.
    $transition = collect(Http::recorded())
        ->first(fn (array $pair): bool => str_contains($pair[0]->url(), '/transitions') && $pair[0]->method() === 'POST');

    expect(json_decode($transition[0]->body(), true)['transition']['id'])->toBe('31');
});

it('files an asana task into the mapped section and marks a done story complete', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['status' => 'DONE']);

    Http::fake([
        'app.asana.com/api/1.0/projects/1200/sections*' => Http::response(['data' => [
            ['gid' => 's1', 'name' => 'To Do'],
            ['gid' => 's5', 'name' => 'Done'],
        ]]),
        'app.asana.com/api/1.0/tasks' => Http::response(['data' => [
            'gid' => 't1', 'permalink_url' => 'https://app.asana.com/0/1200/t1',
        ]]),
        '*' => Http::response(['data' => []]),
    ]);

    useTracker('asana');

    expect(Artisan::call('larapilot:tracker-push'))->toBe(0);

    $create = collect(Http::recorded())
        ->first(fn (array $pair): bool => $pair[0]->url() === 'https://app.asana.com/api/1.0/tasks');

    expect(json_decode($create[0]->body(), true)['data']['completed'])->toBeTrue();

    $move = collect(Http::recorded())
        ->first(fn (array $pair): bool => str_contains($pair[0]->url(), '/sections/s5/addTask'));

    expect($move)->not->toBeNull()
        ->and(json_decode($move[0]->body(), true)['data']['task'])->toBe('t1');
});

it('explains which trello lists exist when the status map points at a missing one', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    Http::fake([
        'api.trello.com/1/boards/board1/lists*' => Http::response([
            ['id' => 'l1', 'name' => 'Backlog'],
            ['id' => 'l2', 'name' => 'Shipped'],
        ]),
        '*' => Http::response([]),
    ]);

    useTracker('trello');

    expect(Artisan::call('larapilot:tracker-push'))->toBe(3);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['kind'])->toBe('error')
        ->and($envelope['error']['details']['errors'][0]['message'])
        ->toContain('no list named "To Do"')
        ->and($envelope['error']['details']['errors'][0]['message'])
        ->toContain('Backlog, Shipped');
});

it('refuses to file a linear issue in a workflow state the team does not have', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    // A team using "Backlog" rather than the default "Todo" — Linear would
    // otherwise accept the issue and quietly file it in its default state.
    Http::fake([
        'api.linear.app/*' => Http::response(['data' => ['teams' => ['nodes' => [[
            'id' => 'team-1', 'key' => 'ENG', 'name' => 'Engineering',
            'states' => ['nodes' => [
                ['id' => 's1', 'name' => 'Backlog', 'type' => 'backlog', 'position' => 0],
                ['id' => 's2', 'name' => 'In Progress', 'type' => 'started', 'position' => 2],
            ]],
        ]]]]]),
    ]);

    useTracker('linear');

    expect(Artisan::call('larapilot:tracker-push'))->toBe(3);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['error']['message'])->toBe('1 of 1 stories failed to sync.')
        ->and($envelope['error']['details']['errors'][0]['message'])
        ->toContain('no workflow state named "Todo"')
        ->and($envelope['error']['details']['errors'][0]['message'])
        ->toContain('Backlog, In Progress');

    // Nothing was created, so nothing may be linked.
    expect(is_file(base_path('.larapilot/tracker.yaml')))->toBeTrue()
        ->and(trackerLinks()['providers']['linear'] ?? [])->toBe([]);
});

it('pushes a monday item with the status column and a native subitem', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    planSpec();

    Http::fake([
        'api.monday.com/*' => Http::sequence()
            ->push(['data' => ['create_item' => ['id' => '101', 'url' => 'https://acme.monday.com/boards/77/pulses/101']]])
            ->push(['data' => ['create_subitem' => ['id' => '201', 'url' => 'https://x', 'board' => ['id' => '78']]]])
            ->push(['data' => ['boards' => [['columns' => [['id' => 'status', 'title' => 'Status', 'type' => 'status']]]]]])
            ->push(['data' => ['change_multiple_column_values' => ['id' => '201']]])
            ->push(['data' => ['create_subitem' => ['id' => '202', 'url' => 'https://y', 'board' => ['id' => '78']]]])
            ->push(['data' => ['change_multiple_column_values' => ['id' => '202']]]),
    ]);

    useTracker('monday', ['description_column' => 'long_text']);

    expect(Artisan::call('larapilot:tracker-push'))->toBe(0);

    $create = sentBody(0)['variables'];
    $columns = json_decode($create['columnValues'], true);

    expect($create['boardId'])->toBe('77')
        ->and($create['name'])->toBe('US-001 — Login')
        ->and($columns['status'])->toBe(['label' => 'Not Started'])
        ->and($columns['long_text'])->toContain('I want to log in');

    expect(sentBody(1)['variables']['parentId'])->toBe('101')
        ->and(sentBody(1)['variables']['name'])->toBe('TASK-01 — Create model');
});

it('sends the provider auth headers each request expects', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    Http::fake(['*' => Http::response(['data' => ['teams' => ['nodes' => [[
        'id' => 't', 'key' => 'ENG', 'name' => 'Eng', 'states' => ['nodes' => []],
    ]]]]])]);

    useTracker('linear');

    expect(Artisan::call('larapilot:tracker-status', ['--ping' => true]))->toBe(0);

    // Linear personal keys go in Authorization with no Bearer prefix.
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'lin_api_test'));
});

it('pushes only the requested spec', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    addSpec(['code' => 'US-002', 'title' => 'Logout']);

    Http::fake(['*' => Http::response([
        'id' => 'task-1', 'url' => 'https://app.clickup.com/t/x',
        'name' => 'Delivery', 'statuses' => [['status' => 'to do']],
    ])]);

    useTracker('clickup');

    expect(Artisan::call('larapilot:tracker-push', ['--spec' => ['US-002']]))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['stories'])->toHaveCount(1)
        ->and($envelope['data']['stories'][0]['code'])->toBe('US-002')
        ->and(trackerLinks()['providers']['clickup'])->toHaveKey('US-002')
        ->and(trackerLinks()['providers']['clickup'])->not->toHaveKey('US-001');
});

it('reports a dry-run plan without calling the provider', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    planSpec();

    Http::fake();

    useTracker('linear');

    expect(Artisan::call('larapilot:tracker-push', ['--dry-run' => true]))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['dry_run'])->toBeTrue()
        ->and($envelope['data']['stories'][0]['action'])->toBe('created')
        ->and($envelope['data']['summary']['tasks_created'])->toBe(2)
        ->and(Http::recorded())->toBeEmpty()
        ->and(is_file(base_path('.larapilot/tracker.yaml')))->toBeFalse();
});

/* -------------------------------------------------------------------------
 | Pull
 |------------------------------------------------------------------------- */

it('reports drift read-only and applies it only with --apply', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    Http::fake([
        'api.linear.app/*' => Http::sequence()
            ->push(['data' => ['teams' => ['nodes' => [[
                'id' => 'team-1', 'key' => 'ENG', 'name' => 'Eng',
                'states' => ['nodes' => [['id' => 's1', 'name' => 'Todo', 'type' => 'unstarted', 'position' => 1]]],
            ]]]]])
            ->push(['data' => ['issueCreate' => ['success' => true, 'issue' => [
                'id' => 'issue-1', 'identifier' => 'ENG-42', 'url' => 'https://linear.app/x',
            ]]]])
            ->whenEmpty(Http::response(['data' => ['issues' => ['nodes' => [[
                'id' => 'issue-1', 'identifier' => 'ENG-42', 'url' => 'https://linear.app/x',
                'title' => 'US-001 — Login', 'updatedAt' => '2026-07-27T10:00:00Z',
                'state' => ['name' => 'In Progress'],
            ]]]]])),
    ]);

    useTracker('linear');

    expect(Artisan::call('larapilot:tracker-push'))->toBe(0);
    expect(Artisan::call('larapilot:tracker-pull'))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['kind'])->toBe('tracker-pull')
        ->and($envelope['data']['apply'])->toBeFalse()
        ->and($envelope['data']['stories'][0]['local_status'])->toBe('TODO')
        ->and($envelope['data']['stories'][0]['remote_status'])->toBe('In Progress')
        ->and($envelope['data']['stories'][0]['suggested_status'])->toBe('IN PROGRESS')
        ->and($envelope['data']['stories'][0]['drift'])->toBeTrue()
        ->and($envelope['data']['stories'][0]['applied'])->toBeFalse()
        ->and($envelope['data']['hint'])->toContain('--apply');

    // Read-only: the backlog is untouched.
    expect(app(SpecService::class)->find('US-001')['status'])->toBe('TODO');

    expect(Artisan::call('larapilot:tracker-pull', ['--apply' => true]))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['stories'][0]['applied'])->toBeTrue()
        ->and($envelope['data']['summary']['applied'])->toBe(1);

    app()->forgetInstance(SpecService::class);

    expect(app(SpecService::class)->find('US-001')['status'])->toBe('IN PROGRESS');
});

it('never applies DONE from the tracker', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    Http::fake([
        'api.linear.app/*' => Http::sequence()
            ->push(['data' => ['teams' => ['nodes' => [[
                'id' => 'team-1', 'key' => 'ENG', 'name' => 'Eng',
                'states' => ['nodes' => [['id' => 's1', 'name' => 'Todo', 'type' => 'unstarted', 'position' => 1]]],
            ]]]]])
            ->push(['data' => ['issueCreate' => ['success' => true, 'issue' => [
                'id' => 'issue-1', 'identifier' => 'ENG-42', 'url' => 'https://linear.app/x',
            ]]]])
            ->whenEmpty(Http::response(['data' => ['issues' => ['nodes' => [[
                'id' => 'issue-1', 'identifier' => 'ENG-42', 'url' => 'https://linear.app/x',
                'title' => 'US-001', 'updatedAt' => '2026-07-27T10:00:00Z',
                'state' => ['name' => 'Done'],
            ]]]]])),
    ]);

    useTracker('linear');

    expect(Artisan::call('larapilot:tracker-push'))->toBe(0);
    expect(Artisan::call('larapilot:tracker-pull', ['--apply' => true]))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['stories'][0]['suggested_status'])->toBe('DONE')
        ->and($envelope['data']['stories'][0]['applied'])->toBeFalse()
        ->and($envelope['data']['stories'][0]['blocked'])->toContain('larapilot:spec-approve');

    app()->forgetInstance(SpecService::class);

    expect(app(SpecService::class)->find('US-001')['status'])->toBe('TODO');
});

it('treats a status the map does not cover as unresolvable drift', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    Http::fake([
        'api.linear.app/*' => Http::sequence()
            ->push(['data' => ['teams' => ['nodes' => [[
                'id' => 'team-1', 'key' => 'ENG', 'name' => 'Eng',
                'states' => ['nodes' => [['id' => 's1', 'name' => 'Todo', 'type' => 'unstarted', 'position' => 1]]],
            ]]]]])
            ->push(['data' => ['issueCreate' => ['success' => true, 'issue' => [
                'id' => 'issue-1', 'identifier' => 'ENG-42', 'url' => 'https://linear.app/x',
            ]]]])
            ->whenEmpty(Http::response(['data' => ['issues' => ['nodes' => [[
                'id' => 'issue-1', 'identifier' => 'ENG-42', 'url' => 'https://x',
                'title' => 'US-001', 'updatedAt' => '2026-07-27T10:00:00Z',
                'state' => ['name' => 'Blocked by legal'],
            ]]]]])),
    ]);

    useTracker('linear');

    expect(Artisan::call('larapilot:tracker-push'))->toBe(0);
    expect(Artisan::call('larapilot:tracker-pull', ['--apply' => true]))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['stories'][0]['drift'])->toBeTrue()
        ->and($envelope['data']['stories'][0]['suggested_status'])->toBeNull()
        ->and($envelope['data']['stories'][0]['applied'])->toBeFalse()
        ->and($envelope['data']['stories'][0]['blocked'])->toContain('not in the status map');
});

it('flags a link whose remote record was deleted', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    Http::fake([
        'api.linear.app/*' => Http::sequence()
            ->push(['data' => ['teams' => ['nodes' => [[
                'id' => 'team-1', 'key' => 'ENG', 'name' => 'Eng',
                'states' => ['nodes' => [['id' => 's1', 'name' => 'Todo', 'type' => 'unstarted', 'position' => 1]]],
            ]]]]])
            ->push(['data' => ['issueCreate' => ['success' => true, 'issue' => [
                'id' => 'issue-1', 'identifier' => 'ENG-42', 'url' => 'https://linear.app/x',
            ]]]])
            ->whenEmpty(Http::response(['data' => ['issues' => ['nodes' => []]]])),
    ]);

    useTracker('linear');

    expect(Artisan::call('larapilot:tracker-push'))->toBe(0);
    expect(Artisan::call('larapilot:tracker-pull'))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['stories'][0]['missing'])->toBeTrue()
        ->and($envelope['data']['stories'][0]['blocked'])->toContain('no longer exists');
});

it('imports remote comments once as internal feedback', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    Http::fake([
        'api.linear.app/*' => Http::sequence()
            ->push(['data' => ['teams' => ['nodes' => [[
                'id' => 'team-1', 'key' => 'ENG', 'name' => 'Eng',
                'states' => ['nodes' => [['id' => 's1', 'name' => 'Todo', 'type' => 'unstarted', 'position' => 1]]],
            ]]]]])
            ->push(['data' => ['issueCreate' => ['success' => true, 'issue' => [
                'id' => 'issue-1', 'identifier' => 'ENG-42', 'url' => 'https://linear.app/x',
            ]]]])
            ->whenEmpty(Http::response(['data' => ['issues' => ['nodes' => [[
                'id' => 'issue-1', 'identifier' => 'ENG-42', 'url' => 'https://x',
                'title' => 'US-001', 'updatedAt' => '2026-07-27T10:00:00Z',
                'state' => ['name' => 'Todo'],
                'comments' => ['nodes' => [[
                    'body' => 'Please confirm the SSO scope.',
                    'createdAt' => '2026-07-27T09:00:00Z',
                    'user' => ['name' => 'Dana'],
                ]]],
            ]]]]])),
    ]);

    useTracker('linear');
    config()->set('larapilot.tracker.pull.comments', true);

    expect(Artisan::call('larapilot:tracker-push'))->toBe(0);
    expect(Artisan::call('larapilot:tracker-pull', ['--apply' => true]))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);
    $feedback = (string) file_get_contents(base_path('.larapilot/internal-feedback/US-001.md'));

    expect($envelope['data']['summary']['imported_comments'])->toBe(1)
        ->and($feedback)->toContain('Please confirm the SSO scope.')
        ->and($feedback)->toContain('Linear · Dana')
        // Imported comments are context, never a merge gate.
        ->and($feedback)->not->toContain('blocks-merge');

    // A second pull must not duplicate the same comment.
    expect(Artisan::call('larapilot:tracker-pull', ['--apply' => true]))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['summary']['imported_comments'])->toBe(0)
        ->and(substr_count((string) file_get_contents(base_path('.larapilot/internal-feedback/US-001.md')), 'SSO scope'))
        ->toBe(1);
});

/* -------------------------------------------------------------------------
 | Status mapping
 |------------------------------------------------------------------------- */

it('maps statuses forward and resolves an ambiguous label to the earliest slot', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    useTracker('linear');

    $manager = app(TrackerManager::class);

    expect($manager->remoteFor('TODO'))->toBe('Todo')
        ->and($manager->remoteFor('PLANNED'))->toBe('Todo')
        ->and($manager->remoteFor('IN PROGRESS'))->toBe('In Progress')
        // Both TODO and PLANNED map to "Todo"; the earliest slot wins.
        ->and($manager->localFor('Todo'))->toBe('TODO')
        ->and($manager->localFor('nonexistent'))->toBeNull()
        // A PLANNED spec sitting in "Todo" is in sync, not drifted.
        ->and($manager->inSync('PLANNED', 'Todo'))->toBeTrue()
        ->and($manager->inSync('TODO', 'In Progress'))->toBeFalse();
});

it('does not report drift for a planned spec resting in the shared todo column', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    planSpec();

    Http::fake([
        'api.linear.app/*' => Http::sequence()
            ->push(['data' => ['teams' => ['nodes' => [[
                'id' => 'team-1', 'key' => 'ENG', 'name' => 'Eng',
                'states' => ['nodes' => [['id' => 's1', 'name' => 'Todo', 'type' => 'unstarted', 'position' => 1]]],
            ]]]]])
            ->whenEmpty(Http::response(['data' => [
                'issueCreate' => ['success' => true, 'issue' => ['id' => 'i1', 'identifier' => 'ENG-1', 'url' => 'https://x']],
                'issues' => ['nodes' => [[
                    'id' => 'i1', 'identifier' => 'ENG-1', 'url' => 'https://x',
                    'title' => 'US-001', 'updatedAt' => '2026-07-27T10:00:00Z',
                    'state' => ['name' => 'Todo'],
                ]]],
            ]])),
    ]);

    useTracker('linear');

    expect(Artisan::call('larapilot:tracker-push'))->toBe(0);
    expect(Artisan::call('larapilot:tracker-pull'))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['stories'][0]['local_status'])->toBe('PLANNED')
        ->and($envelope['data']['stories'][0]['remote_status'])->toBe('Todo')
        ->and($envelope['data']['stories'][0]['drift'])->toBeFalse()
        ->and($envelope['data']['summary']['drifted'])->toBe(0);
});

/* -------------------------------------------------------------------------
 | Failure handling
 |------------------------------------------------------------------------- */

it('surfaces a provider error per spec without aborting the run', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    addSpec(['code' => 'US-002', 'title' => 'Logout']);

    Http::fake([
        'api.clickup.com/api/v2/list/900/task' => Http::sequence()
            ->push(['err' => 'Rate limit reached'], 429)
            ->push(['id' => 'task-2', 'url' => 'https://app.clickup.com/t/2']),
        '*' => Http::response(['name' => 'Delivery', 'statuses' => [['status' => 'to do']]]),
    ]);

    useTracker('clickup');

    expect(Artisan::call('larapilot:tracker-push'))->toBe(3);

    $envelope = json_decode(Artisan::output(), true);
    $details = $envelope['error']['details'];

    expect($envelope['kind'])->toBe('error')
        ->and($details['errors'])->toHaveCount(1)
        ->and($details['errors'][0]['code'])->toBe('US-001')
        ->and($details['errors'][0]['message'])->toContain('HTTP 429')
        // The second spec still synced.
        ->and($details['stories'])->toHaveCount(1)
        ->and($details['stories'][0]['code'])->toBe('US-002')
        ->and(trackerLinks()['providers']['clickup'])->toHaveKey('US-002');
});

it('recreates a story whose remote record was deleted behind our back', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    Http::fake([
        'api.clickup.com/api/v2/list/900' => Http::response(['name' => 'Delivery', 'statuses' => [['status' => 'to do']]]),
        'api.clickup.com/api/v2/list/900/task' => Http::sequence()
            ->push(['id' => 'task-1', 'url' => 'https://app.clickup.com/t/1'])
            ->push(['id' => 'task-9', 'url' => 'https://app.clickup.com/t/9']),
        'api.clickup.com/api/v2/task/task-1' => Http::response(['err' => 'not found'], 404),
    ]);

    useTracker('clickup');

    expect(Artisan::call('larapilot:tracker-push'))->toBe(0);
    expect(trackerLinks()['providers']['clickup']['US-001']['id'])->toBe('task-1');

    // Someone deletes the card in ClickUp; the next push notices and recreates.
    $this->artisan('larapilot:spec-add', ['--file' => payloadFile(specsPayload(['title' => 'Sign in']))])
        ->assertSuccessful();

    expect(Artisan::call('larapilot:tracker-push'))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['stories'][0]['action'])->toBe('updated')
        ->and(trackerLinks()['providers']['clickup']['US-001']['id'])->toBe('task-9');
});

it('keeps links for a provider when another provider is used', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    Http::fake([
        'api.clickup.com/*' => Http::response([
            'id' => 'task-1', 'url' => 'https://app.clickup.com/t/x',
            'name' => 'Delivery', 'statuses' => [['status' => 'to do']],
        ]),
        'app.asana.com/api/1.0/projects/1200/sections*' => Http::response([
            'data' => [['gid' => 's1', 'name' => 'To Do']],
        ]),
        'app.asana.com/*' => Http::response([
            'data' => ['gid' => 't1', 'permalink_url' => 'https://app.asana.com/0/1200/t1'],
        ]),
    ]);

    useTracker('clickup');
    expect(Artisan::call('larapilot:tracker-push'))->toBe(0);

    useTracker('asana');
    forgetTracker();

    expect(Artisan::call('larapilot:tracker-push'))->toBe(0);

    $links = trackerLinks();

    expect($links['provider'])->toBe('asana')
        ->and($links['providers']['clickup']['US-001']['id'])->toBe('task-1')
        ->and($links['providers']['asana']['US-001']['id'])->toBe('t1');
});
