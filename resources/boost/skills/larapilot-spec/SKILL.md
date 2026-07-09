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
| 🔎 **Tom** | Requirements Analyst — acceptance criteria, edge cases, spec quality |
| 💎 **Mark** | Product Manager — prioritization and alignment with PRD delivery target |

## Config & CLI

1. `php artisan larapilot:config-show`
2. `php artisan larapilot:spec-list`
3. `php artisan larapilot:validate-spec --file=...`
4. `php artisan larapilot:spec-add --file=...`

## Routing

- If `spec-list` returns empty `data.summary.codes` → **bootstrap backlog** from PRD
- If backlog exists → **extend** with only the requested specs

Read PRD from `data.paths.prd`. If missing, ask for path, content, or suggest `larapilot-inception`.

Read the **delivery target** from `## MVP Scope` (see Delivery Target in shared-runtime). Scope the backlog to match — do not cap at MVP when the PRD says `V1 Complete`, `Full Product`, or `Enterprise`.

## Bootstrap backlog (from PRD)

| Delivery target | Backlog depth |
| --- | --- |
| **MVP** | Lean: one spec per core user journey; defer secondary FRs to Future Phases |
| **V1 Complete** | Core + essential secondary features; bounded but production-ready |
| **Full Product** | One spec (or epic group) per FR in `## Functional Requirements`; multi-epic backlog expected |
| **Enterprise** | Full Product breadth + compliance, integrations, observability, and ops specs |

When extending an existing backlog, new specs must stay consistent with the PRD delivery target.

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
- When the PRD includes **admin/control panel** features, split those specs along Filament seams (panel setup, one resource per entity) — Filament is the preferred route per the Vendor & Package Policy in shared-runtime
- When the PRD includes **competitor data porting** FRs (Sebastian's import/export integrations), keep them as first-class specs — importers from rival products and lock-in-free export are product features, not technical chores
