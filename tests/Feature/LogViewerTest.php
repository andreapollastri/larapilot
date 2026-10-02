<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Larapilot\Mcp\LarapilotServer;
use Larapilot\Mcp\Tools\RunArtisanTool;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DashboardAuthService;
use Larapilot\Services\DiagnosticsService;
use Larapilot\Services\LogViewerService;

/**
 * A folder of logs outside the application, so a test never reads — or
 * leaves behind — the log of the shared skeleton.
 *
 * @param  array<string, string>  $files
 */
function logFolder(array $files = []): string
{
    $folder = sys_get_temp_dir().'/larapilot-logs-'.bin2hex(random_bytes(6));
    mkdir($folder, 0755, true);

    foreach ($files as $name => $contents) {
        if (! is_dir(dirname($folder.'/'.$name))) {
            mkdir(dirname($folder.'/'.$name), 0755, true);
        }

        file_put_contents($folder.'/'.$name, $contents);
    }

    config()->set('larapilot.log_viewer.path', $folder);
    config()->set('logging.default', 'single');
    config()->set('logging.channels.single', ['driver' => 'single', 'path' => $folder.'/laravel.log']);

    $GLOBALS['larapilot_log_folders'][] = $folder;

    return $folder;
}

/**
 * What Laravel writes for an exception: the message, the context as JSON
 * with the exception and its stack inside, on as many lines as the stack.
 */
function thrownEntry(string $time, string $message = 'SQLSTATE[23000]: Integrity constraint violation', int $line = 88): string
{
    return '['.$time.'] production.ERROR: '.$message.' {"userId":7,"exception":"[object] (Illuminate\\\\Database\\\\QueryException(code: 23000): '.$message.' at /var/www/html/app/Services/CustomerImporter.php:'.$line.')'."\n"
        .'[stacktrace]'."\n"
        .'#0 /var/www/html/vendor/laravel/framework/src/Illuminate/Database/Connection.php(779): Illuminate\\\\Database\\\\Connection->runQueryCallback()'."\n"
        .'#1 /var/www/html/app/Services/CustomerImporter.php('.$line.'): Illuminate\\\\Database\\\\Connection->insert()'."\n"
        .'#2 /var/www/html/config/app.php(12): App\\\\Services\\\\CustomerImporter->import()'."\n"
        .'#3 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Controller.php(54): App\\\\Http\\\\Controllers\\\\CustomerController->store()'."\n"
        .'#4 /var/www/html/public/index.php(17): Illuminate\\\\Foundation\\\\Application->handleRequest()'."\n"
        .'#5 {main}'."\n"
        .'"} '."\n";
}

function sampleLog(): string
{
    return "[2026-10-01 08:00:00] production.INFO: Worker started \n"
        ."[2026-10-01 09:15:00] production.WARNING: Slow query {\"ms\":1840,\"connection\":\"mysql\"} \n"
        .thrownEntry('2026-10-01 10:00:00')
        ."[2026-10-01 10:05:00] production.DEBUG: Cache miss for customer:42 \n"
        ."[2026-10-01 11:00:00] production.INFO: Login attempt {\"email\":\"ada@example.test\",\"password\":\"hunter2-do-not-show\"} \n"
        ."[2026-10-01 11:30:00] production.ERROR: Payment gateway refused: Authorization: Bearer live-token-do-not-show \n"
        .thrownEntry('2026-10-01 12:00:00', 'SQLSTATE[23000]: Integrity constraint violation', 88)
        ."[2026-10-01 12:30:00] production.CRITICAL: Disk almost full on /dev/sda1 \n";
}

afterEach(function (): void {
    foreach ($GLOBALS['larapilot_log_folders'] ?? [] as $folder) {
        shell_exec('rm -rf '.escapeshellarg($folder));
    }

    $GLOBALS['larapilot_log_folders'] = [];
});

it('lists the log files and opens the one the application writes to', function (): void {
    $folder = logFolder([
        'laravel.log' => sampleLog(),
        'worker.log' => "plain line\n",
        'archive/laravel-2026-09-30.log' => "[2026-09-30 10:00:00] production.INFO: Yesterday \n",
        'notes.txt' => 'not a log',
    ]);
    touch($folder.'/worker.log', time() - 60);
    touch($folder.'/archive/laravel-2026-09-30.log', time() - 3600);

    $files = app(LogViewerService::class)->files();

    expect(array_column($files, 'key'))->toBe(['laravel.log', 'worker.log', 'archive/laravel-2026-09-30.log'])
        ->and($files[0]['current'])->toBeTrue()
        ->and($files[1]['current'])->toBeFalse();

    $this->get('/larapilot/logs')
        ->assertOk()
        ->assertSee('Logs', false)
        ->assertSee('<code>laravel.log</code>', false)
        ->assertSee('The application writes here', false)
        ->assertSee('/larapilot/logs/worker.log', false)
        ->assertSee('/larapilot/logs/archive/laravel-2026-09-30.log', false)
        ->assertDontSee('notes.txt', false)
        ->assertSee('Disk almost full', false);

    $this->get('/larapilot/logs/archive/laravel-2026-09-30.log')
        ->assertOk()
        ->assertSee('Yesterday', false)
        ->assertDontSee('The application writes here', false);

    $html = $this->get('/larapilot')->assertOk()->getContent();

    expect($html)->toContain('>Logs</a>')
        ->and(strpos($html, '>Database</a>'))->toBeLessThan(strpos($html, '>Logs</a>'))
        ->and(strpos($html, '>Logs</a>'))->toBeLessThan(strpos($html, '>Git</a>'));
});

