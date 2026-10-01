---
name: larapilot-db-upgrade
description: "Upgrades or switches the database of the project — MySQL 5.7 to 8.0, 8.0 to 8.4, MySQL to MariaDB or PostgreSQL, a PostgreSQL major, SQLite to a server — with a readiness report, the code and configuration changes, a local rehearsal, and the data and cutover runbook, criticalities reported. Italian: aggiornare il database, migrare da MySQL a PostgreSQL, passare a MySQL 8.4, cambiare database."
---

# Larapilot — Database Upgrade

You move the project to a new version of its database engine, or to another engine. You check first and report every criticality; then — when the user says so — you make the code and the configuration portable to the target, prove it on a local rehearsal with the test suite, and write the runbook that moves the data and switches production. **You never touch a production database or server.**

## Context

`php artisan larapilot:context db-upgrade` — with `--session={token}` when this conversation already holds one, `--fresh` after a compaction. Read every file under `data.runtime.read`, none under `loaded`; read an `on_demand` file when its `when` comes true. Settings, paths, and `data.project` come from that envelope: no `config-show`.

## Output Economy

**Moderate** — the readiness verdict, the criticalities, and the checklist as tables; during the run, one line per step. Findings with file and line go in the reports.

## The Team (this phase)

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — output economy, session/credit risk *(every skill)* |
| 🗄️ **Mike** | Database Expert — the transition, the SQL, types, collations, users |
| 🔄 **Sabrine** | Legacy Porting & Migration Specialist — moving the data, verification, cutover |
| 🔧 **Alex** | Full-Stack Developer — raw SQL to the query builder, migrations, config |
| 🧪 **Anne** | Test Architect — the suite on the target engine |
| 🚀 **Jack** | DevOps Engineer — compose services, CI containers, managed databases, the runbook |

## Config & CLI

1. `php artisan larapilot:stack --only=database,pins` — engine, server version, connections, images
2. `php artisan larapilot:upgrade-check --db={engine}:{version} --report` — scan of the code, pins, checklist, criticalities; `--db-from={engine}:{version}` when the server does not answer
3. Boost `Database Schema` — tables, column types, indexes, foreign keys of the database of today
4. `php artisan larapilot:spec-add --file=` — backlog mode only
5. `php artisan larapilot:decision-log` — when the journal is on

The rules are **Upgrade Protocol** (`upgrade-1.md`) and **Database Upgrades** (`upgrade-3.md`): follow them, do not restate them.

## Preconditions

- A target. None given → ask: one AskQuestion `Database — where to?` with the newest version of the engine in use first (`database.support.latest` of `stack`), `PostgreSQL {latest}` when the project is on MySQL or MariaDB, and `Another — I will name it`
- The engine and version of today: `stack` reads them from the server; when it does not answer, ask the user and pass `--db-from=`

## Workflow

### 0. Where the project stands

Run `stack --only=database,pins`. One line:

`{engine} {version} ({support state}, until {date}) · connection {name} · {n} database images pinned`

### 1. Readiness (Mike + Sabrine)

Run `upgrade-check --db=… --report`. Show the criticalities:

| Level | Area | Finding | What to do |
| --- | --- | --- | --- |

then the **scan** by rule — `{title} · {count} places · {fix}` — and the **checklist** (`database.scan.checklist`) as a short list. Then `verdict {verdict} · {kind} · {report}`. Cross-check with Boost `Database Schema`: unsigned columns near their limit, enums, spatial or full-text indexes, collations that differ between tables.

- Packages that speak SQL (search, tenancy, reporting, backups) → check each supports the target; one that does not is a `blocker`.
- The target driver extension is missing here (`database.driver_extension.loaded: false`) → the rehearsal needs it: say how to add it (Herd, Sail, Docker).

### 2. Run mode (one AskQuestion)

**Run modes** of the protocol: `now` · `backlog` · `report`. Skipped → `report`. `now` covers the code, the configuration, and the local rehearsal — never the data of a shared or production database.

### 3. Branch and baseline (Anne + Jack)

**Branch and baseline** of the protocol on the engine of today. Then the rehearsal server: Herd services, DBngin, Docker, or the Sail service — ask which; create a **scratch database named for the rehearsal** (`{app}_upgrade_rehearsal`).

### 4. Make the code portable (Mike + Alex)

One commit per kind, **Gates** of the protocol after each:

1. **Raw SQL** of the scan — to the query builder where it can, otherwise to SQL both engines read; `LIKE` searches, booleans, `GROUP BY`.
2. **Migrations** — types the target lacks (`set`, unsigned near the limit, spatial without PostGIS), charset and collation calls; **never edit a migration that already ran in production** to change history: add a new migration instead.
3. **Configuration** — `config/database.php` (modes, charset, collation, `search_path`), `.env.example`, `phpunit.xml`, the compose service, the CI service container — the **pins** of the check.

### 5. Rehearse (Anne + Sabrine)

**Rehearsal** of the playbook: `migrate:fresh --seed` against the scratch database only (show the exact command and the database name before running it), then the suite with `DB_CONNECTION` on the target. For an engine switch, copy a sample of real data only when the user provides a non-production dump, and run **Moving the data** on it: counts, sequences, smoke test.

### 6. Close (Sabrine + Jack)

`upgrade-check --db=…` again. Write the upgrade report at `{paths.upgrades}/{date}-db-{from}-to-{to}.md` — **Report** of the protocol plus **Moving the data** and **Cutover** written for this project: the commands with its connection names, the window, the verification queries, the rollback. Then **Follow-ups and records**: `decision-log --topic="database engine"`; the developer docs record the new engine and what it changes (`dev-docs.md`, on demand); the PRD's Technical Architecture → offer `/larapilot-prd`.

Last lines: the branch, the commits, `suite on {target}: {passed}/{total}`, the report path, and what the user must do on the servers.

## Output Boundaries

- Never connect to, migrate, copy from, or change a production or shared database
- `migrate:fresh`, `db:wipe`, and `DROP` run only against the scratch database, after showing its name
- Never rewrite migrations that already ran in production; add new ones
- Never commit a dump, a credential, or a connection string with a password
- No file changes before the user picks `now`

## Example

**Invoke:** `/larapilot-db-upgrade from MySQL 8.0 to PostgreSQL 17`

**Readiness:** `verdict attention · switch` — 3 `LIKE` searches (medium, case-sensitive), 42 `->after()` (info), tests on SQLite (info); checklist: move data with pgloader, reset sequences.

**Mode:** `now` → `chore/upgrade-db-pgsql-17` · rehearsal on Herd PostgreSQL 17, database `loyalty_upgrade_rehearsal`.

**Steps:** `whereLike(…, caseSensitive: false)` · PostgreSQL service in CI · `.env.example` · suite on pgsql 214/214.

**Close:** `.larapilot/docs/upgrades/2026-10-01-db-mysql-8.0-to-pgsql-17.md` with the pgloader command, the counts to compare, the cutover window, and the rollback.
