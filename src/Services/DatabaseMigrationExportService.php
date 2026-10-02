<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Writes the structure of the database as Laravel migrations: a file for
 * each table, in an order where a table comes after the ones it points
 * at, one for the foreign keys that close a circle, and one for the views.
 *
 * A column is written with the Blueprint method that makes its type, read
 * from what Laravel's schema builder reports, so one code path serves
 * every driver. What Blueprint has no word for — a type of one database
 * only, an index on an expression — is written as the nearest thing, with
 * a comment on the line above that says what it was. The `migrations`
 * table is left out: Laravel makes it itself.
 */
class DatabaseMigrationExportService
{
    /** The actions a foreign key takes when nothing is written. */
    protected const NO_ACTION = ['', 'no action'];

    public function __construct(
        protected DatabaseViewerService $viewer,
        protected DatabaseDumpService $dumps,
        protected DatabaseMigrationService $migrations,
    ) {}

    public function filename(): string
    {
        return $this->viewer->fileName().'-migrations-'.Carbon::now()->format('Y-m-d-His').'.zip';
    }

    /**
     * The migration files, by the path they have in a Laravel project.
     *
     * @return array<string, string>
     */
    public function files(): array
    {
        $tables = $this->tables();
        $driver = $this->viewer->connection()->getDriverName();
        $prefix = $this->viewer->connection()->getTablePrefix();
        $enums = $this->enums($driver);
        $day = Carbon::now()->format('Y_m_d');
        $number = 0;
        $path = static function (string $name) use ($day, &$number): string {
            return 'database/migrations/'.$day.'_'.str_pad((string) ++$number, 6, '0', STR_PAD_LEFT).'_'.$name.'.php';
        };

        // Only MySQL has unsigned integers, and the keys Laravel makes
        // there are unsigned: elsewhere a column that points at a key is
        // written as the key is, for the day the migrations run on MySQL.
        $keys = [];

        foreach (in_array($driver, ['mysql', 'mariadb'], true) ? [] : $tables as $table) {
            foreach ($table['columns'] as $column) {
                $method = $this->type($column, $driver, $enums)['method'];

                if (str_ends_with($method, 'ncrements')) {
                    $keys[$table['key']][(string) $column['name']] = 'unsigned'.ucfirst(str_replace('ncrements', 'nteger', $method));
                }
            }
        }

        $files = [];
        $deferred = [];

        foreach ($tables as $table) {
            $pointing = [];

            foreach ($table['foreign'] as $foreign) {
                if ($foreign['target'] !== null && count($foreign['columns']) === 1 && count($foreign['references']) === 1 && isset($keys[$foreign['target']][$foreign['references'][0]])) {
                    $pointing[$foreign['columns'][0]] = $keys[$foreign['target']][$foreign['references'][0]];
                }
            }

            $lines = $this->columns($table, $driver, $enums, $pointing);
            $names = array_map(static fn (array $column): string => (string) $column['name'], $table['columns']);

            foreach ($table['indexes'] as $index) {
                // MySQL makes an index for a foreign key, under the name of the key: Laravel will again.
                $implied = in_array($driver, ['mysql', 'mariadb'], true) && empty($index['unique']) && array_filter(
                    $table['foreign'],
                    static fn (array $foreign): bool => $foreign['name'] === ($index['name'] ?? null) && $foreign['columns'] === array_values((array) ($index['columns'] ?? []))
                ) !== [];

                if (! $implied) {
                    array_push($lines, ...$this->index($index, $table, $names, $prefix));
                }
            }

            foreach ($table['foreign'] as $foreign) {
                if ($foreign['inline']) {
                    $lines[] = '$table'.$this->foreign($foreign, $table['name'], $prefix).';';
                } else {
                    $deferred[$table['name']][] = $foreign;
                }
            }

            $comment = $table['object']['comment'] ?? null;

            if (is_string($comment) && $comment !== '') {
                $lines[] = '$table->comment('.$this->php($comment).');';
            }

            $files[$path('create_'.$this->slug($table['name']).'_table')] = $this->migration(
                '`'.$table['name'].'`, as it was in the database on '.Carbon::now()->format('Y-m-d').'.',
                ['Schema::create('.$this->php($table['name']).', function (Blueprint $table) {', ...array_map(static fn (string $line): string => '    '.$line, $lines), '});'],
                ['Schema::dropIfExists('.$this->php($table['name']).');'],
                str_contains(implode("\n", $lines), 'DB::raw(')
            );
        }

        if ($deferred !== []) {
            $up = [];
            $down = [];

            foreach ($deferred as $name => $keysOfTable) {
                $up[] = 'Schema::table('.$this->php($name).', function (Blueprint $table) {';
                $down[] = 'Schema::table('.$this->php($name).', function (Blueprint $table) {';

                foreach ($keysOfTable as $foreign) {
                    $up[] = '    $table'.$this->foreign($foreign, $name, $prefix).';';
                    // By its columns where Laravel named it: SQLite drops a key no other way.
                    $down[] = '    $table->dropForeign('.$this->php($this->foreignName($foreign, $name, $prefix) ?? $foreign['columns']).');';
                }

                $up[] = '});';
                $down[] = '});';
            }

            $files[$path('add_foreign_keys')] = $this->migration(
                'The foreign keys that could not be made with their table: they point at a table that comes after it.',
                $up,
                $down
            );
        }

        $views = $this->views();

        if ($views !== []) {
            $up = [];
            $down = [];

            foreach ($views as $view) {
                $up[] = 'DB::unprepared('.$this->php($view['sql']).');';
                array_unshift($down, 'DB::unprepared('.$this->php('DROP VIEW IF EXISTS '.$view['name']).');');
            }

            $files[$path('create_views')] = $this->migration(
                'The views, in the SQL of '.$this->viewer->describe()['driver_label'].': a view has no Blueprint.',
                $up,
                $down,
                true,
                false
            );
        }

        return $files;
    }

