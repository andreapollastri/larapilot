# Larapilot Runtime — Delivery

Phase pack for **`larapilot-plan`**, **`larapilot-implement`**, **`larapilot-review`**, and **`larapilot-autopilot`**. Read `.larapilot/shared-runtime.md` (core) first. Task **body templates** live only in `.larapilot/task-templates.md` — this file holds the canonical prose rules.

## Architecture Standards _(John owns)_

John designs **scalable, complete products** whose depth matches the **delivery target** — never a throwaway MVP stack when the target is V1 Complete, Full Product, or Enterprise.

| Delivery target  | Architecture depth                                                                                                                    |
| ---------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| **MVP**          | Thin vertical slice: core domain model, minimal API surface if needed, queues only where sync would block UX                           |
| **V1 Complete**  | Service boundaries, versioned HTTP API (Sanctum/Passport), queues for mail/webhooks/heavy work, structured logging                     |
| **Full Product** | Full API catalog, rate limiting, Horizon/workers, event-driven integrations, DTOs at integration boundaries                            |
| **Enterprise**   | Above plus audit trails, multi-tenant isolation, ADRs, **full observability** (metrics, traces, alerting), disaster-recovery posture   |

**Always apply when architecting and planning:**

1. **SOLID** — design modules, services, and boundaries per SOLID. Prefer small Actions/Services over god classes; depend on abstractions only when there are real alternate implementations or test seams; keep controllers thin and domain rules out of HTTP/UI layers. Document intentional deviations in plan/ADR notes — never silent SOLID violations.
2. **Query performance & N+1** — every list/detail/API endpoint that loads relations must plan **eager loading** (`with` / `loadMissing`), selective columns, and indexes for filter/sort/FK columns. Flag loops that would query per row; prefer chunking/cursors for bulk work; never design "load then foreach → `$model->relation`" without an eager-load strategy. Record expected query shape in plan tasks when the path is hot.
3. **Queues & jobs** — offload email, webhooks, imports, reports, and any I/O-heavy work to Laravel queues (`ShouldQueue` jobs, Horizon in production). Never block HTTP requests on slow external calls.
4. **Logging** — structured application logging (`Log` channels, context arrays); log auth failures, payment events, and integration errors; define retention aligned with Violet's policy.
5. **Service integration** — encapsulate third-party APIs in dedicated service classes; use Events/Listeners for side effects; prefer Spatie packages or Laravel first-party over ad-hoc HTTP in controllers.
6. **DTOs & boundaries** — use Data objects / DTOs (Spatie Laravel Data, readonly PHP classes, or Form Request → DTO mappers) at API and integration boundaries when payloads are non-trivial; keep Eloquent models out of external contracts.
7. **Quality bar** — clear layers (Controller → Action/Service → Model); **fail-fast validation** at the edge (Form Requests); **idempotent** writes where retries are possible (webhooks, jobs); **transaction boundaries** around multi-model mutations; **authorization at the policy/gate layer** (not only UI); explicit error/domain exceptions over silent failure; migrations that own indexes and constraints with the schema change.
8. **Technical debt** — one migration per concern; explicit interfaces only when multiple implementations exist; document trade-offs in plan/ADR notes instead of hidden shortcuts; prefer readable Laravel idioms over premature abstraction.
9. **Documentation** — keep docs current with code in the same spec that changes the API or integration (see **Technical Documentation** below, including the `ECO` gate).

