<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DashboardAuthService;
use Larapilot\Services\DatabaseViewerService;

/**
 * An in-memory SQLite database with the shape of a small application:
 * users with credentials, posts that point at them, and a view.
 */
function viewerDatabase(string $prefix = ''): void
{
    config()->set('database.connections.viewer', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => $prefix,
        'foreign_key_constraints' => true,
    ]);
    config()->set('database.default', 'viewer');
    DB::purge('viewer');

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

    Schema::create('posts', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->constrained();
        $table->string('title');
        $table->boolean('published')->default(false);
    });

    DB::table('users')->insert([
        ['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'hash-of-ada-s3cret', 'remember_token' => 'remember-ada-token', 'bio' => null, 'settings' => '{"theme":"dark","beta":true}', 'avatar' => "\x89PNG\r\n\x1a\n\x00\x00"],
        ['name' => 'Grace Hopper', 'email' => 'grace@example.test', 'password' => 'hash-of-grace-s3cret', 'remember_token' => null, 'bio' => str_repeat('Compilers. ', 40), 'settings' => null, 'avatar' => null],
        ['name' => 'Alan Turing', 'email' => 'alan@example.test', 'password' => 'hash-of-alan-s3cret', 'remember_token' => null, 'bio' => 'Machines', 'settings' => null, 'avatar' => null],
    ]);

    DB::table('posts')->insert([
        ['user_id' => 1, 'title' => 'Notes on the Analytical Engine', 'published' => true],
        ['user_id' => 2, 'title' => 'The first bug', 'published' => false],
    ]);

    DB::statement('create view '.$prefix.'published_posts as select id, title from '.$prefix.'posts where published = 1');
}

it('lists the tables and views of the connection in .env', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    viewerDatabase();

    $this->get('/larapilot/database')
        ->assertOk()
        ->assertSee('Database', false)
        ->assertSee('SQLite', false)
        ->assertSee('Connection <code>viewer</code>', false)
        ->assertSee('/larapilot/database/users', false)
        ->assertSee('/larapilot/database/posts', false)
        ->assertSee('/larapilot/database/published_posts', false)
        ->assertSee('2 tables', false)
        ->assertSee('1 view', false);

    $html = $this->get('/larapilot')->assertOk()->getContent();

    expect($html)->toContain('>Database</a>')
        ->and(strpos($html, '>File manager</a>'))->toBeLessThan(strpos($html, '>Database</a>'))
        ->and(strpos($html, '>Database</a>'))->toBeLessThan(strpos($html, '>Git</a>'));
});

it('shows the rows of a table with the credentials hidden', function (): void {
    viewerDatabase();

    $this->get('/larapilot/database/users')
        ->assertOk()
        ->assertSee('Ada Lovelace', false)
        ->assertSee('grace@example.test', false)
        ->assertSee('3 rows', false)
        ->assertSee('Rows 1–3 of 3', false)
        ->assertSee(DatabaseViewerService::MASK, false)
        ->assertSee('NULL', false)
        // binary as hex, a long value cut in the grid
        ->assertSee('0x89504E470D0A1A0A0000', false)
        ->assertSee('Compilers. Compilers.', false)
        ->assertDontSee('hash-of-ada-s3cret', false)
        ->assertDontSee('remember-ada-token', false);
});

it('pages, sorts, searches, and filters the rows', function (): void {
    viewerDatabase();
    config()->set('larapilot.database_viewer.per_page', 2);

    $this->get('/larapilot/database/users')
        ->assertOk()
        ->assertSee('Rows 1–2 of 3', false)
        ->assertSee('Page 1 of 2', false)
        ->assertDontSee('Alan Turing', false);

    $this->get('/larapilot/database/users?page=2')
        ->assertOk()
        ->assertSee('Rows 3–3 of 3', false)
        ->assertSee('Alan Turing', false);

    // A page past the end shows the last one.
    $this->get('/larapilot/database/users?page=99')->assertOk()->assertSee('Alan Turing', false);

    $html = $this->get('/larapilot/database/users?sort=name&dir=desc')->assertOk()->getContent();
    expect(strpos($html, 'Grace Hopper'))->toBeLessThan(strpos($html, 'Alan Turing'))
        ->and($html)->not->toContain('Ada Lovelace');

    $this->get('/larapilot/database/users?q=hopper')
        ->assertOk()
        ->assertSee('Grace Hopper', false)
        ->assertDontSee('Ada Lovelace', false)
        ->assertSee('1 row found', false);

    $this->get('/larapilot/database/posts?where=user_id&is=2')
        ->assertOk()
        ->assertSee('The first bug', false)
        ->assertDontSee('Notes on the Analytical Engine', false)
        ->assertSee('user_id = 2', false);
});

