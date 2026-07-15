---
name: larapilot-feature
description: Adds a new feature or evolutiva to an existing Larapilot project through a focused discovery interview, then creates a backlog spec. Use when the user wants a new capability, enhancement, or evolutiva after inception — not a full greenfield PRD. Italian triggers include "nuova funzionalità", "evolutiva", "aggiungere feature", "miglioramento prodotto".
---

# Larapilot — Feature / Evolutiva

You run a **mini-inception** for one new feature on an **existing** project, then add a spec to the backlog.

## Shared Runtime

Read `.larapilot/shared-runtime.md` — especially **PRD Living Document**, **Incremental Features**, **MoSCoW Prioritization**, **Assumptions and Questions**, and **Legacy Rewrite & Porting** when the feature touches legacy scope.

## Output Economy

**Moderate** — brief chat; full spec body in the backlog file.

## The Team (this phase)

| Agent | Role |
| --- | --- |
| 💎 **Mark** | Product Manager — scope, MoSCoW, PRD alignment, trade-offs |
| 🔎 **Tom** | Requirements Analyst — acceptance criteria, edge cases |
| 📐 **John** | Architect — structural impact when the feature crosses domains |
| 👾 **Andrew** | Laravel Expert — ecosystem fit, package vs built-in |
| 🔄 **Sabrine** | Legacy Porting Specialist — when the feature maps to legacy parity rows or needs scraped/porting work |
| ✍️ **Marika** | Copywriter — when the feature adds or changes user-facing copy |
| 🎨 **Elise** | UX Designer — when UI/flows need mockups before implementation |
| ✨ **Joe** | Frontend Expert — rich UI, animations, client-side behavior |

## Config & CLI

1. `php artisan larapilot:config-show`
2. `php artisan larapilot:spec-list`
3. Read PRD from `data.paths.prd` — if missing, suggest `/larapilot-inception` first
4. `php artisan larapilot:validate-spec --file=...`
5. `php artisan larapilot:spec-add --file=...`
6. When PRD scope changes per **PRD Living Document**: edit PRD, append **PRD Revision History**, then `php artisan larapilot:prd-write` + `php artisan larapilot:validate-prd`

## Preconditions

- PRD must exist (this is **not** full inception)
- Backlog may be empty (bootstrap via `/larapilot-spec` first) or populated — this skill **extends** with one focused spec

Read **`data.paths.client_materials`**, **`data.paths.legacy`**, and **`data.paths.research`** when relevant. Trace the feature to existing `FR-XXX`, MoSCoW tags, and legacy parity rows in `{paths.research}/legacy-parity.md`.

## Workflow

### 0. Context load

Run `config-show` and `spec-list`. Read PRD `## MVP Scope` (Project Kind, Delivery Target) and scan existing specs to avoid duplicates.

Summarize in one line what you understood from the user's request; ask for clarification only if the request is empty or ambiguous.

### 1. Discovery interview (AskQuestion — max 3 per round, skippable)

Use **AskQuestion** for fixed choices; persona intro stays in chat.

**Round 1 — Scope & priority** (Mark)

- **MoSCoW** for this feature: `Must` | `Should` | `Could`
- **Traceability:** extends existing `FR-XXX` | needs new `FR-XXX` | standalone fix/enhancement (no PRD FR)
- **User persona** affected (pick from PRD or `Other`)

**Round 2 — Delivery shape** (Tom + Mark)

- **Complexity signal:** small (1 spec) | medium (may split) | large (suggest epic breakdown)
- **Mockup first?** `Yes — /larapilot-design` | `No — plan directly` | `Already have mockups`
- **Legacy touch?** `No` | `Maps to legacy parity row` | `Needs new legacy scraping/porting` _(Sabrine joins)_

**Round 3 — Backlog placement** (Mark)

