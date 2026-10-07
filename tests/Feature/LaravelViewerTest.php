<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DashboardAuthService;
use Larapilot\Services\Laravel\DumpRecorder;
use Larapilot\Services\Laravel\MailRecorder;
use Larapilot\Services\LaravelViewerService;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\VarDumper;

/**
 * A folder outside the application for what the recorders keep, so a test
 * never reads — or leaves behind — the records of the shared skeleton.
 * Keeping is asked for by name: under `testing` it is off unless it is.
 */
function recordFolder(bool $mail = true, bool $dumps = true): string
{
    $folder = sys_get_temp_dir().'/larapilot-laravel-'.bin2hex(random_bytes(6));

    config()->set('larapilot.laravel_viewer.path', $folder);
    config()->set('larapilot.laravel_viewer.mail', $mail);
    config()->set('larapilot.laravel_viewer.dumps', $dumps);
    config()->set('mail.default', 'array');
    config()->set('mail.from', ['address' => 'app@example.test', 'name' => 'The App']);

    $GLOBALS['larapilot_laravel_folders'][] = $folder;

    return $folder;
}

/**
 * Put a silent handler under the recorder, so a dump of a test is kept and
 * not printed over the output of the suite. Answers what reached it.
 */
function silentDumps(): ArrayObject
{
    $seen = new ArrayObject;

    VarDumper::setHandler(static function (mixed $var) use ($seen): void {
        $seen->append($var);
    });
    DumpRecorder::install();

    return $seen;
}

/**
 * An in-memory database with the tables of the queue: two jobs that wait,
 * one that is delayed, one a worker holds, and one that failed.
 */
function queueDatabase(): void
{
    config()->set('database.connections.queues', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    config()->set('database.default', 'queues');
    DB::purge('queues');

    Schema::create('jobs', function (Blueprint $table): void {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });

    Schema::create('failed_jobs', function (Blueprint $table): void {
        $table->id();
        $table->string('uuid')->unique();
        $table->text('connection');
        $table->text('queue');
        $table->longText('payload');
        $table->longText('exception');
        $table->timestamp('failed_at')->useCurrent();
    });

    config()->set('queue.default', 'database');
    config()->set('queue.connections.database', ['driver' => 'database', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90]);
    config()->set('queue.failed', ['driver' => 'database-uuids', 'database' => 'queues', 'table' => 'failed_jobs']);

    $now = time();
    $payload = static fn (string $job): string => (string) json_encode(['displayName' => $job, 'maxTries' => 3]);

    DB::table('jobs')->insert([
        ['queue' => 'default', 'payload' => $payload('App\\Jobs\\SendInvoice'), 'attempts' => 0, 'reserved_at' => null, 'available_at' => $now - 30, 'created_at' => $now - 30],
        ['queue' => 'default', 'payload' => $payload('App\\Jobs\\SyncCustomer'), 'attempts' => 1, 'reserved_at' => $now - 5, 'available_at' => $now - 60, 'created_at' => $now - 60],
        ['queue' => 'emails', 'payload' => $payload('App\\Mail\\WelcomeMail'), 'attempts' => 0, 'reserved_at' => null, 'available_at' => $now - 10, 'created_at' => $now - 10],
        ['queue' => 'emails', 'payload' => $payload('App\\Jobs\\SendReminder'), 'attempts' => 0, 'reserved_at' => null, 'available_at' => $now + 3600, 'created_at' => $now - 10],
    ]);

    DB::table('failed_jobs')->insert([
        'uuid' => '4f1c2d3e-0000-4000-8000-000000000001',
        'connection' => 'database',
        'queue' => 'default',
        'payload' => $payload('App\\Jobs\\ChargeCard'),
        'exception' => "RuntimeException: Gateway refused password=hunter2-do-not-show in /var/www/app/Jobs/ChargeCard.php:41\nStack trace:\n#0 {main}",
        'failed_at' => Carbon::now()->subHour()->toDateTimeString(),
    ]);
}

class LaravelViewerTestMail extends Mailable
{
    public function build(): static
    {
        return $this->subject('Your invoice')
            ->html('<html><head><title>Invoice</title></head><body><h1>Invoice 42</h1><a href="https://app.example.test/invoices/42">Open</a><script>alert(1)</script></body></html>')
            ->attachData('PDF-BYTES', 'invoice-42.pdf', ['mime' => 'application/pdf']);
    }
}

afterEach(function (): void {
    foreach ($GLOBALS['larapilot_laravel_folders'] ?? [] as $folder) {
        shell_exec('rm -rf '.escapeshellarg($folder));
    }

    $GLOBALS['larapilot_laravel_folders'] = [];
});

it('puts Laravel in the Workspace group, under Logs', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $html = $this->get('/larapilot')->assertOk()->getContent();

    expect($html)->toContain('>Laravel</a>')
        ->and(strpos($html, '>Laravel</a>'))->toBeGreaterThan(strpos($html, '>Logs</a>'))
        ->and(strpos($html, '>Laravel</a>'))->toBeLessThan(strpos($html, '>Git</a>'));
});

