# Changelog

All notable changes to `larapilot` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[1.0.0]: https://github.com/andreapollastri/larapilot/compare/0.3...1.0
[0.3.0]: https://github.com/andreapollastri/larapilot/compare/0.2...0.3
[0.2.0]: https://github.com/andreapollastri/larapilot/compare/0.1...0.2
[0.1.0]: https://github.com/andreapollastri/larapilot/releases/tag/0.1
