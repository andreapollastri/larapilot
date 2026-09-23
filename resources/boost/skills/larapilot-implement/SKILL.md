---
name: larapilot-implement
description: "Implements a planned spec: code, tests, review, handoff. Use when the user wants to implement a PLANNED spec or start coding a backlog item. Not for discovery, backlog, or planning."
---

# Larapilot — Spec Implementation

Execute a planned spec: code, tests, review, handoff to REVIEW.

## Shared Runtime

Obey **Read protocol** in `.larapilot/shared-runtime.md`: file-read tool only, never `cat` / `head` / `sed`. A truncated preview is a failed load — read the remainder before any other step. Then read only the section files that index lists for this skill.

Read `.larapilot/shared-runtime.md`, then every part of `.larapilot/runtime-delivery.md` and `.larapilot/runtime-dev-docs.md` (developer domain docs — mandatory, English, every effort level).

Read `.larapilot/task-templates.md` — execute each task's **Git Deliverables** and **Test Data** sections per `data.settings`.

## Output Economy

**High.** After each task, one line and nothing else:

`TASK-04 → Invoice policy → Pest 4 passed → TASK-05`

No table. Do not repeat the diff, filenames already committed, or test output the command already returned. One extra line per blocker: `BLOCKED TASK-04 — reason`. Robert/Lars findings: one bullet per finding, severity first. Spec handoff before `spec-review`: **6 lines maximum**. A table is allowed only in that handoff.

When `settings.effort` is **`ECO`**: **never spawn sub-agents**; **defer docs** except OpenAPI when public/partner API routes change **and the developer domain docs under `{paths.dev_docs}`, which are written every time** (terse prose, same sections); short inline Robert/Lars checklist only; one-line status. When **`MAX`**: always run Robert + Lars as sub-agents when available (else inline deep), expand residual-risk notes.

## Autopilot spec worker

When the handoff says you are the autopilot spec worker, follow **Spec worker** in `.larapilot/runtime-core-subagents.md`. Run Phases 0–1 only. `spec-start` already ran — do not call it again, and do not call `task-done`, `spec-review`, `code-log`, `usage-log`, or `decision-log`. Do not spawn Robert, Lars, or any other sub-agent. Do not AskQuestion — return `BLOCKED` plus a `done:` line. The final message is `OK implement {code} | N tasks | pr: {url or —}` or the two-line `BLOCKED` form. Standalone `/larapilot-implement` still runs Phase 2 and Phase 3.

## The Team

🤖 Zoey · 📒 Lucille · 🔧 Alex · 🗄️ Mike · 👾 Andrew · ⌨️ Sarah · ✨ Joe · 📱 Ricky · 📝 Albert · ✍️ Marika · 🔄 Sabrine · 🔗 Matt · 🌍 Emily · 🧪 Anne · 🛡️ Robert · 🔐 Lars — roles in the shared-runtime roster. Mike reviews schema/migration work; **Sarah** owns Git mechanics (conflicts, rebase/merge, history hygiene), CLIs, forge automation, CI pipeline YAML/scripts, and Linux/server shell whenever those surfaces appear; Lucille logs the session at the end.

## Config & CLI

1. `php artisan larapilot:config-show --only=settings,paths,frontend,dev_docs` — **read `data.settings`** (`effort`, `git_mode`, `testing`), **`data.paths`**, **`data.frontend`** when topology is external, and **`data.dev_docs`** (`documented`, `count`, `domains`); honor them for the whole run.
2. `php artisan larapilot:spec-show {code} --fields=id,title,status,dependencies` for the task list, then `php artisan larapilot:spec-show {code} --task=TASK-NN` for the task you are about to execute. OR `php artisan larapilot:spec-next --status=PLANNED --fields=id,title,status,dependencies` when no code was given, then `--task=` for the active task.
3. `php artisan larapilot:spec-start {code}`
4. `php artisan larapilot:task-done {code} {taskId}` (after each task)
5. `php artisan larapilot:code-log --spec={code} --task={taskId} --skill=larapilot-implement` — **only when `data.settings.code_history` is `YES`** (default OFF); run right after `task-done`, and once more after `spec-review`
6. `php artisan larapilot:quality` — Pint + Larastan (level 5+) before backend `task-done`; use `--fix` for formatting when needed. The envelope is verdict and findings. Run with `-v` only when a finding line was cut.
7. `php artisan larapilot:spec-review {code}`
8. `php artisan larapilot:decision-log …` / `decision-check …` — when `data.settings.decision_log` is `YES` (default) and the user redirects scope or changes a preference mid-run; `decision-check` first when it reverses an earlier recorded choice

