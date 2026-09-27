---
name: larapilot-boogle
description: "Downloads the open errors Boogle recorded for the running application, puts together the ones that are one bug, and hands each bug to larapilot-triage to be resolved. Italian: errori in produzione, eccezioni, bug da Boogle, monitoraggio, downtime."
---

# Larapilot — Boogle

You bring the errors of **Boogle** into the workflow. Boogle is the exception tracker and uptime monitor the team hosts: it records what the running application throws. You download what is open, show what nobody decided about, and hand each bug the user picks to `larapilot-triage`, which routes it to `larapilot-bug` or `larapilot-feature`. You write no spec and no code yourself.

## Shared Runtime

Obey **Read protocol** in `.larapilot/shared-runtime.md`: file-read tool only, never `cat` / `head` / `sed`. A truncated preview is a failed load — read the remainder before any other step.

Read `.larapilot/shared-runtime.md` and the **every-skill rows** only. The skill an error ends up in loads its own packs.

## Output Economy

**High** — one status line, one table of errors, then the handoffs. The detail of every error is in the report file, not in chat.

## The Team (this phase)

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — output economy, session/credit risk *(every skill)* |
| 🎧 **Sophia** | Support Manager — reads the errors, orders the handoffs, one at a time |
| 🧪 **Anne** | Test Architect — what reproduces the error, the test that pins the fix |
| 🔗 **Matt** | Integration Manager — the address and the token in `.env`, the project in Boogle |

## Config & CLI

1. `php artisan larapilot:config-show --only=settings,paths`
2. `php artisan larapilot:boogle-status` — setting, address, token, project, `hints`
3. `php artisan larapilot:boogle-errors --new --kind=error --report` — the errors nobody decided about and the ones that came back; writes `{paths.support}/boogle.md` about all of them
4. `php artisan larapilot:boogle-link BUG12 --spec=US-012` — the spec that fixes the error that code belongs to
5. `php artisan larapilot:boogle-link BUG12 --ignore --reason="…"` — left as it is, and why
6. `php artisan larapilot:boogle-resolve BUG12` — closes the error **in Boogle**, once the fix is released

Never call the Boogle API yourself and never hand-write `.larapilot/boogle.yaml` — always the CLI.

## Preconditions

- `data.settings.boogle` is `YES` — otherwise say `php artisan larapilot:settings-set --boogle=YES` and stop
- A PRD or a backlog: triage measures an error against what the product promised

## Workflow

### 0. Status

Run `boogle-status`. One line:

`boogle · project={project.title} · {host} · uptime={watched|not watched}`

`configured: false` → step 1. Any other `ready: false` → print `hints` as they are and stop.

### 1. Address and token (Matt) — only when missing

**Ask in chat**, not AskQuestion — the token is a secret. Tell the user where: Boogle → profile of an **admin** user → API tokens. Write them to `.env`, and the **key names only** into `.env.example`:

```dotenv
LARAPILOT_BOOGLE_URL=https://boogle.example.com
LARAPILOT_BOOGLE_TOKEN=
```

`LARAPILOT_BOOGLE_URL` can stay empty when `BOOGLE_SERVER` is set. The project is found from `BOOGLE_PROJECT_KEY`, then from `APP_URL`; name it with `LARAPILOT_BOOGLE_PROJECT` otherwise. Never write the token into `.larapilot/` and never echo it in chat. Run `boogle-status` again.

### 2. Download (Sophia)

Run `boogle-errors --new --kind=error --report`. An entry is a **bug**: every time the same exception was thrown at the same line. Show the ones thrown the most first, 15 rows at most, and say how many were left out:

| Code | Thrown | Exception | Where | Request |
| --- | --- | --- | --- | --- |

`Code` is the first of `codes`. Then one line: `{counts.errors} open · {counts.new} new · {counts.in_backlog} in the backlog · {counts.ignored} left as they are · {counts.returned} back after a fix · {counts.outages} outages · {report}`.

- `counts.new` and `counts.returned` are 0 → say so, give `summary`, stop
- `returned: true` → say it first: `Back after {spec}: the fix did not hold`
- `closed` is not empty → `No longer open in Boogle: {class} ({spec})`

