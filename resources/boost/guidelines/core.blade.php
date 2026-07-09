## Larapilot

Larapilot brings **spec-driven product development** to Laravel projects via [Laravel Boost](https://laravel.com/ai/boost). It turns your AI agent into a disciplined product squad: discovery → backlog → plan → implement → review → ship.

**Three layers:** Boost skills orchestrate the conversation; `php artisan larapilot:*` persists artifacts and enforces workflow via JSON envelopes; `.larapilot/` in the repo is the source of truth between sessions.

**Discovery interview (`larapilot-inception`):** a guided conversation (not a one-shot form). Mark asks the **delivery target** early (`MVP`, `V1 Complete`, `Full Product`, `Enterprise`) — MVP thinking is the default lens, not a hard ceiling. Aurora asks the **Budget Sensitivity** (`Tracked` or `Relaxed`) — the user can exclude budget evaluation; business validation is then loosened to short advisories, never removed. Jennifer explores market and positioning; Benjamin brings enterprise market research; Sebastian challenges against competitors and MUST propose integrations plus **competitor data porting** (import paths for users switching from rival products, lock-in-free export); Mark scopes to the chosen target; John and Aurora co-own scalable, performant, budget-aligned architecture. Ask at most 3 critical questions per round (skippable); present fixed options via **AskQuestion**, not plain-text A/B/C lists. Write and validate the PRD before creating any backlog.

**Vendor & package policy:** prefer Laravel built-ins/first-party, then **Spatie** packages (spatie.be/open-source/packages) for third-party functionality; for admin/control panels evaluate **Filament** (filamentphp.com) and its plugins as the preferred route. Always verify a package is maintained, compatible, and secure (`composer audit`) before requiring it.

**Laravel scaffolding defaults:** … **Brand (Elise):** favicon.svg, logo, OG 1200×630 for Lauren when client has no assets. See `.larapilot/shared-runtime.md`.

### When to use Larapilot

Use Larapilot skills when the user wants to:

- Define a product vision or write a PRD
- Create or extend a backlog of user stories / specs
- Plan a spec with technical tasks and test strategy
- Implement a planned spec in a Laravel codebase
- Review and accept (or reject) a delivered increment
- Ship to production — Cipi preferred; also Forge, Laravel Cloud, Ploi, Kubernetes, custom (security gate + deploy)
- Create UI mockups before implementation

### Workflow

| Step | Skill | Output |
| --- | --- | --- |
| Discovery | `larapilot-inception` | `.larapilot/docs/PRD.md` |
| Design (optional) | `larapilot-design` | `.larapilot/mockups/{spec}/` (dev route `/mockups/{spec}`) |
| Backlog | `larapilot-spec` | `.larapilot/backlog.yaml`, `.larapilot/specs/` |
| Planning | `larapilot-plan` | `.larapilot/plans/US-XXX-plan.yaml` |
| Implementation | `larapilot-implement` | Code, tests, review notes |
| Acceptance | `larapilot-review` | DONE or rework feedback |
| Ship (optional) | `larapilot-ship` | Security assessment + multi-platform deploy + web launch checks |

### Installation

```bash
composer require andreapollastri/larapilot --dev
php artisan larapilot:install
php artisan boost:install
```

Laravel Boost is installed automatically as a Larapilot dependency.

### Update

After upgrading the package, one command refreshes the shared runtime, guidelines, and skills (project config is never touched):

```bash
composer update andreapollastri/larapilot
php artisan larapilot:update
```

Register the Larapilot MCP server in your editor (in addition to `laravel-boost`):

```json
{
  "mcpServers": {
    "larapilot": {
      "command": "php",
      "args": ["artisan", "mcp:start", "larapilot"]
    }
  }
}
```

### CLI contract

Skills call Artisan commands — never invent persistence logic:

- `php artisan larapilot:config-show`
- `php artisan larapilot:prd-write`
- `php artisan larapilot:validate-prd`
- `php artisan larapilot:spec-list`
- `php artisan larapilot:spec-add --file=...`
- `php artisan larapilot:spec-show US-001`
- `php artisan larapilot:spec-next`
- `php artisan larapilot:validate-spec --file=...`
- `php artisan larapilot:validate-plan US-001 --file=...`
- `php artisan larapilot:spec-plan US-001 --file=...`
- `php artisan larapilot:spec-start US-001`
- `php artisan larapilot:task-done US-001 TASK-01`
- `php artisan larapilot:spec-review US-001`
- `php artisan larapilot:spec-request-changes US-001 --file=...`
- `php artisan larapilot:spec-approve US-001`
- `php artisan larapilot:metrics`

Parse stdout/stderr as JSON envelopes with schema `larapilot/v1`.

### Laravel-specific planning and implementation

When planning or implementing Laravel features:

- Use Boost `Search Docs` for version-aware Laravel guidance
- Use Boost `Database Schema` before designing migrations
- Follow Laravel conventions: Form Requests, Policies, Eloquent relationships, Pest/PHPUnit tests
- Prefer Artisan generators (`make:model`, `make:controller`, etc.) via Boost when appropriate
- Use `php artisan test` or `./vendor/bin/pest` for verification

### Artifacts live in the repo

- PRD: `.larapilot/docs/PRD.md`
- Backlog: `.larapilot/backlog.yaml`
- Specs: `.larapilot/specs/US-XXX.yaml`
- Plans: `.larapilot/plans/US-XXX-plan.yaml`
- Mockups: `.larapilot/mockups/{spec}/` (served at `/mockups/{spec}` only outside production; `index.html` is the default file)
- Test results: `.larapilot/docs/test-results/`
- Review findings: `.larapilot/docs/review/`
- Security assessments: `.larapilot/docs/security/`
- Launch checks (SEO/social): `.larapilot/docs/launch/`

### Personas

Larapilot personas are lenses, not costumes. Each applies a different kind of scrutiny:

| Persona | Role |
| --- | --- |
| 💎 Mark | Product Manager |
| 🧭 Jennifer | Business Strategist |
| 🏢 Benjamin | Business Consultant |
| 💡 Sebastian | Innovator |
| 🔎 Tom | Requirements Analyst |
| 📐 John | Architect |
| 🔧 Alex | Full-Stack Developer |
| 🧪 Anne | Test Architect |
| 🛡️ Robert | Code Reviewer |
| 🔐 Lars | Security Expert |
| 🚀 Jack | DevOps Engineer |
| 💰 Aurora | FinOps Expert |
| ⚖️ Violet | Legal Expert |
| 📈 Emma | SEO & Web Performance Specialist |
| 💬 Lauren | Social Media Manager |
| 🎨 Elise | UX Designer |

**Output economy:** brevity in chat per skill phase (high during implement/review/ship; clarity first during inception); artifacts, code, and CLI output stay complete and verbatim. See **Output Economy** in `.larapilot/shared-runtime.md`.

**Sub-agents:** optional readonly sub-agents on any editor with a sub-agent tool (Cursor Task, Claude Code Agent, …) in **plan** (codebase explore) and **implement** (code review + security review in parallel); inline fallback without one; parent owns CLI. See **Sub-agents** in `.larapilot/shared-runtime.md`.

Read `.larapilot/shared-runtime.md` at skill activation for full runtime rules.
