<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Larapilot\Support\ZipStream;
use Throwable;

/**
 * Writes the rows of the database as Laravel seeders: a class for each
 * table that holds any, and one that calls them all in an order where a
 * table is filled after the ones it points at. The rows are read the way
 * the dump reads them — inside one snapshot, a page at a time, what the
 * viewer hides left out unless asked for — and written into the archive
 * as they come, so a large table fills neither the memory nor the disk.
 * The `migrations` table is left out: its rows are the migrations that
 * ran, which is not for a seeder to say.
 */
class DatabaseSeederExportService
{
    /** The class that calls every other. */
    public const ENTRY = 'DatabaseDataSeeder';

    protected const ROWS_PER_INSERT = 100;

    /** The most values one INSERT binds: SQL Server takes 2,100. */
    protected const VALUES_PER_INSERT = 2000;

    public function __construct(
        protected DatabaseViewerService $viewer,
        protected DatabaseDumpService $dumps,
        protected DatabaseMigrationExportService $structure,
    ) {}

    public function filename(): string
    {
        return $this->viewer->fileName().'-seeders-'.Carbon::now()->format('Y-m-d-His').'.zip';
    }

    /**
     * Write the seeders into `$zip`, by the path they have in a Laravel
     * project.
     *
     * @return array{tables: int, rows: int, hidden: list<string>, error: string|null}
     */
    public function write(ZipStream $zip, bool $credentials = false): array
    {
        $summary = ['tables' => 0, 'rows' => 0, 'hidden' => [], 'error' => null];
        $classes = [];

        try {
            $tables = [];
            $taken = [];

            foreach ($this->structure->tables() as $table) {
                $plan = $this->dumps->plan($table['object'], $credentials);

                if ($plan['columns'] === []) {
                    continue;
                }

                foreach ($plan['hidden'] as $column) {
                    $summary['hidden'][] = $table['name'].'.'.$column;
                }

                $tables[] = $table + ['plan' => $plan, 'class' => $this->className($table['name'], $taken)];
            }

            $this->dumps->within(function () use ($zip, $tables, &$summary, &$classes): void {
                foreach ($tables as $table) {
                    $records = $this->dumps->records($table['object'], $table['plan']);

                    // An empty table has nothing to seed.
                    if (! $records->valid()) {
                        continue;
                    }

                    $zip->begin('database/seeders/'.$table['class'].'.php');
                    $summary['rows'] += $this->seeder($zip, $table, $records);
                    $zip->end();

                    $classes[] = $table['class'];
                    $summary['tables']++;
                }
            });
        } catch (Throwable $e) {
            $summary['error'] = trim($e->getMessage()) ?: class_basename($e).' while reading the database.';

            $zip->end();
            $zip->add('database/seeders/INCOMPLETE.txt', "The export stopped before the end, so these seeders are incomplete — the last one may be cut:\n\n".$summary['error']."\n");
        }

        $zip->add('database/seeders/'.self::ENTRY.'.php', $this->entry($classes, $summary['hidden'], $credentials));

        return $summary;
    }

