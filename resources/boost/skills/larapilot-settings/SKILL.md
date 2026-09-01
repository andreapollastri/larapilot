---
name: larapilot-settings
description: Configure persistent Larapilot project settings (effort, backlog granularity, git mode, testing, auto-approve, lucille, decision journal, code change history, dashboard auth, API auth, security scan, GitHub/GitLab/Bitbucket/Azure DevOps, notifications) via AskQuestion. Use when the user runs /larapilot-settings, wants to change token economy, backlog/spec granularity, Gitflow/push behavior, test depth, auto-approve, Lucille, the decision journal / regression guard, per-task file+line history, dashboard login/password, the /larapilot/api token gate, the checkpoint security scan in review/ship, remote forge, or Slack/Discord/Telegram notifications. Italian triggers include "impostazioni larapilot", "settings", "modalità eco", "granularità backlog", "meno specs", "gitflow push", "autoapprove", "disattiva Lucille", "escludi Lucille", "traccia le decisioni", "storico decisioni", "evita regressioni", "storico modifiche codice", "file e righe modificate", "proteggi la dashboard", "password dashboard", "login dashboard", "utenti dashboard", "proteggi le api", "token api", "autenticazione api", "scan di sicurezza", "controlli di sicurezza", "checkpoint", "notifiche slack", "telegram", "discord", "github", "gitlab", "bitbucket", "azure devops".
---

# Larapilot — Project Settings

Persist project-wide Larapilot settings into `.larapilot/config.yaml`. All other skills read and honor them.

## Shared Runtime

Read `.larapilot/shared-runtime.md` — **Project Settings** (effort, backlog, git mode, testing, auto_approve, lucille, decision_log, code_history, dashboard_auth, api_auth, security_scan, github, gitlab, bitbucket, azure, notifications). Bot/webhook/forge setup: `.larapilot/integrations.md`.

## Output Economy

**High** — short confirmations only. AskQuestion carries the options; chat stays terse. Still honor Zoey's start/end **Context estimate** lines from shared-runtime.

## The Team

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — frames trade-offs (tokens vs depth, human gate vs auto-approve) and confirms persistence |
| 💎 **Mark** | Product Manager — owns backlog granularity implications (spec/epic count vs traceability) |
| 🚀 **Jack** | DevOps — owns git_mode and optional GitHub / GitLab / Bitbucket / Azure DevOps integrations |
| ⌨️ **Sarah** | CLI / Git / Linux — involved when forge/CI automation or Git mechanics guidance is needed |
| 🧪 **Anne** | Test Architect — owns testing mode implications |
| 🛡️ **Robert** | Code Reviewer — owns auto_approve risk framing |
| 📒 **Lucille** | Project tracking — owns the lucille on/exclude setting; default is always ON |
| 🔐 **Lars** | Security Expert — owns the `dashboard_auth` toggle + dashboard users (`larapilot:dashboard-user`), the `api_auth` toggle (`LARAPILOT_API_TOKEN` on `/larapilot/api/*`) **and** the `security_scan` toggle (`andreapollastri/checkpoint` in review/ship) |
| 🔗 **Matt** | Integration Manager — owns Slack/Discord/Telegram notification toggles (secrets stay in `.env`) |

## Config & CLI

1. `php artisan larapilot:config-show` — read current `data.settings`
2. After answers: `php artisan larapilot:settings-set` with the answered flags
3. Re-run `config-show` and confirm the saved values
4. Optional probes: `larapilot:github-status`, `larapilot:gitlab-status`, `larapilot:bitbucket-status`, `larapilot:azure-status`, `larapilot:notify --event=custom --title="Larapilot test"`
5. Dashboard auth users: `php artisan larapilot:dashboard-user {list|add <username>|remove <username>}` — `add` prompts for the password (or takes `--password=`); credentials hash into `.larapilot/auth.yaml` (git-ignored)

