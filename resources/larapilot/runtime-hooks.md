# Workflow Hooks

`settings.hooks` — opt-in, default OFF. The project attaches its own commands and skills to the moments of the loop in **`.larapilot/hooks.yaml`** (`paths.hooks`, committed: the team shares them). `LARAPILOT_HOOKS_ENABLED=false` in `.env` runs none on one machine. `php artisan larapilot:hook-list` lists them and checks the file; `larapilot:hook-run {event} --phase=before|after [--spec=] [--task=] [--release=] [--dry-run]` fires one event without changing any state.

## Events

| Event | Fired by | `before` runs | `after` runs |
| --- | --- | --- | --- |
| `prd.written` | `prd-write` | before the PRD is saved | once it is saved |
| `spec.added` | `spec-add` | before the stories enter the backlog | once they have |
| `spec.planned` | `spec-plan` | before the plan is saved | once the spec is `PLANNED` |
| `spec.started` | `spec-start` | before the spec moves | once it is `IN PROGRESS` |
| `task.done` | `task-done` | before the task is marked done | once it is, with its commit |
| `spec.review` | `spec-review` | before the spec moves | once it is `REVIEW` |
| `spec.approved` | `spec-approve` | before the approval | once it is `DONE`, with the merge commit |
| `spec.changes_requested` | `spec-request-changes` | before the rework is written | once it is back to `TODO` |
| `release.shipped` | `release-ship` | before the merge and the tag | once the release is shipped |
| `ship` | `/larapilot-ship`, with `larapilot:hook-run ship` | as the gate starts | after a GO verdict |

## Two kinds

- **`run:`** — a shell command. The Artisan command runs it itself, from the project root, with `LARAPILOT_HOOK_EVENT`, `_PHASE`, `_PROJECT_ROOT`, `_SPEC`, `_SPEC_TITLE`, `_TASK`, `_STATUS_FROM`, `_STATUS_TO`, `_COMMIT`, `_RELEASE` in its environment and the event as JSON on stdin. You only read the result.
- **`skill:`** — a skill **you** run at that moment, for the spec, task, or release of the event. Often a custom skill of `.larapilot/skills/`.

## Reading the answer

- A command that ran hooks answers with `data.hooks`: `before` and `after`, each with `ran` (name, `ok`, `exit_code`, `timed_out`, the tail of `output` (at most 40 lines, 4 KB), and `log`, the whole output under `.larapilot/cache/hooks/`), `skills`, and `warnings`. No hook, no key.
- **Blocked** — `E_PRECONDITION` with `details.hooks`, and **nothing was written**. A failed `run:` hook is a check of the project that failed, like a red test: read `output`, then `log` when the cause is not in the tail; fix the code; run the same command again. When the fix is not yours to make — a service down, a credential missing, a tool not installed — stop and tell the user which hook and why.
- **Skill hooks before a transition** — the first call is refused and names the skills. Run each one now, act on what it finds, then repeat the command with `--skill-hooks-done=<name>[,<name>]`. Name only the skills you ran.
- **After** — run every skill under `data.hooks.after.skills` now, before the next step of the workflow. A failed `after` command is under `warnings`: the transition stands; tell the user in one line.

## Rules

- **The hooks belong to the user.** Never add, change, or remove a hook, never turn `hooks` off, and never set `LARAPILOT_HOOKS_ENABLED` to get past one, unless the user asks for it in this conversation; then record it with `larapilot:decision-log --topic="hooks"`.
- A file with errors refuses **every** transition while hooks are on: show the `findings` of `hook-list` and let the user fix the file or turn hooks off. `hook-list` exits 2 on such a file (for CI) while its envelope is still `hook_list`: read the envelope, not the exit code.
- `--force` on `spec-review` and `spec-approve` skips the task and feedback checks, never a hook.
- Hooks run from Artisan only: never from the dashboard, the API, or MCP. Never fire an `after` hook a second time with `hook-run` to retry a deploy or a push the user did not ask for again.
- An autopilot worker blocked by a hook it cannot fix stops that spec and reports, as on a failing test.

## Turning them on

`php artisan larapilot:settings-set --hooks=YES`. `larapilot:install` and `larapilot:update` write `.larapilot/hooks.yaml` once, with every event listed and every example commented out, so turning the setting on runs nothing until the user writes a hook. A hook is `- run: <command>` or `- skill: <name>` under `hooks.{event}.before` or `.after`, with optional `name`, `timeout` (seconds, default 300, at most 3600), and `blocking: false` for a `before` hook that only warns. Secrets stay in `.env`: a hook reads them from its environment. Good first hooks: the test suite or static analysis `before` `task.done` or `spec.review`, a staging deploy `after` `spec.approved`, a pre-deploy custom skill `before` `ship`, a production deploy `after` `ship`, release notes `after` `release.shipped`. Check with `hook-list`, try with `hook-run … --dry-run`, then without it.
