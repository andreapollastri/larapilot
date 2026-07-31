# Larapilot

**From product idea to reviewed Laravel code — with an AI product team that follows a real process.**

Larapilot is a spec-driven workflow for Laravel projects, integrated with [Laravel Boost](https://laravel.com/ai/boost). Install the package, run `/larapilot-*` skills in your AI editor, and ship backlog artifacts, plans, and reviewed code from `.larapilot/`.

**The agent proposes. You approve what ships.** Human-in-the-loop, always.

📖 **Documentation:** [larapilot.web.ap.it](https://larapilot.web.ap.it) · [Walkthrough](https://larapilot.web.ap.it/#examples) · [API](https://larapilot.web.ap.it/#deep-dive-api)

---

## Why Larapilot

AI agents are fast, but isolated prompts are not a product process. Larapilot gives your assistant a disciplined squad — discovery → backlog → plan → implement → review → ship — with **27 personas** (Mark, John, Alex, Anne, …) as review lenses, not costumes.

Each skill orchestrates the conversation. **Artisan commands** persist state; **Boost skills** drive the workflow in chat; **MCP** exposes Laravel context and workflow tools to your editor.

---

## Core loop

Greenfield — repeat steps 3–5 per user story:

```
/larapilot-inception "…"  →  /larapilot-spec  →  /larapilot-plan US-XXX
  →  /larapilot-implement US-XXX  →  /larapilot-review US-XXX
```

| When | Start with |
| --- | --- |
| New product, pivot, or legacy rewrite | `/larapilot-inception` |
| One new capability on an existing product | `/larapilot-feature "…"` |
| Defect or regression | `/larapilot-bug "…"` |

Optional: `/larapilot-design` before plan · `/larapilot-ship` when MVP stories are **DONE** · `/larapilot-autopilot` to batch plan + implement · `/larapilot-settings` for project effort / backlog granularity / git / testing modes · `/larapilot-backstage` to publish the repo into a Backstage developer portal.

Git discipline follows **`settings.git_mode`** (default **Gitflow without auto-push**): one `feature/US-XXX-*` branch per story, atomic commits per plan task; push + remote PR only when mode is **`GITFLOW_PUSH`**. Configure with `/larapilot-settings`. Details on the [docs site](https://larapilot.web.ap.it/#deep-dive-gitflow).

---

## What lands in `.larapilot/`

| Path | Purpose |
| --- | --- |
| `config.yaml` | Project workflow config + `settings` (`effort`, `backlog`, `git_mode`, `testing`, `auto_approve`) |
| `docs/PRD.md` | Product Requirements Document |
| `backlog/` | User stories (`US-XXX`) with status machine |
| `plans/` | Technical plans and tasks per spec |
| `mockups/{spec}/` | Static HTML previews (optional) |
| `internal-feedback/{code}.md` | PM/dev comments until **DONE** |
| `design-systems/` | Packaged references (Filament, Starter Kit, Bootstrap 5, Tailwind, AdminLTE) |
| `techdocs/` | Generated Backstage TechDocs sources (only after `larapilot:backstage-export --write`) |

Skills write artifacts; the workflow engine blocks invalid state transitions (e.g. implement before plan, approve before review, approve with open `[blocks-merge]` feedback or unfinished tasks — override with `--force`).

### Two configuration layers

| Layer | File | Owns | Changed via |
| --- | --- | --- | --- |
| **Laravel config** | `config/larapilot.php` (publishable) + `.env` | Environment toggles: routes, environments, diagnostics, `LARAPILOT_API_TOKEN`, package defaults | `php artisan vendor:publish --tag=larapilot-config`, env vars |
| **Project workflow** | `.larapilot/config.yaml` (committed) | Per-project `settings` (effort, backlog, git mode, testing, auto-approve), paths, statuses | `/larapilot-settings` or `php artisan larapilot:settings-set` |

The YAML wins for workflow settings; Laravel config only provides their defaults on first install.

---

## Skills

Published via Laravel Boost after `php artisan boost:install`:

| Skill | Role |
| --- | --- |
| `/larapilot-inception` | Product discovery → PRD (includes **Frontend Topology**) |
| `/larapilot-spec` | MoSCoW backlog from PRD |
| `/larapilot-feature` | Mini-inception for one evolutiva |
| `/larapilot-bug` | Bug triage → fix spec or rework |
| `/larapilot-frontend-companion` | Configure external FE repo, scan code, sync PRD — **from Laravel** (split-repo cockpit) |
| `/larapilot-design` | Static HTML mockups from design system |
| `/larapilot-plan` | Technical plan + tasks for a spec |
| `/larapilot-implement` | Code + tests on a feature branch |
| `/larapilot-review` | Human gate → **DONE** or rework |
| `/larapilot-ship` | Release checklist when MVP is done |
| `/larapilot-autopilot` | Batch plan + implement |
| `/larapilot-settings` | Persist effort / backlog granularity / git mode / testing / auto-approve for the project |
| `/larapilot-backstage` | Publish the repo into a **Backstage** developer portal (catalog entity + TechDocs) |
| `/larapilot-tracker` | Mirror the backlog into **Linear · Asana · Jira · Trello · ClickUp · Monday** |

During inception, **John + Joe** ask **Frontend Topology**: `Laravel-coupled` (Blade/Livewire/Inertia in this repo), `SPA-in-Laravel` (Vite SPA in this repo), or `API + external frontend` (Laravel API-only + separate FE repo). For the split-repo case, configure the FE absolute path with `php artisan larapilot:frontend-set --path=…`, scan with `larapilot:frontend-scan`, and sync with `larapilot:companion-sync` or `/larapilot-frontend-companion` — **all from this Laravel workspace**. Details: [Frontend companion](https://larapilot.web.ap.it/#deep-dive-frontend-companion).

---

## Dashboard & API (dev/staging)

When the dashboard is browsable (never in production):

- **`/larapilot`** — Kanban board, PRD reader, spec detail with mockup preview and internal feedback
- **`/larapilot/api`** — JSON over the same artifacts (board, specs, PRD, OpenAPI at `/larapilot/api/docs`)
- **`GET /larapilot/api/backstage`** — Backstage catalog entities + delivery snapshot (see [Developer portal](#developer-portal--backstage))
- **`POST /larapilot/api/specs/{code}/comments`** — append internal feedback from scripts or tooling

**API auth:** set `LARAPILOT_API_TOKEN` to require a bearer token (or `X-Larapilot-Token` header) on every `/larapilot/api/*` request — strongly recommended on shared staging hosts. Without a token, reads stay open in the allowed environments, but **writes are refused outside local/development/testing**.

### Diagnostics (bug triage)

Read-only runtime snapshot for `/larapilot-bug` and local debugging — **never mutates workflow state**.

| Surface | How |
| --- | --- |
| **API** | `GET /larapilot/api/diagnostics` — same dashboard gate (dev/staging only); `404` when `LARAPILOT_DIAGNOSTICS_ENABLED=false` |
| **CLI** | `php artisan larapilot:diagnostics` — `--lines=` (cap log tail), `--no-logs` (status + checks only) |
| **MCP** | Larapilot `diagnostics` tool, or `RunArtisanTool` with `larapilot:diagnostics` |

**Query params (API):** `?lines=100` (default from config, capped by `max_log_lines`) · `?no_logs=1` to omit the log tail.

**Payload:** `app` (name, env, Laravel/PHP versions, …), `checks` (`storage_writable`, `cache`, `database`, `queue`, `log_file`), `healthy` (critical checks), optional `logs` with **secrets redacted** (`[REDACTED]`).

**Config** (`config/larapilot.php` / env): `LARAPILOT_DIAGNOSTICS_ENABLED` (default `true`), `LARAPILOT_DIAGNOSTICS_LOG_LINES` (default `100`), `LARAPILOT_DIAGNOSTICS_MAX_LOG_LINES` (default `500`).

Workflow **state** still changes only via skills or Artisan — not from the dashboard or API.

---

## Frontend companion — split repo

When **Frontend Topology** is `API + external frontend`, the **Laravel workspace is the only Larapilot entry point**. The FE repo is a configured write target; PRD/backlog/plans stay in Laravel.

```bash
php artisan larapilot:frontend-set --path=/absolute/path/to/fe-repo --stack=React
php artisan larapilot:frontend-scan
php artisan larapilot:companion-sync
```

Or run `/larapilot-frontend-companion` in the Laravel editor (configure path, scan, sync, orient cross-repo work).

| Command | Purpose |
| --- | --- |
| `larapilot:frontend-set` | Persist absolute `frontend.repo_path` (+ optional `stack`) in `.larapilot/config.yaml` |
| `larapilot:frontend-scan` | Detect stack, Vite/Next/Nuxt tooling, directories, entrypoints |
| `larapilot:companion-sync` | Push PRD + product OpenAPI mirror into the FE repo's `.larapilot/` |

Plan/implement use `repo: frontend` on UI tasks — files write under `data.frontend.repo_path` from `config-show`. Re-run `companion-sync` after PRD living-document edits. Details: [Frontend companion](https://larapilot.web.ap.it/#deep-dive-frontend-companion).

---

## Developer portal — Backstage

Larapilot is repo-level; [Backstage](https://backstage.io) is org-level. The integration publishes `.larapilot/` into the portal — **one way**. The workspace stays the source of truth and workflow state never changes from Backstage.

```bash
php artisan larapilot:backstage-export           # preview the bundle (writes nothing)
php artisan larapilot:backstage-export --write   # generate catalog + TechDocs
```

Or run `/larapilot-backstage`, which asks for owner/system/lifecycle first and persists them to `.env`.

| Generated | Contents |
| --- | --- |
| `catalog-info.yaml` (repo root) | `Component` entity + one `API` entity per OpenAPI contract found (`storage/api-docs/api-docs.json`, `openapi.json`, …) |
| `mkdocs.yml` (repo root) | TechDocs config — `docs_dir: .larapilot/techdocs`, plugin `techdocs-core` |
| `.larapilot/techdocs/` | `index.md` (delivery snapshot), `prd.md`, `backlog/index.md`, `backlog/US-XXX.md` (spec + plan + tasks) |

`catalog-info.yaml` and `mkdocs.yml` are **never overwritten** without `--force` — a project may already own them. Everything under `.larapilot/techdocs/` is regenerated, and pages for deleted specs are pruned. `--no-techdocs` generates the catalog entity only.

**Catalog identity** lives in Laravel config / `.env` (not `.larapilot/config.yaml` — it describes the org catalog, not the delivery workflow):

| Env var | Default | Purpose |
| --- | --- | --- |
| `LARAPILOT_BACKSTAGE_ENABLED` | `true` | Master switch for the integration and its endpoints |
| `LARAPILOT_BACKSTAGE_OWNER` | `guests` | Backstage Group/User that owns the entity — **set this**, Backstage flags unresolvable owners |
| `LARAPILOT_BACKSTAGE_SYSTEM` | — | Parent System entity, when your org uses them |
| `LARAPILOT_BACKSTAGE_LIFECYCLE` | `experimental` | `experimental` · `production` · `deprecated` |
| `LARAPILOT_BACKSTAGE_COMPONENT_TYPE` | `service` | Backstage component type |
| `LARAPILOT_BACKSTAGE_NAME` | slug of `app.name` | Entity name override |
| `LARAPILOT_BACKSTAGE_BASE_URL` | `app.url` | Base URL for catalog links/annotations — **non-production only** |
| `LARAPILOT_BACKSTAGE_TECHDOCS` | `true` | Generate the TechDocs site |
| `LARAPILOT_BACKSTAGE_WORKFLOW_API` | `false` | Also register the dev-only Larapilot API as an `API` entity |

### Live delivery data

For a Backstage plugin or entity provider, two endpoints share the dashboard gate (dev/staging only):

- **`GET /larapilot/api/backstage`** — catalog entities, rendered YAML, TechDocs metadata, and a lean `snapshot` (metrics, per-status counts, blocking feedback, story list without bodies) built for polling many repos
- **`GET /larapilot/api/backstage/catalog-info.yaml`** — the same entities as a Backstage `url` location

Call them through the **Backstage backend proxy** so `LARAPILOT_API_TOKEN` stays server-side. The API returns `404` in production by design — if the portal cannot reach a dev/staging host, ship the committed `catalog-info.yaml` and TechDocs instead.

Keep the catalog fresh with a CI step on the default branch (`--write --force`) or by re-running `/larapilot-backstage` after PRD and backlog milestones. `php artisan larapilot:config-show` reports the current mapping under `data.backstage`.

---

## Project trackers — Linear, Asana, Jira, Trello, ClickUp, Monday

Optional, API-key based. Mirrors the backlog into the tool the rest of the organisation already uses, so a PM or a client can follow delivery without opening `backlog.yaml`. **`.larapilot/` stays the source of truth** — the tracker is a window, not a second workflow.

```bash
php artisan larapilot:tracker-status --ping   # provider, status map, credentials check
php artisan larapilot:tracker-push --dry-run  # what would change, no API calls
php artisan larapilot:tracker-push            # backlog → tracker
php artisan larapilot:tracker-pull            # tracker → drift report (read-only)
php artisan larapilot:tracker-pull --apply    # write mapped statuses back
```

Or run `/larapilot-tracker`, which picks the provider, collects the credentials into `.env`, and checks the status map before the first push.

### What gets mirrored

| Larapilot | Tracker |
| --- | --- |
| User story `US-XXX` | Issue / task / card / item titled `US-XXX — Title`, with the spec body, priority, points, and epic |
| Plan task `TASK-XX` | A **native** sub-issue, subtask, subitem, or checklist item — not a checklist buried in the description |
| Workflow status | The provider's own column: workflow state, status, section, list, or status-column label |

| Provider | Auth | Destination | Subtasks | Status maps to |
| --- | --- | --- | --- | --- |
| **Linear** | personal API key | team key | sub-issues (`parentId`) | workflow state |
| **Jira** (Cloud, REST v2) | email + API token | project key | subtasks (`parent`) | status, via a workflow **transition** |
| **Asana** | personal access token | project gid | subtasks | section (a DONE story is also marked complete) |
| **Trello** | key + token | board id | checklist items | list (board column) |
| **ClickUp** | personal token `pk_…` | list id | subtasks (`parent`) | list status |
| **Monday** | API token | board id | subitems | status-column label |

Only one provider is active at a time (`LARAPILOT_TRACKER_PROVIDER`), but links are stored per provider, so switching tools — or switching back — never loses the mapping.

### Direction: push writes, pull reports

Push is authoritative. Pull is a **report**: it reads remote state and lists drift, and changes the backlog only with `--apply`. Two things it will never do:

- **Set a spec to DONE.** DONE is a human review gate that records the merge commit — that stays with `/larapilot-review` and `larapilot:spec-approve`.
- **Change spec text.** Titles, bodies, and acceptance criteria are owned by `.larapilot/`; the card description says so, and edits made in the tracker are overwritten on the next push.

`TODO` and `PLANNED` mapping to the same column is normal and is not reported as drift. A remote status outside the map is reported as drift with no suggestion rather than guessed at.

Set `LARAPILOT_TRACKER_PULL_COMMENTS=true` to import tracker comments as internal feedback (non-blocking, imported once).

### Configuration

Credentials live in `.env` only — **never** in `.larapilot/`, which is committed:

| Env var | Purpose |
| --- | --- |
| `LARAPILOT_TRACKER_ENABLED` | Master switch (default `false`) |
| `LARAPILOT_TRACKER_PROVIDER` | `linear` · `asana` · `jira` · `trello` · `clickup` · `monday` |
| `LARAPILOT_TRACKER_SYNC_TASKS` | Mirror plan tasks as native subtasks (default `true`) |
| `LARAPILOT_TRACKER_PULL_COMMENTS` | Import remote comments as internal feedback (default `false`) |
| `LARAPILOT_LINEAR_API_KEY` / `_TEAM` | Linear key and team key (e.g. `ENG`) |
| `LARAPILOT_JIRA_BASE_URL` / `_EMAIL` / `_API_TOKEN` / `_PROJECT` | Jira site, account, token, project key |
| `LARAPILOT_ASANA_TOKEN` / `_PROJECT` | Asana PAT and project gid |
| `LARAPILOT_TRELLO_KEY` / `_TOKEN` / `_BOARD` | Trello credentials and board id |
| `LARAPILOT_CLICKUP_TOKEN` / `_LIST` | ClickUp token and list id |
| `LARAPILOT_MONDAY_TOKEN` / `_BOARD` / `_DESCRIPTION_COLUMN` | Monday token, board, and the long-text column that carries the spec body |

Status maps live in `config/larapilot.php` → `tracker.providers.{provider}.status_map`. If a mapped column does not exist, the push fails and names the columns that do — Larapilot never creates columns in your tracker.

`.larapilot/tracker.yaml` holds the spec → remote-id mapping. **Commit it**: without a shared map, every machine creates duplicate cards. It contains identifiers only, never credentials. `php artisan larapilot:config-show` reports the wiring under `data.tracker`, including whether credentials are present — never their values.

---

## Requirements

- PHP **^8.3**
- Laravel **^12** or **^13**
- [Laravel Boost](https://laravel.com/ai/boost) `^2.0` (installed automatically)
- MCP-capable editor (Cursor, Claude Code, VS Code, …)

---

## Quickstart

```bash
composer require andreapollastri/larapilot --dev
php artisan larapilot:install
php artisan boost:install
```

Already on Boost? Refresh skills once:

```bash
php artisan boost:update --discover
```

Register MCP servers in your editor if needed:

```json
{
  "mcpServers": {
    "laravel-boost": {
      "command": "php",
      "args": ["artisan", "boost:mcp"]
    },
    "larapilot": {
      "command": "php",
      "args": ["artisan", "mcp:start", "larapilot"]
    }
  }
}
```

First run in your editor:

```
/larapilot-inception "your product idea"
```

Then `/larapilot-spec`, and the per-story loop above.

### Upgrade

```bash
composer update andreapollastri/larapilot
php artisan larapilot:update
php artisan larapilot:doctor
```

Runtime-only refresh (skip Boost republish): `php artisan larapilot:update --skip-boost`.

`larapilot:update` overwrites `.larapilot/design-systems/` with the packaged references; pass `--preserve-design-systems` to keep local customizations.

---

## Learn more

- [Why & how it works](https://larapilot.web.ap.it/#how-it-works)
- [Five walkthrough examples](https://larapilot.web.ap.it/#examples) — new product, legacy port, feature, bug, frontend companion
- [Frontend companion](https://larapilot.web.ap.it/#deep-dive-frontend-companion) — split FE repo + shared PRD sync
- [Backstage portal](https://larapilot.web.ap.it/#deep-dive-backstage) — catalog entity, TechDocs, delivery snapshot
- [Design systems](https://larapilot.web.ap.it/#deep-dive-design-systems)
- [Team personas](https://larapilot.web.ap.it/#deep-dive-team)

---

## License

MIT © [Andrea Pollastri](https://web.ap.it)
