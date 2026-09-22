Section of the Larapilot runtime. Index: `.larapilot/shared-runtime.md`. Read this file with the editor file-read tool, never `cat`.

## Sub-agents

Some skills spawn **readonly sub-agents** for fresh context via the editor's sub-agent tool (Cursor Task tool, Claude Code Agent tool, or equivalent) — not separate Larapilot personas. Sub-agents **never** call `php artisan larapilot:*`, edit files, or replace the human gate.

**Capability check:** sub-agents are an optimization, not a requirement. If the editor has no sub-agent tool, skip the spawn and run the same pass **inline in the parent session** using the handoff prompt as a checklist — every flow produces the same artifacts either way.

**Effort gate:** when `settings.effort` is **`ECO`**, **do not spawn any sub-agents** — no explore, no Robert/Lars, no parallel Task/Agent calls. Run every pass inline in the parent with a minimal checklist. When **`MAX`**, always run the sub-agents listed below when the editor supports them. When **`STANDARD`**, follow each skill's default (optional explore; implement still runs Robert + Lars).

### Global rules

1. **Parent owns the workflow** — only the parent agent runs CLI transitions (`spec-start`, `task-done`, `spec-plan`, `spec-review`, `spec-approve`, …).
2. **Read-only always** — code review and security passes never edit files: enable the editor's readonly flag when available; the handoff prompt forbids edits regardless. The parent applies fixes and re-runs tests.
3. **Compact handoff** — pass spec code, absolute `data.workdir`, branch name, acceptance criteria, and plan path — not the full runtime files.
4. **Parallel when independent** — Robert and Lars reviews launch together (one message, two sub-agent calls, synchronous) when the editor supports it; otherwise run them sequentially. Explore during plan is a single sub-agent.
5. **Never parallelize specs** — autopilot and batch flows stay one spec at a time; no sub-agent per spec in parallel.

### Where sub-agents are used

| Skill                     | Sub-agent                     | When                                                        | Role                                              |
| ------------------------- | ----------------------------- | ----------------------------------------------------------- | ---------------------------------------------------|
| **`larapilot-adopt`**     | Codebase explore _(optional)_ | Step 1, large or unfamiliar repo (never under `effort: ECO`) | readonly structure/inventory mapping                |
| **`larapilot-plan`**      | Codebase explore _(optional)_ | Stage 1, large or unfamiliar `data.workdir`                  | readonly codebase mapping                           |
| **`larapilot-implement`** | Robert + Lars                 | Phase 2, after all tasks `task-done`                         | readonly code review + security review, parallel    |
| **`larapilot-review`**    | —                             | Reads parent-written `{paths.review}/{code}.md` if present   | no spawn                                            |

**Type mapping:** pick the closest sub-agent type the editor offers — e.g. Cursor: `explore`, `bugbot`, `security-review`; Claude Code: `Explore` for mapping, `general-purpose` with the review prompt for Robert/Lars. No matching type: use the generic/default sub-agent with the handoff prompt as-is. No sub-agent tool at all: inline fallback (see Capability check).

Skills **without** sub-agents: `inception`, `feature`, `bug`, `spec`, `design`, `frontend-companion`, `ship`, `settings`, `autopilot` (the parent follows child skill rules when batching, but does not fork implement/plan sub-agents itself). `adopt` may spawn **one** optional readonly `Explore` sub-agent for repo mapping (never under `effort: ECO`).

### Review artifact

After merging sub-agent findings in **`larapilot-implement`**, the parent writes `{paths.review}/{code}.md` (path from `config-show`, default `.larapilot/docs/review/`; create parent dirs) before `spec-review`, with sections: `## Robert (code review)` and `## Lars (security)` — one `- [severity] finding` bullet per item — plus `## Parent actions` (`Fixed: …` / `Open (Medium/Low): …`). **`larapilot-review`** reads this file when presenting the increment to the human.

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
