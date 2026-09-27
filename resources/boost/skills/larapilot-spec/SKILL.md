---
name: larapilot-spec
description: "Creates or extends the backlog from a PRD. Use for a backlog, epics, or user stories. Italian: creare il backlog, user story, specifiche."
---

# Larapilot — Spec / Backlog

You create and extend the Larapilot backlog. Each spec body is a user story.

## Shared Runtime

Obey **Read protocol** in `.larapilot/shared-runtime.md`: file-read tool only, never `cat` / `head` / `sed`. A truncated preview is a failed load — read the remainder before any other step. Then read only the section files that index lists for this skill.

Read `.larapilot/shared-runtime.md` (core), then `.larapilot/runtime-discovery.md` (**MoSCoW Prioritization**, **Delivery Target**, backlog mapping, **Domain Model & User Journeys**, **Requirement Quality**, **Non-Functional Requirements**, **Risks & Assumptions**, **Prior Art & Open-Source Alternatives**) and `.larapilot/runtime-ops.md` (**PRD Living Document**).

When `data.settings.decision_log` is `YES` (default), journal material user choices with `php artisan larapilot:decision-log` and run `php artisan larapilot:decision-check` before reversing a previously recorded choice — contract: **Decision journal (`settings.decision_log`)** in `shared-runtime.md`.

## PRD

Read the PRD — **do not write** to it. For scoped product additions with interview, direct the user to `/larapilot-feature`; for a change to what the PRD already says (priorities, scope, wording, targets, missing sections), to `/larapilot-prd` (see **PRD Living Document** and **PRD Revision** in `runtime-ops.md`).

**Coverage check** — after a bootstrap or an extend, run `php artisan larapilot:prd-impact` (no `--ids`): `data.uncovered_must` lists the `Must` FRs no spec cites. Each one gets a spec, or a line in chat saying why it waits. `data.untraced` journeys and NFRs are reported the same way.

## Output Economy

**Moderate** — see `larapilot-spec` in shared-runtime. Chat: brief announce of bootstrap vs extend and priority choices. Spec bodies: full user story and acceptance criteria in the backlog file.

## The Team (this phase)

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — sharpens user intent, output economy, sub-agent orchestration, session/credit risk *(every skill)* |
| 🔎 **Tom** | Requirements Analyst — acceptance criteria, edge cases, spec quality |
| 💎 **Mark** | Product Manager — prioritization and alignment with PRD delivery target |
| 🔄 **Sabrine** | Legacy Porting Specialist — parity specs, scraping, DB/assets porting *(legacy projects)* |
| ✍️ **Marika** | Copywriter — content/copy user stories when PRD defines copy scope |
| 🎧 **Sophia** | Support Manager — triages post-launch bugs into backlog specs *(maintenance mode)* |
| 🌍 **Emily** | Translator — i18n user stories when PRD defines multi-market scope |
| 📝 **Albert** | Tech Writer — **baseline technical docs always**; proposes extended documentation scope per spec via AskQuestion |

## Config & CLI

1. `php artisan larapilot:config-show`
2. `php artisan larapilot:spec-list`
3. `php artisan larapilot:validate-spec --file=...`
4. `php artisan larapilot:spec-add --file=...`

## Routing

- If `spec-list` returns empty `data.summary.codes` → **bootstrap backlog** from PRD
- If backlog exists → **extend** with only the requested specs
- For **one new feature/enhancement** with interactive discovery → prefer `/larapilot-feature`
- For **bug reports** → prefer `/larapilot-bug` (Sophia triage)
- For **one request that may be either** → prefer `/larapilot-triage`

Read PRD from `data.paths.prd`. If missing, ask for path, content, or suggest `larapilot-inception`.

Read **`data.paths.client_materials`**, **`data.paths.legacy`**, and **`data.paths.research`** when present (see **Client Materials**, **Legacy Rewrite & Porting**, and **Reference Products & Sebastian Deepsearch** in shared-runtime). Trace specs to client doc sections and legacy parity rows; never ignore these inputs.

Read the **delivery target** from `## MVP Scope` (see Delivery Target in shared-runtime). Scope the backlog to match — do not cap at MVP when the PRD says `V1 Complete`, `Full Product`, or `Enterprise`.

Read **MoSCoW** on each `### FR-XXX` in `## Functional Requirements` (see **MoSCoW Prioritization** in shared-runtime). MoSCoW is the primary input for bootstrap/deferral; fall back to delivery target + `## MVP Scope` only when a tag is missing (legacy PRDs).