it('reads a log as entries, the newest first, with the exception taken apart', function (): void {
    logFolder(['laravel.log' => sampleLog()]);
    $logs = app(LogViewerService::class);

    $page = $logs->read($logs->current());
    $entries = $page['entries'];

    expect($entries)->toHaveCount(8)
        ->and($page['next'])->toBeNull()
        ->and($page['resume'])->toBeNull()
        ->and(array_column($entries, 'level'))->toBe(['critical', 'error', 'error', 'info', 'debug', 'error', 'warning', 'info'])
        ->and($entries[0]['message'])->toBe('Disk almost full on /dev/sda1')
        ->and($entries[0]['time'])->toBe('2026-10-01 12:30:00')
        ->and($entries[0]['env'])->toBe('production');

    $thrown = $entries[1];

    expect($thrown['message'])->toBe('SQLSTATE[23000]: Integrity constraint violation')
        ->and($thrown['exception']['class'])->toBe('Illuminate\Database\QueryException')
        ->and($thrown['exception']['short'])->toBe('QueryException')
        ->and($thrown['exception']['code'])->toBe('23000')
        // The path of the server is read as a file of the project.
        ->and($thrown['exception']['where'])->toBe('app/Services/CustomerImporter.php:88')
        ->and($thrown['where'])->toBe('app/Services/CustomerImporter.php:88')
        ->and($thrown['context'])->toContain('"userId": 7')
        ->and($thrown['frames_total'])->toBe(6)
        ->and($thrown['frames_app'])->toBe(2)
        ->and($thrown['frames'][0])->toMatchArray(['index' => 0, 'file' => 'vendor/laravel/framework/src/Illuminate/Database/Connection.php', 'line' => 779, 'app' => false])
        ->and($thrown['frames'][1])->toMatchArray(['file' => 'app/Services/CustomerImporter.php', 'line' => 88, 'app' => true, 'call' => 'Illuminate\Database\Connection->insert()'])
        // A file that is in this checkout opens in the file manager.
        ->and($thrown['frames'][2]['url'])->toEndWith('/larapilot/files/project/config/app.php')
        // The front controller is in every stack: it is not the application's own code.
        ->and($thrown['frames'][4])->toMatchArray(['file' => 'public/index.php', 'app' => false])
        ->and($thrown['frames'][5])->toMatchArray(['file' => '', 'call' => '{main}']);

    // A context that is JSON is told apart from the message.
    expect($entries[6]['message'])->toBe('Slow query')
        ->and($entries[6]['context'])->toContain('"ms": 1840');

    $this->get('/larapilot/logs')
        ->assertOk()
        ->assertSee('QueryException', false)
        ->assertSee('app/Services/CustomerImporter.php:88', false)
        ->assertSee('2 in the application', false)
        ->assertSee('8 entries from 2026-10-01 08:00:00 to 2026-10-01 12:30:00', false)
        ->assertSee('Error <b>3</b>', false)
        ->assertSee('Critical <b>1</b>', false);
});

it('never shows a secret a log quotes', function (): void {
    logFolder(['laravel.log' => sampleLog()]);

    $this->get('/larapilot/logs')
        ->assertOk()
        ->assertSee('[REDACTED]', false)
        ->assertSee('ada@example.test', false)
        ->assertDontSee('hunter2-do-not-show', false)
        ->assertDontSee('live-token-do-not-show', false);

    // A search for the secret finds nothing: a match would tell its value.
    $this->get('/larapilot/logs?q=hunter2')
        ->assertOk()
        ->assertSee('No entry matches.', false)
        ->assertDontSee('Login attempt', false);

    $this->get('/larapilot/logs?view=groups&q=live-token')
        ->assertOk()
        ->assertSee('No entry matches.', false);
});

it('filters by level, by words, and by period', function (): void {
    Carbon::setTestNow('2026-10-01 12:45:00');
    logFolder(['laravel.log' => sampleLog()]);
    $logs = app(LogViewerService::class);
    $file = $logs->current();

    // A level returns itself and every one more severe.
    expect(array_column($logs->read($file, ['level' => 'error'])['entries'], 'level'))->toBe(['critical', 'error', 'error', 'error'])
        ->and(array_column($logs->read($file, ['level' => 'warning'])['entries'], 'level'))->toBe(['critical', 'error', 'error', 'error', 'warning'])
        ->and($logs->read($file, ['level' => 'critical'])['entries'])->toHaveCount(1)
        // An unknown level is no filter.
        ->and($logs->read($file, ['level' => 'loud'])['entries'])->toHaveCount(8);

    // Every word must be there, in any case; quotes keep a phrase together.
    expect($logs->read($file, ['search' => 'customerimporter integrity'])['entries'])->toHaveCount(2)
        ->and($logs->read($file, ['search' => 'integrity worker'])['entries'])->toHaveCount(0)
        ->and($logs->read($file, ['search' => '"Slow query"'])['entries'])->toHaveCount(1)
        ->and($logs->read($file, ['search' => '"query Slow"'])['entries'])->toHaveCount(0);

    expect(array_column($logs->read($file, ['since' => '1h'])['entries'], 'time'))->toBe(['2026-10-01 12:30:00', '2026-10-01 12:00:00'])
        ->and($logs->read($file, ['since' => '2026-10-01 11:00'])['entries'])->toHaveCount(4)
        ->and($logs->read($file, ['since' => '30d'])['entries'])->toHaveCount(8)
        // What cannot be read as a moment is no filter.
        ->and($logs->read($file, ['since' => 'whenever'])['entries'])->toHaveCount(8)
        ->and($logs->understandsSince('7d'))->toBeTrue()
        ->and($logs->understandsSince('whenever'))->toBeFalse();

    $this->get('/larapilot/logs?level=error&q=gateway')
        ->assertOk()
        ->assertSee('Payment <mark>gateway</mark> refused', false)
        ->assertSee('1 entry found', false)
        ->assertSee('Error and worse', false)
        ->assertDontSee('Disk almost full', false);

    Carbon::setTestNow();
});

