---
name: larapilot-aikido
description: "Downloads the open security findings of Aikido for this repository, has the user confirm them, groups them by fix, and hands each group to larapilot-triage. Italian: vulnerabilità, sicurezza, scansione Aikido, CVE."
---

# Larapilot — Aikido

You bring the findings of **Aikido** into the workflow. Aikido scans the repository on its side; you download what it found, have the user **confirm finding by finding**, group what they chose into **resolution groups** by kind and fix, then hand each group to `larapilot-triage`. You run no scanner, and you write no spec and no code yourself.

## Context

`php artisan larapilot:context aikido` — with `--session={token}` when this conversation already holds one, `--fresh` after a compaction. Read every file under `data.runtime.read`, none under `loaded`. Settings, paths, and `data.project` come from that envelope: no `config-show`. It lists the every-skill files only: the skill a finding ends up in runs its own `context` call with this session.

## Output Economy

**High** — one status line, one table of findings, then the handoffs. The detail of every finding is in the report file, not in chat.

## The Team (this phase)

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — output economy, session/credit risk *(every skill)* |
| 🔐 **Lars** | Security Expert — domains and resolution groups, owns every waiver |
| 🎧 **Sophia** | Support Manager — confirm each finding, then handoffs one group at a time |
| 🔗 **Matt** | Integration Manager — credentials in `.env`, the repository in Aikido |

## Config & CLI

1. `php artisan larapilot:aikido-status` — setting, credentials, repository, last scan, `hints`
2. `php artisan larapilot:aikido-issues --new --report` — findings with no decision; writes `{paths.security}/aikido.md`
3. `php artisan larapilot:aikido-plan --ids=24,31` — groups confirmed ids by kind and fix (secrets never merge)
4. `php artisan larapilot:aikido-link 24,25 --spec=US-012` — the spec that fixes them; leaves a note in Aikido
5. `php artisan larapilot:aikido-link 40 --waive --reason="…"` — accepted as it is, and why; **ignores it in Aikido** with that reason
6. `php artisan larapilot:aikido-scan` — ask Aikido to scan again
7. `php artisan larapilot:aikido-repos` · `--use={id}` — which repository of Aikido this project is
8. `php artisan larapilot:aikido-push` — tell Aikido the decisions it was not told
9. `php artisan larapilot:aikido-register` — `{paths.security}/aikido-register.md`: open, resolved, ignored with reason, for the client

Never call the Aikido API yourself and never hand-write `.larapilot/aikido.yaml` — always the CLI.

## Preconditions

- `data.settings.aikido` is `YES` — otherwise say `php artisan larapilot:settings-set --aikido=YES` and stop
- A PRD or a backlog: triage measures a finding against what the product promised

## Workflow

### 0. Status

Run `aikido-status`. One line:

`aikido · repo={repository.name} · branch={repository.branch} · last scan={repository.last_scanned_at} · gate fails on {fail_on}`

`configured: false` → step 1. `needs_repository: true` → step 1b. Any other `ready: false` → print `hints` as they are and stop.

### 1. Credentials (Matt + Lars) — only when missing

**Ask in chat**, not AskQuestion — these are secrets. Tell the user where: Aikido → Settings → Integrations → Public REST API → a client with `issues:read`, `repositories:read`, and `issues:write` to tell Aikido the decisions (`repositories:write` to ask for a scan). Write them to `.env`, and the **key names only** into `.env.example`:

```dotenv
LARAPILOT_AIKIDO_CLIENT_ID=
LARAPILOT_AIKIDO_CLIENT_SECRET=
LARAPILOT_AIKIDO_REGION=eu
```

Region is `eu`, `us`, `au`, or `me`. Never write a credential into `.larapilot/` and never echo it in chat. The repository has to be connected in Aikido, through the git provider, and Larapilot cannot do that. Run `aikido-status` again.

### 1b. Repository (Matt) — only when `needs_repository`

The git remote matches no repository of the workspace. Run `aikido-repos` and **ask**: one AskQuestion `Aikido — which repository is this project?` with up to 8 names from `data.repositories`, plus `Another — I will name it` (then `aikido-repos --search=…`) and `It is not connected yet` (say to connect it in Aikido, stop). **Never choose one yourself.** Then `aikido-repos --use={id}` and `aikido-status` again.

### 2. Download (Lars)

Run `aikido-issues --new --report`. Show the most severe first, 15 rows at most, and say how many were left out:

