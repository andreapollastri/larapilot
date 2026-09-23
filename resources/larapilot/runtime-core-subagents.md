Section of the Larapilot runtime. Index: `.larapilot/shared-runtime.md`. Read this file with the editor file-read tool, never `cat`.

## Sub-agents

Skills spawn sub-agents for a fresh context via the editor's sub-agent tool (Cursor Task tool, Claude Code Agent tool, or equivalent) — not separate Larapilot personas.

Two classes:

- **Readonly** (explore, Robert, Lars) — never call `php artisan larapilot:*`, never edit files, never replace the human gate.
- **Spec worker** (`larapilot-autopilot` only) — one writing sub-agent at a time. It may edit files and commit. It never calls a Larapilot transition, never spawns nested sub-agents, and never AskQuestion. Contract: **Spec worker** below.

**Capability check:** sub-agents are an optimization, not a requirement. If the editor has no sub-agent tool, skip the spawn and run the same pass **inline in the parent session** using the handoff prompt as a checklist — every flow produces the same artifacts either way. Autopilot without a writing sub-agent runs plan and implement in the parent.

**Effort gate:** when `settings.effort` is **`ECO`**, **do not spawn any sub-agents** — no explore, no Robert/Lars, no spec worker, no parallel Task/Agent calls. Run every pass inline in the parent with a minimal checklist. When **`MAX`**, always run the sub-agents listed below when the editor supports them. When **`STANDARD`**, follow each skill's default (optional explore; implement still runs Robert + Lars; autopilot uses a spec worker).

### Global rules

1. **Parent owns the workflow** — only the parent agent runs CLI transitions (`spec-start`, `task-done`, `spec-plan`, `spec-review`, `spec-approve`, `decision-log`, `decision-check`, `usage-log`, `code-log`, `notify`, …). A spec worker reports task ids; the parent applies them.
2. **Readonly passes never edit** — code review and security passes never edit files: enable the editor's readonly flag when available; the handoff prompt forbids edits regardless. The parent applies fixes and re-runs tests. The spec worker is the exception that may edit, and only inside its own contract.
3. **Compact handoff** — pass spec code, absolute `data.workdir`, branch name, acceptance criteria, and plan path — not the full runtime files. The worker reads the skill and runtime itself; that context dies with it.
4. **Parallel when independent** — Robert and Lars reviews launch together (one message, two sub-agent calls, synchronous) when the editor supports it; otherwise run them sequentially. Explore during a standalone plan is a single sub-agent. A spec worker never nests either of those; the autopilot parent launches Robert + Lars after the implement worker returns.
5. **Never parallelize specs** — autopilot and batch flows stay one spec at a time. Spec workers are sequential. Same working tree: a second spec does not start until the parent has finished transitions and review for the current one.

### Where sub-agents are used

| Skill                     | Sub-agent                     | When                                                        | Role                                              |
| ------------------------- | ----------------------------- | ----------------------------------------------------------- | ---------------------------------------------------|
| **`larapilot-adopt`**     | Codebase explore _(optional)_ | Step 1, large or unfamiliar repo (never under `effort: ECO`) | readonly structure/inventory mapping                |
| **`larapilot-plan`**      | Codebase explore _(optional)_ | Stage 1, large or unfamiliar `data.workdir`                  | readonly codebase mapping                           |
| **`larapilot-implement`** | Robert + Lars                 | Phase 2, after all tasks `task-done`                         | readonly code review + security review, parallel    |
| **`larapilot-review`**    | —                             | Reads parent-written `{paths.review}/{code}.md` if present   | no spawn                                            |
| **`larapilot-autopilot`** | Spec worker (plan)            | `TODO` spec, effort not `ECO`, editor can spawn a writer     | writes the plan file; no CLI transition             |
| **`larapilot-autopilot`** | Spec worker (implement)       | after parent `spec-start`, effort not `ECO`                  | Phase 0–1 only: code, tests, commits                |
| **`larapilot-autopilot`** | Robert + Lars                 | after the implement worker returns `OK`                      | same readonly Phase 2 as implement, parent launches |

**Type mapping:** pick the closest sub-agent type the editor offers — e.g. Cursor: `explore`, `bugbot`, `security-review`; Claude Code: `Explore` for mapping, `general-purpose` with the review prompt for Robert/Lars. The spec worker is a generic writing sub-agent (Cursor `generalPurpose`, Claude Code `general-purpose`), never a readonly type. No matching type: use the generic/default sub-agent with the handoff prompt as-is. No sub-agent tool at all: inline fallback (see Capability check).

