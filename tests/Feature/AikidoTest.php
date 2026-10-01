<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Larapilot\Mcp\Tools\RunArtisanTool;
use Larapilot\Services\Aikido\AikidoClient;
use Larapilot\Services\AikidoRegisterWriter;
use Larapilot\Services\AikidoService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\GitService;
use Larapilot\Services\PrdService;
use Symfony\Component\Yaml\Yaml;

/**
 * @return list<array<string, mixed>>
 */
function aikidoIssues(): array
{
    return [
        [
            'id' => 31,
            'type' => 'sast',
            'title' => 'SQL built from request input',
            'description' => 'A query is assembled from a request parameter without binding.',
            'time_to_fix_minutes' => 45,
            'group_status' => 'new',
            'severity_score' => 72,
            'severity' => 'high',
            'locations' => [['id' => 12, 'name' => 'fjord-invoices', 'type' => 'code_repository']],
            'how_to_fix' => 'Use query bindings or the query builder.',
            'related_cve_ids' => [],
            'first_detected_at' => 1758000000,
        ],
        [
            'id' => 24,
            'type' => 'open_source',
            'title' => 'guzzlehttp/psr7',
            'description' => 'Improper header parsing allows request smuggling.',
            'time_to_fix_minutes' => 30,
            'group_status' => 'new',
            'severity_score' => 95,
            'severity' => 'critical',
            'locations' => [['id' => 12, 'name' => 'fjord-invoices', 'type' => 'code_repository']],
            'how_to_fix' => 'Upgrade guzzlehttp/psr7 to 2.7.0 or later.',
            'related_cve_ids' => ['CVE-2026-1111', 'CVE-2026-1111'],
            'first_detected_at' => 1757000000,
        ],
        [
            'id' => 40,
            'type' => 'license',
            'title' => 'Package under AGPL-3.0',
            'description' => null,
            'time_to_fix_minutes' => 10,
            'group_status' => 'todo',
            'severity_score' => 20,
            'severity' => 'low',
            'locations' => [],
            'how_to_fix' => 'Replace the package or confirm the license is acceptable.',
            'related_cve_ids' => [],
            'first_detected_at' => 1756000000,
        ],
    ];
}

/**
 * A second `Http::fake()` adds to the first instead of replacing it: what
 * Aikido answers is set from scratch every time.
 */
function resetHttp(): void
{
    Http::swap(new Factory);
}

/**
 * The single issues of the findings, as the export of Aikido lists them:
 * finding 24 is two CVEs of one package, finding 40 is in this repository
 * and in another one of the workspace.
 *
 * @return list<array<string, mixed>>
 */
function aikidoSingles(): array
{
    $issue = static fn (int $id, int $group, int $repository, string $status = 'open', array $more = []): array => array_merge([
        'id' => $id,
        'group_id' => $group,
        'status' => $status,
        'code_repo_id' => $repository,
        'closed_at' => null,
        'ignored_at' => null,
        'ignored_by' => null,
        'snooze_until' => null,
    ], $more);

    return [
        $issue(2401, 24, 12),
        $issue(2402, 24, 12),
        $issue(3101, 31, 12),
        $issue(4001, 40, 12),
        $issue(4002, 40, 11),
        $issue(5201, 52, 12, 'closed', ['closed_at' => 1758300000]),
        $issue(6101, 61, 12, 'ignored', ['ignored_at' => 1758400000, 'ignored_by' => 'user']),
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function aikidoRepositories(): array
{
    return [
        ['id' => 11, 'name' => 'fjord-website', 'provider' => 'github', 'url' => 'https://github.com/example/fjord-website', 'branch' => 'main', 'last_scanned_at' => 1758900000, 'connectivity' => 'connected'],
        ['id' => 12, 'name' => 'fjord-invoices', 'provider' => 'github', 'url' => 'https://github.com/example/fjord-invoices', 'branch' => 'main', 'last_scanned_at' => 1758900000, 'connectivity' => 'connected'],
    ];
}

/**
 * Aikido as the documentation of its API describes it. Nothing a test does
 * leaves the machine: a call no line below answers fails the test.
 *
 * With `$readOnly`, the credentials have no `issues:write` scope: Aikido
 * refuses every decision it is told.
 *
 * @param  list<array<string, mixed>>|null  $issues
 */
function fakeAikido(?array $issues = null, bool $readOnly = false): void
{
    resetHttp();
    Http::preventStrayRequests();

    $query = static function (Request $request): array {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query;
    };

    $group = static fn (int $id, string $title, string $severity, string $type): array => [
        'id' => $id, 'type' => $type, 'title' => $title, 'description' => null, 'time_to_fix_minutes' => 20, 'group_status' => 'todo',
        'severity_score' => $severity === 'high' ? 70 : 40, 'severity' => $severity, 'locations' => [], 'how_to_fix' => '', 'related_cve_ids' => [], 'first_detected_at' => 1755000000,
    ];

    Http::fake([
        'app.aikido.dev/api/oauth/token' => Http::response(['access_token' => 'token-1', 'token_type' => 'bearer', 'expires_in' => 3600]),
        'app.aikido.dev/api/public/v1/repositories/code/12/scan*' => Http::response(null, 204),
        'app.aikido.dev/api/public/v1/repositories/code/11' => Http::response(aikidoRepositories()[0]),
        'app.aikido.dev/api/public/v1/repositories/code/12' => Http::response(aikidoRepositories()[1]),
        'app.aikido.dev/api/public/v1/repositories/code/*' => Http::response(['reason_phrase' => 'Not found'], 404),
        'app.aikido.dev/api/public/v1/repositories/code*' => Http::response(aikidoRepositories()),
        'app.aikido.dev/api/public/v1/open-issue-groups*' => static fn (Request $request) => Http::response(match ($query($request)['filter_status'] ?? 'open') {
            'open' => $issues ?? aikidoIssues(),
            'closed' => [$group(52, 'symfony/http-kernel', 'high', 'open_source')],
            'ignored' => [$group(61, 'Debug flag in a test fixture', 'medium', 'sast')],
            default => [],
        }),
        'app.aikido.dev/api/public/v1/issues/export*' => static function (Request $request) use ($query) {
            $asked = $query($request);

            return Http::response(array_values(array_filter(aikidoSingles(), static fn (array $issue): bool => (! isset($asked['filter_issue_group_id']) || (int) $asked['filter_issue_group_id'] === $issue['group_id'])
                && (! isset($asked['filter_code_repo_id']) || (int) $asked['filter_code_repo_id'] === $issue['code_repo_id'])
                && (($asked['filter_status'] ?? 'all') === 'all' || $asked['filter_status'] === $issue['status']))));
        },
        ...($readOnly ? ['app.aikido.dev/api/public/v1/issues/*' => Http::response(['reason_phrase' => 'The client lacks the issues:write scope.'], 403)] : []),
        'app.aikido.dev/api/public/v1/issues/groups/*/ignore' => Http::response(['success' => 1, 'ignored_single_issues_amount' => 2]),
        'app.aikido.dev/api/public/v1/issues/groups/*/unignore' => Http::response(['status' => 'ok']),
        'app.aikido.dev/api/public/v1/issues/groups/*/notes' => Http::response(['note_id' => 900]),
        'app.aikido.dev/api/public/v1/issues/*/ignore' => Http::response(['status' => 'ok']),
        'app.aikido.dev/api/public/v1/issues/*/unignore' => Http::response(['status' => 'ok']),
    ]);
}

function enableAikido(): void
{
    test()->artisan('larapilot:settings-set', ['--aikido' => 'YES'])->assertSuccessful();

    config()->set('larapilot.aikido.client_id', 'client-id');
    config()->set('larapilot.aikido.client_secret', 'client-secret');
    config()->set('larapilot.aikido.repository', 'fjord-invoices');
    config()->set('larapilot.aikido.region', 'eu');
    config()->set('larapilot.aikido.fail_on', 'high');
}

/**
 * @return array<string, mixed>
 */
function envelope(): array
{
    $output = trim(Artisan::output());
    $decoded = json_decode($output, true);

    return is_array($decoded) ? $decoded : [];
}

it('keeps Aikido off until the project turns it on', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    Http::fake();

    $config = app(ConfigService::class);

    expect($config->aikidoEnabled())->toBeFalse()
        ->and($config->settings()['aikido'])->toBe('NO')
        ->and($config->allowedAikidoModes())->toBe(['YES', 'NO']);

    $status = app(AikidoService::class)->status();

    expect($status['enabled'])->toBeFalse()
        ->and($status['configured'])->toBeFalse()
        ->and($status['ready'])->toBeFalse()
        ->and($status['hints'][0])->toContain('larapilot:settings-set --aikido=YES')
        ->and($status['hints'][1])->toContain('LARAPILOT_AIKIDO_CLIENT_ID');

    foreach (['larapilot:aikido-issues', 'larapilot:aikido-plan', 'larapilot:aikido-scan'] as $command) {
        $this->artisan($command)->assertExitCode(4)->expectsOutputToContain('Aikido is off for this project');
    }

    $this->artisan('larapilot:aikido-link', ['issues' => '24', '--spec' => 'US-001'])->assertExitCode(4);

    // Nothing was asked of Aikido.
    Http::assertNothingSent();

    $this->artisan('larapilot:settings-set', ['--aikido' => 'YES'])->assertSuccessful();

    expect(Yaml::parseFile(base_path('.larapilot/config.yaml'))['settings']['aikido'])->toBeTrue()
        ->and(app(ConfigService::class)->aikidoEnabled())->toBeTrue();
});

it('signs in with the client credentials and keeps the token', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    fakeAikido();

    $status = app(AikidoService::class)->status();

    expect($status['ready'])->toBeTrue()
        ->and($status['authenticated'])->toBeTrue()
        ->and($status['region'])->toBe('eu')
        ->and($status['host'])->toBe('https://app.aikido.dev')
        ->and($status['repository']['id'])->toBe(12)
        ->and($status['repository']['name'])->toBe('fjord-invoices')
        ->and($status['repository']['branch'])->toBe('main')
        ->and($status['repository']['last_scanned_at'])->not->toBeNull()
        ->and($status['hints'])->toBe([]);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://app.aikido.dev/api/oauth/token'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('client-id:client-secret'))
            && $request['grant_type'] === 'client_credentials';
    });

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/api/public/v1/repositories/code')
        && $request->hasHeader('Authorization', 'Bearer token-1'));

    // A second read asks for no second token.
    app(AikidoService::class)->status();

    expect(Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/oauth/token')))->toHaveCount(1);
});