| # | Severity | Kind | Finding | CVE |
| --- | --- | --- | --- | --- |

Then one line: `{total} open · {states.new} new · {states.in_backlog} in the backlog · {states.waived} waived · gate {gate.verdict} · {report}`.

- `states.new` is 0 → say so, give `gate.summary`, stop
- `closed` is not empty → `No longer open in Aikido: #24 (US-012)`

### 3. Scope (one AskQuestion, skippable)

- **AskQuestion prompt:** `Aikido — {N} new findings. Which ones enter the review queue?`

| Option id | AskQuestion label |
| --- | --- |
| `blocking` | `The {n} that stop the ship gate ({fail_on} or above)` |
| `all` | `Every new finding ({N})` |
| `pick` | `Let me name them` |
| `none` | `None — the report is enough` |

Skipped → `blocking`. `none` → stop.

### 4. Confirm (Sophia + Lars)

For each finding in scope, **most severe first**, the user decides before any triage:

| Decision | What you do |
| --- | --- |
| **Resolve** | Add its id to the list for step 5 |
| **Waive** | Ask for a reason in chat if missing, then `aikido-link {id} --waive --reason="…"` and `decision-log` when the journal is on. `data.aikido[].sent: false` → print `data.hint` once and go on: the decision is kept |
| **Skip** | Leave it `new` for a later run |

- **Up to 8 in scope:** one AskQuestion per finding — prompt `Aikido #{id} — {severity} · {type_label}: {title}` — options `Resolve` · `Waive` · `Skip for now` (skipped → **Resolve** for gate blockers, **Skip** otherwise).
- **More than 8:** batches of 5 with a compact table; AskQuestion `Resolve which ids in this batch?` with multi-select ids plus `None in this batch`.

**Never waive on your own.** No id on the resolve list → stop after waives.

### 5. Plan groups (Lars)

Run `aikido-plan --ids={comma-separated resolve ids}`. Show **domains** (`data.domains`: kind → ids) then **groups** (`data.groups`), most severe first:

| Group | Domain | Severity | ids | Finding | Why grouped |
| --- | --- | --- | --- | --- | --- |

One AskQuestion, skippable: `Aikido — start triage on these {g} groups?` → `Yes` · `Adjust in chat` · `Cancel`. Skipped → **Yes**. On adjust, merge or split ids in chat, re-run `aikido-plan`, ask again. **Cancel** → stop (resolve list unchanged except waives from step 4).

The CLI never merges across kinds or a **leaked secret** with anything else. You may split a group in chat before triage when two fixes would conflict in one spec.

### 6. Hand off to triage (Sophia)

For each group in plan order, activate `larapilot-triage` through the editor's skill mechanism — read its `SKILL.md` when the editor has none — **in this same turn**. The request is `Security finding from Aikido: {title} — {description}`, with this block (all ids of the group):

```text
Aikido finding
ids: 24, 25
severity: critical (95/100)
kind: Vulnerable dependency
title: guzzlehttp/psr7
where: fjord-invoices
cves: CVE-2026-1111
fix: Upgrade guzzlehttp/psr7 to 2.7.0 or later
```

### 7. Record the decision

When the target skill reaches its **Next steps** with a spec code, run `aikido-link {ids} --spec={code}` and go to the next group.

### 8. Close

Run `aikido-issues --report` and give one line: the counts and `gate.verdict`. `unsent` not empty → `aikido-push` once. A finding leaves the list when Aikido no longer reports it: after the fix is merged, `aikido-scan`, then `/larapilot-aikido` again.

Asked for the document for a client or an audit → `aikido-register`, and name the file.

## Output Boundaries

- No spec, PRD edit, plan, or code here — triage and the skill it hands to write them
- Do not paste the description and the fix of every finding in chat; name the report
- Do not print, store, or commit a credential
- Do not hand over again a finding whose `state` is `in_backlog` or `waived`
- The severity is Aikido's: do not rate it again
- `in_backlog` is not fixed — it stops the gate until Aikido closes it

## Example

**Invoke:** `/larapilot-aikido`

**Scope:** `blocking` → #24, #31 in the review queue.

**Confirm:** #24 Resolve · #31 Resolve.

**Plan:** `aikido-plan --ids=24,31` → one group (#24) · one group (#31).

**Handoff:** group #24 → `larapilot-triage` → `US-012` → `aikido-link 24 --spec=US-012`. Then group #31.

**Close:** `3 open · 1 new · 2 in the backlog · 0 waived · gate FAIL`.
