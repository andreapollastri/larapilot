---
name: larapilot-plan
description: "Writes the technical plan and tasks for one spec. Use for plan US-005, break this down, or the next TODO spec."
---

# Larapilot — Spec Planning

Produce a detailed implementation plan for one spec and persist it via the CLI.

## Context

`php artisan larapilot:context plan` — with `--session={token}` when this conversation already holds one, `--fresh` after a compaction. Read every file under `data.runtime.read`, none under `loaded`. Settings, paths, `data.project`, `data.dev_docs`, and `data.frontend` come from that envelope: no `config-show`.

Read an `on_demand` file as soon as the spec calls for it — decide right after Stage 1, before the team brief: UI → `ux-1.md` (+ `ux-2.md`, and `ux-3.md` for public pages); a package, a panel, tenancy, or a non-trivial data model → `delivery-3.md`; CI/CD, CLI, scripts, versioning → `delivery-4.md`; third-party APIs or locales → `delivery-5.md`; personal data → `ship-2.md`; open deploy choices → `ship-1.md`; legacy or client documents → `discovery-3.md`. A surface the spec does not touch is not read.

When `data.settings.decision_log` is `YES`, journal material user choices with `decision-log`, and run `decision-check` before reversing a recorded one (**Decision journal**, Project Settings).

## Output Economy

**Split.** Team brief: 1–3 sentences per agent. Chat between stages: status and blockers only. `plan_body` and task bodies stay detailed execution contracts — do not strip them.

## Autopilot spec worker

When the handoff says you are the autopilot spec worker, read `spec-worker.md` (on demand) and follow **Spec worker**. Explore inline (no nested explore). Write `.larapilot/tmp-payload-{code}-plan.json` and stop before `validate-plan` / `spec-plan`. Do not AskQuestion — return `BLOCKED`. The final message is `OK plan {code} | N tasks` or the two-line `BLOCKED` form. No team brief in that message. Standalone `/larapilot-plan` is unchanged.

## The Team

🤖 Zoey · 📒 Lucille · 🔎 Tom · 📐 John · 🗄️ Mike · 💡 Sebastian · 🔗 Matt · 🌍 Emily · 💰 Aurora · ⚖️ Violet · 📈 Emma · 💬 Lauren · 🎨 Elise · ✨ Joe · 📱 Ricky · 📝 Albert · ✍️ Marika · 👾 Andrew · ⌨️ Sarah · 🔄 Sabrine · 🔧 Alex · 🧪 Anne. Mike owns data/schema tasks; **Sarah** owns Git mechanics (conflicts/rebase), CLI, forge automation, CI pipeline scripts, and Linux/server scripting whenever those surfaces appear (partner Jack on gates/deploy).

## CLI

1. `php artisan larapilot:spec-show {code}` OR `php artisan larapilot:spec-next --status=TODO`
2. `php artisan larapilot:prd-show --ids=…` — the ids on the spec's `**Traces to:**` line; `--section="Technical Architecture"` (and `"Domain Model"` when the spec names entities) for the recorded choices. Never the whole PRD.
3. `php artisan larapilot:validate-plan {code} --file=...`
4. `php artisan larapilot:spec-plan {code} --file=...` — when `data.settings.hooks` is `YES` it runs the hooks of `spec.planned` (`hooks.md`): a refusal with `details.hooks` is fixed and the command run again; a skill it names runs first, then `--skill-hooks-done=`; the skills under `data.hooks.after.skills` run before the handoff

## Workflow

### Stage 0 — Select spec

- With code argument → `spec-show`
- Without argument → `spec-next`
- Free-text descriptions → route to `larapilot-spec` first

### Stage 1 — Load context (parallel)

From `data.workdir` (codebase) and `data.project_root` (artifacts):

- The promise — `prd-show --ids=` for the FRs, journeys, and NFRs the spec traces to; `data.project` for delivery target, topology, panel, and local dev; `prd-show --section="Technical Architecture"` for the other recorded choices. When `data.project.frontend_topology` is **`API + external frontend`**, use `data.frontend` and run `larapilot:frontend-scan` (once per spec) — `targets.needs_project` stops the plan until the user names the projects (`/larapilot-frontend-companion`); then follow **Frontend Companion** (`frontend.md`) and the playbook the scan lists
- **Client materials** (`paths.client_materials`) — mandatory when populated; cite in task notes
- **Legacy** (`paths.legacy`) + **`{paths.research}/legacy-parity.md`** — when rewrite/port; map tasks to parity rows
- **Reference products** (`paths.research/reference-products/`) — when the spec traces to deepsearch findings
- Mockups (`paths.mockups/{code}/`) if they exist
- Relevant Laravel code: models, migrations, routes, tests
- Boost `Database Schema` for data model context; `Search Docs` for Laravel/package patterns

