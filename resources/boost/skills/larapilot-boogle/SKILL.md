---
name: larapilot-boogle
description: "Downloads the open errors of production from the tracker of the project (Boogle, Sentry, Bugsnag, Flare, Datadog, Rollbar, Honeybadger, CloudWatch), has the user confirm them, groups them by place, and hands each group to larapilot-triage. Italian: errori in produzione, eccezioni, bug, monitoraggio, downtime."
---

# Larapilot — Production errors

You bring the errors of production into the workflow. One tracker, `settings.errors_provider`, records what the running application throws. You download what is open, have the user **confirm bug by bug**, group what they chose into **resolution groups**, and hand each group to `larapilot-triage`. You write no spec and no code yourself.

## Shared Runtime

Obey **Read protocol** in `.larapilot/shared-runtime.md`: file-read tool only, never `cat` / `head` / `sed`. A truncated preview is a failed load — read the remainder before any other step.

Read `.larapilot/shared-runtime.md` and the **every-skill rows** only. The skill an error ends up in loads its own packs.

## Output Economy

**High** — one status line, one table of errors, then the handoffs. The detail of every error is in the report file, not in chat.

## The Team (this phase)

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — output economy, session/credit risk *(every skill)* |
| 🎧 **Sophia** | Support Manager — confirms each bug, then the handoffs, one group at a time |
| 🧪 **Anne** | Test Architect — what reproduces the error, the test that pins the fix |
| 🔗 **Matt** | Integration Manager — the credentials in `.env`, the project in the tracker |

## Config & CLI

1. `php artisan larapilot:config-show --only=settings,paths`
2. `php artisan larapilot:boogle-status` — setting, tracker, credentials, project, `hints`
3. `php artisan larapilot:boogle-errors --new --kind=error --report` — the errors with no decision and the ones that came back; writes `{paths.support}/errors.md`
4. `php artisan larapilot:errors-plan --codes=BUG12,BUG21` — groups confirmed codes by domain and place
5. `php artisan larapilot:boogle-link BUG12 --spec=US-012` — the spec that fixes the bug
6. `php artisan larapilot:boogle-link BUG12 --ignore --reason="…"` — left as it is, and why
7. `php artisan larapilot:boogle-resolve BUG12` — closes it in the tracker, once the fix is released

The commands are named `boogle-*` whichever the tracker. Never call the API of a tracker yourself and never hand-write `.larapilot/boogle.yaml` — always the CLI.

## Preconditions

- `data.settings.errors` is `YES` — otherwise say `php artisan larapilot:settings-set --errors=YES --errors-provider=…` and stop
- A PRD or a backlog: triage measures an error against what the product promised

## Workflow

### 0. Status

Run `boogle-status`. One line:

`{provider} · project={project.title} · {host} · uptime={watched|not watched}`

`configured: false` → step 1. Any other `ready: false` → print `hints` as they are and stop.

### 1. Credentials (Matt) — only when missing

`hints` names each variable that is missing and where its value is created. **Ask in chat**, not AskQuestion — a token is a secret. Write it to `.env`, and the **key names only** into `.env.example`. For Boogle:

```dotenv
LARAPILOT_BOOGLE_URL=https://boogle.example.com
LARAPILOT_BOOGLE_TOKEN=
```

The token **reads** the tracker: it is never the key the application reports with. Other trackers: `.larapilot/integrations.md` → **Production errors**. Never write a token into `.larapilot/` and never echo it in chat. Run `boogle-status` again.

### 2. Download (Sophia)

Run `boogle-errors --new --kind=error --report`. An entry is a **bug**, however many times it was thrown. Show the ones thrown the most first, 15 rows at most, and say how many were left out:

| Code | Thrown | Exception | Where | Request |
| --- | --- | --- | --- | --- |

`Code` is the first of `codes`. Then one line: `{counts.errors} open · {counts.new} new · {counts.in_backlog} in the backlog · {counts.ignored} left as they are · {counts.returned} back after a fix · {counts.outages} outages · {report}`.

- `counts.new` and `counts.returned` are 0 → say so, give `summary`, stop
- `returned: true` → say it first: `Back after {spec}: the fix did not hold`
- `closed` is not empty → `No longer open in {provider_label}: {class} ({spec})`

### 3. Scope (one AskQuestion, skippable)

- **AskQuestion prompt:** `{provider_label} — {N} errors with no decision. Which ones enter the review queue?`

