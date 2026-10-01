<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Larapilot\Mcp\Tools\RunArtisanTool;
use Larapilot\Services\Boogle\BoogleClient;
use Larapilot\Services\BoogleService;
use Larapilot\Services\ConfigService;
use Symfony\Component\Yaml\Yaml;

const BOOGLE_PROJECT = 'e2fa2ce4-b8a2-4f5a-973d-f89a8ff3f4c0';

/**
 * One time an exception was thrown, as Boogle lists it: with the user and
 * the request, which Larapilot has to leave where they are.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function boogleRow(int $number, array $overrides = []): array
{
    return array_merge([
        'id' => sprintf('%08d-0000-4000-8000-%012d', $number, $number),
        'project_id' => BOOGLE_PROJECT,
        'exception' => 'Illuminate\\Database\\QueryException',
        'error' => "SQLSTATE[23000]: Duplicate entry 'mario.rossi@example.com' for key 'customers_email_unique'",
        'file' => '/home/forge/loyalty.example.com/releases/20260920/app/Services/CustomerImporter.php',
        'line' => 88,
        'status' => 'OPEN',
        'issue_code' => '#BUG'.$number,
        'issue_prefix' => 'BUG',
        'issue_number' => $number,
        'user' => ['id' => 7, 'email' => 'mario.rossi@example.com', 'name' => 'Mario Rossi'],
        'http' => [
            'method' => 'POST',
            'url' => 'https://loyalty.example.com/admin/customers/import?token=query-secret',
            'query' => ['token' => 'query-secret'],
            'payload' => ['card' => '4111111111111111'],
        ],
        'created_at' => '2026-09-2'.min(6, $number % 7).'T10:00:00Z',
    ], $overrides);
}

/**
 * @return array{OPEN: list<array<string, mixed>>, READ: list<array<string, mixed>>}
 */
function boogleRows(): array
{
    $card = [
        'exception' => 'ErrorException',
        'error' => 'Attempt to read property "points" on null',
        'file' => '/home/forge/loyalty.example.com/releases/20260920/app/Http/Controllers/CardController.php',
        'line' => 41,
        'http' => ['method' => 'GET', 'url' => 'https://loyalty.example.com/cards/0f8fad5b-d9cb-469f-a165-70867728950e'],
    ];

    return [
        'OPEN' => [
            boogleRow(1, ['created_at' => '2026-09-21T10:00:00Z']),
            boogleRow(2, ['created_at' => '2026-09-23T10:00:00Z']),
            boogleRow(3, ['created_at' => '2026-09-26T10:00:00Z']),
            boogleRow(4, $card + ['created_at' => '2026-09-25T08:00:00Z']),
            boogleRow(5, [
                'exception' => 'Symfony\\Component\\Mailer\\Exception\\TransportException',
                'error' => 'Connection could not be established with host "smtp.example.com:587"',
                'file' => '/home/forge/loyalty.example.com/releases/20260920/vendor/symfony/mailer/Transport/Smtp/Stream/SocketStream.php',
                'line' => 154,
                'http' => ['method' => 'POST', 'url' => '/password/email'],
                'created_at' => '2026-09-24T07:00:00Z',
            ]),
            boogleRow(6, [
                'exception' => 'App\\Monitoring\\ProjectOfflineException',
                'error' => 'Uptime check failed with HTTP status 503.',
                'file' => null,
                'line' => null,
                'issue_code' => '#OUT1',
                'issue_prefix' => 'OUT',
                'issue_number' => 1,
                'user' => null,
                'http' => null,
                'created_at' => '2026-09-22T03:00:00Z',
            ]),
        ],
        'READ' => [
            boogleRow(7, $card + ['status' => 'READ', 'created_at' => '2026-09-20T08:00:00Z']),
        ],
    ];
}

/**
 * A second `Http::fake()` adds to the first instead of replacing it: what
 * Boogle answers is set from scratch every time.
 *
 * @param  array{OPEN?: list<array<string, mixed>>, READ?: list<array<string, mixed>>}|null  $rows
 */
