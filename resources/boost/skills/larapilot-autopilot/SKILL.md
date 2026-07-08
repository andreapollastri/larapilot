---
name: larapilot-autopilot
description: Runs planning and implementation across multiple eligible backlog specs with optional filters (status, epic, max count). Use for "run everything", "autopilot the backlog", "implement all ready specs".
---

# Larapilot — Autopilot

Batch-run `larapilot-plan` and `larapilot-implement` across eligible specs.

## Shared Runtime

Read `.larapilot/shared-runtime.md`.

## Config & CLI

1. `php artisan larapilot:config-show`
2. `php artisan larapilot:spec-list`
3. `php artisan larapilot:metrics`

## Selection Rules

Read the PRD delivery target (see Delivery Target in shared-runtime). For `Full Product` or `Enterprise`, confirm with the user before processing large batches.

Default pipeline per spec:

1. If status is `TODO` → run `larapilot-plan` for that spec
2. If status is `PLANNED` → run `larapilot-implement` for that spec
3. Skip specs in `IN PROGRESS`, `REVIEW`, or `DONE` unless explicitly requested

Respect user filters:

- `--epic EP-001` (if provided in user message)
- `--max 3` (maximum specs to process)
- `--stop-on-failure` (halt batch on first blocker)

## Execution

Process specs one at a time in priority order (same ordering as `spec-next`).

After each spec:

- Report progress: code, status transition, task count
- On blocker: log, skip or stop per policy

## Safety

- Never auto-approve specs (human gate via `larapilot-review` remains required)
- Confirm with user before processing more than 5 specs
- Use stronger models for plan phases; cheaper models acceptable for implement when tasks have explicit contracts

## Laravel

Ensure Laravel Boost MCP is available during implementation phases for docs, schema, and test debugging.
