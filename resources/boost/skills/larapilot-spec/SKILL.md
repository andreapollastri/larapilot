---
name: larapilot-spec
description: Creates the initial product backlog from a PRD, or appends new specs to an existing backlog. Use when the user asks for a backlog, epics, specs, user stories, or wants to add a feature. Italian triggers include "creare il backlog", "user story", "specifiche".
---

# Larapilot — Spec / Backlog

You create and extend the Larapilot backlog. Each spec body is a user story.

## Shared Runtime

Read `.larapilot/shared-runtime.md`.

## The Team (this phase)

| Agent | Role |
| --- | --- |
| 🔎 **Mark** | Requirements Analyst — acceptance criteria, edge cases, spec quality |
| 💎 **Mark** | Product Manager — prioritization and MVP alignment |

## Config & CLI

1. `php artisan larapilot:config-show`
2. `php artisan larapilot:spec-list`
3. `php artisan larapilot:validate-spec --file=...`
4. `php artisan larapilot:spec-add --file=...`

## Routing

- If `spec-list` returns empty `data.summary.codes` → **bootstrap backlog** from PRD
- If backlog exists → **extend** with only the requested specs

Read PRD from `data.paths.prd`. If missing, ask for path, content, or suggest `larapilot-inception`.

## Spec Template

```markdown
#### US-XXX: [Title]

**Epic:** EP-XXX | **Priority:** HIGH | **Points:** N | **Status:** TODO
**Blocked by:** -

**User Story**
As [persona],
I want [capability],
so that [benefit].

**Demonstrates**
After implementing this spec, [observable verification].

**Acceptance Criteria**
- [ ] [Happy path]
- [ ] [Error case]
- [ ] [Edge case]
```

## Payload Shape

Write payload to `.larapilot/tmp-payload-specs.yaml`:

```yaml
specs:
  - code: US-001
    title: "..."
    epic: { code: EP-001, title: "..." }
    priority: HIGH
    points: 3
    status: TODO
    body: |
      ...markdown user story...
```

Validate first, then `spec-add`. Delete temp file after CLI exits.

## Laravel Notes

- Split specs along Laravel seams: models/migrations, routes/controllers, policies, Livewire/Inertia UI, API resources
- Keep specs INVEST-compliant and independently demonstrable
- Use Boost `Application Info` to align specs with installed packages (Livewire, Inertia, Pest, etc.)
