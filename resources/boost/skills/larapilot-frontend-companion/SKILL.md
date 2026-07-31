---
name: larapilot-frontend-companion
description: Configures an external frontend repository from the Laravel Larapilot workspace. The backend is the only entry point for PRD, backlog, plans, and implementation. Use when PRD Frontend Topology is "API + external frontend", when linking the FE repo path, or Italian triggers like "repo frontend", "path frontend assoluto", "frontend esterno".
---

# Larapilot — External frontend repo

When **Frontend Topology** is `API + external frontend`, **everything runs from the Laravel workspace**. The FE repo is a linked write target — not a second Larapilot cockpit.

## How it works

1. **Laravel** owns PRD, backlog, plans, mockups, workflow state.
2. **`frontend.repo_path`** in `.larapilot/config.yaml` points to the absolute FE directory.
3. **`larapilot-plan` / `larapilot-implement`** write UI code there via tasks marked `repo: frontend`.

The FE repo holds application code only — no mirrored PRD, no Larapilot workflow.

## When to use

- Setting up or verifying split-repo delivery from **Laravel**
- User provides or changes the absolute FE repo path
- Before first plan on an existing FE codebase (`frontend-scan`)

## Shared Runtime

Read `.larapilot/shared-runtime.md` (core) and `.larapilot/runtime-discovery.md` → **Frontend Topology**.

## The Team

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — output economy |
| ✨ **Joe** | Frontend Expert — stack + existing code from scan |
| 📐 **John** | Architect — API boundaries |
| 🔗 **Matt** | Integration — auth/CORS/OpenAPI |
| 🎨 **Elise** | UX — mockups stay in Laravel `.larapilot/mockups/` |

## Workflow

### 1. Link the FE repo (once)

If `config-show` → `data.frontend.configured` is **false**, ask for the **absolute path** (e.g. `/Users/dev/acme-web`).

```bash
php artisan larapilot:frontend-set --path=/absolute/path/to/fe-repo --stack=React
```

Record the same path in the PRD → **External frontend repo**.

### 2. Scan existing code (before plan / evolutive)

```bash
php artisan larapilot:frontend-scan
```

Summarize stack, tooling, directories, entrypoints. **Joe** uses this so specs start from code already present.

### 3. Deliver from Laravel

`/larapilot-spec` → `/larapilot-plan` → `/larapilot-implement` — all from this workspace.

- Backend tasks → Laravel (`repo: backend` or default)
- UI tasks → `repo: frontend`, paths under `data.frontend.repo_path`
- FE git: `git -C {repo_path} …` · FE tests: `npm test` / vitest from FE root

## Output Boundaries

- No workflow commands in the FE repo
- No PRD or product edits in the FE repo — change scope on Laravel only
- Never invent API endpoints on the client

## Output Economy

**Moderate** — short setup report after link + scan.
