---
name: larapilot-frontend-companion
description: "Links an external frontend repo from the Laravel workspace. Italian: repo frontend, frontend esterno."
---

# Larapilot — External frontend repo

When **Frontend Topology** is `API + external frontend`, **everything runs from the Laravel workspace**. The FE repo is a linked write target — not a second Larapilot cockpit.

## How it works

1. **Laravel** owns PRD, backlog, plans, mockups, workflow state.
2. **`LARAPILOT_FRONTEND_REPO_PATH`** in `.env` points to the absolute FE directory (set via `larapilot:frontend-set` — never commit user paths in YAML).
3. **`larapilot-plan` / `larapilot-implement`** write UI code there via tasks marked `repo: frontend`.

The FE repo holds application code only — no mirrored PRD, no Larapilot workflow.

## When to use

- Setting up or verifying split-repo delivery from **Laravel**
- User provides or changes the absolute FE repo path
- Before first plan on an existing FE codebase (`frontend-scan`)

## Shared Runtime

Obey **Read protocol** in `.larapilot/shared-runtime.md`: file-read tool only, never `cat` / `head` / `sed`. A truncated preview is a failed load — read the remainder before any other step. Then read only the section files that index lists for this skill.

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

If `config-show` → `data.frontend.configured` is **false**, **AskQuestion** (or chat) for the **absolute path** — keep asking until provided; never use placeholder user paths in artifacts.

```bash
php artisan larapilot:frontend-set --path=/absolute/path/to/fe-repo --stack=React
```

This writes `LARAPILOT_FRONTEND_REPO_PATH` in `.env`. Record the stack (not the path) in the PRD → **External frontend repo**.

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