Never edit `.larapilot/config.yaml` by hand from the skill — always use `larapilot:settings-set`. **Never ask for webhook/token/password values in chat** — point at `.larapilot/integrations.md` and `.env`, and let `larapilot:dashboard-user` prompt for the dashboard password.

## Workflow

### 0. Load current settings

Run `config-show`. Show one line with current values:

`effort={…} · backlog={…} · git_mode={…} · testing={…} · auto_approve={…} · lucille={…} · decision_log={…} · code_history={…} · dashboard_auth={…} · api_auth={…} · security_scan={…} · github={…} · gitlab={…} · bitbucket={…} · azure={…} · notifications={…}`

If `.larapilot/config.yaml` is missing, suggest `php artisan larapilot:install` first (settings-set will scaffold defaults if needed, but install is preferred).

### 1. AskQuestion (Zoey — max 3 per round)

Use **AskQuestion**; persona intro in chat; options only in the tool. Mark the **current** value in each prompt when known.
Copy the **AskQuestion prompt** and **option labels** below as closely as possible — do **not** invent shorter cryptic labels.

**Round 1 — Effort, Backlog, Git**

**1. Effort** — how hard Larapilot works (tokens & depth)

- **AskQuestion prompt:** `Effort (current: {VALUE}) — how deep should Larapilot work?`
- **Chat framing (one line):** Zoey — tokens vs thoroughness.

| Option id | AskQuestion label |
| --- | --- |
| `ECO` | `ECO — save tokens: no sub-agents, disables Lucille (re-enable via settings), lighter docs (OpenAPI still when APIs change), skip deep/E2E` |
| `STANDARD` | `STANDARD — normal depth (default)` |
| `MAX` | `MAX — deep on every flow: fuller personas, sub-agents, richer plans/reviews` |

Warn once when the user picks `ECO`: **Lucille will be disabled automatically** (`lucille=NO`). She can be re-enabled later with `/larapilot-settings` or `php artisan larapilot:settings-set --lucille=YES` without leaving ECO. Do not pass `--lucille=YES` in the same persist unless the user explicitly asks to keep Lucille on in ECO.

**2. Backlog** — how finely Mark slices the product into specs & epics (`larapilot-spec` / `feature` / `bug`)

- **AskQuestion prompt:** `Backlog granularity (current: {VALUE}) — how many specs for the same product scope? (coverage stays the same; only slicing changes)`
- **Chat framing (one line):** 💎 Mark — same FRs either way; this only controls how many US-XXX files and epics you get.

| Option id | AskQuestion label |
| --- | --- |
| `LEAN` | `LEAN — fewest specs: one per end-to-end journey; merge related FRs; seams/admin/i18n stay plan tasks; ≤ 5 epics` |
| `STANDARD` | `STANDARD — one spec per user capability (default); related FRs may share a spec; models/UI/API are plan tasks, not separate specs` |
| `GRANULAR` | `GRANULAR — fine-grained: one spec per FR OK; split by seam / admin entity / locale; more epics — for large teams or one-PR-per-spec` |

**3. Git mode** — branching & remote discipline

- **AskQuestion prompt:** `Git mode (current: {VALUE}) — branching and push behavior`
- **Chat framing (one line):** 🚀 Jack — push/PR remote updates only with `GITFLOW_PUSH`.

| Option id | AskQuestion label |
| --- | --- |
| `NO_GITFLOW` | `NO_GITFLOW — stay on current branch; commits only, no feature-branch/PR ceremony` |
| `GITFLOW` | `GITFLOW — feature/US-XXX-* + atomic commits + PR prepared locally; no auto-push (default)` |
| `GITFLOW_PUSH` | `GITFLOW_PUSH — same as GITFLOW, plus push and open/update PR toward develop after each task` |

**Round 2 — Testing, Auto-approve**

**4. Testing** — Anne's bar for plan/implement/review

- **AskQuestion prompt:** `Testing (current: {VALUE}) — how deep should automated tests go?`
- **Chat framing (one line):** 🧪 Anne — browser/E2E only in `BEST`.

