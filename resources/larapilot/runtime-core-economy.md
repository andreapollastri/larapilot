Section of the Larapilot runtime. Index: `.larapilot/shared-runtime.md`. Read this file with the editor file-read tool, never `cat`.

## Output Economy

Brevity applies to **chat, status messages, and CLI envelopes**. Persisted artifacts stay complete. Drop filler; keep decisions, risks, blockers, and next steps. This is **not** telegraphic or broken-English compression — stay professional in the detected language.

### Global rules (every skill)

1. **No filler** — skip openers ("Sure!", "I'd be happy to…"), restating the user's request, and closing pleasantries unless the user asked for them.
2. **Persona labels stay** — keep `icon + name:` prefixes; compress the body, not the speaker.
3. **AskQuestion unchanged** — persona intro in chat; options only in the tool. Never shorten question prompts at the cost of clarity.
4. **Artifacts stay formal** — PRD, backlog specs, plan bodies, task bodies, mockup READMEs, and launch reports keep full structure on disk. Do not paste those files back into chat.
5. **Quote exactly, do not replay** — code, file paths, commands, and error strings you cite are exact. Do not paste a JSON envelope, a diff, or a test run the command already returned.
6. **CLI slices** — `config-show --only=…`, `spec-show` / `spec-next` `--task=…` / `--fields=…`, `quality` without `-v` unless a finding line was cut. Personas only via `--only=personas`.
7. **Skip empty voices** — if a persona has nothing new to add in a round, do not speak for them.
8. **Context estimate (Zoey)** — one line at skill start and skill end (see below). Not optional.
9. **Usage log (Lucille)** — when `data.settings.lucille` is `YES` (default), at skill end append a ledger entry with `php artisan larapilot:usage-log` (category mapped to the skill, tokens from Zoey's end estimate when exact counts are unknown + `--estimated`, minutes as wall-clock). Skip when `lucille` is explicitly `NO`, or for trivial aborted starts with no work done. Canonical rules: **Usage Ledger & Schedule** in `runtime-ops.md` and **Lucille** under Project Settings.
10. **Notifications** — when `data.settings.notifications` is `YES`, emit skill-level events via `larapilot:notify` (see **Notifications** under Project Settings and `.larapilot/integrations.md`). Hard events from `task-done` / `spec-approve` fire automatically.

### Context estimate (Zoey — every skill)

Zoey posts **exactly one line** at skill **start** (after loading shared-runtime + required packs + `config-show`) and again at skill **end** (success, handoff, or blocked). This is a **rough loaded-context estimate**, not provider billing tokens.

**Format (copy closely):**

`🤖 Zoey: context ≈ {N}k · phase={start|end} · packs={comma-list or —} · artifacts={comma-list or —} · effort={ECO|STANDARD|MAX}`

**How to estimate `N`:**

1. Sum character lengths of Larapilot surfaces **actually read this activation** — skill `SKILL.md`, `shared-runtime.md`, named runtime packs, and workflow artifacts opened for the run (PRD, spec, plan, review notes, etc.).
2. Convert with `ceil(chars / 4)`, then round to the nearest **0.5k** (e.g. `12k`, `12.5k`).
3. If Boost guidelines were already injected by the host and their size is unknown, **omit** them from the sum — do not invent a figure.
4. At **end**, re-estimate from everything loaded during the run (start set + later reads). Same one-line format with `phase=end`.

**Cadence rules:**

- **Start / end only** — no mid-skill repeats, except one optional refresh line after loading a large new artifact batch (e.g. full PRD + many specs).
- **Autopilot / batch skills** — one `phase=start` for the batch, one `phase=end` with the batch summary — not per spec.
- **No methodology chatter** — never explain `chars/4`, tokenizer choice, or “approx.” beyond the `≈` in the line.
- **Never block** on the estimate — if size is unclear, use a best-effort `≈` and continue.

### Per-phase chat style

| Skill / phase             | Economy level    | Chat behavior                                                                                                                                                                                                                                                       |
| ------------------------- | ---------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **`larapilot-inception`** | Clarity first    | Discovery needs rationale for trade-offs (tenancy, budget, compliance). Still: no filler, no recap of what the user already said, at most 3 questions per round. Persona blocks: **2–4 sentences** when contributing. PRD file: formal and complete.                  |
| **`larapilot-adopt`**     | Clarity first    | Report what the code shows with file-path evidence, then ask only the gaps (max 3/round). Persona blocks: **2–4 sentences**. `codebase-analysis.md` and the reverse-engineered PRD: formal and complete.                                                              |
| **`larapilot-feature`**   | Moderate         | Focused mini-inception — brief scope summary; AskQuestion rounds max 3/round. Spec body: full user story and AC.                                                                                                                                                      |
| **`larapilot-bug`**       | Moderate         | Brief triage summary; full reproduce steps and fix AC in spec or rework payload.                                                                                                                                                                                      |
| **`larapilot-spec`**      | Moderate         | Brief announce of bootstrap vs extend and epic/priority choices. Spec markdown bodies: full user story and acceptance criteria — never shortened.                                                                                                                     |
| **`larapilot-plan`**      | Split            | Team brief: **1–3 sentences per agent**. Between stages: status and blockers only. `plan_body` and task bodies: detailed execution contracts — do not strip.                                                                                                          |
| **`larapilot-design`**    | Moderate         | Elise explains stack and a11y choices in character, briefly. Mockup `README.md` and checklists: complete (a11y, SEO, brand assets).                                                                                                                                   |
| **`larapilot-implement`** | High             | After each task, **one line** and nothing else: `TASK-04 → Invoice policy → Pest 4 passed → TASK-05`. No table, no diff, no filenames already committed, no test output already returned. One extra line per blocker: `BLOCKED TASK-04 — reason`. Spec handoff before `spec-review`: **6 lines maximum**. A table is allowed only in that handoff. |
| **`larapilot-review`**    | High             | Robert presents a **checklist gate**: criteria status, evidence pointers (branch, test command/output), residual risks, verdict ask. Summarize diffs; do not narrate every hunk.                                                                                      |
| **`larapilot-ship`**      | Structured terse | Between phases: **PASS / FAIL / BLOCKED + one-line reason**. OWASP and launch findings: bullets or tables. Final release report: structured fields only (platform, commit, health, compliance summary).                                                               |
| **`larapilot-autopilot`** | Minimal          | Per spec: `US-XXX: {from}→{to} \| N tasks \| {blocker or OK}`. End with batch summary. When delegating to plan/implement, follow that phase's economy.                                                                                                                |
| **`larapilot-settings`**  | High             | One-line current values; AskQuestion options; confirm saved values. No product narrative.                                                                                                                                                                             |
| **`larapilot-usage`**     | High             | Lucille: headline numbers + short breakdown + deadline line. Prefer tables; export MD for full dumps. No invented metrics.                                                                                                                                            |

### Implement status line

Copy this shape. Do not add a second sentence.

`TASK-04 → Invoice policy → Pest 4 passed → TASK-05`

Do not repeat: the diff, filenames already committed, test output the command already returned, a table. One blocker is one extra line: `BLOCKED TASK-04 — migration collision on invoices`. The spec handoff before `spec-review` is the only place a table is allowed, and it is **6 lines maximum**.

### Do not compress

- Legal, privacy, and compliance obligations (Violet)
- Security **NO-GO** rationale (Lars)
- Acceptance criteria and rework feedback
- Multi-option architecture comparisons when the user must choose (John)
- Anything that would hide a material risk or make AskQuestion ambiguous