    /**
     * The tables with what the schema builder says of each, in an order
     * where a table comes after the ones its foreign keys point at. A key
     * is `inline` when its table can be made with it: the table it points
     * at is the same one, or comes before.
     *
     * @return list<array{key: string, name: string, object: array<string, mixed>, columns: list<array<string, mixed>>, indexes: list<array<string, mixed>>, foreign: list<array{name: string|null, columns: list<string>, target: string|null, on: string, references: list<string>, on_delete: string, on_update: string, inline: bool}>}>
     */
    public function tables(): array
    {
        $connection = $this->viewer->connection();
        $schema = $connection->getSchemaBuilder();
        $prefix = $connection->getTablePrefix();
        $current = $this->currentSchema();
        $repository = $this->migrations->table();

        $tables = [];
        $byRaw = [];

        foreach ($this->dumps->tables() as $object) {
            if ($object['name'] === $repository) {
                continue;
            }

            $key = (string) $object['key'];
            $byRaw[$object['schema'].'.'.$object['raw']] = $key;
            $byRaw['.'.$object['raw']] ??= $key;

            $tables[$key] = [
                'key' => $key,
                'name' => $object['schema'] === null || $object['schema'] === $current ? (string) $object['name'] : $key,
                'object' => $object,
                'columns' => $this->attempt(fn (): array => $schema->getColumns($key)),
                'indexes' => $this->attempt(fn (): array => $schema->getIndexes($key)),
                'foreign' => $this->attempt(fn (): array => $schema->getForeignKeys($key)),
            ];
        }

        foreach ($tables as $key => $table) {
            $tables[$key]['foreign'] = array_map(function (array $foreign) use ($byRaw, $tables, $prefix): array {
                $raw = (string) ($foreign['foreign_table'] ?? '');
                $owner = is_string($foreign['foreign_schema'] ?? null) ? $foreign['foreign_schema'] : '';
                $target = $byRaw[$owner.'.'.$raw] ?? $byRaw['.'.$raw] ?? null;

                return [
                    'name' => is_string($foreign['name'] ?? null) && $foreign['name'] !== '' ? $foreign['name'] : null,
                    'columns' => array_values(array_map('strval', (array) ($foreign['columns'] ?? []))),
                    'target' => $target,
                    // A table this file does not make is named as the database names it, less the prefix Laravel adds.
                    'on' => $target !== null ? $tables[$target]['name'] : ($prefix !== '' && str_starts_with($raw, $prefix) ? substr($raw, strlen($prefix)) : $raw),
                    'references' => array_values(array_map('strval', (array) ($foreign['foreign_columns'] ?? []))),
                    'on_delete' => strtolower((string) ($foreign['on_delete'] ?? '')),
                    'on_update' => strtolower((string) ($foreign['on_update'] ?? '')),
                    'inline' => false,
                ];
            }, $table['foreign']);
        }

        $ordered = [];
        $remaining = array_keys($tables);

        while ($remaining !== []) {
            $next = null;

            foreach ($remaining as $key) {
                $waits = array_filter(
                    array_column($tables[$key]['foreign'], 'target'),
                    static fn (?string $target): bool => $target !== null && $target !== $key && ! isset($ordered[$target])
                );

                if ($waits === []) {
                    $next = $key;
                    break;
                }
            }

            // A circle: one of its tables goes first, and its keys wait.
            $next ??= $remaining[0];

            foreach ($tables[$next]['foreign'] as $index => $foreign) {
                $tables[$next]['foreign'][$index]['inline'] = $foreign['target'] === $next || isset($ordered[(string) $foreign['target']]);
            }

            $ordered[$next] = $tables[$next];
            $remaining = array_values(array_diff($remaining, [$next]));
        }

        return array_values($ordered);
    }

