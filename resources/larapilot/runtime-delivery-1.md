## Architecture Standards _(John owns)_

John designs **scalable, complete products** whose depth matches the **delivery target** — never a throwaway MVP stack when the target is V1 Complete, Full Product, or Enterprise.

| Delivery target | Architecture depth |
| --- | --- |
| **MVP** | Thin vertical slice: core domain model, minimal API surface if needed, queues only where sync would block UX |
| **V1 Complete** | Service boundaries, versioned HTTP API (Sanctum/Passport), queues for mail/webhooks/heavy work, structured logging |
| **Full Product** | Full API catalog, rate limiting, Horizon/workers, event-driven integrations, DTOs at integration boundaries |
| **Enterprise** | Above plus audit trails, multi-tenant isolation, ADRs, **full observability** (metrics, traces, alerting), disaster-recovery posture |

**Always apply when architecting and planning:**

1. **SOLID** — design modules, services, and boundaries per SOLID. Prefer small Actions/Services over god classes; depend on abstractions only when there are real alternate implementations or test seams; keep controllers thin and domain rules out of HTTP/UI layers. Document intentional deviations in plan/ADR notes — never silent SOLID violations.
2. **Query performance & N+1** — every list/detail/API endpoint that loads relations must plan **eager loading** (`with` / `loadMissing`), selective columns, and indexes for filter/sort/FK columns. Flag loops that would query per row; prefer chunking/cursors for bulk work; never design "load then foreach → `$model->relation`" without an eager-load strategy. Record expected query shape in plan tasks when the path is hot.
3. **Queues & jobs** — offload email, webhooks, imports, reports, and any I/O-heavy work to Laravel queues (`ShouldQueue` jobs, Horizon in production). Never block HTTP requests on slow external calls.
4. **Logging** — structured application logging (`Log` channels, context arrays); log auth failures, payment events, and integration errors; define retention aligned with Violet's policy.
5. **Service integration** — encapsulate third-party APIs in dedicated service classes; use Events/Listeners for side effects; prefer Spatie packages or Laravel first-party over ad-hoc HTTP in controllers.
6. **DTOs & boundaries** — use Data objects / DTOs (Spatie Laravel Data, readonly PHP classes, or Form Request → DTO mappers) at API and integration boundaries when payloads are non-trivial; keep Eloquent models out of external contracts.
7. **Quality bar** — clear layers (Controller → Action/Service → Model); **fail-fast validation** at the edge (Form Requests); **idempotent** writes where retries are possible (webhooks, jobs); **transaction boundaries** around multi-model mutations; **authorization at the policy/gate layer** (not only UI); explicit error/domain exceptions over silent failure; migrations that own indexes and constraints with the schema change.
8. **Technical debt** — one migration per concern; explicit interfaces only when multiple implementations exist; document trade-offs in plan/ADR notes instead of hidden shortcuts; prefer readable Laravel idioms over premature abstraction.
9. **Documentation** — keep docs current with code in the same spec that changes the API or integration, and update the touched **developer domain docs** under `paths.dev_docs` in that same spec (**Technical Documentation**, `runtime-delivery-2.md`, and `runtime-dev-docs.md`).

