# Larapilot Runtime — Upgrades, part 3

Read by **`larapilot-php-upgrade`** and **`larapilot-db-upgrade`**, and by **`larapilot-laravel-upgrade`** when PHP must move first. Index: `.larapilot/runtime-upgrade.md`.

## PHP Upgrades

### Which PHP the project is on

Not the one on this laptop: `upgrade-check` takes `config.platform.php`, else the floor of `require.php`, else the PHP that runs it — and says which (`current.php.source`). When servers run something else, pass `--php-from=`. Each version gets two years of active support and two of security fixes, to 31 December:

| PHP | Active until | Security until |
| --- | --- | --- |
| 8.2 | 2024-12-31 | 2026-12-31 |
| 8.3 | 2025-12-31 | 2027-12-31 |
| 8.4 | 2026-12-31 | 2028-12-31 |
| 8.5 | 2027-12-31 | 2029-12-31 |

Laravel bounds it too (part 2, **The ladder**): moving past the newest PHP the installed Laravel supports needs a Laravel upgrade.

### What each version changes

`upgrade-check --php=` scans the project's code for what its rules know (`php.code`); the migration guide of each version (`https://www.php.net/manual/en/migration84.php`, …) has the rest.

- **8.2** — dynamic properties are deprecated (declare them, or `#[\AllowDynamicProperties]` on purpose); `"${var}"` interpolation and `utf8_encode()` / `utf8_decode()` are deprecated. Readonly classes, DNF types.
- **8.3** — `get_class()` / `get_parent_class()` without an argument are deprecated. Typed class constants, `#[\Override]`, `json_validate()`.
- **8.4** — **implicitly nullable parameters are deprecated** (`Foo $x = null` → `?Foo $x = null`): the change that touches most Laravel code bases, and the one Rector does whole. `E_STRICT` and `trigger_error(…, E_USER_ERROR)` are deprecated. **imap, pspell, oci8, and PDO_OCI left core** for PECL — a server that needs them installs them. The default bcrypt cost of `password_hash()` rose to 12. Property hooks, asymmetric visibility, `new Foo()->bar()` without parentheses, `array_find()` / `array_any()` / `array_all()`.
- **8.5** — the casts `(integer)`, `(boolean)`, `(double)`, `(binary)` are deprecated in favour of `(int)`, `(bool)`, `(float)`, `(string)`; so are the backtick operator and `null` as an array offset; `curl_close()`, `imagedestroy()`, `finfo_close()`, `xml_parser_free()` — no-ops since 8.0 — are deprecated. The pipe operator `|>`, `clone()` with properties, `#[\NoDiscard]`, `array_first()` / `array_last()`.

### The steps

