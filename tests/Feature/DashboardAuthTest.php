<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Larapilot\Services\Auth\AccessPolicy;
use Larapilot\Services\Auth\AuthAuditService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DashboardAuthService;
use Larapilot\Support\Totp;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Yaml\Yaml;

// ---- the mode follows APP_ENV ----

it('is open on a developer machine: no sign-in, an implicit admin, the sign-in routes lead back', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    expect(app(AccessPolicy::class)->mode())->toBe(AccessPolicy::MODE_OPEN);

    $this->get('/larapilot')->assertOk()->assertSee('US-001')
        ->assertSee('>Users</a>', false)
        ->assertSee('>API token</a>', false)
        ->assertDontSee('Sign out', false)
        ->assertSee('href="https://larapilot.web.ap.it"', false)
        ->assertSee('<span class="brand-sub">Dashboard</span>', false);

    $this->get('/larapilot/users')->assertOk()->assertSee('Open here', false);
    $this->get('/larapilot/api-token')->assertOk();
    $this->get('/larapilot/login')->assertRedirect('/larapilot');
    $this->get('/larapilot/account')->assertRedirect('/larapilot');
});

it('stays closed on a shared host until the first admin exists, and says how to create one', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    sharedHost();

    expect(app(AccessPolicy::class)->mode())->toBe(AccessPolicy::MODE_AUTH);

    $this->get('/larapilot')
        ->assertStatus(503)
        ->assertSee('This dashboard is protected')
        ->assertSee('php artisan larapilot:dashboard-user add &lt;username&gt; --role=admin', false)
        ->assertDontSee('dashboard_auth')
        ->assertDontSee('US-001');

    $this->get('/larapilot/login')->assertStatus(503);
    $this->get('/larapilot/settings')->assertStatus(503);

    $this->getJson('/larapilot')
        ->assertStatus(503)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'larapilot:dashboard-user add <username> --role=admin'));
});

it('asks for a sign-in on a shared host and comes back to the page asked for', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    dashboardUser('andrea');
    sharedHost();

    $this->get('/larapilot')->assertRedirect('/larapilot/login');
    $this->getJson('/larapilot')->assertStatus(401);
    $this->get('/larapilot/login')->assertOk()->assertSee('Sign in to the dashboard')->assertSee('APP_ENV=staging', false);

    signInDashboard('andrea', 'wrong-password-here')
        ->assertRedirect('/larapilot/login')
        ->assertSessionHasErrors('username');

    // The page asked for is where the sign-in lands.
    $this->get('/larapilot/usage')->assertRedirect('/larapilot/login');
    signInDashboard('andrea')->assertRedirect('/larapilot/usage');

    $this->get('/larapilot')->assertOk()->assertSee('US-001')
        ->assertSee('class="who-name">andrea<', false)
        ->assertSee('Admin', false)
        ->assertSee('Sign out', false);

    // The sign-in itself leads to the board.
    $this->get('/larapilot/login')->assertRedirect('/larapilot');

    dashboardPost('/larapilot/logout')->assertRedirect('/larapilot/login');
    $this->get('/larapilot')->assertRedirect('/larapilot/login');
});

it('hides the dashboard in production unless forced, and then asks for an authenticator of everyone', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    dashboardUser('andrea');

    $this->app['env'] = 'production';
    expect(app(AccessPolicy::class)->mode())->toBe(AccessPolicy::MODE_HIDDEN);
    $this->get('/larapilot')->assertNotFound();
    $this->get('/larapilot/login')->assertNotFound();

    forcedProduction();
    expect(app(AccessPolicy::class)->mode())->toBe(AccessPolicy::MODE_PRODUCTION);

    $this->get('/larapilot')->assertRedirect('/larapilot/login');
    $this->get('/larapilot/login')->assertOk()->assertSee('authenticator app', false);

    signInDashboard('andrea')->assertRedirect('/larapilot/two-factor/setup');

    // Nothing opens before the authenticator is enrolled.
    $this->get('/larapilot')->assertRedirect('/larapilot/two-factor/setup');
    $this->get('/larapilot/two-factor')->assertRedirect('/larapilot/two-factor/setup');

    $setup = $this->get('/larapilot/two-factor/setup')->assertOk()
        ->assertSee('Set up your authenticator')
        ->assertSee('<svg', false)
        ->getContent();

    $secret = totpSetupSecret();
    expect($secret)->not->toBe('')
        ->and($setup)->toContain(Totp::pretty($secret))
        ->and($setup)->not->toContain('otpauth://'); // the URI lives in the QR modules, not in the page

    dashboardPost('/larapilot/two-factor/setup', ['code' => '000000'])
        ->assertRedirect('/larapilot/two-factor/setup')
        ->assertSessionHasErrors('code');

    dashboardPost('/larapilot/two-factor/setup', ['code' => totpCode($secret)])->assertRedirect('/larapilot');

    $this->get('/larapilot')->assertOk()->assertSee('US-001')->assertSee('Admin · 2FA', false);

    expect(app(DashboardAuthService::class)->hasTotp('andrea'))->toBeTrue();

    // From now on the sign-in has two steps, and the authenticator cannot be turned off here.
    dashboardPost('/larapilot/logout');
    signInDashboard('andrea')->assertRedirect('/larapilot/two-factor');
    $this->get('/larapilot')->assertRedirect('/larapilot/two-factor');
    $this->travel(61)->seconds();
    dashboardPost('/larapilot/two-factor', ['code' => totpCode($secret)])->assertRedirect('/larapilot');
    $this->get('/larapilot/account')->assertOk()->assertSee('cannot be turned off here', false)->assertDontSee('Turn the authenticator off', false);
    $this->travel(61)->seconds();
    dashboardPost('/larapilot/account/two-factor/disable', ['password' => 'correct-horse-battery', 'code' => totpCode($secret)])
        ->assertRedirect('/larapilot/account')
        ->assertSessionHas('larapilot_error');
    expect(app(DashboardAuthService::class)->hasTotp('andrea'))->toBeTrue();
});