it('never searches, sorts, or filters on a hidden column', function (): void {
    viewerDatabase();

    // Probing a password by search finds nothing.
    $this->get('/larapilot/database/users?q=s3cret')
        ->assertOk()
        ->assertSee('No row matches.', false);

    // A filter on it is ignored: every row comes back.
    $this->get('/larapilot/database/users?where=password&is=hash-of-ada-s3cret')
        ->assertOk()
        ->assertSee('3 rows', false)
        ->assertDontSee('password = ', false);

    // A sort on it is ignored: the primary key order stays.
    $html = $this->get('/larapilot/database/users?sort=password&dir=desc')->assertOk()->getContent();
    expect(strpos($html, 'Ada Lovelace'))->toBeLessThan(strpos($html, 'Alan Turing'));
});

it('links a foreign key to the row it points at', function (): void {
    viewerDatabase();

    $this->get('/larapilot/database/posts')
        ->assertOk()
        ->assertSee('/larapilot/database/users?where=id&amp;is=1', false);

    $this->get('/larapilot/database/users?where=id&is=1')
        ->assertOk()
        ->assertSee('Ada Lovelace', false)
        ->assertDontSee('Grace Hopper', false);
});

it('describes the structure of a table', function (): void {
    viewerDatabase();

    $this->get('/larapilot/database/posts?tab=structure')
        ->assertOk()
        ->assertSee('Columns', false)
        ->assertSee('user_id', false)
        ->assertSee('Primary', false)
        ->assertSee('Foreign keys', false)
        ->assertSee('→ users.id', false)
        ->assertSee('/larapilot/database/users', false);

    $this->get('/larapilot/database/users?tab=structure')
        ->assertOk()
        ->assertSee('users_email_unique', false)
        ->assertSee('Values hidden', false);
});

it('reads a view like a table', function (): void {
    viewerDatabase();

    $this->get('/larapilot/database/published_posts')
        ->assertOk()
        ->assertSee('View', false)
        ->assertSee('Notes on the Analytical Engine', false)
        ->assertDontSee('The first bug', false);
});

it('answers 404 for a table the connection does not hold', function (): void {
    viewerDatabase();

    $this->get('/larapilot/database/missing')->assertNotFound();
    $this->get('/larapilot/database/users;drop table users')->assertNotFound();
    $this->get('/larapilot/database/sqlite_master')->assertNotFound();

    expect(Schema::hasTable('users'))->toBeTrue();
});

it('reads a connection with a table prefix', function (): void {
    viewerDatabase('app_');

    $this->get('/larapilot/database')
        ->assertOk()
        ->assertSee('/larapilot/database/users', false)
        ->assertDontSee('/larapilot/database/app_users', false);

    $this->get('/larapilot/database/users')
        ->assertOk()
        ->assertSee('Ada Lovelace', false);
});

it('says why when the database cannot be read, without its password', function (): void {
    config()->set('database.connections.unreachable', [
        'driver' => 'pgsql',
        'host' => '127.0.0.1',
        'port' => 1,
        'database' => 'shop',
        'username' => 'shop',
        'password' => 'hunter2-do-not-show',
        'url' => 'pgsql://shop:hunter2-do-not-show@127.0.0.1:1/shop',
    ]);
    config()->set('larapilot.database_viewer.connection', 'unreachable');

    $this->get('/larapilot/database')
        ->assertOk()
        ->assertSee('The database cannot be read', false)
        ->assertSee('PostgreSQL', false)
        ->assertSee('127.0.0.1:1', false)
        ->assertDontSee('hunter2-do-not-show', false);

    $this->get('/larapilot/database/users')
        ->assertOk()
        ->assertSee('The database cannot be read', false);
});

