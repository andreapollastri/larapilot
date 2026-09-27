---
name: larapilot-bug
description: "Triages a bug into a fix spec or a rework. Italian: bug, errore, non funziona, regressione."
---

# Larapilot — Bug Report

You triage a **bug** on an existing project and route it into the Larapilot workflow — never fix code directly in this skill.

## Shared Runtime

Obey **Read protocol** in `.larapilot/shared-runtime.md`: file-read tool only, never `cat` / `head` / `sed`. A truncated preview is a failed load — read the remainder before any other step. Then read only the section files that index lists for this skill.

Read `.larapilot/shared-runtime.md` (core), then `.larapilot/runtime-ops.md` (**PRD Living Document**, **Maintenance & Support**), `.larapilot/runtime-delivery.md` (Gitflow `hotfix/*`), and `.larapilot/runtime-dev-docs.md` — a fix that changes a domain's behavior updates that domain's file under `{paths.dev_docs}` in the same change, and when `data.dev_docs.documented` is `false` the fix first brings the whole project level (**First-change catch-up**). On a **requirement gap** only, also read `.larapilot/runtime-discovery-7.md` (**Requirement Quality**, **Non-Functional Requirements**) — the part directly, not the discovery index.

When `data.settings.decision_log` is `YES` (default), journal material user choices with `php artisan larapilot:decision-log` and run `php artisan larapilot:decision-check` before reversing a previously recorded choice. When `data.settings.code_history` is `YES` (default OFF), run `php artisan larapilot:code-log` after each `task-done`. Contracts in `shared-runtime.md`.

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
2. `php artisan larapilot:diagnostics` — optional runtime snapshot (status, health checks, redacted log tail); also via MCP `diagnostics` or `GET /larapilot/api/diagnostics` when the dashboard is browsable
3. `php artisan larapilot:spec-list`
4. `php artisan larapilot:spec-show US-XXX` — when mapping to an existing spec
5. `php artisan larapilot:validate-spec --file=...` + `spec-add` — new fix spec, or re-issue of an open spec with added criteria (same code, **no `status` key**)
6. `php artisan larapilot:spec-request-changes US-XXX --file=...` — rework on a spec in `REVIEW`, the only status it accepts
7. Read PRD when severity, scope, or **requirement gap** is unclear
8. **PRD gap only:** `php artisan larapilot:prd-write` + `validate-prd` — clarify parent FR per **PRD Living Document** (never add “fix FRs”); `php artisan larapilot:prd-impact --ids=FR-XXX` lists the other specs that rest on the promise being clarified

Append normalized intake to `{paths.support}/intake.md` (create parent dirs if needed).

## Preconditions

- Prefer an existing PRD and backlog; if missing, still triage but note that `/larapilot-inception` + `/larapilot-spec` would improve traceability

## Handoff from `larapilot-triage`

When the session arrives with a **Triage handoff** block, take it as answers already given:

- The every-skill runtime rows are loaded and Zoey's start line is posted — do not repeat either. Post the end line and the single `usage-log`, counting what triage read
- `request` is the report — do not restate it or ask for it again
- `maps to` answers **Maps to existing spec?** in Round 2; `evidence` answers **Promise broken**
- `verdict: Bug — requirement gap` answers Round 4 as **Product requirement gap** — confirm it in one line instead of asking
- A **Boogle error** block under `evidence`: environment is production and the reproduction starts from `request` and `where`, so ask neither; severity from `thrown` and the route, not from the user. Quote `codes`, `class`, and `where` in the intake entry and in the fix spec, with a test that throws the same exception before the fix. Never ask who the user of the request was. `larapilot-boogle` records the link to the spec
- An **Aikido finding** block under `evidence`: severity is Aikido's and environment is the repository, so ask neither. Quote `ids`, `severity`, and `cves` in the intake entry and in the fix spec, and write `fix` as the remedy to verify, with an acceptance criterion that Aikido no longer reports the finding. `larapilot-aikido` records the link to the spec

With or without a handoff: when reproduction shows nothing ever promised this behavior, say so in one line and hand over to `larapilot-feature` instead of writing a fix spec. Hand over once, on new evidence only — never against a verdict the user settled.

## Workflow

### 0. Context load

Run `config-show` and `spec-list`. When the report mentions production/staging errors, stack traces, or “check the logs”, run `larapilot:diagnostics` (or MCP diagnostics) and cite relevant redacted lines in intake — do not paste secrets. Read `{paths.support}/intake.md` if it exists. Scan open specs (`REVIEW`, `IN PROGRESS`, `PLANNED`, `TODO`) for likely matches.

