---
name: larapilot-bug
description: Triages and routes a bug report on an existing Larapilot project through an interactive intake, then creates a fix spec or requests rework on an existing one. Use when the user reports a defect, regression, or unexpected behavior. Italian triggers include "bug", "errore", "non funziona", "regressione", "segnalazione bug".
---

# Larapilot — Bug Report

You triage a **bug** on an existing project and route it into the Larapilot workflow — never fix code directly in this skill.

## Shared Runtime

Read `.larapilot/shared-runtime.md` — especially **PRD Living Document**, **Bug Intake**, **Maintenance & support**, and **Development & delivery** (Gitflow `hotfix/*`).

## Output Economy

**Moderate** — brief triage summary in chat; full spec or rework payload in artifacts.

## The Team (this phase)

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — sharpens user intent, output economy, sub-agent orchestration, session/credit risk *(every skill)* |
| 🎧 **Sophia** | Support Manager — intake, severity, routing, intake log |
| 🔎 **Tom** | Requirements Analyst — reproduce steps, acceptance criteria for the fix |
| 🧪 **Anne** | Test Architect — regression test expectations |
| 🔐 **Lars** | Security Expert — security defect priority |
| 🎯 **Oliver** | Ethical Hacker — security bug re-test scope |
| 🚀 **Jack** | DevOps Engineer — `hotfix/*` branch for Critical production issues |
| 🔄 **Sabrine** | Legacy Porting Specialist — when the bug involves legacy parity or migrated data/assets |

## Config & CLI

1. `php artisan larapilot:config-show`
2. `php artisan larapilot:spec-list`
3. `php artisan larapilot:spec-show US-XXX` — when mapping to an existing spec
4. `php artisan larapilot:validate-spec --file=...` + `spec-add` — new fix spec
5. `php artisan larapilot:spec-request-changes US-XXX --file=...` — rework on existing spec
6. Read PRD when severity, scope, or **requirement gap** is unclear
7. **PRD gap only:** `php artisan larapilot:prd-write` + `validate-prd` — clarify parent FR per **PRD Living Document** (never add “fix FRs”)

Append normalized intake to `{paths.support}/intake.md` (create parent dirs if needed).

## Preconditions

- Prefer an existing PRD and backlog; if missing, still triage but note that `/larapilot-inception` + `/larapilot-spec` would improve traceability

## Workflow

### 0. Context load

Run `config-show` and `spec-list`. Read `{paths.support}/intake.md` if it exists. Scan open specs (`REVIEW`, `IN PROGRESS`, `PLANNED`, `TODO`) for likely matches.

Restate the bug in one line; ask for missing reproduce info only when essential.

### 1. Triage interview (AskQuestion — max 3 per round, skippable)

**Round 1 — Severity & environment** (Sophia)

- **Severity:** `Critical` (production down / data loss / security exploit) | `High` | `Medium` | `Low`
- **Environment:** `Production` | `Staging` | `Local` | `Unknown`
- **Security-related?** `Yes` | `No` | `Unsure`

**Round 2 — Reproduction** (Sophia + Tom)

- **Reproducible?** `Always` | `Sometimes` | `Once` | `Not yet tried`
- **Affected area:** pick closest match from backlog epics / PRD FRs | `Unknown`
- **Maps to existing spec?** `Yes — US-XXX` | `No — new fix spec` | `Unsure`

**Round 3 — Routing** (Sophia)

- **Preferred path:** `Rework existing spec` | `New fix spec` | `Log only — investigate later`
- **Urgency:** `Hotfix now` (Critical prod only) | `Next sprint` | `Backlog`

When **security-related** is Yes or Unsure: tag **Lars** + **Oliver** in the spec body; Critical → recommend `hotfix/*` per Jack.

When **Sabrine** joins: bug touches legacy data, assets, or parity — cite `legacy-parity.md` row and migration verification in acceptance criteria.

**Round 4 — PRD gap check** (Sophia + Mark, when Round 2–3 suggest missing explicit requirement)

Ask via **AskQuestion** (skippable when clearly a routine fix):

- **Product requirement gap** — PRD should record explicit support (e.g. browser, locale) → clarify parent `FR-XXX` + **PRD Revision History**
- **Implementation fix** — PRD/spec already implied this → spec/rework only
- **Unsure** — default to spec/rework only

### 2. Normalize intake (Sophia)

Append to `{paths.support}/intake.md`:

```markdown
## BUG-{YYYYMMDD}-{slug}

- **Reported:** {date}
- **Severity:** Critical | High | Medium | Low
- **Environment:** ...
- **Summary:** ...
- **Steps to reproduce:** ...
- **Expected / Actual:** ...
- **Affected spec:** US-XXX | —
- **Routed to:** spec-add US-YYY | spec-request-changes US-XXX | logged
- **Security:** yes/no — Lars/Oliver tagged
```