it('pages back through a file from the entry the page above stopped at', function (): void {
    config()->set('larapilot.log_viewer.per_page', 3);
    logFolder(['laravel.log' => sampleLog()]);
    $logs = app(LogViewerService::class);
    $file = $logs->current();

    $first = $logs->read($file);
    $second = $logs->read($file, ['before' => $first['next']]);
    $third = $logs->read($file, ['before' => $second['next']]);

    expect(array_column($first['entries'], 'time'))->toBe(['2026-10-01 12:30:00', '2026-10-01 12:00:00', '2026-10-01 11:30:00'])
        ->and(array_column($second['entries'], 'time'))->toBe(['2026-10-01 11:00:00', '2026-10-01 10:05:00', '2026-10-01 10:00:00'])
        ->and(array_column($third['entries'], 'time'))->toBe(['2026-10-01 09:15:00', '2026-10-01 08:00:00'])
        ->and($third['next'])->toBeNull();

    $this->get('/larapilot/logs')
        ->assertOk()
        ->assertSee('before='.$first['next'], false)
        ->assertSee('Older', false)
        ->assertDontSee('Newest', false);

    $this->get('/larapilot/logs?before='.$first['next'])
        ->assertOk()
        ->assertSee('Login attempt', false)
        ->assertDontSee('Disk almost full', false)
        ->assertSee('Newest', false);
});

it('counts the repeats, the thing logged the most first', function (): void {
    $log = sampleLog();

    for ($minute = 10; $minute < 15; $minute++) {
        $log .= "[2026-10-01 13:{$minute}:00] production.WARNING: Retrying job 4{$minute} for customer 'acme-{$minute}' \n";
    }

    // The same exception thrown from another line is another bug.
    $log .= thrownEntry('2026-10-01 14:00:00', 'SQLSTATE[23000]: Integrity constraint violation', 102);

    logFolder(['laravel.log' => $log]);
    $logs = app(LogViewerService::class);

    $groups = $logs->groups($logs->current());
    $rows = $groups['groups'];

    expect($groups['entries'])->toBe(14)
        ->and($groups['total'])->toBe(9)
        ->and($rows[0]['count'])->toBe(5)
        ->and($rows[0]['message'])->toBe("Retrying job 414 for customer 'acme-14'")
        ->and($rows[0]['first'])->toBe('2026-10-01 13:10:00')
        ->and($rows[0]['last'])->toBe('2026-10-01 13:14:00')
        ->and($rows[1]['count'])->toBe(2)
        ->and($rows[1]['where'])->toBe('app/Services/CustomerImporter.php:88')
        ->and($rows[2]['count'])->toBe(1);

    expect($logs->groups($logs->current(), ['level' => 'error'])['total'])->toBe(4);

    $this->get('/larapilot/logs?view=groups')
        ->assertOk()
        ->assertSee('×5', false)
        ->assertSee('×2', false)
        ->assertSee('9 of 9 things logged, in 14 entries', false)
        ->assertSee('Every time it was logged', false);
});

it('reads what Laravel itself writes for an exception with a cause', function (): void {
    $folder = logFolder();

    try {
        try {
            throw new InvalidArgumentException('The "currency" is not one we take');
        } catch (InvalidArgumentException $cause) {
            throw new RuntimeException('Import failed for customer 42', 7, $cause);
        }
    } catch (RuntimeException $exception) {
        Log::build(['driver' => 'single', 'path' => $folder.'/laravel.log'])
            ->error($exception->getMessage(), ['customer' => 42, 'exception' => $exception]);
    }

    $logs = app(LogViewerService::class);
    $entry = $logs->read($logs->current())['entries'][0];

    expect($entry['level'])->toBe('error')
        ->and($entry['message'])->toBe('Import failed for customer 42')
        ->and($entry['exception']['class'])->toBe('RuntimeException')
        ->and($entry['exception']['code'])->toBe('7')
        ->and($entry['exception']['file'])->toEndWith('tests/Feature/LogViewerTest.php')
        ->and($entry['previous'])->toHaveCount(1)
        ->and($entry['previous'][0]['class'])->toBe('InvalidArgumentException')
        ->and($entry['previous'][0]['message'])->toBe('The "currency" is not one we take')
        ->and($entry['context'])->toContain('"customer": 42')
        ->and($entry['frames'])->not->toBeEmpty()
        ->and($entry['ago'])->not->toBeNull();

    $this->get('/larapilot/logs')
        ->assertOk()
        ->assertSee('Caused by', false)
        ->assertSee('InvalidArgumentException', false);
});

it('reads a file that is not in the format of Laravel a line at a time', function (): void {
    logFolder(['worker.log' => "Processing: App\\Jobs\\SendInvoice\nProcessed:  App\\Jobs\\SendInvoice\n\nFailed:     App\\Jobs\\SyncStock\n"]);
    $logs = app(LogViewerService::class);
    $file = $logs->find('worker.log');

    expect($logs->overview($file))->toMatchArray(['format' => 'plain', 'entries' => 3, 'levels' => []])
        ->and(array_column($logs->read($file)['entries'], 'message'))->toBe(['Failed:     App\Jobs\SyncStock', 'Processed:  App\Jobs\SendInvoice', 'Processing: App\Jobs\SendInvoice'])
        // No levels and no dates: those filters are left out, the words are not.
        ->and($logs->read($file, ['level' => 'error', 'since' => '1h'])['entries'])->toHaveCount(3)
        ->and($logs->read($file, ['search' => 'syncstock'])['entries'])->toHaveCount(1);

    $this->get('/larapilot/logs/worker.log')
        ->assertOk()
        ->assertSee('read a line at a time', false)
        ->assertSee('SyncStock', false)
        ->assertDontSee('name="level"', false);
});

