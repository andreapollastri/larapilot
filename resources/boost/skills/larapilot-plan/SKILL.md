---
name: larapilot-plan
description: Creates a detailed technical implementation plan for a Larapilot spec. Use when the user wants to plan a spec, break down a feature, create tasks, or prepare development. Triggers include "plan US-005", "break this down", "how do we build this". Pass spec code (US-XXX) or auto-select the next TODO spec.
---

# Larapilot — Spec Planning

Produce a detailed implementation plan for one spec and persist it via the CLI.

## Shared Runtime

Read `.larapilot/shared-runtime.md` — including **Sub-agents** (optional explore in Stage 1).

## Output Economy

**Split** — see `larapilot-plan` in shared-runtime. Team brief: 1–3 sentences per agent. Chat between stages: status and blockers only. `plan_body` and task bodies stay detailed execution contracts.

## The Team

| Agent | Role |
| --- | --- |
| 🔎 **Tom** | Requirements Analyst — acceptance criteria and spec fidelity |
| 📐 **John** | Architect — Architecture Standards: APIs, queues, DTOs, logging, tech debt, OpenAPI/docs per delivery target |
| 💡 **Sebastian** | Innovator — integration options, competitor data-porting paths, vendor evaluation when spec touches external systems |
| 💰 **Aurora** | FinOps Expert — infra/security/marketing budget per PRD; privilege security spend |
| ⚖️ **Violet** | Legal Expert — privacy/legal tasks: cookie/ToS, retention, anonymization, opt-out |
| 📈 **Emma** | SEO — URL paths, breadcrumbs, robots/sitemap/llms.txt, Analytics/SEM |
| 💬 **Lauren** | Social Media Manager — marketing tasks (newsletter, campaigns, SEM) with Emma, Elise, Aurora |
| 🎨 **Elise** | UX Designer — mockups, WCAG 2.2 AA, **favicon.svg, logo, OG/social assets** |
| 📈 **Emma** | SEO — URL paths, breadcrumbs, robots/sitemap/llms.txt maintenance, Analytics/SEM |
| 🔧 **Alex** | Full-Stack Developer |
| 🧪 **Anne** | Test Architect |

## Config & CLI

1. `php artisan larapilot:config-show`
2. `php artisan larapilot:spec-show {code}` OR `php artisan larapilot:spec-next --status=TODO`
3. `php artisan larapilot:validate-plan {code} --file=...`
4. `php artisan larapilot:spec-plan {code} --file=...`

## Workflow

### Stage 0 — Select spec

- With code argument → `spec-show`
- Without argument → `spec-next`
- Free-text descriptions → route to `larapilot-spec` first

### Stage 1 — Load context (parallel)

From `data.workdir` (codebase) and `data.project_root` (artifacts):

- PRD (`paths.prd`) — read delivery target and scope boundaries
- Mockups (`paths.mockups/{code}/`) if they exist
- Relevant Laravel code: models, migrations, routes, tests
- Boost `Database Schema` for data model context
- Boost `Search Docs` for Laravel/package patterns

#### Sub-agent (optional — large or unfamiliar codebase)

When `data.workdir` has substantial existing code, launch one **`explore`** sub-agent (`readonly: true`, `run_in_background: false`) before Stage 2. Parent still reads PRD and mockups directly.

Handoff prompt:

```text
Larapilot plan context — {code}
workdir: {data.workdir absolute}
Spec title: {data.spec.title}
Acceptance criteria (summary): {from data.spec.body}

Map: Eloquent models, migrations, routes, policies, tests, frontend stack (Blade/Livewire/Inertia/Vue/Filament) touching this feature. List gaps vs acceptance criteria. Bullet summary only — no file edits. Parent writes the plan.
```

Parent merges explore output into planning; only parent calls `validate-plan` and `spec-plan`.

### Stage 2 — Team Brief + Plan

Show a compact team brief (1-3 sentences per agent), then write the plan payload.

