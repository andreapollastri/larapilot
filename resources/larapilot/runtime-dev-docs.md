# Larapilot Runtime — Developer Domain Docs

Developer domain docs are **always on**. There is no setting to enable them and no effort level that switches them off — including **`ECO`**, which shortens the prose but never skips the file. They are the one place where the *reasoning* behind the implementation survives the session that produced it.

## What they are

A Markdown file per **domain / entity / feature** under **`paths.dev_docs`** (default `.larapilot/docs/devs/`), written **for the engineers who will maintain the code**:

| Layer | The file answers |
| --- | --- |
| **Functional** | What the domain does, and how it behaves step by step at runtime — including the failure paths |
| **Technical** | Which models, services, actions, jobs, events, routes, commands, config keys, and tests make it up, and where they live |
| **Architectural** | What was chosen, what was rejected, and **why** — so the next developer does not undo a constraint by accident |
| **Decisional** | The invariants and trade-offs that keep the system running, and what breaks when they are violated |

They are **not** the PRD (product contract, client language), **not** the quote (commercial, client language), and **not** the handbook in `.larapilot/docs/handbook/` (opt-in manual for the whole project, mixed technical/functional audience). Those may repeat facts; this folder is the only one that carries implementation rationale.

## Hard rules

1. **English, always.** Regardless of the PRD language, the user's language, or the conversation language. These files outlive the team that wrote them.
2. **One file per domain**, `kebab-case.md` (`billing.md`, `user-authentication.md`, `webhook-ingestion.md`). An **entity** gets its own file only when it carries behavior beyond plain CRUD; otherwise it is a section inside its domain file. Do not create one file per class — that is a code listing, not documentation.
3. **Updated in the same spec that changes the behavior.** Never deferred to a follow-up spec, never "at ship". A task is not `task-done` while the domain doc still describes the old behavior.
4. **Fixed section skeleton** — from `TEMPLATE.md` in the folder — so files stay diffable and readers know where to look: `Purpose`, `Functional flow`, `Technical design`, `Architectural choices`, `Key decisions & invariants`, `Extension points & gotchas`, `Related`. Plus the metadata table (`Status`, `Introduced by`, `Last updated by`, `Owner persona`).
5. **Index stays current** — `README.md` in the folder lists every domain with a one-line purpose and the last spec that touched it.
6. **No secrets, no user-specific absolute paths.** Env var **names** only. These files are committed.
7. **Rewrite, do not append.** The folder holds the current truth of the system, not a changelog — history belongs to Git and to `CHANGELOG.md`. Delete or mark `Status: Deprecated` when a domain is removed.

## Write contract _(Albert owns; Alex/Joe supply the mechanics)_

At **Phase 0** of `larapilot-implement`, check `data.dev_docs.documented` first — when it is `false` on a codebase that already has domains, run the **First-change catch-up** (`runtime-dev-docs-catchup.md`) before any task starts.

Then, during each task, once the code and tests are verified and **before the task commit**:

1. Take `paths.dev_docs` from the `context` envelope; create the folder if the workspace predates it.
2. Determine which domains the task touched. One spec may touch several; each gets its own update.
3. For each: **update** the existing file, or **create** it from `TEMPLATE.md` when the domain is new.
4. Record in `Architectural choices` every decision the implementation actually made — including the ones the plan did not anticipate. When a decision was logged with `larapilot:decision-log`, reference its id rather than re-arguing it.
5. Refresh the `README.md` index row and the `Last updated by` metadata line.
6. Let the docs ride **in the task commit** — code, tests, and domain docs together, never a separate docs commit. (The catch-up backfill is the one exception: it commits on its own, before the first task.)

A task is not `task-done` while its domain doc describes the old behavior.

Under **`ECO`**: same files, same sections, terse prose — bullets instead of paragraphs, tables instead of narrative. Never skipped.

## Freshness gate _(Robert enforces at review)_

At **`larapilot-implement` Phase 2** and in **`larapilot-review`**, a domain whose code changed in the diff while its doc did not is a **High** finding, reported as `stale dev doc — {file}`. The parent fixes it before `spec-review`, like any other High.

At **`larapilot-ship`**, a stale or missing domain doc for a shipped feature blocks the release checklist until written.
