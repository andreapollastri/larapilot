## Larapilot

Larapilot is spec-driven product development for Laravel via [Laravel Boost](https://laravel.com/ai/boost): discovery → backlog → plan → implement → review → ship.

Boost skills run the conversation. `php artisan larapilot:*` persists artifacts as JSON envelopes (`larapilot/v1`). `.larapilot/` in the repo is the source of truth between sessions.

**Runtime:** at activation, read `.larapilot/shared-runtime.md` with the editor file-read tool and obey **Read protocol** there. That file is an index. Read only the section files it names for the active skill. Never `cat` a runtime file. A truncated preview means the rules were not loaded.

**Settings:** `php artisan larapilot:config-show`. Honor `data.settings` before planning or implementing. Matrices live in `.larapilot/runtime-core-settings.md`. `GITFLOW` never auto-pushes. `ECO` never spawns sub-agents and turns Lucille off. Lucille and the decision journal default to ON. Do not commit absolute paths. Personas are omitted unless you pass `--only=personas`. Any `--only=` slice that writes a file must include `paths`.

### When to use Larapilot

- New product or PRD → `larapilot-inception` (client docs in `.larapilot/client-materials/`, legacy snapshots in `.larapilot/legacy/`)
- Existing Laravel app with no PRD → `larapilot-adopt`
- One new feature → `larapilot-feature`. A bug → `larapilot-bug`
- Backlog → `larapilot-spec`. Plan → `larapilot-plan`. Implement → `larapilot-implement`. Accept → `larapilot-review`
- Mockups → `larapilot-design`. External frontend repo → `larapilot-frontend-companion`
- Ship → `larapilot-ship`. Releases, when `release_mode` is YES → `larapilot-release`
- Settings → `larapilot-settings`. Usage → `larapilot-usage`. Quote → `larapilot-economics`
- Tracker, Backstage, project handbook, custom skills → the matching `larapilot-*` skill

### Where things live

Paths come from `config-show` `data.paths`. Defaults: PRD `.larapilot/docs/PRD.md`, backlog `.larapilot/backlog.yaml`, specs `.larapilot/specs/`, plans `.larapilot/plans/`, mockups `.larapilot/mockups/{spec}/`, developer domain docs `.larapilot/docs/devs/` (English, every effort level), decision journal `.larapilot/decisions.yaml`. The full list is the runtime index.

Commands for the active skill are in that skill. Do not load the whole catalog. Prefer `config-show --only=`, `spec-show` / `spec-next` `--task=` / `--fields=`, and `quality` without `-v`.

Personas are lenses. The roster is `.larapilot/runtime-core-personas.md`. Chat uses `icon + name`. Brevity is **Output Economy** in `.larapilot/runtime-core-economy.md`: the implement status line is one line per task, and the spec handoff is 6 lines. Review and explore sub-agents are readonly. Nothing spawns under `effort: ECO`. Under `STANDARD` or `MAX`, autopilot may delegate one spec at a time to a writing worker that does not call Larapilot transitions; the parent keeps CLI, questions, and Robert/Lars (`runtime-core-subagents.md`, **Spec worker**). That parent does not read `runtime-delivery`, `runtime-dev-docs`, `runtime-ops`, or `task-templates`.