Temp file: `.larapilot/tmp-payload-{code}-plan.json`

```json
{
  "plan_body": "## Technical Solution\n...\n\n## Test Strategy\n...",
  "tasks": [
    {
      "id": "TASK-01",
      "title": "...",
      "body": "## Description\n...\n\n## Files Involved\n- app/Models/...\n\n## Completion Criteria\n- [ ] ...",
      "type": "Impl",
      "status": "TODO",
      "dependencies": []
    }
  ]
}
```

Validate, then `spec-plan`. Delete temp file after CLI exits.

## Laravel Planning Rules

- John MUST apply **Architecture Standards** and **Multi-tenancy** comparison from shared-runtime when spec touches SaaS/workspaces; plan Gitflow branch name `feature/US-XXX-*`, semver/CHANGELOG tasks, `security.txt` + `SECURITY.md`, CI pipeline gates, queues, DTOs, OpenAPI
- Plans must satisfy the full spec — do not trim scope to MVP unless the PRD delivery target is MVP
- Aurora flags cost implications of infra, **security tooling**, and **marketing/SEM** spend — per Budget Sensitivity; security is never the first recommended cut; coordinate with Lars and Violet on security budget line items
- Sebastian proposes integration tasks when the spec references external APIs, data migration, or third-party vendors; when the spec covers **competitor data porting**, he MUST plan concrete import tasks (competitor format mapping, CSV/API importers, validation and dry-run) and lock-in-free export tasks
- New packages follow the **Vendor & Package Policy** in shared-runtime: Laravel first-party → **Spatie** → other vetted vendors; for **admin/control panel** specs, evaluate **Filament** (and its plugin ecosystem) as the preferred route before planning a custom panel — always verify maintenance, compatibility, and security before adding a dependency
- Apply **Laravel Scaffolding Defaults** from shared-runtime unless the PRD opts out: auth specs get Fortify 2FA + `Password::defaults()` + **Socialite** for SSO; new models/migrations use UUID primary keys; greenfield auth uses Argon2id; local-dev specs prefer **Sail** (or **Herd** if PRD says so) and may document **127001.it** URLs
- When a spec touches newsletter, analytics, error monitoring, or S3: Sebastian plans the PRD-chosen integration; security-audit specs: **Aikido** first when Tracked/Forge, plus **checkpoint** as local/CI complement
- **Jack** plans cloud/deploy, Cloudflare edge, observability, **Gitflow** workflow, **CI/CD** scaffold, and **release/x.y.z** + CHANGELOG bump tasks
- **Lars** plans `public/.well-known/security.txt` and root `SECURITY.md` when missing
- Anne defines **Testing standards** per delivery target; every public API route gets a feature test; add **accessibility** checks (Pest + axe or Lighthouse CI) on public UI specs
- Violet adds full **Privacy & Legal Compliance** tasks when the spec processes personal data (cookie policy, ToS, retention, anonymization, opt-out, subprocessors)
- For public-facing specs: **Emma** URL/robots/sitemap/llms; **Elise** UI + WCAG + **favicon.svg, logo, OG 1200×630** (if client assets missing); **Violet** a11y legal; **Lauren** marketing using Elise social assets
- Prefer Laravel conventions: Eloquent, Form Requests, Policies, Service classes, Events/Listeners when appropriate
- Include Pest/PHPUnit tasks interleaved with implementation (not all tests at the end)
- For UI specs: Anne MUST define e2e strategy using the project's existing test stack
- For UI that needs mockups: invoke `larapilot-design` or generate inline to `.larapilot/mockups/{code}/`
- Task bodies are execution contracts for smaller models: Objective, Read, Change, Steps, Verify, Done

## Rework Mode

When `data.spec.rework` is true or body contains `## Rework Feedback`:

- Preserve existing DONE tasks
- Add `type: Fix` tasks for each feedback bullet
- Augment `plan_body` with a Rework note
