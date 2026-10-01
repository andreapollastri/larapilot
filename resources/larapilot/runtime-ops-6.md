## Project Trackers — Linear, Asana, Jira, Trello, ClickUp, Monday _(Matt owns — integration surface)_

A **project tracker** is where the rest of the organisation follows delivery. Larapilot mirrors the backlog into it so a PM, a client, or a designer never has to open `backlog.yaml`. The tracker is a **window**, not a second workflow.

**Direction is asymmetric, not symmetric.** Push is authoritative: `.larapilot/` decides what a story says and which column it sits in. Pull is a **report** — it reads remote state and describes drift, and writes back only when the operator passes `--apply`.

### What maps to what

| Larapilot | Tracker |
| --- | --- |
| User story (`US-XXX`) | Issue / task / card / item, titled `US-XXX — Title` |
| Plan task (`TASK-XX`) | **Native** sub-issue, subtask, subitem, or checklist item |
| Spec body + priority/points/epic | Issue description (Monday needs a long-text column) |
| Workflow status | Workflow state · status · section · list · status column label |

All of it comes from `larapilot:tracker-push` — never create or edit cards by hand from a skill.

### Ownership rules

| Rule | Why |
| --- | --- |
| API keys live in **`.env`** only, never in `.larapilot/` | `.larapilot/` is committed; a token there is a leaked token |
| `.larapilot/tracker.yaml` **is** committed | It maps specs to remote ids — without a shared map, every machine creates duplicate cards |
| Spec text edited **in the tracker** is overwritten on the next push | The card description carries a footer saying exactly that |
| Statuses only move to columns that already exist | The push fails with the real column names instead of inventing one |
| **DONE is never applied from a tracker** | DONE is a human review gate that records the merge commit — `spec-approve` owns it |
| One provider active at a time; links stored per provider | Status maps are per-tool; switching back must not lose the old mapping |

### Status mapping

Each Larapilot status maps to one label in the tracker's own vocabulary. Several statuses may share a column (`TODO` and `PLANNED` → "Todo") — that is **not** drift: a story is in sync when its *forward* mapping matches the remote label. Reverse mapping is used only once drift is real, and resolves an ambiguous label to the earliest workflow slot. A remote label outside the map yields drift with no suggestion, never a guess.

### Sync cadence

Push after backlog changes, planning, and review milestones; a CI step on the default branch is the reliable option (**Jack**). Pull before a standup or planning session to see what moved in the tool. Unchanged stories are skipped without an API call, so re-running is cheap.

### Security boundary _(Lars + Matt)_

The tracker credential is a **write-capable** key for a third-party workspace — a tighter boundary than the read-only Larapilot API. It belongs in `.env` (and in CI secrets), never in the repo, never in chat, never in a skill's output. `config-show` and `tracker-status` report *whether* a credential is present, never its value. Scope the key to the one board/project being synced where the provider allows it.

Ownership: **Matt** owns provider choice, status mapping, and link hygiene; **Mark** owns what non-developers should see on the board; **Jack** owns the CI push step; **Lars** owns the credential boundary.