it('finds every entry of a large file once, whatever the piece it falls in', function (): void {
    $folder = logFolder();
    $handle = fopen($folder.'/laravel.log', 'wb');
    fwrite($handle, "a line written before the first entry\n");

    // Entries of every length, so the pieces the file is read in cut them
    // in the header, in the message, and in the stack.
    for ($index = 1; $index <= 6000; $index++) {
        $level = $index % 50 === 0 ? 'ERROR' : 'INFO';
        $entry = sprintf("[2026-10-01 %02d:%02d:%02d] local.%s: entry-%05d %s \n", intdiv($index, 3600) % 24, intdiv($index, 60) % 60, $index % 60, $level, $index, str_repeat('x', $index % 211));

        if ($index % 7 === 0) {
            $entry .= str_repeat("#0 /var/www/html/vendor/package/src/File.php(12): Some\\\\Class->call()\n", 1 + $index % 5);
        }

        fwrite($handle, $entry);
    }

    // One entry far longer than a piece: its start is kept, the rest counted.
    fwrite($handle, '[2026-10-02 00:00:00] local.ERROR: entry-huge '.str_repeat('y', 700000)." \n");
    fwrite($handle, "[2026-10-02 00:00:01] local.INFO: entry-last \n");
    fclose($handle);

    expect(filesize($folder.'/laravel.log'))->toBeGreaterThan(1500000);

    config()->set('larapilot.log_viewer.scan_mb', 1);
    $logs = app(LogViewerService::class);
    $file = $logs->current();

    $overview = $logs->overview($file);

    // The counts are of the megabyte one request reads.
    expect($overview['complete'])->toBeFalse()
        ->and($overview['entries'])->toBeLessThan(6002)
        ->and($overview['to'])->toBe('2026-10-02 00:00:01');

    $seen = [];
    $before = null;
    $requests = 0;

    do {
        $page = $logs->read($file, ['limit' => 200, 'before' => $before]);

        foreach ($page['entries'] as $entry) {
            $seen[] = $entry['message'];
        }

        $before = $page['next'] ?? $page['resume'];
    } while ($before !== null && ++$requests < 200);

    expect($seen)->toHaveCount(6003)
        ->and($seen[0])->toBe('entry-last')
        ->and($seen[1])->toStartWith('entry-huge yyyy')
        ->and($seen[2])->toStartWith('entry-06000')
        ->and($seen[6001])->toStartWith('entry-00001')
        ->and($seen[6002])->toBe('a line written before the first entry')
        ->and(array_unique(array_map(static fn (string $message): string => substr($message, 0, 11), $seen)))->toHaveCount(6003);

    // The huge one says how long it was, and that its start is what is shown.
    $huge = $logs->read($file, ['search' => 'entry-huge'])['entries'][0];

    expect($huge['truncated'])->toBeTrue()
        ->and($huge['bytes'])->toBeGreaterThan(700000)
        ->and(strlen($huge['body']))->toBeLessThan(20000);

    // A search that the megabyte does not answer says where it stopped.
    $search = $logs->read($file, ['search' => 'entry-00001 ']);

    expect($search['entries'])->toBe([])
        ->and($search['resume'])->toBeGreaterThan(0);

    $this->get('/larapilot/logs?q=entry-00001')
        ->assertOk()
        ->assertSee('Keep reading older', false)
        ->assertSee('before='.$search['resume'], false);
});

it('downloads the log with the secrets redacted, or as written on a developer machine', function (): void {
    logFolder(['laravel.log' => sampleLog()]);

    $response = $this->get('/larapilot/logs/laravel.log?download=1')->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('laravel.log')
        ->and($response->headers->get('Content-Type'))->toContain('text/plain');

    $redacted = $response->streamedContent();

    expect($redacted)->toContain('Disk almost full')
        ->toContain('"password":"[REDACTED]"')
        ->not->toContain('hunter2-do-not-show')
        ->not->toContain('live-token-do-not-show')
        // Every line of the file is there, in the order it was written.
        ->and(substr_count($redacted, "\n"))->toBe(substr_count(sampleLog(), "\n"));

    expect($this->get('/larapilot/logs/laravel.log?download=1&secrets=1')->assertOk()->streamedContent())->toBe(sampleLog());

    $this->get('/larapilot/logs')
        ->assertOk()
        ->assertSee('Download log', false)
        ->assertSee('Secrets as written', false);

    $this->artisan('larapilot:install')->assertSuccessful();
    app(DashboardAuthService::class)->setUser('andrea', 's3cret-pass');
    app(ConfigService::class)->updateSettings(['dashboard_auth' => 'YES']);
    $this->app['env'] = 'staging';

    $headers = ['Authorization' => 'Basic '.base64_encode('andrea:s3cret-pass')];

    $this->get('/larapilot/logs/laravel.log?download=1')->assertStatus(401);

    // On a shared host the secrets stay redacted, asked for or not.
    expect($this->get('/larapilot/logs/laravel.log?download=1&secrets=1', $headers)->assertOk()->streamedContent())
        ->not->toContain('hunter2-do-not-show');

    $this->get('/larapilot/logs', $headers)
        ->assertOk()
        ->assertSee('Download log', false)
        ->assertDontSee('Secrets as written', false)
        ->assertSee('Secrets are redacted on a shared host.', false);
});

it('opens nothing that is not a log of the folder', function (): void {
    $folder = logFolder(['laravel.log' => sampleLog(), 'secret.txt' => 'not a log']);
    file_put_contents(dirname($folder).'/outside-'.basename($folder).'.log', 'outside');
    symlink(dirname($folder).'/outside-'.basename($folder).'.log', $folder.'/linked.log');

    try {
        foreach (['missing.log', 'secret.txt', 'linked.log', '../outside-'.basename($folder).'.log', '..%2Foutside-'.basename($folder).'.log', '%2Fetc%2Fhosts'] as $name) {
            $this->get('/larapilot/logs/'.$name)->assertNotFound();
            $this->get('/larapilot/logs/'.$name.'?download=1')->assertNotFound();
        }

        expect(array_column(app(LogViewerService::class)->files(), 'key'))->toBe(['laravel.log']);
    } finally {
        unlink(dirname($folder).'/outside-'.basename($folder).'.log');
    }
});

it('says so when there is no log yet', function (): void {
    logFolder();

    $this->get('/larapilot/logs')
        ->assertOk()
        ->assertSee('No log yet.', false)
        ->assertDontSee('Download log', false);

    config()->set('larapilot.log_viewer.path', sys_get_temp_dir().'/larapilot-no-such-folder');

    $this->get('/larapilot/logs')->assertOk()->assertSee('No log yet.', false);
});

