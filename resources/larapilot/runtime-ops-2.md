Part of this runtime pack. The pack file is an index. Read with the editor file-read tool, never `cat`.

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

## Usage Ledger & Schedule _(Lucille owns)_

**Lucille** enters every skill **quietly** by default (`settings.lucille: true` / `YES`): she records AI/session **tokens** and wall-clock **time**, categorized so the project always has committed metrics for the Larapilot dashboard (token charts on Usage, Gantt on Plan, consolidated Markdown reports). **Exclusion is opt-out only** — when `settings.lucille` is explicitly `false` / `NO`, skills skip Lucille rounds and `usage-log` (historical ledger stays readable). Missing key → treat as ON.

### Paths

| Artifact | Default path | Purpose |
| -------- | ------------ | ------- |
| Ledger (append-only) | `{paths.usage}/ledger.jsonl` | One JSON object per line — date/time, user, category, tokens, minutes, skill, optional spec |
| Schedule | `{paths.schedule}` (`.larapilot/usage/schedule.yaml`) | Deadlines, milestones, delay notes |
| Choices snapshot | `{paths.choices}` (`.larapilot/choices.yaml`) | Structured inception/settings decisions for the dashboard |

All three are **git-committed** project truth (no secrets — never log API keys or prompt bodies).

### Categories

Use exactly these labels for `--category=`:

`analysis` · `planning` · `implementation` · `support` · `feature` · `review` · `ship` · `other`

Map skills roughly: inception/discovery → `analysis`; plan → `planning`; implement → `implementation`; feature enhancement → `feature`; bug/Sophia → `support`; review → `review`; ship → `ship`.

### When to log

1. **End of every meaningful skill session** (or major phase inside a long session) — Lucille (via the agent) runs:

   ```bash
   php artisan larapilot:usage-log --category=analysis --tokens=12000 --minutes=45 --skill=larapilot-inception --note="PRD draft"
   ```

2. Prefer estimates from the session when exact token counts are unavailable — note `estimated: true` via `--estimated` rather than inventing precision.
3. `--user=` defaults to `git config user.name` / `user.email` when available (`git:Name <email>`).
4. Optional `--spec=US-XXX` ties time to a story for Gantt realism.

### Deadlines & drift

1. At **inception**, Lucille asks for delivery dates / milestones (skippable). Persist:

   ```bash
   php artisan larapilot:schedule-set --deadline=2026-09-01 --label="Go-live" --note="Client demo"
   ```

2. During later skills, if the team is behind or blocked, Lucille updates schedule notes (`--status=at_risk|delayed|on_track`) and mentions drift briefly in chat — never blocks Mark/John decisions.
3. Dashboard **Usage** renders the token and hour ledger; **Plan** (nav item before Design) renders the **dependency-aware Gantt** (epics → tasks with `dependencies` / `assignee` / `estimate_hours`) and schedule milestones; `larapilot:usage-report` exports a consolidated Markdown report.
4. **Interrogation skill** — `/larapilot-usage` (Lucille) answers questions about tempistiche and token burn. Prefer `php artisan larapilot:usage-report --format=json --insights` with filters (`--category=`, `--user=`, `--skill=`, `--spec=`, `--from=`, `--to=`) over hand-reading `ledger.jsonl`.
5. **Effort forecast** — Lucille compares remaining story points / task `estimate_hours` against project milestones and epic `deadline` fields, surfacing temporal criticality on the dashboard (`criticality` in `--insights`).
6. **Zoey vs Lucille** — Zoey’s `context ≈ Nk` is loaded-context size; Lucille’s ledger is session spend (often `--estimated` from Zoey’s end line). The dashboard explains why the two figures diverge; do not force them equal.

### Epics & parallel work

- Specs belong to epics with `objective` + optional `deadline`. Lucille treats epics as delivery containers on the Gantt.
- Plan tasks must declare `dependencies`. Independent tasks after the same gate are parallelizable and may use different `assignee` values so the timeline can spread across developers.
- Prefer hours on the Usage UI (minutes stay in the ledger for precision); tokens ≥ 1000 display as `K`.

### Choices snapshot

After inception (and when settings change), persist a structured snapshot for the dashboard **Settings** page:

```bash
php artisan larapilot:choices-set --file=...   # or key flags
```

Agents may also let `choices-set` scrape the PRD via `--from-prd`. Fields include Project Kind, Website Type, Package Origin, Delivery Target, Budget Sensitivity, Frontend Topology, data-store choices (Mike), CLI tools (Sarah), and current `settings.*`.

Ownership: **Lucille** ledger + schedule + reporting; **Zoey** may suggest logging when a long session ends without an entry; **Mark** owns deadline negotiation with the user.
