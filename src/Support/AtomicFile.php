<?php

declare(strict_types=1);

namespace Larapilot\Support;

final class AtomicFile
{
    /**
     * Write via temp file + rename so concurrent readers never observe
     * a partially written file.
     */
    public static function write(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Unable to create directory {$directory}.");
        }

        $temp = $directory.DIRECTORY_SEPARATOR.'.'.basename($path).'.'.bin2hex(random_bytes(6)).'.tmp';

        if (@file_put_contents($temp, $contents) === false) {
            throw new \RuntimeException("Unable to write file {$path}.");
        }

        if (! @rename($temp, $path)) {
            @unlink($temp);

            throw new \RuntimeException("Unable to write file {$path}.");
        }
    }
}
