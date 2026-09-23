---
name: larapilot-autopilot
description: "Plans and implements eligible backlog specs, optionally auto-approving when that setting is on. Use for run everything or autopilot the backlog."
---

# Larapilot — Autopilot

Batch-run `larapilot-plan` and `larapilot-implement` across eligible specs. Optionally auto-approve when `settings.auto_approve` is `YES`.

## Shared Runtime

Obey **Read protocol** in `.larapilot/shared-runtime.md`: file-read tool only, never `cat` / `head` / `sed`. A truncated preview is a failed load — read the remainder before any other step. Then read only the section files that index lists for this skill.

Read `.larapilot/shared-runtime.md` and **Spec worker** in `.larapilot/runtime-core-subagents.md`.

When this session delegates to a spec worker, stop after the every-skill rows plus `runtime-core-subagents.md`. Do not read `runtime-delivery` or its parts, `runtime-dev-docs`, `runtime-ops`, `task-templates`, the PRD, or `larapilot-implement`. `config-show --only=settings,paths,dev_docs`. Delivery target is `{paths.choices}` key `delivery_target`. Phase 2 uses **Review handoff** in that same sub-agents file. One `usage-log` at batch end comes from Output Economy.

When `effort` is `ECO`, or the editor has no writing sub-agent, this session runs plan and implement inline and reads `runtime-delivery` (every part), `runtime-dev-docs`, and `task-templates`.

An unattended run still writes the developer domain docs for every spec. The first-change catch-up runs inside the first implement worker when `data.dev_docs.documented` is `false`. Under the inline path, this session runs that catch-up before the first spec.

## Output Economy

**Minimal** — see `larapilot-autopilot` in shared-runtime. Per spec: `US-XXX: {from}→{to} | N tasks | OK or blocker`. Batch summary at end. Plan and implement chat stay inside the spec worker. This session prints the line, not the phase.

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

Delivery target is `{paths.choices}` key `delivery_target` (do not open the PRD). For `Full Product` or `Enterprise`, confirm with the user before processing large batches.

Default pipeline per spec. Walk forward in the same visit until `REVIEW`, `DONE`, or a blocker — a `TODO` spec is planned and then implemented before the next spec starts.

1. If status is `TODO` → plan worker (or inline plan), then continue
2. If status is `PLANNED` → implement worker Phase 1, then parent Phase 2, then continue
3. If status is `REVIEW` and `settings.auto_approve` is **`YES`** → short Robert checklist → `spec-approve` → `DONE`
4. If status is `REVIEW` and `auto_approve` is **`NO`** → leave in `REVIEW` (human `/larapilot-review` later)
5. Skip specs in `IN PROGRESS` or `DONE` unless explicitly requested

Respect user filters:

- `--epic EP-001` (if provided in user message)
- `--max 3` (maximum specs to process)
- `--stop-on-failure` (halt batch on first blocker)

## Execution

Process specs one at a time in priority order (same ordering as `spec-next`). Follow **Spec worker** in `.larapilot/runtime-core-subagents.md` — that section is the contract. Do not re-derive it.

When `effort` is `ECO`, or the editor cannot spawn a writing sub-agent: run `larapilot-plan` and `larapilot-implement` inline in this session, including their own sub-agent rules (none under `ECO`).

Otherwise, for each spec: plan worker, then parent `validate-plan` / `spec-plan`; parent `spec-start`, then implement worker (Phases 0–1 only); parent `task-done` and Phase 2 (Robert + Lars from this session); parent `spec-review`. One worker at a time. The worker's final message is `OK plan …`, `OK implement …`, or `BLOCKED …` — copy the batch line into chat and discard the rest.

After each spec:

- Report progress in one line (Output Economy): code, status transition, task count, blocker if any
- On blocker: log, skip or stop per policy. A `BLOCKED` return is an AskQuestion on this session, then resume or re-spawn with the decision — not a silent skip
- When implement finishes at `REVIEW` and `auto_approve` is `YES`: one-line checklist (criteria met / tests / residual risk) then `spec-approve` in the same turn — never invent approval if Critical blockers remain; leave in `REVIEW` and report

## Safety

- **`auto_approve: NO` (default)** — never call `spec-approve`; human gate via `/larapilot-review` remains required
- **`auto_approve: YES`** — autopilot may approve only after implement with no Critical open blockers; still never approve on test failure or explicit rework need
- Confirm with user before processing more than 5 specs — **Zoey** flags suspension risk and suggests `--max` or phased batches when Budget Sensitivity is `Tracked`
- Use stronger models for the plan worker; cheaper models are acceptable for the implement worker when tasks have explicit contracts
- **One spec worker at a time** — never a worker per spec in parallel, and never a nested sub-agent inside the worker. Robert and Lars spawn from this session after the implement worker returns. `ECO` spawns nothing

## Laravel

Ensure Laravel Boost MCP is available during implementation phases for docs, schema, and test debugging.