it('reads the region and an address of its own', function (): void {
    $client = app(AikidoClient::class);

    foreach (['eu' => 'https://app.aikido.dev', 'US' => 'https://app.us.aikido.dev', 'au' => 'https://app.au.aikido.dev', 'me' => 'https://app.me.aikido.dev', 'mars' => 'https://app.aikido.dev'] as $region => $host) {
        config()->set('larapilot.aikido.region', $region);

        expect($client->host())->toBe($host);
    }

    config()->set('larapilot.aikido.base_url', 'https://aikido.internal.example/');

    expect($client->host())->toBe('https://aikido.internal.example');
});

it('downloads the open findings of the repository, the most severe first', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    fakeAikido();

    $findings = app(AikidoService::class)->findings();

    expect(array_column($findings['issues'], 'id'))->toBe([24, 31, 40])
        ->and($findings['counts'])->toBe(['critical' => 1, 'high' => 1, 'medium' => 0, 'low' => 1, 'all' => 3])
        ->and($findings['states'])->toBe(['new' => 3, 'in_backlog' => 0, 'waived' => 0])
        ->and($findings['total'])->toBe(3)
        ->and($findings['truncated'])->toBeFalse()
        ->and($findings['repository']['id'])->toBe(12);

    $first = $findings['issues'][0];

    expect($first['title'])->toBe('guzzlehttp/psr7')
        ->and($first['type_label'])->toBe('Vulnerable dependency')
        ->and($first['severity'])->toBe('critical')
        ->and($first['score'])->toBe(95)
        ->and($first['cves'])->toBe(['CVE-2026-1111'])
        ->and($first['where'])->toBe(['fjord-invoices'])
        ->and($first['how_to_fix'])->toBe('Upgrade guzzlehttp/psr7 to 2.7.0 or later.')
        ->and($first['state'])->toBe('new')
        ->and($first['spec'])->toBeNull()
        ->and($findings['issues'][2]['description'])->toBe('')
        ->and($findings['issues'][2]['type_label'])->toBe('License risk');

    // Only this repository is asked for.
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/open-issue-groups')
        && str_contains($request->url(), 'filter_code_repo_id=12'));

    expect($findings['gate']['verdict'])->toBe('FAIL')
        ->and($findings['gate']['blocking'])->toBe([24, 31])
        ->and($findings['gate']['undecided'])->toBe(2)
        ->and($findings['gate']['summary'])->toBe('2 open findings are high or above: 2 with no decision yet.');
});

it('narrows the list without changing the verdict', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    fakeAikido();

    $aikido = app(AikidoService::class);

    expect(array_column($aikido->findings(['severity' => 'critical'])['issues'], 'id'))->toBe([24])
        ->and(array_column($aikido->findings(['severity' => 'high'])['issues'], 'id'))->toBe([24, 31])
        ->and(array_column($aikido->findings(['type' => 'license'])['issues'], 'id'))->toBe([40])
        ->and(array_column($aikido->findings(['limit' => 1])['issues'], 'id'))->toBe([24])
        ->and($aikido->findings(['type' => 'license'])['gate']['verdict'])->toBe('FAIL')
        ->and($aikido->findings(['type' => 'license'])['total'])->toBe(3);

    expect(fn () => $aikido->findings(['severity' => 'urgent']))->toThrow(InvalidArgumentException::class, 'Unknown severity');
});

