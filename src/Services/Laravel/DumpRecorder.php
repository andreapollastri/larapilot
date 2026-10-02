<?php

declare(strict_types=1);

namespace Larapilot\Services\Laravel;

use Closure;
use Illuminate\Container\Container;
use Larapilot\Services\ConfigService;
use ReflectionClass;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\VarDumper;
use Throwable;

/**
 * Keeps what `dump()` and `dd()` print, to be read on the Laravel page:
 * the value as text, the file and line that dumped it, and the request or
 * command it happened in. It stands in front of the handler that was there
 * and hands every value on to it, so a dump still shows where it did.
 */
class DumpRecorder
{
    /** The most that is kept of one dump, in bytes. */
    protected const TEXT = 65536;

    protected static ?Closure $handler = null;

    /** @var callable|null */
    protected static $previous = null;

    protected RecordStore $store;

    public function __construct(protected ConfigService $config)
    {
        $this->store = new RecordStore('dumps');
    }

    public function store(): RecordStore
    {
        return $this->store;
    }

    public function recording(): bool
    {
        return $this->config->laravelViewerRecords('dumps');
    }

    /**
     * Stand in front of the dump handler in place. One closure for the
     * life of the process, whatever application is running in it: a test
     * suite boots hundreds, and each one puts Laravel's own handler back.
     */
    public static function install(): void
    {
        self::$handler ??= static function (mixed $var, ?string $label = null): mixed {
            self::keep($var, $label);

            if (self::$previous === null) {
                // Nothing was there: let the component choose its own
                // handler, then stand in front of that one.
                VarDumper::setHandler(null);

                try {
                    return VarDumper::dump($var, $label);
                } finally {
                    self::$previous = VarDumper::setHandler(self::$handler);
                }
            }

            return (self::$previous)($var, $label);
        };

        $previous = VarDumper::setHandler(self::$handler);

        if ($previous !== self::$handler) {
            self::$previous = $previous;
        }
    }

    /**
     * Who takes the dumps before any handler sees them, when someone does.
     * Laravel Herd, while its Dumps window is watching, puts a class of
     * its own in the place of the component's: `dump()` then goes to that
     * window, and a handler set here is never called.
     */
    public static function takenBy(): ?string
    {
        try {
            $class = (new ReflectionClass(VarDumper::class))->getName();
        } catch (Throwable) {
            return null;
        }

        if ($class === 'Symfony\\Component\\VarDumper\\VarDumper') {
            return null;
        }

        return str_starts_with($class, 'Herd\\') ? 'Laravel Herd' : $class;
    }

    /**
     * Never throws: a dump that cannot be kept is still shown.
     */
    protected static function keep(mixed $var, ?string $label): void
    {
        try {
            $container = Container::getInstance();

            if (! $container->bound(self::class)) {
                return;
            }

            $recorder = $container->make(self::class);

            if ($recorder->recording()) {
                $recorder->record($var, $label, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30));
            }
        } catch (Throwable) {
            //
        }
    }

    /**
     * @param  list<array<string, mixed>>  $trace
     */
    public function record(mixed $var, ?string $label, array $trace = []): void
    {
        $cloner = new VarCloner;
        $cloner->setMaxItems(500);
        $cloner->setMaxString(4000);

        $dumper = new CliDumper;
        $dumper->setColors(false);

        $text = rtrim((string) $dumper->dump($cloner->cloneVar($var), true));
        [$file, $line] = $this->source($trace);

        $this->store->put([
            'at' => date(DATE_ATOM),
            'label' => $label !== null && $label !== '' ? mb_substr($label, 0, 200) : null,
            'file' => $file,
            'line' => $line,
            'context' => RunContext::describe(),
            'text' => substr($text, 0, self::TEXT),
            'cut' => strlen($text) > self::TEXT,
        ]);
    }

    /**
     * Where the dump was written: the first file of the trace that is not
     * a package's — `dump()`, `dd()`, and the `->dump()` of a collection
     * all live under `vendor/` — as a path of the project.
     *
     * @param  list<array<string, mixed>>  $trace
     * @return array{0: string|null, 1: int|null}
     */
    protected function source(array $trace): array
    {
        $vendor = DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR;

        foreach ($trace as $frame) {
            $file = $frame['file'] ?? null;

            if (! is_string($file) || $file === __FILE__ || str_contains($file, $vendor)) {
                continue;
            }

            $line = is_int($frame['line'] ?? null) ? $frame['line'] : null;
            $view = $this->view($file);

            // A compiled view does not keep the lines of the template.
            return $view !== null ? [$this->relative($view), null] : [$this->relative($file), $line];
        }

        return [null, null];
    }

    /**
     * The Blade template a compiled view was made from: Laravel writes its
     * path at the end of the file.
     */
    protected function view(string $file): ?string
    {
        $compiled = config('view.compiled');

        if (! is_string($compiled) || $compiled === '' || ! str_starts_with($file, rtrim($compiled, '/\\').DIRECTORY_SEPARATOR)) {
            return null;
        }

        $size = (int) @filesize($file);
        $tail = @file_get_contents($file, false, null, max(0, $size - 1024));

        return is_string($tail) && preg_match('/\/\*\*PATH (.+?) ENDPATH\*\*\//', $tail, $match) === 1 ? $match[1] : null;
    }

    protected function relative(string $file): string
    {
        $file = str_replace('\\', '/', $file);
        $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';

        return str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }
}
