---
name: larapilot-settings
description: Configure persistent Larapilot project settings (effort, git mode, testing, auto-approve) via AskQuestion. Use when the user runs /larapilot-settings, wants to change token economy, Gitflow/push behavior, test depth, or auto-approve. Italian triggers include "impostazioni larapilot", "settings", "modalità eco", "gitflow push", "autoapprove".
---

# Larapilot — Project Settings

Persist project-wide Larapilot settings into `.larapilot/config.yaml`. All other skills read and honor them.

## Shared Runtime

Read `.larapilot/shared-runtime.md` — **Project Settings** (effort, git mode, testing, auto_approve).

## Output Economy

**High** — short confirmations only. AskQuestion carries the options; chat stays terse.

## The Team

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — frames trade-offs (tokens vs depth, human gate vs auto-approve) and confirms persistence |
| 🚀 **Jack** | DevOps — owns git_mode implications |
| 🧪 **Anne** | Test Architect — owns testing mode implications |
| 🛡️ **Robert** | Code Reviewer — owns auto_approve risk framing |

## Config & CLI

1. `php artisan larapilot:config-show` — read current `data.settings`
2. After answers: `php artisan larapilot:settings-set --effort=… --git-mode=… --testing=… --auto-approve=…`
3. Re-run `config-show` and confirm the saved values

Never edit `.larapilot/config.yaml` by hand from the skill — always use `larapilot:settings-set`.

## Workflow

### 0. Load current settings

Run `config-show`. Show one line with current values:

`effort={…} · git_mode={…} · testing={…} · auto_approve={…}`

If `.larapilot/config.yaml` is missing, suggest `php artisan larapilot:install` first (settings-set will scaffold defaults if needed, but install is preferred).

### 1. AskQuestion (Zoey — max 3 per round)

Use **AskQuestion**; persona intro in chat; options only in the tool. Mark the **current** value in each prompt when known.

**Round 1 — Effort, Git, Testing**

**1. Effort** — how hard Larapilot works (tokens & depth)

| Option | Meaning |
| --- | --- |
| `ECO` | Few tokens, reduced functionality — **no sub-agents**, **defer docs** (no README/PDF/diagrams; **OpenAPI still updated** when APIs change), skip deep checks, heavy reviews, E2E, optional research |
| `STANDARD` | Normal current behavior (**default**) |
| `MAX` | Treat every process/flow as **deep** — fuller persona rounds, deeper review, richer plans |

**2. Git mode** — branching & remote discipline

| Option | Meaning |
| --- | --- |
| `NO_GITFLOW` | No Gitflow — work on the current branch; no feature-branch/PR ceremony |
| `GITFLOW` | Gitflow locally: `feature/US-XXX-*`, atomic commits, PR prepared — **no automatic push** (**default**) |
| `GITFLOW_PUSH` | Full Gitflow **including** push + open/update internal PR toward `develop` after each task |

**3. Testing** — Anne's bar for plan/implement/review

| Option | Meaning |
| --- | --- |
| `MINIMAL` | Minimal Pest/PHPUnit for critical paths only — no Playwright, Dusk, browser E2E, viewport matrix |
| `NORMAL` | Standard feature/unit/policy/API tests + review evidence — **no** Playwright/Dusk/E2E (**default**) |
| `BEST` | All imaginable automated coverage — browser/E2E, Playwright or Dusk, viewport matrix, axe, Lighthouse when applicable |

**Round 2 — Auto-approve**

**4. Auto-approve** — skip the human DONE gate after implement (mainly `/larapilot-autopilot`)

| Option | Meaning |
| --- | --- |
| `NO` | Human gate required — stop at `REVIEW`; `/larapilot-review` waits for Approve / Request changes (**default**) |
| `YES` | After implement reaches `REVIEW`, autopilot may present a short checklist and call `spec-approve` without waiting for a human verdict |

Warn once when the user picks `YES`: this bypasses the usual human-in-the-loop DONE gate.

Defaults when unset: `STANDARD` / `GITFLOW` / `NORMAL` / `NO`.  
(`config.yaml` stores `auto_approve` as a boolean; `config-show` / CLI envelopes expose `YES` | `NO`.)

### 2. Persist

Map AskQuestion answers to CLI flags (normalize spaces/hyphens; `SI` → `YES`):

```bash
php artisan larapilot:settings-set \
  --effort=STANDARD \
  --git-mode=GITFLOW \
  --testing=NORMAL \
  --auto-approve=NO
```

Pass only the keys the user answered. On success, parse the JSON envelope (`kind: "settings"`) and confirm:

`Saved → effort=… · git_mode=… · testing=… · auto_approve=…`  
`Path: data.config_path` (or `.larapilot/config.yaml`)

### 3. Next steps

Remind once (one line): other skills honor these on next run via `config-show` → `data.settings`.

## Rules

- Do not change PRD, backlog, or code — settings only
- Do not re-ask unanswered skippable questions; keep previous values for skipped keys
- If the user wants a single setting changed, AskQuestion only that dimension
- Never invent persistence — CLI only