function fakeBoogle(?array $rows = null): void
{
    Http::swap(new Factory);

    $rows ??= boogleRows();

    Http::fake([
        'boogle.example.com/api/admin/projects?*' => Http::response(['current_page' => 1, 'last_page' => 1, 'data' => [
            ['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'title' => 'Website', 'url' => 'https://www.example.com', 'uptime_enabled' => false, 'group' => null, 'key' => 'WEBSITE_KEY', 'api_token' => 'WEBSITE_INGEST_TOKEN'],
            ['id' => BOOGLE_PROJECT, 'title' => 'Loyalty', 'url' => 'https://loyalty.example.com', 'uptime_enabled' => true, 'uptime_url' => 'https://loyalty.example.com/up', 'group' => ['id' => 'g1', 'title' => 'Shops'], 'key' => 'LOYALTY_KEY', 'api_token' => 'LOYALTY_INGEST_TOKEN'],
        ]]),
        'boogle.example.com/api/admin/projects/'.BOOGLE_PROJECT.'/exceptions/*/status' => Http::response(['status' => 'FIXED', 'status_events' => []]),
        'boogle.example.com/api/admin/projects/'.BOOGLE_PROJECT.'/exceptions?status=OPEN*' => Http::response(['current_page' => 1, 'last_page' => 1, 'data' => $rows['OPEN'] ?? []]),
        'boogle.example.com/api/admin/projects/'.BOOGLE_PROJECT.'/exceptions?status=READ*' => Http::response(['current_page' => 1, 'last_page' => 1, 'data' => $rows['READ'] ?? []]),
    ]);
}

function enableBoogle(): void
{
    test()->artisan('larapilot:settings-set', ['--boogle' => 'YES'])->assertSuccessful();

    config()->set('larapilot.boogle.url', 'https://boogle.example.com');
    config()->set('larapilot.boogle.server', null);
    config()->set('larapilot.boogle.token', 'admin-token');
    config()->set('larapilot.boogle.project', 'Loyalty');
    config()->set('larapilot.boogle.project_key', null);
}

/**
 * @return array<string, mixed>
 */
function boogleEnvelope(): array
{
    $decoded = json_decode(trim(Artisan::output()), true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * What an occurrence says about a person, and what opens Boogle: none of
 * it may be in what Larapilot keeps or shows.
 */
function expectNothingPersonal(string $text, string $where): void
{
    foreach (['mario.rossi@example.com', 'Mario Rossi', 'query-secret', '4111111111111111', 'LOYALTY_KEY', 'LOYALTY_INGEST_TOKEN', 'WEBSITE_INGEST_TOKEN', 'admin-token'] as $secret) {
        expect(str_contains($text, $secret))->toBeFalse($secret.' is in '.$where);
    }
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-27 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('keeps Boogle off until the project turns it on', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    Http::fake();

    $config = app(ConfigService::class);

    expect($config->boogleEnabled())->toBeFalse()
        ->and($config->settings()['boogle'])->toBe('NO')
        ->and($config->allowedBoogleModes())->toBe(['YES', 'NO']);

    config()->set('larapilot.boogle.url', null);
    config()->set('larapilot.boogle.server', null);
    config()->set('larapilot.boogle.token', null);

    $status = app(BoogleService::class)->status();

    expect($status['enabled'])->toBeFalse()
        ->and($status['configured'])->toBeFalse()
        ->and($status['ready'])->toBeFalse()
        ->and($status['provider'])->toBe('boogle')
        ->and($status['hints'][0])->toContain('larapilot:settings-set --errors=YES --errors-provider=boogle')
        ->and($status['hints'][1])->toContain('LARAPILOT_BOOGLE_URL')
        ->and($status['hints'][2])->toContain('LARAPILOT_BOOGLE_TOKEN');

    $this->artisan('larapilot:errors-list')->assertExitCode(4)->expectsOutputToContain('Production errors are off for this project');
    $this->artisan('larapilot:errors-link', ['errors' => 'BUG1', '--spec' => 'US-001'])->assertExitCode(4);
    $this->artisan('larapilot:errors-resolve', ['errors' => 'BUG1'])->assertExitCode(4);

    // Nothing was asked of Boogle.
    Http::assertNothingSent();

    $this->artisan('larapilot:settings-set', ['--boogle' => 'YES'])->assertSuccessful();

    $written = Yaml::parseFile(base_path('.larapilot/config.yaml'))['settings'];

    expect($written['boogle'])->toBeTrue()
        ->and($written['errors'])->toBeTrue()
        ->and($written['errors_provider'])->toBe('boogle')
        ->and(app(ConfigService::class)->boogleEnabled())->toBeTrue()
        ->and(app(ConfigService::class)->errorsEnabled())->toBeTrue()
        ->and(app(ConfigService::class)->errorsProvider())->toBe('boogle');
});

it('finds where Boogle is from its address, or from where the exceptions are sent', function (): void {
    $client = app(BoogleClient::class);

    foreach ([
        ['https://boogle.example.com', null, 'https://boogle.example.com'],
        ['https://boogle.example.com/', null, 'https://boogle.example.com'],
        ['HTTPS://Boogle.Example.com:8443/tools/boogle/', null, 'https://Boogle.Example.com:8443/tools/boogle'],
        // what the client package sends to, without its path
        [null, 'https://boogle.example.com/api/log', 'https://boogle.example.com'],
        [null, 'https://example.com/boogle/api/log', 'https://example.com/boogle'],
        // the address given for Larapilot comes first
        ['https://a.example.com', 'https://b.example.com/api/log', 'https://a.example.com'],
        ['https://boogle.example.com/api/admin', null, 'https://boogle.example.com'],
        // not an address of the web
        ['ftp://boogle.example.com', null, ''],
        ['file:///etc/passwd', null, ''],
        ['boogle.example.com', null, ''],
        [null, null, ''],
    ] as [$url, $server, $host]) {
        config()->set('larapilot.boogle.url', $url);
        config()->set('larapilot.boogle.server', $server);

        expect($client->host())->toBe($host, (string) ($url ?? $server));
    }
});

it('finds the project the application is, and keeps nothing that opens it', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableBoogle();
    fakeBoogle();

    $this->artisan('larapilot:errors-status')->assertSuccessful();

    $status = app(BoogleService::class)->status();

    expect($status['ready'])->toBeTrue()
        ->and($status['authenticated'])->toBeTrue()
        ->and($status['host'])->toBe('https://boogle.example.com')
        ->and($status['project'])->toBe([
            'id' => BOOGLE_PROJECT,
            'title' => 'Loyalty',
            'url' => 'https://loyalty.example.com',
            'group' => 'Shops',
            'uptime' => true,
            'provider' => 'boogle',
        ])
        ->and($status['hints'])->toBe([]);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer admin-token')
        && str_starts_with($request->url(), 'https://boogle.example.com/api/admin/projects'));

    expectNothingPersonal((string) json_encode($status), 'the status');

    // by its id
    config()->set('larapilot.boogle.project', BOOGLE_PROJECT);
    expect(app(BoogleService::class)->project()['title'])->toBe('Loyalty');

    // by the key the client package sends with
    config()->set('larapilot.boogle.project', null);
    config()->set('larapilot.boogle.project_key', 'WEBSITE_KEY');
    expect(app(BoogleService::class)->project()['title'])->toBe('Website');

    // by the address of the application, with or without www
    config()->set('larapilot.boogle.project_key', null);
    config()->set('app.url', 'https://www.loyalty.example.com');
    expect(app(BoogleService::class)->project()['title'])->toBe('Loyalty');

    // a machine of a developer is not the application of anybody
    config()->set('app.url', 'http://localhost');
    expect(app(BoogleService::class)->project())->toBeNull();

    config()->set('larapilot.boogle.project', 'Nope');

    $status = app(BoogleService::class)->status();

    expect($status['ready'])->toBeFalse()
        ->and($status['authenticated'])->toBeTrue()
        ->and($status['hints'][0])->toContain('No project of Boogle matches this application')
        ->and($status['hints'][0])->toContain('LARAPILOT_BOOGLE_PROJECT');
});

it('puts together the times one bug was thrown, and leaves the person in Boogle', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableBoogle();
    fakeBoogle();

    $errors = app(BoogleService::class)->errors();
    $byClass = collect($errors['errors'])->keyBy('short');

    // the ones thrown the most first, the outages last
    expect(array_column($errors['errors'], 'short'))->toBe(['QueryException', 'ErrorException', 'TransportException', 'ProjectOfflineException'])
        ->and(array_column($errors['errors'], 'count'))->toBe([3, 2, 1, 1])
        ->and($errors['counts'])->toBe(['errors' => 3, 'occurrences' => 6, 'new' => 3, 'in_backlog' => 0, 'ignored' => 0, 'returned' => 0, 'outages' => 1])
        ->and($errors['summary'])->toBe('3 open errors, thrown 6 times: 3 with no decision yet. The monitor found the application down 1 time.')
        ->and($errors['total'])->toBe(4)
        ->and($errors['truncated'])->toBeFalse();

    expect($byClass['QueryException'])->toMatchArray([
        'kind' => 'error',
        'class' => 'Illuminate\\Database\\QueryException',
        'message' => "SQLSTATE[23000]: Duplicate entry '[address]' for key 'customers_email_unique'",
        'file' => 'app/Services/CustomerImporter.php',
        'line' => 88,
        'where' => 'app/Services/CustomerImporter.php:88',
        'in_vendor' => false,
        'request' => 'POST /admin/customers/import',
        'codes' => ['#BUG1', '#BUG2', '#BUG3'],
        'unseen' => 3,
        'state' => 'new',
        'returned' => false,
    ])
        ->and(Carbon::parse($byClass['QueryException']['first_seen'])->toDateString())->toBe('2026-09-21')
        ->and(Carbon::parse($byClass['QueryException']['last_seen'])->toDateString())->toBe('2026-09-26')
        ->and(strlen($byClass['QueryException']['key']))->toBe(10);

    // seen in Boogle or not, it is the same bug; what names a record is not the route
    expect($byClass['ErrorException']['codes'])->toBe(['#BUG4', '#BUG7'])
        ->and($byClass['ErrorException']['unseen'])->toBe(1)
        ->and($byClass['ErrorException']['request'])->toBe('GET /cards/{id}')
        ->and($byClass['TransportException']['where'])->toBe('vendor/symfony/mailer/Transport/Smtp/Stream/SocketStream.php:154')
        ->and($byClass['TransportException']['in_vendor'])->toBeTrue()
        ->and($byClass['ProjectOfflineException']['kind'])->toBe('outage')
        ->and($byClass['ProjectOfflineException']['where'])->toBeNull()
        ->and($byClass['ProjectOfflineException']['request'])->toBeNull();

    // a day for each of the last fourteen, the oldest first
    expect(count($errors['days']))->toBe(14)
        ->and($errors['days'][0])->toBe(['date' => '2026-09-14', 'count' => 0])
        ->and($errors['days'][13]['date'])->toBe('2026-09-27')
        ->and(array_sum(array_column($errors['days'], 'count')))->toBe(6)
        ->and(collect($errors['days'])->firstWhere('date', '2026-09-26')['count'])->toBe(1);

    expectNothingPersonal((string) json_encode($errors), 'what was read');
    expectNothingPersonal((string) json_encode(Cache::get((fn () => $this->cacheKey())->call(app(BoogleService::class)))), 'the cache');

    expect(array_column(app(BoogleService::class)->errors(['kind' => 'outage'], false)['errors'], 'short'))->toBe(['ProjectOfflineException'])
        ->and(array_column(app(BoogleService::class)->errors(['kind' => 'error', 'limit' => 2], false)['errors'], 'short'))->toBe(['QueryException', 'ErrorException']);

    expect(fn () => app(BoogleService::class)->errors(['kind' => 'warning'], false))->toThrow(InvalidArgumentException::class, 'Unknown kind');
});

