<?php

declare(strict_types=1);

namespace Larapilot\Support;

/**
 * Advisory exclusive lock around read-modify-write cycles on workflow
 * files (backlog, plans, internal feedback). Re-entrant per path within
 * the same process, so nested service calls do not deadlock.
 */
final class FileLock
{
    /**
     * @var array<string, int>
     */
    private static array $depth = [];

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withLock(string $path, callable $callback): mixed
    {
        if ((self::$depth[$path] ?? 0) > 0) {
            self::$depth[$path]++;

            try {
                return $callback();
            } finally {
                self::$depth[$path]--;
            }
        }

        $lockPath = $path.'.lock';
        $directory = dirname($lockPath);

        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        $handle = @fopen($lockPath, 'c');

        if ($handle === false) {
            // Degrade gracefully (e.g. read-only filesystem): run unlocked
            // rather than failing the whole command.
            return $callback();
        }

        try {
            flock($handle, LOCK_EX);
            self::$depth[$path] = 1;

            return $callback();
        } finally {
            unset(self::$depth[$path]);
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