    /**
     * The views with the statement that creates each, in the SQL of the driver.
     *
     * @return list<array{name: string, sql: string}>
     */
    protected function views(): array
    {
        $definitions = $this->dumps->viewDefinitions();
        $grammar = $this->viewer->connection()->getQueryGrammar();
        $views = [];

        foreach ($this->dumps->views() as $view) {
            $sql = rtrim(trim($definitions[$view['key']] ?? ''), ';');

            if ($sql !== '') {
                $views[] = ['name' => $grammar->wrapTable((string) $view['key']), 'sql' => $sql];
            }
        }

        return $views;
    }

    // ---- columns ----

    /**
     * The lines of the columns, with the shorthands Laravel has for the
     * ones every table carries: `id()`, `timestamps()`, `softDeletes()`,
     * `rememberToken()`.
     *
     * @param  array{columns: list<array<string, mixed>>}  $table
     * @param  array<string, list<string>>  $enums
     * @param  array<string, string>  $pointing  the method of a column that points at a key, by its name
     * @return list<string>
     */
    protected function columns(array $table, string $driver, array $enums, array $pointing): array
    {
        $built = [];

        foreach ($table['columns'] as $column) {
            $name = (string) $column['name'];
            $type = $this->type($column, $driver, $enums);

            if ($type['kind'] === 'integer' && isset($pointing[$name]) && empty($column['auto_increment'])) {
                $type['method'] = $pointing[$name];
            }

            $serial = str_ends_with(strtolower($type['method']), 'increments') || in_array('autoIncrement()', $type['modifiers'], true);
            $generation = is_array($column['generation'] ?? null) ? $column['generation'] : null;
            $nullable = ! $serial && ! empty($column['nullable']);
            $default = $serial || $generation !== null ? null : $this->defaultModifier($column, $type['kind'], $driver);
            $comment = is_string($column['comment'] ?? null) && $column['comment'] !== '' ? $column['comment'] : null;
            $modifiers = $type['modifiers'];

            if ($generation !== null && is_string($generation['expression'] ?? null) && $generation['expression'] !== '') {
                $modifiers[] = (($generation['type'] ?? '') === 'virtual' ? 'virtualAs' : 'storedAs').'('.$this->php($generation['expression']).')';
            }

            if ($nullable) {
                $modifiers[] = 'nullable()';
            }

            if ($default !== null) {
                $modifiers[] = $default;
            }

            if ($comment !== null) {
                $modifiers[] = 'comment('.$this->php($comment).')';
            }

            $built[] = [
                'name' => $name,
                'method' => $type['method'],
                'arguments' => $type['arguments'],
                'modifiers' => $modifiers,
                'note' => $type['note'],
                // Nothing but "may be empty": what a shorthand of Laravel makes.
                'plain' => $nullable && $modifiers === ['nullable()'],
                'stamp' => in_array($type['method'], $driver === 'sqlite' ? ['timestamp', 'dateTime'] : ['timestamp'], true),
            ];
        }

        $lines = [];

        for ($index = 0; $index < count($built); $index++) {
            $column = $built[$index];
            $next = $built[$index + 1] ?? null;
            $precision = $column['arguments'] === [] ? '' : $this->php($column['arguments'][0]);

            if ($column['method'] === 'bigIncrements' && $column['name'] === 'id' && $column['modifiers'] === []) {
                $lines[] = '$table->id();';
            } elseif ($column['name'] === 'created_at' && $next !== null && $next['name'] === 'updated_at' && $column['stamp'] && $next['stamp'] && $column['plain'] && $next['plain'] && $column['arguments'] === $next['arguments']) {
                $lines[] = '$table->timestamps('.$precision.');';
                $index++;
            } elseif ($column['name'] === 'deleted_at' && $column['stamp'] && $column['plain']) {
                $lines[] = '$table->softDeletes('.($precision === '' ? '' : "'deleted_at', ".$precision).');';
            } elseif ($column['name'] === 'remember_token' && $column['method'] === 'string' && $column['plain'] && in_array($column['arguments'], $driver === 'sqlite' ? [[], [100]] : [[100]], true)) {
                $lines[] = '$table->rememberToken();';
            } else {
                if ($column['note'] !== null) {
                    $lines[] = '// '.$column['note'];
                }

                $lines[] = '$table->'.$column['method'].'('.implode(', ', array_map(fn (mixed $value): string => $this->php($value), [$column['name'], ...$column['arguments']])).')'
                    .implode('', array_map(static fn (string $modifier): string => '->'.$modifier, $column['modifiers'])).';';
            }
        }

        return $lines;
    }

