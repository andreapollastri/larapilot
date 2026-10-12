# Larapilot Runtime — Versions

The version is a dimension of every step of the workflow, read by every workflow skill — inception, adopt, feature, triage, bug, spec, plan, implement, review, autopilot, schedule, release, ship. The ledger and the Gitflow mechanics are in `release.md`; this pack says how each skill works **inside a version**.

## Gate _(Zoey enforces)_

Honor **`data.settings.release_mode`**. When `NO`, ignore this pack: no version is asked, named, or checked. When `YES`, a story without a version is an open question, never a default.

## Where a skill reads the versions

`larapilot:context` returns **`data.release`** when release mode is on — no extra call:

| Key | Meaning |
| --- | --- |
| `current` | The version the work is on: the `release/x.y.z` branch checked out, else the one in progress, else the oldest in progress, else the oldest planned |
| `current_why` | `branch` · `only_in_progress` · `oldest_in_progress` · `oldest_planned` |
| `last_shipped` | The newest shipped version: what runs in production |
| `open` | Each planned / in-progress version: `specs`, `done`, `points`, `points_done`, `progress` (% of points delivered) |
| `unassigned` | Open stories no version claims |

More when needed: `release-show [--semver=]` (one version: its stories by state, epics, forecast end against its milestone, `risk`: `on_track` · `late` · `overdue`), `spec-list --release=x.y.z|none`, `release-list` (every version with `progress`).

## The version line _(every skill, every status update)_

Zoey's **Context estimate** line, and every phase report, names the version in scope: `· v1.2.0 3/5 (60%)`. A skill working on a story names the story's version; a skill working on the backlog names `current`. When `unassigned` is above zero, add `· N with no version`.

## Rules by phase

| Phase | Rule |
| --- | --- |
| **inception / adopt** | The roadmap is a list of versions (`release.md` → Sarah's table). Every MVP story lands in the first version; nothing leaves inception with no version. Adopt imports the shipped history first. |
| **feature / spec / triage → feature** | Every new story gets a target version **before the skill ends**: the `current` one, the next open one, or a new one Sarah names. One AskQuestion for the batch (spec → version table). Persist with `release-set --semver=x.y.z --add-spec=US-XXX` (it leaves any other open version) and `**Release:** x.y.z` in the body. "None" only when the user says so. |
| **bug** | A defect in a **shipped** version targets the patch line: the open `x.y.(z+1)` of `last_shipped`, else Sarah proposes it (`release-add --semver=x.y.Z+1 --title="Fixes for x.y.z"`). A defect in a story of an **open** version targets that version: it is not out yet. A Critical production defect is a hotfix of `last_shipped`. Say the version in the bug verdict. |
| **plan** | Plan the stories of `current` first: `spec-next` already orders them. The plan header carries `**Release:** x.y.z`. A spec with no version is planned only after its version is settled. |
| **implement** | Before TASK-00, read the story's version (`spec-next` → `data.release.version`, or its `**Release:**` line). **No version → stop**: AskQuestion — `current`, the next open version, or a new one — and persist before the first commit. A story of a version other than `current` → say so in one line; work lands on that version's branch (`release-feature`), never on another's. |
| **review** | The report ends with the version line after the verdict. An approval that delivers the last open story of its version says so and proposes `/larapilot-release` → ship. A PR whose base is not the story's release branch (Gitflow) is a FAIL. |
| **autopilot** | Works one version at a time: `spec-next` with no `--release` picks the `current` version's stories first. When `current` has no open story left, stop and hand over to `/larapilot-release` (notes, ship) — do not start the next version unless the user asked to run across versions. |
| **schedule** | Every open version has a milestone (`release` on the deadline). `release-show` → `risk` `late` / `overdue` is a date to settle with the user, version by version. |
| **release / ship** | See **Shipping a version** below. |

## Choosing the next number _(Jack decides; Sarah proposes)_

SemVer, read from the stories the version holds (`**Type:**`):

| The version holds | Bump |
| --- | --- |
| Only `Fix` stories | patch `x.y.Z` |
| A `Feature`, `Enhancement`, or `Change request` | minor `x.Y.0` |
| A change that breaks a public API, an import/export format, or data a client keeps | major `X.0.0` — AskQuestion, never silent |

Before `1.0.0` the product is pre-release: the MVP is `0.1.0`, and a breaking change is a minor bump. `1.0.0` is the first version the user calls generally available.

## Shipping a version _(Jack owns; Albert writes the notes; Sarah runs git)_

1. `release-show --semver=x.y.z` — the stories still open (`open`) and the risk.
2. **Open stories block the ship.** AskQuestion, one option each: deliver them first; move them to the next open version (`release-ship --defer-to=x.y.z`) or back to the backlog (`--defer-to=backlog`); ship anyway (`--force`, the notes list them as not delivered). `release-ship` refuses without one of these.
3. **Release notes** — `release-notes --semver=x.y.z` returns the draft from the stories (Added · Changed · Fixed · Not delivered yet · Moved). Albert rewrites it in the PRD language and in the words of the product — no codes, people, hours, or git in the public text — writes it to a `.larapilot/tmp-*.md` payload, and keeps it with `release-notes --semver=x.y.z --file=…` (`paths.release_notes`, `vX.Y.Z.md`); the payload is deleted. Commit it on the release branch **before** the ship ceremony.
4. `release-ship --semver=x.y.z` (`release.md` → Gitflow). Then `roadmap-show` (public wording) and the version line of the next `current`.

## Where people see it

The **Versions** page of the dashboard (`/larapilot/versions`, one page per version with its stories, forecast, milestone, and notes), the version scope of the Board, the Plan, and the Forecast (`?version=1.2.0,1.3.0`, `none`; kept while the person moves between pages), the version tag of the spec page, and `GET /larapilot/api/versions`. Point the user there instead of pasting the ledger in chat.
