---
name: larapilot-plan
description: Creates a detailed technical implementation plan for a Larapilot spec. Use when the user wants to plan a spec, break down a feature, create tasks, or prepare development. Triggers include "plan US-005", "break this down", "how do we build this". Pass spec code (US-XXX) or auto-select the next TODO spec.
---

# Larapilot — Spec Planning

Produce a detailed implementation plan for one spec and persist it via the CLI.

## Shared Runtime

Read `.larapilot/shared-runtime.md` — including **Sub-agents** (optional explore in Stage 1).

Read `.larapilot/task-templates.md` — copy task body structures (TASK-00 bootstrap, entity, non-entity, test, fix).

## Output Economy

**Split** — see `larapilot-plan` in shared-runtime. Team brief: 1–3 sentences per agent. Chat between stages: status and blockers only. `plan_body` and task bodies stay detailed execution contracts.

## The Team

| Agent            | Role                                                                                                                 |
| ---------------- | -------------------------------------------------------------------------------------------------------------------- |
| 🤖 **Zoey**      | AI Guru — sharpens user intent, output economy, sub-agent orchestration, session/credit risk *(every skill)*         |
| 🔎 **Tom**       | Requirements Analyst — acceptance criteria and spec fidelity                                                         |
| 📐 **John**      | Architect — Architecture Standards: APIs, queues, DTOs, logging, tech debt, OpenAPI/docs per delivery target         |
| 💡 **Sebastian** | Innovator — integration options, competitor data-porting paths, vendor evaluation when spec touches external systems |
| 🔗 **Matt**      | Integration Manager — API/service wiring tasks with Alex, John, Elise                                                |
| 🌍 **Emily**     | Translator — locale files, currency, timezone, country-target UX _(with Violet)_                                     |
| 💰 **Aurora**    | FinOps Expert — infra/security/marketing budget per PRD; privilege security spend                                    |
| ⚖️ **Violet**    | Legal Expert — privacy/legal tasks: cookie/ToS, retention, anonymization, opt-out                                    |
| 📈 **Emma**      | SEO — URL paths, breadcrumbs, robots/sitemap/llms.txt, Analytics/SEM                                                 |
| 💬 **Lauren**    | Social Media Manager — marketing tasks (newsletter, campaigns, SEM) with Emma, Elise, Aurora                         |
| 🎨 **Elise**     | UX Designer — mockups, **mobile-first responsive**, WCAG 2.2 AA, **favicon.svg, logo, OG/social assets**             |
| ✨ **Joe**       | Frontend Expert — Vite/JS architecture, animations, client performance tasks                                         |
| 📱 **Ricky**     | App Developer — mobile shells, device permissions, Flutter/native platform tasks                                     |
| 📝 **Albert**    | Tech Writer — OpenAPI, README, diagrams, doc-site and manual deliverables                                          |
| ✍️ **Marika**    | Copywriter — copy tasks for views, notifications, `lang/` strings                                                    |
| 👾 **Andrew**    | Laravel Expert — idiomatic Laravel patterns, package recommendations, anti-pattern review                            |
| 🔄 **Sabrine**   | Legacy Porting Specialist — parity/migration tasks, DB/assets porting, content scraping mapped to `legacy-parity.md` |
| 🔧 **Alex**      | Full-Stack Developer                                                                                                 |
| 🧪 **Anne**      | Test Architect — **multi-viewport responsive UI tests**, Pest strategy                                               |

## Config & CLI

1. `php artisan larapilot:config-show`
2. `php artisan larapilot:spec-show {code}` OR `php artisan larapilot:spec-next --status=TODO`
3. `php artisan larapilot:validate-plan {code} --file=...`
4. `php artisan larapilot:spec-plan {code} --file=...`

## Workflow

### Stage 0 — Select spec

- With code argument → `spec-show`
- Without argument → `spec-next`
- Free-text descriptions → route to `larapilot-spec` first

### Stage 1 — Load context (parallel)

From `data.workdir` (codebase) and `data.project_root` (artifacts):

- PRD (`paths.prd`) — read delivery target and scope boundaries
- **Client materials** (`paths.client_materials`) — mandatory when populated; cite in task notes
- **Legacy** (`paths.legacy`) + **`{paths.research}/legacy-parity.md`** — when rewrite/port; map tasks to parity rows
- **Reference products** (`paths.research/reference-products/`) — when spec traces to deepsearch findings
- Mockups (`paths.mockups/{code}/`) if they exist
- Relevant Laravel code: models, migrations, routes, tests
- Boost `Database Schema` for data model context
- Boost `Search Docs` for Laravel/package patterns

#### Sub-agent (optional — large or unfamiliar codebase)

