<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Larapilot\Services\BoogleService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\Errors\ErrorTrackerManager;
use Symfony\Component\Yaml\Yaml;

/**
 * The trackers besides Boogle, each answering as its API does. What is
 * read from every one of them is the same shape, and a person is never
 * in it.
 */
function enableErrorsProvider(string $provider): void
{
    test()->artisan('larapilot:settings-set', ['--errors' => 'YES', '--errors-provider' => $provider])->assertSuccessful();
}

/**
 * @return array<string, mixed>
 */
function errorsEnvelope(): array
{
    $decoded = json_decode(trim(Artisan::output()), true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * @param  array<string, mixed>  $responses
 */
function fakeTracker(array $responses): void
{
    Http::swap(new Factory);
    Http::fake($responses);
}

/**
 * `config.yaml` written by hand, as a person would, and read again: the
 * service keeps what it read until it writes.
 *
 * @param  callable(array<string, mixed>): array<string, mixed>  $change
 */
function rewriteSettingsByHand(callable $change): void
{
    $path = base_path('.larapilot/config.yaml');
    $config = Yaml::parseFile($path);
    $config['settings'] = $change($config['settings']);
    file_put_contents($path, Yaml::dump($config, 4, 2));

    (fn () => $this->resolved = null)->call(app(ConfigService::class));
}

it('lists the trackers Larapilot can read, Boogle first', function (): void {
    $providers = ['boogle', 'sentry', 'bugsnag', 'flare', 'datadog', 'rollbar', 'honeybadger', 'cloudwatch'];

    expect(app(ConfigService::class)->allowedErrorsProviders())->toBe($providers)
        ->and(app(ErrorTrackerManager::class)->available())->toBe($providers)
        ->and($providers)->not->toContain('nightwatch');
});

it('turns the errors on with a tracker, and keeps the old boogle flag in step', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $config = app(ConfigService::class);
    $written = static fn (): array => Yaml::parseFile(base_path('.larapilot/config.yaml'))['settings'];

    expect($config->errorsEnabled())->toBeFalse()
        ->and($config->errorsProvider())->toBe('boogle')
        ->and($config->settings()['errors_provider'])->toBe('');

    enableErrorsProvider('sentry');

    expect($config->settings())->toMatchArray(['errors' => 'YES', 'errors_provider' => 'sentry', 'boogle' => 'NO'])
        ->and($config->errorsProvider())->toBe('sentry')
        ->and($written()['boogle'])->toBeFalse();

    // The old flag names Boogle as the tracker.
    $this->artisan('larapilot:settings-set', ['--boogle' => 'YES'])->assertSuccessful();

    expect($config->settings())->toMatchArray(['errors' => 'YES', 'errors_provider' => 'boogle', 'boogle' => 'YES']);

    // Another tracker takes its place: the old flag says so.
    $this->artisan('larapilot:settings-set', ['--errors-provider' => 'flare'])->assertSuccessful();

    expect($config->settings())->toMatchArray(['errors' => 'YES', 'errors_provider' => 'flare', 'boogle' => 'NO']);

    // Turning the old flag off means nothing while Flare is the tracker…
    $this->artisan('larapilot:settings-set', ['--boogle' => 'NO'])->assertSuccessful();

    expect($config->settings())->toMatchArray(['errors' => 'YES', 'errors_provider' => 'flare', 'boogle' => 'NO']);

    // …and turns the errors off when Boogle is.
    $this->artisan('larapilot:settings-set', ['--errors-provider' => 'boogle'])->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--boogle' => 'NO'])->assertSuccessful();

    expect($config->settings())->toMatchArray(['errors' => 'NO', 'errors_provider' => 'boogle', 'boogle' => 'NO'])
        ->and($config->errorsEnabled())->toBeFalse();

    // Off keeps the choice, so turning it on again does not ask for it.
    $this->artisan('larapilot:settings-set', ['--errors' => 'YES'])->assertSuccessful();

    expect($config->settings())->toMatchArray(['errors' => 'YES', 'errors_provider' => 'boogle', 'boogle' => 'YES']);

    $this->artisan('larapilot:settings-set', ['--errors-provider' => 'newrelic'])
        ->assertExitCode(2)
        ->expectsOutputToContain('Invalid --errors-provider value: newrelic');

    $this->artisan('larapilot:settings-set', ['--errors-provider' => 'Sentry'])->assertSuccessful();

    expect($config->settings()['errors_provider'])->toBe('sentry');
});

it('reads a project that turned Boogle on before the other trackers existed', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $path = base_path('.larapilot/config.yaml');

    rewriteSettingsByHand(static function (array $settings): array {
        unset($settings['errors'], $settings['errors_provider']);

        return ['boogle' => true] + $settings;
    });

    $service = app(ConfigService::class);

    // What an agent reads says the same as a file written today.
    expect($service->errorsEnabled())->toBeTrue()
        ->and($service->errorsProvider())->toBe('boogle')
        ->and($service->settings())->toMatchArray(['errors' => 'YES', 'errors_provider' => 'boogle', 'boogle' => 'YES']);

    Artisan::call('larapilot:config-show', ['--only' => 'settings']);

    expect(errorsEnvelope()['data']['settings'])->toMatchArray(['errors' => 'YES', 'errors_provider' => 'boogle', 'boogle' => 'YES']);

    // A change to anything else brings the file to the new shape without
    // turning the errors off.
    $this->artisan('larapilot:settings-set', ['--effort' => 'MAX'])->assertSuccessful();

    $written = Yaml::parseFile($path)['settings'];

    expect($written)->toMatchArray(['errors' => true, 'errors_provider' => 'boogle', 'boogle' => true])
        ->and(app(ConfigService::class)->errorsEnabled())->toBeTrue();
});

