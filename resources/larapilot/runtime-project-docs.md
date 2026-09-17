# Larapilot Runtime — Project Docs

Phase pack for **`larapilot-project-docs`** and for incremental updates from **every skill** when `settings.project_docs` is `YES`. Read `.larapilot/shared-runtime.md` (core) first.

## Gate _(Zoey enforces)_

Honor **`data.settings.project_docs`** from `config-show`. When `NO` (**default**), skip this entire pack — no `_project_docs/` obligation. When `YES`, Albert maintains a living handbook at **`paths.project_docs`** (default `_project_docs/` at the project root).

## Handbook structure _(Albert owns layout; all personas contribute content)_

Organize by chapter — one topic per file, cross-linked index at `_project_docs/README.md`:

| Chapter | File(s) | Owner | Content |
| --- | --- | --- | --- |
| Overview | `README.md`, `01-overview.md` | Mark + Albert | Elevator pitch, personas, delivery target, quick start |
| Architecture | `02-architecture.md` | John + Mike | Stack, modules, data model diagram (Mermaid), tenancy |
| Features | `03-features/` | Mark + Tom | One file per major capability / epic — flows + AC summary |
| API | `04-api.md` | Alex + Albert | Routes, auth, OpenAPI link, request/response examples |
| CLI & jobs | `05-cli-and-jobs.md` | Sarah | Artisan commands, scheduler, queues |
| Frontend | `06-frontend.md` | Joe + Elise | Topology, design system, key screens (when UI exists) |
| Operations | `07-operations.md` | Jack + Sophia | Deploy, env vars (names only — no secrets), support runbook |
| Releases | `08-releases.md` | Sarah + Jack | Version history, release branches *(when `release_mode=YES`)* |
| Diagrams | `diagrams/` | Albert | Mermaid / ASCII exports referenced from chapters |

Every chapter that describes behavior **must** include at least one **functional diagram** (Mermaid sequence, flowchart, or C4-style context) where it aids comprehension.

## Update contract _(Albert enforces on every material change)_

When `project_docs=YES`:

1. **After implement** — update the feature chapter (or create it) for the spec just delivered: user flow, touched modules, new env keys (names only), CLI/API surface changes.
2. **After review/ship** — refresh operations/release chapters when deploy or versioning changed.
3. **After settings/topology changes** — update architecture/overview within the same session.
4. **Never** paste secrets, tokens, or user-specific absolute paths — reference env var **names** only (`LARAPILOT_FRONTEND_REPO_PATH`, not `/Users/…`).

Keep diffs focused — update the sections that changed; do not rewrite unrelated chapters.

## Retroactive bootstrap _(mid-project enable)_

When the user turns `project_docs` on **after** work already shipped:

1. Read PRD, `spec-list`, representative plans, `CHANGELOG` (if any), and recent git history.
2. Scaffold the chapter tree above with **best-effort** content — mark uncertain sections with `<!-- TODO: verify -->`.
3. Present a one-screen summary in chat; AskQuestion whether to deep-dive any chapter before continuing incremental updates.

`/larapilot-project-docs` runs the full bootstrap or refresh on demand.

## Skill invocation

Dedicated skill: **`/larapilot-project-docs`** — bootstrap, re-index, audit stale chapters, or regenerate diagrams. Other skills perform **minimal inline updates** to the relevant chapter when they touch product behavior (implement, review, ship, feature, release).
