<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Larapilot\Services\Auth\AuthAuditService;
use Larapilot\Services\DatabaseAnonymizer;

/**
 * The same small database the viewer tests use, so a row can be recognized
 * when it should be there and missed when it should have been replaced.
 */
function anonymizerDatabase(): void
{
    config()->set('database.connections.anonymizer', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    config()->set('database.default', 'anonymizer');
    DB::purge('anonymizer');

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->rememberToken();
        $table->text('bio')->nullable();
        $table->json('settings')->nullable();
        $table->binary('avatar')->nullable();
    });

    DB::table('users')->insert([
        ['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'hash-of-ada-s3cret', 'remember_token' => 'remember-ada-token', 'bio' => 'Machines', 'settings' => '{"theme":"dark","beta":true}', 'avatar' => null],
        ['name' => 'Grace Hopper', 'email' => 'grace@example.test', 'password' => 'hash-of-grace-s3cret', 'remember_token' => null, 'bio' => null, 'settings' => null, 'avatar' => null],
        ['name' => 'Alan Turing', 'email' => 'alan@example.test', 'password' => 'hash-of-alan-s3cret', 'remember_token' => null, 'bio' => null, 'settings' => null, 'avatar' => null],
    ]);
}

/**
 * @return array<string, string>
 */
function anonymizedZip(string $bytes): array
{
    $file = (string) tempnam(sys_get_temp_dir(), 'lp-anon-');
    file_put_contents($file, $bytes);
    $zip = new ZipArchive;
    $files = [];

    try {
        expect($zip->open($file, ZipArchive::CHECKCONS))->toBeTrue();

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            $files[(string) $name] = (string) $zip->getFromIndex($index);
        }
    } finally {
        $zip->close();
        @unlink($file);
    }

    return $files;
}

it('replaces a personal value with the same fake every time and leaves structure alone', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $anonymizer = app(DatabaseAnonymizer::class);

    expect($anonymizer->active())->toBeFalse()
        ->and($anonymizer->required())->toBeFalse()
        ->and($anonymizer->apply('users', 'email', 'ada@example.test', false, false, false)['value'])->toBe('ada@example.test');

    $anonymizer->engage(true);

    $once = $anonymizer->apply('users', 'email', 'ada@example.test', false, false, false, 'varchar(255)');
    $twice = $anonymizer->apply('users', 'email', 'ada@example.test', false, false, false, 'varchar(255)');
    $other = $anonymizer->apply('users', 'email', 'grace@example.test', false, false, false, 'varchar(255)');

    expect($once['masked'])->toBeFalse()
        ->and($once['value'])->toBe($twice['value'])
        ->and($once['value'])->not->toBe('ada@example.test')
        ->and($once['value'])->toMatch('/^[a-z0-9._-]+\.[0-9a-f]{8}@example\.(com|net|org)$/')
        ->and($other['value'])->not->toBe($once['value'])
        ->and($anonymizer->apply('users', 'id', 1, false, true, false, 'integer')['value'])->toBe(1)
        ->and($anonymizer->apply('users', 'user_id', 4, false, false, false, 'integer')['value'])->toBe(4)
        ->and($anonymizer->apply('users', 'created_at', '2026-01-02 03:04:05', false, false, false, 'datetime')['value'])->toBe('2026-01-02 03:04:05')
        ->and($anonymizer->apply('users', 'status', 'active', false, false, false, 'varchar(20)')['value'])->toBe('active')
        ->and($anonymizer->apply('studios', 'brand_color', '#1f6f5c', false, false, false, 'varchar')['value'])->toBe('#1f6f5c')
        ->and($anonymizer->apply('users', 'timezone', 'Europe/Rome', false, false, false, 'varchar')['value'])->toBe('Europe/Rome')
        ->and($anonymizer->apply('users', 'password', 'hash-of-ada-s3cret', true, false, false)['masked'])->toBeTrue()
        ->and($anonymizer->apply('users', 'avatar', "\x89PNG", false, false, true, 'blob')['masked'])->toBeTrue();

    $settings = $anonymizer->apply('users', 'settings', '{"theme":"dark","beta":true,"owner":"ada@example.test"}', false, false, false, 'json')['value'];

    expect($settings)->toBeString()
        ->and($settings)->not->toContain('"dark"')
        ->and($settings)->not->toContain('ada@example.test')
        ->and($settings)->toContain('"beta":true')
        ->and(json_decode((string) $settings, true))->toHaveKeys(['theme', 'beta', 'owner']);
});

