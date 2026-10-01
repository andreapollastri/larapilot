<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Throwable;

/**
 * Reads the application's own database for the dashboard: the tables and
 * views of the connection the app uses, their structure, and their rows a
 * page at a time. Everything goes through Laravel's schema builder and
 * query builder, so one code path serves MySQL, MariaDB, PostgreSQL,
 * SQLite, and SQL Server alike. It only ever reads.
 */
class DatabaseViewerService
{
    public const MASK = FileManagerService::MASK;

    /** How much of a value a cell of the grid shows. */
    protected const CELL_CHARS = 120;

    /** How much of a value the row panel shows. */
    protected const VALUE_CHARS = 20000;

    /** A value longer than this is not offered as a filter link. */
    protected const LINK_CHARS = 200;

    protected const DRIVERS = [
        'mysql' => 'MySQL',
        'mariadb' => 'MariaDB',
        'pgsql' => 'PostgreSQL',
        'sqlite' => 'SQLite',
        'sqlsrv' => 'SQL Server',
    ];

    /** @var array<string, array<string, array{key: string, name: string, raw: string, schema: string|null, kind: string, size: int|null, comment: string|null}>> */
    protected array $objects = [];

    public function __construct(protected DatabaseManager $databases) {}

    /**
     * The connection the viewer reads: `database_viewer.connection` when it
     * is set, otherwise the application's default — `DB_CONNECTION`.
     */
    public function connectionName(): string
    {
        $configured = config('larapilot.database_viewer.connection');

        return is_string($configured) && $configured !== '' ? $configured : (string) config('database.default');
    }

    /**
     * Where the connection points, from its configuration — never its
     * password or its URL, which can carry one.
     *
     * @return array{name: string, driver: string, driver_label: string, database: string, host: string|null}
     */
    public function describe(): array
    {
        $name = $this->connectionName();
        $config = config('database.connections.'.$name);

        try {
            $config = $this->connection()->getConfig();
        } catch (Throwable) {
            // An unknown driver fails when the connection is made: describe
            // what the configuration says and let the page report the error.
        }

        $config = is_array($config) ? $config : [];
        $driver = is_string($config['driver'] ?? null) ? $config['driver'] : '';
        $database = is_scalar($config['database'] ?? null) ? (string) $config['database'] : '';

        if ($driver === 'sqlite') {
            $base = rtrim(base_path(), '/\\').DIRECTORY_SEPARATOR;
            $database = str_starts_with($database, $base) ? substr($database, strlen($base)) : $database;
        }

        return [
            'name' => $name,
            'driver' => $driver,
            'driver_label' => self::DRIVERS[$driver] ?? ($driver === '' ? 'Unknown' : ucfirst($driver)),
            'database' => $database,
            'host' => $driver === 'sqlite' ? null : $this->host($config),
        ];
    }

    /**
     * Every table and view, with the totals the page leads with.
     *
     * @return array{connection: array{name: string, driver: string, driver_label: string, database: string, host: string|null}, objects: list<array{key: string, name: string, raw: string, schema: string|null, kind: string, size: int|null, comment: string|null, size_label: string|null}>, tables: int, views: int, size_label: string|null, schemas: bool, error: string|null}
     */
    public function overview(): array
    {
        $connection = $this->describe();
        $result = [
            'connection' => $connection,
            'objects' => [],
            'tables' => 0,
            'views' => 0,
            'size_label' => null,
            'schemas' => false,
            'error' => null,
        ];

        try {
            $objects = array_values($this->objects());
        } catch (Throwable $e) {
            $result['error'] = $this->failure($e);

            return $result;
        }

        $bytes = null;

        foreach ($objects as $object) {
            if ($object['size'] !== null) {
                $bytes = ($bytes ?? 0) + $object['size'];
            }
        }

        // SQLite reports no size per table; the file is the database.
        if ($bytes === null && $connection['driver'] === 'sqlite') {
            $file = (string) config('database.connections.'.$connection['name'].'.database');
            $bytes = is_file($file) ? (int) filesize($file) : null;
        }

        $result['objects'] = array_map(fn (array $object): array => $object + [
            'size_label' => $object['size'] === null ? null : $this->formatBytes($object['size']),
        ], $objects);
        $result['tables'] = count(array_filter($objects, static fn (array $object): bool => $object['kind'] === 'table'));
        $result['views'] = count($objects) - $result['tables'];
        $result['size_label'] = $bytes === null ? null : $this->formatBytes($bytes);
        $result['schemas'] = count(array_unique(array_map(static fn (array $object): string => (string) $object['schema'], $objects))) > 1;

        return $result;
    }