it('lets a user enrol an authenticator by choice on a shared host, asks its code once, and lets them turn it off', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    dashboardUser('andrea');
    sharedHost();
    signInDashboard('andrea')->assertRedirect('/larapilot');

    $this->get('/larapilot/account')->assertOk()->assertSee('Set up an authenticator', false);
    $this->get('/larapilot/two-factor/setup')->assertRedirect('/larapilot/account/two-factor');
    $this->get('/larapilot/account/two-factor')->assertOk()->assertSee('Confirm and turn it on', false);

    $secret = totpSetupSecret();
    dashboardPost('/larapilot/account/two-factor', ['code' => totpCode($secret)])->assertRedirect('/larapilot/account');
    $this->get('/larapilot/account')->assertOk()->assertSee('Turn the authenticator off', false);

    // The file holds the secret encrypted, never in clear.
    $file = (string) file_get_contents(app(DashboardAuthService::class)->path());
    expect($file)->not->toContain($secret)->toContain('totp:');
    expect(app(DashboardAuthService::class)->totpSecret('andrea'))->toBe($secret);

    dashboardPost('/larapilot/logout');
    signInDashboard('andrea')->assertRedirect('/larapilot/two-factor');
    $this->get('/larapilot/two-factor')->assertOk()->assertSee('six digits', false);

    // The code that confirmed the enrolment is spent; the next one is good.
    dashboardPost('/larapilot/two-factor', ['code' => totpCode($secret)])->assertRedirect('/larapilot/two-factor')->assertSessionHasErrors('code');
    $this->travel(61)->seconds();
    $code = totpCode($secret);
    dashboardPost('/larapilot/two-factor', ['code' => 'abc'])->assertRedirect('/larapilot/two-factor')->assertSessionHasErrors('code');
    dashboardPost('/larapilot/two-factor', ['code' => $code])->assertRedirect('/larapilot');
    $this->get('/larapilot')->assertOk();

    // The same code never opens a second session.
    dashboardPost('/larapilot/logout');
    signInDashboard('andrea')->assertRedirect('/larapilot/two-factor');
    dashboardPost('/larapilot/two-factor', ['code' => $code])->assertRedirect('/larapilot/two-factor')->assertSessionHasErrors('code');
    $this->travel(61)->seconds();
    dashboardPost('/larapilot/two-factor', ['code' => totpCode($secret)])->assertRedirect('/larapilot');

    $this->travel(61)->seconds();
    dashboardPost('/larapilot/account/two-factor/disable', ['password' => 'wrong-password-here', 'code' => totpCode($secret)])
        ->assertRedirect('/larapilot/account')->assertSessionHas('larapilot_error');
    expect(app(DashboardAuthService::class)->hasTotp('andrea'))->toBeTrue();

    dashboardPost('/larapilot/account/two-factor/disable', ['password' => 'correct-horse-battery', 'code' => totpCode($secret)])
        ->assertRedirect('/larapilot/account')->assertSessionHas('larapilot_success');
    expect(app(DashboardAuthService::class)->hasTotp('andrea'))->toBeFalse();
});

// ---- the roles ----