it('reads a file of the server as a file of the repository', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableBoogle();

    if (! is_dir(base_path('modules/billing'))) {
        mkdir(base_path('modules/billing'), 0755, true);
    }

    file_put_contents(base_path('modules/billing/Invoice.php'), '<?php');

    try {
        fakeBoogle(['OPEN' => [
            // a file of this checkout: the longest end of the path that is here
            boogleRow(1, ['exception' => 'A', 'file' => '/var/www/app/current/modules/billing/Invoice.php', 'line' => 3]),
            // not here: cut at the first folder an application has
            boogleRow(2, ['exception' => 'B', 'file' => 'C:\\inetpub\\site\\routes\\web.php', 'line' => 9]),
            boogleRow(3, ['exception' => 'C', 'file' => '/srv/site/vendor/acme/toolkit/src/Report.php', 'line' => 1]),
            // a path that climbs is not followed
            boogleRow(4, ['exception' => 'D', 'file' => '/srv/site/app/../../../etc/passwd', 'line' => 1]),
            boogleRow(5, ['exception' => 'E', 'file' => '/opt/thing/Runner.php', 'line' => 7]),
            boogleRow(6, ['exception' => 'F', 'file' => '', 'line' => 0]),
        ]]);

        $where = collect(app(BoogleService::class)->errors()['errors'])->pluck('where', 'class')->all();
        ksort($where);

        expect($where)->toBe([
            'A' => 'modules/billing/Invoice.php:3',
            'B' => 'routes/web.php:9',
            'C' => 'vendor/acme/toolkit/src/Report.php:1',
            'D' => 'passwd:1',
            'E' => 'Runner.php:7',
            'F' => null,
        ]);
    } finally {
        unlink(base_path('modules/billing/Invoice.php'));
        rmdir(base_path('modules/billing'));
        rmdir(base_path('modules'));
    }
});