it('records the spec that fixes a finding, and the reason one is waived', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    fakeAikido();
    addSpec(['code' => 'US-001', 'title' => 'Upgrade guzzlehttp/psr7']);

    $this->artisan('larapilot:aikido-issues')->assertSuccessful();

    $this->artisan('larapilot:aikido-link', ['issues' => '24', '--spec' => 'US-001'])->assertSuccessful();
    $this->artisan('larapilot:aikido-link', [
        'issues' => '40',
        '--waive' => true,
        '--reason' => 'Internal tool, never distributed: AGPL does not apply.',
    ])->assertSuccessful();

    $ledger = Yaml::parseFile(base_path('.larapilot/aikido.yaml'));

    expect($ledger['issues'][24]['state'])->toBe('in_backlog')
        ->and($ledger['issues'][24]['spec'])->toBe('US-001')
        ->and($ledger['issues'][24]['title'])->toBe('guzzlehttp/psr7')
        ->and($ledger['issues'][24]['severity'])->toBe('critical')
        ->and($ledger['issues'][40]['state'])->toBe('waived')
        ->and($ledger['issues'][40]['reason'])->toBe('Internal tool, never distributed: AGPL does not apply.')
        ->and($ledger['repository'])->toBe(['id' => 12, 'name' => 'fjord-invoices'])
        // identifiers and decisions, never a credential
        ->and(file_get_contents(base_path('.larapilot/aikido.yaml')))->not->toContain('client-secret')
        ->not->toContain('token-1');

    $findings = app(AikidoService::class)->findings();
    $byId = collect($findings['issues'])->keyBy('id');

    expect($byId[24]['state'])->toBe('in_backlog')
        ->and($byId[24]['spec'])->toBe('US-001')
        ->and($byId[24]['spec_status'])->toBe('TODO')
        ->and($byId[40]['state'])->toBe('waived')
        ->and($byId[31]['state'])->toBe('new')
        ->and($findings['states'])->toBe(['new' => 1, 'in_backlog' => 1, 'waived' => 1])
        ->and(array_column(app(AikidoService::class)->findings(['new' => true])['issues'], 'id'))->toBe([31])
        // in the backlog is not fixed: the finding still stops the gate
        ->and($findings['gate']['verdict'])->toBe('FAIL')
        ->and($findings['gate']['blocking'])->toBe([24, 31])
        ->and($findings['gate']['undecided'])->toBe(1);

    $this->artisan('larapilot:aikido-link', ['issues' => '24,40', '--forget' => true])->assertSuccessful();

    expect(app(AikidoService::class)->findings()['states']['new'])->toBe(3);
});

it('refuses a decision that says nothing', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    fakeAikido();
    addSpec();

    $this->artisan('larapilot:aikido-link', ['issues' => '24'])->assertExitCode(2);
    $this->artisan('larapilot:aikido-link', ['issues' => '24', '--spec' => 'US-001', '--waive' => true])->assertExitCode(2);
    $this->artisan('larapilot:aikido-link', ['issues' => 'abc', '--spec' => 'US-001'])->assertExitCode(2);
    $this->artisan('larapilot:aikido-link', ['issues' => '24', '--spec' => '../etc'])->assertExitCode(2);
    $this->artisan('larapilot:aikido-link', ['issues' => '24', '--spec' => 'US-999'])->assertExitCode(4);
    $this->artisan('larapilot:aikido-link', ['issues' => '24', '--waive' => true])->assertExitCode(2);
    $this->artisan('larapilot:aikido-link', ['issues' => '24', '--waive' => true, '--reason' => 'later'])->assertExitCode(2);

    // A number Aikido does not report would later read as a finding that was fixed.
    $this->artisan('larapilot:aikido-link', ['issues' => '24,999', '--spec' => 'US-001'])
        ->assertExitCode(4)
        ->expectsOutputToContain('Aikido reports no open finding #999');

    expect(is_file(base_path('.larapilot/aikido.yaml')))->toBeFalse();
});

it('keeps the title of a finding beside the decision, whatever process records it', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    fakeAikido();
    addSpec();

    // Nothing was read before: the decision reads Aikido itself.
    Cache::flush();
    $this->artisan('larapilot:aikido-link', ['issues' => '31', '--spec' => 'US-001'])->assertSuccessful();

    $ledger = Yaml::parseFile(base_path('.larapilot/aikido.yaml'));

    expect($ledger['issues'][31]['title'])->toBe('SQL built from request input')
        ->and($ledger['issues'][31]['severity'])->toBe('high')
        ->and($ledger['issues'][31]['type'])->toBe('sast')
        ->and($ledger['repository'])->toBe(['id' => 12, 'name' => 'fjord-invoices']);

    // Aikido down: nothing is recorded on a guess.
    Cache::flush();
    resetHttp();
    Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
    app()->forgetInstance(AikidoClient::class);
    app()->forgetInstance(AikidoService::class);

    $this->artisan('larapilot:aikido-link', ['issues' => '24', '--spec' => 'US-001'])->assertExitCode(3);

    expect(Yaml::parseFile(base_path('.larapilot/aikido.yaml'))['issues'])->toHaveCount(1);
});

it('gives the gate a verdict from what is open and what was decided', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    addSpec();

    $aikido = app(AikidoService::class);

    fakeAikido([]);
    expect($aikido->findings()['gate'])->toMatchArray(['verdict' => 'PASS', 'summary' => 'Aikido reports nothing open.']);

    fakeAikido([aikidoIssues()[2]]);
    expect($aikido->findings()['gate'])->toMatchArray(['verdict' => 'WARN', 'summary' => '1 open finding below high.', 'blocking' => []]);

    $aikido->waive([40], 'Internal tool, never distributed.');
    expect($aikido->findings()['gate'])->toMatchArray(['verdict' => 'PASS', 'summary' => 'Every open finding was waived with a reason.']);

    fakeAikido();
    $aikido->waive([24], 'Not reachable: the header parser is never given outside input.');
    $aikido->link([31], 'US-001');

    expect($aikido->findings()['gate'])->toMatchArray([
        'verdict' => 'FAIL',
        'blocking' => [31],
        'undecided' => 0,
        'summary' => '1 open finding is high or above, all in the backlog and not fixed yet.',
    ]);

    config()->set('larapilot.aikido.fail_on', 'critical');
    expect($aikido->findings()['gate'])->toMatchArray(['verdict' => 'WARN', 'fail_on' => 'critical']);

    config()->set('larapilot.aikido.fail_on', 'none');
    expect($aikido->findings()['gate'])->toMatchArray(['verdict' => 'WARN', 'blocking' => []]);

    config()->set('larapilot.aikido.fail_on', 'nonsense');
    expect($aikido->failOn())->toBe('high');
});

it('says what is no longer open in Aikido', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    addSpec();
    fakeAikido();

    $aikido = app(AikidoService::class);
    $aikido->findings();
    $aikido->link([24], 'US-001');

    // The dependency was upgraded and Aikido scanned again.
    fakeAikido([aikidoIssues()[0], aikidoIssues()[2]]);
    $findings = $aikido->findings();

    expect(array_column($findings['issues'], 'id'))->toBe([31, 40])
        ->and($findings['closed'])->toHaveCount(1)
        ->and($findings['closed'][0]['id'])->toBe(24)
        ->and($findings['closed'][0]['spec'])->toBe('US-001')
        ->and($findings['closed'][0]['title'])->toBe('guzzlehttp/psr7');
});

it('writes the findings as a document of the project', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    fakeAikido();
    addSpec(['code' => 'US-001', 'title' => 'Upgrade guzzlehttp/psr7']);

    app(AikidoService::class)->findings();
    app(AikidoService::class)->link([24], 'US-001');

    Artisan::call('larapilot:aikido-issues', ['--new' => true, '--report' => true]);
    $data = envelope()['data'];

    expect($data['report'])->toBe('.larapilot/docs/security/aikido.md')
        ->and(array_column($data['issues'], 'id'))->toBe([31, 40])
        ->and($data['total'])->toBe(3);

    $report = (string) file_get_contents(base_path('.larapilot/docs/security/aikido.md'));

    // The report is about every finding, whatever the list was narrowed to.
    expect($report)->toStartWith("# Aikido findings — fjord-invoices\n")
        ->toContain('**Gate:** FAIL — 2 open findings are high or above: 1 with no decision yet.')
        ->toContain('| Critical | 1 | 0 | 1 | 0 |')
        ->toContain('| High | 1 | 1 | 0 | 0 |')
        ->toContain('### #24 — guzzlehttp/psr7')
        ->toContain('- **Decision:** in the backlog as US-001 (TODO)')
        ->toContain('- **CVE:** CVE-2026-1111')
        ->toContain('**How to fix:** Upgrade guzzlehttp/psr7 to 2.7.0 or later.')
        ->toContain('### #31 — SQL built from request input')
        ->toContain('- **Decision:** none yet')
        ->toContain('## Low (1)')
        ->not->toContain('client-secret');
});

