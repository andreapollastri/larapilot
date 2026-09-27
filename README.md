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
| A request that may be either — a ticket, a client email | `/larapilot-triage "…"` |
| A change to the PRD that is neither — priorities, scope, a sharper requirement, a decision reversed, an older PRD brought up to date | `/larapilot-prd "…"` |
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
| `/larapilot-triage` | Bug or feature? Classifies a request against the PRD and the backlog, then hands off to `/larapilot-bug` or `/larapilot-feature` |
| `/larapilot-aikido` | Downloads the open security findings of **Aikido** for the repository and hands each one to `/larapilot-triage` to be resolved (`aikido=YES`) |
| `/larapilot-boogle` | Downloads the open errors **Boogle** recorded for the running application, puts together the ones that are one bug, and hands each bug to `/larapilot-triage` (`boogle=YES`) |
| `/larapilot-prd` | Revises the PRD when it is neither — sharpen, re-scope, re-model, re-decide, upgrade — and aligns the stories that cite what changed |
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

**Inception verifies before it scopes, and writes a PRD a spec can be built from.** After the goal challenge, Sebastian runs a **prior-art check** (`prior_art`, ON by default): he states the queries, asks consent, searches GitHub, Packagist, and open-source catalogs for products or packages that already do it, writes `research/prior-art.md` (license, stack, last release, what it covers and lacks, adoption cost), and you record a verdict — `Build anyway`, `Adopt / fork`, `Integrate as dependency`, or `Not checked`. Then the PRD gets its nouns and paths before its features: `## User Journeys` (one per way a persona gets value — the unit a spec is cut from), `## Domain Model` (entities, states, relations, glossary), every `### FR-XXX` with a named actor and verifiable **Done means** bullets, `## Non-Functional Requirements` with a target and a verifier per row, and `## Risks & Assumptions` (riskiest assumption, kill condition, open questions with an owner, the `Not decided` list). Tom runs a ten-point **Definition of Ready**, Mark reads the decisions back in twelve lines, and only then is the PRD written. `validate-prd` reports the new sections as warnings, so older PRDs stay valid.

### Changing the PRD after inception

The PRD changes through three doors, and each one leaves a row in `## PRD Revision History`:

| The change is | Use | What it does to the PRD |
| --- | --- | --- |
| One new capability, or a shipped one that must now behave differently | `/larapilot-feature` | New `FR-XXX`, or the FR edited in place on a change request |
| A defect that shows a requirement was never written | `/larapilot-bug` | A done-means bullet under the parent FR, or an NFR row. Never a "fix FR" |
| Anything else | `/larapilot-prd` | See the revision kinds below |

| Revision kind | Example | Backlog impact |
| --- | --- | --- |
| **Editorial** | Typos, a clearer sentence, a glossary term | None |
| **Sharpen** | Done-means for an FR, a target for an NFR, an open question answered | Criteria added to open stories |
| **Re-scope** | MoSCoW change, In Scope ↔ Future Phases, Delivery Target, an FR retired | Stories created, deferred, or deleted |
| **Re-model** | Entity renamed, state added, journey split | Stories citing the old names |
| **Re-decide** | Business model, ops owner, topology, data store, prior-art verdict | Architecture stories; quote recomputed |
| **Upgrade** | An older PRD gains journeys, domain model, NFRs, risks | None |
| **Pivot** | New vision or target user | `/larapilot-inception`, current PRD as input |

- **Ids are permanent.** `FR-`, `J-`, `NFR-`, and `Q-` ids are never renumbered or reused. A dropped requirement keeps its heading with `MoSCoW: Won't` and a `Retired` line. `validate-prd` warns on `PRD_DUPLICATE_ID` and `PRD_DANGLING_REFERENCE`.
- **The backlog follows.** `php artisan larapilot:prd-impact --ids=FR-004,J-001` lists the stories that cite the ids, with the action each needs: `TODO` re-issued, `PLANNED` re-issued and planned again, `IN PROGRESS` on your consent, `REVIEW` sent back with `spec-request-changes`, `DONE` never reopened. Without `--ids` it traces the whole PRD and lists the Must requirements no story covers.
- **Nothing is written before the readback.** Mark shows before → after per changed item and asks Apply · Revise · Cancel.
- **You edited `PRD.md` by hand?** Run `/larapilot-prd "I edited the PRD by hand"`. It reads the git diff and adds what a hand edit skips: the history row, validation, the inception snapshot, and the backlog check.

