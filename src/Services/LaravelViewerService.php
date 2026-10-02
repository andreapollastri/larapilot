<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Closure;
use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Larapilot\Services\Laravel\DumpRecorder;
use Larapilot\Services\Laravel\MailRecorder;
use ReflectionClass;
use ReflectionFunction;
use Throwable;

/**
 * How Laravel is set up in this application and what it is doing: the
 * drivers in use, what is cached, the scheduled tasks, the jobs that wait
 * and the ones that failed, and what the recorders kept — the outgoing
 * mail and the dumps.
 *
 * Read by the Laravel page of the dashboard. Nothing here changes the
 * application: a cache is not cleared, a job is not retried.
 */
class LaravelViewerService
{
    /** How many jobs, and how many failed jobs, one page lists. */
    public const LISTED = 50;

    /** How many files of a cache folder are counted before the page says "more than". */
    protected const COUNTED = 5000;

    /** @var array<string, string|null> */
    protected array $links = [];

    protected ?bool $browsable = null;

    public function __construct(
        protected ConfigService $config,
        protected DiagnosticsService $diagnostics,
        protected FileManagerService $project,
        protected MailRecorder $mail,
        protected DumpRecorder $dumps,
    ) {}

    /**
     * The service each part of the framework runs on: the name of the
     * store, connection, or mailer in use, its driver, and what says where
     * it points — never a password or a key.
     *
     * @return list<array{service: string, name: string, driver: string, facts: list<string>, note: string|null}>
     */
    public function drivers(): array
    {
        $rows = [];

        $name = $this->text('database.default');
        $settings = $this->settings("database.connections.{$name}");
        $rows[] = $this->driver('Database', $name, $settings, array_filter([
            $this->fact('database', $this->relative((string) ($settings['database'] ?? ''))),
            $this->host($settings),
        ]));

        $name = $this->text('cache.default');
        $settings = $this->settings("cache.stores.{$name}");
        $rows[] = $this->driver('Cache', $name, $settings, array_filter([
            $this->fact('prefix', $this->text('cache.prefix')),
            $this->fact('path', $this->relative((string) ($settings['path'] ?? ''))),
            $this->fact('table', $settings['table'] ?? null),
            $this->fact('connection', $settings['connection'] ?? null),
        ]), [
            'array' => 'Kept in memory: lost at the end of each request.',
            'null' => 'Nothing is kept: every read misses.',
        ]);

        $driver = $this->text('session.driver');
        $rows[] = $this->driver('Session', $driver, ['driver' => $driver], array_filter([
            $this->fact('lifetime', $this->text('session.lifetime') !== '' ? $this->text('session.lifetime').' min' : null),
            $driver === 'database' ? $this->fact('table', config('session.table')) : null,
            $this->fact('connection', config('session.connection')),
            $this->fact('store', config('session.store')),
            config('session.encrypt') ? 'encrypted' : null,
        ]), [
            'array' => 'Kept in memory: nobody stays signed in between two requests.',
        ]);

        $name = $this->text('queue.default');
        $settings = $this->settings("queue.connections.{$name}");
        $rows[] = $this->driver('Queue', $name, $settings, array_filter([
            $this->fact('queue', $settings['queue'] ?? null),
            $this->fact('table', $settings['table'] ?? null),
            $this->fact('connection', $settings['connection'] ?? null),
            $this->fact('retry after', isset($settings['retry_after']) ? $settings['retry_after'].' s' : null),
        ]), [
            'sync' => 'A job runs inside the request that dispatches it: nothing waits, no worker is needed.',
            'null' => 'A dispatched job is thrown away.',
        ]);

        $name = $this->text('mail.default');
        $settings = $this->settings("mail.mailers.{$name}");
        $rows[] = $this->driver('Mail', $name, ['driver' => $settings['transport'] ?? ''], array_filter([
            $this->host($settings),
            $this->fact('from', config('mail.from.address')),
            $this->fact('mailers', is_array($settings['mailers'] ?? null) ? implode(', ', $settings['mailers']) : null),
            $this->fact('channel', $settings['channel'] ?? null),
        ]), [
            'log' => 'A mail is written to the log and reaches nobody.',
            'array' => 'A mail is kept in memory and reaches nobody.',
        ]);

        $name = $this->text('filesystems.default');
        $settings = $this->settings("filesystems.disks.{$name}");
        $rows[] = $this->driver('Filesystem', $name, $settings, array_filter([
            $this->fact('root', $this->relative((string) ($settings['root'] ?? ''))),
            $this->fact('bucket', $settings['bucket'] ?? null),
            $this->fact('region', $settings['region'] ?? null),
            $this->fact('disks', implode(', ', array_keys((array) config('filesystems.disks', [])))),
        ]));

        $name = $this->text('broadcasting.default');

        if ($name !== '') {
            $rows[] = $this->driver('Broadcasting', $name, $this->settings("broadcasting.connections.{$name}"), [], [
                'null' => 'An event that is broadcast reaches nobody.',
                'log' => 'An event that is broadcast is written to the log.',
            ]);
        }

        $name = $this->text('logging.default');
        $settings = $this->settings("logging.channels.{$name}");
        $rows[] = $this->driver('Logging', $name, $settings, array_filter([
            $this->fact('channels', is_array($settings['channels'] ?? null) ? implode(', ', $settings['channels']) : null),
            $this->fact('level', $settings['level'] ?? null),
        ]));

        if ($this->text('hashing.driver') !== '') {
            $rows[] = $this->driver('Hashing', $this->text('hashing.driver'), ['driver' => $this->text('hashing.driver')], []);
        }

        if ($this->text('scout.driver') !== '') {
            $rows[] = $this->driver('Scout', $this->text('scout.driver'), ['driver' => $this->text('scout.driver')], array_filter([
                $this->fact('prefix', config('scout.prefix')),
                config('scout.queue') ? 'queued' : null,
            ]));
        }

        if ($this->text('octane.server') !== '') {
            $rows[] = $this->driver('Octane', $this->text('octane.server'), ['driver' => $this->text('octane.server')], []);
        }

        return $rows;
    }

