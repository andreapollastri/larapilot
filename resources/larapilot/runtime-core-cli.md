Section of the Larapilot runtime. Index: `.larapilot/shared-runtime.md`. Read this file with the editor file-read tool, never `cat`.

## CLI Runtime Contract

Larapilot skills use `php artisan larapilot:*` as the only backend for PRD, backlog, plan, task, and workflow-status operations.

- Run `php artisan larapilot:config-show` at the start of every skill that needs project metadata or configured paths.
- Parse stdout as a JSON success envelope:

```json
{"schema":"larapilot/v1","kind":"<kind>","data":{...}}
```

- Parse stderr as a JSON error envelope:

```json
{"schema":"larapilot/v1","kind":"error","error":{"code":"E_*","message":"...","hint":"..."}}
```

- `php artisan larapilot:validate-*` commands return a normal stdout envelope with `kind:"validation_result"`. Structural validation outcomes are reported in `data.ok` and `data.findings`; the exit code is `0` when `data.ok` is true and `2` otherwise. Error envelopes are reserved for process failures.
- `spec-add` and `spec-plan` reject invalid payloads with an error envelope (`E_INVALID_INPUT`, exit `2`) that carries the findings in `error.details.findings`.
- Workflow transitions are enforced: `spec-start` requires `PLANNED`, `spec-review` requires `IN PROGRESS`, `spec-approve` and `spec-request-changes` require `REVIEW`. Invalid transitions fail with `E_PRECONDITION` (exit `4`).
- Branch on `error.code`, never on `error.message`.
- Treat exit codes as stable: `0` success · `1` generic error · `2` invalid input · `3` connector/backend failure · `4` missing precondition.
- When `.larapilot/config.yaml` is absent, the CLI applies its built-in defaults for connector, paths, workflow statuses, and **project settings**.
- `config-show` returns `data.project_root`: the ABSOLUTE project root containing `.larapilot/config.yaml` (or the current directory when defaults are used). Run connector/backlog commands from this root unless a command-specific rule says otherwise.
- `config-show` also returns `data.settings` (`effort`, `backlog`, `git_mode`, `testing`, `account`, `auto_approve`, `lucille`, `decision_log`, `code_history`, `release_mode`, `project_docs`, `comments`, `dashboard_auth`, `api_auth`, `security_scan`, `github`, `gitlab`, `bitbucket`, `azure`, `notifications`, `notify_slack`, `notify_discord`, `notify_telegram`). **Every skill MUST read and honor these before planning work.** Change them only via `/larapilot-settings` → `php artisan larapilot:settings-set`.
- **Selective envelopes.** `config-show` omits `data.personas` unless you pass `--only=personas`. Prefer `--only=settings,paths,frontend,dev_docs` (comma-separated slices: `settings`, `paths`, `frontend`, `tracker`, `dev_docs`, `backstage`, `workflow`, `personas`). `project_root` and `connector` are always included. Personas are chat labels; the roster lives in **Agent Persona**.
- `spec-show` and `spec-next` accept `--task=TASK-NN` (one task) and `--fields=id,title,status,dependencies,body` (task keys; `id` is always kept). While implementing, list tasks with `--fields=id,title,status,dependencies`, then load the task you are about to execute with `--task=`. The default with no flags still returns the full spec and every task.
- `larapilot:quality` returns `ok` plus Pint and Larastan verdict, summary, and findings. Raw tool stdout is inside the envelope only when Artisan verbosity is on (`-v` / `--verbose`). Never print or paste the Pint progress matrix.
- Decision journal (default ON): record every explicit user choice with `php artisan larapilot:decision-log`, and run `php artisan larapilot:decision-check` before overriding a topic that may already carry one — see **Decision journal (`settings.decision_log`)** below.
- Code change history (default OFF): when `data.settings.code_history` is `YES`, call `php artisan larapilot:code-log` after each `task-done` — see **Code change history (`settings.code_history`)** below.

### Worktree working directory

Specs may be implemented inside a per-spec git worktree. `php artisan larapilot:spec-show {code}` and `spec-next` return `data.workdir`: the ABSOLUTE directory for that spec. After resolving a spec, treat `data.workdir` as the single root for ALL of that spec's file work. Connector commands still run from `data.project_root`.

### Laravel Boost integration

Larapilot works **with** [Laravel Boost](https://laravel.com/ai/boost), not instead of it. Composer accepts Boost **^1** or **^2** and resolves the latest compatible release for your Laravel version (Boost 1 on Laravel 10/11, Boost 2 on Laravel 12+). `php artisan larapilot:update` runs `composer update laravel/boost --with-dependencies` and then `boost:update`, so both the package and the published skills stay current. During planning and implementation use Boost MCP tools when you need Laravel context: `Search Docs` (version-aware docs), `Database Schema` / `Database Query`, `Application Info` (versions and packages), `Tinker`, `Last Error` / `Read Log Entries`. Boost handles Laravel conventions; Larapilot handles the product workflow and persistent artifacts.

Laravel **10** and **11** are past their security-fix window. Composer 2.9+ refuses every `laravel/framework` 10.x/11.x release (open advisories have no patched line; fixes shipped in Laravel **12.60+** / **13**). Compatibility CI ignores only `laravel/framework` on those jobs. Apps still on 10/11 that fail `composer update` with *affected by security advisories* should add `composer config --json policy.advisories.ignore '["laravel/framework"]'` or upgrade.

