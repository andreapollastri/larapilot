## Frontend Companion _(Joe owns · John, Matt, Anne partner)_

Applies when **Frontend Topology** is `API + external frontend` and `data.frontend.configured` is true. The frontend repository is a write target driven from this workspace. The editor loads the agent rules of the workspace it opened, never those of the frontend repository: this protocol loads them on purpose.

### 1. Scan — once per spec, before planning or writing

`php artisan larapilot:frontend-scan` returns one envelope. Every path in it is relative to `root`, and every command runs in `run_in`. `root` is the repository itself, or the workspace it builds in when it is one project of a monorepo kept elsewhere (`workspace.location`). Read it in this order:

| Field | Use |
| --- | --- |
| `run_in` | `null` → the workspace is out of reach (a `warnings` line says why): nothing can be built or tested. **AskQuestion** where that workspace is checked out and link it with `frontend-set --workspace=/absolute/path`, or say so in the handoff. |
| `targets` | `needs_project: true` or `missing` → stop. **AskQuestion** which projects belong to this product (`suggested` first, then `candidates`), then `frontend-set --project=<name>` (repeat). Never pick one yourself. |
| `workspace` | `kind` (`nx`, `angular-cli`, `pnpm-workspaces`, `turborepo`, `single`, …), tool version, `package_manager`. Use its `exec` / `run`, never another manager. |
| `target_projects[]` | `stack` + `framework.version` (write for that major), `commands`, `unavailable` (targets that cannot run, with the reason — never run them), `tests`, `libraries`, `observed`, `exemplars`, `depends_on`, and `git_root` when the project is a git repository of its own inside the workspace. |
| `write_scope` | `owned` roots: write. `shared`: other applications depend on it. `vendored`: built third-party code copied in — never edit. Anything else belongs to another team. |
| `rules` | The repository's agent rules, and under `inherited` those of the workspace around it — section 2. |
| `git` | Where the frontend commits go (`root`), its base branch, and `commits`: how the team writes subjects — section 7. |
| `commands` | `affected`, `format`, `format_check`, `install`, `show_project`. |
| `generators` | `local` (the team's own) before `collections`; `defaults` are the team's choices. |
| `api_client` | Generated client or hand-written calls — section 5. |
| `playbooks` | Read each file listed: defaults for the stack and its version. |
| `warnings` | Act on each one, or report it in the handoff. |

`--no-cli` reads the files only (no `nx graph`); `--fresh` rebuilds the cached Nx graph; `--full` lists every project; `--project=` overrides the configured targets for one scan.

### 2. House rules — the frontend team's rules win on code

- Before the **first** frontend edit of a session, read every file in `rules.inherited.must_read` (absolute paths: the workspace around the repository, read as an editor opened on it would), then every file in `rules.must_read`, top to bottom, with the file-read tool. A truncated read is a failed read.
- Before writing, run `php artisan larapilot:frontend-rules --file=<path>` (repeat `--file`, or comma-separate; relative to `root`, or absolute) with every file the task creates or changes. Read each `must_read` entry this session has not read, or whose `sha` changed. `on_request`: read when its `description` fits the task. `manual`: only when the user names it. `references`: guides the rules link to — read the one that covers the task.
- Precedence: inside the frontend repository its rules decide structure, naming, style, state, testing, commit messages, and branches. Larapilot decides the workflow only — no PRD or Larapilot files in the frontend repository, no undocumented endpoint, task status through `larapilot:*` here. A deeper folder's rule beats a shallower one. Two rules of one folder that disagree, or a rule that contradicts the spec → **AskQuestion**; never settle it silently.
- No rules at all (a `warnings` line says so) → follow `observed` and the `exemplars`, and say so in the handoff.

### 3. Write the way the code is already written

- Model every new file on an `exemplar` of its kind, with its `related` template, styles, and spec.
- `observed` is measured on the code and beats the playbook and your habits: a project whose components are declared in NgModules stays on NgModules, one on `*ngIf` stays on it, until a rule or the user says to migrate.
- Generate with the workspace: `generators.local` before `collections`, `--dry-run` first, `--help` for the options of this version. New libraries follow the layout and tags of their neighbours (Nx `scope:` / `type:` tags feed `@nx/enforce-module-boundaries`).
- Cross a project boundary through its alias or package name, never a relative path.
- Add no dependency, state library, UI kit, or test runner the project does not have, unless the task says so and the user agreed.

### 4. Write scope

- `owned` → write. `shared` → only when the task lists the library under `shared` with the reason; keep its public API backward compatible; run `commands.affected`. Any other folder → ask first.
- Plan: a task that changes a shared library carries `"shared": ["<project>"]` and names the applications that depend on it in its Description.

### 5. API contract _(Matt)_

- The frontend calls only what the product OpenAPI (`api_client.product_openapi`) documents. A missing endpoint is a backend task of this spec, never a client-side workaround.
- `api_client.generated: true` → after the backend task that changes the API, regenerate with `api_client.regenerate` (or the generator's `config`, whose `inputs` must point at the product OpenAPI). Never edit generated files; never write a service beside the generated client.
- Hand-written calls → use the HTTP layer `api_client.handwritten` and `observed` show (Angular `HttpClient` services, an axios instance, `$fetch`), typed from the OpenAPI schemas; loading, empty, and error states for every call.

### 6. Verify with the workspace's commands

- Per task: `lint`, `typecheck`, `test`, `build` from `target_projects[].commands` for every project changed. They run in `run_in`, never wait for input, and Karma runs headless. A target under `unavailable` is never run: report it.
- Before each commit: `commands.affected` when present — it also runs the projects that depend on the change — and `commands.format_check`. No `affected` because the project is a git repository nested in the workspace → its own `commands` are the check, plus the targets of the applications in `write_scope.shared[].apps` when a shared library changed.
- Tests: the bar is `settings.testing`. A project with no spec file yet (`tests.spec_files: 0`, often `generators_skip: true`) or no runnable test target is the team's habit against that bar: **AskQuestion** once whether this spec starts its tests or keeps the habit, and record the answer with `decision-log`. Never invent a test command.
- `package_manager.installed: false` → run `commands.install` first.
- Red → fix in the same task. A failure that existed before the change, in a project the task did not touch → report it; do not fix it silently.

### 7. Git in the frontend repository

- `git -C {git_root of the project, else git.root} …`. Its hooks (`git.commits.hooks`, commitlint) run: never `--no-verify`.
- Subject as `git.commits.pattern` says, in the language and the style of `git.commits.samples`, with `{code} TASK-NN` in it — `feat: US-012 TASK-03 lista ordini` for a team that writes Conventional Commits without scopes in Italian. A scope from `declared_scopes` only when it fits. `php artisan larapilot:task-done {code} TASK-NN`, run from `data.project_root`, finds that commit in the right repository by itself (the task's `project` names it).
- Branches follow `settings.git_mode` and the repository's own branch rules. Never push to a branch its rules protect.

### 8. Handoff mode

`data.frontend.mode` is `handoff` → this workspace writes no frontend code. Plan still writes the `repo: frontend` tasks. Implement runs the backend tasks, then `php artisan larapilot:frontend-brief {code}` (`--task=` for some, `--stdout` to return it without writing) and gives the user the `prompt` line of the envelope. The frontend tasks stay open until the frontend team names their commits; then `task-done`.

### 9. Review _(Robert, Lars, Joe)_

- Diff the frontend work: `git -C {git.root} diff {git.default_base.ref}...HEAD` (`git` of the scan). Check it against `rules.must_read`, against `frontend-rules --file` for every changed file, and against `observed`.
- A finding cites the rule file it breaks. Check also: write scope kept, no undocumented endpoint, generated files untouched, `affected` green, no secret and no absolute path committed.
