---
name: larapilot-settings
description: Configure persistent Larapilot project settings (effort, backlog granularity, git mode, testing, auto-approve) via AskQuestion. Use when the user runs /larapilot-settings, wants to change token economy, backlog/spec granularity, Gitflow/push behavior, test depth, or auto-approve. Italian triggers include "impostazioni larapilot", "settings", "modalità eco", "granularità backlog", "meno specs", "gitflow push", "autoapprove".
---

# Larapilot — Project Settings

Persist project-wide Larapilot settings into `.larapilot/config.yaml`. All other skills read and honor them.

## Shared Runtime

Read `.larapilot/shared-runtime.md` — **Project Settings** (effort, backlog granularity, git mode, testing, auto_approve).

## Output Economy

**High** — short confirmations only. AskQuestion carries the options; chat stays terse.

## The Team

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — frames trade-offs (tokens vs depth, human gate vs auto-approve) and confirms persistence |
| 💎 **Mark** | Product Manager — owns backlog granularity implications (spec/epic count vs traceability) |
| 🚀 **Jack** | DevOps — owns git_mode implications |
| 🧪 **Anne** | Test Architect — owns testing mode implications |
| 🛡️ **Robert** | Code Reviewer — owns auto_approve risk framing |

## Config & CLI

1. `php artisan larapilot:config-show` — read current `data.settings`
2. After answers: `php artisan larapilot:settings-set --effort=… --backlog=… --git-mode=… --testing=… --auto-approve=…`
3. Re-run `config-show` and confirm the saved values

Never edit `.larapilot/config.yaml` by hand from the skill — always use `larapilot:settings-set`.

## Workflow

### 0. Load current settings

Run `config-show`. Show one line with current values:

`effort={…} · backlog={…} · git_mode={…} · testing={…} · auto_approve={…}`

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
| `ECO` | `ECO — save tokens: no sub-agents, lighter docs (OpenAPI still when APIs change), skip deep/E2E` |
| `STANDARD` | `STANDARD — normal depth (default)` |
| `MAX` | `MAX — deep on every flow: fuller personas, sub-agents, richer plans/reviews` |

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

Defaults when unset: `STANDARD` / `STANDARD` / `GITFLOW` / `NORMAL` / `NO`.  
(`config.yaml` stores `auto_approve` as a boolean; `config-show` / CLI envelopes expose `YES` | `NO`.)

### 2. Persist

Map AskQuestion answers to CLI flags (normalize spaces/hyphens; `SI` → `YES`):

```bash
php artisan larapilot:settings-set \
  --effort=STANDARD \
  --backlog=STANDARD \
  --git-mode=GITFLOW \
  --testing=NORMAL \
  --auto-approve=NO
```

Pass only the keys the user answered. On success, parse the JSON envelope (`kind: "settings"`) and confirm:

`Saved → effort=… · backlog=… · git_mode=… · testing=… · auto_approve=…`  
`Path: data.config_path` (or `.larapilot/config.yaml`)

### 3. Next steps

Remind once (one line): other skills honor these on next run via `config-show` → `data.settings`.

## Rules

- Do not change PRD, backlog, or code — settings only
- Do not re-ask unanswered skippable questions; keep previous values for skipped keys
- If the user wants a single setting changed, AskQuestion only that dimension
- Never invent persistence — CLI only
