---
name: larapilot-settings
description: Configure persistent Larapilot project settings (effort, backlog granularity, git mode, testing, auto-approve, lucille, GitHub/GitLab/Bitbucket, notifications) via AskQuestion. Use when the user runs /larapilot-settings, wants to change token economy, backlog/spec granularity, Gitflow/push behavior, test depth, auto-approve, Lucille, remote forge, or Slack/Discord/Telegram notifications. Italian triggers include "impostazioni larapilot", "settings", "modalità eco", "granularità backlog", "meno specs", "gitflow push", "autoapprove", "disattiva Lucille", "escludi Lucille", "notifiche slack", "telegram", "discord", "github", "gitlab", "bitbucket".
---

# Larapilot — Project Settings

Persist project-wide Larapilot settings into `.larapilot/config.yaml`. All other skills read and honor them.

## Shared Runtime

Read `.larapilot/shared-runtime.md` — **Project Settings** (effort, backlog, git mode, testing, auto_approve, lucille, github, gitlab, bitbucket, notifications). Bot/webhook/forge setup: `.larapilot/integrations.md`.

## Output Economy

**High** — short confirmations only. AskQuestion carries the options; chat stays terse. Still honor Zoey's start/end **Context estimate** lines from shared-runtime.

## The Team

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — frames trade-offs (tokens vs depth, human gate vs auto-approve) and confirms persistence |
| 💎 **Mark** | Product Manager — owns backlog granularity implications (spec/epic count vs traceability) |
| 🚀 **Jack** | DevOps — owns git_mode and optional GitHub / GitLab / Bitbucket integrations |
| ⌨️ **Sarah** | CLI / Git / Linux — involved when forge/CI automation or Git mechanics guidance is needed |
| 🧪 **Anne** | Test Architect — owns testing mode implications |
| 🛡️ **Robert** | Code Reviewer — owns auto_approve risk framing |
| 📒 **Lucille** | Account — owns the lucille on/exclude setting; default is always ON |
| 🔗 **Matt** | Integration Manager — owns Slack/Discord/Telegram notification toggles (secrets stay in `.env`) |

## Config & CLI

1. `php artisan larapilot:config-show` — read current `data.settings`
2. After answers: `php artisan larapilot:settings-set` with the answered flags
3. Re-run `config-show` and confirm the saved values
4. Optional probes: `larapilot:github-status`, `larapilot:gitlab-status`, `larapilot:bitbucket-status`, `larapilot:notify --event=custom --title="Larapilot test"`

Never edit `.larapilot/config.yaml` by hand from the skill — always use `larapilot:settings-set`. **Never ask for webhook/token values in chat** — point at `.larapilot/integrations.md` and `.env`.

## Workflow

### 0. Load current settings

Run `config-show`. Show one line with current values:

`effort={…} · backlog={…} · git_mode={…} · testing={…} · auto_approve={…} · lucille={…} · github={…} · gitlab={…} · bitbucket={…} · notifications={…}`

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

**6. Lucille** — usage ledger + schedule at every skill level (default ON; exclusion must be explicit)

- **AskQuestion prompt:** `Lucille (current: {VALUE}) — keep time/token tracking on every skill?`
- **Chat framing (one line):** 📒 Lucille — ON by default everywhere; choose NO only to exclude her explicitly.

| Option id | AskQuestion label |
| --- | --- |
| `YES` | `YES — Lucille active on every skill (default): log tokens/time, deadlines, /larapilot-usage` |
| `NO` | `NO — explicit exclusion: no usage-log, no Lucille interview rounds (historical ledger stays readable)` |

Warn once when the user picks `NO`: this opts out of project time/token metrics until they set `YES` again. Note: choosing **Effort = ECO** also sets Lucille to `NO` automatically — same re-enable path (`--lucille=YES`).

**7. Remote forge** — optional GitHub / GitLab / Bitbucket (each default OFF; orthogonal to git_mode)

Ask only the forge(s) that match the user's remote (skip others or leave NO).

- **GitHub prompt:** `GitHub (current: {VALUE}) — use gh CLI for remote PRs?`
- **GitLab prompt:** `GitLab (current: {VALUE}) — use glab CLI for merge requests?`
- **Bitbucket prompt:** `Bitbucket (current: {VALUE}) — use Bitbucket Cloud API tokens for PRs?`
- **Chat framing (one line):** 🚀 Jack — OFF by default; see `.larapilot/integrations.md`.

| Setting | YES label |
| --- | --- |
| `github` | `YES — gh pr create/view; print PR URL; notify pr_*` |
| `gitlab` | `YES — glab mr create/view; print MR URL; notify pr_*` |
| `bitbucket` | `YES — Bitbucket REST API with access token or app password; print PR URL; notify pr_*` |

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

Defaults when unset: `STANDARD` / `STANDARD` / `GITFLOW` / `NORMAL` / `NO` / **`YES` (lucille)** / **`NO` (github/gitlab/bitbucket)** / **`NO` (notifications + channels)**.  
(`config.yaml` stores booleans; `config-show` / CLI envelopes expose `YES` | `NO`. Missing `lucille` → YES; missing forge/notifications → NO.)

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
  --github=NO \
  --gitlab=NO \
  --bitbucket=NO \
  --notifications=NO \
  --notify-slack=NO \
  --notify-discord=NO \
  --notify-telegram=NO
```

Pass only the keys the user answered. On success, parse the JSON envelope (`kind: "settings"`) and confirm:

`Saved → effort=… · backlog=… · git_mode=… · testing=… · auto_approve=… · lucille=… · github=… · gitlab=… · bitbucket=… · notifications=…`  
`Path: data.config_path` (or `.larapilot/config.yaml`)

If `data.lucille_disabled_by_eco` is true (or effort was just set to ECO without an explicit lucille flag), state once: **Lucille disabled by ECO** — re-enable with `php artisan larapilot:settings-set --lucille=YES`.

If a forge is YES, optionally run the matching `larapilot:{github,gitlab,bitbucket}-status` and surface `ready` / `hints`.

### 3. Next steps

Remind once (one line): other skills honor these on next run via `config-show` → `data.settings`.

## Rules

- Do not change PRD, backlog, or code — settings only
- Do not re-ask unanswered skippable questions; keep previous values for skipped keys
- If the user wants a single setting changed, AskQuestion only that dimension
- Never invent persistence — CLI only
- Never collect Slack/Discord/Telegram secrets in chat