    /**
     * One seeder, written as its rows are read. Returns how many.
     *
     * @param  array{name: string, class: string, plan: array{columns: list<array{name: string, nullable: bool, binary: bool, numeric: bool, hidden: bool, distinct: bool, serial: bool}>, overriding: bool}}  $table
     * @param  iterable<int, array<string, mixed>>  $records
     */
    protected function seeder(ZipStream $zip, array $table, iterable $records): int
    {
        $plan = $table['plan'];
        $name = $this->php($table['name']);
        $serial = array_values(array_filter($plan['columns'], static fn (array $column): bool => $column['serial']));
        $perInsert = max(1, min(self::ROWS_PER_INSERT, intdiv(self::VALUES_PER_INSERT, count($plan['columns']))));

        $zip->append(
            "<?php\n\nnamespace Database\\Seeders;\n\nuse Illuminate\\Database\\Seeder;\nuse Illuminate\\Support\\Facades\\DB;\n\n"
            ."/**\n * The rows of `".$table['name'].'`, as they were in the database on '.Carbon::now()->format('Y-m-d').".\n */\n"
            .'class '.$table['class']." extends Seeder\n{\n    public function run(): void\n    {\n"
        );

        if ($plan['overriding']) {
            $zip->append("        // PostgreSQL made a column of this table `GENERATED ALWAYS`: it refuses a value for it\n        // unless the insert says `OVERRIDING SYSTEM VALUE`, which the query builder does not write.\n\n");
        }

        if ($serial !== []) {
            $zip->append(
                '        $table = DB::getQueryGrammar()->wrapTable('.$name.");\n\n"
                ."        // SQL Server takes a value for a column that counts by itself only when told.\n"
                ."        if (DB::getDriverName() === 'sqlsrv') {\n            DB::unprepared('SET IDENTITY_INSERT '.\$table.' ON');\n        }\n\n"
            );
        }

        $written = 0;
        $open = false;
        $bytes = false;

        foreach ($records as $record) {
            if ($written % $perInsert === 0) {
                $zip->append(($open ? "        ]);\n\n" : '').'        DB::table('.$name.")->insert([\n");
                $open = true;
            }

            $pairs = [];

            foreach ($plan['columns'] as $column) {
                $value = $column['hidden'] ? $this->dumps->hiddenValue($column, $written + 1) : ($record[$column['name']] ?? null);
                $pairs[] = $this->php($column['name']).' => '.$this->value($value, $column['binary'], $bytes);
            }

            $zip->append('            ['.implode(', ', $pairs)."],\n");
            $written++;
        }

        $zip->append("        ]);\n");

        if ($serial !== []) {
            $column = '"'.str_replace('"', '""', $serial[0]['name']).'"';

            $zip->append(
                "\n        if (DB::getDriverName() === 'sqlsrv') {\n            DB::unprepared('SET IDENTITY_INSERT '.\$table.' OFF');\n        }\n\n"
                ."        // PostgreSQL keeps the next number in a sequence, which the rows did not move.\n"
                ."        if (DB::getDriverName() === 'pgsql') {\n"
                .'            DB::select('.$this->php('select setval(pg_get_serial_sequence(?, ?), coalesce(max('.$column.'), 1), max('.$column.') is not null) from ').'.$table, [$table, '.$this->php($serial[0]['name'])."]);\n"
                ."        }\n"
            );
        }

        $zip->append("    }\n");

        if ($bytes) {
            $zip->append(
                "\n    /**\n     * Bytes, the way the driver takes them: PostgreSQL reads a string as text.\n     */\n"
                ."    protected function bytes(string \$encoded): mixed\n    {\n"
                ."        \$bytes = (string) base64_decode(\$encoded);\n\n"
                ."        if (DB::getDriverName() !== 'pgsql') {\n            return \$bytes;\n        }\n\n"
                ."        \$stream = fopen('php://memory', 'r+');\n        fwrite(\$stream, \$bytes);\n        rewind(\$stream);\n\n"
                ."        return \$stream;\n    }\n"
            );
        }

        $zip->append("}\n");

        return $written;
    }