it('stays behind the dashboard sign-in on a shared environment', function (): void {
    logFolder(['laravel.log' => sampleLog()]);
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->app['env'] = 'staging';

    $this->get('/larapilot')->assertOk()->assertDontSee('>Logs</a>', false);
    $this->get('/larapilot/logs')->assertNotFound();
    $this->get('/larapilot/logs/laravel.log')->assertNotFound();
    $this->get('/larapilot/logs/laravel.log?download=1')->assertNotFound();

    app(DashboardAuthService::class)->setUser('andrea', 's3cret-pass');
    app(ConfigService::class)->updateSettings(['dashboard_auth' => 'YES']);

    $this->get('/larapilot/logs')->assertStatus(401);

    $headers = ['Authorization' => 'Basic '.base64_encode('andrea:s3cret-pass')];

    $this->get('/larapilot', $headers)->assertOk()->assertSee('>Logs</a>', false);
    $this->get('/larapilot/logs', $headers)->assertOk()->assertSee('Disk almost full', false);
});

it('hides the log viewer in production and when it is switched off', function (): void {
    logFolder(['laravel.log' => sampleLog()]);
    $this->artisan('larapilot:install')->assertSuccessful();

    config()->set('larapilot.log_viewer.enabled', false);

    $this->get('/larapilot/logs')->assertNotFound();
    $this->get('/larapilot/logs/laravel.log')->assertNotFound();
    $this->get('/larapilot')->assertOk()->assertDontSee('>Logs</a>', false);

    config()->set('larapilot.log_viewer.enabled', true);
    $this->app['env'] = 'production';

    $this->get('/larapilot/logs')->assertNotFound();
    $this->get('/larapilot/logs/laravel.log?download=1')->assertNotFound();
});

it('gives the skills the logs as lean entries, through artisan', function (): void {
    logFolder(['laravel.log' => sampleLog(), 'worker.log' => "plain line\n"]);

    expect(Artisan::call('larapilot:logs', ['--level' => 'error', '--limit' => 2]))->toBe(0);

    $data = json_decode(Artisan::output(), true)['data'];

    expect($data['file']['name'])->toBe('laravel.log')
        ->and($data['redacted'])->toBeTrue()
        ->and($data['format'])->toBe('laravel')
        ->and($data['holds']['levels'])->toBe(['critical' => 1, 'error' => 3, 'warning' => 1, 'info' => 2, 'debug' => 1])
        ->and($data['holds']['whole_file'])->toBeTrue()
        ->and($data['filters'])->toBe(['level' => 'error and worse'])
        ->and($data['more'])->toBeTrue()
        ->and($data['entries'])->toHaveCount(2)
        ->and($data['entries'][0])->toBe(['time' => '2026-10-01 12:30:00', 'level' => 'critical', 'env' => 'production', 'message' => 'Disk almost full on /dev/sda1'])
        ->and($data['entries'][1])->toMatchArray([
            'class' => 'Illuminate\Database\QueryException',
            'where' => 'app/Services/CustomerImporter.php:88',
            // The frames of the application, not the ones of the framework.
            'frames' => [
                'app/Services/CustomerImporter.php:88 Illuminate\Database\Connection->insert()',
                'config/app.php:12 App\Services\CustomerImporter->import()',
            ],
        ]);

    expect(Artisan::call('larapilot:logs', ['--group' => true, '--search' => 'integrity']))->toBe(0);

    $grouped = json_decode(Artisan::output(), true)['data'];

    expect($grouped['grouped'])->toBeTrue()
        ->and($grouped['matched'])->toBe(2)
        ->and($grouped['distinct'])->toBe(1)
        ->and($grouped['entries'][0])->toMatchArray(['count' => 2, 'first' => '2026-10-01 10:00:00', 'time' => '2026-10-01 12:00:00']);

    expect(Artisan::call('larapilot:logs', ['--search' => 'login']))->toBe(0)
        ->and(Artisan::output())->toContain('[REDACTED]')->not->toContain('hunter2-do-not-show');

    expect(Artisan::call('larapilot:logs', ['--files' => true]))->toBe(0);

    $files = json_decode(Artisan::output(), true);

    expect($files['kind'])->toBe('log_files')
        ->and(array_column($files['data']['files'], 'name'))->toEqualCanonicalizing(['laravel.log', 'worker.log']);

    expect(Artisan::call('larapilot:logs', ['--file' => 'worker.log']))->toBe(0)
        ->and(json_decode(Artisan::output(), true)['data']['entries'])->toBe([['message' => 'plain line']]);
});

it('refuses what the logs command cannot read', function (): void {
    logFolder(['laravel.log' => sampleLog()]);

    $this->artisan('larapilot:logs', ['--level' => 'loud'])->assertExitCode(2);
    $this->artisan('larapilot:logs', ['--since' => 'whenever'])->assertExitCode(2);
    $this->artisan('larapilot:logs', ['--file' => '../.env'])->assertExitCode(4);

    config()->set('larapilot.diagnostics.enabled', false);

    $this->artisan('larapilot:logs')->assertExitCode(4);
});

it('answers the logs command with nothing when nothing was logged', function (): void {
    logFolder();

    expect(Artisan::call('larapilot:logs', ['--group' => true, '--level' => 'warning']))->toBe(0);

    $data = json_decode(Artisan::output(), true)['data'];

    expect($data['file'])->toBeNull()
        ->and($data['entries'])->toBe([])
        ->and($data['hint'])->toContain('No .log file');
});

it('lets an agent read the logs through the MCP tool', function (): void {
    logFolder(['laravel.log' => sampleLog()]);

    LarapilotServer::tool(RunArtisanTool::class, ['command' => 'larapilot:logs', 'parameters' => ['--group' => true, '--level' => 'error', '--since' => '2026-10-01']])
        ->assertHasNoErrors()
        ->assertSee('CustomerImporter')
        ->assertDontSee('hunter2-do-not-show');
});

