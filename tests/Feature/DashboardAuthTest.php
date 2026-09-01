<?php

declare(strict_types=1);

use Larapilot\Services\ConfigService;
use Larapilot\Services\DashboardAuthService;

function enableDashboardAuth(): void
{
    app(ConfigService::class)->updateSettings(['dashboard_auth' => 'YES']);
}

function basicAuth(string $user, string $password): array
{
    return ['Authorization' => 'Basic '.base64_encode($user.':'.$password)];
}

it('leaves the dashboard open when dashboard_auth is off (default)', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    $this->get('/larapilot')->assertOk()->assertSee('US-001');
});

it('returns 500 when dashboard_auth is on but no users are configured', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    enableDashboardAuth();

    $this->get('/larapilot')->assertStatus(500);
});

it('challenges unauthenticated requests once a user exists', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    app(DashboardAuthService::class)->setUser('andrea', 's3cret-pass');
    enableDashboardAuth();

    $this->get('/larapilot')
        ->assertStatus(401)
        ->assertHeader('WWW-Authenticate', 'Basic realm="Larapilot", charset="UTF-8"');

    $this->get('/larapilot', basicAuth('andrea', 'wrong'))->assertStatus(401);
});

it('serves the dashboard with valid Basic Auth credentials', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    app(DashboardAuthService::class)->setUser('andrea', 's3cret-pass');
    enableDashboardAuth();

    $this->get('/larapilot', basicAuth('andrea', 's3cret-pass'))
        ->assertOk()
        ->assertSee('US-001');
});

it('never applies the dashboard gate to the JSON API', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    app(DashboardAuthService::class)->setUser('andrea', 's3cret-pass');
    enableDashboardAuth();

    // No dashboard credentials, no API token configured — reads stay open.
    $this->getJson('/larapilot/api/board')->assertOk();
});

it('throttles repeated failed sign-in attempts per IP', function (): void {
    config()->set('larapilot.dashboard_route.auth.max_attempts', 2);

    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    app(DashboardAuthService::class)->setUser('andrea', 's3cret-pass');
    enableDashboardAuth();

    $this->get('/larapilot', basicAuth('andrea', 'nope'))->assertStatus(401);
    $this->get('/larapilot', basicAuth('andrea', 'nope'))->assertStatus(401);
    $this->get('/larapilot', basicAuth('andrea', 'nope'))->assertStatus(429);
});

it('manages dashboard users through the artisan command', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:dashboard-user', ['action' => 'add', 'username' => 'andrea', '--password' => 'pw-one'])
        ->assertSuccessful();
    $this->artisan('larapilot:dashboard-user', ['action' => 'add', 'username' => 'guest', '--password' => 'pw-two'])
        ->assertSuccessful();

    $auth = app(DashboardAuthService::class);
    expect($auth->usernames())->toBe(['andrea', 'guest'])
        ->and($auth->validate('andrea', 'pw-one'))->toBeTrue()
        ->and($auth->validate('andrea', 'pw-two'))->toBeFalse()
        ->and($auth->validate('nobody', 'pw-one'))->toBeFalse();

    $this->artisan('larapilot:dashboard-user', ['action' => 'remove', 'username' => 'guest'])
        ->assertSuccessful();
    expect(app(DashboardAuthService::class)->usernames())->toBe(['andrea']);

    $this->artisan('larapilot:dashboard-user', ['action' => 'remove', 'username' => 'ghost'])
        ->assertFailed();
});

it('requires a password for the add action', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:dashboard-user', ['action' => 'add', 'username' => 'andrea', '--no-interaction' => true])
        ->assertFailed();
});

it('git-ignores the credential file the first time a user is written', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    app(DashboardAuthService::class)->setUser('andrea', 's3cret-pass');

    $gitignore = base_path('.gitignore');
    expect($gitignore)->toBeFile()
        ->and(file_get_contents($gitignore))->toContain('/.larapilot/auth.yaml');

    // Credentials themselves are never stored in cleartext.
    expect(file_get_contents(app(DashboardAuthService::class)->path()))
        ->not->toContain('s3cret-pass');
});

it('persists the dashboard_auth setting via settings-set', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:settings-set', ['--dashboard-auth' => 'YES'])->assertSuccessful();

    expect(app(ConfigService::class)->settings()['dashboard_auth'])->toBe('YES')
        ->and(app(ConfigService::class)->dashboardAuthEnabled())->toBeTrue();
});
