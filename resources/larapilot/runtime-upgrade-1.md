# Larapilot Runtime — Upgrades, part 1

Read by **`larapilot-laravel-upgrade`**, **`larapilot-php-upgrade`**, and **`larapilot-db-upgrade`**. Index: `.larapilot/runtime-upgrade.md`.

## Upgrade Protocol

An upgrade moves the whole project — code, dependencies, configuration, the files that pin a version, and the servers — from one version to another. Done well it is boring: checked first, run one step at a time on its own branch, verified after each step, and reported. Every rule below serves that.

### Readiness first

- Start with **`php artisan larapilot:upgrade-check --laravel=13`** (or `--php=8.4`, `--db=pgsql:17`, several at once) **`--report`**. It reads `composer.lock`, asks Packagist which release of each dependency supports the target, scans the code and the files around it, and writes `{paths.upgrades}/{date}-readiness-….md`. `--php-from=` / `--db-from=` say the version of today when the project does not; `--offline` reads the lock only.
- Read `criticalities` (level, area, message, hint), then the detail: `laravel.dependencies` (verdict per package: `ok`, `update`, `bump`, `blocker`, `abandoned`, `private`, `unknown`), `php.packages`, `php.pins`, `php.code`, `database.scan`.
- It is a readiness check, not a resolver: **Composer has the last word**. Run the dry run it lists under `commands` (`composer require … --with-all-dependencies --dry-run`, `composer why-not …`) and read what it answers before planning.
- `php artisan larapilot:stack` gives the facts: versions, support windows, drivers, packages, pins. Boost **`Application Info`** confirms installed versions; Boost **`Search Docs`** returns the upgrade guide **of the target version** — read it there, or at `https://laravel.com/docs/{N}.x/upgrade`, never from memory.

### Criticality levels

| Level | Means | What the skill does |
| --- | --- | --- |
| `blocker` | The upgrade cannot land as asked | Stop. Resolve it first (another upgrade before this one, a replacement) or change the target with the user |
| `high` | Lands only with a change outside the code, or a risk to data or production | Resolve in the run, or write it as a follow-up the user accepts |
| `medium` | A change in code or configuration | Do it in the run |
| `low` | A deprecation that still works, cosmetics | Do it when cheap; otherwise list it |
| `info` | Context | Report only |

