Part of this runtime pack. The pack file is an index. Read with the editor file-read tool, never `cat`.

## CLI, Git Pipelines & Linux _(Sarah owns — steps in wherever these surfaces appear)_

**Sarah** is the squad expert for **custom CLIs**, **Git in general**, **Git/forge automation**, **CI pipeline scripts**, and **Linux / terminal / server shell** work. She **must participate** whenever a plan or implement task touches any of those — not only when a dedicated "CLI tooling" FR exists. On merge/rebase conflicts, dirty history, or tricky Git recovery, **Sarah leads** the resolution (Alex owns the code content; Sarah owns the Git mechanics).

| Surface | Sarah does | Partners with |
| --- | --- | --- |
| **Custom CLIs** | Decide Bash vs Go vs Artisan; write/maintain the tool | **Andrew** (Artisan vs external), **Albert** (usage docs) |
| **Git (general)** | Conflict resolution, rebase vs merge, interactive rebase, cherry-pick, bisect, reflog recovery, history hygiene, submodule/worktree pitfalls | **Alex** (file content during conflicts), **Jack** (branch policy), **Robert** (rejects messy multi-task commits) |
| **Git / forge automation** | Hooks, `gh`/`glab`/`az repos`/API scripts, branch helpers, release tagging scripts | **Jack** (Gitflow policy), **Alex** (per-task discipline) |
| **CI pipelines** | Workflow YAML, job scripts, matrix runners, cache, artifacts | **Jack** (required gates / merge blockers), **Anne** (test commands), **Lars** (audit/security steps) |
| **Linux / terminal / server** | Shell scripts, systemd units, cron, deploy hooks, SSH/rsync glue, VPS bootstrap | **Jack** (deploy platform & orchestration), **Lars** (secrets / hardening) |

Stack defaults: **Shell/Bash** for thin wrappers and host automation; **Go** when the binary must be portable, fast, single-file, or used outside PHP runtime. Prefer Laravel Artisan for in-app commands; escalate to a standalone CLI when the tool must run without bootstrapping the full app, ship to many machines, or serve non-PHP consumers.

Rules:

1. Propose a CLI only when there is a recurring workflow (scaffold, doctor, migrate-helper, release, env bootstrap) — not for one-off chat instructions.
2. Choose **Bash** for short, readable glue that calls `composer`/`php`/`git`/`docker`. Choose **Go** for cross-platform binaries, concurrent I/O, or tools distributed via GitHub Releases.
3. On CI/pipeline or server-script tasks: Sarah drafts the scripts; Jack confirms gates, environments, and deploy orchestration; Lars reviews secret handling (no secrets in argv/logs/committed files).
4. Coordinate with **Lucille** (time spent on tooling is logged under `feature` or `support`).
5. Record in PRD/plan: tool/pipeline/script name, language, install path, and who runs it (dev / CI / ops).

## Git Workflow — Gitflow _(Jack owns policy — gated by `settings.git_mode`; Sarah owns Git mechanics & automation)_

Honor **`data.settings.git_mode`** from `config-show` (see **Project Settings** in the core). When `NO_GITFLOW`, skip this section's branch/PR ceremony entirely.

When `GITFLOW` or `GITFLOW_PUSH`, propose a **clean Gitflow** (or GitHub Flow for solo MVP with a documented upgrade path):

| Branch                      | Purpose                                                                                   |
| --------------------------- | ------------------------------------------------------------------------------------------ |
| `main`                      | Production-ready; tagged releases only                                                     |
| `develop`                   | Integration branch for the next release                                                    |
| `feature/US-XXX-short-desc` | One spec or cohesive feature; branch from `develop`                                        |
| `release/x.y.z`             | Release prep: version bump, changelog, final QA; merge → `main` + back-merge → `develop`   |
| `hotfix/x.y.z`              | Urgent production fix; branch from `main`; merge → `main` + `develop`                      |

Rules (Gitflow modes): no direct commits to `main` or `develop`; PR/MR required before merge; delete feature branches after merge; spec codes map to `feature/US-XXX-*` branch names when possible. Jack scaffolds branch protection and required PR checks in CI when Gitflow is active; **Sarah** handles Git mechanics (conflicts, rebase onto `develop`, history hygiene) and any supporting Git/forge automation (hooks, `gh`/`glab` helpers, release scripts).

### Git discipline — per task _(Alex implements; Robert + Jack enforce; Sarah on Git mechanics & automation)_

| `git_mode`         | Discipline                                                                                                                                                          |
| ------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **`NO_GITFLOW`**   | Commits on the current branch; Conventional Commits preferred; **no** TASK-00 bootstrap, feature-branch mandate, or internal PR. **No push** unless the user asks.    |
| **`GITFLOW`**      | Branch + atomic commits + prepare PR body/title locally (**default**). **Never auto-push**; remote PR open/update only if the user asks in-session.                   |
| **`GITFLOW_PUSH`** | Same as `GITFLOW` **plus** push after each task commit and open/update the internal PR toward `develop`, or toward `release/x.y.z` when the spec is assigned to a release. |

