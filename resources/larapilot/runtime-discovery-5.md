## Frontend Topology _(John + Joe co-own)_

During **`larapilot-inception`**, **John** and **Joe** **must** ask **Frontend Topology** via **AskQuestion** whenever the product has a user-facing UI (most **Website** and **Application** projects; skip only for pure CLI/API-worker **Personal** tools with no UI). Ask **before** the Filament / Starter Kit / custom panel question so the panel route stays coherent.

### Options (never assume)

| Value                         | Meaning                                                                                | Typical stack in this Laravel repo                                                  |
| ----------------------------- | -------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| **`Laravel-coupled`**         | UI lives in the same Laravel repo                                                      | Blade, Livewire, Inertia + Vue/React/Svelte, Filament, Flux                          |
| **`SPA-in-Laravel`**          | SPA (or SPA islands) built with Vite **inside** this Laravel repo                       | React / Vue / Angular / Svelte (or Starter Kit Inertia variants) served by Laravel   |
| **`API + external frontend`** | Laravel exposes **API (+ optional admin)** only; the primary UI is another repository   | Sanctum/Passport API, OpenAPI; optional Filament admin for ops                       |

Record in PRD `## Technical Architecture`:

```markdown
**Frontend Topology:** Laravel-coupled | SPA-in-Laravel | API + external frontend
**Frontend stack (in-repo):** {{Blade / Livewire / Inertia+… / Vite SPA … / N/A}}
**External frontend repo:** {{repository name or URL — the local path lives in LARAPILOT_FRONTEND_REPO_PATH, never here}}
**External frontend stack:** {{React / Vue / Angular / Svelte / Other — when external}}
**Frontend projects:** {{workspace projects of this product when the repository is a monorepo (Nx, Angular CLI, pnpm / yarn workspaces, Turborepo) — or N/A}}
**Frontend delivery:** {{driven — Larapilot writes the frontend | handoff — the frontend team builds from a brief}}
```

### When topology is `API + external frontend`

1. **Laravel repo** is the **only** Larapilot cockpit — PRD, backlog, plans, mockups, and all `/larapilot-*` commands.
2. **Link the FE repo** — ask for the **absolute path** at inception; persist with `php artisan larapilot:frontend-set --path=… [--stack=…]` (it goes to `.env`). The PRD records the repository's name, never the path.
3. **Name the projects when it is a monorepo** — run `php artisan larapilot:frontend-scan`; when it answers `targets.needs_project`, **AskQuestion** which of its applications belong to this product (`suggested` first) and persist them with `frontend-set --project=<name>` (repeat). A monorepo shared with other products is the normal case, not an exception: never pick for the user.
4. **Ask who writes the frontend** — `driven` (default: Larapilot writes it from this workspace) or `handoff` (the frontend team builds it in its own repository from `larapilot:frontend-brief`); persist with `frontend-set --mode=…`.
5. **Scan before planning** — `frontend-scan` gives the workspace, the target projects, the agent rules of the frontend repository, the conventions measured on its code, the commands that verify it, and the API client. The protocol for all of it is **Frontend Companion** (`frontend.md`).
6. **Implement from Laravel** — plan/implement use `repo: frontend` (plus `project:` in a monorepo) for UI tasks; files write under `data.frontend.repo_path`, under the frontend repository's own rules.

### Downstream honor rules

- **`larapilot-plan` / `larapilot-implement`**: when topology is external, Laravel tasks focus on API, auth, jobs, admin (`repo: backend` or default); UI tasks use `repo: frontend` and write under `data.frontend.repo_path` from the `context` envelope — do not invent a Blade SPA in Laravel unless the user changes topology.
- **`larapilot-design`**: mockups stay the shared UX contract in Laravel `.larapilot/mockups/`; Joe implements them in the FE repo.
- **PRD edits** happen on Laravel only — the FE repo never holds a mirrored PRD.

Ownership: **John** owns topology and API boundaries; **Joe** owns in-repo or external web FE stack choice and companion skill usage; **Ricky** owns mobile shells that may also be separate repos (same companion pattern when they consume the Laravel API).
