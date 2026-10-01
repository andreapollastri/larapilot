## Larapilot

Larapilot is spec-driven product development for Laravel via [Laravel Boost](https://laravel.com/ai/boost): discovery → backlog → plan → implement → review → ship. Boost skills run the conversation, `php artisan larapilot:*` persists artifacts as JSON envelopes (`larapilot/v1`), and `.larapilot/` in the repo is the source of truth between sessions.

### Context protocol

A Larapilot skill starts with **`php artisan larapilot:context {skill}`**. One envelope carries `data.settings`, `data.paths`, `data.project`, and `data.runtime`:

- Read every file under `runtime.read`, from `runtime.dir`, with the editor file-read tool — all in one step when the editor reads in parallel. Never `cat` one. A truncated preview means the rules were not loaded: read the rest before anything else.
- Do not read what `runtime.loaded` lists, nor a file this conversation already read: it is still here.
- Read a `runtime.on_demand` file only when its `when` comes true.
- Pass `--session={data.session}` on every later `context` call of the conversation. After the conversation was compacted, pass `--fresh`: the token survives a summary, the rules do not.

The files are compiled for the project and hold only the rules its settings call for. Honor `data.settings` before planning or implementing, and never apply from memory a rule the files do not show. Outside a skill, load no Larapilot runtime file.

### When to use Larapilot

- New product or PRD → `larapilot-inception` (client docs in `.larapilot/client-materials/`, legacy snapshots in `.larapilot/legacy/`)
- Existing Laravel app with no PRD → `larapilot-adopt`
- One new feature → `larapilot-feature`. A bug → `larapilot-bug`. A request that may be either → `larapilot-triage`, which classifies it and hands off. Security findings from Aikido → `larapilot-aikido`, errors in production from the tracker of the project (Boogle, Sentry, …) → `larapilot-error`, which asks which tracker when none is set: each downloads them, has the user confirm, and hands them to triage group by group
- A change to the PRD that is neither — sharpen, re-prioritize, re-scope, answer an open question, upgrade an older PRD → `larapilot-prd`. A PRD edited by hand → `larapilot-prd` to reconcile history, validation, and backlog
- Backlog → `larapilot-spec`. Plan → `larapilot-plan`. Implement → `larapilot-implement`. Accept → `larapilot-review`
- Mockups → `larapilot-design`. External frontend repo — a single app or a monorepo, its agent rules, a handoff to its team → `larapilot-frontend-companion`
- Ship → `larapilot-ship`. Releases, when `release_mode` is YES → `larapilot-release`
- Upgrade Laravel → `larapilot-laravel-upgrade`, PHP → `larapilot-php-upgrade`, the database or its engine → `larapilot-db-upgrade`: each checks readiness first (`larapilot:upgrade-check`), reports the criticalities, and changes nothing until the user chooses. Vulnerable dependencies (CVE) and the SBOM → `larapilot-vendor-check`
- Settings → `larapilot-settings`. Usage → `larapilot-usage`. Re-plan the order and the dates of delivery → `larapilot-schedule`. Quote → `larapilot-economics`
- Tracker, Backstage, project handbook, custom skills → the matching `larapilot-*` skill

### Working rules

- **Read the slice, not the document** — `spec-list` (the backlog without the bodies), `spec-show` / `spec-next` with `--task=` / `--fields=`, `prd-show --ids=` / `--section=`, `quality` without `-v`. The commands of a skill are in that skill: do not load the whole catalog.
- **Paths** come from `data.paths`. Defaults: PRD `.larapilot/docs/PRD.md`, backlog `.larapilot/backlog.yaml`, specs `.larapilot/specs/`, plans `.larapilot/plans/`, mockups `.larapilot/mockups/{spec}/`, developer domain docs `.larapilot/docs/devs/` (English, every effort level), decision journal `.larapilot/decisions.yaml`. Do not commit absolute paths.
- **Settings** — `GITFLOW` never auto-pushes. `ECO` never spawns sub-agents and turns Lucille off. Lucille and the decision journal default to ON.
- **Hooks** — with `hooks` YES, a transition (`spec-plan`, `spec-start`, `task-done`, `spec-review`, `spec-approve`, …) runs the project's hooks from `.larapilot/hooks.yaml`. `E_PRECONDITION` with `details.hooks` means a hook failed or a skill hook must run first: fix it and repeat, or report it. Never edit the hooks or turn them off to get past one unless the user asks.
- **Personas** are lenses: chat uses `icon + name`, briefly (**Output Economy**). Review and explore sub-agents are readonly. Under `STANDARD` or `MAX`, autopilot may delegate one spec at a time to a writing worker that does not call Larapilot transitions; the parent keeps CLI, questions, and Robert/Lars (**Spec worker**), and does not read `runtime-delivery`, `runtime-dev-docs`, or `task-templates`.
