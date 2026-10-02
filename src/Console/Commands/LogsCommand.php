<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\LogViewerService;
use Larapilot\Support\LarapilotCommand;

class LogsCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:logs
                            {--file= : The log to read, by the name --files gives it (default: the one the application writes to now)}
                            {--files : List the log files instead of reading one}
                            {--level= : The lowest level to return: debug, info, notice, warning, error, critical, alert, emergency}
                            {--search= : Words every entry must hold; quotes keep several together}
                            {--since= : How far back: 30m, 1h, 7d, or a date (2026-10-02)}
                            {--limit= : Return at most this many (default 20, at most 100)}
                            {--group : Count the repeats: one row for each thing logged, the one logged the most first}';

    protected $description = 'Read the log files of the application as entries — newest first, by level, searched, the repeats counted — with secrets redacted';

    public function handle(LogViewerService $logs): int
    {
        if (! (bool) config('larapilot.diagnostics.enabled', true)) {
            return $this->failure(
                'E_PRECONDITION',
                'Diagnostics are disabled, and the logs with them.',
                $this->exitForCode('E_PRECONDITION'),
                'Set LARAPILOT_DIAGNOSTICS_ENABLED=true to enable them.'
            );
        }

        $files = array_map(static fn (array $file): array => [
            'name' => $file['key'],
            'size' => $file['size_label'],
            'modified' => date('Y-m-d H:i:s', $file['modified']),
            'current' => $file['current'],
        ], $logs->files());

        if ($this->option('files')) {
            return $this->success('log_files', [
                'directory' => $logs->directoryLabel(),
                'files' => $files,
            ]);
        }

        $level = strtolower(trim((string) $this->option('level')));

        if ($level !== '' && ! in_array($level, LogViewerService::LEVELS, true)) {
            return $this->failure(
                'E_INVALID_INPUT',
                "Unknown level \"{$level}\".",
                $this->exitForCode('E_INVALID_INPUT'),
                'One of: '.implode(', ', array_reverse(LogViewerService::LEVELS)).'. A level returns itself and every one more severe.'
            );
        }

        $since = trim((string) $this->option('since'));

        if ($since !== '' && ! $logs->understandsSince($since)) {
            return $this->failure(
                'E_INVALID_INPUT',
                "Cannot read --since=\"{$since}\".",
                $this->exitForCode('E_INVALID_INPUT'),
                'Minutes, hours, or days back (30m, 1h, 7d), or a date (2026-10-02, 2026-10-02 14:30).'
            );
        }

        $name = trim((string) $this->option('file'));
        $file = $name === '' ? $logs->current() : $logs->find($name);

        if ($file === null && $name !== '') {
            return $this->failure(
                'E_NOT_FOUND',
                "No log named \"{$name}\" in {$logs->directoryLabel()}.",
                $this->exitForCode('E_NOT_FOUND'),
                'php artisan larapilot:logs --files lists the ones there are.'
            );
        }

        $base = [
            'directory' => $logs->directoryLabel(),
            'file' => null,
            'redacted' => true,
        ];

        // Nothing logged is an answer, not a failure: a bug can leave no trace.
        if ($file === null) {
            return $this->success('logs', $base + [
                'entries' => [],
                'hint' => 'No .log file in '.$logs->directoryLabel().'. LARAPILOT_LOG_VIEWER_PATH names another folder.',
            ]);
        }

        $options = [
            'level' => $level,
            'search' => (string) $this->option('search'),
            'since' => $since,
            'limit' => is_numeric($this->option('limit')) ? max(1, min(100, (int) $this->option('limit'))) : 20,
        ];
        $overview = $logs->overview($file);
        $laravel = $overview['format'] === 'laravel';

        $data = array_merge($base, [
            'file' => [
                'name' => $file['key'],
                'size' => $file['size_label'],
                'modified' => date('Y-m-d H:i:s', $file['modified']),
            ],
            'format' => $overview['format'],
            // What the file holds, whatever was asked for.
            'holds' => [
                'levels' => $overview['levels'],
                'from' => $overview['from'],
                'to' => $overview['to'],
                'whole_file' => $overview['complete'],
            ],
            // A file read by line has no levels and no dates: those two are
            // not applied, and are not said to be.
            'filters' => array_filter([
                'level' => $level === '' || ! $laravel ? null : $level.' and worse',
                'search' => $options['search'] === '' ? null : $options['search'],
                'since' => $since === '' || ! $laravel ? null : $since,
            ]),
        ]);

        if (! $laravel && ($level !== '' || $since !== '')) {
            $data['hint'] = $file['key'].' is not in Laravel\'s log format: it has no levels and no dates, so --level and --since were left out. Every line that matches --search is returned, of any age.';
        }

        if ($this->option('group')) {
            $groups = $logs->groups($file, $options);

            if ($groups['capped']) {
                $data['hint'] = 'More than '.number_format($groups['total']).' different things were logged: the repeats are counted of the first '.number_format($groups['total']).' met from the end of the file. --search, --level, or --since narrows it.';
            }

            return $this->success('logs', $data + [
                'grouped' => true,
                'matched' => $groups['entries'],
                'distinct' => $groups['total'],
                'entries' => array_map(fn (array $entry): array => $this->lean($entry), $groups['groups']),
            ]);
        }

        $page = $logs->read($file, $options);

        return $this->success('logs', $data + [
            'grouped' => false,
            'more' => $page['next'] !== null || $page['resume'] !== null,
            'entries' => array_map(fn (array $entry): array => $this->lean($entry), $page['entries']),
        ]);
    }

    /**
     * What an agent needs of an entry: when, what, where — and the frames
     * of the application, not the sixty of the framework under them.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    protected function lean(array $entry): array
    {
        $exception = $entry['exception'];
        $frames = [];

        foreach ($entry['frames'] as $frame) {
            if ($frame['app'] && count($frames) < 5) {
                $frames[] = $frame['file'].':'.$frame['line'].' '.$this->short($frame['call'], 120);
            }
        }

        $cause = $entry['previous'] === [] ? null : $entry['previous'][count($entry['previous']) - 1];

        return array_filter([
            'time' => $entry['time'] === '' ? null : $entry['time'],
            'level' => $entry['level'] === '' ? null : $entry['level'],
            'env' => $entry['env'] === '' ? null : $entry['env'],
            'count' => $entry['count'] ?? null,
            'first' => ($entry['count'] ?? 1) > 1 ? $entry['first'] : null,
            'message' => $this->short((string) $entry['message'], 400),
            'class' => $exception['class'] ?? null,
            'where' => $entry['where'],
            'caused_by' => $cause === null ? null : $cause['class'].': '.$this->short((string) $cause['message'], 200).' at '.$cause['where'],
            'frames' => $frames,
            // Indented for the page; on one line here, where every space is paid for.
            'context' => $entry['context'] === null ? null : $this->short((string) preg_replace('/\s*\n\s*/', ' ', (string) $entry['context']), 400),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    protected function short(string $text, int $chars): string
    {
        return mb_strlen($text) > $chars ? mb_substr($text, 0, $chars).'…' : $text;
    }
}