    /**
     * What Laravel keeps ready so it does not work it out again: the
     * configuration, the routes, the events, the compiled views — and the
     * cache of the application itself.
     *
     * @return list<array{key: string, label: string, cached: bool, state: string, detail: string, warning: string|null, build: string|null, clear: string}>
     */
    public function caches(): array
    {
        $app = app();
        $local = $app->environment(['local', 'development']);

        $rows = [
            $this->cachedFile('config', 'Configuration', $app->configurationIsCached(), $app->getCachedConfigPath(), 'config:cache', 'config:clear',
                $local ? 'A change to .env or config/ is not read until the cache is cleared.' : null),
            $this->cachedFile('routes', 'Routes', $app->routesAreCached(), $app->getCachedRoutesPath(), 'route:cache', 'route:clear',
                $local ? 'A new or changed route is not seen until the cache is cleared.' : null),
            $this->cachedFile('events', 'Events', $app->eventsAreCached(), $app->getCachedEventsPath(), 'event:cache', 'event:clear',
                $local ? 'A new listener is not discovered until the cache is cleared.' : null),
        ];

        $compiled = $this->text('view.compiled');
        $views = $compiled !== '' ? $this->folder($compiled, '.php') : ['files' => 0, 'bytes' => 0, 'modified' => null, 'more' => false];

        $rows[] = [
            'key' => 'views',
            'label' => 'Views',
            'cached' => $views['files'] > 0,
            'state' => $views['files'] > 0 ? $this->count($views).' compiled' : 'None compiled',
            'detail' => $views['files'] > 0
                ? $this->project->formatBytes($views['bytes']).' in '.$this->relative($compiled).$this->since($views['modified'], ', the last one ')
                : 'A view is compiled the first time it is shown.',
            'warning' => null,
            'build' => 'view:cache',
            'clear' => 'view:clear',
        ];

        $rows[] = $this->applicationCache();

        return $rows;
    }