it('masks what a message and a path say about a person', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableBoogle();
    fakeBoogle(['OPEN' => [
        boogleRow(1, [
            'exception' => 'RuntimeException',
            'error' => "Mail to anna.bianchi+shop@example.co.uk failed.\nToken eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9abcdefghijklmnop was refused.  ".str_repeat('x ', 400),
            'http' => ['method' => 'get', 'fullUrl' => 'https://loyalty.example.com/reset/9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08/anna@example.com?email=anna@example.com'],
        ]),
    ]]);

    $error = app(BoogleService::class)->errors()['errors'][0];

    expect($error['message'])->toStartWith('Mail to [address] failed. Token [secret] was refused. x x')
        ->and(mb_strlen($error['message']))->toBe(401)
        ->and($error['message'])->toEndWith('…')
        ->and($error['request'])->toBe('GET /reset/{token}/{address}');
});

it('reads every page Boogle gives', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableBoogle();
    Http::swap(new Factory);

    Http::fake([
        'boogle.example.com/api/admin/projects?*' => Http::response(['current_page' => 1, 'last_page' => 1, 'data' => [
            ['id' => BOOGLE_PROJECT, 'title' => 'Loyalty', 'url' => null, 'uptime_enabled' => false, 'group' => null, 'key' => 'LOYALTY_KEY', 'api_token' => 'LOYALTY_INGEST_TOKEN'],
        ]]),
        'boogle.example.com/api/admin/projects/'.BOOGLE_PROJECT.'/exceptions?status=OPEN&page=1' => Http::response(['current_page' => 1, 'last_page' => 2, 'data' => [boogleRow(1), boogleRow(2)]]),
        'boogle.example.com/api/admin/projects/'.BOOGLE_PROJECT.'/exceptions?status=OPEN&page=2' => Http::response(['current_page' => 2, 'last_page' => 2, 'data' => [boogleRow(3)]]),
        'boogle.example.com/api/admin/projects/'.BOOGLE_PROJECT.'/exceptions?status=READ*' => Http::response(['current_page' => 1, 'last_page' => 1, 'data' => []]),
    ]);

    $errors = app(BoogleService::class)->errors();

    expect($errors['errors'][0]['count'])->toBe(3)
        ->and($errors['truncated'])->toBeFalse()
        ->and($errors['project']['uptime'])->toBeFalse();
});

it('records the spec that fixes a bug, and the reason one is left as it is', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableBoogle();
    fakeBoogle();
    addSpec(['code' => 'US-001', 'title' => 'Skip a customer that exists on import']);

    $this->artisan('larapilot:errors-list')->assertSuccessful();

    // any code of the bug names it, with or without the hash
    $this->artisan('larapilot:errors-link', ['errors' => 'bug2', '--spec' => 'US-001'])->assertSuccessful();
    $this->artisan('larapilot:errors-link', [
        'errors' => '#BUG5',
        '--ignore' => true,
        '--reason' => 'The mail provider was down on its side; the queue sent every mail later.',
    ])->assertSuccessful();

    $ledger = Yaml::parseFile(base_path('.larapilot/boogle.yaml'));
    $keys = collect(app(BoogleService::class)->errors()['errors'])->pluck('key', 'short');

    expect($ledger['errors'][$keys['QueryException']])->toMatchArray([
        'kind' => 'error',
        'class' => 'Illuminate\\Database\\QueryException',
        'where' => 'app/Services/CustomerImporter.php:88',
        'codes' => ['#BUG1', '#BUG2', '#BUG3'],
        'state' => 'in_backlog',
        'spec' => 'US-001',
    ])
        ->and($ledger['errors'][$keys['TransportException']]['state'])->toBe('ignored')
        ->and($ledger['errors'][$keys['TransportException']]['reason'])->toBe('The mail provider was down on its side; the queue sent every mail later.')
        ->and($ledger['project'])->toBe(['id' => BOOGLE_PROJECT, 'title' => 'Loyalty']);

    // the decision, never the message, the person, or a secret
    $file = (string) file_get_contents(base_path('.larapilot/boogle.yaml'));

    expectNothingPersonal($file, 'the ledger');
    expect($file)->not->toContain('SQLSTATE')->not->toContain('[address]')->not->toContain('/admin/customers/import');

    $errors = app(BoogleService::class)->errors();
    $byClass = collect($errors['errors'])->keyBy('short');

    expect($byClass['QueryException']['state'])->toBe('in_backlog')
        ->and($byClass['QueryException']['spec'])->toBe('US-001')
        ->and($byClass['QueryException']['spec_status'])->toBe('TODO')
        ->and($byClass['TransportException']['state'])->toBe('ignored')
        ->and($byClass['ErrorException']['state'])->toBe('new')
        ->and($errors['counts']['new'])->toBe(1)
        ->and($errors['counts']['in_backlog'])->toBe(1)
        ->and($errors['counts']['ignored'])->toBe(1)
        ->and($errors['summary'])->toStartWith('3 open errors, thrown 6 times: 1 with no decision yet.')
        ->and(array_column(app(BoogleService::class)->errors(['new' => true, 'kind' => 'error'])['errors'], 'short'))->toBe(['ErrorException']);

    // the bug is thrown again tomorrow, under a code nobody has seen: the decision holds
    $rows = boogleRows();
    $rows['OPEN'][] = boogleRow(40, ['created_at' => '2026-09-27T09:00:00Z']);
    fakeBoogle($rows);

    $again = collect(app(BoogleService::class)->errors()['errors'])->keyBy('short');

    expect($again['QueryException']['count'])->toBe(4)
        ->and($again['QueryException']['codes'])->toContain('#BUG40')
        ->and($again['QueryException']['state'])->toBe('in_backlog');

    // by its key, which is all that names a bug in the ledger
    $this->artisan('larapilot:errors-link', ['errors' => $keys['QueryException'].',BUG5', '--forget' => true])->assertSuccessful();

    expect(app(BoogleService::class)->errors()['counts']['new'])->toBe(3)
        ->and(Yaml::parseFile(base_path('.larapilot/boogle.yaml'))['errors'])->toBe([]);
});

