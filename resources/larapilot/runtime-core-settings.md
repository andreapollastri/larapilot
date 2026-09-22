Section of the Larapilot runtime. Index: `.larapilot/shared-runtime.md`. Read this file with the editor file-read tool, never `cat`.

## Project Settings

Persisted in `.larapilot/config.yaml` under `settings:`. Configure with **`/larapilot-settings`** (AskQuestion) or `php artisan larapilot:settings-set`. Defaults when unset: `effort: STANDARD` / `backlog: STANDARD` / `git_mode: GITFLOW` / `testing: NORMAL` / `account: NONE` / `auto_approve: false` / `lucille: true` / `decision_log: true` / `code_history: false` / `release_mode: false` / `project_docs: false` / `comments: false` / `dashboard_auth: false` / `api_auth: false` / `security_scan: false` / `github|gitlab|bitbucket|azure: false` / `notifications: false` / `notify_*: false`.

### Environment paths (never commit user-specific absolute paths)

Machine-specific absolute paths **must not** appear in committed YAML or skill examples. Store them in `.env` and resolve via `config-show`:

| Concern | Env key | Set via |
| --- | --- | --- |
| External frontend repo | `LARAPILOT_FRONTEND_REPO_PATH` | `/larapilot-frontend-companion` or `larapilot:frontend-set --path=…` (writes `.env`) |

When a skill needs a path that is missing from env, **AskQuestion** (or chat) until the user provides it, then persist with the matching CLI command and continue. Never embed `/Users/…` style examples in artifacts.

### Effort (`settings.effort`)

Controls token economy and process depth across all skills.

| Value | Behavior |
| --- | --- |
| **`ECO`** | Token economy. **Never spawn sub-agents** (explore, Robert, Lars, or any other) — always stay in the parent session with inline checklists. **Lucille is disabled automatically** when you switch to `ECO` (`settings.lucille` → `NO`) — no usage-log, no deadline interviews, no schedule-drift prompts. **Re-enable Lucille anytime** with `/larapilot-settings` or `php artisan larapilot:settings-set --lucille=YES` (ECO can stay selected). **Defer documentation theater** — no Albert baseline/extended doc tasks, no PDF/diagrams/runbooks, no README rewrites, no AskQuestion for extended docs — **except OpenAPI/Swagger: still update when public/partner API routes change**, and **except developer domain docs under `.larapilot/docs/devs/`, which are never deferred** — terse prose, same sections (see **Technical Documentation** in `runtime-delivery.md` and `.larapilot/runtime-dev-docs.md`). Skip optional deepsearch, Oliver red-team, and non-essential persona rounds. Prefer one-voice summaries. No E2E/browser planning. Implement: task → code → minimal tests → commit (per git_mode) → next. Review: short checklist only. Workflow artifacts (PRD/spec/plan/AC) still required. |
| **`STANDARD`** | Normal Larapilot behavior (**default**). Full skill contracts without forcing every optional deep pass. |
| **`MAX`** | **Deep** mode on every process and flow. Prefer thorough persona rounds, always run optional explore/review sub-agents when the editor supports them, expand plan/test strategy, and surface residual risks. Treat "optional" research and verification as in-scope unless the user waives them. |

Zoey may remind the team once per skill when `effort` is `ECO` or `MAX`. Do not narrate the setting on every message.

### Backlog granularity (`settings.backlog`)

Controls how many specs and epics `larapilot-spec` / `larapilot-feature` / `larapilot-bug` create for the same PRD scope. It changes **spec cardinality only** — never coverage: deferred/merged scope must stay traceable via `FR-XXX` citations in spec bodies and plan tasks. MoSCoW × delivery target still decides *what* enters the backlog; `backlog` decides *how finely* it is sliced.

| Value | Behavior |
| --- | --- |
| **`LEAN`** | Fewest possible specs: one spec per **end-to-end user journey**, merging all related FRs into it (each cited as `Traces to: FR-XXX, FR-YYY`). Technical seams, per-entity admin resources, and per-locale i18n work are **always plan tasks**, never separate specs. Single epic per product area; target ≤ 5 epics total. |
| **`STANDARD`** | One spec per **demonstrable user capability** (**default**). Closely related FRs may share a spec when they are demonstrated together. Laravel seams (models, controllers, policies, UI, API resources) become **plan tasks** inside the spec — split into separate specs only when a seam is independently demonstrable to a user *and* likely to ship separately. Reuse existing epics; new epic only for a genuinely new product area (guideline: 5–8 epics per product). |
| **`GRANULAR`** | Fine-grained backlog: one spec per FR is acceptable; splitting along Laravel seams, Filament resource-per-entity, and i18n per-locale is allowed when it aids parallelization or review. Multi-epic backlog expected. Use for large teams or when specs map to individual PR assignments. |