When `data.workdir` has substantial existing code and the editor has a sub-agent tool, launch one **readonly explore sub-agent** (synchronous; e.g. Cursor `explore`, Claude Code `Explore` — see **Type mapping** in shared-runtime) before Stage 2. When **`{paths.legacy}`** is populated, include it in the explore scope alongside `data.workdir`. Parent still reads PRD and mockups directly. **Inline fallback** — no sub-agent tool: the parent explores the codebase itself in Stage 1, using the handoff prompt below as a checklist.

Handoff prompt:

```text
Larapilot plan context — {code}
workdir: {data.workdir absolute}
Spec title: {data.spec.title}
Acceptance criteria (summary): {from data.spec.body}

Map: Eloquent models, migrations, routes, policies, tests, frontend stack (Blade/Livewire/Inertia/Vue/Filament) touching this feature. List gaps vs acceptance criteria. Bullet summary only — no file edits. Parent writes the plan.
```

Parent merges explore output into planning; only parent calls `validate-plan` and `spec-plan`.

### Stage 2 — Team Brief + Plan

Show a compact team brief (1-3 sentences per agent), then write the plan payload.

Temp file: `.larapilot/tmp-payload-{code}-plan.json`

```json
{
    "plan_body": "## Technical Solution\n...\n\n## Git & Branching\n- Branch: feature/US-XXX-short-desc\n- TASK-00: bootstrap + internal PR\n- Per task: one commit, push, update PR\n\n## Test Data Strategy\n- Factories + seeders for every entity\n- Demo volumes: ...\n\n## Test Strategy\n...",
    "tasks": [
        {
            "id": "TASK-00",
            "title": "Bootstrap feature branch and internal PR",
            "body": "## Description\n...\n\n## Git Deliverables\n- Commit: chore(US-XXX): TASK-00 bootstrap feature branch\n...",
            "type": "Impl",
            "status": "TODO",
            "dependencies": []
        },
        {
            "id": "TASK-01",
            "title": "...",
            "body": "## Description\n...\n\n## Files Involved\n- app/Models/...\n\n## Test Data\n- [ ] Factory + seeder updated\n\n## Git Deliverables\n- Commit: feat(US-XXX): TASK-01 ...\n\n## Completion Criteria\n- [ ] ...",
            "type": "Impl",
            "status": "TODO",
            "dependencies": ["TASK-00"]
        }
    ]
}
```

Validate, then `spec-plan`. Delete temp file after CLI exits.

## Task body templates

Use `.larapilot/task-templates.md` — do not invent ad-hoc task shapes.

| Template            | When                                                                           |
| ------------------- | ------------------------------------------------------------------------------ |
| **TASK-00**         | Always first — branch `feature/US-XXX-*`, push, open internal PR to `develop`  |
| **Entity task**     | New/changed Eloquent model — migration + factory + seeder in the **same task** |
| **Non-entity Impl** | Routes, UI, services — `## Test Data` = `N/A`                                  |
| **Test task**       | Anne — reuse factories; `test(US-XXX): TASK-NN` commit                         |
| **Fix / evolutiva** | Rework — same Git + factory/seeder rules when schema changes                   |

Every **Impl** and **Fix** task body MUST include:

- `## Git Deliverables` — commit message, push target, PR update line
- `## Test Data` — factory/seeder checklist, or explicit `N/A`
- `## Completion Criteria` — checkboxes (auto-ticked by `task-done`)

`plan_body` MUST include `## Git & Branching` and `## Test Data Strategy` sections.

## Laravel Planning Rules