it('shows the tracker among the settings of the dashboard', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableErrorsProvider('sentry');

    $this->get('/larapilot/settings')
        ->assertOk()
        ->assertSee('Production errors', false)
        ->assertSee('Error tracker', false)
        ->assertSee('errors_provider', false)
        ->assertSee('LARAPILOT_SENTRY_AUTH_TOKEN', false)
        ->assertSee('LARAPILOT_HONEYBADGER_AUTH_TOKEN', false)
        ->assertSee('Boogle (the old name)', false);
});

it('says what the tracker still needs, whether the errors are on or off', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    Http::fake();

    enableErrorsProvider('sentry');
    config()->set('larapilot.errors.sentry.token', null);
    config()->set('larapilot.errors.sentry.organization', 'acme');
    config()->set('larapilot.errors.sentry.project', null);

    $status = app(BoogleService::class)->status();

    expect($status['enabled'])->toBeTrue()
        ->and($status['provider'])->toBe('sentry')
        ->and($status['provider_label'])->toBe('Sentry')
        ->and($status['configured'])->toBeFalse()
        ->and($status['ready'])->toBeFalse()
        ->and($status['host'])->toBe('https://sentry.io')
        ->and($status['remote_resolve'])->toBeTrue()
        ->and($status['hints'])->toHaveCount(2)
        ->and($status['hints'][0])->toContain('LARAPILOT_SENTRY_AUTH_TOKEN')
        ->and($status['hints'][1])->toContain('LARAPILOT_SENTRY_PROJECT');

    $this->artisan('larapilot:settings-set', ['--errors' => 'NO'])->assertSuccessful();

    $status = app(BoogleService::class)->status();

    expect($status['enabled'])->toBeFalse()
        ->and($status['hints'][0])->toContain('larapilot:settings-set --errors=YES --errors-provider=sentry')
        ->and($status['hints'][1])->toContain('LARAPILOT_SENTRY_AUTH_TOKEN');

    $this->artisan('larapilot:boogle-errors')->assertExitCode(4)->expectsOutputToContain('Production errors are off for this project');

    // A tracker Larapilot does not know, written by hand.
    rewriteSettingsByHand(static fn (array $settings): array => ['errors' => true, 'errors_provider' => 'newrelic'] + $settings);

    $status = app(BoogleService::class)->status();

    expect($status['enabled'])->toBeTrue()
        ->and($status['provider'])->toBe('newrelic')
        ->and($status['provider_label'])->toBeNull()
        ->and($status['hints'][0])->toContain('Unknown errors provider "newrelic"');

    expect(Artisan::call('larapilot:boogle-errors'))->toBe(3)
        ->and(errorsEnvelope()['error'])->toMatchArray([
            'code' => 'E_CONNECTOR',
            'message' => 'Unknown errors provider "newrelic".',
        ]);

    Http::assertNothingSent();
});

