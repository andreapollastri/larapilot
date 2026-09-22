Part of this runtime pack. The pack file is an index. Read with the editor file-read tool, never `cat`.

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
**External frontend repo:** {{absolute path — when API + external frontend}}
**External frontend stack:** {{React / Vue / Angular / Svelte / Other — when external}}
```

### When topology is `API + external frontend`

1. **Laravel repo** is the **only** Larapilot cockpit — PRD, backlog, plans, mockups, and all `/larapilot-*` commands.
2. **Link the FE repo** — ask for the **absolute path** at inception; persist with `php artisan larapilot:frontend-set --path=… [--stack=…]`. Record the same path in the PRD.
3. **Scan before planning** — `php artisan larapilot:frontend-scan` so inception and evolutive specs start from existing FE code.
4. **Implement from Laravel** — plan/implement use `repo: frontend` for UI tasks; files write under `data.frontend.repo_path`.

### Downstream honor rules

- **`larapilot-plan` / `larapilot-implement`**: when topology is external, Laravel tasks focus on API, auth, jobs, admin (`repo: backend` or default); UI tasks use `repo: frontend` and write under `data.frontend.repo_path` from `config-show` — do not invent a Blade SPA in Laravel unless the user changes topology.
- **`larapilot-design`**: mockups stay the shared UX contract in Laravel `.larapilot/mockups/`; Joe implements them in the FE repo.
- **PRD edits** happen on Laravel only — the FE repo never holds a mirrored PRD.

Ownership: **John** owns topology and API boundaries; **Joe** owns in-repo or external web FE stack choice and companion skill usage; **Ricky** owns mobile shells that may also be separate repos (same companion pattern when they consume the Laravel API).