it('fails the gate only when it is asked to', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    fakeAikido();

    $this->artisan('larapilot:aikido-issues')->assertExitCode(0);
    $this->artisan('larapilot:aikido-issues', ['--gate' => true])->assertExitCode(1);
    $this->artisan('larapilot:aikido-issues', ['--severity' => 'urgent'])->assertExitCode(2);

    fakeAikido([aikidoIssues()[2]]);
    $this->artisan('larapilot:aikido-issues', ['--gate' => true])->assertExitCode(0);
});

it('asks Aikido for a new scan', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    fakeAikido();

    $this->artisan('larapilot:aikido-scan')->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_starts_with($request->url(), 'https://app.aikido.dev/api/public/v1/repositories/code/12/scan')
        && str_contains($request->url(), 'include_sast_scan=true')
        && str_contains($request->url(), 'include_secrets_scan=true'));
});

it('finds the repository from the git remote', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    fakeAikido();
    config()->set('larapilot.aikido.repository', null);

    $remote = static fn (string $url): GitService => new class(app(ConfigService::class), $url) extends GitService
    {
        public function __construct(ConfigService $config, private readonly string $url)
        {
            parent::__construct($config);
        }

        public function originUrl(): ?string
        {
            return $this->url;
        }
    };

    foreach (['git@github.com:example/fjord-invoices.git', 'https://github.com/Example/Fjord-Invoices'] as $url) {
        app()->instance(GitService::class, $remote($url));
        app()->forgetInstance(AikidoService::class);

        expect(app(AikidoService::class)->repository()['id'])->toBe(12, $url);
    }

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'filter_name=fjord-invoices'));

    // A project Aikido does not know is said so, with what to do about it.
    app()->instance(GitService::class, $remote('git@github.com:example/unknown-project.git'));
    app()->forgetInstance(AikidoService::class);

    $status = app(AikidoService::class)->status();

    expect($status['repository'])->toBeNull()
        ->and($status['ready'])->toBeFalse()
        ->and($status['authenticated'])->toBeTrue()
        ->and($status['hints'][0])->toContain('LARAPILOT_AIKIDO_REPOSITORY');
});

it('says what is wrong when Aikido cannot be read', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();

    Http::fake(['app.aikido.dev/api/oauth/token' => Http::response(['error' => 'invalid_client'], 400)]);

    $status = app(AikidoService::class)->status();

    expect($status['ready'])->toBeFalse()
        ->and($status['authenticated'])->toBeFalse()
        ->and($status['error'])->toBe('Aikido refused the credentials (invalid_client).')
        ->and($status['hints'][0])->toContain('LARAPILOT_AIKIDO_CLIENT_SECRET');

    $this->artisan('larapilot:aikido-issues')->assertExitCode(3)->expectsOutputToContain('Aikido refused the credentials');

    Cache::flush();
    resetHttp();
    Http::fake([
        'app.aikido.dev/api/oauth/token' => Http::response(['access_token' => 'token-2', 'token_type' => 'bearer', 'expires_in' => 3600]),
        'app.aikido.dev/api/public/v1/*' => Http::response(['error' => 'Missing scope'], 403),
    ]);

    app()->forgetInstance(AikidoClient::class);
    app()->forgetInstance(AikidoService::class);

    $status = app(AikidoService::class)->status();

    expect($status['authenticated'])->toBeTrue()
        ->and($status['ready'])->toBeFalse()
        ->and($status['hints'][0])->toContain('issues:read');
});

it('asks once for a new token when the one it holds is refused', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();

    Cache::put('larapilot.aikido.token.'.sha1('https://app.aikido.dev|client-id'), 'stale-token', 600);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/oauth/token')) {
            return Http::response(['access_token' => 'fresh-token', 'token_type' => 'bearer', 'expires_in' => 3600]);
        }

        if ($request->hasHeader('Authorization', 'Bearer stale-token')) {
            return Http::response(['error' => 'expired'], 401);
        }

        return Http::response(str_contains($request->url(), 'open-issue-groups') ? [] : [
            ['id' => 12, 'name' => 'fjord-invoices', 'url' => 'https://github.com/example/fjord-invoices', 'branch' => 'main', 'last_scanned_at' => 1758900000],
        ]);
    });

    expect(app(AikidoService::class)->repository()['id'])->toBe(12)
        ->and(Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/oauth/token')))->toHaveCount(1);
});

it('lets an agent read Aikido over MCP and nothing more', function (): void {
    $allowed = (new ReflectionClass(RunArtisanTool::class))->getDefaultProperties()['allowed'];

    expect($allowed)->toContain('larapilot:aikido-status', 'larapilot:aikido-issues', 'larapilot:aikido-plan', 'larapilot:aikido-repos')
        ->not->toContain('larapilot:aikido-link')
        ->not->toContain('larapilot:aikido-push')
        ->not->toContain('larapilot:aikido-register')
        ->not->toContain('larapilot:aikido-scan');
});

it('shows the findings on the dashboard, with what was decided', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    // Off: the page stays in the menu and says what Aikido is and how to connect it.
    Http::fake();
    $this->get('/larapilot/security')
        ->assertOk()
        ->assertSee('Aikido scans this repository for vulnerable dependencies', false)
        ->assertSee('https://www.aikido.dev/', false)
        ->assertSee('larapilot:settings-set --aikido=YES', false)
        ->assertSee('>Security</a>', false)
        ->assertDontSee('Aikido is off for this project', false)
        ->assertDontSee('Read again', false);
    $this->get('/larapilot')->assertOk()->assertSee('href="'.url('/larapilot/security').'"', false);
    $this->get('/larapilot/security/aikido.md')->assertNotFound();
    Http::assertNothingSent();

    enableAikido();
    fakeAikido();
    addSpec(['code' => 'US-001', 'title' => 'Upgrade guzzlehttp/psr7']);

    app(AikidoService::class)->link([24], 'US-001');
    app(AikidoService::class)->waive([40], 'Internal tool, never distributed: AGPL does not apply.');

    $html = $this->get('/larapilot/security')
        ->assertOk()
        ->assertSee('>Security</a>', false)
        ->assertSee('A release is stopped', false)
        ->assertSee('2 open findings are high or above: 1 with no decision yet.', false)
        ->assertSee('guzzlehttp/psr7', false)
        ->assertSee('Vulnerable dependency', false)
        ->assertSee('https://www.cve.org/CVERecord?id=CVE-2026-1111', false)
        ->assertSee('Upgrade guzzlehttp/psr7 to 2.7.0 or later.', false)
        ->assertSee('href="'.url('/larapilot/specs/US-001').'"', false)
        ->assertSee('SQL built from request input', false)
        ->assertSee('No decision yet', false)
        // Waived here and ignored in Aikido: it left the open findings.
        ->assertSee('waived: Internal tool, never distributed: AGPL does not apply.', false)
        ->assertSee('Register for the client (.md)', false)
        ->assertSee('told to Aikido', false)
        ->assertDontSee('not told to Aikido yet', false)
        ->assertSee('/larapilot-aikido', false)
        ->assertDontSee('client-secret', false)
        ->assertDontSee('token-1', false)
        ->assertDontSee('@endif', false)
        ->getContent();

    // The most severe first, and the state of each one on its row.
    expect(strpos($html, 'guzzlehttp/psr7'))->toBeLessThan(strpos($html, 'SQL built from request input'))
        ->and(strpos($html, 'SQL built from request input'))->toBeLessThan(strpos($html, 'Package under AGPL-3.0'))
        ->and(substr_count($html, 'data-state="new"'))->toBe(2)
        ->and(substr_count($html, 'data-state="in_backlog"'))->toBe(2)
        ->and(substr_count($html, 'data-state="waived"'))->toBe(1);

    $report = $this->get('/larapilot/security/aikido.md')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
        ->getContent();

    expect($report)->toContain('# Aikido findings — fjord-invoices')
        ->toContain('- **Decision:** in the backlog as US-001 (TODO)');
});