it('shows the drivers in use, and never a password', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    config()->set('queue.default', 'sync');
    config()->set('mail.default', 'smtp');
    config()->set('mail.mailers.smtp', ['transport' => 'smtp', 'host' => 'smtp.example.test', 'port' => 2525, 'username' => 'mailer', 'password' => 'smtp-pass-do-not-show']);
    config()->set('mail.from.address', 'app@example.test');
    config()->set('filesystems.default', 's3');
    config()->set('filesystems.disks.s3', ['driver' => 's3', 'key' => 'AKIA-do-not-show', 'secret' => 's3-secret-do-not-show', 'bucket' => 'invoices', 'region' => 'eu-south-1']);
    config()->set('session.driver', 'array');

    $drivers = collect(app(LaravelViewerService::class)->drivers())->keyBy('service');

    expect($drivers['Mail']['name'])->toBe('smtp')
        ->and($drivers['Mail']['facts'])->toContain('smtp.example.test:2525', 'from app@example.test')
        ->and($drivers['Filesystem']['facts'])->toContain('bucket invoices', 'region eu-south-1')
        ->and($drivers['Queue']['note'])->toContain('nothing waits')
        ->and($drivers['Cache']['driver'])->toBe('array');

    $this->get('/larapilot/laravel')
        ->assertOk()
        ->assertSee('Drivers', false)
        ->assertSee('smtp.example.test:2525', false)
        ->assertSee('bucket invoices', false)
        ->assertSee('A job runs inside the request that dispatches it', false)
        ->assertSee('Scheduled tasks', false)
        ->assertDontSee('smtp-pass-do-not-show', false)
        ->assertDontSee('s3-secret-do-not-show', false)
        ->assertDontSee('AKIA-do-not-show', false);
});

it('says what is cached and what is not', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $compiled = sys_get_temp_dir().'/larapilot-laravel-views-'.bin2hex(random_bytes(6));
    $GLOBALS['larapilot_laravel_folders'][] = $compiled;
    mkdir($compiled, 0755, true);
    file_put_contents($compiled.'/a.php', '<?php // one');
    file_put_contents($compiled.'/b.php', '<?php // two');
    config()->set('view.compiled', $compiled);

    $cached = sys_get_temp_dir().'/larapilot-laravel-config-'.bin2hex(random_bytes(6)).'.php';
    file_put_contents($cached, '<?php return [];');
    $this->app['env'] = 'local';

    try {
        // Laravel reads the file at boot; from version 12 it remembers
        // what it found for the life of the application.
        $_ENV['APP_CONFIG_CACHE'] = $cached;
        $this->app->instance('config_loaded_from_cache', true);

        $caches = collect(app(LaravelViewerService::class)->caches())->keyBy('key');
    } finally {
        unset($_ENV['APP_CONFIG_CACHE']);
        unlink($cached);
    }

    expect($caches['config']['cached'])->toBeTrue()
        ->and($caches['config']['warning'])->toContain('.env')
        ->and($caches['routes']['cached'])->toBeFalse()
        ->and($caches['routes']['state'])->toBe('Not cached')
        ->and($caches['events']['cached'])->toBeFalse()
        ->and($caches['views']['state'])->toBe('2 views compiled')
        ->and($caches['application']['state'])->toBe('array')
        ->and($caches['application']['detail'])->toContain('lost at the end of each request');

    $this->get('/larapilot/laravel')
        ->assertOk()
        ->assertSee('Caches', false)
        ->assertSee('Not cached', false)
        ->assertSee('php artisan view:clear', false)
        ->assertSee('php artisan optimize:clear', false);
});

