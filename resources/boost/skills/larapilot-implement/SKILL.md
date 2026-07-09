---
name: larapilot-implement
description: Implements a planned Larapilot spec by executing its technical plan. Use when the user wants to implement a PLANNED spec, start coding a backlog item, or execute sprint work. Do not use for discovery, backlog creation, or planning.
---

# Larapilot — Spec Implementation

Execute a planned spec: code, tests, review, handoff to REVIEW.

## Shared Runtime

Read `.larapilot/shared-runtime.md`.

## The Team

| Agent | Role |
| --- | --- |
| 🔧 **Alex** | Full-Stack Developer |
| 🧪 **Anne** | Test Architect |
| 🛡️ **Robert** | Code Reviewer — plan adherence, Laravel conventions, **Gitflow** branch hygiene |
| 🔐 **Lars** | Security Expert — OWASP-aligned security assessment |

## Config & CLI

1. `php artisan larapilot:config-show`
2. `php artisan larapilot:spec-show {code}` OR `php artisan larapilot:spec-next --status=PLANNED`
3. `php artisan larapilot:spec-start {code}`
4. `php artisan larapilot:task-done {code} {taskId}` (after each task)
5. `php artisan larapilot:spec-review {code}`

## Execution Contract

1. **Autonomous by default** — stop only for explicit blockers (scope change, missing prerequisite spec, semantic test breakage).
2. Implement the **full planned spec** — never silently drop acceptance criteria to fit an MVP unless the PRD delivery target is MVP and the spec was scoped accordingly. If in doubt, read `paths.prd` (from `config-show`) for the delivery target — do not assume MVP.
3. Work under `data.workdir` for all file operations.
4. Run connector commands from `data.project_root`.
5. After `spec-start`, re-run `spec-show` if worktree may have been created.

## Laravel Implementation

Use **Laravel Boost** throughout:

- `Search Docs` before unfamiliar APIs
- `Database Schema` / `Database Query` for data work
- `Tinker` for quick verification
- `Application Info` for package versions
- `Last Error` / `Read Log Entries` when debugging

Follow Laravel best practices from Boost guidelines: thin controllers, Form Requests, policies, eager loading, Pest tests.

Apply **Laravel Scaffolding Defaults** and **Architecture Standards** from shared-runtime on greenfield work unless the PRD or existing codebase opts out:

- **2FA:** enable Fortify TOTP when implementing auth; expose setup/confirm/recovery flows.
- **Passwords:** register `Password::defaults()` in `AppServiceProvider` and use `Password::defaults()` in validation rules.
- **UUIDs:** new models use `HasUuids` (or `HasVersion4Uuids`) and UUID columns in migrations.
- **Hashing:** ensure `HASH_DRIVER=argon2id` (or `config/hashing.php` → `argon2id`).
- **SSO:** use Laravel Socialite + [Socialite Providers](https://socialiteproviders.com/) for OAuth; link accounts on User model.
- **Queues:** implement `ShouldQueue` jobs for async work; never block HTTP on slow I/O.
- **Logging:** structured log context on auth, payments, and integration failures.
- **DTOs / services:** service classes for integrations; DTOs at API boundaries when payloads are non-trivial.
- **Docs:** update README, OpenAPI/Swagger (`public/openapi.yaml` or Scramble/L5-Swagger) in the same spec that changes APIs.
- **Local dev:** prefer Sail (`sail up`, `sail artisan …`); use `*.127001.it` in `.env.example` when the PRD calls for shareable local domains.
- **Git:** work on `feature/US-XXX-*` (or current spec branch per Gitflow); never commit directly to `main`.
- **Docs & security files:** add/update `CHANGELOG.md` (Unreleased), `SECURITY.md`, `public/.well-known/security.txt` when in scope.
- **Integrations:** wire the PRD-chosen stack — e.g. `boogle-client`, S3/R2 disk, indiestats snippet, newsletter package; **Cloudflare** trusted proxies + cache rules; **Nightwatch** or CloudWatch agent; Aikido/checkpoint for security audit.
- **Frontend (Elise):** Blade/Livewire/Tailwind; dark+light; WCAG 2.2 AA; commit **`public/favicon.svg`**, logo, OG image when client did not provide assets.
- **SEO (Emma):** robots/sitemap/llms, breadcrumbs, semantic headings, descriptive links.
- **Accessibility legal (Violet):** accessibility statement page and regulatory notes when EU/public sector.
- **Multi-tenancy:** implement chosen pattern per PRD; add isolation tests when Anne requires.

When a task requires a new dependency, follow the **Vendor & Package Policy** in shared-runtime: Laravel first-party → **Spatie** → **Filament plugins** (admin panels) → other vetted vendors. Verify version compatibility via `Application Info`, confirm the package is actively maintained, and run `composer audit` after `composer require`.

## Workflow

### Phase 0 — Load plan

From `spec-show`: `data.spec`, `data.tasks`, `data.workdir`.

### Phase 1 — Execute tasks in waves

Group tasks by dependencies. For each task:

1. Alex implements per task body contract
2. Anne writes/runs tests (`php artisan test` or `./vendor/bin/pest`)
3. `task-done` when verified

### Phase 2 — Review

Robert reviews: plan adherence, Laravel conventions, test coverage, code quality.

Lars runs an OWASP-aligned security pass (Top 10 mapping, `composer audit`, auth/access-control checks). Verify scaffolding defaults, `public/.well-known/security.txt` and `SECURITY.md` on public apps, CI pipeline audit/test gates. Run `php artisan checkpoint:scan` when installed. Fix Critical/High findings before handoff; document Medium findings.

Fix blockers autonomously; loop until clean or explicit blocker.

### Phase 3 — Handoff

`php artisan larapilot:spec-review {code}` with summary note.

Report: spec code, tasks completed, tests run, review outcome.