it('keeps what it read for a few minutes, and reads again when asked', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    fakeAikido();

    $asked = static fn (): int => Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/open-issue-groups'))->count();

    $this->get('/larapilot/security')->assertOk();
    $this->get('/larapilot/security')->assertOk();

    expect($asked())->toBe(1);

    $this->get('/larapilot/security?refresh=1')->assertOk();

    expect($asked())->toBe(2);
});

it('says on the page what is wrong when Aikido cannot be read', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    config()->set('larapilot.aikido.client_secret', '');

    Http::fake();

    $this->get('/larapilot/security')
        ->assertOk()
        ->assertSee('The Aikido credentials are not set.', false)
        ->assertSee('LARAPILOT_AIKIDO_CLIENT_ID', false)
        ->assertSee('larapilot:aikido-status', false)
        ->assertDontSee('A release is stopped', false);

    Http::assertNothingSent();
});

it('groups confirmed findings by kind and fix for triage', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();

    $issues = aikidoIssues();
    $issues[] = [
        'id' => 25,
        'type' => 'open_source',
        'title' => 'guzzlehttp/psr7',
        'description' => 'Another CVE on the same package.',
        'time_to_fix_minutes' => 30,
        'group_status' => 'new',
        'severity_score' => 90,
        'severity' => 'critical',
        'locations' => [['id' => 12, 'name' => 'fjord-invoices', 'type' => 'code_repository']],
        'how_to_fix' => 'Upgrade guzzlehttp/psr7 to 2.7.0 or later.',
        'related_cve_ids' => ['CVE-2026-2222'],
        'first_detected_at' => 1757100000,
    ];

    fakeAikido($issues);

    app(AikidoService::class)->findings();

    $plan = app(AikidoService::class)->resolutionPlan([24, 25, 31]);

    expect($plan['ids'])->toBe([24, 25, 31])
        ->and($plan['groups'])->toHaveCount(2)
        ->and($plan['groups'][0]['ids'])->toBe([24, 25])
        ->and($plan['groups'][0]['cves'])->toBe(['CVE-2026-1111', 'CVE-2026-2222'])
        ->and($plan['groups'][1]['ids'])->toBe([31])
        ->and(collect($plan['domains'])->pluck('type_label', 'type_label')->keys()->all())->toBe(['Vulnerable dependency', 'Weakness in the code']);

    Artisan::call('larapilot:aikido-plan', ['--ids' => '24,25,31']);
    $payload = envelope();

    expect($payload['kind'])->toBe('aikido_plan')
        ->and($payload['data']['groups'][0]['ids'])->toBe([24, 25]);
});

it('never merges a leaked secret with another finding', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();

    $issues = aikidoIssues();
    $issues[] = [
        'id' => 50,
        'type' => 'leaked_secret',
        'title' => 'API key in history',
        'description' => 'A secret was committed.',
        'time_to_fix_minutes' => 60,
        'group_status' => 'new',
        'severity_score' => 99,
        'severity' => 'critical',
        'locations' => [['id' => 12, 'name' => 'fjord-invoices', 'type' => 'code_repository']],
        'how_to_fix' => 'Rotate the key and purge history.',
        'related_cve_ids' => [],
        'first_detected_at' => 1757200000,
    ];

    fakeAikido($issues);
    app(AikidoService::class)->findings();

    $plan = app(AikidoService::class)->resolutionPlan([24, 50]);

    expect($plan['groups'])->toHaveCount(2);

    $soloIds = array_map(static fn (array $group): int => $group['ids'][0], $plan['groups']);

    expect($soloIds)->toEqualCanonicalizing([24, 50]);
});

it('ships a skill that downloads the findings and hands them to triage', function (): void {
    $root = dirname(__DIR__, 2).'/resources';
    $skill = (string) file_get_contents($root.'/boost/skills/larapilot-aikido/SKILL.md');
    $triage = (string) file_get_contents($root.'/boost/skills/larapilot-triage/SKILL.md');
    $bug = (string) file_get_contents($root.'/boost/skills/larapilot-bug/SKILL.md');
    $ship = (string) file_get_contents($root.'/boost/skills/larapilot-ship/SKILL.md');
    $settings = (string) file_get_contents($root.'/boost/skills/larapilot-settings/SKILL.md');

    expect($skill)->toStartWith("---\nname: larapilot-aikido\n")
        ->toContain('php artisan larapilot:aikido-status')
        ->toContain('php artisan larapilot:aikido-issues --new --report')
        ->toContain('php artisan larapilot:aikido-plan --ids=24,31')
        ->toContain('aikido-link {ids} --spec={code}')
        ->toContain('activate `larapilot-triage`')
        ->toContain('Confirm (Sophia + Lars)')
        ->toContain('aikido-plan --ids=')
        ->toContain('**in this same turn**')
        ->toContain("```text\nAikido finding\n")
        ->toContain('**Never waive on your own.**')
        // The repository is the user's to name, and a decision is told to Aikido.
        ->toContain('### 1b. Repository (Matt) — only when `needs_repository`')
        ->toContain('**Never choose one yourself.**')
        ->toContain('aikido-repos --use={id}')
        ->toContain('**ignores it in Aikido** with that reason')
        ->toContain('`unsent` not empty → `aikido-push` once')
        ->toContain('php artisan larapilot:aikido-register')
        ->toContain('`issues:write` to tell Aikido the decisions')
        ->toContain('Never call the Aikido API yourself')
        ->toContain('never echo it in chat')
        ->toContain('`in_backlog` is not fixed')
        ->and(strlen($skill))->toBeLessThanOrEqual(9000);

    // Every command the skill names exists.
    preg_match_all('/larapilot:(aikido-[a-z]+)/', $skill, $matches);

    foreach (array_unique($matches[1]) as $command) {
        expect(array_key_exists('larapilot:'.$command, Artisan::all()))->toBeTrue($command);
    }

    expect($triage)->toContain('## Handoff from `larapilot-aikido`')
        ->toContain('Do not ask about the verdict')
        ->and(strlen($triage))->toBeLessThanOrEqual(9000)
        ->and($bug)->toContain('An **Aikido finding** block under `evidence`')
        ->and($ship)->toContain('php artisan larapilot:aikido-issues --gate --report')
        ->and($settings)->toContain('**7e. Aikido**')
        ->toContain('--aikido=NO');

    expect((string) file_get_contents($root.'/larapilot/runtime-core-settings-2.md'))->toContain('### Aikido (`settings.aikido`)')
        ->and((string) file_get_contents($root.'/larapilot/shared-runtime.md'))->toContain('`larapilot-aikido` and `larapilot-error` do the same.')
        ->and((string) file_get_contents($root.'/larapilot/runtime-core-economy.md'))->toContain('**`larapilot-aikido`**')
        ->and((string) file_get_contents($root.'/larapilot/integrations.md'))->toContain('## Aikido (`settings.aikido`)')
        ->and((string) file_get_contents($root.'/boost/guidelines/core.blade.php'))->toContain('`larapilot-aikido`');
});