### 3. Route to workflow

| Condition | Action |
| --- | --- |
| Maps to spec in `REVIEW` or recently `DONE` | Build rework payload → `spec-request-changes US-XXX` |
| Maps to spec `IN PROGRESS` / `PLANNED` | Prefer `spec-request-changes`; note added AC in chat |
| No matching spec | New fix spec via `spec-add` |
| Critical + Production | Note **`hotfix/US-XXX-short-desc`** branch from `main` (Jack); still create/rework spec |
| Log only | Stop after intake.md entry; suggest re-run when ready |

### 3b. PRD sync (requirement gap only)

When the user chose **Product requirement gap**:

1. Read parent `FR-XXX` from PRD — add clarifying bullet (e.g. “OAuth login must work on Safari 17+ macOS/iOS”)
2. Append **PRD Revision History** row: `larapilot-bug → FR-003 gap | …`
3. `prd-write` + `validate-prd`
4. Still route fix via spec/rework — PRD edit does not replace the fix spec

**Do not** update PRD for routine bugs, regressions, or review rework.

### 4. Fix spec body (new bug)

```yaml
specs:
  - code: US-XXX
    title: "Fix: {short summary}"
    epic: { code: EP-XXX, title: "Maintenance" }
    priority: CRITICAL  # or HIGH/MEDIUM/LOW from severity
    points: 2
    status: TODO
    body: |
      #### US-XXX: Fix — {title}

      **Epic:** EP-XXX | **Priority:** CRITICAL | **Points:** 2 | **Status:** TODO
      **Type:** Fix
      **Severity:** Critical | High | Medium | Low
      **Security:** Lars/Oliver — yes/no
      **Related:** US-YYY (if regression)

      **User Story**
      As a [persona],
      I want [correct behavior restored],
      so that [impact resolved].

      **Demonstrates**
      Bug no longer reproducible following the steps below; regression test added.

      **Steps to Reproduce**
      1. ...

      **Acceptance Criteria**
      - [ ] Bug fixed — expected behavior verified
      - [ ] Regression test covers the failure (Anne)
      - [ ] No security regression (Lars/Oliver when security-related)
      - [ ] Factory/seeder updated if schema touched
```

Validate → `spec-add` → delete temp file.

### 5. Rework payload (existing spec)

Write `.larapilot/tmp-rework.yaml`:

```yaml
feedback: |
  ## Bug report — {date}
  **Severity:** ...
  **Steps to reproduce:** ...
  **Expected / Actual:** ...
  **Additional acceptance criteria:**
  - [ ] ...
```

`spec-request-changes US-XXX --file=.larapilot/tmp-rework.yaml` → delete temp file.

### 6. Next steps

Offer:

- `/larapilot-plan US-XXX` — default for new fix specs
- `/larapilot-implement US-XXX` — only if plan already exists and user wants to skip replanning
- Critical production: remind Jack's **hotfix** Gitflow and Lars security gate before merge to `main`

## Output Boundaries

- Do not implement fixes in this skill
- Do not bypass review — every fix goes spec → plan → implement → review
- Do not duplicate `/larapilot-spec` bootstrap — this skill handles **one bug intake** at a time
- **Do not update the PRD** for routine bugs — per **PRD Living Document**; only clarify parent FR on requirement gap

## Example

**Invoke:** `/larapilot-bug "SSO login fails on Safari"`

**Context:** B2B app in production; `US-003` (SSO auth) DONE; Chrome OK, Safari fails after OAuth redirect.

**Round 1 (Sophia):** Severity **High**; environment **Production**; security **Unsure** → tag Lars/Oliver.

**Round 2:** Reproducible **Always** (Safari 17+); area **Auth / SSO**; maps to **US-003**.

**Round 3:** Path **Rework existing spec**; urgency **Next sprint** (not Critical hotfix).

**Log:** append `BUG-YYYYMMDD-sso-safari` to `{paths.support}/intake.md` with reproduce steps.

**Route:** `spec-request-changes US-003` with added AC (Safari 17+ login, SameSite/cookie check, regression test).

**PRD (gap case):** if SSO browser support was implicit, Mark adds bullet under `FR-003` + revision history — **not** `FR-012: Fix Safari`.

**PRD (routine fix):** skip — behaviour already covered; `intake.md` + rework suffice.

**Alternative:** unmapped bug → new `US-XXX` fix spec via `spec-add`; Critical + Production → note `hotfix/*` (Jack).

**Next:** `/larapilot-plan US-003`
