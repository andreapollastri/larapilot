---
name: larapilot-plan
description: Creates a detailed technical implementation plan for a Larapilot spec. Use when the user wants to plan a spec, break down a feature, create tasks, or prepare development. Triggers include "plan US-005", "break this down", "how do we build this". Pass spec code (US-XXX) or auto-select the next TODO spec.
---

# Larapilot — Spec Planning

Produce a detailed implementation plan for one spec and persist it via the CLI.

## Shared Runtime

Read `.larapilot/shared-runtime.md` (core — **Project Settings**, **Sub-agents**), then `.larapilot/runtime-delivery.md` (architecture, Git/TASK-00, factories/seeders, testing gates, scaffolding defaults, vendor policy, docs). When the spec has UI, also read `.larapilot/runtime-ux.md` (mobile-first, a11y, brand, SEO).

Read `.larapilot/task-templates.md` — copy task body structures gated by `data.settings`.

## Output Economy

**Split** — see `larapilot-plan` in the shared-runtime table. Team brief: 1–3 sentences per agent. Chat between stages: status and blockers only. `plan_body` and task bodies stay detailed execution contracts.

## The Team

🤖 Zoey · 📒 Lucille · 🔎 Tom · 📐 John · 🗄️ Mike · 💡 Sebastian · 🔗 Matt · 🌍 Emily · 💰 Aurora · ⚖️ Violet · 📈 Emma · 💬 Lauren · 🎨 Elise · ✨ Joe · 📱 Ricky · 📝 Albert · ✍️ Marika · 👾 Andrew · ⌨️ Sarah · 🔄 Sabrine · 🔧 Alex · 🧪 Anne — roles in the shared-runtime roster. Mike owns data/schema tasks; **Sarah** owns Git mechanics (conflicts/rebase), CLI, forge automation, CI pipeline scripts, and Linux/server scripting whenever those surfaces appear (partner Jack on gates/deploy).

## Config & CLI

1. `php artisan larapilot:config-show` — **read `data.settings`** and scale Git/Test Strategy accordingly
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

- PRD (`paths.prd`) — read delivery target and scope boundaries; when topology is **`API + external frontend`**, read `data.frontend` from `config-show` and run `larapilot:frontend-scan` if not done recently
- **Client materials** (`paths.client_materials`) — mandatory when populated; cite in task notes
- **Legacy** (`paths.legacy`) + **`{paths.research}/legacy-parity.md`** — when rewrite/port; map tasks to parity rows
- **Reference products** (`paths.research/reference-products/`) — when the spec traces to deepsearch findings
- Mockups (`paths.mockups/{code}/`) if they exist
- Relevant Laravel code: models, migrations, routes, tests
- Boost `Database Schema` for data model context; `Search Docs` for Laravel/package patterns

#### Sub-agent (optional — large or unfamiliar codebase)

When `settings.effort` is **`ECO`**, **never spawn an explore sub-agent** — the parent explores inline only.

Otherwise, when `data.workdir` has substantial existing code and the editor has a sub-agent tool, launch one **readonly explore sub-agent** (synchronous; see **Type mapping** in shared-runtime) before Stage 2. When **`{paths.legacy}`** is populated, include it in the explore scope alongside `data.workdir`. Parent still reads PRD and mockups directly. **Inline fallback** — no sub-agent tool (or `ECO`): the parent explores the codebase itself in Stage 1, using the handoff prompt below as a checklist.

Handoff prompt:

```text
Larapilot plan context — {code}
workdir: {data.workdir absolute}
Spec title: {data.spec.title}
Acceptance criteria (summary): {from data.spec.body}

Map: Eloquent models, migrations, routes, policies, tests, frontend stack (Blade/Livewire/Inertia/Vue/Filament) touching this feature. List gaps vs acceptance criteria. Bullet summary only — no file edits. Parent writes the plan.
```

Parent merges explore output into planning; only the parent calls `validate-plan` and `spec-plan`.

### Stage 2 — Team Brief + Plan

Show a compact team brief (1–3 sentences per agent), then write the plan payload.

Temp file: `.larapilot/tmp-payload-{code}-plan.json`

```json
{
    "plan_body": "## Technical Solution\n...\n\n## Git & Branching\n- Mode: {from settings.git_mode}\n- Branch/PR/push rules per Git Workflow in runtime-delivery.md\n\n## Test Data Strategy\n- Factories + seeders for every entity\n- Demo volumes: ...\n\n## Test Strategy\n- Bar: {from settings.testing} — no Playwright/E2E unless BEST\n...",
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

Validate, then `spec-plan`. Delete the temp file after the CLI exits.

## Task body templates

Use `.larapilot/task-templates.md` — do not invent ad-hoc task shapes.

| Template            | When                                                                                                                                                                          |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **TASK-00**         | First task **only when `git_mode` is `GITFLOW` or `GITFLOW_PUSH`** — branch `feature/US-XXX-*` from `develop`; **push + internal PR only under `GITFLOW_PUSH`** (under `GITFLOW` prepare the PR locally, no push); **omit entirely under `NO_GITFLOW`** |
| **Entity task**     | New/changed Eloquent model — migration + factory + seeder in the **same task**                                                                                                 |
| **Non-entity Impl** | Routes, UI, services — `## Test Data` = `N/A`                                                                                                                                  |
| **Test task**       | Anne — reuse factories; `test(US-XXX): TASK-NN` commit; depth per `settings.testing`                                                                                           |
| **Fix / evolutiva** | Rework — same Git + factory/seeder rules when schema changes                                                                                                                   |

