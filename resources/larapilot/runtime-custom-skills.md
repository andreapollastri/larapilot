# Larapilot Runtime — Custom Skills

Phase pack for **`larapilot-custom-skill`**. Read `.larapilot/shared-runtime.md` (core) first.

## Storage _(Zoey + Sarah)_

User-authored Boost skills live under **`.larapilot/skills/{skill-name}/SKILL.md`** (path key `paths.custom_skills`). Each folder is one skill; the file follows the same front-matter contract as packaged Larapilot skills:

```yaml
---
name: my-custom-flow
description: When to trigger this skill — be explicit for Boost routing.
---
```

List discovered skills: `php artisan larapilot:custom-skill-list`.

Custom skills extend Larapilot's base layer — they **never replace** core workflow commands; they call `php artisan larapilot:*` for persistence exactly like packaged skills.

## Authoring flow _(Zoey interviews; Sarah scaffolds files)_

`/larapilot-custom-skill` runs an AskQuestion-driven interview (max 3 per round):

1. **Intent** — one custom skill or a **family** of related skills?
2. **Trigger** — slash command name + natural-language description (Boost `description` field).
3. **Workflow** — numbered steps, personas involved, which runtime packs to load, which CLI commands to call.
4. **Persistence** — new artifacts (if any) must use existing `.larapilot/` paths or propose a new path key via `/larapilot-settings` — never ad-hoc files outside the workspace contract.

Zoey drafts the `SKILL.md` body; Sarah writes the file(s) under `.larapilot/skills/`. After creation, remind the user to run `php artisan boost:update` (or `larapilot:update`) so Boost picks up the new skill.

## ON / OFF

Custom skills are **always available** once written — there is no global toggle. Remove a skill by deleting its folder. Packaged Larapilot skills remain the default workflow; custom skills are opt-in via their slash command.

## Quality bar

- Reuse persona names from `config-show` → `data.personas`.
- Honor `data.settings` (effort, git_mode, release_mode, …) like any packaged skill.
- Start every custom skill with `php artisan larapilot:config-show`.
- Never embed user-specific absolute paths — use env vars per **Environment paths** in shared-runtime.
