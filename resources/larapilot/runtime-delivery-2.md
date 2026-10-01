## Git Workflow — Gitflow _(Jack owns policy — gated by `settings.git_mode`; Sarah owns Git mechanics & automation)_

<!-- when: git_mode=NO_GITFLOW -->
`git_mode` is **`NO_GITFLOW`**: no branch or PR ceremony. Commits go on the current branch, one per completed task, Conventional Commits preferred (`type(US-XXX): TASK-NN short summary`). **No** TASK-00 bootstrap, feature-branch mandate, or internal PR. **No push** unless the user asks. Run tests before every commit; update `CHANGELOG.md` Unreleased when user-facing behavior changes.
<!-- end -->
<!-- when: git_mode!=NO_GITFLOW -->
A **clean Gitflow** (or GitHub Flow for solo MVP with a documented upgrade path):

| Branch                      | Purpose                                                                                   |
| --------------------------- | ------------------------------------------------------------------------------------------ |
| `main`                      | Production-ready; tagged releases only                                                     |
| `develop`                   | Integration branch for the next release                                                    |
| `feature/US-XXX-short-desc` | One spec or cohesive feature; branch from `develop`                                        |
| `release/x.y.z`             | Release prep: version bump, changelog, final QA; merge → `main` + back-merge → `develop`   |
| `hotfix/x.y.z`              | Urgent production fix; branch from `main`; merge → `main` + `develop`                      |

Rules: no direct commits to `main` or `develop`; PR/MR required before merge; delete feature branches after merge; spec codes map to `feature/US-XXX-*` branch names when possible. Jack scaffolds branch protection and required PR checks in CI; **Sarah** handles Git mechanics (conflicts, rebase onto `develop`, history hygiene) and any supporting Git/forge automation (hooks, `gh`/`glab` helpers, release scripts).

### Git discipline — per task _(Alex implements; Robert + Jack enforce; Sarah on Git mechanics & automation)_

<!-- when: git_mode=GITFLOW -->
`git_mode` is **`GITFLOW`**: branch + atomic commits + PR body and title prepared locally. **Never auto-push**; the remote PR is opened or updated only if the user asks in-session.
<!-- end -->
<!-- when: git_mode=GITFLOW_PUSH -->
`git_mode` is **`GITFLOW_PUSH`**: branch + atomic commits, **plus** push after each task commit and open/update the internal PR toward `develop`, or toward `release/x.y.z` when the spec is assigned to a release.
<!-- end -->