it('refuses a decision that says nothing', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableBoogle();
    fakeBoogle();
    addSpec(['code' => 'US-001', 'title' => 'Skip a customer that exists on import']);

    // nothing said, or two things at once
    $this->artisan('larapilot:errors-link', ['errors' => 'BUG1'])->assertExitCode(2);
    $this->artisan('larapilot:errors-link', ['errors' => 'BUG1', '--spec' => 'US-001', '--forget' => true])->assertExitCode(2);
    $this->artisan('larapilot:errors-link', ['errors' => 'BUG1', '--spec' => '../etc'])->assertExitCode(2);
    // a reason that is not one
    $this->artisan('larapilot:errors-link', ['errors' => 'BUG1', '--ignore' => true, '--reason' => 'noise'])->assertExitCode(2);
    $this->artisan('larapilot:errors-link', ['errors' => 'BUG1', '--ignore' => true])->assertExitCode(2);
    // a name that is not a code
    $this->artisan('larapilot:errors-link', ['errors' => '../../etc', '--spec' => 'US-001'])->assertExitCode(2);
    $this->artisan('larapilot:errors-link', ['errors' => ' , ', '--spec' => 'US-001'])->assertExitCode(2);
    // a code Boogle does not hold, a spec the backlog does not have
    $this->artisan('larapilot:errors-link', ['errors' => 'BUG999', '--spec' => 'US-001'])
        ->assertExitCode(4)
        ->expectsOutputToContain('Boogle holds no open error BUG999 for this project.');
    $this->artisan('larapilot:errors-link', ['errors' => 'BUG1', '--spec' => 'US-099'])->assertExitCode(4);
    $this->artisan('larapilot:errors-resolve', ['errors' => 'BUG1', '--status' => 'OPEN'])->assertExitCode(2);

    expect(is_file(base_path('.larapilot/boogle.yaml')))->toBeFalse();

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'PATCH');
});

it('closes a bug in Boogle when it is asked to, and says when it comes back', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableBoogle();
    fakeBoogle();
    addSpec(['code' => 'US-001', 'title' => 'Skip a customer that exists on import']);

    $this->artisan('larapilot:errors-link', ['errors' => 'BUG1', '--spec' => 'US-001'])->assertSuccessful();

    // linking writes nothing to Boogle
    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'PATCH');

    Artisan::call('larapilot:errors-resolve', ['errors' => 'BUG1']);
    $result = boogleEnvelope();

    expect($result['kind'])->toBe('errors_resolve')
        ->and($result['data']['closed'])->toBe(['#BUG3', '#BUG2', '#BUG1'])
        ->and($result['data']['status'])->toBe('FIXED')
        ->and($result['data']['project'])->toBe('Loyalty');

    // every time it was thrown, with what fixed it
    foreach ([1, 2, 3] as $number) {
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
            && $request->url() === 'https://boogle.example.com/api/admin/projects/'.BOOGLE_PROJECT.'/exceptions/'.sprintf('%08d-0000-4000-8000-%012d', $number, $number).'/status'
            && $request['status'] === 'FIXED'
            && $request['comment'] === 'Fixed by US-001.'
            && $request->hasHeader('Authorization', 'Bearer admin-token'));
    }

    Http::assertSentCount(3 + 3); // projects, open, read — then the three that were closed

    $key = array_key_first(Yaml::parseFile(base_path('.larapilot/boogle.yaml'))['errors']);

    expect(Yaml::parseFile(base_path('.larapilot/boogle.yaml'))['errors'][$key]['resolved_at'])->toBe(now()->toIso8601String());

    // Boogle holds it no more: it is said, not lost
    $rows = boogleRows();
    $rows['OPEN'] = array_values(array_filter($rows['OPEN'], static fn (array $row): bool => $row['exception'] !== 'Illuminate\\Database\\QueryException'));
    fakeBoogle($rows);

    $errors = app(BoogleService::class)->errors();

    expect(array_column($errors['errors'], 'short'))->not->toContain('QueryException')
        ->and($errors['closed'])->toHaveCount(1)
        ->and($errors['closed'][0])->toMatchArray(['key' => $key, 'class' => 'Illuminate\\Database\\QueryException', 'spec' => 'US-001', 'state' => 'in_backlog']);

    // thrown again after the fix was released: the fix did not hold
    Carbon::setTestNow('2026-09-30 09:00:00');
    $rows['OPEN'][] = boogleRow(50, ['created_at' => '2026-09-29T18:00:00Z']);
    fakeBoogle($rows);

    $errors = app(BoogleService::class)->errors();
    $back = collect($errors['errors'])->firstWhere('short', 'QueryException');

    expect($back['returned'])->toBeTrue()
        ->and($back['state'])->toBe('in_backlog')
        ->and($back['codes'])->toBe(['#BUG50'])
        ->and($errors['counts']['returned'])->toBe(1)
        ->and($errors['summary'])->toContain('1 came back after its fix.')
        // it is to be looked at again, whatever was decided
        ->and(array_column(app(BoogleService::class)->errors(['new' => true, 'kind' => 'error'], false)['errors'], 'short'))->toContain('QueryException');

    // a comment of one's own, and the other word Boogle has for closed
    Artisan::call('larapilot:errors-resolve', ['errors' => 'BUG50', '--status' => 'done', '--comment' => 'Closed after the hotfix of Tuesday.']);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
        && $request['status'] === 'DONE'
        && $request['comment'] === 'Closed after the hotfix of Tuesday.');
});