    /**
     * The tasks the scheduler knows, the next one due first. The schedule
     * is defined for the console, so the commands of the application are
     * loaded here the way `schedule:list` loads them.
     *
     * @return array{error: string|null, timezone: string, tasks: list<array<string, mixed>>}
     */
    public function schedule(): array
    {
        $timezone = $this->text('app.schedule_timezone') ?: ($this->text('app.timezone') ?: 'UTC');

        try {
            $kernel = app(ConsoleKernel::class);
            // Loads routes/console.php and the commands, then starts the
            // console, which is when `withSchedule()` adds its tasks.
            $kernel->all();

            $events = app(Schedule::class)->events();
        } catch (Throwable $e) {
            return ['error' => $this->reason($e), 'timezone' => $timezone, 'tasks' => []];
        }

        $tasks = [];

        foreach ($events as $event) {
            try {
                $tasks[] = $this->task($event);
            } catch (Throwable) {
                // One task that cannot be described does not hide the others.
            }
        }

        usort($tasks, static fn (array $a, array $b): int => [$a['next_at'] ?? PHP_INT_MAX, $a['command']] <=> [$b['next_at'] ?? PHP_INT_MAX, $b['command']]);

        return ['error' => null, 'timezone' => $timezone, 'tasks' => $tasks];
    }

    /**
     * The connections the queue may use, and what waits on one of them —
     * the default, unless another is asked for by name.
     *
     * @return array{default: string, connections: list<array{name: string, driver: string, default: bool, current: bool}>, connection: array<string, mixed>, failed: array<string, mixed>, batches: int|null}
     */
    public function queue(?string $connection = null): array
    {
        $default = $this->text('queue.default');
        $configured = (array) config('queue.connections', []);
        $current = is_string($connection) && isset($configured[$connection]) ? $connection : $default;
        $connections = [];

        foreach ($configured as $name => $settings) {
            $connections[] = [
                'name' => (string) $name,
                'driver' => (string) ($settings['driver'] ?? ''),
                'default' => $name === $default,
                'current' => $name === $current,
            ];
        }

        return [
            'default' => $default,
            'connections' => $connections,
            'connection' => $this->connection($current, $this->settings("queue.connections.{$current}")),
            'failed' => $this->failed(),
            'batches' => $this->openBatches(),
        ];
    }

    /**
     * The outgoing mail that was kept, the newest first.
     *
     * @return array{recording: bool, records: list<array<string, mixed>>, keep: int, directory: string, mailer: string, transport: string}
     */
    public function mail(): array
    {
        $mailer = $this->text('mail.default');

        return [
            'recording' => $this->mail->recording(),
            'records' => $this->mail->store()->all(),
            'keep' => $this->mail->store()->keep(),
            'directory' => $this->mail->store()->directoryLabel(),
            'mailer' => $mailer,
            'transport' => $this->text("mail.mailers.{$mailer}.transport"),
        ];
    }

    /**
     * One mail, with its bodies.
     *
     * @return array<string, mixed>|null
     */
    public function message(string $id): ?array
    {
        $record = $this->mail->store()->find($id);

        if ($record === null) {
            return null;
        }

        return $record + [
            'html_body' => $this->mail->store()->part($id, 'html'),
            'text_body' => $this->mail->store()->part($id, 'txt'),
        ];
    }

    public function clearMail(): int
    {
        return $this->mail->store()->clear();
    }

    /**
     * What `dump()` and `dd()` printed, the newest first, each with the
     * file manager page of the file that dumped it.
     *
     * @return array{recording: bool, taken_by: string|null, records: list<array<string, mixed>>, keep: int, directory: string}
     */
    public function dumps(): array
    {
        $records = array_map(fn (array $record): array => $record + [
            'link' => is_string($record['file'] ?? null) ? $this->link($record['file']) : null,
        ], $this->dumps->store()->all());

        return [
            'recording' => $this->dumps->recording(),
            'taken_by' => DumpRecorder::takenBy(),
            'records' => $records,
            'keep' => $this->dumps->store()->keep(),
            'directory' => $this->dumps->store()->directoryLabel(),
        ];
    }

    public function clearDumps(): int
    {
        return $this->dumps->store()->clear();
    }