#### Sub-agent (optional — large or unfamiliar codebase)

When `settings.effort` is **`ECO`**, or this run is an autopilot spec worker, **never spawn an explore sub-agent** — explore inline only. A nested explore cannot start, and the worker's context is already fresh.

Otherwise, when `data.workdir` has substantial existing code and the editor has a sub-agent tool, launch one **readonly explore sub-agent** (synchronous; **Type mapping**, `core-subagents.md`) before Stage 2. When **`{paths.legacy}`** is populated, include it in the explore scope alongside `data.workdir`. Parent still reads the PRD slices and mockups directly. **Inline fallback** — no sub-agent tool (or `ECO`): the parent explores the codebase itself in Stage 1, using the handoff prompt below as a checklist.

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
    "plan_body": "## Technical Solution\n...\n\n## Git & Branching\n- Mode: {from settings.git_mode}\n- Branch/PR/push rules per Git Workflow\n\n## Test Data Strategy\n- Factories + seeders for every entity\n- Demo volumes: ...\n\n## Test Strategy\n- Bar: {from settings.testing} — no Playwright/E2E unless BEST\n...",
    "tasks": [
        {
            "id": "TASK-00",
            "title": "Bootstrap feature branch and internal PR",
            "body": "## Description\n...\n\n## Git Deliverables\n- Commit: chore(US-XXX): TASK-00 bootstrap feature branch\n...",
            "type": "Impl",
            "status": "TODO",
            "assignee": "Alex",
            "estimate_hours": 1,
            "dependencies": []
        },
        {
            "id": "TASK-01",
            "title": "...",
            "body": "## Description\n...\n\n## Files Involved\n- app/Models/...\n\n## Test Data\n- [ ] Factory + seeder updated\n\n## Domain Docs\n- [ ] {paths.dev_docs}/{domain}.md updated\n\n## Git Deliverables\n- Commit: feat(US-XXX): TASK-01 ...\n\n## Completion Criteria\n- [ ] ...",
            "type": "Impl",
            "status": "TODO",
            "assignee": "Alex",
            "estimate_hours": 4,
            "dependencies": ["TASK-00"]
        },
        {
            "id": "TASK-02",
            "title": "Parallel UI polish (can run beside TASK-01 when no shared files)",
            "body": "...",
            "type": "Impl",
            "status": "TODO",
            "assignee": "Joe",
            "estimate_hours": 3,
            "dependencies": ["TASK-00"]
        }
    ]
}
```

**Dependencies & parallelism (Lucille + planners):** every task lists `dependencies` (empty = can start when the spec starts). Tasks that share the same dependency set and do not block each other are **parallel** — Lucille’s Gantt marks them, and runs them at the same time only when their `assignee` values differ (developers / personas executing the step); tasks of one assignee follow one another. Prefer realistic `estimate_hours` on **every** task — they drive Lucille’s Gantt bars and the delivery forecast on the Plan page.

Validate, then `spec-plan`. Delete the temp file after the CLI exits. An autopilot spec worker stops after writing the temp file — the parent validates and calls `spec-plan`.

## Task body templates

Use `task-templates.md` — do not invent ad-hoc task shapes. It holds the templates the settings of this project call for: **TASK-00** (first task under a Gitflow mode, never under `NO_GITFLOW`; `larapilot:release-feature` when the spec carries `**Release:** x.y.z`), **Entity task** (migration + factory + seeder in the same task), **Non-entity Impl**, **Test task** (Anne, at the `settings.testing` bar), **Fix / enhancement** (rework), and the external-frontend task when a frontend repo is linked.

Every **Impl** and **Fix** task body MUST include:

- `## Git Deliverables` — commit message; push/PR lines only per `git_mode`
- `## Test Data` — factory/seeder checklist, or explicit `N/A`
- `## Domain Docs` — the file under `{paths.dev_docs}` the task leaves current, whenever it changes a domain's behavior — at every effort level, `ECO` included
- `## Completion Criteria` — checkboxes (auto-ticked by `task-done`)

`plan_body` MUST include `## Git & Branching` and `## Test Data Strategy` sections. Implement executes these bodies without reading the templates: a body that leaves a section out leaves the rule out.

## Laravel Planning Rules

Skill-unique sequencing plus canonical references — do not re-derive the rules here:

