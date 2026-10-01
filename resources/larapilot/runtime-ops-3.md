## PRD Revision _(`larapilot-prd` — changing the PRD when it is neither a bug nor a feature)_

The PRD changes for three reasons. A **defect** shows a promise was not kept (`larapilot-bug`). A **new capability** adds a promise (`larapilot-feature`). Everything else is a **revision**: the product promises the same things better, fewer things, in a different order, or under different decisions. Revisions go through `larapilot-prd`.

Editing `PRD.md` by hand is allowed, but it skips the history row, the validator, the dashboard snapshot, and the backlog check. Run `larapilot-prd` afterwards and say the file was edited by hand: it reconciles all four from the git diff.

### Revision kinds

| Kind | What changes | Examples | Backlog impact | Owner |
| --- | --- | --- | --- | --- |
| **Editorial** | Wording only — no promise moves | Typos, clearer sentences, a glossary term, a translated heading | None | Marika + Mark |
| **Sharpen** | A promise becomes testable or complete | Done-means added to an FR, the actor named, an NFR given a target and a verifier, an open question answered, a `Not decided` settled | Criteria added to open specs | Tom |
| **Re-scope** | What is in, what is out, and when | MoSCoW change, In Scope ↔ Future Phases, Delivery Target change, an FR retired | Specs created, deferred, or deleted | Mark |
| **Re-model** | Nouns and paths | Entity renamed, state added, journey split or merged, persona added | Specs that cite the old names | Mike + Mark |
| **Re-decide** | A recorded decision | Business Model, ops owner, support window, topology, panel, data store, prior-art verdict | Architecture and infra specs; Economics refresh | John + Jack + Aurora |
| **Upgrade** | The PRD's shape, not its promises | An older PRD gains journeys, domain model, NFRs, risks, and the FR shape — clears `validate-prd` warnings | None while promises are unchanged | Tom + Mark |
| **Pivot** | Vision, target user, or core journey | New audience, new core value | The whole backlog | `/larapilot-inception`, current PRD as input |

One request can carry several kinds: name each, treat the heaviest first. When the request hides a new capability or a defect, hand that part to `larapilot-feature` or `larapilot-bug` and keep the revision here.

### Identifier stability

1. **Never renumber, never reuse.** `FR-`, `J-`, `NFR-`, `Q-`, and `A-` ids are permanent. Specs, plans, commits, and developer docs cite them.
2. **Retire, do not delete.** A dropped FR keeps its heading with `**MoSCoW:** Won't` and a `**Retired:** {date} — {reason}` line, and is listed in `### Out of Scope`. A dropped journey keeps its heading with the same `**Retired:**` line; a dropped NFR or question row keeps its id with `Retired` in place of its content.
3. **Split keeps the parent.** Splitting `FR-004` creates `FR-012` and `FR-013`; `FR-004` stays with `**Superseded by:** FR-012, FR-013`.
4. **Merge keeps both.** The survivor cites `**Merges:** FR-007`; `FR-007` is retired with `**Superseded by:** FR-003`.
5. **Rename everywhere.** An entity or state renamed in `## Domain Model` is renamed in every journey, FR, and NFR in the same revision; the glossary records the old term as `formerly`.

`validate-prd` reports `PRD_DUPLICATE_ID` and `PRD_DANGLING_REFERENCE` as warnings. A revision leaves neither behind. Ids named in `## PRD Revision History` are never counted as dangling.

### Impact on the backlog

Run `php artisan larapilot:prd-impact --ids=FR-004,J-001` with every id the revision touches. It returns the specs whose title or body cites those ids, each with its status, an `action`, and a `hint`. Without `--ids` it traces the whole PRD: every FR, journey, and NFR with the specs that cite it, the ids no spec covers (`untraced`), and the `Must` FRs among them (`uncovered_must`).

| Spec status | `action` | How |
| --- | --- | --- |
| `TODO` | `update_spec` | Re-issue the spec through `spec-add` with the same code and **no `status` key** — the body is replaced, the status kept |
| `PLANNED` | `update_and_replan` | Same re-issue, then `/larapilot-plan US-XXX` again — the plan was written against the old promise |
| `IN PROGRESS` | `coordinate` | Work is under way: tell the user, and on consent re-issue the body and carry the delta into the next task. Never silently |
| `REVIEW` | `rework` | `spec-request-changes US-XXX` with the PRD delta as feedback |
| `DONE` | `new_spec` | It shipped against the old promise and is never reopened. A changed promise → `/larapilot-feature` as a change request; a promise the code never met → `/larapilot-bug` |

`spec-request-changes` accepts a spec in `REVIEW` only; every other status follows its own row. New `Must` or `Should` FRs the Delivery Target allows and no spec covers → `/larapilot-spec` to extend. Retired FRs → `spec-delete` for `TODO` specs only, and only on consent.

### Procedure

1. **Read narrowly.** `validate-prd` first — its warnings are the upgrade to-do list — then only the PRD sections the request touches.
2. **Name the kind** in one line, with the ids involved.
3. **Interview in proportion.** Editorial: none. Sharpen and Upgrade: Tom asks only what cannot be derived, and never invents a target or a done-means to fill a cell — an unknown is written as an open question with an owner. Re-scope, Re-model, Re-decide: the owning persona says what the change implies and what it costs, then **AskQuestion** for the fixed choices. `decision-check` runs before any recorded decision is reversed.
4. **Impact** — `prd-impact`, then the table above.
5. **Readback of the delta** — before and after for each changed item, one line each, then the impact rows. One **AskQuestion**: `Apply` | `Revise` | `Cancel`.
6. **Apply** — the minimal edit; one **PRD Revision History** row (`| {date} | larapilot-prd — {kind} | {summary with ids} |`); `prd-write`; `validate-prd`; `choices-set --from-prd` when a scraped line changed; `decision-log` for every durable choice.
7. **Align the backlog** per the table, in the same session when the user agrees, otherwise listed as next steps with the exact command for each spec.

### What a revision never does

- Add a capability that has no spec — a new promise goes through `/larapilot-feature` or `/larapilot-spec`.
- Record a fix as an FR.
- Rewrite history — revision rows are appended, never edited.
- Touch code, plans, or developer domain docs — those follow the specs the revision produces.
- Lower a security, privacy, or accessibility NFR without **Lars** or **Violet** stating the consequence in the readback.