    /**
     * How many mails and dumps are kept, for the overview.
     *
     * @return array{mail: int, mail_recording: bool, dumps: int, dumps_recording: bool}
     */
    public function recorded(): array
    {
        return [
            'mail' => $this->mail->store()->count(),
            'mail_recording' => $this->mail->recording(),
            'dumps' => $this->dumps->store()->count(),
            'dumps_recording' => $this->dumps->recording(),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<int, string>  $facts
     * @param  array<string, string>  $notes  by driver
     * @return array{service: string, name: string, driver: string, facts: list<string>, note: string|null}
     */
    protected function driver(string $service, string $name, array $settings, array $facts, array $notes = []): array
    {
        $driver = is_string($settings['driver'] ?? null) ? $settings['driver'] : '';

        return [
            'service' => $service,
            'name' => $name,
            'driver' => $driver,
            'facts' => array_values($facts),
            'note' => $notes[$driver] ?? null,
        ];
    }

    protected function fact(string $label, mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? $label.' '.$value : null;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function host(array $settings): ?string
    {
        $host = $settings['host'] ?? null;

        if (! is_string($host) || $host === '') {
            return null;
        }

        $port = $settings['port'] ?? null;

        return $host.(is_scalar($port) && (string) $port !== '' ? ':'.$port : '');
    }

    /**
     * @return array{key: string, label: string, cached: bool, state: string, detail: string, warning: string|null, build: string|null, clear: string}
     */
    protected function cachedFile(string $key, string $label, bool $cached, string $path, string $build, string $clear, ?string $warning): array
    {
        clearstatcache(true, $path);
        $modified = $cached && is_file($path) ? (int) filemtime($path) : null;

        return [
            'key' => $key,
            'label' => $label,
            'cached' => $cached,
            'state' => $cached ? 'Cached' : 'Not cached',
            'detail' => $cached
                ? $this->relative($path).$this->since($modified, ', written ')
                : 'Read from the files of the project at every request.',
            'warning' => $cached ? $warning : null,
            'build' => $build,
            'clear' => $clear,
        ];
    }

    /**
     * The cache the application reads and writes through `Cache::`: the
     * store, whether it answers, and how much it holds where that can be
     * counted without reading every key of a server.
     *
     * @return array{key: string, label: string, cached: bool, state: string, detail: string, warning: string|null, build: string|null, clear: string}
     */
    protected function applicationCache(): array
    {
        $name = $this->text('cache.default');
        $settings = $this->settings("cache.stores.{$name}");
        $driver = (string) ($settings['driver'] ?? '');
        $row = [
            'key' => 'application',
            'label' => 'Application cache',
            'cached' => true,
            'state' => $name !== '' ? $name : 'None',
            'detail' => '',
            'warning' => null,
            'build' => null,
            'clear' => 'cache:clear',
        ];

        try {
            // A read of a key nothing writes: it only tells whether the
            // store answers.
            Cache::store($name !== '' ? $name : null)->get('larapilot:laravel-viewer:probe');
        } catch (Throwable $e) {
            return ['cached' => false, 'state' => 'Does not answer', 'detail' => $this->reason($e)] + $row;
        }

        $held = null;

        try {
            if ($driver === 'file' && is_string($settings['path'] ?? null)) {
                $folder = $this->folder($settings['path'], '', true);
                $held = $this->count($folder, 'entry', 'entries').', '.$this->project->formatBytes($folder['bytes']).' in '.$this->relative($settings['path']);
            } elseif ($driver === 'database') {
                $rows = DB::connection($settings['connection'] ?? null)->table((string) ($settings['table'] ?? 'cache'))->count();
                $held = number_format($rows).' '.($rows === 1 ? 'entry' : 'entries').' in the table '.($settings['table'] ?? 'cache');
            }
        } catch (Throwable) {
            // The store answered; how much it holds stays unknown.
        }

        $row['detail'] = $held ?? match ($driver) {
            'array' => 'Kept in memory: lost at the end of each request.',
            'null' => 'Nothing is kept: every read misses.',
            default => 'The '.$driver.' store answers; what it holds is not counted.',
        };

        return $row;
    }

    /**
     * What a folder holds, counted up to a ceiling so a cache of a million
     * files does not hold the page.
     *
     * @return array{files: int, bytes: int, modified: int|null, more: bool}
     */
    protected function folder(string $path, string $suffix = '', bool $deep = false): array
    {
        $result = ['files' => 0, 'bytes' => 0, 'modified' => null, 'more' => false];
        $pending = [rtrim($path, '/\\')];

        while ($pending !== []) {
            $directory = array_pop($pending);

            foreach (is_dir($directory) ? (@scandir($directory) ?: []) : [] as $name) {
                if ($name === '.' || $name === '..' || $name === '.gitignore') {
                    continue;
                }

                $item = $directory.DIRECTORY_SEPARATOR.$name;

                if (is_dir($item)) {
                    if ($deep && ! is_link($item)) {
                        $pending[] = $item;
                    }

                    continue;
                }

                if ($suffix !== '' && ! str_ends_with($name, $suffix)) {
                    continue;
                }

                if ($result['files'] >= self::COUNTED) {
                    $result['more'] = true;

                    return $result;
                }

                $result['files']++;
                $result['bytes'] += (int) @filesize($item);
                $result['modified'] = max((int) $result['modified'], (int) @filemtime($item));
            }
        }

        return $result;
    }

    /**
     * @param  array{files: int, more: bool}  $folder
     */
    protected function count(array $folder, string $one = 'view', string $many = 'views'): string
    {
        return ($folder['more'] ? 'more than ' : '').number_format($folder['files']).' '.($folder['files'] === 1 ? $one : $many);
    }

    protected function since(?int $time, string $lead): string
    {
        return $time !== null && $time > 0 ? $lead.Carbon::createFromTimestamp($time)->diffForHumans() : '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function task(Event $event): array
    {
        $callback = $event instanceof CallbackEvent;
        $command = $callback ? (string) $event->getSummaryForDisplay() : $this->command((string) $event->command);
        $where = null;

        if ($callback && in_array($command, ['Closure', 'Callback'], true)) {
            $where = $this->closure($event);
            $command = $where !== null ? 'Closure' : $command;
        }

        $description = is_string($event->description) && $event->description !== '' && $event->description !== $command ? $event->description : null;
        $next = null;

        try {
            $next = Carbon::instance($event->nextRunDate());
        } catch (Throwable) {
            // An expression cron cannot read has no next run.
        }

        $environments = array_values(array_filter((array) $event->environments, 'is_string'));

        return [
            'command' => $command,
            'kind' => $callback ? 'callback' : (str_starts_with($command, 'php artisan ') ? 'artisan' : 'shell'),
            'description' => $description,
            'where' => $where,
            'where_link' => $where !== null ? $this->link((string) strstr($where.':', ':', true)) : null,
            'expression' => (string) $event->expression,
            'repeat' => $event->repeatSeconds,
            'timezone' => $event->timezone instanceof \DateTimeZone ? $event->timezone->getName() : (is_string($event->timezone) && $event->timezone !== '' ? $event->timezone : null),
            'next_at' => $next?->getTimestamp(),
            'next_label' => $next?->format('D j M, H:i'),
            'next_human' => $next?->diffForHumans(),
            'runs_here' => $event->runsInEnvironment(app()->environment()),
            'environments' => $environments,
            'without_overlapping' => (bool) $event->withoutOverlapping,
            'one_server' => (bool) $event->onOneServer,
            'background' => (bool) $event->runInBackground,
            'maintenance' => (bool) $event->evenInMaintenanceMode,
        ];
    }

    /**
     * A command as it is typed: `php artisan inspire`, without the path of
     * the PHP binary the scheduler would call it with.
     */
    protected function command(string $command): string
    {
        $command = str_replace([Artisan::phpBinary(), Artisan::artisanBinary()], ['php', 'artisan'], $command);

        return trim((string) preg_replace('/\s+/', ' ', $command));
    }

    /**
     * Where a scheduled closure was written, as `file:line`.
     */
    protected function closure(CallbackEvent $event): ?string
    {
        try {
            $callback = (new ReflectionClass($event))->getProperty('callback')->getValue($event);

            if ($callback instanceof Closure) {
                $function = new ReflectionFunction($callback);
                $file = $function->getFileName();

                return is_string($file) ? $this->relative($file).':'.$function->getStartLine() : null;
            }
        } catch (Throwable) {
            //
        }

        return null;
    }

    /**
     * What waits on one connection.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    protected function connection(string $name, array $settings): array
    {
        $driver = (string) ($settings['driver'] ?? '');
        $result = [
            'name' => $name,
            'driver' => $driver,
            'error' => null,
            'note' => match ($driver) {
                'sync' => 'A job runs inside the request that dispatches it, so nothing ever waits here and no worker is needed.',
                'null' => 'A job dispatched to this connection is thrown away.',
                default => null,
            },
            'queues' => [],
            'jobs' => [],
            'listed' => false,
            'totals' => ['waiting' => 0, 'delayed' => 0, 'reserved' => 0],
        ];

        if ($name === '' || in_array($driver, ['sync', 'null', ''], true)) {
            return $result;
        }

        try {
            $queue = Queue::connection($name);

            if ($queue instanceof DatabaseQueue) {
                $result = $this->databaseQueue($settings) + $result;
            } elseif ($queue instanceof RedisQueue) {
                $result = $this->redisQueue($queue, $name, $settings) + $result;
            } else {
                $result = $this->otherQueue($queue, $settings) + $result;
            }
        } catch (Throwable $e) {
            $result['error'] = $this->reason($e);

            return $result;
        }

        foreach ($result['queues'] as $row) {
            foreach (['waiting', 'delayed', 'reserved'] as $state) {
                $result['totals'][$state] += (int) ($row[$state] ?? 0);
            }
        }

        return $result;
    }

    /**
     * The `jobs` table: every queue that has a row, and the first jobs in
     * the order a worker takes them.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    protected function databaseQueue(array $settings): array
    {
        $table = (string) ($settings['table'] ?? 'jobs');
        $jobs = fn () => DB::connection($settings['connection'] ?? null)->table($table);
        $now = Carbon::now()->getTimestamp();
        $queues = [];

        $counted = [
            'total' => $jobs(),
            'reserved' => $jobs()->whereNotNull('reserved_at'),
            'delayed' => $jobs()->whereNull('reserved_at')->where('available_at', '>', $now),
        ];

        foreach ($counted as $state => $query) {
            foreach ($query->selectRaw('queue, count(*) as jobs')->groupBy('queue')->get() as $row) {
                $queues[(string) $row->queue] ??= ['name' => (string) $row->queue, 'total' => 0, 'reserved' => 0, 'delayed' => 0];
                $queues[(string) $row->queue][$state] = (int) $row->jobs;
            }
        }

        $default = (string) ($settings['queue'] ?? 'default');
        $queues[$default] ??= ['name' => $default, 'total' => 0, 'reserved' => 0, 'delayed' => 0];
        ksort($queues);

        $listed = [];

        foreach ($jobs()->orderBy('id')->limit(self::LISTED)->get() as $row) {
            $listed[] = [
                'id' => (string) $row->id,
                'queue' => (string) $row->queue,
                'attempts' => (int) $row->attempts,
                'state' => $row->reserved_at !== null ? 'reserved' : ((int) $row->available_at > $now ? 'delayed' : 'waiting'),
                'at' => (int) ($row->reserved_at ?? $row->available_at),
                'created' => (int) $row->created_at,
            ] + $this->payload((string) $row->payload);
        }

        return [
            'queues' => array_values(array_map(static fn (array $row): array => $row + [
                'waiting' => max(0, $row['total'] - $row['reserved'] - $row['delayed']),
            ], $queues)),
            'jobs' => $listed,
            'listed' => true,
            'table' => $table,
        ];
    }

    /**
     * A Redis queue: the list of what waits, and the two sets beside it —
     * what is delayed and what a worker holds. The queues are the one of
     * the connection, the ones Horizon is told to work, and every other
     * one that holds a job.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    protected function redisQueue(RedisQueue $queue, string $connection, array $settings): array
    {
        $redis = $queue->getConnection();
        $queues = [];
        $listed = [];

        foreach ($this->redisQueueNames($queue, $connection, $settings) as $name) {
            $key = $queue->getQueue($name);
            $row = [
                'name' => $name,
                'waiting' => (int) $redis->llen($key),
                'delayed' => (int) $redis->zcard($key.':delayed'),
                'reserved' => (int) $redis->zcard($key.':reserved'),
            ];
            $queues[] = $row + ['total' => $row['waiting'] + $row['delayed'] + $row['reserved']];

            foreach (count($listed) < self::LISTED ? (array) $redis->lrange($key, 0, self::LISTED - count($listed) - 1) : [] as $payload) {
                $job = $this->payload((string) $payload);
                $decoded = json_decode((string) $payload, true);

                $listed[] = [
                    'id' => (string) ($decoded['id'] ?? $decoded['uuid'] ?? ''),
                    'queue' => $name,
                    'attempts' => (int) ($decoded['attempts'] ?? 0),
                    'state' => 'waiting',
                    'at' => null,
                    'created' => isset($decoded['pushedAt']) ? (int) $decoded['pushedAt'] : null,
                ] + $job;
            }
        }

        return ['queues' => $queues, 'jobs' => $listed, 'listed' => true];
    }

    /**
     * The queues of a Redis connection: the ones the configuration names,
     * then the ones found by their keys — a job dispatched with
     * `onQueue()` waits on a queue nothing else declares. Asking for the
     * keys reads the whole keyspace, which a server that is not production
     * answers at once; a cluster that refuses is left to the names known.
     *
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    protected function redisQueueNames(RedisQueue $queue, string $connection, array $settings): array
    {
        $names = $this->queueNames($connection, $settings);

        try {
            foreach ((array) $queue->getConnection()->command('keys', ['queues:*']) as $key) {
                $at = strpos((string) $key, 'queues:');
                $name = $at === false ? '' : (string) preg_replace('/:(?:delayed|reserved|notify)$/', '', substr((string) $key, $at + 7));

                if ($name !== '') {
                    $names[] = $name;
                }
            }
        } catch (Throwable) {
            //
        }

        return array_slice(array_values(array_unique($names)), 0, self::LISTED);
    }

    /**
     * Any other driver — SQS, Beanstalkd, one a package adds: how many
     * jobs it says wait, and no more.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    protected function otherQueue(object $queue, array $settings): array
    {
        $name = (string) ($settings['queue'] ?? 'default');
        $total = (int) $queue->size();
        $row = ['name' => basename($name), 'total' => $total, 'waiting' => $total, 'delayed' => null, 'reserved' => null];

        // Laravel 12 and later tell the three apart.
        if (method_exists($queue, 'pendingSize') && method_exists($queue, 'delayedSize') && method_exists($queue, 'reservedSize')) {
            try {
                $row = ['waiting' => (int) $queue->pendingSize(), 'delayed' => (int) $queue->delayedSize(), 'reserved' => (int) $queue->reservedSize()] + $row;
            } catch (Throwable) {
                //
            }
        }

        return ['queues' => [$row], 'jobs' => [], 'listed' => false];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    protected function queueNames(string $connection, array $settings): array
    {
        $names = [(string) ($settings['queue'] ?? 'default')];
        $supervisors = array_merge(
            (array) config('horizon.defaults', []),
            (array) config('horizon.environments.'.app()->environment(), []),
        );

        foreach ($supervisors as $supervisor) {
            if (is_array($supervisor) && ($supervisor['connection'] ?? $connection) === $connection) {
                foreach ((array) ($supervisor['queue'] ?? []) as $name) {
                    $names[] = (string) $name;
                }
            }
        }

        return array_values(array_unique(array_filter($names, static fn (string $name): bool => $name !== '')));
    }

    /**
     * What a job is, from the payload Laravel wrote for it.
     *
     * @return array{job: string, tries: int|null}
     */
    protected function payload(string $payload): array
    {
        $decoded = json_decode($payload, true);
        $decoded = is_array($decoded) ? $decoded : [];
        $name = $decoded['displayName'] ?? $decoded['job'] ?? null;

        return [
            'job' => is_string($name) && $name !== '' ? $name : 'Unknown job',
            'tries' => isset($decoded['maxTries']) && is_numeric($decoded['maxTries']) ? (int) $decoded['maxTries'] : null,
        ];
    }

    /**
     * The jobs that failed, the last one first: what, where, when, and the
     * first line of the exception with its secrets redacted.
     *
     * @return array{driver: string, stored: bool, count: int|null, jobs: list<array<string, mixed>>, error: string|null}
     */
    protected function failed(): array
    {
        $driver = $this->text('queue.failed.driver') ?: ($this->text('queue.failed.table') !== '' ? 'database' : 'null');
        $result = ['driver' => $driver, 'stored' => $driver !== 'null', 'count' => null, 'jobs' => [], 'error' => null];

        if (! $result['stored']) {
            return $result;
        }

        try {
            if (in_array($driver, ['database', 'database-uuids'], true)) {
                $table = fn () => DB::connection(config('queue.failed.database'))->table((string) config('queue.failed.table', 'failed_jobs'));
                $result['count'] = (int) $table()->count();
                $rows = $table()->orderByDesc('id')->limit(self::LISTED)->get()->all();
            } else {
                $rows = array_values((array) app('queue.failer')->all());
                $result['count'] = count($rows);
                $rows = array_slice($rows, 0, self::LISTED);
            }
        } catch (Throwable $e) {
            $result['error'] = $this->reason($e);

            return $result;
        }

        foreach ($rows as $row) {
            $row = (array) $row;
            $exception = trim((string) strtok((string) ($row['exception'] ?? ''), "\n"));

            $result['jobs'][] = [
                'id' => (string) ($row['uuid'] ?? $row['id'] ?? ''),
                'connection' => (string) ($row['connection'] ?? ''),
                'queue' => (string) ($row['queue'] ?? ''),
                'failed_at' => (string) ($row['failed_at'] ?? ''),
                'exception' => $exception !== '' ? mb_substr($this->diagnostics->redact($exception), 0, 400) : null,
            ] + $this->payload((string) ($row['payload'] ?? ''));
        }

        return $result;
    }

    /**
     * Batches that are neither finished nor cancelled; null when the
     * application keeps none.
     */
    protected function openBatches(): ?int
    {
        try {
            return (int) DB::connection(config('queue.batching.database'))
                ->table((string) config('queue.batching.table', 'job_batches'))
                ->whereNull('finished_at')
                ->whereNull('cancelled_at')
                ->count();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Where the file manager shows a file of the project, when it may.
     */
    protected function link(string $file): ?string
    {
        if ($file === '') {
            return null;
        }

        if (array_key_exists($file, $this->links)) {
            return $this->links[$file];
        }

        try {
            $this->browsable ??= $this->config->fileManagerBrowsable();

            return $this->links[$file] = $this->browsable && $this->project->file(FileManagerService::PROJECT, $file) !== null
                ? $this->project->url(FileManagerService::PROJECT, $file)
                : null;
        } catch (Throwable) {
            return $this->links[$file] = null;
        }
    }

    /**
     * Why something could not be read, in one line and without a secret.
     */
    protected function reason(Throwable $e): string
    {
        $message = trim((string) strtok($e->getMessage(), "\n"));

        return mb_substr($this->diagnostics->redact($message !== '' ? $message : $e::class), 0, 400);
    }

    /**
     * A path as the page names it: from the root of the project when it is
     * inside it.
     */
    protected function relative(string $path): string
    {
        if ($path === '') {
            return '';
        }

        $normal = str_replace('\\', '/', $path);
        $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';

        return str_starts_with($normal, $base) ? substr($normal, strlen($base)) : $path;
    }

    protected function text(string $key): string
    {
        $value = config($key);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function settings(string $key): array
    {
        $value = config($key);

        return is_array($value) ? $value : [];
    }
}
