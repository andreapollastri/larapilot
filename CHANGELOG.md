# Changelog

All notable changes to `larapilot` will be documented in this file.

## [2.5.1] - 2026-09-01

### Added

- **Mandatory JSON API token (`settings.api_auth`, OFF by default)** — a project setting that locks down **every** `/larapilot/api/*` endpoint behind `LARAPILOT_API_TOKEN`. Until now the token was purely optional: with no `LARAPILOT_API_TOKEN` set, all GET endpoints (`/board`, `/specs`, `/specs/{code}`, `/prd`, `/diagnostics`, `/backstage`, `/openapi.json`, `/docs`) answered unauthenticated in the allowed (dev/staging) environments. With `api_auth=YES` the `EnsureApiAuthorized` middleware requires the bearer token (or `X-Larapilot-Token` header) on reads **and** writes, and **fails closed with HTTP 503** when `LARAPILOT_API_TOKEN` is not configured — a missing env var can no longer silently leave the API open. When OFF the legacy behaviour is unchanged (token enforced only when set; writes still blocked outside local). The gate never touches the `/larapilot` dashboard UI (that is `settings.dashboard_auth`) or the MCP server, and the API is still never served in `production`. New `config.yaml` key `settings.api_auth` (`false`) + updated `api` block comment in `config/larapilot.php`; new `ConfigService::apiAuthEnabled()` / `allowedApiAuthModes()`. Enable with `php artisan larapilot:settings-set --api-auth=YES`; setup in `.larapilot/integrations.md`.
- **API rate limiting (`larapilot.api.rate_limit`, default `120,1`)** — a per-IP `throttle` on `/larapilot/api/*`, resolved from config at request time via the named `larapilot-api` limiter (register in `LarapilotServiceProvider`). A `"max,minutes"` spec; empty or a non-positive max disables it. `LARAPILOT_API_RATE_LIMIT`.
- **API audit log (`larapilot.api.audit`, ON by default)** — `AuditLarapilotApiWrites` middleware + `ApiAuditService` append one JSON line per **mutating** `/larapilot/api/*` request (timestamp, method, path, IP, whether a token was sent, response status — never bodies) to `.larapilot/api-audit.log`, git-ignored automatically on first write. Read back with `ApiAuditService::entries()`. `LARAPILOT_API_AUDIT=false` / `LARAPILOT_API_AUDIT_FILE`.
- **Security headers** — `AddLarapilotSecurityHeaders` on both the dashboard and JSON API route groups: `X-Content-Type-Options: nosniff` and `Referrer-Policy: no-referrer` everywhere, plus `X-Frame-Options: DENY` on the dashboard UI.
- **Conditional requests on the API** — `GET /board`, `/specs`, `/specs/{code}`, `/prd`, `/metrics` now send a content-derived `ETag` and honour `If-None-Match` with `304 Not Modified` (plus `Cache-Control: private, must-revalidate`), so pollers (Backstage, CI) revalidate cheaply.
- **Paginated spec list** — `GET /larapilot/api/specs` accepts `?page` (1-based, clamped to the last page) and `?per_page` (1-200, default 50); the response gains `total`, `page`, `per_page`, `total_pages` (`count` stays the count on the current page).
- **`GET /larapilot/api/metrics`** — new endpoint + `MetricsService`: backlog completion, plan/task progress, and — when the Lucille usage ledger is on — an effort-timing block (tracked hours, tokens, estimated entries, first/last activity). Documented in the OpenAPI contract (`Metrics` tag, `MetricsResponse` schema). `larapilot:metrics` now delegates to `MetricsService::flat()`.
- **Security scan (`settings.security_scan`, OFF by default)** — opt-in project setting that folds a static Laravel security scan into `/larapilot-review` and the pre-ship gate, backed by the optional dev package [`andreapollastri/checkpoint`](https://github.com/andreapollastri/checkpoint) (added to `composer.json` `suggest`, never a hard dependency). When `YES`, `/larapilot-review` runs `php artisan checkpoint:scan --json`: `FAIL` findings become review blockers (fix, or waive via `larapilot:decision-log`), `WARN` findings become review notes; if the package is absent the review skill stops and asks for `composer require --dev andreapollastri/checkpoint`. Larapilot never installs the scanner and never runs it unless the setting is on. Owned by **Lars** alongside `dashboard_auth` / `api_auth`. New `config.yaml` key `settings.security_scan` (`false`); new `ConfigService::securityScanEnabled()` / `allowedSecurityScanModes()`; `larapilot:settings-set --security-scan=YES|NO`; dashboard **Settings** page lists it as **Security scan**; `larapilot:doctor` / `larapilot:update` report it as a missing key on an older `config.yaml`. Setup in `.larapilot/integrations.md` → **Security scan**.
- **Self-hosted VPS provisioning (`larapilot:vps-provision`)** — a new command that emits a standalone `provision.sh` for an Ubuntu 24.04/26.04 LTS server hosting several Larapilot/Claude-Code projects for a team over SSH. `--output` (default `./provision.sh`), `--with-readme` (also writes `VPS-README.md`), `--stdout`, `--force`. The one script embeds and generates four CLIs: `prj-ai` (admin: `config` / `list` / `add` / `del` / `php` / `user-add` / `user-del` / `deploy` / `rollback` / `preview {list,up,down,reap}` / `workspace-init`), `prj-work` (per-developer project menu with persistent tmux), **`prj-pr`** — a provider-agnostic PR/MR opener that supports **GitHub, GitLab, Bitbucket Cloud and Azure DevOps** (via `gh` / `glab` when authenticated, REST API otherwise) — and **`prj-token`** (each developer saves their own git token). Provisioning installs PHP 8.3/8.4/8.5 (FPM, one tuned pool per project), MySQL, Redis, Nginx + certbot, Supervisor, cron, Node LTS, Composer, Claude Code and all three git CLIs. Per-project isolation (own system user, DB, FPM pool, vhost) + Claude Code `deny` guardrails per workspace. **Atomic zero-downtime deploys**: each deploy `git archive`s HEAD into `releases/<ts>-<sha>/`, builds it (Composer/npm/`artisan optimize`/migrate run only when the matching paths changed; `vendor` + `node_modules` + `public/build` carried forward for a ~3–5s code-only build), then flips the live `current` symlink with `mv -T` (`rename(2)`) — nginx serves `current/public` and passes `$realpath_root` so OPcache keys on a fresh path with no FPM reload. A failed build is discarded and `current` never moves; `prj-ai rollback <project>` flips back to the previous release (retention: `RELEASES_KEEP`, default 3); `.env` + `storage` live in `shared/` and are symlinked into every release. Optional `MIGRATE_MAINTENANCE` wraps migrate + flip in `artisan down`/`up`. **Per-developer previews** (opt-in per project, default on): every developer who opens a project gets `<user>-<project>.<domain>` serving their live `~/work` tree from an on-demand FPM pool that runs as their uid, with its own MySQL DB `prv_<project>_<user>` seeded from staging, behind shared HTTP Basic Auth; a single wildcard `*.<domain>` DNS record covers project + preview hosts; `prj-preview-reap.timer` tears down previews idle beyond `PREVIEW_TTL_DAYS` (default 14). **Mandatory per-developer git token**: `prj-token` stores `https://<user>:<token>@<host>` in `~/.git-credentials` (0600); `workspace-init` runs `GIT_TERMINAL_PROMPT=0 git ls-remote` with it and refuses to create the workspace or preview unless the token can read that project's repo — so commits, pushes and PRs are always attributed to the developer, and nobody can open a project they lack access to. Efficiency: OPcache/realpath tuning, static-asset cache headers, `Nice`/`idle` I/O + randomised delay on the auto-deploy timer. Bundled at `resources/larapilot/vps/{provision.sh,README.md}`.

### Changed

- **`larapilot:settings-set`** — new `--api-auth=YES|NO` and `--security-scan=YES|NO` options; `config-show` exposes `settings.api_auth` and `settings.security_scan` in `data.settings`; the dashboard **Settings** page lists them as **API auth** and **Security scan**; `larapilot:doctor` / `larapilot:update` report either as a missing key on an older `config.yaml`.
- **OpenAPI document** (`/larapilot/api/openapi.json`) — now declares `components.securitySchemes` (`bearerToken` HTTP bearer + `larapilotTokenHeader` `X-Larapilot-Token` API key) and a top-level `security` block, plus an `Unauthorized` (HTTP 401) response component, so the Swagger UI at `/larapilot/api/docs` exposes an **Authorize** button.
- **Docs — API auth** — `README`, `shared-runtime` (new **API auth** setting section + Project Settings defaults/list), `runtime-ops`, `.larapilot/integrations.md` (new **API access** section), the `larapilot-settings` skill (new AskQuestion round + rules, Lars owns the toggle), Boost core guideline, `config.yaml.stub`, and the docs site updated for `settings.api_auth`.
- **Docs site** — the **What's new** section (and its nav links / styles) has been removed from `docs/index.html`; release notes live in this `CHANGELOG` only.
- **`config/larapilot.php`** — new `api.rate_limit`, `api.audit`, `api.audit_file` keys under the existing `api` block.
- Site / package version **v2.5.1**.

## [2.5.0] - 2026-09-01

### Added

- **`larapilot-adopt` skill (brownfield onboarding)** — reverse-engineers a complete PRD from an existing production Laravel codebase that was built without Larapilot, so the normal loop (`spec → plan → implement → review → ship`) can continue on it. Reads models/migrations, routes/controllers, jobs, policies, config, packages, CLI/scheduler, CI, and frontend surfaces (optional readonly `Explore` sub-agent for large repos; never under `effort: ECO`), derives personas + functional requirements from shipped behavior with file-path evidence, asks only the gaps the code can't answer, then writes `.larapilot/research/codebase-analysis.md` and the PRD via `larapilot:prd-write` + `larapilot:validate-prd`.
- **`Project Origin: Adopted (existing codebase)`** — new value recorded in the PRD `## MVP Scope` by `larapilot-adopt`; downstream skills treat it like Greenfield for scoping (Sabrine stays silent, no legacy parity contract).
- **Azure DevOps remote forge (`settings.azure`)** — fourth optional forge alongside `github` / `gitlab` / `bitbucket`, OFF by default and orthogonal to `git_mode`. New `AzureDevopsService` + `larapilot:azure-status` probe report `az` CLI / `azure-devops` extension / auth (`az login` or `AZURE_DEVOPS_EXT_PAT` / `AZURE_DEVOPS_PAT` PAT) and whether `origin` is an Azure Repos remote. `GitService` recognises `dev.azure.com`, `ssh.dev.azure.com:v3/…`, and legacy `*.visualstudio.com` remotes — `originProvider()` returns `azure`, `originRepoSlug()` yields `{org}/{project}/{repo}`, and commit URLs resolve to `…/_git/{repo}/commit/{sha}`. Toggle with `larapilot:settings-set --azure=YES`; setup in `.larapilot/integrations.md`.
- **Optional dashboard auth (`settings.dashboard_auth`, OFF by default)** — HTTP Basic Auth in front of the `/larapilot` dashboard **UI** only; the dashboard stays open in the allowed environments until it is switched on. New `DashboardAuthService` + `EnsureDashboardAuthorized` middleware; credentials are argon2id/bcrypt hashes in `.larapilot/auth.yaml` (no database, no `User` model, never cleartext), and the file is appended to `.gitignore` automatically the first time a user is written. New `larapilot:dashboard-user {list|add|remove}` command — `add` prompts for the password (or takes `--password=`). Failed sign-ins are rate-limited per IP (`LARAPILOT_DASHBOARD_AUTH_MAX_ATTEMPTS`, default 30/min); with the setting ON and no users configured the dashboard fails closed with HTTP 500. The gate **never** touches `/larapilot/api/*` (still `LARAPILOT_API_TOKEN`) or the MCP server, and the dashboard is still never served in `production`. New `config.yaml` key `settings.dashboard_auth` (`false`) + `dashboard_route.auth` block in `config/larapilot.php`. Enable with `larapilot:settings-set --dashboard-auth=YES`; setup in `.larapilot/integrations.md`.
- **Decision journal + regression guard (`settings.decision_log`, ON by default)** — every explicit user choice across all phases (AskQuestion answers **and** free-text directives/preferences) is appended, timestamped, to `.larapilot/decisions.yaml` via `larapilot:decision-log --topic= --value= [--source= --skill= --spec= --rationale= --supersedes=]`. Before a phase records a value on a topic that already carries a decision it runs `larapilot:decision-check --topic= --value=`; when the candidate differs from an earlier non-superseded choice the command returns the earlier decision(s) so the skill can surface them ("on {date} you chose orange for the background; confirm red supersedes it") via AskQuestion and only then re-log with `--supersedes={id}`. The journal is never rewritten — a reversal is a new entry. New `DecisionService`; disable with `larapilot:settings-set --decision-log=NO`.
- **Code change history (`settings.code_history`, OFF by default)** — opt-in per spec/task log of files and new-file line ranges touched, derived automatically from the task's git commit. `larapilot:code-log --spec=US-XXX --task=TASK-XX [--commit= | --range=]` appends to `.larapilot/code-history.yaml` (falls back to the working-tree diff when no commit resolves); `larapilot:code-history [--file= --spec=]` reports per-file touchpoints. New `CodeHistoryService`; new `GitService::changeSet()` parses `git diff --numstat` + `--unified=0` hunks. Enable with `larapilot:settings-set --code-history=YES`.
- **`config.yaml` keys** — `settings.decision_log` (`true`), `settings.code_history` (`false`), `paths.decisions`, `paths.code_history`. `config-show` exposes all four; `larapilot:doctor` / `larapilot:update` report them as missing keys on an older `config.yaml`. Read-only `larapilot:decision-check` and `larapilot:code-history` are added to the MCP `RunArtisanTool` allowlist.

### Changed

- **Docs / runtime** — `README`, Boost core guideline, `shared-runtime` (pack table, Output Economy per-phase table, sub-agent table, Remote forges table), and `runtime-discovery` (Project Kind / Project Origin) updated for `larapilot-adopt`; `shared-runtime` / `runtime-delivery` / `integrations.md` / `larapilot-settings` / `larapilot-implement` updated for the Azure DevOps forge.
- **Docs — dashboard auth** — `README`, `shared-runtime` (new **Dashboard auth** setting section), `.larapilot/integrations.md` (new **Dashboard access** section), the `larapilot-settings` skill (new AskQuestion round + rules), Boost core guideline, and the docs site updated for `settings.dashboard_auth` and `larapilot:dashboard-user`.
- **Docs — decision journal & code history** — `shared-runtime` (Project Settings defaults + two new setting sections, CLI contract, file-output paths), `runtime-discovery`, `runtime-delivery`, Boost core guideline, and the `larapilot-settings` / `larapilot-inception` / `larapilot-implement` / `larapilot-spec` / `larapilot-plan` / `larapilot-design` / `larapilot-feature` / `larapilot-bug` / `larapilot-adopt` skills updated for `settings.decision_log` and `settings.code_history`.
- **`larapilot:install`** — closing hint now points to `/larapilot-inception` (new product) or `/larapilot-adopt` (existing codebase).
- Site / package version **v2.5.0**.

## [2.4.2] - 2026-08-08

### Changed

- **Settings dashboard** — Lucille setting key label is now **Project tracking** (was `Lucille · Project tracking`). Persona role and `/larapilot-settings` AskQuestion copy are unchanged.

### Fixed

- **PHPStan** — remove redundant `array_filter` on Gantt `$assignees` in `UsageService` (false positive / noise after `array_unique`).
- **UsageTest** — import `PlanService` instead of a fully-qualified class reference; assert the shortened settings label.

## [2.4.1] - 2026-08-07

### Added

- **Epics as delivery containers** — specs carry `epic: { code, title, objective, deadline }`; Mark owns titles/objectives, Lucille validates deadlines against schedule milestones and remaining effort.
- **Dependency-aware Gantt** — plan tasks schedule from `dependencies`, mark parallelizable work, and optionally spread across `assignee` + `estimate_hours` for multi-developer timelines.
- **Schedule criticality** — Lucille forecasts remaining points/hours against project milestones and epic deadlines; alerts on the Usage dashboard and in `usage-report --insights`.
- **Zoey vs Lucille panel** — dashboard and reports explain why Zoey `context ≈ Nk` (loaded context) and Lucille ledger tokens/hours will not match 1:1.
- **Ledger history UX** — full history (not last 50), client-side pagination, filters for search / executor / category / estimated vs measured.
- **Gantt legend** — colors and statuses (epic, TODO/PLANNED/IN PROGRESS/REVIEW/DONE, parallel, milestone) under the chart.

### Changed

- **Lucille · Project tracking** — settings label, persona role, and `/larapilot-settings` AskQuestion copy (was plain “Account” / `lucille`).
- **Usage metrics** — hours only on the dashboard (minutes stay in the ledger); tokens ≥ 1000 display as `K` (e.g. `2.5K`).
- **OpenAPI** — Epic gains `objective` / `deadline`; Task gains `assignee` / `estimate_hours`.
- **Skills / runtime** — `larapilot-spec`, `larapilot-plan`, `larapilot-usage`, `larapilot-settings`, `shared-runtime`, `runtime-ops` updated for epics, parallelism, Zoey reconciliation, and criticality.
- Site / package version **v2.4.1**.

## [2.4.0] - 2026-08-06

### Added

- **Optional remote forges + chat notifications** — all OFF by default; orthogonal to `git_mode`.
    - Settings: `github`, `gitlab`, `bitbucket`, `notifications`, `notify_slack`, `notify_discord`, `notify_telegram` via `/larapilot-settings` / `larapilot:settings-set`.
    - Secrets in `.env` only: Slack/Discord/Telegram webhooks; Bitbucket `BITBUCKET_ACCESS_TOKEN` or `BITBUCKET_USERNAME` + `BITBUCKET_APP_PASSWORD` (also `LARAPILOT_BITBUCKET_*`).
    - `larapilot:notify` fan-out; hard hooks on `task-done` / `spec-approve`; skill events for PR/MR/review/ship/schedule.
    - Status probes: `larapilot:github-status` (`gh`), `larapilot:gitlab-status` (`glab`), `larapilot:bitbucket-status` (API tokens); implement prints PR/MR URLs when the matching forge is ON.
    - Setup guide: `.larapilot/integrations.md` (installed/updated with the package).
- **Personas — Mike, Lucille, Sarah** — roster grows to 30 agents.
    - **🗄️ Mike** (Database Expert) — owns schema, SQL/NoSQL, hierarchy algorithms (Adjacency List, Nested Sets, Path Enumeration, Closure Table, …), search engines, and migrations; collaborates with John, Jack, Aurora, Alex, Lars, Sabrine, Tom, Mark. Canonical rules in `runtime-delivery.md` → **Data Architecture**.
    - **📒 Lucille** (Account) — cross-cutting silent ledger of tokens + wall-clock time by category (`analysis`, `planning`, `implementation`, `support`, `feature`, `review`, `ship`, `other`); asks for deadlines at inception and reports schedule drift. Canonical rules in `runtime-ops.md` → **Usage Ledger & Schedule**.
    - **⌨️ Sarah** (CLI, Git & Linux Expert) — Shell/Bash or Go CLIs, **Git in general** (conflict resolution, rebase/merge, history hygiene, bisect), forge automation, CI pipeline YAML/scripts, and Linux/terminal/server scripting. Steps in wherever those surfaces appear; partners with Jack on Gitflow policy/gates/deploy. Canonical rules in `runtime-delivery.md` → **CLI, Git Pipelines & Linux**.
- **Project Kind: Package** — inception AskQuestion adds `Package` beside Personal / Website / Application. Workflow covers new vs existing local path vs existing git, Laravel package standards (tests, security, CI, semver), distribution (Packagist / Satis / VCS), docs, optional GitHub Pages / dedicated minisite, and consumer integration modes.
- **Usage persistence (Lucille)** — committed under `.larapilot/usage/` (`ledger.jsonl`, `schedule.yaml`).
    - `larapilot:usage-log` — append ledger entries (git user by default).
    - `larapilot:usage-report` — JSON/MD/human summary + filters (`--category=`, `--user=`, `--skill=`, `--spec=`, `--from=`, `--to=`, `--limit=`) + `--insights` (top categories, hot specs, deadline drift) + optional `--output=` Markdown resoconto.
    - `larapilot:schedule-set` — deadlines and drift notes.
    - `larapilot:choices-set` — inception/settings snapshot (`--from-prd` or flags) into `.larapilot/choices.yaml`.
- **`/larapilot-usage`** — Lucille skill to analyze and query time/token tracking, schedule status, and export consolidated reports.
- **Dashboard — Settings & Usage** — parallel to PRD: current settings + allowed options, visual inception choices summary, Lucille charts, living Gantt (specs + milestones), and `GET /larapilot/usage/report.md` download.
- MCP `RunArtisanTool` allows `larapilot:usage-report`.

### Changed

- **Inception / discovery / guidelines** — Project Kind options, Package branch, Mike/Sarah/Lucille participation, usage + choices CLI in Boost guidelines and `larapilot-inception` / `larapilot-spec` / `larapilot-usage`.
- **`settings.lucille`** — Lucille is **ON by default** at every skill level (`true` / `YES`). Explicit exclusion via `/larapilot-settings` or `larapilot:settings-set --lucille=NO` (aliases: `OFF`, `EXCLUDE`, `DISABLED`). Missing key never means excluded. `usage-log` refuses writes while excluded.
- **ECO disables Lucille** — switching `effort` to `ECO` via `settings-set` sets `lucille: false` automatically (envelope flag `lucille_disabled_by_eco`). Re-enable anytime with `--lucille=YES` without leaving ECO; pass `--lucille=YES` together with `--effort=ECO` to keep her on.
- Site / package version **v2.4.0**.

## [2.3.2] - 2026-07-31

### Fixed

- **CI lint job** — Pint EOF in `CompanionTest.php` and PHPStan `@param list<string>` on `CodeQualityService::runBinary()`.

## [2.3.1] - 2026-07-31

### Added

- **Mandatory code quality gate** — every Larapilot project stays on [Larastan](https://github.com/larastan/larastan) **level 5+** and [Laravel Pint](https://laravel.com/docs/pint).
    - **`larapilot:install`** scaffolds `phpstan.neon.dist` (Larastan extension, `level: 5`), `pint.json`, Composer scripts (`lint`, `lint:check`, `analyse`), and `require-dev` entries; runs `composer require --dev larastan/larastan laravel/pint` when Composer is available (`--skip-composer` to skip).
    - **`larapilot:quality`** — run Pint (check-only; `--fix` applies formatting) then Larastan analysis.
    - **`CodeQualityService`** — scaffold configs, merge `composer.json`, enforce minimum level.
    - **`larapilot:doctor`** — `healthy` requires Pint/Larastan config, level ≥ 5, and dev dependencies declared.

### Changed

- **Split-repo frontend model simplified** — the FE repo is an application write target only; no mirrored PRD, OpenAPI, or Larapilot metadata under `FE/.larapilot/`.
- **`/larapilot-frontend-companion`**, **inception / plan / implement skills**, **`runtime-discovery.md`**, **Boost guidelines**, **README**, **docs site**, **`runtime-delivery.md`** — removed sync/mirror/publish wording; implement runs `larapilot:quality` before backend `task-done`; CI minimum adds Larastan analyse. Site version **v2.3.1**.
- **`larapilot:update`** — adds missing quality stubs and Composer entries on upgrade.
- **MCP `RunArtisanTool`** — allows `larapilot:quality`.

### Removed

- **`larapilot:companion-sync`** — introduced in 2.3.0; dropped before release. Product truth stays in Laravel only.
- **`FrontendService::syncCompanion()`**, **`CompanionService::bundle()`**, and **`companion-sync.md`** metadata — only used for the FE mirror path.
- **PRD field `Companion sync:`** — no longer part of the topology template.

## [2.3.0] - 2026-07-31

### Added

- **BE-orchestrated frontend companion** — when topology is `API + external frontend`, the **Laravel workspace is the only entry point** for specs, PRD, and workflow commands. The external FE repo is a configured write target.
    - **`larapilot:frontend-set`** — persist absolute `frontend.repo_path` and optional `stack` in `.larapilot/config.yaml`.
    - **`larapilot:frontend-scan`** — detect stack, tooling (Vite/Next/Nuxt/…), structure, and entrypoints in the FE repo (optional `--path=`).
    - **`larapilot:companion-sync`** — push PRD + product OpenAPI mirror into the configured FE repo (`.larapilot/docs/PRD.md`, `openapi-product.json`, `companion-sync.md`).
    - **`FrontendService`** — validate path, scan codebase, sync companion artifacts.
    - **`config-show`** now exposes `data.frontend` (`repo_path`, `stack`, `configured`).
- **Cross-repo plan/implement** — plan tasks may set `repo: frontend`; implement writes under `data.frontend.repo_path` with FE git/test commands. Task template in `task-templates.md`.

### Changed

- **`/larapilot-frontend-companion`** — redesigned to run from the **Laravel backend** (configure path, scan FE, sync mirror, orchestrate cross-repo work).
- **Inception, plan, implement skills** — external topology asks for FE absolute path, runs scan, uses `companion-sync`.
- **`runtime-discovery.md`**, **Boost guidelines**, **README**, **docs site** — updated frontend companion deep dive and walkthrough.
- **MCP `RunArtisanTool`** — allows `larapilot:companion-sync` and `larapilot:frontend-scan`.

### Removed

- **`larapilot:companion-export`** — replaced by `larapilot:companion-sync` (direct push into the configured FE repo).
- **`GET /larapilot/api/companion`** — companion sync is CLI/skill-only from the Laravel workspace.

## [2.2.0] - 2026-07-27

### Added

- **Backstage integration** — publish the `.larapilot/` workspace into a [backstage.io](https://backstage.io) developer portal. One-way by design: the workspace stays the source of truth and workflow state never changes from the portal.
    - **`/larapilot-backstage`** — skill that asks for catalog identity (owner, system, lifecycle, component type, TechDocs), persists it to `.env`, generates the artifacts, and explains registration. Personas: **Matt** (catalog mapping), **Jack** (CI refresh), **Albert** (TechDocs), **Lars** (token/proxy boundary).
    - **`larapilot:backstage-export`** — read-only bundle preview by default; `--write` generates `catalog-info.yaml` (a `Component` entity plus one `API` entity per OpenAPI contract found), `mkdocs.yml`, and TechDocs sources under `.larapilot/techdocs/` (`index.md`, `prd.md`, `backlog/index.md`, `backlog/US-XXX.md` rendered from the YAML specs and plans). Also `--force`, `--no-techdocs`, `--file=`, `--api-base=`. Allowed via MCP `RunArtisanTool`.
    - **`GET /larapilot/api/backstage`** — catalog entities, rendered YAML, TechDocs metadata, and a lean delivery `snapshot` (metrics, per-status counts, blocking feedback, story list without bodies) for a Backstage plugin or entity provider; **`GET /larapilot/api/backstage/catalog-info.yaml`** serves the descriptor as a Backstage `url` location. Both share the dashboard gate (never in production) and belong behind the Backstage backend proxy so `LARAPILOT_API_TOKEN` stays server-side. OpenAPI updated.
    - **`larapilot.backstage` config** (`LARAPILOT_BACKSTAGE_*`) — `enabled`, `name`/`title`/`description`/`namespace`, `owner`, `system`, `lifecycle`, `component_type`, `tags`, `base_url`, `techdocs`, `workflow_api` (off by default). Catalog identity lives in Laravel config/`.env`, not `.larapilot/config.yaml`, because it describes the org catalog rather than the delivery workflow.
    - **Runtime pack** — canonical rules in `runtime-ops.md` → **Developer Portal — Backstage** (ownership of generated files, regeneration cadence, security boundary).
- **Project tracker integration** — optional, API-key based sync between the backlog and **Linear**, **Asana**, **Jira**, **Trello**, **ClickUp**, or **Monday**. `.larapilot/` stays the source of truth; the tracker is a window on delivery for people who never open `backlog.yaml`.
    - **`/larapilot-tracker`** — skill that picks the provider, collects credentials into `.env`, validates the status map against the real board, dry-runs, then pushes. Personas: **Matt** (provider choice, status mapping, link hygiene), **Mark** (what non-developers should see), **Lars** (credential boundary).
    - **`larapilot:tracker-push`** — user stories become issues/tasks/cards/items titled `US-XXX — Title` carrying the spec body, priority, points, and epic; plan tasks become **native** sub-issues (Linear), subtasks (Jira, Asana, ClickUp), subitems (Monday), or checklist items (Trello). Unchanged stories are skipped without an API call; `--dry-run`, `--spec=`, `--force`. A subtask whose plan task disappears is deleted; a story whose remote record was deleted is recreated.
    - **`larapilot:tracker-pull`** — read-only drift report by default (local vs remote status per story); `--apply` writes the mapped status back into the backlog. **DONE is never applied** — it is a human review gate that records the merge commit, so it stays with `larapilot:spec-approve`. `LARAPILOT_TRACKER_PULL_COMMENTS=true` imports tracker comments as non-blocking internal feedback, once each.
    - **`larapilot:tracker-status`** — provider, readiness, missing env vars, status map, linked spec count; `--ping` verifies the credential and the target board/project. Allowed via MCP `RunArtisanTool` (read-only; push/pull are not).
    - **`larapilot.tracker` config** (`LARAPILOT_TRACKER_*`, `LARAPILOT_{LINEAR,JIRA,ASANA,TRELLO,CLICKUP,MONDAY}_*`) — one active provider, per-provider credentials and `status_map`. Jira uses REST v2 so descriptions stay plain text instead of ADF; Monday needs a long-text column for the spec body.
    - **`.larapilot/tracker.yaml`** — committed spec → remote-id map (identifiers only, never credentials), keyed by provider so switching tools does not lose the mapping.
    - **Runtime pack** — canonical rules in `runtime-ops.md` → **Project Trackers** (asymmetric direction, credential boundary, status mapping, sync cadence).
- **`larapilot:config-show`** now reports `data.backstage` — resolved entity ref, owner, system, lifecycle, TechDocs paths, and whether the catalog descriptor already exists — and `data.tracker` — provider, readiness, missing env vars, status map, and linked spec count, without ever echoing a credential.

### Changed

- **Generated-file safety** — `catalog-info.yaml` and `mkdocs.yml` are never overwritten without `--force` (a project may already own them); everything under `.larapilot/techdocs/` is regenerated and pages for deleted specs are pruned.
- **Status mapping is forward-first** — a story is in sync when its Larapilot status maps _onto_ the remote label, so `TODO` and `PLANNED` sharing one column is not drift. Reverse mapping runs only once drift is real and resolves an ambiguous label to the earliest workflow slot; an unmapped remote status is reported, never guessed.
- **A status map pointing at a column that does not exist fails loudly on every provider**, naming the columns that do exist. Larapilot never creates columns in someone else's tracker — Linear previously would have accepted the issue and filed it in the team's default state.
- **README + docs site** — new **Developer portal** / **Backstage portal** sections (catalog entity sample, identity env table, TechDocs, live snapshot, security boundary), a **Project trackers** deep-dive (provider matrix, direction, configuration, credential boundary), and a **Tracker sync** use-case walkthrough covering first-run status-map mismatch, push, drift report, and the DONE gate; skill and CLI tables updated; site version **v2.2.0**.
- **`illuminate/http`** added to `require` — the tracker drivers use Laravel's HTTP client.

## [2.1.2] - 2026-07-26

### Added

- **Context estimate (Zoey)** — every skill posts one-line rough loaded-context estimate at start and end (`chars/4`, nearest 0.5k); canonical rule in `shared-runtime` → **Output Economy → Context estimate**; pointed from Boost guidelines.

## [2.1.1] - 2026-07-26

### Changed

- **`larapilot-settings` AskQuestion copy** — prompts and option labels spell out trade-offs instead of cryptic one-liners (especially backlog: same FR coverage, different slicing — journey vs capability vs per-FR). Effort, git mode, testing, and auto-approve labels clarified the same way.

## [2.1.0] - 2026-07-26

### Added

- **`settings.backlog`** — `LEAN` | `STANDARD` | `GRANULAR` (default `STANDARD`): explicit control over spec/epic granularity in `larapilot-spec`, `larapilot-feature`, and `larapilot-bug`. Exposed on `config-show` as `data.settings.backlog`; persisted via `/larapilot-settings` or `larapilot:settings-set --backlog=…`.
- **Epic consolidation rule (shared runtime)** — reuse existing epics from `spec-list` before proposing a new `EP-XXX`; new epic only for a genuinely new product area (guideline: 5–8 epics per product); fix specs reuse the existing Maintenance epic.
- **`LARAPILOT_API_TOKEN`** — optional shared token for `/larapilot/api/*` (bearer or `X-Larapilot-Token`); when unset, API **writes** are refused outside local/development/testing environments. Strongly recommended on staging.
- **Workflow guards** — `larapilot:spec-review` blocks with incomplete plan tasks; `larapilot:spec-approve` blocks with open `[blocks-merge]` feedback or incomplete tasks. Both accept `--force`.
- **`larapilot:update --preserve-design-systems`** — keep local `.larapilot/design-systems/` customizations; the command now states when design systems are overwritten and warns about settings keys missing from `config.yaml` after an upgrade.
- **`larapilot:doctor`** — new checks (`task_templates`, `design_systems`, `settings_valid`), `settings_missing_keys` drift report, and `--human` table output (also on `larapilot:metrics`).
- **Runtime packs** — `shared-runtime.md` is now a slim always-on core; phase content moved to `runtime-discovery.md`, `runtime-delivery.md`, `runtime-ux.md`, `runtime-ship.md`, `runtime-ops.md` (copied on install/update; each skill loads only its packs, cutting per-skill prompt tokens sharply).

### Changed

- **Backlog granularity defaults** — specs are now **journey-level by default**: one spec per demonstrable user capability, with related FRs merged (each cited as `Traces to: FR-XXX`) and Laravel seams (models, controllers, policies, UI, API resources) planned as `TASK-NN` in `/larapilot-plan` instead of separate specs. The previous fine-grained behavior (one spec per FR, seam / Filament per-entity / i18n per-locale splits) remains available via `settings.backlog: GRANULAR`.
- **Full Product / Enterprise bootstrap** — still covers every FR, but spec count follows `settings.backlog` instead of the fixed "one spec per FR; multi-epic backlog expected" rule.
- **`larapilot-settings`** — new Backlog question (Mark) in Round 1; Testing and Auto-approve move to Round 2.
- **`larapilot:metrics`** now merges plan/task metrics (`total_tasks`, `done_tasks`, `task_completion_rate`, `specs_with_plans`) into the envelope.
- **API feedback summaries** (board and spec list) are counts-only; full entries stay on the spec detail endpoint. OpenAPI schema updated.
- **Diagnostics** honor `LARAPILOT_DIAGNOSTICS_ENABLED=false` on CLI and MCP too (previously HTTP only), and redaction also covers JWTs, cookie headers, `base64:` secrets, and PEM key material.
- **Config stub** now lists every configurable path (`review`, `security`, `launch`, `support`, `internal_feedback`).

### Fixed

- **CSRF exemption on the JSON API** did not match the `PreventRequestForgery` middleware registered by newer Laravel versions; scripted `POST /comments` requests failed with 419.
- **`spec-list` summary `titles`** collapsed to an empty map when any backlog entry lacked a `code`.
- **`spec-delete`** (service layer) now removes the spec's internal-feedback file as well.
- **Task commit auto-link** no longer falls back to a commit whose subject references a _different_ spec code.
- **Mockup assets route** now honors its own `mockup_assets_route` config instead of the `mockups_route` one.
- **Concurrency** — backlog, plan, and internal-feedback read-modify-write cycles now run under an advisory file lock, so parallel agents can't drop each other's updates.

## [2.0.0] - 2026-07-24

### Added

- **Frontend Topology (inception)** — John + Joe ask `Laravel-coupled` | `SPA-in-Laravel` | `API + external frontend` before the admin-panel route; recorded in the PRD.
- **`/larapilot-frontend-companion`** — skill for an external frontend repo to pull/mirror the shared PRD (and optional product OpenAPI) from Laravel.
- **`GET /larapilot/api/companion`** — companion artifact bundle (PRD, parsed topology, sync instructions); OpenAPI updated.
- **`larapilot:companion-export`** — CLI export of the same bundle (optional `--file=` / `--api-base=`); allowed via MCP `RunArtisanTool`.
- **`larapilot:diagnostics`** — read-only runtime snapshot (app status, health checks, redacted Laravel log tail) for bug triage.
- **MCP `diagnostics` tool** — same snapshot for editors; also allowed via `RunArtisanTool` as `larapilot:diagnostics`.
- **`GET /larapilot/api/diagnostics`** — read-only app status, health checks, and redacted log tail for bug triage; documented in README and docs site (API + CLI + MCP).
- **`/larapilot-bug` + shared-runtime** — optional diagnostics step during bug intake.

### Changed

- **Shared runtime, inception skill, Boost guidelines** — Frontend Topology policy, companion sync rules, and Vendor & Package Policy ordering (topology before panel route).
- **README + docs site** — document topology choices, companion skill, companion API/CLI, diagnostics (API + CLI + MCP), a **Frontend companion** deep dive, and a fifth walkthrough example; site version **v2.0.0**.

## [1.9.1] - 2026-07-21

### Changed

- **Quality bar for John / Alex / Robert** — Architecture and development personas **MUST** follow **SOLID**; architecture, development, and review **MUST** prevent and check **N+1** queries (eager loading, indexes, chunking). Also require fail-fast validation, policy-level auth, transactions on multi-writes, and idempotent jobs/webhooks. Documented in `shared-runtime.md`, plan/implement/review/inception skills, and Boost guidelines.
- **Docs site** — v1.9.1 release note; persona phase copy for John, Alex, and Robert reflects the SOLID / N+1 quality bar.

## [1.9.0] - 2026-07-17

### Added

- **`/larapilot-settings`** — AskQuestion skill to persist project settings in `.larapilot/config.yaml`.
- **`larapilot:settings-set`** — CLI to write `settings.effort`, `settings.git_mode`, and `settings.testing` (exposed on `config-show` as `data.settings`).
- **Project settings defaults** — `effort: STANDARD`, `git_mode: GITFLOW`, `testing: NORMAL`, `auto_approve: NO`.
- **`settings.auto_approve`** — `YES`/`NO` (default `NO`). When `YES`, `/larapilot-autopilot` may call `spec-approve` after implement without waiting for a human verdict.

### Changed

- **Git discipline** — automatic push + remote PR is no longer implied by Gitflow. Use `git_mode: GITFLOW_PUSH` for the previous push-after-each-task behavior; default `GITFLOW` keeps branches/commits/PR prep local.
- **Testing bar** — default is now `NORMAL` (standard Pest/feature/policy tests **without** Playwright/Dusk/E2E). Full browser/viewport/E2E suite requires `testing: BEST`.
- **Effort modes** — `ECO` reduces tokens (**never spawn sub-agents**; **defers documentation** except **OpenAPI** when APIs change; no README/PDF/diagram theater; skips deep reviews/E2E); `MAX` forces deep passes on every flow.
- **Shared runtime, task templates, plan/implement/review skills, Boost guidelines** — honor `data.settings` throughout.

## [1.8.2] - 2026-07-15

### Added

- **`POST /larapilot/api/specs/{code}/comments`** — append internal feedback via JSON (`author`, `message`, optional `blocks_merge`); returns updated feedback snapshot. OpenAPI updated.

### Changed

- **Dashboard board** — comment and blocking counts shown as pill badges with icons (alongside the mockup indicator).
- **Comment form** — footer stacks blocking checkbox, log path, and blocking count for a cleaner compact layout.
- **Workflow API GET responses** — board, spec list, and spec detail now include full `feedback.entries` and `mockups.screens` (absolute preview URLs when browsable) on each user story; spec detail also embeds both on the nested `spec` object.

## [1.8.1] - 2026-07-15

### Changed

- **Internal feedback dashboard** — comment list uses task-style accordions with author, date, status, and a closed-state preview; blocking comments show a prominent **Needs rework** badge.
- **Comment form** — Author and Comment are required (no pre-filled author); simple visual Markdown toolbar with live preview; log path, blocking checkbox, and submit control on one compact footer row.
- **Filament design system** — default primary palette is **slate** (not amber/orange) in `tokens.css` and packaged HTML mockups.

## [1.8.0] - 2026-07-15

### Added

- **Internal feedback on user stories** — PM and dev comments persist as append-only markdown in `.larapilot/internal-feedback/{code}.md`, visible on the workflow dashboard and JSON API until the spec reaches **DONE**.
- **`larapilot:spec-comment`** — append a comment from the CLI (`--author`, `--message`, optional `--blocks-merge`).
- **Dashboard comment form** — post comments from spec detail (dev/staging only); blocking comments flagged with `[blocks-merge]`.
- **`--include-feedback` on `spec-request-changes`** — promotes blocking internal comments into `## Rework Feedback` when sending a spec back to TODO.
- **`LARAPILOT_COMMENTS_ENABLED`** — env toggle (default `true`) to enable or disable comments globally via `config/larapilot.php`.

### Fixed

- **Mockup asset resolution** — `filament-tokens.css` (and other `{system}-tokens.css` aliases) now resolve to packaged design-system `tokens.css`; orphan requests like `/mockups/filament-tokens.css` work when the browser resolves parent-relative paths incorrectly.
- **Brand assets in mockups** — `logo.svg`, `favicon.svg`, and similar files are found in nested mockup folders; `srcset`, inline `style url()`, and CSS `url()` references are rewritten to working preview URLs.
- **Design-system paths** — references to `.larapilot/design-systems/{system}/…` in mockup HTML resolve to `/mockup-assets/design-systems/…`.
- **`spec-request-changes`** — now records `status_history` via `SpecService::requestChanges()`.

## [1.7.3] - 2026-07-15

### Added

- **Dashboard mockup linking** — when `/larapilot-design` produced HTML in `.larapilot/mockups/{spec}/`, the workflow dashboard and JSON API now surface it on the board (indicator) and spec detail (embedded preview iframe, screen links, artifact path). Served via the existing dev-only `/mockups/{spec}` route — no storage symlink required.
- **`MockupService`** — discovers HTML screens per spec code and builds preview URLs when the mockup route is browsable.

### Changed

- **Workflow API & OpenAPI** — `mockups` summary on board/spec list items; full `mockups` object (screens, entry URL) on spec detail.
- **Mockup asset serving** — HTML mockups rewrite relative `tokens.css` and sibling asset paths to working URLs; packaged design-system files are served at `/mockup-assets/design-systems/` (dev/staging only) so screens copied from `design-systems/*/html/` render with CSS in the dashboard iframe and `/mockups/{spec}` preview.
- **Docs site** — v1.7.3 release note and dashboard spec-detail copy for mockup preview.

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