**Epic consolidation (all values):** before proposing a new `EP-XXX`, read existing epics from `spec-list` and reuse the closest match. Create a new epic only when no existing epic reasonably covers the product area — never one epic per spec, and never duplicate an existing epic under a new title. Maintenance/fix specs reuse the existing Maintenance epic when present.

**Epics are first-class delivery containers** (beyond individual US specs): every epic must carry a clear **objective** (outcome in one sentence) and, when the project has dates, an epic **deadline** (`YYYY-MM-DD`). Lucille uses these with schedule milestones to forecast effort and flag temporal criticality on the Plan dashboard. Mark owns epic titles/objectives; Lucille owns deadline realism and Gantt drift.

### Git mode (`settings.git_mode`)

| Value | Behavior |
| --- | --- |
| **`NO_GITFLOW`** | No Gitflow ceremony. Work on the current branch; Conventional Commits still preferred. No mandatory `feature/US-XXX-*`, TASK-00 bootstrap, or internal PR. Do not push unless the user explicitly asks. |
| **`GITFLOW`** | Gitflow **without automatic push** (**default**). One `feature/US-XXX-*` from `develop`, one atomic commit per task, prepare/update an internal PR description toward `develop`. **Do not `git push` or open/update the remote PR unless the user explicitly asks** in the session. |
| **`GITFLOW_PUSH`** | Full Gitflow **with** push: after each task commit, `git push` the feature branch and open/update the internal PR/MR toward `develop`. |

Push is **never** implied by `GITFLOW` alone — only `GITFLOW_PUSH` enables automatic push/PR remote updates. Full branch and per-task discipline: **Git Workflow** in `runtime-delivery.md`.

### Testing (`settings.testing`)

Anne scales plan tasks, implement verification, and review evidence to this bar — **independent of** delivery target (delivery target may still add domain cases within the bar).

| Value | Behavior |
| --- | --- |
| **`MINIMAL`** | Critical-path Pest/PHPUnit only (auth, payments, core API happy paths + key validation). No Playwright, Laravel Dusk, Pest browser, viewport matrix, axe automation, or journey E2E. |
| **`NORMAL`** | Standard feature/unit/policy/API/queue tests and review evidence (**default**). **No** Playwright, Dusk, Pest browser E2E, or multi-viewport browser suites. Manual test handoff notes are OK when helpful. |
| **`BEST`** | All imaginable automation for the stack: above + integration/HTTP fakes, tenancy isolation when multi-tenant, primary-journey E2E, Playwright or Dusk (or Pest browser), viewport matrix (375 / 768 / 1280), axe at mobile, Lighthouse a11y when public UI — match project tooling. |

**Do not** plan or run Playwright/Dusk/E2E/viewport-browser work under `MINIMAL` or `NORMAL`. Those belong to `BEST` only. Full bar details: **Testing Standards** in `runtime-delivery.md`.

### Account (`settings.account`) — opt-in, default NONE

Who is selling the work. Unlocks the **Economics** dashboard (`/larapilot/economics`), `larapilot:economics-set` / `economics-show`, and `/larapilot-economics`. Aurora owns the numbers. Full contract: `.larapilot/runtime-economics.md`.

Stored as an enum string (`NONE` / `FREELANCE` / `COMPANY`) — not a boolean (`OFF` is a YAML 1.1 bool token, so the idle value is `NONE`). Missing key → **`NONE`**.

| Value | Behavior |
| --- | --- |
| **`NONE`** | **Default.** No Economics quotes. `/larapilot/economics` shows the enable empty-state. |
| **`FREELANCE`** | Sole trader / partita IVA. Tax engine uses forfettario, IRPEF, autónomo, sole trader, … for the chosen country. |
| **`COMPANY`** | Structured entity (SRL, SPA, Ltd, GmbH, C-Corp). Corporate tax + dividend extraction + higher compliance. |

Enable with `php artisan larapilot:settings-set --account=FREELANCE` or `--account=COMPANY`, then persist country/regime/rates with `larapilot:economics-set`.

### Auto-approve (`settings.auto_approve`)

Stored in `config.yaml` as a boolean **`true`/`false`**. The `settings-set` flag and the `config-show` envelope express it as `YES`/`NO`, mapping to `true`/`false`; AskQuestion labels may say Yes/No but always mean the boolean.

