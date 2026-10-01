---
name: larapilot-implement
description: "Implements a planned spec: code, tests, review, handoff. Use when the user wants to implement a PLANNED spec or start coding a backlog item. Not for discovery, backlog, or planning."
---

# Larapilot — Spec Implementation

Execute a planned spec: code, tests, review, handoff to REVIEW.

## Context

`php artisan larapilot:context implement` — with `--session={token}` when this conversation already holds one, `--fresh` after a compaction. Read every file under `data.runtime.read`, none under `loaded`, and an `on_demand` file only when its `when` comes true. Settings (`effort`, `git_mode`, `testing`), paths, `data.project`, `data.dev_docs`, and `data.frontend` come from that envelope: no `config-show`.

The task bodies are the execution contract: each carries its own **Git Deliverables**, **Test Data**, and **Domain Docs** sections, written by the plan. The files you read hold the rules behind them — do not re-derive them, and do not apply a rule for a setting value the files do not show.

## Output Economy

**High.** After each task, one line and nothing else:

`TASK-04 → Invoice policy → Pest 4 passed → TASK-05`

Copy that shape; do not add a second sentence. No table. Do not repeat the diff, filenames already committed, or test output the command already returned. One extra line per blocker: `BLOCKED TASK-04 — reason`. Robert/Lars findings: one bullet per finding, severity first. Spec handoff before `spec-review`: **6 lines maximum**. A table is allowed only in that handoff.

When `settings.effort` is **`ECO`**: **never spawn sub-agents**; **defer docs** except OpenAPI when public/partner API routes change **and the developer domain docs under `{paths.dev_docs}`, which are written every time** (terse prose, same sections); short inline Robert/Lars checklist only; one-line status. When **`MAX`**: always run Robert + Lars as sub-agents when available (else inline deep), expand residual-risk notes.

## Autopilot spec worker

When the handoff says you are the autopilot spec worker, read `spec-worker.md` (on demand) and follow **Spec worker**. Run Phases 0–1 only. `spec-start` already ran — do not call it again, and do not call `task-done`, `spec-review`, `code-log`, `usage-log`, or `decision-log`. Do not spawn Robert, Lars, or any other sub-agent. Do not AskQuestion — return `BLOCKED` plus a `done:` line. The final message is `OK implement {code} | N tasks | pr: {url or —}` or the two-line `BLOCKED` form. Standalone `/larapilot-implement` still runs Phase 2 and Phase 3.

## The Team

🤖 Zoey · 📒 Lucille · 🔧 Alex · 🗄️ Mike · 👾 Andrew · ⌨️ Sarah · ✨ Joe · 📱 Ricky · 📝 Albert · ✍️ Marika · 🔄 Sabrine · 🔗 Matt · 🌍 Emily · 🧪 Anne · 🛡️ Robert · 🔐 Lars. Mike reviews schema/migration work; **Sarah** owns Git mechanics (conflicts, rebase/merge, history hygiene), CLIs, forge automation, CI pipeline YAML/scripts, and Linux/server shell whenever those surfaces appear; Lucille logs the session at the end.

## CLI

1. `php artisan larapilot:spec-show {code} --fields=id,title,status,dependencies` for the task list, then `php artisan larapilot:spec-show {code} --task=TASK-NN` for the task you are about to execute. OR `php artisan larapilot:spec-next --status=PLANNED --fields=id,title,status,dependencies` when no code was given, then `--task=` for the active task.
2. `php artisan larapilot:spec-start {code}`
3. `php artisan larapilot:task-done {code} {taskId}` (after each task)
4. `php artisan larapilot:code-log --spec={code} --task={taskId} --skill=larapilot-implement` — **only when `data.settings.code_history` is `YES`**; right after `task-done`, and once more after `spec-review`
5. `php artisan larapilot:quality` — Pint + Larastan (level 5+) before backend `task-done`; `--fix` for formatting when needed. The envelope is verdict and findings. Run with `-v` only when a finding line was cut.
6. `php artisan larapilot:spec-review {code}`
7. `php artisan larapilot:decision-log …` / `decision-check …` — when `data.settings.decision_log` is `YES` and the user redirects scope or changes a preference mid-run; `decision-check` first when it reverses an earlier recorded choice
8. `php artisan larapilot:prd-show --ids=…` — the FRs, journeys, or NFRs the spec traces to, when a criterion is unclear. Never the whole PRD.