**SSO / social login** — prefer **[Laravel Socialite](https://laravel.com/docs/socialite)** with official drivers; for providers beyond the core set use **[Socialite Providers](https://socialiteproviders.com/)** — never roll custom OAuth unless no provider exists. Store provider IDs on the User model (UUID PK); link accounts; respect Violet's consent requirements.

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

Honor **`data.settings.testing`** (**Testing**, Project Settings). Delivery target may add domain cases **within** that bar — it must not upgrade `MINIMAL`/`NORMAL` into browser E2E.

<!-- when: testing=MINIMAL -->
- **`MINIMAL`** — Critical-path Pest/PHPUnit only (auth, payments, core API + key Form Request validation). No browser/E2E tooling.
<!-- end -->
<!-- when: testing=NORMAL -->
- **`NORMAL`** — Feature/unit/policy/API/queue tests scaled to delivery target (**default**). **No** Playwright, Dusk, Pest browser, or journey E2E.
<!-- end -->
<!-- when: testing=BEST -->
- **`BEST`** — Full bar: feature/unit/policy/API/queue tests scaled to delivery target + integration (`Http::fake`), tenancy isolation when multi-tenant, primary-journey E2E, and **Responsive & UI testing** below.
<!-- end -->

Delivery-target hints **inside** the active bar: **MVP** — critical paths + Form Request validation. **V1 Complete** — above + policy tests, API contract tests, queue job tests. **Full Product / Enterprise** — above + integration tests; tenancy isolation when multi-tenant; **E2E only if `testing` is `BEST`**.

Always: use **Pest** when the project already does; `php artisan test` in CI; no untested public API routes under `NORMAL`/`BEST`; Anne defines strategy in every plan; interleave test tasks with implementation, not all at the end. When automation cannot run reliably, Anne **documents manual test steps** for the human (**manual test handoff**) — allowed at every bar.

### Responsive & UI testing _(Anne — **`settings.testing: BEST` only**)_

<!-- when: testing!=BEST -->
Skip automated viewport/browser suites; optional short **Manual tests recommended** notes are enough for UI specs.
<!-- end -->
<!-- when: testing=BEST -->
Anne verifies UI across devices and resolutions:

| Area | Requirement |
| --- | --- |
| **Viewport matrix** | UI/e2e tests exercise at least **375 px (mobile)**, **768 px (tablet)**, and **1280 px (desktop)** — add 320 px when layout is tight |
| **Mobile First alignment** | Tests must fail if primary navigation, CTAs, or forms are hidden, clipped, or unreachable at mobile widths |
| **Navigation** | Assert mobile menu open/close, keyboard access to nav links, and wayfinding on deep pages (breadcrumbs or back affordance) |
| **Responsive regression** | Critical user journeys (auth, checkout, create/edit flows) run at multiple viewports in Pest browser, Laravel Dusk, or Playwright — match the project's stack |
| **Accessibility × responsive** | Run axe (or equivalent) at **mobile viewport** — not desktop only; verify focus order and touch targets |
| **Lighthouse** | Emma's mobile Lighthouse gate (Accessibility ≥ 90) is part of Anne's test evidence for public UI specs |
| **Orientation / devices** | When automatable, test landscape on mobile for primary screens; cover every device class the stack supports (phone, tablet, desktop, PWA/app shells in scope) |
| **No desktop-only assumptions** | Never assert layout using desktop-only selectors without also covering the mobile DOM (e.g. collapsed nav, stacked forms) |

Anne plans explicit **responsive test tasks** interleaved with UI implementation. Elise's mockup README breakpoint notes are the test contract. At review, Anne attaches automated evidence **and** a **Manual tests recommended** section when human verification is still required.
<!-- end -->

## Code quality gate _(Andrew + Jack — mandatory; Sarah when CI scripts change)_

Every Larapilot project stays compatible with [Larastan](https://github.com/larastan/larastan) **level 5 or higher** and [Laravel Pint](https://laravel.com/docs/pint) formatting.

- **`larapilot:install`** scaffolds `phpstan.neon.dist` (Larastan extension, `level: 5`), `pint.json`, Composer scripts (`lint`, `lint:check`, `analyse`), and `require-dev` entries for `larastan/larastan` + `laravel/pint`.
- **`larapilot:quality`** runs Pint (check-only by default; `--fix` applies formatting) then Larastan analysis — use before review/merge and during implement.
- **`larapilot:doctor`** fails healthy when Pint/Larastan config, level, or dev dependencies are missing.
- **Never lower** `level` below 5 without an explicit human waiver recorded in the PRD or plan.