**Already reported?** When intake already holds the same defect, add one `Occurrences` line to that entry (date, environment, reporter) and route to the spec it names — no second entry, no second spec. A defect that returns after its fix shipped is a **regression**: new entry, `**Related:**` the fix spec, severity one step up.

Restate the bug in one line; ask for missing reproduce info only when essential.

### 1. Triage interview (AskQuestion — max 3 per round, skippable)

**Round 1 — Severity & environment** (Sophia)

- **Severity:** `Critical` (production down / data loss / security exploit) | `High` | `Medium` | `Low`
- **Environment:** `Production` | `Staging` | `Local` | `Unknown`
- **Security-related?** `Yes` | `No` | `Unsure`

**Round 2 — Reproduction** (Sophia + Tom)

- **Reproducible?** `Always` | `Sometimes` | `Once` | `Not yet tried`
- **Affected area:** closest journey `J-XXX`, FR, or backlog epic | `Unknown`
- **Maps to existing spec?** `Yes — US-XXX` | `No — new fix spec` | `Unsure`

**Promise broken** (Tom — read from the PRD and the spec, not a question): quote the `FR-XXX` done-means bullet, the `US-XXX` acceptance criterion, or the `NFR-XXX` target the defect violates. `Nothing written` opens Round 4. A fix without a quoted promise has no definition of fixed.

**Round 3 — Routing** (Sophia)

- **Preferred path:** `Rework existing spec` | `New fix spec` | `Log only — investigate later`
- **Urgency:** `Hotfix now` (Critical prod only) | `Next sprint` | `Backlog`

When **security-related** is Yes or Unsure: tag **Lars** + **Oliver** in the spec body; Critical → recommend `hotfix/*` per Jack.

When **Sabrine** joins: bug touches legacy data, assets, or parity — cite `legacy-parity.md` row and migration verification in acceptance criteria.

**Round 4 — PRD gap check** (Sophia + Mark, when Round 2–3 suggest missing explicit requirement)

Ask via **AskQuestion** (skippable when clearly a routine fix):

- **Product requirement gap** — PRD should record explicit support (e.g. browser, locale) → clarify it where it belongs + **PRD Revision History**: a **Done means** bullet under the parent `FR-XXX`, the NFR row that owns it (compatibility, performance, accessibility, retention), or the state missing from `## Domain Model`
- **Implementation fix** — PRD/spec already implied this → spec/rework only
- **Unsure** — default to spec/rework only

When the gap is wider than one clarification — several FRs, a priority or scope change, a recorded decision to reverse — keep the fix here and name `/larapilot-prd` under **Next steps** for the PRD part.

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
- **Promise broken:** FR-XXX "…" | US-XXX criterion "…" | NFR-XXX target | none — requirement gap
- **Affected spec:** US-XXX | —
- **Routed to:** spec-add US-YYY | spec-request-changes US-XXX | re-issued US-XXX | logged
- **Security:** yes/no — Lars/Oliver tagged
- **Occurrences:** {date} {environment}
```

### 3. Route to workflow

| Condition | Action |
| --- | --- |
| Maps to a spec in `REVIEW` | Build rework payload → `spec-request-changes US-XXX` — the only status that command accepts |
| Maps to a spec in `TODO` / `PLANNED` | Re-issue the spec through `spec-add` — same code, **no `status` key**, criteria added; `PLANNED` → `/larapilot-plan` again |
| Maps to a spec `IN PROGRESS` | Tell the user work is under way; on consent re-issue the body with the added criteria and carry them into the next task |
| Maps to a spec `DONE` | New fix spec with `**Related:** US-XXX` — a `DONE` spec is never reopened |
| Breaches an NFR target (slow page, failed accessibility check, missed retention) | Fix spec citing `NFR-XXX`; **Demonstrates** uses that row's verifier |
| No matching spec | New fix spec via `spec-add` |
| Critical + Production | Note **`hotfix/US-XXX-short-desc`** branch from `main` (Jack); still create/rework spec |
| Log only | Stop after intake.md entry; suggest re-run when ready |

### 3b. PRD sync (requirement gap only)

When the user chose **Product requirement gap**:

1. Read the parent `FR-XXX` and write the clarification where Round 4 placed it — a **Done means** bullet a tester can verify (“OAuth login succeeds on Safari 17+, macOS and iOS”), an NFR row with target and verifier, or a Domain Model state. Same shape as **Requirement Quality**; never a vague bullet
2. Append **PRD Revision History** row: `larapilot-bug → FR-003 gap | …`
3. `prd-write` + `validate-prd`
4. `prd-impact --ids=FR-XXX` — other open specs citing that FR gain the same criterion through their own status row in **Route to workflow**
5. Still route fix via spec/rework — PRD edit does not replace the fix spec

**Do not** update PRD for routine bugs, regressions, or review rework.

### 4. Fix spec body (new bug)

Reuse the existing **Maintenance** epic from `spec-list` when one exists — create it once, never a new epic per fix (see **Epic consolidation** in shared-runtime).

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
      **Related:** US-YYY (the spec that shipped it, or the earlier fix on a regression)
      **Traces to:** J-XXX · FR-XXX · NFR-XXX
      **Promise broken:** {{FR done-means, criterion, or NFR target — quoted}}

      **User Story**
      As a [persona],
      I want [correct behavior restored],
      so that [impact resolved].

      **Demonstrates**
      Bug no longer reproducible following the steps below; regression test added.

      **Steps to Reproduce**
      1. ...

      **Acceptance Criteria**
      - [ ] Bug fixed — the promise holds again: {{quoted promise, observable}}
      - [ ] Regression test covers the failure and fails without the fix (Anne)
      - [ ] Root cause named in the fix commit; the domain's developer doc updated when behavior changes
      - [ ] No security regression (Lars/Oliver when security-related)
      - [ ] Factory/seeder updated if schema touched
```

