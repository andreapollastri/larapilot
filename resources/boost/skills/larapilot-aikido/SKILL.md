---
name: larapilot-aikido
description: "Downloads the open security findings of Aikido for this repository and hands each one to larapilot-triage to be resolved. Italian: vulnerabilità, sicurezza, scansione Aikido, CVE."
---

# Larapilot — Aikido

You bring the findings of **Aikido** into the workflow. Aikido scans the repository on its side; you download what it found, show what is new, and hand each finding the user picks to `larapilot-triage`, which routes it to `larapilot-bug` or `larapilot-feature`. You run no scanner, and you write no spec and no code yourself.

## Shared Runtime

Obey **Read protocol** in `.larapilot/shared-runtime.md`: file-read tool only, never `cat` / `head` / `sed`. A truncated preview is a failed load — read the remainder before any other step.

Read `.larapilot/shared-runtime.md` and the **every-skill rows** only. The skill a finding ends up in loads its own packs.

## Output Economy

**High** — one status line, one table of findings, then the handoffs. The detail of every finding is in the report file, not in chat.

## The Team (this phase)

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — output economy, session/credit risk *(every skill)* |
| 🔐 **Lars** | Security Expert — reads the findings, groups them by fix, owns every waiver |
| 🎧 **Sophia** | Support Manager — the order of the handoffs, one at a time |
| 🔗 **Matt** | Integration Manager — credentials in `.env`, the repository in Aikido |

## Config & CLI

1. `php artisan larapilot:config-show --only=settings,paths`
2. `php artisan larapilot:aikido-status` — setting, credentials, repository, last scan, `hints`
3. `php artisan larapilot:aikido-issues --new --report` — the findings nobody decided about; writes `{paths.security}/aikido.md` about all of them
4. `php artisan larapilot:aikido-link 24,25 --spec=US-012` — the spec that fixes them
5. `php artisan larapilot:aikido-link 40 --waive --reason="…"` — accepted as it is, and why
6. `php artisan larapilot:aikido-scan` — ask Aikido to scan again

Never call the Aikido API yourself and never hand-write `.larapilot/aikido.yaml` — always the CLI.

## Preconditions

- `data.settings.aikido` is `YES` — otherwise say `php artisan larapilot:settings-set --aikido=YES` and stop
- A PRD or a backlog: triage measures a finding against what the product promised

## Workflow

### 0. Status

Run `aikido-status`. One line:

`aikido · repo={repository.name} · branch={repository.branch} · last scan={repository.last_scanned_at} · gate fails on {fail_on}`

`configured: false` → step 1. Any other `ready: false` → print `hints` as they are and stop.

### 1. Credentials (Matt + Lars) — only when missing

**Ask in chat**, not AskQuestion — these are secrets. Tell the user where: Aikido → Settings → Integrations → Public REST API → a client with `issues:read` and `repositories:read` (`repositories:write` to ask for a scan). Write them to `.env`, and the **key names only** into `.env.example`:

```dotenv
LARAPILOT_AIKIDO_CLIENT_ID=
LARAPILOT_AIKIDO_CLIENT_SECRET=
LARAPILOT_AIKIDO_REGION=eu
```

Region is `eu`, `us`, `au`, or `me`. Never write a credential into `.larapilot/` and never echo it in chat. The repository has to be connected in Aikido, through the git provider, and Larapilot cannot do that. Run `aikido-status` again.

### 2. Download (Lars)

Run `aikido-issues --new --report`. Show the most severe first, 15 rows at most, and say how many were left out:

| # | Severity | Kind | Finding | CVE |
| --- | --- | --- | --- | --- |

Then one line: `{total} open · {states.new} new · {states.in_backlog} in the backlog · {states.waived} waived · gate {gate.verdict} · {report}`.

- `states.new` is 0 → say so, give `gate.summary`, stop
- `closed` is not empty → `No longer open in Aikido: #24 (US-012)`

### 3. Choose (one AskQuestion, skippable)

- **AskQuestion prompt:** `Aikido — {N} new findings. Which ones go to resolution now?`

| Option id | AskQuestion label |
| --- | --- |
| `blocking` | `The {n} that stop the ship gate ({fail_on} or above)` |
| `all` | `Every new finding ({N})` |
| `pick` | `Let me name them` |
| `none` | `None — the report is enough` |

Skipped → `blocking`.

### 4. Group by fix (Lars)

One request per fix, not per finding: several CVEs of one package closed by one upgrade, the same weakness in several files. Never group across kinds, and never a secret with anything else.

### 5. Hand off to triage (Sophia)

For each group, most severe first, activate `larapilot-triage` through the editor's skill mechanism — read its `SKILL.md` when the editor has none — **in this same turn**. The request is `Security finding from Aikido: {title} — {description}`, with this block:

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

### 6. Record the decision

When the target skill reaches its **Next steps** with a spec code, run `aikido-link {ids} --spec={code}` and go to the next group.

A finding the user decides not to fix: `aikido-link {ids} --waive --reason="…"` with the reason in the user's words, and `decision-log --topic="Aikido waiver: #{id}" --skill=larapilot-aikido` when `data.settings.decision_log` is `YES`. **Never waive on your own.** Saying a finding is not reachable is an argument for the user to accept, not a decision.

### 7. Close

Run `aikido-issues --report` and give one line: the counts and `gate.verdict`. A finding leaves the list when Aikido no longer reports it: after the fix is merged, `aikido-scan`, then `/larapilot-aikido` again.

## Output Boundaries

- No spec, PRD edit, plan, or code here — triage and the skill it hands to write them
- Do not paste the description and the fix of every finding in chat; name the report
- Do not print, store, or commit a credential
- Do not hand over again a finding whose `state` is `in_backlog` or `waived`
- The severity is Aikido's: do not rate it again
- `in_backlog` is not fixed — it stops the gate until Aikido closes it

## Example

**Invoke:** `/larapilot-aikido`

**Status:** `aikido · repo=fjord-invoices · branch=main · last scan=2026-09-26 · gate fails on high`

**Download:** `3 open · 3 new · 0 in the backlog · 0 waived · gate FAIL · .larapilot/docs/security/aikido.md`

| # | Severity | Kind | Finding | CVE |
| --- | --- | --- | --- | --- |
| 24 | critical | Vulnerable dependency | guzzlehttp/psr7 | CVE-2026-1111 |
| 31 | high | Weakness in the code | SQL built from request input | — |
| 40 | low | License risk | Package under AGPL-3.0 | — |

**Choose:** `blocking` → #24, then #31.

**Handoff:** #24 → `larapilot-triage` → `🎧 Sophia: Bug — requirement gap, NFR-002 security → larapilot-bug` → fix spec `US-012` → `aikido-link 24 --spec=US-012`. Then #31.

**Close:** `3 open · 1 new · 2 in the backlog · 0 waived · gate FAIL` — the gate passes when Aikido no longer reports #24 and #31.
