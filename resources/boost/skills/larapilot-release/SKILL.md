---
name: larapilot-release
description: "Manages the versions of the product: semver releases, which story targets which version, progress and forecast per version, release notes, and Gitflow release branches. Requires release_mode=YES. Italian: release, versione, versioni, semver, note di rilascio."
---

# Larapilot — Release Management

Manage the **versions of the product** when `data.settings.release_mode` is `YES`: the release ledger, which story targets which version, how far each version is, its release notes, and the Gitflow **release branches**. Sarah owns Git mechanics; Jack owns policy; Mark owns scope; Albert writes the notes.

## Context

`php artisan larapilot:context release` — with `--session={token}` when this conversation already holds one, `--fresh` after a compaction. Read every file under `data.runtime.read`, none under `loaded`. Settings, paths, and `data.project` come from that envelope: no `config-show`. The contracts are `release.md` (ledger, Gitflow) and `versions.md` (the version in every step, numbering, shipping). `data.release` already holds the version the work is on, each open version's progress, and the stories with none. When the release is shipped in this session, read `ship-2.md` (on demand) or hand over to `/larapilot-ship`.

## Gate

If `data.settings.release_mode` is `NO`, stop and suggest `/larapilot-settings` → enable **Release mode**, or `php artisan larapilot:settings-set --release-mode=YES`.

## The Team

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | Context estimate; frames release trade-offs |
| ⌨️ **Sarah** | Git — release branches, tags, parallel branch switching, import from Git tags |
| 🚀 **Jack** | Release policy, status transitions, ship ceremony with Lars/Oliver |
| 💎 **Mark** | Scope — which specs belong to which release |
| 📒 **Lucille** | Deadlines influence roadmap cuts when enabled; milestones per version |
| 📝 **Albert** | Release notes — the draft rewritten in the PRD language and the words of the product |

## Config & CLI

1. `php artisan larapilot:release-list` `{--status=}` — every version with `progress` (stories and points by state, `forecast_end`, `milestone`, `risk`), `current`, `unassigned`
2. `php artisan larapilot:release-show` `{--semver=}` — one version: its stories by state, epics, open stories, forecast against its milestone; the version the work is on when omitted
3. `php artisan larapilot:release-notes` `{--semver=} {--file=} {--write}` — the release notes: the draft from the stories, or the team's text; `--file=` keeps a rewritten text, `--write` keeps the draft (`{paths.release_notes}`)
4. `php artisan larapilot:release-add` `{--semver=} {--title=} {--status=} {--specs=} {--branch=}` — status `in_progress` also cuts `release/x.y.z` (no checkout)
5. `php artisan larapilot:release-set` `{--semver=} {--status=} {--branch=} {--specs=} {--add-spec=} {--remove-spec=} {--shipped-at=}` — moving to `in_progress` cuts the branch (no checkout); `--add-spec=` moves the story out of any other open version (`moved_from`)
6. `php artisan larapilot:release-cut` `{--semver=} {--no-checkout} {--push}` — create `release/x.y.z` from `develop` and check it out
7. `php artisan larapilot:release-feature` `{--semver=} {--spec=} {--slug=} {--no-checkout} {--push}` — `feature/US-XXX-*` from that release
8. `php artisan larapilot:release-sync` `{--semver=}` — merge `develop` into the release branch
9. `php artisan larapilot:release-ship` `{--semver=} {--push} {--defer-to=} {--force}` — merge to `main`, tag `vX.Y.Z`, back-merge `develop`, mark shipped. It refuses a version with open stories (`details.open`) unless `--defer-to=x.y.z|backlog` moves them once the version is out, or `--force` ships them as not delivered — the user's choice, never yours. When `data.settings.hooks` is `YES` it runs the hooks of `release.shipped` (`hooks.md`): a refusal with `details.hooks` means nothing was merged — fix it or report it; a skill it names runs first, then `--skill-hooks-done=`; the skills under `data.hooks.after.skills` run before the release is reported
10. `php artisan larapilot:release-import` `{--dry-run}` — rebuild shipped releases from Git semver tags
11. `php artisan larapilot:spec-list` `{--release=x.y.z|none}` — the stories of a version, or of none; every item carries its `release`

## Workflow

### 0. Load state

Run `context` + `release-list`. Zoey posts the start **Context estimate** line with the version line. Show the open versions in one short table — version, status, stories done/total, progress, forecast vs milestone, risk — and the count of open stories with no version. Point to the **Versions** page of the dashboard for the rest.

### 1. AskQuestion — intent (max 3 per round)

**Round 1 — What to do?**

| Option id | Label |
| --- | --- |
| `status` | Where a version stands: `release-show` → stories by state, open ones, forecast vs milestone, risk |
| `roadmap` | Propose / refine release roadmap (Sarah table → confirm → `release-add`) |
| `register` | Register one release (`release-add`) |
| `assign` | Give versions to stories — the open ones with none first (`spec-list --release=none`), one table spec → version, one AskQuestion, then `release-set --add-spec=` (moves) / `--remove-spec=` |
| `switch` | `release-cut --semver=` for the chosen release (Sarah). Skip the question when `release-list` → `git.needs_choice` is false |
| `progress` | Move status (`planned` → `in_progress` → `shipped`) |
| `import` | Import history from Git tags (`release-import`) |
| `notes` | Release notes of a version: `release-notes` draft → Albert rewrites → `release-notes --file=` |
| `ship` | Ship ceremony for one `in_progress` release: **Shipping a version** (`versions.md`) — open stories, notes, `release-ship` → hand off to `/larapilot-ship` for deploy |

### 2. Roadmap proposal (Sarah)

When `roadmap` or new project planning: present Sarah's table per **Sarah's Release-Evolution Proposal** (`release.md`), then AskQuestion to confirm versions/titles/scope before any `release-add`.

Inputs: `spec-list`, backlog shape, `effort`, Lucille schedule when `lucille=YES`.

**Public roadmap** — the Roadmap page (and its public link when `public_roadmap` is `YES`) is built from the releases. After every `release-add` / `release-set` / ship, run `php artisan larapilot:roadmap-show` and, when a title or a story reads as internal, give it public wording with `larapilot:roadmap-set --semver=x.y.z [--item=US-XXX] --title="…" --summary="…" [--target="Q1 2027"]`, or `--hide` it. Public wording never names codes, people, hours, costs, or git.

### 3. Gitflow (when `git_mode` is GITFLOW or GITFLOW_PUSH)

Sarah runs the commands in `release.md`. She does not type `git checkout -b`, `git merge`, or `git tag` for this flow.

- `release-add` / `release-set` to `in_progress` cuts `release/x.y.z` without switching
- `release-cut` checks that branch out
- `release-feature` is TASK-00 for an assigned spec (PR base is `release/x.y.z`)
- `release-sync` when `develop` has moved
- `release-ship` is the ship ceremony
- `--push` only for `GITFLOW_PUSH` or an explicit user request
- If `checked_out` is false, stop and report `reason`

### 4. Persist & confirm

After every mutation, re-run `release-list` and show the updated ledger as the version line of each version touched. Log material choices with `decision-log` when `decision_log=YES`.

## Output Economy

**Structured terse** — tables for roadmaps and ledger, the version line (`v1.2.0 3/5 (60%) · forecast 2026-11-20 · milestone 2026-11-30`) for one version; one-line Git commands for Sarah; no prose ledgers in chat (the YAML file is canonical).
