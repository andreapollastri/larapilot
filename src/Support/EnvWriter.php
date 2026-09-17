<?php

declare(strict_types=1);

namespace Larapilot\Support;

/**
 * Read and update key/value pairs in the project `.env` file without
 * overwriting unrelated lines or secrets.
 */
class EnvWriter
{
    public static function envPath(?string $basePath = null): string
    {
        return rtrim($basePath ?? base_path(), '/\\').'/.env';
    }

    public static function get(string $key, ?string $envPath = null): ?string
    {
        $path = $envPath ?? self::envPath();

        if (is_file($path)) {
            $fromFile = self::readFromFile($path, $key);

            if ($fromFile !== null) {
                return $fromFile;
            }
        }

        $value = env($key);

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return null;
    }

    public static function set(string $key, string $value, ?string $envPath = null): bool
    {
        $path = $envPath ?? self::envPath();
        $quoted = self::quote($value);
        $line = $key.'='.$quoted;

        if (! is_file($path)) {
            AtomicFile::write($path, $line.PHP_EOL);
            self::refreshRuntimeEnv($key, $value);

            return true;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return false;
        }

        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $content) === 1) {
            $updated = (string) preg_replace($pattern, $line, $content, 1);
        } else {
            $updated = rtrim($content).PHP_EOL.$line.PHP_EOL;
        }

        AtomicFile::write($path, $updated);
        self::refreshRuntimeEnv($key, $value);

        return true;
    }

    protected static function readFromFile(string $path, string $key): ?string
    {
        $content = file_get_contents($path);

        if ($content === false) {
            return null;
        }

        $pattern = '/^'.preg_quote($key, '/').'=(.*)$/m';

        if (preg_match($pattern, $content, $matches) !== 1) {
            return null;
        }

        $raw = trim($matches[1]);

        if ($raw === '') {
            return null;
        }

        if (
            (str_starts_with($raw, '"') && str_ends_with($raw, '"'))
            || (str_starts_with($raw, "'") && str_ends_with($raw, "'"))
        ) {
            return stripcslashes(substr($raw, 1, -1));
        }

        return $raw;
    }

    protected static function refreshRuntimeEnv(string $key, string $value): void
    {
        if ($value === '') {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    protected static function quote(string $value): string
    {
        if ($value === '' || preg_match('/[\s#\'"\\\\]/', $value) === 1) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }
}