Every criticality ends the run in one of three states, written in the report: **resolved** (with the commit), **follow-up** (with the spec code), or **accepted** (with the user's reason, logged with `larapilot:decision-log` when the journal is on).

### Run modes

After the readiness report, one AskQuestion — `Upgrade — how do we go on?`:

| Option id | Label |
| --- | --- |
| `now` | `Upgrade now, step by step, on its own branch` |
| `backlog` | `Add it to the backlog as a technical story` |
| `report` | `Stop here — the report is enough` |

Skipped → **`report`**. With a `blocker` open, `now` is offered only for the steps before it (PHP first, for example). Never change a file before the user chooses.

### Backlog mode

One spec through **`php artisan larapilot:spec-add --file=`** (check it with `larapilot:validate-spec --file=` first):

- **Title** `Upgrade Laravel to 13` (PHP, database alike). **Priority** `CRITICAL` when today's version is past its end of life, `HIGH` when it gets security fixes only or ends within six months, `MEDIUM` otherwise. **Points** 5 for one major, 8 for two, 13 for an engine switch.
- **Body** — `**User Story**` (as the maintainer, I want …, so that the project keeps receiving security fixes), `**Demonstrates**`, `**Acceptance Criteria**` — one checkbox per `blocker`/`high`/`medium` criticality, plus *suite green on the target*, *pins updated*, *upgrade report written* — the path of the readiness report, and `**Blocked by:** -` (or the spec of a prerequisite upgrade).
- Then name the next step: `/larapilot-plan {code}` turns this protocol's steps into tasks. Do not start the plan in this turn.

### Branch and baseline

- **Clean tree** — `git status --porcelain` must be empty. Otherwise ask: commit, stash, or stop. Never discard work.
- **Branch** by Project Settings → **Git mode**: `GITFLOW` / `GITFLOW_PUSH` → `chore/upgrade-{laravel|php|db}-{target}` from `develop` (from `main` when there is none); `NO_GITFLOW` → the current branch. When the upgrade is a backlog spec, use that spec's branch. Push only under `GITFLOW_PUSH`.
- **Baseline** — before any change: the test suite (`php artisan test`, Pest, or PHPUnit as the project runs it), `vendor/bin/pint --test`, Larastan/PHPStan when installed, the frontend build when there is one. Record the counts. A failure that exists before the upgrade belongs to the project: list it in the report, do not fix it in the upgrade commits unless the user asks.

### One step at a time

- **Order** — PHP before Laravel when the target Laravel needs it; one Laravel major per step (11 → 12 → 13, never two in one go); the database last, once the code runs on the target engine locally.
- **Inside a step** — (1) the dependencies with Composer, dry run first, `--with-all-dependencies`; (2) what the upgrade guide of that version lists, item by item, applied where it applies here; (3) the package playbooks of part 2; (4) automated fixes when the project has the tool (Rector with the Laravel or PHP set of the target); (5) the gates; (6) **one commit**, Conventional Commits — `chore(deps): upgrade laravel/framework to ^12.0`, `refactor: explicit nullable parameters for PHP 8.4` — naming the criticalities it resolves.
- **Never** — `composer update` with no package named (it moves everything), `--ignore-platform-reqs`, deleting `composer.lock`, editing `vendor/`, `--no-verify`, loosening a constraint until Composer stops complaining, or removing a package to make it resolve without the user's decision.
- **A package with no compatible release** is a `blocker`: ask — stop at the previous step, replace it (a follow-up spec), wait, or fork it (the user decides). Never patch it in place.

### Gates

After each step, before its commit: `composer validate`; `php artisan about` boots; `php artisan route:list` compiles; `php artisan config:cache` and `view:cache` succeed (then `optimize:clear`); the suite passes at least as the baseline did; Pint; Larastan at the project's level; the frontend build when frontend packages moved. The bar is Project Settings → **Testing**.

A gate that fails is fixed inside the step. After three attempts at the same failure, stop: report it as a `high` criticality with the error and the step it belongs to, and leave the branch at the last green commit.

### Environments

The code is half of an upgrade. `upgrade-check` lists the **pins** — Dockerfile and compose images, CI matrices (`php-version`, service containers), `vapor.yml` runtime, `.php-version`, `.nvmrc`, `config.platform.php`, `phpstan.neon` `phpVersion`, `rector.php` sets. Change those in the repository in the same branch. What lives outside it — Forge or Cloud sites, a VPS, a managed database, a teammate's Herd — goes in the report as runbook steps; never change a server from the session unless the user asks and the access is there.

Every report's deploy runbook names, as they apply: the PHP version of the site and PHP-FPM restart; `php artisan optimize:clear` then `optimize`; `php artisan migrate --force` when packages published migrations; `queue:restart`, `horizon:terminate`, Octane reload, the scheduler; an OPcache reset.

### Report

**`{paths.upgrades}/{YYYY-MM-DD}-{laravel|php|db}-{from}-to-{to}.md`**, in English like the readiness report it follows, with commands, versions, and paths verbatim:

1. **Summary** — from → to, branch, commits, verdict, what is left
2. **Criticalities** — level · finding · state (resolved in `abc1234` / follow-up `US-031` / accepted: reason)
3. **Dependencies** — direct packages before → after; added; removed
4. **Changes** — step by step: what the guide asked, what changed here, the files
5. **Environments** — pins changed; what to change outside the repository
6. **Verification** — baseline and after: tests, Pint, Larastan, build
7. **Deploy runbook** — ordered steps
8. **Rollback** — revert the merge commit and redeploy; restore `composer.lock`; for a database, the dump and the switch back
9. **Follow-ups** — what remains, with its spec

### Follow-ups and records

- Each `high` criticality left open: ask — backlog (hand it to **`larapilot-triage`** with the readiness block: level, area, message, hint) or accepted (`decision-log` with the reason).
- The journal on: `larapilot:decision-log --topic="laravel version"` (or `php version`, `database engine`) with the new value and the report as rationale.
- An upgrade that changes an architectural choice — the database engine, a framework feature the domains rely on — updates the developer docs of the domains it touches in the same branch (**Developer Domain Docs**, `dev-docs.md`, on demand).
- When the PRD's Technical Architecture names the old version or engine, say so and offer `/larapilot-prd` to update it; never edit the PRD here.
- Log the session with the command in `data.usage_log` of the context envelope.
