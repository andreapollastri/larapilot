<?php

declare(strict_types=1);

namespace Larapilot\Services;

use DateTimeInterface;
use Generator;
use Illuminate\Support\Carbon;
use Larapilot\Services\Errors\ErrorDataHelper;
use Throwable;

/**
 * Reads the log files of the application for the dashboard and for the
 * skills. A file is read from its end backwards, so the newest entries of
 * a log of any size come without reading the rest; an entry is what
 * Laravel wrote between one `[date] env.LEVEL:` and the next, stack trace
 * included. Everything that leaves this class has its secrets redacted,
 * and nothing is ever written.
 */
class LogViewerService
{
    /** Monolog's levels, the most severe first. */
    public const LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    /** How far back the page and the command offer to look. */
    public const SINCE = ['1h' => 'Last hour', '24h' => 'Last 24 hours', '7d' => 'Last 7 days', '30d' => 'Last 30 days'];

    /** `[2026-10-02 10:00:00] local.ERROR: ` — where an entry starts. */
    protected const HEADER = '/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2})(?:[.,]\d{1,9})?(Z|[+-]\d{2}:?\d{2})?\] ([\w.-]+)\.(EMERGENCY|ALERT|CRITICAL|ERROR|WARNING|NOTICE|INFO|DEBUG):[ \t]?/m';

    /** A file that is not in Laravel's format is read a line at a time. */
    protected const LINE = '/^(?=[^\r\n])/m';

    /** `[object] (Class(code: 0): message at /path/File.php:12)` — how Monolog writes an exception. */
    protected const EXCEPTION = '/\[object\] \(([^\s(]+)\(code: ([^)]*)\): (.*?) at ([^\n]+?):(\d+)\)(?=\r?\n|"|$)/s';

    protected const CHUNK = 262144;

    /** How much of one entry is kept while a file is read. */
    protected const ENTRY_BYTES = 131072;

    /** How much of one entry is shown. */
    protected const BODY_BYTES = 16384;

    /** How much of one line the redacted download checks, and gives. */
    protected const LINE_BYTES = 1048576;

    protected const MESSAGE_CHARS = 2000;

    protected const FRAMES = 60;

    protected const FILE_LIMIT = 500;

    protected const DEPTH = 3;

    /** How many different things the repeats are counted of. */
    protected const GROUPS = 10000;

    /** How far out of order two processes may write, in seconds. */
    protected const DISORDER = 600;

    /** @var array<string, string> */
    protected array $places = [];

    /** @var array<string, string|null> */
    protected array $links = [];

    /**
     * One for each file, of the size and the time it was read at.
     *
     * @var array<string, array{stamp: string, overview: array<string, mixed>}>
     */
    protected array $overviews = [];

    /** @var array<string, array{stamp: string, pattern: string}> */
    protected array $patterns = [];

    protected ?bool $browsable = null;

    public function __construct(
        protected DiagnosticsService $diagnostics,
        protected ErrorDataHelper $paths,
        protected FileManagerService $project,
        protected ConfigService $config,
    ) {}

    /**
     * The folder the logs are read from: `log_viewer.path` when it is set,
     * otherwise `storage/logs`. A path that is not absolute is one of the
     * project, wherever the process was started from.
     */
    public function directory(): string
    {
        $configured = config('larapilot.log_viewer.path');

        if (! is_string($configured) || trim($configured) === '') {
            return rtrim(storage_path('logs'), '/\\');
        }

        $configured = trim($configured);

        if (preg_match('/^(?:[\/\\\\]|[A-Za-z]:[\/\\\\])/', $configured) !== 1) {
            $configured = base_path($configured);
        }

        return rtrim($configured, '/\\') ?: DIRECTORY_SEPARATOR;
    }

    /**
     * The folder as the page names it: from the root of the project when
     * it is inside it.
     */
    public function directoryLabel(): string
    {
        $directory = str_replace('\\', '/', $this->directory());
        $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';

        return str_starts_with($directory.'/', $base) ? rtrim(substr($directory, strlen($base)), '/') : $directory;
    }

    /**
     * Every `.log` file of the folder, the one written last first. The
     * file the application writes to now is marked `current`; none is when
     * no channel writes to a file of this folder.
     *
     * @return list<array{key: string, name: string, absolute: string, size: int, size_label: string, modified: int, modified_label: string, current: bool}>
     */
    public function files(): array
    {
        // A process that stays up — Octane, the MCP server — would otherwise
        // be told the size a file had the last time it asked.
        clearstatcache();

        $directory = realpath($this->directory());

        if ($directory === false || ! is_dir($directory)) {
            return [];
        }

        $found = [];
        $this->collect($directory, '', 0, $found);

        usort($found, static fn (array $a, array $b): int => [$b['modified'], $a['key']] <=> [$a['modified'], $b['key']]);

        $writing = $this->diagnostics->currentLogPath();
        $writing = $writing === null ? false : realpath($writing);
        $current = null;

        foreach ($found as $index => $file) {
            if ($writing !== false && $file['absolute'] === $writing) {
                $current = $index;
                break;
            }
        }

        return array_map(fn (array $file, int $index): array => $file + [
            'size_label' => $this->formatBytes($file['size']),
            'modified_label' => $this->ago($file['modified']),
            'current' => $index === $current,
        ], $found, array_keys($found));
    }