    /**
     * @param  list<string>  $classes
     * @param  list<string>  $hidden
     */
    protected function entry(array $classes, array $hidden, bool $credentials): string
    {
        $connection = $this->viewer->describe();
        $about = [
            'Every row of `'.$connection['database'].'` ('.$connection['driver_label'].'), as it was on '.Carbon::now()->utc()->format('Y-m-d H:i').' UTC.',
            'The tables are expected to be there and empty, as after `php artisan migrate:fresh`:',
            '',
            '    php artisan db:seed --class='.self::ENTRY,
        ];

        if ($credentials) {
            array_push($about, '', 'Passwords, tokens, and secrets are in these files. Keep them private.');
        } elseif ($hidden !== []) {
            array_push($about, '', 'Passwords, tokens, and secrets were left out — written as null, or as nothing where', 'null is not allowed, and as hidden-N, one for each row, where the column is unique:', '', ...array_map(static fn (string $column): string => '    '.$column, $hidden));
        }

        $calls = implode('', array_map(static fn (string $class): string => '                    '.$class."::class,\n", $classes));
        $about = implode("\n", array_map(static fn (string $line): string => rtrim(' * '.$line), $about));
        $entry = self::ENTRY;

        return <<<PHP
        <?php

        namespace Database\Seeders;

        use Illuminate\Database\Seeder;
        use Illuminate\Support\Facades\DB;
        use Illuminate\Support\Facades\Schema;

        /**
        {$about}
         */
        class {$entry} extends Seeder
        {
            public function run(): void
            {
                // A table is filled after the ones it points at, and the check of the foreign keys
                // is off meanwhile, for the tables that point at each other or at themselves.
                \$fill = function (): void {
                    Schema::disableForeignKeyConstraints();

                    try {
                        \$this->call([
        {$calls}                ]);
                    } finally {
                        Schema::enableForeignKeyConstraints();
                    }
                };

                if (DB::getDriverName() !== 'pgsql') {
                    \$fill();

                    return;
                }

                // PostgreSQL turns the check off only for a key that may wait, inside a transaction:
                // the keys are let wait while the tables are filled, then put back as they were.
                \$keys = DB::select("select conrelid::regclass::text as on_table, quote_ident(conname) as name from pg_constraint where contype = 'f' and not condeferrable and connamespace = current_schema()::regnamespace");

                foreach (\$keys as \$key) {
                    DB::statement("alter table {\$key->on_table} alter constraint {\$key->name} deferrable");
                }

                try {
                    DB::transaction(\$fill);
                } finally {
                    foreach (\$keys as \$key) {
                        DB::statement("alter table {\$key->on_table} alter constraint {\$key->name} not deferrable");
                    }
                }
            }
        }

        PHP;
    }

    /**
     * A value as PHP writes it. Bytes go through base64, so the file
     * stays text whatever the column holds.
     */
    protected function value(mixed $value, bool $binary, bool &$bytes): string
    {
        // PostgreSQL hands a bytea column over as a stream.
        if (is_resource($value)) {
            $value = (string) stream_get_contents($value);
        }

        if ($value === null || is_bool($value) || is_int($value)) {
            return $this->php($value);
        }

        if (is_float($value)) {
            return is_finite($value) ? var_export($value, true) : $this->php(is_nan($value) ? 'NaN' : ($value > 0 ? 'Infinity' : '-Infinity'));
        }

        $string = is_scalar($value) ? (string) $value : (string) json_encode($value);

        if ($binary && $string !== '') {
            $bytes = true;

            return '$this->bytes('.$this->php(base64_encode($string)).')';
        }

        return mb_check_encoding($string, 'UTF-8') ? $this->php($string) : 'base64_decode('.$this->php(base64_encode($string)).')';
    }

    protected function php(mixed $value): string
    {
        if (! is_string($value)) {
            return is_bool($value) ? ($value ? 'true' : 'false') : ($value === null ? 'null' : var_export($value, true));
        }

        // A carriage return, or any other control character, written out:
        // an editor or git would change it, or not show it.
        if (preg_match('/[\x00-\x08\x0B-\x1F\x7F]/', $value) === 1) {
            return '"'.preg_replace_callback(
                '/[\x00-\x1F\x7F"$\\\\]/',
                static fn (array $match): string => match ($match[0]) {
                    "\n" => '\n',
                    "\r" => '\r',
                    "\t" => '\t',
                    '"', '$', '\\' => '\\'.$match[0],
                    default => sprintf('\x%02X', ord($match[0])),
                },
                $value
            ).'"';
        }

        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }

    /**
     * @param  array<string, true>  $taken
     */
    protected function className(string $table, array &$taken): string
    {
        $studly = Str::studly((string) preg_replace('/[^A-Za-z0-9]+/', '_', $table));
        $studly = $studly === '' || ctype_digit($studly[0]) ? 'Table'.$studly : $studly;
        $class = $studly.'TableSeeder';

        for ($number = 2; isset($taken[strtolower($class)]); $number++) {
            $class = $studly.$number.'TableSeeder';
        }

        $taken[strtolower($class)] = true;

        return $class;
    }
}
