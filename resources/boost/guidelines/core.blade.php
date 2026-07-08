## Larapilot

Larapilot brings **spec-driven product development** to Laravel projects via [Laravel Boost](https://laravel.com/ai/boost). It turns your AI agent into a disciplined product squad: discovery → backlog → plan → implement → review.

### When to use Larapilot

Use Larapilot skills when the user wants to:

- Define a product vision or write a PRD
- Create or extend a backlog of user stories / specs
- Plan a spec with technical tasks and test strategy
- Implement a planned spec in a Laravel codebase
- Review and accept (or reject) a delivered increment
- Create UI mockups before implementation

### Workflow

| Step | Skill | Output |
| --- | --- | --- |
| Discovery | `larapilot-inception` | `.larapilot/docs/PRD.md` |
| Design (optional) | `larapilot-design` | `.larapilot/mockups/` (dev route `/mockups/`) |
| Backlog | `larapilot-spec` | `.larapilot/backlog.yaml`, `.larapilot/specs/` |
| Planning | `larapilot-plan` | `.larapilot/plans/US-XXX-plan.yaml` |
| Implementation | `larapilot-implement` | Code, tests, review notes |
| Acceptance | `larapilot-review` | DONE or rework feedback |

### Installation

```bash
composer require andreapollastri/larapilot --dev
composer require laravel/boost --dev
php artisan larapilot:install
php artisan boost:install
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
- Mockups: `.larapilot/mockups/` (served at `/mockups/{spec}/` only outside production)
- Test results: `.larapilot/docs/test-results/`

### Personas

Larapilot personas are lenses, not costumes. Each applies a different kind of scrutiny:

| Persona | Role |
| --- | --- |
| 💎 Mark | Product Manager |
| 🧭 Jennifer | Business Strategist |
| 🔎 Mark | Requirements Analyst |
| 📐 John | Architect |
| 🔧 Alex | Full-Stack Developer |
| 🧪 Anne | Test Architect |
| 🛡️ Robert | Code Reviewer |
| 🎨 Elise | UX Designer |

Read `.larapilot/shared-runtime.md` at skill activation for full runtime rules.