it('shows each role its pages and answers 403 on the others', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    dashboardUser('root', 'correct-horse-battery', 'admin');
    dashboardUser('dev', 'correct-horse-battery', 'dev');
    dashboardUser('pm', 'correct-horse-battery', 'pm');
    dashboardUser('ext', 'correct-horse-battery', 'ext');
    sharedHost();

    $open = fn (string $path) => $this->get($path);

    signInDashboard('dev')->assertRedirect('/larapilot');
    $html = $this->get('/larapilot')->assertOk()->getContent();
    expect($html)->toContain('>Database</a>')->toContain('>Logs</a>')->toContain('>Settings</a>')->toContain('>Usage</a>')
        ->not->toContain('>Users</a>')->not->toContain('>API token</a>')->not->toContain('>Anonymization</a>')->not->toContain('>File manager</a>');
    $open('/larapilot/users')->assertForbidden()->assertSee('This page is for another role')->assertSee('Developer');
    $open('/larapilot/api-token')->assertForbidden();
    $open('/larapilot/files')->assertForbidden();
    $open('/larapilot/settings')->assertOk();
    $open('/larapilot/usage')->assertOk();
    $this->getJson('/larapilot/users')->assertForbidden();
    dashboardPost('/larapilot/users', ['username' => 'x', 'password' => 'correct-horse-battery', 'password_confirmation' => 'correct-horse-battery', 'role' => 'admin'])->assertForbidden();
    expect(app(DashboardAuthService::class)->user('x'))->toBeNull();
    dashboardPost('/larapilot/logout');

    signInDashboard('pm')->assertRedirect('/larapilot');
    $html = $this->get('/larapilot')->assertOk()->getContent();
    expect($html)->toContain('>Board</a>')->toContain('>Plan</a>')->toContain('>Usage</a>')->toContain('>Economics</a>')->toContain('>Docs</a>')->toContain('>Logs</a>')
        ->not->toContain('>Settings</a>')->not->toContain('>Database</a>')->not->toContain('>Git</a>')->not->toContain('>About</a>')->not->toContain('>Skills</a>')->not->toContain('>Users</a>')->not->toContain('>Anonymization</a>');
    $open('/larapilot/settings')->assertForbidden();
    $open('/larapilot/git')->assertForbidden();
    $open('/larapilot/logs')->assertOk();
    $open('/larapilot/economics')->assertOk();
    $open('/larapilot/account')->assertOk();
    dashboardPost('/larapilot/logout');

    signInDashboard('ext')->assertRedirect('/larapilot');
    $html = $this->get('/larapilot')->assertOk()->getContent();
    expect($html)->toContain('>Board</a>')->toContain('>PRD</a>')->toContain('>Inception</a>')->toContain('>Plan</a>')->toContain('>Design</a>')
        ->not->toContain('>Usage</a>')->not->toContain('>Economics</a>')->not->toContain('>Docs</a>')->not->toContain('>API</a>')->not->toContain('>Logs</a>')->not->toContain('>Settings</a>')->not->toContain('>Anonymization</a>');
    $open('/larapilot/prd')->assertOk();
    $open('/larapilot/plan')->assertOk();
    $open('/larapilot/design')->assertOk();
    $open('/larapilot/specs/US-001')->assertOk();
    $open('/larapilot/usage')->assertForbidden();
    $open('/larapilot/docs')->assertForbidden();
    $open('/larapilot/logs')->assertForbidden();
    $open('/larapilot/database')->assertForbidden();
    dashboardPost('/larapilot/logout');

    signInDashboard('root')->assertRedirect('/larapilot');
    $html = $this->get('/larapilot')->assertOk()->getContent();
    expect($html)->toContain('>Users</a>')->toContain('>API token</a>')->toContain('>Anonymization</a>')->toContain('>File manager</a>')->toContain('>Database</a>');
    $open('/larapilot/users')->assertOk();
    $open('/larapilot/files')->assertOk();
});

it('takes the logs away from a product manager in production', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    dashboardUser('pm', 'correct-horse-battery', 'pm');
    dashboardUser('dev', 'correct-horse-battery', 'dev');
    forcedProduction();

    $policy = app(AccessPolicy::class);
    expect($policy->allows('pm', 'logs'))->toBeFalse()
        ->and($policy->allows('dev', 'logs'))->toBeTrue()
        ->and($policy->allows('pm', 'usage'))->toBeTrue();

    $this->app['env'] = 'staging';
    expect($policy->allows('pm', 'logs'))->toBeTrue();
});