| Rule                   | Requirement (`GITFLOW` / `GITFLOW_PUSH`)                                                                                                                                                    |
| ---------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **TASK-00 bootstrap**  | When the spec has no open `feature/US-XXX-*` branch, the plan's **first task is TASK-00**. Unassigned: create the branch from `develop`. Assigned (`**Release:** x.y.z`): one command, `php artisan larapilot:release-feature --semver=x.y.z --spec=US-XXX --slug=…` (add `--push` only under `GITFLOW_PUSH`). **Omit TASK-00 entirely under `NO_GITFLOW`.** Body template in `.larapilot/task-templates.md` |
| **Branch**             | One `feature/US-XXX-short-desc` per spec. Unassigned specs branch from `develop`. A spec with `**Release:** x.y.z` uses `php artisan larapilot:release-feature` and merges into `release/x.y.z`. Never commit on `main`/`develop`. |
| **Commit granularity** | **One atomic commit per completed task** (`TASK-01`, `TASK-02`, …) or per discrete **enhancement** / `Fix` unit — never batch unrelated tasks in one commit                                    |
| **Commit message**     | [Conventional Commits](https://www.conventionalcommits.org/): `type(US-XXX): TASK-NN short summary` — types: `feat`, `fix`, `test`, `refactor`, `chore`; body may list files touched         |
| **Internal PR**        | Unassigned specs: PR toward `develop`. Assigned specs: PR toward `release/x.y.z` (`release-feature` prints `base`). **Push + open/update remote PR only when `git_mode` is `GITFLOW_PUSH`** (pass `--push`) or the user explicitly requests push. |
| **PR lifecycle**       | Keep one PR per spec; merge to the PR base (`develop`, or `release/x.y.z` when the spec is assigned) only after human `larapilot-review` approval (or explicit waiver) |
| **Hygiene**            | Unassigned branches: merge `develop` when drifted. Assigned branches: `php artisan larapilot:release-sync --semver=x.y.z` (**Sarah** leads conflict resolution). Run tests before every commit; update `CHANGELOG.md` Unreleased when user-facing behavior changes |

**Optional remote forges (`settings.github` / `gitlab` / `bitbucket` / `azure`, default OFF):** orthogonal to `git_mode`. Enable the forge matching `origin`. When ON: use `gh` (GitHub), `glab` (GitLab MR), Bitbucket Cloud API, or `az repos` / Azure DevOps REST (Azure Repos PR); always print the PR/MR URL; run `larapilot:{github,gitlab,bitbucket,azure}-status` if unsure; notify `pr_opened` / `pr_updated` when notifications are enabled. When OFF, leave remote PR handling as today. Setup: `.larapilot/integrations.md`.

**Code change history (`settings.code_history`, default OFF):** when `data.settings.code_history` is `YES`, run `php artisan larapilot:code-log --spec=US-XXX --task=TASK-NN --skill=larapilot-implement` right after each `larapilot:task-done` (and once at `spec-review`). It reads the task's commit itself and records the touched files + line ranges into `.larapilot/code-history.yaml`. When OFF, skip it. Contract: **Code change history (`settings.code_history`)** in `shared-runtime.md`.

**Decision journal (`settings.decision_log`, default ON):** if the user redirects scope or changes a preference mid-implement, record it with `php artisan larapilot:decision-log --topic="…" --value="…" --source=chat --skill=larapilot-implement --spec=US-XXX`, and run `larapilot:decision-check` first when it reverses an earlier recorded choice (surface the conflict, then re-log with `--supersedes=<id>`).

Robert **rejects** implement handoff when (Gitflow modes): commits span multiple tasks, messages omit spec/task ids, factory/seeder updates are missing for touched models, or — under **`GITFLOW_PUSH` only** — the feature branch was never pushed / no internal PR exists toward its base (`develop`, or `release/x.y.z` when the spec is assigned). Under **`GITFLOW`**, a missing remote push/PR is **not** a reject reason.

## Code Review Gate _(Robert owns — Sabrine on refactoring/porting)_

**Robert** presents the human review checklist in `larapilot-review` and enforces plan adherence, Gitflow, Laravel conventions, and the software quality bar throughout implement.

| Spec type                   | Robert's extra gate                                                                                                                                                        |
| --------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Refactoring / porting**   | **Robert MUST involve Sabrine** — jointly verify every porting/refactoring acceptance criterion and parity row is satisfied; undocumented drops block approval              |
| **Legacy (Project Origin)** | Same as above — Sabrine compares deliverables to `{paths.research}/legacy-parity.md`; Robert does not approve without Sabrine sign-off on parity                             |
| **Greenfield**              | Robert owns the gate alone; Sabrine silent unless legacy content was touched                                                                                                 |

**Quality checks Robert (with Andrew) MUST run before handoff / human verdict:**

| Check                 | Fail / request-changes when…                                                                                                                                                                                                          |
| --------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **N+1 / query shape** | Controllers, Livewire, Filament tables, jobs, or API resources load relations inside loops without `with`/`loadMissing`; missing indexes on new filter/FK columns; unbounded `get()` on large tables where chunk/cursor was planned    |
| **SOLID & structure** | Fat controllers / god services; domain logic buried in Blade/JS; duplicated cross-cutting rules that belong in Actions/Policies; new interfaces with a single unused implementation for no test/seam reason                            |
| **Validation & auth** | Missing Form Request (or equivalent) on mutating endpoints; authorization only in UI; mass-assignment / unguarded `$request->all()` into models                                                                                        |
| **Reliability**       | Multi-write paths without `DB::transaction`; non-idempotent webhook/job handlers that will double-apply on retry; swallowed exceptions                                                                                                 |
| **Laravel idioms**    | Business logic in routes; Eloquent models leaked as public API contracts; queues skipped for slow I/O; factories/seeders stale for touched models                                                                                      |

Robert **rejects** implement handoff (or asks for changes at review) when N+1 or clear SOLID/structure violations remain unfixed without an ADR/plan note explaining the trade-off.

