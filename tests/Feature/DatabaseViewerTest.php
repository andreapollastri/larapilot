<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DashboardAuthService;
use Larapilot\Services\DatabaseDiagramPdfWriter;
use Larapilot\Services\DatabaseDiagramService;
use Larapilot\Services\DatabaseMigrationService;
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

it('draws the database as a diagram, a line for each foreign key', function (): void {
    viewerDatabase();

    $this->get('/larapilot/database')
        ->assertOk()
        ->assertSee('/larapilot/database?view=diagram', false)
        ->assertDontSee('data-db-erd-svg', false);

    $html = $this->get('/larapilot/database?view=diagram')
        ->assertOk()
        ->assertSee('data-db-erd-svg', false)
        ->assertSee('data-table="users"', false)
        ->assertSee('data-table="posts"', false)
        ->assertSee('data-table="published_posts"', false)
        ->assertSee('data-from="posts" data-to="users"', false)
        ->assertSee('posts.user_id → users.id', false)
        ->assertSee('/larapilot/database/posts', false)
        ->assertSee('remember_token', false)
        ->assertSee('1 foreign key', false)
        // the structure, never a row
        ->assertDontSee('Ada Lovelace', false)
        ->assertDontSee('hash-of-ada-s3cret', false)
        ->getContent();

    expect(substr_count($html, 'class="db-erd-edge"'))->toBe(1);

    // Keys only: the columns no key touches are counted, not drawn.
    $this->get('/larapilot/database?view=diagram&columns=keys')
        ->assertOk()
        ->assertSee('user_id', false)
        ->assertSee('+ 7 columns', false)
        ->assertDontSee('remember_token', false);
});

it('stands a table to the right of the ones it points at', function (): void {
    $table = static fn (string $key, array $columns, array $points = []): array => [
        'key' => $key,
        'name' => $key,
        'kind' => 'table',
        'columns' => array_map(static fn (string $column): array => [
            'name' => $column,
            'type' => 'integer',
            'primary' => $column === 'id',
            'foreign' => isset($points[$column]),
        ], $columns),
        'foreign_keys' => array_map(static fn (string $column, string $to): array => [
            'columns' => [$column],
            'key' => $to,
            'foreign_columns' => ['id'],
        ], array_keys($points), array_values($points)),
    ];

    $diagram = app(DatabaseDiagramService::class)->layout([
        $table('comments', ['id', 'post_id', 'user_id', 'body'], ['post_id' => 'posts', 'user_id' => 'users']),
        $table('posts', ['id', 'user_id', 'title'], ['user_id' => 'users']),
        $table('users', ['id', 'name', 'team_id'], ['team_id' => 'teams']),
        // a circle, and a table that points at itself: neither goes round for ever
        $table('teams', ['id', 'owner_id', 'parent_id'], ['owner_id' => 'users', 'parent_id' => 'teams']),
        $table('cache', ['key', 'value']),
        $table('orphans', ['id', 'gone_id'], ['gone_id' => 'not_in_the_database']),
    ]);

    $nodes = array_column($diagram['nodes'], null, 'key');

    expect($nodes['posts']['x'])->toBeGreaterThan($nodes['users']['x'] + $nodes['users']['w'])
        ->and($nodes['comments']['x'])->toBeGreaterThan($nodes['posts']['x'] + $nodes['posts']['w'])
        ->and($diagram['edges'])->toHaveCount(6)
        ->and($diagram['related'])->toBe(4)
        // no foreign key reaches them: under the rest, with their own heading
        ->and($nodes['cache']['y'])->toBeGreaterThan($nodes['comments']['y'] + $nodes['comments']['h'])
        ->and($nodes['orphans']['y'])->toBe($nodes['cache']['y'])
        ->and($diagram['labels'][0]['text'])->toBe('No foreign key');

    // Every box is inside the drawing and none lies over another.
    foreach ($nodes as $a) {
        expect($a['x'] + $a['w'])->toBeLessThanOrEqual($diagram['width'])
            ->and($a['y'] + $a['h'])->toBeLessThanOrEqual($diagram['height']);

        foreach ($nodes as $b) {
            if ($a['key'] !== $b['key']) {
                $apart = $b['x'] >= $a['x'] + $a['w'] || $a['x'] >= $b['x'] + $b['w'] || $b['y'] >= $a['y'] + $a['h'] || $a['y'] >= $b['y'] + $b['h'];

                expect($apart)->toBeTrue($a['key'].' lies over '.$b['key']);
            }
        }
    }

    // A line leaves the foreign key's own row and ends on the row it references.
    $edge = collect($diagram['edges'])->firstWhere('label', 'posts.user_id → users.id');
    $row = collect($nodes['posts']['rows'])->firstWhere('name', 'user_id');
    $target = collect($nodes['users']['rows'])->firstWhere('name', 'id');

    expect($edge['y'])->toBe($row['y'])
        ->and($edge['x'])->toBe($nodes['posts']['x'])
        ->and($edge['path'])->toEndWith(($nodes['users']['x'] + $nodes['users']['w']).' '.$target['y']);
});

