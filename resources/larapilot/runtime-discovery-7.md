Part of this runtime pack. The pack file is an index. Read with the editor file-read tool, never `cat`.

## Requirement Quality _(Tom — the shape of an FR)_

A functional requirement is a promise a spec can be written from and a reviewer can test. One line and a MoSCoW tag is a wish. Every `### FR-XXX` in the PRD carries:

```markdown
### FR-004: Issue an invoice

**MoSCoW:** Must · **Journey:** J-001 · **Persona:** Freelancer
**Actor & trigger:** the freelancer, from a client's page, when the work is done
**Behavior:** creates a Draft invoice with lines and tax per client country; a sequential number is assigned on Send
**Done means:**
- a Sent invoice cannot be edited, only credited
- the number sequence has no gaps per tenant per year
- the client receives the PDF by email within one minute of Send
**Out of this FR:** recurring invoices (FR-009), multi-currency (Future Phases)
**Depends on:** FR-002 (clients), NFR-003 (email deliverability)
**Source:** interview · client-materials/brief.md §3 · prior-art (closest candidate lacks it) · reference-products/{slug}.md
```

Rules:

1. **Done means is verifiable.** Each bullet is something a tester can observe: a state, a number, a timing, a refusal. "Works well", "user-friendly", and "fast" are not done-means — they become NFRs with a target.
2. **One behavior per FR.** When "and" joins two behaviors that could ship separately, split. When it joins two halves of one journey step, keep.
3. **Named actor.** A persona from `## User Personas` or a system actor (scheduler, webhook, admin). "The user" is not an actor.
4. **Out of this FR** stops scope creep in the spec: what a reader might assume is included and is not.
5. **Source** keeps traceability: interview, client-materials section, prior-art candidate, reference product, legacy parity row.
6. **Tom's pass** — before the PRD is written, Tom reads every `Must` and `Should` FR against rules 1–5 and says in chat which ones fail and how he would fix them. Mark decides; the fix lands in the PRD, not in a later spec.

Under **Personal** kind or `effort: ECO`, **Done means** and **Actor & trigger** stay mandatory; **Out of this FR**, **Depends on**, and **Source** may be omitted when empty.

## Non-Functional Requirements _(`## Non-Functional Requirements` — John + Lars + Emma + Violet + Aurora)_

Quality targets the product must meet, each with a measurable value and a way to verify it. Without them, "fast" and "secure" are opinions at review time.

```markdown
| ID | Category | Target | Applies to | Verified by |
| --- | --- | --- | --- | --- |
| NFR-001 | Performance | p95 < 300 ms on list pages at 10k rows per tenant | J-001, J-003 | k6 run in CI (Anne) |
| NFR-002 | Availability | 99.5% monthly · RPO 24h · RTO 4h | all | backup restore drill (Jack) |
| NFR-003 | Security | OWASP ASVS L1 · 2FA for admins · secrets outside the repo | all | Lars gate at ship |
| NFR-004 | Privacy | retention 24 months · export and erasure within 30 days | Client, Invoice | Violet checklist |
| NFR-005 | Accessibility | WCAG 2.2 AA on public and authenticated UI | all UI | Lighthouse + axe (Emma) |
| NFR-006 | Capacity | 200 tenants · 50k invoices/month · 5 GB uploads per tenant | all | Aurora sizing |
| NFR-007 | Compatibility | last two versions of evergreen browsers · mobile Safari | all UI | device matrix (Anne) |
```

Categories to consider every time: performance, availability and recovery, security, privacy and retention, accessibility, capacity and growth, compatibility, observability, localization, operability (deploy frequency, rollback). **Personal** kind: security and accessibility rows at minimum. **Enterprise**: every category has a row. Each NFR names the journeys or entities it applies to, so `larapilot-spec` attaches it as acceptance criteria to the right specs and `larapilot-review` judges against it.

Owners: **John** performance, capacity, observability; **Lars** security; **Violet** privacy and legal; **Emma** accessibility and web performance; **Aurora** capacity against cost; **Jack** availability, recovery, operability; **Anne** the verification column.

