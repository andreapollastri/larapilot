---
name: larapilot-laravel-upgrade
description: "Upgrades the project from its Laravel version to the one the user names: readiness report first, then dependencies, codebase, Filament, Nova, Livewire, Inertia and the other packages, one major at a time on its own branch, with the criticalities reported. Italian: aggiornare Laravel, upgrade Laravel, nuova versione Laravel, migrare a Laravel 13."
---

# Larapilot — Laravel Upgrade

You move the project from the Laravel it is on to the one the user asks for. You check first, report every criticality, and only then — when the user says so — change the code, the dependencies, and the files that pin versions, **one major at a time**, with the gates green after each step. The upgrade ends with a report anyone on the team can deploy from.

## Context

`php artisan larapilot:context laravel-upgrade` — with `--session={token}` when this conversation already holds one, `--fresh` after a compaction. Read every file under `data.runtime.read`, none under `loaded`; read an `on_demand` file when its `when` comes true. Settings, paths, and `data.project` come from that envelope: no `config-show`.

## Output Economy

**Moderate** — the readiness verdict and the criticalities as one table; during the run, one line per step (`step 2/3 · Laravel 12 → 13 · gates green · a1b2c3d`). The detail goes in the reports, not in chat.

## The Team (this phase)

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — output economy, session/credit risk *(every skill)* |
| 👾 **Andrew** | Laravel Expert — the ladder, the upgrade guides, the order of the steps |
| 🔧 **Alex** | Full-Stack Developer — Composer, code changes, package upgrade scripts |
| ✨ **Joe** | Frontend Expert — Livewire, Inertia adapters, Vite, the frontend companion |
| 🧪 **Anne** | Test Architect — baseline and gates after each step |
| 🚀 **Jack** | DevOps Engineer — pins in CI, Docker, and deploy files; the deploy runbook |

## Config & CLI

1. `php artisan larapilot:stack --only=laravel,php,packages,pins` — where the project stands
2. `php artisan larapilot:upgrade-check --laravel={N} --report` — dependencies, PHP, pins, criticalities; writes `{paths.upgrades}/…-readiness-….md`
3. The Composer commands it lists under `laravel.commands` — `why-not`, then the `--dry-run`
4. Boost `Search Docs` (the upgrade guide of each major) and `Application Info`
5. `php artisan larapilot:spec-add --file=` — backlog mode only
6. `php artisan larapilot:decision-log` — when the journal is on

The rules are **Upgrade Protocol** (`upgrade-1.md`) and **Laravel Upgrades** with **Package Playbooks** (`upgrade-2.md`): follow them, do not restate them.

## Preconditions

- `composer.json` and `composer.lock` at the root — without the lock, stop: `composer install` first
- A target newer than the installed major. No target given → ask: one AskQuestion `Laravel — upgrade to which version?` with the newest major (`laravel.support.latest` of `stack`) first, the next major after the installed one, and `Another — I will name it`

## Workflow

### 0. Where the project stands

Run `stack`. One line:

`laravel {version} ({support state}, security until {date}) · php {running} · composer.json php {constraint} · {n} tracked packages`

### 1. Readiness (Andrew + Alex)

Run `upgrade-check --laravel={N} --report`, then the `why-not` and the dry run it lists. Show:

| Level | Area | Finding | What to do |
| --- | --- | --- | --- |

the `blocker` and `high` rows first, 15 rows at most, then one line: `verdict {verdict} · {counts} · {report}`. Name the packages that move a major (`bump`) — Filament, Nova, Livewire, Inertia first — and the ones that cannot (`blocker`, `private`, `abandoned`). The dry run contradicts the check → the dry run wins: add what it names as a `blocker`.

- A `php` blocker (the target needs a newer PHP) → the PHP step comes first: offer `/larapilot-php-upgrade` now, or as step 1 of this run (read `upgrade-3.md`).
- `private` (Nova, a company package) → ask the user whether the vendor supports the target; never assume.

### 2. Run mode (one AskQuestion)

**Run modes** of the protocol: `now` · `backlog` · `report`. Skipped → `report`. `backlog` → **Backlog mode**, then stop. `report` → name the report and stop.

### 3. Branch and baseline (Anne + Jack)

**Branch and baseline** of the protocol: clean tree, the branch of the Git mode, the baseline of the suite, Pint, Larastan, and the build. One line: `baseline · tests {passed}/{total} · pint {ok|n files} · larastan {ok|n errors}`.

### 4. One major at a time (Andrew + Alex + Joe)

For each step of `laravel.steps` (and the PHP step first when needed):

1. **Dependencies** — `laravel/framework:^{N}.0` with the bumps of that step in one `composer require … --with-all-dependencies`, dry run first.
2. **The guide** — Boost `Search Docs` for the upgrade guide of version N; apply each item that applies here; note the ones that do not.
3. **Packages** — the playbook of each package that moved (Filament's upgrade script, `livewire:upgrade`, Inertia server and client adapter together, Nova's guide, the `UPGRADE.md` of first-party packages); publish the migrations and config they ask for.
4. **Automated fixes** — Rector with the Laravel set of the target, when the project has it.
5. **Gates** — **Gates** of the protocol. Three failed attempts at one failure → stop the run at the last green commit and report it.
6. **Commit** — one per step, naming the criticalities it resolves.

Then the **pins** of the step (CI, Docker, `config.platform.php`) in the same branch.

### 5. Close (Anne + Jack)

Run `upgrade-check --laravel={N}` again: what is left must be accepted or a follow-up. Write the upgrade report — **Report** of the protocol — at `{paths.upgrades}/{date}-laravel-{from}-to-{N}.md`, with the deploy runbook and the rollback. Then **Follow-ups and records**: each `high` left open → backlog through `larapilot-triage` or accepted; `decision-log --topic="laravel version"`; dev docs when an architectural choice changed (`dev-docs.md`, on demand); the PRD's Technical Architecture → offer `/larapilot-prd`.

Last lines: the branch, the commits, `tests {before} → {after}`, the report path, and — under `GITFLOW` — that nothing was pushed.

## Output Boundaries

- No file changes before the user picks `now`
- No `composer update` without a package, no `--ignore-platform-reqs`, no edits in `vendor/`, no `--no-verify`
- Never remove or replace a package, or loosen a constraint, without the user's decision
- Never change a server, a CI secret, or production data from the session
- Never skip a major or squash two majors into one commit
- The upgrade guide is read for the target version, never recalled from memory

## Example

**Invoke:** `/larapilot-laravel-upgrade 13`

**Readiness:** `verdict attention · 0 blocker · 1 high · 3 medium` — Laravel 13 needs PHP 8.3 (composer.json allows 8.2); `spatie/laravel-backup` 9 → 10, `laravel/tinker` 2 → 3, `laravel/boost` 1 → 2 (dev); Filament 5 already supports 13.

**Mode:** `now` → branch `chore/upgrade-laravel-13` · baseline 214/214.

**Step 1/1:** `composer require laravel/framework:^13.0 laravel/tinker:^3.0 spatie/laravel-backup:^10.3 -W` · guide items applied · `require.php` → `^8.3`, CI matrix 8.3/8.4 · gates green · `chore(deps): upgrade to Laravel 13`.

**Close:** `tests 214 → 214 · .larapilot/docs/upgrades/2026-10-01-laravel-12-to-13.md` · not pushed (GITFLOW).
