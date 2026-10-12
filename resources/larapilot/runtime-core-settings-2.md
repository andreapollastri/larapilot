## Project Settings — opt-in toggles

<!-- when: view=full -->
Continuation of **Project Settings** (`.larapilot/runtime-core-settings.md`). These toggles default to OFF: each is stored as a boolean `true`/`false`, the envelope exposes `YES`/`NO`, and a missing key is `NO`. A skill is given only the sections of the toggles that are `YES`.
<!-- end -->

<!-- when: release_mode=YES -->
### Release mode (`settings.release_mode`) — opt-in, default OFF

Semver **release ledger** (`.larapilot/releases.yaml`) plus optional Gitflow **`release/x.y.z`** branches when `git_mode` is `GITFLOW` or `GITFLOW_PUSH`. Full contract: `.larapilot/runtime-release.md`.

<!-- when: view=full -->
- **`NO`** — **Default.** Ignore release ledger, release branches, and release AskQuestion rounds — classic `develop` / `feature/US-XXX-*` flow only.
<!-- end -->
- **`YES`** — Sarah owns Git mechanics; Jack owns policy. `/larapilot-release` manages the ledger; inception/adopt propose roadmaps; `/larapilot-feature`, `/larapilot-spec`, and `/larapilot-bug` give every story a version; implement stops on a story with none; review and autopilot report the version; `/larapilot-ship` ships a version only with its open stories settled and its release notes kept (`.larapilot/runtime-versions.md`). The dashboard gains the **Versions** page and a version scope on the Board, the Plan, and the Forecast.

<!-- when: view=full -->
Enable with `php artisan larapilot:settings-set --release-mode=YES`.
<!-- end -->
<!-- end -->

<!-- when: project_docs=YES -->
### Project docs (`settings.project_docs`) — opt-in, default OFF

Living technical + functional handbook under **`.larapilot/docs/handbook/`** (path key `paths.project_docs`). Albert owns structure; every material change updates the relevant chapter. Full contract: `.larapilot/runtime-project-docs.md`.

<!-- when: view=full -->
- **`NO`** — **Default.** No handbook maintenance obligation; the folder holds only its README stub.
<!-- end -->
- **`YES`** — After each implement/review/ship (and on enable mid-project), update the handbook; bootstrap retroactively from PRD, specs, plans, and git history when not enabled from day 1.

<!-- when: view=full -->
Enable with `php artisan larapilot:settings-set --project-docs=YES`.
<!-- end -->
<!-- end -->

<!-- when: comments=YES -->
### Comments (`settings.comments`) — opt-in, default OFF

Internal feedback comments on the dashboard spec page, `POST /larapilot/api/specs/{code}/comments`, and `larapilot:spec-comment`.

<!-- when: view=full -->
- **`NO`** — **Default.** Comments disabled project-wide: the feedback UI, API writes, and CLI command are off (existing `.larapilot/internal-feedback/*.md` files stay readable). Optional env kill-switch: `LARAPILOT_COMMENTS_ENABLED=false`.
<!-- end -->
- **`YES`** — PM/dev can append comments until the spec is DONE; blocking comments feed `spec-request-changes --include-feedback`.

<!-- when: view=full -->
Enable with `php artisan larapilot:settings-set --comments=YES`. Details: `.larapilot/internal-feedback/README.md`.
<!-- end -->
<!-- end -->

<!-- when: view=full -->
### Dashboard sign-in — follows `APP_ENV`, not a setting

There is no `dashboard_auth` setting. Access follows `APP_ENV`: **`local`** is open; any other environment but `production` asks for a user of `.larapilot/auth.yaml` (authenticator optional); **`production`** serves nothing unless `LARAPILOT_FORCE_IN_PROD=true`, then password **and** authenticator for everyone. Roles: `admin` everything · `dev` all but Users, API token, file manager, secrets (database anonymized in production) · `pm` Workflow, Insights, Reference (logs outside production) · `ext` Workflow only. Users: `larapilot:dashboard-user {list|add|role|password|rename|totp-reset|remove}` or the Users page; no sign-up, no password reset. No user yet → HTTP 503 with the command for the first admin. `auth.yaml` is git-ignored. Setup notes: `.larapilot/integrations.md`.

### API auth (`settings.api_auth`) — opt-in, default OFF

The token of `/larapilot/api/*` is made by an admin on the **API token** page or with `larapilot:api-token {show|generate|rotate|revoke}` (shown once, kept hashed in `auth.yaml`), or set as `LARAPILOT_API_TOKEN`. Once one exists every request needs it (bearer or `X-Larapilot-Token`), reads included; a dashboard user whose role sees the API reads from the browser without it. With none: `local` is open, any other environment answers **HTTP 503** with how to make one.

- **`NO`** — **Default.** As above.
- **`YES`** — `local` too needs the token, and answers 503 until one exists.

