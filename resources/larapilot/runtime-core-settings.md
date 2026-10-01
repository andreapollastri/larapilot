## Project Settings

The values are `data.settings` in the `context` envelope. Change one only with **`/larapilot-settings`** (AskQuestion) or `php artisan larapilot:settings-set` — never by editing `.larapilot/config.yaml`. A toggle that is `NO` and has no section in the files you were given is off: do nothing for it.

<!-- when: view=full -->
Persisted in `.larapilot/config.yaml` under `settings:`. Defaults when unset: `effort: STANDARD` / `backlog: STANDARD` / `git_mode: GITFLOW` / `testing: NORMAL` / `account: NONE` / `auto_approve: false` / `lucille: true` / `decision_log: true` / `code_history: false` / `prior_art: true` / `release_mode: false` / `project_docs: false` / `comments: false` / `dashboard_auth: false` / `api_auth: false` / `security_scan: false` / `github|gitlab|bitbucket|azure: false` / `notifications: false` / `notify_*: false`. Boolean settings are stored as `true`/`false`; the `settings-set` flag and every envelope express them as `YES`/`NO`.
<!-- end -->

### Environment paths

Machine-specific absolute paths never appear in committed YAML, skill examples, or artifacts: they live in `.env` and come back through the envelope (the external frontend repo is `LARAPILOT_FRONTEND_REPO_PATH`, written by `larapilot:frontend-set --path=…`). A path missing from env is asked for (**AskQuestion** or chat), persisted with its CLI command, and only then used.

### Effort (`settings.effort`)

<!-- when: effort=ECO -->
- **`ECO`** — Token economy. **Never spawn sub-agents** (explore, Robert, Lars, or any other) — always stay in the parent session with inline checklists. **Lucille is disabled automatically** when you switch to `ECO` (`settings.lucille` → `NO`) — no usage-log, no deadline interviews, no schedule-drift prompts. **Re-enable Lucille anytime** with `/larapilot-settings` or `php artisan larapilot:settings-set --lucille=YES` (ECO can stay selected). **Defer documentation theater** — no Albert baseline/extended doc tasks, no PDF/diagrams/runbooks, no README rewrites, no AskQuestion for extended docs — **except OpenAPI/Swagger: still update when public/partner API routes change**, and **except developer domain docs under `.larapilot/docs/devs/`, which are never deferred** — terse prose, same sections (**Technical Documentation**, `runtime-dev-docs.md`). Skip optional deepsearch, Oliver red-team, and non-essential persona rounds. Prefer one-voice summaries. No E2E/browser planning. Implement: task → code → minimal tests → commit (per git_mode) → next. Review: short checklist only. Workflow artifacts (PRD/spec/plan/AC) still required.
<!-- end -->
<!-- when: effort=STANDARD -->
- **`STANDARD`** — Normal Larapilot behavior (**default**). Full skill contracts without forcing every optional deep pass.
<!-- end -->
<!-- when: effort=MAX -->
- **`MAX`** — **Deep** mode on every process and flow. Prefer thorough persona rounds, always run optional explore/review sub-agents when the editor supports them, expand plan/test strategy, and surface residual risks. Treat "optional" research and verification as in-scope unless the user waives them.
<!-- end -->

<!-- when: effort!=STANDARD -->
Zoey may remind the team once per skill that `effort` is not `STANDARD`. Do not narrate the setting on every message.
<!-- end -->

### Backlog granularity (`settings.backlog`)

How finely `larapilot-spec` / `larapilot-feature` / `larapilot-bug` slice the same PRD scope. It changes **spec cardinality only** — never coverage: merged or deferred scope stays traceable via `FR-XXX` citations in spec bodies and plan tasks. MoSCoW × delivery target still decides *what* enters the backlog.

<!-- when: backlog=LEAN -->
- **`LEAN`** — Fewest possible specs: one spec per **end-to-end user journey**, merging all related FRs into it (each cited as `Traces to: FR-XXX, FR-YYY`). Technical seams, per-entity admin resources, and per-locale i18n work are **always plan tasks**, never separate specs. Single epic per product area; target ≤ 5 epics total.
<!-- end -->
<!-- when: backlog=STANDARD -->
- **`STANDARD`** — One spec per **demonstrable user capability** (**default**). Closely related FRs may share a spec when they are demonstrated together. Laravel seams (models, controllers, policies, UI, API resources) become **plan tasks** inside the spec — split into separate specs only when a seam is independently demonstrable to a user *and* likely to ship separately. Reuse existing epics; new epic only for a genuinely new product area (guideline: 5–8 epics per product).
<!-- end -->
<!-- when: backlog=GRANULAR -->
- **`GRANULAR`** — Fine-grained backlog: one spec per FR is acceptable; splitting along Laravel seams, Filament resource-per-entity, and i18n per-locale is allowed when it aids parallelization or review. Multi-epic backlog expected. Use for large teams or when specs map to individual PR assignments.
<!-- end -->