- John MUST apply **Architecture Standards** and **Multi-tenancy** comparison from shared-runtime when spec touches SaaS/workspaces; plan Gitflow branch name `feature/US-XXX-*`, semver/CHANGELOG tasks, `security.txt` + `SECURITY.md`, CI pipeline gates, queues, DTOs, OpenAPI
- **Alex** plans **factory + seeder tasks** for every new/changed Eloquent model (domain-meaningful Faker data, states, relationships, coherent `DatabaseSeeder`); factory/seeder updates ship in the same task as migrations — never deferred
- **Alex + Jack** plan **per-task Git discipline**: each task body ends with commit message template (`type(US-XXX): TASK-NN …`), push, and internal PR update toward `develop`; no batched multi-task commits
- Plans must satisfy the full spec — do not trim scope to MVP unless the PRD delivery target is MVP
- Aurora flags cost implications of infra, **security tooling**, and **marketing/SEM** spend — per Budget Sensitivity; security is never the first recommended cut; coordinate with Lars and Violet on security budget line items
- Sebastian proposes integration tasks when the spec references external APIs, data migration, or third-party vendors; when the spec covers **competitor data porting**, he MUST plan concrete import tasks (competitor format mapping, CSV/API importers, validation and dry-run) and lock-in-free export tasks
- **Legacy specs:** **Sabrine** plans parity verification tasks per `legacy-parity.md` row; plan migration/ETL tasks with dry-run, checksum/row-count verification, and rollback; explore `{paths.legacy}` when mapping behavior; never plan feature or content drops without PRD **Out of Scope**
- **Andrew** reviews plan for Laravel idioms — prefer first-party/Spatie/Filament solutions; flag bespoke abstractions; cite authoritative sources when recommending patterns
- **Joe** plans frontend tasks when spec needs animations (Three.js/CSS) or client performance budgets
- **Ricky** plans mobile/hybrid tasks when spec needs app shells, device APIs, store release, or PWA device permissions
- **Albert** plans documentation tasks — OpenAPI updates, README/runbook sections, diagrams, PDF manual chapters
- **Marika** plans explicit copy tasks — Blade views, Filament labels, notifications, `lang/` files
- **Matt** owns integration **delivery tasks**: HTTP clients, webhooks, OAuth, queue sync, `.env.example` keys, `Http::fake()` tests, integration README — coordinates with Alex and John
- **Emily** plans i18n/l10n tasks when spec touches locales: `lang/` files, currency display, timezone prefs, hreflang with Emma, cultural copy review with Violet
- New packages follow the **Vendor & Package Policy** in shared-runtime: Laravel first-party → **Spatie** → other vetted vendors; for **admin/control panel** and authenticated dashboard specs, honor the panel route recorded in the PRD — if none is recorded, **ask the user** (Filament vs [Laravel Starter Kit](https://laravel.com/starter-kits) vs custom, never assume), recommending the best fit for the specific case and the option closest to the project mockups — always verify maintenance, compatibility, and security before adding a dependency
- Apply **Laravel Scaffolding Defaults** from shared-runtime unless the PRD opts out: auth specs get Fortify 2FA + `Password::defaults()` + **Socialite** for SSO; new models/migrations use UUID primary keys; greenfield auth uses Argon2id; local-dev tasks honor the **local dev method recorded in the PRD** — if none is recorded, **ask the user** (Sail, Herd, not defined yet, or other; never assume Sail); plan Sail/Herd scaffold tasks only when the PRD chose that method; may document **127001.it** URLs when the PRD calls for them
- When a spec touches newsletter, analytics, error monitoring, or S3: Sebastian plans the PRD-chosen integration; security-audit specs: **Aikido** first when Tracked/Forge, plus **checkpoint** as local/CI complement
- **Jack** plans deploy, edge, cloud, and observability tasks per **choices recorded in the PRD** — if deploy, edge, or cloud is missing, **ask the user** (never assume Cipi, Cloudflare, or AWS); plan Cipi/Forge/AWS/Cloudflare scaffold tasks only when explicitly chosen; also plans **Gitflow** workflow, **CI/CD** scaffold, and **release/x.y.z** + CHANGELOG bump tasks
- **Lars** plans `public/.well-known/security.txt` and root `SECURITY.md` when missing
- Anne defines **Testing standards** per delivery target; every public API route gets a feature test; add **accessibility** checks (Pest + axe or Lighthouse CI) on public UI specs
- **Elise** plans **mobile-first** UI/mockup tasks — mobile screen primary, desktop enhancement, responsive README contract (breakpoints, nav pattern)
- **Anne** plans **responsive UI test tasks** for every UI spec: viewport matrix (375 / 768 / 1280 px minimum), mobile nav assertions, critical journeys at multiple widths, axe at mobile viewport — interleaved with implementation, not at ship only
- Violet adds full **Privacy & Legal Compliance** tasks when the spec processes personal data (cookie policy, ToS, retention, anonymization, opt-out, subprocessors)
- For public-facing specs: **Emma** URL/robots/sitemap/llms; **Elise** UI + WCAG + **favicon.svg, logo, OG 1200×630** (if client assets missing); **Violet** a11y legal; **Lauren** marketing using Elise social assets
- Prefer Laravel conventions: Eloquent, Form Requests, Policies, Service classes, Events/Listeners when appropriate
- Include Pest/PHPUnit tasks interleaved with implementation (not all tests at the end)
- For UI specs: Anne MUST define e2e/responsive strategy using the project's test stack — **Mobile First test contract** from Elise's mockup README (viewports, nav, orientation)
- For UI that needs mockups: invoke `larapilot-design` or generate inline to `.larapilot/mockups/{code}/`
- Task bodies are execution contracts for smaller models: use templates from `.larapilot/task-templates.md` — Objective (Description), Files, Steps, Test Data, Git Deliverables, Completion Criteria

## Rework Mode

When `data.spec.rework` is true or body contains `## Rework Feedback`:

- Preserve existing DONE tasks
- Add `type: Fix` tasks for each feedback bullet
- Augment `plan_body` with a Rework note