it('writes the errors as a document of the project', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableBoogle();
    fakeBoogle();
    addSpec(['code' => 'US-001', 'title' => 'Skip a customer that exists on import']);

    $this->artisan('larapilot:errors-link', ['errors' => 'BUG1', '--spec' => 'US-001'])->assertSuccessful();

    // the list is narrowed, the report is about every error
    Artisan::call('larapilot:errors-list', ['--new' => true, '--kind' => 'error', '--limit' => 1, '--report' => true]);
    $envelope = boogleEnvelope();

    expect($envelope['kind'])->toBe('errors_list')
        ->and($envelope['data']['report'])->toBe('.larapilot/docs/support/errors.md')
        ->and(array_column($envelope['data']['errors'], 'short'))->toBe(['ErrorException'])
        ->and($envelope['data']['counts']['errors'])->toBe(3)
        // what an agent reads is kept short
        ->and($envelope['data'])->not->toHaveKey('days')
        ->and($envelope['data']['errors'][0])->not->toHaveKey('occurrences');

    expectNothingPersonal((string) json_encode($envelope), 'the output of the command');

    $report = (string) file_get_contents(base_path('.larapilot/docs/support/errors.md'));

    expect($report)->toStartWith("# Boogle errors — Loyalty\n")
        ->toContain('**In short:** 3 open errors, thrown 6 times: 2 with no decision yet.')
        ->toContain('| 3 | 6 | 2 | 1 | 0 | 0 | 1 |')
        ->toContain('## Errors (3)')
        ->toContain('### #BUG1, #BUG2, #BUG3 — Illuminate\\Database\\QueryException')
        ->toContain('- **Decision:** in the backlog as US-001 (TODO)')
        ->toContain('- **Thrown:** 3 times, first 2026-09-21 10:00, last 2026-09-26 10:00')
        ->toContain('- **Where:** `app/Services/CustomerImporter.php:88`')
        ->toContain('- **Where:** `vendor/symfony/mailer/Transport/Smtp/Stream/SocketStream.php:154` (in a package)')
        ->toContain('- **Request:** `GET /cards/{id}`')
        ->toContain('- **Decision:** none yet')
        ->toContain("> SQLSTATE[23000]: Duplicate entry '[address]' for key 'customers_email_unique'")
        ->toContain('## Outages (1)')
        ->toContain('The user, the query string, and the payload of a request stay in Boogle.');

    expectNothingPersonal($report, 'the report');
});

it('says what is wrong when Boogle cannot be read', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableBoogle();

    foreach ([
        [401, 'Boogle refused the token — Unauthenticated.', 'LARAPILOT_BOOGLE_TOKEN', false],
        [403, 'The user of this token is not an admin of Boogle', 'admin users only', false],
        [404, 'Boogle does not know this', 'LARAPILOT_BOOGLE_PROJECT', true],
        [429, 'Boogle asked to slow down', 'Wait a minute', true],
        [500, 'Boogle answered 500', '', true],
    ] as [$code, $message, $hint, $authenticated]) {
        Http::swap(new Factory);
        Http::fake(['boogle.example.com/*' => Http::response(['message' => $code === 401 ? 'Unauthenticated.' : ''], $code)]);

        $status = app(BoogleService::class)->status();

        expect($status['ready'])->toBeFalse()
            ->and($status['error'])->toContain($message)
            ->and($status['authenticated'])->toBe($authenticated)
            ->and($status['hints'][0])->toContain($hint);

        $this->artisan('larapilot:errors-list')->assertExitCode(3)->expectsOutputToContain($message);
    }

    Http::swap(new Factory);
    Http::fake(fn () => throw new ConnectionException('timed out'));

    $status = app(BoogleService::class)->status();

    expect($status['error'])->toBe('Boogle could not be reached at https://boogle.example.com.')
        ->and($status['authenticated'])->toBeFalse();

    // no address, no token: said before anything is asked
    Http::swap(new Factory);
    Http::fake();
    config()->set('larapilot.boogle.token', '');

    $this->artisan('larapilot:errors-list')->assertExitCode(3)->expectsOutputToContain('The Boogle token is not set.');

    config()->set('larapilot.boogle.token', 'admin-token');
    config()->set('larapilot.boogle.url', '');

    $this->artisan('larapilot:errors-list')->assertExitCode(3)->expectsOutputToContain('The address of Boogle is not set.');

    Http::assertNothingSent();
});

it('lets an agent read Boogle over MCP and nothing more', function (): void {
    $allowed = (new ReflectionClass(RunArtisanTool::class))->getDefaultProperties()['allowed'];

    expect($allowed)->toContain('larapilot:errors-status', 'larapilot:errors-list', 'larapilot:errors-plan')
        ->toContain('larapilot:boogle-status', 'larapilot:boogle-errors', 'larapilot:boogle-plan')
        ->not->toContain('larapilot:errors-link')
        ->not->toContain('larapilot:errors-resolve')
        ->not->toContain('larapilot:boogle-link')
        ->not->toContain('larapilot:boogle-resolve');
});

it('still answers to the names the commands had when Boogle was the only tracker', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableBoogle();
    fakeBoogle();
    addSpec(['code' => 'US-001', 'title' => 'Skip a customer that exists on import']);

    $commands = Artisan::all();

    foreach ([
        'larapilot:boogle-status' => 'larapilot:errors-status',
        'larapilot:boogle-errors' => 'larapilot:errors-list',
        'larapilot:boogle-plan' => 'larapilot:errors-plan',
        'larapilot:boogle-link' => 'larapilot:errors-link',
        'larapilot:boogle-resolve' => 'larapilot:errors-resolve',
    ] as $old => $new) {
        expect($commands[$old] ?? null)->toBe($commands[$new]);
    }

    Artisan::call('larapilot:boogle-status');
    expect(boogleEnvelope()['kind'])->toBe('errors_status');

    Artisan::call('larapilot:boogle-errors', ['--new' => true]);
    expect(boogleEnvelope()['kind'])->toBe('errors_list');

    Artisan::call('larapilot:boogle-plan', ['--codes' => 'BUG1']);
    expect(boogleEnvelope()['kind'])->toBe('errors_plan');

    Artisan::call('larapilot:boogle-link', ['errors' => 'BUG1', '--spec' => 'US-001']);
    expect(boogleEnvelope()['kind'])->toBe('errors_link');
});

