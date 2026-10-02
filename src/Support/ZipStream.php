<?php

declare(strict_types=1);

namespace Larapilot\Support;

use DeflateContext;
use HashContext;
use RuntimeException;

/**
 * Writes a zip archive a piece at a time, so it is sent while it is made:
 * no file on disk, and never more of an entry in memory than the piece
 * being written. An entry is compressed as it comes and its size follows
 * it, which every reader of the format accepts. Without zlib the entries
 * are stored as they are, one whole entry in memory at a time.
 *
 * The plain format, with no zip64: an archive holds up to 65,535 entries
 * and 4 GB.
 */
class ZipStream
{
    /** @var callable(string): void */
    protected $write;

    protected bool $deflate;

    /** @var list<array{name: string, crc: int, compressed: int, size: int, offset: int, method: int, flags: int}> */
    protected array $entries = [];

    protected int $offset = 0;

    protected int $time;

    protected int $date;

    /** @var array{name: string, offset: int, size: int, compressed: int}|null */
    protected ?array $entry = null;

    protected ?HashContext $hash = null;

    protected ?DeflateContext $deflater = null;

    protected string $stored = '';

    /**
     * @param  callable(string): void  $write
     */
    public function __construct(callable $write, ?bool $deflate = null, ?int $moment = null)
    {
        $this->write = $write;
        $this->deflate = $deflate ?? function_exists('deflate_init');

        // The date of every entry: when the archive was made, as the format keeps it.
        $moment = getdate($moment ?? time());
        $this->time = ($moment['hours'] << 11) | ($moment['minutes'] << 5) | intdiv($moment['seconds'], 2);
        $this->date = (max(0, $moment['year'] - 1980) << 9) | ($moment['mon'] << 5) | $moment['mday'];
    }

    /**
     * One whole file.
     */
    public function add(string $name, string $contents): void
    {
        $this->begin($name);
        $this->append($contents);
        $this->end();
    }

    /**
     * Start a file that is written a piece at a time with `append()`.
     */
    public function begin(string $name): void
    {
        if ($this->entry !== null) {
            $this->end();
        }

        $name = ltrim(str_replace('\\', '/', $name), '/');

        if ($name === '' || strlen($name) > 0xFFFF) {
            throw new RuntimeException('A zip entry needs a name.');
        }

        $this->entry = ['name' => $name, 'offset' => $this->offset, 'size' => 0, 'compressed' => 0];
        $this->hash = hash_init('crc32b');
        $this->stored = '';

        if ($this->deflate) {
            $deflater = deflate_init(ZLIB_ENCODING_RAW, ['level' => 6]);

            if ($deflater === false) {
                throw new RuntimeException('The zip entry cannot be compressed.');
            }

            $this->deflater = $deflater;

            // Bit 3: the checksum and the sizes come after the data. Bit 11: the name is UTF-8.
            $this->out($this->localHeader($name, 8, 0x0808, 0, 0, 0));
        }
    }

    public function append(string $chunk): void
    {
        if ($this->entry === null || $this->hash === null) {
            throw new RuntimeException('No zip entry is open.');
        }

        if ($chunk === '') {
            return;
        }

        hash_update($this->hash, $chunk);
        $this->entry['size'] += strlen($chunk);

        if ($this->deflater !== null) {
            $this->compressed((string) deflate_add($this->deflater, $chunk, ZLIB_NO_FLUSH));
        } else {
            $this->stored .= $chunk;
        }
    }

    public function end(): void
    {
        if ($this->entry === null || $this->hash === null) {
            return;
        }

        $crc = (int) hexdec(hash_final($this->hash));

        if ($this->deflater !== null) {
            $this->compressed((string) deflate_add($this->deflater, '', ZLIB_FINISH));
            $this->out(pack('VVVV', 0x08074B50, $crc, $this->entry['compressed'], $this->entry['size']));
            [$method, $flags] = [8, 0x0808];
        } else {
            $this->entry['compressed'] = $this->entry['size'];
            $this->out($this->localHeader($this->entry['name'], 0, 0x0800, $crc, $this->entry['size'], $this->entry['size']));
            $this->out($this->stored);
            [$method, $flags] = [0, 0x0800];
        }

        $this->entries[] = [
            'name' => $this->entry['name'],
            'crc' => $crc,
            'compressed' => $this->entry['compressed'],
            'size' => $this->entry['size'],
            'offset' => $this->entry['offset'],
            'method' => $method,
            'flags' => $flags,
        ];

        $this->entry = null;
        $this->hash = null;
        $this->deflater = null;
        $this->stored = '';
    }

    /**
     * Close the archive: the list of what it holds, which a reader opens it by.
     */
    public function finish(): void
    {
        $this->end();

        $start = $this->offset;

        foreach ($this->entries as $entry) {
            $this->out(
                // Made on Unix, so the permissions below are read: a plain file, rw-r--r--.
                pack('VvvvvvvVVVvvvvvVV', 0x02014B50, 0x0314, 20, $entry['flags'], $entry['method'], $this->time, $this->date, $entry['crc'], $entry['compressed'], $entry['size'], strlen($entry['name']), 0, 0, 0, 0, 0o100644 << 16, $entry['offset'])
                .$entry['name']
            );
        }

        $count = min(0xFFFF, count($this->entries));

        $this->out(pack('VvvvvVVv', 0x06054B50, 0, 0, $count, $count, $this->offset - $start, $start, 0));
    }

    protected function localHeader(string $name, int $method, int $flags, int $crc, int $compressed, int $size): string
    {
        return pack('VvvvvvVVVvv', 0x04034B50, 20, $flags, $method, $this->time, $this->date, $crc, $compressed, $size, strlen($name), 0).$name;
    }

    protected function compressed(string $bytes): void
    {
        if ($this->entry !== null) {
            $this->entry['compressed'] += strlen($bytes);
        }

        $this->out($bytes);
    }

    protected function out(string $bytes): void
    {
        if ($bytes === '') {
            return;
        }

        $this->offset += strlen($bytes);
        ($this->write)($bytes);
    }
}