    /**
     * The Blueprint method that makes the type of a column, with its
     * arguments, and what kind of value the column holds.
     *
     * @param  array<string, mixed>  $column
     * @param  array<string, list<string>>  $enums
     * @return array{method: string, arguments: list<mixed>, modifiers: list<string>, kind: string, note: string|null}
     */
    protected function type(array $column, string $driver, array $enums): array
    {
        $written = trim((string) ($column['type'] ?? $column['type_name'] ?? ''));
        $full = strtolower($written);
        $base = strtolower(trim((string) ($column['type_name'] ?? '')));
        $base = $base !== '' ? $base : trim((string) preg_replace('/\(.*$/s', '', $full));
        $unsigned = str_contains($full, 'unsigned');
        $arguments = preg_match('/\(([^()]*)\)/', $full, $match) === 1 ? array_map('trim', explode(',', $match[1])) : [];
        $length = isset($arguments[0]) && ctype_digit($arguments[0]) ? (int) $arguments[0] : null;
        $serial = ! empty($column['auto_increment']);

        // SQL Server reports the length of a national type in bytes.
        if ($driver === 'sqlsrv' && $length !== null && in_array($base, ['nchar', 'nvarchar'], true)) {
            $length = max(1, intdiv($length, 2));
        }

        $make = static fn (string $method, string $kind, array $arguments = [], array $modifiers = [], ?string $note = null): array => [
            'method' => $method,
            'arguments' => array_values($arguments),
            'modifiers' => array_values($modifiers),
            'kind' => $kind,
            'note' => $note,
        ];

        $size = match (true) {
            in_array($base, ['bigint', 'int8', 'bigserial'], true) => 'big',
            in_array($base, ['int', 'integer', 'int4', 'serial'], true) => '',
            $base === 'mediumint' => 'medium',
            in_array($base, ['smallint', 'int2', 'smallserial'], true) => 'small',
            // tinyint(1) is how MySQL and SQLite write a boolean.
            $base === 'tinyint' && ($length !== 1 || $driver === 'sqlsrv') => 'tiny',
            default => null,
        };

        if ($size !== null) {
            // SQLite has one integer, of 64 bits.
            $size = $driver === 'sqlite' && $size === '' ? 'big' : $size;
            $integer = lcfirst($size.'Integer');

            if (! $serial) {
                return $make($unsigned ? 'unsigned'.ucfirst($integer) : $integer, 'integer');
            }

            // Only MySQL has a signed key that counts by itself; `increments` is unsigned there.
            return $unsigned || ! in_array($driver, ['mysql', 'mariadb'], true)
                ? $make(lcfirst($size.'Increments'), 'integer')
                : $make($integer, 'integer', [], ['autoIncrement()']);
        }

        // Without a precision PostgreSQL keeps the microseconds; Laravel writes 0 unless told.
        $precision = $length ?? ($driver === 'pgsql' ? 6 : 0);
        $precision = $precision === 0 ? [] : [$precision];
        $scale = array_values(array_map('intval', array_filter($arguments, 'ctype_digit')));
        $sign = $unsigned ? ['unsigned()'] : [];

        return match (true) {
            in_array($base, ['bool', 'boolean'], true),
            $base === 'tinyint',
            $base === 'bit' && ($driver === 'sqlsrv' || $length === null || $length === 1) => $make('boolean', 'boolean'),

            in_array($base, ['decimal', 'numeric', 'dec'], true) => $make('decimal', 'decimal', count($scale) === 2 ? $scale : [], $sign),
            $base === 'money' => $make('decimal', 'decimal', [19, 4]),
            $base === 'smallmoney' => $make('decimal', 'decimal', [10, 4]),
            in_array($base, ['float', 'float4', 'real'], true) => $make('float', 'float', [], $sign),
            in_array($base, ['double', 'float8', 'double precision'], true) => $make('double', 'float', [], $sign),

            in_array($base, ['char', 'bpchar', 'character', 'nchar'], true) => $make('char', 'string', $length === null ? [] : [$length]),
            in_array($base, ['varchar', 'nvarchar', 'character varying', 'varchar2', 'string'], true) => in_array('max', $arguments, true)
                ? $make('text', 'string')
                : $make('string', 'string', $length === null || $length === 255 ? [] : [$length]),
            $base === 'tinytext' => $make('tinyText', 'string'),
            in_array($base, ['text', 'ntext', 'clob'], true) => $make('text', 'string'),
            $base === 'mediumtext' => $make('mediumText', 'string'),
            $base === 'longtext' => $make('longText', 'string'),
            $base === 'json' => $make('json', 'string'),
            $base === 'jsonb' => $make('jsonb', 'string'),

            $base === 'date' => $make('date', 'time'),
            in_array($base, ['datetime', 'datetime2', 'smalldatetime'], true) => $make('dateTime', 'time', $precision),
            $base === 'datetimeoffset' => $make('dateTimeTz', 'time', $precision),
            // SQL Server calls `timestamp` a row version, which no migration writes.
            $base === 'timestamp' && $driver !== 'sqlsrv' => $make('timestamp', 'time', $precision),
            $base === 'timestamptz' => $make('timestampTz', 'time', $precision),
            $base === 'time' => $make('time', 'time', $precision),
            $base === 'timetz' => $make('timeTz', 'time', $precision),
            $base === 'year' => $make('year', 'integer'),

            // A length is read from Laravel 11 on; before, the column is a blob all the same.
            in_array($base, ['binary', 'varbinary'], true) && $length !== null && $driver !== 'sqlite' => $make('binary', 'binary', [$length, $base === 'binary']),
            in_array($base, ['binary', 'varbinary', 'blob', 'tinyblob', 'mediumblob', 'longblob', 'bytea', 'image'], true) => $make('binary', 'binary'),

            in_array($base, ['uuid', 'uniqueidentifier'], true) => $make('uuid', 'string'),
            $base === 'inet' => $make('ipAddress', 'string'),
            in_array($base, ['macaddr', 'macaddr8'], true) => $make('macAddress', 'string'),
            in_array($base, ['enum', 'set'], true) => $make($base, 'string', [$this->options($written)]),
            isset($enums[$base]) => $make('enum', 'string', [$enums[$base]], [], 'The type `'.$base.'` of PostgreSQL: Laravel writes an enum as a string with a check.'),
            in_array($base, ['geometry', 'geography', 'point', 'linestring', 'polygon', 'multipoint', 'multilinestring', 'multipolygon', 'geometrycollection', 'geomcollection'], true) => $make('geometry', 'other', [], [], $base === 'geometry' ? null : '`'.$written.'` in the database.'),

            // SQLite takes any word as a type, and none at all.
            $base === '' => $make('text', 'string'),
            default => $make('text', 'other', [], [], '`'.$written.'` in the database: Blueprint has no column of this type, so it is written as text.'),
        };
    }

