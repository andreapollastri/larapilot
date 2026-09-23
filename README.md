# Larapilot

**From product idea to reviewed Laravel code — with an AI product team that follows a real process.**

Larapilot is a spec-driven workflow for Laravel projects, integrated with [Laravel Boost](https://laravel.com/ai/boost). Install the package, run `/larapilot-*` skills in your AI editor, and ship backlog artifacts, plans, and reviewed code from `.larapilot/`.

**The agent proposes. You approve what ships.** Human-in-the-loop, always.

📖 **Documentation:** [larapilot.web.ap.it](https://larapilot.web.ap.it) · [Walkthrough](https://larapilot.web.ap.it/#examples) · [API](https://larapilot.web.ap.it/#deep-dive-api)

---

## Why Larapilot

AI agents are fast, but isolated prompts are not a product process. Larapilot gives your assistant a disciplined squad — discovery → backlog → plan → implement → review → ship — with **30 personas** (Mark, John, Mike, Lucille, Sarah for CLI/Git/Linux, Alex, Anne, …) as review lenses, not costumes. Packages (PHP/Laravel Composer) are a first-class Project Kind beside apps and sites.

Each skill orchestrates the conversation. **Artisan commands** persist state; **Boost skills** drive the workflow in chat; **MCP** exposes Laravel context and workflow tools to your editor.

---

## Core loop

Greenfield — repeat steps 3–5 per user story:

```
/larapilot-inception "…"  →  /larapilot-spec  →  /larapilot-plan US-XXX
  →  /larapilot-implement US-XXX  →  /larapilot-review US-XXX
```

Brownfield — adopt an app already in production that was built without Larapilot:

```
php artisan larapilot:install  →  /larapilot-adopt  →  /larapilot-spec  →  (per-story loop)
```

| When | Start with |
| --- | --- |
| New product, site, app, PHP/Laravel package, pivot, or legacy rewrite | `/larapilot-inception` |
| Existing Laravel app in production, built without Larapilot, no PRD yet | `/larapilot-adopt` |
| One new capability on an existing product | `/larapilot-feature "…"` |
| Defect or regression | `/larapilot-bug "…"` |

Optional: `/larapilot-design` before plan · `/larapilot-ship` when MVP stories are **DONE** · `/larapilot-autopilot` to batch plan + implement · `/larapilot-settings` for project effort / backlog granularity / git / testing modes · `/larapilot-backstage` to publish the repo into a Backstage developer portal.

Git discipline follows **`settings.git_mode`** (default **Gitflow without auto-push**): one `feature/US-XXX-*` branch per story, atomic commits per plan task; push + remote PR only when mode is **`GITFLOW_PUSH`**. Optional forge toggles **`github` / `gitlab` / `bitbucket` / `azure`** (default OFF) add PR/MR URLs via `gh`, `glab`, the Bitbucket Cloud API, or the Azure DevOps `az` CLI / REST API; optional Slack/Discord/Telegram notifications (default OFF) cover task/spec/PR/schedule/ship events — see `.larapilot/integrations.md`. Configure with `/larapilot-settings`. Details on the [docs site](https://larapilot.web.ap.it/#deep-dive-gitflow).

---

## What lands in `.larapilot/`

| Path | Purpose |
| --- | --- |
| `config.yaml` | Project workflow config + `settings` (`effort`, `backlog`, `git_mode`, `testing`, `auto_approve`, `lucille`, `decision_log`, `code_history`, optional `dashboard_auth` / `api_auth` / `security_scan` / `github` / `gitlab` / `bitbucket` / `azure` / `notifications` / `notify_*`) |
| `decisions.yaml` | Append-only journal of explicit user choices + regression guard (`decision_log`, ON by default) |
| `code-history.yaml` | Per spec/task files + line ranges touched, from git commits (`code_history`, OFF by default) |
| `integrations.md` | Setup guide for optional GitHub / GitLab / Bitbucket / Azure DevOps + Slack / Discord / Telegram |
| `docs/PRD.md` | Product Requirements Document |
| `docs/devs/` | **Developer domain docs** — one Markdown file per domain/entity/feature: functional flow, technical design, architectural choices with the alternatives that were rejected, key decisions and invariants. Always English, written by every spec that changes the domain, at every effort level |
| `backlog/` | User stories (`US-XXX`) with status machine |
| `plans/` | Technical plans and tasks per spec |
| `mockups/{spec}/` | Static HTML previews (optional) |
| `internal-feedback/{code}.md` | PM/dev comments until **DONE** |
| `design-systems/` | Packaged references (Filament, Starter Kit, Bootstrap 5, Tailwind, AdminLTE) |
| `techdocs/` | Generated Backstage TechDocs sources (only after `larapilot:backstage-export --write`) |

Skills write artifacts; the workflow engine blocks invalid state transitions (e.g. implement before plan, approve before review, approve with open `[blocks-merge]` feedback or unfinished tasks — override with `--force`).

### Developer domain docs (`docs/devs/`, always on)

The one place where the **reasoning** behind the implementation survives the session that produced it. `/larapilot-implement` writes a Markdown file per **domain / entity / feature** — `billing.md`, `user-authentication.md`, `webhook-ingestion.md` — with a fixed skeleton:

| Section | What it carries |
| --- | --- |
| `Purpose` | What the domain is responsible for, in business terms |
| `Functional flow` | How it behaves step by step at runtime, failure paths included |
| `Technical design` | Models, services, actions, jobs, events, routes, commands, config keys, tests — and where they live |
| `Architectural choices` | What was chosen, **what was rejected, and why** |
| `Key decisions & invariants` | The rules that must stay true, and what breaks when they do not |
| `Extension points & gotchas` | Where to plug new behavior in, and the traps |

Three rules make it useful instead of decorative:

- **Always English**, whatever language the PRD and the conversation use — these files address whoever inherits the codebase.
- **Updated in the same spec that changes the behavior.** A task is not `task-done` while its domain doc describes the old code, and a domain whose code moved in the diff without its doc is a **High** review finding.
- **Never deferred.** Unlike README/diagram/runbook work, domain docs survive `effort: ECO` — the prose gets terse, the file still gets written.

There is no setting to turn them on: the folder ships with its contract (`README.md`) and its skeleton (`TEMPLATE.md`) on install, and `/larapilot-ship` blocks on a stale one. Path key: `paths.dev_docs`. Full contract: `.larapilot/runtime-dev-docs.md`.

**A project with no docs is brought level on the first change, not gradually.** `config-show` reports `data.dev_docs.documented`; when it is `false` and the codebase already has domains to describe, the first spec, fix, or hotfix inventories **every** existing domain, writes a file for each, commits the backfill on its own (`docs(US-XXX): bring developer domain docs level`), and only then runs its own work. No AskQuestion, no partial pass, no `ECO` exemption — "we will fill the rest in later" is exactly what produced the empty folder. `/larapilot-adopt` runs the same catch-up at the end of onboarding, so a brownfield project reaches its first spec already level. Where the original reasoning is unrecoverable from git history, the PRD, `decisions.yaml`, and the plans, the file says `<!-- TODO: verify -->` rather than inventing a motive.

This is not `_project_docs/` — that optional handbook is a mixed technical/functional manual for the whole project. `docs/devs/` is engineering-only and mandatory.

### Two configuration layers

| Layer | File | Owns | Changed via |
| --- | --- | --- | --- |
| **Laravel config** | `config/larapilot.php` (publishable) + `.env` | Environment toggles: routes, diagnostics, `LARAPILOT_API_TOKEN`, notification webhooks/tokens, package defaults | `php artisan vendor:publish --tag=larapilot-config`, env vars |
| **Project workflow** | `.larapilot/config.yaml` (committed) | Per-project `settings` (effort, backlog, git, testing, account, auto-approve, lucille, decision-log, code-history, comments, dashboard-auth, api-auth, github/gitlab/bitbucket/azure, notifications), paths, statuses | `/larapilot-settings` or `php artisan larapilot:settings-set` |

The YAML wins for workflow settings; Laravel config only provides their defaults on first install.

---

## Skills

Published via Laravel Boost after `php artisan boost:install`:

| Skill | Role |
| --- | --- |
| `/larapilot-inception` | Product discovery → PRD (includes **Frontend Topology**) |
| `/larapilot-adopt` | Reverse-engineer a PRD from an existing production codebase (brownfield onboarding) |
| `/larapilot-spec` | MoSCoW backlog from PRD |
| `/larapilot-feature` | Mini-inception for one enhancement |
| `/larapilot-bug` | Bug triage → fix spec or rework |
| `/larapilot-frontend-companion` | Link external FE repo path, scan code — **from Laravel only** |
| `/larapilot-release` | Semver release ledger + Gitflow `release/x.y.z` branches (when `release_mode=YES`) |
| `/larapilot-project-docs` | Living handbook in `_project_docs/` (when `project_docs=YES`) |
| `/larapilot-custom-skill` | Create custom skills under `.larapilot/skills/` (auto-registered with Boost) |
| `/larapilot-design` | Static HTML mockups from design system — navigable index at `/larapilot/design` |
| `/larapilot-plan` | Technical plan + tasks for a spec |
| `/larapilot-implement` | Code + tests on a feature branch, plus the developer domain docs in `.larapilot/docs/devs/` |
| `/larapilot-review` | Human gate → **DONE** or rework |
| `/larapilot-ship` | Release checklist when MVP is done |
| `/larapilot-autopilot` | Batch plan + implement, one fresh context per spec |
| `/larapilot-settings` | Persist effort / backlog / git / testing / account / auto-approve / lucille / decision-log / code-history / comments / dashboard-auth / api-auth / GitHub·GitLab·Bitbucket·Azure / notification channels |
| `/larapilot-economics` | **Aurora + Jennifer + Benjamin** — quote, country tax, payback, competitor research, BASE/PRO/PREMIUM packaging, three-line business plan, client quote Markdown (when `account` is FREELANCE or COMPANY) |
| `/larapilot-usage` | **Lucille** — query time/token ledger, deadlines, export Markdown report |
| `/larapilot-backstage` | Publish the repo into a **Backstage** developer portal (catalog entity + TechDocs) |
| `/larapilot-tracker` | Mirror the backlog into **Linear · Asana · Jira · Trello · ClickUp · Monday** |

Inception is run as a **conversation**: AskQuestion only for the fixed choices Larapilot persists, a reaction to every answer before the next question, and — before any requirement is written — at least two **challenge** exchanges from Mark, Jennifer, and Benjamin on the goal itself (who has this problem and what they do instead, what changes if it works, how you will know in 90 days, the riskiest assumption, what would make you stop). Four rounds always happen whatever the branch, including on a legacy rewrite: **Project Kind**, **Delivery Target**, **Business Model** (client project · SaaS · e-commerce · licensed package · internal tool), and **Operations & support** (who manages the server, who is on the hook when it is down, what support window is promised). A skipped round is recorded as `Not decided`, never as a guess, and the ones that feed the quote say what skipping them costs.

During inception, **John + Joe** ask **Frontend Topology**: `Laravel-coupled`, `SPA-in-Laravel`, or `API + external frontend`. For split-repo: `larapilot:frontend-set --path=…` (writes `LARAPILOT_FRONTEND_REPO_PATH` in `.env` — never commit user paths in YAML), then `frontend-scan` — **all from Laravel**. Optional **release mode** tracks semver releases in `.larapilot/releases.yaml` with Gitflow release branches. Optional **project docs** maintains `_project_docs/`. Details: [Frontend companion](https://larapilot.web.ap.it/#deep-dive-frontend-companion).

---

## Dashboard & API (dev/staging)

When the dashboard is browsable (never in production):

- **`/larapilot`** — Kanban board (search, plus priority, epic, and status filters), PRD reader (with decision journal timeline), Inception, **Design** (one navigable index: presentation cover, ordered walk through every flow with prev/next and a contextual flow gallery, plus a zip of HTML/assets), Settings, Skills (custom Boost skills), Git (full-width 12-month contribution heatmap — recent on the right — from local branch history, filterable by developer), Usage (Lucille metrics + Gantt + report download), Economics (**an interactive pricing console**: dropdowns for rate, discount, team size, regime, account type, price line and market scenario recompute the whole page and the downloadable quote live, over scope & effort from the backlog, take-home after tax, payback, packaging, business plan and competitors — when `account` is FREELANCE or COMPANY), spec detail with decision journal, mockup preview, internal feedback, and Docs last in the nav
- **`/larapilot/api`** — JSON over the same artifacts (board, specs, PRD, Economics, OpenAPI at `/larapilot/api/docs`)
- **`GET /larapilot/api/economics`** — quote, tax, payback, packaging, business plan, SaaS forecast (`enabled: false` when `account` is NONE). Accepts what-if query parameters (`?hourly_rate=70&discount_pct=10&tier=premium`) that are computed and returned, never stored
- **`GET /larapilot/api/backstage`** — Backstage catalog entities + delivery snapshot (see [Developer portal](#developer-portal--backstage))
- **`POST /larapilot/api/specs/{code}/comments`** — append internal feedback from scripts or tooling

**API auth:** set `LARAPILOT_API_TOKEN` to require a bearer token (or `X-Larapilot-Token` header) on every `/larapilot/api/*` request — reads and writes alike. Without a token configured, reads stay open in the allowed environments, but **writes are refused outside local/development/testing**.

Turn on the `api_auth` project setting to make the token **mandatory** for the whole API:

```bash
php artisan larapilot:settings-set --api-auth=YES   # every /larapilot/api/* call now needs LARAPILOT_API_TOKEN
```

With `api_auth=YES` and **no** `LARAPILOT_API_TOKEN` set, the API **fails closed** (HTTP 503) instead of answering unauthenticated — strongly recommended on shared staging hosts. This gate never touches the `/larapilot` dashboard UI (`dashboard_auth`) or the MCP server, and the API is still never served in `production`.

**Dashboard UI auth (optional):** the `/larapilot` HTML pages are open by default. Turn on the `dashboard_auth` project setting to require **HTTP Basic Auth**:

```bash
php artisan larapilot:dashboard-user add andrea      # prompts for a password, stores only the hash
php artisan larapilot:settings-set --dashboard-auth=YES
```

Credentials are argon2id/bcrypt hashes in `.larapilot/auth.yaml` (added to `.gitignore` automatically — never committed, no database, no `User` model). Manage them with `larapilot:dashboard-user {list|add|remove}`. Failed sign-ins are rate-limited per IP (`LARAPILOT_DASHBOARD_AUTH_MAX_ATTEMPTS`, default 30/min). This gate **never** touches `/larapilot/api/*` (use `LARAPILOT_API_TOKEN`) or the MCP server. Use HTTPS on shared hosts — Basic Auth sends credentials on every request.

### Account mode & Economics (`account`, NONE by default)

`settings.account` is `NONE` | `FREELANCE` | `COMPANY`. Freelance uses partita IVA / sole-trader regimes (Italian forfettario, IRPEF, autónomo, …); company uses SRL / SPA / Ltd / GmbH / C-Corp tax plus dividend extraction. Both unlock `/larapilot/economics` with a quote (hours × rate, overhead, margin, VAT), net-to-owner after FY-2026 statutory rates, payback, and — when the product looks like a SaaS — ARR, break-even customers, hosting, LTV:CAC, and a 36-month forecast. Every figure in the catalogue (brackets, hourly rates, accountancy costs) is in the country's own currency, `net to owner` is what is left after tax, contributions, the accountant, and any retained reserve — a price that cannot carry its own costs reports a loss rather than a zero. Hours come straight from the backlog — planned task hours per spec, story points where no plan exists, with a per-spec breakdown and warnings on the dashboard — and the snapshot refreshes itself whenever specs, plans, the PRD, or inception change.

`/larapilot/economics` is an **interactive pricing tool**, not a report: a console of dropdowns (hourly rate, margin, commercial discount, team size, overhead, maintenance, account type, country, regime, VAT, how it is sold, monthly list price, BASE/PRO/PREMIUM price line, market scenario, churn, growth, planning customers) recomputes every figure, chart, and the quote download on each change. A discount comes out of the margin and flags the project when it drops below cost; team size compresses the timeline without moving the price. Nothing is written from the browser — a simulation prints the `economics-set` command that would make it real. Sold as a subscription, the page adds three price lines with the features each carries, and pessimistic / realistic / optimistic 36-month projections you can switch between. Competitors, price trend, demand, and packaging come from `.larapilot/economics.market.yaml`, researched by Jennifer and Benjamin during `/larapilot-economics` and persisted with `larapilot:economics-market-write` — Larapilot plots that research and never invents it; on a one-off client delivery that research is skipped unless you ask for it, and the page says so instead of pointing at a command that would do nothing. The page itself reads in the **PRD's language** (`en` · `it` · `es` · `fr` · `de` · `pt` · `nl` · `pl`, English otherwise), like the client quote: headings, captions, banners, glossary, and every sentence the engine writes. The pricing console stays in English — those dropdowns are operator controls, not client-facing prose.

The **maintenance retainer follows the inception answers** instead of a flat percentage: delivery target, who manages the server and who is on the hook when it is down, the support window, budget sensitivity, and the ship method (release mode, Gitflow, testing mode, security scan) each move it by a stated amount from a 12% baseline. The dashboard shows the whole arithmetic, what the client gets for it, and which questions inception never asked — an unanswered server question prices the retainer as application-only and says so.

The **client quote** is a commercial document `/larapilot-economics` writes in the PRD's own language (any language, not a fixed set of templates), stored at `.larapilot/docs/quote.md` via `larapilot:economics-quote-write`. Between the capability list and the price it carries two chapters a buyer actually reads: **infrastructure and hosting** (platform, who manages the server, backups, monitoring, certificates, support window, estimated running cost — only when inception decided one, never invented) and **security and quality**, which sells what a demo cannot show, with every claim derived from what the project's settings genuinely do. Download it at `/larapilot/economics/quote.md` or with `php artisan larapilot:economics-show --format=quote`; until one is written, a built-in template in `en` · `it` · `es` · `fr` · `de` · `pt` · `nl` · `pl` renders the download.

```bash
php artisan larapilot:settings-set --account=FREELANCE
php artisan larapilot:economics-set --country=IT --regime=forfettario_15 --hourly-rate=55
php artisan larapilot:economics-set --product-model=saas --price-monthly=29 --churn=4
```

Or `/larapilot-settings` then `/larapilot-economics`. Profile lives in `.larapilot/economics.yaml`. Figures are planning estimates, not tax advice.

### Decision journal & regression guard (`decision_log`, ON by default)

Every explicit choice you make in any phase — a fixed-choice answer or a free-text directive like _"the background must be orange"_ — is appended, with a timestamp, to `.larapilot/decisions.yaml`:

```bash
php artisan larapilot:decision-log --topic="background color" --value="orange" --source=chat --skill=larapilot-inception
```

Before a later phase records a **different** value for a topic that already has a decision, it runs `php artisan larapilot:decision-check --topic="background color" --value="red"`. If today's answer contradicts an earlier one, the check returns the earlier decision (with its date) so the skill can ask you to confirm — _"on 2026-05-01 you chose **orange**; confirm **red** supersedes it"_ — and only then re-logs with `--supersedes=<id>`. The file is never rewritten; a reversal is a new entry. Turn it off with `php artisan larapilot:settings-set --decision-log=NO`.

### Code change history (`code_history`, OFF by default)

Opt in to keep a per spec/task record of which files and line ranges were touched, read straight from the task's git commit:

```bash
php artisan larapilot:settings-set --code-history=YES
# after each task-done, larapilot-implement runs:
php artisan larapilot:code-log --spec=US-014 --task=TASK-03 --skill=larapilot-implement
php artisan larapilot:code-history --file=app/Models/Post.php   # where has this file been worked on?
```

Entries land in `.larapilot/code-history.yaml`.

### Security scan (`security_scan`, OFF by default)

Fold a static Laravel security scan into `/larapilot-review` and the pre-ship gate. It uses the optional dev package [`andreapollastri/checkpoint`](https://github.com/andreapollastri/checkpoint) (`php artisan checkpoint:scan` — 26 static checks + `composer`/`npm` audit). Larapilot never bundles the scanner and never runs it unless this setting is on:

```bash
composer require --dev andreapollastri/checkpoint
php artisan larapilot:settings-set --security-scan=YES
```

With `security_scan=YES`, `/larapilot-review` runs `checkpoint:scan` and treats `FAIL` findings as review blockers (fix them, or log a waiver with `larapilot:decision-log`); `WARN` findings become review notes. If the package is missing, the review skill stops and asks you to install it. Owned by **Lars** alongside `dashboard_auth` and `api_auth`. Setup: `.larapilot/integrations.md` → **Security scan**.

### Diagnostics (bug triage)

Read-only runtime snapshot for `/larapilot-bug` and local debugging — **never mutates workflow state**.

| Surface | How |
| --- | --- |
| **API** | `GET /larapilot/api/diagnostics` — same dashboard gate (dev/staging only); `404` when `LARAPILOT_DIAGNOSTICS_ENABLED=false`. Covered by the API token like every other `/larapilot/api/*` endpoint — with `api_auth=YES` it requires `LARAPILOT_API_TOKEN` |
| **CLI** | `php artisan larapilot:diagnostics` — `--lines=` (cap log tail), `--no-logs` (status + checks only). Local artisan command — no token needed |
| **MCP** | Larapilot `diagnostics` tool, or `RunArtisanTool` with `larapilot:diagnostics` |

**Query params (API):** `?lines=100` (default from config, capped by `max_log_lines`) · `?no_logs=1` to omit the log tail.

**Payload:** `app` (name, env, Laravel/PHP versions, …), `checks` (`storage_writable`, `cache`, `database`, `queue`, `log_file`), `healthy` (critical checks), optional `logs` with **secrets redacted** (`[REDACTED]`).

**Config** (`config/larapilot.php` / env): `LARAPILOT_DIAGNOSTICS_ENABLED` (default `true`), `LARAPILOT_DIAGNOSTICS_LOG_LINES` (default `100`), `LARAPILOT_DIAGNOSTICS_MAX_LOG_LINES` (default `500`).

Workflow **state** still changes only via skills or Artisan — not from the dashboard or API.

---

## Frontend companion — split repo

When **Frontend Topology** is `API + external frontend`, **Laravel is the only Larapilot cockpit**. PRD, backlog, plans, and all `/larapilot-*` commands run in the backend workspace. The FE repo is a **linked write target** whose absolute path lives in **`LARAPILOT_FRONTEND_REPO_PATH`** (`.env`).

### How it works

```
Laravel (cockpit)                         Frontend repo (write target)
─────────────────                         ────────────────────────────
.larapilot/docs/PRD.md                    src/…                   ◄── implement (repo: frontend)
.larapilot/backlog.yaml                   tests/…
.larapilot/plans/                         (application code only)
.larapilot/mockups/
```

1. **Inception** records topology and asks for the FE absolute path → `larapilot:frontend-set`.
2. **Scan** (`larapilot:frontend-scan`) reads existing FE structure before planning evolutive work.
3. **Spec → plan → implement** run on Laravel. UI tasks use `repo: frontend` and write under the path resolved from `LARAPILOT_FRONTEND_REPO_PATH`.

### Setup commands

```bash
php artisan larapilot:frontend-set --path=/absolute/path/to/fe-repo --stack=React
php artisan larapilot:frontend-scan
```

Or `/larapilot-frontend-companion` in the Laravel editor.

| Command | Purpose |
| --- | --- |
| `larapilot:frontend-set` | Persist `LARAPILOT_FRONTEND_REPO_PATH` in `.env` (+ optional `stack` in config) |
| `larapilot:economics-set` | Persist country, tax regime, hourly rate, margin, discount, team size, SaaS prices (requires `account` ≠ NONE) |
| `larapilot:economics-show` | Quote, tax, payback, SaaS forecast (`--format=md` internal report, `--format=quote` client document) |
| `larapilot:economics-quote-write` | Persist the client quote document written in the PRD language (`--file=`, `--content=`, `--lang=`) |
| `larapilot:economics-market-write` | Persist the researched market: competitors, price trend, demand scenarios, packaging tiers (`--file=`, `--content=`) |
| `larapilot:release-list` | List releases from `.larapilot/releases.yaml`, including which release branch is already active |
| `larapilot:release-add` / `release-set` | Register or update a release. Moving to `in_progress` cuts `release/x.y.z` without switching branches |
| `larapilot:release-cut` | Check out the release branch (`--semver=`, optional `--push`) |
| `larapilot:release-feature` | Open `feature/US-XXX-*` from that release (`--spec=`, optional `--slug=`) |
| `larapilot:release-sync` | Merge `develop` into the release branch |
| `larapilot:release-ship` | Merge to `main`, tag `vX.Y.Z`, back-merge `develop`, mark shipped |
| `larapilot:release-import` | Rebuild shipped releases from Git semver tags |
| `larapilot:custom-skill-list` | List skills under `.larapilot/skills/` and auto-register them with Boost |
| `larapilot:custom-skill-add` | Persist a skill to `.larapilot/skills/{name}/SKILL.md` and register it (`.ai/skills/` + `boost:update`) |
| `larapilot:frontend-scan` | Detect stack, tooling, structure, entrypoints |

Details: [Frontend companion](https://larapilot.web.ap.it/#deep-dive-frontend-companion).

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

## Self-hosted VPS — one server for the whole team

`larapilot:vps-provision` writes a **standalone `provision.sh`** for an Ubuntu 24.04/26.04 LTS server that hosts several Larapilot projects for a team working over SSH with Claude Code and their own Claude plan.

```bash
php artisan larapilot:vps-provision              # writes ./provision.sh
php artisan larapilot:vps-provision --with-readme # + VPS-README.md (operator guide)
scp provision.sh root@<vps>: && ssh root@<vps> 'bash provision.sh'
```

The one script installs PHP 8.3/8.4/8.5 (FPM pool per project), MySQL, Redis, Nginx + certbot, Supervisor, cron, Node LTS, Composer, Claude Code and the `gh` / `glab` / `az` CLIs, then generates three tools:

| Tool | For | Does |
| --- | --- | --- |
| `prj-ai` | admin (root) | `config` · `list` · `add` · `del` · `php <p> [ver]` · `user-add` · `user-del` · `deploy` |
| `prj-work` | developers | login menu → per-dev workspace (clone of the canonical) inside a persistent `tmux` session |
| `prj-pr` | developers | open a PR/MR from the workspace — **GitHub, GitLab, Bitbucket Cloud, Azure DevOps** (via `gh` / `glab` when authenticated, REST otherwise) |

Git provider is picked in `prj-ai config` and can change without re-provisioning. Each project gets its own system user, database, FPM pool and vhost; each workspace gets Claude Code `deny` guardrails scoping the agent to that project. Deploys are incremental (Composer / npm / migrations run only when the relevant paths changed) and the canonical is cached with `artisan optimize`. Full operator guide: `resources/larapilot/vps/README.md` (or `--with-readme`).

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

- PHP **^8.1** (8.2+ recommended)
- Laravel **^10.49** · **^11.45.3** · **^12** · **^13**
- [Laravel Boost](https://laravel.com/ai/boost) **^1** or **^2** (Composer resolves Boost 1 on Laravel 10/11 and Boost 2 on Laravel 12+; kept current by `larapilot:update`)
- MCP-capable editor (Cursor, Claude Code, VS Code, …)

On Laravel 10/11 the MCP stack pulls `illuminate/json-schema` — use a recent framework patch (Laravel **11.47+** recommended on 11.x) so JsonSchema tooling loads cleanly.

Laravel **10** and **11** are past their security-fix window. Composer 2.9+ refuses every `laravel/framework` 10.x/11.x release because open advisories have no patched line (fixes shipped in Laravel **12.60+** / **13**). Larapilot's CI still runs those majors by ignoring only `laravel/framework` advisories on the 10/11 jobs. Apps still on 10/11 that fail `composer update` with *affected by security advisories* need the same ignore (`composer config --json policy.advisories.ignore '["laravel/framework"]'`) or should upgrade to Laravel 12+.

---

## Quickstart

```bash
composer require andreapollastri/larapilot --dev
php artisan larapilot:install
php artisan boost:install
```

`larapilot:install` also scaffolds **[Larastan](https://github.com/larastan/larastan) level 5+** and **[Laravel Pint](https://laravel.com/docs/pint)** (`phpstan.neon.dist`, `pint.json`, Composer scripts, dev dependencies). Run `php artisan larapilot:quality` before merge; `larapilot:doctor` fails when the gate is missing.

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
composer update andreapollastri/larapilot laravel/boost --with-dependencies
php artisan larapilot:update
php artisan larapilot:doctor
```

`larapilot:update` also runs `composer update laravel/boost` (unless it is already inside a Composer script) and then `boost:update`, so Boost itself tracks the latest stable release — not only the published skills. Runtime-only refresh: `php artisan larapilot:update --skip-boost`.

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