it('proves the credentials of a tracker by asking it, and only while the errors are on', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    config()->set('larapilot.errors.sentry.token', 'sentry-token');
    config()->set('larapilot.errors.sentry.organization', 'acme');
    config()->set('larapilot.errors.sentry.project', 'shop');

    // Off: configured, and nothing is asked.
    fakeTracker(['sentry.io/*' => Http::response([])]);
    $this->artisan('larapilot:settings-set', ['--errors-provider' => 'sentry'])->assertSuccessful();

    $status = app(BoogleService::class)->status();

    expect($status['enabled'])->toBeFalse()
        ->and($status['configured'])->toBeTrue()
        ->and($status['authenticated'])->toBeFalse()
        ->and($status['ready'])->toBeFalse();

    Http::assertNothingSent();

    // On, with a token Sentry refuses.
    enableErrorsProvider('sentry');
    fakeTracker(['sentry.io/*' => Http::response(['detail' => 'Invalid token'], 401)]);

    $status = app(BoogleService::class)->status();

    expect($status['authenticated'])->toBeFalse()
        ->and($status['ready'])->toBeFalse()
        ->and($status['error'])->toBe('Sentry answered 401.')
        ->and($status['hints'][0])->toContain('Invalid token');

    // On, with one it takes: what was answered is kept for the read that follows.
    fakeTracker(['sentry.io/*' => Http::response([
        ['id' => '1', 'shortId' => 'SHOP-1', 'title' => 'TypeError', 'metadata' => ['type' => 'TypeError', 'value' => 'Unsupported operand types'], 'count' => '3', 'lastSeen' => '2026-09-27T10:00:00Z'],
    ])]);

    $status = app(BoogleService::class)->status();

    expect($status['authenticated'])->toBeTrue()
        ->and($status['ready'])->toBeTrue()
        ->and($status['project']['title'])->toBe('shop')
        ->and($status['hints'])->toBe([])
        ->and(app(BoogleService::class)->errors([], false)['counts']['errors'])->toBe(1);

    Http::assertSentCount(1);
});

it('reads the unresolved issues of Sentry, and closes one when asked', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableErrorsProvider('sentry');

    config()->set('larapilot.errors.sentry.token', 'sentry-token');
    config()->set('larapilot.errors.sentry.organization', 'acme');
    config()->set('larapilot.errors.sentry.project', 'shop');

    fakeTracker([
        'sentry.io/api/0/projects/acme/shop/issues/*' => Http::response([
            [
                'id' => '1',
                'shortId' => 'SHOP-1',
                'title' => 'QueryException: SQLSTATE[23000]',
                'culprit' => 'App\\Http\\Controllers\\ImportController::store',
                'metadata' => [
                    'type' => 'Illuminate\\Database\\QueryException',
                    'value' => "Duplicate entry 'mario.rossi@example.com' for key 'customers_email_unique'",
                    'filename' => 'app/Http/Controllers/ImportController.php',
                ],
                'count' => '14',
                'firstSeen' => '2026-09-20T10:00:00Z',
                'lastSeen' => '2026-09-27T10:00:00Z',
                'permalink' => 'https://acme.sentry.io/issues/1/',
            ],
            [
                'id' => '2',
                'shortId' => 'SHOP-2',
                'title' => 'TypeError',
                'culprit' => 'app/Support/Money.php:19',
                'metadata' => ['type' => 'TypeError', 'value' => 'Unsupported operand types'],
                'count' => '2',
                'lastSeen' => '2026-09-26T10:00:00Z',
            ],
        ]),
        'sentry.io/api/0/organizations/acme/issues/*' => Http::response(['id' => '1', 'status' => 'resolved']),
    ]);

    $payload = app(BoogleService::class)->errors([], true);

    expect($payload['provider'])->toBe('sentry')
        ->and($payload['provider_label'])->toBe('Sentry')
        ->and($payload['granularity'])->toBe('issue')
        ->and($payload['days'])->toBe([])
        ->and($payload['counts']['errors'])->toBe(2)
        ->and($payload['counts']['occurrences'])->toBe(16)
        ->and($payload['errors'][0]['short'])->toBe('QueryException')
        ->and($payload['errors'][0]['codes'])->toBe(['SHOP-1'])
        ->and($payload['errors'][0]['count'])->toBe(14)
        ->and($payload['errors'][0]['unseen'])->toBe(0)
        ->and($payload['errors'][0]['where'])->toBe('app/Http/Controllers/ImportController.php')
        ->and($payload['errors'][0]['message'])->toContain('[address]')
        ->and($payload['errors'][0]['url'])->toBe('https://acme.sentry.io/issues/1/')
        ->and($payload['errors'][1]['where'])->toBe('app/Support/Money.php:19')
        ->and($payload['summary'])->toStartWith('2 open errors, thrown 16 times')
        ->and(json_encode($payload))->not->toContain('mario.rossi');

    Http::assertSent(static fn (Request $request): bool => $request->method() === 'GET'
        && str_contains($request->url(), '/api/0/projects/acme/shop/issues/')
        && str_contains($request->url(), 'query=is%3Aunresolved')
        && $request->hasHeader('Authorization', 'Bearer sentry-token'));

    expect(app(BoogleService::class)->report($payload))->toStartWith("# Sentry errors — shop\n");

    Artisan::call('larapilot:boogle-resolve', ['errors' => 'SHOP-1']);
    $envelope = errorsEnvelope();

    expect($envelope['kind'])->toBe('boogle_resolve')
        ->and($envelope['data']['closed'])->toBe(['SHOP-1']);

    Http::assertSent(static fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://sentry.io/api/0/organizations/acme/issues/1/'
        && $request['status'] === 'resolved');
});

