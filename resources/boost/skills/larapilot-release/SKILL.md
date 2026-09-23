---
name: larapilot-release
description: "Manages semver releases and Gitflow release branches. Requires release_mode=YES. Italian: release, versione, semver."
---

# Larapilot — Release Management

Manage the **release ledger** and Gitflow **release branches** when `data.settings.release_mode` is `YES`. Sarah owns Git mechanics; Jack owns policy; Mark owns scope.

## Shared Runtime

Obey **Read protocol** in `.larapilot/shared-runtime.md`: file-read tool only, never `cat` / `head` / `sed`. A truncated preview is a failed load — read the remainder before any other step. Then read only the section files that index lists for this skill.

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
3. `php artisan larapilot:release-add` `{--semver=} {--title=} {--status=} {--specs=} {--branch=}` — status `in_progress` also cuts `release/x.y.z` (no checkout)
4. `php artisan larapilot:release-set` `{--semver=} {--status=} {--branch=} {--specs=} {--add-spec=} {--shipped-at=}` — moving to `in_progress` cuts the branch (no checkout)
5. `php artisan larapilot:release-cut` `{--semver=} {--no-checkout} {--push}` — create `release/x.y.z` from `develop` and check it out
6. `php artisan larapilot:release-feature` `{--semver=} {--spec=} {--slug=} {--no-checkout} {--push}` — `feature/US-XXX-*` from that release
7. `php artisan larapilot:release-sync` `{--semver=}` — merge `develop` into the release branch
8. `php artisan larapilot:release-ship` `{--semver=} {--push}` — merge to `main`, tag `vX.Y.Z`, back-merge `develop`, mark shipped
9. `php artisan larapilot:release-import` `{--dry-run}` — rebuild shipped releases from Git semver tags
10. `php artisan larapilot:spec-list` — assign specs, read progress

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
| `switch` | `release-cut --semver=` for the chosen release (Sarah). Skip the question when `release-list` → `git.needs_choice` is false |
| `progress` | Move status (`planned` → `in_progress` → `shipped`) |
| `import` | Import history from Git tags (`release-import`) |
| `ship` | Ship ceremony for one `in_progress` release → hand off to `/larapilot-ship` steps |

### 2. Roadmap proposal (Sarah)

When `roadmap` or new project planning: present Sarah's table per **Sarah's Release-Evolution Proposal** in `runtime-release.md`, then AskQuestion to confirm versions/titles/scope before any `release-add`.

Inputs: `spec-list`, backlog shape, `effort`, Lucille schedule when `lucille=YES`.

### 3. Gitflow (when `git_mode` is GITFLOW or GITFLOW_PUSH)

Sarah runs the commands in `runtime-release.md`. She does not type `git checkout -b`, `git merge`, or `git tag` for this flow.

- `release-add` / `release-set` to `in_progress` cuts `release/x.y.z` without switching
- `release-cut` checks that branch out
- `release-feature` is TASK-00 for an assigned spec (PR base is `release/x.y.z`)
- `release-sync` when `develop` has moved
- `release-ship` is the ship ceremony
- `--push` only for `GITFLOW_PUSH` or an explicit user request
- If `checked_out` is false, stop and report `reason`

### 4. Persist & confirm

After every mutation, re-run `release-list` and show the updated ledger. Log material choices with `decision-log` when `decision_log=YES`.

## Output Economy

**Structured terse** — tables for roadmaps and ledger; one-line Git commands for Sarah; no prose ledgers in chat (the YAML file is canonical).
