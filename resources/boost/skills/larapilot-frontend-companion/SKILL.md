---
name: larapilot-frontend-companion
description: "Links an external frontend repo — single app or monorepo (Nx, Angular CLI, pnpm/yarn workspaces, Turborepo) in Angular, React, Vue, Svelte — from the Laravel workspace, loads its AGENTS.md / CLAUDE.md / Cursor / Copilot rules, and hands off to its team. Italian: repo frontend, frontend esterno, monorepo, regole agent."
---

# Larapilot — External frontend repo

When **Frontend Topology** is `API + external frontend`, **everything runs from the Laravel workspace**. The FE repo is a linked write target — not a second Larapilot cockpit. Often it is a monorepo shared with other products, and it has rules for agents its team wrote: Larapilot finds the projects that are this product's, and follows those rules.

## How it works

1. **Laravel** owns PRD, backlog, plans, mockups, workflow state.
2. **`LARAPILOT_FRONTEND_REPO_PATH`** in `.env` points to the absolute FE directory; the target **projects** and the **mode** live in `.larapilot/config.yaml` — all set with `larapilot:frontend-set`, never a user path in YAML.
3. **`frontend-scan`** reads the workspace, the projects, the FE repo's agent rules, the conventions measured on its code, its commands, its API client. **`frontend-rules`** says which rules govern the files a task writes.
4. **`driven` mode** — `larapilot-plan` / `larapilot-implement` write UI code there via tasks marked `repo: frontend`. **`handoff` mode** — the frontend team builds from `larapilot:frontend-brief`.

The FE repo holds application code only — no mirrored PRD, no Larapilot workflow.

## When to use

- Setting up or verifying split-repo delivery from **Laravel**
- User provides or changes the FE repo path, its projects, or who builds the frontend
- Before the first plan on an existing FE codebase, and whenever the FE repo moved a lot
- The FE team reports that its rules or its monorepo were ignored

## Context

`php artisan larapilot:context frontend-companion` — with `--session={token}` when this conversation already holds one, `--fresh` after a compaction. Read every file under `data.runtime.read`, none under `loaded`, and an `on_demand` file only when its `when` comes true. Settings, paths, `data.project`, and `data.frontend` (`repo_path`, `workspace_path`, `stack`, `projects`, `mode`, `configured`) come from that envelope: no `config-show`. The rules are **Frontend Topology** (`discovery-5.md`) and **Frontend Companion** (`frontend.md`).

## The Team

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — output economy |
| ✨ **Joe** | Frontend Expert — workspace, rules, conventions, playbook from the scan |
| 📐 **John** | Architect — API boundaries, write scope in the monorepo |
| 🔗 **Matt** | Integration — auth/CORS/OpenAPI, generated API client |
| 🎨 **Elise** | UX — mockups stay in Laravel `.larapilot/mockups/` |

## Workflow

### 1. Link the FE repo (once)

If `data.frontend.configured` is **false**, **AskQuestion** (or chat) for the **absolute path** — keep asking until provided; never use placeholder user paths in artifacts.

```bash
php artisan larapilot:frontend-set --path=/absolute/path/to/fe-repo
```

This writes `LARAPILOT_FRONTEND_REPO_PATH` in `.env`. The PRD records the repository's name and stack, never the path.

### 2. Scan, and name the projects of this product

```bash
php artisan larapilot:frontend-scan
```

- `targets.needs_project` (a monorepo, no target yet) → **AskQuestion** which applications belong to this product — `targets.suggested` first, then `targets.candidates` — and save them; never pick for the user:

```bash
php artisan larapilot:frontend-set --project=portal --project=portal-admin
```

- `targets.missing` → a saved project is gone or renamed: ask again. A project only an Nx plugin infers is kept with `--skip-check`.
- `run_in: null` → the repository is one project of a monorepo that is not around it (`workspace.location`: a `project.json` with no `nx.json`, a `tsconfig` that extends a file two folders up). **AskQuestion** where that monorepo is checked out, then `frontend-set --workspace=/absolute/path` (it goes to `.env`). A repository cloned inside its monorepo needs nothing: the scan finds the monorepo among its parent folders, runs the commands there, and commits in the project's own repository (`git_root`).
- Then ask who builds the frontend, when the PRD's **Frontend delivery** does not say: `driven` (default) or `handoff`, saved with `frontend-set --mode=…`.

Report in a few lines: workspace and package manager, target projects with stack and version, owned and shared libraries, how many rule files (`rules.must_read`) and which formats, generated or hand-written API client, the `warnings`. Record **Frontend projects** and **Frontend delivery** in the PRD.

### 3. Load the frontend team's rules

Read every `rules.must_read` file, in order, with the file-read tool (paths relative to the scan's `root` — the workspace, which is `repo_path` only when the repository is its own workspace), and the playbook files under `playbooks`. Before writing any file: `php artisan larapilot:frontend-rules --file=<path> [--file=…]`. The protocol and the precedence are **Frontend Companion** (`frontend.md`) — the FE repo's rules win on code.

### 4. Deliver from Laravel

`/larapilot-spec` → `/larapilot-plan` → `/larapilot-implement` — all from this workspace.

- Backend tasks → Laravel (`repo: backend` or default)
- UI tasks → `repo: frontend`, `project:` in a monorepo, `shared:` when a shared library changes; paths under `data.frontend.repo_path`
- FE git: `git -C {git_root}` — `target_projects[].git_root` when set, else `git.root` from the scan — hooks on, `{code} TASK-NN` in the subject · FE checks: `target_projects[].commands` and `commands.affected` from the scan — never a guessed `npm test`
- `handoff` → `php artisan larapilot:frontend-brief {code}`; give the user the `prompt` line. The FE team reports its commits; `task-done` links them.

## Output Boundaries

- No workflow commands in the FE repo
- No PRD or product edits in the FE repo — change scope on Laravel only
- No write outside `write_scope.owned` without a task that names the shared library
- Never invent API endpoints on the client; never edit a generated client

## Output Economy

**Moderate** — short setup report after link + scan. Do not paste the scan.