Skills load rules from **runtime packs** in `.larapilot/`: `shared-runtime.md` is an index with a mandatory **Read protocol**, and each skill reads only the section files it needs (`runtime-core-*.md`, `runtime-delivery-N.md`, …), each under 15 KB.

---

## Custom skills — your own slash commands

The 22 packaged skills are the base layer. On top of them, each project can keep its **own** Boost skills in `.larapilot/skills/` — committed with the code, registered with Boost automatically, built on the same `larapilot:*` CLI. Good candidates: a pre-deploy GO/NO-GO gate, a compliance check, client release notes, your team's house rules for a Filament resource.

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
| Remove | delete the folder from the dashboard **File manager** — it removes the mirrors in `.ai/skills/` and the agent folders that still match the skill — then `php artisan boost:update`. By hand: delete `.larapilot/skills/{name}/` **and** its mirrors. Registration never deletes a copy |

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
| `choices.yaml` | Snapshot of inception answers (kinds, targets, prior-art verdict, success signal, kill condition, ops) |
| `decisions.yaml` | Append-only journal of your explicit choices + regression guard (`decision_log`, ON) |
| `code-history.yaml` | Files and line ranges touched per spec/task (`code_history`, OFF) |
| `releases.yaml` | Semver release ledger (`release_mode`) |
| `tracker.yaml` | Spec → tracker issue ids — commit it, or every machine creates duplicates |
| `economics.yaml` · `economics.snapshot.yaml` · `economics.market.yaml` | Economics profile, last computed quote, researched competitors |
| `usage/` | Lucille ledger (`ledger.jsonl`) and schedule |
| `mockups/{spec}/` | Static HTML previews; `styles/{slug}/` for style variants |
| `internal-feedback/{code}.md` | PM/dev comments until **DONE** |
| `skills/{name}/SKILL.md` | Your custom Boost skills |
| `client-materials/` · `legacy/` · `research/` · `brand/` | Inputs for inception, legacy snapshots, prior-art / reference-product / parity reports, brand assets |
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
| Discovery | `prior_art` → `YES` (Sebastian's existing-solutions search at inception, consent asked before every search) |
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
| Board | `/larapilot` | Kanban by status, with search and priority / epic / status filters; counts and metrics follow the cards on screen. **Download status (.md)** saves the board as it stands, filters included |
| PRD | `/larapilot/prd` | Rendered PRD with a **search** that looks in the PRD and nowhere else, decision journal timeline, **Download PRD (.md)**, and a **functional analysis summary** download (one Markdown file in the PRD language, requirements numbered by priority) |
| Inception | `/larapilot/inception` | Discovery choices snapshot |
| Plan | `/larapilot/plan` | Epics, milestones, schedule criticality, dependency-aware Gantt |
| Design | `/larapilot/design` | Every screen first, as a card. A click opens that mockup as a site you browse, with **All screens** to come back; prev / next through every flow, style compare with **Use this style**, zip download |
| Settings | `/larapilot/settings` | Every project mode with its options explained |
| Skills | `/larapilot/skills` | Every skill the agents of the project can run, whoever brought it — the project, Larapilot, another package, Laravel Boost, a hand that dropped it into the folder of an agent — with which agent has it. Click one to **read it**. Under them, **what the agents are told**: `CLAUDE.md`, `AGENTS.md`, and the rules around them, one part for each author |
| File manager | `/larapilot/files` | The five material folders — `brand/`, `client-materials/`, `design-systems/`, `legacy/`, `skills/` — each with what it is for. Browse the tree, preview, read a PDF in the page, download, upload files or a whole folder (structure kept), rename, delete. A sixth folder, **Project**, shows the application itself, read only |
| Git | `/larapilot/git` | 12-month contribution heatmap, every branch measured against the branch it is heading for, and the history drawn as a graph with each commit on the branch it was made on. Filterable by developer |
| Usage | `/larapilot/usage` | Lucille's token and hour ledger + Markdown report |
| Security | `/larapilot/security` | What Aikido found in the repository, the most severe first, with what was decided about each finding and the verdict of the ship gate. Always in the menu; with `aikido` off it says what Aikido is and how to connect it |
| Errors | `/larapilot/errors` | What the running application threw, as Boogle recorded it: one row for each bug, how many times it was thrown day by day, and what was decided about it. Always in the menu; with `boogle` off it says what Boogle is, how to connect it, and invites the project to use it |
| Economics | `/larapilot/economics` | What the project costs, what the client pays, what is left for you — every sum written as a receipt (`account` ≠ NONE) |
| Spec | `/larapilot/specs/{code}` | Story, plan, tasks, mockups, decisions, internal feedback. **Download spec (.md)** saves all of it, tasks included, in one file |
| API docs | `/larapilot/api/docs` | Swagger UI over the JSON API |
| Docs | `/larapilot/docs` | Delivery loop, packaged skills, persona roster |

The dashboard follows the system theme; pin **light** or **dark** from the sidebar. Every page works on a phone.

### File manager

`/larapilot/files` manages what you hand the skills before they start:

| Folder | What it is for |
| --- | --- |
| `brand/` | Logo, palette, typography, and the brand guide |
| `client-materials/` | Briefs, analyses, and documents supplied by the client |
| `design-systems/` | Visual references and tokens the mockups are built on |
| `legacy/` | Snapshots of the old system to port or migrate |
| `skills/` | Your custom skills, one folder per slash command |
| `./` — **Project** | The Laravel application itself: code, config, routes, tests. **Read only** |

- **Adding, renaming, and deleting** files and folders work in the five material folders. **Project is read only**: you browse, preview, and download; the routes that write do not know the folder and the service refuses a write to it.
- **Folders that start with a dot are left out of Project** — `.git`, `.larapilot`, `.github`, `.idea`, at any depth. Files that start with a dot are shown. `vendor/` and `node_modules/` can be opened and are left out of the count.
- **Credentials show their keys, never their values.** `.env`, `.env.*`, `auth.json`, `.npmrc`, `.netrc`, `.pgpass` are shown with every value replaced by `*****************`, on screen and in the download; a template such as `.env.example` is shown as it is. A key or certificate is one line of asterisks; a database (`.sqlite`, `.db`) is listed and neither shown nor downloaded.
- **A PDF is read in the page**: one page or two side by side, zoom, page jump, full screen. The reader is PDF.js from cdnjs, checked against its hash; where it cannot load, the file opens in its own tab.
- **Upload a folder** and it keeps its structure; an existing file is kept unless **Replace existing files** is on. One file is limited by `LARAPILOT_FILE_MANAGER_MAX_UPLOAD_KB` (default 50 MB) and by PHP's `upload_max_filesize` / `post_max_size`, whichever is lower.
- **Local by default.** The file manager is open in `local`, `development`, and `testing`. On any other environment (`staging`) it is served only when `dashboard_auth` is `YES` — client documents and legacy snapshots stay behind a sign-in.
- Nothing outside the six folders can be reached, a symlink is never followed, and an uploaded `.html`, `.js`, or `.svg` is never run in the dashboard. `LARAPILOT_FILE_MANAGER=false` removes the page.

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

- **The page is written for someone who has never read a balance sheet.** The answer comes before the detail, no figure appears without the sum that produced it, and a word of finance appears only in the glossary.
  - **The short answer** — one sentence and four figures: what the client pays, what you keep (and how much of every 100 of the price that is), the time to deliver, the upkeep each year.
  - **Two receipts, each under a bar drawn to scale** — *how the price is built* (work + running costs + margin − discount = client price, + VAT = what the client pays) and *where the money goes* (client price − running costs − tax and contributions − accountant = what you keep).
  - **A subscription, one step at a time** — what one customer leaves, what the product costs every month, how many customers it takes with the division written out, and a chart of **when the money comes back** that reads any month by pointer or keyboard and tells the same story in sentences. Three forecasts side by side, each with a verdict in words.
  - **Words used here** — VAT, margin, net, fixed costs, churn, break-even, and the rest, each with the figure it has in this project.
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
| **Aikido** — the findings of [Aikido](https://www.aikido.dev/) for the repository, in triage and at the ship gate | OFF | credentials in `.env`, then `--aikido=YES` |
| **Boogle** — the errors [Boogle](https://boogle.web.ap.it/) recorded for the running application, in triage and on the dashboard | OFF | address and token in `.env`, then `--boogle=YES` |

- Dashboard credentials are argon2id/bcrypt hashes in `.larapilot/auth.yaml` (git-ignored, no database, no `User` model); failed sign-ins are rate-limited per IP (`LARAPILOT_DASHBOARD_AUTH_MAX_ATTEMPTS`, default 30/min). The dashboard gate never touches the API or MCP, and the API gate never touches the dashboard.
- Without a token, API reads stay open in the allowed environments but **writes are refused** outside local/development/testing.
- The dashboard **file manager** is stricter than the rest of the UI: outside local/development/testing it answers `404` — reads and writes alike — until `dashboard_auth` is `YES`.
- With `security_scan=YES`, `checkpoint:scan` `FAIL` findings block the review (fix them, or log a waiver with `larapilot:decision-log`); `WARN` findings become notes. Larapilot never bundles or runs the scanner while the setting is off.

### Aikido

[Aikido](https://www.aikido.dev/) scans the repository on its side — dependencies, code, secrets, infrastructure. Larapilot runs no scanner and installs nothing: it **reads what Aikido found** over the public REST API and brings it into the workflow.

```dotenv
LARAPILOT_AIKIDO_CLIENT_ID=
LARAPILOT_AIKIDO_CLIENT_SECRET=
LARAPILOT_AIKIDO_REGION=eu        # eu · us · au · me
LARAPILOT_AIKIDO_REPOSITORY=      # id or name in Aikido; empty = found from the git remote
LARAPILOT_AIKIDO_FAIL_ON=high     # critical · high · medium · low · none
```

```bash
php artisan larapilot:settings-set --aikido=YES
php artisan larapilot:aikido-status                  # setting, credentials, repository, last scan
php artisan larapilot:aikido-issues --new --report   # what nobody decided about; writes docs/security/aikido.md
php artisan larapilot:aikido-link 24 --spec=US-012   # the spec that fixes it
php artisan larapilot:aikido-link 40 --waive --reason="Internal tool, never distributed."
php artisan larapilot:aikido-issues --gate           # exit 1 when the gate fails — for CI
php artisan larapilot:aikido-scan                    # ask Aikido to scan again
```

- **Create the credentials** in Aikido under *Settings → Integrations → Public REST API*, with the `issues:read` and `repositories:read` scopes (`repositories:write` to ask for a scan). The repository has to be connected in Aikido, through the git provider.
- **`/larapilot-aikido`** downloads the open findings, shows the new ones, and hands each one you pick to **`/larapilot-triage`** with an *Aikido finding* block. Triage measures it against the PRD like any request — a known vulnerability in shipped code is a bug, a requirement gap when no requirement names security — and `/larapilot-bug` writes the fix spec. The link between finding and spec is recorded.
- **The ship gate** stops on an open finding at `LARAPILOT_AIKIDO_FAIL_ON` or above that was not waived. A finding in the backlog is not fixed: it counts until Aikido no longer reports it, after the fix is merged and scanned.
- **A waiver needs a reason**, in a sentence, and only the user gives it.
- **What is kept**: `.larapilot/aikido.yaml` holds the decisions — ids, the spec, the reason — and is meant to be committed. The credentials stay in `.env`; the access token lives in the cache and is never written to a file of the project.

### Boogle

[Boogle](https://boogle.web.ap.it/) is a self-hosted exception tracker and uptime monitor: the application sends what it throws there, with [`andreapollastri/boogle-client`](https://github.com/andreapollastri/boogle). Larapilot **reads the open errors back** over the admin API and brings each bug into the workflow.

```dotenv
LARAPILOT_BOOGLE_URL=https://boogle.example.com   # empty = taken from BOOGLE_SERVER
LARAPILOT_BOOGLE_TOKEN=                            # the token of an admin user of Boogle
LARAPILOT_BOOGLE_PROJECT=                          # id or title; empty = found from BOOGLE_PROJECT_KEY, then APP_URL
```

```bash
php artisan larapilot:settings-set --boogle=YES
php artisan larapilot:boogle-status                           # setting, address, token, project
php artisan larapilot:boogle-errors --new --report            # what nobody decided about; writes docs/support/boogle.md
php artisan larapilot:boogle-link BUG12 --spec=US-012         # the spec that fixes the bug that code belongs to
php artisan larapilot:boogle-link BUG21 --ignore --reason="The mail provider was down on its side."
php artisan larapilot:boogle-resolve BUG12                    # once the fix is released: closes it in Boogle
```

- **One entry for each bug.** Boogle keeps a row for each time an exception is thrown. Larapilot puts together the rows that share the exception, the file, and the line, and says how many times and on how many routes. A file of the server (`/home/forge/…/releases/…/app/Services/X.php`) is read as the file of the repository it is.
- **`/larapilot-boogle`** downloads the open errors, shows the ones nobody decided about, and hands each bug you pick to **`/larapilot-triage`** with a *Boogle error* block. `/larapilot-bug` then writes the fix spec, with a test that throws the same exception before the fix.
- **A decision is about the bug**, not about one time it was thrown: the next time it happens, under a code nobody has seen, it is not handed over again. `.larapilot/boogle.yaml` holds the decisions and is meant to be committed.
- **Back after the fix.** An error closed with `boogle-resolve` and thrown again is shown as such, first in the list: the fix did not hold.
- **Personal data stays in Boogle.** The user, the query string, and the payload of a request are never read into a file, a report, the cache, or the chat. Larapilot keeps the exception, the message with addresses masked, the file and line, the method and the path — with ids and tokens in the path replaced by `{id}` and `{token}`.
- **Writing to Boogle is asked for.** `boogle-resolve` is the only command that writes there; it runs when you say so and is not allowed through the MCP tool. The token reads every project of that Boogle — Boogle gives tokens to users, not to projects — so it lives in `.env`.

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
| Discovery | `prd-write` · `validate-prd` · `prd-impact` (`--ids=`) · `choices-set` · `frontend-set` · `frontend-scan` |
| Backlog | `spec-list` · `spec-add` · `spec-show` (`--task=`, `--fields=`) · `spec-next` · `spec-delete` · `validate-spec` · `spec-comment` |
| Design | `mockup-choose-style US-XXX --style=` |
| Plan & build | `validate-plan` · `spec-plan` · `spec-start` · `task-done` · `spec-review` |
| Review | `spec-approve` (`--force`) · `spec-request-changes` (`--include-feedback`) |
| Traceability | `decision-log` · `decision-check` · `code-log` · `code-history` |
| Metrics & usage | `metrics` · `usage-log` · `usage-report` (`--insights`, `--format=json\|md\|human`) · `schedule-set` |
| Economics | `economics-set` · `economics-show` (`--format=json\|md\|quote`) · `economics-market-write` · `economics-quote-write` |
| Releases | `release-list` · `release-add` · `release-set` · `release-cut` · `release-feature` · `release-sync` · `release-ship` (`--push`) · `release-import` |
| Custom skills | `custom-skill-list` · `custom-skill-add` (`--name=`, `--file=` / `--content=` / stdin, `--force`) |
| Security | `aikido-status` · `aikido-issues` (`--new`, `--severity=`, `--type=`, `--report`, `--gate`) · `aikido-link` (`--spec=`, `--waive --reason=`, `--forget`) · `aikido-scan` |
| Errors | `boogle-status` · `boogle-errors` (`--new`, `--kind=error\|outage`, `--limit=`, `--report`) · `boogle-link` (`--spec=`, `--ignore --reason=`, `--forget`) · `boogle-resolve` (`--status=FIXED\|DONE`, `--comment=`) |
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