it('maps every dashboard route to an area, and an unknown route to admins alone', function (): void {
    $policy = app(AccessPolicy::class);
    $this->artisan('larapilot:install')->assertSuccessful();

    $unmapped = [];

    foreach (app('router')->getRoutes() as $route) {
        $name = $route->getName();

        if (! is_string($name) || ! str_starts_with($name, 'larapilot.') || str_starts_with($name, 'larapilot.auth.')) {
            continue;
        }

        if ($policy->areaOfRoute($name) === null) {
            $unmapped[] = $name;
        }
    }

    expect($unmapped)->toBe([])
        ->and($policy->allows('dev', 'something-new'))->toBeFalse()
        ->and($policy->allows('admin', 'something-new'))->toBeTrue()
        ->and($policy->areaOfRoute('larapilot.dashboard.spec.comments.store'))->toBe('board')
        ->and($policy->areaOfRoute('larapilot.mockups.show'))->toBe('design')
        ->and($policy->areaOfRoute('larapilot.dashboard.database.dump.legacy'))->toBe('database');
});

// ---- the users ----

it('lets an admin manage the users, never themself, and any other admin', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    dashboardUser('andrea');
    dashboardUser('other-admin');
    sharedHost();
    signInDashboard('andrea')->assertRedirect('/larapilot');

    $this->get('/larapilot/users')->assertOk()
        ->assertSee('class="chip current">You<', false)
        ->assertSee('Another admin changes your role.', false)
        ->assertSee('other-admin', false);

    // Created with a role.
    dashboardPost('/larapilot/users', ['username' => 'giulia', 'password' => 'a-long-enough-pass', 'password_confirmation' => 'a-long-enough-pass', 'role' => 'pm'])
        ->assertRedirect('/larapilot/users')->assertSessionHas('larapilot_success');
    expect(app(DashboardAuthService::class)->role('giulia'))->toBe('pm');

    // Not created: short password, mismatch, bad name, taken name, unknown role.
    dashboardPost('/larapilot/users', ['username' => 'short', 'password' => 'tiny', 'password_confirmation' => 'tiny', 'role' => 'dev'])
        ->assertRedirect('/larapilot/users')->assertSessionHas('larapilot_error');
    dashboardPost('/larapilot/users', ['username' => 'mismatch', 'password' => 'a-long-enough-pass', 'password_confirmation' => 'another-long-pass', 'role' => 'dev'])
        ->assertSessionHas('larapilot_error');
    dashboardPost('/larapilot/users', ['username' => '../evil', 'password' => 'a-long-enough-pass', 'password_confirmation' => 'a-long-enough-pass', 'role' => 'dev'])
        ->assertSessionHas('larapilot_error');
    dashboardPost('/larapilot/users', ['username' => 'giulia', 'password' => 'a-long-enough-pass', 'password_confirmation' => 'a-long-enough-pass', 'role' => 'dev'])
        ->assertSessionHas('larapilot_error');
    dashboardPost('/larapilot/users', ['username' => 'marco', 'password' => 'a-long-enough-pass', 'password_confirmation' => 'a-long-enough-pass', 'role' => 'superuser'])
        ->assertSessionHas('larapilot_error');
    expect(app(DashboardAuthService::class)->usernames())->toBe(['andrea', 'giulia', 'other-admin']);

    // Role, password, rename, 2FA reset, removal — on others.
    dashboardPost('/larapilot/users/giulia/role', ['role' => 'dev'])->assertSessionHas('larapilot_success');
    expect(app(DashboardAuthService::class)->role('giulia'))->toBe('dev');
    dashboardPost('/larapilot/users/giulia/password', ['password' => 'brand-new-password', 'password_confirmation' => 'brand-new-password'])->assertSessionHas('larapilot_success');
    expect(app(DashboardAuthService::class)->validate('giulia', 'brand-new-password'))->toBeTrue();
    dashboardPost('/larapilot/users/giulia/rename', ['name' => 'giulia.r'])->assertSessionHas('larapilot_success');
    expect(app(DashboardAuthService::class)->user('giulia'))->toBeNull()->and(app(DashboardAuthService::class)->role('giulia.r'))->toBe('dev');
    app(DashboardAuthService::class)->enableTotp('giulia.r', Totp::generateSecret());
    dashboardPost('/larapilot/users/giulia.r/two-factor/reset')->assertSessionHas('larapilot_success');
    expect(app(DashboardAuthService::class)->hasTotp('giulia.r'))->toBeFalse();
    dashboardPost('/larapilot/users/nobody/role', ['role' => 'dev'])->assertNotFound();

    // Never oneself.
    dashboardPost('/larapilot/users/andrea/role', ['role' => 'ext'])->assertSessionHas('larapilot_error');
    dashboardPost('/larapilot/users/andrea/rename', ['name' => 'someone'])->assertSessionHas('larapilot_error');
    dashboardPost('/larapilot/users/andrea/delete')->assertSessionHas('larapilot_error');
    expect(app(DashboardAuthService::class)->role('andrea'))->toBe('admin');
    $this->get('/larapilot')->assertOk();

    // Another admin, yes.
    dashboardPost('/larapilot/users/other-admin/role', ['role' => 'pm'])->assertSessionHas('larapilot_success');
    dashboardPost('/larapilot/users/other-admin/delete')->assertSessionHas('larapilot_success');
    expect(app(DashboardAuthService::class)->usernames())->toBe(['andrea', 'giulia.r']);
});