When `data.settings.hooks` is `YES`, `spec-start`, `task-done`, and `spec-review` run the project's workflow hooks (`hooks.md`). A refusal with `details.hooks` is a failing check: fix it and run the same command again. A skill it names runs now, then the command again with `--skill-hooks-done=`. Every skill under `data.hooks.after.skills` runs before the next step.

## Execution Contract

1. **Autonomous by default** — stop only for explicit blockers (scope change, missing prerequisite spec, semantic test breakage).
2. Implement the **full planned spec** — never silently drop acceptance criteria to fit an MVP unless `data.project.delivery_target` is MVP and the spec was scoped accordingly.
3. Work under the task's target repo: default **`data.workdir`** (Laravel) for backend tasks; **`data.frontend.repo_path`** for tasks marked `repo: frontend`. `larapilot:*` commands always run from `data.project_root`.
4. After `spec-start`, re-run `spec-show` if a worktree may have been created.

## Laravel Implementation

Use **Laravel Boost** throughout: `Search Docs` before unfamiliar APIs, `Database Schema` / `Database Query` for data work, `Tinker` for quick verification, `Application Info` for versions, `Last Error` / `Read Log Entries` when debugging.

The rules are in the files you read, once: **Architecture Standards**, **Security baseline**, **Test Data — Factories & Seeders**, **Testing Standards**, and **Code quality gate** (`delivery-1.md`); **Git Workflow** and **Technical Documentation** (`delivery-2.md`); the developer domain docs contract (`dev-docs.md`). What this skill adds:

