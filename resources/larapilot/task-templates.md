# Larapilot — Task body templates

Copy these structures into `larapilot-plan` task bodies. Every **Impl** and **Fix** task MUST include **## Git Deliverables**; every task that touches Eloquent models MUST include **## Test Data** (factory + seeder). Anne's test tasks omit Git/Test Data unless they add seed-only fixtures.

Replace `{US-XXX}`, `{TASK-NN}`, `{Model}`, and placeholders with real values.

---

## TASK-00 — Git bootstrap (first task in every plan)

Use when the spec has no open `feature/US-XXX-*` branch yet.

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
6. `php artisan test` — feature/policy tests for the model

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
- Push: `origin feature/US-XXX-short-desc`
- PR: update open PR — title/body mention `TASK-NN` and factory/seeder
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
2. Implement **mobile-first** per Elise mockup README (Tailwind `sm:`/`md:`/`lg:` breakpoints); verify layout at 375, 768, 1280 px
3. `php artisan test` (or targeted Pest filter)

## Completion Criteria
- [ ] {acceptance-linked outcomes}
- [ ] UI navigable and usable at mobile (375 px), tablet (768 px), and desktop (1280 px) — no clipped CTAs or horizontal scroll
- [ ] Tests pass

## Test Data
- N/A — no model/schema changes in this task

## Git Deliverables
- Commit: `feat(US-XXX): TASK-NN {short summary}`
- Push: `origin feature/US-XXX-short-desc`
- PR: update open PR — reference `TASK-NN`
```

---

## Test task (Anne)

```markdown
## Description
Add Pest coverage for {feature/API/policy}.

## Files Involved
- tests/Feature/...
- tests/Unit/... (if needed)

## Steps
1. Use existing factories from `database/factories/` — do not duplicate factory definitions here
2. Cover happy path, validation failures, and authorization
3. For UI specs: test at **375 px (mobile)**, **768 px (tablet)**, **1280 px (desktop)** — assert nav, primary CTA, and forms reachable at each width
4. Run axe (or equivalent) at mobile viewport when the project supports it
5. `php artisan test --filter=...`

## Completion Criteria
- [ ] Tests pass in CI locally
- [ ] Public routes / policies covered per delivery target
- [ ] UI specs: responsive assertions at mobile + desktop viewports; mobile nav open/close when applicable

## Git Deliverables
- Commit: `test(US-XXX): TASK-NN cover {feature}`
- Push: `origin feature/US-XXX-short-desc`
- PR: update open PR — reference `TASK-NN`
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
- Push: `origin feature/US-XXX-short-desc`
- PR: update open PR — reference `TASK-NN`
```

---

## Plan body snippet — Git & test data

Add to every `plan_body`:

```markdown
## Git & Branching
- Branch: `feature/US-XXX-short-desc` from `develop`
- TASK-00: bootstrap branch + internal PR
- Per task: one Conventional Commit, push, update PR toward `develop`
- Merge after human `larapilot-review` approval

## Test Data Strategy
- Alex maintains factories + seeders for every entity touched
- Demo dataset: {e.g. 3 users, 2 orgs, 10 orders with line items — adjust per spec}
- Verify `migrate:fresh --seed` before each entity `task-done`
```
