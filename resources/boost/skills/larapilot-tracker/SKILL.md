---
name: larapilot-tracker
description: Syncs the Larapilot backlog with an external project tracker — Linear, Asana, Jira, Trello, ClickUp, or Monday — over an API key. Pushes user stories as issues and plan tasks as native subtasks, and reads remote status back as a drift report. Use when the user mentions Linear, Asana, Jira, Trello, ClickUp, Monday, project tracker, issue tracker, ticket, sprint board, or Italian triggers like "sincronizza con Jira", "porta il backlog su Linear", "ticket", "bacheca", "gestionale progetti".
---

# Larapilot — Project Tracker Sync

You mirror the `.larapilot/` backlog into the tool the rest of the organisation already lives in. **Larapilot stays the source of truth.** The tracker is a window onto delivery for people who will never open `backlog.yaml` — it is not a second place to run the workflow.

## Shared Runtime

Read `.larapilot/shared-runtime.md` (core) and `.larapilot/runtime-ops.md` → **Project Trackers** (canonical direction, credential, and status-mapping rules — do not restate them, apply them).

## The Team (this phase)

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — intent + output economy |
| 🔗 **Matt** | Integration Manager — owns provider choice, status mapping, link hygiene |
| 💎 **Mark** | Product Manager — decides what non-developers need to see on the board |
| 🔐 **Lars** | Security Expert — API keys live in `.env`, never in `.larapilot/` |

## Preconditions

- Larapilot installed (`.larapilot/config.yaml` exists) — otherwise run `php artisan larapilot:install` first
- A backlog worth syncing (`larapilot:spec-list`) — an empty backlog syncs nothing; say so rather than creating placeholder cards
- The user has an account and an API key for one of: **Linear · Asana · Jira · Trello · ClickUp · Monday**

## Config & CLI

1. `php artisan larapilot:tracker-status` — provider, readiness, missing env vars, status map, linked spec count
2. `php artisan larapilot:tracker-status --ping` — one live call to verify the credential and the target board/project
3. `php artisan larapilot:tracker-push [--dry-run] [--spec=US-001] [--force]` — Larapilot → tracker
4. `php artisan larapilot:tracker-pull [--apply] [--spec=US-001]` — tracker → drift report

Never create or edit cards by hand, and never hand-write `.larapilot/tracker.yaml` — always the CLI.

## Workflow

### 0. Read current state

Run `tracker-status`. Report one line:

`provider={data.provider} · ready={data.configured} · linked={data.specs.linked}/{data.specs.total} · tasks={data.sync_tasks}`

If `enabled` is `false`, tell the user to set `LARAPILOT_TRACKER_ENABLED=true` and stop.

### 1. Choose the provider (Matt)

Only when no provider is set, or the user wants to change it.

- **AskQuestion prompt:** `Project tracker (current: {VALUE}) — where should the backlog be mirrored?`

| Option id | AskQuestion label |
| --- | --- |
| `linear` | `Linear — issues on a team, plan tasks as sub-issues, native estimates and priority` |
| `jira` | `Jira — issues in a project, plan tasks as subtasks; status moves through workflow transitions` |
| `asana` | `Asana — tasks in a project, statuses are sections, plan tasks as subtasks` |
| `clickup` | `ClickUp — tasks in a list, statuses are list statuses, plan tasks as subtasks` |
| `trello` | `Trello — cards on a board, statuses are lists, plan tasks as checklist items` |
| `monday` | `Monday — items on a board, statuses are a status column, plan tasks as subitems` |

One tracker at a time. Links are stored per provider, so switching later does not lose the old mapping.

### 2. Collect credentials and the destination (Lars + Matt)

**Ask in chat** (not AskQuestion — these are free-text secrets and ids). Ask only for the provider chosen, and tell the user where to get the key:

| Provider | Ask for | Where |
| --- | --- | --- |
| **Linear** | API key, team key (e.g. `ENG`) | Settings → API → Personal API keys |
| **Jira** | site URL, account email, API token, project key | id.atlassian.com → Security → API tokens |
| **Asana** | personal access token, project gid | Settings → Apps → Developer apps → PAT |
| **Trello** | API key, token, board id | trello.com/power-ups/admin |
| **ClickUp** | personal token (`pk_…`), list id | Settings → Apps → API token |
| **Monday** | API token, board id | Admin → API → Personal API token |

Write them to `.env` (and mirror the **key names only**, with empty values, into `.env.example` when present):

```dotenv
LARAPILOT_TRACKER_ENABLED=true
LARAPILOT_TRACKER_PROVIDER=linear
LARAPILOT_LINEAR_API_KEY=lin_api_xxx
LARAPILOT_LINEAR_TEAM=ENG
```

**Never** write a token into `.larapilot/` — that directory is committed. Never echo the token back in chat. Re-run `tracker-status --ping` and confirm the connection before going further.

### 3. Align the status map (Matt)

The status map is the whole integration. Read `data.status_map` from `tracker-status` and check each destination really exists in the tracker:

| Provider | A status maps to |
| --- | --- |
| Linear | a workflow **state** on the team |
| Jira | a workflow **status** reachable by a transition |
| Asana | a **section** in the project |
| Trello | a **list** on the board |
| ClickUp | a **status** in the list |
| Monday | a **label** on the status column |

If a destination is missing, the push fails with the names that *do* exist — offer two ways out: rename the column in the tracker, or override the map in `config/larapilot.php` → `tracker.providers.{provider}.status_map`. Do not invent columns in the user's tracker.

`TODO` and `PLANNED` both mapping to one column is normal and is **not** treated as drift.

### 4. Dry run, then push

```bash
php artisan larapilot:tracker-push --dry-run
php artisan larapilot:tracker-push
```

Always dry-run first on a backlog that has never been synced — it reports create/update counts without calling the provider. Report:

`Pushed → {summary.created} created · {summary.updated} updated · {summary.tasks_created} subtasks · links in {link_file}`

Commit `.larapilot/tracker.yaml` — it is how the team shares one mapping instead of each machine creating duplicate cards.

### 5. Read back (optional)

```bash
php artisan larapilot:tracker-pull            # report only, changes nothing
php artisan larapilot:tracker-pull --apply    # write mapped statuses into the backlog
```

Show the drift table before offering `--apply`. Two things pull will **never** do, and you should not offer them:

- **Set a spec to DONE.** DONE is a human review gate that records the merge commit — route to `/larapilot-review` or `larapilot:spec-approve`.
- **Change spec text.** Titles, bodies, and acceptance criteria are owned by `.larapilot/`; a card edited in the tracker is overwritten on the next push.

Enable `LARAPILOT_TRACKER_PULL_COMMENTS=true` only if the user wants tracker comments imported as internal feedback. They arrive non-blocking and are imported once.

### 6. Keep it fresh (optional)

Offer, do not silently configure:

- A CI step on the default branch running `php artisan larapilot:tracker-push`
- Or re-running `/larapilot-tracker` after review milestones and `spec-approve`

## Rules

- No PRD, backlog, plan, or code changes — this skill syncs, it does not author
- Never invent boards, projects, teams, or columns; ask, or stop and report what exists
- Never present the tracker as a place to change workflow state
- Never commit or print an API key; credentials are `.env` only
- One failing spec does not abort a push — report the per-spec errors from `error.details.errors`

## Output Economy

**High** — the status line, the drift table when pulling, and the push summary. Honor Zoey's start/end **Context estimate** lines from shared-runtime.
