---
name: larapilot-custom-skill
description: Create one or more custom Larapilot/Boost skills under .larapilot/skills/ via a Zoey-guided interview. Use when the user runs /larapilot-custom-skill, wants custom workflows, team-specific procedures, or extensions on top of the standard Larapilot layer. Italian triggers include "skill custom", "skill personalizzata", "flusso custom", "procedura custom larapilot".
---

# Larapilot — Custom Skills

Author **user-defined Boost skills** stored in `.larapilot/skills/`. Zoey interviews; Sarah persists files via `larapilot:custom-skill-add` (never hand-write `SKILL.md`). Packaged Larapilot skills remain the base layer.

## Shared Runtime

Read `.larapilot/shared-runtime.md` (core), then `.larapilot/runtime-custom-skills.md`.

## The Team

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AskQuestion interview — intent, triggers, workflow |
| ⌨️ **Sarah** | Persist folders + `SKILL.md` via `larapilot:custom-skill-add` |
| 📝 **Albert** | Clear descriptions and step numbering |

## Config & CLI

1. `php artisan larapilot:config-show`
2. `php artisan larapilot:custom-skill-list` — avoid duplicate names; also registers discovered skills with Boost

## Workflow

### 1. AskQuestion — Round 1 (intent)

| Option id | Label |
| --- | --- |
| `single` | One new custom skill |
| `family` | Several related skills (shared prefix / domain) |
| `list` | List existing custom skills only |

When `list`: run `custom-skill-list`, print triggers, stop.

### 2. AskQuestion — Round 2 (when creating)

- **Slash name** — e.g. `my-deploy-checklist` (becomes `/my-deploy-checklist` in Boost after auto-register)
- **Trigger description** — one paragraph for the YAML `description:` field (when to activate)

### 3. AskQuestion — Round 3 (workflow)

- **Personas** — which Larapilot agents speak in this skill?
- **Runtime packs** — which `.larapilot/runtime-*.md` files to load?
- **CLI commands** — which `larapilot:*` commands must be called (in order)?

Free-text allowed for step details after the round.

### 4. Draft & persist

1. Zoey drafts full `SKILL.md` (front matter + sections mirroring packaged skills: Shared Runtime, Team, Config & CLI, Workflow, Output Economy). Front matter **must** include `name` and `description`.
2. Sarah **must** persist with Artisan (never write the canonical `SKILL.md` by hand). Write the draft to a temp file, then:

```bash
php artisan larapilot:custom-skill-add --name={name} --file=.larapilot/tmp-{name}-SKILL.md
```

Delete the temp file after a successful envelope. For a family, call `custom-skill-add` once per skill. Use `--force` only when the user asked to overwrite.
3. Run `custom-skill-list` to confirm. The command also copies each skill into `.ai/skills/` (and existing agent skill folders) so Boost can publish the slash command.
4. Tell the user the skill lives at `.larapilot/skills/{name}/SKILL.md` and is listed on the dashboard **Skills** page (`/larapilot/skills`). Do **not** ask them to run `boost:update` — `custom-skill-add` already registered it.

### 5. Quality checklist (before finish)

- [ ] Starts with `config-show`
- [ ] Honors `data.settings`
- [ ] No user-specific paths in examples
- [ ] Persistence only via `larapilot:*` commands
- [ ] Saved with `larapilot:custom-skill-add`, not a raw file write

## Output Economy

**High** — show the draft skill structure in chat; `.larapilot/skills/{name}/SKILL.md` is the deliverable.
