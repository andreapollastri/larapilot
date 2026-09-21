# Larapilot Runtime — Developer Domain Docs

Phase pack for **`larapilot-implement`** and for every skill that changes code or reviews it. Read `.larapilot/shared-runtime.md` (core) first.

Developer domain docs are **always on**. There is no setting to enable them and no effort level that switches them off — including **`ECO`**, which shortens the prose but never skips the file. They are the one place where the *reasoning* behind the implementation survives the session that produced it.

## What they are

A Markdown file per **domain / entity / feature** under **`paths.dev_docs`** (default `.larapilot/docs/devs/`), written **for the engineers who will maintain the code**:

| Layer | The file answers |
| --- | --- |
| **Functional** | What the domain does, and how it behaves step by step at runtime — including the failure paths |
| **Technical** | Which models, services, actions, jobs, events, routes, commands, config keys, and tests make it up, and where they live |
| **Architectural** | What was chosen, what was rejected, and **why** — so the next developer does not undo a constraint by accident |
| **Decisional** | The invariants and trade-offs that keep the system running, and what breaks when they are violated |

They are **not** the PRD (product contract, client language), **not** the quote (commercial, client language), and **not** `_project_docs/` (opt-in handbook for the whole project, mixed technical/functional audience). Those may repeat facts; this folder is the only one that carries implementation rationale.

## Hard rules

1. **English, always.** Regardless of the PRD language, the user's language, or the conversation language. These files outlive the team that wrote them.
2. **One file per domain**, `kebab-case.md` (`billing.md`, `user-authentication.md`, `webhook-ingestion.md`). An **entity** gets its own file only when it carries behavior beyond plain CRUD; otherwise it is a section inside its domain file. Do not create one file per class — that is a code listing, not documentation.
3. **Updated in the same spec that changes the behavior.** Never deferred to a follow-up spec, never "at ship". A task is not `task-done` while the domain doc still describes the old behavior.
4. **Fixed section skeleton** — from `TEMPLATE.md` in the folder — so files stay diffable and readers know where to look: `Purpose`, `Functional flow`, `Technical design`, `Architectural choices`, `Key decisions & invariants`, `Extension points & gotchas`, `Related`. Plus the metadata table (`Status`, `Introduced by`, `Last updated by`, `Owner persona`).
5. **Index stays current** — `README.md` in the folder lists every domain with a one-line purpose and the last spec that touched it.
6. **No secrets, no user-specific absolute paths.** Env var **names** only. These files are committed.
7. **Rewrite, do not append.** The folder holds the current truth of the system, not a changelog — history belongs to Git and to `CHANGELOG.md`. Delete or mark `Status: Deprecated` when a domain is removed.

## Write contract _(Albert owns; Alex/Joe supply the mechanics)_

At **Phase 0** of `larapilot-implement`, check `data.dev_docs.documented` first — when it is `false` on a codebase that already has domains, run the **First-change catch-up** below before any task starts.

Then, during each task, once the code and tests are verified and **before the task commit**:

1. Read `paths.dev_docs` from `config-show`; create the folder if the workspace predates it.
2. Determine which domains the task touched. One spec may touch several; each gets its own update.
3. For each: **update** the existing file, or **create** it from `TEMPLATE.md` when the domain is new.
4. Record in `Architectural choices` every decision the implementation actually made — including the ones the plan did not anticipate. When a decision was logged with `larapilot:decision-log`, reference its id rather than re-arguing it.
5. Refresh the `README.md` index row and the `Last updated by` metadata line.
6. Let the docs ride **in the task commit** — code, tests, and domain docs together, never a separate docs commit. (The catch-up backfill is the one exception: it commits on its own, before the first task.)

A task is not `task-done` while its domain doc describes the old behavior.

Under **`ECO`**: same files, same sections, terse prose — bullets instead of paragraphs, tables instead of narrative. Never skipped.

## First-change catch-up _(mandatory)_

A project that has code but no domain docs is **brought level on the first change**, not gradually. The first spec, fix, or hotfix that touches the codebase documents **the whole project** — every domain that already exists — and only then its own change. There is no "we will fill the rest in later": the rest never arrives, and a half-documented folder is read as a complete one.

`config-show` reports the state under **`data.dev_docs`**:

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

## Freshness gate _(Robert enforces at review)_

At **`larapilot-implement` Phase 2** and in **`larapilot-review`**, a domain whose code changed in the diff while its doc did not is a **High** finding, reported as `stale dev doc — {file}`. The parent fixes it before `spec-review`, like any other High.

At **`larapilot-ship`**, a stale or missing domain doc for a shipped feature blocks the release checklist until written.
