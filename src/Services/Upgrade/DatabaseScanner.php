<?php

declare(strict_types=1);

namespace Larapilot\Services\Upgrade;

/**
 * What in the code depends on the database it runs on today, measured
 * against the one it is moving to: SQL written for MySQL on its way to
 * PostgreSQL, a word MySQL 8.0 reserved, a server option MySQL 8.4 removed.
 *
 * The query builder and the schema builder speak every engine; the risk is
 * in raw SQL (`DB::raw`, `whereRaw`, `DB::statement`, …), in migrations that
 * use a type one engine lacks, and in the configuration around them. Each
 * finding names the file and the line; the checklist names what no scan can
 * see (data, users, privileges, collations on the live server).
 */
class DatabaseScanner
{
    /**
     * Folders read, from the project root.
     *
     * @var list<string>
     */
    public const FOLDERS = ['app', 'database', 'config', 'routes', 'resources/views', 'tests', 'src', 'packages', 'modules', 'Modules', 'docker', '.docker'];

    /**
     * Root files read for server options and service images.
     *
     * @var list<string>
     */
    public const ROOT_FILES = ['docker-compose.yml', 'docker-compose.yaml', 'compose.yml', 'compose.yaml', 'docker-compose.override.yml', '.env.example', 'phpunit.xml', 'phpunit.xml.dist'];

    private const MAX_FILES = 6000;

    private const MAX_OCCURRENCES = 25;

    /**
     * A line that carries SQL of its own.
     */
    private const RAW = '/(DB::(raw|select|selectOne|statement|unprepared|insert|update|delete|affectingStatement|scalar)|->(selectRaw|whereRaw|orWhereRaw|havingRaw|orHavingRaw|orderByRaw|groupByRaw|fromRaw|joinRaw)\s*\(|->raw\s*\(|\bSELECT\s|\bINSERT\s+INTO\b|\bALTER\s+TABLE\b|\bCREATE\s+(TABLE|INDEX|VIEW|TRIGGER|FUNCTION|PROCEDURE)\b)/i';

    /**
     * Words MySQL 8.0 reserved that 5.7 did not.
     *
     * @var list<string>
     */
    public const MYSQL8_RESERVED = ['CUME_DIST', 'DENSE_RANK', 'EMPTY', 'EXCEPT', 'FIRST_VALUE', 'GROUPING', 'GROUPS', 'JSON_TABLE', 'LAG', 'LAST_VALUE', 'LATERAL', 'LEAD', 'NTH_VALUE', 'NTILE', 'OF', 'OVER', 'PERCENT_RANK', 'RANK', 'RECURSIVE', 'ROW_NUMBER', 'SYSTEM', 'WINDOW'];

    /**
     * The engine family of a target, as the rules know it.
     */
    public static function family(string $engine): string
    {
        return match (strtolower($engine)) {
            'postgres', 'postgresql', 'pgsql' => 'pgsql',
            'mariadb' => 'mariadb',
            'sqlite' => 'sqlite',
            'sqlsrv', 'mssql', 'sqlserver' => 'sqlsrv',
            default => 'mysql',
        };
    }

