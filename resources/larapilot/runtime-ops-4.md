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

   A milestone before the end of the project names its release — `--release=0.1.0` — and is measured against the last spec of that release. One that names none is measured against the last spec of the backlog.

2. During later skills, if the team is behind or blocked, Lucille updates schedule notes (`--status=at_risk|delayed|on_track`) and mentions drift briefly in chat — never blocks Mark/John decisions.
3. Dashboard **Usage** renders the token and hour ledger; **Plan** (nav item before Design) renders the **dependency-aware Gantt** (epics → tasks with `dependencies` / `assignee` / `estimate_hours`) and schedule milestones; `larapilot:usage-report` exports a consolidated Markdown report.
4. **Interrogation skill** — `/larapilot-usage` (Lucille) answers questions about tempistiche and token burn. Prefer `php artisan larapilot:usage-report --format=json --insights` with filters (`--category=`, `--user=`, `--skill=`, `--spec=`, `--from=`, `--to=`) over hand-reading `ledger.jsonl`.
5. **Effort forecast** — the Gantt is a forecast in sequence: open work is queued from today, **one spec at a time** — work in progress first, then priority and code as `spec-next` picks, never before the specs on its `**Blocked by:**` line, and a spec that blocks a more urgent one as urgent as it. A working day is 6 h, Monday to Friday; a spec with no plan counts 4 h per story point. Done work sits on the days the ledger recorded for the spec. Lucille measures milestones and epic `deadline` fields against `gantt.forecast_end` — the day the last open spec is done — and surfaces temporal criticality on the dashboard (`criticality` in `--insights`). Re-planning — order, blockers, estimates, epic deadlines, milestones — is **`/larapilot-schedule`**: `larapilot:schedule-show` for the queue and what the forecast could not read, `larapilot:schedule-apply --file= [--dry-run]` to change it in one batch, `--repair` for what takes no decision (run by `larapilot:update`).
6. **Zoey vs Lucille** — Zoey’s `context ≈ Nk` is loaded-context size; Lucille’s ledger is session spend (often `--estimated` from Zoey’s end line). The dashboard explains why the two figures diverge; do not force them equal.

### Epics & parallel work

- Specs belong to epics with `objective` + optional `deadline`. Lucille treats epics as delivery containers on the Gantt.
- Plan tasks must declare `dependencies`. Independent tasks after the same gate are parallelizable: the forecast runs them at the same time only when their `assignee` values differ — tasks of one person, or with no assignee, follow one another.
- Prefer hours on the Usage UI (minutes stay in the ledger for precision); tokens ≥ 1000 display as `K`.

### Choices snapshot

After inception (and when settings change), persist a structured snapshot for the dashboard **Settings** page:

```bash
php artisan larapilot:choices-set --file=...   # or key flags
```

Agents may also let `choices-set` scrape the PRD via `--from-prd`. Fields include Project Kind, Website Type, Package Origin, Delivery Target, Budget Sensitivity, Frontend Topology, data-store choices (Mike), CLI tools (Sarah), and current `settings.*`.

Ownership: **Lucille** ledger + schedule + reporting; **Zoey** may suggest logging when a long session ends without an entry; **Mark** owns deadline negotiation with the user.
