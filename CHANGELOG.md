# Changelog

All notable changes to `larapilot` will be documented in this file.

## [1.4.0] - 2026-07-09

### Added

- **New personas — Matt, Oliver, Sophia, Emily**
    - **🔗 Matt** (Integration Manager) — hands-on API & third-party service delivery with Alex, John, Elise; Sebastian proposes, Matt wires.
    - **🎯 Oliver** (Ethical Hacker) — red-team assessments before ship; reports findings to Lars.
    - **🎧 Sophia** (Support Manager) — post-ship bug intake/triage, maintenance backlog, docs & software updates with Lars.
    - **🌍 Emily** (Translator) — multilingual UI, currency, timezones, country-target culture with Violet.
    - New `paths.support` (`.larapilot/docs/support/`); security folder holds Lars OWASP + Oliver red-team reports.
    - Updated `shared-runtime.md`, all skills, `config/larapilot.php`, `core.blade.php`, README.

### Changed

- **Project Kind — inception interview branches** — Mark now opens discovery with **AskQuestion** for `Personal`, `Website`, or `Application`, switching persona depth and follow-up questions (website type, delivery target, multi-tenancy). Recorded in PRD `## MVP Scope`; downstream skills (`spec`, `design`, `ship`) read it. Updated `shared-runtime.md`, `larapilot-inception`, `larapilot-spec`, README, and docs.

- **Alex — factories, seeders & strict Gitflow** — Alex must create/update Eloquent factories (domain-meaningful Faker data, states, relationships) and keep seeders (`DatabaseSeeder` + dedicated seeders) producing a coherent demo dataset; updates ship in the same task as model/migration changes with `migrate:fresh --seed` verification. **Git discipline** is now non-negotiable: one atomic Conventional Commit per completed task or evolutiva, push after each task, and open/update an internal PR toward `develop` (Robert blocks handoff on violation). Updated `shared-runtime.md`, `larapilot-plan`, `larapilot-implement`, `larapilot-review`, `core.blade.php`, and README.

- **Mobile First — Elise & Anne** — UI design and tests must follow **Mobile First**: smallest viewport first (320–375 px), progressive desktop enhancement without neglecting large screens; extremely navigable and simple on any device/resolution. Elise documents breakpoint/nav contract in mockup README; Anne plans and runs multi-viewport tests (375 / 768 / 1280 px minimum, mobile nav, axe at mobile). Updated `shared-runtime.md`, `larapilot-design`, `larapilot-plan`, `larapilot-implement`, `larapilot-inception`, `larapilot-review`, `core.blade.php`, and README.

- **Task body templates** — new `.larapilot/task-templates.md` (published on install/update): TASK-00 Git bootstrap, entity/non-entity/test/fix templates with `## Git Deliverables` and `## Test Data` sections; `larapilot-plan` and `larapilot-implement` reference it; `SharedRuntime::refresh()` copies all packaged docs.

- **Workflow dashboard** — dev-only read-only UI at `/larapilot` (board, PRD viewer, spec/task detail). Disabled in production; configure with `LARAPILOT_DASHBOARD_ROUTE`.

- **Vendor & Package Policy (Filament)** — Filament is no longer the assumed "preferred route" for admin/control panels. The team now **explicitly asks the user** (Filament vs custom panel) via AskQuestion, recommending the best-fit technology for the specific case and, above all, the option closest to the project mockups. The choice is recorded in the PRD (`## Technical Architecture`); `larapilot-spec`/`larapilot-plan` honor it (and ask when missing), `larapilot-implement` never introduces Filament on its own, and `larapilot-design` mockups no longer presuppose Filament's look — updated across `shared-runtime.md`, `core.blade.php`, all affected skills, and the README.

## [1.3.0] - 2026-07-09

### Added

- **Output Economy** in `shared-runtime.md` — per-phase brevity rules for chat and status (drop filler, keep persona labels and AskQuestion intact; artifacts, compliance, and security NO-GO rationale stay complete).
- **Sub-agents (editor-agnostic)** in `shared-runtime.md` — optional readonly sub-agents on any editor with a sub-agent tool (Cursor Task, Claude Code Agent, …), with inline fallback when none exists: codebase explore during plan (large codebases); code review + security review in parallel during implement Phase 2; parent owns CLI and writes `{paths.review}/{code}.md` before handoff.
- **`paths.review` config path** (default `.larapilot/docs/review/`) — registered in `config/larapilot.php`, exposed by `config-show`, and created by `ensureDirectories()` like the other docs paths.
- **Checklist auto-tick** — `task-done` now marks the task's `- [ ]` completion criteria as `- [x]` in the plan body; `spec-approve` ticks the spec's acceptance criteria on human approval. Artifact checkboxes reflect real progress without manual YAML edits.