- **Before `task-done`** — no N+1 on the path the task touched, factory and seeder updated in the same task as the model or migration (`migrate:fresh --seed` passes), tests green at the `settings.testing` bar, `larapilot:quality` clean, the commit the task body names.
- **Packages** — Laravel first-party → Spatie → Filament plugins (only when the PRD chose Filament — never introduce it on your own) → other vetted vendors; verify compatibility via `Application Info`; `composer audit` after `composer require`. Detail: **Vendor & Package Policy** (`delivery-3.md`, on demand).
- **Client materials & research** — before implementing, read the files the spec cites under `{paths.client_materials}` and `{paths.research}/`; verify acceptance criteria against them.
- **Legacy parity (Sabrine)** — when the spec touches legacy parity, read `{paths.legacy}` and `{paths.research}/legacy-parity.md`; preserve behavior and data — verify each in-scope parity row before `task-done`; Anne verifies migration evidence; flag gaps in handoff.
- **Frontend (Elise + Joe)** — on a UI task read `ux-1.md` and `ux-2.md` (on demand): design system aligned with Elise from mockups through code, mobile-first responsive (320 px up), dark+light, WCAG 2.2 AA; commit `public/favicon.svg`, logo, OG image when the client provided none. When `data.project.frontend_topology` is `API + external frontend`, implement API/admin in Laravel (`repo: backend`) and the primary UI in the configured FE repo (`repo: frontend` → write under the scan's `root`, inside `write_scope.owned`) per **Frontend Companion** (`frontend.md`): `frontend-scan` once, every `rules.must_read` file and the playbook it lists read before the first FE edit, `frontend-rules --file=…` before each FE task, the scan's commands for checks, `git -C {git_root}` (`target_projects[].git_root`, else `git.root`) with hooks on. Under `data.frontend.mode` `handoff`, write no FE code: run the backend tasks, then `larapilot:frontend-brief {code}`, and leave the FE tasks open. Joe guards tokens/components, animations, bundle/performance, visual fidelity.
- **Mobile (Ricky)** — hybrid/native/PWA device features per PRD: permissions, graceful degradation, store constraints.
- **Copy (Marika)** — no placeholder lorem on shipped surfaces; realistic copy in views, notifications, `lang/` files. **i18n (Emily)** — `lang/` translations, locale detection, currency/timezone display when in scope (`delivery-5.md`, on demand).
- **Integrations (Matt)** — wire third-party APIs per plan: OAuth, webhooks + signature verification, SDK/HTTP clients, queued sync, `Http::fake()` tests, README notes (`delivery-5.md`, on demand). **Jack** is involved when choices touch deploy, CDN, queues, storage, or CI runners.
- **CLI / Git / Linux (Sarah)** — whenever a task touches `.github/workflows`, other CI, git hooks, `gh`/`glab`/`az repos` helpers, Bash/Go tooling, systemd/cron, or VPS bootstrap: `delivery-4.md` (on demand). On merge/rebase conflicts or history cleanup, **Sarah leads** Git resolution; Alex resolves conflicting file content.
- **Multi-tenancy** — implement the pattern the PRD chose; add isolation tests when Anne requires.
- **High-risk integrations** — note in handoff if an **Oliver** red-team pass is recommended before ship (payments, OAuth, webhooks, imports).

## Workflow

### Phase 0 — Load plan

From the field-filtered `spec-show`: `data.spec`, task ids and dependencies, `data.workdir`. Load a task body with `--task=` only for the task you are starting.

**Developer domain docs catch-up gate.** When `data.dev_docs.documented` is `false` and the codebase already has domains to describe, Albert brings the **whole project** level before any task runs — one file per existing domain, not just the ones this spec touches — per **First-change catch-up** (`dev-docs-catchup.md`, already in `read` in that case). Announce the scope in one line (no AskQuestion), commit it on its own as `docs({code}): bring developer domain docs level`, then start Phase 1. A greenfield project on its first spec has nothing to catch up on and skips straight to Phase 1. This gate runs at **every** effort level, `ECO` included.

### Phase 1 — Execute tasks in waves

Group tasks by dependencies. For each task:

1. Alex / Joe implement per the task body contract — backend under `data.workdir`, frontend under `data.frontend.repo_path` when `repo: frontend`
2. Anne writes/runs tests per `settings.testing` — `php artisan test` / Pest for Laravel; for `repo: frontend` tasks the commands `frontend-scan` gives (`target_projects[].commands`, then `commands.affected`), never a guessed `npm test`
3. Albert updates the touched `{paths.dev_docs}/{domain}.md` files (create from `TEMPLATE.md` when the domain is new) — **a task is not done while its domain doc describes the old behavior**
4. Alex commits (one atomic commit per task, code + tests + domain docs together). Push + remote PR **only** when `git_mode` is `GITFLOW_PUSH` (or the user explicitly asks); with a forge ON, open or update the PR/MR and print its URL
5. `task-done` when verified — the CLI also ticks the task's `- [ ]` completion criteria and may emit a `task_done` notification; never edit the plan YAML manually. An autopilot spec worker skips this call and step 6; the parent runs both after the worker returns
6. When `data.settings.code_history` is `YES`: `php artisan larapilot:code-log --spec={code} --task={taskId} --skill=larapilot-implement`

### Phase 2 — Review (sub-agents or inline)

After all tasks are verified, run review per `settings.effort`: **`ECO`** → **no sub-agents**; short inline Robert/Lars checklist only. **`STANDARD`** → two **readonly** passes (Robert + Lars). **`MAX`** → always spawn sub-agents when available, deeper findings. Only the **parent** edits code, re-runs tests, writes the review artifact, and calls the CLI.

With a sub-agent tool and `effort` **`STANDARD`** or **`MAX`**: spawn both passes as **readonly sub-agents in parallel** (one message, two calls, synchronous — not background), the closest available type per pass (**Type mapping**, `core-subagents.md`): 🛡️ Robert → code review (Cursor `bugbot`), 🔐 Lars → security review (Cursor `security-review`), else a generic readonly sub-agent. Enable the editor's readonly flag when available. Review scope: the branch diff (or uncommitted changes when nothing is committed yet).

**Inline fallback** — `ECO`, or no sub-agent tool: the parent runs the same two passes itself, sequentially (Robert, then Lars), with the handoff prompt as a checklist (`ECO`: keep findings to Critical/High bullets only). All later steps are identical.

**Handoff prompt** — **Review handoff** in `core-subagents.md`, braces filled from the envelope and `spec-show`. Do not paste a second copy of the prompt. The return cap is 8 bullets; the diff stays out of the parent session.

**Parent merge loop**

1. Deduplicate Robert + Lars bullets; fix all **Critical** and **High** autonomously.
2. Re-run tests after fixes (`php artisan test` or `./vendor/bin/pest`).
3. Re-run **Lars only** if auth, policies, or security files changed materially; skip a Robert re-run unless code changed widely.
4. Write `{paths.review}/{code}.md` per **Review artifact** (`core-subagents.md`).
5. Document **Medium** findings in Parent actions if not fixed.
6. Before handoff, confirm every domain the spec touched has a current file under `{paths.dev_docs}` and an index row in its `README.md`.

Robert and Lars still speak in character when the **parent** summarizes merged findings in chat (Output Economy bullets).

### Phase 3 — Handoff

`php artisan larapilot:spec-review {code}` with a summary note; `larapilot:notify --event=spec_review` when notifications are ON.

Report in **6 lines maximum**: spec code, tasks completed, tests run, review outcome, developer domain docs written or updated. A table is allowed only in this handoff.
