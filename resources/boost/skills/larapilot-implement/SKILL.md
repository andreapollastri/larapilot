---
name: larapilot-implement
description: Implements a planned Larapilot spec by executing its technical plan. Use when the user wants to implement a PLANNED spec, start coding a backlog item, or execute sprint work. Do not use for discovery, backlog creation, or planning.
---

# Larapilot — Spec Implementation

Execute a planned spec: code, tests, review, handoff to REVIEW.

## Shared Runtime

Read `.larapilot/shared-runtime.md` — including **Sub-agents**.

Read `.larapilot/task-templates.md` — execute each task's **Git Deliverables** and **Test Data** sections verbatim.

## Output Economy

**High** — see `larapilot-implement` in shared-runtime. Status lines: task → action → result → next. Robert/Lars: bullet findings with severity. Handoff summary ~10 lines unless blockers need detail. Code, tests, and CLI output verbatim.

## The Team

| Agent | Role |
| --- | --- |
| 🔧 **Alex** | Full-Stack Developer |
| 🔗 **Matt** | Integration Manager — third-party APIs & services with Alex/John/Elise |
| 🌍 **Emily** | Translator — locales, currency, timezones when in scope |
| 🧪 **Anne** | Test Architect — **multi-viewport responsive UI tests**, Pest/PHPUnit |
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

- **Client materials & research:** before implementing, read cited files under `{paths.client_materials}` and `{paths.research}/`; verify acceptance criteria against them
- **Legacy rewrite/port:** when spec touches legacy parity, read `{paths.legacy}` and `{paths.research}/legacy-parity.md`; preserve behavior and data — Anne verifies migration evidence before `task-done`

