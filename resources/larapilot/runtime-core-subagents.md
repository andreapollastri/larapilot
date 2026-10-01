## Sub-agents

<!-- when: effort=ECO -->
`effort` is `ECO`: **no sub-agent is spawned** — no explore, no Robert or Lars, no spec worker, no parallel Task/Agent call. Every pass runs inline in this session, with the handoff prompt below as a checklist kept to Critical and High findings.
<!-- end -->
<!-- when: effort!=ECO -->
Skills spawn sub-agents for a fresh context via the editor's sub-agent tool (Cursor Task tool, Claude Code Agent tool, or equivalent) — not separate Larapilot personas.

Two classes:

- **Readonly** (explore, Robert, Lars) — never call `php artisan larapilot:*`, never edit files, never replace the human gate.
- **Spec worker** (`larapilot-autopilot` only) — one writing sub-agent at a time. It may edit files and commit. It never calls a Larapilot transition, never spawns nested sub-agents, and never AskQuestion. Contract: **Spec worker** (`runtime-spec-worker.md`).

**Capability check:** sub-agents are an optimization, not a requirement. If the editor has no sub-agent tool, skip the spawn and run the same pass **inline in the parent session** using the handoff prompt as a checklist — every flow produces the same artifacts either way. Autopilot without a writing sub-agent runs plan and implement in the parent.

<!-- when: effort=STANDARD -->
**Effort `STANDARD`:** follow each skill's default — optional explore; implement runs Robert + Lars; autopilot uses a spec worker.
<!-- end -->
<!-- when: effort=MAX -->
**Effort `MAX`:** always run the sub-agents listed below when the editor supports them.
<!-- end -->

### Global rules

1. **Parent owns the workflow** — only the parent agent runs CLI transitions (`spec-start`, `task-done`, `spec-plan`, `spec-review`, `spec-approve`, `decision-log`, `decision-check`, `usage-log`, `code-log`, `notify`, …). A spec worker reports task ids; the parent applies them.
2. **Readonly passes never edit** — code review and security passes never edit files: enable the editor's readonly flag when available; the handoff prompt forbids edits regardless. The parent applies fixes and re-runs tests. The spec worker is the exception that may edit, and only inside its own contract.
3. **Compact handoff** — pass spec code, absolute `data.workdir`, branch name, acceptance criteria, and plan path — not the runtime files. A worker runs `larapilot:context` itself; that context dies with it.
4. **Parallel when independent** — Robert and Lars reviews launch together (one message, two sub-agent calls, synchronous) when the editor supports it; otherwise run them sequentially. Explore during a standalone plan is a single sub-agent. A spec worker never nests either of those; the autopilot parent launches Robert + Lars after the implement worker returns.
5. **Never parallelize specs** — autopilot and batch flows stay one spec at a time. Spec workers are sequential. Same working tree: a second spec does not start until the parent has finished transitions and review for the current one.

### Where sub-agents are used

| Skill | Sub-agent | When | Role |
| --- | --- | --- | --- |
| **`larapilot-adopt`** | Codebase explore _(optional)_ | Step 1, large or unfamiliar repo | readonly structure/inventory mapping |
| **`larapilot-plan`** | Codebase explore _(optional)_ | Stage 1, large or unfamiliar `data.workdir` | readonly codebase mapping |
| **`larapilot-implement`** | Robert + Lars | Phase 2, after all tasks `task-done` | readonly code review + security review, parallel |
| **`larapilot-review`** | — | Reads parent-written `{paths.review}/{code}.md` if present | no spawn |
| **`larapilot-autopilot`** | Spec worker (plan) | `TODO` spec, editor can spawn a writer | writes the plan file; no CLI transition |
| **`larapilot-autopilot`** | Spec worker (implement) | after parent `spec-start` | Phase 0–1 only: code, tests, commits |
| **`larapilot-autopilot`** | Robert + Lars | after the implement worker returns `OK` | same readonly Phase 2 as implement, parent launches |

No other skill spawns one.

**Type mapping:** pick the closest sub-agent type the editor offers — e.g. Cursor: `explore`, `bugbot`, `security-review`; Claude Code: `Explore` for mapping, `general-purpose` with the review prompt for Robert/Lars. The spec worker is a generic writing sub-agent (Cursor `generalPurpose`, Claude Code `general-purpose`), never a readonly type. No matching type: use the generic/default sub-agent with the handoff prompt as-is. No sub-agent tool at all: inline fallback (see Capability check).
<!-- end -->

### Review handoff

Canonical prompt for implement Phase 2 and for the autopilot parent. Fill the braces from the `context` envelope and `spec-show --fields=id,title`. Pass acceptance criteria as a short list, not the whole spec body.

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

After merging the findings in **`larapilot-implement`** (or the autopilot parent, after its Phase 2), the parent writes `{paths.review}/{code}.md` (create parent dirs) before `spec-review`, with sections: `## Robert (code review)` and `## Lars (security)` — one `- [severity] finding` bullet per item — plus `## Parent actions` (`Fixed: …` / `Open (Medium/Low): …`). **`larapilot-review`** reads this file when presenting the increment to the human.
