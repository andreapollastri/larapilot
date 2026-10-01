# Larapilot Shared Runtime (Index)

This file is the index. It is not the rules, and a skill does not read it to start.

## Context protocol

At activation a skill runs **`php artisan larapilot:context {skill}`** and reads what `data.runtime.read` lists, from `data.runtime.dir`. Those files are compiled for the project: the rules its settings call for, and none of the others. A later call in the same conversation passes `--session={data.session}` and lists only what is new; after the conversation was compacted, it passes `--fresh`. Contract: **Context and the session cache** in `runtime-core-cli.md`.

## Read protocol (mandatory)

1. Load a runtime file with the editor **file-read tool**. Never `cat`, `head`, or `sed` one.
2. A truncated preview, an "output saved to" path, or a byte cap means the load **failed**. Read the remainder before any other step. Do not plan, do not call the next command, do not write code.
3. Read all of `read`, none of `loaded`, and an `on_demand` file only when its `when` comes true. Each file is under 15 KB so one file-read returns it whole.

## Without `larapilot:context`

Only when the command cannot run. Read the four every-skill files, then the part that holds each heading the active skill cites — a pack index maps its headings to its parts. These are the files as the package ships them, with every value of every setting between `<!-- when: … -->` markers: apply the block that matches `config-show` → `data.settings`.

| File | Who reads it |
| --- | --- |
| `.larapilot/runtime-core-cli.md` | Every skill. CLI contract, context and session cache, slices, worktree, Boost, non-negotiables |
| `.larapilot/runtime-core-settings.md` | Every skill. Effort, backlog, git, testing, account, auto-approve, Lucille, decision journal, prior art, code history |
| `.larapilot/runtime-core-economy.md` | Every skill. Output Economy, Zoey's context estimate |
| `.larapilot/runtime-core-settings-2.md` | Every skill, for the opt-in toggles that are `YES`: release mode, project docs, comments, security scan, Aikido, production errors, forges, notifications |
| `.larapilot/runtime-core-language.md` | Skills that write a PRD, a spec, a plan, or mockups |
| `.larapilot/runtime-core-personas.md` | Inception, adopt, feature, spec, custom-skill. Other skills name their cast |
| `.larapilot/runtime-core-subagents.md` | Plan, implement, adopt, autopilot |
| `.larapilot/runtime-spec-worker.md` | Autopilot, and plan or implement when they run as its worker |
| `.larapilot/runtime-delivery.md` | Plan, implement, review. Index |
| `.larapilot/runtime-dev-docs.md` | Plan, implement, review, ship, adopt |
| `.larapilot/runtime-dev-docs-catchup.md` | Implement and adopt, on a project with code and no domain docs |
| `.larapilot/task-templates.md` | Plan |
| `.larapilot/runtime-discovery.md` | Inception, adopt, feature, spec, prd, frontend-companion. Index |
| `.larapilot/runtime-frontend.md` | Frontend-companion, plan, implement, review, once an external frontend repository is linked |
| `.larapilot/runtime-frontend-angular.md`, `.larapilot/runtime-frontend-react.md`, `.larapilot/runtime-frontend-vue.md`, `.larapilot/runtime-frontend-svelte.md` | The same skills, for the stack `frontend-scan` lists under `playbooks` |
| `.larapilot/runtime-ux.md` | Design; plan and implement on a spec with UI; ship on public sites. Index |
| `.larapilot/runtime-ship.md` | Ship. Index |
| `.larapilot/runtime-ops.md` | Feature, bug, prd, ship, usage, schedule, tracker, backstage. Index, one part per audience |
| `.larapilot/runtime-economics.md` | Economics; settings when `account` is not `NONE`. Index |
| `.larapilot/runtime-release.md` | Release; inception, adopt, feature, plan, implement, and ship when `release_mode` is `YES` |
| `.larapilot/runtime-project-docs.md` | Project-docs; implement, review, and ship when `project_docs` is `YES` |
| `.larapilot/runtime-hooks.md` | Inception, adopt, feature, bug, prd, spec, plan, implement, review, autopilot, ship, release when `hooks` is `YES`: what a hook that blocks or asks for a skill means. Settings and custom-skill on demand |
| `.larapilot/runtime-upgrade.md` | Laravel-upgrade, php-upgrade, db-upgrade. Index: the Upgrade Protocol, the Laravel ladder and package playbooks, PHP and database playbooks |
| `.larapilot/runtime-custom-skills.md` | Custom-skill |

`larapilot-triage` uses the every-skill rows only: the skill it hands off to reads its own rows. `larapilot-aikido` and `larapilot-error` do the same. So does `larapilot-vendor-check`. A delegating autopilot parent does not read the delivery packs, the dev-docs packs, or `task-templates`: its workers do. One concept has one canonical file — reference it by file and heading, never re-paste it. A citation of the form `shared-runtime` → **Heading** means the file in the table above that holds that heading.