| Option id | AskQuestion label |
| --- | --- |
| `MINIMAL` | `MINIMAL — critical-path Pest/PHPUnit only; no browser/E2E` |
| `NORMAL` | `NORMAL — feature/unit/policy/API tests + review evidence; no Playwright/Dusk/E2E (default)` |
| `BEST` | `BEST — full automation: E2E (Playwright/Dusk), viewport matrix, axe, Lighthouse when applicable` |

**5. Auto-approve** — skip the human DONE gate after implement (mainly `/larapilot-autopilot`)

- **AskQuestion prompt:** `Auto-approve (current: {VALUE}) — may autopilot mark specs DONE without your Approve?`
- **Chat framing (one line):** 🛡️ Robert — `YES` bypasses the usual human DONE gate.

| Option id | AskQuestion label |
| --- | --- |
| `NO` | `NO — always wait for human Approve / Request changes (default)` |
| `YES` | `YES — after implement reaches REVIEW, autopilot may spec-approve from a short checklist` |

Warn once when the user picks `YES`: this bypasses the usual human-in-the-loop DONE gate.

**Round 3 — Lucille + integrations**

**6. Lucille · Project tracking** — usage ledger + schedule at every skill level (default ON; exclusion must be explicit)

- **AskQuestion prompt:** `Lucille · Project tracking (current: {VALUE}) — keep time/token/deadline tracking on every skill?`
- **Chat framing (one line):** 📒 Lucille — Project tracking ON by default everywhere; choose NO only to exclude her explicitly.

| Option id | AskQuestion label |
| --- | --- |
| `YES` | `YES — Lucille Project tracking on every skill (default): log tokens/hours, deadlines, epics Gantt, /larapilot-usage` |
| `NO` | `NO — explicit exclusion: no usage-log, no Lucille interview rounds (historical ledger stays readable)` |

Warn once when the user picks `NO`: this opts out of project time/token metrics until they set `YES` again. Note: choosing **Effort = ECO** also sets Lucille to `NO` automatically — same re-enable path (`--lucille=YES`).

**6b. Decision journal & Code change history**

- **Decision journal prompt:** `Decision journal (current: {VALUE}) — record every explicit choice you make and warn you before a later choice contradicts an earlier one?`
- **Code history prompt:** `Code change history (current: {VALUE}) — keep a per-spec/task log of which files and lines were changed?`
- **Chat framing (one line):** 📒 Lucille — the journal (`.larapilot/decisions.yaml`) is ON by default and powers the regression guard; the code log (`.larapilot/code-history.yaml`) is OFF by default and needs git commits.

| Setting | YES label | NO label |
| --- | --- | --- |
| `decision_log` | `YES — journal AskQuestion answers + explicit directives; decision-check flags contradictions (default)` | `NO — do not record decisions or run the regression guard` |
| `code_history` | `YES — after each task-done, log touched files + line ranges from the task commit` | `NO — no code change history (default)` |

**7. Remote forge** — optional GitHub / GitLab / Bitbucket / Azure DevOps (each default OFF; orthogonal to git_mode)

Ask only the forge(s) that match the user's remote (skip others or leave NO).

- **GitHub prompt:** `GitHub (current: {VALUE}) — use gh CLI for remote PRs?`
- **GitLab prompt:** `GitLab (current: {VALUE}) — use glab CLI for merge requests?`
- **Bitbucket prompt:** `Bitbucket (current: {VALUE}) — use Bitbucket Cloud API tokens for PRs?`
- **Azure DevOps prompt:** `Azure DevOps (current: {VALUE}) — use az CLI / PAT for Azure Repos PRs?`
- **Chat framing (one line):** 🚀 Jack — OFF by default; see `.larapilot/integrations.md`.

| Setting | YES label |
| --- | --- |
| `github` | `YES — gh pr create/view; print PR URL; notify pr_*` |
| `gitlab` | `YES — glab mr create/view; print MR URL; notify pr_*` |
| `bitbucket` | `YES — Bitbucket REST API with access token or app password; print PR URL; notify pr_*` |
| `azure` | `YES — az repos pr create/show (or Azure DevOps REST with a PAT); print PR URL; notify pr_*` |

