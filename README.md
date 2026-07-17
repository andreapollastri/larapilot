# Larapilot

**From product idea to reviewed Laravel code — with an AI product team that follows a real process.**

Larapilot is a spec-driven workflow for Laravel projects, integrated with [Laravel Boost](https://laravel.com/ai/boost). Install the package, run `/larapilot-*` skills in your AI editor, and ship backlog artifacts, plans, and reviewed code from `.larapilot/`.

**The agent proposes. You approve what ships.** Human-in-the-loop, always.

📖 **Documentation:** [larapilot.web.ap.it](https://larapilot.web.ap.it) · [Walkthrough](https://larapilot.web.ap.it/#walkthrough) · [API](https://larapilot.web.ap.it/#deep-dive-api)

---

## Why Larapilot

AI agents are fast, but isolated prompts are not a product process. Larapilot gives your assistant a disciplined squad — discovery → backlog → plan → implement → review → ship — with **27 personas** (Mark, John, Alex, Anne, …) as review lenses, not costumes.

Each skill orchestrates the conversation. **Artisan commands** persist state; **Boost skills** drive the workflow in chat; **MCP** exposes Laravel context and workflow tools to your editor.

---

## Core loop

Greenfield — repeat steps 3–5 per user story:

```
/larapilot-inception "…"  →  /larapilot-spec  →  /larapilot-plan US-XXX
  →  /larapilot-implement US-XXX  →  /larapilot-review US-XXX
```

| When | Start with |
| --- | --- |
| New product, pivot, or legacy rewrite | `/larapilot-inception` |
| One new capability on an existing product | `/larapilot-feature "…"` |
| Defect or regression | `/larapilot-bug "…"` |

Optional: `/larapilot-design` before plan · `/larapilot-ship` when MVP stories are **DONE** · `/larapilot-autopilot` to batch plan + implement · `/larapilot-settings` for project effort / git / testing modes.

Git discipline follows **`settings.git_mode`** (default **Gitflow without auto-push**): one `feature/US-XXX-*` branch per story, atomic commits per plan task; push + remote PR only when mode is **`GITFLOW_PUSH`**. Configure with `/larapilot-settings`. Details on the [docs site](https://larapilot.web.ap.it/#deep-dive-gitflow).

---

## What lands in `.larapilot/`

| Path | Purpose |
| --- | --- |
| `config.yaml` | Project workflow config + `settings` (`effort`, `git_mode`, `testing`, `auto_approve`) |
| `docs/PRD.md` | Product Requirements Document |
| `backlog/` | User stories (`US-XXX`) with status machine |
| `plans/` | Technical plans and tasks per spec |
| `mockups/{spec}/` | Static HTML previews (optional) |
| `internal-feedback/{code}.md` | PM/dev comments until **DONE** |
| `design-systems/` | Packaged references (Filament, Starter Kit, Bootstrap 5, Tailwind, AdminLTE) |

Skills write artifacts; the workflow engine blocks invalid state transitions (e.g. implement before plan, approve before review).

---

## Skills

Published via Laravel Boost after `php artisan boost:install`:

| Skill | Role |
| --- | --- |
| `/larapilot-inception` | Product discovery → PRD |
| `/larapilot-spec` | MoSCoW backlog from PRD |
| `/larapilot-feature` | Mini-inception for one evolutiva |
| `/larapilot-bug` | Bug triage → fix spec or rework |
| `/larapilot-design` | Static HTML mockups from design system |
| `/larapilot-plan` | Technical plan + tasks for a spec |
| `/larapilot-implement` | Code + tests on a feature branch |
| `/larapilot-review` | Human gate → **DONE** or rework |
| `/larapilot-ship` | Release checklist when MVP is done |
| `/larapilot-autopilot` | Batch plan + implement |
| `/larapilot-settings` | Persist effort / git mode / testing / auto-approve for the project |

---

## Dashboard & API (dev/staging)

When the dashboard is browsable (never in production):

- **`/larapilot`** — Kanban board, PRD reader, spec detail with mockup preview and internal feedback
- **`/larapilot/api`** — JSON over the same artifacts (board, specs, PRD, OpenAPI at `/larapilot/api/docs`)
- **`POST /larapilot/api/specs/{code}/comments`** — append internal feedback from scripts or tooling

Workflow **state** still changes only via skills or Artisan — not from the dashboard or API.

---

## Requirements

- PHP **^8.3**
- Laravel **^12** or **^13**
- [Laravel Boost](https://laravel.com/ai/boost) `^2.0` (installed automatically)
- MCP-capable editor (Cursor, Claude Code, VS Code, …)

---

## Quickstart

```bash
composer require andreapollastri/larapilot --dev
php artisan larapilot:install
php artisan boost:install
```

Already on Boost? Refresh skills once:

```bash
php artisan boost:update --discover
```

Register MCP servers in your editor if needed:

```json
{
  "mcpServers": {
    "laravel-boost": {
      "command": "php",
      "args": ["artisan", "boost:mcp"]
    },
    "larapilot": {
      "command": "php",
      "args": ["artisan", "mcp:start", "larapilot"]
    }
  }
}
```

First run in your editor:

```
/larapilot-inception "your product idea"
```

Then `/larapilot-spec`, and the per-story loop above.

### Upgrade

```bash
composer update andreapollastri/larapilot
php artisan larapilot:update
php artisan larapilot:doctor
```

Runtime-only refresh (skip Boost republish): `php artisan larapilot:update --skip-boost`.

---

## Learn more

- [Why & how it works](https://larapilot.web.ap.it/#how-it-works)
- [Four walkthrough examples](https://larapilot.web.ap.it/#examples) — new product, legacy port, feature, bug
- [Design systems](https://larapilot.web.ap.it/#deep-dive-design-systems)
- [Team personas](https://larapilot.web.ap.it/#deep-dive-team)

---

## License

MIT © [Andrea Pollastri](https://web.ap.it)
