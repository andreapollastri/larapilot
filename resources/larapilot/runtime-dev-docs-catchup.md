## First-change catch-up _(mandatory)_

A project that has code but no domain docs is **brought level on the first change**, not gradually. The first spec, fix, or hotfix that touches the codebase documents **the whole project** — every domain that already exists — and only then its own change. There is no "we will fill the rest in later": the rest never arrives, and a half-documented folder is read as a complete one.

The `context` envelope reports the state under **`data.dev_docs`**:

| Field | Meaning |
| --- | --- |
| `path` | The folder, relative to the project root |
| `documented` | `false` while no domain file exists — **the catch-up trigger** |
| `count` | Domain files present (`README.md` and `TEMPLATE.md` do not count) |
| `domains` | The slugs already documented |

### Trigger

Run the catch-up when `data.dev_docs.documented` is `false` **and** the codebase already has domains to describe — models, routes, services, commands, jobs beyond a bare Laravel skeleton. A greenfield project on its first spec has nothing to catch up on: the set is empty, the rule costs nothing, and the spec documents only what it creates.

Also run it when `documented` is `true` but domains are **plainly missing** — the folder was started and then abandoned. Same procedure, restricted to the gaps.

### Procedure

1. **Inventory the domains** before touching code. Derive them from `php artisan larapilot:spec-list`, `.larapilot/research/codebase-analysis.md` when a `/larapilot-adopt` run produced one, and the codebase itself — models and their relations, route groups, service/action namespaces, queued jobs, Artisan commands, external integrations. Group into **domains**, not classes: one file per bounded area, an entity only when it carries behavior beyond CRUD.
2. **Announce the scope** in one line — how many domains were found, that they will be written before the spec's own work — and continue. Do not AskQuestion: the catch-up is not optional, and a list of twelve domains is not a decision the user needs to arbitrate.
3. **Write one file per domain** from `TEMPLATE.md`, **English**, reading the code rather than guessing from names. Mark anything inferred rather than verified with `<!-- TODO: verify -->` — an honest gap beats a confident invention.
4. **Fill `Architectural choices` from the evidence available** — git history, the PRD, `.larapilot/decisions.yaml`, the plans under `.larapilot/plans/`, the review artifacts. Where the reasoning is genuinely unrecoverable, write `<!-- TODO: verify -->` and state the choice without inventing a motive for it.
5. **Write the `README.md` index** with one row per domain.
6. **Commit the catch-up on its own**, before the spec's first task commit: `docs(US-XXX): bring developer domain docs level`. It is not part of any task's diff — mixing a twelve-file backfill into a feature commit makes both unreviewable.
7. **Then run the spec normally.** Its own domain updates follow the standard rule and ride with their task commits.

### Cost

The catch-up is proportional to the codebase, and on a large brownfield project it is the most expensive thing the session does. Zoey posts a context estimate before starting it. Under **`ECO`** it still runs — terse sections, no diagrams, `<!-- TODO: verify -->` used liberally rather than reverse-engineering every decision. It is never deferred to a later spec, because "later" is what produced the empty folder.

`/larapilot-adopt` performs the same catch-up at the end of onboarding, so a project adopted through it arrives at its first spec already level.
