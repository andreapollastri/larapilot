---
name: larapilot-security
description: "Brings the open security findings of this repository from the scanner the user associates — Aikido (SaaS) or Hackly (self-hosted, hackly.web.ap.it) — has the user confirm them, groups them by fix, and hands each group to larapilot-triage. Use for security findings, vulnerabilities, CVEs, a new scan, the security register. Italian: vulnerabilità, sicurezza, scansione, segnalazioni, Aikido, Hackly."
---

# Larapilot — Security

You bring the open findings of a security scanner into the workflow: **Aikido** (SaaS, scans through the git provider) or **[Hackly](https://hackly.web.ap.it)** (self-hosted: code, dependencies, secrets, and the domains linked to the repository). The user associates one; you never pick it. It scans on its side; you download what it found, have the user **confirm finding by finding**, group what they chose into **resolution groups**, and hand each group to `larapilot-triage`. You run no scanner, and you write no spec and no code yourself.

## Context

`php artisan larapilot:context security` — with `--session={token}` when this conversation already holds one, `--fresh` after a compaction. Read every file under `data.runtime.read`, none under `loaded`. Settings, paths, and `data.project` come from that envelope: no `config-show`.

## Output Economy

**High** — one status line, one table of findings, then the handoffs. The detail of every finding is in the report file, not in chat.

## The Team (this phase)

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — output economy, session/credit risk *(every skill)* |
| 🔐 **Lars** | Security Expert — domains and resolution groups, owns every waiver |
| 🎧 **Sophia** | Support Manager — confirm each finding, then handoffs one group at a time |
| 🔗 **Matt** | Integration Manager — credentials in `.env`, the repository in the scanner |

## Config & CLI

Always the CLI — never call a scanner's API yourself, never hand-write `.larapilot/aikido.yaml` or `.larapilot/hackly.yaml`. Ids are numbers in Aikido (`24`), UUIDs in Hackly.

| # | Command | What it does |
| --- | --- | --- |
| 1 | `php artisan larapilot:security-status` | Scanner, credentials, repository, last scan, `hints` |
| 2 | `php artisan larapilot:security-issues --new --report` | Findings with no decision; writes `{paths.security}/security-report.md` |
| 3 | `php artisan larapilot:security-issues --detail={id}` | One finding as a brief and a fix prompt (Hackly writes its own) |
| 4 | `php artisan larapilot:security-plan --ids=24,31` | Groups confirmed ids by kind and fix — a leaked secret never merges |
| 5 | `php artisan larapilot:security-link {ids} --spec=US-012` | The spec that fixes them — Aikido gets a note, Hackly acknowledges them |
| 6 | `php artisan larapilot:security-link {ids} --waive --reason="…"` | Accepted as it is, and why — **ignores it in Aikido** with that reason, acknowledges it in Hackly |
| 7 | `php artisan larapilot:security-link {ids} --false-positive --reason="…"` | Not real — Hackly marks it a false positive with the reason as its note |
| 8 | `php artisan larapilot:security-repos` · `--use={id}` | Which repository of the scanner this project is |
| 9 | `php artisan larapilot:security-scan` | Ask for a new scan — Hackly: `--profile=quick\|standard\|deep`, `--include-targets` |
| 10 | `php artisan larapilot:security-push` | Tell the scanner the decisions it was not told |
| 11 | `php artisan larapilot:security-register` | `{paths.security}/security-register.md`: open, resolved, ignored with reason, for the client |

`larapilot:aikido-*` are the older names of the same Aikido steps; prefer `security-*`.

## Workflow

### 0. Scanner (Matt)

Run `security-status`. `provider` is `null` → one AskQuestion, not skippable:

**AskQuestion prompt:** `Security findings — associate Aikido or Hackly?`

| Option id | AskQuestion label |
| --- | --- |
| `aikido` | `Aikido — SaaS, scans through the git provider` |
| `hackly` | `Hackly — self-hosted: code, dependencies, secrets, linked domains` |
| `none` | `Neither` |

**Never pick the service yourself.** `none` → stop. Otherwise `php artisan larapilot:settings-set --security-provider={id}` and `security-status` again. Switch scanner only when the user asks.

Then one line:

`{provider} · repo={repository.name} · branch={repository.branch} · last scan={repository.last_scanned_at} · gate fails on {fail_on}` — Hackly adds `· domains={targets}`.

`configured: false` → step 1. `needs_repository: true` → step 1b. Any other `ready: false` → print `hints` as they are and stop.

### 1. Credentials (Matt + Lars) — only when missing

**Ask in chat**, not AskQuestion — these are secrets. Tell the user where they come from:

- **Aikido** — Settings → Integrations → Public REST API → a client with `issues:read`, `repositories:read`, and `issues:write` to tell Aikido the decisions (`repositories:write` to ask for a scan). Keys `LARAPILOT_AIKIDO_CLIENT_ID`, `LARAPILOT_AIKIDO_CLIENT_SECRET`, `LARAPILOT_AIKIDO_REGION` (`eu`, `us`, `au`, `me`).
- **Hackly** — the address of **their own** Hackly (hackly.web.ap.it is its documentation, never an instance) and a personal token: Profile → API tokens, or `php artisan hackly:token you@example.com --ability=write` on the Hackly server (`read` lists; `write` tells decisions and asks for scans). Keys `LARAPILOT_HACKLY_URL`, `LARAPILOT_HACKLY_TOKEN`.

Write them to `.env`, and the **key names only** into `.env.example`. Never write a credential into `.larapilot/` and never echo it in chat. The repository has to be connected in Aikido, or added in Hackly with a GitHub token that reads it — Larapilot cannot do that. Run `security-status` again.

### 1b. Repository (Matt) — only when `needs_repository`

The git remote matches no repository of the scanner. Run `security-repos` and **ask**: one AskQuestion `{Scanner} — which repository is this project?` with up to 8 names from `data.repositories`, plus `Another — I will name it` (then `security-repos --search=…`) and `It is not there yet` (say how to add it, stop). **Never choose one yourself.** Then `security-repos --use={id}` and `security-status` again.

### 2. Download (Lars)

Run `security-issues --new --report`. Show the most severe first, 15 rows at most, and say how many were left out:

| # | Severity | Kind | Finding | CVE | Where |
| --- | --- | --- | --- | --- | --- |

For Hackly show the first 8 characters of the id in `#`, keep the whole id for the commands, and say `domain` in **Where** for a finding of a linked domain. Then one line: `{total} open · {states.new} new · {states.in_backlog} in the backlog · {states.waived} waived · gate {gate.verdict} · {report}`.

- `states.new` is 0 → say so, give `gate.summary`, stop
- `closed` is not empty → `No longer open in {Scanner}: {ids} ({specs})`

### 3. Scope (one AskQuestion, skippable)

**AskQuestion prompt:** `{Scanner} — {N} new findings. Which ones enter the review queue?`

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
| **Waive** | Ask for a reason in chat if missing, then `security-link {id} --waive --reason="…"` and `decision-log` when the journal is on |
| **False positive** | Ask what makes it not real, then `security-link {id} --false-positive --reason="…"` and `decision-log` when the journal is on |
| **Skip** | Leave it `new` for a later run |

`data.told[].sent: false` → print `data.hint` once and go on: the decision is kept, `unsent`.

- **Up to 8 in scope:** one AskQuestion per finding — prompt `{Scanner} {id} — {severity} · {type_label}: {title}` — options `Resolve` · `Waive` · `False positive` · `Skip for now` (skipped → **Resolve** for gate blockers, **Skip** otherwise).
- **More than 8:** batches of 5 with a compact table; AskQuestion `Resolve which in this batch?` with multi-select ids plus `None in this batch`.

**Never waive on your own**, and never call a finding a false positive the user did not. No id on the resolve list → stop after waives.

### 5. Plan groups (Lars)

Run `security-plan --ids={comma-separated resolve ids}`. Show **domains** (`data.domains`: kind → ids) then **groups** (`data.groups`), most severe first:

| Group | Domain | Severity | ids | Finding | Why grouped |
| --- | --- | --- | --- | --- | --- |

One AskQuestion, skippable: `{Scanner} — start triage on these {g} groups?` → `Yes` · `Adjust in chat` · `Cancel`. Skipped → **Yes**. On adjust, merge or split ids in chat, re-run `security-plan`, ask again. **Cancel** → stop.

### 6. Hand off to triage (Sophia)

For each group in plan order, run `security-issues --detail={lead id}` for its fix prompt, then activate `larapilot-triage` through the editor's skill mechanism — read its `SKILL.md` when the editor has none — **in this same turn**. The request is `Security finding from {Scanner}: {title} — {description}`, with this block (all ids of the group; omit a line the scanner does not give):

```text
Security finding
scanner: hackly
ids: 5f0e1d2c-…, 7a1b…
severity: high
kind: Dependency
title: guzzlehttp/psr7 header parsing
where: example/fjord-invoices
cves: CVE-2026-1111
fix: Upgrade guzzlehttp/psr7 to 2.7.0 or later
```

### 7. Record the decision

When the target skill reaches its **Next steps** with a spec code, run `security-link {ids} --spec={code}` and go to the next group.

### 8. Close

Run `security-issues --report` and give one line: the counts and `gate.verdict`. `unsent` not empty → `security-push` once. A finding leaves the list when the scanner no longer reports it: after the fix is merged, `security-scan`, then `/larapilot-security` again. Asked for the document for a client or an audit → `security-register`, and name the file.

## Preconditions

- A scanner associated (step 0), with its credentials
- A PRD or a backlog: triage measures a finding against what the product promised

## Output Boundaries

- No spec, PRD edit, plan, or code here — triage and the skill it hands to write them
- Do not paste the description and the fix of every finding in chat; name the report
- Do not print, store, or commit a credential
- Do not hand over again a finding whose `state` is `in_backlog`, `waived`, or `false_positive`
- `in_backlog` is not fixed: it stops the ship gate until the scanner no longer reports it