    /**
     * One table or view: its structure and one page of its rows. Null when
     * the connection holds nothing by that name.
     *
     * @param  array{page?: int, sort?: string|null, direction?: string|null, search?: string|null, where?: string|null, is?: string|null}  $options
     * @return array<string, mixed>|null
     */
    public function table(string $key, array $options = []): ?array
    {
        $overview = $this->overview();

        if ($overview['error'] !== null) {
            return $overview + ['object' => null];
        }

        $object = null;

        foreach ($overview['objects'] as $candidate) {
            if ($candidate['key'] === $key) {
                $object = $candidate;
                break;
            }
        }

        if ($object === null) {
            return null;
        }

        $connection = $this->connection();
        $schema = $connection->getSchemaBuilder();
        $isTable = $object['kind'] === 'table';

        $indexes = $isTable ? $this->attempt(fn (): array => $schema->getIndexes($key)) : [];
        $foreignKeys = $isTable ? $this->attempt(fn (): array => $schema->getForeignKeys($key)) : [];
        $primary = [];

        foreach ($indexes as $index) {
            if (! empty($index['primary'])) {
                $primary = array_values(array_map('strval', (array) $index['columns']));
                break;
            }
        }

        $references = $this->references($foreignKeys, $overview['objects']);
        $columns = [];

        foreach ($this->attempt(fn (): array => $schema->getColumns($key)) as $column) {
            $name = (string) $column['name'];
            $typeName = strtolower((string) ($column['type_name'] ?? ''));

            $columns[$name] = [
                'name' => $name,
                'type' => (string) ($column['type'] ?? $typeName),
                'type_name' => $typeName,
                'nullable' => (bool) ($column['nullable'] ?? false),
                'default' => isset($column['default']) && is_scalar($column['default']) ? (string) $column['default'] : null,
                'auto_increment' => (bool) ($column['auto_increment'] ?? false),
                'comment' => isset($column['comment']) && is_string($column['comment']) && $column['comment'] !== '' ? $column['comment'] : null,
                'primary' => in_array($name, $primary, true),
                'masked' => $this->masked($name),
                'numeric' => (bool) preg_match('/int|dec|num|float|double|real|money|serial|bit/', $typeName),
                'searchable' => $this->searchable($connection->getDriverName(), $typeName),
                'reference' => $references[$name] ?? null,
            ];
        }

        $data = [
            'connection' => $overview['connection'],
            'objects' => $overview['objects'],
            'schemas' => $overview['schemas'],
            'object' => $object,
            'columns' => array_values($columns),
            'indexes' => array_map(static fn (array $index): array => [
                'name' => (string) ($index['name'] ?? ''),
                'columns' => array_values(array_map('strval', (array) ($index['columns'] ?? []))),
                'type' => is_string($index['type'] ?? null) ? $index['type'] : null,
                'unique' => (bool) ($index['unique'] ?? false),
                'primary' => (bool) ($index['primary'] ?? false),
            ], $indexes),
            'foreign_keys' => $this->foreignKeyRows($foreignKeys, $overview['objects']),
            'error' => null,
        ];

        return $data + $this->rows($connection, $key, $columns, $primary, $options);
    }

