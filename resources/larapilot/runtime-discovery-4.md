## Delivery Target

Larapilot uses **MVP thinking** as a default lens — smallest valuable slice, clear trade-offs, defer what is not essential — but **does not lock every project to an MVP**.

During **`larapilot-inception`**, Mark asks the user to choose a **delivery target** (via **AskQuestion**, after **Project Kind** — branching rules in `runtime-discovery-2.md`). Persist in the PRD under `## MVP Scope` as:

```markdown
**Delivery Target:** MVP | V1 Complete | Full Product | Enterprise
```

**Lucille** asks in the same early rounds (skippable) whether there are **delivery deadlines** or fixed milestones (go-live, demo, compliance date). Persist via `php artisan larapilot:schedule-set` into `{paths.schedule}` and mirror a one-line summary in the PRD (`**Deadlines:** …` under `## MVP Scope` when known). See **Usage Ledger & Schedule** in `runtime-ops.md`.

| Target | Meaning | Backlog & delivery behavior |
| --- | --- | --- |
| **MVP** | Smallest demonstrable slice to validate the core hypothesis | `larapilot-spec` creates a lean backlog; defer non-essential FRs explicitly |
| **V1 Complete** | Polished first release: core journey + essential secondary features | Broader backlog than MVP; still bounded to a shippable V1 |
| **Full Product** | Entire vision from `## Functional Requirements` — no artificial cuts | `larapilot-spec` covers all FRs; spec/epic count follows `settings.backlog` (journey-level specs citing multiple FRs under `LEAN`/`STANDARD`) |
| **Enterprise** | Full product plus compliance, integrations, scale, and operational readiness | Same breadth as Full Product, with enterprise-grade NFRs and launch criteria |

Rules for all skills:

1. **Read the delivery target from the PRD** (`paths.prd`) before scoping work. If missing, infer from `## MVP Scope` content or ask once.
2. **Never downgrade** the user's chosen target to MVP unless they explicitly change it.
3. **MVP is a method, not a ceiling** — trade-off framing stays useful at every level; scope depth follows the target.
4. The PRD section stays named `## MVP Scope` for validator compatibility; its body reflects the chosen target (In Scope / Out of Scope / Future Phases).

## MoSCoW Prioritization _(Functional Requirements)_

Every functional requirement in the PRD carries a **MoSCoW** priority — the per-FR scope lens that complements **Delivery Target** (macro) and backlog **Priority** (implementation order).

During **`larapilot-inception`**, **Mark** assigns MoSCoW while drafting `## Functional Requirements` (negotiate trade-offs in discovery when the target is **MVP** or **V1 Complete**). Persist on each FR as:

```markdown
### FR-001: {{REQUIREMENT}}

**MoSCoW:** Must | Should | Could | Won't
```

Use the English labels **Must**, **Should**, **Could**, **Won't** in every locale — MoSCoW is a standard acronym; requirement text stays in the detected artifact language.

| Label | Meaning |
| --- | --- |
| **Must** | Non-negotiable for the chosen delivery target — launch fails without it |
| **Should** | Important but not vital for the current target — include when target is **V1 Complete** or broader |
| **Could** | Desirable if time/budget allows — defer unless target is **Full Product** or **Enterprise** |
| **Won't** | Explicitly out of this release — document in `### Out of Scope`, not cancelled forever |

When to tag: **all projects** — every `### FR-XXX` gets a `**MoSCoW:**` line. **Personal** — lean tagging is fine (mostly Must and Won't). **MVP / V1 Complete** — Mark must negotiate Must vs Should vs Could in the interview. **Full Product / Enterprise** — default surviving FRs to **Must**; use **Could** only for genuinely optional polish; **Won't** only with user consent.

Alignment with `## MVP Scope`: **Must** FRs → reflected in `### In Scope`; **Should**/**Could** FRs deferred under **MVP** → listed in `### Future Phases` (not silently dropped); **Won't** FRs → listed in `### Out of Scope` with brief rationale.

### Backlog mapping (`larapilot-spec`)

When bootstrapping from the PRD, read each FR's MoSCoW tag (fallback: infer from delivery target and `## MVP Scope` when a tag is missing — legacy PRDs).

| MoSCoW | MVP | V1 Complete | Full Product / Enterprise |
| --- | --- | --- | --- |
| **Must** | Create spec | Create spec | Create spec |
| **Should** | Defer → Future Phases | Create spec | Create spec |
| **Could** | Defer → Future Phases | Defer → Future Phases | Create spec |
| **Won't** | Skip — verify `### Out of Scope` | Skip | Skip |

Default backlog **Priority** from MoSCoW when creating specs: **Must** → `HIGH` (compliance/security-critical FRs → `CRITICAL`); **Should** → `MEDIUM`; **Could** → `LOW`. Tom/Mark may override per spec.

Downstream: **`larapilot-spec`** — primary input for bootstrap/deferral; never create specs for **Won't** FRs. **`larapilot-plan`** — plans only exist for specced FRs. **`larapilot-review`** — judge delivered scope against FR MoSCoW + delivery target.

Ownership: **Mark** assigns MoSCoW at inception and reconciles tags when extending the backlog; **Tom** preserves FR traceability in spec bodies.

## Budget Sensitivity

Budget is a default lens, not a mandatory gate. During **`larapilot-inception`**, Aurora asks the user (via **AskQuestion**, in the same round as the delivery target or right after it) whether budget should actively drive decisions — **except** for **Personal** projects, where **`Relaxed`** is the default and Aurora only asks if the user wants **Tracked**. Persist in the PRD under `## Technical Architecture` as:

```markdown
**Budget Sensitivity:** Tracked | Relaxed
```

| Mode | Meaning | Business-lens behavior (Aurora, Benjamin, Jennifer) |
| --- | --- | --- |
| **Tracked** _(default)_ | Budget is an active constraint | Aurora sizes infra and services against the stated budget; cost concerns can reshape or block technical choices |
| **Relaxed** | The user opted out of budget evaluation | Validation is **loosened, never removed**: no cost-based vetoes, no budget interrogation — but business figures still flag order-of-magnitude cost risks, vendor lock-in, and choices that are expensive to reverse, as short advisory notes (1–2 lines) |

Rules for all skills:

1. **Read the budget sensitivity from the PRD** (`paths.prd`) before making cost-driven recommendations. If missing, treat it as **Tracked**.
2. In **Relaxed** mode, never drop the business lens entirely — compress it to concise advisories and move on without asking budget questions.
3. The user can switch mode at any time; update the PRD line when they do.

Security budget, and SaaS pricing with infra sizing: `runtime-discovery-8.md`.