## Risks & Assumptions _(`## Risks & Assumptions` — Mark + Jennifer + Tom)_

The goal challenge produces answers the PRD used to lose. They live here, where spec and plan can read them.

```markdown
**Riskiest assumption:** {{from the goal challenge — what the MVP tests first}}
**Kill condition:** {{what would make the user stop}}

### Assumptions
| ID | Assumption | Validated by | If wrong |
| --- | --- | --- | --- |
| A-001 | Freelancers will pay €9/month for invoicing | 20 paid sign-ups in 90 days (Success signal) | per-invoice pricing |

### Risks
| ID | Risk | Likelihood | Impact | Mitigation | Owner |
| --- | --- | --- | --- | --- | --- |

### Open questions
| ID | Question | Blocks | Owner | Needed by |
| --- | --- | --- | --- | --- |
| Q-001 | Which tax regimes at launch? | FR-004 | Client | before /larapilot-spec |

### Not decided
{{every round the user skipped — one line each with what it costs downstream; mirrors the `Not decided` values in MVP Scope and Technical Architecture}}
```

Rules: an FR that depends on an open question cites it (`**Depends on:** Q-001`), and `larapilot-spec` writes the question into that spec's acceptance criteria as a blocker instead of guessing. An open question has an owner and a date, or it is an assumption in disguise. **Not decided** is a list, not a mood: it is the readback's second table.

## Definition of Ready & Readback _(before `prd-write`)_

The PRD is written once, when it is ready. **Tom** runs the checklist in chat — one line per failed item — and the team fixes the content before persisting, never after.

| # | Ready when | Owner |
| --- | --- | --- |
| 1 | The four core rounds are answered or written as `Not decided` with their downstream cost | Mark |
| 2 | Prior Art verdict recorded, or `Not checked` with the reason | Sebastian |
| 3 | Every persona appears in at least one journey; the core journey is marked | Mark |
| 4 | Every `Must` FR has a journey, a named actor, and at least two verifiable done-means bullets | Tom |
| 5 | Every entity named in an FR is in the Domain Model with its states | Mike |
| 6 | NFRs cover at least performance, security, availability, accessibility; every row has a target and a verifier | John + Lars |
| 7 | Riskiest assumption, kill condition, and Success signal are written and consistent with each other | Jennifer |
| 8 | Every open question has an owner and a date; none blocks a `Must` FR without being cited by it | Tom |
| 9 | In Scope / Out of Scope / Future Phases agree with the MoSCoW tags and the Delivery Target | Mark |
| 10 | Technical Architecture records every asked choice (topology, panel, data store, local dev, deploy, ops) — none assumed | John + Jack |

**Readback.** Before `prd-write`, **Mark** reads back in chat, in the user's language, at most twelve lines: the pitch in one sentence, the core journey, Delivery Target and Business Model, the Prior Art verdict, the three to five `Must` FRs that define the release, the riskiest assumption and the Success signal, the `Not decided` list, and the three most consequential technical choices. Then one **AskQuestion**: `Write the PRD` | `Revise` (follow up in chat on what changes). Only after `Write the PRD` does the skill persist, validate, sync choices, and log the decisions. The readback is the last cheap moment to catch a wrong assumption, not a gate: a user who wants the PRD now gets it.

`validate-prd` reports the recommended sections (`## User Journeys`, `## Domain Model`, `## Non-Functional Requirements`, `## Risks & Assumptions`) and any `### FR-XXX` without a `**MoSCoW:**` line as **warnings**. They do not fail validation — older PRDs stay valid — but a fresh inception clears every warning before it finishes. Two more warnings guard the ids: `PRD_DUPLICATE_ID` (an id defined twice) and `PRD_DANGLING_REFERENCE` (an id cited and never defined).

After the PRD is written, it changes through `larapilot-feature` (a new capability), `larapilot-bug` (a requirement gap a defect revealed), or `larapilot-prd` (everything else: sharpen, re-scope, re-model, re-decide, upgrade an older PRD) — see **PRD Revision** in `runtime-ops.md`.