    /**
     * The values of a MySQL `enum('a','b')` or `set('a','b')`.
     *
     * @return list<string>
     */
    protected function options(string $type): array
    {
        preg_match_all("/'((?:[^'\\\\]|''|\\\\.)*)'/s", (string) substr($type, (int) strpos($type, '(')), $matches);

        return array_map(
            static fn (string $value): string => stripslashes(str_replace("''", "'", $value)),
            $matches[1]
        );
    }

    /**
     * The default of a column as a modifier. A driver reports it the way
     * it wrote it — `'draft'::character varying`, `('draft')`, `draft` —
     * so the value is read back out of that.
     *
     * @param  array<string, mixed>  $column
     */
    protected function defaultModifier(array $column, string $kind, string $driver): ?string
    {
        $default = $column['default'] ?? null;

        if (! is_scalar($default)) {
            return null;
        }

        $raw = trim((string) $default);

        // SQL Server wraps a default in brackets, a number in two pairs.
        while ($driver === 'sqlsrv' && preg_match('/^\((.*)\)$/s', $raw, $match) === 1) {
            $raw = trim($match[1]);
        }

        if ($raw === '' || preg_match('/^null(::.+)?$/i', $raw) === 1) {
            return null;
        }

        // A sequence is what makes the column count by itself.
        if (stripos($raw, 'nextval(') === 0) {
            return null;
        }

        $value = $raw;
        $quoted = false;

        if (preg_match("/^N?'((?:[^']|'')*)'(?:::[\\w .\"\\[\\]]+)?$/s", $raw, $match) === 1) {
            $value = str_replace("''", "'", $match[1]);
            $value = in_array($driver, ['mysql', 'mariadb'], true) ? stripslashes($value) : $value;
            $quoted = true;
        }

        if (! $quoted && preg_match('/^(current_timestamp(\(\d*\))?|now\(\)|localtimestamp(\(\d*\))?|getdate\(\)|sysdatetime\(\))$/i', $raw) === 1) {
            return $kind === 'time' ? 'useCurrent()' : 'default(DB::raw('.$this->php($raw).'))';
        }

        if ($kind === 'boolean') {
            return match (strtolower(trim($value, "b'"))) {
                '1', 'true', 't' => 'default(true)',
                '0', 'false', 'f' => 'default(false)',
                default => 'default(DB::raw('.$this->php($raw).'))',
            };
        }

        if (in_array($kind, ['integer', 'float', 'decimal'], true) && is_numeric($value)) {
            return match (true) {
                $kind === 'integer' && preg_match('/^-?\d+$/', $value) === 1 => 'default('.$value.')',
                // A decimal as it is written: 0.10 is not the float 0.1.
                $kind === 'decimal' => 'default('.$this->php($value).')',
                default => 'default('.$this->php((float) $value).')',
            };
        }

        // MySQL reports a text default bare; anywhere else, what is not quoted is an expression.
        if ($quoted || (in_array($driver, ['mysql', 'mariadb'], true) && preg_match('/^[a-z_]+\(.*\)$/is', $raw) !== 1)) {
            return 'default('.$this->php($value).')';
        }

        return 'default(DB::raw('.$this->php($raw).'))';
    }