it('writes nothing a dictionary could link back to the person, and keys it to the application', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $anonymizer = app(DatabaseAnonymizer::class);
    $anonymizer->engage(true);
    $email = 'ada@example.test';
    $made = (string) $anonymizer->apply('users', 'email', $email, false, false, false, 'varchar(255)')['value'];

    // No plain hash of the value — of any common shape — is in the replacement.
    foreach ([$email, 'users|email|'.$email, "users\0email\0".$email] as $input) {
        foreach (['sha256', 'sha1', 'md5'] as $algo) {
            expect($made)->not->toContain(substr(hash($algo, $input), 0, 8));
        }
    }

    // Another install — another APP_KEY — gives another replacement.
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('b', 32)));
    app()->forgetScopedInstances();
    $elsewhere = app(DatabaseAnonymizer::class);
    $elsewhere->engage(true);

    expect($elsewhere->apply('users', 'email', $email, false, false, false, 'varchar(255)')['value'])->not->toBe($made);
});

it('writes a value the column can take back: dates for dates, digits for numbers, never longer than the column', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $anonymizer = app(DatabaseAnonymizer::class);
    $anonymizer->engage(true);

    $birth = (string) $anonymizer->apply('people', 'date_of_birth', '1984-06-21', false, false, false, 'date')['value'];
    $year = (int) substr($birth, 0, 4);

    expect($birth)->toMatch('/^\d{4}-\d{2}-\d{2}$/')->not->toBe('1984-06-21')
        ->and($year)->toBeGreaterThanOrEqual((int) date('Y') - 91)->toBeLessThanOrEqual((int) date('Y') - 17)
        ->and($anonymizer->apply('people', 'phone', '+39 333 123 4567', false, false, false, 'varchar(32)')['value'])->toBeString()
        ->and($anonymizer->apply('people', 'mobile_number', 3331234567, false, true, false, 'bigint')['value'])->toMatch('/^[1-9]\d{9}$/')
        ->and($anonymizer->apply('people', 'tax_code', 'RSSMRA80A01H501U', false, false, false, 'char(16)')['value'])->toMatch('/^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]$/')
        ->and($anonymizer->apply('people', 'role', 'admin', false, false, false, "enum('admin','member')")['value'])->toBe('admin')
        ->and($anonymizer->apply('people', 'is_vip', 1, false, true, false, 'tinyint(1)')['value'])->toBe(1);

    $short = (string) $anonymizer->apply('people', 'name', 'Ada Lovelace', false, false, false, 'varchar(6)')['value'];
    $bio = (string) $anonymizer->apply('people', 'bio', str_repeat('Engines and numbers. ', 10), false, false, false, 'text')['value'];

    expect(mb_strlen($short))->toBeLessThanOrEqual(6)
        ->and($short)->not->toBe('Ada Lo')
        ->and($bio)->not->toContain('Engines and numbers')
        ->and(mb_strlen($bio))->toBeGreaterThan(60);
});

it('keeps the tables of the framework working and hides what queues and sessions carry', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $anonymizer = app(DatabaseAnonymizer::class);
    $anonymizer->engage(true);

    expect($anonymizer->apply('migrations', 'migration', '2026_01_01_000000_create_users_table', false, false, false, 'varchar(255)')['value'])->toBe('2026_01_01_000000_create_users_table')
        ->and($anonymizer->apply('failed_jobs', 'payload', '{"user":"ada@example.test"}', false, false, false, 'longtext')['masked'])->toBeTrue()
        ->and($anonymizer->apply('jobs', 'payload', '{"user":"ada@example.test"}', false, false, false, 'longtext')['masked'])->toBeTrue()
        ->and($anonymizer->apply('sessions', 'payload', 'serialized', false, false, false, 'longtext')['masked'])->toBeTrue()
        ->and($anonymizer->apply('sessions', 'ip_address', '203.0.113.7', false, false, false, 'varchar(45)')['value'])->toMatch('/^\d+\.\d+\.\d+\.\d+$/')->not->toBe('203.0.113.7')
        ->and($anonymizer->apply('password_reset_tokens', 'email', 'ada@example.test', false, false, false, 'varchar(255)')['value'])->not->toBe('ada@example.test');
});