/**
 * A project whose git remote is the one given.
 */
function aikidoOrigin(string $url): void
{
    app()->instance(GitService::class, new class(app(ConfigService::class), $url) extends GitService
    {
        public function __construct(ConfigService $config, private readonly string $url)
        {
            parent::__construct($config);
        }

        public function originUrl(): ?string
        {
            return $this->url;
        }
    });

    app()->forgetInstance(AikidoService::class);
}

/**
 * @return list<string>
 */
function aikidoWrites(): array
{
    return Http::recorded(fn (Request $request): bool => in_array($request->method(), ['PUT', 'POST'], true) && str_contains($request->url(), '/issues/'))
        ->map(fn (array $pair): string => $pair[0]->method().' '.(string) parse_url($pair[0]->url(), PHP_URL_PATH))
        ->values()
        ->all();
}

it('tells Aikido a waiver by ignoring the finding there, with the reason', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    fakeAikido();

    // Finding 31 is in this repository alone: the whole finding is ignored.
    Artisan::call('larapilot:aikido-link', ['issues' => '31', '--waive' => true, '--reason' => 'The query is built from a fixed list, never from the request.']);
    $told = envelope()['data']['aikido'];

    expect($told)->toBe([['id' => 31, 'sent' => true, 'action' => 'ignored', 'issues' => 1, 'whole_finding' => true]])
        ->and(aikidoWrites())->toBe(['PUT /api/public/v1/issues/groups/31/ignore']);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/issues/groups/31/ignore')
        && $request['reason'] === 'The query is built from a fixed list, never from the request.');

    // Finding 40 is also in another repository of the workspace: only the
    // issue of this one is ignored, and the other project keeps its own.
    Artisan::call('larapilot:aikido-link', ['issues' => '40', '--waive' => true, '--reason' => 'Internal tool, never distributed: AGPL does not apply.']);

    expect(envelope()['data']['aikido'][0])->toBe(['id' => 40, 'sent' => true, 'action' => 'ignored', 'issues' => 1, 'whole_finding' => false])
        ->and(aikidoWrites())->toBe(['PUT /api/public/v1/issues/groups/31/ignore', 'PUT /api/public/v1/issues/4001/ignore']);

    $ledger = Yaml::parseFile(base_path('.larapilot/aikido.yaml'));

    expect($ledger['issues'][31]['sent'])->toBe('ignored')
        ->and($ledger['issues'][31]['sent_group'])->toBeTrue()
        ->and($ledger['issues'][40]['sent'])->toBe('ignored')
        ->and($ledger['issues'][40]['sent_issues'])->toBe([4001])
        ->and($ledger['issues'][40])->not->toHaveKey('sent_group');

    // Taking a waiver back takes it back in Aikido, the way it was told.
    $this->artisan('larapilot:aikido-link', ['issues' => '31,40', '--forget' => true])->assertSuccessful();

    expect(array_slice(aikidoWrites(), 2))->toBe(['PUT /api/public/v1/issues/groups/31/unignore', 'PUT /api/public/v1/issues/4001/unignore'])
        ->and(Yaml::parseFile(base_path('.larapilot/aikido.yaml'))['issues'])->toBe([]);
});

it('leaves a note in Aikido on a finding that went to the backlog', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    fakeAikido();
    addSpec(['code' => 'US-001', 'title' => 'Upgrade guzzlehttp/psr7']);

    Artisan::call('larapilot:aikido-link', ['issues' => '24', '--spec' => 'US-001']);

    expect(envelope()['data']['aikido'])->toBe([['id' => 24, 'sent' => true, 'action' => 'noted', 'spec' => 'US-001']])
        ->and(aikidoWrites())->toBe(['POST /api/public/v1/issues/groups/24/notes']);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/issues/groups/24/notes')
        && $request['note'] === 'Larapilot: the fix for fjord-invoices is in the backlog as US-001 — Upgrade guzzlehttp/psr7.');

    $findings = app(AikidoService::class)->findings();

    // Told, and still open: a finding in the backlog is not fixed.
    expect($findings['unsent'])->toBe([])
        ->and($findings['states']['in_backlog'])->toBe(1)
        ->and(Yaml::parseFile(base_path('.larapilot/aikido.yaml'))['issues'][24]['sent_spec'])->toBe('US-001');

    // Forgetting a note takes nothing back: nothing was ignored.
    $this->artisan('larapilot:aikido-link', ['issues' => '24', '--forget' => true])->assertSuccessful();

    expect(aikidoWrites())->toHaveCount(1);
});

it('keeps a decision to itself when it is asked to', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    fakeAikido();
    addSpec();

    Artisan::call('larapilot:aikido-link', ['issues' => '40', '--waive' => true, '--reason' => 'Internal tool, never distributed: AGPL does not apply.', '--local' => true]);

    expect(envelope()['data']['aikido'])->toBe([])
        ->and(aikidoWrites())->toBe([])
        ->and(app(AikidoService::class)->findings()['unsent'])->toBe([40]);

    // The whole project, with one line of .env.
    config()->set('larapilot.aikido.push_decisions', false);

    $this->artisan('larapilot:aikido-link', ['issues' => '24', '--spec' => 'US-001'])->assertSuccessful();
    $this->artisan('larapilot:aikido-push')->assertExitCode(4)->expectsOutputToContain('keeps its decisions to itself');

    $findings = app(AikidoService::class)->findings();

    expect(aikidoWrites())->toBe([])
        ->and($findings['unsent'])->toBe([])
        ->and($findings['push_decisions'])->toBeFalse()
        ->and(app(AikidoService::class)->status()['push_decisions'])->toBeFalse();
});