it('shows the errors of another tracker on the dashboard, without the day chart', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableErrorsProvider('sentry');

    config()->set('larapilot.errors.sentry.token', 'sentry-token');
    config()->set('larapilot.errors.sentry.organization', 'acme');
    config()->set('larapilot.errors.sentry.project', 'shop');

    fakeTracker([
        'sentry.io/api/0/projects/acme/shop/issues/*' => Http::response([
            ['id' => '1', 'shortId' => 'SHOP-1', 'title' => 'TypeError', 'metadata' => ['type' => 'TypeError', 'value' => 'Unsupported operand types'], 'count' => '3', 'lastSeen' => '2026-09-27T10:00:00Z'],
        ]),
    ]);

    $this->get('/larapilot/errors')
        ->assertOk()
        ->assertSee('as Sentry recorded it', false)
        ->assertSee('<dt>In Sentry</dt>', false)
        ->assertSee('stay in Sentry', false)
        ->assertSee('Sentry has no uptime monitor', false)
        ->assertDontSee('Errors thrown, day by day', false)
        ->assertDontSee('not opened in Sentry yet', false);

    $download = $this->get('/larapilot/errors/errors.md')->assertOk();

    expect($download->headers->get('Content-Disposition'))->toBe('attachment; filename="sentry-errors-'.now()->format('Y-m-d').'.md"')
        ->and($download->getContent())->toStartWith("# Sentry errors — shop\n");
});

it('reads the open errors of Bugsnag, and marks one fixed when asked', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableErrorsProvider('bugsnag');

    config()->set('larapilot.errors.bugsnag.token', 'bugsnag-token');
    config()->set('larapilot.errors.bugsnag.project_id', 'p1');
    config()->set('larapilot.errors.bugsnag.project_name', 'Shop');

    fakeTracker([
        'api.bugsnag.com/projects/p1/errors*' => Http::response([
            [
                'id' => 'e1',
                'error_class' => 'RuntimeException',
                'message' => 'Import failed',
                'context' => 'POST /admin/customers/import',
                'events' => 9,
                'first_seen' => '2026-09-20T10:00:00.000Z',
                'last_seen' => '2026-09-27T10:00:00.000Z',
                'grouping_fields' => ['file' => 'app/Services/CustomerImporter.php', 'lineNumber' => 88],
                'url' => 'https://app.bugsnag.com/shop/errors/e1',
            ],
        ]),
    ]);

    $payload = app(BoogleService::class)->errors([], true);

    expect($payload['project']['title'])->toBe('Shop')
        ->and($payload['errors'][0]['count'])->toBe(9)
        ->and($payload['errors'][0]['codes'])->toBe(['#e1'])
        ->and($payload['errors'][0]['where'])->toBe('app/Services/CustomerImporter.php:88')
        ->and($payload['errors'][0]['request'])->toBe('POST /admin/customers/import');

    Http::assertSent(static fn (Request $request): bool => $request->method() === 'GET'
        && str_starts_with($request->url(), 'https://api.bugsnag.com/projects/p1/errors?')
        && str_contains($request->url(), 'filters%5Berror.status%5D%5B%5D%5Btype%5D=eq')
        && str_contains($request->url(), 'filters%5Berror.status%5D%5B%5D%5Bvalue%5D=open')
        && ! str_contains($request->url(), '%5B0%5D')
        && $request->hasHeader('Authorization', 'token bugsnag-token')
        && $request->hasHeader('X-Version', '2'));

    Artisan::call('larapilot:boogle-resolve', ['errors' => 'e1']);

    expect(errorsEnvelope()['kind'])->toBe('boogle_resolve');

    Http::assertSent(static fn (Request $request): bool => $request->method() === 'PATCH'
        && $request->url() === 'https://api.bugsnag.com/projects/p1/errors/e1'
        && $request['operation'] === 'fix');
});