it('writes the replacements in the locale the admin picks, with that locale\'s own formatters', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    anonymizerDatabase();

    $anonymizer = app(DatabaseAnonymizer::class);

    expect($anonymizer->locale())->toBe('en_US')
        ->and($anonymizer->locales())->toHaveKeys(['it_IT', 'en_US', 'fr_FR', 'de_DE'])
        ->and($anonymizer->localFormatters('it_IT'))->toHaveKeys(['taxId', 'vatId'])
        ->and($anonymizer->localFormatters('fr_FR'))->toHaveKey('nir')
        ->and($anonymizer->localFormatters('en_US'))->not->toHaveKey('taxId');

    $this->get('/larapilot/anonymization')->assertOk()
        ->assertSee('Language of the replacements', false)
        ->assertSee('<optgroup label="Person">', false)
        ->assertSee('<optgroup label="Internet">', false)
        ->assertSee('value="it_IT"', false)
        ->assertDontSee('Only in it_IT', false);

    dashboardPost('/larapilot/anonymization/locale', ['locale' => 'xx_XX'])->assertSessionHas('larapilot_error');
    dashboardPost('/larapilot/anonymization/locale', ['locale' => 'it_IT'])->assertSessionHas('larapilot_success');

    $this->get('/larapilot/anonymization')->assertOk()
        ->assertSee('<optgroup label="Only in it_IT">', false)
        ->assertSee('value="taxId"', false);

    dashboardPost('/larapilot/anonymization', ['table' => 'users', 'columns' => ['bio' => 'taxId']])->assertSessionHas('larapilot_success');

    $map = (string) file_get_contents(app(DatabaseAnonymizer::class)->path());
    expect($map)->toContain('locale: it_IT')->toContain('bio: taxId');

    app()->forgetScopedInstances();
    $italian = app(DatabaseAnonymizer::class);
    $italian->engage(true);

    expect($italian->apply('users', 'bio', 'Machines', false, false, false, 'text')['value'])->toMatch('/^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]$/');

    // Back to a locale without that formatter: the line is dropped, the default applies again.
    dashboardPost('/larapilot/anonymization/locale', ['locale' => 'en_US'])->assertSessionHas('larapilot_success');
    expect((string) file_get_contents(app(DatabaseAnonymizer::class)->path()))->toContain('locale: en_US')->not->toContain('taxId');
});

it('offers only keep or hide for an enumeration or a flag', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    anonymizerDatabase();
    Schema::create('memberships', function (Blueprint $table): void {
        $table->id();
        $table->boolean('active')->default(true);
        $table->string('plan');
    });

    dashboardPost('/larapilot/anonymization', ['table' => 'memberships', 'columns' => ['active' => 'word']])
        ->assertSessionHas('larapilot_error');
    dashboardPost('/larapilot/anonymization', ['table' => 'memberships', 'columns' => ['active' => 'mask', 'plan' => 'keep']])
        ->assertSessionHas('larapilot_success');

    expect((string) file_get_contents(app(DatabaseAnonymizer::class)->path()))->toContain('active: mask')->toContain('plan: keep');
});

it('saves one table of the map and refuses a formatter it does not know', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    anonymizerDatabase();

    $this->get('/larapilot/anonymization')->assertOk()
        ->assertSee('Anonymization')
        ->assertSee('>Anonymization</a>', false)
        ->assertSee('users')
        ->assertSee('Always hidden')
        ->assertSee('Default — Email', false)
        ->assertSee('.larapilot/anonymization.yaml', false);

    dashboardPost('/larapilot/anonymization', [
        'table' => 'users',
        'columns' => ['name' => 'keep', 'email' => '', 'no-such-column' => 'word'],
    ])->assertSessionHas('larapilot_success');

    $path = app(DatabaseAnonymizer::class)->path();
    $yaml = (string) file_get_contents($path);

    expect($yaml)->toContain('name: keep')->not->toContain('email:')->not->toContain('no-such-column')
        ->and(array_column(app(AuthAuditService::class)->entries(), 'event'))->toContain('anonymization.saved');

    dashboardPost('/larapilot/anonymization', [
        'table' => 'users',
        'columns' => ['email' => 'system'],
    ])->assertSessionHas('larapilot_error');

    expect((string) file_get_contents($path))->toContain('name: keep')->not->toContain('system');

    dashboardPost('/larapilot/anonymization', [
        'table' => 'not_a_table',
        'columns' => ['name' => 'keep'],
    ])->assertSessionHas('larapilot_error');

    dashboardUser('dev', 'correct-horse-battery', 'dev');
    sharedHost();
    signInDashboard('dev')->assertRedirect('/larapilot');
    $this->get('/larapilot/anonymization')->assertForbidden();
    dashboardPost('/larapilot/anonymization', [
        'table' => 'users',
        'columns' => ['email' => 'keep'],
    ])->assertForbidden();

    expect((string) file_get_contents($path))->toContain('name: keep')->not->toContain('email:');
});