it('signs a user out everywhere when their password changes or they are removed, and follows a role change at once', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    dashboardUser('andrea');
    dashboardUser('dev', 'correct-horse-battery', 'dev');
    sharedHost();
    signInDashboard('dev')->assertRedirect('/larapilot');
    $this->get('/larapilot/database')->assertOk();

    app(DashboardAuthService::class)->setRole('dev', 'ext');
    $this->get('/larapilot/database')->assertForbidden();
    $this->get('/larapilot')->assertOk();

    app(DashboardAuthService::class)->setPassword('dev', 'a-different-password');
    $this->get('/larapilot')->assertRedirect('/larapilot/login');

    signInDashboard('dev', 'a-different-password')->assertRedirect('/larapilot');
    $this->get('/larapilot')->assertOk();
    app(DashboardAuthService::class)->removeUser('dev');
    $this->get('/larapilot')->assertRedirect('/larapilot/login');
});

it('lets every user change their own password with the current one', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    dashboardUser('ext', 'correct-horse-battery', 'ext');
    sharedHost();
    signInDashboard('ext')->assertRedirect('/larapilot');

    dashboardPost('/larapilot/account/password', ['current_password' => 'nope-nope-nope', 'password' => 'a-brand-new-password', 'password_confirmation' => 'a-brand-new-password'])
        ->assertRedirect('/larapilot/account')->assertSessionHas('larapilot_error');
    dashboardPost('/larapilot/account/password', ['current_password' => 'correct-horse-battery', 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHas('larapilot_error');
    dashboardPost('/larapilot/account/password', ['current_password' => 'correct-horse-battery', 'password' => 'a-brand-new-password', 'password_confirmation' => 'a-brand-new-password'])
        ->assertSessionHas('larapilot_success');

    // This session follows; the password is the new one.
    $this->get('/larapilot')->assertOk();
    expect(app(DashboardAuthService::class)->validate('ext', 'a-brand-new-password'))->toBeTrue();
});

it('slows down wrong passwords per name and per address', function (): void {
    config()->set('larapilot.dashboard_route.auth.max_attempts', 7);
    $this->artisan('larapilot:install')->assertSuccessful();
    dashboardUser('andrea');
    sharedHost();

    foreach (range(1, 5) as $attempt) {
        signInDashboard('andrea', 'wrong-password-here')->assertSessionHasErrors('username');
    }

    // The sixth is not even tried: the right password is refused too.
    signInDashboard('andrea')->assertRedirect('/larapilot/login')->assertSessionHasErrors('username');
    expect(session('errors')->first('username'))->toContain('Too many attempts');

    // Another name from the same address still has two tries before the address is out.
    signInDashboard('someone-else', 'wrong-password-here')->assertSessionHasErrors('username');
    signInDashboard('someone-else', 'wrong-password-here')->assertSessionHasErrors('username');
    signInDashboard('third', 'wrong-password-here');
    expect(session('errors')->first('username'))->toContain('Too many attempts');
});

it('keeps a trail of what happens at the door, out of Git', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    dashboardUser('andrea');
    sharedHost();

    signInDashboard('andrea', 'wrong-password-here');
    signInDashboard('andrea');
    dashboardPost('/larapilot/users', ['username' => 'giulia', 'password' => 'a-long-enough-pass', 'password_confirmation' => 'a-long-enough-pass', 'role' => 'pm']);
    dashboardPost('/larapilot/logout');

    $events = array_column(app(AuthAuditService::class)->entries(), 'event');
    expect($events)->toContain('login.failed', 'login.ok', 'user.created', 'logout');

    $trail = (string) file_get_contents(app(AuthAuditService::class)->path());
    expect($trail)->toContain('"user":"andrea"')->toContain('"ip":')->not->toContain('correct-horse-battery')->not->toContain('a-long-enough-pass')
        ->and(file_get_contents(base_path('.gitignore')))->toContain('/.larapilot/auth-audit.log')->toContain('/.larapilot/auth.yaml');
});

// ---- the file ----