it('redacts a secret written as JSON, and leaves code alone', function (): void {
    $service = app(DiagnosticsService::class);

    expect($service->redact('Login {"email":"a@b.test","password":"hunter2","api_token":"abc123","remember":true}'))
        ->toBe('Login {"email":"a@b.test","password":"[REDACTED]","api_token":"[REDACTED]","remember":true}')
        ->and($service->redact('{"client_secret":"a \"quoted\" value","count":3}'))
        ->toBe('{"client_secret":"[REDACTED]","count":3}')
        // `Password::min()` is a call, not a password.
        ->and($service->redact('#3 /app/Rules.php(12): Illuminate\Validation\Rules\Password::min()'))
        ->toBe('#3 /app/Rules.php(12): Illuminate\Validation\Rules\Password::min()')
        ->and($service->redact('password: hunter2'))->toBe('password: [REDACTED]')
        ->and($service->redact('password=hunter2 failed login'))->toBe('password=[REDACTED] failed login');
});

it('has the bug and error skills read the logs every time', function (): void {
    $root = dirname(__DIR__, 2).'/resources';
    $bug = (string) file_get_contents($root.'/boost/skills/larapilot-bug/SKILL.md');
    $error = (string) file_get_contents($root.'/boost/skills/larapilot-error/SKILL.md');

    expect($bug)->toContain('php artisan larapilot:logs --group --level=warning --since=7d')
        ->toContain('**every time**')
        ->toContain('- **Logs:**')
        ->not->toContain('optional runtime snapshot')
        ->and($error)->toContain('php artisan larapilot:logs --group --search=')
        ->toContain('**every time**')
        ->and(strlen($error))->toBeLessThanOrEqual(10500);

    foreach ([$bug, $error] as $skill) {
        preg_match_all('/larapilot:([a-z0-9-]+)/', $skill, $matches);

        foreach (array_unique($matches[1]) as $command) {
            expect(array_key_exists('larapilot:'.$command, Artisan::all()))->toBeTrue($command);
        }
    }
});

it('opens a log that was just emptied', function (): void {
    logFolder(['laravel.log' => '']);
    $logs = app(LogViewerService::class);
    $file = $logs->current();

    expect($logs->overview($file))->toMatchArray(['entries' => 0, 'complete' => true])
        ->and($logs->read($file)['entries'])->toBe([])
        ->and($logs->groups($file)['groups'])->toBe([]);

    $this->get('/larapilot/logs')->assertOk()->assertSee('This log is empty.', false);

    expect(Artisan::call('larapilot:logs'))->toBe(0)
        ->and(json_decode(Artisan::output(), true)['data']['entries'])->toBe([]);
});

it('redacts the lists, the numbers, and the JSON in a string a context holds', function (): void {
    $service = app(DiagnosticsService::class);

    // What `$request->headers->all()` and a body logged as text look like.
    $line = 'Request {"headers":{"cookie":["laravel_session=abc123"],"x-api-key":["sk-abc"],"php-auth-pw":["s3cret"],"accept":["*/*"]},'
        .'"body":"{\"password\":\"hunter2\",\"name\":\"Ada\"}","api_key":987654,"tokens":{"access":"aaa","refresh":["bbb","c]c"]},'
        .'"prompt_tokens":1500,"token_ttl":3600,"remember_token":null,"user":"ada"}';

    expect($service->redact($line))->toBe(
        'Request {"headers":{"cookie":"[REDACTED]","x-api-key":"[REDACTED]","php-auth-pw":"[REDACTED]","accept":["*/*"]},'
        .'"body":"{\"password\":\"[REDACTED]\",\"name\":\"Ada\"}","api_key":"[REDACTED]","tokens":"[REDACTED]",'
        // How many tokens, and how long one lives, is not a secret.
        .'"prompt_tokens":1500,"token_ttl":3600,"remember_token":null,"user":"ada"}'
    )
        // A value the line cuts short is hidden to the end of the line.
        ->and($service->redact('{"email":"a@b.test","password":"hunter2 cut in the mid'))->toBe('{"email":"a@b.test","password":"[REDACTED]"')
        ->and($service->redact('{"cookie":["a=1","b=2'))->toBe('{"cookie":"[REDACTED]"')
        // What is redacted stays redacted, and stays JSON.
        ->and($service->redact($service->redact($line)))->toBe($service->redact($line))
        ->and(json_decode(substr($service->redact($line), 8), true))->toBeArray();

    // A value far longer than a pattern can follow is still one value.
    $long = $service->redact('{"file":"'.str_repeat('QUJD', 50000).'","password":"hunter2","secret":"'.str_repeat('ab\"', 30000).'","next":"visible"}');

    expect($long)->toEndWith('","password":"[REDACTED]","secret":"[REDACTED]","next":"visible"}');
});

it('shows nothing of a line it could not check for secrets', function (): void {
    $service = app(DiagnosticsService::class);
    $jit = ini_get('pcre.jit');
    $limit = ini_get('pcre.backtrack_limit');

    // A pattern that gives up: the line is hidden, not shown as it was written.
    ini_set('pcre.jit', '0');
    ini_set('pcre.backtrack_limit', '1');

    try {
        expect($service->redact('{"file":"x","password":"hunter2"}'))->toBe(DiagnosticsService::UNCHECKED)
            ->and($service->redact('password=hunter2 failed login'))->toBe(DiagnosticsService::UNCHECKED);
    } finally {
        ini_set('pcre.jit', (string) $jit);
        ini_set('pcre.backtrack_limit', (string) $limit);
    }

    expect($service->redact('password=hunter2 failed login'))->toBe('password=[REDACTED] failed login');
});

