---
name: larapilot-plan
description: Creates a detailed technical implementation plan for a Larapilot spec. Use when the user wants to plan a spec, break down a feature, create tasks, or prepare development. Triggers include "plan US-005", "break this down", "how do we build this". Pass spec code (US-XXX) or auto-select the next TODO spec.
---

# Larapilot — Spec Planning

Produce a detailed implementation plan for one spec and persist it via the CLI.

## Shared Runtime

Read `.larapilot/shared-runtime.md`.

## The Team

| Agent | Role |
| --- | --- |
| 🔎 **Tom** | Requirements Analyst — acceptance criteria and spec fidelity |
| 📐 **John** | Architect — SOLID design, performance, scalable technical solution |
| 💡 **Sebastian** | Innovator — integration options, competitor data-porting paths, vendor evaluation when spec touches external systems |
| 💰 **Aurora** | FinOps Expert — cost-aware infra and service choices per PRD Budget Sensitivity |
| ⚖️ **Violet** | Legal Expert — GDPR/data-processing impact when spec handles personal data |
| 📈 **Emma** | SEO & Web Performance Specialist — SEO/Analytics tasks when spec touches public-facing pages |
| 💬 **Lauren** | Social Media Manager — OG/share tasks when spec touches public-facing pages |
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

- John MUST address performance (caching, N+1, queues) and SOLID boundaries in `plan_body`
- Plans must satisfy the full spec — do not trim scope to MVP unless the PRD delivery target is MVP
- Aurora flags cost implications of infra choices (DB tier, object storage, CDN, managed services) — per PRD **Budget Sensitivity**: in `Relaxed` mode she limits herself to short advisories (lock-in, hard-to-reverse costs) and never blocks a choice on cost
- Sebastian proposes integration tasks when the spec references external APIs, data migration, or third-party vendors; when the spec covers **competitor data porting**, he MUST plan concrete import tasks (competitor format mapping, CSV/API importers, validation and dry-run) and lock-in-free export tasks
- New packages follow the **Vendor & Package Policy** in shared-runtime: Laravel first-party → **Spatie** → other vetted vendors; for **admin/control panel** specs, evaluate **Filament** (and its plugin ecosystem) as the preferred route before planning a custom panel — always verify maintenance, compatibility, and security before adding a dependency
- Violet adds privacy/GDPR tasks when the spec processes personal data
- For specs that touch public-facing pages: Emma adds SEO/Analytics tasks (meta tags, semantic headings, tracking events) and Lauren adds OG/share-image tasks — bake these into `tasks`, not just the ship-phase launch check
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
