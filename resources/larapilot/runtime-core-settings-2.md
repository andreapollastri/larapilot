Section of the Larapilot runtime. Index: `.larapilot/shared-runtime.md`. Read this file with the editor file-read tool, never `cat`.

Continuation of **Project Settings** (`.larapilot/runtime-core-settings.md`). These toggles default to OFF. Read this file only when `config-show` reports one of them as `YES`.

### Release mode (`settings.release_mode`) — opt-in, default OFF

Semver **release ledger** (`.larapilot/releases.yaml`) plus optional Gitflow **`release/x.y.z`** branches when `git_mode` is `GITFLOW` or `GITFLOW_PUSH`. Full contract: `.larapilot/runtime-release.md`.

Stored as a boolean `true`/`false`; envelope exposes `YES`/`NO`. Missing key → **`NO`**.

| Value | Behavior |
| --- | --- |
| **`false` / `NO`** | **Default.** Ignore release ledger, release branches, and release AskQuestion rounds — classic `develop` / `feature/US-XXX-*` flow only. |
| **`true` / `YES`** | Sarah owns Git mechanics; Jack owns policy. `/larapilot-release` manages the ledger; inception/adopt propose roadmaps; `/larapilot-feature` assigns specs to open releases; `/larapilot-ship` runs the release ship ceremony. |

Enable with `php artisan larapilot:settings-set --release-mode=YES`.

### Project docs (`settings.project_docs`) — opt-in, default OFF

Living technical + functional handbook under **`_project_docs/`** (path key `paths.project_docs`). Albert owns structure; every material change updates the relevant chapter. Full contract: `.larapilot/runtime-project-docs.md`.

Stored as a boolean `true`/`false`; envelope exposes `YES`/`NO`. Missing key → **`NO`**.

| Value | Behavior |
| --- | --- |
| **`false` / `NO`** | **Default.** No `_project_docs/` maintenance obligation. |
| **`true` / `YES`** | After each implement/review/ship (and on enable mid-project), update `_project_docs/`; bootstrap retroactively from PRD, specs, plans, and git history when not enabled from day 1. |

Enable with `php artisan larapilot:settings-set --project-docs=YES`.

### Comments (`settings.comments`) — opt-in, default OFF

Internal feedback comments on the dashboard spec page, `POST /larapilot/api/specs/{code}/comments`, and `larapilot:spec-comment`. OFF by default; when ON, PM/dev can append comments until the spec is DONE. When OFF the feedback UI, API writes, and CLI command are disabled (existing `.larapilot/internal-feedback/*.md` files stay readable).

Stored as a boolean `true`/`false`; envelope exposes `YES`/`NO`. Missing key → **`NO`**.

| Value | Behavior |
| --- | --- |
| **`true` / `YES`** | PM/dev can append comments until the spec is DONE; blocking comments feed `spec-request-changes --include-feedback`. |
| **`false` / `NO`** | **Default.** Comments disabled project-wide. Optional env kill-switch: `LARAPILOT_COMMENTS_ENABLED=false`. |

Enable with `php artisan larapilot:settings-set --comments=YES`. Details: `.larapilot/internal-feedback/README.md`.

### Dashboard auth (`settings.dashboard_auth`) — opt-in, default OFF

Optional HTTP Basic Auth on the `/larapilot` **dashboard UI only**. OFF by default: the dashboard stays open in the allowed environments exactly as before. **Never** gates `/larapilot/api/*` (that is `LARAPILOT_API_TOKEN`) or the MCP server.

Stored as a boolean `true`/`false`; envelope exposes `YES`/`NO`. Missing key → **`NO`**.

| Value | Behavior |
| --- | --- |
| **`false` / `NO`** | **Default.** Dashboard pages require no credentials. |
| **`true` / `YES`** | Every dashboard page requires a username + password from `.larapilot/auth.yaml`. With the setting ON and **no** users configured, the dashboard returns HTTP 500 until a user is added. |

Credentials are argon2id/bcrypt hashes only — no database, no `User` model. `.larapilot/auth.yaml` is added to `.gitignore` automatically and must never be committed. Manage users with `php artisan larapilot:dashboard-user {list|add|remove}` (the `add` action prompts for the password, or takes `--password=`). Failed sign-ins are throttled per IP (`LARAPILOT_DASHBOARD_AUTH_MAX_ATTEMPTS`, default 30/min). Enable the gate with `php artisan larapilot:settings-set --dashboard-auth=YES`. Setup notes: `.larapilot/integrations.md`.

### API auth (`settings.api_auth`) — opt-in, default OFF

Makes `LARAPILOT_API_TOKEN` **mandatory** on every `/larapilot/api/*` request — the JSON API **only**. OFF by default: the token is honoured when set but the read endpoints stay open in the allowed environments when it is not. **Never** gates the `/larapilot` dashboard UI (that is `settings.dashboard_auth`) or the MCP server. The API is never served in `production` regardless.

