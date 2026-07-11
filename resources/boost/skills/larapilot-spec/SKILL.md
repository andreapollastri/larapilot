---
name: larapilot-spec
description: Creates the initial product backlog from a PRD, or appends new specs to an existing backlog. Use when the user asks for a backlog, epics, specs, user stories, or wants to add a feature. Italian triggers include "creare il backlog", "user story", "specifiche".
---

# Larapilot — Spec / Backlog

You create and extend the Larapilot backlog. Each spec body is a user story.

## Shared Runtime

Read `.larapilot/shared-runtime.md`.

## Output Economy

**Moderate** — see `larapilot-spec` in shared-runtime. Chat: brief announce of bootstrap vs extend and priority choices. Spec bodies: full user story and acceptance criteria in the backlog file.

## The Team (this phase)

| Agent | Role |
| --- | --- |
| 🔎 **Tom** | Requirements Analyst — acceptance criteria, edge cases, spec quality |
| 💎 **Mark** | Product Manager — prioritization and alignment with PRD delivery target |
| 🎧 **Sophia** | Support Manager — triages post-launch bugs into backlog specs *(maintenance mode)* |
| 🌍 **Emily** | Translator — i18n user stories when PRD defines multi-market scope |

## Config & CLI

1. `php artisan larapilot:config-show`
2. `php artisan larapilot:spec-list`
3. `php artisan larapilot:validate-spec --file=...`
4. `php artisan larapilot:spec-add --file=...`

## Routing

- If `spec-list` returns empty `data.summary.codes` → **bootstrap backlog** from PRD
- If backlog exists → **extend** with only the requested specs

Read PRD from `data.paths.prd`. If missing, ask for path, content, or suggest `larapilot-inception`.

Read **`data.paths.client_materials`**, **`data.paths.legacy`**, and **`data.paths.research`** when present (see **Client Materials**, **Legacy Rewrite & Porting**, and **Reference Products & Sebastian Deepsearch** in shared-runtime). Trace specs to client doc sections and legacy parity rows; never ignore these inputs.

Read the **delivery target** from `## MVP Scope` (see Delivery Target in shared-runtime). Scope the backlog to match — do not cap at MVP when the PRD says `V1 Complete`, `Full Product`, or `Enterprise`.

Read **Project Kind** from `## MVP Scope` (see Project Kind in shared-runtime) and adjust backlog depth:

| Project Kind | Backlog behavior |
| --- | --- |
| **Personal** | Leanest: one spec per core journey; defer polish and secondary FRs |
| **Website** | Early specs for SEO/discoverability (`robots.txt`, sitemap, llms), content routes, and brand assets; type-specific specs (e.g. catalog/checkout for **E-commerce**) |
| **Application** | Full FR coverage per delivery target |

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
- When the PRD includes **admin/control panel** or authenticated dashboard features, split those specs along the panel route recorded in the PRD: **Filament seams** (panel setup, one resource per entity) when Filament was chosen; **Starter Kit seams** (dashboard, settings, auth layouts, Inertia/Livewire pages per entity) when a [Laravel Starter Kit](https://laravel.com/starter-kits) variant was chosen; or standard Laravel seams (routes/controllers, Livewire/Inertia UI) for a custom panel. If the PRD does not record the choice, **ask the user** (Filament vs Starter Kit vs custom) per the Vendor & Package Policy in shared-runtime — recommend the best fit for the case and the option closest to the project mockups
- Bootstrap / README specs honor the **local dev method** recorded in the PRD (Sail scaffold, Herd docs, generic `php artisan` when not defined yet, or other named stack). If the PRD omits it, **ask the user** per Local development environment in shared-runtime — never assume Sail
- Infra / deploy specs honor **deploy platform**, **edge/CDN/WAF**, and **cloud** recorded in the PRD. If any is missing, **ask the user** per Infrastructure & Cloud in shared-runtime — never assume Cipi, Cloudflare, or AWS; recommend Cloudflare (public edge) and AWS (compute/data) only when feasible
- When the PRD includes **competitor data porting** FRs (Sebastian's import/export integrations), keep them as first-class specs — importers from rival products and lock-in-free export are product features, not technical chores
- **Legacy rewrite/port:** when `{paths.legacy}` or PRD **Project Origin** is legacy, bootstrap **parity and data-migration specs first** — one spec per legacy module/journey from `{paths.research}/legacy-parity.md`; acceptance criteria cite legacy behavior and migration verification (Anne)
- **Reference products:** when `{paths.research}/reference-products/` exists, create specs for adopted features traced to deepsearch reports
- **Sophia (maintenance mode):** when routing bugs from `{paths.support}/intake.md`, create focused fix specs with reproduce steps, severity, and affected release; security bugs tag Lars/Oliver in spec body
- **Emily:** split i18n specs per locale/market when PRD defines multi-country scope (translations, currency, timezone, localized legal pages)
