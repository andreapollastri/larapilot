---
name: larapilot-project-docs
description: "Bootstraps the living handbook in .larapilot/docs/handbook/. Requires project_docs=YES. Italian: documentazione progetto, handbook."
---

# Larapilot — Project Documentation

Maintain a **living handbook** under `paths.project_docs` (`.larapilot/docs/handbook/`) when `data.settings.project_docs` is `YES`. Albert leads structure and prose; John/Mike/Sarah/Joe contribute domain sections.

## Context

`php artisan larapilot:context project-docs` — with `--session={token}` when this conversation already holds one, `--fresh` after a compaction. Read every file under `data.runtime.read`, none under `loaded`. Settings, paths, and `data.project` come from that envelope: no `config-show`. The contract is `project-docs.md`.

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

1. `paths.project_docs` is in the `context` envelope
2. `php artisan larapilot:spec-list` — feature inventory for retroactive bootstrap
3. Read PRD at `paths.prd` when present

## Workflow

### 0. Assess

Check whether the handbook holds a chapter — any `.md` besides `README.md`, which until the bootstrap is the stub Larapilot seeds on install. A `_project_docs/` folder left at the project root is the old location: run `php artisan larapilot:update`, which moves it into the handbook, before assessing.

| State | Action |
| --- | --- |
| No chapter | **Bootstrap** (retroactive catch-up per runtime pack) |
| Present | **Incremental refresh** — AskQuestion which chapter(s) to update |

### 1. Bootstrap (mid-project or day 1)

1. Scaffold chapter tree from `project-docs.md`.
2. Fill from PRD, specs, plans, git log, existing README/CHANGELOG.
3. Add Mermaid diagrams for architecture and primary user flows.
4. Mark uncertain blocks with `<!-- TODO: verify -->`.
5. Replace the seeded `README.md` with the index, linking every chapter.

### 2. Incremental update

After user picks chapters (or following implement/review/ship inline updates):

- Edit only affected sections
- Add/update diagrams when behavior changed materially
- Never store secrets or user-specific absolute paths

### 3. Confirm

Show a bullet list of files created/updated. When `decision_log=YES`, log enablement or major structural choices.

## Output Economy

**Moderate** — chat summary stays short; the handbook files carry the detail.
