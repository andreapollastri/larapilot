# Larapilot

**From product idea to reviewed Laravel code — with an AI product team that follows a real process.**

Larapilot is a spec-driven workflow for Laravel projects, built on [Laravel Boost](https://laravel.com/ai/boost). Install the package, run `/larapilot-*` skills in your AI editor, and get a PRD, a backlog, technical plans, and reviewed code — all versioned in `.larapilot/` next to your app.

**The agent proposes. You approve what ships.** Human-in-the-loop, always.

📖 **Documentation:** [larapilot.web.ap.it](https://larapilot.web.ap.it) · [Use cases](https://larapilot.web.ap.it/#examples) · [Custom skills](https://larapilot.web.ap.it/#example-custom-skill) · [API](https://larapilot.web.ap.it/#deep-dive-api)

---

## Contents

- [Why Larapilot](#why-larapilot)
- [Quickstart](#quickstart)
- [The core loop](#the-core-loop)
- [Skills](#skills)
- [Custom skills — your own slash commands](#custom-skills--your-own-slash-commands)
- [What lands in `.larapilot/`](#what-lands-in-larapilot)
- [Developer domain docs](#developer-domain-docs-docsdevs-always-on)
- [Project settings](#project-settings)
- [Dashboard & API](#dashboard--api-devstaging)
- [Economics](#economics-account-none-by-default)
- [Traceability](#traceability)
- [Security](#security)
- [Integrations](#integrations)
- [Artisan CLI & MCP](#artisan-cli--mcp)
- [Requirements](#requirements)
- [Learn more](#learn-more)

---

## Why Larapilot

AI agents are fast, but isolated prompts are not a product process. Larapilot gives your assistant a disciplined squad — discovery → backlog → plan → implement → review → ship — with **30 personas** (Mark for product, John for architecture, Robert for review, Lars for security, Sarah for CLI/Git/Linux, Lucille for time and deadlines, …) used as review lenses, not costumes. Apps, websites, and PHP/Laravel Composer packages are all first-class.

Three layers, each with one job:

| Layer | Job |
| --- | --- |
| **Boost skills** (`/larapilot-*`) | Drive the conversation in your editor |
| **Artisan commands** (`larapilot:*`) | Persist state and enforce the workflow — skills never write workflow files by hand |
| **MCP server** (`larapilot`) | Lets the agent read backlog, specs, and diagnostics mid-conversation |

The workflow engine blocks invalid transitions: no implement before plan, no approve before review, no approve with open `[blocks-merge]` feedback or unfinished tasks (unless you pass `--force`).

---

## Quickstart

```bash
composer require andreapollastri/larapilot --dev
php artisan larapilot:install
php artisan boost:install
```

`larapilot:install` scaffolds the `.larapilot/` workspace, and also **[Larastan](https://github.com/larastan/larastan) level 5+** and **[Laravel Pint](https://laravel.com/docs/pint)** (`phpstan.neon.dist`, `pint.json`, Composer scripts, dev dependencies). Run `php artisan larapilot:quality` before merge; `larapilot:doctor` fails when the gate is missing.

Already on Boost? Pick up the skills once with `php artisan boost:update --discover`.

If your editor does not list the MCP servers after `boost:install`, register them:

```json
{
  "mcpServers": {
    "laravel-boost": { "command": "php", "args": ["artisan", "boost:mcp"] },
    "larapilot":     { "command": "php", "args": ["artisan", "mcp:start", "larapilot"] }
  }
}
```

Then, in your editor:

| Your project | Type |
| --- | --- |
| A new idea — app, site, or Laravel package | `/larapilot-inception "your product idea"` |
| A Laravel app in production, built without Larapilot | `/larapilot-adopt` |
| A legacy system you are rewriting in Laravel | `/larapilot-inception` with a snapshot in `.larapilot/legacy/` |

### Upgrade

```bash
composer update andreapollastri/larapilot laravel/boost --with-dependencies
php artisan larapilot:update
php artisan larapilot:doctor
```

`larapilot:update` refreshes the runtime packs, `task-templates.md`, `integrations.md`, and the packaged design systems (`--preserve-design-systems` keeps yours), re-registers your custom skills, then runs `composer update laravel/boost` (skipped inside a Composer script) and `boost:update`. Runtime-only refresh: `php artisan larapilot:update --skip-boost`. It never touches `config.yaml`, the PRD, the backlog, plans, domain docs, or custom skills. Do not re-run `larapilot:install` on an existing project unless you mean `--force`.

---

## The core loop

Greenfield — repeat steps 3–5 per user story:

```
/larapilot-inception "…"  →  /larapilot-spec  →  /larapilot-plan US-XXX
  →  /larapilot-implement US-XXX  →  /larapilot-review US-XXX
```

Brownfield — adopt an app already in production:

```
php artisan larapilot:install  →  /larapilot-adopt  →  /larapilot-spec  →  (per-story loop)
```

| When | Start with |
| --- | --- |
| New product, site, app, PHP/Laravel package, pivot, or legacy rewrite | `/larapilot-inception` |
| Existing Laravel app in production, built without Larapilot, no PRD yet | `/larapilot-adopt` |
| One new capability on an existing product | `/larapilot-feature "…"` |
| Defect or regression | `/larapilot-bug "…"` |
| Many planned stories at once | `/larapilot-autopilot US-004 US-005 …` |
| A team ritual the packaged skills don't cover | `/larapilot-custom-skill` |

Optional around the loop: `/larapilot-design` before plan · `/larapilot-ship` when the MVP stories are **DONE** · `/larapilot-settings` for project modes · `/larapilot-usage` for time and tokens · `/larapilot-economics` for quotes and pricing.

**Status machine:** `TODO` → `PLANNED` → `IN PROGRESS` → `REVIEW` → `DONE`. A rejected review sends the story back to `TODO` with your feedback attached.

**Git** follows `settings.git_mode` (default **`GITFLOW`, no auto-push**): one `feature/US-XXX-*` branch per story, one atomic Conventional Commit per plan task; push and remote PRs only under `GITFLOW_PUSH`. With `release_mode=YES`, stories can branch from `release/x.y.z` instead of `develop`. Details: [Git workflow](https://larapilot.web.ap.it/#deep-dive-gitflow).

**Autopilot** chains plan + implement for several specs, one at a time. Under `STANDARD` or `MAX` each spec runs in a fresh writing sub-agent; your session keeps the CLI transitions, questions, and the Robert/Lars review. Under `ECO`, or in an editor without sub-agents, it stays inline. You still run `/larapilot-review` per story unless `auto_approve=YES`.

---

## Skills

Published by Laravel Boost after `php artisan boost:install`:

| Skill | Role |
| --- | --- |
| `/larapilot-inception` | Product discovery → PRD (includes **Frontend Topology**) |
| `/larapilot-adopt` | Reverse-engineer a PRD from an existing production codebase |
| `/larapilot-spec` | MoSCoW backlog from the PRD |
| `/larapilot-feature` | Mini-inception for one enhancement |
| `/larapilot-bug` | Bug triage → fix spec or rework, with redacted diagnostics |
| `/larapilot-design` | Static HTML mockups from a design system, with style variants to compare |
| `/larapilot-plan` | Technical plan + tasks for a spec |
| `/larapilot-implement` | Code + tests + developer domain docs, one commit per task |
| `/larapilot-review` | Human gate → **DONE** or rework |
| `/larapilot-autopilot` | Batch plan + implement, one fresh context per spec |
| `/larapilot-ship` | Security gate + deploy runbook when the MVP is done |
| `/larapilot-settings` | Persist project modes (effort, backlog, git, testing, account, auth, forges, notifications, …) |
| `/larapilot-release` | Semver release ledger + Gitflow `release/x.y.z` branches (`release_mode=YES`) |
| `/larapilot-project-docs` | Living handbook in `_project_docs/` (`project_docs=YES`) |
| `/larapilot-custom-skill` | Create your own skills under `.larapilot/skills/`, registered with Boost |
| `/larapilot-frontend-companion` | Link an external frontend repo and scan it — driven from Laravel |
| `/larapilot-economics` | **Aurora + Jennifer + Benjamin** — quote, tax, payback, packaging, business plan, client quote (`account` ≠ NONE) |
| `/larapilot-usage` | **Lucille** — time/token ledger, deadlines, Markdown report |
| `/larapilot-backstage` | Publish the repo into a **Backstage** developer portal |
| `/larapilot-tracker` | Mirror the backlog into **Linear · Asana · Jira · Trello · ClickUp · Monday** |

**Inception is a conversation, not a questionnaire.** AskQuestion is used only for the fixed choices Larapilot persists; every answer gets a reaction before the next question; and before any requirement is written, Mark, Jennifer, and Benjamin run at least two **challenge** exchanges on the goal itself (who has this problem and what they do instead, how you will know in 90 days, the riskiest assumption, what would make you stop). Four rounds always happen, legacy rewrites included: **Project Kind**, **Delivery Target**, **Business Model**, and **Operations & support**. A skipped round is recorded as `Not decided`, never as a guess.

Skills load rules from **runtime packs** in `.larapilot/`: `shared-runtime.md` is an index with a mandatory **Read protocol**, and each skill reads only the section files it needs (`runtime-core-*.md`, `runtime-delivery-N.md`, …), each under 15 KB.

---

## Custom skills — your own slash commands

The 20 packaged skills are the base layer. On top of them, each project can keep its **own** Boost skills in `.larapilot/skills/` — committed with the code, registered with Boost automatically, built on the same `larapilot:*` CLI. Good candidates: a pre-deploy GO/NO-GO gate, a compliance check, client release notes, your team's house rules for a Filament resource.

**Example — turn a pre-deploy checklist into `/acme-predeploy-gate`:**

```
/larapilot-custom-skill "A pre-deploy gate we run before every production release:
nothing left in review, doctor healthy, quality green, security scan when it's on, then GO or NO-GO"
```

1. **Zoey** interviews you in three rounds: one skill or a family · slash name and trigger description (the YAML `description` Boost routes on) · personas, runtime packs, and the CLI commands in order.
2. She shows the draft `SKILL.md` — front matter, Shared Runtime, Team, Config & CLI, Workflow, Output Economy — the same shape as a packaged skill.
3. **Sarah** saves it with one command, which validates it, writes the canonical copy, mirrors it into `.ai/skills/` and every agent skill folder that already exists (`.claude/skills/`, `.cursor/skills/`, …), and runs `boost:update`:

```bash
php artisan larapilot:custom-skill-add --name=acme-predeploy-gate --file=.larapilot/tmp-acme-predeploy-gate-SKILL.md
```

```json
{
  "schema": "larapilot/v1",
  "kind": "custom_skill",
  "data": {
    "skill": {
      "name": "acme-predeploy-gate",
      "relative_path": ".larapilot/skills/acme-predeploy-gate/SKILL.md",
      "registered": [".ai/skills/acme-predeploy-gate", ".claude/skills/acme-predeploy-gate"]
    },
    "boost_published": true
  }
}
```

4. Type `/acme-predeploy-gate` before the next deploy. Commit `.larapilot/skills/`; teammates run `php artisan larapilot:custom-skill-list` after `git pull` to register it on their machine.

The draft the example produces:

```markdown
---
name: acme-predeploy-gate
description: "Pre-deploy gate before a production release — GO or NO-GO. Use when someone says deploy, go live, release to production, or asks whether main is safe to ship."
---

# Acme — Pre-deploy gate

## Config & CLI
1. `php artisan larapilot:config-show --only=settings`

## Workflow
1. `larapilot:spec-list --status=REVIEW` and `--status="IN PROGRESS"` — anything listed → NO-GO.
2. `larapilot:doctor` — `data.healthy` false → NO-GO.
3. `larapilot:quality` — an `E_QUALITY` error → NO-GO.
4. When `security_scan` is `YES`: `php artisan checkpoint:scan` — FAIL → NO-GO (or a logged waiver).
5. `larapilot:metrics` — one scope line; then GO / NO-GO, and `larapilot:notify` when notifications are on.

Stop at the first NO-GO. Never deploy, push, tag, or merge from this skill.
```

| Rule | Behavior |
| --- | --- |
| Name | kebab-case, 1–63 chars; `--name` overrides the front matter |
| Front matter | `description` is required. A file with no front matter gets a placeholder one — replace it |
| Overwrite | refused without `--force` |
| Input | `--file=`, `--content=`, or stdin |
| Register again | `custom-skill-list`, `larapilot:update`, and the dashboard **Skills** page re-register every skill on disk |
| Remove | delete `.larapilot/skills/{name}/` **and** its mirrors in `.ai/skills/` and the agent folders, then `php artisan boost:update` — registration never deletes a copy |

Full walkthrough: [Your own skill](https://larapilot.web.ap.it/#example-custom-skill) · Contract: [Custom skills](https://larapilot.web.ap.it/#deep-dive-custom-skills).

---

## What lands in `.larapilot/`

| Path | Purpose |
| --- | --- |
| `config.yaml` | Connector, paths, and project `settings` — committed, so the team shares one mode |
| `docs/PRD.md` | Product Requirements Document — the living product contract |
| `backlog.yaml` · `specs/US-XXX.yaml` | User stories with their status machine |
| `plans/US-XXX-plan.yaml` | Technical plans and tasks per spec |
| `docs/devs/` | **Developer domain docs** — why the code is built the way it is (always on) |
| `docs/review/` · `test-results/` · `security/` · `launch/` · `support/` | Review findings, test evidence, OWASP assessments, launch checks, bug intake |
| `docs/quote.md` | Client quote in the PRD language (Economics) |
| `choices.yaml` | Snapshot of inception answers |
| `decisions.yaml` | Append-only journal of your explicit choices + regression guard (`decision_log`, ON) |
| `code-history.yaml` | Files and line ranges touched per spec/task (`code_history`, OFF) |
| `releases.yaml` | Semver release ledger (`release_mode`) |
| `tracker.yaml` | Spec → tracker issue ids — commit it, or every machine creates duplicates |
| `economics.yaml` · `economics.snapshot.yaml` · `economics.market.yaml` | Economics profile, last computed quote, researched competitors |
| `usage/` | Lucille ledger (`ledger.jsonl`) and schedule |
| `mockups/{spec}/` | Static HTML previews; `styles/{slug}/` for style variants |
| `internal-feedback/{code}.md` | PM/dev comments until **DONE** |
| `skills/{name}/SKILL.md` | Your custom Boost skills |
| `client-materials/` · `legacy/` · `research/` · `brand/` | Inputs for inception, legacy snapshots, analysis and parity reports, brand assets |
| `design-systems/` | Packaged references (Filament, Starter Kit, Bootstrap 5, Tailwind, AdminLTE) — add your own beside them |
| `shared-runtime.md` · `runtime-*.md` · `task-templates.md` · `integrations.md` | Rules and guides the skills read — refreshed by `larapilot:update` |
| `auth.yaml` | Hashed dashboard users — git-ignored |
| `techdocs/` | Generated Backstage TechDocs (after `larapilot:backstage-export --write`) |

`_project_docs/` (repo root) holds the optional handbook when `project_docs=YES`.

---

## Developer domain docs (`docs/devs/`, always on)

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
- **Updated in the same spec that changes the behavior**, inside the task commit. A task is not `task-done` while its domain doc describes the old code, and a domain whose code moved without its doc is a **High** review finding.
- **Never deferred.** Domain docs survive `effort: ECO` — the prose gets terse, the file still gets written.

There is no setting to turn them on: the folder ships with its contract (`README.md`) and skeleton (`TEMPLATE.md`), and `/larapilot-ship` blocks on a stale one. Path key: `paths.dev_docs`. Full contract: `.larapilot/runtime-dev-docs.md`.

**A project with no docs is brought level on the first change, not gradually.** `config-show` reports `data.dev_docs.documented`; when it is `false` and the codebase already has domains, the first spec, fix, or hotfix documents **every** existing domain, commits the backfill on its own (`docs(US-XXX): bring developer domain docs level`), and only then runs its own work. `/larapilot-adopt` does the same at the end of onboarding. Where the original reasoning is unrecoverable, the file says `<!-- TODO: verify -->` rather than inventing a motive.

This is not `_project_docs/` — that optional handbook is a mixed technical/functional manual for the whole project. `docs/devs/` is engineering-only and mandatory.

---

## Project settings

Set with `/larapilot-settings` or `php artisan larapilot:settings-set --key=VALUE`; read with `php artisan larapilot:config-show --only=settings`.

| Group | Keys → default |
| --- | --- |
| Process | `effort` → `STANDARD` (`ECO` · `MAX`) · `backlog` → `STANDARD` (`LEAN` · `GRANULAR`) · `git_mode` → `GITFLOW` (`NO_GITFLOW` · `GITFLOW_PUSH`) · `testing` → `NORMAL` (`MINIMAL` · `BEST`) · `auto_approve` → `NO` |
| Tracking | `lucille` → `YES` (switched off by `ECO` unless you pass `--lucille=YES`) · `decision_log` → `YES` · `code_history` → `NO` |
| Business | `account` → `NONE` (`FREELANCE` · `COMPANY` unlock Economics) |
| Delivery extras | `release_mode` → `NO` · `project_docs` → `NO` |
| Access & security | `comments` → `NO` · `dashboard_auth` → `NO` · `api_auth` → `NO` · `security_scan` → `NO` |
| Integrations | `github` · `gitlab` · `bitbucket` · `azure` · `notifications` · `notify_slack` · `notify_discord` · `notify_telegram` → all `NO` |

```bash
php artisan larapilot:settings-set --effort=ECO --testing=MINIMAL
php artisan larapilot:settings-set --release-mode=YES --comments=YES
```

`ECO` never spawns sub-agents and defers docs theater (README, PDFs, diagrams) — but still updates OpenAPI when an API changes, and still writes the developer domain docs. Playwright/E2E only runs under `testing=BEST`.

### Two configuration layers

| Layer | File | Owns | Changed via |
| --- | --- | --- | --- |
| **Laravel config** | `config/larapilot.php` (publishable) + `.env` | Environment toggles: routes, diagnostics, `LARAPILOT_API_TOKEN`, webhooks and tokens, Backstage and tracker credentials, package defaults | `php artisan vendor:publish --tag=larapilot-config`, env vars |
| **Project workflow** | `.larapilot/config.yaml` (committed) | Per-project `settings`, paths, statuses | `/larapilot-settings` or `larapilot:settings-set` |

The YAML wins for workflow settings; Laravel config only provides the defaults at install. Machine-specific paths (like an external frontend repo) live in `.env`, never in committed YAML.

---

## Dashboard & API (dev/staging)

Available when `APP_ENV` is `local`, `development`, `testing`, or `staging` — **never in production**.

| Page | URL | What you see |
| --- | --- | --- |
| Board | `/larapilot` | Kanban by status, with search and priority / epic / status filters; counts and metrics follow the cards on screen |
| PRD | `/larapilot/prd` | Rendered PRD, decision journal timeline, and a **functional analysis summary** download (one Markdown file in the PRD language, requirements numbered by priority) |
| Inception | `/larapilot/inception` | Discovery choices snapshot |
| Plan | `/larapilot/plan` | Epics, milestones, schedule criticality, dependency-aware Gantt |
| Design | `/larapilot/design` | One navigable mockup index: cover, ordered walk through every flow, style compare with **Use this style**, zip download |
| Settings | `/larapilot/settings` | Every project mode with its options explained |
| Skills | `/larapilot/skills` | Your custom skills — trigger, description, registration status |
| Git | `/larapilot/git` | 12-month contribution heatmap from local history, filterable by developer |
| Usage | `/larapilot/usage` | Lucille's token and hour ledger + Markdown report |
| Economics | `/larapilot/economics` | Pricing simulator (`account` ≠ NONE) |
| Spec | `/larapilot/specs/{code}` | Story, plan, tasks, mockups, decisions, internal feedback |
| API docs | `/larapilot/api/docs` | Swagger UI over the JSON API |
| Docs | `/larapilot/docs` | Delivery loop, packaged skills, persona roster |

### JSON API

| Endpoint | Returns |
| --- | --- |
| `GET /larapilot/api/board` | Metrics and specs grouped by status |
| `GET /larapilot/api/specs` · `/specs/{code}` | Specs with task progress, mockups, feedback (paginated, `?status=`) |
| `POST /larapilot/api/specs/{code}/comments` | Append internal feedback (`comments=YES`) |
| `GET /larapilot/api/prd` | PRD Markdown and heading index |
| `GET /larapilot/api/metrics` | Delivery snapshot + effort timing |
| `GET /larapilot/api/economics` | Economics snapshot; what-if parameters (`?hourly_rate=70&discount_pct=10&tier=premium`) are computed, never stored |
| `GET /larapilot/api/diagnostics` | Read-only runtime snapshot for bug triage |
| `GET /larapilot/api/backstage` · `/backstage/catalog-info.yaml` | Backstage entities and delivery snapshot |
| `GET /larapilot/api/openapi.json` · `/docs` | OpenAPI 3 document and Swagger UI |

Rate limited per IP (`LARAPILOT_API_RATE_LIMIT`, default `120,1`), mutating requests audited to `.larapilot/api-audit.log`, `ETag` / `304` on the read endpoints. Workflow **state** changes only through skills and Artisan — never from the dashboard or the API.

---

## Economics (`account`, NONE by default)

`settings.account` is `NONE` | `FREELANCE` | `COMPANY`. Freelance uses sole-trader regimes (Italian forfettario, IRPEF, autónomo, …); company uses corporate tax plus dividend extraction (SRL, SPA, Ltd, GmbH, C-Corp, …), FY-2026 rates across 38 countries. Either unlocks `/larapilot-economics` and `/larapilot/economics`.

```bash
php artisan larapilot:settings-set --account=FREELANCE
php artisan larapilot:economics-set --country=IT --regime=forfettario_15 --hourly-rate=55
php artisan larapilot:economics-set --product-model=saas --price-monthly=29 --churn=4
```

- **The page reads as a simulator.** It opens on one plain-language answer — what the client pays, what you keep after costs and tax, how long it takes. For a subscription it adds a four-step calculator: monthly price · what one customer leaves you · customers to cover the monthly bills · customers to earn the build back in a year, and when. Hour tables, tax, packaging, scenarios, and competitor prices stay one click away.
- **A console of dropdowns** (rate, margin, discount, team size, regime, how it is sold, price line, scenario, …) recomputes everything server side. Nothing is written from the browser: a simulation prints the `economics-set` command that would make it real.
- **Hours come from the backlog** — planned task hours per spec, story points where no plan exists — and the snapshot refreshes itself whenever specs, plans, the PRD, or inception change.
- **Sold as** `fixed` · `saas` · `ecommerce` · `package` changes how payback is read, never the hours or the build price. On `auto` it follows the Business Model answer from inception.
- **Market research** from Jennifer and Benjamin (`larapilot:economics-market-write`) is plotted, never invented; skipped on a one-off client delivery unless you ask.
- **The maintenance retainer** is priced from the inception answers (delivery target, who runs the server, support window, ship method) from a 12% baseline, and the page shows the arithmetic.
- **The client quote** is written by `/larapilot-economics` in the PRD's own language (`.larapilot/docs/quote.md`, download at `/larapilot/economics/quote.md` or `economics-show --format=quote`), with infrastructure and security chapters derived from the project's real settings. A built-in template covers `en` · `it` · `es` · `fr` · `de` · `pt` · `nl` · `pl` until one is written.

Figures are planning estimates, not tax advice.

---

## Traceability

### Decision journal & regression guard (`decision_log`, ON by default)

Every explicit choice — a fixed-choice answer or a free-text directive like _"the background must be orange"_ — is appended, with a timestamp, to `.larapilot/decisions.yaml`:

```bash
php artisan larapilot:decision-log --topic="background color" --value="orange" --source=chat --skill=larapilot-inception
```

Before a later phase records a **different** value for the same topic, it runs `larapilot:decision-check --topic="background color" --value="red"`. If that contradicts an earlier decision, the skill asks you to confirm — _"on 2026-05-01 you chose **orange**; confirm **red** supersedes it"_ — and re-logs with `--supersedes=<id>`. The file is never rewritten. Turn it off with `--decision-log=NO`.

### Code change history (`code_history`, OFF by default)

```bash
php artisan larapilot:settings-set --code-history=YES
# after each task-done, /larapilot-implement runs:
php artisan larapilot:code-log --spec=US-014 --task=TASK-03 --skill=larapilot-implement
php artisan larapilot:code-history --file=app/Models/Post.php   # where has this file been worked on?
```

---

## Security

| Gate | Default | Turn it on |
| --- | --- | --- |
| **Dashboard auth** — HTTP Basic Auth on the `/larapilot` UI | OFF | `larapilot:dashboard-user add andrea` then `--dashboard-auth=YES` |
| **API token** — bearer token or `X-Larapilot-Token` on `/larapilot/api/*` | enforced when `LARAPILOT_API_TOKEN` is set | set the env var |
| **API auth** — token mandatory; fails closed (`503`) with no token configured | OFF | `--api-auth=YES` |
| **Security scan** — [`andreapollastri/checkpoint`](https://github.com/andreapollastri/checkpoint) in review and pre-ship | OFF | `composer require --dev andreapollastri/checkpoint` then `--security-scan=YES` |

- Dashboard credentials are argon2id/bcrypt hashes in `.larapilot/auth.yaml` (git-ignored, no database, no `User` model); failed sign-ins are rate-limited per IP (`LARAPILOT_DASHBOARD_AUTH_MAX_ATTEMPTS`, default 30/min). The dashboard gate never touches the API or MCP, and the API gate never touches the dashboard.
- Without a token, API reads stay open in the allowed environments but **writes are refused** outside local/development/testing.
- With `security_scan=YES`, `checkpoint:scan` `FAIL` findings block the review (fix them, or log a waiver with `larapilot:decision-log`); `WARN` findings become notes. Larapilot never bundles or runs the scanner while the setting is off.

### Diagnostics (bug triage)

A read-only runtime snapshot — app info, health checks (`storage_writable`, `cache`, `database`, `queue`, `log_file`), and a log tail with **secrets redacted** — that never mutates workflow state.

| Surface | How |
| --- | --- |
| CLI | `php artisan larapilot:diagnostics` (`--lines=`, `--no-logs`) — no token needed |
| MCP | the `diagnostics` tool |
| API | `GET /larapilot/api/diagnostics?lines=100&no_logs=1` — same gate as the rest of the API; `404` when `LARAPILOT_DIAGNOSTICS_ENABLED=false` |

---

## Integrations

All optional, all OFF until configured. Credentials live in `.env` — never in `.larapilot/`. Setup guide: `.larapilot/integrations.md`.

### Forges & notifications

`github` / `gitlab` / `bitbucket` / `azure` add PR/MR URLs through `gh`, `glab`, the Bitbucket Cloud API, or `az` / the Azure DevOps REST API — orthogonal to `git_mode`. Probe with `larapilot:github-status` (and `gitlab-`, `bitbucket-`, `azure-status`). `notifications` plus `notify_slack` / `notify_discord` / `notify_telegram` fan out task, spec, PR, schedule, and ship events through `larapilot:notify` (`LARAPILOT_SLACK_WEBHOOK_URL`, `LARAPILOT_DISCORD_WEBHOOK_URL`, `LARAPILOT_TELEGRAM_BOT_TOKEN` + `_CHAT_ID`).

### Frontend companion — split repo

When **Frontend Topology** is `API + external frontend`, **Laravel stays the only cockpit**: PRD, backlog, plans, and every `/larapilot-*` command run in the backend workspace, and the frontend repo is a linked write target.

```bash
php artisan larapilot:frontend-set --path=/absolute/path/to/fe-repo --stack=React   # writes LARAPILOT_FRONTEND_REPO_PATH to .env
php artisan larapilot:frontend-scan                                                  # stack, tooling, structure, entrypoints
```

UI tasks in a plan carry `repo: frontend`; implement writes under the env-resolved path and commits there. Or run `/larapilot-frontend-companion`. Details: [Frontend companion](https://larapilot.web.ap.it/#deep-dive-frontend-companion).

### Project trackers — Linear, Asana, Jira, Trello, ClickUp, Monday

Mirrors the backlog into the tool the rest of the organisation already uses. **`.larapilot/` stays the source of truth** — the tracker is a window, not a second workflow.

```bash
php artisan larapilot:tracker-status --ping   # provider, status map, credentials check
php artisan larapilot:tracker-push --dry-run  # what would change, no API calls
php artisan larapilot:tracker-push            # backlog → tracker
php artisan larapilot:tracker-pull            # tracker → drift report (read-only)
php artisan larapilot:tracker-pull --apply    # write mapped statuses back
```

| Provider | Auth | Destination | Subtasks | Status maps to |
| --- | --- | --- | --- | --- |
| **Linear** | personal API key | team key | sub-issues | workflow state |
| **Jira** (Cloud, REST v2) | email + API token | project key | subtasks | status, via a workflow transition |
| **Asana** | personal access token | project gid | subtasks | section |
| **Trello** | key + token | board id | checklist items | list |
| **ClickUp** | personal token | list id | subtasks | list status |
| **Monday** | API token | board id | subitems | status-column label |

Stories become issues titled `US-XXX — Title`; plan tasks become **native** subtasks. Push is authoritative; pull is a report and changes the backlog only with `--apply`. Pull never sets a spec to **DONE** (that stays a human review gate) and never changes spec text. Set `LARAPILOT_TRACKER_ENABLED=true`, `LARAPILOT_TRACKER_PROVIDER=…`, and the provider's credentials (`LARAPILOT_LINEAR_API_KEY` / `_TEAM`, `LARAPILOT_JIRA_BASE_URL` / `_EMAIL` / `_API_TOKEN` / `_PROJECT`, …); status maps live in `config/larapilot.php`. Or run `/larapilot-tracker`. Details: [Project trackers](https://larapilot.web.ap.it/#deep-dive-tracker).

### Developer portal — Backstage

Publishes `.larapilot/` into [Backstage](https://backstage.io) — one way; the workspace stays the source of truth.

```bash
php artisan larapilot:backstage-export           # preview the bundle (writes nothing)
php artisan larapilot:backstage-export --write   # catalog-info.yaml + mkdocs.yml + .larapilot/techdocs/
```

`catalog-info.yaml` and `mkdocs.yml` are never overwritten without `--force`; TechDocs pages are regenerated. Set at least `LARAPILOT_BACKSTAGE_OWNER` (also `_SYSTEM`, `_LIFECYCLE`, `_COMPONENT_TYPE`, `_BASE_URL`). For a portal plugin, `GET /larapilot/api/backstage` returns entities and a lean delivery snapshot — call it through the Backstage backend proxy so the API token stays server-side. Or run `/larapilot-backstage`. Details: [Backstage portal](https://larapilot.web.ap.it/#deep-dive-backstage).

### Self-hosted VPS — one server for the whole team

`larapilot:vps-provision` writes a standalone `provision.sh` for an Ubuntu 24.04 / 26.04 LTS server that hosts several Laravel projects for a team working over SSH with Claude Code and their own Claude plan.

```bash
php artisan larapilot:vps-provision              # writes ./provision.sh
php artisan larapilot:vps-provision --with-readme # + the operator guide
scp provision.sh root@<vps>: && ssh root@<vps> 'bash provision.sh'
```

It installs PHP 8.3/8.4/8.5 (an FPM pool per project), MySQL, Redis, Nginx + certbot, Supervisor, Node LTS, Composer, Claude Code, and the `gh` / `glab` / `az` CLIs, then generates:

| Tool | For | Does |
| --- | --- | --- |
| `prj-ai` | admin (root) | `config` · `list` · `add` · `del` · `php` · `user-add` · `user-del` · `deploy` · `rollback` · `preview` · `workspace-init` |
| `prj-work` | developers | login menu → per-developer workspace inside a persistent `tmux` session |
| `prj-token` | developers | save their **own** git token, so commits and PRs are attributed to them |
| `prj-pr` | developers | open a PR/MR on GitHub, GitLab, Bitbucket Cloud, or Azure DevOps |

Deploys are atomic and zero-downtime (a new `releases/` directory, `current` flipped only after every build step succeeds; `prj-ai rollback` flips back). Each developer can get a private preview URL with its own database. Full operator guide: `resources/larapilot/vps/README.md`, or the [Self-hosted VPS](https://larapilot.web.ap.it/#deep-dive-vps) docs.

---

## Artisan CLI & MCP

Skills call these for you — run them by hand for scripting, CI, or debugging. Every command prints one JSON envelope: `{"schema":"larapilot/v1","kind":"…","data":{…}}`, or `"kind":"error"` with a stable `code` (`E_INVALID_INPUT` → exit 2, `E_CONNECTOR` → 3, `E_PRECONDITION` / `E_NOT_FOUND` → 4).

| Area | Commands |
| --- | --- |
| Setup & health | `install` · `update` · `doctor` (`--human`) · `config-show` (`--only=settings,paths,frontend,tracker,dev_docs,backstage,workflow,personas`) · `settings-set` · `quality` (`--fix`) |
| Access | `dashboard-user {list\|add\|remove}` |
| Discovery | `prd-write` · `validate-prd` · `choices-set` · `frontend-set` · `frontend-scan` |
| Backlog | `spec-list` · `spec-add` · `spec-show` (`--task=`, `--fields=`) · `spec-next` · `spec-delete` · `validate-spec` · `spec-comment` |
| Design | `mockup-choose-style US-XXX --style=` |
| Plan & build | `validate-plan` · `spec-plan` · `spec-start` · `task-done` · `spec-review` |
| Review | `spec-approve` (`--force`) · `spec-request-changes` (`--include-feedback`) |
| Traceability | `decision-log` · `decision-check` · `code-log` · `code-history` |
| Metrics & usage | `metrics` · `usage-log` · `usage-report` (`--insights`, `--format=json\|md\|human`) · `schedule-set` |
| Economics | `economics-set` · `economics-show` (`--format=json\|md\|quote`) · `economics-market-write` · `economics-quote-write` |
| Releases | `release-list` · `release-add` · `release-set` · `release-cut` · `release-feature` · `release-sync` · `release-ship` (`--push`) · `release-import` |
| Custom skills | `custom-skill-list` · `custom-skill-add` (`--name=`, `--file=` / `--content=` / stdin, `--force`) |
| Integrations | `github-status` · `gitlab-status` · `bitbucket-status` · `azure-status` · `notify` · `tracker-status` · `tracker-push` · `tracker-pull` · `backstage-export` · `vps-provision` |
| Runtime | `diagnostics` (`--lines=`, `--no-logs`) |

All commands are prefixed `larapilot:`. Release commands need `release_mode=YES` and take `--semver=` (Artisan reserves `--version`); nothing is pushed without `--push`.

The **`larapilot` MCP server** exposes four tools: `BacklogListTool`, `SpecShowTool`, `DiagnosticsTool`, and `RunArtisanTool`, which runs only read and validate commands (`config-show`, `spec-list`, `spec-show`, `spec-next`, `metrics`, `usage-report`, `decision-check`, `code-history`, the forge probes, the three validators, `doctor`, `diagnostics`, `quality`, `frontend-scan`, `backstage-export`, `tracker-status`).

---

## Requirements

- PHP **^8.1** (8.2+ recommended)
- Laravel **^10.49** · **^11.45.3** · **^12** · **^13**
- [Laravel Boost](https://laravel.com/ai/boost) **^1** or **^2** (Composer resolves Boost 1 on Laravel 10/11 and Boost 2 on Laravel 12+; kept current by `larapilot:update`)
- An MCP-capable editor (Claude Code, Cursor, VS Code, …)

On Laravel 10/11 the MCP stack pulls `illuminate/json-schema` — use a recent framework patch (Laravel **11.47+** recommended on 11.x).

Laravel **10** and **11** are past their security-fix window. Composer 2.9+ refuses every `laravel/framework` 10.x/11.x release because open advisories have no patched line (fixes shipped in Laravel **12.60+** / **13**). Larapilot's CI still runs those majors by ignoring only `laravel/framework` advisories on the 10/11 jobs. Apps still on 10/11 that fail `composer update` with *affected by security advisories* need the same ignore (`composer config --json policy.advisories.ignore '["laravel/framework"]'`) or should upgrade to Laravel 12+.

---

## Learn more

- [How it works](https://larapilot.web.ap.it/#deep-dive-how-it-works) — skills, artifacts, CLI, runtime packs
- [Eleven use cases](https://larapilot.web.ap.it/#examples) — new product, adopt an app, Laravel package, legacy porting, feature, bug, frontend companion, tracker sync, team server, SSH & tmux, your own skill
- [Custom skills](https://larapilot.web.ap.it/#deep-dive-custom-skills) — anatomy, validation, lifecycle
- [Developer domain docs](https://larapilot.web.ap.it/#deep-dive-dev-docs)
- [Project settings](https://larapilot.web.ap.it/#deep-dive-settings)
- [Economics](https://larapilot.web.ap.it/#deep-dive-economics)
- [Design systems](https://larapilot.web.ap.it/#deep-dive-design-systems)
- [Team personas](https://larapilot.web.ap.it/#deep-dive-personas)
- [Changelog](CHANGELOG.md)

---

## License

MIT © [Andrea Pollastri](https://web.ap.it)