## Execution Contract

1. **Autonomous by default** — stop only for explicit blockers (scope change, missing prerequisite spec, semantic test breakage).
2. Implement the **full planned spec** — never silently drop acceptance criteria to fit an MVP unless the PRD delivery target is MVP and the spec was scoped accordingly. If in doubt, read `paths.prd` for the delivery target — do not assume MVP.
3. Work under the task's target repo: default **`data.workdir`** (Laravel) for backend tasks; **`data.frontend.repo_path`** for tasks marked `repo: frontend`. Connector commands always run from `data.project_root`.
4. After `spec-start`, re-run `spec-show` if a worktree may have been created.

## Laravel Implementation

Use **Laravel Boost** throughout: `Search Docs` before unfamiliar APIs, `Database Schema` / `Database Query` for data work, `Tinker` for quick verification, `Application Info` for versions, `Last Error` / `Read Log Entries` when debugging.

Apply the canonical delivery rules from `runtime-delivery.md` — do not re-derive them:

- **Architecture Standards** — SOLID Actions/Services, thin controllers, Form Requests + Policies at the edge, `DB::transaction` on multi-write paths, queues for slow I/O, eager loading + indexes on every relation-touching path (**no N+1** before `task-done`).
- **Laravel Scaffolding Defaults** — Fortify 2FA on auth specs, `Password::defaults()`, UUID PKs (`HasUuids`), Argon2id hashing, Socialite for SSO; local dev per the PRD choice (Sail commands only when the PRD chose Sail; generic `php artisan` when undefined).
- **Git Workflow / Git discipline** — honor `settings.git_mode`: `NO_GITFLOW` → current branch, commits only; `GITFLOW` → `feature/US-XXX-*` + atomic commits + PR prepared **without push**; `GITFLOW_PUSH` → same **plus** push and open/update the internal PR. Unassigned specs target `develop`. A spec with `**Release:** x.y.z` starts with `php artisan larapilot:release-feature` and the PR targets `base` (`release/x.y.z`); catch `develop` up with `release-sync`. Never commit directly to `main`/`develop` in Gitflow modes. If `checked_out` is false, stop.
- **Remote forges (`settings.github` / `gitlab` / `bitbucket` / `azure`, default OFF)** — orthogonal to `git_mode`. Enable the forge matching `origin`. When ON: probe `larapilot:{github,gitlab,bitbucket,azure}-status`; after push open/update PR/MR via `gh` / `glab` / Bitbucket API / `az repos` (or Azure DevOps REST); **always print the PR/MR URL**; `larapilot:notify --event=pr_opened|pr_updated` when notifications are ON.
- **Notifications** — when `settings.notifications` is `YES`, after handoff to REVIEW call `larapilot:notify --event=spec_review --title="…"`. `task-done` / hard hooks notify automatically.
- **Test Data — Factories & Seeders** — factory + seeder updated in the **same task** as model/migration changes; `migrate:fresh --seed` verified before `task-done`.
- **Vendor & Package Policy** — Laravel first-party → Spatie → Filament plugins (only when the PRD chose Filament — never introduce it on your own) → other vetted vendors; Starter Kit specs scaffold per [starter-kits docs](https://laravel.com/docs/starter-kits) — never mix a mismatched UI stack. Verify compatibility via `Application Info`; `composer audit` after `composer require`.
- **Technical Documentation** — update OpenAPI/Swagger in the same spec that changes APIs (**including under `ECO`**); README/CHANGELOG/`security.txt`/`SECURITY.md` when in scope and effort is not `ECO`.
- **Developer Domain Docs (Albert)** — when the project has none yet, the first change documents **every existing domain** before its own work (**First-change catch-up** in `runtime-dev-docs.md`). Then, for every domain/entity/feature the spec touches, write or refresh `{paths.dev_docs}/{domain}.md` (default `.larapilot/docs/devs/`) per `runtime-dev-docs.md`: functional flow, technical design, architectural choices **with the alternatives that were rejected and why**, key decisions and invariants. **English regardless of the project language. Never deferred, at any effort level.** Commit the doc with the task that changed the behavior, and keep the folder `README.md` index current.
- **Code quality gate** — run `larapilot:quality` on Laravel tasks before `task-done`; project stays on [Larastan](https://github.com/larastan/larastan) level 5+ and Laravel Pint (never lower level without human waiver).

Skill-specific execution notes:

- **Client materials & research:** before implementing, read cited files under `{paths.client_materials}` and `{paths.research}/`; verify acceptance criteria against them.
- **Legacy parity (Sabrine):** when the spec touches legacy parity, read `{paths.legacy}` and `{paths.research}/legacy-parity.md`; preserve behavior and data — verify each in-scope parity row before `task-done`; Anne verifies migration evidence; flag gaps in handoff.
- **Frontend (Elise + Joe):** honor **Frontend Topology** from the PRD — when `API + external frontend`, implement API/admin in Laravel (`repo: backend`) and primary UI in the configured FE repo (`repo: frontend` → write under `data.frontend.repo_path`; `git -C {repo_path}` for commits; `npm`/`pnpm`/`vitest` for FE tests). Implement per `runtime-ux.md`: design system aligned with Elise from mockups through code, mobile-first responsive (320 px up), dark+light, WCAG 2.2 AA; commit `public/favicon.svg`, logo, OG image when the client provided none. Joe guards tokens/components, animations, bundle/performance, visual fidelity.
- **Mobile (Ricky):** hybrid/native/PWA device features per PRD — permissions, graceful degradation, store constraints.
- **Copy (Marika):** no placeholder lorem on shipped surfaces; realistic copy in views, notifications, `lang/` files. **i18n (Emily):** `lang/` translations, locale detection, currency/timezone display when in scope.
- **Integrations (Matt):** wire third-party APIs per plan — OAuth, webhooks + signature verification, SDK/HTTP clients, queued sync, `Http::fake()` tests, README notes; also wire the PRD-chosen stack (storage, newsletter, analytics, edge proxies, observability). **Jack** is involved when choices touch deploy, CDN, queues, storage, or CI runners; **Sarah** authors/updates CI workflow files, deploy hooks, and server shell scripts for those choices.
- **CLI / Git / Linux (Sarah):** whenever tasks touch `.github/workflows`, GitLab/Bitbucket/Azure Pipelines CI, git hooks, `gh`/`glab`/`az repos` helpers, Bash/Go tooling, systemd/cron, or VPS bootstrap — Sarah implements them per **CLI, Git Pipelines & Linux** in `runtime-delivery.md`. On merge/rebase conflicts or history cleanup, **Sarah leads** Git resolution; Alex resolves conflicting file content.
- **Multi-tenancy:** implement the chosen pattern per PRD; add isolation tests when Anne requires.
- **High-risk integrations:** note in handoff if an **Oliver** red-team pass is recommended before ship (payments, OAuth, webhooks, imports).

## Workflow

### Phase 0 — Load plan

From the field-filtered `spec-show`: `data.spec`, task ids and dependencies, `data.workdir`. Load `data.tasks[0].body` with `--task=` only for the task you are starting.

**Developer domain docs catch-up gate.** When `data.dev_docs.documented` is `false` and the codebase already has domains to describe, Albert brings the **whole project** level before any task runs — one file per existing domain, not just the ones this spec touches — per **First-change catch-up** in `runtime-dev-docs.md`. Announce the scope in one line (no AskQuestion), commit it on its own as `docs({code}): bring developer domain docs level`, then start Phase 1. A greenfield project on its first spec has nothing to catch up on and skips straight to Phase 1. This gate runs at **every** effort level, `ECO` included.

### Phase 1 — Execute tasks in waves

Group tasks by dependencies. For each task:

1. Alex / Joe implement per the task body contract — backend under `data.workdir`, frontend under `data.frontend.repo_path` when `repo: frontend`
2. Anne writes/runs tests per `settings.testing` — `php artisan test` / Pest for Laravel; `npm test` / vitest / playwright from the FE root for `repo: frontend` tasks
3. Albert updates the touched `{paths.dev_docs}/{domain}.md` files (create from `TEMPLATE.md` when the domain is new) — **a task is not done while its domain doc describes the old behavior**
4. Alex commits (one atomic commit per task, code + tests + domain docs together). Push + remote PR **only** when `git_mode` is `GITFLOW_PUSH` (or the user explicitly asks). If a forge setting is `YES`, open/update via `gh` / `glab` / Bitbucket API / `az repos` (Azure DevOps), print the PR/MR URL, and notify `pr_opened` / `pr_updated` when notifications are on
5. `task-done` when verified — the CLI also ticks the task's `- [ ]` completion criteria and may emit a `task_done` notification; never edit the plan YAML manually. An autopilot spec worker skips this call and step 6; the parent runs both after the worker returns
6. When `data.settings.code_history` is `YES` (default OFF): `php artisan larapilot:code-log --spec={code} --task={taskId} --skill=larapilot-implement` — records the touched files + line ranges from the task commit into `.larapilot/code-history.yaml`

### Phase 2 — Review (sub-agents or inline)

After all tasks are verified, run review per `settings.effort`: **`ECO`** → **no sub-agents**; short inline Robert/Lars checklist only. **`STANDARD`** → two **readonly** passes (Robert + Lars). **`MAX`** → always spawn sub-agents when available, deeper findings. Only the **parent** edits code, re-runs tests, writes the review artifact, and calls the CLI.

#### Launch

**`ECO`:** do not use the sub-agent tool — stay in the parent and run the inline checklist below.

With a sub-agent tool and `effort` **`STANDARD`** or **`MAX`**: spawn both passes as **readonly sub-agents in parallel** (one message, two calls, synchronous — not background). Pick the closest available type per pass (see **Type mapping** in shared-runtime):

| Persona   | Pass            | Example types                                             |
| --------- | --------------- | --------------------------------------------------------- |
| 🛡️ Robert | code review     | Cursor `bugbot`; else generic readonly sub-agent          |
| 🔐 Lars   | security review | Cursor `security-review`; else generic readonly sub-agent |

Enable the editor's readonly flag when available; the handoff prompt forbids edits regardless. Review scope: the branch diff (or uncommitted changes when nothing is committed yet).

**Inline fallback** — `ECO`, or no sub-agent tool: the parent runs the same two passes itself, sequentially (Robert, then Lars), using the handoff prompt below as a checklist (`ECO`: keep findings to Critical/High bullets only). All later steps are identical.

#### Handoff prompt

Use **Review handoff** in `.larapilot/runtime-core-subagents.md`. Fill the braces from `config-show` and `spec-show`. Do not paste a second copy of the prompt. The return cap is 8 bullets; the diff stays out of the parent session.

#### Parent merge loop

1. Deduplicate Robert + Lars bullets; fix all **Critical** and **High** autonomously.
2. Re-run tests after fixes (`php artisan test` or `./vendor/bin/pest`).
3. Re-run **Lars only** if auth, policies, or security files changed materially; skip a Robert re-run unless code changed widely.
4. Write `{paths.review}/{code}.md` (from `config-show`; default `.larapilot/docs/review/`) per **Sub-agents → Review artifact** in shared-runtime.
5. Document **Medium** findings in Parent actions if not fixed.
6. Before handoff, confirm every domain the spec touched has a current file under `{paths.dev_docs}` and an index row in its `README.md`.

Robert and Lars still speak in character when the **parent** summarizes merged findings in chat (Output Economy bullets).

### Phase 3 — Handoff

`php artisan larapilot:spec-review {code}` with a summary note.

Report in **6 lines maximum**: spec code, tasks completed, tests run, review outcome, developer domain docs written or updated. A table is allowed only in this handoff.