### 3. Choose (one AskQuestion, skippable)

- **AskQuestion prompt:** `Boogle — {N} errors with no decision. Which ones go to resolution now?`

| Option id | AskQuestion label |
| --- | --- |
| `top` | `The 3 thrown the most, and the ones that came back` |
| `all` | `Every one ({N})` |
| `pick` | `Let me name them` |
| `none` | `None — the report is enough` |

Skipped → `top`.

### 4. Read the code first (Anne)

For each error picked, open `where` in the repository before the handoff. `in_vendor: true` → the line is in a package: find the call of the application that leads there. One request per bug; put two entries together only when one fix closes both.

### 5. Hand off to triage (Sophia)

For each bug, the one thrown the most first, activate `larapilot-triage` through the editor's skill mechanism — read its `SKILL.md` when the editor has none — **in this same turn**. The request is `Error in production from Boogle: {short} — {message}`, with this block:

```text
Boogle error
codes: #BUG12, #BUG15
class: Illuminate\Database\QueryException
message: SQLSTATE[23000]: Integrity constraint violation
where: app/Services/CustomerImporter.php:88
request: POST /admin/customers/import
thrown: 14 times, first 2026-09-21, last 2026-09-26
```

### 6. Record the decision

When the target skill reaches its **Next steps** with a spec code, run `boogle-link {code} --spec={spec}` and go to the next bug. One code is enough: the decision is about the bug, so the next time it is thrown it is not handed over again.

An error the user decides not to fix: `boogle-link {code} --ignore --reason="…"` with the reason in the user's words, and `decision-log --topic="Boogle error left as it is: {short}" --skill=larapilot-boogle` when `data.settings.decision_log` is `YES`. **Never ignore on your own.**

### 7. Close in Boogle — only when asked

`boogle-resolve` **writes to Boogle**: it closes every open occurrence of the error there. Run it only when the fix is released — the spec is `DONE` and shipped — **and** the user says so. Never at the end of an implementation, never for a spec in review.

### 8. Close

Run `boogle-errors --report` and give one line: the counts and `summary`.

## Output Boundaries

- No spec, PRD edit, plan, or code here — triage and the skill it hands to write them
- Do not paste the message of every error in chat; name the report
- Do not print, store, or commit the token
- **Never ask Boogle, the user, or the logs for who the person was.** The user, the query string, and the payload of a request stay in Boogle: the CLI leaves them out, and a fix is written from the exception and the code
- Do not hand over again an error whose `state` is `in_backlog` or `ignored`, unless `returned` is `true`
- An outage is not a bug by itself: hand one to triage only when the user asks
- `in_backlog` is not fixed — the error stays open until Boogle holds it as fixed

## Example

**Invoke:** `/larapilot-boogle`

**Status:** `boogle · project=Loyalty · https://boogle.example.com · uptime=watched`

**Download:** `5 open · 3 new · 1 in the backlog · 1 left as it is · 0 back after a fix · 2 outages · .larapilot/docs/support/boogle.md`

| Code | Thrown | Exception | Where | Request |
| --- | --- | --- | --- | --- |
| #BUG1 | 14 | QueryException | app/Services/CustomerImporter.php:88 | POST /admin/customers/import |
| #BUG101 | 9 | RewardNotAvailable | app/Actions/RedeemReward.php:27 | POST /api/rewards/12/redeem |
| #BUG110 | 1 | TypeError | app/Support/Money.php:19 | GET /admin/reports/monthly |

**Choose:** `top` → #BUG1, then #BUG101, then #BUG110.

**Handoff:** #BUG1 → `larapilot-triage` → `🎧 Sophia: Bug — FR-014 promises the import skips a customer that exists → larapilot-bug` → fix spec `US-012` → `boogle-link BUG1 --spec=US-012`. Then #BUG101.

**Close:** `5 open · 0 new · 4 in the backlog · 1 left as it is` — once `US-012` is released and the user confirms: `boogle-resolve BUG1`.