    // ---- indexes and foreign keys ----

    /**
     * @param  array<string, mixed>  $index
     * @param  array{name: string, columns: list<array<string, mixed>>}  $table
     * @param  list<string>  $columns
     * @return list<string>
     */
    protected function index(array $index, array $table, array $columns, string $prefix): array
    {
        $on = array_values(array_map('strval', (array) ($index['columns'] ?? [])));
        $name = (string) ($index['name'] ?? '');
        $kind = strtolower((string) ($index['type'] ?? ''));

        if ($on === [] || array_diff($on, $columns) !== []) {
            return ['// The index `'.$name.'` is not on plain columns: it is left to be written by hand.'];
        }

        if (! empty($index['primary'])) {
            // A column that counts by itself is the key already.
            return count($on) === 1 && $this->counts($on[0], $table) ? [] : ['$table->primary('.$this->php(count($on) === 1 ? $on[0] : $on).');'];
        }

        $method = match (true) {
            ! empty($index['unique']) => 'unique',
            $kind === 'fulltext' => 'fullText',
            $kind === 'spatial' => 'spatialIndex',
            default => 'index',
        };

        $algorithm = $method === 'index' && in_array($kind, ['hash', 'gin', 'gist', 'spgist', 'brin'], true) ? $kind : null;
        // The name Laravel gives when none is written is not written.
        $named = $algorithm !== null || ! ($this->conventional($name, $prefix, $table['name'], $on, $method) || str_starts_with($name, 'sqlite_autoindex'));

        return ['$table->'.$method.'('.implode(', ', array_filter([
            $this->php(count($on) === 1 ? $on[0] : $on),
            $named ? $this->php($name) : null,
            $algorithm !== null ? $this->php($algorithm) : null,
        ])).');'];
    }