it('groups confirmed error codes by kind and place for triage', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableBoogle();

    $rows = boogleRows();
    $rows['OPEN'][] = boogleRow(8, [
        'exception' => 'ErrorException',
        'error' => 'Undefined index in report',
        'file' => '/home/forge/loyalty.example.com/releases/20260920/app/Services/ReportBuilder.php',
        'line' => 10,
        'http' => ['method' => 'GET', 'url' => '/admin/reports/daily'],
        'created_at' => '2026-09-24T09:00:00Z',
    ]);
    $rows['OPEN'][] = boogleRow(9, [
        'exception' => 'ErrorException',
        'error' => 'Undefined index in export',
        'file' => '/home/forge/loyalty.example.com/releases/20260920/app/Services/ReportExporter.php',
        'line' => 22,
        'http' => ['method' => 'GET', 'url' => '/admin/reports/export'],
        'created_at' => '2026-09-23T09:00:00Z',
    ]);

    fakeBoogle($rows);
    app(BoogleService::class)->errors();

    $plan = app(BoogleService::class)->resolutionPlan(['BUG8', 'BUG9', 'BUG4']);

    expect($plan['codes'])->toContain('#BUG8', '#BUG4')
        ->and($plan['groups'])->toHaveCount(2);

    $reports = collect($plan['groups'])->first(
        static fn (array $group): bool => in_array('#BUG8', $group['codes'], true)
    );

    expect($reports)->not->toBeNull()
        ->and($reports['codes'])->toEqualCanonicalizing(['#BUG8', '#BUG9']);

    Artisan::call('larapilot:errors-plan', ['--codes' => 'BUG8,BUG9,BUG4']);
    $payload = boogleEnvelope();

    expect($payload['kind'])->toBe('errors_plan');

    $merged = collect($payload['data']['groups'])->first(
        static fn (array $group): bool => in_array('#BUG8', $group['codes'], true)
    );

    expect($merged)->not->toBeNull()
        ->and($merged['codes'])->toEqualCanonicalizing(['#BUG8', '#BUG9']);
});

it('shows the errors on the dashboard, with what was decided', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    // off: Boogle first, other trackers listed second
    $this->get('/larapilot/errors')
        ->assertOk()
        ->assertSee('Boogle records the exceptions the running application throws', false)
        ->assertSee('https://boogle.web.ap.it/', false)
        ->assertSee('Use <a href="https://boogle.web.ap.it/">Boogle</a> for this project', false)
        ->assertSee('andreapollastri/boogle-client', false)
        ->assertSee('php artisan larapilot:settings-set --boogle=YES', false)
        ->assertSee('Other trackers you can use instead', false)
        ->assertSee('https://flareapp.io/', false)
        ->assertSee('https://www.datadoghq.com/', false)
        ->assertSee('--errors-provider=sentry', false)
        ->assertDontSee('Boogle is off for this project', false)
        ->assertDontSee('Read again', false);

    $this->get('/larapilot')->assertOk()->assertSee('href="'.url('/larapilot/errors').'"', false);
    $this->get('/larapilot/errors/boogle.md')->assertNotFound();

    enableBoogle();
    fakeBoogle();
    addSpec(['code' => 'US-001', 'title' => 'Skip a customer that exists on import']);

    $this->artisan('larapilot:errors-link', ['errors' => 'BUG1', '--spec' => 'US-001'])->assertSuccessful();
    $this->artisan('larapilot:errors-link', ['errors' => 'BUG5', '--ignore' => true, '--reason' => 'The mail provider was down on its side for an hour.'])->assertSuccessful();

    $this->get('/larapilot')->assertOk()->assertSee('href="'.url('/larapilot/errors').'"', false);

    $html = $this->get('/larapilot/errors')
        ->assertOk()
        ->assertSee('<span class="chip current">Loyalty</span>', false)
        ->assertSee('Uptime watched', false)
        ->assertSee('There are errors nobody decided about', false)
        ->assertSee('3 open errors, thrown 6 times: 1 with no decision yet.', false)
        ->assertSee('Errors thrown, day by day', false)
        ->assertSee('style="--days: 14"', false)
        ->assertSee('The same numbers as a table', false)
        ->assertSee('QueryException: SQLSTATE[23000]: Duplicate entry &#039;[address]&#039;', false)
        ->assertSee('<code>app/Services/CustomerImporter.php:88</code>', false)
        ->assertSee('<code>POST /admin/customers/import</code>', false)
        ->assertSee('In the backlog as <a href="'.url('/larapilot/specs/US-001').'">US-001</a>, now TODO.', false)
        ->assertSee('php artisan larapilot:errors-resolve BUG1', false)
        ->assertSee('Left as it is: The mail provider was down on its side for an hour.', false)
        ->assertSee('in a package, called by the application', false)
        ->assertSee('None yet. Run <code>/larapilot-error</code> to hand it to triage.', false)
        ->assertSee('<code>GET /cards/{id}</code>', false)
        ->assertSee('Uptime check failed with HTTP status 503.', false)
        ->assertSee('The user, the query string, and the payload of a request stay in Boogle', false)
        ->assertSee('Download report (.md)', false)
        ->getContent();

    expectNothingPersonal((string) $html, 'the page');

    // what was read is kept for a few minutes
    $sent = count(Http::recorded());
    $this->get('/larapilot/errors')->assertOk();
    expect(count(Http::recorded()))->toBe($sent);

    $this->get('/larapilot/errors?refresh=1')->assertOk();
    expect(count(Http::recorded()))->toBe($sent + 3);

    $download = $this->get('/larapilot/errors/boogle.md')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

    expect($download->headers->get('Content-Disposition'))->toBe('attachment; filename="boogle-errors-2026-09-27.md"')
        ->and($download->getContent())->toStartWith("# Boogle errors — Loyalty\n");

    $this->get('/larapilot/errors/errors.md')
        ->assertOk();

    // nothing open
    fakeBoogle(['OPEN' => [], 'READ' => []]);

    $this->get('/larapilot/errors?refresh=1')
        ->assertOk()
        ->assertSee('Boogle holds no open error for <strong>Loyalty</strong>.', false)
        ->assertSee('No longer open in Boogle', false)
        ->assertSee('fixed by US-001', false)
        ->assertSee('was left as it was', false)
        ->assertDontSee('Errors thrown, day by day', false);

    config()->set('larapilot.dashboard_route.enabled', false);

    $this->get('/larapilot/errors')->assertNotFound();
    $this->get('/larapilot/errors/boogle.md')->assertNotFound();
});

