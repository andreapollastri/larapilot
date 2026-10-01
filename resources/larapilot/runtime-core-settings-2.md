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
- **`YES`** — Sarah owns Git mechanics; Jack owns policy. `/larapilot-release` manages the ledger; inception/adopt propose roadmaps; `/larapilot-feature` assigns specs to open releases; `/larapilot-ship` runs the release ship ceremony.

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
### Dashboard auth (`settings.dashboard_auth`) — opt-in, default OFF

Optional HTTP Basic Auth on the `/larapilot` **dashboard UI only**. OFF by default: the dashboard stays open in the allowed environments exactly as before. **Never** gates `/larapilot/api/*` (that is `LARAPILOT_API_TOKEN`) or the MCP server.

- **`NO`** — **Default.** Dashboard pages require no credentials.
- **`YES`** — Every dashboard page requires a username + password from `.larapilot/auth.yaml`. With the setting ON and **no** users configured, the dashboard returns HTTP 500 until a user is added.

Credentials are argon2id/bcrypt hashes only — no database, no `User` model. `.larapilot/auth.yaml` is added to `.gitignore` automatically and must never be committed. Manage users with `php artisan larapilot:dashboard-user {list|add|remove}` (the `add` action prompts for the password, or takes `--password=`). Failed sign-ins are throttled per IP (`LARAPILOT_DASHBOARD_AUTH_MAX_ATTEMPTS`, default 30/min). Enable the gate with `php artisan larapilot:settings-set --dashboard-auth=YES`. Setup notes: `.larapilot/integrations.md`.

### API auth (`settings.api_auth`) — opt-in, default OFF

Makes `LARAPILOT_API_TOKEN` **mandatory** on every `/larapilot/api/*` request — the JSON API **only**. OFF by default: the token is honoured when set but the read endpoints stay open in the allowed environments when it is not. **Never** gates the `/larapilot` dashboard UI (that is `settings.dashboard_auth`) or the MCP server. The API is never served in `production` regardless.

- **`NO`** — **Default.** With `LARAPILOT_API_TOKEN` set, every request must carry it (bearer token or `X-Larapilot-Token`). With no token set, reads are open in the allowed environments and mutating requests are refused outside `local`/`development`/`testing`.
- **`YES`** — Every request — reads **and** writes — must carry `LARAPILOT_API_TOKEN`. With the setting ON and **no** token configured, the API returns **HTTP 503** (fail-closed) until the token env var is set.

Enable the gate with `php artisan larapilot:settings-set --api-auth=YES`. Setup notes: `.larapilot/integrations.md`.
<!-- end -->

<!-- when: security_scan=YES -->
### Security scan (`settings.security_scan`) — opt-in, default OFF

Folds a static Laravel security scan into **`/larapilot-review`** and the **pre-ship gate**. Uses the optional dev package [`andreapollastri/checkpoint`](https://github.com/andreapollastri/checkpoint) through `php artisan larapilot:checkpoint-scan`, which keeps the result for the dashboard (**Security → Checkpoint**). Larapilot never installs it; the setting makes review and ship run it, and otherwise it runs only when someone asks (the command, or *Run the scan* on the dashboard).

<!-- when: view=full -->
- **`NO`** — **Default.** No security scan step; review and ship gates behave exactly as before.
<!-- end -->
- **`YES`** — `/larapilot-review` runs `php artisan larapilot:checkpoint-scan` when the package is present: `FAIL` findings become **review blockers** (resolve or log an explicit decision with `larapilot:decision-log` before `/larapilot-ship`), `WARN` findings become review notes. When the package is **absent**, the skill stops and tells the user to run `composer require --dev andreapollastri/checkpoint`.

Owned by **Lars** (Security Expert), alongside `dashboard_auth` and `api_auth`.

<!-- when: view=full -->
Enable with `php artisan larapilot:settings-set --security-scan=YES`. Setup notes: `.larapilot/integrations.md`.
<!-- end -->
<!-- end -->

<!-- when: aikido=YES -->
### Aikido (`settings.aikido`) — opt-in, default OFF

Reads the findings of [Aikido](https://www.aikido.dev/) for this repository. **Aikido scans on its side**, through its connection to the git provider; Larapilot runs no scanner: it reads the result over the public REST API, with `LARAPILOT_AIKIDO_CLIENT_ID` and `LARAPILOT_AIKIDO_CLIENT_SECRET` from `.env`, and tells Aikido what the user decided.

<!-- when: view=full -->
- **`NO`** — **Default.** Aikido is never called.
<!-- end -->
- **`YES`** — **`/larapilot-aikido`** downloads the open findings, confirms them with the user, plans resolution groups with `larapilot:aikido-plan`, and hands each group to `/larapilot-triage`. **`/larapilot-ship`** runs `larapilot:aikido-issues --gate`: `FAIL` when a finding at `LARAPILOT_AIKIDO_FAIL_ON` (default `high`) or above is open and not waived, `WARN` when only lower ones are. `/larapilot/security` shows them.

<!-- when: view=full -->
| Command | What it does |
| --- | --- |
| `larapilot:aikido-status` | Setting, credentials, repository, last scan, `hints` |
| `larapilot:aikido-issues [--new] [--severity=] [--report] [--gate]` | The open findings with `state` `new` · `in_backlog` · `waived`, and `gate.verdict` |
| `larapilot:aikido-plan --ids=24,31` | Groups confirmed ids by kind and fix for triage (read-only; secrets never merge) |
| `larapilot:aikido-link {ids} --spec=US-XXX` · `--waive --reason="…"` · `--forget` | What was decided, kept in `.larapilot/aikido.yaml` (committed: ids and decisions, never a credential) and **told to Aikido** (`issues:write`): a waiver ignores the finding there with its reason, a spec leaves a note, `--forget` takes the waiver back. `--local` tells nothing |
| `larapilot:aikido-push` | Tells Aikido the decisions it was not told (`unsent`) |
| `larapilot:aikido-repos [--search=]` · `--use={id}` · `--forget` | The repositories of the workspace, and the one this project is when the git remote finds none (`needs_repository`). **Ask the user; never choose** |
| `larapilot:aikido-register` | `{paths.security}/aikido-register.md` for the client: every finding open, resolved, or ignored with its reason, in the PRD language |
| `larapilot:aikido-scan` | Asks Aikido for a new scan (`repositories:write`) |
<!-- end -->

A finding `in_backlog` is **not fixed**: it stops the gate until Aikido no longer reports it. Only the user waives a finding, with a reason. Aikido refusing a decision never undoes it: it stays `unsent`. Owned by **Lars**. The commands are in `/larapilot-aikido`. Setup notes: `.larapilot/integrations.md` → **Aikido**.
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