it('counts what a file cache holds, and says when a store does not answer', function (): void {
    $folder = sys_get_temp_dir().'/larapilot-laravel-cache-'.bin2hex(random_bytes(6));
    $GLOBALS['larapilot_laravel_folders'][] = $folder;

    config()->set('cache.stores.files', ['driver' => 'file', 'path' => $folder]);
    config()->set('cache.default', 'files');
    Cache::store('files')->put('customer:42', 'Ada', 60);
    Cache::store('files')->put('customer:43', 'Grace', 60);

    $application = collect(app(LaravelViewerService::class)->caches())->firstWhere('key', 'application');

    expect($application['cached'])->toBeTrue()
        ->and($application['detail'])->toContain('2 entries');

    // A database store whose table was never migrated.
    config()->set('database.connections.empty', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    config()->set('cache.stores.rows', ['driver' => 'database', 'connection' => 'empty', 'table' => 'cache']);
    config()->set('cache.default', 'rows');

    $application = collect(app(LaravelViewerService::class)->caches())->firstWhere('key', 'application');

    expect($application['cached'])->toBeFalse()
        ->and($application['state'])->toBe('Does not answer')
        ->and($application['detail'])->toContain('cache');
});

it('lists the scheduled tasks, the next one due first', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $schedule = app(Schedule::class);
    $schedule->command('invoices:send --force')->dailyAt('02:00')->withoutOverlapping()->onOneServer()->description('Send the invoices of the day');
    $schedule->command('queue:prune-failed')->everyMinute();
    $schedule->call(static fn () => null)->hourly()->environments(['production']);
    $schedule->exec('node scripts/report.js')->monthly()->runInBackground();

    $read = app(LaravelViewerService::class)->schedule();
    $tasks = collect($read['tasks'])->keyBy('command');

    expect($read['error'])->toBeNull()
        ->and($read['tasks'][0]['command'])->toBe('php artisan queue:prune-failed')
        ->and($tasks['php artisan invoices:send --force']['expression'])->toBe('0 2 * * *')
        ->and($tasks['php artisan invoices:send --force']['description'])->toBe('Send the invoices of the day')
        ->and($tasks['php artisan invoices:send --force']['without_overlapping'])->toBeTrue()
        ->and($tasks['php artisan invoices:send --force']['one_server'])->toBeTrue()
        ->and($tasks['php artisan invoices:send --force']['kind'])->toBe('artisan')
        ->and($tasks['Closure']['runs_here'])->toBeFalse()
        ->and($tasks['Closure']['where'])->toContain('LaravelViewerTest.php:')
        ->and($tasks['node scripts/report.js']['kind'])->toBe('shell')
        ->and($tasks['node scripts/report.js']['background'])->toBeTrue();

    $this->get('/larapilot/laravel/schedule')
        ->assertOk()
        ->assertSee('php artisan invoices:send --force', false)
        ->assertSee('Send the invoices of the day', false)
        ->assertSee('0 2 * * *', false)
        ->assertSee('No overlap', false)
        ->assertSee('One server', false)
        ->assertSee('Not in testing', false)
        ->assertSee('Shell command', false);

    $this->get('/larapilot/laravel')->assertOk()->assertSee('Next: php artisan queue:prune-failed', false);
});

it('says so when nothing is scheduled', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->get('/larapilot/laravel/schedule')
        ->assertOk()
        ->assertSee('No task is scheduled.', false);
});