it('reads the open errors of Flare, and resolves one when asked', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableErrorsProvider('flare');

    config()->set('larapilot.errors.flare.token', 'flare-token');
    config()->set('larapilot.errors.flare.project_id', '42');

    fakeTracker([
        'flareapp.io/api/projects/42/errors*' => Http::response([
            'data' => [
                [
                    'id' => 9001,
                    'exception_class' => 'RuntimeException',
                    'exception_message' => 'Something broke',
                    'file' => 'app/Http/Controllers/DemoController.php',
                    'line' => 12,
                    'status' => 'open',
                    'occurrence_count' => 5,
                    'first_seen_at' => '2026-09-25T10:00:00Z',
                    'last_seen_at' => '2026-09-27T10:00:00Z',
                    'url' => 'https://flareapp.io/projects/42/errors/9001',
                ],
                [
                    'id' => 9002,
                    'exception_class' => 'TypeError',
                    'exception_message' => 'Fixed already',
                    'file' => 'app/Support/Money.php',
                    'line' => 19,
                    'status' => 'resolved',
                    'occurrence_count' => 1,
                    'last_seen_at' => '2026-09-21T10:00:00Z',
                ],
            ],
        ]),
        'flareapp.io/api/errors/*' => Http::response(['status' => 'resolved']),
    ]);

    $payload = app(BoogleService::class)->errors([], true);

    expect($payload['counts']['errors'])->toBe(1)
        ->and($payload['errors'][0]['class'])->toBe('RuntimeException')
        ->and($payload['errors'][0]['codes'])->toBe(['#FL9001'])
        ->and($payload['errors'][0]['count'])->toBe(5)
        ->and($payload['errors'][0]['where'])->toBe('app/Http/Controllers/DemoController.php:12')
        ->and($payload['project']['provider'])->toBe('flare');

    Http::assertSent(static fn (Request $request): bool => $request->method() === 'GET'
        && str_contains($request->url(), 'page%5Bsize%5D=100')
        && $request->hasHeader('Authorization', 'Bearer flare-token'));

    Artisan::call('larapilot:boogle-resolve', ['errors' => 'FL9001']);

    expect(errorsEnvelope()['kind'])->toBe('boogle_resolve');

    Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://flareapp.io/api/errors/9001/resolve');
});