**Epic consolidation (all values):** before proposing a new `EP-XXX`, read the epics `spec-list` has and reuse the closest match; a new epic only when none covers the product area — never one per spec, never a duplicate under a new title. Fix specs reuse the Maintenance epic when present. Every epic carries an **objective** (one sentence) and, when the project has dates, a **deadline** (`YYYY-MM-DD`) Lucille forecasts against. Mark owns titles and objectives; Lucille deadline realism and Gantt drift.

### Git mode (`settings.git_mode`)

<!-- when: git_mode=NO_GITFLOW -->
- **`NO_GITFLOW`** — No Gitflow ceremony. Work on the current branch; Conventional Commits still preferred. No mandatory `feature/US-XXX-*`, TASK-00 bootstrap, or internal PR. Do not push unless the user explicitly asks.
<!-- end -->
<!-- when: git_mode=GITFLOW -->
- **`GITFLOW`** — Gitflow **without automatic push** (**default**). One `feature/US-XXX-*` from `develop`, one atomic commit per task, prepare/update an internal PR description toward `develop`. **Do not `git push` or open/update the remote PR unless the user explicitly asks** in the session.
<!-- end -->
<!-- when: git_mode=GITFLOW_PUSH -->
- **`GITFLOW_PUSH`** — Full Gitflow **with** push: after each task commit, `git push` the feature branch and open/update the internal PR/MR toward `develop`.
<!-- end -->

Push is **never** implied by `GITFLOW` alone — only `GITFLOW_PUSH` enables automatic push/PR remote updates. Full branch and per-task discipline: **Git Workflow** in `runtime-delivery.md`.

### Testing (`settings.testing`)

Anne scales plan tasks, implement verification, and review evidence to this bar — **independent of** delivery target (delivery target may still add domain cases within the bar).

<!-- when: testing=MINIMAL -->
- **`MINIMAL`** — Critical-path Pest/PHPUnit only (auth, payments, core API happy paths + key validation). No Playwright, Laravel Dusk, Pest browser, viewport matrix, axe automation, or journey E2E.
<!-- end -->
<!-- when: testing=NORMAL -->
- **`NORMAL`** — Standard feature/unit/policy/API/queue tests and review evidence (**default**). **No** Playwright, Dusk, Pest browser E2E, or multi-viewport browser suites. Manual test handoff notes are OK when helpful.
<!-- end -->
<!-- when: testing=BEST -->
- **`BEST`** — All imaginable automation for the stack: feature/unit/policy/API/queue tests + integration/HTTP fakes, tenancy isolation when multi-tenant, primary-journey E2E, Playwright or Dusk (or Pest browser), viewport matrix (375 / 768 / 1280), axe at mobile, Lighthouse a11y when public UI — match project tooling.
<!-- end -->

<!-- when: testing!=BEST -->
**Do not** plan or run Playwright/Dusk/E2E/viewport-browser work: those belong to `BEST` only.
<!-- end -->
Full bar details: **Testing Standards** in `runtime-delivery.md`.

### Account (`settings.account`) — opt-in, default NONE

Who is selling the work. Unlocks the **Economics** dashboard (`/larapilot/economics`), `larapilot:economics-set` / `economics-show`, and `/larapilot-economics`. Aurora owns the numbers. Full contract: `.larapilot/runtime-economics.md`.

<!-- when: view=full -->
Stored as an enum string (`NONE` / `FREELANCE` / `COMPANY`) — not a boolean (`OFF` is a YAML 1.1 bool token, so the idle value is `NONE`). Missing key → **`NONE`**.
<!-- end -->

<!-- when: account=NONE -->
- **`NONE`** — **Default.** No Economics quotes. `/larapilot/economics` shows the enable empty-state. Enable with `php artisan larapilot:settings-set --account=FREELANCE` or `--account=COMPANY`, then persist country/regime/rates with `larapilot:economics-set`.
<!-- end -->
<!-- when: account=FREELANCE -->
- **`FREELANCE`** — Sole trader / partita IVA. Tax engine uses forfettario, IRPEF, autónomo, sole trader, … for the chosen country.
<!-- end -->
<!-- when: account=COMPANY -->
- **`COMPANY`** — Structured entity (SRL, SPA, Ltd, GmbH, C-Corp). Corporate tax + dividend extraction + higher compliance.
<!-- end -->

### Auto-approve (`settings.auto_approve`)