| Value | Behavior |
| --- | --- |
| **`false`** | Human gate required (**default**). Specs stop at `REVIEW`; only a human Approve via `/larapilot-review` → `spec-approve` moves to `DONE`. |
| **`true`** | After implement reaches `REVIEW`, **`/larapilot-autopilot`** may present a short Robert checklist and call `php artisan larapilot:spec-approve {code}` without waiting for a human verdict. Standalone `/larapilot-review` still presents the checklist; when `true` and the user (or autopilot) did not request changes, it may approve in the same turn. |

`true` explicitly opts out of the default human-in-the-loop DONE gate for batch delivery. Prefer `false` unless the user accepts that risk.

### Lucille (`settings.lucille`)

**Lucille is ON by default at every skill level** (silent usage ledger, deadlines, schedule drift, `/larapilot-usage`), **except when switching to `effort: ECO`**, which **disables Lucille automatically** (`lucille` → `NO`). She can be **re-enabled via settings** while remaining on ECO: `/larapilot-settings` or `php artisan larapilot:settings-set --lucille=YES`. Passing `--lucille=YES` in the same `settings-set` call as `--effort=ECO` keeps her on.

Stored in `config.yaml` as a boolean **`true`/`false`**. The `settings-set` flag and the `config-show` envelope express it as `YES`/`NO`.

| Value | Behavior |
| --- | --- |
| **`true` / `YES`** | **Default** (when not on a fresh ECO switch). Lucille is active: log tokens/time at skill end, ask deadlines at inception, surface schedule drift, honor `/larapilot-usage`. |
| **`false` / `NO`** | **Excluded.** Skills must not call `usage-log`, must not run Lucille interview rounds, and `/larapilot-usage` only reports that she is excluded (historical ledger remains readable via `usage-report` / dashboard). Set automatically when selecting **`ECO`** unless `--lucille` is also passed. |

Unset or missing key → treat as **`YES`** (never infer exclusion), unless you just switched to ECO via `settings-set` (that path writes `lucille: false`). Re-enable with `php artisan larapilot:settings-set --lucille=YES`.

### Decision journal (`settings.decision_log`) — opt-out, default ON

An append-only, timestamped record of every **explicit user decision** across all phases — both fixed-choice **AskQuestion** answers and free-text directives/preferences ("the background must be orange", "no soft-delete", "drop German for v1"). Persisted to `.larapilot/decisions.yaml` (never rewritten in place — a reversal is a new entry pointing at the one it supersedes).

Stored as a boolean `true`/`false`; envelope exposes `YES`/`NO`. Missing key → **`YES`** (never infer exclusion).

| Value | Behavior |
| --- | --- |
| **`true` / `YES`** | **Default.** After the user commits a material choice, record it: `php artisan larapilot:decision-log --topic="…" --value="…" --source=askquestion\|chat --skill=<skill> [--spec=US-XXX] [--rationale="…"]`. **Before** recording a value on a topic that might already carry a decision, first run `php artisan larapilot:decision-check --topic="…" --value="…"`; when `data.has_regression` is `true`, surface the earlier choice(s) from `data.conflicts` via **AskQuestion** ("on {ts date} you chose **{old}** for {label}; confirm **{new}** supersedes it") and only then re-run `decision-log` with `--supersedes=<id>` of the entry being overridden. Never silently overwrite an earlier decision; never hand-edit `decisions.yaml`. |
| **`false` / `NO`** | **Excluded.** Skills must not call `decision-log` / `decision-check`. Any existing `.larapilot/decisions.yaml` stays readable. |

Topic is matched case-insensitively (normalized + substring), so keep `--topic` stable and specific ("primary background color", not "color"). `--source=askquestion` for AskQuestion answers, `--source=chat` for free-text directives.

### Code change history (`settings.code_history`) — opt-in, default OFF

An append-only log of **where in the codebase work happened** — per spec/task, the files and new-file line ranges touched, derived automatically from the task's git commit. Persisted to `.larapilot/code-history.yaml`.

Stored as a boolean `true`/`false`; envelope exposes `YES`/`NO`. Missing key → **`NO`**.

| Value | Behavior |
| --- | --- |
| **`true` / `YES`** | After each `larapilot:task-done` (and once more at `spec-review`), call `php artisan larapilot:code-log --spec=US-XXX --task=TASK-XX --skill=larapilot-implement`. The command resolves the task's commit itself (`--commit=` / `--range=` override it; it falls back to the working-tree diff when no commit resolves). `larapilot:code-history [--file= --spec=]` reports per-file touchpoints. |
| **`false` / `NO`** | **Default.** Skip entirely — no `code-log` calls. |