it('reads the open issues of Datadog Error Tracking, and resolves one when asked', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableErrorsProvider('datadog');

    config()->set('larapilot.errors.datadog.api_key', 'dd-api');
    config()->set('larapilot.errors.datadog.application_key', 'dd-app');
    config()->set('larapilot.errors.datadog.service', 'my-laravel-app');
    config()->set('larapilot.errors.datadog.source', 'error_tracking');

    fakeTracker([
        'api.datadoghq.com/api/v2/error-tracking/issues/search*' => Http::response([
            'data' => [
                [
                    'id' => 'result-1',
                    'type' => 'error_tracking_search_result',
                    'attributes' => ['issue_id' => 'issue-1', 'total_count' => 27],
                    'relationships' => ['issue' => ['data' => ['id' => 'issue-1', 'type' => 'issue']]],
                ],
            ],
            'included' => [
                [
                    'id' => 'issue-1',
                    'type' => 'issue',
                    'attributes' => [
                        'error_type' => 'Illuminate\\Database\\QueryException',
                        'error_message' => 'SQLSTATE[HY000]: General error',
                        'file_path' => 'app/Models/User.php',
                        'function_name' => 'App\\Models\\User::save',
                        'first_seen' => 1758967200000,
                        'last_seen' => 1759000000000,
                        'state' => 'OPEN',
                        'service' => 'my-laravel-app',
                    ],
                ],
            ],
        ]),
        'api.datadoghq.com/api/v2/error-tracking/issues/*' => Http::response(['data' => ['id' => 'issue-1', 'type' => 'issue', 'attributes' => ['state' => 'RESOLVED']]]),
    ]);

    $payload = app(BoogleService::class)->errors([], true);

    expect($payload['granularity'])->toBe('issue')
        ->and($payload['counts']['errors'])->toBe(1)
        ->and($payload['errors'][0]['short'])->toBe('QueryException')
        ->and($payload['errors'][0]['count'])->toBe(27)
        ->and($payload['errors'][0]['where'])->toBe('app/Models/User.php')
        ->and($payload['errors'][0]['last_seen'])->toBe('2025-09-27T19:06:40+00:00')
        ->and($payload['project']['provider'])->toBe('datadog');

    Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST'
        && str_starts_with($request->url(), 'https://api.datadoghq.com/api/v2/error-tracking/issues/search?include=issue')
        && $request->hasHeader('DD-API-KEY', 'dd-api')
        && $request->hasHeader('DD-APPLICATION-KEY', 'dd-app')
        && $request['data']['type'] === 'search_request'
        && $request['data']['attributes']['query'] === 'service:my-laravel-app'
        && $request['data']['attributes']['track'] === 'trace'
        && $request['data']['attributes']['states'] === ['OPEN']
        && is_int($request['data']['attributes']['from']));

    Artisan::call('larapilot:boogle-resolve', ['errors' => 'DDissue-1']);

    expect(errorsEnvelope()['kind'])->toBe('boogle_resolve');

    Http::assertSent(static fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://api.datadoghq.com/api/v2/error-tracking/issues/issue-1/state'
        && $request['data']['attributes']['state'] === 'RESOLVED');
});

it('reads the error logs of Datadog when that is what the project sends, one row for each throw', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableErrorsProvider('datadog');

    config()->set('larapilot.errors.datadog.api_key', 'dd-api');
    config()->set('larapilot.errors.datadog.application_key', 'dd-app');
    config()->set('larapilot.errors.datadog.service', 'my-laravel-app');
    config()->set('larapilot.errors.datadog.source', 'logs');

    $stack = "#0 /var/www/releases/20260920/app/Services/ReportBuilder.php(10): App\\Services\\ReportBuilder->build()\n#1 {main}";
    $event = static fn (string $id, string $at): array => [
        'id' => $id,
        'type' => 'log',
        'attributes' => [
            'timestamp' => $at,
            'message' => 'Undefined index in report for user mario.rossi@example.com',
            'attributes' => ['error' => ['kind' => 'ErrorException', 'message' => 'Undefined index in report', 'stack' => $stack]],
        ],
    ];

    fakeTracker([
        'api.datadoghq.com/api/v2/logs/events/search' => Http::response(['data' => [
            $event('log-1', now()->subDay()->toIso8601String()),
            $event('log-2', now()->subDays(2)->toIso8601String()),
        ]]),
    ]);

    $payload = app(BoogleService::class)->errors([], true);

    expect($payload['granularity'])->toBe('occurrence')
        ->and($payload['counts']['errors'])->toBe(1)
        ->and($payload['errors'][0]['class'])->toBe('ErrorException')
        ->and($payload['errors'][0]['count'])->toBe(2)
        ->and($payload['errors'][0]['where'])->toBe('app/Services/ReportBuilder.php:10')
        ->and(array_sum(array_column($payload['days'], 'count')))->toBe(2)
        ->and(json_encode($payload))->not->toContain('mario.rossi');

    Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST'
        && $request['filter']['query'] === 'service:my-laravel-app status:error'
        && $request['filter']['from'] === 'now-14d');

    // Logs are not closed: the ledger keeps the decision, the tracker is not written to.
    Artisan::call('larapilot:boogle-resolve', ['errors' => $payload['errors'][0]['key']]);
    $envelope = errorsEnvelope();

    expect($envelope['kind'])->toBe('error')
        ->and($envelope['error']['code'])->toBe('E_CONNECTOR')
        ->and($envelope['error']['message'])->toBe('Datadog cannot close an error from Larapilot.')
        ->and($envelope['error']['hint'])->toContain('stays in the ledger');

    Http::assertNotSent(static fn (Request $request): bool => $request->method() === 'PUT');
});