    /**
     * @return array{transition: string, kind: string, findings: list<array<string, mixed>>, checklist: list<array{level: string, title: string, detail: string, raise?: bool}>, files_scanned: int, truncated: bool}
     */
    public function scan(string $root, string $fromEngine, ?string $fromVersion, string $toEngine, ?string $toVersion): array
    {
        $from = self::family($fromEngine);
        $to = self::family($toEngine);
        $kind = $from === $to ? 'upgrade' : 'switch';
        $rules = $this->rules($from, $fromVersion, $to, $toVersion);
        $findings = [];
        $scanned = 0;
        $truncated = false;

        foreach ($this->files($root) as $relative) {
            if (++$scanned > self::MAX_FILES) {
                $truncated = true;
                break;
            }

            $path = $root.'/'.$relative;

            if (filesize($path) > 1024 * 1024) {
                continue;
            }

            $lines = preg_split('/\R/', (string) file_get_contents($path)) ?: [];
            $isMigration = str_contains($relative, 'migrations/');
            $isConfig = str_starts_with($relative, 'config/');
            $isInfra = ! str_ends_with($relative, '.php');

            foreach ($lines as $index => $line) {
                if (trim($line) === '') {
                    continue;
                }

                $raw = preg_match(self::RAW, $line) === 1;

                foreach ($rules as $id => $rule) {
                    $scope = $rule['scope'];

                    if (($scope === 'raw' && ! $raw)
                        || ($scope === 'migration' && ! $isMigration)
                        || ($scope === 'config' && ! $isConfig)
                        || ($scope === 'infra' && ! $isInfra)
                        || ($scope !== 'infra' && $isInfra)) {
                        continue;
                    }

                    if (preg_match($rule['pattern'], $line) !== 1) {
                        continue;
                    }

                    $findings[$id] ??= [
                        'id' => $id,
                        'level' => $rule['level'],
                        'title' => $rule['title'],
                        'fix' => $rule['fix'],
                        'count' => 0,
                        'occurrences' => [],
                    ];

                    $findings[$id]['count']++;

                    if (count($findings[$id]['occurrences']) < self::MAX_OCCURRENCES) {
                        $findings[$id]['occurrences'][] = [
                            'file' => $relative,
                            'line' => $index + 1,
                            'text' => mb_substr(trim($line), 0, 180),
                        ];
                    }
                }
            }
        }

        $order = ['blocker' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'info' => 4];
        $findings = array_values($findings);
        usort($findings, static fn (array $a, array $b): int => [$order[$a['level']] ?? 9, -$a['count']] <=> [$order[$b['level']] ?? 9, -$b['count']]);

        return [
            'transition' => $from.($fromVersion ? ' '.$fromVersion : '').' → '.$to.($toVersion ? ' '.$toVersion : ''),
            'kind' => $kind,
            'findings' => $findings,
            'checklist' => $this->checklist($from, $fromVersion, $to, $toVersion),
            'files_scanned' => min($scanned, self::MAX_FILES),
            'truncated' => $truncated,
        ];
    }

    /**
     * @return list<string>
     */
    protected function files(string $root): array
    {
        $files = [];

        foreach (self::ROOT_FILES as $file) {
            if (is_file($root.'/'.$file)) {
                $files[] = $file;
            }
        }

        foreach (self::FOLDERS as $folder) {
            if (! is_dir($root.'/'.$folder)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveCallbackFilterIterator(
                    new \RecursiveDirectoryIterator($root.'/'.$folder, \FilesystemIterator::SKIP_DOTS),
                    static fn (\SplFileInfo $file): bool => ! in_array($file->getFilename(), ['vendor', 'node_modules', '.git', 'storage', 'build', 'dist'], true)
                )
            );

            foreach ($iterator as $file) {
                if (! $file instanceof \SplFileInfo || ! $file->isFile()) {
                    continue;
                }

                $name = $file->getFilename();

                if (str_ends_with($name, '.php') || preg_match('/\.(ya?ml|cnf|conf|ini|sql)$/', $name) === 1 || str_starts_with(strtolower($name), 'dockerfile')) {
                    $files[] = ltrim(substr($file->getPathname(), strlen($root)), '/');
                }

                if (count($files) > self::MAX_FILES) {
                    return $files;
                }
            }
        }

        sort($files);

        return array_values(array_unique($files));
    }