it('shows the jobs that wait, the ones that are held, and the ones that failed', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    queueDatabase();

    $queue = app(LaravelViewerService::class)->queue();
    $connection = $queue['connection'];
    $queues = collect($connection['queues'])->keyBy('name');

    expect($connection['error'])->toBeNull()
        ->and($connection['totals'])->toBe(['waiting' => 2, 'delayed' => 1, 'reserved' => 1])
        ->and($queues['default'])->toMatchArray(['waiting' => 1, 'reserved' => 1, 'delayed' => 0])
        ->and($queues['emails'])->toMatchArray(['waiting' => 1, 'reserved' => 0, 'delayed' => 1])
        ->and(array_column($connection['jobs'], 'job'))->toBe(['App\\Jobs\\SendInvoice', 'App\\Jobs\\SyncCustomer', 'App\\Mail\\WelcomeMail', 'App\\Jobs\\SendReminder'])
        ->and(array_column($connection['jobs'], 'state'))->toBe(['waiting', 'reserved', 'waiting', 'delayed'])
        ->and($queue['failed']['count'])->toBe(1)
        ->and($queue['failed']['jobs'][0]['job'])->toBe('App\\Jobs\\ChargeCard');

    $this->get('/larapilot/laravel/queue')
        ->assertOk()
        ->assertSee('App\\Jobs\\SendInvoice', false)
        ->assertSee('App\\Jobs\\SendReminder', false)
        ->assertSee('Running', false)
        ->assertSee('Delayed', false)
        ->assertSee('App\\Jobs\\ChargeCard', false)
        ->assertSee('Gateway refused', false)
        ->assertSee('php artisan queue:retry 4f1c2d3e-0000-4000-8000-000000000001', false)
        ->assertDontSee('hunter2-do-not-show', false);

    $this->get('/larapilot/laravel')
        ->assertOk()
        ->assertSee('Jobs waiting', false)
        ->assertSee('Last: <span title="App\\Jobs\\ChargeCard">ChargeCard</span>', false);
});

it('reads another connection when it is asked for, and only one that is configured', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    queueDatabase();
    config()->set('queue.default', 'sync');
    config()->set('queue.connections.sync', ['driver' => 'sync']);

    $this->get('/larapilot/laravel/queue')
        ->assertOk()
        ->assertSee('nothing ever waits here', false)
        ->assertDontSee('App\\Jobs\\SendInvoice', false)
        // The failed jobs belong to no connection of the page: they stay.
        ->assertSee('App\\Jobs\\ChargeCard', false);

    $this->get('/larapilot/laravel/queue?connection=database')
        ->assertOk()
        ->assertSee('App\\Jobs\\SendInvoice', false);

    expect(app(LaravelViewerService::class)->queue('no-such-connection')['connection']['name'])->toBe('sync');
});

it('says why when the queue cannot be read', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    config()->set('database.connections.bare', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    config()->set('database.default', 'bare');
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database', ['driver' => 'database', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90]);
    config()->set('queue.failed', ['driver' => 'database-uuids', 'database' => 'bare', 'table' => 'failed_jobs']);

    $this->get('/larapilot/laravel/queue')
        ->assertOk()
        ->assertSee('did not answer', false)
        ->assertSee('php artisan queue:table', false);

    $this->get('/larapilot/laravel')->assertOk()->assertSee('The queue did not answer.', false);
});