`php artisan larapilot:settings-set --api-auth=YES`. Setup notes: `.larapilot/integrations.md`.
<!-- end -->

<!-- when: security_scan=YES -->
### Security scan (`settings.security_scan`) — opt-in, default OFF

Folds a static Laravel security scan into **`/larapilot-review`** and the **pre-ship gate**. Uses the optional dev package [`andreapollastri/checkpoint`](https://github.com/andreapollastri/checkpoint) through `php artisan larapilot:checkpoint-scan`, which keeps the result for the dashboard (**Security → Checkpoint**). Larapilot never installs it; the setting makes review and ship run it, and otherwise it runs only when someone asks (the command, or *Run the scan* on the dashboard).

<!-- when: view=full -->
- **`NO`** — **Default.** No security scan step; review and ship gates behave exactly as before.
<!-- end -->
- **`YES`** — `/larapilot-review` runs `php artisan larapilot:checkpoint-scan` when the package is present: `FAIL` findings become **review blockers** (resolve or log an explicit decision with `larapilot:decision-log` before `/larapilot-ship`), `WARN` findings become review notes. When the package is **absent**, the skill stops and tells the user to run `composer require --dev andreapollastri/checkpoint`.

Owned by **Lars** (Security Expert), alongside the dashboard sign-in and `api_auth`.

<!-- when: view=full -->
Enable with `php artisan larapilot:settings-set --security-scan=YES`. Setup notes: `.larapilot/integrations.md`.
<!-- end -->
<!-- end -->

<!-- when: security=YES -->
### Security findings (`settings.security` + `settings.security_provider`) — opt-in, default OFF

Reads the open findings of this repository from **one scanner the user associates**: **Aikido** (SaaS, `LARAPILOT_AIKIDO_CLIENT_ID` / `_CLIENT_SECRET`) or **Hackly** (self-hosted, https://hackly.web.ap.it — `LARAPILOT_HACKLY_URL` of **their own instance** and `LARAPILOT_HACKLY_TOKEN`; Hackly also reads the domains linked to the repository). The scanner scans on its side; Larapilot runs none, reads the result, and tells it what the user decided. `--security-provider=aikido|hackly|none` (the old `--aikido=YES` is `aikido`).

- **`YES`** — **`/larapilot-security`** downloads, confirms, groups with `security-plan`, hands each group to `/larapilot-triage`. **`/larapilot-ship`** runs `security-issues --gate`: `FAIL` when a finding at the fail-on level (`LARAPILOT_{AIKIDO|HACKLY}_FAIL_ON`, default `high`) is open and not waived, `WARN` below. `/larapilot/security` shows them.

<!-- when: view=full -->
| Command (`larapilot:`) | What it does |
| --- | --- |
| `security-status` | Scanner, credentials, repository, `hints` |
| `security-issues [--new] [--severity=] [--report] [--gate] [--detail={id}]` | Open findings, `state` `new`/`in_backlog`/`waived`/`false_positive`, `gate.verdict`; `--detail` = brief + fix prompt |
| `security-plan --ids=…` | Groups confirmed ids by kind and fix (a leaked secret never merges) |
| `security-link {ids} --spec=US-XXX` · `--waive --reason=` · `--false-positive --reason=` · `--forget` | Kept in `.larapilot/aikido.yaml` / `hackly.yaml` (committed) and told to the scanner; `--local` tells nothing |
| `security-push` · `security-repos [--use=]` · `security-scan [--profile=]` · `security-register` | Unsent decisions · which repository (**ask the user; never choose**) · a new scan · the register for the client |
<!-- end -->

Ids are numbers in Aikido, UUIDs in Hackly. A finding `in_backlog` stops the gate until the scanner no longer reports it. Only the user waives. `aikido-*` commands keep working. Owned by **Lars**. Setup notes: `.larapilot/integrations.md` → **Security scanners**.
<!-- end -->

<!-- when: errors=YES -->
### Production errors (`settings.errors` + `settings.errors_provider`) — opt-in, default OFF

Reads the open errors of the running application from **one** tracker and brings each bug into triage. `errors_provider` names it: **boogle** (self-hosted, the default), **sentry**, **bugsnag**, **flare**, **datadog**, **rollbar**, **honeybadger**, **cloudwatch**. Credentials live in `.env`: a token that **reads** the tracker, never the key the application reports with.

<!-- when: view=full -->
- **`errors` = `NO`** — **Default.** No tracker is called. `errors_provider` is kept for when it is turned on again. **`/larapilot-error`** asks which tracker, Boogle included, and turns the errors on.
<!-- end -->
- **`errors` = `YES`** — **`/larapilot-error`** downloads the open errors, has the user confirm them, runs **`errors-plan`** on the codes to fix, and hands **each group** to **`/larapilot-triage`**. Writes `{paths.support}/errors.md`. **`/larapilot-ship`** names the errors with no decision as a note. `/larapilot/errors` shows them.

<!-- when: view=full -->
`settings.boogle` is the name `errors` had when Boogle was the only tracker: `--boogle=YES` is `--errors=YES --errors-provider=boogle`, and the key is kept in step with the two.

| Command | What it does |
| --- | --- |
| `larapilot:settings-set --errors=YES --errors-provider=sentry` | Turns the errors on and names the tracker |
| `larapilot:errors-status` | Setting, tracker, credentials, project, `remote_resolve`, `hints` |
| `larapilot:errors-list [--new] [--kind=error\|outage] [--limit=] [--report]` | The open errors with `state` `new` · `in_backlog` · `ignored`, and `returned` when one came back after its fix |
| `larapilot:errors-plan --codes=BUG12,BUG21` | Groups the confirmed codes by domain and place for triage (read-only; outages and packages never merge with app code) |
| `larapilot:errors-link {codes} --spec=US-XXX` · `--ignore --reason="…"` · `--forget` | What was decided, kept in `.larapilot/boogle.yaml` (committed: class, place in the code, decision) |
| `larapilot:errors-resolve {codes}` | **Writes to the tracker:** closes the error there. Only when the fix is released and the user says so; refused where nothing can be closed (CloudWatch, the logs of Datadog) |

The commands are the same for every tracker; `boogle-status`, `boogle-errors`, `boogle-plan`, `boogle-link`, and `boogle-resolve` are their old names and still answer.
<!-- end -->

**Personal data stays in the tracker.** The user, the query string, and the payload of a request are never read into a file, a report, or the chat; an address in a message is masked. Never ask for them. Owned by **Sophia**. The commands are in `/larapilot-error`. Setup notes: `.larapilot/integrations.md` → **Production errors**.
<!-- end -->

<!-- when: forge=YES -->
### Remote forges (`settings.github` / `gitlab` / `bitbucket` / `azure`) — opt-in, default OFF

Optional remote forge integrations. **Orthogonal to `git_mode`**: when all are OFF, Gitflow push/PR rules behave exactly as before. Enable the forge that matches `origin`. With one ON: probe its status command when unsure, open or update the PR/MR after a push, **always print its URL**, and notify `pr_opened` / `pr_updated` when notifications are ON.

<!-- when: github=YES -->
- **`github`** — `gh` CLI · `larapilot:github-status` · `gh pr create/view`.
<!-- end -->
<!-- when: gitlab=YES -->
- **`gitlab`** — `glab` CLI · `larapilot:gitlab-status` · `glab mr create/view`.
<!-- end -->
<!-- when: bitbucket=YES -->
- **`bitbucket`** — Bitbucket Cloud REST API (token / app password) · `larapilot:bitbucket-status` · create or update the PR via the API.
<!-- end -->
<!-- when: azure=YES -->
- **`azure`** — Azure CLI (`az repos` + `azure-devops` ext) or REST API (PAT) · `larapilot:azure-status` · `az repos pr create/show` (or REST).
<!-- end -->

Setup steps: `.larapilot/integrations.md`.
<!-- end -->

<!-- when: notifications=YES -->
### Notifications (`settings.notifications` + channels) — opt-in, default OFF

Master switch plus per-channel toggles (`notify_slack`, `notify_discord`, `notify_telegram`). Secrets live only in `.env` (`LARAPILOT_SLACK_WEBHOOK_URL`, `LARAPILOT_DISCORD_WEBHOOK_URL`, `LARAPILOT_TELEGRAM_BOT_TOKEN`, `LARAPILOT_TELEGRAM_CHAT_ID`).

<!-- when: view=full -->
| Setting | Default | Meaning |
| --- | --- | --- |
| `notifications` | `false` / `NO` | Master switch; OFF → `larapilot:notify` no-ops |
| `notify_slack` | `false` / `NO` | Fan-out to Slack webhook when master is ON |
| `notify_discord` | `false` / `NO` | Fan-out to Discord webhook when master is ON |
| `notify_telegram` | `false` / `NO` | Fan-out to Telegram bot when master is ON |
<!-- end -->

**Hard hooks (Artisan):** `task-done` → `task_done`; `spec-approve` → `spec_done`.

**Skill contract:** call `php artisan larapilot:notify --event=… --title=… [--body=…] [--url=…]` for `pr_opened`, `pr_updated`, `spec_review`, `spec_blocked`, `review_changes`, `schedule_drift`, `ship_go`, `ship_nogo`, `security_fail`, and (optionally) `doctor_fail` / `custom`. Never ask for webhook/token values in chat — point at `.larapilot/integrations.md`.

Missing channel credentials → skip that channel with a warning; do not fail the workflow.
<!-- end -->