    /**
     * The rules that apply to one transition. `scope` says where a rule
     * looks: `raw` (lines that carry SQL), `migration`, `config`, `infra`
     * (compose, Docker, server configuration), or `code` (any PHP line).
     *
     * @return array<string, array{level: string, title: string, fix: string, pattern: string, scope: string}>
     */
    protected function rules(string $from, ?string $fromVersion, string $to, ?string $toVersion): array
    {
        $rules = [];
        $mysqlish = static fn (string $engine): bool => in_array($engine, ['mysql', 'mariadb'], true);

        // Leaving MySQL or MariaDB for another engine.
        if ($mysqlish($from) && ! $mysqlish($to)) {
            $rules += [
                'mysql-backticks' => ['level' => 'high', 'scope' => 'raw', 'pattern' => '/`[A-Za-z_][A-Za-z0-9_]*`/', 'title' => 'Identifiers quoted with backticks in raw SQL', 'fix' => 'Backticks are MySQL only. Drop them, or quote with double quotes; better, move the query to the builder, which quotes for the engine.'],
                'mysql-functions' => ['level' => 'high', 'scope' => 'raw', 'pattern' => '/\b(GROUP_CONCAT|IFNULL|DATE_FORMAT|STR_TO_DATE|UNIX_TIMESTAMP|FROM_UNIXTIME|FIND_IN_SET|DATE_ADD|DATE_SUB|TIMESTAMPDIFF|CURDATE|CURTIME|LAST_INSERT_ID|SUBSTRING_INDEX)\s*\(/i', 'title' => 'MySQL-only functions in raw SQL', 'fix' => 'PostgreSQL equivalents: GROUP_CONCAT → string_agg, IFNULL → COALESCE, DATE_FORMAT → to_char, UNIX_TIMESTAMP → extract(epoch from …), DATE_ADD → + interval, FIND_IN_SET → = ANY(string_to_array(…)).'],
                'mysql-if' => ['level' => 'high', 'scope' => 'raw', 'pattern' => '/\bIF\s*\(/i', 'title' => 'IF() in raw SQL', 'fix' => 'Use CASE WHEN … THEN … ELSE … END, which every engine understands.'],
                'mysql-rand' => ['level' => 'medium', 'scope' => 'raw', 'pattern' => '/\bRAND\s*\(\s*\)|FIELD\s*\(/i', 'title' => 'RAND() or FIELD() in raw SQL', 'fix' => 'Use inRandomOrder() instead of RAND(); replace ORDER BY FIELD(…) with a CASE expression.'],
                'mysql-upsert' => ['level' => 'high', 'scope' => 'raw', 'pattern' => '/ON\s+DUPLICATE\s+KEY|INSERT\s+IGNORE|REPLACE\s+INTO/i', 'title' => 'MySQL insert variants in raw SQL', 'fix' => 'Use the builder: upsert(), insertOrIgnore(), updateOrInsert() — they write ON CONFLICT for PostgreSQL.'],
                'mysql-limit-offset' => ['level' => 'medium', 'scope' => 'raw', 'pattern' => '/\bLIMIT\s+\d+\s*,\s*\d+/i', 'title' => 'LIMIT offset, count in raw SQL', 'fix' => 'Write LIMIT count OFFSET offset, or use ->skip()->take().'],
                'mysql-hints' => ['level' => 'medium', 'scope' => 'raw', 'pattern' => '/\b(FORCE|USE|IGNORE)\s+INDEX\b|SQL_CALC_FOUND_ROWS|FOUND_ROWS\s*\(|STRAIGHT_JOIN/i', 'title' => 'MySQL index hints and row counters', 'fix' => 'Remove the hints; replace SQL_CALC_FOUND_ROWS with a separate count() or paginate().'],
                'mysql-fulltext' => ['level' => 'high', 'scope' => 'raw', 'pattern' => '/MATCH\s*\(.*AGAINST/i', 'title' => 'MATCH … AGAINST full-text search', 'fix' => 'Use whereFullText(), which writes to_tsvector for PostgreSQL, or Scout.'],
                'mysql-json-functions' => ['level' => 'medium', 'scope' => 'raw', 'pattern' => '/\b(JSON_EXTRACT|JSON_UNQUOTE|JSON_CONTAINS|JSON_SEARCH|JSON_SET|JSON_LENGTH)\s*\(|->>\s*\'\$/i', 'title' => 'MySQL JSON functions in raw SQL', 'fix' => 'Use the builder\'s JSON paths (where(\'meta->key\', …), whereJsonContains) — they compile to jsonb operators.'],
                'mysql-foreign-key-checks' => ['level' => 'high', 'scope' => 'code', 'pattern' => '/FOREIGN_KEY_CHECKS|SET\s+NAMES|SET\s+SESSION\s+sql_mode/i', 'title' => 'MySQL session statements', 'fix' => 'Use Schema::disableForeignKeyConstraints() / enableForeignKeyConstraints(); drop SET NAMES and sql_mode statements.'],
                'mysql-set-type' => ['level' => 'high', 'scope' => 'migration', 'pattern' => '/->set\s*\(/', 'title' => 'SET columns', 'fix' => 'SET exists in MySQL only: use a JSON column or a pivot table.'],
                'mysql-spatial' => ['level' => 'medium', 'scope' => 'migration', 'pattern' => '/->(geometry|geography|point|polygon|lineString|multiPoint|multiPolygon|geometryCollection)\s*\(/', 'title' => 'Spatial columns', 'fix' => 'On PostgreSQL spatial types need the PostGIS extension: install it on every environment before migrating.'],
                'mysql-charset' => ['level' => 'medium', 'scope' => 'migration', 'pattern' => '/->(charset|collation)\s*\(|\$table->(charset|collation)\s*=/', 'title' => 'Charset or collation set on a table or column', 'fix' => 'MySQL collation names mean nothing to PostgreSQL: remove them, or map them to an ICU collation on purpose.'],
                'mysql-after' => ['level' => 'info', 'scope' => 'migration', 'pattern' => '/->(after|first)\s*\(/', 'title' => 'Column positions (->after(), ->first())', 'fix' => 'Ignored by PostgreSQL: harmless, the column goes at the end.'],
                'mysql-unsigned' => ['level' => 'info', 'scope' => 'migration', 'pattern' => '/->unsigned\s*\(|unsigned(Big|Small|Tiny|Medium)?Integer\s*\(/', 'title' => 'Unsigned integers', 'fix' => 'PostgreSQL has no unsigned types: the range is halved. Check columns that store values above 2^31 (or 2^63).'],
                'mysql-enum' => ['level' => 'low', 'scope' => 'migration', 'pattern' => '/->enum\s*\(/', 'title' => 'Enum columns', 'fix' => 'PostgreSQL gets a varchar with a check constraint; changing the allowed values later needs a raw ALTER TABLE … DROP/ADD CONSTRAINT.'],
                'like-case' => ['level' => 'medium', 'scope' => 'code', 'pattern' => '/[\'"]like[\'"]\s*,|\bLIKE\s+[\'"%?:]/i', 'title' => 'LIKE comparisons', 'fix' => 'LIKE is case-sensitive on PostgreSQL. Where users search, use whereLike(…, caseSensitive: false) or ilike.'],
                'mysql-zero-date' => ['level' => 'medium', 'scope' => 'code', 'pattern' => '/0000-00-00/', 'title' => 'Zero dates', 'fix' => '0000-00-00 is not a date on PostgreSQL: use NULL.'],
                'boolean-int' => ['level' => 'low', 'scope' => 'raw', 'pattern' => '/\b(is_|has_|active|enabled|published|visible)[a-z_]*\s*=\s*[01]\b/i', 'title' => 'Boolean columns compared with 0 or 1 in raw SQL', 'fix' => 'PostgreSQL booleans compare with true / false: `= 1` on a boolean column is an error.'],
            ];
        }

        // Arriving on MySQL or MariaDB from another engine.
        if (! $mysqlish($from) && $mysqlish($to)) {
            $rules += [
                'pgsql-ilike' => ['level' => 'high', 'scope' => 'code', 'pattern' => '/\bilike\b/i', 'title' => 'ILIKE', 'fix' => 'MySQL has no ILIKE; its default collations compare case-insensitively already. Use whereLike(…, caseSensitive: false) or plain like.'],
                'pgsql-casts' => ['level' => 'high', 'scope' => 'raw', 'pattern' => '/::\s*(text|int|integer|bigint|numeric|date|timestamp|jsonb?|uuid|boolean|varchar)\b/i', 'title' => 'PostgreSQL :: casts in raw SQL', 'fix' => 'Write CAST(… AS …).'],
                'pgsql-functions' => ['level' => 'high', 'scope' => 'raw', 'pattern' => '/\b(string_agg|array_agg|to_char|to_tsvector|to_tsquery|plainto_tsquery|generate_series|date_trunc|gen_random_uuid|uuid_generate_v4|nextval|setval|regexp_replace|jsonb_[a-z_]+)\s*\(/i', 'title' => 'PostgreSQL-only functions in raw SQL', 'fix' => 'string_agg → GROUP_CONCAT, to_char → DATE_FORMAT, date_trunc → DATE_FORMAT / DATE(), gen_random_uuid → Str::orderedUuid() in PHP.'],
                'pgsql-returning' => ['level' => 'high', 'scope' => 'raw', 'pattern' => '/\bRETURNING\b|\bON\s+CONFLICT\b|\bDISTINCT\s+ON\b/i', 'title' => 'RETURNING, ON CONFLICT, DISTINCT ON', 'fix' => 'Use the builder (insertGetId, upsert); rewrite DISTINCT ON with a window function or a grouped subquery.'],
                'pgsql-types' => ['level' => 'medium', 'scope' => 'migration', 'pattern' => '/->(jsonb|inet|cidr|macAddress|tsvector|interval)\s*\(|\[\]/', 'title' => 'PostgreSQL column types', 'fix' => 'jsonb becomes json; arrays and tsvector have no MySQL type — use a JSON column or a pivot table.'],
                'pgsql-extensions' => ['level' => 'high', 'scope' => 'code', 'pattern' => '/CREATE\s+EXTENSION|search_path|pgvector|->vector\s*\(/i', 'title' => 'Extensions, schemas, and vectors', 'fix' => 'MySQL has no extensions or schemas in the PostgreSQL sense; vector columns need another store.'],
                'double-quotes' => ['level' => 'medium', 'scope' => 'raw', 'pattern' => '/"[a-z_]+"\."[a-z_]+"/', 'title' => 'Identifiers quoted with double quotes', 'fix' => 'MySQL reads double quotes as strings unless ANSI_QUOTES is on: use the builder or backticks.'],
            ];
        }

        // MySQL 5.7 → 8.x.
        if ($from === 'mysql' && $to === 'mysql' && self::below($fromVersion, '8.0') && ! self::below($toVersion ?? '8.0', '8.0')) {
            $rules += [
                // `OF` is left to the column rule: in a raw line it is as often English as SQL.
                'mysql8-reserved' => ['level' => 'medium', 'scope' => 'raw', 'pattern' => '/(?<![`\'"])\b('.implode('|', array_diff(self::MYSQL8_RESERVED, ['OF'])).')\b(?![`\'"(])/i', 'title' => 'Words MySQL 8.0 reserved, unquoted in raw SQL', 'fix' => 'Quote them with backticks, or rename the column. The builder quotes for you.'],
                'mysql8-reserved-columns' => ['level' => 'info', 'scope' => 'migration', 'pattern' => '/->[a-zA-Z]+\(\s*[\'"]('.strtolower(implode('|', self::MYSQL8_RESERVED)).')[\'"]/', 'title' => 'Columns named with a word MySQL 8.0 reserved', 'fix' => 'Safe through the builder; every raw query that names them must quote them.'],
                'mysql8-group-by-order' => ['level' => 'high', 'scope' => 'raw', 'pattern' => '/GROUP\s+BY\s+[^;]*?\b(ASC|DESC)\b/i', 'title' => 'GROUP BY … ASC / DESC', 'fix' => 'Removed in 8.0: add an explicit ORDER BY.'],
                'mysql8-removed-functions' => ['level' => 'high', 'scope' => 'raw', 'pattern' => '/\b(PASSWORD|ENCODE|DECODE|DES_ENCRYPT|DES_DECRYPT|ENCRYPT)\s*\(/i', 'title' => 'Functions removed in MySQL 8.0', 'fix' => 'Hash in PHP (Hash::make) and encrypt with Crypt.'],
                'mysql8-sql-mode' => ['level' => 'high', 'scope' => 'config', 'pattern' => '/NO_AUTO_CREATE_USER/', 'title' => 'NO_AUTO_CREATE_USER in the sql modes', 'fix' => 'The mode was removed in 8.0 and the server refuses it: delete it from `modes` in config/database.php.'],
                'mysql8-utf8mb3' => ['level' => 'medium', 'scope' => 'config', 'pattern' => '/[\'"]charset[\'"]\s*=>\s*[\'"]utf8[\'"]|utf8_(unicode|general)_ci/', 'title' => 'utf8 (utf8mb3) charset', 'fix' => 'utf8mb3 is deprecated: move to utf8mb4 / utf8mb4_unicode_ci (or utf8mb4_0900_ai_ci) and convert the tables.'],
                'mysql8-zero-date' => ['level' => 'medium', 'scope' => 'code', 'pattern' => '/0000-00-00/', 'title' => 'Zero dates', 'fix' => 'Strict mode rejects them by default: use NULL.'],
                'mysql8-found-rows' => ['level' => 'low', 'scope' => 'raw', 'pattern' => '/SQL_CALC_FOUND_ROWS|FOUND_ROWS\s*\(/i', 'title' => 'SQL_CALC_FOUND_ROWS', 'fix' => 'Deprecated since 8.0.17: use a separate count().'],
            ];
        }

        // MySQL 8.0 → 8.4 (and later): server options and authentication.
        if ($from === 'mysql' && $to === 'mysql' && self::below($fromVersion, '8.4') && ! self::below($toVersion ?? '8.4', '8.4')) {
            $rules += [
                'mysql84-native-password' => ['level' => 'blocker', 'scope' => 'infra', 'pattern' => '/default[-_]authentication[-_]plugin|mysql_native_password/i', 'title' => 'mysql_native_password / default_authentication_plugin', 'fix' => 'MySQL 8.4 disables mysql_native_password by default and removed default_authentication_plugin: a server started with that option does not start. Remove the option and move the users to caching_sha2_password.'],
                'mysql84-removed-options' => ['level' => 'high', 'scope' => 'infra', 'pattern' => '/expire[-_]logs[-_]days|skip[-_]host[-_]cache|binlog[-_]transaction[-_]dependency[-_]tracking|log[-_]bin[-_]use[-_]v1[-_]row[-_]events|innodb[-_]log[-_]file[-_]size/i', 'title' => 'Server options removed or replaced in MySQL 8.4', 'fix' => 'expire_logs_days → binlog_expire_logs_seconds, --skip-host-cache → host_cache_size=0, innodb_log_file_size → innodb_redo_log_capacity. Remove the others.'],
                'mysql84-fk-non-unique' => ['level' => 'medium', 'scope' => 'migration', 'pattern' => '/->references\(\s*[\'"](?!id[\'"])[a-z_]+[\'"]/', 'title' => 'Foreign keys that reference a column other than id', 'fix' => 'MySQL 8.4 refuses a foreign key to a column that is not a primary key or unique (restrict_fk_on_non_standard_key): make sure the referenced column is unique.'],
            ];
        }

        // MySQL → MariaDB.
        if ($from === 'mysql' && $to === 'mariadb') {
            $rules += [
                'mariadb-0900-collation' => ['level' => 'high', 'scope' => 'code', 'pattern' => '/utf8mb4_0900_[a-z_]+/', 'title' => 'MySQL 8 collations (utf8mb4_0900_*)', 'fix' => 'MariaDB before 11.4.5 does not know them: use utf8mb4_unicode_ci, or utf8mb4_uca1400_ai_ci on MariaDB 11.'],
                'mariadb-json' => ['level' => 'low', 'scope' => 'raw', 'pattern' => '/->>|JSON_TABLE|JSON_OVERLAPS|MEMBER\s+OF/i', 'title' => 'JSON operators and functions', 'fix' => 'MariaDB stores JSON as LONGTEXT with a check; ->> and some JSON functions differ. Prefer the builder\'s JSON paths.'],
                'mariadb-driver' => ['level' => 'info', 'scope' => 'config', 'pattern' => '/[\'"]driver[\'"]\s*=>\s*[\'"]mysql[\'"]/', 'title' => 'The mysql driver', 'fix' => 'Laravel 11+ has a mariadb driver (DB_CONNECTION=mariadb): it compiles the few statements that differ.'],
            ];
        }

        // Towards SQLite.
        if ($to === 'sqlite' && $from !== 'sqlite') {
            $rules += [
                'sqlite-alter' => ['level' => 'medium', 'scope' => 'migration', 'pattern' => '/->change\s*\(|dropForeign|renameColumn/', 'title' => 'Column changes in migrations', 'fix' => 'SQLite alters a column by rebuilding the table (Laravel 11+ does it for you); test every migration on SQLite.'],
                'sqlite-raw-ddl' => ['level' => 'high', 'scope' => 'raw', 'pattern' => '/ALTER\s+TABLE\s+\S+\s+(MODIFY|CHANGE|ALTER\s+COLUMN)/i', 'title' => 'Raw ALTER TABLE … MODIFY / ALTER COLUMN', 'fix' => 'SQLite cannot: use the schema builder.'],
            ];
        }

        // Upgrading PostgreSQL across majors.
        if ($from === 'pgsql' && $to === 'pgsql') {
            $rules += [
                'pgsql-extensions-upgrade' => ['level' => 'medium', 'scope' => 'code', 'pattern' => '/CREATE\s+EXTENSION|postgis|pgvector|pg_trgm|uuid-ossp/i', 'title' => 'Extensions in use', 'fix' => 'Every extension must exist, at a compatible version, on the new server before pg_upgrade or the restore.'],
                'pgsql-md5' => ['level' => 'low', 'scope' => 'infra', 'pattern' => '/\bmd5\b|POSTGRES_HOST_AUTH_METHOD/i', 'title' => 'md5 password authentication', 'fix' => 'PostgreSQL 18 deprecates md5 passwords: use scram-sha-256.'],
            ];
        }

        // Tests that run on another engine than production.
        $rules['tests-engine'] = ['level' => 'info', 'scope' => 'infra', 'pattern' => '/name="DB_CONNECTION"\s+value="(sqlite|mysql|mariadb|pgsql)"/i', 'title' => 'The engine the test suite runs on', 'fix' => 'Run the suite on the target engine at least once (and in CI): a suite on SQLite does not prove the move.'];

        return $rules;
    }

