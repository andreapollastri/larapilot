# Larapilot — Task body templates

Copy these structures into `larapilot-plan` task bodies. Every **Impl** and **Fix** task MUST include **## Git Deliverables** when `settings.git_mode` is `GITFLOW` or `GITFLOW_PUSH`; every task that touches Eloquent models MUST include **## Test Data** (factory + seeder). Anne's test tasks omit Git/Test Data unless they add seed-only fixtures.

**Read `data.settings` from `config-show` before planning.** Honor **Project Settings** in `.larapilot/shared-runtime.md`.

Replace `{US-XXX}`, `{TASK-NN}`, `{Model}`, and placeholders with real values.

---

## Settings gates (read first)

| Setting | Effect on templates |
| --- | --- |
| `git_mode: NO_GITFLOW` | **Omit TASK-00.** In Git Deliverables: local commit only — no Push/PR lines. |
| `git_mode: GITFLOW` | Include TASK-00 and Git Deliverables **without** Push. PR line = "prepare locally / open when user asks". |
| `git_mode: GITFLOW_PUSH` | Include TASK-00 with push + remote PR (legacy full Gitflow). |
| `testing: MINIMAL` | Thin Anne tasks — critical paths only; no viewport/browser/E2E steps. |
| `testing: NORMAL` | Standard Pest feature/unit/policy — **no** Playwright/Dusk/viewport browser steps (**default**). |
| `testing: BEST` | Full Anne bar including viewport matrix and browser/E2E tooling. |
| `effort: ECO` | Fewer/shorter tasks; **no sub-agents**; defer Albert docs (README/PDF/diagrams) but **keep OpenAPI updates** when APIs change; skip optional research/E2E-shaped work. |
| `effort: MAX` | Extra verification/docs tasks; deeper Test Strategy in `plan_body`. |

---

## TASK-00 — Git bootstrap (first task — Gitflow modes only)

Use when `git_mode` is `GITFLOW` or `GITFLOW_PUSH` and the spec has no open `feature/US-XXX-*` branch yet. **Skip entirely when `NO_GITFLOW`.**

### `GITFLOW` (no automatic push)

```markdown
## Description
Bootstrap Gitflow for this spec: create the feature branch from `develop` and prepare the internal PR description toward `develop`. Do **not** push unless the user asks.

## Files Involved
- (git only — no application files)

## Steps
1. `git checkout develop && git pull` (pull only if already tracking; otherwise local develop)
2. `git checkout -b feature/US-XXX-short-desc`
3. Draft internal PR title/body toward `develop` (keep local until push is requested)
4. Optional empty commit for branch anchor: `chore(US-XXX): TASK-00 bootstrap feature branch`

## Completion Criteria
- [ ] Branch `feature/US-XXX-*` exists locally
- [ ] PR title/body drafted for `develop` (remote open optional — only if user requested push)

## Git Deliverables
- Commit: `chore(US-XXX): TASK-00 bootstrap feature branch` (empty commit allowed)
- Push: **skip** (`git_mode: GITFLOW`)
- PR: prepare locally — open/update remote only if user requests
```

### `GITFLOW_PUSH`

```markdown
## Description
Bootstrap Gitflow for this spec: create the feature branch from `develop`, push it, and open the internal PR toward `develop`.

## Files Involved
- (git only — no application files)

## Steps
1. `git checkout develop && git pull`
2. `git checkout -b feature/US-XXX-short-desc`
3. Push branch: `git push -u origin feature/US-XXX-short-desc`
4. Open internal PR to `develop` — title: `US-XXX: {spec title}`; body: link plan + list planned tasks

## Completion Criteria
- [ ] Branch `feature/US-XXX-*` exists and tracks `origin`
- [ ] Internal PR open toward `develop` (draft OK)

## Git Deliverables
- Commit: `chore(US-XXX): TASK-00 bootstrap feature branch` (empty commit allowed if branch/PR only)
- Push: `origin feature/US-XXX-short-desc`
- PR: open or update — reference `US-XXX` + `TASK-00`
```

---

## Entity task — model + migration + factory + seeder

Use when introducing or materially changing an Eloquent model.

```markdown
## Description
Add `{Model}` with migration, factory, and seeder entries so the demo dataset stays coherent.

## Files Involved
- app/Models/{Model}.php
- database/migrations/xxxx_create_{models}_table.php
- database/factories/{Model}Factory.php
- database/seeders/DatabaseSeeder.php (or database/seeders/{Model}Seeder.php)

## Steps
1. `php artisan make:model {Model} -mf` (or update existing files)
2. Define migration columns, indexes, foreign keys
3. Factory: domain-meaningful Faker fields; at least one `state()` (e.g. `inactive()`); relationship helpers (`for()`, `has()`, `afterCreating()`)
4. Seeder: compose factories into linked demo records (fixed demo IDs where useful)
5. `php artisan migrate:fresh --seed` (or `sail artisan …` when the PRD chose Sail)
6. `php artisan test` — feature/policy tests for the model (depth per `settings.testing`)

## Completion Criteria
- [ ] Model, migration, policy (if applicable) in place
- [ ] `{Model}Factory` produces valid, domain-realistic records
- [ ] Seeder creates coherent related demo data (no orphan FKs)
- [ ] `migrate:fresh --seed` succeeds
- [ ] Tests pass

## Test Data
- [ ] `database/factories/{Model}Factory.php` created or updated
- [ ] `DatabaseSeeder` (or dedicated seeder) calls `{Model}::factory()` with meaningful volumes/states
- [ ] Factory updated in **this same task** as migration/model changes

## Git Deliverables
- Commit: `feat(US-XXX): TASK-NN add {Model} with factory and seeder`
- Push: {`origin feature/…` only if `git_mode: GITFLOW_PUSH`; otherwise **skip**}
- PR: {update remote PR if `GITFLOW_PUSH`; else prepare/update local notes}
```