### Changed

- All **`/larapilot-*` skills** — each skill references the matching Output Economy level (`inception`: clarity first; `spec`/`design`: moderate; `plan`: split chat vs artifact; `implement`/`review`: high; `ship`: structured terse; `autopilot`: minimal progress lines).
- **`larapilot-plan`** — optional explore sub-agent in Stage 1 for codebase mapping before writing the plan (inline fallback without a sub-agent tool).
- **`larapilot-implement`** — Phase 2 launches Robert (code review) and Lars (security review) as parallel readonly sub-agents, or inline when the editor has none; parent merges findings, fixes Critical/High, persists `{paths.review}/{code}.md`; handoff before `spec-review` capped at ~10 lines unless blockers need detail.
- **`larapilot-review`** — checklist gate (criteria, evidence, risks, verdict); no diff narration; reads `{paths.review}/{code}.md` from implement when present.
- **`larapilot-autopilot`** — one-line progress report per spec; explicit ban on spawning sub-agents in batch mode.
- **`core.blade.php`** — Boost guidelines summarize output economy and sub-agent policy alongside existing Larapilot policies.
- **README** — sub-agents section under Larapilot + Boost; implement step documents parallel review sub-agents.

## [1.2.0] - 2026-07-09

### Added

- **Laravel Scaffolding Defaults** in `shared-runtime.md` and Boost guidelines — security baseline (Fortify 2FA, `Password::defaults()` with `uncompromised()`, UUID primary keys, Argon2id, Laravel Socialite + Socialite Providers for SSO), local dev (Laravel Sail preferred, Herd alternative, [127001.it](https://127001.it/) wildcard URLs), and an optional-integrations matrix (mainstream SaaS plus self-hosted: [Aikido](https://www.aikido.dev/), [checkpoint](https://github.com/andreapollastri/checkpoint), [newsletter](https://github.com/andreapollastri/newsletter), [indiestats](https://github.com/andreapollastri/indiestats), [boogle](https://github.com/andreapollastri/boogle), [johnny](https://github.com/andreapollastri/johnny)).
- **Architecture Standards** (John) — scalable product depth per delivery target; queues/jobs, structured logging, service/DTO boundaries, minimal technical debt, OpenAPI/Swagger and README kept current.
- **Multi-tenancy Architecture** (John) — mandatory pros/cons comparison across patterns: distributed monolith (one repo, N deploys, custom subdomains, optional central SSO), row-level `tenant_id`, database-per-tenant, schema-per-tenant, and package-driven (stancl/tenancy, Spatie multitenancy).
- **Development & Delivery Standards** (Jack, Robert, Anne, Lars) — Gitflow (`main`, `develop`, `feature/*`, `release/*`, `hotfix/*`), SemVer + `CHANGELOG.md` (Keep a Changelog), `public/.well-known/security.txt` + root `SECURITY.md`, minimum CI/CD gates (Pint, Pest, `composer audit`, checkpoint), and testing bars scaled to delivery target.
- **Security budget** (Aurora + Lars + Violet) — security spend is never the first cost cut; Lars and Violet review tooling and architecture against cybersecurity best practice and applicable regulations.
- **Infrastructure & Cloud** (Jack + Aurora) — **Cloudflare** preferred for DNS/CDN/WAF (alternatives: AWS WAF + CloudFront, Bunny, Akamai, Fastly); AWS compute step-by-step when budget allows; **DigitalOcean** alternative; **Hetzner** and **OVH** for EU residency; observability always proposed (Laravel Nightwatch, AWS CloudWatch, or alternatives).
- **Marketing & Growth** (Lauren + Emma + Elise + Aurora) — newsletter, campaigns, and SEM within budget; tasks baked into plan/implement, not deferred to ship only.
- **Privacy & Legal Compliance** (Violet) — expanded surface: cookie/ToS policies, anonymization, opt-out, log retention, subprocessors, data-subject rights, and digital accessibility regulations.
- **UX & Frontend Design** (Elise) — Laravel-aligned stack preference (Blade → Livewire → Tailwind → Bootstrap → Vue → Flux/Filament); default Nordic minimal aesthetic; **dark + light mode** unless explicitly opted out.
- **Accessibility** (Elise + Emma + Violet) — WCAG 2.2 Level AA from design through ship; Emma covers semantic SEO overlap and Lighthouse Accessibility ≥ 90; Violet covers EAA, EN 301 549, Legge Stanca, ADA, and accessibility statement pages when required.
- **SEO Structure & Discoverability** (Emma) — URL conventions, breadcrumbs with JSON-LD, and mandatory **`robots.txt`**, **`sitemap.xml`**, and **`llms.txt`** kept updated with every public route change.
- **Brand identity & assets** (Elise → Lauren) — when the client provides no artwork, Elise creates **`favicon.svg`**, logo (SVG), coordinated brand imagery, OG/social PNG (1200×630), and apple-touch-icon; Lauren applies them to distribution and meta tags.

### Changed

- Persona roles updated across `shared-runtime.md`, README, and skills — John (multi-tenancy, APIs), Jack (Gitflow, CI/CD, Cloudflare, observability), Lars (`security.txt`, pipeline gates), Anne (Pest/CI test gates), Robert (branch hygiene), Emma (structural SEO + a11y overlap), Elise (Laravel UI stack, WCAG, brand assets), Lauren (marketing + Elise social assets), Violet (EAA/accessibility law).
- **`larapilot-inception`** — PRD template extended with multi-tenancy, development/delivery, SEO/discoverability, UX/frontend, and marketing sections; workflow steps aligned to new policies.
- **`larapilot-plan`** — plans now include Gitflow branch names, semver/CHANGELOG, security files, CI scaffold, Cloudflare/observability, multi-tenancy, accessibility, brand assets, and structural SEO tasks.
- **`larapilot-implement`** — implementation contract covers scaffolding defaults, architecture standards, Gitflow, `security.txt`/`SECURITY.md`, frontend/a11y/SEO deliverables, and multi-tenancy patterns.
- **`larapilot-ship`** — OWASP gate expanded (WAF/CDN, observability live); Emma launch checks include `llms.txt`, breadcrumbs, Lighthouse a11y; Violet checks digital accessibility; Lauren verifies Elise brand/social assets and `favicon.svg`.
- **`larapilot-design`** — rewritten for Laravel stack alignment, WCAG mockup requirements, brand asset deliverables (`favicon.svg`, `logo.svg`, `og-default.png`), and Emma/Violet/Lauren collaboration notes.
- **`larapilot-review`** — Robert presents Gitflow branch hygiene, CHANGELOG/security-file updates, and testing evidence per delivery target.
- **`core.blade.php`** — Boost guidelines summarize scaffolding defaults, brand assets, and key policies for all Laravel work.
- **README** — Team policies section documents architecture standards, security budget, cloud/edge/observability, marketing, privacy/legal, UX/frontend, and brand assets.

## [1.1.0] - 2026-07-09

### Added

- **`larapilot:update` command** — one-step refresh after a package upgrade: rewrites `.larapilot/shared-runtime.md` from the packaged copy and re-runs `boost:update` to republish guidelines and the `/larapilot-*` skills, without ever touching `.larapilot/config.yaml`. `--skip-boost` refreshes the runtime only. Suitable for Composer `post-update-cmd` hooks (documented in README and site docs).
- **Budget Sensitivity** (`Tracked` | `Relaxed`): during inception Aurora asks whether budget should drive decisions; `Relaxed` excludes budget evaluation while keeping loosened business validation (short advisories on lock-in and hard-to-reverse costs, no cost-based vetoes). Persisted in the PRD under `## Technical Architecture` and honored by the plan and ship skills.
- **Vendor & Package Policy** in the shared runtime: Laravel built-ins/first-party → Spatie packages (preferred third-party source) → Filament and its plugins (preferred route for admin/control panels) → other vetted vendors, with a mandatory maintenance/compatibility/security check (`composer audit`) before any `composer require`. Referenced by the inception, spec, plan, design, and implement skills.

### Changed

- `larapilot:install` no longer refreshes the shared runtime on already-installed projects: it now fails fast with a hint pointing to `larapilot:update` (the dedicated refresh path) or `--force`. The 1.0.0 refresh-on-rerun behavior moved to `larapilot:update`, which exits `0` so it can run in scripts.
- Sebastian (Innovator) now **must propose competitor data porting** whenever comparable products exist: concrete import paths for users switching from rival products (CSV/API importers, onboarding flows) plus lock-in-free export — promoted to Functional Requirements and first-class backlog specs. Docs clarified accordingly (was previously an ambiguous "import/export opportunities").

## [1.0.0] - 2026-07-08

### Added

- Personas **Benjamin** (Business Consultant), **Sebastian** (Innovator), **Aurora** (FinOps), and **Violet** (Legal Expert).
- **Delivery target** selection in inception (`MVP`, `V1 Complete`, `Full Product`, `Enterprise`) — Mark asks early via AskQuestion; MVP is the default lens, not a hard ceiling.
- Plan skill: Emma and Lauren join for public-facing specs (SEO, Analytics, and OG/share tasks baked into plans, not deferred to ship).
- `larapilot:install` always refreshes `.larapilot/shared-runtime.md`, including on already-installed projects, without resetting `config.yaml`.

### Changed

- Emma expanded to **SEO & Web Performance Specialist** (Analytics, tracking events, Lighthouse targets).
- Inception, plan, implement, review, ship, autopilot, and design skills aligned to delivery target and the expanded persona roster.
- Shared runtime: delivery target policy, persona guidance, and public-site / GDPR notes.
- Install command writes shared runtime before the already-installed check and reports when only the runtime doc was refreshed.

## [0.3.0] - 2026-07-08

### Added

- `/larapilot-ship` skill — OWASP security gate, multi-platform deploy (Cipi preferred, Forge, Laravel Cloud, Ploi, Kubernetes, custom), and web launch checks for public sites.
- Personas **Lars** (Security), **Jack** (DevOps), **Emma** (SEO), and **Lauren** (Social Media).
- Config paths for `security` (`.larapilot/docs/security/`) and `launch` (`.larapilot/docs/launch/`).

### Changed

- Requirements Analyst persona renamed from Mark to **Tom** (fixes duplicate name with PM Mark).
- Workflow documented as discovery → backlog → plan → implement → review → **ship**.
- Eight `/larapilot-*` skills published via Boost (was seven).
- Expanded persona roles across discovery, implement, review, plan, and ship skills.
- README and docs site: install steps, MCP config example, workflow table, and ship phase documentation.

## [0.2.0] - 2026-07-08

### Changed

- Discovery interview and skills now require fixed-choice questions to use the editor **AskQuestion** tool instead of plain A/B/C lists in chat.
- Shared runtime documents AskQuestion usage (`allow_multiple`, persona framing in chat vs. options in the wizard).

## [0.1.0] - 2026-07-08

### Added

- Initial release: spec-driven product workflow for Laravel via Laravel Boost (skills, Artisan CLI, MCP server, `.larapilot/` artifacts).
- Seven `/larapilot-*` skills: inception, spec, design, plan, implement, review, autopilot.
- `larapilot:spec-delete` command to remove a spec together with its spec and plan files.
- Workflow transition guards: `spec-start` requires `PLANNED`, `spec-review` requires `IN PROGRESS`, `spec-approve` and `spec-request-changes` require `REVIEW`, and `spec-plan` refuses specs already in `REVIEW` or `DONE`.
- Spec codes are validated everywhere they are written to disk, preventing path traversal via crafted codes.
- Specs added without a status now default to the configured `TODO` status.
- Italian spec section names (`Storia Utente`, `Dimostra`, `Criteri di Accettazione`) are accepted by the validator.
- GitHub Actions CI (Pest across PHP 8.2–8.4 × Laravel 11/12, plus Pint and PHPStan).

### Changed

- Requires PHP `^8.3` and Laravel `^12.41.1|^13.0`; PHP 8.2 and Laravel 11 are no longer supported (the `laravel/boost`/`laravel/mcp` dependency chain cannot resolve on Laravel 11).
- Allows `laravel/boost` `^2.0` alongside `^1.0` — boost 2.x is required for Laravel 13.
- Validation commands (`validate-prd`, `validate-spec`, `validate-plan`) exit with code `2` when validation fails; `spec-add` and `spec-plan` return an error envelope with the findings instead of a success envelope.
- Spec body validation requires marked-up sections (`**User Story**` or `## User Story`) instead of matching plain substrings.
- All backlog, spec, plan, PRD, and project config writes are atomic (temp file + rename).
- Artisan commands and config publishing stay registered when `larapilot.enabled` is `false`, so `larapilot:doctor` can diagnose a disabled install; the MCP server and mockup route remain gated.
- Project config is memoized per process instead of re-parsing `.larapilot/config.yaml` on every access.

### Fixed

- All commands taking arguments (`spec-show`, `spec-plan`, `spec-start`, `spec-review`, `spec-approve`, `spec-request-changes`, `task-done`, `validate-plan`) crashed with a container resolution error because command arguments were type-hinted in `handle()`.
- The mockup controller no longer falls back to serving unresolved paths when `realpath()` fails.
