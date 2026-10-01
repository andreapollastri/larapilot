---
name: larapilot-php-upgrade
description: "Upgrades the PHP version of the project: readiness report, dependencies that exclude the target, deprecated code, every file that pins PHP (Docker, CI, Vapor, Herd, Sail), and the server runbook, with the criticalities reported. Italian: aggiornare PHP, nuova versione PHP, passare a PHP 8.4, upgrade PHP."
---

# Larapilot — PHP Upgrade

You move the project to another PHP version. You check first, report every criticality, and only then — when the user says so — move the dependencies, fix the code the new version deprecates, change every file that pins PHP, and write the runbook for the servers. The suite runs on the target PHP before and after.

## Context

`php artisan larapilot:context php-upgrade` — with `--session={token}` when this conversation already holds one, `--fresh` after a compaction. Read every file under `data.runtime.read`, none under `loaded`; read an `on_demand` file when its `when` comes true. Settings, paths, and `data.project` come from that envelope: no `config-show`.

## Output Economy

**Moderate** — the readiness verdict and the criticalities as one table; during the run, one line per step. The detail goes in the reports.

## The Team (this phase)

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — output economy, session/credit risk *(every skill)* |
| 👾 **Andrew** | Laravel Expert — what the installed Laravel and its packages support |
| ⌨️ **Sarah** | CLI, Git & Linux Expert — local PHP (Herd, Sail, Docker), extensions, FPM |
| 🔧 **Alex** | Full-Stack Developer — Composer, Rector, the deprecated code |
| 🧪 **Anne** | Test Architect — the suite on the target PHP, before and after |
| 🚀 **Jack** | DevOps Engineer — CI matrices, images, Vapor, Forge, Cloud; the runbook |

## Config & CLI

1. `php artisan larapilot:stack --only=php,laravel,pins` — PHP running, required, pinned; extensions
2. `php artisan larapilot:upgrade-check --php={X.Y} --report` — packages that exclude it, pins, code, criticalities
3. `composer why-not php {X.Y}` and the dry run in `php.commands`
4. `php artisan larapilot:spec-add --file=` — backlog mode only
5. `php artisan larapilot:decision-log` — when the journal is on

The rules are **Upgrade Protocol** (`upgrade-1.md`) and **PHP Upgrades** (`upgrade-3.md`): follow them, do not restate them.

## Preconditions

- `composer.json` and `composer.lock` at the root
- A target version. None given → ask: one AskQuestion `PHP — upgrade to which version?` with the newest PHP the installed Laravel supports (`laravel.php_range` of `stack`) first, the newest PHP (`php.support.latest`), and `Another — I will name it`
- The PHP the project is on: `current.php.source` of the check says where it came from. `running` while servers run another version → ask, and pass `--php-from=`

## Workflow

### 0. Where the project stands

Run `stack`. One line:

`php {project} (from {source}; {support state}, security until {date}) · laravel {version} supports {min}–{max} · {n} pins`

### 1. Readiness (Andrew + Alex + Jack)

Run `upgrade-check --php={X.Y} --report` and `composer why-not php {X.Y}`. Show the criticalities:

| Level | Area | Finding | What to do |
| --- | --- | --- | --- |

`blocker` and `high` first, 15 rows at most, then `verdict {verdict} · {counts} · {report}`. Then two short lists: the **pins** behind the target (`php.pins_behind`: file:line), and the **code** to change (`php.code.findings`: title and count).

- The target is past what the installed Laravel supports → a `blocker` for this skill alone: offer `/larapilot-laravel-upgrade` in the same run, after this step, or a lower target.
- `ext-imap`, `pspell`, `oci8`, `pdo_oci` on 8.4+ → the servers need PECL: name every server and image.

### 2. Run mode (one AskQuestion)

**Run modes** of the protocol: `now` · `backlog` · `report`. Skipped → `report`.

### 3. Branch and baseline (Sarah + Anne)

**Branch and baseline** of the protocol, then the step the PHP playbook puts first: **the suite on the target PHP** — Herd (`herd isolate {X.Y}`), Sail (runtime in the compose file, `sail build --no-cache`), Docker, or a CI job. Ask the user which one when none is at hand; never install a PHP version system-wide without asking. One line: `baseline · php {current} {passed}/{total} · php {target} {passed}/{total}`.

### 4. The steps (Alex + Jack)

**The steps** of the PHP playbook, one commit each:

1. **Dependencies** that exclude the target — `composer require … --with-all-dependencies`, dry run first; transitive ones through what requires them.
2. **Code** — Rector with the PHP set of the target, then what `php.code` lists and Rector did not cover; PHPStan with `phpVersion` set to the target.
3. **Pins** — CI with old and new side by side, then Docker, `vapor.yml`, `.php-version`, `phpstan.neon`, `rector.php`.
4. **composer.json** — `config.platform.php`; `require.php` raised to `^{X.Y}` only when the user confirms every environment runs it.

**Gates** of the protocol after each step, on the target PHP.

### 5. Close (Anne + Jack)

`upgrade-check --php={X.Y}` again. Write the upgrade report at `{paths.upgrades}/{date}-php-{from}-to-{X.Y}.md` — with the **Servers** runbook of the playbook (Forge, Cloud, Vapor, VPS; the CLI PHP of cron and workers; the extensions) and the rollback. Then **Follow-ups and records**; `decision-log --topic="php version"`.

Last lines: the branch, the commits, `tests on {X.Y}: {before} → {after}`, the report path, what must happen on the servers.

## Output Boundaries

- No file changes before the user picks `now`
- No `--ignore-platform-reqs`, no edits in `vendor/`, no `--no-verify`
- Never raise `require.php` above what every environment runs without the user saying so
- Never install or switch the system PHP, or change a server, without the user's explicit yes
- `@` and `error_reporting` are not fixes for a deprecation

## Example

**Invoke:** `/larapilot-php-upgrade 8.4`

**Readiness:** `php 8.2 (from constraint) · verdict attention` — `ext-imap` required by composer.json (high: PECL on 8.4); 37 implicitly nullable parameters (medium); pins: `.github/workflows/tests.yml:18` 8.2, `Dockerfile:1` php:8.2-fpm.

**Mode:** `now` → `chore/upgrade-php-8.4` · suite on 8.4 through Herd: 212/214.

**Steps:** Rector `php84` set → 37 parameters · CI 8.2 + 8.4 · Dockerfile `php:8.4-fpm` · `config.platform.php` 8.4.0 · gates green.

**Close:** `tests on 8.4: 212 → 214 · .larapilot/docs/upgrades/2026-10-01-php-8.2-to-8.4.md` · servers: Forge site to PHP 8.4, `pecl install imap`.
