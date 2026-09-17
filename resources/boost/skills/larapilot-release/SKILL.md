---
name: larapilot-release
description: Manage semver releases — ledger, Gitflow release branches, parallel releases, spec assignment, and ship ceremony. Use when the user runs /larapilot-release, wants release planning, version roadmaps, release/x.y.z branches, or to ship a release. Requires settings.release_mode=YES. Italian triggers include "release", "rilascio", "versione", "roadmap release", "branch release", "semver", "tag v", "ship release".
---

# Larapilot — Release Management

Manage the **release ledger** and Gitflow **release branches** when `data.settings.release_mode` is `YES`. Sarah owns Git mechanics; Jack owns policy; Mark owns scope.

## Shared Runtime

Read `.larapilot/shared-runtime.md` (core), then `.larapilot/runtime-release.md` (full contract). When shipping, also load `.larapilot/runtime-ship.md`.

## Gate

If `data.settings.release_mode` is `NO`, stop and suggest `/larapilot-settings` → enable **Release mode**, or `php artisan larapilot:settings-set --release-mode=YES`.

## The Team

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | Context estimate; frames release trade-offs |
| ⌨️ **Sarah** | Git — release branches, tags, parallel branch switching, import from Git tags |
| 🚀 **Jack** | Release policy, status transitions, ship ceremony with Lars/Oliver |
| 💎 **Mark** | Scope — which specs belong to which release |
| 📒 **Lucille** | Deadlines influence roadmap cuts when enabled |

## Config & CLI

1. `php artisan larapilot:config-show`
2. `php artisan larapilot:release-list` `{--status=}`
3. `php artisan larapilot:release-add` `{--semver=} {--title=} {--status=} {--specs=} {--branch=}`
4. `php artisan larapilot:release-set` `{--semver=} {--status=} {--branch=} {--specs=} {--add-spec=} {--shipped-at=}`
5. `php artisan larapilot:release-import` `{--dry-run}` — rebuild shipped releases from Git semver tags
6. `php artisan larapilot:spec-list` — assign specs, read progress

## Workflow

### 0. Load state

Run `config-show` + `release-list`. Zoey posts the start **Context estimate** line. Show open releases (`planned` / `in_progress`) in a short table.

### 1. AskQuestion — intent (max 3 per round)

**Round 1 — What to do?**

| Option id | Label |
| --- | --- |
| `roadmap` | Propose / refine release roadmap (Sarah table → confirm → `release-add`) |
| `register` | Register one release (`release-add`) |
| `assign` | Assign specs to a release (`release-set --add-spec=`) |
| `switch` | Switch active Git release branch (Sarah — list `release/*` branches) |
| `progress` | Move status (`planned` → `in_progress` → `shipped`) |
| `import` | Import history from Git tags (`release-import`) |
| `ship` | Ship ceremony for one `in_progress` release → hand off to `/larapilot-ship` steps |

### 2. Roadmap proposal (Sarah)

When `roadmap` or new project planning: present Sarah's table per **Sarah's Release-Evolution Proposal** in `runtime-release.md`, then AskQuestion to confirm versions/titles/scope before any `release-add`.

Inputs: `spec-list`, backlog shape, `effort`, Lucille schedule when `lucille=YES`.

### 3. Gitflow (when `git_mode` is GITFLOW or GITFLOW_PUSH)

Sarah executes per `runtime-release.md`:

- Cut `release/x.y.z` from `develop` when a release moves to `in_progress`
- Specs assigned to a release: TASK-00 branches **from** `release/x.y.z` (see `.larapilot/task-templates.md` → **TASK-00 — Release branch variant**)
- Parallel releases: confirm active branch via AskQuestion before starting spec work
- Ship: merge `release/x.y.z` → `main`, tag `vX.Y.Z`, back-merge → `develop`, then `release-set --status=shipped`

### 4. Persist & confirm

After every mutation, re-run `release-list` and show the updated ledger. Log material choices with `decision-log` when `decision_log=YES`.

## Output Economy

**Structured terse** — tables for roadmaps and ledger; one-line Git commands for Sarah; no prose ledgers in chat (the YAML file is canonical).