it('reads a file written before 5.1 as admins and rewrites it with roles', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $auth = app(DashboardAuthService::class);

    file_put_contents($auth->path(), Yaml::dump(['users' => ['andrea' => Hash::make('legacy-password-1'), 'guest' => Hash::make('legacy-password-2')]], 4, 2));

    expect($auth->needsMigration())->toBeTrue()
        ->and($auth->role('andrea'))->toBe('admin')
        ->and($auth->role('guest'))->toBe('admin')
        ->and($auth->validate('andrea', 'legacy-password-1'))->toBeTrue()
        ->and($auth->validate('guest', 'wrong'))->toBeFalse();

    expect($auth->migrate())->toBe(['andrea', 'guest'])
        ->and($auth->needsMigration())->toBeFalse()
        ->and($auth->migrate())->toBe([]);

    $parsed = Yaml::parseFile($auth->path());
    expect($parsed['users']['andrea']['role'])->toBe('admin')
        ->and($parsed['users']['guest']['role'])->toBe('admin')
        ->and($auth->validate('andrea', 'legacy-password-1'))->toBeTrue();

    // A legacy file not migrated yet: the sign-in works, and the first write brings the layout along.
    file_put_contents($auth->path(), Yaml::dump(['users' => ['guest' => Hash::make('legacy-password-2')]], 4, 2));
    sharedHost();
    signInDashboard('guest', 'legacy-password-2')->assertRedirect('/larapilot');
    $this->get('/larapilot/users')->assertOk();
    expect(Yaml::parseFile($auth->path())['users']['guest']['role'])->toBe('admin')
        ->and($auth->needsMigration())->toBeFalse();
    $this->app['env'] = 'testing';

    // larapilot:update does the same, and says so.
    file_put_contents($auth->path(), Yaml::dump(['users' => ['solo' => Hash::make('legacy-password-3')]], 4, 2));
    $this->artisan('larapilot:update', ['--skip-boost' => true])
        ->expectsOutputToContain('Dashboard users given the admin role')
        ->assertSuccessful();
    expect(Yaml::parseFile($auth->path())['users']['solo']['role'])->toBe('admin');
});

it('never stores a password, a secret, or a token in clear', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $auth = app(DashboardAuthService::class);
    $auth->setUser('andrea', 'correct-horse-battery', 'admin');
    $secret = Totp::generateSecret();
    $auth->enableTotp('andrea', $secret);
    $token = $auth->issueApiToken('andrea');

    $file = (string) file_get_contents($auth->path());
    expect($file)->not->toContain('correct-horse-battery')->not->toContain($secret)->not->toContain($token)
        ->toContain('role: admin')->toContain('api_token:')->toContain('prefix: '.substr($token, 0, 11))
        ->and($auth->apiTokenMatches($token))->toBeTrue()
        ->and($auth->apiTokenMatches($token.'x'))->toBeFalse()
        ->and($auth->apiToken()['created_by'])->toBe('andrea');

    expect(fn () => $auth->setUser('x', 'short'))->toThrow(InvalidArgumentException::class, 'at least 12')
        ->and(fn () => $auth->setUser('andrea', 'Andrea'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $auth->setUser('andrea', 'correct-horse-battery', 'root'))->toThrow(InvalidArgumentException::class, 'Unknown role')
        ->and(fn () => $auth->setUser('bad name', 'correct-horse-battery'))->toThrow(InvalidArgumentException::class);
});

it('computes codes an authenticator agrees with', function (): void {
    // RFC 6238 test vector: secret "12345678901234567890" at T=59 gives 287082 (SHA-1, 8 digits 94287082).
    $secret = Totp::base32Encode('12345678901234567890');

    expect($secret)->toBe('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ')
        ->and(Totp::code($secret, Totp::step(59)))->toBe('287082')
        ->and(Totp::code($secret, Totp::step(1111111109)))->toBe('081804')
        ->and(Totp::verify($secret, '287082', null, 59))->toBe(1)
        ->and(Totp::verify($secret, '287 082', null, 59))->toBe(1)
        ->and(Totp::verify($secret, '287082', 1, 59))->toBeNull()
        ->and(Totp::verify($secret, '000000', null, 59))->toBeNull()
        ->and(Totp::verify('not base32!', '287082', null, 59))->toBeNull()
        ->and(Totp::uri('My App', 'andrea', $secret))->toBe('otpauth://totp/My%20App:andrea?secret='.$secret.'&issuer=My%20App&algorithm=SHA1&digits=6&period=30')
        ->and(Totp::base32Decode('gezd gnbv gy3t qojq gezd gnbv gy3t qojq'))->toBe('12345678901234567890');
});

// ---- the shell ----

