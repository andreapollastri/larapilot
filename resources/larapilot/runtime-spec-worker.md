## Spec worker (autopilot)

Only `larapilot-autopilot`, only when `effort` is `STANDARD` or `MAX` and the editor can spawn a sub-agent that may edit files. One worker, then the parent, then the next worker. Never two specs, and never a worker that itself spawns.

**What the parent reads**

The delegating parent runs `larapilot:context autopilot` and reads what it lists: the core, `core-subagents.md`, and this file. It does not read `runtime-delivery` (or its parts), `runtime-dev-docs`, `task-templates`, the PRD, or `larapilot-implement`. Delivery target is `data.project.delivery_target`. Phase 2 uses **Review handoff** (`runtime-core-subagents.md`). An inline autopilot (no writing sub-agent) runs `larapilot:context plan` and `larapilot:context implement` with the same `--session`, because that parent is the one planning and implementing.

**Parent loop**

1. Resolve the next spec from `spec-list` / `spec-next`. Settings come from the `context` envelope (`git_mode`, `testing`, `effort`, `auto_approve`, `code_history`, `decision_log`).
2. `TODO` → spawn the **plan** worker. On `OK`, the parent runs `validate-plan` and `spec-plan`, then continues with step 3 for the same spec before starting another. On validation failure, resume (or re-spawn) with the error text only.
3. `PLANNED` → parent calls `spec-start`, then spawns the **implement** worker for Phases 0–1 only. First-change domain-doc catch-up stays inside that worker.
4. `BLOCKED` → parent calls `task-done` for every id on the `done:` line, then AskQuestion (and `decision-check` / `decision-log` when the journal is on). Resume the same worker when the editor supports resume; otherwise spawn a new one whose handoff includes `decision: {one line}`.
5. Implement worker `OK` → parent calls `task-done` for each reported id, then `code-log` when `code_history` is `YES`, then `notify` when the line carries a PR URL and notifications are on. Parent launches Robert + Lars with **Review handoff** (do not open `larapilot-implement`), applies Critical/High fixes, writes the review artifact, then `spec-review`. Auto-approve stays on the parent. Chat gets one line per fix, not the diff.
6. The parent prints one line per spec, `US-XXX: {from}→{to} | N tasks | OK or blocker`, plus the batch summary. `{to}` is the status after the parent's own CLI (`PLANNED`, `REVIEW`, or `DONE`). Do not paste the worker's reads, diffs, or test output.

**Worker**

- Follow `larapilot-plan` or `larapilot-implement` Phases 0–1. Run `larapilot:context plan` or `larapilot:context implement` and read what it lists. Do not spawn. Do not AskQuestion. Do not run implement Phase 2 or Phase 3. During plan, explore inline. When a non-negotiable would require AskQuestion (Filament, Sail, Cipi, Cloudflare, AWS, or a decision-log supersede), return `BLOCKED` instead of assuming.
- May edit `data.workdir` and, when a task says `repo: frontend`, `data.frontend.repo_path`. May commit per `git_mode`. When `git_mode` is `GITFLOW_PUSH`, may push and open or update the PR.
- May call `context`, `spec-show`, `spec-next`, `prd-show`, and `quality` (including `--fix` for formatting). No other `php artisan larapilot:*`.
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
Overrides: no nested sub-agents; no AskQuestion; no Larapilot CLI except context, spec-show, spec-next, prd-show, and quality.
Plan: write `.larapilot/tmp-payload-{code}-plan.json` and stop before validate-plan / spec-plan.
Implement: Phases 0–1 only. spec-start already ran. Do not task-done, spec-review, code-log, usage-log, or decision-log.
Return only the status line in Spec worker (spec-worker.md).
workdir: {absolute}
project_root: {absolute}
git_mode: {value}
testing: {value}
effort: {STANDARD|MAX}
plan: {paths.planning}/{code}-plan.yaml
```

Append `decision: {one line}` when resuming after a human answer. Append `validate-plan failed: {error}` when re-spawning a plan worker.
