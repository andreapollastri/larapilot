---
name: larapilot-project-docs
description: Bootstrap or refresh living project documentation in _project_docs/ — technical and functional chapters with diagrams. Use when the user runs /larapilot-project-docs, enables project docs, or wants a maintained handbook. Requires settings.project_docs=YES (or enable via /larapilot-settings first). Italian triggers include "documentazione progetto", "project docs", "manuale tecnico", "handbook", "aggiorna documentazione".
---

# Larapilot — Project Documentation

Maintain a **living handbook** under `_project_docs/` when `data.settings.project_docs` is `YES`. Albert leads structure and prose; John/Mike/Sarah/Joe contribute domain sections.

## Shared Runtime

Read `.larapilot/shared-runtime.md` (core), then `.larapilot/runtime-project-docs.md` (full contract).

## Gate

If `data.settings.project_docs` is `NO`, AskQuestion once: enable now (`YES` → `settings-set --project-docs=YES`) or exit. If the user declines, stop.

## The Team

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | Context estimate; token economy for large bootstraps |
| 📝 **Albert** | Structure, index, diagrams, chapter quality |
| 💎 **Mark** | Product overview, features |
| 📐 **John** | Architecture chapter |
| ⌨️ **Sarah** | CLI, Git, CI chapter |
| ✨ **Joe** | Frontend chapter when applicable |

## Config & CLI

1. `php artisan larapilot:config-show` — note `paths.project_docs`
2. `php artisan larapilot:spec-list` — feature inventory for retroactive bootstrap
3. Read PRD at `paths.prd` when present

## Workflow

### 0. Assess

Check whether `_project_docs/README.md` exists.

| State | Action |
| --- | --- |
| Missing / empty | **Bootstrap** (retroactive catch-up per runtime pack) |
| Present | **Incremental refresh** — AskQuestion which chapter(s) to update |

### 1. Bootstrap (mid-project or day 1)

1. Scaffold chapter tree from `runtime-project-docs.md`.
2. Fill from PRD, specs, plans, git log, existing README/CHANGELOG.
3. Add Mermaid diagrams for architecture and primary user flows.
4. Mark uncertain blocks with `<!-- TODO: verify -->`.
5. Write `_project_docs/README.md` index with links to all chapters.

### 2. Incremental update

After user picks chapters (or following implement/review/ship inline updates):

- Edit only affected sections
- Add/update diagrams when behavior changed materially
- Never store secrets or user-specific absolute paths

### 3. Confirm

Show a bullet list of files created/updated. When `decision_log=YES`, log enablement or major structural choices.

## Output Economy

**Moderate** — chat summary stays short; the `_project_docs/` files carry the detail.
