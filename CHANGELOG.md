# Changelog

All notable changes to `larapilot` will be documented in this file.

## [1.7.2] - 2026-07-15

Website version update and minor fixies.

## [1.7.2] - 2026-07-15

### Added

- **Bootstrap 5 design reference** — packaged reference at `resources/larapilot/design-systems/bootstrap-5/` with `tokens.css`, `components.md`, `sources.md`, and **6 static HTML screens** (landing, dashboard, login, settings, components). Copied to `.larapilot/design-systems/` on install/update.
- **Tailwind CSS design reference** — packaged reference at `resources/larapilot/design-systems/tailwind/` with `tokens.css`, `components.md`, `sources.md`, and **6 static HTML screens** (landing, dashboard, login, settings, components). Copied on install/update.
- **AdminLTE design reference** — packaged reference at `resources/larapilot/design-systems/adminlte/` derived from [AdminLTE 4](https://adminlte.io/) (Bootstrap 5.3 admin template). Includes `tokens.css`, `components.md`, `sources.md`, and **6 static HTML screens** (dashboard, resource list, login, settings, components). Copied on install/update.

### Changed

- **Filament design system accuracy** — sidebar now matches Filament v3 default (light background, subtle primary active state, header strip) instead of the incorrect dark sidebar; updated `tokens.css`, `components.md`, and all packaged HTML shells.
- **Starter Kit tokens** — added missing `.sk-badge--destructive` variant.
- **`shared-runtime.md`**, **`larapilot-design`**, **`core.blade.php`**, **`InstallCommand`**, **`ConfigService`** — document all five packaged design systems (Filament, Starter Kit, Bootstrap 5, Tailwind, AdminLTE).
- **Persona profile refinements** — Joe (design system with Elise, design → implement → review), Albert (baseline technical docs always + extended scope per spec approval), Alex (FE/BE integration per Andrew/Joe, Jack when infra), Aurora (SaaS economics, storage/compute sizing, proactive cost optimization), Robert + Sabrine (refactoring/porting review gate), Emily + Marika (typo/translation consistency in review), Anne (device coverage + manual test handoff to humans).
- **Docs site** — v1.7.2 release highlights, expanded `/larapilot-design` copy, and persona phase text for review and design-system ownership.

## [1.7.1] - 2026-07-15

### Added

- **New personas — Albert, Ricky, Zoey; Joe scope narrowed to web frontend**
    - **📝 Albert** (Tech Writer) — technical docs, OpenAPI/Swagger, draw.io/Mermaid diagrams, runbooks, PDF client manuals (EN default; localized with Emily).
    - **📱 Ricky** (App Developer) — inherits mobile/native/hybrid scope from Joe: Flutter, React Native, Capacitor, device APIs (camera, mic, sensors, GPS, Bluetooth, NFC/RFID), store release.
    - **🤖 Zoey** (AI Guru) — cross-cutting prompt economy, sub-agent orchestration, session/credit risk; active in every skill.
    - **✨ Joe** (Frontend Expert) — web frontend only: visual impact, JS, Three.js animations, client performance (mobile scope → Ricky).
    - Updated `shared-runtime.md`, all affected skills, `core.blade.php`, `config/larapilot.php`, and docs (`team-grid` 3 columns for 27 personas).

## [1.7.0] - 2026-07-15

### Added

- **Workflow JSON API** — read-only REST endpoints under `/larapilot/api/` (same access rules as the dashboard): `GET /board` (full Kanban snapshot), `GET /specs` (optional `?status=` filter), `GET /specs/{code}` (spec + plan + tasks), `GET /prd`. OpenAPI 3.1 spec at `/larapilot/api/openapi.json` and Swagger UI at `/larapilot/api/docs`, linked from the dashboard nav. Documented in `docs/index.html` (`#api`, `#dashboard`).
- **`/larapilot-feature`** — mini-inception for one new evolutiva on an existing project: interactive AskQuestion rounds (MoSCoW, FR traceability, mockup-first, legacy touch), optional PRD `FR-XXX` sync, spec via `spec-add`. Mark + Tom lead; Sabrine/John/Andrew join when relevant.
- **`/larapilot-bug`** — Sophia-led bug triage with interactive intake: severity, environment, security, routing to `spec-add` (fix spec) or `spec-request-changes` (rework); logs to `{paths.support}/intake.md`; Critical production → `hotfix/*` note.
- **Legacy folder — proactive refactor proposal in inception** — when `.larapilot/legacy/` has content, Mark (with Sabrine) asks via AskQuestion whether to pursue legacy rewrite/port before deep discovery.
- **Sabrine — expanded expertise** — content scraping/extraction, DB migration, and assets porting (legacy → new); updated across `shared-runtime.md`, inception/spec/plan skills, `core.blade.php`, and `legacy/README.md`.
- **Docs — feature & bug walkthroughs** — `docs/index.html#examples-incremental` with full interactive examples for `/larapilot-feature "Add PDF export for invoices"` and `/larapilot-bug "SSO login fails on Safari"`.
- **PRD Living Document** — selective PRD updates (features + requirement gaps only); `## PRD Revision History`; bugs/rework/hotfixes stay in specs + `support/intake.md`. Documented in `shared-runtime.md`, `larapilot-feature`, `larapilot-bug`, `larapilot-review`, `larapilot-spec`, inception template, `core.blade.php`, and docs.

- **New personas — Marika, Sabrine, Andrew, Joe**
    - **✍️ Marika** (Copywriter) — creates and reviews website & application copy in any tone; maps legacy content during porting.
    - **🔄 Sabrine** (Legacy Porting Specialist) — leads legacy analysis, **content scraping**, **DB & assets porting**, content/feature inventory, parity matrix, porting proposals, and review parity checks.
    - **� Andrew** (Laravel Expert) — Laravel & ecosystem best practices from laravel.com, Laracasts, Filament, Spatie, Laravel Daily, Filament Examples, Laravel.io, Laravel News, and other authoritative sources.
    - **✨ Joe** (Frontend Expert) — visual impact, JS frontend, Three.js animations, API integration, client-side performance.
    - Updated `shared-runtime.md`, all affected skills, `core.blade.php`, `legacy/README.md`, and docs.

## [1.6.1] - 2026-07-12

### Added

- **MoSCoW prioritization on Functional Requirements** — each `### FR-XXX` in the PRD now carries `**MoSCoW:** Must | Should | Could | Won't`. Mark assigns tags during inception; `larapilot-spec` uses them as the primary input for bootstrap/deferral (with default backlog priority mapping). Documented in `shared-runtime.md`, `larapilot-inception`, `larapilot-spec`, and `core.blade.php`.

## [1.6.0] - 2026-07-11

### Added

- **Filament design reference for admin mockups** — packaged reference at `resources/larapilot/design-systems/filament/` merged from two Figma community kits: [Design System](https://www.figma.com/community/file/1413822581847485668/filament-3-design-system) (Giovanni Zanin) and [UI Kit (Free)](https://www.figma.com/community/file/1417716904167561805/filament-3-free) (VhiWEB). Includes `figma-sources.md`, `tokens.css`, `components.md`, and **17 static HTML screens** in `html/`. Copied to `.larapilot/design-systems/` on install/update. New config path `paths.design_systems`.

- **Laravel Starter Kits design reference for authenticated app mockups** — packaged reference at `resources/larapilot/design-systems/starter-kit/` derived from the [official Laravel starter kits](https://laravel.com/starter-kits). Includes `sources.md`, `tokens.css` (shadcn oklch variables from react-starter-kit), `components.md`, and **7 static HTML screens** in `html/`. Copied to `.larapilot/design-systems/` on install/update.

- **Client materials intake** — new config path `paths.client_materials` (`.larapilot/client-materials/`): structured folder for pre-existing documentation, analysis, briefs, and client-provided materials. Created on install with README stub. **All skills** must read and honor client materials alongside the PRD; inception cross-checks them in the interview and asks clarifying questions when needed.

- **Legacy rewrite & porting** — new config path `paths.legacy` (`.larapilot/legacy/`): holds legacy codebase snapshots, schema dumps, and migration notes for rewrite/port projects. Skills enforce **zero feature and data loss** unless explicitly scoped out in the PRD; parity matrix in `{paths.research}/legacy-parity.md`; bootstrap backlog with migration specs first.

- **Reference products & Sebastian deepsearch** — new config path `paths.research` (`.larapilot/research/` with `reference-products/` subfolder). During inception, Sebastian asks for competitor/inspiration URLs when useful and runs **deepsearch** (WebSearch/WebFetch) — persisting feature, UX, and design findings per product. Reports feed PRD, spec, design, and plan skills.

- **`ConfigService::ensureIntakeReadmes()`** — writes README stubs into intake folders on install when missing (preserves user content on update).

- **`.gitkeep` in workspace directories** — `ensureGitkeeps()` writes an empty `.gitkeep` in every Larapilot scaffold folder (including `specs/`, `plans/`, `mockups/`, all `docs/*` subfolders, intake paths, `research/reference-products/`, and `brand/`) so empty directories are tracked by Git.

### Changed

- **`larapilot-inception`** — workflow step 0 scans client materials and legacy folders; Sebastian deepsearch + reference-product AskQuestion; PRD template adds **Project Origin**, **Reference Products**, and **Legacy parity** sections.

- **Downstream skills** (`spec`, `plan`, `implement`, `design`) — mandatory consultation of client materials, legacy, and research paths; legacy parity and migration verification in plan/implement; Elise reads reference-product research for design patterns.

- **`shared-runtime.md`**, **`core.blade.php`**, **`config/larapilot.php`**, **`config.yaml.stub`**, **`InstallCommand`**, **`larapilot-design`** — document Filament mockup design system and scaffold `paths.design_systems`.

- **Authenticated app UI route** — panel choice expanded from Filament vs custom to **Filament vs [Laravel Starter Kits](https://laravel.com/starter-kits) (Livewire/React/Vue/Svelte) vs custom** across `shared-runtime.md`, `core.blade.php`, and inception/spec/plan/design/implement skills. Packaged Starter Kit mockup design system (`starter-kit/`) with tokens and HTML screens; Elise must use it when the PRD chose a kit variant.

## [1.5.1] - 2026-07-10

### Changed

- **Dashboard — board header metrics** — removed the **Story points** and **Subtasks** summary cards (including `X% delivered` and `Y% complete` completion rates). The board header now shows only the primary backlog KPIs: total specs, done, completion %, and WIP. Per-spec story-point badges, subtask progress bars (`done/total`), and per-column SP totals on the Kanban are unchanged.

- **Dashboard — Kanban UX** — priority tags are color-coded (**CRITICAL/HIGH** red, **MEDIUM** orange, **LOW** green). Column headers show only spec count and total SP (blue pill badge, same style as spec cards); per-column `x/y tasks` removed. On viewports ≤768px the board scrolls horizontally as swipeable columns instead of squeezing into a five-column grid.

- **Dashboard — header copy** — removed “(dev only)” from the topbar subtitle; availability is still indicated in the footer note.

## [1.5.0] - 2026-07-10

### Added

- **`GitService`** — resolves git commits for workflow artifacts: auto-detects task commits from Conventional Commit subjects (`feat(US-XXX): TASK-NN …`), merge commits for spec approval (merge/PR messages referencing the spec code), and builds GitHub commit URLs from `origin` remotes. Registered in `LarapilotServiceProvider`; covered by `tests/Feature/GitServiceTest.php`.

- **Git-linked workflow CLI** — `larapilot:task-done` and `larapilot:spec-approve` accept optional `--commit=`; when omitted, the most recent matching commit is auto-detected from git history. Task plans persist a `commit` object on DONE tasks; approved specs persist `merge_commit` in `backlog.yaml`. JSON envelopes return `commit` / `merge_commit` metadata.

- **Dashboard — delivery metrics & traceability** — board shows **story points** (done/total, completion %) and **subtask progress** (done/total tasks, per-spec progress bars, per-column SP/task totals). Spec cards and detail pages show **merge commit** links when a spec is DONE; task accordions show linked commit SHA, subject, and remote URL. Spec detail tasks use exclusive `<details>` accordions with `@stack('scripts')` in the layout.

- **Story-point metrics in `SpecService` / `PlanService`** — `metrics()` now includes `total_points`, `done_points`, `points_completion_rate`, `total_tasks`, `done_tasks`, `task_completion_rate`, and `specs_with_plans`; `DashboardService` merges spec and plan metrics and enriches board cards with per-spec `tasks` progress.

- **Test helpers** — `initTestGitRepository()` in `tests/Pest.php` for feature tests that exercise git commit resolution.

### Changed

- **Local development environment** — Sail/Docker is no longer the assumed local stack. **Jack** now **asks the user** via AskQuestion (Sail, Herd, not defined yet, or other), recommending the best fit for team, OS, and required services. The choice is recorded in the PRD (`## Technical Architecture`); `larapilot-spec`/`larapilot-plan`/`larapilot-implement` honor it (and ask when missing) — Sail/Herd scaffold tasks are planned only when explicitly chosen. Updated `shared-runtime.md`, `larapilot-inception`, `larapilot-plan`, `larapilot-implement`, `larapilot-spec`, `task-templates.md`, and `core.blade.php`.

- **Infrastructure & deploy** — Cipi, Cloudflare, and AWS are no longer assumed defaults. **Jack** now **asks the user** via AskQuestion for **deploy platform**, **edge/CDN/WAF**, and **cloud/compute** (recommending Cloudflare for public edge and AWS for compute/data when feasible). Choices are recorded in the PRD; `larapilot-spec`/`larapilot-plan`/`larapilot-implement`/`larapilot-ship` honor them (and ask when missing) — platform-specific scaffold tasks run only for explicitly chosen targets. Updated `shared-runtime.md`, all affected skills, `core.blade.php`, and `larapilot-ship`.

- **`larapilot:spec-approve`** — approval logic moved to `SpecService::approve()` (checklist tick, status transition, rework reset, merge-commit link) instead of inline command handling.

## [1.4.0] - 2026-07-09

### Added

- **New personas — Matt, Oliver, Sophia, Emily**
    - **🔗 Matt** (Integration Manager) — hands-on API & third-party service delivery with Alex, John, Elise; Sebastian proposes, Matt wires.
    - **🎯 Oliver** (Ethical Hacker) — red-team assessments before ship; reports findings to Lars.
    - **🎧 Sophia** (Support Manager) — post-ship bug intake/triage, maintenance backlog, docs & software updates with Lars.
    - **🌍 Emily** (Translator) — multilingual UI, currency, timezones, country-target culture with Violet.
    - New `paths.support` (`.larapilot/docs/support/`); security folder holds Lars OWASP + Oliver red-team reports.
    - Updated `shared-runtime.md`, all skills, `config/larapilot.php`, `core.blade.php`, README.

- **Workflow dashboard** — dev-only read-only UI at `/larapilot` (board, PRD viewer, spec/task detail). Disabled in production; configure with `LARAPILOT_DASHBOARD_ROUTE`.

- **Task body templates** — new `.larapilot/task-templates.md` (published on install/update): TASK-00 Git bootstrap, entity/non-entity/test/fix templates with `## Git Deliverables` and `## Test Data` sections; `larapilot-plan` and `larapilot-implement` reference it; `SharedRuntime::refresh()` copies all packaged docs.

### Changed

- **Project Kind — inception interview branches** — Mark now opens discovery with **AskQuestion** for `Personal`, `Website`, or `Application`, switching persona depth and follow-up questions (website type, delivery target, multi-tenancy). Recorded in PRD `## MVP Scope`; downstream skills (`spec`, `design`, `ship`) read it. Updated `shared-runtime.md`, `larapilot-inception`, `larapilot-spec`, README, and docs.

- **Alex — factories, seeders & strict Gitflow** — Alex must create/update Eloquent factories (domain-meaningful Faker data, states, relationships) and keep seeders (`DatabaseSeeder` + dedicated seeders) producing a coherent demo dataset; updates ship in the same task as model/migration changes with `migrate:fresh --seed` verification. **Git discipline** is now non-negotiable: one atomic Conventional Commit per completed task or evolutiva, push after each task, and open/update an internal PR toward `develop` (Robert blocks handoff on violation). Updated `shared-runtime.md`, `larapilot-plan`, `larapilot-implement`, `larapilot-review`, `core.blade.php`, and README.

- **Mobile First — Elise & Anne** — UI design and tests must follow **Mobile First**: smallest viewport first (320–375 px), progressive desktop enhancement without neglecting large screens; extremely navigable and simple on any device/resolution. Elise documents breakpoint/nav contract in mockup README; Anne plans and runs multi-viewport tests (375 / 768 / 1280 px minimum, mobile nav, axe at mobile). Updated `shared-runtime.md`, `larapilot-design`, `larapilot-plan`, `larapilot-implement`, `larapilot-inception`, `larapilot-review`, `core.blade.php`, and README.

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
