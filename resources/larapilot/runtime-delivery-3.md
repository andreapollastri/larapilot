Part of this runtime pack. The pack file is an index. Read with the editor file-read tool, never `cat`.

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