    /**
     * One file, by the name the list gives it. Nothing else is opened: a
     * name that is not in the list is no file at all.
     *
     * @return array{key: string, name: string, absolute: string, size: int, size_label: string, modified: int, modified_label: string, current: bool}|null
     */
    public function find(string $key): ?array
    {
        foreach ($this->files() as $file) {
            if ($file['key'] === $key) {
                return $file;
            }
        }

        return null;
    }

    /**
     * The file to open when none is asked for: the one the application
     * writes to now, or the newest when it writes to none of the folder.
     *
     * @return array{key: string, name: string, absolute: string, size: int, size_label: string, modified: int, modified_label: string, current: bool}|null
     */
    public function current(): ?array
    {
        $files = $this->files();

        foreach ($files as $file) {
            if ($file['current']) {
                return $file;
            }
        }

        return $files[0] ?? null;
    }

    /**
     * Dashboard URL of a file. Each segment is encoded on its own, so a
     * name with `#`, `?`, or `%` in it stays one path.
     *
     * @param  array<string, scalar|null>  $query
     */
    public function url(string $key, array $query = []): string
    {
        $encoded = implode('/', array_map('rawurlencode', explode('/', $key)));
        $url = str_replace('__FILE__', $encoded, route('larapilot.dashboard.logs.file', ['file' => '__FILE__']));
        $query = array_filter($query, static fn ($value): bool => $value !== null && $value !== '');

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    /**
     * What a request asks for, reduced to what the reader understands.
     *
     * @param  array{level?: string|null, search?: string|null, since?: string|null}  $options
     * @return array{level: string|null, search: string, terms: list<string>, marks: list<string>, since: string|null, after: string|null, after_time: int|null, floor: string|null, floor_time: int|null}
     */
    public function filters(array $options): array
    {
        $level = strtolower(trim((string) ($options['level'] ?? '')));
        $search = mb_substr(trim((string) ($options['search'] ?? '')), 0, 200);
        $since = trim((string) ($options['since'] ?? ''));
        $moment = $since === '' ? null : $this->moment($since);
        $terms = $this->terms($search);

        // A log is written in order, give or take what two processes do to
        // each other: an entry older than this ends the reading.
        $floor = $moment === null ? null : Carbon::instance($moment)->subSeconds(self::DISORDER);

        return [
            'level' => in_array($level, self::LEVELS, true) ? $level : null,
            'search' => $search,
            'terms' => $terms,
            'marks' => $this->marks($terms),
            'since' => $moment === null ? null : $since,
            'after' => $moment?->format('Y-m-d H:i:s'),
            'after_time' => $moment?->getTimestamp(),
            'floor' => $floor?->format('Y-m-d H:i:s'),
            'floor_time' => $floor?->getTimestamp(),
        ];
    }

    /**
     * Whether `--since` or `?since=` names a moment: `30m`, `1h`, `7d`, or
     * a date.
     */
    public function understandsSince(string $since): bool
    {
        return $this->moment(trim($since)) !== null;
    }

    /**
     * What the end of the file holds: how many entries of each level, and
     * the period they cover. A file larger than `log_viewer.scan_mb` is
     * counted from its end back to that size.
     *
     * @param  array{absolute: string, size: int, modified: int}  $file
     * @return array{format: string, levels: array<string, int>, entries: int, from: string|null, to: string|null, complete: bool, scanned: int, scanned_label: string}
     */
    public function overview(array $file): array
    {
        $stamp = $file['size'].'|'.$file['modified'];

        if (($this->overviews[$file['absolute']]['stamp'] ?? null) === $stamp) {
            return $this->overviews[$file['absolute']]['overview'];
        }

        $levels = array_fill_keys(self::LEVELS, 0);
        $entries = 0;
        $from = null;
        $to = null;
        $oldest = null;

        $scan = $this->scan($file['absolute'], null, $this->budget());

        foreach ($scan as $raw) {
            $entries++;
            $oldest = $raw['offset'];

            if ($raw['level'] !== '') {
                $levels[$raw['level']]++;
            }

            if ($raw['time'] !== '') {
                $to ??= $raw['time'];
                $from = $raw['time'];
            }
        }

        $complete = (bool) $scan->getReturn();
        $scanned = $complete ? $file['size'] : max(0, $file['size'] - (int) ($oldest ?? $file['size']));

        $overview = [
            'format' => $this->pattern($file['absolute']) === self::HEADER ? 'laravel' : 'plain',
            'levels' => array_filter($levels),
            'entries' => $entries,
            'from' => $from,
            'to' => $to,
            'complete' => $complete,
            'scanned' => $scanned,
            'scanned_label' => $this->formatBytes($scanned),
        ];

        $this->overviews[$file['absolute']] = ['stamp' => $stamp, 'overview' => $overview];

        return $overview;
    }

    /**
     * One page of entries, newest first. `before` is where the page above
     * stopped: the byte an entry starts at, so a page is the same however
     * much is written after it.
     *
     * @param  array{absolute: string, size: int, modified: int}  $file
     * @param  array{level?: string|null, search?: string|null, since?: string|null, before?: int|null, limit?: int|null}  $options
     * @return array{entries: list<array<string, mixed>>, next: int|null, resume: int|null, filters: array<string, mixed>}
     */
    public function read(array $file, array $options = []): array
    {
        $filters = $this->filters($options);
        $limit = $this->limit($options['limit'] ?? null);
        $before = isset($options['before']) && $options['before'] > 0 ? (int) $options['before'] : null;
        $laravel = $this->pattern($file['absolute']) === self::HEADER;

        $entries = [];
        $next = null;
        $oldest = null;
        $full = false;
        $ended = false;

        $scan = $this->scan($file['absolute'], $before, $this->budget());

        foreach ($scan as $raw) {
            $oldest = $raw['offset'];

            // Past the period there is nothing older to find.
            if ($laravel && $this->past($raw, $filters)) {
                $ended = true;
                break;
            }

            if (! $this->matches($raw, $filters, $laravel)) {
                continue;
            }

            $entry = $this->entry($raw);

            // A word is looked for in what is shown: a match in a value
            // that is redacted would tell the value.
            if (! $this->holds($entry['body'], $filters['terms'])) {
                continue;
            }

            // One entry past the page says there is an older page.
            if (count($entries) === $limit) {
                $full = true;
                break;
            }

            $entries[] = $entry;
        }

        if ($full) {
            $next = $entries[$limit - 1]['offset'];
        }

        // The file is larger than one request reads: say where it stopped.
        $resume = null;

        if (! $full && ! $ended && ! $scan->getReturn()) {
            $resume = $oldest ?? max(0, ($before ?? $file['size']) - $this->budget());
            $resume = $resume > 0 ? $resume : null;
        }

        return [
            'entries' => $entries,
            'next' => $next,
            'resume' => $resume,
            'filters' => $filters,
        ];
    }

    /**
     * The same entries with the repeats counted: one row for each thing
     * that was logged, the one logged the most first.
     *
     * @param  array{absolute: string, size: int, modified: int}  $file
     * @param  array{level?: string|null, search?: string|null, since?: string|null, limit?: int|null}  $options
     * @return array{groups: list<array<string, mixed>>, total: int, entries: int, complete: bool, capped: bool, filters: array<string, mixed>}
     */
    public function groups(array $file, array $options = []): array
    {
        $filters = $this->filters($options);
        $limit = $this->limit($options['limit'] ?? null);
        $laravel = $this->pattern($file['absolute']) === self::HEADER;

        /** @var array<string, array{count: int, first: string, last: string, raw: array<string, mixed>, order: int}> $groups */
        $groups = [];
        $entries = 0;
        $order = 0;
        $ended = false;
        $capped = false;

        $scan = $this->scan($file['absolute'], null, $this->budget());

        foreach ($scan as $raw) {
            if ($laravel && $this->past($raw, $filters)) {
                $ended = true;
                break;
            }

            if (! $this->matches($raw, $filters, $laravel)) {
                continue;
            }

            if ($filters['terms'] !== [] && ! $this->holds($this->redacted($raw['text']), $filters['terms'])) {
                continue;
            }

            $entries++;
            $signature = md5($this->signature($raw), true);

            if (isset($groups[$signature])) {
                $groups[$signature]['count']++;
                $groups[$signature]['first'] = $raw['time'];

                continue;
            }

            // A log where nothing repeats has a row for every entry: past
            // this many, what is new is counted and no longer told apart.
            if ($order >= self::GROUPS) {
                $capped = true;

                continue;
            }

            // The file is read from its end: the first one met is the latest.
            // Its text is read again for the rows that are shown, not kept
            // for every one.
            $groups[$signature] = ['count' => 1, 'first' => $raw['time'], 'last' => $raw['time'], 'raw' => ['text' => ''] + $raw, 'order' => $order++];
        }

        $complete = $ended || (bool) $scan->getReturn();

        uasort($groups, static fn (array $a, array $b): int => [$b['count'], $a['order']] <=> [$a['count'], $b['order']]);

        $rows = [];
        $handle = @fopen($file['absolute'], 'rb');

        foreach ($handle === false ? [] : array_slice($groups, 0, $limit) as $group) {
            $raw = $group['raw'];
            $bytes = min($raw['length'], self::ENTRY_BYTES);
            fseek($handle, $raw['offset']);
            $raw['text'] = $bytes > 0 ? (string) fread($handle, $bytes) : '';

            $rows[] = $this->entry($raw) + [
                'count' => $group['count'],
                'first' => str_replace('T', ' ', $group['first']),
                'last' => str_replace('T', ' ', $group['last']),
            ];
        }

        if ($handle !== false) {
            fclose($handle);
        }

        return [
            'groups' => $rows,
            'total' => count($groups),
            'entries' => $entries,
            'complete' => $complete,
            'capped' => $capped,
            'filters' => $filters,
        ];
    }

    /**
     * The file as a download, a piece at a time. Secrets are redacted
     * unless it is asked for as it was written.
     *
     * @param  array{absolute: string}  $file
     * @param  callable(string): void  $write
     */
    public function download(array $file, callable $write, bool $asWritten = false): void
    {
        $handle = @fopen($file['absolute'], 'rb');

        if ($handle === false) {
            return;
        }

        try {
            // A line of any length is read in pieces of at most 1 MB.
            while (($line = fgets($handle, self::LINE_BYTES + 1)) !== false) {
                if ($asWritten) {
                    $write($line);

                    continue;
                }

                $ending = substr($line, strlen(rtrim($line, "\r\n")));
                $write($this->redactLine($line));

                if ($ending !== '' || feof($handle)) {
                    continue;
                }

                // A line longer than a piece: a secret can sit across two
                // pieces, where neither shows it whole. The first piece is
                // given, the rest counted and left out.
                $left = 0;

                while (($rest = fgets($handle, self::LINE_BYTES + 1)) !== false) {
                    $ending = substr($rest, strlen(rtrim($rest, "\r\n")));
                    $left += strlen($rest) - strlen($ending);

                    if ($ending !== '') {
                        break;
                    }
                }

                if ($left > 0) {
                    $write(' [… '.number_format($left).' more bytes of this line left out: too long to check for secrets]');
                }

                $write($ending);
            }
        } finally {
            fclose($handle);
        }
    }

    public function formatBytes(int $bytes): string
    {
        return $this->project->formatBytes($bytes);
    }

    /**
     * How many bytes of a file one request reads, from where it starts
     * backwards.
     */
    public function budget(): int
    {
        return max(1, min(1024, (int) config('larapilot.log_viewer.scan_mb', 32))) * 1048576;
    }

    public function perPage(): int
    {
        return max(1, min(200, (int) config('larapilot.log_viewer.per_page', 50)));
    }

    /**
     * @param  list<array{key: string, name: string, absolute: string, size: int, modified: int}>  $found
     */
    protected function collect(string $directory, string $relative, int $depth, array &$found): void
    {
        $names = @scandir($directory);

        foreach ($names === false ? [] : $names as $name) {
            if ($name === '.' || $name === '..' || count($found) >= self::FILE_LIMIT) {
                continue;
            }

            $absolute = $directory.DIRECTORY_SEPARATOR.$name;

            // A link is never followed: it can point outside the folder.
            if (is_link($absolute)) {
                continue;
            }

            $key = $relative === '' ? $name : $relative.'/'.$name;

            if (is_dir($absolute)) {
                if ($depth < self::DEPTH) {
                    $this->collect($absolute, $key, $depth + 1, $found);
                }

                continue;
            }

            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'log' || ! is_readable($absolute)) {
                continue;
            }

            $found[] = [
                'key' => $key,
                'name' => $name,
                'absolute' => $absolute,
                'size' => (int) @filesize($absolute),
                'modified' => (int) @filemtime($absolute),
            ];
        }
    }