it('reads the active items of Rollbar, and resolves one when asked', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableErrorsProvider('rollbar');

    config()->set('larapilot.errors.rollbar.access_token', 'rollbar-read');
    config()->set('larapilot.errors.rollbar.project_name', 'Shop');

    fakeTracker([
        'api.rollbar.com/api/1/items*' => Http::response(['err' => 0, 'result' => ['page' => 1, 'total_count' => 1, 'items' => [
            [
                'id' => 275123456,
                'counter' => 57,
                'title' => 'RuntimeException: Boom',
                'status' => 'active',
                'level' => 'error',
                'environment' => 'production',
                'total_occurrences' => 9,
                'first_occurrence_timestamp' => 1758967200,
                'last_occurrence_timestamp' => 1759000000,
            ],
            [
                'id' => 275123457,
                'counter' => 58,
                'title' => 'RuntimeException: Another place',
                'status' => 'active',
                'total_occurrences' => 2,
                'last_occurrence_timestamp' => 1758990000,
            ],
        ]]]),
        'api.rollbar.com/api/1/item/*' => Http::response(['err' => 0, 'result' => ['id' => 275123456, 'status' => 'resolved']]),
    ]);

    $payload = app(BoogleService::class)->errors([], true);

    expect($payload['project']['title'])->toBe('Shop')
        ->and($payload['errors'][0]['class'])->toBe('RuntimeException')
        ->and($payload['errors'][0]['message'])->toBe('Boom')
        ->and($payload['errors'][0]['codes'])->toBe(['#RB57'])
        ->and($payload['errors'][0]['count'])->toBe(9)
        ->and($payload['errors'][0]['last_seen'])->toBe('2025-09-27T19:06:40+00:00')
        ->and($payload['counts']['errors'])->toBe(2);

    // Rollbar gives no place in the code: two bugs of one class are two groups.
    $plan = app(BoogleService::class)->resolutionPlan(['RB57', 'RB58']);

    expect($plan['provider'])->toBe('rollbar')
        ->and($plan['groups'])->toHaveCount(2)
        ->and($plan['groups'][0]['codes'])->toBe(['#RB57'])
        ->and($plan['groups'][0]['thrown'])->toBe(9);

    expect(Artisan::call('larapilot:errors-plan', ['--codes' => 'RB999']))->toBe(4)
        ->and(errorsEnvelope()['error'])->toMatchArray([
            'code' => 'E_NOT_FOUND',
            'message' => 'Rollbar holds no open error RB999 for this project.',
        ]);

    Http::assertSent(static fn (Request $request): bool => $request->method() === 'GET'
        && str_contains($request->url(), 'status=active')
        && $request->hasHeader('X-Rollbar-Access-Token', 'rollbar-read'));

    Artisan::call('larapilot:boogle-resolve', ['errors' => 'RB57']);

    expect(errorsEnvelope()['kind'])->toBe('boogle_resolve');

    Http::assertSent(static fn (Request $request): bool => $request->method() === 'PATCH'
        && $request->url() === 'https://api.rollbar.com/api/1/item/275123456'
        && $request['status'] === 'resolved');
});