Skills **without** sub-agents: `inception`, `feature`, `bug`, `spec`, `design`, `frontend-companion`, `ship`, `settings`. `adopt` may spawn **one** optional readonly `Explore` sub-agent for repo mapping (never under `effort: ECO`).

### Spec worker (autopilot)

Only `larapilot-autopilot`, only when `effort` is `STANDARD` or `MAX` and the editor can spawn a sub-agent that may edit files. One worker, then the parent, then the next worker. Never two specs, and never a worker that itself spawns.

**What the parent reads**

The delegating parent reads the every-skill rows, this file, and `config-show --only=settings,paths,dev_docs`. It does not read `runtime-delivery` (or its parts), `runtime-dev-docs`, `runtime-ops`, `task-templates`, the PRD, or `larapilot-implement`. Delivery target comes from `{paths.choices}` key `delivery_target`. Phase 2 uses **Review handoff** below. An inline autopilot (ECO, or no writing sub-agent) reads delivery, dev-docs, and task-templates, because that parent is the one planning and implementing.

**Parent loop**

1. Resolve the next spec from `spec-list` / `spec-next`. Settings come from the `config-show` slice above (`git_mode`, `testing`, `effort`, `auto_approve`, `code_history`, `decision_log`).
2. `TODO` → spawn the **plan** worker. On `OK`, the parent runs `validate-plan` and `spec-plan`, then continues with step 3 for the same spec before starting another. On validation failure, resume (or re-spawn) with the error text only.
3. `PLANNED` → parent calls `spec-start`, then spawns the **implement** worker for Phases 0–1 only. First-change domain-doc catch-up stays inside that worker.
4. `BLOCKED` → parent calls `task-done` for every id on the `done:` line, then AskQuestion (and `decision-check` / `decision-log` when the journal is on). Resume the same worker when the editor supports resume; otherwise spawn a new one whose handoff includes `decision: {one line}`.
5. Implement worker `OK` → parent calls `task-done` for each reported id, then `code-log` when `code_history` is `YES`, then `notify` when the line carries a PR URL and notifications are on. Parent launches Robert + Lars with **Review handoff** (do not open `larapilot-implement`), applies Critical/High fixes, writes the review artifact, then `spec-review`. Auto-approve stays on the parent. Chat gets one line per fix, not the diff.
6. The parent prints one line per spec, `US-XXX: {from}→{to} | N tasks | OK or blocker`, plus the batch summary. `{to}` is the status after the parent's own CLI (`PLANNED`, `REVIEW`, or `DONE`). Do not paste the worker's reads, diffs, or test output.

**Worker**

- Follow `larapilot-plan` or `larapilot-implement` Phases 0–1. Read the runtime those skills name. Do not spawn. Do not AskQuestion. Do not run implement Phase 2 or Phase 3. During plan, explore inline. When a non-negotiable would require AskQuestion (Filament, Sail, Cipi, Cloudflare, AWS, or a decision-log supersede), return `BLOCKED` instead of assuming.
- May edit `data.workdir` and, when a task says `repo: frontend`, `data.frontend.repo_path`. May commit per `git_mode`. When `git_mode` is `GITFLOW_PUSH`, may push and open or update the PR.
- May call `config-show`, `spec-show`, `spec-next`, and `quality` (including `--fix` for formatting). No other `php artisan larapilot:*`.
- Final message is one line, or two when blocked. Nothing else.

`OK plan US-014 | 6 tasks`

`OK implement US-014 | 4 tasks | pr: {url or —}`

```text
BLOCKED US-014 TASK-03 — {question}
done: TASK-01, TASK-02
```

Use `done: —` when no task finished. The parent copies these lines into chat and discards the rest.

**Handoff** (parent fills values; does not attach runtime files):

```text
Larapilot spec worker — {plan|implement} {code}
You are the autopilot spec worker. Follow larapilot-{plan|implement}.
Overrides: no nested sub-agents; no AskQuestion; no Larapilot CLI except config-show, spec-show, spec-next, and quality.
Plan: write `.larapilot/tmp-payload-{code}-plan.json` and stop before validate-plan / spec-plan.
Implement: Phases 0–1 only. spec-start already ran. Do not task-done, spec-review, code-log, usage-log, or decision-log.
Return only the status line in Spec worker (runtime-core-subagents.md).
workdir: {absolute}
project_root: {absolute}
git_mode: {value}
testing: {value}
effort: {STANDARD|MAX}
plan: {paths.planning}/{code}-plan.yaml
```