    /**
     * The entries of a file from `$before` — or from its end — backwards,
     * the newest first, for at most `$budget` bytes. Returns whether the
     * start of the file was reached.
     *
     * @return Generator<int, array{offset: int, length: int, time: string, zone: string, env: string, level: string, text: string}, mixed, bool>
     */
    protected function scan(string $path, ?int $before, int $budget): Generator
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return true;
        }

        $pattern = $this->pattern($path);

        try {
            $size = (int) (fstat($handle)['size'] ?? 0);
            $position = $before === null ? $size : max(0, min($before, $size));
            $floor = max(0, $position - max(self::CHUNK, $budget));
            $carry = '';
            $dropped = 0;

            while ($position > $floor) {
                $read = min(self::CHUNK, $position - $floor);
                $position -= $read;
                fseek($handle, $position);
                $buffer = (string) fread($handle, $read).$carry;
                $carry = '';

                preg_match_all($pattern, $buffer, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

                // The buffer may start in the middle of a line: what looks
                // like a start there waits for the piece before it.
                if ($matches !== [] && $matches[0][0][1] === 0 && $position > 0) {
                    array_shift($matches);
                }

                if ($matches === []) {
                    // The end of one very long entry. Its start is all that
                    // is shown, so as much as an entry keeps is carried to
                    // the piece that holds the start; the rest is counted
                    // and let go.
                    if (strlen($buffer) > self::ENTRY_BYTES) {
                        $dropped += strlen($buffer) - self::ENTRY_BYTES;
                        $buffer = substr($buffer, 0, self::ENTRY_BYTES);
                    }

                    $carry = $buffer;

                    continue;
                }

                $last = count($matches) - 1;

                for ($index = $last; $index >= 0; $index--) {
                    $start = $matches[$index][0][1];
                    $stop = $index === $last ? strlen($buffer) : $matches[$index + 1][0][1];

                    yield [
                        'offset' => $position + $start,
                        'length' => $stop - $start + ($index === $last ? $dropped : 0),
                        'time' => (string) ($matches[$index][1][0] ?? ''),
                        'zone' => (string) ($matches[$index][2][0] ?? ''),
                        'env' => (string) ($matches[$index][3][0] ?? ''),
                        'level' => strtolower((string) ($matches[$index][4][0] ?? '')),
                        'text' => substr($buffer, $start, min($stop - $start, self::ENTRY_BYTES)),
                    ];
                }

                $dropped = 0;
                $carry = substr($buffer, 0, $matches[0][0][1]);
            }

            if ($floor > 0) {
                return false;
            }

            // What comes before the first entry: lines written by something
            // other than the logger.
            if (trim($carry) !== '') {
                yield [
                    'offset' => 0,
                    'length' => strlen($carry) + $dropped,
                    'time' => '',
                    'zone' => '',
                    'env' => '',
                    'level' => '',
                    'text' => substr($carry, 0, self::ENTRY_BYTES),
                ];
            }

            return true;
        } finally {
            fclose($handle);
        }
    }

    /**
     * How a file is cut into entries: by Laravel's header when its start
     * or its end holds one, by line otherwise.
     */
    protected function pattern(string $path): string
    {
        clearstatcache(true, $path);
        $stamp = (string) (int) @filesize($path);

        if (($this->patterns[$path]['stamp'] ?? null) === $stamp) {
            return $this->patterns[$path]['pattern'];
        }

        $sample = '';
        $handle = @fopen($path, 'rb');

        if ($handle !== false) {
            $size = (int) (fstat($handle)['size'] ?? 0);

            // A file that was just emptied has nothing to read: `fread` refuses a length of zero.
            if ($size > 0) {
                $sample = (string) fread($handle, min($size, 65536));
            }

            if ($size > 65536) {
                fseek($handle, max(65536, $size - self::CHUNK));
                $sample .= "\n".fread($handle, self::CHUNK);
            }

            fclose($handle);
        }

        $pattern = preg_match(self::HEADER, $sample) === 1 ? self::HEADER : self::LINE;
        $this->patterns[$path] = ['stamp' => $stamp, 'pattern' => $pattern];

        return $pattern;
    }

    /**
     * @param  array{time: string, zone: string, level: string, text: string}  $raw
     * @param  array{level: string|null, terms: list<string>, after: string|null, after_time: int|null}  $filters
     */
    protected function matches(array $raw, array $filters, bool $laravel): bool
    {
        // A file read by line has no levels and no dates to filter on.
        if ($laravel) {
            if ($filters['level'] !== null && ($raw['level'] === '' || $this->rank($raw['level']) > $this->rank($filters['level']))) {
                return false;
            }

            if ($filters['after'] !== null && ! $this->after($raw, $filters)) {
                return false;
            }
        }

        // The words first as they were written, which is quick; what is
        // found is checked again once the entry is redacted.
        return $this->holds($raw['text'], $filters['terms']);
    }

    /**
     * Whether an entry is older than the period asked for by more than two
     * processes writing at once can explain: every entry before it is too.
     *
     * @param  array{time: string, zone: string}  $raw
     * @param  array{floor: string|null, floor_time: int|null}  $filters
     */
    protected function past(array $raw, array $filters): bool
    {
        if ($filters['floor'] === null || $raw['time'] === '') {
            return false;
        }

        if ($raw['zone'] === '') {
            return str_replace('T', ' ', $raw['time']) < $filters['floor'];
        }

        $time = strtotime($raw['time'].$raw['zone']);

        return $time !== false && $time < $filters['floor_time'];
    }

    /**
     * @param  array{time: string, zone: string}  $raw
     * @param  array{after: string|null, after_time: int|null}  $filters
     */
    protected function after(array $raw, array $filters): bool
    {
        if ($raw['time'] === '') {
            return false;
        }

        // Laravel writes the time of the application, which is the one
        // `now()` gives: the two compare as text.
        if ($raw['zone'] === '') {
            return str_replace('T', ' ', $raw['time']) >= $filters['after'];
        }

        $time = strtotime($raw['time'].$raw['zone']);

        return $time !== false && $time >= $filters['after_time'];
    }

    /**
     * @param  list<string>  $terms
     */
    protected function holds(string $text, array $terms): bool
    {
        foreach ($terms as $term) {
            $found = false;

            foreach ($this->spellings($term) as $spelling) {
                if (stripos($text, $spelling) !== false) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                return false;
            }
        }

        return true;
    }

    /**
     * A word as it may be written. Monolog doubles the backslashes of a
     * class name and the page shows them single: the name copied from
     * either place finds the entry.
     *
     * @return list<string>
     */
    protected function spellings(string $term): array
    {
        if (! str_contains($term, '\\')) {
            return [$term];
        }

        $single = (string) preg_replace('/\\\\{2,}/', '\\', $term);

        return array_values(array_unique([$term, $single, str_replace('\\', '\\\\', $single)]));
    }

    /**
     * What the page marks: every spelling of every word, the longest first
     * so the doubled backslashes are marked as one match.
     *
     * @param  list<string>  $terms
     * @return list<string>
     */
    protected function marks(array $terms): array
    {
        $marks = [];

        foreach ($terms as $term) {
            array_push($marks, ...$this->spellings($term));
        }

        $marks = array_values(array_unique($marks));
        usort($marks, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $marks;
    }

    /**
     * The words of a search: every one must be in the entry. Quotes keep
     * several words together.
     *
     * @return list<string>
     */
    protected function terms(string $search): array
    {
        if ($search === '') {
            return [];
        }

        preg_match_all('/"([^"]+)"|(\S+)/u', $search, $matches, PREG_SET_ORDER);

        $terms = [];

        foreach ($matches as $match) {
            $term = trim(($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? ''), '"');

            if ($term !== '') {
                $terms[] = $term;
            }
        }

        return array_values(array_unique($terms));
    }

    protected function moment(string $since): ?DateTimeInterface
    {
        if ($since === '') {
            return null;
        }

        if (preg_match('/^(\d{1,4})\s*([mhd])$/i', $since, $match) === 1) {
            $amount = (int) $match[1];

            return match (strtolower($match[2])) {
                'm' => now()->subMinutes($amount),
                'h' => now()->subHours($amount),
                default => now()->subDays($amount),
            };
        }

        // A date, not a sentence: `yesterday at noon` is not what a URL carries.
        if (preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2})?)?$/', $since) !== 1) {
            return null;
        }

        try {
            return Carbon::parse($since);
        } catch (Throwable) {
            return null;
        }
    }

    protected function limit(mixed $limit): int
    {
        return is_numeric($limit) ? max(1, min(200, (int) $limit)) : $this->perPage();
    }

    protected function rank(string $level): int
    {
        $rank = array_search($level, self::LEVELS, true);

        return $rank === false ? count(self::LEVELS) : (int) $rank;
    }

    /**
     * What makes two entries the same thing logged twice: the level, the
     * message without what changes from one time to the next, and for an
     * exception its class and where it was thrown.
     *
     * @param  array{level: string, text: string}  $raw
     */
    protected function signature(array $raw): string
    {
        $first = substr($raw['text'], 0, 4000);
        $break = strcspn($first, "\r\n");
        $first = (string) preg_replace(self::HEADER, '', substr($first, 0, $break), 1);
        $thrown = '';
        $context = strpos($first, '"exception":"[object] (');

        if ($context !== false) {
            if (preg_match('/\[object\] \(([^\s(]+)\(code: [^)]*\): .* at ([^\n]+?:\d+)\)/', substr($first, $context), $match) === 1) {
                $thrown = '|'.$match[1].'|'.$match[2];
            }

            $first = substr($first, 0, $this->contextStart($first, $context) ?? $context);
        }

        $message = substr($first, 0, 300);
        $message = (string) preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', '{id}', $message);
        $message = (string) preg_replace('/\b(?:0x)?[0-9a-f]{12,}\b/i', '{hex}', $message);
        $message = (string) preg_replace('/\d+/', '{n}', $message);
        $message = (string) preg_replace('/\'[^\']{0,120}\'|"[^"]{0,120}"/', '{value}', $message);

        return $raw['level'].'|'.trim($message).$thrown;
    }

    /**
     * One entry as it is shown: the message, the exception and where it
     * was thrown, the frames of its stack with the ones of the application
     * told apart, the context, and the text as it was written — all of it
     * with the secrets redacted.
     *
     * @param  array{offset: int, length: int, time: string, zone: string, env: string, level: string, text: string}  $raw
     * @return array<string, mixed>
     */
    protected function entry(array $raw): array
    {
        $truncated = $raw['length'] > strlen($raw['text']);

        // Redacted before it is cut: a secret the cut would end short is
        // still read as one value.
        $body = $this->redacted(rtrim($raw['text']));

        if (strlen($body) > self::BODY_BYTES) {
            $body = rtrim(mb_strcut($body, 0, self::BODY_BYTES, 'UTF-8'));
            $truncated = true;
        }
        $rest = $raw['level'] === '' ? $body : (string) preg_replace(self::HEADER, '', $body, 1);
        $lines = explode("\n", $rest);
        $first = $lines[0];

        $exception = null;
        $previous = [];
        $context = null;

        if (preg_match_all(self::EXCEPTION, $rest, $thrown, PREG_SET_ORDER) > 0) {
            foreach ($thrown as $index => $match) {
                $class = str_replace('\\\\', '\\', $match[1]);
                $file = $this->place($match[4]);
                $described = [
                    'class' => $class,
                    'short' => ($slash = strrpos($class, '\\')) === false ? $class : substr($class, $slash + 1),
                    'code' => $match[2],
                    'message' => $this->cut($this->unescape($match[3]), self::MESSAGE_CHARS),
                    'file' => $file,
                    'line' => (int) $match[5],
                    'where' => $file.':'.$match[5],
                    'app' => $this->inApplication($file),
                    'url' => $this->link($file),
                ];

                if ($index === 0) {
                    $exception = $described;
                } else {
                    $previous[] = $described;
                }
            }

            // What Laravel puts in the context beside the exception: the
            // user id, what `context()` of the handler adds.
            $at = strpos($first, '"exception":"[object] (');
            $start = $at === false ? null : $this->contextStart($first, $at);

            if ($at !== false && $start !== null) {
                $beside = rtrim(substr($first, $start + 1, $at - $start - 1), ', ');
                $context = $beside === '{' ? null : $this->pretty($beside.'}');
                $first = rtrim(substr($first, 0, $start));
            }
        } elseif (count($lines) === 1) {
            [$first, $context] = $this->splitContext($first);
        }

        $frames = $this->frames($lines);
        $message = trim($first);

        if ($message === '' && $exception !== null) {
            $message = $exception['message'];
        }

        // Where to look first: the place of the exception when it is code
        // of the application, otherwise the first frame that is.
        $where = null;

        if ($exception !== null) {
            $where = $exception['app'] ? $exception['where'] : null;

            foreach ($where === null ? $frames['list'] : [] as $frame) {
                if ($frame['app'] && $frame['file'] !== '') {
                    $where = $frame['file'].':'.$frame['line'];
                    break;
                }
            }

            $where ??= $exception['where'];
        }

        $time = str_replace('T', ' ', $raw['time']);

        return [
            'offset' => $raw['offset'],
            'time' => $time,
            'zone' => $raw['zone'],
            'ago' => $this->agoOf($raw['time'], $raw['zone']),
            'env' => $raw['env'],
            'level' => $raw['level'],
            'message' => $this->cut($message, self::MESSAGE_CHARS),
            'exception' => $exception,
            'previous' => $previous,
            'where' => $where,
            'frames' => $frames['list'],
            'frames_total' => $frames['total'],
            'frames_app' => count(array_filter($frames['list'], static fn (array $frame): bool => $frame['app'])),
            'context' => $context,
            'body' => $body,
            'bytes' => $raw['length'],
            'bytes_label' => $this->formatBytes($raw['length']),
            'truncated' => $truncated,
        ];
    }

    /**
     * The frames of the first stack trace of an entry.
     *
     * @param  list<string>  $lines
     * @return array{list: list<array{index: int, file: string, line: int|null, call: string, app: bool, url: string|null}>, total: int}
     */
    protected function frames(array $lines): array
    {
        $list = [];
        $total = 0;
        $inside = false;

        foreach ($lines as $line) {
            $line = rtrim($line);

            if (! $inside) {
                $inside = $line === '[stacktrace]';

                continue;
            }

            if (preg_match('/^#(\d+) (.*)$/', $line, $match) !== 1) {
                break;
            }

            $total++;

            if (count($list) >= self::FRAMES) {
                continue;
            }

            $file = '';
            $number = null;
            $call = $match[2];

            if (preg_match('/^(.+?)\((\d+)\): (.*)$/', $match[2], $parts) === 1) {
                $file = $this->place($parts[1]);
                $number = (int) $parts[2];
                $call = $parts[3];
            }

            $list[] = [
                'index' => (int) $match[1],
                'file' => $file,
                'line' => $number,
                'call' => $this->cut(str_replace('\\\\', '\\', $call), 300),
                'app' => $file !== '' && $this->inApplication($file),
                'url' => $file === '' ? null : $this->link($file),
            ];
        }

        return ['list' => $list, 'total' => $total];
    }

    /**
     * Where the context that holds the exception opens: the first ` {`
     * from which what is written up to the exception reads as JSON. The
     * message, or a value of the context, can hold a brace of its own.
     */
    protected function contextStart(string $line, int $exception): ?int
    {
        $offset = 0;

        for ($try = 0; $try < 12; $try++) {
            $at = strpos($line, ' {', $offset);

            if ($at === false || $at >= $exception) {
                break;
            }

            $beside = rtrim(substr($line, $at + 1, $exception - $at - 1), ', ');

            if ($beside === '{' || is_array(json_decode($beside.'}', true))) {
                return $at;
            }

            $offset = $at + 2;
        }

        $last = strrpos(substr($line, 0, $exception), ' {');

        return $last === false ? null : $last;
    }

    /**
     * `message {"key":"value"}` — the message and its context apart, when
     * what follows the message reads as JSON.
     *
     * @return array{0: string, 1: string|null}
     */
    protected function splitContext(string $line): array
    {
        $offset = 0;

        for ($try = 0; $try < 6; $try++) {
            $at = strpos($line, ' {', $offset);

            if ($at === false) {
                break;
            }

            $pretty = $this->pretty(trim(substr($line, $at + 1)));

            if ($pretty !== null) {
                return [rtrim(substr($line, 0, $at)), $pretty];
            }

            $offset = $at + 2;
        }

        return [$line, null];
    }

    /**
     * JSON indented, or null when the text is not JSON. Context and extra
     * come as two objects one after the other.
     */
    protected function pretty(string $json): ?string
    {
        $parts = [];

        foreach (preg_split('/(?<=\})\s+(?=[{\[])/', $json) ?: [] as $part) {
            $decoded = json_decode($part, true);

            if (! is_array($decoded)) {
                return null;
            }

            if ($decoded !== []) {
                $parts[] = (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        return $parts === [] ? null : implode("\n", $parts);
    }

    /**
     * A path of the server as a file of the project.
     */
    protected function place(string $file): string
    {
        return $this->places[$file] ??= $this->paths->place($file);
    }

    /**
     * Code somebody of the project wrote: a file in one of its folders that
     * is not a package, and not the front controller every request enters by.
     */
    protected function inApplication(string $file): bool
    {
        return str_contains($file, '/') && ! str_starts_with($file, 'vendor/') && $file !== 'public/index.php';
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
     * Text with its secrets redacted, a line at a time, and readable as
     * UTF-8 whatever was written.
     */
    protected function redacted(string $text): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_scrub($text, 'UTF-8');
        }

        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];

        return implode("\n", array_map(fn (string $line): string => $this->diagnostics->redact($line), $lines));
    }

    protected function redactLine(string $line): string
    {
        $content = rtrim($line, "\r\n");

        return $this->diagnostics->redact($content).substr($line, strlen($content));
    }

    /**
     * A message as it was thrown: Monolog writes it inside a JSON string.
     */
    protected function unescape(string $text): string
    {
        return str_replace(['\\\\', '\\"', '\\/'], ['\\', '"', '/'], $text);
    }

    protected function cut(string $text, int $chars): string
    {
        return mb_strlen($text) > $chars ? mb_substr($text, 0, $chars).'…' : $text;
    }

    protected function agoOf(string $time, string $zone): ?string
    {
        if ($time === '') {
            return null;
        }

        try {
            $moment = Carbon::parse($time.$zone);

            // A log brought from a server in another timezone reads as
            // written later than now: how long ago is then anyone's guess.
            return $moment->isFuture() ? null : $moment->diffForHumans();
        } catch (Throwable) {
            return null;
        }
    }

    protected function ago(int $timestamp): string
    {
        return $timestamp > 0 ? Carbon::createFromTimestamp($timestamp)->diffForHumans() : '';
    }
}