it('reads the unresolved faults of Honeybadger, and resolves one when asked', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableErrorsProvider('honeybadger');

    config()->set('larapilot.errors.honeybadger.auth_token', 'hb-personal');
    config()->set('larapilot.errors.honeybadger.project_id', '7');

    fakeTracker([
        'app.honeybadger.io/v2/projects/7/faults*' => Http::response(['results' => [
            ['id' => 2, 'klass' => 'RuntimeError', 'message' => 'This is a runtime error', 'component' => 'pages', 'action' => 'runtime_error', 'environment' => 'production', 'notices_count' => 7, 'resolved' => false, 'ignored' => false, 'last_notice_at' => '2026-09-27T10:00:00Z', 'url' => 'https://app.honeybadger.io/projects/7/faults/2'],
            ['id' => 3, 'klass' => 'ArgumentError', 'message' => 'Gone', 'notices_count' => 1, 'resolved' => true, 'ignored' => false, 'last_notice_at' => '2026-09-21T10:00:00Z'],
        ], 'links' => ['self' => 'https://app.honeybadger.io/v2/projects/7/faults']]),
    ]);

    $payload = app(BoogleService::class)->errors([], true);

    expect($payload['counts']['errors'])->toBe(1)
        ->and($payload['errors'][0]['class'])->toBe('RuntimeError')
        ->and($payload['errors'][0]['codes'])->toBe(['#HB2'])
        ->and($payload['errors'][0]['count'])->toBe(7);

    Http::assertSent(static fn (Request $request): bool => $request->method() === 'GET'
        && str_contains($request->url(), 'q=-is%3Aresolved')
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('hb-personal:')));

    Artisan::call('larapilot:boogle-resolve', ['errors' => 'HB2']);

    expect(errorsEnvelope()['kind'])->toBe('boogle_resolve');

    Http::assertSent(static fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://app.honeybadger.io/v2/projects/7/faults/2'
        && $request['fault']['resolved'] === true);
});

it('reads the error logs of CloudWatch with the AWS CLI, and never writes there', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableErrorsProvider('cloudwatch');

    config()->set('larapilot.errors.cloudwatch.log_group', '/forge/shop/laravel');
    config()->set('larapilot.errors.cloudwatch.region', 'eu-south-1');
    config()->set('larapilot.errors.cloudwatch.profile', 'shop');

    $line = static fn (int $n): string => '[2026-09-27 10:0'.$n.':00] production.ERROR: Undefined index "points" {"exception":"[object] (ErrorException(code: 0): Undefined index \"points\" at /home/forge/shop/releases/20260920/app/Services/ReportBuilder.php:10)"}';

    Process::fake([
        '*filter-log-events*' => Process::result(output: json_encode(['events' => [
            ['eventId' => 'ev-1', 'timestamp' => now()->subDay()->getTimestamp() * 1000, 'message' => $line(1)],
            ['eventId' => 'ev-2', 'timestamp' => now()->subDays(3)->getTimestamp() * 1000, 'message' => $line(2)],
        ]])),
    ]);

    $payload = app(BoogleService::class)->errors([], true);

    expect($payload['granularity'])->toBe('occurrence')
        ->and($payload['project']['title'])->toBe('laravel')
        ->and($payload['counts']['errors'])->toBe(1)
        ->and($payload['errors'][0]['class'])->toBe('ErrorException')
        ->and($payload['errors'][0]['count'])->toBe(2)
        ->and($payload['errors'][0]['where'])->toBe('app/Services/ReportBuilder.php:10')
        ->and(array_sum(array_column($payload['days'], 'count')))->toBe(2);

    Process::assertRan(static function ($process): bool {
        $command = implode(' ', (array) $process->command);

        return str_starts_with($command, 'aws logs filter-log-events --log-group-name /forge/shop/laravel')
            && str_contains($command, '--region eu-south-1')
            && str_contains($command, '--profile shop')
            && str_contains($command, '--max-items 300');
    });

    $status = app(BoogleService::class)->status();

    expect($status['remote_resolve'])->toBeFalse()
        ->and($status['granularity'])->toBe('occurrence');

    Artisan::call('larapilot:boogle-resolve', ['errors' => $payload['errors'][0]['key']]);

    expect(errorsEnvelope()['error']['message'])->toBe('AWS CloudWatch Logs cannot close an error from Larapilot.');
});

it('says what to check when the AWS CLI is not signed in', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableErrorsProvider('cloudwatch');
    config()->set('larapilot.errors.cloudwatch.log_group', '/forge/shop/laravel');

    Process::fake(['*filter-log-events*' => Process::result(errorOutput: 'Unable to locate credentials. You can configure credentials by running "aws configure".', exitCode: 255)]);

    expect(Artisan::call('larapilot:boogle-errors'))->toBe(3);

    $error = errorsEnvelope()['error'];

    expect($error['code'])->toBe('E_CONNECTOR')
        ->and($error['message'])->toBe('CloudWatch Logs could not be read.')
        ->and($error['hint'])->toContain('aws configure');

    $status = app(BoogleService::class)->status();

    expect($status['authenticated'])->toBeFalse()
        ->and($status['error'])->toBe('CloudWatch Logs could not be read.');
});