---

## Non-entity Impl task — routes, UI, services

Use when the task does not add or change Eloquent models.

```markdown
## Description
{One sentence objective — e.g. Add project index Livewire component.}

## Files Involved
- {list paths}

## Steps
1. {implementation steps}
2. Implement **mobile-first** per Elise mockup README (Tailwind `sm:`/`md:`/`lg:` breakpoints); smoke-check layout at 375, 768, 1280 px during implement
3. `php artisan test` (or targeted Pest filter) — depth per `settings.testing`

## Completion Criteria
- [ ] {acceptance-linked outcomes}
- [ ] UI usable at mobile/tablet/desktop (manual smoke OK under MINIMAL/NORMAL)
- [ ] Tests pass

## Test Data
- N/A — no model/schema changes in this task

## Git Deliverables
- Commit: `feat(US-XXX): TASK-NN {short summary}`
- Push: {only if `GITFLOW_PUSH`; else **skip**}
- PR: {remote update only if `GITFLOW_PUSH`}
```

---

## Test task (Anne)

Scale steps to `settings.testing`.

### `MINIMAL` / `NORMAL`

```markdown
## Description
Add Pest coverage for {feature/API/policy}.

## Files Involved
- tests/Feature/...
- tests/Unit/... (if needed)

## Steps
1. Use existing factories from `database/factories/` — do not duplicate factory definitions here
2. Cover happy path, validation failures, and authorization (NORMAL); critical happy path only (MINIMAL)
3. `php artisan test --filter=...`
4. Do **not** add Playwright, Dusk, Pest browser, or viewport E2E suites

## Completion Criteria
- [ ] Tests pass locally
- [ ] Coverage matches `settings.testing` bar

## Git Deliverables
- Commit: `test(US-XXX): TASK-NN cover {feature}`
- Push: {only if `GITFLOW_PUSH`; else **skip**}
- PR: {remote update only if `GITFLOW_PUSH`}
```

### `BEST`

```markdown
## Description
Add Pest coverage for {feature/API/policy}, including responsive/browser checks when UI is in scope.

## Files Involved
- tests/Feature/...
- tests/Unit/... (if needed)
- tests/Browser/... (or Pest browser / Dusk / Playwright — match stack)

## Steps
1. Use existing factories from `database/factories/`
2. Cover happy path, validation failures, and authorization
3. For UI specs: test at **375 px (mobile)**, **768 px (tablet)**, **1280 px (desktop)** — assert nav, primary CTA, and forms reachable at each width
4. Run axe (or equivalent) at mobile viewport when the project supports it
5. `php artisan test --filter=...`

## Completion Criteria
- [ ] Tests pass in CI locally
- [ ] Public routes / policies covered
- [ ] UI specs: responsive assertions at mobile + desktop viewports; mobile nav open/close when applicable

## Git Deliverables
- Commit: `test(US-XXX): TASK-NN cover {feature}`
- Push: {only if `GITFLOW_PUSH`; else **skip**}
- PR: {remote update only if `GITFLOW_PUSH`}
```

---

## Fix / evolutiva task (rework)

```markdown
## Description
Fix: {one-line from rework feedback}

## Files Involved
- {paths}

## Steps
1. {fix steps}
2. If models/migrations touched → update matching factory + seeder in this task
3. `php artisan test`

## Completion Criteria
- [ ] Rework feedback addressed
- [ ] Tests pass
- [ ] Factory/seeder updated if schema or relationships changed

## Test Data
- [ ] Factory/seeder updated when applicable; otherwise `N/A`

## Git Deliverables
- Commit: `fix(US-XXX): TASK-NN {short summary}`
- Push: {only if `GITFLOW_PUSH`; else **skip**}
- PR: {remote update only if `GITFLOW_PUSH`}
```

---

## Plan body snippet — Git & test data

Add to every `plan_body` (adjust Git lines to `git_mode`):

```markdown
## Git & Branching
- Mode: {NO_GITFLOW | GITFLOW | GITFLOW_PUSH from settings}
- Branch: `feature/US-XXX-short-desc` from `develop` _(omit if NO_GITFLOW)_
- TASK-00: bootstrap _(omit if NO_GITFLOW)_
- Per task: one Conventional Commit; push/PR remote **only** when `GITFLOW_PUSH`
- Merge after human `larapilot-review` approval

## Test Data Strategy
- Alex maintains factories + seeders for every entity touched
- Demo dataset: {e.g. 3 users, 2 orgs, 10 orders with line items — adjust per spec}
- Verify `migrate:fresh --seed` before each entity `task-done`

## Test Strategy
- Bar: {MINIMAL | NORMAL | BEST from settings}
- {Anne details matching that bar — no Playwright/E2E unless BEST}
```
