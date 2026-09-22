Part of this runtime pack. The pack file is an index. Read with the editor file-read tool, never `cat`.

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

**Developer domain docs sit outside that gate**: they are written at every effort level, in every project, in English. Full contract: `.larapilot/runtime-dev-docs.md`.

| Tier          | Always present                                                                                                        |
| ------------- | -----------------------------------------------------------------------------------------------------------------------|
| **Baseline**  | README (setup, local dev method per PRD, env vars, queue worker, scheduler, test commands), architecture overview, CHANGELOG discipline |
| **Technical** | Developer-facing docs for APIs, webhooks, and domain modules touched by the backlog — **OpenAPI/Swagger** for every public or partner API (`public/openapi.yaml`, Scramble, or L5-Swagger); ship verifies the spec matches routes |
| **Domain (devs)** | One Markdown file per domain/entity/feature under **`paths.dev_docs`** (default `.larapilot/docs/devs/`): functional flow, technical design, architectural choices with rejected alternatives, key decisions and invariants. **English only, never deferred — including under `ECO`.** Written in the same spec that changes the behavior |
| **Extended**  | Diagram sets (draw.io/Mermaid), runbooks, admin handbooks, **PDF client tutorials/manuals** — only when the user opts in per spec |

Rules:

1. **Inception** — Albert records the baseline doc set in the PRD; notes optional extended deliverables without assuming them globally.
2. **Spec approval (`larapilot-spec`)** — when presenting user stories for approval, **Albert proposes via AskQuestion** whether the spec needs **extended documentation** beyond the baseline. Default may be baseline-only; extended scope is explicit per spec. Under **`ECO`**: skip this AskQuestion entirely.
3. **Plan** — explicit doc tasks per spec: baseline updates always; extended tasks only when approved.
4. **Implement** — Albert writes or updates docs alongside code; never leaves API routes undocumented when OpenAPI is in scope; update docs in the same spec that changes the API or integration. **Always** update the touched domain files under `paths.dev_docs` before `spec-review` — a domain whose code moved while its doc did not is a **High** review finding. On a project where `data.dev_docs.documented` is `false`, the first change documents **every existing domain** first (**First-change catch-up** in `runtime-dev-docs.md`).
5. **Ship / maintenance** — verify baseline completeness before release; keep docs in sync with **Sophia** on every maintenance release; flag stale OpenAPI, runbooks, or domain docs in review.

### Effort gate — `ECO` docs deferral

When `settings.effort` is **`ECO`**:

| Still required                                                                                                          | Deferred / skipped                                                                                                              |
| ----------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------|
| Workflow artifacts: PRD, specs, plans, AC, review checklist                                                              | Albert baseline + extended doc tasks (README, architecture notes, runbooks)                                                       |
| **OpenAPI/Swagger** when public/partner API routes change (`public/openapi.yaml`, Scramble, L5-Swagger, or equivalent)   | Diagrams, PDF manuals, Postman collections, doc-site polish                                                                       |
| **Developer domain docs** under `paths.dev_docs` — same sections, terse prose (bullets and tables instead of narrative)   | Nothing in this folder is deferred — under `ECO` it gets shorter, never skipped                                                    |
| Code comments only when needed to unblock the next task                                                                  | AskQuestion for extended docs; CHANGELOG narrative passes (a one-line Unreleased bump stays OK for a user-requested release)      |

Ownership: **Albert** owns technical documentation, developer domain docs (**always English** — Emily does not localize these), and client manuals (default **English**; localized editions with **Emily**); **Marika** owns product/marketing copy (not technical docs); **John** owns API design accuracy; **Alex** implements doc-site routes when applicable.