Validate → `spec-add` → delete temp file.

### 5. Rework payload (existing spec)

Write `.larapilot/tmp-rework.yaml`:

```yaml
markdown: |
  ## Bug report — {date}
  **Severity:** ...
  **Steps to reproduce:** ...
  **Expected / Actual:** ...
  **Additional acceptance criteria:**
  - [ ] ...
```

`spec-request-changes US-XXX --file=.larapilot/tmp-rework.yaml` → delete temp file. The payload key is `markdown` (or `body`); the command refuses a spec that is not in `REVIEW` — use the re-issue row of **Route to workflow** for the other statuses.

### 6. Next steps

Offer:

- `/larapilot-plan US-XXX` — default for new fix specs
- `/larapilot-implement US-XXX` — only if plan already exists and user wants to skip replanning
- Critical production: remind Jack's **hotfix** Gitflow and Lars security gate before merge to `main`
- `/larapilot-prd` — when the defect exposed a PRD problem wider than one clarification

## Output Boundaries

- Do not implement fixes in this skill
- Do not bypass review — every fix goes spec → plan → implement → review
- Do not duplicate `/larapilot-spec` bootstrap — this skill handles **one bug intake** at a time
- **Do not update the PRD** for routine bugs — per **PRD Living Document**; only clarify parent FR on requirement gap
- Do not reopen a `DONE` spec, and do not send `spec-request-changes` to a spec outside `REVIEW`

## Example

**Invoke:** `/larapilot-bug "SSO login fails on Safari"`

**Context:** B2B app in production; `US-003` (SSO auth) DONE; Chrome OK, Safari fails after OAuth redirect.

**Round 1 (Sophia):** Severity **High**; environment **Production**; security **Unsure** → tag Lars/Oliver.

**Round 2:** Reproducible **Always** (Safari 17+); area **J-001 Sign in** / `FR-003`; promise broken **Nothing written** — no browser list anywhere; maps to **US-003** (`DONE`).

**Round 3:** Path **New fix spec** — `US-003` is `DONE` and stays closed; urgency **Next sprint** (not Critical hotfix).

**Round 4:** **Product requirement gap** — browser support belongs to the compatibility NFR row.

**Log:** append `BUG-YYYYMMDD-sso-safari` to `{paths.support}/intake.md` with reproduce steps.

**Route:** new `US-015` fix spec, `**Related:** US-003`, `**Traces to:** J-001 · FR-003 · NFR-007` (Safari 17+ login, SameSite/cookie check, regression test).

**PRD (gap case):** Mark adds the browser list to `NFR-007` Compatibility and a done-means bullet under `FR-003` + revision history — **not** `FR-012: Fix Safari`.

**Same bug, spec still in `REVIEW`:** `spec-request-changes US-003` with the added criteria sends it back to `TODO`.

**PRD (routine fix):** skip — behaviour already covered; `intake.md` + rework suffice.

**Alternative:** unmapped bug → new `US-XXX` fix spec via `spec-add`; Critical + Production → note `hotfix/*` (Jack).

**Next:** `/larapilot-plan US-015`