Stored as a boolean `true`/`false`; envelope exposes `YES`/`NO`. Missing key → **`NO`**.

| Value | Behavior |
| --- | --- |
| **`false` / `NO`** | **Default.** With `LARAPILOT_API_TOKEN` set, every request must carry it (bearer token or `X-Larapilot-Token`). With no token set, reads are open in the allowed environments and mutating requests are refused outside `local`/`development`/`testing`. |
| **`true` / `YES`** | Every request — reads **and** writes — must carry `LARAPILOT_API_TOKEN`. With the setting ON and **no** token configured, the API returns **HTTP 503** (fail-closed) until the token env var is set. |

Enable the gate with `php artisan larapilot:settings-set --api-auth=YES`. Setup notes: `.larapilot/integrations.md`.

### Security scan (`settings.security_scan`) — opt-in, default OFF

Folds a static Laravel security scan into **`/larapilot-review`** and the **pre-ship gate**. Uses the optional dev package [`andreapollastri/checkpoint`](https://github.com/andreapollastri/checkpoint) (`php artisan checkpoint:scan`). Larapilot never installs it and never runs the scanner unless this setting is `YES`.

Stored as a boolean `true`/`false`; envelope exposes `YES`/`NO`. Missing key → **`NO`**.

| Value | Behavior |
| --- | --- |
| **`false` / `NO`** | **Default.** No security scan step; review and ship gates behave exactly as before. |
| **`true` / `YES`** | `/larapilot-review` runs `php artisan checkpoint:scan --json` when the package is present: `FAIL` findings become **review blockers** (resolve or log an explicit decision with `larapilot:decision-log` before `/larapilot-ship`), `WARN` findings become review notes. When the package is **absent**, the skill stops and tells the user to run `composer require --dev andreapollastri/checkpoint`. |

Owned by **Lars** (Security Expert), alongside `dashboard_auth` and `api_auth`. Enable with `php artisan larapilot:settings-set --security-scan=YES`. Setup notes: `.larapilot/integrations.md`.

### Remote forges (`settings.github` / `gitlab` / `bitbucket` / `azure`) — opt-in, default OFF

Optional remote forge integrations. **Orthogonal to `git_mode`**: when all are OFF, Gitflow push/PR rules behave exactly as before. Enable the forge that matches `origin`.

Stored as booleans `true`/`false`; envelope exposes `YES`/`NO`. Missing keys → **`NO`**.

| Setting | Tooling | Status command | When YES |
| --- | --- | --- | --- |
| `github` | `gh` CLI | `larapilot:github-status` | `gh pr create/view`; print PR URL; notify `pr_*` |
| `gitlab` | `glab` CLI | `larapilot:gitlab-status` | `glab mr create/view`; print MR URL; notify `pr_*` |
| `bitbucket` | Bitbucket Cloud REST API (token / app password) | `larapilot:bitbucket-status` | Create/update PR via API; print PR URL; notify `pr_*` |
| `azure` | Azure CLI (`az repos` + `azure-devops` ext) or REST API (PAT) | `larapilot:azure-status` | `az repos pr create/show` (or REST); print PR URL; notify `pr_*` |

Setup steps: `.larapilot/integrations.md`.

### Notifications (`settings.notifications` + channels) — opt-in, default OFF

Master switch plus per-channel toggles. Secrets live only in `.env` (`LARAPILOT_SLACK_WEBHOOK_URL`, `LARAPILOT_DISCORD_WEBHOOK_URL`, `LARAPILOT_TELEGRAM_BOT_TOKEN`, `LARAPILOT_TELEGRAM_CHAT_ID`).

| Setting | Default | Meaning |
| --- | --- | --- |
| `notifications` | `false` / `NO` | Master switch; OFF → `larapilot:notify` no-ops |
| `notify_slack` | `false` / `NO` | Fan-out to Slack webhook when master is ON |
| `notify_discord` | `false` / `NO` | Fan-out to Discord webhook when master is ON |
| `notify_telegram` | `false` / `NO` | Fan-out to Telegram bot when master is ON |

**Hard hooks (Artisan):** `task-done` → `task_done`; `spec-approve` → `spec_done`.

**Skill contract:** when notifications are ON, call `php artisan larapilot:notify --event=… --title=… [--body=…] [--url=…]` for `pr_opened`, `pr_updated`, `spec_review`, `spec_blocked`, `review_changes`, `schedule_drift`, `ship_go`, `ship_nogo`, `security_fail`, and (optionally) `doctor_fail` / `custom`. Never ask for webhook/token values in chat — point at `.larapilot/integrations.md`.

Missing channel credentials → skip that channel with a warning; do not fail the workflow.

