<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class DiagnosticsService
{
    /** What stands for a line the redaction could not check. */
    public const UNCHECKED = '[REDACTED: this line could not be checked for secrets]';

    /** `"key":` — and `\"key\":`, the way JSON is written inside a string. */
    protected const CONTEXT_KEY = '/(\\\\*+)"([^"\\\\]{1,200}+)\\1"\s*+:\s*+/';

    /** A key that names a secret, whatever comes before and after it. */
    protected const SECRET_KEY = '/api[_-]?key|token|secret|password|passwd|pwd|authorization|cookie|private[_-]?key|auth[_-]?pw/i';

    /** A number under one of these is a quantity or a date, not the secret. */
    protected const QUANTITY_KEY = '/tokens|count|length|size|limit|ttl|expir|lifetime|timeout|_(?:at|id)$/i';

    /**
     * Read-only runtime snapshot for bug triage (status + optional redacted log tail).
     *
     * @return array<string, mixed>
     */
    public function snapshot(?int $logLines = null, bool $includeLogs = true): array
    {
        $defaultLines = (int) config('larapilot.diagnostics.default_log_lines', 100);
        $maxLines = (int) config('larapilot.diagnostics.max_log_lines', 500);
        $lines = max(1, min($logLines ?? $defaultLines, $maxLines));

        $checks = $this->checks();
        $critical = ['storage_writable', 'database'];
        $healthy = collect($critical)->every(
            fn (string $key): bool => (bool) ($checks[$key]['ok'] ?? false)
        );

        $payload = [
            'collected_at' => now()->toIso8601String(),
            'app' => $this->appInfo(),
            'checks' => $checks,
            'healthy' => $healthy,
        ];

        if ($includeLogs) {
            $payload['logs'] = $this->logTail($lines);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function appInfo(): array
    {
        return [
            'name' => (string) config('app.name'),
            'env' => app()->environment(),
            'debug' => (bool) config('app.debug'),
            'url' => (string) config('app.url'),
            'timezone' => (string) config('app.timezone'),
            'locale' => (string) config('app.locale'),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
        ];
    }

    /**
     * @return array<string, array{ok: bool, detail: string}>
     */
    protected function checks(): array
    {
        return [
            'storage_writable' => $this->checkStorageWritable(),
            'cache' => $this->checkCache(),
            'database' => $this->checkDatabase(),
            'queue' => $this->checkQueue(),
            'log_file' => $this->checkLogFile(),
        ];
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    protected function checkStorageWritable(): array
    {
        $path = storage_path('app');

        if (! is_dir($path)) {
            return ['ok' => false, 'detail' => 'storage/app missing'];
        }

        if (! is_writable($path)) {
            return ['ok' => false, 'detail' => 'storage/app not writable'];
        }

        return ['ok' => true, 'detail' => 'storage/app writable'];
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    protected function checkCache(): array
    {
        $driver = (string) config('cache.default', 'unknown');

        try {
            $key = 'larapilot:diagnostics:'.bin2hex(random_bytes(4));
            Cache::put($key, 'ok', 5);
            $value = Cache::pull($key);

            if ($value !== 'ok') {
                return ['ok' => false, 'detail' => "cache driver {$driver} failed round-trip"];
            }

            return ['ok' => true, 'detail' => "cache driver {$driver}"];
        } catch (Throwable $exception) {
            return ['ok' => false, 'detail' => "cache driver {$driver}: ".$this->safeMessage($exception)];
        }
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    protected function checkDatabase(): array
    {
        $connection = (string) config('database.default', 'unknown');
        $driver = (string) config("database.connections.{$connection}.driver", 'unknown');

        try {
            DB::connection()->getPdo();

            return ['ok' => true, 'detail' => "connection {$connection} ({$driver})"];
        } catch (Throwable $exception) {
            return ['ok' => false, 'detail' => "connection {$connection} ({$driver}): ".$this->safeMessage($exception)];
        }
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    protected function checkQueue(): array
    {
        $connection = (string) config('queue.default', 'unknown');
        $driver = (string) config("queue.connections.{$connection}.driver", 'unknown');

        return [
            'ok' => true,
            'detail' => "default connection {$connection} ({$driver})",
        ];
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    protected function checkLogFile(): array
    {
        $path = $this->resolveLogPath();

        if ($path === null) {
            return ['ok' => false, 'detail' => 'log path unresolved for default channel'];
        }

        if (! is_file($path)) {
            return ['ok' => false, 'detail' => $this->relativeOrBasename($path).' missing'];
        }

        if (! is_readable($path)) {
            return ['ok' => false, 'detail' => $this->relativeOrBasename($path).' not readable'];
        }

        return ['ok' => true, 'detail' => $this->relativeOrBasename($path)];
    }

    /**
     * @return array<string, mixed>
     */
    protected function logTail(int $lines): array
    {
        $channel = (string) config('logging.default', 'stack');
        $path = $this->resolveLogPath();

        $base = [
            'available' => false,
            'path' => $path !== null ? $this->relativeOrBasename($path) : null,
            'channel' => $channel,
            'lines_requested' => $lines,
            'lines_returned' => 0,
            'redacted' => true,
            'entries' => [],
        ];

        if ($path === null || ! is_file($path) || ! is_readable($path)) {
            return $base;
        }

        $rawLines = $this->readLastLines($path, $lines);
        $entries = array_map(fn (string $line): string => $this->redact($line), $rawLines);

        return [
            ...$base,
            'available' => true,
            'lines_returned' => count($entries),
            'entries' => $entries,
        ];
    }

    /**
     * The file the default log channel writes to now, when it writes to one.
     */
    public function currentLogPath(): ?string
    {
        return $this->resolveLogPath();
    }

    protected function resolveLogPath(): ?string
    {
        $channel = (string) config('logging.default', 'stack');
        $channels = config('logging.channels', []);

        if (! is_array($channels)) {
            return null;
        }

        return $this->resolveChannelPath($channel, $channels, []);
    }

    /**
     * @param  array<string, mixed>  $channels
     * @param  list<string>  $seen
     */
    protected function resolveChannelPath(string $channel, array $channels, array $seen): ?string
    {
        if (in_array($channel, $seen, true)) {
            return null;
        }

        $seen[] = $channel;
        $config = $channels[$channel] ?? null;

        if (! is_array($config)) {
            return null;
        }

        $driver = (string) ($config['driver'] ?? '');

        if ($driver === 'stack') {
            $nested = $config['channels'] ?? [];

            if (! is_array($nested) || $nested === []) {
                return null;
            }

            $first = (string) ($nested[0] ?? '');

            return $first !== '' ? $this->resolveChannelPath($first, $channels, $seen) : null;
        }

        if (in_array($driver, ['single', 'daily'], true)) {
            $path = $config['path'] ?? null;

            if (! is_string($path) || $path === '') {
                return null;
            }

            if ($driver === 'daily') {
                $daily = $this->latestDailyLog($path);

                return $daily ?? $path;
            }

            return $path;
        }

        return null;
    }

    protected function latestDailyLog(string $path): ?string
    {
        $directory = dirname($path);
        $basename = basename($path);
        $stem = preg_replace('/\.log$/', '', $basename) ?: $basename;

        if (! is_dir($directory)) {
            return null;
        }

        $matches = glob($directory.'/'.$stem.'-*.log') ?: [];

        if ($matches === []) {
            return is_file($path) ? $path : null;
        }

        rsort($matches);

        return $matches[0];
    }

    /**
     * @return list<string>
     */
    protected function readLastLines(string $path, int $lines): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        try {
            $buffer = '';
            $chunkSize = 4096;
            $position = fstat($handle)['size'] ?? 0;
            $lineCount = 0;

            while ($position > 0 && $lineCount <= $lines) {
                $read = min($chunkSize, $position);
                $position -= $read;
                fseek($handle, $position);
                $chunk = fread($handle, $read);

                if ($chunk === false) {
                    break;
                }

                $buffer = $chunk.$buffer;
                $lineCount = substr_count($buffer, "\n");
            }
        } finally {
            fclose($handle);
        }

        $all = preg_split("/\r\n|\n|\r/", $buffer) ?: [];
        $all = array_values(array_filter($all, fn (string $line): bool => $line !== ''));

        if (count($all) <= $lines) {
            return $all;
        }

        return array_values(array_slice($all, -$lines));
    }

    public function redact(string $line): string
    {
        // `"password":"…"` — the way a context array is written.
        $redacted = $this->redactContext($line);

        if ($redacted === null) {
            return self::UNCHECKED;
        }

        $patterns = [
            '/(?i)(authorization:\s*bearer\s+)\S+/' => '$1[REDACTED]',
            // `password=…`, `secret: …` — never `Password::min()`, which is code.
            '/(?i)(api[_-]?key|access[_-]?token|refresh[_-]?token|secret|password|passwd|pwd)\s*((?:=|:(?!:))\s*)(?!"?\[REDACTED\])\S+/' => '$1$2[REDACTED]',
            '/(?i)(APP_KEY\s*=\s*)\S+/' => '$1[REDACTED]',
            '/\b(sk_(?:live|test)_)[A-Za-z0-9]+/' => '$1[REDACTED]',
            '/\b(AKIA[0-9A-Z]{16})\b/' => '[REDACTED_AWS_KEY]',
            '/(?i)(\/\/[^:\s\/]+:)[^@\s]+(@)/' => '$1[REDACTED]$2',
            '/(?i)(Bearer\s+)[A-Za-z0-9\-._~+\/]+=*/' => '$1[REDACTED]',
            // JWTs (header.payload.signature).
            '/\beyJ[A-Za-z0-9_-]{6,}\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+/' => '[REDACTED_JWT]',
            // Cookie headers carry session identifiers.
            '/(?i)\b(cookie|set-cookie)(\s*[:=]\s*).+/' => '$1$2[REDACTED]',
            // Laravel-style base64 secrets outside APP_KEY assignments.
            '/\bbase64:[A-Za-z0-9+\/=]{16,}/' => '[REDACTED]',
            // PEM boundary lines and their base64 body lines.
            '/-----(BEGIN|END)[A-Z ]*(PRIVATE|PUBLIC)? ?KEY[A-Z ]*-----.*/' => '[REDACTED_PEM]',
            '/^[A-Za-z0-9+\/=]{40,}$/' => '[REDACTED]',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $redacted = preg_replace($pattern, $replacement, $redacted);

            // A pattern that gives up on a line has not checked it: none of
            // the line is shown, rather than all of it as it was written.
            if ($redacted === null) {
                return self::UNCHECKED;
            }
        }

        return $redacted;
    }

    /**
     * The values of a context written as JSON, under the keys that name a
     * secret: a string, the list a header comes as, an object, a number.
     * JSON inside a string — a request body that was logged — is read the
     * same way, with its quotes escaped. Null when the line cannot be read.
     */
    protected function redactContext(string $line): ?string
    {
        if (! str_contains($line, '"')) {
            return $line;
        }

        $out = '';
        $copied = 0;
        $offset = 0;
        $length = strlen($line);

        while ($offset < $length) {
            $found = preg_match(self::CONTEXT_KEY, $line, $match, PREG_OFFSET_CAPTURE, $offset);

            if ($found === false) {
                return null;
            }

            if ($found === 0) {
                break;
            }

            // The backslashes before each quote say how deep the JSON is.
            $escape = $match[1][0];
            $value = $match[0][1] + strlen($match[0][0]);
            $end = preg_match(self::SECRET_KEY, $match[2][0]) === 1
                ? $this->contextValueEnd($line, $value, $escape, $match[2][0])
                : null;

            if ($end === null) {
                $offset = $value;

                continue;
            }

            $out .= substr($line, $copied, $value - $copied).$escape.'"[REDACTED]'.$escape.'"';
            $copied = $offset = $end;
        }

        return $out.substr($line, $copied);
    }

    /**
     * Where the value of a secret key ends, or null when it is not one to
     * hide: `null`, `true`, `false`, and a number that is a quantity. A
     * value the line cuts short is hidden to the end of the line.
     */
    protected function contextValueEnd(string $line, int $at, string $escape, string $key): ?int
    {
        $length = strlen($line);
        $depth = strlen($escape);
        $quote = $escape.'"';

        if ($at >= $length) {
            return null;
        }

        if (substr_compare($line, $quote, $at, strlen($quote)) === 0) {
            return $this->contextStringEnd($line, $at + strlen($quote), $depth) ?? $length;
        }

        if ($line[$at] === '[' || $line[$at] === '{') {
            return $this->contextStructureEnd($line, $at, $depth);
        }

        $end = $at + strcspn($line, ",}] \t\r\n\"\\", $at);
        $scalar = substr($line, $at, $end - $at);

        if (in_array($scalar, ['', 'null', 'true', 'false'], true)) {
            return null;
        }

        // `"prompt_tokens":1500`, `"token_ttl":3600` — how many, not which.
        if (is_numeric($scalar) && preg_match(self::QUANTITY_KEY, $key) === 1) {
            return null;
        }

        return $end;
    }

    /**
     * The end of a JSON string that starts at `$from`. A quote closes it
     * when the backslashes before it are the ones of its depth: none, or
     * an even number, in plain JSON; one, five, nine inside a string.
     */
    protected function contextStringEnd(string $line, int $from, int $depth): ?int
    {
        $period = 2 * ($depth + 1);
        $at = $from;

        while (($at = strpos($line, '"', $at)) !== false) {
            if ($this->backslashesBefore($line, $at, $from) % $period === $depth) {
                return $at + 1;
            }

            $at++;
        }

        return null;
    }

    /**
     * The end of a list or an object that starts at `$from`: its closing
     * bracket, whatever its strings hold.
     */
    protected function contextStructureEnd(string $line, int $from, int $depth): int
    {
        $length = strlen($line);
        $period = 2 * ($depth + 1);
        $open = 0;
        $at = $from;

        while ($at < $length) {
            $at += strcspn($line, '[]{}"', $at);

            if ($at >= $length) {
                break;
            }

            if ($line[$at] === '"') {
                if ($this->backslashesBefore($line, $at, $from) % $period !== $depth) {
                    $at++;

                    continue;
                }

                $closed = $this->contextStringEnd($line, $at + 1, $depth);

                if ($closed === null) {
                    break;
                }

                $at = $closed;

                continue;
            }

            $open += $line[$at] === '[' || $line[$at] === '{' ? 1 : -1;
            $at++;

            if ($open <= 0) {
                return $at;
            }
        }

        return $length;
    }

    protected function backslashesBefore(string $line, int $at, int $from): int
    {
        $count = 0;

        while ($at - $count - 1 >= $from && $line[$at - $count - 1] === '\\') {
            $count++;
        }

        return $count;
    }

    protected function safeMessage(Throwable $exception): string
    {
        return $this->redact($exception->getMessage());
    }

    protected function relativeOrBasename(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';
        $normalized = str_replace('\\', '/', $path);

        if (str_starts_with($normalized, $base)) {
            return substr($normalized, strlen($base));
        }

        return basename($normalized);
    }
}