it('keeps the mail the application sends', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $folder = recordFolder();

    Mail::raw('Welcome aboard, Ada.', static function ($message): void {
        $message->to('ada@example.test', 'Ada Lovelace')->cc('grace@example.test')->subject('Welcome');
    });
    Mail::to('ada@example.test')->send(new LaravelViewerTestMail);

    $mail = app(LaravelViewerService::class)->mail();

    expect($mail['recording'])->toBeTrue()
        ->and($mail['records'])->toHaveCount(2)
        ->and($mail['records'][0]['subject'])->toBe('Your invoice')
        ->and($mail['records'][0]['source'])->toBe(LaravelViewerTestMail::class)
        ->and($mail['records'][0]['attachments'])->toBe([['name' => 'invoice-42.pdf', 'type' => 'application/pdf', 'size' => 9]])
        ->and($mail['records'][1]['subject'])->toBe('Welcome')
        ->and($mail['records'][1]['to'])->toBe([['address' => 'ada@example.test', 'name' => 'Ada Lovelace']])
        ->and($mail['records'][1]['from'])->toBe([['address' => 'app@example.test', 'name' => 'The App']])
        ->and($mail['records'][1]['transport'])->toBe('array')
        // The folder keeps itself out of the repository.
        ->and(file_get_contents($folder.'/.gitignore'))->toBe("*\n");

    $this->get('/larapilot/laravel/mail')
        ->assertOk()
        ->assertSee('Your invoice', false)
        ->assertSee('Welcome', false)
        ->assertSee('ada@example.test', false)
        ->assertSee('LaravelViewerTestMail', false)
        ->assertSee('1 attachment', false)
        ->assertSee('Every mail sent is kept', false);

    // The text of a mail, with who it went to.
    $this->get('/larapilot/laravel/mail/'.$mail['records'][1]['id'])
        ->assertOk()
        ->assertSee('Ada Lovelace &lt;ada@example.test&gt;', false)
        ->assertSee('grace@example.test', false)
        ->assertSee('Welcome aboard, Ada.', false)
        ->assertDontSee('<iframe', false);

    // The HTML of a mail only ever inside a frame that runs no script.
    $html = $this->get('/larapilot/laravel/mail/'.$mail['records'][0]['id'])
        ->assertOk()
        ->assertSee('invoice-42.pdf', false)
        ->assertSee('sandbox="allow-popups allow-popups-to-escape-sandbox"', false)
        ->assertSee('&lt;head&gt;&lt;base target=&quot;_blank&quot;&gt;&lt;title&gt;Invoice', false)
        ->assertSee('&lt;h1&gt;Invoice 42&lt;/h1&gt;', false)
        ->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->not->toContain('PDF-BYTES');

    $this->get('/larapilot/laravel')->assertOk()->assertSee('Mail kept', false)->assertSee('Every mail sent is kept.', false);
});

it('opens no mail that is not one of the folder', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $folder = recordFolder();

    Mail::raw('One', static fn ($message) => $message->to('ada@example.test')->subject('One'));
    file_put_contents($folder.'/secret.json', '{"subject":"not a record"}');

    $id = app(LaravelViewerService::class)->mail()['records'][0]['id'];

    $this->get('/larapilot/laravel/mail/'.$id)->assertOk();
    $this->get('/larapilot/laravel/mail/'.substr($id, 0, -1).(str_ends_with($id, '0') ? '1' : '0'))->assertNotFound();
    $this->get('/larapilot/laravel/mail/secret')->assertNotFound();
    $this->get('/larapilot/laravel/mail/..%2Fsecret')->assertNotFound();

    expect(app(LaravelViewerService::class)->message('../mail/'.$id))->toBeNull()
        ->and(app(LaravelViewerService::class)->mail()['records'])->toHaveCount(1);
});

it('keeps only the newest records, and forgets them all when asked', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    recordFolder();
    config()->set('larapilot.laravel_viewer.keep', 2);

    foreach (['First', 'Second', 'Third'] as $subject) {
        Mail::raw($subject, static fn ($message) => $message->to('ada@example.test')->subject($subject));
    }

    $store = app(MailRecorder::class)->store();

    expect(array_column($store->all(), 'subject'))->toBe(['Third', 'Second'])
        ->and(glob($store->directory().'/*'))->toHaveCount(4);

    $this->post('/larapilot/laravel/mail/clear')
        ->assertRedirect('/larapilot/laravel/mail')
        ->assertSessionHas('larapilot_success', 'Forgot 2 mails.');

    expect($store->count())->toBe(0)
        ->and(glob($store->directory().'/*'))->toBe([]);

    $this->get('/larapilot/laravel/mail')->assertOk()->assertSee('No mail yet.', false)->assertDontSee('Forget them all', false);
});