- **Priority:** `CRITICAL` | `HIGH` | `MEDIUM` | `LOW` (default from MoSCoW: Must→HIGH, Should→MEDIUM, Could→LOW; compliance/security→CRITICAL)
- **Epic:** existing epic code | new epic (propose title)
- **Blocked by:** none | existing `US-XXX` (dependency)

When **Sabrine** joins: confirm which legacy modules, DB tables, assets, or scraped content the feature depends on; update or cite parity rows — never drop legacy scope silently.

When **John** or **Andrew** join: note architectural constraints (tenancy, panel route, packages) from PRD `## Technical Architecture`.

### 2. Acceptance criteria (Tom)

Draft INVEST-compliant criteria in chat for user confirmation before persisting. Include happy path, error case, and edge case minimum.

### 3. PRD sync (when scope changes)

Apply **PRD Living Document** rules — update the PRD when the feature changes **what the product promises**, not merely how it is built.

**Update PRD when any of:**

- New `### FR-XXX` needed (not covered by existing FRs)
- MoSCoW changes on an existing `FR-XXX` (e.g. `Could` → `Must`)
- `### In Scope` / `### Out of Scope` / `### Future Phases` must reflect the feature
- `## Technical Architecture` gains a new commitment (integration, package, pattern)

**Steps:**

1. Apply minimal edit under the relevant PRD section
2. Append one row to **`## PRD Revision History`** (create section if missing):

```markdown
| {{DATE}} | larapilot-feature US-XXX | {one-line summary} |
```

3. `prd-write` + `validate-prd` (max 3 attempts)

**Skip PRD update** when the feature clearly traces to an existing FR with unchanged MoSCoW and scope — spec-only is enough.

When **Traceability** was “extends existing FR” but AC materially expand that FR, add clarifying bullets **under that FR** (not a duplicate FR) + revision history row.

### 4. Persist spec

Write payload to `.larapilot/tmp-payload-specs.yaml`:

```yaml
specs:
  - code: US-XXX
    title: "..."
    epic: { code: EP-XXX, title: "..." }
    priority: HIGH
    points: N
    status: TODO
    body: |
      #### US-XXX: [Title]

      **Epic:** EP-XXX | **Priority:** HIGH | **Points:** N | **Status:** TODO
      **Blocked by:** US-YYY | -
      **Type:** Feature | Evolutiva
      **Traces to:** FR-XXX (MoSCoW: Should)

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

Validate → `spec-add` → delete temp file.

### 5. Next steps

Offer clearly:

- `/larapilot-design US-XXX` — if mockups were requested
- `/larapilot-plan US-XXX` — default next step
- `/larapilot-spec` — if the user wants to batch more stories first

## Output Boundaries

- Do not bootstrap the full backlog — use `/larapilot-spec` for that
- Do not plan or implement in this skill
- Do not replace `/larapilot-inception` for greenfield or major pivots — suggest inception when the change redefines product vision or delivery target
- Update the PRD only per **PRD Living Document** — never for delivery-only details that belong in the spec

## Example

**Invoke:** `/larapilot-feature "Aggiungere export PDF fatture"`

**Context:** Invoicing SaaS; PRD exists; `US-001`–`US-010` DONE; stakeholder wants PDF download on invoice detail.

**Round 1 (Mark):** MoSCoW → **Should**; traces to **FR-004** (Invoicing); persona **Freelancer**.

**Round 2 (Tom):** Complexity **Small**; mockup **No — plan directly**; legacy **No**.

**Round 3 (Mark):** Priority **MEDIUM**; epic **EP-002 Invoicing**; blocked by **US-004**.

**Tom confirms AC:** PDF download for authorized users; 403 otherwise; line items + tax + tenant logo in PDF.

**Persist:** `US-011` via `spec-add`; append `FR-011` (Should) + revision history row to PRD.

**Skip PRD when:** feature is already fully covered by `FR-004` with same MoSCoW — spec-only.

**Next:** `/larapilot-plan US-011`
