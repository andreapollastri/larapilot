# Larapilot Runtime — Custom Skills

Phase pack for **`larapilot-custom-skill`**. Read `.larapilot/shared-runtime.md` (core) first.

## Storage _(Zoey + Sarah)_

User-authored Boost skills live under **`.larapilot/skills/{skill-name}/SKILL.md`** (path key `paths.custom_skills`). The folder is created on `larapilot:install` with a `.gitkeep`. Each folder is one skill; the file follows the same front-matter contract as packaged Larapilot skills:

```yaml
---
name: my-custom-flow
description: When to trigger this skill — be explicit for Boost routing.
---
```

List (and auto-register) discovered skills: `php artisan larapilot:custom-skill-list`.

Persist a new skill: `php artisan larapilot:custom-skill-add --name=… --content=…` (or `--file=`). Sarah **never** writes `SKILL.md` by hand. The command saves under `.larapilot/skills/` and mirrors the folder into **`.ai/skills/`** (Laravel Boost's custom-skill source) plus any existing agent skill directories (`.cursor/skills/`, `.claude/skills/`, …), then runs `boost:update` when available.

Custom skills extend Larapilot's base layer — they **never replace** core workflow commands; they call `php artisan larapilot:*` for persistence exactly like packaged skills.

The dashboard **Skills** page (`/larapilot/skills`) lists every custom skill with its slash trigger and the YAML `description` (what it does).

## Authoring flow _(Zoey interviews; Sarah persists via CLI)_

`/larapilot-custom-skill` runs an AskQuestion-driven interview (max 3 per round):

1. **Intent** — one custom skill or a **family** of related skills?
2. **Trigger** — slash command name + natural-language description (Boost `description` field).
3. **Workflow** — numbered steps, personas involved, which runtime packs to load, which CLI commands to call.
4. **Persistence** — new artifacts (if any) must use existing `.larapilot/` paths or propose a new path key via `/larapilot-settings` — never ad-hoc files outside the workspace contract.

Zoey drafts the `SKILL.md` body; Sarah saves it with `larapilot:custom-skill-add`. Registration with Boost is automatic.

## ON / OFF

Custom skills are **always available** once written — there is no global toggle. Remove a skill by deleting `.larapilot/skills/{name}/` **and** its mirrors in `.ai/skills/{name}/` and any agent skill folder it was copied to (`.claude/skills/`, `.cursor/skills/`, …), then run `php artisan boost:update`. Registration (`custom-skill-add`, `custom-skill-list`, `larapilot:update`, the Skills page) only adds and refreshes copies — it never deletes one. Packaged Larapilot skills remain the default workflow; custom skills are opt-in via their slash command.

## Quality bar

- Reuse persona names from `config-show --only=personas` → `data.personas`, or from **Agent Persona** in the runtime index.
- Honor `data.settings` (effort, git_mode, release_mode, …) like any packaged skill.
- Start every custom skill with `php artisan larapilot:config-show`.
- Never embed user-specific absolute paths — use env vars per **Environment paths** in shared-runtime.
