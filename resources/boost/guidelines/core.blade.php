## Larapilot

Larapilot brings **spec-driven product development** to Laravel projects via [Laravel Boost](https://laravel.com/ai/boost). It turns your AI agent into a disciplined product squad: discovery → backlog → plan → implement → review → ship.

**Three layers:** Boost skills orchestrate the conversation; `php artisan larapilot:*` persists artifacts and enforces workflow via JSON envelopes; `.larapilot/` in the repo is the source of truth between sessions.

**Discovery interview (`larapilot-inception`):** a guided conversation (not a one-shot form). Before the interview, drop client docs in **`.larapilot/client-materials/`** and legacy snapshots in **`.larapilot/legacy/`** — skills always read them. Mark opens with **Project Kind** (`Personal`, `Website`, `Application`) — the first layer that switches persona depth and follow-up questions. **Personal** → lean path (MVP/V1, budget defaults `Relaxed`, business personas silent). **Website** → type next (showcase, portal, blog, e-commerce, landing, docs) then delivery target; Emma/Lauren/Elise lead. **Application** → full discovery: delivery target (`MVP` … `Enterprise`), Budget Sensitivity (`Tracked` or `Relaxed`), multi-tenancy, admin panel or authenticated dashboard route (Filament vs Laravel Starter Kit vs custom), integrations, compliance. Jennifer explores market and positioning when relevant; Benjamin brings enterprise research on Application; Sebastian proposes integrations, **competitor data porting**, and runs **deepsearch** on reference product URLs (reports in `.larapilot/research/reference-products/`); John and Aurora co-own scalable, budget-aligned architecture; legacy rewrite/port preserves **all features and data** unless explicitly scoped out. **Jack asks local dev environment** (Sail, Herd, not defined yet, or other) via AskQuestion — never assumes Docker/Sail; **Jack asks deploy platform, edge/CDN/WAF, and cloud** — never assumes Cipi, Cloudflare, or AWS; recommends Cloudflare and AWS when feasible. Ask at most 3 critical questions per round (skippable); present fixed options via **AskQuestion**, not plain-text A/B/C lists. Write and validate the PRD before creating any backlog.

**Vendor & package policy:** prefer Laravel built-ins/first-party (including **[Laravel Starter Kits](https://laravel.com/starter-kits)** when they fit), then **Spatie** packages (spatie.be/open-source/packages) for third-party functionality; for admin/control panels and authenticated dashboards never assume **Filament** (filamentphp.com) — explicitly ask the user **Filament vs Starter Kit (Livewire/React/Vue/Svelte) vs custom**, recommending the best fit for the specific case and the option closest to the project mockups. Always verify a package is maintained, compatible, and secure (`composer audit`) before requiring it.

**Laravel scaffolding defaults:** … **Brand (Elise):** favicon.svg, logo, OG 1200×630 for Lauren when client has no assets. See `.larapilot/shared-runtime.md`.

### When to use Larapilot

Use Larapilot skills when the user wants to:

- Define a product vision or write a PRD
- Create or extend a backlog of user stories / specs
- Plan a spec with technical tasks and test strategy
- Implement a planned spec in a Laravel codebase
- Review and accept (or reject) a delivered increment
- Ship to production — honors PRD deploy/edge/cloud choices; supports Cipi, Forge, Laravel Cloud, Ploi, AWS, Kubernetes, DigitalOcean, Hetzner/OVH, custom VPS (security gate + deploy)
- Create UI mockups before implementation

### Workflow

| Step | Skill | Output |
| --- | --- | --- |
| Discovery | `larapilot-inception` | `.larapilot/docs/PRD.md` |
| Design (optional) | `larapilot-design` | `.larapilot/mockups/{spec}/` (dev route `/mockups/{spec}`); Filament admin mockups use `.larapilot/design-systems/filament/` when PRD chose Filament; Starter Kit mockups use `.larapilot/design-systems/starter-kit/` when PRD chose a kit variant |
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
- **UX (Elise):** mobile-first responsive — navigable and simple on any device/resolution; WCAG 2.2 AA
- **UI tests (Anne):** multi-viewport (375 / 768 / 1280 px); mobile nav and CTAs reachable
- **Development & delivery:** Gitflow, strict per-task commits + internal PRs, factories/seeders (Alex), SemVer + CHANGELOG, security files, CI gates — see `.larapilot/shared-runtime.md`.
- Prefer Artisan generators (`make:model`, `make:controller`, etc.) via Boost when appropriate
- Use `php artisan test` or `./vendor/bin/pest` for verification

### Artifacts live in the repo

- PRD: `.larapilot/docs/PRD.md`
- Backlog: `.larapilot/backlog.yaml`
- Specs: `.larapilot/specs/US-XXX.yaml`
- Plans: `.larapilot/plans/US-XXX-plan.yaml`
- Mockups: `.larapilot/mockups/{spec}/` (served at `/mockups/{spec}` only outside production; `index.html` is the default file)
- Dashboard: `/larapilot` (read-only board, PRD, spec detail — dev/staging only; disabled in production)
- Test results: `.larapilot/docs/test-results/`
- Review findings: `.larapilot/docs/review/`
- Security assessments: `.larapilot/docs/security/` (Lars OWASP + Oliver red-team)
- Support & maintenance: `.larapilot/docs/support/`
- Launch checks (SEO/social): `.larapilot/docs/launch/`
- Client materials: `.larapilot/client-materials/` (docs/analysis from client — always honored)
- Legacy project: `.larapilot/legacy/` (codebase to rewrite/port — parity contract)
- Research: `.larapilot/research/` (Sebastian deepsearch, legacy parity matrix)

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
| 🔗 Matt | Integration Manager |
| 🎯 Oliver | Ethical Hacker |
| 🎧 Sophia | Support Manager |
| 🌍 Emily | Translator |

**Output economy:** brevity in chat per skill phase (high during implement/review/ship; clarity first during inception); artifacts, code, and CLI output stay complete and verbatim. See **Output Economy** in `.larapilot/shared-runtime.md`.

**Sub-agents:** optional readonly sub-agents on any editor with a sub-agent tool (Cursor Task, Claude Code Agent, …) in **plan** (codebase explore) and **implement** (code review + security review in parallel); inline fallback without one; parent owns CLI. See **Sub-agents** in `.larapilot/shared-runtime.md`.

Read `.larapilot/shared-runtime.md` at skill activation for full runtime rules.

Task body templates for planning and implementation: `.larapilot/task-templates.md`.