it('does not draw a database of too many tables', function (): void {
    viewerDatabase();

    $viewer = Mockery::mock(DatabaseViewerService::class);
    $viewer->shouldReceive('overview')->andReturn([
        'connection' => app(DatabaseViewerService::class)->describe(),
        'objects' => array_fill(0, DatabaseDiagramService::LIMIT + 1, ['key' => 't', 'name' => 't', 'kind' => 'table']),
        'tables' => DatabaseDiagramService::LIMIT + 1,
        'views' => 0,
        'size_label' => null,
        'schemas' => false,
        'error' => null,
    ]);
    $viewer->shouldNotReceive('schema');

    expect((new DatabaseDiagramService($viewer))->diagram()['diagram'])->toBeNull();
});

/**
 * What a PDF of the diagram draws, after checking the file holds together:
 * every object where the table at its end says it is.
 */
function diagramDrawing(string $pdf): string
{
    expect($pdf)->toStartWith('%PDF-1.4')->toEndWith("%%EOF\n")
        ->and(preg_match('/startxref\n(\d+)\n%%EOF/', $pdf, $start))->toBe(1)
        ->and(substr($pdf, (int) $start[1], 4))->toBe('xref')
        ->and(preg_match_all('/^(\d{10}) 00000 n $/m', $pdf, $entries))->toBe(7);

    foreach ($entries[1] as $index => $offset) {
        expect(substr($pdf, (int) $offset, strlen(($index + 1).' 0 obj')))->toBe(($index + 1).' 0 obj');
    }

    expect(preg_match('/\/Length (\d+) \/Filter \/FlateDecode >>\nstream\n(.*)\nendstream/s', $pdf, $stream))->toBe(1)
        ->and(strlen($stream[2]))->toBe((int) $stream[1]);

    return (string) gzuncompress($stream[2]);
}

it('downloads the diagram as a PDF of one page', function (): void {
    viewerDatabase();

    $this->get('/larapilot/database?view=diagram')
        ->assertOk()
        ->assertSee('/larapilot/database-diagram.pdf"', false)
        ->assertSee('Download PDF', false);

    $this->get('/larapilot/database?view=diagram&columns=keys')
        ->assertOk()
        ->assertSee('/larapilot/database-diagram.pdf?columns=keys', false);

    $response = $this->get('/larapilot/database-diagram.pdf')->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('-diagram-')->toContain('.pdf');

    $pdf = (string) $response->getContent();
    $drawing = diagramDrawing($pdf);

    expect($pdf)->toContain('/Count 1')->toContain('/BaseFont /Courier')
        ->and($drawing)->toContain('(users) Tj')
        ->toContain('(posts) Tj')
        ->toContain('(user_id) Tj')
        ->toContain('(remember_token) Tj')
        ->toContain('(published_posts) Tj')
        ->toContain('(VIEW) Tj')
        ->toContain('2 tables')
        ->toContain('1 foreign key')
        // one line, with its arrow and the dot where it leaves the column
        ->and(substr_count($drawing, ' c S'))->toBe(1)
        // the structure, never a row
        ->and($drawing)->not->toContain('Ada')->not->toContain('s3cret');

    $drawing = diagramDrawing((string) $this->get('/larapilot/database-diagram.pdf?columns=keys')->assertOk()->getContent());

    expect($drawing)->toContain('(user_id) Tj')
        ->toContain('(+ 7 columns) Tj')
        ->not->toContain('remember_token');
});

