<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class DiagnosticsService
{
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
        $patterns = [
            '/(?i)(authorization:\s*bearer\s+)\S+/' => '$1[REDACTED]',
            '/(?i)(api[_-]?key|access[_-]?token|refresh[_-]?token|secret|password|passwd|pwd)\s*([:=]\s*)\S+/' => '$1$2[REDACTED]',
            '/(?i)(APP_KEY\s*=\s*)\S+/' => '$1[REDACTED]',
            '/\b(sk_(?:live|test)_)[A-Za-z0-9]+/' => '$1[REDACTED]',
            '/\b(AKIA[0-9A-Z]{16})\b/' => '[REDACTED_AWS_KEY]',
            '/(?i)(\/\/[^:\s\/]+:)[^@\s]+(@)/' => '$1[REDACTED]$2',
            '/(?i)(Bearer\s+)[A-Za-z0-9\-._~+\/]+=*/' => '$1[REDACTED]',
        ];

        $redacted = $line;

        foreach ($patterns as $pattern => $replacement) {
            $redacted = preg_replace($pattern, $replacement, $redacted) ?? $redacted;
        }

        return $redacted;
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