it('manages users and roles through the artisan command', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:dashboard-user', ['action' => 'add', 'username' => 'andrea', '--password' => 'correct-horse-battery'])
        ->assertSuccessful();
    $this->artisan('larapilot:dashboard-user', ['action' => 'add', 'username' => 'giulia', '--password' => 'correct-horse-battery', '--role' => 'pm'])
        ->assertSuccessful();
    $this->artisan('larapilot:dashboard-user', ['action' => 'add', 'username' => 'giulia', '--password' => 'correct-horse-battery'])
        ->assertFailed();
    $this->artisan('larapilot:dashboard-user', ['action' => 'add', 'username' => 'short', '--password' => 'short'])
        ->assertFailed();
    $this->artisan('larapilot:dashboard-user', ['action' => 'add', 'username' => 'marco', '--password' => 'correct-horse-battery', '--role' => 'king'])
        ->assertFailed();

    $auth = app(DashboardAuthService::class);
    expect($auth->role('andrea'))->toBe('admin')->and($auth->role('giulia'))->toBe('pm');

    $this->artisan('larapilot:dashboard-user', ['action' => 'role', 'username' => 'giulia', 'value' => 'dev'])->assertSuccessful();
    expect($auth->role('giulia'))->toBe('dev');
    $this->artisan('larapilot:dashboard-user', ['action' => 'role', 'username' => 'giulia', 'value' => 'king'])->assertFailed();
    $this->artisan('larapilot:dashboard-user', ['action' => 'role', 'username' => 'ghost', 'value' => 'dev'])->assertFailed();

    $this->artisan('larapilot:dashboard-user', ['action' => 'password', 'username' => 'giulia', '--password' => 'another-long-password'])->assertSuccessful();
    expect($auth->validate('giulia', 'another-long-password'))->toBeTrue();

    $this->artisan('larapilot:dashboard-user', ['action' => 'rename', 'username' => 'giulia', 'value' => 'giulia.r'])->assertSuccessful();
    expect($auth->user('giulia'))->toBeNull()->and($auth->role('giulia.r'))->toBe('dev');

    $auth->enableTotp('giulia.r', Totp::generateSecret());
    $this->artisan('larapilot:dashboard-user', ['action' => 'totp-reset', 'username' => 'giulia.r'])->assertSuccessful();
    expect($auth->hasTotp('giulia.r'))->toBeFalse();

    $this->artisan('larapilot:dashboard-user', ['action' => 'list'])
        ->expectsOutputToContain('giulia.r')
        ->assertSuccessful();

    $this->artisan('larapilot:dashboard-user', ['action' => 'remove', 'username' => 'giulia.r'])->assertSuccessful();
    $this->artisan('larapilot:dashboard-user', ['action' => 'remove', 'username' => 'ghost'])->assertFailed();
    expect($auth->usernames())->toBe(['andrea']);

    $this->artisan('larapilot:dashboard-user', ['action' => 'add', 'username' => 'nopass', '--no-interaction' => true])->assertFailed();
    $this->artisan('larapilot:dashboard-user', ['action' => 'dance'])->assertFailed();
});

it('refuses the old settings-set flag for the sign-in', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    expect(fn () => $this->artisan('larapilot:settings-set', ['--dashboard-auth' => 'YES']))
        ->toThrow(InvalidOptionException::class);
    expect(app(ConfigService::class)->settings())->not->toHaveKey('dashboard_auth');
});

// ---- the API token ----