it('says on the page what is wrong when Boogle cannot be read', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableBoogle();
    Http::swap(new Factory);
    Http::fake(['boogle.example.com/*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

    $this->get('/larapilot/errors')
        ->assertOk()
        ->assertSee('Boogle refused the token', false)
        ->assertSee('What to check', false)
        ->assertSee('php artisan larapilot:errors-status', false)
        ->assertDontSee('admin-token', false);

    $this->get('/larapilot/errors/boogle.md')->assertNotFound();

    // the rest of the dashboard does not depend on Boogle
    $this->get('/larapilot')->assertOk();
});

it('ships a skill that asks for the tracker, downloads the errors, and hands them to triage', function (): void {
    $root = dirname(__DIR__, 2).'/resources';
    $skill = (string) file_get_contents($root.'/boost/skills/larapilot-error/SKILL.md');
    $triage = (string) file_get_contents($root.'/boost/skills/larapilot-triage/SKILL.md');
    $bug = (string) file_get_contents($root.'/boost/skills/larapilot-bug/SKILL.md');
    $ship = (string) file_get_contents($root.'/boost/skills/larapilot-ship/SKILL.md');
    $settings = (string) file_get_contents($root.'/boost/skills/larapilot-settings/SKILL.md');

    // One skill for every tracker: the one named after Boogle is gone.
    expect($root.'/boost/skills/larapilot-boogle')->not->toBeDirectory();

    expect($skill)->toStartWith("---\nname: larapilot-error\n")
        ->toContain('### 0. Tracker (Matt)')
        ->toContain('`Which tracker records the errors of production? (current: {errors_provider or none})`')
        ->toContain('**Never choose the tracker yourself.**')
        ->toContain('php artisan larapilot:settings-set --errors=YES --errors-provider={id}')
        ->toContain('php artisan larapilot:errors-status')
        ->toContain('php artisan larapilot:errors-list --new --kind=error --report')
        ->toContain('php artisan larapilot:errors-plan --codes=BUG12,BUG21')
        ->toContain('errors-plan --codes=')
        ->toContain('errors-link {codes} --spec={spec}')
        ->toContain('activate `larapilot-triage`')
        ->toContain('**in this same turn**')
        ->toContain("```text\nProduction error\n")
        ->toContain('**Never ignore on your own.**')
        ->toContain('Never call the API of a tracker yourself')
        ->toContain('remote_resolve: true')
        ->toContain('never echo it in chat')
        ->toContain('`errors-resolve` **writes to the remote tracker**')
        ->toContain('**Never ask the tracker, the user, or the logs for who the person was.**')
        ->toContain('`in_backlog` is not fixed')
        ->not->toContain('boogle-')
        ->and(strlen($skill))->toBeLessThanOrEqual(10500);

    // The tracker question offers every tracker Larapilot reads, Boogle included.
    foreach (app(ConfigService::class)->allowedErrorsProviders() as $provider) {
        expect($skill)->toContain("| `{$provider}` |");
    }

    // Every command the skill names exists.
    preg_match_all('/larapilot:([a-z0-9-]+)/', $skill, $matches);

    foreach (array_unique($matches[1]) as $command) {
        expect(array_key_exists('larapilot:'.$command, Artisan::all()))->toBeTrue($command);
    }

    expect($triage)->toContain('## Handoff from `larapilot-error`')
        ->and(strlen($triage))->toBeLessThanOrEqual(9000)
        ->and($bug)->toContain('A **Production error** block under `evidence`')
        ->toContain('Never ask who the user of the request was.')
        ->and($ship)->toContain('php artisan larapilot:errors-list --new --kind=error')
        ->and($settings)->toContain('**7f. Production errors**')
        ->toContain('--errors-provider=')
        ->toContain('--errors=NO');

    // No file the package ships names the old skill any more.
    foreach ([
        '/boost/skills/larapilot-triage/SKILL.md',
        '/boost/skills/larapilot-bug/SKILL.md',
        '/boost/skills/larapilot-ship/SKILL.md',
        '/boost/skills/larapilot-settings/SKILL.md',
        '/boost/guidelines/core.blade.php',
        '/larapilot/shared-runtime.md',
        '/larapilot/runtime-core-economy.md',
        '/larapilot/runtime-core-settings-2.md',
        '/larapilot/integrations.md',
        '/views/dashboard/errors.blade.php',
        '/views/dashboard/docs.blade.php',
        '/views/dashboard/settings.blade.php',
    ] as $file) {
        expect((string) file_get_contents($root.$file))->not->toContain('larapilot-boogle', $file);
    }

    expect((string) file_get_contents($root.'/larapilot/runtime-core-settings-2.md'))->toContain('### Production errors (`settings.errors`')
        ->toContain('larapilot:errors-plan')
        ->and((string) file_get_contents($root.'/larapilot/shared-runtime.md'))->toContain('`larapilot-aikido` and `larapilot-error` do the same.')
        ->and((string) file_get_contents($root.'/larapilot/runtime-core-economy.md'))->toContain('**`larapilot-error`**')
        ->and((string) file_get_contents($root.'/larapilot/integrations.md'))->toContain('## Production errors (`settings.errors`')
        ->toContain('## Boogle (`errors_provider: boogle`)')
        ->toContain('## Sentry (`errors_provider: sentry`)')
        ->toContain('## Bugsnag (`errors_provider: bugsnag`)')
        ->toContain('## Flare (`errors_provider: flare`)')
        ->toContain('## Datadog (`errors_provider: datadog`)')
        ->toContain('## Rollbar (`errors_provider: rollbar`)')
        ->toContain('## Honeybadger (`errors_provider: honeybadger`)')
        ->toContain('## AWS CloudWatch Logs (`errors_provider: cloudwatch`)')
        ->not->toContain('nightwatch')
        ->and((string) file_get_contents($root.'/boost/guidelines/core.blade.php'))->toContain('`larapilot-error`');

    $this->artisan('larapilot:install')->assertSuccessful();

    $this->get('/larapilot/skills/larapilot-error')
        ->assertOk()
        ->assertSee('Larapilot — Production errors', false)
        ->assertSee('Ships with Larapilot', false);

    $this->get('/larapilot/skills/larapilot-boogle')->assertNotFound();
});