it('shows a cell the way its value reads', function (): void {
    $viewer = app(DatabaseViewerService::class);

    expect($viewer->cell(null))->toMatchArray(['kind' => 'null'])
        ->and($viewer->cell('secret', masked: true))->toMatchArray(['kind' => 'masked', 'text' => DatabaseViewerService::MASK])
        ->and($viewer->cell(true))->toMatchArray(['kind' => 'bool', 'text' => 'true'])
        ->and($viewer->cell(42))->toMatchArray(['kind' => 'number', 'text' => '42'])
        ->and($viewer->cell('12.50', numeric: true))->toMatchArray(['kind' => 'number'])
        ->and($viewer->cell("\xff\xfe\x00"))->toMatchArray(['kind' => 'binary', 'text' => '0xFFFE00'])
        ->and($viewer->cell("two\nlines")['text'])->toBe('two lines')
        ->and($viewer->cell('{"a":1}')['full'])->toBe("{\n    \"a\": 1\n}")
        ->and(mb_strlen($viewer->cell(str_repeat('x', 500))['text']))->toBe(121);

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, 'from a stream');
    rewind($stream);

    expect($viewer->cell($stream)['text'])->toBe('from a stream')
        ->and($viewer->formatBytes(512))->toBe('512 B')
        ->and($viewer->formatBytes(20480))->toBe('20 KB')
        ->and($viewer->formatBytes(1536))->toBe('1.5 KB')
        ->and($viewer->formatBytes(104857600))->toBe('100 MB');
});

it('stays behind the dashboard sign-in on a shared environment', function (): void {
    viewerDatabase();
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->app['env'] = 'staging';

    $this->get('/larapilot')->assertOk()->assertDontSee('>Database</a>', false);
    $this->get('/larapilot/database')->assertNotFound();
    $this->get('/larapilot/database/users')->assertNotFound();

    app(DashboardAuthService::class)->setUser('andrea', 's3cret-pass');
    app(ConfigService::class)->updateSettings(['dashboard_auth' => 'YES']);

    $this->get('/larapilot/database/users')->assertStatus(401);

    $headers = ['Authorization' => 'Basic '.base64_encode('andrea:s3cret-pass')];

    $this->get('/larapilot', $headers)->assertOk()->assertSee('>Database</a>', false);
    $this->get('/larapilot/database/users', $headers)->assertOk()->assertSee('Ada Lovelace', false);
});

it('hides the database viewer in production and when it is switched off', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    viewerDatabase();

    config()->set('larapilot.database_viewer.enabled', false);

    $this->get('/larapilot/database')->assertNotFound();
    $this->get('/larapilot/database/users')->assertNotFound();
    $this->get('/larapilot')->assertOk()->assertDontSee('>Database</a>', false);

    config()->set('larapilot.database_viewer.enabled', true);
    $this->app['env'] = 'production';

    $this->get('/larapilot/database')->assertNotFound();
    $this->get('/larapilot/database/users')->assertNotFound();
});

