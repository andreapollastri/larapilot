## CLI Runtime Contract

Larapilot skills use `php artisan larapilot:*` as the only backend for PRD, backlog, plan, task, and workflow-status operations.

- **`php artisan larapilot:context {skill}` opens every skill.** One call answers `data.settings`, `data.paths`, `data.project`, and `data.runtime` — the files to read. **Honor `data.settings` before planning or acting.** Change a setting only via `/larapilot-settings` → `php artisan larapilot:settings-set`.
- Stdout is a JSON success envelope, stderr a JSON error envelope:

```json
{"schema":"larapilot/v1","kind":"<kind>","data":{...}}
{"schema":"larapilot/v1","kind":"error","error":{"code":"E_*","message":"...","hint":"..."}}
```

- Branch on `error.code`, never on `error.message`. Exit codes are stable: `0` success · `1` generic error · `2` invalid input · `3` connector/backend failure · `4` missing precondition.
- `validate-*` commands answer on stdout with `kind:"validation_result"`: the outcome is `data.ok` and `data.findings`, the exit code `0` when `data.ok` is true and `2` otherwise. Error envelopes are for process failures.
- `spec-add` and `spec-plan` reject an invalid payload with `E_INVALID_INPUT` (exit `2`) and the findings in `error.details.findings`.
- Workflow transitions are enforced: `spec-start` requires `PLANNED`, `spec-review` requires `IN PROGRESS`, `spec-approve` and `spec-request-changes` require `REVIEW`. Anything else fails with `E_PRECONDITION` (exit `4`).
- `data.project_root` is the ABSOLUTE root that holds `.larapilot/config.yaml`. Run every `larapilot:*` command from it. `data.paths` are absolute and under it. With no `config.yaml` the CLI applies its built-in defaults.

### Context and the session cache

`data.runtime` in the `context` envelope:

| Key | What to do |
| --- | --- |
| `dir` | The folder the files below are in |
| `read` | Read every file, whole, with the editor file-read tool — all in one step when the editor reads in parallel. Never `cat`. A truncated preview is a failed load: read the rest before any other step |
| `loaded` | Read earlier in this conversation and unchanged. Do not read them again |
| `on_demand` | Read one only when its `when` comes true, and only once |
| `tokens` | Size of the skill, of `read`, and of `loaded` — Zoey's start line |

The files are compiled for this project: a rule that depends on a setting shows the value the project has, and no other. A value you do not see is not the project's — never apply it from memory.

`data.session` names this conversation. Pass `--session={token}` on every later `context` call in it — another skill, a handoff, the same skill again — and `read` lists only what is new or changed. **After the conversation was compacted or summarized, pass `--fresh`**: a summary keeps the token and loses the rules. A file whose rules you cannot recall is not loaded — read it.

`data.project` carries what the PRD answers for every skill: `kind`, `origin`, `delivery_target`, `business_model`, `budget_sensitivity`, `frontend_topology`, `admin_panel`, `local_dev`, `deploy_platform`, whether a PRD exists (`prd`, `prd_tokens`), and the specs by status. Use it instead of opening the PRD for those answers. An answer that is missing was never given: ask, or read the PRD — never assume one.

### Read the slice, not the document

- `spec-list` — the backlog without the bodies: code, title, status, priority, points, epic, and `cites`, the PRD ids each spec names. `--full` only when every body is needed.
- `spec-show` / `spec-next` — `--task=TASK-NN` (one task) and `--fields=id,title,status,dependencies` (task keys; `id` is always kept). With no flags: the whole spec and every task.
- `prd-show` — the outline of the PRD: sections with their size, every id with its title and MoSCoW. `--ids=FR-004,J-001,NFR-002` returns those blocks, `--section="Technical Architecture"` that section. Read the whole PRD with the file-read tool only in a skill that writes or revises it.
- `config-show --only=…` — a slice `context` does not carry: `tracker`, `backstage`, `workflow`, `personas`.
- `quality` — verdict, summary, findings. Raw stdout only with `-v`. Never paste the Pint progress matrix.
- A file larger than the answer you need (`.larapilot/integrations.md`, a long plan, a log): search its headings with the editor search tool and read that range.

### Worktree working directory

Specs may be implemented inside a per-spec git worktree. `spec-show {code}` and `spec-next` return `data.workdir`: the ABSOLUTE directory for that spec. After resolving a spec, treat `data.workdir` as the single root for ALL of that spec's file work. `larapilot:*` commands still run from `data.project_root`.

### Files

Write a generated artifact at the path `data.paths` gives, creating parent directories. Overwrite the artifact of the current run unless the flow says otherwise. The backlog, plans, `decisions.yaml`, `code-history.yaml`, and `releases.yaml` are written by their commands, never by hand.

### Laravel Boost integration

Larapilot works **with** [Laravel Boost](https://laravel.com/ai/boost): Boost handles Laravel conventions, Larapilot the product workflow and its artifacts. Use the Boost MCP tools for Laravel context — `Search Docs` (version-aware), `Database Schema` / `Database Query`, `Application Info` (versions and packages), `Tinker`, `Last Error` / `Read Log Entries`. `php artisan larapilot:update` keeps Boost and the published skills current. An app on Laravel 10 or 11 whose `composer update` fails with *affected by security advisories*: `composer config --json policy.advisories.ignore '["laravel/framework"]'`, or upgrade.

## Non-negotiables

1. **`effort: ECO` never spawns sub-agents** — every pass runs inline in the parent session.
2. **Human DONE gate** — only a human Approve moves a spec to `DONE`, except when `settings.auto_approve` is `YES` (**Auto-approve**, Project Settings).
3. **Never assume Filament, Laravel Sail, Cipi, Cloudflare, or AWS** — always ask via AskQuestion and honor the choice recorded in the PRD.
4. **PRD before backlog** — write and validate the PRD before creating any backlog spec.
5. **Skills call Artisan** — `php artisan larapilot:*` is the only persistence backend; never invent persistence logic or edit workflow YAML by hand.
6. **One source of truth** — a rule lives in one file; cite it by file and heading, never re-paste it.
7. **Developer domain docs are never optional** — every spec that changes a domain's behavior updates its file under `paths.dev_docs` in the same spec, in English, at every effort level including `ECO`. When `data.dev_docs.documented` is `false` on a codebase that already has domains, the **first** change documents the whole project before its own work — never gradually (`runtime-dev-docs-catchup.md`).