it('keeps the decision when Aikido refuses it, and tells it later', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    fakeAikido();
    addSpec();

    // Credentials made for reading: Aikido refuses the write.
    fakeAikido(null, true);

    Artisan::call('larapilot:aikido-link', ['issues' => '40,24', '--waive' => true, '--reason' => 'Internal tool, never distributed: AGPL does not apply.']);
    $data = envelope()['data'];

    // The decision is recorded, the command succeeds, and it says what is left to do.
    expect($data['state'])->toBe('waived')
        ->and($data['aikido'])->toHaveCount(1)
        ->and($data['aikido'][0]['sent'])->toBeFalse()
        ->and($data['aikido'][0]['status'])->toBe(403)
        ->and($data['aikido'][0]['error'])->toBe('These Aikido credentials may not do this — The client lacks the issues:write scope.')
        ->and($data['hint'])->toContain('issues:write')->toContain('larapilot:aikido-push');

    $ledger = Yaml::parseFile(base_path('.larapilot/aikido.yaml'));

    expect($ledger['issues'][40]['state'])->toBe('waived')
        ->and($ledger['issues'][40])->not->toHaveKey('sent')
        ->and($ledger['issues'][24]['state'])->toBe('waived');

    $findings = app(AikidoService::class)->findings();

    expect($findings['unsent'])->toBe([24, 40])
        ->and($findings['states']['waived'])->toBe(2);

    $this->get('/larapilot/security')->assertOk()->assertSee('2 decisions were not told to Aikido yet (#24, #40)', false);

    // Still refused: the push says so, and fails.
    $this->artisan('larapilot:aikido-push')->assertExitCode(3)->expectsOutputToContain('php artisan larapilot:aikido-push');

    // The credentials were given the scope.
    fakeAikido();
    Artisan::call('larapilot:aikido-push');
    $pushed = envelope()['data'];

    expect(array_column($pushed['aikido'], 'id'))->toBe([24, 40])
        ->and(array_column($pushed['aikido'], 'sent'))->toBe([true, true])
        ->and($pushed['left'])->toBe([])
        ->and(aikidoWrites())->toBe(['PUT /api/public/v1/issues/groups/24/ignore', 'PUT /api/public/v1/issues/4001/ignore']);
});

it('keeps a waiver Aikido would not take back', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    fakeAikido();

    $this->artisan('larapilot:aikido-link', ['issues' => '31', '--waive' => true, '--reason' => 'The query is built from a fixed list, never from the request.'])->assertSuccessful();

    resetHttp();
    Http::preventStrayRequests();
    Http::fake([
        'app.aikido.dev/api/oauth/token' => Http::response(['access_token' => 'token-1', 'expires_in' => 3600]),
        'app.aikido.dev/api/public/v1/issues/groups/31/unignore' => Http::response(['reason_phrase' => 'Try later'], 500),
    ]);

    // Ignored in Aikido and forgotten here, the finding would never come back.
    $this->artisan('larapilot:aikido-link', ['issues' => '31', '--forget' => true])
        ->assertExitCode(3)
        ->expectsOutputToContain('Aikido did not take back the waiver of #31');

    expect(Yaml::parseFile(base_path('.larapilot/aikido.yaml'))['issues'][31]['state'])->toBe('waived');

    // Here only, when that is what is wanted.
    $this->artisan('larapilot:aikido-link', ['issues' => '31', '--forget' => true, '--local' => true])->assertSuccessful();

    expect(Yaml::parseFile(base_path('.larapilot/aikido.yaml'))['issues'])->toBe([]);
});

it('asks which repository of Aikido the project is when the remote finds none', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    fakeAikido();
    config()->set('larapilot.aikido.repository', null);
    aikidoOrigin('git@github.com:agency/client-portal.git');

    $status = app(AikidoService::class)->status();

    expect($status['repository'])->toBeNull()
        ->and($status['needs_repository'])->toBeTrue()
        ->and($status['ready'])->toBeFalse()
        ->and($status['hints'][0])->toContain('php artisan larapilot:aikido-repos --use={id}');

    $this->artisan('larapilot:aikido-issues')->assertExitCode(3)->expectsOutputToContain('larapilot:aikido-repos');

    // The repositories of the workspace, for the user to choose from.
    Artisan::call('larapilot:aikido-repos');
    $listed = envelope()['data'];

    expect(array_column($listed['repositories'], 'name'))->toBe(['fjord-invoices', 'fjord-website'])
        ->and(array_column($listed['repositories'], 'current'))->toBe([false, false])
        ->and($listed['origin'])->toBe('git@github.com:agency/client-portal.git')
        ->and($listed['chosen'])->toBeNull();

    // A name Aikido does not hold is refused, and nothing is kept.
    $this->artisan('larapilot:aikido-repos', ['--use' => '999'])->assertExitCode(4)->expectsOutputToContain('Aikido holds no repository');
    $this->artisan('larapilot:aikido-repos', ['--use' => 'fjord'])->assertExitCode(4);
    $this->artisan('larapilot:aikido-repos', ['--use' => '12', '--forget' => true])->assertExitCode(2);

    expect(is_file(base_path('.larapilot/aikido.yaml')))->toBeFalse();

    Artisan::call('larapilot:aikido-repos', ['--use' => '12']);
    $chosen = envelope()['data'];

    expect($chosen['repository']['name'])->toBe('fjord-invoices')
        ->and($chosen['source'])->toBe('chosen')
        ->and($chosen['hint'])->toContain('commit it');

    $ledger = Yaml::parseFile(base_path('.larapilot/aikido.yaml'));

    expect($ledger['chosen_repository']['id'])->toBe(12)
        ->and($ledger['chosen_repository']['name'])->toBe('fjord-invoices');

    app()->forgetInstance(AikidoService::class);
    $status = app(AikidoService::class)->status();

    expect($status['ready'])->toBeTrue()
        ->and($status['needs_repository'])->toBeFalse()
        ->and($status['repository']['id'])->toBe(12)
        ->and($status['repository_source'])->toBe('chosen')
        ->and(app(AikidoService::class)->findings()['total'])->toBe(3);

    Artisan::call('larapilot:aikido-repos');

    expect(collect(envelope()['data']['repositories'])->firstWhere('current', true)['id'])->toBe(12);

    // By its exact name too, and a decision does not lose the choice.
    $this->artisan('larapilot:aikido-repos', ['--use' => 'Fjord-Website'])->assertSuccessful();
    expect(Yaml::parseFile(base_path('.larapilot/aikido.yaml'))['chosen_repository']['id'])->toBe(11);

    $this->artisan('larapilot:aikido-repos', ['--use' => '12'])->assertSuccessful();
    $this->artisan('larapilot:aikido-link', ['issues' => '31', '--waive' => true, '--reason' => 'The query is built from a fixed list, never from the request.'])->assertSuccessful();
    expect(Yaml::parseFile(base_path('.larapilot/aikido.yaml'))['chosen_repository']['id'])->toBe(12);

    // .env names the repository for one machine, and wins there.
    config()->set('larapilot.aikido.repository', 'fjord-website');
    Artisan::call('larapilot:aikido-repos', ['--use' => '12']);

    expect(envelope()['data']['source'])->toBe('env')
        ->and(envelope()['data']['hint'] ?? '')->toBe('');

    config()->set('larapilot.aikido.repository', null);
    Artisan::call('larapilot:aikido-repos', ['--forget' => true]);

    expect(envelope()['data']['repository'])->toBeNull()
        ->and(Yaml::parseFile(base_path('.larapilot/aikido.yaml')))->not->toHaveKey('chosen_repository');
});

