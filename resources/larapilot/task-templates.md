# Larapilot — Task body templates

Copy these structures into `larapilot-plan` task bodies. Every **Impl** and **Fix** task MUST include **## Git Deliverables** when `settings.git_mode` is `GITFLOW` or `GITFLOW_PUSH`; every task that touches Eloquent models MUST include **## Test Data** (factory + seeder); every task that changes a domain's behavior MUST include **## Domain Docs** (`.larapilot/runtime-dev-docs.md`) — at every effort level, `ECO` included. Anne's test tasks omit Git/Test Data unless they add seed-only fixtures.

The templates below are the ones the settings of the project call for: the Git lines follow `settings.git_mode`, the test task follows `settings.testing`. Under `effort: ECO` write fewer and shorter tasks and defer docs except OpenAPI and the domain docs; under `effort: MAX` add verification and docs tasks and a deeper Test Strategy. Canonical Git and TASK-00 prose: **Git Workflow** in `runtime-delivery.md`.

Replace `{US-XXX}`, `{TASK-NN}`, `{Model}`, and placeholders with real values.

---

<!-- when: git_mode!=NO_GITFLOW -->
## TASK-00 — Git bootstrap (first task — Gitflow modes only)

Use when the spec has no open `feature/US-XXX-*` branch yet.

<!-- when: git_mode=GITFLOW -->
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
<!-- end -->
<!-- when: git_mode=GITFLOW_PUSH -->
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
<!-- end -->
<!-- end -->

---

<!-- when: release_mode=YES and git_mode!=NO_GITFLOW -->
## TASK-00 — Release branch variant (release_mode + Gitflow)

Use when **`settings.release_mode` is `YES`**, `git_mode` is `GITFLOW` or `GITFLOW_PUSH`, and the spec body carries **`Release: x.y.z`** (assigned to an `in_progress` release — `release-feature` opens the release branch if it is still `planned`). Branch **from** `release/x.y.z`, merge **into** `release/x.y.z` — **not** `develop`. One Artisan command; do not run the git steps by hand.

Replace `{RELEASE}` with the semver (e.g. `1.2.0`).

### `GITFLOW` (no automatic push)

```markdown
## Description
Bootstrap Gitflow for this spec on release `{RELEASE}`: create `feature/US-XXX-*` from `release/{RELEASE}` and prepare the internal PR description toward `release/{RELEASE}`. Do **not** push unless the user asks.

## Files Involved
- (git only — no application files)

## Steps
1. `php artisan larapilot:release-feature --semver={RELEASE} --spec=US-XXX --slug=short-desc`
2. Read `branch`, `base`, and `checked_out` from the JSON. Stop if `checked_out` is false and surface `reason`.
3. Draft internal PR title/body toward `base` (`release/{RELEASE}`)

## Completion Criteria
- [ ] `release-feature` returned `ok: true` and `checked_out: true`
- [ ] PR title/body drafted toward `release/{RELEASE}` (not `develop`)

## Git Deliverables
- Commit: `chore(US-XXX): TASK-00 bootstrap feature branch for release/{RELEASE}` (only if the command did not already leave you on the new branch with a commit — an empty commit is optional)
- Push: **skip** (`git_mode: GITFLOW`)
- PR: prepare locally toward `release/{RELEASE}` — open remote only if user requests
```

### `GITFLOW_PUSH`

Same as above, but pass `--push` on `release-feature` and open/update the PR toward **`release/{RELEASE}`** (`base` from the command).

When the spec has **no** `Release:` line, use the standard **TASK-00 — Git bootstrap** templates toward `develop`.
<!-- end -->

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

## Domain Docs
- [ ] `{paths.dev_docs}/{domain}.md` updated (or created from `TEMPLATE.md`) — data model, invariants, and the schema choices that were rejected
- [ ] Folder `README.md` index row updated
- [ ] English, committed with this task

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

## Domain Docs
- [ ] `{paths.dev_docs}/{domain}.md` updated (or created from `TEMPLATE.md`) — functional flow, technical design, architectural choices
- [ ] Folder `README.md` index row updated
- [ ] English, committed with this task

## Git Deliverables
- Commit: `feat(US-XXX): TASK-NN {short summary}`
- Push: {only if `GITFLOW_PUSH`; else **skip**}
- PR: {remote update only if `GITFLOW_PUSH`}
```

---

## Test task (Anne)

Steps scaled to `settings.testing`.

<!-- when: testing!=BEST -->
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
<!-- end -->
<!-- when: testing=BEST -->
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
<!-- end -->

---

## Fix / enhancement task (rework)

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

## Domain Docs
- [ ] `{paths.dev_docs}/{domain}.md` corrected when the fix changed how the domain behaves; otherwise `N/A`
- [ ] English, committed with this task

## Git Deliverables
- Commit: `fix(US-XXX): TASK-NN {short summary}`
- Push: {only if `GITFLOW_PUSH`; else **skip**}
- PR: {remote update only if `GITFLOW_PUSH`}
```

---

<!-- when: frontend=external -->
## Frontend task — external repo (`repo: frontend`)

Use when **Frontend Topology** is `API + external frontend` and the task implements UI in the configured FE repo. Set `"repo": "frontend"` on the task in the plan JSON, `"project": "<name>"` when `frontend-scan` reports a monorepo, and `"shared": ["<library>"]` when the task must change a library in `write_scope.shared`. Paths are relative to the scan's `root` (the workspace; `repo_path` only when the repository is its own workspace). Fill the braces from `frontend-scan` — never from habit — and follow **Frontend Companion** (`frontend.md`).

```markdown
## Description
{UI work — map to mockups/OpenAPI. Project `{project}` ({stack} {framework.version}). Shared libraries changed and the apps that depend on them, or "none".}

## Files Involved
- {owned root}/...  _(under `write_scope.owned`; model each new file on the exemplar of its kind)_

## Frontend Rules
- {`rules.must_read` paths, plus `frontend-rules --file=` for the files above — the repository's rules win on code}

## Steps
1. Read mockups at `{paths.mockups}/{code}/` and the product OpenAPI
2. Generate with the workspace generators (`generators.local` first, `--dry-run`), then implement as `observed` and the exemplars show
3. Wire the API: regenerate the generated client (`api_client.regenerate`) or extend the HTTP layer the project has — loading, empty, and error states; no undocumented endpoint
4. Verify: {`target_projects[].commands` — lint, typecheck, test, build} then {`commands.affected`}

## Completion Criteria
- [ ] UI matches mockups / acceptance criteria
- [ ] API calls match the documented contract
- [ ] The frontend rules that govern the files are respected
- [ ] Lint, tests, and build green for the project and what depends on it

## Git Deliverables
- Repo: frontend (`git -C {git_root} …` — `target_projects[].git_root` when set, else `git.root` of the scan) — its hooks run, never `--no-verify`
- Commit: `git.commits.pattern` from the scan, in the language and style of its `samples`, with `US-XXX TASK-NN` in the subject — in `git_root` when the project has one
- Push: {only if `GITFLOW_PUSH`; else **skip**}
- PR: {remote update only if `GITFLOW_PUSH`}
```
<!-- end -->

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