<!-- when: auto_approve=NO -->
- **`NO`** — Human gate required (**default**). Specs stop at `REVIEW`; only a human Approve via `/larapilot-review` → `spec-approve` moves to `DONE`.
<!-- end -->
<!-- when: auto_approve=YES -->
- **`YES`** — After implement reaches `REVIEW`, **`/larapilot-autopilot`** may present a short Robert checklist and call `php artisan larapilot:spec-approve {code}` without waiting for a human verdict. Standalone `/larapilot-review` still presents the checklist; when the user (or autopilot) did not request changes, it may approve in the same turn. This explicitly opts out of the default human-in-the-loop DONE gate for batch delivery.
<!-- end -->

### Lucille (`settings.lucille`)

<!-- when: lucille=YES -->
- **`YES`** — Lucille is active, usually quietly. **At skill end, log the session once** with the `usage_log` command of the `context` envelope: `{N}` is Zoey's end estimate, `{M}` the wall-clock minutes; add `--spec=US-XXX` when the work was on one spec and `--note="…"` for one line of context. Skip it for an aborted start with no work done. She asks deadlines at inception, surfaces schedule drift, and answers `/larapilot-usage`. She never blocks a decision.
<!-- end -->
<!-- when: lucille=NO -->
- **`NO`** — **Excluded.** Skills must not call `usage-log`, must not run Lucille interview rounds, and `/larapilot-usage` only reports that she is excluded (the historical ledger stays readable via `usage-report` and the dashboard). Re-enable with `php artisan larapilot:settings-set --lucille=YES`.
<!-- end -->

<!-- when: view=full -->
**Lucille is ON by default at every skill level**, **except when switching to `effort: ECO`**, which **disables Lucille automatically** (`lucille` → `NO`). She can be **re-enabled via settings** while remaining on ECO; passing `--lucille=YES` in the same `settings-set` call as `--effort=ECO` keeps her on. Unset or missing key → treat as **`YES`** (never infer exclusion), unless you just switched to ECO via `settings-set` (that path writes `lucille: false`).
<!-- end -->

### Decision journal (`settings.decision_log`) — opt-out, default ON

An append-only, timestamped record of every **explicit user decision** — **AskQuestion** answers and free-text directives ("the background must be orange", "no soft-delete") — in `.larapilot/decisions.yaml`; a reversal is a new entry pointing at the one it supersedes.

<!-- when: decision_log=YES -->
- **`YES`** — **Default.** After the user commits a material choice: `php artisan larapilot:decision-log --topic="…" --value="…" --source=askquestion|chat --skill=<skill> [--spec=US-XXX] [--rationale="…"]`. On a topic that may already carry a decision, run `php artisan larapilot:decision-check --topic="…" --value="…"` first; when `data.has_regression` is `true`, surface `data.conflicts` via **AskQuestion** ("on {ts date} you chose **{old}** for {label}; confirm **{new}** supersedes it"), then `decision-log` with `--supersedes=<id>`. Never silently overwrite a decision; never hand-edit `decisions.yaml`. Topics match case-insensitively by substring: keep `--topic` stable and specific ("primary background color", not "color").
<!-- end -->
<!-- when: decision_log=NO -->
- **`NO`** — **Excluded.** Skills must not call `decision-log` / `decision-check`. Any existing `.larapilot/decisions.yaml` stays readable.
<!-- end -->

### Prior art check (`settings.prior_art`) — opt-out, default ON

<!-- when: prior_art=YES -->
- **`YES`** — **Default.** Before scope is written, **Sebastian** asks consent for the search queries, looks for existing open-source, packaged, or commercial solutions, writes `{paths.research}/prior-art.md`, and the user records a verdict (`Build anyway` | `Adopt / fork` | `Integrate as dependency` | `Not checked`) as `**Prior Art:**` in the PRD. Full contract: **Prior Art & Open-Source Alternatives** in `runtime-discovery.md`.
<!-- end -->
<!-- when: prior_art=NO -->
- **`NO`** — The round is skipped without asking and the PRD records `Not checked`. Never run the search.
<!-- end -->

### Code change history (`settings.code_history`) — opt-in, default OFF

<!-- when: code_history=YES -->
- **`YES`** — An append-only log of **where in the codebase work happened** — per spec/task, the files and new-file line ranges touched, derived from the task's git commit, in `.larapilot/code-history.yaml`. After each `larapilot:task-done` (and once more at `spec-review`), call `php artisan larapilot:code-log --spec=US-XXX --task=TASK-XX --skill=larapilot-implement`. The command resolves the task's commit itself (`--commit=` / `--range=` override it; it falls back to the working-tree diff when no commit resolves). `larapilot:code-history [--file= --spec=]` reports per-file touchpoints.
<!-- end -->
<!-- when: code_history=NO -->
- **`NO`** — **Default.** No `code-log` calls.
<!-- end -->