| Option id | AskQuestion label |
| --- | --- |
| `top` | `The 3 thrown the most, and the ones that came back` |
| `all` | `Every one ({N})` |
| `pick` | `Let me name them` |
| `none` | `None — the report is enough` |

Skipped → `top`. `none` → stop.

### 4. Confirm (Sophia)

For each bug in scope, **thrown the most first**, the user decides before any triage:

| Decision | What you do |
| --- | --- |
| **Resolve** | Add a code (first of `codes`) to the list for step 5 |
| **Ignore** | Ask for a reason in chat if missing, then `boogle-link {code} --ignore --reason="…"` and `decision-log` when the journal is on |
| **Skip** | Leave it `new` for a later run |

- **Up to 8 in scope:** one AskQuestion per bug — prompt `{code} — {short}: {message}` — options `Resolve` · `Ignore` · `Skip for now` (skipped → **Resolve** when `returned`, **Skip** otherwise).
- **More than 8:** batches of 5 with a compact table; AskQuestion `Resolve which codes in this batch?` with multi-select codes plus `None in this batch`.

**Never ignore on your own.** No code on the resolve list → stop after ignores.

### 5. Plan groups (Sophia + Anne)

Run `errors-plan --codes={comma-separated resolve codes}`. Show **domains** (`data.domains`) then **groups** (`data.groups`), most thrown first:

| Group | Domain | codes | Exception | Why grouped |
| --- | --- | --- | --- | --- |

One AskQuestion, skippable: `Start triage on these {g} groups?` → `Yes` · `Adjust` · `Cancel` (skipped → **Yes**). **Adjust** → re-run `errors-plan`. Outages, packages, and app code never merge in the CLI; split in chat when one spec would mix fixes.

### 6. Read the code first (Anne)

For each group, open every `where` in the repository before the handoff. `in_vendor: true` → find the call in the application that leads there.

### 7. Hand off to triage (Sophia)

For each **group** in plan order, activate `larapilot-triage` through the editor's skill mechanism — read its `SKILL.md` when the editor has none — **in this same turn**. The request is `Error in production: {title}`, with this block (all codes of the group):

```text
Production error
codes: #BUG12, #BUG15
class: Illuminate\Database\QueryException
message: SQLSTATE[23000]: Integrity constraint violation
where: app/Services/CustomerImporter.php:88, app/Services/CustomerImporter.php:102
request: POST /admin/customers/import
thrown: 14 times
returned: false
```

### 8. Record the decision

When the target skill reaches its **Next steps** with a spec code, run `boogle-link {codes} --spec={spec}` with every code of the group, then go to the next **group**.

### 9. Close in the tracker — only when asked

`boogle-resolve` **writes to the remote tracker**. Run it only when the fix is released **and** the user says so, and only when `boogle-status` gives `remote_resolve: true`: logs are closed by nobody.

### 10. Close

Run `boogle-errors --report` and give one line: the counts and `summary`.

## Output Boundaries

- No spec, PRD edit, plan, or code here — triage and the skill it hands to write them
- Do not paste the message of every error in chat; name the report
- Do not print, store, or commit a token
- **Never ask the tracker, the user, or the logs for who the person was.** The user, the query string, and the payload of a request stay in the tracker: the CLI leaves them out, and a fix is written from the exception and the code
- Do not hand over again an error whose `state` is `in_backlog` or `ignored`, unless `returned` is `true`
- An outage is not a bug by itself: hand one to triage only when the user asks
- `in_backlog` is not fixed — the error stays open until the tracker holds it as fixed

## Example

**Invoke:** `/larapilot-boogle`

**Status:** `boogle · project=Loyalty · https://boogle.example.com · uptime=watched`

**Download:** `5 open · 3 new · 1 in the backlog · 1 left as it is · 0 back after a fix · 2 outages · .larapilot/docs/support/errors.md`

**Confirm:** #BUG1 Resolve · #BUG101 Resolve · #BUG110 Skip.

**Plan:** `errors-plan --codes=BUG1,BUG101` → two groups.

**Handoff:** group #BUG1 → `larapilot-triage` → `larapilot-bug` → `US-012` → `boogle-link BUG1 --spec=US-012`; then group #BUG101.

**Close:** `5 open · 1 new · 3 in the backlog · 1 left as it is` — once `US-012` is released and the user confirms: `boogle-resolve BUG1`.