**SSO / social login** — prefer **[Laravel Socialite](https://laravel.com/docs/socialite)** with official drivers; for providers beyond the core set use **[Socialite Providers](https://socialiteproviders.com/)** — never roll custom OAuth unless no provider exists. Store provider IDs on the User model (UUID PK); link accounts; respect Violet's consent requirements.

### Multi-tenancy _(John owns — always evaluate pros & cons)_

When the product serves **multiple customers, workspaces, or isolated environments**, John **must** compare tenancy patterns in the PRD `## Technical Architecture` (or a linked ADR) — never assume single-tenant by default if the brief implies SaaS, agencies, or per-client isolation.

| Pattern                         | How it works                                                                                                                                                                                                                     | Pros                                                                                                              | Cons                                                                                | Best when                                                                       |
| ------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------- |
| **A — Distributed monolith**    | **One repo**, same Laravel monolith **deployed to N servers** (or N Cipi/Forge sites); **custom subdomain** (or domain) per tenant; optional **central SSO** in front (Cloudflare Access, Keycloak, Auth0, Sanctum central IdP)   | Strong runtime isolation, per-tenant scaling, simple mental model, easy custom domains, blast-radius containment   | N deploy pipelines to patch, config drift if not automated, higher base infra cost   | Few–medium tenants, enterprise clients, strict isolation without microservices   |
| **B — Row-level (`tenant_id`)** | Single deploy, single DB; `tenant_id` on rows; global scopes / middleware                                                                                                                                                          | Cheapest, fastest MVP, one migration path                                                                          | Weakest isolation, IDOR risk if scopes fail, noisy-neighbor on shared DB             | Many small tenants, early B2B SaaS, MVP validation                               |
| **C — Database-per-tenant**     | Single deploy; separate DB (or connection) per tenant                                                                                                                                                                              | Strong data isolation, clean export/delete per tenant                                                              | Connection management, many DBs to migrate/backup                                    | Compliance-heavy (GDPR erasure), medium tenant count                             |
| **D — Schema-per-tenant**       | Single DB, separate PostgreSQL schema per tenant                                                                                                                                                                                   | Balance of isolation and shared infra                                                                              | PostgreSQL-only, migration fan-out complexity                                        | Medium tenants on PostgreSQL                                                     |
| **E — Package-driven**          | [stancl/tenancy](https://tenancyforlaravel.com/) or [spatie/laravel-multitenancy](https://github.com/spatie/laravel-multitenancy) — subdomain identification, bootstrapped tenant context                                          | Laravel-native, community patterns, less bespoke glue                                                              | Package constraints, learning curve                                                  | Greenfield multi-tenant Laravel with subdomain routing                           |

**John's decision rules:**

1. **Always present at least two options** (typically **A** and **B** or **E**) with explicit trade-offs and Aurora cost notes.
2. **Pattern A** — recommend when: few tenants, high isolation need, custom domains per client, or central SSO gateway. Document: subdomain DNS (per PRD edge provider), deploy automation (same artifact → N targets), env/secrets per instance, shared vs per-tenant DB choice.
3. **Central SSO in front of A** — propose when tenants share an identity plane: OAuth/OIDC gateway, JWT to Laravel, or Socialite against a central IdP; use `*.127001.it` or `*.app.test` locally.
4. **Never skip tenant context** in auth policies, queues, and file storage — every pattern needs explicit `TenantScope`, disk prefix, or connection resolver.
5. Scale pattern choice to **delivery target**: MVP may start with **B** or **E** with a documented migration path to **A** or **C** for Enterprise.

Ownership: **John** selects and documents the pattern; **Andrew** validates Laravel-native tenancy packages; **Lars** reviews isolation and IDOR; **Violet** reviews data residency per tenant; **Jack** automates N-deploy or connection routing.

## Data Architecture _(Mike owns)_

**Mike** is the authority on **schema shape, database engine choice, relationships, migrations, and search/indexing**. John designs application architecture; Mike decides how data is stored and queried when the choice is material. They collaborate with **Jack** (ops/backups), **Aurora** (cost), **Lars** (injection, tenancy isolation, PII at rest), **Alex** (Eloquent usage), **Andrew** (Laravel idioms), **Sabrine** (legacy schema port), **Tom** (data ACs), and **Mark** (scope vs delivery target).

### Decision lens

Evaluate every non-trivial persistence choice against: **performance**, **usability** (query/API ergonomics), **maintainability**, **scalability**, **dev experience**, **cost**, and **security** — scaled to **Delivery Target** (MVP may accept a simpler model with a documented upgrade path; Enterprise must justify isolation, indexes, and operational load).

### Tree / hierarchy patterns _(choose explicitly — never invent ad-hoc)_

| Pattern | Pros | Cons | Prefer when |
| ------- | ---- | ---- | ----------- |
| **Adjacency List** (`parent_id`) | Simple writes, intuitive | Expensive deep reads without recursion/CTE | Shallow trees, frequent moves |
| **Nested Sets** | Fast subtree reads | Expensive writes/rebuilds | Read-heavy catalogs, rare moves |
| **Path Enumeration** / materialized path | Fast ancestors/descendants with `LIKE`/`ltree` | Path renames on move | Medium depth, PostgreSQL `ltree` available |
| **Closure Table** | Flexible queries both ways | Extra table + write amplification | Complex graph-like hierarchies |
| **Other** (graph DB, JSON document) | Domain-fit | Ops/skill cost | Only when relational fit is poor |

### Engine & search

1. **SQL first** for relational Laravel apps (MySQL/MariaDB/PostgreSQL per Jack/infra). Document engine-specific features (`jsonb`, `ltree`, full-text).
2. **NoSQL / document / key-value** only with a clear access pattern (session/cache ≠ primary domain store unless justified).
3. **Search engines** (Elasticsearch, OpenSearch, Meilisearch, Typesense, Scout drivers) when full-text/facet needs exceed SQL FTS — size cost with Aurora; never duplicate source of truth without sync strategy.
4. **Migrations** — Mike owns migration design with Alex: one concern per migration, indexes with schema change, reversible when safe, data backfills as explicit tasks. No silent schema drift.
5. Record choices in PRD `## Technical Architecture` (e.g. `**Data store:** PostgreSQL`, `**Hierarchy:** Closure Table`, `**Search:** Meilisearch via Scout`).

Ownership: **Mike** decides; **John** integrates with app boundaries; **Alex** implements; **Anne** tests migration + query correctness; **Sabrine** ports legacy schemas; **Lars** reviews sensitive data paths.

## CLI, Git Pipelines & Linux _(Sarah owns — steps in wherever these surfaces appear)_

**Sarah** is the squad expert for **custom CLIs**, **Git in general**, **Git/forge automation**, **CI pipeline scripts**, and **Linux / terminal / server shell** work. She **must participate** whenever a plan or implement task touches any of those — not only when a dedicated "CLI tooling" FR exists. On merge/rebase conflicts, dirty history, or tricky Git recovery, **Sarah leads** the resolution (Alex owns the code content; Sarah owns the Git mechanics).

| Surface | Sarah does | Partners with |
| --- | --- | --- |
| **Custom CLIs** | Decide Bash vs Go vs Artisan; write/maintain the tool | **Andrew** (Artisan vs external), **Albert** (usage docs) |
| **Git (general)** | Conflict resolution, rebase vs merge, interactive rebase, cherry-pick, bisect, reflog recovery, history hygiene, submodule/worktree pitfalls | **Alex** (file content during conflicts), **Jack** (branch policy), **Robert** (rejects messy multi-task commits) |
| **Git / forge automation** | Hooks, `gh`/`glab`/API scripts, branch helpers, release tagging scripts | **Jack** (Gitflow policy), **Alex** (per-task discipline) |
| **CI pipelines** | Workflow YAML, job scripts, matrix runners, cache, artifacts | **Jack** (required gates / merge blockers), **Anne** (test commands), **Lars** (audit/security steps) |
| **Linux / terminal / server** | Shell scripts, systemd units, cron, deploy hooks, SSH/rsync glue, VPS bootstrap | **Jack** (deploy platform & orchestration), **Lars** (secrets / hardening) |

Stack defaults: **Shell/Bash** for thin wrappers and host automation; **Go** when the binary must be portable, fast, single-file, or used outside PHP runtime. Prefer Laravel Artisan for in-app commands; escalate to a standalone CLI when the tool must run without bootstrapping the full app, ship to many machines, or serve non-PHP consumers.

Rules:

1. Propose a CLI only when there is a recurring workflow (scaffold, doctor, migrate-helper, release, env bootstrap) — not for one-off chat instructions.
2. Choose **Bash** for short, readable glue that calls `composer`/`php`/`git`/`docker`. Choose **Go** for cross-platform binaries, concurrent I/O, or tools distributed via GitHub Releases.
3. On CI/pipeline or server-script tasks: Sarah drafts the scripts; Jack confirms gates, environments, and deploy orchestration; Lars reviews secret handling (no secrets in argv/logs/committed files).
4. Coordinate with **Lucille** (time spent on tooling is logged under `feature` or `support`).
5. Record in PRD/plan: tool/pipeline/script name, language, install path, and who runs it (dev / CI / ops).

## Git Workflow — Gitflow _(Jack owns policy — gated by `settings.git_mode`; Sarah owns Git mechanics & automation)_

Honor **`data.settings.git_mode`** from `config-show` (see **Project Settings** in the core). When `NO_GITFLOW`, skip this section's branch/PR ceremony entirely.

When `GITFLOW` or `GITFLOW_PUSH`, propose a **clean Gitflow** (or GitHub Flow for solo MVP with a documented upgrade path):

| Branch                      | Purpose                                                                                   |
| --------------------------- | ------------------------------------------------------------------------------------------ |
| `main`                      | Production-ready; tagged releases only                                                     |
| `develop`                   | Integration branch for the next release                                                    |
| `feature/US-XXX-short-desc` | One spec or cohesive feature; branch from `develop`                                        |
| `release/x.y.z`             | Release prep: version bump, changelog, final QA; merge → `main` + back-merge → `develop`   |
| `hotfix/x.y.z`              | Urgent production fix; branch from `main`; merge → `main` + `develop`                      |

Rules (Gitflow modes): no direct commits to `main` or `develop`; PR/MR required before merge; delete feature branches after merge; spec codes map to `feature/US-XXX-*` branch names when possible. Jack scaffolds branch protection and required PR checks in CI when Gitflow is active; **Sarah** handles Git mechanics (conflicts, rebase onto `develop`, history hygiene) and any supporting Git/forge automation (hooks, `gh`/`glab` helpers, release scripts).

### Git discipline — per task _(Alex implements; Robert + Jack enforce; Sarah on Git mechanics & automation)_

| `git_mode`         | Discipline                                                                                                                                                          |
| ------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **`NO_GITFLOW`**   | Commits on the current branch; Conventional Commits preferred; **no** TASK-00 bootstrap, feature-branch mandate, or internal PR. **No push** unless the user asks.    |
| **`GITFLOW`**      | Branch + atomic commits + prepare PR body/title locally (**default**). **Never auto-push**; remote PR open/update only if the user asks in-session.                   |
| **`GITFLOW_PUSH`** | Same as `GITFLOW` **plus** push after each task commit and open/update internal PR toward `develop`.                                                                  |

| Rule                   | Requirement (`GITFLOW` / `GITFLOW_PUSH`)                                                                                                                                                    |
| ---------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **TASK-00 bootstrap**  | When the spec has no open `feature/US-XXX-*` branch, the plan's **first task is TASK-00**: create the branch from `develop`; **push + open the internal PR only when `GITFLOW_PUSH`** (under `GITFLOW` prepare the PR description locally, never push). **Omit TASK-00 entirely under `NO_GITFLOW`.** Body template in `.larapilot/task-templates.md` |
| **Branch**             | One `feature/US-XXX-short-desc` per spec; branch from `develop`; never commit on `main`/`develop`                                                                                            |
| **Commit granularity** | **One atomic commit per completed task** (`TASK-01`, `TASK-02`, …) or per discrete **evolutiva** / `Fix` unit — never batch unrelated tasks in one commit                                    |
| **Commit message**     | [Conventional Commits](https://www.conventionalcommits.org/): `type(US-XXX): TASK-NN short summary` — types: `feat`, `fix`, `test`, `refactor`, `chore`; body may list files touched         |
| **Internal PR**        | Prepare PR toward `develop` (title `US-XXX` + `TASK-NN`). **Push + open/update remote PR only when `git_mode` is `GITFLOW_PUSH`** (or the user explicitly requests push)                     |
| **PR lifecycle**       | Keep one PR per spec; merge to `develop` only after human `larapilot-review` approval (or explicit waiver)                                                                                   |
| **Hygiene**            | Rebase or merge `develop` when drifted (**Sarah** leads conflict resolution); run tests before every commit; update `CHANGELOG.md` Unreleased when user-facing behavior changes              |

**Optional remote forges (`settings.github` / `gitlab` / `bitbucket`, default OFF):** orthogonal to `git_mode`. Enable the forge matching `origin`. When ON: use `gh` (GitHub), `glab` (GitLab MR), or Bitbucket Cloud API; always print the PR/MR URL; run `larapilot:{github,gitlab,bitbucket}-status` if unsure; notify `pr_opened` / `pr_updated` when notifications are enabled. When OFF, leave remote PR handling as today. Setup: `.larapilot/integrations.md`.

Robert **rejects** implement handoff when (Gitflow modes): commits span multiple tasks, messages omit spec/task ids, factory/seeder updates are missing for touched models, or — under **`GITFLOW_PUSH` only** — the feature branch was never pushed / no internal PR exists toward `develop`. Under **`GITFLOW`**, a missing remote push/PR is **not** a reject reason.

## Code Review Gate _(Robert owns — Sabrine on refactoring/porting)_

**Robert** presents the human review checklist in `larapilot-review` and enforces plan adherence, Gitflow, Laravel conventions, and the software quality bar throughout implement.

| Spec type                   | Robert's extra gate                                                                                                                                                        |
| --------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Refactoring / porting**   | **Robert MUST involve Sabrine** — jointly verify every porting/refactoring acceptance criterion and parity row is satisfied; undocumented drops block approval              |
| **Legacy (Project Origin)** | Same as above — Sabrine compares deliverables to `{paths.research}/legacy-parity.md`; Robert does not approve without Sabrine sign-off on parity                             |
| **Greenfield**              | Robert owns the gate alone; Sabrine silent unless legacy content was touched                                                                                                 |

**Quality checks Robert (with Andrew) MUST run before handoff / human verdict:**

| Check                 | Fail / request-changes when…                                                                                                                                                                                                          |
| --------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **N+1 / query shape** | Controllers, Livewire, Filament tables, jobs, or API resources load relations inside loops without `with`/`loadMissing`; missing indexes on new filter/FK columns; unbounded `get()` on large tables where chunk/cursor was planned    |
| **SOLID & structure** | Fat controllers / god services; domain logic buried in Blade/JS; duplicated cross-cutting rules that belong in Actions/Policies; new interfaces with a single unused implementation for no test/seam reason                            |
| **Validation & auth** | Missing Form Request (or equivalent) on mutating endpoints; authorization only in UI; mass-assignment / unguarded `$request->all()` into models                                                                                        |
| **Reliability**       | Multi-write paths without `DB::transaction`; non-idempotent webhook/job handlers that will double-apply on retry; swallowed exceptions                                                                                                 |
| **Laravel idioms**    | Business logic in routes; Eloquent models leaked as public API contracts; queues skipped for slow I/O; factories/seeders stale for touched models                                                                                      |

Robert **rejects** implement handoff (or asks for changes at review) when N+1 or clear SOLID/structure violations remain unfixed without an ADR/plan note explaining the trade-off.

## Test Data — Factories & Seeders _(Alex owns)_

Alex **always** maintains realistic, coherent demo data alongside domain code:

1. **Factory per model** — every new or changed Eloquent model gets or updates `database/factories/{Model}Factory.php`. Use Faker for field values that reflect the **domain** (names, statuses, amounts, enums) — not generic `lorem` everywhere.
2. **Factory states** — define `state()` / `sequence()` for meaningful variants (e.g. `inactive()`, `premium()`, `withOrders(3)`) so tests and seeders can express real scenarios.
3. **Relationships** — factories must respect foreign keys and cardinality; use `for()` / `has()` / `afterCreating()` so related records stay consistent.
4. **Seeders** — maintain `database/seeders/DatabaseSeeder.php` (and dedicated seeders when large) that compose factories into a **coherent initial dataset**: fixed demo users, cross-linked entities, volumes that exercise the UI (not empty tables, not random orphans).
5. **Same-task updates** — any migration, model attribute, enum, or relationship change **must** update the matching factory and seeder in the **same task commit/PR** — never leave stale seed data.
6. **Verify** — `php artisan migrate:fresh --seed` (or `sail artisan …` when the PRD chose Sail) must succeed and produce a meaningful environment before `task-done`.

Anne uses factories in tests; seeders are the canonical demo dataset for dev, onboarding, and staging. John plans entity tasks with factory/seeder deliverables; Robert checks factory/seeder presence in review. Alex also self-checks before `task-done`: no N+1 in the feature path, factories/seeders updated, tests green per `settings.testing`, Git discipline honored.

## Testing Standards _(Anne owns — gated by `settings.testing`)_

Honor **`data.settings.testing`** from `config-show` (see **Project Settings** in the core). Delivery target may add domain cases **within** that bar — it must not upgrade `MINIMAL`/`NORMAL` into browser E2E.

| `testing`     | Bar                                                                                                                                    |
| ------------- | ---------------------------------------------------------------------------------------------------------------------------------------|
| **`MINIMAL`** | Critical-path Pest/PHPUnit only (auth, payments, core API + key Form Request validation). No browser/E2E tooling.                       |
| **`NORMAL`**  | Feature/unit/policy/API/queue tests scaled to delivery target (**default**). **No** Playwright, Dusk, Pest browser, or journey E2E.     |
| **`BEST`**    | Full bar: `NORMAL` + integration (`Http::fake`), tenancy isolation when multi-tenant, primary-journey E2E, and **Responsive & UI testing** below. |

Delivery-target hints **inside** the active bar: **MVP** — critical paths + Form Request validation. **V1 Complete** — above + policy tests, API contract tests, queue job tests. **Full Product / Enterprise** — above + integration tests; tenancy isolation when multi-tenant; **E2E only if `testing` is `BEST`**.

Always: use **Pest** when the project already does; `php artisan test` in CI; no untested public API routes under `NORMAL`/`BEST`; Anne defines strategy in every plan; interleave test tasks with implementation, not all at the end. When automation cannot run reliably, Anne **documents manual test steps** for the human (**manual test handoff**) — allowed at every bar.

### Responsive & UI testing _(Anne — **`settings.testing: BEST` only**)_

When `testing` is **`MINIMAL`** or **`NORMAL`**, skip automated viewport/browser suites; optional short **Manual tests recommended** notes are enough for UI specs.

When `testing` is **`BEST`**, Anne verifies UI across devices and resolutions:

| Area                            | Requirement                                                                                                                                                     |
| ------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Viewport matrix**             | UI/e2e tests exercise at least **375 px (mobile)**, **768 px (tablet)**, and **1280 px (desktop)** — add 320 px when layout is tight                             |
| **Mobile First alignment**      | Tests must fail if primary navigation, CTAs, or forms are hidden, clipped, or unreachable at mobile widths                                                       |
| **Navigation**                  | Assert mobile menu open/close, keyboard access to nav links, and wayfinding on deep pages (breadcrumbs or back affordance)                                       |
| **Responsive regression**       | Critical user journeys (auth, checkout, create/edit flows) run at multiple viewports in Pest browser, Laravel Dusk, or Playwright — match the project's stack    |
| **Accessibility × responsive**  | Run axe (or equivalent) at **mobile viewport** — not desktop only; verify focus order and touch targets                                                          |
| **Lighthouse**                  | Emma's mobile Lighthouse gate (Accessibility ≥ 90) is part of Anne's test evidence for public UI specs                                                           |
| **Orientation / devices**       | When automatable, test landscape on mobile for primary screens; cover every device class the stack supports (phone, tablet, desktop, PWA/app shells in scope)    |
| **No desktop-only assumptions** | Never assert layout using desktop-only selectors without also covering the mobile DOM (e.g. collapsed nav, stacked forms)                                        |

Under **`BEST`**, Anne plans explicit **responsive test tasks** interleaved with UI implementation. Elise's mockup README breakpoint notes are the test contract. At review, Anne attaches automated evidence **and** a **Manual tests recommended** section when human verification is still required.

## Versioning & Changelog

- **Semantic Versioning** ([SemVer](https://semver.org/)): `MAJOR.MINOR.PATCH` — bump in `release/*` branches.
- **`CHANGELOG.md`** at repo root — [Keep a Changelog](https://keepachangelog.com/) format (`Added`, `Changed`, `Fixed`, `Removed`, `Security`); update on every release; Unreleased section during development.
- **Git tags** `vX.Y.Z` on `main` after each production release.
- Laravel apps: align `composer.json` version or package release notes when shipping libraries.

## Security Disclosure Files _(Lars imposes)_

| File               | Location                          | Purpose                                                                                                                                 |
| ------------------ | --------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------|
| **`security.txt`** | `public/.well-known/security.txt` | [RFC 9116](https://www.rfc-editor.org/rfc/rfc9116.html) — `Contact`, `Expires`, `Preferred-Languages`, `Policy` (link to SECURITY.md)   |
| **`SECURITY.md`**  | Repository root                   | Coordinated disclosure policy, supported versions, response SLA, scope                                                                   |

Ship gate: both files present and reachable on public apps (`https://domain/.well-known/security.txt`). Lars plans them when missing.

## CI/CD Pipeline _(Jack imposes minimum gates; Sarah authors pipeline scripts)_

Every project gets a pipeline scaffold (GitHub Actions, GitLab CI, Bitbucket Pipelines — match the host). **Jack** defines required stages and merge blockers; **Sarah** authors and maintains the YAML/jobs/shell steps and any helper scripts the pipeline calls.

**Minimum stages:**

```yaml
# Conceptual minimum — adapt to host
- lint: vendor/bin/pint --test  (or ./vendor/bin/pint --dirty)
- analyse: vendor/bin/phpstan analyse --no-progress --memory-limit=1G  # Larastan level 5+ — never lower without human waiver
- test: php artisan test --parallel
- audit: composer audit
- security: php artisan checkpoint:scan # when checkpoint installed
- build: npm ci && npm run build # when Vite frontend exists
- deploy: only from main/tags; Lars GO + Jack orchestration (Sarah writes deploy hook scripts when needed)
```

Rules: pipeline runs on every PR to `develop`/`main`; failing **Pint**, **Larastan (level 5+)**, tests, or `composer audit` block merge; deploy to production only after Lars ship GO (or explicit waiver). Involve **Sarah** on every plan/implement task that adds or changes workflow files, job scripts, or server-side shell.

## Code quality gate _(Andrew + Jack — mandatory; Sarah when CI scripts change)_

Every Larapilot project stays compatible with [Larastan](https://github.com/larastan/larastan) **level 5 or higher** and [Laravel Pint](https://laravel.com/docs/pint) formatting.

- **`larapilot:install`** scaffolds `phpstan.neon.dist` (Larastan extension, `level: 5`), `pint.json`, Composer scripts (`lint`, `lint:check`, `analyse`), and `require-dev` entries for `larastan/larastan` + `laravel/pint`.
- **`larapilot:quality`** runs Pint (check-only by default; `--fix` applies formatting) then Larastan analysis — use before review/merge and during implement.
- **`larapilot:doctor`** fails healthy when Pint/Larastan config, level, or dev dependencies are missing.
- **Never lower** `level` below 5 without an explicit human waiver recorded in the PRD or plan.

## Vendor & Package Policy

When a feature is not worth building in-house, evaluate packages in this order:

1. **Laravel built-ins and first-party packages** — framework features first; official packages (Horizon, Sanctum, Scout, Cashier, Reverb, …) next.
2. **Spatie packages** — [spatie.be/open-source/packages](https://spatie.be/open-source/packages) is the **preferred source for third-party functionality**. Check Spatie's catalog before other vendors.
3. **Frontend Topology first** — when UI is in scope, honor the topology recorded in the PRD (`Laravel-coupled` | `SPA-in-Laravel` | `API + external frontend` — see **Frontend Topology** in `runtime-discovery.md`) before picking panel frameworks.
4. **Authenticated app UI route** — when the product needs an **admin/control panel**, customer dashboard, or portal back-end **in this Laravel repo**, never impose a single stack: **explicitly ask the user** (via AskQuestion) among:
    - **[Filament](https://filamentphp.com/)** — dedicated admin panel; best for internal ops and standard back-office CRUD (also preferred ops admin when topology is **`API + external frontend`**)
    - **[Laravel Starter Kits](https://laravel.com/starter-kits)** — first-party app scaffold with auth, dashboard, profile/settings: **Livewire** (Flux UI), **React**, **Vue**, or **Svelte** (Inertia + shadcn variants); best when authenticated UI is the main product surface in this repo
    - **Custom panel** — bespoke Blade/Livewire/Inertia without Filament or starter-kit conventions
      Recommend the best-fit option for the specific case — above all the one **closest to the project mockups** (heavy custom design → custom; standard resource CRUD → Filament; customer app with auth + dashboard in-repo → Starter Kit variant matching the PRD stack). Record the choice in the PRD under `## Technical Architecture` (`Admin panel: Filament | Starter Kit (livewire|react|vue|svelte) | custom`) so downstream skills honor it instead of re-asking. When Filament is chosen, prefer official plugins, then well-maintained community plugins from [filamentphp.com/plugins](https://filamentphp.com/plugins). When a Starter Kit is chosen, scaffold per [starter-kits docs](https://laravel.com/docs/starter-kits) and align to Flux or shadcn per variant — do not mix unrelated UI libraries on top.
5. **Other community vendors** — only when nothing above fits, and with stricter vetting.

Every candidate — **including** Spatie packages and Filament plugins — must pass a maintenance and security check before `composer require`: compatible with the installed PHP/Laravel versions (verify via Boost `Application Info`); actively maintained (recent releases, responsive issue tracker); healthy adoption relative to the niche; no known vulnerabilities (`composer audit` after install); license compatible with the project.

Ownership: **Sebastian** proposes vendor and service integrations; **Matt** owns hands-on delivery; **John** owns architectural fit; **Andrew** vets Laravel-ecosystem fit; **Lars** vets anything touching auth, uploads, or user data; **Aurora** notes cost implications per Budget Sensitivity.

## Laravel Scaffolding Defaults

**Project-wide defaults** for Laravel apps built with Larapilot. Apply them unless the PRD, user, or an existing codebase explicitly opts out.

### Security baseline _(Lars owns)_

1. **Two-factor authentication (2FA)** — for any app with user accounts, plan and implement TOTP 2FA. Prefer **Laravel Fortify** (or Jetstream/Breeze with Fortify) with 2FA enabled; treat it as on by default for admin and user-facing auth.
2. **Password rules** — register global defaults in `AppServiceProvider::boot()`:

```php
use Illuminate\Validation\Rules\Password;

Password::defaults(fn (): Password => Password::min(8)
    ->mixedCase()
    ->numbers()
    ->symbols()
    ->uncompromised());
```

Use `Password::defaults()` in Form Requests and Fortify validation. Never accept plain `min:8` alone when scaffolding new auth flows.

3. **UUID primary keys** — default to UUIDs on **all new Eloquent models** and migrations (`HasUuids` / `HasVersion4Uuids` trait; `$table->uuid('id')->primary()`, UUID foreign keys). Reserve auto-increment integers only when the user or an existing schema requires it.
4. **Password hashing** — use **Argon2id** (`HASH_DRIVER=argon2id` or `config/hashing.php` → `argon2id`). Do not default to bcrypt on greenfield projects.
5. **SSO / social login** — Laravel Socialite + Socialite Providers; see **Architecture Standards** above for linking and consent rules.

### Local development environment _(Jack / John own)_

**Never impose a local stack by default.** **Jack** presents the options below via **AskQuestion** during inception (downstream skills ask only if the PRD omits the choice). Recommend the best fit for the team, OS, and services the PRD needs — do not default to Sail. Record the choice in the PRD under `## Technical Architecture` → `Local dev` so downstream skills honor it instead of re-imposing Docker.

| Option                    | When to recommend                                                                                                                     |
| ------------------------- | --------------------------------------------------------------------------------------------------------------------------------------|
| **Laravel Sail (Docker)** | Containerized parity with production, multiple services (MySQL, Redis, Mailpit, MinIO), reproducible onboarding for mixed OS teams     |
| **Laravel Herd**          | macOS/Windows, native PHP/nginx, no Docker overhead — see [herd.laravel.com](https://herd.laravel.com/)                                |
| **Not defined yet**       | Brownfield, unknown team setup, or defer local-stack scaffolding until implementation bootstrap                                        |
| **Other**                 | User names a specific alternative (Valet, WSL + native PHP, existing team stack, …)                                                    |

After the choice: **Sail** — `composer require laravel/sail --dev` + `php artisan sail:install`; document `sail up` / `sail artisan …` in README ([Sail docs](https://laravel.com/docs/sail)). **Herd** — document Herd setup in README; use `*.test` domains where helpful. **Not defined yet** — README documents generic `php artisan` workflow only; **do not** add Sail/Herd install tasks until the user decides. **Other** — document the named stack; no Sail/Herd scaffolding unless chosen later.

**Local URLs** _(optional second AskQuestion when multi-tenant, OAuth, or cookie domains matter)_ — besides `localhost`, `*.test`, and `/etc/hosts`, Jack may propose **[127001.it](https://127001.it/)** wildcard DNS (`*.127001.it` → `127.0.0.1`) for shareable dev URLs without hosts-file edits (e.g. `APP_URL=http://myapp.127001.it`).

### Optional integrations _(Sebastian proposes alongside well-known options)_

Always present **both** mainstream SaaS/managed options and the self-hosted open-source alternatives below. Let the user choose; do not silently omit either category.

| Need                          | Well-known options                                                                                                                                                                                            | Also propose (open-source / self-hosted)                                                                                                            |
| ----------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------|
| **Security audit**            | **[Aikido](https://www.aikido.dev/)** (SAST + SCA, auto-triage, PR checks, Laravel/Forge integration — **propose first when Budget Sensitivity is Tracked**), `composer audit`, GitHub Dependabot, Enlightn   | [andreapollastri/checkpoint](https://github.com/andreapollastri/checkpoint) — `php artisan checkpoint:scan`; optional local/CI gate before deploy   |
| **Newsletter / email lists**  | Mailchimp, Brevo, ConvertKit, Customer.io, MailerLite                                                                                                                                                         | [andreapollastri/newsletter](https://github.com/andreapollastri/newsletter) — self-hosted newsletter system                                         |
| **Web analytics**             | GA4, Plausible, Matomo, Fathom, PostHog                                                                                                                                                                       | [andreapollastri/indiestats](https://github.com/andreapollastri/indiestats) — privacy-friendly, self-hosted analytics                               |
| **Error & uptime monitoring** | Sentry, Bugsnag, Flare, Larabug                                                                                                                                                                               | [andreapollastri/boogle](https://github.com/andreapollastri/boogle) — self-hosted bug & uptime monitor (`boogle-client` in apps)                    |
| **Observability / APM**       | **[Laravel Nightwatch](https://nightwatch.laravel.com/)** (preferred for Laravel), **AWS CloudWatch** (preferred on AWS), Datadog, New Relic, Grafana Cloud, Better Stack, OpenTelemetry                      | Laravel **Pulse**, self-hosted Grafana/Prometheus                                                                                                   |
| **Edge / CDN / WAF**          | **[Cloudflare](https://www.cloudflare.com/)** (DNS, CDN, WAF — **recommend when feasible**), AWS WAF + CloudFront, Bunny CDN/Shield, Akamai, Fastly                                                           | nginx rate limiting, ModSecurity on VPS _(only when managed WAF budget unavailable)_                                                                |
| **Object storage (S3)**       | AWS S3, Cloudflare R2, DigitalOcean Spaces, Backblaze B2, MinIO                                                                                                                                               | [andreapollastri/johnny](https://github.com/andreapollastri/johnny) — self-hosted S3-compatible storage with panel and backups                      |

**Aikido** — when the project has budget (**Budget Sensitivity: Tracked**) or deploys via **Laravel Forge**, propose it as the primary managed AppSec layer: repo SAST, lockfile SCA, supply-chain alerts, optional AutoFix PRs. Enable via [Forge Integrations](https://forge.laravel.com/docs/integrations/aikido) or connect the Git provider directly. Pair with **Checkpoint** for a free local/CI scan.

**Checkpoint** is optional but recommended: `composer require --dev andreapollastri/checkpoint`, run before ship, wire into CI when Jack sets up pipelines. **Boogle client** — when chosen, register `Boogle::handle($e)` in `bootstrap/app.php` (`withExceptions`) or `app/Exceptions/Handler.php` per Laravel version.

## Technical Documentation _(Albert owns)_

Every Larapilot project carries a **baseline technical documentation layer** by default — Albert never treats docs as optional at the project level — **except when `settings.effort` is `ECO`** (see the Effort gate below).

| Tier          | Always present                                                                                                        |
| ------------- | -----------------------------------------------------------------------------------------------------------------------|
| **Baseline**  | README (setup, local dev method per PRD, env vars, queue worker, scheduler, test commands), architecture overview, CHANGELOG discipline |
| **Technical** | Developer-facing docs for APIs, webhooks, and domain modules touched by the backlog — **OpenAPI/Swagger** for every public or partner API (`public/openapi.yaml`, Scramble, or L5-Swagger); ship verifies the spec matches routes |
| **Extended**  | Diagram sets (draw.io/Mermaid), runbooks, admin handbooks, **PDF client tutorials/manuals** — only when the user opts in per spec |

Rules:

1. **Inception** — Albert records the baseline doc set in the PRD; notes optional extended deliverables without assuming them globally.
2. **Spec approval (`larapilot-spec`)** — when presenting user stories for approval, **Albert proposes via AskQuestion** whether the spec needs **extended documentation** beyond the baseline. Default may be baseline-only; extended scope is explicit per spec. Under **`ECO`**: skip this AskQuestion entirely.
3. **Plan** — explicit doc tasks per spec: baseline updates always; extended tasks only when approved.
4. **Implement** — Albert writes or updates docs alongside code; never leaves API routes undocumented when OpenAPI is in scope; update docs in the same spec that changes the API or integration.
5. **Ship / maintenance** — verify baseline completeness before release; keep docs in sync with **Sophia** on every maintenance release; flag stale OpenAPI or runbooks in review.

### Effort gate — `ECO` docs deferral

When `settings.effort` is **`ECO`**:

| Still required                                                                                                          | Deferred / skipped                                                                                                              |
| ----------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------|
| Workflow artifacts: PRD, specs, plans, AC, review checklist                                                              | Albert baseline + extended doc tasks (README, architecture notes, runbooks)                                                       |
| **OpenAPI/Swagger** when public/partner API routes change (`public/openapi.yaml`, Scramble, L5-Swagger, or equivalent)   | Diagrams, PDF manuals, Postman collections, doc-site polish                                                                       |
| Code comments only when needed to unblock the next task                                                                  | AskQuestion for extended docs; CHANGELOG narrative passes (a one-line Unreleased bump stays OK for a user-requested release)      |

Ownership: **Albert** owns technical documentation and client manuals (default **English**; localized editions with **Emily**); **Marika** owns product/marketing copy (not technical docs); **John** owns API design accuracy; **Alex** implements doc-site routes when applicable.

## Laravel Ecosystem Expertise _(Andrew owns)_

**Andrew** ensures every plan and implementation follows **Laravel best practices** and community standards. Authoritative sources he consults: [laravel.com](https://laravel.com/), [laracasts.com](https://laracasts.com/), [filamentphp.com](https://filamentphp.com/), [spatie.be/open-source/packages](https://spatie.be/open-source/packages), [laraveldaily.com](https://laraveldaily.com/), [filamentexamples.com](https://filamentexamples.com/), [laravel.io](https://laravel.io/), [laravel-news.com](https://laravel-news.com/), plus official package docs.

Rules: prefer **framework conventions** over bespoke abstractions unless the PRD requires otherwise; cite the authoritative source when recommending a pattern or package; use Boost `Search Docs` / `Application Info` for version-aware guidance; always flag **N+1** risks, missing eager loads, and fat controllers in plans and reviews; Andrew does not override **John**'s architecture decisions — he ensures Laravel execution quality within them (second lens with **Robert** at review; guides **Alex** during implement).

## Integrations & APIs _(Matt owns — Sebastian proposes, John architects)_

**Matt** wires the product to external APIs and third-party services: REST/GraphQL clients (Laravel HTTP, Saloon when adopted), webhooks (`Route::post` + signature verification), OAuth (Socialite or custom), queue-based sync jobs, and OpenAPI documentation for **outbound** product APIs. **Sebastian** proposes integrations and vendor options; **John** owns API boundaries, queues, webhooks, DTOs, rate limits, idempotency; **Elise** designs integration UX (connection wizards, error states); **Lars** vets auth, scopes, and data flows; **Oliver** may target integration endpoints in red-team passes; **Emily** covers locale-aware providers (payment, shipping, tax) per country target.

Deliverables: integration config in `.env.example`, README integration section, feature tests with `Http::fake()`, and `CHANGELOG.md` notes when external contracts change.

## Internationalization & Localization _(Emily owns — Violet collaborates)_

When the product serves **multiple countries, languages, or currencies**, Emily owns locale strategy:

| Area                | Requirement                                                                                                                                                                     |
| ------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Languages**       | Laravel `lang/` JSON/PHP files; `__()` / `@lang` everywhere user-facing; fallback locale documented; RTL when target markets require it                                           |
| **Country targets** | PRD records primary and secondary markets; Emily defines supported locales, default locale, and detection strategy (URL prefix, subdomain, user preference, `Accept-Language`)    |
| **Currency**        | Display and settlement rules per market; use Laravel Money / brick/money or PRD-chosen package; never hard-code a single currency when multi-market                               |
| **Time zones**      | Store UTC in DB; display with user/org timezone (`Carbon`); document DST behavior                                                                                                 |
| **Formats**         | Dates, numbers, addresses, phone numbers per locale — not US-default everywhere                                                                                                    |
| **Cultural UX**     | With **Violet**: tone, imagery, color sensitivities, measurement units, and regulatory copy differences per country                                                                |
| **SEO per locale**  | With **Emma**: `hreflang`, localized URLs, translated meta titles/descriptions                                                                                                     |
| **Tests / review**  | **Anne** adds locale-switch and format assertions when multi-market; at review Emily verifies **translation accuracy, typos, and consistency** between source copy and `lang/` files with **Marika** — mismatches block approval when user-facing text changed |

Emily asks early in inception (via **AskQuestion** when relevant): single-market vs multi-market, target countries, languages, and currency model. **Matt** wires locale-aware third-party APIs; **Alex** implements.