it('redacts a secret that sits where an entry or a line is cut', function (): void {
    // The value starts before the 16 KB an entry shows and ends after them.
    $entry = '[2026-10-01 10:00:00] production.INFO: Import '.json_encode([
        'rows' => str_repeat('r', 16300),
        'password' => 'hunter2-do-not-show-'.str_repeat('p', 400),
        'after' => 'the cut',
    ])." \n";

    // The key ends one piece of the download, its value starts the next.
    $head = '[2026-10-01 11:00:00] production.INFO: Dump {"rows":"';
    $tail = '","password":"';
    $line = $head.str_repeat('d', 1048576 - strlen($head) - strlen($tail)).$tail.'hunter2-do-not-show","after":"the piece"}'." \n";

    $log = $entry.$line."[2026-10-01 12:00:00] production.INFO: Last \n";

    logFolder(['laravel.log' => $log]);
    $logs = app(LogViewerService::class);
    $entries = $logs->read($logs->current())['entries'];

    expect($entries)->toHaveCount(3)
        ->and($entries[2]['truncated'])->toBeTrue()
        ->and(strlen($entries[2]['body']))->toBeLessThanOrEqual(16384)
        ->and($entries[2]['body'])->toContain('"password":"[REDACTED]"')
        ->and(implode(' ', array_column($entries, 'body')))->not->toContain('hunter2');

    $this->get('/larapilot/logs')->assertOk()->assertDontSee('hunter2', false);

    $download = $this->get('/larapilot/logs/laravel.log?download=1')->assertOk()->streamedContent();

    expect($download)->not->toContain('hunter2')
        ->toContain('more bytes of this line left out: too long to check for secrets]')
        ->toContain('production.INFO: Last')
        ->and(substr_count($download, "\n"))->toBe(3)
        // As written, on a developer machine, every byte is there.
        ->and($this->get('/larapilot/logs/laravel.log?download=1&secrets=1')->assertOk()->streamedContent() === $log)->toBeTrue();
});

it('shows the start of an entry longer than the pieces a file is read in', function (): void {
    $first = "[2026-10-01 08:00:00] production.INFO: First \n";
    $thrown = thrownEntry('2026-10-01 10:00:00');

    // The entry starts 60 bytes before the end of a piece, counted from the
    // end of the file as the reader counts them, and fills two more.
    $long = $thrown.str_repeat('z', 2 * 262144 + 60 - strlen($thrown) - 1)."\n";

    logFolder(['laravel.log' => $first.$long]);
    $logs = app(LogViewerService::class);
    $entry = $logs->read($logs->current())['entries'][0];

    expect($entry['bytes'])->toBe(2 * 262144 + 60)
        ->and($entry['truncated'])->toBeTrue()
        ->and($entry['exception']['class'])->toBe('Illuminate\Database\QueryException')
        ->and($entry['where'])->toBe('app/Services/CustomerImporter.php:88')
        ->and($entry['frames_total'])->toBe(6)
        ->and($entry['frames_app'])->toBe(2)
        ->and($logs->read($logs->current(), ['search' => 'CustomerController'])['entries'])->toHaveCount(1)
        ->and($logs->groups($logs->current())['groups'][0]['where'])->toBe('app/Services/CustomerImporter.php:88');
});

it('tells the message from a context that holds a brace of its own', function (): void {
    $entry = static fn (string $time, string $sql): string => '['.$time.'] production.ERROR: Import {failed} for a customer {"sql":"'.$sql.'","userId":7,"exception":"[object] (RuntimeException(code: 0): Import failed at /var/www/html/app/Services/CustomerImporter.php:88)'."\n"
        .'[stacktrace]'."\n"
        .'#0 {main}'."\n"
        .'"} '."\n";

    logFolder(['laravel.log' => $entry('2026-10-01 10:00:00', 'insert into t values {x}').$entry('2026-10-01 11:00:00', 'insert into t values {y} and {z}')]);
    $logs = app(LogViewerService::class);
    $read = $logs->read($logs->current())['entries'][0];

    expect($read['message'])->toBe('Import {failed} for a customer')
        ->and($read['context'])->toContain('"sql": "insert into t values {y} and {z}"')->toContain('"userId": 7')
        // The same thing logged twice, whatever its context held.
        ->and($logs->groups($logs->current()))->toMatchArray(['total' => 1, 'entries' => 2]);
});

it('finds a class name as it is shown and as it was written', function (): void {
    logFolder(['laravel.log' => sampleLog(), 'worker.log' => "Failed: App\\Jobs\\SyncStock\n"]);
    $logs = app(LogViewerService::class);
    $file = $logs->current();

    // Monolog doubles the backslashes; the page shows them single.
    expect($logs->read($file, ['search' => 'Illuminate\Database\QueryException'])['entries'])->toHaveCount(2)
        ->and($logs->read($file, ['search' => 'Illuminate\\\\Database\\\\QueryException'])['entries'])->toHaveCount(2)
        ->and($logs->groups($file, ['search' => 'Database\QueryException'])['entries'])->toBe(2)
        ->and($logs->read($file, ['search' => 'Illuminate\Database\NoSuchException'])['entries'])->toHaveCount(0)
        ->and($logs->read($logs->find('worker.log'), ['search' => 'App\Jobs\SyncStock'])['entries'])->toHaveCount(1)
        ->and($logs->read($logs->find('worker.log'), ['search' => 'App\\\\Jobs\\\\SyncStock'])['entries'])->toHaveCount(1);

    $this->get('/larapilot/logs?q='.rawurlencode('Illuminate\Database\QueryException'))
        ->assertOk()
        ->assertSee('2 entries found', false)
        ->assertSee('<dt><mark>Illuminate\Database\QueryException</mark></dt>', false);

    expect(Artisan::call('larapilot:logs', ['--search' => 'Illuminate\Database\QueryException']))->toBe(0)
        ->and(json_decode(Artisan::output(), true)['data']['entries'])->toHaveCount(2);
});