Every **Impl** and **Fix** task body MUST include:

- `## Git Deliverables` — commit message; push/PR lines only per `git_mode`
- `## Test Data` — factory/seeder checklist, or explicit `N/A`
- `## Completion Criteria` — checkboxes (auto-ticked by `task-done`)

`plan_body` MUST include `## Git & Branching` and `## Test Data Strategy` sections.

## Laravel Planning Rules

Skill-unique sequencing plus canonical references — do not re-derive the rules here:

1. **John** applies **Architecture Standards** and (for SaaS/workspaces) **Multi-tenancy** from `runtime-delivery.md`; plans Gitflow branch name, semver/CHANGELOG, `security.txt` + `SECURITY.md`, CI gates, queues, DTOs, OpenAPI per delivery target. Task bodies that load relations must name eager-load / index deliverables.
2. **Alex** plans factory + seeder tasks for every new/changed model (same task as migrations — never deferred) and, with **Jack**, per-task Git discipline per **Git Workflow** in `runtime-delivery.md` — no batched multi-task commits. **Sarah** plans tasks for CI workflow YAML, Git/forge helper scripts, deploy hooks, Shell/Bash/Go tooling, and any expected rebase/merge-conflict hygiene (see **CLI, Git Pipelines & Linux**).
3. Plans must satisfy the **full spec** — do not trim scope to MVP unless the PRD delivery target is MVP.
4. **Anne** defines the Test Strategy per **Testing Standards** in `runtime-delivery.md`, interleaving test tasks with implementation (not all at the end); every public API route gets a feature test. **Gate on `settings.testing`:** under **`BEST`** only, plan responsive UI test tasks (viewport matrix 375/768/1280, mobile nav assertions, journeys at multiple widths, axe at mobile, E2E per the project stack — Elise's mockup README is the test contract). Under **`NORMAL`**, plan Pest feature/unit/policy/API tasks plus **manual test handoff** notes for UI specs — no browser/E2E/viewport suites. Under **`MINIMAL`**, essential critical-path tests only.
5. **Elise** plans mobile-first UI/mockup tasks per **Mobile first & responsive design** in `runtime-ux.md`; **Joe** plans design-system scaffold tasks (tokens, shared components, theme), animations, and client performance budgets; honor **Frontend Topology** from the PRD — when `API + external frontend`, keep Laravel tasks API/admin-focused (`repo: backend` or omit) and add explicit FE tasks with `repo: frontend` + paths under `data.frontend.repo_path`. **Ricky** plans mobile/device tasks when in scope. For UI needing mockups: invoke `larapilot-design` or generate inline to `.larapilot/mockups/{code}/`.
6. **Public-facing specs:** Emma (URLs/robots/sitemap/llms), Elise (WCAG + brand assets when the client has none), Violet (a11y legal), Lauren (marketing) — per `runtime-ux.md`. **Violet** adds full privacy/legal tasks when the spec processes personal data (see **Privacy & Legal Compliance** in `runtime-ship.md`).
7. **Sebastian/Matt** plan integration tasks (clients, webhooks, OAuth, `.env.example`, `Http::fake()` tests) per **Integrations & APIs** in `runtime-delivery.md`; competitor-data-porting specs get concrete import (format mapping, CSV/API importers, dry-run) and lock-in-free export tasks. **Emily** plans i18n tasks per **Internationalization** in `runtime-delivery.md`. **Marika** plans explicit copy tasks (views, labels, notifications, `lang/`).
8. **Legacy specs:** **Sabrine** plans parity verification per `legacy-parity.md` row; migration/ETL tasks with dry-run, checksum/row-count verification, and rollback — never plan feature/content drops without PRD **Out of Scope**.
9. **Packages & scaffolding:** follow **Vendor & Package Policy** and **Laravel Scaffolding Defaults** in `runtime-delivery.md` (Fortify 2FA, `Password::defaults()`, Socialite, UUID PKs, Argon2id; local dev per the PRD choice — ask, never assume Sail). **Jack** plans deploy/edge/cloud/observability tasks per PRD choices — if missing, ask per **Infrastructure & Cloud** in `runtime-ship.md` (never assume Cipi, Cloudflare, or AWS). **Sarah** co-plans pipeline/job scripts and server shell for those choices. **Andrew** reviews the plan for Laravel idioms and flags anti-patterns.
10. **Albert** plans baseline doc tasks per **Technical Documentation** in `runtime-delivery.md` (extended docs only when spec approval recorded them; under `effort: ECO` plan only the OpenAPI update when public/partner APIs change). **Aurora** flags cost implications per **Budget Sensitivity** in `runtime-discovery.md`.

## Rework Mode

When `data.spec.rework` is true or the body contains `## Rework Feedback`:

- Preserve existing DONE tasks
- Add `type: Fix` tasks for each feedback bullet
- Augment `plan_body` with a Rework note