it('keeps the mail on a developer machine only, unless it is asked for', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    recordFolder();
    config()->set('larapilot.laravel_viewer.mail', null);

    $send = static fn () => Mail::raw('Reset link', static fn ($message) => $message->to('ada@example.test')->subject('Reset'));
    $store = app(MailRecorder::class)->store();

    // While the tests of a project run, nothing is kept.
    $send();
    expect($store->count())->toBe(0);

    $this->get('/larapilot/laravel/mail')->assertOk()->assertSee('Mail is not kept here', false);

    $this->app['env'] = 'local';
    $send();
    expect($store->count())->toBe(1);

    config()->set('larapilot.laravel_viewer.mail', false);
    $send();
    expect($store->count())->toBe(1);

    $this->get('/larapilot/laravel/mail')->assertOk()->assertSee('Keeping the mail is switched off.', false);

    // A shared host: never by default, and never where the page is closed.
    $this->app['env'] = 'staging';
    config()->set('larapilot.laravel_viewer.mail', null);
    $send();
    config()->set('larapilot.laravel_viewer.mail', true);
    $send();
    expect($store->count())->toBe(1);

    app(DashboardAuthService::class)->setUser('andrea', 's3cret-pass');
    app(ConfigService::class)->updateSettings(['dashboard_auth' => 'YES']);
    $send();
    expect($store->count())->toBe(2);

    $this->app['env'] = 'production';
    $send();
    expect($store->count())->toBe(2);
});

it('sends the mail all the same when it cannot be kept', function (): void {
    recordFolder();
    $blocked = sys_get_temp_dir().'/larapilot-laravel-blocked-'.bin2hex(random_bytes(6));
    $GLOBALS['larapilot_laravel_folders'][] = $blocked;
    file_put_contents($blocked, 'a file where the folder should be');
    config()->set('larapilot.laravel_viewer.path', $blocked);

    Mail::raw('Still sent', static fn ($message) => $message->to('ada@example.test')->subject('Still sent'));

    $sent = app('mailer')->getSymfonyTransport()->messages();

    expect($sent)->toHaveCount(1)
        ->and(app(MailRecorder::class)->store()->count())->toBe(0);
});

it('keeps what dump() prints, with the line that dumped it', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    recordFolder();
    $seen = silentDumps();

    $line = __LINE__ + 1;
    dump(['customer' => 'Ada', 'total' => 42]);
    collect(['first', 'second'])->dump();

    $dumps = app(LaravelViewerService::class)->dumps();

    expect($dumps['recording'])->toBeTrue()
        ->and($dumps['records'])->toHaveCount(2)
        ->and($dumps['records'][1]['text'])->toContain('"customer" => "Ada"', '"total" => 42')
        ->and($dumps['records'][1]['file'])->toEndWith('tests/Feature/LaravelViewerTest.php')
        ->and($dumps['records'][1]['line'])->toBe($line)
        ->and($dumps['records'][1]['context'])->toStartWith('artisan')
        ->and($dumps['records'][0]['text'])->toContain('"first"', '"second"')
        ->and($dumps['records'][0]['file'])->toEndWith('tests/Feature/LaravelViewerTest.php')
        // The handler that was there still gets every value.
        ->and($seen)->toHaveCount(2)
        ->and($seen[0])->toBe(['customer' => 'Ada', 'total' => 42]);

    $this->get('/larapilot/laravel/dumps')
        ->assertOk()
        ->assertSee('&quot;customer&quot; =&gt; &quot;Ada&quot;', false)
        ->assertSee('LaravelViewerTest.php:'.$line, false)
        ->assertSee('Every dump is kept', false)
        // Nothing stands between dump() and the recorder here; Herd would.
        ->assertDontSee('is taking the dumps', false);

    expect(DumpRecorder::takenBy())->toBeNull();

    $this->post('/larapilot/laravel/dumps/clear')
        ->assertRedirect('/larapilot/laravel/dumps')
        ->assertSessionHas('larapilot_success', 'Forgot 2 dumps.');

    $this->get('/larapilot/laravel/dumps')->assertOk()->assertSee('No dump yet.', false);
});

