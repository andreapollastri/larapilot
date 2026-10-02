<?php

declare(strict_types=1);

namespace Larapilot\Services\Laravel;

use Larapilot\Support\AtomicFile;
use Throwable;

/**
 * What the application sent or dumped, kept on disk for the Laravel page:
 * one JSON file for each record, and beside it the parts too large to sit
 * in a list — the body of a mail. Only the newest are kept.
 *
 * The folder is `laravel_viewer.path`, or `storage/larapilot`: it ignores
 * itself in git, since it holds what one machine did.
 */
class RecordStore
{
    /** An id is the moment of the record, then six random hex digits. */
    public const ID = '/^[0-9]{20}[0-9a-f]{6}$/';

    public function __construct(protected string $kind) {}

    public function directory(): string
    {
        return $this->root().DIRECTORY_SEPARATOR.$this->kind;
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

    public function keep(): int
    {
        return max(1, min(1000, (int) config('larapilot.laravel_viewer.keep', 100)));
    }

    /**
     * Keep a record, and drop the oldest ones beyond what is kept.
     *
     * @param  array<string, mixed>  $record
     * @param  array<string, string>  $parts  extension => contents
     */
    public function put(array $record, array $parts = []): string
    {
        $this->prepare();

        [$fraction, $seconds] = explode(' ', microtime());
        $id = date('YmdHis', (int) $seconds).substr($fraction, 2, 6).bin2hex(random_bytes(3));
        $path = $this->directory().DIRECTORY_SEPARATOR.$id;

        foreach ($parts as $extension => $contents) {
            AtomicFile::write($path.'.'.$extension, $contents);
        }

        // The record goes last: a list never names a part that is not there.
        AtomicFile::write($path.'.json', (string) json_encode(
            ['id' => $id] + $record,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR,
        ));

        $this->prune();

        return $id;
    }

    /**
     * The records, the newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $records = [];

        foreach (array_reverse($this->ids()) as $id) {
            $record = $this->read($id);

            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    public function count(): int
    {
        return count($this->ids());
    }

    /**
     * One record, found by id in what the folder lists: nothing else is
     * opened, whatever is asked for.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        return in_array($id, $this->ids(), true) ? $this->read($id) : null;
    }

    public function part(string $id, string $extension): ?string
    {
        if (preg_match('/^[a-z]{1,8}$/', $extension) !== 1 || ! in_array($id, $this->ids(), true)) {
            return null;
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$id.'.'.$extension;
        $contents = is_file($path) ? @file_get_contents($path) : false;

        return $contents === false ? null : $contents;
    }

    /**
     * Forget every record. Answers how many there were.
     */
    public function clear(): int
    {
        $ids = $this->ids();
        $this->forget($ids);

        return count($ids);
    }

    /**
     * @return list<string> oldest first
     */
    protected function ids(): array
    {
        clearstatcache();
        $directory = $this->directory();

        if (! is_dir($directory)) {
            return [];
        }

        $ids = [];

        foreach (@scandir($directory) ?: [] as $name) {
            if (str_ends_with($name, '.json') && preg_match(self::ID, $id = substr($name, 0, -5)) === 1) {
                $ids[] = $id;
            }
        }

        sort($ids, SORT_STRING);

        return $ids;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function read(string $id): ?array
    {
        $contents = @file_get_contents($this->directory().DIRECTORY_SEPARATOR.$id.'.json');
        $record = $contents === false ? null : json_decode($contents, true);

        return is_array($record) ? ['id' => $id] + $record : null;
    }

    protected function prune(): void
    {
        $ids = $this->ids();

        $this->forget(array_slice($ids, 0, max(0, count($ids) - $this->keep())));
    }

    /**
     * Remove the records and their parts: every file named after one of
     * the ids.
     *
     * @param  list<string>  $ids
     */
    protected function forget(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $gone = array_flip($ids);
        $directory = $this->directory();

        foreach (@scandir($directory) ?: [] as $name) {
            if (isset($gone[strstr($name, '.', true)])) {
                @unlink($directory.DIRECTORY_SEPARATOR.$name);
            }
        }
    }

    /**
     * The folder, with the `.gitignore` that keeps it out of the repository.
     */
    protected function prepare(): void
    {
        $root = $this->root();
        $directory = $this->directory();

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Unable to create directory {$directory}.");
        }

        if (! is_file($root.DIRECTORY_SEPARATOR.'.gitignore')) {
            try {
                AtomicFile::write($root.DIRECTORY_SEPARATOR.'.gitignore', "*\n");
            } catch (Throwable) {
                // The records are still kept; the folder is only not ignored.
            }
        }
    }

    protected function root(): string
    {
        $configured = config('larapilot.laravel_viewer.path');

        if (! is_string($configured) || trim($configured) === '') {
            return rtrim(storage_path('larapilot'), '/\\');
        }

        $configured = trim($configured);

        if (preg_match('/^(?:[\/\\\\]|[A-Za-z]:[\/\\\\])/', $configured) !== 1) {
            $configured = base_path($configured);
        }

        return rtrim($configured, '/\\') ?: DIRECTORY_SEPARATOR;
    }
}
