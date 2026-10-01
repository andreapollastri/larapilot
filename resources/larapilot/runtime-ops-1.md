## PRD Living Document _(selective updates — not every change)_

The PRD is the **product contract** — what the product promises. It is **not** a maintenance log. Backlog specs, `{paths.support}/intake.md`, and the app `CHANGELOG.md` carry operational history.

### Two layers of truth

| Layer | Artifact | Updates when |
| --- | --- | --- |
| **Product contract** | `{paths.prd}` | Scope, FRs, MoSCoW, in/out of scope, architecture commitments change |
| **Delivery & ops** | `backlog.yaml`, specs, `intake.md`, code `CHANGELOG.md` | Every feature, bug, rework, hotfix |

### When to update the PRD _(Mark owns)_

| Trigger | Update | Example |
| --- | --- | --- |
| **New capability** | New `### FR-XXX` + MoSCoW | Export PDF fatture → `FR-011` |
| **Existing FR strengthened** | Change MoSCoW on `FR-XXX`; optional bullet under that FR | `Could` → `Must` for compliance |
| **Scope deferral / removal** | `### Out of Scope` or `### Future Phases` | PDF deferred to V2 |
| **Architecture commitment** | `## Technical Architecture` | New required integration, tenancy pattern |
| **Legacy parity change** | PRD + `{paths.research}/legacy-parity.md` | New module in port scope |
| **Bug reveals requirement gap** | Clarify **parent FR** or NFR — **not** a "fix FR" | Under `FR-003`: SSO must work on Safari 17+ |
| **New journey, entity, or state** | `## User Journeys` / `## Domain Model` row | Refund flow adds `Credit note` entity |
| **Quality target changes** | `## Non-Functional Requirements` row (target + verifier) | p95 tightened to 200 ms for J-001 |
| **Open question answered** | Resolve the `Q-XXX` row in `## Risks & Assumptions`; cite the decision id | Tax regimes at launch: IT + DE (D-014) |
| **Revision — no new capability, no defect** | `/larapilot-prd` — sharpen, re-scope, re-model, re-decide, upgrade (**PRD Revision**) | `Should` → `Won't`; "fast" → p95 < 300 ms |
| **Vision pivot** | `/larapilot-inception` with the current PRD as input | New product direction |

### When **not** to update the PRD

| Trigger | Route instead |
| --- | --- |
| Routine bug (restores expected behaviour) | Spec fix / `spec-request-changes` + `intake.md` |
| Review rework (implementation gap) | `spec-request-changes` only |
| Refactor, perf, tech debt (no user-facing change) | Spec or plan only |
| Hotfix production | Spec + `hotfix/*`; app `CHANGELOG.md` |
| Regression on existing AC | Rework spec; regression test |

**Never** add `FR-XXX: Fix …` for bugs — fixes trace to existing FRs via spec **Type: Fix**.

### Decision gate _(when uncertain)_

**Mark** or **Sophia** asks via **AskQuestion** (one round, skippable):

- **Product requirement gap** — PRD should record this → update PRD (clarify FR / new FR)
- **Implementation fix** — behaviour already implied by PRD/spec → spec/rework only
- **Unsure** — default to **spec only**; note in chat to revisit the PRD after the fix if the gap persists

### How to apply a PRD update

1. Read the current PRD from `{paths.prd}`.
2. Apply the **minimal** edit — new/changed `FR-XXX`, `## MVP Scope`, or `## Technical Architecture` bullet.
3. Append one row to **`## PRD Revision History`** (create the section on the first post-inception edit):

```markdown
## PRD Revision History

| Date | Trigger | Summary |
| --- | --- | --- |
| {{DATE}} | larapilot-feature US-011 | Added FR-011 Export PDF (MoSCoW: Should) |
| {{DATE}} | larapilot-bug → FR-003 gap | SSO must work on Safari 17+ (macOS/iOS) |
```

4. `php artisan larapilot:prd-write` + `php artisan larapilot:validate-prd` (max 3 attempts).

### Per-skill PRD rules

| Skill | PRD |
| --- | --- |
| **`larapilot-inception`** | Create / full rewrite |
| **`larapilot-feature`** | Update when scope changes (new FR, MoSCoW, in/out of scope) |
| **`larapilot-bug`** | **No** by default; update only on **requirement gap** (clarify parent FR) |
| **`larapilot-spec`** | Read-only — trace specs to FRs; suggest `larapilot-feature` for scoped additions |
| **`larapilot-plan` / `implement` / `review`** | Read-only — **never** `prd-write` |
| **`spec-request-changes`** | **Never** — rework lives in spec + plan |

Ownership: **Mark** owns PRD scope edits; **Sophia** flags requirement gaps from bugs; **Tom** ensures spec AC align with FRs after PRD edits.