Append `decision: {one line}` when resuming after a human answer. Append `validate-plan failed: {error}` when re-spawning a plan worker.

### Review handoff

Canonical prompt for implement Phase 2 and for the autopilot parent. Fill the braces from `config-show` and `spec-show --fields=id,title`. Pass acceptance criteria as a short list, not the whole spec body.

```text
Larapilot implement review — {code}

workdir: {data.workdir absolute}
project_root: {data.project_root absolute}
branch: feature/{code}-* (or current branch in workdir)
plan: {paths.planning}/{code}-plan.yaml (under project_root)
acceptance: {criteria, one line each}

Robert (code review): plan adherence, Laravel conventions, Gitflow branch hygiene (no direct main/develop commits), per-task commit discipline, factory/seeder completeness, developer domain doc freshness — code changed in the diff while `{paths.dev_docs}/{domain}.md` did not is a High finding. Return at most 8 bullets: severity (Critical|High|Medium|Low) — file:line — finding. No edits. Do not return the diff, file bodies, or test output.

Lars (security review): OWASP Top 10 on the branch diff; auth and access control; composer audit implications. Same bullet cap and format. No edits. Do not return the diff.
```

The parent writes the full list to the review file. Chat is one line per Critical or High that was fixed.

### Review artifact

After merging sub-agent findings in **`larapilot-implement`** (or the autopilot parent, after its Phase 2), the parent writes `{paths.review}/{code}.md` (path from `config-show`, default `.larapilot/docs/review/`; create parent dirs) before `spec-review`, with sections: `## Robert (code review)` and `## Lars (security)` — one `- [severity] finding` bullet per item — plus `## Parent actions` (`Fixed: …` / `Open (Medium/Low): …`). **`larapilot-review`** reads this file when presenting the increment to the human.

## File Output Rules

- Use the configured output path from `config-show` whenever present; create parent directories if they do not exist.
- Overwrite the target generated artifact for the current run unless the active flow explicitly says otherwise.
- Standard artifact homes (defaults): PRD `.larapilot/docs/PRD.md` · backlog `.larapilot/backlog.yaml` · specs `.larapilot/specs/` · plans `.larapilot/plans/` · mockups `.larapilot/mockups/{spec}/` · developer domain docs `.larapilot/docs/devs/` (English only — see `runtime-dev-docs.md`) · review findings `.larapilot/docs/review/` · security `.larapilot/docs/security/` · support `.larapilot/docs/support/` · launch `.larapilot/docs/launch/` · test results `.larapilot/docs/test-results/` · client materials `.larapilot/client-materials/` · legacy `.larapilot/legacy/` · research `.larapilot/research/` · design systems `.larapilot/design-systems/` · decision journal `.larapilot/decisions.yaml` (via `larapilot:decision-log`, never hand-edited) · code change history `.larapilot/code-history.yaml` (via `larapilot:code-log`, never hand-edited).

## Non-negotiables

1. **`effort: ECO` never spawns sub-agents** — every pass runs inline in the parent session.
2. **Human DONE gate** — only a human Approve moves a spec to `DONE`, except when `settings.auto_approve` is `true` (see Auto-approve above and `larapilot-autopilot`).
3. **Never assume Filament, Laravel Sail, Cipi, Cloudflare, or AWS** — always ask via AskQuestion and honor the choice recorded in the PRD.
4. **PRD before backlog** — write and validate the PRD before creating any backlog spec.
5. **Skills call Artisan** — `php artisan larapilot:*` is the only persistence backend; never invent persistence logic or edit workflow YAML by hand.
6. **One source of truth** — settings matrices, the persona roster, and pack sections are canonical where they live; reference them by file + heading, never re-paste.
7. **Developer domain docs are never optional** — every spec that changes a domain's behavior updates its file under `.larapilot/docs/devs/` in the same spec, in English, at every effort level including `ECO`. When `config-show` reports `data.dev_docs.documented` as `false` on a codebase that already has domains, the **first** change documents the whole project before its own work — never gradually (`runtime-dev-docs.md`).

## Conversation Rules

- Each agent speaks in character
- Follow **Output Economy** for the active skill — brevity in chat, completeness in artifacts (including Zoey's start/end **Context estimate** lines)
- Never mention internal mode names, workflow names, or routing decisions in the conversation