it('opens the API with a token made on the dashboard or in the shell, and closes it on a shared host without one', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    dashboardUser('andrea');
    dashboardUser('ext', 'correct-horse-battery', 'ext');

    // Open here: reads without a token, as before.
    $this->getJson('/larapilot/api/board')->assertOk();

    sharedHost();

    // Closed, with the way to open it.
    $this->getJson('/larapilot/api/board')->assertStatus(503)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'larapilot:api-token generate'));
    $this->get('/larapilot/api/docs', ['Accept' => 'text/html'])->assertStatus(503)->assertSee('This API is protected');

    // A signed-in team member reads from the browser; an external user does not; nobody writes without the token.
    signInDashboard('andrea')->assertRedirect('/larapilot');
    $this->getJson('/larapilot/api/board')->assertOk();
    $this->get('/larapilot/api/docs')->assertOk();
    dashboardPost('/larapilot/logout');
    signInDashboard('ext')->assertRedirect('/larapilot');
    $this->getJson('/larapilot/api/board')->assertStatus(503);
    dashboardPost('/larapilot/logout');

    // The token, from the shell: shown once.
    $this->app['env'] = 'testing';
    $this->artisan('larapilot:api-token', ['action' => 'show'])->expectsOutputToContain('"configured":false')->assertSuccessful();
    $this->artisan('larapilot:api-token', ['action' => 'generate'])->assertSuccessful();
    $token = app(DashboardAuthService::class);
    $this->app['env'] = 'staging';

    // We cannot read the token back from the file, so make one through the page and read it from the flash.
    signInDashboard('andrea')->assertRedirect('/larapilot');
    $this->get('/larapilot/api-token')->assertOk()->assertSee('Active', false)->assertSee('Rotate token', false);
    dashboardPost('/larapilot/api-token')->assertRedirect('/larapilot/api-token')->assertSessionHas('larapilot_api_token');
    $plain = (string) session('larapilot_api_token');
    expect($plain)->toStartWith('lp_')->toHaveLength(43);
    $this->get('/larapilot/api-token')->assertOk()->assertSee($plain, false)->assertSee('copy it now', false);
    $this->get('/larapilot/api-token')->assertOk()->assertDontSee($plain, false);
    dashboardPost('/larapilot/logout');

    $this->getJson('/larapilot/api/board')->assertStatus(401);
    $this->getJson('/larapilot/api/board', ['Authorization' => 'Bearer '.$plain])->assertOk();
    $this->getJson('/larapilot/api/board', ['X-Larapilot-Token' => $plain])->assertOk();
    $this->getJson('/larapilot/api/board', ['Authorization' => 'Bearer lp_wrong'])->assertStatus(401);

    // The env token is still honoured; a rotation ends the old file token; a revocation ends them all.
    config()->set('larapilot.api.token', 'env-secret-token');
    $this->getJson('/larapilot/api/board', ['Authorization' => 'Bearer env-secret-token'])->assertOk();
    $this->getJson('/larapilot/api/board', ['Authorization' => 'Bearer '.$plain])->assertOk();

    $this->app['env'] = 'testing';
    $this->artisan('larapilot:api-token', ['action' => 'rotate'])->assertSuccessful();
    $this->app['env'] = 'staging';
    $this->getJson('/larapilot/api/board', ['Authorization' => 'Bearer '.$plain])->assertStatus(401);

    $this->app['env'] = 'testing';
    $this->artisan('larapilot:api-token', ['action' => 'revoke'])->assertSuccessful();
    $this->artisan('larapilot:api-token', ['action' => 'revoke'])->assertFailed();
    config()->set('larapilot.api.token', null);
    $this->app['env'] = 'staging';
    $this->getJson('/larapilot/api/board')->assertStatus(503);
    expect($token->apiToken())->toBeNull();
});

it('requires the token on a developer machine too once one exists', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableComments();
    addSpec(['status' => 'REVIEW']);

    $this->postJson('/larapilot/api/specs/US-001/comments', ['author' => 'PM', 'message' => 'Open write.'])->assertCreated();

    $this->artisan('larapilot:api-token', ['action' => 'generate'])->assertSuccessful();

    // Once a token exists it is needed everywhere, reads included.
    $this->getJson('/larapilot/api/board')->assertStatus(401);
    $this->postJson('/larapilot/api/specs/US-001/comments', ['author' => 'PM', 'message' => 'Needs the token.'])->assertStatus(401);

    $this->get('/larapilot/api-token')->assertOk()->assertSee('A token exists, so every call needs it', false);

    // The reference holds no data: the sidebar's API link still opens.
    $this->get('/larapilot/api/docs')->assertOk();
    $this->getJson('/larapilot/api/openapi.json')->assertOk();

    // On a shared host it opens for a signed-in user whose role sees the API, never for a stranger.
    dashboardUser('andrea');
    dashboardUser('client', role: 'ext');
    sharedHost();

    $this->getJson('/larapilot/api/openapi.json')->assertStatus(401);
    signInDashboard('client')->assertRedirect();
    $this->get('/larapilot/api/docs')->assertStatus(401);
});

it('keeps where the database lives from a developer, and shows it to an admin', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    dashboardUser('andrea');
    dashboardUser('dev', 'correct-horse-battery', 'dev');

    config()->set('database.connections.remote', [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => '1',
        'database' => 'fjord_secret_db',
        'username' => 'fjord',
        'password' => 'do-not-show',
    ]);
    config()->set('database.default', 'remote');
    sharedHost();

    signInDashboard('dev')->assertRedirect();
    $this->get('/larapilot/about')->assertOk()->assertDontSee('fjord_secret_db', false)->assertDontSee('127.0.0.1:1', false)->assertSee('Shown to admins', false);
    $this->get('/larapilot/database')->assertOk()->assertDontSee('fjord_secret_db', false)->assertDontSee('127.0.0.1', false);
    dashboardPost('/larapilot/logout');

    signInDashboard('andrea')->assertRedirect();
    $this->get('/larapilot/about')->assertOk()->assertSee('fjord_secret_db', false)->assertSee('127.0.0.1:1', false)->assertDontSee('do-not-show', false);
    $this->get('/larapilot/database')->assertOk()->assertSee('fjord_secret_db', false);
});
