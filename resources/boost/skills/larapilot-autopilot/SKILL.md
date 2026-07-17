---
name: larapilot-autopilot
description: Runs planning and implementation across multiple eligible backlog specs with optional filters (status, epic, max count). Use for "run everything", "autopilot the backlog", "implement all ready specs". Honors settings.auto_approve for optional auto DONE.
---

# Larapilot — Autopilot

Batch-run `larapilot-plan` and `larapilot-implement` across eligible specs. Optionally auto-approve when `settings.auto_approve` is `YES`.

## Shared Runtime

Read `.larapilot/shared-runtime.md` — especially **Project Settings** (`auto_approve`).

## Output Economy

**Minimal** — see `larapilot-autopilot` in shared-runtime. Per spec: `US-XXX: {from}→{to} | N tasks | OK or blocker`. Batch summary at end. Delegate plan/implement chat style when those flows run.

## The Team

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — batch credit risk, recommends `--max` and checkpoints before long autopilot runs |
| 🛡️ **Robert** | Code Reviewer — short checklist before auto-approve when enabled |

## Config & CLI

1. `php artisan larapilot:config-show` — read `data.settings.auto_approve`
2. `php artisan larapilot:spec-list`
3. `php artisan larapilot:metrics`
4. When auto-approving: `php artisan larapilot:spec-approve {code}`

## Selection Rules

Read the PRD delivery target (see Delivery Target in shared-runtime). For `Full Product` or `Enterprise`, confirm with the user before processing large batches.

Default pipeline per spec:

1. If status is `TODO` → run `larapilot-plan` for that spec
2. If status is `PLANNED` → run `larapilot-implement` for that spec
3. If status is `REVIEW` and `settings.auto_approve` is **`YES`** → short Robert checklist → `spec-approve` → `DONE`
4. If status is `REVIEW` and `auto_approve` is **`NO`** → leave in `REVIEW` (human `/larapilot-review` later)
5. Skip specs in `IN PROGRESS` or `DONE` unless explicitly requested

Respect user filters:

- `--epic EP-001` (if provided in user message)
- `--max 3` (maximum specs to process)
- `--stop-on-failure` (halt batch on first blocker)

## Execution

Process specs one at a time in priority order (same ordering as `spec-next`).

After each spec:

- Report progress in one line (Output Economy): code, status transition, task count, blocker if any
- On blocker: log, skip or stop per policy
- When implement finishes at `REVIEW` and `auto_approve` is `YES`: one-line checklist (criteria met / tests / residual risk) then `spec-approve` in the same turn — never invent approval if Critical blockers remain; leave in `REVIEW` and report

## Safety

- **`auto_approve: NO` (default)** — never call `spec-approve`; human gate via `/larapilot-review` remains required
- **`auto_approve: YES`** — autopilot may approve only after implement with no Critical open blockers; still never approve on test failure or explicit rework need
- Confirm with user before processing more than 5 specs — **Zoey** flags suspension risk and suggests `--max` or phased batches when Budget Sensitivity is `Tracked`
- Use stronger models for plan phases; cheaper models acceptable for implement when tasks have explicit contracts
- **Never spawn sub-agents in autopilot** — run plan/implement flows in the parent session; sub-agents run only inside `larapilot-implement` Phase 2 (and optional explore in `larapilot-plan` Stage 1) when those skills are active and `effort` is not `ECO`

## Laravel

Ensure Laravel Boost MCP is available during implementation phases for docs, schema, and test debugging.