- **2FA:** enable Fortify TOTP when implementing auth; expose setup/confirm/recovery flows.
- **Passwords:** register `Password::defaults()` in `AppServiceProvider` and use `Password::defaults()` in validation rules.
- **UUIDs:** new models use `HasUuids` (or `HasVersion4Uuids`) and UUID columns in migrations.
- **Hashing:** ensure `HASH_DRIVER=argon2id` (or `config/hashing.php` → `argon2id`).
- **SSO:** use Laravel Socialite + [Socialite Providers](https://socialiteproviders.com/) for OAuth; link accounts on User model.
- **Queues:** implement `ShouldQueue` jobs for async work; never block HTTP on slow I/O.
- **Logging:** structured log context on auth, payments, and integration failures.
- **DTOs / services:** service classes for integrations; DTOs at API boundaries when payloads are non-trivial.
- **Docs:** update README, OpenAPI/Swagger (`public/openapi.yaml` or Scramble/L5-Swagger) in the same spec that changes APIs.
- **Local dev:** honor the local dev method recorded in the PRD — use `sail up` / `sail artisan …` only when the PRD chose Sail; document Herd setup when Herd was chosen; when **not defined yet**, stick to generic `php artisan` in README/tasks until the user decides; use `*.127001.it` in `.env.example` when the PRD calls for shareable local domains
- **Git:** work on `feature/US-XXX-*` (or current spec branch per Gitflow); never commit directly to `main` or `develop`.
- **Git discipline (strict):** after **each** completed task — one atomic [Conventional Commit](https://www.conventionalcommits.org/) (`feat(US-XXX): TASK-NN summary`), push, and open/update the internal PR toward `develop` (title/body reference spec + task id). Same rule for evolutive fixes. See **Git discipline** in shared-runtime — Robert blocks handoff if violated.
- **Factories & seeders (Alex):** for every new/changed Eloquent model, create or update `database/factories/{Model}Factory.php` with domain-meaningful Faker data, relationship helpers, and states; keep `DatabaseSeeder` (and dedicated seeders) producing a **coherent demo dataset**; update factory + seeder in the **same task** as migrations/models; verify `migrate:fresh --seed` before `task-done`.
- **Docs & security files:** add/update `CHANGELOG.md` (Unreleased), `SECURITY.md`, `public/.well-known/security.txt` when in scope.
- **Frontend (Elise):** Blade/Livewire/Tailwind; **mobile-first responsive** (320 px up, progressive desktop enhancement); extremely navigable on any device/resolution; dark+light; WCAG 2.2 AA; commit **`public/favicon.svg`**, logo, OG image when client did not provide assets.
- **SEO (Emma):** robots/sitemap/llms, breadcrumbs, semantic headings, descriptive links.
- **Accessibility legal (Violet):** accessibility statement page and regulatory notes when EU/public sector.
- **Integrations (Matt):** wire third-party APIs/services per plan — OAuth, webhooks, SDK/HTTP clients, queued sync, signature verification; coordinate with Alex; `Http::fake()` tests; document in README; also wire PRD-chosen stack (S3/R2, newsletter, analytics, edge proxies per PRD edge choice, observability)
- **i18n (Emily):** `lang/` translations, locale middleware/detection, currency/timezone display, market-specific copy — with Violet on legal/cultural strings
- **Multi-tenancy:** implement chosen pattern per PRD; add isolation tests when Anne requires
- **High-risk integrations:** note in handoff if **Oliver** red-team pass is recommended before ship (payments, OAuth, webhooks, imports)

When a task requires a new dependency, follow the **Vendor & Package Policy** in shared-runtime: Laravel first-party → **Spatie** → **Filament plugins** (only when the PRD/plan chose Filament for the admin panel — never introduce Filament on your own) → other vetted vendors. When the PRD chose a **Laravel Starter Kit** variant, scaffold and extend per [starter-kits docs](https://laravel.com/docs/starter-kits) — never introduce a mismatched UI stack on your own. Verify version compatibility via `Application Info`, confirm the package is actively maintained, and run `composer audit` after `composer require`.

## Workflow

### Phase 0 — Load plan

From `spec-show`: `data.spec`, `data.tasks`, `data.workdir`.

### Phase 1 — Execute tasks in waves

Group tasks by dependencies. For each task:

1. Alex implements per task body contract — including factory/seeder updates when the task touches entities
2. Anne writes/runs tests (`php artisan test` or `./vendor/bin/pest`) — for UI tasks: assert at **375 / 768 / 1280 px** minimum; mobile nav and CTAs reachable; axe at mobile viewport when applicable
3. Alex commits (one atomic commit per task), pushes, and opens/updates internal PR to `develop`
4. `task-done` when verified — the CLI also ticks the task's `- [ ]` completion criteria; never edit the plan YAML manually

### Phase 2 — Review (sub-agents or inline)

After all tasks are verified, run two **readonly review passes** — Robert (code review) and Lars (security) — before fixing and handoff. Only the **parent** edits code, re-runs tests, writes review artifact, and calls CLI.

#### Launch

With a sub-agent tool (Cursor Task tool, Claude Code Agent tool, or equivalent): spawn both passes as **readonly sub-agents in parallel** (one message, two calls, synchronous — not background). Pick the closest available type per pass (see **Type mapping** in shared-runtime):

| Persona | Pass | Example types |
| --- | --- | --- |
| 🛡️ Robert | code review | Cursor `bugbot`; else generic readonly sub-agent |
| 🔐 Lars | security review | Cursor `security-review`; else generic readonly sub-agent |

Enable the editor's readonly flag when available; the handoff prompt forbids edits regardless. Review scope: the branch diff (or uncommitted changes when nothing is committed yet).

**Inline fallback** — no sub-agent tool: the parent runs the same two passes itself, sequentially (Robert, then Lars), using the handoff prompt below as a checklist. All later steps are identical.

#### Handoff prompt (fill from `config-show` + `spec-show`)

```text
Larapilot implement review — {code}

workdir: {data.workdir absolute}
project_root: {data.project_root absolute}
branch: feature/{code}-* (or current branch in workdir)
plan: {paths.planning}/{code}-plan.yaml (under project_root)
spec body: {acceptance criteria + Demonstrates from data.spec.body}

Robert (code review): plan adherence, Laravel conventions, Gitflow branch hygiene (no direct main/develop commits), **per-task commit + internal PR discipline**, **factory/seeder completeness** for touched models. Return bullets: severity (Critical|High|Medium|Low) — file:line — finding. No edits.

Lars (security review): OWASP Top 10 on branch diff; auth/access-control; composer audit implications; security.txt/SECURITY.md when in scope. Return same bullet format. No edits.
```

#### Parent merge loop

1. Deduplicate Robert + Lars bullets; fix all **Critical** and **High** autonomously.
2. Re-run tests after fixes (`php artisan test` or `./vendor/bin/pest`).
3. Re-run **Lars only** if auth, policies, or security files changed materially; skip Robert re-run unless code changed widely.
4. Write `{paths.review}/{code}.md` (from `config-show`; default `.larapilot/docs/review/`) per **Sub-agents** in shared-runtime (Robert, Lars, Parent actions sections).
5. Document **Medium** findings in Parent actions if not fixed.

Robert and Lars still speak in character when the **parent** summarizes merged findings in chat (Output Economy bullets).

### Phase 3 — Handoff

`php artisan larapilot:spec-review {code}` with summary note.

Report (concise): spec code, tasks completed, tests run, review outcome — per Output Economy handoff limit.