it('stands in front of the dump handler once, whatever was there', function (): void {
    recordFolder();
    $seen = silentDumps();

    // Asked again — as every application of a test suite does — it does
    // not wrap itself.
    DumpRecorder::install();
    DumpRecorder::install();
    dump('once');

    expect($seen)->toHaveCount(1)
        ->and(app(DumpRecorder::class)->store()->count())->toBe(1);

    // With no handler before it, the component picks its own and the
    // recorder stays in front of it.
    $output = CliDumper::$defaultOutput;
    CliDumper::$defaultOutput = 'php://memory';

    try {
        VarDumper::setHandler(null);
        DumpRecorder::install();
        dump('twice');
        dump('thrice');
    } finally {
        CliDumper::$defaultOutput = $output;
    }

    expect($seen)->toHaveCount(1)
        ->and(array_column(app(DumpRecorder::class)->store()->all(), 'text'))->toBe(['"thrice"', '"twice"', '"once"']);
});

it('keeps no dump where it is not asked to', function (): void {
    recordFolder(dumps: false);
    $seen = silentDumps();

    dump('not kept');

    config()->set('larapilot.laravel_viewer.dumps', null);
    dump('not kept while the tests run');

    expect($seen)->toHaveCount(2)
        ->and(app(DumpRecorder::class)->store()->count())->toBe(0);

    $this->app['env'] = 'local';
    dump('kept on a developer machine');

    expect(app(DumpRecorder::class)->store()->count())->toBe(1);
});

it('stays behind the dashboard sign-in on a shared environment', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    recordFolder();

    $this->app['env'] = 'staging';

    $this->get('/larapilot')->assertOk()->assertDontSee('>Laravel</a>', false);

    foreach (['', '/schedule', '/queue', '/mail', '/dumps'] as $tab) {
        $this->get('/larapilot/laravel'.$tab)->assertNotFound();
    }

    $this->withSession(['_token' => 'laravel-viewer-test']);
    $this->post('/larapilot/laravel/mail/clear', ['_token' => 'laravel-viewer-test'])->assertNotFound();
    $this->post('/larapilot/laravel/dumps/clear', ['_token' => 'laravel-viewer-test'])->assertNotFound();

    app(DashboardAuthService::class)->setUser('andrea', 's3cret-pass');
    app(ConfigService::class)->updateSettings(['dashboard_auth' => 'YES']);

    $this->get('/larapilot/laravel')->assertStatus(401);

    $headers = ['Authorization' => 'Basic '.base64_encode('andrea:s3cret-pass')];

    $this->get('/larapilot', $headers)->assertOk()->assertSee('>Laravel</a>', false);

    foreach (['', '/schedule', '/queue', '/mail', '/dumps'] as $tab) {
        $this->get('/larapilot/laravel'.$tab, $headers)->assertOk();
    }

    config()->set('larapilot.laravel_viewer.dumps', null);

    $this->get('/larapilot/laravel/dumps', $headers)
        ->assertOk()
        ->assertSee('kept only on a developer', false)
        ->assertSee('LARAPILOT_LARAVEL_VIEWER_DUMPS=true', false);
});

it('hides the Laravel page in production and when it is switched off', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    config()->set('larapilot.laravel_viewer.enabled', false);

    $this->get('/larapilot/laravel')->assertNotFound();
    $this->get('/larapilot/laravel/mail')->assertNotFound();
    $this->get('/larapilot')->assertOk()->assertDontSee('>Laravel</a>', false);

    config()->set('larapilot.laravel_viewer.enabled', true);
    $this->app['env'] = 'production';

    $this->get('/larapilot/laravel')->assertNotFound();
    $this->get('/larapilot/laravel/queue')->assertNotFound();
    $this->withSession(['_token' => 'laravel-viewer-test']);
    $this->post('/larapilot/laravel/dumps/clear', ['_token' => 'laravel-viewer-test'])->assertNotFound();
});
