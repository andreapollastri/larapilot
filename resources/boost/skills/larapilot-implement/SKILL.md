---
name: larapilot-implement
description: Implements a planned Larapilot spec by executing its technical plan. Use when the user wants to implement a PLANNED spec, start coding a backlog item, or execute sprint work. Do not use for discovery, backlog creation, or planning.
---

# Larapilot — Spec Implementation

Execute a planned spec: code, tests, review, handoff to REVIEW.

## Shared Runtime

Read `.larapilot/shared-runtime.md`.

## The Team

| Agent | Role |
| --- | --- |
| 🔧 **Alex** | Full-Stack Developer |
| 🧪 **Anne** | Test Architect |
| 🛡️ **Robert** | Code Reviewer — code quality, plan adherence, Laravel conventions |
| 🔐 **Lars** | Security Expert — OWASP-aligned security assessment |

## Config & CLI

1. `php artisan larapilot:config-show`
2. `php artisan larapilot:spec-show {code}` OR `php artisan larapilot:spec-next --status=PLANNED`
3. `php artisan larapilot:spec-start {code}`
4. `php artisan larapilot:task-done {code} {taskId}` (after each task)
5. `php artisan larapilot:spec-review {code}`

## Execution Contract

1. **Autonomous by default** — stop only for explicit blockers (scope change, missing prerequisite spec, semantic test breakage).
2. Work under `data.workdir` for all file operations.
3. Run connector commands from `data.project_root`.
4. After `spec-start`, re-run `spec-show` if worktree may have been created.

## Laravel Implementation

Use **Laravel Boost** throughout:

- `Search Docs` before unfamiliar APIs
- `Database Schema` / `Database Query` for data work
- `Tinker` for quick verification
- `Application Info` for package versions
- `Last Error` / `Read Log Entries` when debugging

Follow Laravel best practices from Boost guidelines: thin controllers, Form Requests, policies, eager loading, Pest tests.

## Workflow

### Phase 0 — Load plan

From `spec-show`: `data.spec`, `data.tasks`, `data.workdir`.

### Phase 1 — Execute tasks in waves

Group tasks by dependencies. For each task:

1. Alex implements per task body contract
2. Anne writes/runs tests (`php artisan test` or `./vendor/bin/pest`)
3. `task-done` when verified

### Phase 2 — Review

Robert reviews: plan adherence, Laravel conventions, test coverage, code quality.

Lars runs an OWASP-aligned security pass (Top 10 mapping, `composer audit`, auth/access-control checks). Fix Critical/High findings before handoff; document Medium findings.

Fix blockers autonomously; loop until clean or explicit blocker.

### Phase 3 — Handoff

`php artisan larapilot:spec-review {code}` with summary note.

Report: spec code, tasks completed, tests run, review outcome.