| Rule                   | Requirement                                                                                                                                                                                  |
| ---------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **TASK-00 bootstrap**  | When the spec has no open `feature/US-XXX-*` branch, the plan's **first task is TASK-00**. Unassigned: create the branch from `develop`. Assigned (`**Release:** x.y.z`): one command, `php artisan larapilot:release-feature --semver=x.y.z --spec=US-XXX --slug=…` (add `--push` only under `GITFLOW_PUSH`). Body template in `task-templates.md` |
| **Branch**             | One `feature/US-XXX-short-desc` per spec. Unassigned specs branch from `develop`. A spec with `**Release:** x.y.z` uses `php artisan larapilot:release-feature` and merges into `release/x.y.z`. Never commit on `main`/`develop`. |
| **Commit granularity** | **One atomic commit per completed task** (`TASK-01`, `TASK-02`, …) or per discrete **enhancement** / `Fix` unit — never batch unrelated tasks in one commit                                    |
| **Commit message**     | [Conventional Commits](https://www.conventionalcommits.org/): `type(US-XXX): TASK-NN short summary` — types: `feat`, `fix`, `test`, `refactor`, `chore`; body may list files touched         |
| **Internal PR**        | Unassigned specs: PR toward `develop`. Assigned specs: PR toward `release/x.y.z` (`release-feature` prints `base`). **Push + open/update remote PR only when `git_mode` is `GITFLOW_PUSH`** (pass `--push`) or the user explicitly requests push. |
| **PR lifecycle**       | Keep one PR per spec; merge to the PR base (`develop`, or `release/x.y.z` when the spec is assigned) only after human `larapilot-review` approval (or explicit waiver) |
| **Hygiene**            | Unassigned branches: merge `develop` when drifted. Assigned branches: `php artisan larapilot:release-sync --semver=x.y.z` (**Sarah** leads conflict resolution). Run tests before every commit; update `CHANGELOG.md` Unreleased when user-facing behavior changes |

Robert **rejects** implement handoff when commits span multiple tasks, messages omit spec/task ids, or factory/seeder updates are missing for touched models.
<!-- end -->
<!-- when: git_mode=GITFLOW -->
A missing remote push or PR is **not** a reject reason.
<!-- end -->
<!-- when: git_mode=GITFLOW_PUSH -->
He also rejects when the feature branch was never pushed, or no internal PR exists toward its base (`develop`, or `release/x.y.z` when the spec is assigned).
<!-- end -->

<!-- when: forge=YES -->
**Remote forge:** after a push, open or update the PR/MR with the forge that is ON and print its URL (**Remote forges**, Project Settings).
<!-- end -->

## Technical Documentation _(Albert owns)_

Every Larapilot project carries a **baseline technical documentation layer** by default — Albert never treats docs as optional at the project level — **except when `settings.effort` is `ECO`**, which defers the baseline and keeps OpenAPI and the developer domain docs.

**Developer domain docs sit outside that gate**: they are written at every effort level, in every project, in English. Full contract: `.larapilot/runtime-dev-docs.md`.

| Tier          | Always present                                                                                                        |
| ------------- | -----------------------------------------------------------------------------------------------------------------------|
| **Baseline**  | README (setup, local dev method per PRD, env vars, queue worker, scheduler, test commands), architecture overview, CHANGELOG discipline |
| **Technical** | Developer-facing docs for APIs, webhooks, and domain modules touched by the backlog — **OpenAPI/Swagger** for every public or partner API (`public/openapi.yaml`, Scramble, or L5-Swagger); ship verifies the spec matches routes |
| **Domain (devs)** | One Markdown file per domain/entity/feature under **`paths.dev_docs`** (default `.larapilot/docs/devs/`): functional flow, technical design, architectural choices with rejected alternatives, key decisions and invariants. **English only, never deferred — including under `ECO`.** Written in the same spec that changes the behavior |
| **Extended**  | Diagram sets (draw.io/Mermaid), runbooks, admin handbooks, **PDF client tutorials/manuals** — only when the user opts in per spec |

Rules:

1. **Inception** — Albert records the baseline doc set in the PRD; notes optional extended deliverables without assuming them globally.
2. **Spec approval (`larapilot-spec`)** — when presenting user stories for approval, **Albert proposes via AskQuestion** whether the spec needs **extended documentation** beyond the baseline. Default may be baseline-only; extended scope is explicit per spec. Under **`ECO`**: skip this AskQuestion entirely.
3. **Plan** — explicit doc tasks per spec: baseline updates always; extended tasks only when approved.
4. **Implement** — Albert writes or updates docs alongside code; never leaves API routes undocumented when OpenAPI is in scope; update docs in the same spec that changes the API or integration. **Always** update the touched domain files under `paths.dev_docs` before `spec-review` — a domain whose code moved while its doc did not is a **High** review finding. On a project where `data.dev_docs.documented` is `false`, the first change documents **every existing domain** first (**First-change catch-up**, `runtime-dev-docs-catchup.md`).
5. **Ship / maintenance** — verify baseline completeness before release; keep docs in sync with **Sophia** on every maintenance release; flag stale OpenAPI, runbooks, or domain docs in review.

<!-- when: effort=ECO -->
### Effort gate — `ECO` docs deferral

`settings.effort` is **`ECO`**:

| Still required                                                                                                          | Deferred / skipped                                                                                                              |
| ----------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------|
| Workflow artifacts: PRD, specs, plans, AC, review checklist                                                              | Albert baseline + extended doc tasks (README, architecture notes, runbooks)                                                       |
| **OpenAPI/Swagger** when public/partner API routes change (`public/openapi.yaml`, Scramble, L5-Swagger, or equivalent)   | Diagrams, PDF manuals, Postman collections, doc-site polish                                                                       |
| **Developer domain docs** under `paths.dev_docs` — same sections, terse prose (bullets and tables instead of narrative)   | Nothing in this folder is deferred — under `ECO` it gets shorter, never skipped                                                    |
| Code comments only when needed to unblock the next task                                                                  | AskQuestion for extended docs; CHANGELOG narrative passes (a one-line Unreleased bump stays OK for a user-requested release)      |
<!-- end -->

Ownership: **Albert** owns technical documentation, developer domain docs (**always English** — Emily does not localize these), and client manuals (default **English**; localized editions with **Emily**); **Marika** owns product/marketing copy (not technical docs); **John** owns API design accuracy; **Alex** implements doc-site routes when applicable.

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