it('writes into the PDF only what its fonts can say, on a page a reader can open', function (): void {
    $node = [
        'key' => 'a', 'label' => 'a(b)\\c è 日本', 'kind' => 'table', 'x' => 24, 'y' => 24, 'w' => 200, 'h' => 58, 'head' => 30,
        'rows' => [['name' => 'id', 'label' => 'id', 'type' => 'integer', 'mark' => 'PK', 'foreign' => false, 'y' => 68]],
    ];

    $pdf = app(DatabaseDiagramPdfWriter::class)->write(['width' => 40000, 'height' => 900, 'nodes' => [$node], 'edges' => [], 'labels' => []], 'shop (live)');
    $drawing = diagramDrawing($pdf);

    expect($drawing)->toContain('(a\\(b\\)\\\\c '."\xE8".' ??) Tj')
        ->and($pdf)->toContain('/Title (shop \\(live\\))')
        // a page is at most 200 inches a side
        ->and(preg_match('/\/MediaBox \[0 0 ([\d.]+) ([\d.]+)\]/', $pdf, $box))->toBe(1)
        ->and((float) $box[1])->toBeLessThanOrEqual(14400.0)
        ->and((float) $box[2])->toBeGreaterThan(0.0);
});

it('offers no PDF when there is nothing to draw or the page is off', function (): void {
    config()->set('database.connections.empty', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    config()->set('database.default', 'empty');
    DB::purge('empty');

    $this->get('/larapilot/database-diagram.pdf')
        ->assertRedirect('/larapilot/database')
        ->assertSessionHas('larapilot_error');

    config()->set('larapilot.database_viewer.enabled', false);

    $this->get('/larapilot/database-diagram.pdf')->assertNotFound();
});

it('lists the migrations that ran and the ones still to run', function (): void {
    viewerDatabase();

    $folder = sys_get_temp_dir().'/larapilot-migrations-'.uniqid();
    mkdir($folder);

    foreach (['2024_01_10_090000_create_orders_table', '2024_02_20_090000_add_total_to_orders_table', '2024_03_30_090000_create_invoices_table'] as $name) {
        file_put_contents($folder.'/'.$name.'.php', '<?php // '.$name);
    }

    $migrator = app('migrator');
    $migrator->path($folder);

    try {
        // Before the first `migrate`: no table, so every file is still to run.
        $this->get('/larapilot/database')->assertOk()->assertSee('/larapilot/database?view=migrations', false);

        $this->get('/larapilot/database?view=migrations')
            ->assertOk()
            ->assertSee('Create orders table', false)
            ->assertSee('2024_01_10_090000_create_orders_table', false)
            ->assertSee('is not there yet', false)
            ->assertDontSee('badge-done">Ran<', false);

        $status = app(DatabaseMigrationService::class)->status();

        expect($status['installed'])->toBeFalse()
            ->and($status['ran'])->toBe(0)
            ->and($status['pending'])->toBeGreaterThanOrEqual(3);

        $migrator->getRepository()->createRepository();
        $migrator->getRepository()->log('2024_01_10_090000_create_orders_table', 1);
        $migrator->getRepository()->log('2024_02_20_090000_add_total_to_orders_table', 2);
        $migrator->getRepository()->log('2023_12_01_090000_create_legacy_table', 1);
        $migrator->getRepository()->log('0001_01_01_000009_create_first_table', 1);

        $status = app(DatabaseMigrationService::class)->status();
        $byName = array_column($status['migrations'], null, 'name');

        expect($status['installed'])->toBeTrue()
            ->and($status['error'])->toBeNull()
            ->and($status['ran'])->toBe(2)
            ->and($status['missing'])->toBe(2)
            // Laravel's own are numbered from year 1: an order, not a day
            ->and($byName['0001_01_01_000009_create_first_table'])->toMatchArray(['date' => null, 'title' => 'Create first table'])
            ->and($status['batch'])->toBe(2)
            ->and($byName['2024_01_10_090000_create_orders_table'])->toMatchArray(['state' => 'ran', 'batch' => 1, 'date' => '2024-01-10', 'title' => 'Create orders table'])
            ->and($byName['2024_02_20_090000_add_total_to_orders_table'])->toMatchArray(['state' => 'ran', 'batch' => 2])
            ->and($byName['2024_03_30_090000_create_invoices_table'])->toMatchArray(['state' => 'pending', 'batch' => null])
            ->and($byName['2023_12_01_090000_create_legacy_table'])->toMatchArray(['state' => 'missing', 'batch' => 1, 'path' => null])
            // what is still to run comes first
            ->and($status['migrations'][0]['state'])->toBe('pending')
            ->and(array_search('ran', array_column($status['migrations'], 'state'), true))->toBeGreaterThanOrEqual($status['pending']);

        $html = $this->get('/larapilot/database?view=migrations')
            ->assertOk()
            ->assertSee('badge-done">Ran<', false)
            ->assertSee('badge-in-progress">Pending<', false)
            ->assertSee('Ran · file gone', false)
            ->assertSee('Batch 2', false)
            ->assertSee('as batch 3', false)
            ->assertSee('2 ran', false)
            ->assertDontSee('is not there yet', false)
            ->getContent();

        expect(strpos($html, '2024_03_30_090000_create_invoices_table'))->toBeLessThan(strpos($html, '2024_02_20_090000_add_total_to_orders_table'))
            ->and(strpos($html, '2024_02_20_090000_add_total_to_orders_table'))->toBeLessThan(strpos($html, '2024_01_10_090000_create_orders_table'));

        // Nothing was migrated by looking.
        expect(Schema::hasTable('orders'))->toBeFalse()
            ->and(DB::table('migrations')->count())->toBe(4);
    } finally {
        array_map('unlink', glob($folder.'/*.php') ?: []);
        rmdir($folder);
    }
});

it('offers the migrations of a database with no table yet, and no diagram', function (): void {
    config()->set('database.connections.empty', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    config()->set('database.default', 'empty');
    DB::purge('empty');

    $this->get('/larapilot/database')
        ->assertOk()
        ->assertSee('The database has no tables yet', false)
        ->assertSee('/larapilot/database?view=migrations', false)
        ->assertDontSee('/larapilot/database?view=diagram', false);

    $this->get('/larapilot/database?view=diagram')
        ->assertOk()
        ->assertSee('The database has no tables yet', false);

    $this->get('/larapilot/database?view=migrations')->assertOk();
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

it('keeps a dump restorable when a hidden column is unique', function (): void {
    viewerDatabase();

    // The shape of Sanctum's table: the token is hidden, required, and unique.
    Schema::create('personal_access_tokens', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('token', 64)->unique();
    });

    DB::table('personal_access_tokens')->insert([
        ['name' => 'ci', 'token' => str_repeat('a', 64)],
        ['name' => 'mobile', 'token' => str_repeat('b', 64)],
        ['name' => 'cli', 'token' => str_repeat('c', 64)],
    ]);

    $sql = $this->get('/larapilot/database.sql')->assertOk()->streamedContent();

    expect($sql)->toContain('personal_access_tokens.token')
        ->not->toContain(str_repeat('a', 64));

    $copy = new PDO('sqlite::memory:');
    $copy->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $copy->exec($sql);

    $tokens = $copy->query('select token from personal_access_tokens order by id')->fetchAll(PDO::FETCH_COLUMN);

    expect($tokens)->toHaveCount(3)
        ->and(array_unique($tokens))->toHaveCount(3)
        ->and(implode('', $tokens))->not->toContain('aaaa');
});

it('dumps every row of a table with no primary key, once each', function (): void {
    viewerDatabase();

    // A pivot with only a unique index, and a log with nothing at all.
    Schema::create('role_user', function (Blueprint $table): void {
        $table->unsignedInteger('role_id');
        $table->unsignedInteger('user_id');
        $table->unique(['role_id', 'user_id']);
    });
    Schema::create('events_log', function (Blueprint $table): void {
        $table->string('event');
        $table->text('payload')->nullable();
    });

    $rows = [];

    for ($i = 1; $i <= 2500; $i++) {
        $rows[] = ['role_id' => $i % 7, 'user_id' => $i];
    }

    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('role_user')->insert($chunk);
    }

    DB::table('events_log')->insert([
        ['event' => 'a', 'payload' => '50% off_season'],
        ['event' => 'b', 'payload' => null],
        ['event' => 'a', 'payload' => '50% off_season'],
    ]);

    $sql = $this->get('/larapilot/database.sql')->assertOk()->streamedContent();

    $copy = new PDO('sqlite::memory:');
    $copy->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $copy->exec($sql);

    expect((int) $copy->query('select count(*) from role_user')->fetchColumn())->toBe(2500)
        ->and((int) $copy->query('select count(distinct user_id) from role_user')->fetchColumn())->toBe(2500)
        ->and((int) $copy->query('select count(*) from events_log')->fetchColumn())->toBe(3)
        ->and($sql)->toContain('-- Dump complete.');
});

it('searches for a wildcard character as the character it is', function (): void {
    viewerDatabase();
    DB::table('posts')->insert([
        ['user_id' => 1, 'title' => '50% off', 'published' => true],
        ['user_id' => 1, 'title' => 'a_b', 'published' => true],
        ['user_id' => 1, 'title' => '500 items', 'published' => true],
        ['user_id' => 1, 'title' => 'axb', 'published' => true],
    ]);

    $this->get('/larapilot/database/posts?q=50%25')->assertOk()->assertSee('50% off')->assertDontSee('500 items');
    $this->get('/larapilot/database/posts?q=a_b')->assertOk()->assertSee('a_b')->assertDontSee('axb');
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