    /**
     * What no scan can see, for one transition.
     *
     * `raise` marks what the readiness check reports as a criticality too.
     *
     * @return list<array{level: string, title: string, detail: string, raise?: bool}>
     */
    protected function checklist(string $from, ?string $fromVersion, string $to, ?string $toVersion): array
    {
        $items = [];

        $items[] = ['level' => 'high', 'title' => 'Backup and a rehearsed rollback', 'detail' => 'A dump taken just before, restored once on a scratch server, and the steps to point the application back.'];

        if ($from !== $to) {
            $items[] = ['level' => 'high', 'raise' => true, 'title' => 'Move the data with a tool, not with the migrations', 'detail' => 'Run the migrations on an empty target, then copy the data (pgloader for MySQL → PostgreSQL; mysqldump or a dedicated script the other way), then compare row counts table by table.'];
            $items[] = ['level' => 'high', 'raise' => true, 'title' => 'Reset the sequences after the copy', 'detail' => 'Auto-increment counters do not travel: on PostgreSQL run setval on each table\'s sequence to MAX(id).'];
            $items[] = ['level' => 'medium', 'title' => 'GROUP BY strictness', 'detail' => 'PostgreSQL rejects a select of columns that are neither grouped nor aggregated, which MySQL without ONLY_FULL_GROUP_BY accepted.'];
            $items[] = ['level' => 'medium', 'title' => 'Ordering and comparison of text', 'detail' => 'Collations differ: check the order of sorted lists and case-sensitivity of unique indexes (emails, slugs).'];
            $items[] = ['level' => 'medium', 'title' => 'Packages that speak SQL', 'detail' => 'Search, tenancy, reporting, and backup packages may support one engine only: check each in the dependency list.'];
        }

        if ($from === 'mysql' && $to === 'mysql' && self::below($fromVersion, '8.0') && ! self::below($toVersion ?? '8.0', '8.0')) {
            $items[] = ['level' => 'medium', 'title' => 'Default collation', 'detail' => 'MySQL 8.0 creates tables with utf8mb4_0900_ai_ci; keep config/database.php and existing tables on one collation, or joins across them fail with "Illegal mix of collations".'];
            $items[] = ['level' => 'medium', 'title' => 'Authentication plugin', 'detail' => 'New users default to caching_sha2_password: PHP 7.4+ (mysqlnd) supports it; older clients and some GUI tools do not.'];
        }

        if ($from === 'mysql' && $to === 'mysql' && self::below($fromVersion, '8.4') && ! self::below($toVersion ?? '8.4', '8.4')) {
            $items[] = ['level' => 'high', 'raise' => true, 'title' => 'Users on mysql_native_password', 'detail' => 'SELECT user, host, plugin FROM mysql.user; move each to caching_sha2_password (ALTER USER … IDENTIFIED WITH caching_sha2_password BY …) before the upgrade.'];
            $items[] = ['level' => 'medium', 'title' => 'Upgrade path', 'detail' => 'MySQL supports an in-place upgrade from 8.0 to 8.4 LTS; from 5.7, go through 8.0 first.'];
        }

        if ($from === 'pgsql' && $to === 'pgsql') {
            $items[] = ['level' => 'high', 'title' => 'pg_upgrade or dump and restore', 'detail' => 'A major upgrade needs pg_upgrade (fast, same machine) or pg_dump / pg_restore. PostgreSQL 18 enables data checksums by default: pg_upgrade from a cluster without them needs --no-data-checksums on initdb.'];
            if (self::below($fromVersion, '15') && ! self::below($toVersion ?? '15', '15')) {
                $items[] = ['level' => 'high', 'raise' => true, 'title' => 'CREATE on the public schema', 'detail' => 'PostgreSQL 15 no longer lets every user create tables in public: grant it to the application user, or make it the owner, or migrations fail.'];
            }
            $items[] = ['level' => 'low', 'title' => 'Statistics', 'detail' => 'Run ANALYZE (vacuumdb --analyze-in-stages) after pg_upgrade, or the first queries plan badly.'];
        }

        if ($to === 'mariadb') {
            $items[] = ['level' => 'medium', 'title' => 'One-way move', 'detail' => 'MariaDB and MySQL have diverged: a MariaDB data directory cannot go back to MySQL in place. Keep the MySQL dump.'];
        }

        $items[] = ['level' => 'medium', 'title' => 'Every environment', 'detail' => 'Local (Sail, Herd, Docker), CI service containers, staging, production, and the backups: the same engine and version everywhere.'];
        $items[] = ['level' => 'medium', 'title' => 'PHP extension', 'detail' => 'pdo_pgsql / pdo_mysql on every server, CI image, and Docker image.'];

        return $items;
    }

    /**
     * Whether a version is older than another; an unknown version is not.
     */
    public static function below(?string $version, string $than): bool
    {
        if ($version === null || preg_match('/(\d+(?:\.\d+)?)/', $version, $m) !== 1) {
            return false;
        }

        return version_compare($m[1], $than, '<');
    }
}