1. **John** applies **Architecture Standards** (`delivery-1.md`) and, for SaaS/workspaces, **Multi-tenancy** (`delivery-3.md`); plans the Gitflow branch name, semver/CHANGELOG, `security.txt` + `SECURITY.md`, CI gates (`delivery-4.md`), queues, DTOs, OpenAPI per delivery target. Task bodies that load relations must name eager-load / index deliverables.
2. **Alex** plans factory + seeder tasks for every new/changed model (same task as migrations — never deferred) and, with **Jack**, per-task Git discipline per **Git Workflow** (`delivery-2.md`) — no batched multi-task commits. **Sarah** plans tasks for CI workflow YAML, Git/forge helper scripts, deploy hooks, Shell/Bash/Go tooling, and any expected rebase/merge-conflict hygiene (**CLI, Git Pipelines & Linux**, `delivery-4.md`).
3. Plans must satisfy the **full spec** — do not trim scope to MVP unless `data.project.delivery_target` is MVP.
4. **Anne** defines the Test Strategy per **Testing Standards** (`delivery-1.md`), interleaving test tasks with implementation (not all at the end); every public API route gets a feature test. The bar is the one `settings.testing` sets — responsive, viewport, and E2E tasks only under `BEST`; under the other bars, Pest tasks plus **manual test handoff** notes for UI specs.
5. **Elise** plans mobile-first UI/mockup tasks per **Mobile first & responsive design** (`ux-1.md`); **Joe** plans design-system scaffold tasks (tokens, shared components, theme), animations, and client performance budgets (`ux-2.md`); honor `data.project.frontend_topology` — when `API + external frontend`, keep Laravel tasks API/admin-focused (`repo: backend` or omit) and add explicit FE tasks from the external-frontend template — `repo: frontend`, `project:` in a monorepo, `shared:` for a shared library, paths under `write_scope.owned`, the scan's commands as the verification steps. A backend task that changes the API comes before the FE task that calls it; with a generated client, the FE task starts by regenerating it. Under `data.frontend.mode` `handoff` the FE tasks are still planned: the frontend team builds them from the brief. **Ricky** plans mobile/device tasks when in scope. For UI needing mockups: invoke `larapilot-design` or generate inline to `{paths.mockups}/{code}/`.
6. **Public-facing specs:** Emma (URLs/robots/sitemap/llms), Elise (WCAG + brand assets when the client has none), Violet (a11y legal), Lauren (marketing) — `ux-3.md`. **Violet** adds full privacy/legal tasks when the spec processes personal data (**Privacy & Legal Compliance**, `ship-2.md`).
7. **Sebastian/Matt** plan integration tasks (clients, webhooks, OAuth, `.env.example`, `Http::fake()` tests) per **Integrations & APIs** (`delivery-5.md`); competitor-data-porting specs get concrete import (format mapping, CSV/API importers, dry-run) and lock-in-free export tasks. **Emily** plans i18n tasks per **Internationalization** (`delivery-5.md`). **Marika** plans explicit copy tasks (views, labels, notifications, `lang/`).
8. **Legacy specs:** **Sabrine** plans parity verification per `legacy-parity.md` row; migration/ETL tasks with dry-run, checksum/row-count verification, and rollback — never plan feature/content drops without PRD **Out of Scope**.
9. **Packages & scaffolding:** **Security baseline** (`delivery-1.md`: Fortify 2FA, `Password::defaults()`, Socialite, UUID PKs, Argon2id) and **Vendor & Package Policy** (`delivery-3.md`); local dev per `data.project.local_dev` — ask, never assume Sail. **Jack** plans deploy/edge/cloud/observability tasks per the PRD choices — if missing, ask per **Infrastructure & Cloud** (`ship-1.md`; never assume Cipi, Cloudflare, or AWS). **Sarah** co-plans pipeline/job scripts and server shell for those choices. **Andrew** reviews the plan for Laravel idioms and flags anti-patterns.
10. **Albert** plans baseline doc tasks per **Technical Documentation** (`delivery-2.md`; extended docs only when spec approval recorded them; under `effort: ECO` only the OpenAPI update when public/partner APIs change) **and adds a `## Domain Docs` section to every task that changes a domain's behavior** (`dev-docs.md`). **Aurora** flags cost implications per `data.project.budget_sensitivity`.

## Rework Mode

When `data.spec.rework` is true or the body contains `## Rework Feedback`:

- Preserve existing DONE tasks
- Add `type: Fix` tasks for each feedback bullet
- Augment `plan_body` with a Rework note
