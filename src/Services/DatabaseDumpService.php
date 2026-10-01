<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Writes the application's database as one SQL file: the structure, the
 * rows, and what has to come after them, in the dialect of the driver it
 * was read from, so the file goes back into the same kind of database.
 *
 * MySQL, MariaDB, and SQLite give their own CREATE statements; PostgreSQL
 * is rebuilt from its catalogs the way pg_dump does it, constraints after
 * the rows; SQL Server and any other driver from Laravel's schema builder.
 * The file is written a piece at a time and read inside one snapshot, so
 * a large database neither fills the memory nor comes out half-updated.
 */
class DatabaseDumpService
{
    /** Rows in one INSERT statement. */
    protected const ROWS_PER_INSERT = 100;

    /** An INSERT statement is closed past this many bytes. */
    protected const BYTES_PER_INSERT = 1048576;

    /** Rows read from the database at a time. */
    protected const ROWS_PER_READ = 1000;

    protected Connection $connection;

    protected string $driver = '';

    /** @var callable(string): void */
    protected $write;

    public function __construct(protected DatabaseViewerService $viewer) {}

    /**
     * The name of the file: the database and the moment it was taken.
     */
    public function filename(): string
    {
        $database = $this->viewer->describe()['database'];
        $database = pathinfo($database, PATHINFO_FILENAME) ?: $this->viewer->connectionName();
        $database = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $database), '-.') ?: 'database';

        return $database.'-'.Carbon::now()->format('Y-m-d-His').'.sql';
    }

    /**
     * Write the dump through `$write`, a piece at a time. With
     * `$credentials` off, a column the viewer hides goes out as NULL, or
     * as an empty string when it cannot be NULL — and as a placeholder of
     * its own on each row when a unique index holds it, so the file still
     * goes back in.
     *
     * @param  callable(string): void  $write
     * @return array{tables: int, views: int, rows: int, hidden: list<string>}
     */
    public function dump(callable $write, bool $credentials = false): array
    {
        $this->write = $write;
        $this->connection = $this->viewer->connection();
        $this->driver = $this->connection->getDriverName();

        $objects = array_values(array_filter(
            $this->viewer->objects(),
            // SQLite: the main database, not what is attached to it.
            fn (array $object): bool => $this->driver !== 'sqlite' || in_array($object['schema'], [null, 'main'], true)
        ));
        $tables = array_values(array_filter($objects, static fn (array $object): bool => $object['kind'] === 'table'));
        $views = array_values(array_filter($objects, static fn (array $object): bool => $object['kind'] === 'view'));

        $plans = [];
        $hidden = [];

        foreach ($tables as $table) {
            $plans[$table['key']] = $plan = $this->plan($table, $credentials);

            foreach ($plan['hidden'] as $column) {
                $hidden[] = $table['key'].'.'.$column;
            }
        }

        $summary = ['tables' => count($tables), 'views' => count($views), 'rows' => 0, 'hidden' => $hidden];

        $this->header($summary, $credentials);

        $snapshot = $this->snapshot();

        try {
            $after = match ($this->driver) {
                'mysql', 'mariadb' => $this->mysqlStructure($tables, $views),
                'sqlite' => $this->sqliteStructure($tables, $views),
                'pgsql' => $this->pgsqlStructure($tables, $views),
                default => $this->genericStructure($tables, $views),
            };

            foreach ($tables as $table) {
                $summary['rows'] += $this->rows($table, $plans[$table['key']]);
            }

            $this->comment('After the rows');

            foreach ($after() as $statement) {
                $this->statement($statement);
            }

            $this->footer();
        } catch (Throwable $e) {
            $this->out("\n-- The dump stopped here, so this file is incomplete:\n-- ".str_replace("\n", "\n-- ", trim($e->getMessage()))."\n");
        } finally {
            if ($snapshot) {
                try {
                    $this->connection->rollBack();
                } catch (Throwable) {
                    // The snapshot only read; nothing is lost if it cannot be closed.
                }
            }
        }

        return $summary;
    }

    /**
     * What one table needs to have its rows written.
     *
     * @param  array<string, mixed>  $table
     * @return array{columns: list<array{name: string, nullable: bool, binary: bool, numeric: bool, hidden: bool, distinct: bool}>, order: list<string>, hidden: list<string>, identity: bool, overriding: bool}
     */
    protected function plan(array $table, bool $credentials): array
    {
        $schema = $this->connection->getSchemaBuilder();
        $columns = [];
        $hidden = [];
        $identity = false;
        $order = [];
        $unique = [];

        $uniqueOrder = [];

        foreach ($this->attempt(fn (): array => $schema->getIndexes($table['key'])) as $index) {
            $indexed = array_values(array_map('strval', (array) ($index['columns'] ?? [])));

            if (! empty($index['primary']) && $order === []) {
                $order = $indexed;
            }

            if (! empty($index['primary']) || ! empty($index['unique'])) {
                $unique = array_merge($unique, $indexed);
                $uniqueOrder = $uniqueOrder === [] ? $indexed : $uniqueOrder;
            }
        }

        // Pages are read with OFFSET: without an order the engine may hand
        // the same row twice and another never. A unique index orders as
        // well as a key; SQLite and Postgres have a row address to fall on.
        $order = $order !== [] ? $order : $uniqueOrder;

        if ($order === []) {
            $order = match ($this->driver) {
                'sqlite' => ['rowid'],
                'pgsql' => ['ctid'],
                default => [],
            };
        }

        foreach ($this->attempt(fn (): array => $schema->getColumns($table['key'])) as $column) {
            // A generated column is computed again on the way in.
            if (! empty($column['generation'])) {
                continue;
            }

            $name = (string) $column['name'];
            $hide = ! $credentials && $this->viewer->masked($name);
            $identity = $identity || ! empty($column['auto_increment']);

            if ($hide) {
                $hidden[] = $name;
            }

            $typeName = strtolower((string) ($column['type_name'] ?? ''));

            $columns[] = [
                'name' => $name,
                'nullable' => (bool) ($column['nullable'] ?? true),
                'binary' => (bool) preg_match('/blob|binary|bytea|^image$/', $typeName),
                'numeric' => (bool) preg_match('/int|dec|num|float|double|real|money|serial/', $typeName),
                'hidden' => $hide,
                // Two rows cannot share one value under a unique index.
                'distinct' => $hide && in_array($name, $unique, true),
            ];
        }

        return [
            'columns' => $columns,
            'order' => $order,
            'hidden' => $hidden,
            'identity' => $identity,
            'overriding' => $this->driver === 'pgsql' && $this->pgsqlAlwaysIdentity($table),
        ];
    }

    /**
     * @param  array<string, mixed>  $table
     * @param  array{columns: list<array{name: string, nullable: bool, binary: bool, numeric: bool, hidden: bool, distinct: bool}>, order: list<string>, hidden: list<string>, identity: bool, overriding: bool}  $plan
     */
    protected function rows(array $table, array $plan): int
    {
        if ($plan['columns'] === []) {
            return 0;
        }

        $target = $this->table($table);
        $names = implode(', ', array_map(fn (array $column): string => $this->quote($column['name']), $plan['columns']));
        $head = 'INSERT INTO '.$target.' ('.$names.')'.($plan['overriding'] ? ' OVERRIDING SYSTEM VALUE' : '').' VALUES';
        $identityInsert = $this->driver === 'sqlsrv' && $plan['identity'];

        $this->comment('Rows of '.$table['key']);

        if ($identityInsert) {
            $this->statement('SET IDENTITY_INSERT '.$target.' ON');
        }

        $written = 0;
        $batch = [];
        $bytes = 0;

        $flush = function (object|array $record) use ($plan, $head, &$written, &$batch, &$bytes): void {
            $record = (array) $record;
            $values = [];

            foreach ($plan['columns'] as $column) {
                $values[] = $column['hidden']
                    ? $this->hiddenLiteral($column, $written + 1)
                    : $this->literal($record[$column['name']] ?? null, $column['binary']);
            }

            $tuple = '('.implode(', ', $values).')';
            $batch[] = $tuple;
            $bytes += strlen($tuple);
            $written++;

            if (count($batch) >= self::ROWS_PER_INSERT || $bytes >= self::BYTES_PER_INSERT) {
                $this->statement($head."\n".implode(",\n", $batch));
                $batch = [];
                $bytes = 0;
            }
        };

        if ($plan['order'] === []) {
            // A heap with no key and no unique index (MySQL, SQL Server):
            // OFFSET pages have no stable order, so it is read in one pass.
            foreach ($this->connection->table($table['key'])->cursor() as $record) {
                $flush($record);
            }
        } else {
            $page = 1;

            do {
                $query = $this->connection->table($table['key']);

                foreach ($plan['order'] as $column) {
                    $query->orderBy($column);
                }

                $records = $query->forPage($page++, self::ROWS_PER_READ)->get()->all();

                foreach ($records as $record) {
                    $flush($record);
                }
            } while (count($records) === self::ROWS_PER_READ);
        }

        if ($batch !== []) {
            $this->statement($head."\n".implode(",\n", $batch));
        }

        if ($identityInsert) {
            $this->statement('SET IDENTITY_INSERT '.$target.' OFF');
        }

        return $written;
    }

    /**
     * What a hidden column holds in the dump: NULL, or an empty string when
     * it cannot be NULL. Under a unique index every row gets a value of its
     * own instead — an empty string twice would refuse the restore, and SQL
     * Server takes NULL only once.
     *
     * @param  array{name: string, nullable: bool, binary: bool, numeric: bool, hidden: bool, distinct: bool}  $column
     */
    protected function hiddenLiteral(array $column, int $row): string
    {
        if ($column['distinct'] && (! $column['nullable'] || $this->driver === 'sqlsrv')) {
            return $column['numeric'] ? (string) $row : $this->literal('hidden-'.$row, $column['binary']);
        }

        return $column['nullable'] ? 'NULL' : $this->literal('');
    }

    // ---- MySQL and MariaDB ----

    /**
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array<string, mixed>>  $views
     * @return callable(): list<string>
     */
    protected function mysqlStructure(array $tables, array $views): callable
    {
        foreach ($views as $view) {
            $this->statement('DROP VIEW IF EXISTS '.$this->table($view));
        }

        foreach ($tables as $table) {
            $this->comment('Table '.$table['key']);
            $this->statement('DROP TABLE IF EXISTS '.$this->table($table));
            $this->statement($this->secondColumn('SHOW CREATE TABLE '.$this->table($table)));
        }

        return fn (): array => array_map(
            // A view keeps the account that made it only where that account exists.
            fn (array $view): string => (string) preg_replace(
                '/\s+DEFINER\s*=\s*`(?:[^`]|``)*`@`(?:[^`]|``)*`/',
                '',
                $this->secondColumn('SHOW CREATE VIEW '.$this->table($view))
            ),
            $views
        );
    }

    protected function secondColumn(string $sql): string
    {
        $row = array_values((array) ($this->connection->selectOne($sql) ?? []));

        return (string) ($row[1] ?? '');
    }

    // ---- SQLite ----

    /**
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array<string, mixed>>  $views
     * @return callable(): list<string>
     */
    protected function sqliteStructure(array $tables, array $views): callable
    {
        $tableNames = array_column($tables, 'raw');
        $viewNames = array_column($views, 'raw');
        $master = $this->connection->select(
            "select type, name, tbl_name, sql from sqlite_master where sql is not null and name not like 'sqlite\\_%' escape '\\' "
            ."order by case type when 'table' then 0 when 'index' then 1 when 'trigger' then 2 else 3 end, rowid"
        );

        $later = [];

        foreach ($views as $view) {
            $this->statement('DROP VIEW IF EXISTS '.$this->table($view));
        }

        foreach ($master as $entry) {
            $entry = (array) $entry;
            $type = (string) $entry['type'];

            if ($type === 'table' && in_array($entry['name'], $tableNames, true)) {
                $this->comment('Table '.$entry['name']);
                $this->statement('DROP TABLE IF EXISTS '.$this->quote((string) $entry['name']));
                $this->statement((string) $entry['sql']);
            } elseif (($type === 'index' || $type === 'trigger') && in_array($entry['tbl_name'], $tableNames, true)) {
                $later[] = (string) $entry['sql'];
            } elseif ($type === 'view' && in_array($entry['name'], $viewNames, true)) {
                $later[] = (string) $entry['sql'];
            }
        }

        return function () use ($tableNames, $later): array {
            // The next AUTOINCREMENT number of each table.
            $sequence = $this->connection->select("select name from sqlite_master where type = 'table' and name = 'sqlite_sequence'") !== []
                ? array_values(array_filter(
                    array_map(static fn (object $row): array => (array) $row, $this->connection->select('select name, seq from sqlite_sequence')),
                    static fn (array $row): bool => in_array($row['name'], $tableNames, true)
                ))
                : [];

            if ($sequence === []) {
                return $later;
            }

            return [
                'DELETE FROM "sqlite_sequence" WHERE "name" IN ('.implode(', ', array_map(fn (array $row): string => $this->literal($row['name']), $sequence)).')',
                'INSERT INTO "sqlite_sequence" ("name", "seq") VALUES '.implode(', ', array_map(fn (array $row): string => '('.$this->literal($row['name']).', '.$this->literal($row['seq']).')', $sequence)),
                ...$later,
            ];
        };
    }

    // ---- PostgreSQL ----

    /**
     * Tables without their constraints, the rows, then the primary keys,
     * the unique and check constraints, the indexes, the foreign keys, the
     * sequence values, and the views — the order pg_dump writes in, so
     * the rows load whatever order the tables come in.
     *
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array<string, mixed>>  $views
     * @return callable(): list<string>
     */
    protected function pgsqlStructure(array $tables, array $views): callable
    {
        $version = (int) $this->connection->scalar('show server_version_num');

        foreach ($views as $view) {
            $this->statement('DROP VIEW IF EXISTS '.$this->table($view).' CASCADE');
        }

        foreach ($tables as $table) {
            $this->statement('DROP TABLE IF EXISTS '.$this->table($table).' CASCADE');
        }

        $enums = array_map(static fn (object $row): array => (array) $row, $this->connection->select(
            "select n.nspname as schema, t.typname as name, string_agg(quote_literal(e.enumlabel), ', ' order by e.enumsortorder) as labels "
            .'from pg_type t join pg_enum e on e.enumtypid = t.oid join pg_namespace n on n.oid = t.typnamespace '
            ."where n.nspname <> 'information_schema' and n.nspname not like 'pg\\_%' group by n.nspname, t.typname order by 1, 2"
        ));

        $schemas = array_unique(array_merge(
            array_map(static fn (array $object): string => (string) $object['schema'], array_merge($tables, $views)),
            array_map(static fn (array $enum): string => (string) $enum['schema'], $enums)
        ));

        foreach ($schemas as $schema) {
            if ($schema !== '' && $schema !== 'public') {
                $this->statement('CREATE SCHEMA IF NOT EXISTS '.$this->quote($schema));
            }
        }

        foreach ($enums as $enum) {
            $type = $this->quote((string) $enum['schema'], (string) $enum['name']);
            $this->statement('DROP TYPE IF EXISTS '.$type.' CASCADE');
            $this->statement('CREATE TYPE '.$type.' AS ENUM ('.$enum['labels'].')');
        }

        $constraints = [];
        $indexes = [];
        $foreign = [];
        $sequences = [];

        foreach ($tables as $table) {
            [$owner, $name] = [(string) $table['schema'], (string) $table['raw']];
            $qualified = $this->table($table);

            $this->comment('Table '.$table['key']);

            $owned = array_map(static fn (object $row): array => (array) $row, $this->connection->select(
                'select sn.nspname as schema, s.relname as name, a.attname as "column", d.deptype as kind from pg_depend d '
                ."join pg_class s on s.oid = d.objid and s.relkind = 'S' join pg_namespace sn on sn.oid = s.relnamespace "
                .'join pg_class t on t.oid = d.refobjid join pg_namespace tn on tn.oid = t.relnamespace '
                .'join pg_attribute a on a.attrelid = t.oid and a.attnum = d.refobjsubid '
                ."where d.classid = 'pg_class'::regclass and d.refclassid = 'pg_class'::regclass and d.deptype in ('a', 'i') "
                .'and tn.nspname = ? and t.relname = ?',
                [$owner, $name]
            ));

            // A serial column's default names its sequence: it has to exist first.
            foreach ($owned as $sequence) {
                if ($sequence['kind'] === 'a') {
                    $this->statement('CREATE SEQUENCE IF NOT EXISTS '.$this->quote((string) $sequence['schema'], (string) $sequence['name']));
                }
            }

            $columns = $this->connection->select(
                'select a.attname as name, format_type(a.atttypid, a.atttypmod) as type, a.attnotnull as notnull, '
                .'pg_get_expr(d.adbin, d.adrelid) as expression, '
                .($version >= 100000 ? 'a.attidentity' : "''").' as identity, '
                .($version >= 120000 ? 'a.attgenerated' : "''").' as generated '
                .'from pg_attribute a join pg_class c on c.oid = a.attrelid join pg_namespace n on n.oid = c.relnamespace '
                .'left join pg_attrdef d on d.adrelid = a.attrelid and d.adnum = a.attnum '
                .'where n.nspname = ? and c.relname = ? and a.attnum > 0 and not a.attisdropped order by a.attnum',
                [$owner, $name]
            );

            $definitions = array_map(function (object $column): string {
                $column = (array) $column;
                $definition = $this->quote((string) $column['name']).' '.$column['type'];

                if ($column['identity'] === 'a' || $column['identity'] === 'd') {
                    $definition .= ' GENERATED '.($column['identity'] === 'a' ? 'ALWAYS' : 'BY DEFAULT').' AS IDENTITY';
                } elseif ($column['generated'] === 's') {
                    $definition .= ' GENERATED ALWAYS AS ('.$column['expression'].') STORED';
                } elseif ($column['expression'] !== null) {
                    $definition .= ' DEFAULT '.$column['expression'];
                }

                return $definition.($column['notnull'] ? ' NOT NULL' : '');
            }, $columns);

            $this->statement('CREATE TABLE '.$qualified." (\n    ".implode(",\n    ", $definitions)."\n)");

            foreach ($owned as $sequence) {
                if ($sequence['kind'] === 'a') {
                    $this->statement('ALTER SEQUENCE '.$this->quote((string) $sequence['schema'], (string) $sequence['name']).' OWNED BY '.$this->quote($owner, $name, (string) $sequence['column']));
                }

                $sequences[] = [$this->quote((string) $sequence['schema'], (string) $sequence['name']), $qualified, (string) $sequence['column']];
            }

            foreach ($this->connection->select(
                'select con.conname as name, con.contype as type, pg_get_constraintdef(con.oid) as definition from pg_constraint con '
                .'join pg_class t on t.oid = con.conrelid join pg_namespace n on n.oid = t.relnamespace '
                ."where n.nspname = ? and t.relname = ? and con.contype in ('p', 'u', 'c', 'x', 'f') "
                ."order by case con.contype when 'p' then 0 when 'u' then 1 when 'c' then 2 when 'x' then 3 else 4 end, con.conname",
                [$owner, $name]
            ) as $constraint) {
                $constraint = (array) $constraint;
                $sql = 'ALTER TABLE ONLY '.$qualified.' ADD CONSTRAINT '.$this->quote((string) $constraint['name']).' '.$constraint['definition'];

                if ($constraint['type'] === 'f') {
                    $foreign[] = $sql;
                } else {
                    $constraints[] = $sql;
                }
            }

            foreach ($this->connection->select(
                'select pg_get_indexdef(ix.indexrelid) as definition from pg_index ix '
                .'join pg_class t on t.oid = ix.indrelid join pg_namespace n on n.oid = t.relnamespace '
                .'where n.nspname = ? and t.relname = ? and not exists '
                ."(select 1 from pg_constraint con where con.conindid = ix.indexrelid and con.conrelid = ix.indrelid and con.contype in ('p', 'u', 'x'))",
                [$owner, $name]
            ) as $index) {
                $indexes[] = (string) ((array) $index)['definition'];
            }

            $comment = $table['comment'] ?? null;

            if (is_string($comment) && $comment !== '') {
                $constraints[] = 'COMMENT ON TABLE '.$qualified.' IS '.$this->literal($comment);
            }
        }

        return function () use ($views, $constraints, $indexes, $foreign, $sequences): array {
            $values = [];

            foreach ($sequences as [$sequence, $table, $column]) {
                $state = (array) ($this->connection->selectOne('select last_value, is_called from '.$sequence) ?? []);

                if (isset($state['last_value'])) {
                    $values[] = 'SELECT pg_catalog.setval(pg_get_serial_sequence('.$this->literal($table).', '.$this->literal($column).'), '
                        .(int) $state['last_value'].', '.(! empty($state['is_called']) ? 'true' : 'false').')';
                }
            }

            $definitions = [];

            foreach ($views as $view) {
                $definition = (string) $this->connection->scalar(
                    'select pg_get_viewdef(c.oid, true) from pg_class c join pg_namespace n on n.oid = c.relnamespace where n.nspname = ? and c.relname = ?',
                    [(string) $view['schema'], (string) $view['raw']]
                );
                $definitions[] = 'CREATE OR REPLACE VIEW '.$this->table($view).' AS '.rtrim(trim($definition), ';');
            }

            return [...$constraints, ...$indexes, ...$foreign, ...$values, ...$definitions];
        };
    }

    /**
     * @param  array<string, mixed>  $table
     */
    protected function pgsqlAlwaysIdentity(array $table): bool
    {
        try {
            return (bool) $this->connection->scalar(
                'select exists (select 1 from pg_attribute a join pg_class c on c.oid = a.attrelid join pg_namespace n on n.oid = c.relnamespace '
                ."where n.nspname = ? and c.relname = ? and a.attidentity = 'a')",
                [(string) $table['schema'], (string) $table['raw']]
            );
        } catch (Throwable) {
            return false;
        }
    }

    // ---- SQL Server and any other driver ----

    /**
     * Rebuilt from what Laravel's schema builder reports: columns, the
     * primary key, then indexes and foreign keys after the rows.
     *
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array<string, mixed>>  $views
     * @return callable(): list<string>
     */
    protected function genericStructure(array $tables, array $views): callable
    {
        $schema = $this->connection->getSchemaBuilder();
        $later = [];
        $foreign = [];
        $raw = [];

        foreach ($tables as $table) {
            $raw[$table['schema'].'.'.$table['raw']] = $table;
        }

        foreach ($tables as $table) {
            $this->comment('Table '.$table['key']);
            $definitions = [];

            foreach ($this->attempt(fn (): array => $schema->getColumns($table['key'])) as $column) {
                $name = $this->quote((string) $column['name']);
                $generation = $column['generation'] ?? null;

                if (is_array($generation) && ! empty($generation['expression'])) {
                    $definitions[] = $name.' AS ('.$generation['expression'].')'.(($generation['type'] ?? '') === 'stored' ? ' PERSISTED' : '');

                    continue;
                }

                $type = (string) ($column['type'] ?? $column['type_name'] ?? '');

                // SQL Server reports the length of a national type in bytes.
                if ($this->driver === 'sqlsrv' && preg_match('/^(n(?:var)?char)\((\d+)\)$/', $type, $match) === 1) {
                    $type = $match[1].'('.max(1, intdiv((int) $match[2], 2)).')';
                }

                $definition = $name.' '.$type;

                if (! empty($column['auto_increment']) && $this->driver === 'sqlsrv') {
                    $definition .= ' IDENTITY(1,1)';
                }

                $definition .= empty($column['nullable']) ? ' NOT NULL' : ' NULL';

                if (isset($column['default']) && is_scalar($column['default']) && (string) $column['default'] !== '') {
                    $definition .= ' DEFAULT '.$column['default'];
                }

                $definitions[] = $definition;
            }

            foreach ($this->attempt(fn (): array => $schema->getIndexes($table['key'])) as $index) {
                $columns = implode(', ', array_map(fn (mixed $column): string => $this->quote((string) $column), (array) ($index['columns'] ?? [])));

                if ($columns === '') {
                    continue;
                }

                if (! empty($index['primary'])) {
                    $definitions[] = 'PRIMARY KEY ('.$columns.')';
                } else {
                    $later[] = 'CREATE '.(! empty($index['unique']) ? 'UNIQUE ' : '').'INDEX '.$this->quote((string) $index['name']).' ON '.$this->table($table).' ('.$columns.')';
                }
            }

            foreach ($this->attempt(fn (): array => $schema->getForeignKeys($table['key'])) as $key) {
                $target = $raw[($key['foreign_schema'] ?? $table['schema']).'.'.($key['foreign_table'] ?? '')] ?? null;
                $references = $target !== null
                    ? $this->table($target)
                    : $this->quote(...array_values(array_filter([(string) ($key['foreign_schema'] ?? ''), (string) ($key['foreign_table'] ?? '')], static fn (string $part): bool => $part !== '')));

                $foreign[] = 'ALTER TABLE '.$this->table($table).' ADD '
                    .(! empty($key['name']) ? 'CONSTRAINT '.$this->quote((string) $key['name']).' ' : '')
                    .'FOREIGN KEY ('.implode(', ', array_map(fn (mixed $column): string => $this->quote((string) $column), (array) $key['columns'])).') '
                    .'REFERENCES '.$references.' ('.implode(', ', array_map(fn (mixed $column): string => $this->quote((string) $column), (array) $key['foreign_columns'])).')'
                    .(! empty($key['on_delete']) ? ' ON DELETE '.strtoupper((string) $key['on_delete']) : '')
                    .(! empty($key['on_update']) ? ' ON UPDATE '.strtoupper((string) $key['on_update']) : '');
            }

            $this->statement('CREATE TABLE '.$this->table($table)." (\n    ".implode(",\n    ", $definitions)."\n)");
        }

        return function () use ($views, $later, $foreign): array {
            $definitions = [];
            $listed = $this->attempt(fn (): array => $this->connection->getSchemaBuilder()->getViews());

            foreach ($views as $view) {
                foreach ($listed as $row) {
                    if (($row['name'] ?? null) === $view['raw'] && (($row['schema'] ?? null) === $view['schema'] || $view['schema'] === null)) {
                        $definition = trim((string) ($row['definition'] ?? ''));
                        $definitions[] = preg_match('/^create\s/i', $definition) === 1 ? $definition : 'CREATE VIEW '.$this->table($view).' AS '.$definition;
                    }
                }
            }

            return [...$later, ...$foreign, ...$definitions];
        };
    }

    // ---- writing ----

    /**
     * @param  array{tables: int, views: int, rows: int, hidden: list<string>}  $summary
     */
    protected function header(array $summary, bool $credentials): void
    {
        $connection = $this->viewer->describe();
        $where = $connection['database'].($connection['host'] !== null ? ' @ '.$connection['host'] : '');
        $restore = match ($this->driver) {
            'mysql', 'mariadb' => 'mysql DATABASE < FILE.sql',
            'pgsql' => 'psql -d DATABASE -f FILE.sql',
            'sqlite' => 'sqlite3 database.sqlite < FILE.sql',
            'sqlsrv' => 'sqlcmd -d DATABASE -i FILE.sql',
            default => null,
        };

        $lines = [
            'Larapilot database dump',
            'Driver:      '.$connection['driver_label'],
            'Database:    '.$where.' (connection '.$connection['name'].')',
            'Made:        '.Carbon::now()->utc()->format('Y-m-d H:i:s').' UTC',
            'Contents:    '.$summary['tables'].' '.($summary['tables'] === 1 ? 'table' : 'tables').', '.$summary['views'].' '.($summary['views'] === 1 ? 'view' : 'views').', every row',
        ];

        if ($credentials) {
            $lines[] = 'Credentials: included — passwords, tokens, and secrets are in this file. Keep it private.';
        } elseif ($summary['hidden'] === []) {
            $lines[] = 'Credentials: no column holds any.';
        } else {
            $lines[] = 'Credentials: left out — written as NULL, or as an empty string where NULL is not allowed';
            $lines[] = '             (as hidden-N, one for each row, where the column is unique):';

            foreach ($summary['hidden'] as $column) {
                $lines[] = '               '.$column;
            }
        }

        $lines[] = '';
        $lines[] = in_array($this->driver, ['mysql', 'mariadb', 'sqlite', 'pgsql'], true)
            ? 'Each table and view is dropped first if it exists. Triggers'.($this->driver === 'sqlite' ? ' are kept; functions' : ', functions,').' and grants are not part of the dump.'
            : 'Restore into an empty database. Triggers, functions, and grants are not part of the dump.';

        if ($restore !== null) {
            $lines[] = 'Restore:     '.$restore;
        }

        $this->out('-- '.implode("\n-- ", $lines)."\n\n");

        match ($this->driver) {
            'mysql', 'mariadb' => $this->out(
                "SET NAMES utf8mb4;\n"
                ."SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS = 0;\n"
                ."SET @OLD_UNIQUE_CHECKS = @@UNIQUE_CHECKS, UNIQUE_CHECKS = 0;\n"
                ."SET @OLD_SQL_MODE = @@SQL_MODE, SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n"
            ),
            'sqlite' => $this->out("PRAGMA foreign_keys = OFF;\nBEGIN TRANSACTION;\n"),
            'pgsql' => $this->out(
                "SET client_encoding = 'UTF8';\n"
                ."SET standard_conforming_strings = on;\n"
                ."SET check_function_bodies = false;\n"
                ."SET client_min_messages = warning;\n"
            ),
            default => null,
        };
    }

    protected function footer(): void
    {
        match ($this->driver) {
            'mysql', 'mariadb' => $this->out(
                "\nSET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;\n"
                ."SET UNIQUE_CHECKS = @OLD_UNIQUE_CHECKS;\n"
                ."SET SQL_MODE = @OLD_SQL_MODE;\n"
            ),
            'sqlite' => $this->out("\nCOMMIT;\nPRAGMA foreign_keys = ON;\n"),
            default => null,
        };

        $this->out("\n-- Dump complete.\n");
    }

    /**
     * One read-only snapshot for the whole file, where the driver gives
     * one: the rows of the last table are as old as those of the first.
     */
    protected function snapshot(): bool
    {
        if (! in_array($this->driver, ['mysql', 'mariadb', 'pgsql', 'sqlite'], true)) {
            return false;
        }

        try {
            $this->connection->beginTransaction();

            if ($this->driver === 'pgsql') {
                $this->connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
                // With no search path the catalogs name every table, type, and
                // sequence with its schema, so the file restores under any path.
                $this->connection->statement("SELECT pg_catalog.set_config('search_path', '', true)");
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    protected function statement(string $sql): void
    {
        $sql = rtrim(trim($sql), ';');

        if ($sql === '') {
            return;
        }

        // SQL Server tools run a script in batches: a view must open one.
        $this->out($sql.";\n".($this->driver === 'sqlsrv' ? "GO\n" : ''));
    }

    protected function comment(string $text): void
    {
        $this->out("\n-- ".str_replace("\n", ' ', $text)."\n");
    }

    protected function out(string $chunk): void
    {
        ($this->write)($chunk);
    }

    /**
     * A value as a literal of the driver.
     */
    protected function literal(mixed $value, bool $binary = false): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_resource($value)) {
            $value = (string) stream_get_contents($value);
        }

        if (is_bool($value)) {
            return $this->driver === 'pgsql' ? ($value ? 'TRUE' : 'FALSE') : ($value ? '1' : '0');
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return is_finite($value) ? var_export($value, true) : $this->literal(is_nan($value) ? 'NaN' : ($value > 0 ? 'Infinity' : '-Infinity'));
        }

        $string = is_scalar($value) ? (string) $value : (string) json_encode($value);

        if ($binary || ! mb_check_encoding($string, 'UTF-8')) {
            return $this->bytes($string);
        }

        // SQLite reads a quoted string up to its first NUL byte.
        if ($this->driver === 'sqlite' && str_contains($string, "\0")) {
            return 'CAST('.$this->bytes($string).' AS TEXT)';
        }

        $quoted = $this->connection->getPdo()->quote($string);

        if (! is_string($quoted)) {
            $quoted = "'".str_replace("'", "''", in_array($this->driver, ['mysql', 'mariadb'], true) ? str_replace('\\', '\\\\', $string) : $string)."'";
        }

        // pdo_sqlsrv already answers `N'…'` under UTF-8; a second N would not parse.
        return $this->driver === 'sqlsrv' && ! str_starts_with($quoted, 'N\'') ? 'N'.$quoted : $quoted;
    }

    protected function bytes(string $bytes): string
    {
        $hex = strtoupper(bin2hex($bytes));

        return match ($this->driver) {
            'pgsql' => "decode('".$hex."', 'hex')",
            'sqlite' => "X'".$hex."'",
            default => $hex === '' ? "''" : '0x'.$hex,
        };
    }

    /**
     * The table as the dump names it: with its schema where the driver
     * keeps more than one, never with the name of the database.
     *
     * @param  array<string, mixed>  $object
     */
    protected function table(array $object): string
    {
        $schema = is_string($object['schema'] ?? null) ? $object['schema'] : null;

        return in_array($this->driver, ['pgsql', 'sqlsrv'], true) && $schema !== null
            ? $this->quote($schema, (string) $object['raw'])
            : $this->quote((string) $object['raw']);
    }

    protected function quote(string ...$parts): string
    {
        return implode('.', array_map(fn (string $part): string => match ($this->driver) {
            'mysql', 'mariadb' => '`'.str_replace('`', '``', $part).'`',
            'sqlsrv' => '['.str_replace(']', ']]', $part).']',
            default => '"'.str_replace('"', '""', $part).'"',
        }, $parts));
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