it('anonymizes the rows a developer sees in production and a download asked to anonymize', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    anonymizerDatabase();

    $this->get('/larapilot/database/users')->assertOk()
        ->assertSee('ada@example.test')
        ->assertSee('Ada Lovelace')
        ->assertSee('Preview the anonymized rows', false)
        ->assertDontSee('hash-of-ada-s3cret')
        ->assertDontSee('remember-ada-token');

    $this->get('/larapilot/database/users?anonymize=1')->assertOk()
        ->assertSee('These rows are anonymized', false)
        ->assertSee('3 rows', false)
        ->assertDontSee('rows found', false)
        ->assertDontSee('ada@example.test')
        ->assertDontSee('Ada Lovelace')
        ->assertDontSee('Machines')
        ->assertDontSee('{"theme":"dark"', false)
        ->assertDontSee('hash-of-ada-s3cret');

    $this->get('/larapilot/database/users?anonymize=1&q=Ada')->assertOk()
        ->assertSee('3 rows', false)
        ->assertDontSee('ada@example.test');

    $this->get('/larapilot/database/users?anonymize=1&where=email&is=ada@example.test')->assertOk()
        ->assertSee('3 rows', false)
        ->assertDontSee('1 row', false);

    $sql = $this->get('/larapilot/database-export/sql?anonymize=1')->assertOk()->streamedContent();

    expect($sql)->not->toContain('ada@example.test')
        ->not->toContain('Ada Lovelace')
        ->not->toContain('hash-of-ada-s3cret')
        ->not->toContain('remember-ada-token')
        ->toContain('anonymized');

    expect($this->get('/larapilot/database-export/sql')->streamedContent())
        ->toContain('ada@example.test')
        ->not->toContain('Rows:        anonymized');

    $seeders = implode("\n", anonymizedZip($this->get('/larapilot/database-export/seeders?anonymize=1')->assertOk()->streamedContent()));

    expect($seeders)->not->toContain('ada@example.test')
        ->not->toContain('Ada Lovelace')
        ->not->toContain('hash-of-ada-s3cret')
        ->toContain('anonymized');

    $migrations = anonymizedZip($this->get('/larapilot/database-export/migrations?anonymize=1')->assertOk()->streamedContent());

    expect($migrations)->toHaveKey('database/migrations/ANONYMIZATION.txt')
        ->and(implode("\n", $migrations))->not->toContain('ada@example.test');

    expect(anonymizedZip($this->get('/larapilot/database-export/migrations')->assertOk()->streamedContent()))
        ->not->toHaveKey('database/migrations/ANONYMIZATION.txt');

    dashboardPost('/larapilot/anonymization', [
        'table' => 'users',
        'columns' => ['name' => 'keep'],
    ])->assertSessionHas('larapilot_success');

    $this->get('/larapilot/database/users?anonymize=1')->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertDontSee('ada@example.test');

    dashboardUser('dev', 'correct-horse-battery', 'dev');
    dashboardUser('root', 'correct-horse-battery', 'admin');
    forcedProduction();

    signInDashboard('dev')->assertRedirect('/larapilot/two-factor/setup');
    $this->get('/larapilot/two-factor/setup')->assertOk();
    dashboardPost('/larapilot/two-factor/setup', ['code' => totpCode(totpSetupSecret())])->assertRedirect('/larapilot');

    $this->get('/larapilot/database')->assertOk()->assertSee('These rows are anonymized', false);
    $this->get('/larapilot/database/users')->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertDontSee('ada@example.test')
        ->assertDontSee('Preview the anonymized rows', false);
    $this->get('/larapilot/database/users?q=ada@example.test')->assertOk()
        ->assertSee('3 rows', false)
        ->assertDontSee('ada@example.test');
    $this->get('/larapilot/anonymization')->assertForbidden();

    expect($this->get('/larapilot/database-export/sql')->streamedContent())->not->toContain('ada@example.test');

    dashboardPost('/larapilot/logout');

    signInDashboard('root')->assertRedirect('/larapilot/two-factor/setup');
    $this->get('/larapilot/two-factor/setup')->assertOk();
    dashboardPost('/larapilot/two-factor/setup', ['code' => totpCode(totpSetupSecret())])->assertRedirect('/larapilot');

    $this->get('/larapilot/database/users')->assertOk()
        ->assertSee('ada@example.test')
        ->assertSee('Ada Lovelace');
    $this->get('/larapilot/anonymization')->assertOk();
});
