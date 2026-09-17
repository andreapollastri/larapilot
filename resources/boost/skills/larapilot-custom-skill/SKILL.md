---
name: larapilot-custom-skill
description: Create one or more custom Larapilot/Boost skills under .larapilot/skills/ via a Zoey-guided interview. Use when the user runs /larapilot-custom-skill, wants custom workflows, team-specific procedures, or extensions on top of the standard Larapilot layer. Italian triggers include "skill custom", "skill personalizzata", "flusso custom", "procedura custom larapilot".
---

# Larapilot — Custom Skills

Author **user-defined Boost skills** stored in `.larapilot/skills/`. Zoey interviews; Sarah writes files; packaged Larapilot skills remain the base layer.

## Shared Runtime

Read `.larapilot/shared-runtime.md` (core), then `.larapilot/runtime-custom-skills.md`.

## The Team

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AskQuestion interview — intent, triggers, workflow |
| ⌨️ **Sarah** | Scaffold folders + `SKILL.md` files |
| 📝 **Albert** | Clear descriptions and step numbering |

## Config & CLI

1. `php artisan larapilot:config-show`
2. `php artisan larapilot:custom-skill-list` — avoid duplicate names

## Workflow

### 1. AskQuestion — Round 1 (intent)

| Option id | Label |
| --- | --- |
| `single` | One new custom skill |
| `family` | Several related skills (shared prefix / domain) |
| `list` | List existing custom skills only |

When `list`: run `custom-skill-list`, print triggers, stop.

### 2. AskQuestion — Round 2 (when creating)

- **Slash name** — e.g. `my-deploy-checklist` (becomes `/my-deploy-checklist` in Boost after update)
- **Trigger description** — one paragraph for the YAML `description:` field (when to activate)

### 3. AskQuestion — Round 3 (workflow)

- **Personas** — which Larapilot agents speak in this skill?
- **Runtime packs** — which `.larapilot/runtime-*.md` files to load?
- **CLI commands** — which `larapilot:*` commands must be called (in order)?

Free-text allowed for step details after the round.

### 4. Draft & write

1. Zoey drafts full `SKILL.md` (front matter + sections mirroring packaged skills: Shared Runtime, Team, Config & CLI, Workflow, Output Economy).
2. Sarah writes `.larapilot/skills/{name}/SKILL.md`.
3. Run `custom-skill-list` to confirm.
4. Tell the user: `php artisan boost:update` (or `larapilot:update`) to register the skill in Boost.

### 5. Quality checklist (before finish)

- [ ] Starts with `config-show`
- [ ] Honors `data.settings`
- [ ] No user-specific paths in examples
- [ ] Persistence only via `larapilot:*` commands

## Output Economy

**High** — show the draft skill structure in chat; file on disk is the deliverable.