    /**
     * @param  array<string, array<string, mixed>>  $columns
     * @param  list<string>  $primary
     * @param  array{page?: int, sort?: string|null, direction?: string|null, search?: string|null, where?: string|null, is?: string|null}  $options
     * @return array<string, mixed>
     */
    protected function rows(Connection $connection, string $key, array $columns, array $primary, array $options): array
    {
        $perPage = max(1, min(500, (int) config('larapilot.database_viewer.per_page', 50)));
        $search = mb_substr(trim((string) ($options['search'] ?? '')), 0, 200);
        $sort = (string) ($options['sort'] ?? '');
        $sort = isset($columns[$sort]) && ! $columns[$sort]['masked'] ? $sort : null;
        $direction = strtolower((string) ($options['direction'] ?? '')) === 'desc' ? 'desc' : 'asc';
        $where = (string) ($options['where'] ?? '');
        $where = isset($columns[$where]) && ! $columns[$where]['masked'] && isset($options['is']) ? $where : null;
        $is = $where === null ? null : (string) $options['is'];

        // A masked column is never searched: a match would tell its value.
        $searchable = array_keys(array_filter($columns, static fn (array $column): bool => $column['searchable'] && ! $column['masked']));
        $operator = $connection->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $result = [
            'rows' => [],
            'total' => 0,
            'page' => 1,
            'last_page' => 1,
            'per_page' => $perPage,
            'from' => 0,
            'to' => 0,
            'sort' => $sort,
            'direction' => $direction,
            'search' => $search,
            'searchable' => $searchable !== [],
            'where' => $where,
            'is' => $is,
        ];

        $query = $connection->table($key);

        if ($where !== null) {
            $query->where($where, '=', $is);
        }

        if ($search !== '' && $searchable !== []) {
            // `%`, `_` are wildcards to LIKE: a search for `50%` means those
            // three characters. `!` escapes them on every driver here.
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $grammar = $connection->getQueryGrammar();

            $query->where(function (Builder $query) use ($searchable, $operator, $term, $grammar): void {
                foreach ($searchable as $column) {
                    $query->orWhereRaw($grammar->wrap($column).' '.$operator." ? escape '!'", [$term]);
                }
            });
        }

        try {
            $total = (int) (clone $query)->count();
            $lastPage = max(1, (int) ceil($total / $perPage));
            $page = max(1, min($lastPage, (int) ($options['page'] ?? 1)));

            if ($sort !== null) {
                $query->orderBy($sort, $direction);
            }

            // Primary key last, so a page holds the same rows each time it is opened.
            foreach ($primary as $column) {
                if ($column !== $sort) {
                    $query->orderBy($column);
                }
            }

            $records = $query->forPage($page, $perPage)->get()->all();
        } catch (Throwable $e) {
            return ['error' => $this->failure($e)] + $result;
        }

        $result['total'] = $total;
        $result['page'] = $page;
        $result['last_page'] = $lastPage;
        $result['from'] = $records === [] ? 0 : ($page - 1) * $perPage + 1;
        $result['to'] = $records === [] ? 0 : ($page - 1) * $perPage + count($records);
        $result['rows'] = array_map(fn (object $record): array => $this->row((array) $record, $columns), $records);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, array<string, mixed>>  $columns
     * @return list<array{column: string, kind: string, text: string, full: string, link: array{key: string, column: string, value: string}|null}>
     */
    protected function row(array $record, array $columns): array
    {
        $cells = [];

        foreach ($record as $name => $value) {
            $name = (string) $name;
            $column = $columns[$name] ?? null;
            $cell = $this->cell($value, $column !== null && $column['masked'], $column !== null && $column['numeric']);
            $reference = $column['reference'] ?? null;

            $cell['link'] = is_array($reference) && in_array($cell['kind'], ['text', 'number'], true) && mb_strlen($cell['full']) <= self::LINK_CHARS
                ? ['key' => $reference['key'], 'column' => $reference['column'], 'value' => $cell['full']]
                : null;

            $cells[] = ['column' => $name] + $cell;
        }

        return $cells;
    }

    /**
     * How one value is shown: its kind, the short text of the grid, and
     * the longer text of the row panel.
     *
     * @return array{kind: string, text: string, full: string}
     */
    public function cell(mixed $value, bool $masked = false, bool $numeric = false): array
    {
        if ($value === null) {
            return ['kind' => 'null', 'text' => 'NULL', 'full' => 'NULL'];
        }

        if ($masked) {
            return ['kind' => 'masked', 'text' => self::MASK, 'full' => self::MASK];
        }

        // PostgreSQL hands a bytea column over as a stream.
        if (is_resource($value)) {
            $value = (string) stream_get_contents($value);
        }

        if (is_bool($value)) {
            return ['kind' => 'bool', 'text' => $value ? 'true' : 'false', 'full' => $value ? 'true' : 'false'];
        }

        if (is_int($value) || is_float($value)) {
            return ['kind' => 'number', 'text' => (string) $value, 'full' => (string) $value];
        }

        if (! is_scalar($value)) {
            $value = (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }

        $string = (string) $value;

        if (! mb_check_encoding($string, 'UTF-8')) {
            $bytes = strlen($string);
            $hex = '0x'.strtoupper(bin2hex(substr($string, 0, 16))).($bytes > 16 ? '…' : '');

            return [
                'kind' => 'binary',
                'text' => $hex,
                'full' => '0x'.strtoupper(bin2hex(substr($string, 0, 4096))).($bytes > 4096 ? '…' : '')."\n\n".number_format($bytes).' bytes of binary data',
            ];
        }

        if ($numeric && is_numeric($string)) {
            return ['kind' => 'number', 'text' => $string, 'full' => $string];
        }

        $full = $this->pretty($string);

        if (mb_strlen($full) > self::VALUE_CHARS) {
            $full = mb_substr($full, 0, self::VALUE_CHARS)."…\n\n(".number_format(mb_strlen($string)).' characters in all)';
        }

        $line = trim((string) preg_replace('/\s+/u', ' ', $string));

        return [
            'kind' => 'text',
            'text' => mb_strlen($line) > self::CELL_CHARS ? mb_substr($line, 0, self::CELL_CHARS).'…' : $line,
            'full' => $full,
        ];
    }

    /**
     * Whether a column's values stay hidden: passwords, tokens, secrets,
     * and whatever `database_viewer.masked_columns` adds.
     */
    public function masked(string $column): bool
    {
        $patterns = config('larapilot.database_viewer.masked_columns', []);

        foreach (is_array($patterns) ? $patterns : [] as $pattern) {
            if (is_string($pattern) && $pattern !== '' && fnmatch(strtolower($pattern), strtolower($column))) {
                return true;
            }
        }

        return false;
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return max(0, $bytes).' B';
        }

        $value = $bytes / 1024;
        $unit = 'KB';

        foreach (['MB', 'GB', 'TB'] as $next) {
            if ($value < 1024) {
                break;
            }

            $value /= 1024;
            $unit = $next;
        }

        $label = number_format($value, $value < 10 ? 1 : 0, '.', ',');

        return (str_ends_with($label, '.0') ? substr($label, 0, -2) : $label).' '.$unit;
    }

    public function connection(): Connection
    {
        $connection = $this->databases->connection($this->connectionName());

        if (! $connection instanceof Connection) {
            throw new \RuntimeException('The connection "'.$this->connectionName().'" cannot be read.');
        }

        return $connection;
    }

    /**
     * The tables and views of the connection, keyed the way the query
     * builder addresses them: without the table prefix, and with the
     * schema in front where the driver keeps more than one.
     *
     * @return array<string, array{key: string, name: string, raw: string, schema: string|null, kind: string, size: int|null, comment: string|null}>
     */
    public function objects(): array
    {
        $name = $this->connectionName();

        if (isset($this->objects[$name])) {
            return $this->objects[$name];
        }

        $connection = $this->connection();
        $schema = $connection->getSchemaBuilder();
        $driver = $connection->getDriverName();
        $prefix = $connection->getTablePrefix();

        // MySQL lists every database the user can see unless it is told
        // which one: keep to the one in the configuration.
        $database = in_array($driver, ['mysql', 'mariadb'], true) ? $connection->getDatabaseName() : null;

        $listed = [];

        foreach ($database === null ? $schema->getTables() : $schema->getTables($database) as $table) {
            $listed[] = ['kind' => 'table'] + (array) $table;
        }

        foreach ($this->attempt(fn (): array => $database === null ? $schema->getViews() : $schema->getViews($database)) as $view) {
            $listed[] = ['kind' => 'view'] + (array) $view;
        }

        $objects = [];

        foreach ($listed as $row) {
            $raw = (string) ($row['name'] ?? '');
            $owner = isset($row['schema']) && is_string($row['schema']) && $row['schema'] !== '' ? $row['schema'] : null;

            if ($raw === '' || ($database !== null && $owner !== null && $owner !== $database)) {
                continue;
            }

            // The builder adds the prefix itself; a table outside it cannot be addressed.
            if ($prefix !== '') {
                if (! str_starts_with($raw, $prefix)) {
                    continue;
                }

                $logical = substr($raw, strlen($prefix));
            } else {
                $logical = $raw;
            }

            $qualified = $owner !== null && (in_array($driver, ['pgsql', 'sqlsrv'], true) || ($driver === 'sqlite' && $owner !== 'main'));
            $key = $qualified ? $owner.'.'.$logical : $logical;

            $objects[$key] = [
                'key' => $key,
                'name' => $logical,
                'raw' => $raw,
                'schema' => $owner,
                'kind' => (string) $row['kind'],
                'size' => isset($row['size']) && is_numeric($row['size']) ? (int) $row['size'] : null,
                'comment' => isset($row['comment']) && is_string($row['comment']) && $row['comment'] !== '' ? $row['comment'] : null,
            ];
        }

        uasort($objects, static fn (array $a, array $b): int => [(string) $a['schema'], strtolower($a['name'])] <=> [(string) $b['schema'], strtolower($b['name'])]);

        return $this->objects[$name] = $objects;
    }

    /**
     * Single-column foreign keys, by column: a value in that column opens
     * the row it points at.
     *
     * @param  list<array<string, mixed>>  $foreignKeys
     * @param  list<array<string, mixed>>  $objects
     * @return array<string, array{key: string, column: string}>
     */
    protected function references(array $foreignKeys, array $objects): array
    {
        $references = [];

        foreach ($foreignKeys as $foreignKey) {
            $columns = array_values((array) ($foreignKey['columns'] ?? []));
            $targets = array_values((array) ($foreignKey['foreign_columns'] ?? []));
            $key = $this->objectKey($foreignKey, $objects);

            if (count($columns) === 1 && count($targets) === 1 && $key !== null) {
                $references[(string) $columns[0]] = ['key' => $key, 'column' => (string) $targets[0]];
            }
        }

        return $references;
    }

    /**
     * @param  list<array<string, mixed>>  $foreignKeys
     * @param  list<array<string, mixed>>  $objects
     * @return list<array{name: string|null, columns: list<string>, table: string, key: string|null, foreign_columns: list<string>, on_update: string|null, on_delete: string|null}>
     */
    protected function foreignKeyRows(array $foreignKeys, array $objects): array
    {
        return array_map(fn (array $foreignKey): array => [
            'name' => is_string($foreignKey['name'] ?? null) && $foreignKey['name'] !== '' ? $foreignKey['name'] : null,
            'columns' => array_values(array_map('strval', (array) ($foreignKey['columns'] ?? []))),
            'table' => (string) ($foreignKey['foreign_table'] ?? ''),
            'key' => $this->objectKey($foreignKey, $objects),
            'foreign_columns' => array_values(array_map('strval', (array) ($foreignKey['foreign_columns'] ?? []))),
            'on_update' => is_string($foreignKey['on_update'] ?? null) ? strtolower($foreignKey['on_update']) : null,
            'on_delete' => is_string($foreignKey['on_delete'] ?? null) ? strtolower($foreignKey['on_delete']) : null,
        ], $foreignKeys);
    }

    /**
     * @param  array<string, mixed>  $foreignKey
     * @param  list<array<string, mixed>>  $objects
     */
    protected function objectKey(array $foreignKey, array $objects): ?string
    {
        $table = (string) ($foreignKey['foreign_table'] ?? '');
        $owner = is_string($foreignKey['foreign_schema'] ?? null) ? $foreignKey['foreign_schema'] : null;

        foreach ($objects as $object) {
            if ($object['kind'] === 'table' && $object['raw'] === $table && ($owner === null || $object['schema'] === null || $object['schema'] === $owner)) {
                return (string) $object['key'];
            }
        }

        return null;
    }

    /**
     * Whether a column can take part in the text search. PostgreSQL refuses
     * LIKE on anything but text; SQLite and MySQL compare any value as text.
     */
    protected function searchable(string $driver, string $typeName): bool
    {
        if (preg_match('/char|text|clob|string/', $typeName) === 1) {
            return true;
        }

        return $driver === 'sqlite' && ($typeName === '' || preg_match('/blob/', $typeName) !== 1);
    }

    /**
     * JSON is shown indented in the row panel.
     */
    protected function pretty(string $value): string
    {
        $trimmed = ltrim($value);

        if ($trimmed === '' || ! in_array($trimmed[0], ['{', '['], true)) {
            return $value;
        }

        $decoded = json_decode($value);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded) && ! is_object($decoded)) {
            return $value;
        }

        return (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function host(array $config): ?string
    {
        $host = $config['host'] ?? null;
        $host = is_array($host) ? implode(', ', array_filter($host, 'is_string')) : (is_scalar($host) ? (string) $host : '');

        if ($host === '') {
            return is_string($config['unix_socket'] ?? null) && $config['unix_socket'] !== '' ? $config['unix_socket'] : null;
        }

        $port = $config['port'] ?? null;

        return is_scalar($port) && (string) $port !== '' ? $host.':'.$port : $host;
    }

    /**
     * A schema question one driver cannot answer — indexes of a view, a
     * permission the user lacks — leaves that part empty, not the page.
     *
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

    protected function failure(Throwable $e): string
    {
        $message = trim($e->getMessage());

        return $message === '' ? class_basename($e).' while reading the database.' : $message;
    }
}