Read the PRD sections a current inception writes — and say in one line when one is missing, because the backlog will be weaker for it:

| PRD section | What the backlog takes from it |
| --- | --- |
| `## User Journeys` (`### J-XXX`) | The unit of a spec under `LEAN`/`STANDARD`: one spec per journey citing its FRs; the core journey becomes the first spec. **Demonstrates** is the journey's success end-state; failure modes become error-case criteria |
| `## Domain Model` | The nouns and states used in titles, stories, and acceptance criteria — never a synonym the PRD does not use |
| FR **Done means** bullets | The seed of the acceptance criteria: each bullet becomes at least one `- [ ]`, then Tom adds the error and edge cases. **Out of this FR** becomes an explicit non-goal line when a reader could assume otherwise |
| `## Non-Functional Requirements` | Every NFR whose **Applies to** names the journey or an entity the spec touches becomes an acceptance criterion with its target (p95, WCAG level, retention) |
| `## Risks & Assumptions` → open questions | An FR whose `**Depends on:**` cites `Q-XXX` gets that question written in the spec as `**Open questions**` — the spec is still created, the criterion reads "blocked by Q-XXX until …"; never guess the answer |
| `**Prior Art:**` verdict | `Integrate as dependency` → glue and integration specs first, custom FRs after; `Build anyway` → the differentiator FRs (the ones the closest candidate lacks) lead the backlog |

Read **`data.settings.backlog`** from `config-show` (see **Backlog granularity** in shared-runtime): it controls how finely scope is sliced into specs and epics (`LEAN` | `STANDARD` | `GRANULAR`). Under `LEAN`/`STANDARD` (default), prefer journey-level specs that cite multiple related `FR-XXX`; technical seams become plan tasks. Reuse existing epics from `spec-list` before proposing new ones.

Read **Project Kind** from `## MVP Scope` (see Project Kind in shared-runtime) and adjust backlog depth:

| Project Kind | Backlog behavior |
| --- | --- |
| **Personal** | Leanest: one spec per core journey; defer polish and secondary FRs |
| **Website** | Early specs for SEO/discoverability (`robots.txt`, sitemap, llms), content routes, and brand assets; type-specific specs (e.g. catalog/checkout for **E-commerce**) |
| **Application** | Full FR coverage per delivery target |
| **Package** | Package-surface first: public API, service provider/config, tests+CI matrix, security, semver/release, docs/minisite, consumer install/upgrade |

## Bootstrap backlog (from PRD)

Apply **MoSCoW** first (see shared-runtime), then delivery target:

| MoSCoW | MVP | V1 Complete | Full Product / Enterprise |
| --- | --- | --- | --- |
| **Must** | Spec | Spec | Spec |
| **Should** | Defer | Spec | Spec |
| **Could** | Defer | Defer | Spec |
| **Won't** | Skip | Skip | Skip |

| Delivery target | Backlog depth (when MoSCoW tags are missing — legacy PRDs) |
| --- | --- |
| **MVP** | Lean: one spec per core user journey; defer secondary FRs to Future Phases |
| **V1 Complete** | Core + essential secondary features; bounded but production-ready |
| **Full Product** | Cover every FR in `## Functional Requirements` — slice per `settings.backlog`: journey-level specs citing multiple related FRs (`LEAN`/`STANDARD`, default) or one spec per FR (`GRANULAR`) |
| **Enterprise** | Full Product breadth + compliance, integrations, observability, and ops specs (same `settings.backlog` slicing) |

Default spec **priority** from MoSCoW: **Must** → `HIGH` (compliance/security FRs → `CRITICAL`); **Should** → `MEDIUM`; **Could** → `LOW`. Cite `FR-XXX` and MoSCoW in the spec body when tracing to the PRD.

