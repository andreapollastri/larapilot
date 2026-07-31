## Larapilot

Larapilot brings **spec-driven product development** to Laravel projects via [Laravel Boost](https://laravel.com/ai/boost). It turns your AI agent into a disciplined product squad: discovery → backlog → plan → implement → review → ship.

**Three layers:** Boost skills orchestrate the conversation; `php artisan larapilot:*` persists artifacts and enforces workflow via JSON envelopes; `.larapilot/` in the repo is the source of truth between sessions.

**Runtime loading:** at skill activation read `.larapilot/shared-runtime.md` (core rules: settings, personas, language, output economy, sub-agents); each skill names the additional runtime packs it needs (`.larapilot/runtime-discovery.md`, `runtime-delivery.md`, `runtime-ux.md`, `runtime-ship.md`, `runtime-ops.md`). Task body templates: `.larapilot/task-templates.md`.

**Project settings:** `.larapilot/config.yaml` → `settings` (`effort`, `backlog`, `git_mode`, `testing`, `auto_approve` — set via `/larapilot-settings`, exposed on `config-show` as `data.settings`). Every skill must read and honor `data.settings` before planning or implementing — canonical matrices in `.larapilot/shared-runtime.md` → **Project Settings**. Note: `GITFLOW` never auto-pushes (only `GITFLOW_PUSH` does); `ECO` never spawns sub-agents; `auto_approve` is stored as boolean `true`/`false`.

### When to use Larapilot

Use Larapilot skills when the user wants to:

- Define a product vision or write a PRD (guided discovery interview — Project Kind, delivery target, MoSCoW; drop client docs in `.larapilot/client-materials/` and legacy snapshots in `.larapilot/legacy/` first)
- Create or extend a backlog of user stories / specs
- Add **one new feature or evolutiva** on an existing project (`larapilot-feature`)
- Report or triage a **bug** (`larapilot-bug`)
- Link an **external frontend repo** from Laravel (`larapilot-frontend-companion`)
- Publish the repo into a **Backstage developer portal** — catalog entity + TechDocs (`larapilot-backstage`)
- Mirror the backlog into a **project tracker** — Linear, Asana, Jira, Trello, ClickUp, Monday (`larapilot-tracker`)
- Plan a spec with technical tasks and test strategy
- Implement a planned spec in a Laravel codebase
- Review and accept (or reject) a delivered increment
- Ship to production — security gate + deploy per the platform recorded in the PRD
- Create UI mockups before implementation

### Workflow

| Step | Skill | Output |
| --- | --- | --- |
| Discovery | `larapilot-inception` | `.larapilot/docs/PRD.md` |
| Feature / evolutiva | `larapilot-feature` | New `US-XXX` spec (+ optional PRD `FR-XXX`) |
| Bug report | `larapilot-bug` | Fix spec or rework + `.larapilot/docs/support/intake.md` |
| FE companion (split repo) | `larapilot-frontend-companion` | Link FE path, scan code; implement via `repo: frontend` from Laravel |
| Design (optional) | `larapilot-design` | `.larapilot/mockups/{spec}/` (dev route `/mockups/{spec}`); design system per PRD from `.larapilot/design-systems/` |
| Backlog | `larapilot-spec` | `.larapilot/backlog.yaml`, `.larapilot/specs/` |
| Planning | `larapilot-plan` | `.larapilot/plans/US-XXX-plan.yaml` |
| Implementation | `larapilot-implement` | Code, tests, review notes |
| Acceptance | `larapilot-review` | DONE or rework feedback |
| Ship (optional) | `larapilot-ship` | Security assessment + deploy + web launch checks |
| Settings | `larapilot-settings` | Persist `effort` / `backlog` / `git_mode` / `testing` / `auto_approve` in `.larapilot/config.yaml` |
| Developer portal (optional) | `larapilot-backstage` | `catalog-info.yaml` + TechDocs (`mkdocs.yml`, `.larapilot/techdocs/`) for backstage.io |
| Project tracker (optional) | `larapilot-tracker` | Stories + plan subtasks in Linear/Asana/Jira/Trello/ClickUp/Monday; links in `.larapilot/tracker.yaml` |

### Installation

```bash
composer require andreapollastri/larapilot --dev
php artisan larapilot:install
php artisan boost:install
```

### Update

```bash
composer update andreapollastri/larapilot
php artisan larapilot:update
```

Register the Larapilot MCP server in your editor (in addition to `laravel-boost`): command `php`, args `["artisan", "mcp:start", "larapilot"]`.

### CLI contract

Skills call Artisan commands — never invent persistence logic:

- `php artisan larapilot:config-show`
- `php artisan larapilot:settings-set --effort=… --backlog=… --git-mode=… --testing=… --auto-approve=…`
- `php artisan larapilot:prd-write`
- `php artisan larapilot:validate-prd`
- `php artisan larapilot:frontend-set --path=/abs/fe/repo [--stack=React]`
- `php artisan larapilot:frontend-scan`
- `php artisan larapilot:backstage-export` _(read-only; `--write [--force] [--no-techdocs]` generates the Backstage catalog + TechDocs)_
- `php artisan larapilot:tracker-status` _(read-only; `--ping` verifies the provider credential)_
- `php artisan larapilot:tracker-push` _(backlog → tracker; `--dry-run`, `--spec=`, `--force`)_
- `php artisan larapilot:tracker-pull` _(tracker → drift report; `--apply` writes statuses back, never DONE)_
- `php artisan larapilot:spec-list`
- `php artisan larapilot:spec-add --file=...`
- `php artisan larapilot:spec-show US-001`
- `php artisan larapilot:spec-next`
- `php artisan larapilot:validate-spec --file=...`
- `php artisan larapilot:validate-plan US-001 --file=...`
- `php artisan larapilot:spec-plan US-001 --file=...`
- `php artisan larapilot:spec-start US-001`
- `php artisan larapilot:task-done US-001 TASK-01`
- `php artisan larapilot:quality` _(Pint + Larastan level 5+; `--fix` applies Pint formatting)_
- `php artisan larapilot:spec-review US-001`
- `php artisan larapilot:spec-request-changes US-001 --file=...`
- `php artisan larapilot:spec-approve US-001`
- `php artisan larapilot:metrics`

Parse stdout/stderr as JSON envelopes with schema `larapilot/v1`.

### Laravel-specific planning and implementation

- Use Boost `Search Docs` for version-aware Laravel guidance and `Database Schema` before designing migrations
- Follow Laravel conventions: Form Requests, Policies, Eloquent relationships, Pest/PHPUnit tests; prefer Artisan generators (`make:model`, `make:controller`, …)
- Laravel scaffolding defaults, Git/factories/testing discipline: **Laravel Scaffolding Defaults** and **Git Workflow** in `.larapilot/runtime-delivery.md`
- Mobile-first UX, WCAG, design systems, brand assets: `.larapilot/runtime-ux.md`

### Artifacts live in the repo

PRD `.larapilot/docs/PRD.md` (living product contract — see **PRD Living Document** in `.larapilot/runtime-ops.md`) · backlog `.larapilot/backlog.yaml` · specs `.larapilot/specs/US-XXX.yaml` · plans `.larapilot/plans/US-XXX-plan.yaml` · mockups `.larapilot/mockups/{spec}/` (served at `/mockups/{spec}` outside production) · docs (test-results, review, security, support, launch) under `.larapilot/docs/` · client materials `.larapilot/client-materials/` · legacy `.larapilot/legacy/` · research `.larapilot/research/` · tracker links `.larapilot/tracker.yaml` (commit it; ids only, never credentials). Dashboard: `/larapilot` (read-only board — dev/staging only).

### Personas

Larapilot personas are lenses, not costumes — 27 named agents (💎 Mark PM, 📐 John Architect, 🔧 Alex Developer, 🧪 Anne Tests, 🛡️ Robert Review, 🔐 Lars Security, 🚀 Jack DevOps, 🎨 Elise UX, …). The canonical roster with roles lives in `.larapilot/shared-runtime.md` → **Agent Persona**. Chat output renders speakers as `icon + name`; brevity per **Output Economy** (artifacts, code, and CLI output stay complete and verbatim) — Zoey also posts one **Context estimate** line at skill start and end (see shared-runtime → **Output Economy → Context estimate**); optional readonly sub-agents per **Sub-agents** (never under `effort: ECO`).