    /**
     * Whether the column is written as one that counts by itself.
     *
     * @param  array{columns: list<array<string, mixed>>}  $table
     */
    protected function counts(string $column, array $table): bool
    {
        foreach ($table['columns'] as $described) {
            if (($described['name'] ?? null) === $column) {
                return ! empty($described['auto_increment']);
            }
        }

        return false;
    }

    /**
     * @param  array{name: string|null, columns: list<string>, on: string, references: list<string>, on_delete: string, on_update: string}  $foreign
     */
    protected function foreign(array $foreign, string $table, string $prefix): string
    {
        $name = $this->foreignName($foreign, $table, $prefix);
        $driver = $this->viewer->connection()->getDriverName();
        // InnoDB makes no difference between the two, and MariaDB reports the one nobody wrote.
        $silent = in_array($driver, ['mysql', 'mariadb'], true) ? [...self::NO_ACTION, 'restrict'] : self::NO_ACTION;

        return '->foreign('.$this->php(count($foreign['columns']) === 1 ? $foreign['columns'][0] : $foreign['columns']).($name !== null ? ', '.$this->php($name) : '').')'
            .'->references('.$this->php(count($foreign['references']) === 1 ? $foreign['references'][0] : $foreign['references']).')'
            .'->on('.$this->php($foreign['on']).')'
            .(in_array($foreign['on_delete'], $silent, true) ? '' : '->onDelete('.$this->php($foreign['on_delete']).')')
            .(in_array($foreign['on_update'], $silent, true) ? '' : '->onUpdate('.$this->php($foreign['on_update']).')');
    }