1. **Run the suite on the target PHP first**, before changing anything: Herd (`herd isolate 8.4` for this site), Sail (change the runtime in the compose file, `sail build --no-cache`), the Docker image, or a CI job on the new version. Failures here are the real list.
2. **Dependencies** that exclude the target (`php.packages`): the direct ones move with `composer require … --with-all-dependencies`; a transitive one moves by updating what requires it (`required_by`).
3. **Code** — Rector with the PHP set of the target (`->withPhpSets(php84: true)`), dry run first, then the items of `php.code` it does not cover. PHPStan with `phpVersion` set to the target (`80400`) checks the result; PHPCompatibility (`phpcs --runtime-set testVersion 8.4`) when the project has it.
4. **Pins** (`php.pins`) — CI first, with the old and the new version side by side while the change is under review; then Docker images, `vapor.yml`, `.php-version`, `phpstan.neon`, `rector.php`.
5. **composer.json** — `config.platform.php` to the target; the floor of `require.php` (`^8.4`) once every environment runs it, so nobody installs on an older PHP.
6. **Servers** — the runbook in the report: Forge (site → PHP version, then the FPM pool and the workers), Laravel Cloud and Vapor (the environment's runtime), a VPS (the `php8.4-fpm` packages, the extensions the project needs — `larapilot:stack` → `php.extensions` lists the usual ones — and the FPM socket of the web server). The CLI PHP of cron and queue workers moves too.

## Database Upgrades

### Two kinds

- **A new version of the same engine** — MySQL 8.0 → 8.4, PostgreSQL 14 → 17. The code rarely changes; the server, its options, its users, and the data files do.
- **Another engine** — MySQL → PostgreSQL, MySQL → MariaDB, SQLite → MySQL. Raw SQL, some column types, comparisons and ordering change, and the data must be copied.

`upgrade-check --db=pgsql:17 --db-from=mysql:8.0` scans the code (`database.scan.findings`: file, line, fix) and lists the checklist no scan can see (`database.scan.checklist`).

### Engine and version playbooks

- **MySQL 5.7 → 8.0** — reserved words (`rank`, `groups`, `system`, `lead`, …) must be quoted in raw SQL; `GROUP BY … ASC|DESC` is gone; `PASSWORD()`, `ENCODE()` and friends are gone; `NO_AUTO_CREATE_USER` must leave `modes` in `config/database.php`; tables default to `utf8mb4_0900_ai_ci` — keep `config/database.php` and existing tables on one collation or joins fail with *Illegal mix of collations*; users default to `caching_sha2_password`. Run MySQL Shell's `util.checkForServerUpgrade()` against a copy first.
- **MySQL 8.0 → 8.4 LTS** (8.0 reached its end of life in April 2026) — `mysql_native_password` is disabled by default: move every user to `caching_sha2_password` before the upgrade; `--default-authentication-plugin` was removed and a server started with it does not start (compose files, `my.cnf`); a foreign key must reference a primary or unique key; `expire_logs_days` → `binlog_expire_logs_seconds`. `util.checkForServerUpgrade()` again. An in-place upgrade from 8.0 works; from 5.7, through 8.0.
- **MySQL → MariaDB** — mostly a drop-in, and one way: a MariaDB data directory does not go back. `DB_CONNECTION=mariadb` on Laravel 11+. `utf8mb4_0900_*` collations need MariaDB 11.4.5+; JSON is stored as checked `LONGTEXT`.
- **MySQL or MariaDB → PostgreSQL** — rewrite raw SQL (`GROUP_CONCAT` → `string_agg`, `IFNULL` → `COALESCE`, `DATE_FORMAT` → `to_char`, backticks → nothing; better, the query builder); `LIKE` becomes case-sensitive — searches use `whereLike(…, caseSensitive: false)` or `ilike`; booleans compare with `true`/`false`; every selected column must be grouped or aggregated; unsigned types do not exist; an enum becomes a check constraint; spatial types need PostGIS.
- **PostgreSQL → MySQL** — the reverse, and harder: arrays, `jsonb` operators, `RETURNING`, `DISTINCT ON`, `ON CONFLICT`, extensions, and schemas have no direct equivalent.
- **PostgreSQL majors** — `pg_upgrade` (fast, same host, `--link`) or `pg_dump -Fc` / `pg_restore`. Every extension (PostGIS, pgvector, pg_trgm) must exist at a compatible version first. 15 stopped granting `CREATE` on `public` to everyone: grant it to the application user or migrations fail. 18 enables data checksums at `initdb` (`pg_upgrade` from a cluster without them needs `--no-data-checksums`) and deprecates md5 passwords. Run `vacuumdb --analyze-in-stages` after.
- **SQLite → MySQL or PostgreSQL** — the common move of a prototype that grew: SQLite stored whatever it was given, so validate the data (strings in integer columns, invalid dates) before copying.
- **Managed databases** (RDS, DigitalOcean, Cloud, a Forge database server) — the provider runs the major upgrade (RDS blue/green); take a snapshot first and schedule the window.

### Moving the data (engine switch)

1. **Schema from the code** — `php artisan migrate` on an empty target database: the migrations, not a converted dump, define the schema, so a fresh install and production stay the same.
2. **Data with a tool** — pgloader for MySQL → PostgreSQL, data only, with the sequences reset (`pgloader` `WITH data only, truncate, reset sequences`); `mysqldump` and a script the other way.
3. **Verify** — row counts table by table, a checksum of a few key columns, the sequences (`SELECT setval(…, MAX(id))` where the tool did not), and a smoke test of the main journeys.

### Rehearsal (local, never production)

- A target server on this machine: Herd services, DBngin, Docker (`mysql:8.4`, `postgres:17`), or the Sail service.
- A **scratch database named for the rehearsal**. `php artisan migrate:fresh --seed` runs only against it — never against the configured default database without the user's explicit yes, never on a non-local environment.
- The suite on the target engine (`DB_CONNECTION=pgsql php artisan test`, or `.env.testing`); a suite that runs on SQLite does not prove the move. Add the target engine to CI as a service container so it stays proven.
- The code changes of the scan go in the upgrade branch, one commit per kind (`refactor: portable raw SQL for PostgreSQL`, `chore: PostgreSQL service in CI`).

### Cutover

The runbook in the report, in order: announce the window; `php artisan down --secret=…`; final dump; copy; verify (counts, smoke test through the secret); switch `DB_CONNECTION`, host, port, and credentials on every server and worker; `php artisan config:cache`; `queue:restart` (`horizon:terminate`); `php artisan up`. **Rollback**: the old database stays untouched and read-only during the window — switch the environment back; writes made after the switch are listed for a manual replay.