it('asks for the repository on the page, and keeps the answer', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    fakeAikido();
    config()->set('larapilot.aikido.repository', null);
    aikidoOrigin('git@github.com:agency/client-portal.git');

    $this->get('/larapilot/security')
        ->assertOk()
        ->assertSee('No repository of the Aikido workspace matches this project.', false)
        ->assertSee('Which repository of Aikido is this project?', false)
        ->assertSee('git@github.com:agency/client-portal.git', false)
        ->assertSee('<option value="12">fjord-invoices · main · github (#12)</option>', false)
        ->assertSee('<option value="11">fjord-website · main · github (#11)</option>', false)
        ->assertSee('action="'.url('/larapilot/security/repository').'"', false)
        ->assertDontSee('A release is stopped', false);

    $this->post('/larapilot/security/repository', ['repository' => 'fjord'])->assertSessionHasErrors('repository');
    $this->post('/larapilot/security/repository', ['repository' => 999])
        ->assertRedirect(url('/larapilot/security'))
        ->assertSessionHas('larapilot_error');

    $this->post('/larapilot/security/repository', ['repository' => 12])
        ->assertRedirect(url('/larapilot/security').'?refresh=1')
        ->assertSessionHas('larapilot_success', 'This project is “fjord-invoices” in Aikido. The choice is in .larapilot/aikido.yaml: commit it.');

    expect(Yaml::parseFile(base_path('.larapilot/aikido.yaml'))['chosen_repository']['id'])->toBe(12);

    app()->forgetInstance(AikidoService::class);

    $this->get('/larapilot/security')
        ->assertOk()
        ->assertSee('A release is stopped', false)
        ->assertDontSee('Which repository of Aikido is this project?', false);

    // With the link off there is nothing to choose.
    $this->artisan('larapilot:settings-set', ['--aikido' => 'NO'])->assertSuccessful();
    $this->post('/larapilot/security/repository', ['repository' => 12])->assertNotFound();
});

it('lists every finding for the client: open, resolved, and ignored with its reason', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    fakeAikido();
    addSpec(['code' => 'US-001', 'title' => 'Upgrade guzzlehttp/psr7']);

    $aikido = app(AikidoService::class);
    $aikido->findings();
    $aikido->link([24], 'US-001');
    $aikido->waive([40], 'Internal tool, never distributed: AGPL does not apply.');

    $register = $aikido->register(true);

    expect(array_column($register['open'], 'id'))->toBe([24, 31])
        ->and(array_column($register['resolved'], 'id'))->toBe([52])
        ->and($register['resolved'][0]['closed_at'])->toStartWith('2025-09-19')
        // the one ignored by hand in Aikido, then the one waived here
        ->and(array_column($register['ignored'], 'id'))->toBe([61, 40])
        ->and($register['ignored'][0]['ignored_by'])->toBe('user')
        ->and($register['ignored'][0]['reason'])->toBeNull()
        ->and($register['ignored'][1]['reason'])->toBe('Internal tool, never distributed: AGPL does not apply.')
        ->and($register['counts']['open'])->toBe(['critical' => 1, 'high' => 1, 'medium' => 0, 'low' => 0, 'all' => 2])
        ->and($register['counts']['resolved']['all'])->toBe(1)
        ->and($register['counts']['ignored']['all'])->toBe(2);

    Artisan::call('larapilot:aikido-register');
    $data = envelope()['data'];

    expect($data['register'])->toBe('.larapilot/docs/security/aikido-register.md')
        ->and($data['language'])->toBe('en')
        ->and([$data['open'], $data['resolved'], $data['ignored'], $data['without_reason']])->toBe([2, 1, 2, 1]);

    $document = (string) file_get_contents(base_path('.larapilot/docs/security/aikido-register.md'));

    expect($document)->toStartWith("# Security findings register — fjord-invoices\n")
        ->toContain('- **Repository:** fjord-invoices (https://github.com/example/fjord-invoices)')
        ->toContain('| Severity | Open | Resolved | Ignored |')
        ->toContain('| Critical | 1 | 0 | 0 |')
        ->toContain('| **Total** | **2** | **1** | **2** |')
        ->toContain('## Open findings (2)')
        ->toContain('| 24 | Critical | Vulnerable dependency | guzzlehttp/psr7 | CVE-2026-1111 | 2025-09-04 | Fix planned: US-001 |')
        ->toContain('| 31 | High | Weakness in the code | SQL built from request input | — | 2025-09-16 | None yet |')
        ->toContain('## Resolved findings (1)')
        ->toContain('| 52 | High | Vulnerable dependency | symfony/http-kernel | — | 2025-08-12 | 2025-09-19 | No longer found by the scan |')
        ->toContain('## Ignored findings (2)')
        ->toContain('| 61 | Medium | Weakness in the code | Debug flag in a test fixture | — | 2025-08-12 | 2025-09-20 | Ignored by a person in Aikido, where the reason is kept. |')
        ->toContain('| Internal tool, never distributed: AGPL does not apply. |')
        ->not->toContain('client-secret')
        ->not->toContain('token-1');

    // The same document from the page, under a name that says what it is.
    $this->get('/larapilot/security/register.md')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
        ->assertHeader('Content-Disposition', 'attachment; filename="fjord-invoices-security-register-'.now()->format('Y-m-d').'.md"')
        ->assertSee('## Ignored findings (2)', false);

    $this->artisan('larapilot:settings-set', ['--aikido' => 'NO'])->assertSuccessful();
    $this->get('/larapilot/security/register.md')->assertNotFound();
});

it('writes the register in the language of the PRD, with the reasons as they were written', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    fakeAikido();
    addSpec(['code' => 'US-001', 'title' => 'Aggiornare guzzlehttp/psr7']);

    app(PrdService::class)->write(<<<'MD'
# Portale fatture

## Panoramica
Il portale serve a registrare i clienti e le fatture che i tecnici emettono.

## Funzionalità principali
- Anagrafica dei clienti con lo storico delle fatture
- Gestione degli allegati e delle scadenze
MD);

    $aikido = app(AikidoService::class);
    $aikido->findings();
    $aikido->link([24], 'US-001');
    $aikido->waive([40], 'Strumento interno | mai distribuito: la AGPL non si applica.');

    $document = app(AikidoRegisterWriter::class)->render($aikido->register(true));

    expect(app(AikidoRegisterWriter::class)->language())->toBe('it')
        ->and($document)->toStartWith("# Registro delle vulnerabilità — fjord-invoices\n")
        ->toContain('| Gravità | Aperte | Risolte | Ignorate |')
        ->toContain('## Segnalazioni aperte (2)')
        ->toContain('| 24 | Critica | Dipendenza vulnerabile | guzzlehttp/psr7 | CVE-2026-1111 | 2025-09-04 | Correzione pianificata: US-001 |')
        ->toContain('## Segnalazioni risolte (1)')
        ->toContain('Non più rilevata dalla scansione')
        ->toContain('## Segnalazioni ignorate (2)')
        // The reason is the user's: kept as written, and inside its cell.
        ->toContain('| Strumento interno \\| mai distribuito: la AGPL non si applica. |')
        ->toContain('Ignorata da una persona in Aikido, dove è conservata la motivazione.')
        ->and(app(AikidoRegisterWriter::class)->filename($aikido->register()))->toBe('fjord-invoices-registro-vulnerabilita-'.now()->format('Y-m-d').'.md');
});

it('asks Aikido once for the register, and again after a decision', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableAikido();
    fakeAikido();

    $asked = static fn (): int => Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'filter_status=closed'))->count();

    $this->get('/larapilot/security/register.md')->assertOk();
    $this->get('/larapilot/security/register.md')->assertOk();

    expect($asked())->toBe(1);

    app(AikidoService::class)->waive([40], 'Internal tool, never distributed: AGPL does not apply.');

    $this->get('/larapilot/security/register.md')->assertOk()->assertSee('Internal tool, never distributed', false);

    expect($asked())->toBe(2);
});