it('downloads the database as SQL that builds it again', function (): void {
    viewerDatabase();
    DB::table('posts')->delete();
    DB::table('posts')->insert(['id' => 7, 'user_id' => 1, 'title' => "It's \"quoted\"; with a \\ backslash", 'published' => true]);

    $response = $this->get('/larapilot/database.sql')->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('.sql')
        ->and($response->headers->get('Content-Type'))->toContain('application/sql');

    $sql = $response->streamedContent();

    expect($sql)->toContain('-- Larapilot database dump')
        ->toContain('Driver:      SQLite')
        ->toContain('Credentials: left out')
        ->toContain('users.password')
        ->toContain('users.remember_token')
        ->toContain('CREATE TABLE "users"')
        ->toContain('INSERT INTO "users"')
        ->toContain('Ada Lovelace')
        ->toContain("X'89504E470D0A1A0A0000'")
        ->toContain('-- Dump complete.')
        ->not->toContain('hash-of-ada-s3cret')
        ->not->toContain('remember-ada-token');

    // Run the file into an empty database: the same tables, rows, and view come back.
    $copy = new PDO('sqlite::memory:');
    $copy->exec($sql);

    expect((int) $copy->query('select count(*) from users')->fetchColumn())->toBe(3)
        ->and($copy->query('select name from users where id = 2')->fetchColumn())->toBe('Grace Hopper')
        ->and($copy->query('select password from users where id = 1')->fetchColumn())->toBe('')
        ->and($copy->query('select remember_token from users where id = 1')->fetchColumn())->toBeNull()
        ->and($copy->query('select avatar from users where id = 1')->fetchColumn())->toBe("\x89PNG\r\n\x1a\n\x00\x00")
        ->and($copy->query('select title from posts where id = 7')->fetchColumn())->toBe("It's \"quoted\"; with a \\ backslash")
        ->and($copy->query('select title from published_posts')->fetchColumn())->toBe("It's \"quoted\"; with a \\ backslash")
        ->and($copy->query("select seq from sqlite_sequence where name = 'posts'")->fetchColumn())->toEqual(7)
        ->and($copy->query("select count(*) from sqlite_master where name = 'users_email_unique'")->fetchColumn())->toEqual(1)
        ->and($copy->query('select "table" from pragma_foreign_key_list(\'posts\')')->fetchColumn())->toBe('users');

    // A second run replaces what the first one made.
    $copy->exec($sql);

    expect((int) $copy->query('select count(*) from users')->fetchColumn())->toBe(3);
});

it('puts the credentials in the dump only when asked, and only on a developer machine', function (): void {
    viewerDatabase();

    $sql = $this->get('/larapilot/database.sql?credentials=1')->assertOk()->streamedContent();

    expect($sql)->toContain('hash-of-ada-s3cret')
        ->toContain('remember-ada-token')
        ->toContain('Credentials: included');

    $this->get('/larapilot/database')
        ->assertOk()
        ->assertSee('Download SQL', false)
        ->assertSee('Include passwords and tokens', false);

    $this->artisan('larapilot:install')->assertSuccessful();
    app(DashboardAuthService::class)->setUser('andrea', 's3cret-pass');
    app(ConfigService::class)->updateSettings(['dashboard_auth' => 'YES']);
    $this->app['env'] = 'staging';

    $headers = ['Authorization' => 'Basic '.base64_encode('andrea:s3cret-pass')];

    $this->get('/larapilot/database.sql')->assertStatus(401);

    $sql = $this->get('/larapilot/database.sql?credentials=1', $headers)->assertOk()->streamedContent();

    expect($sql)->not->toContain('hash-of-ada-s3cret')
        ->toContain('Credentials: left out');

    $this->get('/larapilot/database', $headers)
        ->assertOk()
        ->assertSee('Download SQL', false)
        ->assertDontSee('Include passwords and tokens', false)
        ->assertSee('Passwords and tokens are left out on a shared host.', false);
});

it('offers no dump when the database cannot be read or the page is off', function (): void {
    config()->set('database.connections.unreachable', [
        'driver' => 'pgsql',
        'host' => '127.0.0.1',
        'port' => 1,
        'database' => 'shop',
        'username' => 'shop',
        'password' => 'secret',
    ]);
    config()->set('larapilot.database_viewer.connection', 'unreachable');

    $this->get('/larapilot/database')->assertOk()->assertDontSee('Download SQL', false);
    $this->get('/larapilot/database.sql')
        ->assertRedirect('/larapilot/database')
        ->assertSessionHas('larapilot_error');

    config()->set('larapilot.database_viewer.enabled', false);

    $this->get('/larapilot/database.sql')->assertNotFound();
});