When extending an existing backlog, new specs must stay consistent with the PRD delivery target and FR MoSCoW tags.

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
- [ ] [Happy path — from the FR's Done means]
- [ ] [Error case — from the journey's failure modes]
- [ ] [Edge case]
- [ ] [NFR target when one applies — e.g. p95 < 300 ms, WCAG 2.2 AA]

**Open questions** _(only when an FR cites Q-XXX)_
- Q-XXX — blocks {{criterion}} until {{owner}} answers by {{date}}
```

Add `**Traces to:** J-XXX · FR-XXX (MoSCoW) · NFR-XXX` under the header line whenever the PRD carries those ids, so plan, implement, and review can walk back to the promise.

## Payload Shape

Write payload to `.larapilot/tmp-payload-specs.yaml`:

```yaml
specs:
  - code: US-001
    title: "..."
    epic:
      code: EP-001
      title: "Checkout & payments"
      objective: "Shoppers can pay and receive order confirmation"
      deadline: "2026-09-15"   # required when the project has delivery dates
    priority: HIGH
    points: 3
    status: TODO
    body: |
      ...markdown user story...
```

**Story points (Mark + Lucille):** assign `points` for relative size. Per-task `estimate_hours` belong on the plan (`larapilot-plan`) — they feed Lucille’s Gantt and schedule criticality, not the Kanban board.

**Epics (Mark + Lucille):** group related US specs under `EP-XXX` with a clear **objective** and, when dates exist, an epic **deadline**. Reuse epics from `spec-list` before creating new ones. Lucille validates that epic deadlines align with `schedule-set` milestones and remaining story points.

Validate first, then `spec-add`. Delete temp file after CLI exits.

## Laravel Notes

- One spec = one demonstrable user journey/capability. Laravel seams (models/migrations, routes/controllers, policies, Livewire/Inertia UI, API resources) become **plan tasks** inside the spec via `/larapilot-plan` — not separate specs. Split a seam into its own spec only under `settings.backlog: GRANULAR`, or when it is independently demonstrable to a user *and* ships separately
- Keep specs INVEST-compliant and independently demonstrable
- **Tom's quality bar:** every criterion is observable (a state, a number, a timing, a refusal); a criterion that restates the title is not a criterion. Use the Domain Model's names and states verbatim
- Use Boost `Application Info` to align specs with installed packages (Livewire, Inertia, Pest, etc.)
- When the PRD includes **admin/control panel** or authenticated dashboard features, scope those specs to the panel route recorded in the PRD: one spec per **functional admin area** (panel setup + related Filament resources / Starter Kit pages as plan tasks) by default; one spec per entity resource/page only under `settings.backlog: GRANULAR`. Stack follows the PRD choice: **Filament** when Filament was chosen; **Starter Kit** (dashboard, settings, auth layouts, Inertia/Livewire pages) when a [Laravel Starter Kit](https://laravel.com/starter-kits) variant was chosen; or standard Laravel (routes/controllers, Livewire/Inertia UI) for a custom panel. If the PRD does not record the choice, **ask the user** (Filament vs Starter Kit vs custom) per the Vendor & Package Policy in shared-runtime — recommend the best fit for the case and the option closest to the project mockups
- Bootstrap / README specs honor the **local dev method** recorded in the PRD (Sail scaffold, Herd docs, generic `php artisan` when not defined yet, or other named stack). If the PRD omits it, **ask the user** per Local development environment in shared-runtime — never assume Sail
- Infra / deploy specs honor **deploy platform**, **edge/CDN/WAF**, and **cloud** recorded in the PRD. If any is missing, **ask the user** per Infrastructure & Cloud in shared-runtime — never assume Cipi, Cloudflare, or AWS; recommend Cloudflare (public edge) and AWS (compute/data) only when feasible
- When the PRD includes **competitor data porting** FRs (Sebastian's import/export integrations), keep them as first-class specs — importers from rival products and lock-in-free export are product features, not technical chores
- **Legacy rewrite/port:** when `{paths.legacy}` or PRD **Project Origin** is legacy, bootstrap **parity and data-migration specs first** — one spec per legacy module/journey from `{paths.research}/legacy-parity.md` (merge small closely-related modules into one spec under `settings.backlog: LEAN`); acceptance criteria cite legacy behavior and migration verification (Anne)
- **Reference products:** when `{paths.research}/reference-products/` exists, create specs for adopted features traced to deepsearch reports
- **Sophia (maintenance mode):** when routing bugs from `{paths.support}/intake.md`, prefer `/larapilot-bug` for interactive triage; otherwise create focused fix specs with reproduce steps, severity, and affected release; security bugs tag Lars/Oliver in spec body
- **Emily:** when PRD defines multi-country scope, one i18n spec covering the market rollout with per-locale work (translations, currency, timezone, localized legal pages) as plan tasks; split specs per locale/market only under `settings.backlog: GRANULAR`
- **Albert:** every project assumes **baseline technical documentation** (README, architecture notes, API docs when routes exist). When adding specs for human approval, **Albert proposes via AskQuestion** whether the spec needs **extended documentation** (OpenAPI delta, diagrams, runbook section, **PDF client tutorial chapter**) beyond the baseline — record the choice in the spec body or PRD