it('stops reading where the period asked for ends', function (): void {
    Carbon::setTestNow('2026-10-03 12:00:00');
    $folder = logFolder();
    $handle = fopen($folder.'/laravel.log', 'wb');

    for ($index = 0; $index < 14000; $index++) {
        fwrite($handle, sprintf("[2026-10-01 %02d:%02d:%02d] production.INFO: Old entry %05d %s \n", intdiv($index, 3600), intdiv($index, 60) % 60, $index % 60, $index, str_repeat('x', 60)));
    }

    // Two processes write at once: one entry a little out of its place.
    fwrite($handle, "[2026-10-03 11:50:00] production.INFO: Recent, written first \n");
    fwrite($handle, "[2026-10-03 11:40:00] production.INFO: Just before the hour \n");
    fwrite($handle, "[2026-10-03 11:55:00] production.INFO: Recent \n");
    fclose($handle);

    config()->set('larapilot.log_viewer.scan_mb', 1);
    $logs = app(LogViewerService::class);
    $file = $logs->current();

    expect($file['size'])->toBeGreaterThan(1048576);

    $day = $logs->read($file, ['since' => '15m']);

    // Nothing older can match: the page does not offer to keep reading.
    expect(array_column($day['entries'], 'message'))->toBe(['Recent', 'Recent, written first'])
        ->and($day['next'])->toBeNull()
        ->and($day['resume'])->toBeNull()
        ->and($logs->groups($file, ['since' => '15m']))->toMatchArray(['entries' => 2, 'complete' => true])
        // Without a period the megabyte is read, and there is more.
        ->and($logs->read($file, ['search' => 'nowhere'])['resume'])->toBeGreaterThan(0);

    $this->get('/larapilot/logs?since=1h')
        ->assertOk()
        ->assertSee('3 entries found', false)
        ->assertDontSee('Keep reading older', false);

    expect(Artisan::call('larapilot:logs', ['--since' => '1h']))->toBe(0)
        ->and(json_decode(Artisan::output(), true)['data'])->toMatchArray(['more' => false]);

    Carbon::setTestNow();
});

it('counts the repeats of a log where nothing repeats without keeping every entry', function (): void {
    $folder = logFolder();
    $handle = fopen($folder.'/laravel.log', 'wb');
    $letters = static fn (int $number): string => strtr(str_pad(base_convert((string) $number, 10, 26), 4, '0', STR_PAD_LEFT), '0123456789', 'qrstuvwxyz');

    for ($index = 0; $index < 10040; $index++) {
        fwrite($handle, sprintf("[2026-10-01 10:00:00] production.INFO: Signed in as %s@example.test \n", $letters($index)));
    }

    fwrite($handle, str_repeat("[2026-10-01 11:00:00] production.WARNING: Slow query \n", 3));
    fclose($handle);

    $logs = app(LogViewerService::class);
    $groups = $logs->groups($logs->current());

    expect($groups['entries'])->toBe(10043)
        ->and($groups['total'])->toBe(10000)
        ->and($groups['capped'])->toBeTrue()
        ->and($groups['groups'][0])->toMatchArray(['count' => 3, 'message' => 'Slow query'])
        ->and($groups['groups'][1]['message'])->toBe('Signed in as '.$letters(10039).'@example.test');

    $this->get('/larapilot/logs?view=groups')
        ->assertOk()
        ->assertSee('of more than 10,000 things logged, in 10,043 entries', false);

    expect(Artisan::call('larapilot:logs', ['--group' => true, '--limit' => 2]))->toBe(0);

    $data = json_decode(Artisan::output(), true)['data'];

    expect($data['distinct'])->toBe(10000)
        ->and($data['hint'])->toContain('More than 10,000 different things');
});

it('reads a folder given from the root of the project, wherever it runs from', function (): void {
    $folder = logFolder(['laravel.log' => sampleLog()]);
    $up = str_repeat('../', substr_count(trim(str_replace('\\', '/', (string) realpath(base_path())), '/'), '/') + 1);

    config()->set('larapilot.log_viewer.path', $up.ltrim((string) realpath($folder), '/'));

    $logs = app(LogViewerService::class);

    expect($logs->directory())->toStartWith(base_path())
        ->and(array_column($logs->files(), 'key'))->toBe(['laravel.log']);

    $working = getcwd();
    chdir(sys_get_temp_dir());

    try {
        expect(array_column($logs->files(), 'key'))->toBe(['laravel.log']);
        $this->get('/larapilot/logs')->assertOk()->assertSee('Disk almost full', false);
    } finally {
        chdir($working);
    }
});

it('says the application writes to a file only when it does', function (): void {
    $folder = logFolder(['laravel.log' => sampleLog(), 'worker.log' => "plain line\n"]);
    touch($folder.'/worker.log', time() - 60);

    // The default channel writes nowhere in this folder.
    config()->set('logging.default', 'stderr');
    config()->set('logging.channels.stderr', ['driver' => 'monolog']);

    $logs = app(LogViewerService::class);

    expect(array_column($logs->files(), 'current'))->toBe([false, false])
        // The newest one is still the one that opens.
        ->and($logs->current()['key'])->toBe('laravel.log');

    $this->get('/larapilot/logs')
        ->assertOk()
        ->assertSee('Disk almost full', false)
        ->assertDontSee('The application writes here', false);

    expect(Artisan::call('larapilot:logs', ['--files' => true]))->toBe(0)
        ->and(array_column(json_decode(Artisan::output(), true)['data']['files'], 'current'))->toBe([false, false]);
});

it('does not say a file read by line was filtered by level or by period', function (): void {
    logFolder(['laravel.log' => sampleLog(), 'worker.log' => "Processing: App\\Jobs\\SendInvoice\nFailed: App\\Jobs\\SyncStock\n"]);

    expect(Artisan::call('larapilot:logs', ['--file' => 'worker.log', '--level' => 'error', '--since' => '1h', '--search' => 'failed']))->toBe(0);

    $data = json_decode(Artisan::output(), true)['data'];

    expect($data['format'])->toBe('plain')
        ->and($data['filters'])->toBe(['search' => 'failed'])
        ->and($data['hint'])->toContain('--level and --since were left out')
        ->and($data['entries'])->toBe([['message' => 'Failed: App\Jobs\SyncStock']]);

    expect(Artisan::call('larapilot:logs', ['--level' => 'error', '--since' => '2026-10-01']))->toBe(0);

    $data = json_decode(Artisan::output(), true)['data'];

    expect($data['filters'])->toBe(['level' => 'error and worse', 'since' => '2026-10-01'])
        ->and($data)->not->toHaveKey('hint');
});

it('remembers what it read of a file for one request only', function (): void {
    logFolder(['laravel.log' => sampleLog()]);
    $logs = app(LogViewerService::class);

    expect(app(LogViewerService::class))->toBe($logs);

    // What Octane and a queue worker do between two requests.
    app()->forgetScopedInstances();

    expect(app(LogViewerService::class))->not->toBe($logs);
});