    /**
     * The name of a foreign key, when it is not the one Laravel gives by itself.
     *
     * @param  array{name: string|null, columns: list<string>}  $foreign
     */
    protected function foreignName(array $foreign, string $table, string $prefix): ?string
    {
        return $foreign['name'] === null || $this->conventional($foreign['name'], $prefix, $table, $foreign['columns'], 'foreign') ? null : $foreign['name'];
    }

    /**
     * Whether a name is the one Blueprint gives an index or a key when
     * none is written — with the prefix of the tables in front, as
     * Laravel wrote it up to version 11 and with `prefix_indexes` since,
     * or without it.
     *
     * @param  list<string>  $columns
     */
    protected function conventional(string $name, string $prefix, string $table, array $columns, string $method): bool
    {
        $given = str_replace(['-', '.'], '_', strtolower($table.'_'.implode('_', $columns).'_'.$method));

        return in_array(strtolower($name), [$given, str_replace(['-', '.'], '_', strtolower($prefix)).$given], true);
    }

    // ---- writing ----

    /**
     * @param  list<string>  $up
     * @param  list<string>  $down
     */
    protected function migration(string $about, array $up, array $down, bool $database = false, bool $schema = true): string
    {
        $body = static fn (array $lines): string => implode("\n", array_map(static fn (string $line): string => '        '.$line, $lines));
        $uses = array_filter([
            'use Illuminate\Database\Migrations\Migration;',
            $schema ? 'use Illuminate\Database\Schema\Blueprint;' : null,
            $database ? 'use Illuminate\Support\Facades\DB;' : null,
            $schema ? 'use Illuminate\Support\Facades\Schema;' : null,
        ]);

        return "<?php\n\n".implode("\n", $uses)."\n\n"
            ."return new class extends Migration\n{\n"
            ."    /**\n     * ".$about."\n     */\n"
            ."    public function up(): void\n    {\n".$body($up)."\n    }\n\n"
            ."    public function down(): void\n    {\n".$body($down)."\n    }\n};\n";
    }

    /**
     * A value as PHP writes it.
     */
    protected function php(mixed $value): string
    {
        if (is_array($value)) {
            return '['.implode(', ', array_map(fn (mixed $item): string => $this->php($item), $value)).']';
        }

        if (is_string($value)) {
            return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
        }

        return is_bool($value) ? ($value ? 'true' : 'false') : var_export($value, true);
    }

    protected function slug(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), '_') ?: 'table';
    }

    /**
     * The enum types of PostgreSQL, by name: a column of one is written
     * with its values.
     *
     * @return array<string, list<string>>
     */
    protected function enums(string $driver): array
    {
        if ($driver !== 'pgsql') {
            return [];
        }

        $enums = [];

        foreach ($this->attempt(fn (): array => $this->viewer->connection()->select(
            'select t.typname as name, e.enumlabel as label from pg_type t join pg_enum e on e.enumtypid = t.oid order by t.typname, e.enumsortorder'
        )) as $row) {
            $enums[strtolower((string) $row['name'])][] = (string) $row['label'];
        }

        return $enums;
    }

    /**
     * The schema a name with no schema is looked for in, where the driver
     * keeps more than one: a table of that schema is named without it.
     */
    protected function currentSchema(): ?string
    {
        $connection = $this->viewer->connection();

        try {
            $schema = match ($connection->getDriverName()) {
                'pgsql' => $connection->scalar('select current_schema()'),
                'sqlsrv' => $connection->scalar('select schema_name()'),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }

        return is_string($schema) && $schema !== '' ? $schema : null;
    }

    /**
     * @param  callable(): array<int, mixed>  $read
     * @return list<array<string, mixed>>
     */
    protected function attempt(callable $read): array
    {
        try {
            return array_values(array_map(static fn (mixed $row): array => (array) $row, $read()));
        } catch (Throwable) {
            return [];
        }
    }
}
