# Larapilot Runtime — Release

Phase pack for **`larapilot-release`**, and for the release-touching steps of **`inception`**, **`adopt`**, **`feature`**, **`plan`**, and **`ship`**. Read `.larapilot/shared-runtime.md` (core) first.

## Gate _(Zoey enforces)_

Honor **`data.settings.release_mode`** from `config-show` (see **Project Settings** in the core). When `NO` (**default**), ignore this entire pack: no release ledger, no `release/*` branch obligations, no release questions in any skill. Everything behaves exactly as before.

## Release Ledger Contract _(Jack owns policy; Sarah owns Git mechanics)_

Releases are tracked in **`.larapilot/releases.yaml`** (path key `paths.releases`). **Never hand-edit it** — read and write **only** via the `larapilot:release-*` artisan commands.

Each release entry carries:

| Field | Rule |
| --- | --- |
| `version` | SemVer `X.Y.Z`, unique across the ledger |
| `title` | Short release name |
| `status` | `planned` \| `in_progress` \| `shipped` |
| `branch` | `release/x.y.z` |
| `specs` | Assigned `US-XXX` codes |
| `created_at` | Set by `release-add` |
| `shipped_at` | Set by `release-set --status=shipped` (or `--shipped-at=`) |

| Command | Options | Use |
| --- | --- | --- |
| `php artisan larapilot:release-list` | `{--status=}` | List releases (optionally filtered by status) with assigned specs |
| `php artisan larapilot:release-add` | `{--semver=} {--title=} {--status=planned} {--specs=US-001,US-002}` | Register a new release in the ledger |
| `php artisan larapilot:release-set` | `{--semver=} {--status=} {--branch=} {--specs=} {--add-spec=} {--shipped-at=}` | Update an existing release: status transitions, branch, full spec list (`--specs=`), or append one spec (`--add-spec=`) |

## Status Lifecycle _(Mark owns scope; Jack owns transitions)_

| Status | Meaning | Transition |
| --- | --- | --- |
| `planned` | Release registered, scope negotiable, no branch yet | → `in_progress` when the first spec starts work (Sarah opens the branch when Gitflow is active) |
| `in_progress` | Actively being built; assigned specs branch into its `release/x.y.z` when Gitflow is active | → `shipped` only via the ship ceremony below |
| `shipped` | Merged to `main`, tagged `vX.Y.Z`, deployed or deploy-ready | Terminal — never reopen; a follow-up is a **new** release |

Only `release-set` moves a release between statuses. Parallel releases are normal: several `planned`/`in_progress` entries may coexist.

## Starting Release _(Sarah proposes; Mark confirms scope)_

- **Inception (new project)** — when `release_mode` is `YES`, **Sarah** proposes a release roadmap from the backlog shape, the `effort` setting, and Lucille's deadlines (when enabled), then asks via AskQuestion for the **starting release** (default suggestion `0.1.0`). Persist with `release-add` and mirror in the PRD under `## MVP Scope` as `**Starting Release:** …`.
- **Adopt (existing project)** — first run `php artisan larapilot:release-import` (Sarah) to rebuild **shipped** releases from Git semver tags (`vX.Y.Z` / `X.Y.Z`). Present the imported table, then AskQuestion for the **current production version** (they may state none or a version not yet tagged). Persist with `release-add`: status `shipped` when it is what runs live today, `in_progress` when it names the next release being prepared. When parallel `release/*` branches exist locally, list them and AskQuestion which release branch to track as **in_progress**.

## Gitflow Obligations _(Jack owns policy; Sarah owns Git mechanics)_

When `release_mode` is `YES` **and** `git_mode` is `GITFLOW` or `GITFLOW_PUSH`, classic Gitflow is **mandatory**:

| Rule | Requirement |
| --- | --- |
| **Release branches** | Every `in_progress` release has its `release/x.y.z` branch, cut from `develop` (`git checkout -b release/x.y.z` at `release-add` / first assigned spec) |
| **Assigned features** | A spec assigned to an `in_progress` release branches **from** `release/x.y.z` and merges **into** `release/x.y.z` — **not** `develop` (TASK-00 variant in `.larapilot/task-templates.md`) |
| **Unassigned features** | Specs with no release keep the classic flow: branch from `develop`, merge into `develop` |
| **Parallel releases** | Multiple open `release/*` branches coexist; each carries its own assigned features; keep them rebased on `develop` when it drifts (Sarah leads) |
| **Ship ceremony** | Merge `release/x.y.z` → `main`, tag `vX.Y.Z`, back-merge → `develop`, then `php artisan larapilot:release-set --semver=x.y.z --status=shipped` |

When `release_mode` is `YES` **and** `git_mode` is `NO_GITFLOW`: releases are **tracked only** in `.larapilot/releases.yaml` — no branch ceremony; shipping means tagging `vX.Y.Z` on the main working branch and running `release-set --status=shipped`.

## Spec ↔ Release Assignment _(Mark owns scope)_

- When releases are open (`planned` or `in_progress`), `/larapilot-feature` asks via AskQuestion which open release the new spec belongs to — options: each open release, a **new release** (Sarah proposes the version), or **none/backlog**.
- Assignment is persisted with `release-set --add-spec=US-XXX` **after** `spec-add`, and the spec body carries a `**Release:** x.y.z` line (next to `**Blocked by:**` / `**Type:**`).
- A spec with no `**Release:**` line is unassigned and follows the classic `develop` flow.

## Sarah's Release-Evolution Proposal _(Sarah proposes; user confirms)_

Sarah presents release roadmaps and evolution proposals as a **short table** in chat, then confirms via **AskQuestion before persisting anything** with `release-add`:

| Version | Title | Scope (specs) | Rationale |
| --- | --- | --- | --- |
| `0.1.0` | {{TITLE}} | US-001, US-002, US-003 | {{why this cut — backlog shape, effort, deadlines}} |
| `0.2.0` | {{TITLE}} | US-004, US-005 | {{what the next increment adds}} |

Inputs: backlog shape (epics, MoSCoW, points), product progress from `spec-list` DONE/TODO counts, the `effort` setting, and Lucille's deadlines when `settings.lucille` is `YES`. The user may edit versions, titles, or scope before any `release-add` call.

## Parallel releases & branch switching _(Sarah owns Git mechanics)_

- Multiple `planned` / `in_progress` releases may coexist — each with its own `release/x.y.z` branch when Gitflow is active.
- Before starting work on a spec, Sarah confirms the **active release branch** via AskQuestion when more than one `in_progress` release is open (options: each open release branch + **stay on current branch**).
- Switching release context is a normal Git operation (`git checkout release/x.y.z` or the spec's `feature/US-XXX-*` branched from it) — Sarah leads; never mix commits across release branches without an explicit merge plan.
- `/larapilot-release` is the dedicated skill to list releases, register new ones, move statuses (`planned` → `in_progress` → `shipped`), assign specs, and prepare the ship ceremony with Jack.
