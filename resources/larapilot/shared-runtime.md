# Larapilot Shared Runtime (Index)

This file is the index. It is not the rules. Load it with the editor **file-read tool**. Never `cat`, `head`, or `sed` a runtime file.

## Read protocol (mandatory)

1. A truncated preview, an "output saved to" path, or a byte cap means the load **failed**. Read the remainder before any other step. Do not plan, do not call the next command, do not write code.
2. Read only the rows for the active skill. If that file is an index, read **every part it names**. Do not stop after the index, and do not skip a part.
3. Each section file is under 15 KB so one file-read returns it whole. If your tool still truncates, the load failed — go back to step 1.

## Every skill

| File | What it holds |
| --- | --- |
| `.larapilot/runtime-core-cli.md` | CLI contract, selective envelopes, worktree, Boost |
| `.larapilot/runtime-core-settings.md` | Effort, backlog, git, testing, account, auto-approve, Lucille, decision log, code history |
| `.larapilot/runtime-core-economy.md` | Output Economy, including the implement status line |

Read `.larapilot/runtime-core-settings-2.md` only when `config-show` reports `YES` for release mode, project docs, comments, dashboard auth, API auth, security scan, a forge, or notifications.

## When the skill needs them

| File | Read for |
| --- | --- |
| `.larapilot/runtime-core-language.md` | Any skill that writes a PRD, spec, or user-facing copy |
| `.larapilot/runtime-core-personas.md` | Inception, adopt, feature, spec, custom-skill. Other skills already name their cast |
| `.larapilot/runtime-core-subagents.md` | Plan, implement, review, adopt, autopilot |
| `.larapilot/runtime-delivery.md` | Plan, implement, review, autopilot, bug. Index — read every part it names |
| `.larapilot/runtime-discovery.md` | Inception, adopt, feature, spec, frontend-companion. Index — read every part |
| `.larapilot/runtime-ux.md` | Design, plan when the spec has UI, ship. Index |
| `.larapilot/runtime-ship.md` | Ship. Index |
| `.larapilot/runtime-ops.md` | Feature, bug, ship, usage, tracker, backstage; every skill when `lucille` is `YES` (Usage Ledger). Index — read every part |
| `.larapilot/runtime-dev-docs.md` | Implement, review, ship, adopt, bug, autopilot |
| `.larapilot/runtime-economics.md` | Economics; settings when `account` is not `NONE`. Index |
| `.larapilot/runtime-release.md` | Release, and any skill when `release_mode` is `YES` |
| `.larapilot/runtime-project-docs.md` | Project-docs, and any skill when `project_docs` is `YES` |
| `.larapilot/runtime-custom-skills.md` | Custom-skill |
| `.larapilot/task-templates.md` | Plan and implement |

`larapilot-settings` and `larapilot-frontend-companion` use the every-skill rows plus the row above that names them. One concept has one canonical file — reference it by file and heading, never re-paste it. A citation of the form `shared-runtime` → **Heading** means the file in the tables above that holds that heading.