**7b. Dashboard auth** — optional HTTP Basic Auth on the `/larapilot` dashboard **UI** (default OFF)

- **AskQuestion prompt:** `Dashboard auth (current: {VALUE}) — require a username + password to open the /larapilot dashboard?`
- **Chat framing (one line):** 🔐 Lars — UI-only gate; never touches `/larapilot/api/*` (that's `LARAPILOT_API_TOKEN`) or MCP. Credentials are hashed in `.larapilot/auth.yaml` (git-ignored).

| Option id | AskQuestion label |
| --- | --- |
| `NO` | `NO — dashboard open in the allowed environments (default)` |
| `YES` | `YES — HTTP Basic Auth; users managed via php artisan larapilot:dashboard-user` |

When the user picks `YES`, remind once: they must create at least one user, or the dashboard returns HTTP 500. Never collect the password in chat — tell them to run `php artisan larapilot:dashboard-user add <username>` (it prompts securely) or pass `--password=`. Setup notes: `.larapilot/integrations.md`.

**7c. API auth** — make `LARAPILOT_API_TOKEN` mandatory on every `/larapilot/api/*` request (default OFF)

- **AskQuestion prompt:** `API auth (current: {VALUE}) — require the LARAPILOT_API_TOKEN shared token on every /larapilot/api/* request (reads + writes, diagnostics included)?`
- **Chat framing (one line):** 🔐 Lars — JSON-API-only gate; never touches the dashboard UI or MCP. Token lives in `.env`, not `.larapilot/`.

| Option id | AskQuestion label |
| --- | --- |
| `NO` | `NO — token honoured when set; reads open in dev/staging when it is not (default)` |
| `YES` | `YES — every /larapilot/api/* call needs the token; API fails closed (HTTP 503) when LARAPILOT_API_TOKEN is unset` |

When the user picks `YES`, remind once: set `LARAPILOT_API_TOKEN` in `.env` (dev/staging) or the API returns HTTP 503. Never collect the token value in chat. Client callers send it as `Authorization: Bearer <token>` or `X-Larapilot-Token: <token>`. Setup + client examples: `.larapilot/integrations.md` → **API access**.

**7d. Security scan** — run `andreapollastri/checkpoint` inside `/larapilot-review` and the pre-ship gate (default OFF)

- **AskQuestion prompt:** `Security scan (current: {VALUE}) — run the checkpoint static security scan during /larapilot-review and before ship?`
- **Chat framing (one line):** 🔐 Lars — optional dev package (`composer require --dev andreapollastri/checkpoint`); FAIL findings block review until fixed or waived via `larapilot:decision-log`.

| Option id | AskQuestion label |
| --- | --- |
| `NO` | `NO — no security scan step (default)` |
| `YES` | `YES — /larapilot-review runs php artisan checkpoint:scan; FAIL = blocker, WARN = review note` |

When the user picks `YES`, remind once: the scanner is not bundled — run `composer require --dev andreapollastri/checkpoint` in the target app (if missing, `/larapilot-review` will stop and ask for it). Setup notes: `.larapilot/integrations.md` → **Security scan**.

**8. Notifications** — master switch (default OFF)

- **AskQuestion prompt:** `Notifications (current: {VALUE}) — enable chat alerts (Slack/Discord/Telegram)?`
- **Chat framing (one line):** 🔗 Matt — OFF by default; secrets stay in `.env`.

| Option id | AskQuestion label |
| --- | --- |
| `NO` | `NO — no chat fan-out (default)` |
| `YES` | `YES — enable notifications master switch (configure channels next)` |

If notifications = `YES`, ask channels in the same round (or next if at max):

**9–11. Channels** — each YES/NO, default NO. Labels:

| Setting | AskQuestion prompt | YES label |
| --- | --- | --- |
| `notify_slack` | `Slack (current: {VALUE})` | `YES — Incoming Webhook → LARAPILOT_SLACK_WEBHOOK_URL` |
| `notify_discord` | `Discord (current: {VALUE})` | `YES — Channel webhook → LARAPILOT_DISCORD_WEBHOOK_URL` |
| `notify_telegram` | `Telegram (current: {VALUE})` | `YES — BotFather token + chat_id → LARAPILOT_TELEGRAM_*` |

When any channel is YES, remind once: configure env vars per `.larapilot/integrations.md` — do not paste secrets into chat. Suggest a test: `php artisan larapilot:notify --event=custom --title="Larapilot test"`.

Defaults when unset: `STANDARD` / `STANDARD` / `GITFLOW` / `NORMAL` / `NO` / **`YES` (lucille)** / **`YES` (decision_log)** / **`NO` (code_history)** / **`NO` (dashboard_auth)** / **`NO` (api_auth)** / **`NO` (security_scan)** / **`NO` (github/gitlab/bitbucket/azure)** / **`NO` (notifications + channels)**.  
(`config.yaml` stores booleans; `config-show` / CLI envelopes expose `YES` | `NO`. Missing `lucille` / `decision_log` → YES; missing `code_history` / `dashboard_auth` / `api_auth` / `security_scan` / forge / notifications → NO.)

### 2. Persist

Map AskQuestion answers to CLI flags (normalize spaces/hyphens; `SI` → `YES`; `EXCLUDE` → `NO` for lucille):

```bash
php artisan larapilot:settings-set \
  --effort=STANDARD \
  --backlog=STANDARD \
  --git-mode=GITFLOW \
  --testing=NORMAL \
  --auto-approve=NO \
  --lucille=YES \
  --decision-log=YES \
  --code-history=NO \
  --dashboard-auth=NO \
  --api-auth=NO \
  --security-scan=NO \
  --github=NO \
  --gitlab=NO \
  --bitbucket=NO \
  --azure=NO \
  --notifications=NO \
  --notify-slack=NO \
  --notify-discord=NO \
  --notify-telegram=NO
```

Pass only the keys the user answered. On success, parse the JSON envelope (`kind: "settings"`) and confirm:

`Saved → effort=… · backlog=… · git_mode=… · testing=… · auto_approve=… · lucille=… · decision_log=… · code_history=… · dashboard_auth=… · api_auth=… · security_scan=… · github=… · gitlab=… · bitbucket=… · azure=… · notifications=…`  
`Path: data.config_path` (or `.larapilot/config.yaml`)

If `data.lucille_disabled_by_eco` is true (or effort was just set to ECO without an explicit lucille flag), state once: **Lucille disabled by ECO** — re-enable with `php artisan larapilot:settings-set --lucille=YES`.

If a forge is YES, optionally run the matching `larapilot:{github,gitlab,bitbucket,azure}-status` and surface `ready` / `hints`.

### 3. Next steps

Remind once (one line): other skills honor these on next run via `config-show` → `data.settings`.

## Rules

- Do not change PRD, backlog, or code — settings only
- Do not re-ask unanswered skippable questions; keep previous values for skipped keys
- If the user wants a single setting changed, AskQuestion only that dimension
- Never invent persistence — CLI only
- Never collect Slack/Discord/Telegram secrets or the dashboard password in chat — `larapilot:dashboard-user add` prompts for it
- `dashboard_auth` gates the dashboard **UI only**; `api_auth` gates the **JSON API only** (`/larapilot/api/*`, `LARAPILOT_API_TOKEN`) — neither affects the other surface, and neither touches MCP
- `security_scan` only wires `andreapollastri/checkpoint` into `/larapilot-review` + pre-ship — it never installs the package and never runs `checkpoint:scan` on its own; when ON and the package is absent, `/larapilot-review` stops and asks for `composer require --dev andreapollastri/checkpoint`
- Never collect the `LARAPILOT_API_TOKEN` value in chat — it lives in `.env`; point at `.larapilot/integrations.md` → **API access** for setup and client examples
