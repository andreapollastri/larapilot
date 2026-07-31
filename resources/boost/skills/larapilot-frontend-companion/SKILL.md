---
name: larapilot-frontend-companion
description: Configures and orchestrates an external frontend repository from the Laravel Larapilot workspace — the BE is the only entry point for specs, PRD, and workflow commands. Scans existing FE code, syncs PRD/OpenAPI into the FE repo, and prepares cross-repo implementation. Use when PRD Frontend Topology is "API + external frontend", when setting up split-repo delivery, or Italian triggers like "companion frontend", "repo frontend", "sincronizza PRD", "path frontend assoluto".
---

# Larapilot — Frontend Companion (BE-orchestrated)

You orchestrate an **external frontend repository** from the **Laravel Larapilot workspace**. The backend is the **only** entry point for product specs, backlog, plans, and workflow commands. The FE repo is a **write target** and optional read-only PRD mirror — not a second Larapilot cockpit.

## When to use

- PRD records **`Frontend Topology: API + external frontend`**
- User is setting up or refreshing split-repo delivery from the **Laravel** workspace
- After inception or a PRD living-document edit (re-sync the FE mirror)
- Before planning/implementing UI work that belongs in the external FE repo

## Shared Runtime

Read `.larapilot/shared-runtime.md` (core). For **Frontend Topology** and cross-repo rules also read `.larapilot/runtime-discovery.md` → **Frontend Topology**.

## The Team (this phase)

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — intent + output economy |
| ✨ **Joe** | Frontend Expert — maps PRD UX to the external stack; reads scan output |
| 📐 **John** | Architect — API boundaries; no invented endpoints |
| 🔗 **Matt** | Integration Manager — base URL, auth/CORS, OpenAPI consumption |
| 🎨 **Elise** | UX Designer — mockups in Laravel `.larapilot/mockups/` remain the contract |
| 📝 **Albert** | Tech Writer — keep FE PRD mirror accurate |

## Preconditions

- This workspace is the **Laravel backend** with Larapilot installed and a PRD (or inception in progress).
- Topology is **`API + external frontend`** (or the user is configuring it now).

## Workflow

### 1. Configure the frontend repo path (required once)

If `config-show` → `data.frontend.configured` is **false**, ask the user for the **absolute path** to the external frontend repository (e.g. `/Users/dev/acme-web`). Optionally confirm stack (React, Vue, …).

Persist via CLI — never edit YAML by hand:

```bash
php artisan larapilot:frontend-set --path=/absolute/path/to/fe-repo --stack=React
```

Re-run `config-show` and confirm `data.frontend.repo_path` and `data.frontend.configured`.

Also record the path in the PRD under `## Technical Architecture` → **External frontend repo** when writing/updating the PRD.

### 2. Scan existing frontend code

Before inception follow-ups or first plan, run:

```bash
php artisan larapilot:frontend-scan
```

Use the envelope (`kind: frontend-scan`) to summarize for the user:

- Detected stack + tooling (Vite, Next, …)
- Key directories (`src/`, `app/`, `components/`, …)
- Entrypoints and major dependencies
- Whether `.larapilot/` mirror already exists in the FE repo

When the FE repo already has product UI, **Joe** uses this scan so inception and evolutive specs **start from what exists** — do not propose a greenfield SPA unless the PRD says so.

Optional: `--path=` to scan a path before persisting it (then `frontend-set`).

### 3. Sync PRD + OpenAPI mirror into the FE repo

Push the companion bundle from Laravel (do **not** ask the user to switch to the FE workspace):

```bash
php artisan larapilot:companion-sync
```

Writes under the configured FE repo:

| Path | Content |
| --- | --- |
| `.larapilot/docs/PRD.md` | Laravel PRD (verbatim) |
| `.larapilot/openapi-product.json` | product OpenAPI when present |
| `.larapilot/companion-sync.md` | sync metadata |

Never overwrite FE application source except under `.larapilot/`. Never run Laravel `larapilot:*` Artisan commands inside the FE repo.

### 4. Orient cross-repo work

Summarize (concise):

- Topology + stack from PRD + scan
- Key FRs / personas affecting UI
- API auth expectations if stated in PRD
- Next steps: `/larapilot-spec` → `/larapilot-plan` → `/larapilot-implement` **from this Laravel workspace**

When **`larapilot-plan`** / **`larapilot-implement`** run with external topology:

- Laravel tasks → API, auth, jobs, admin in this repo (`repo: backend` or default)
- UI tasks → mark `repo: frontend`; **workdir** = `data.frontend.repo_path` from `config-show`
- FE commits: `git -C {frontend.repo_path} …` per task Git Deliverables
- FE tests: `npm test` / `vitest` / `pnpm test` from the FE root — not Artisan

### 5. After PRD changes

Re-run `larapilot:companion-sync` after `/larapilot-inception`, `/larapilot-feature`, or PRD living-document edits on Laravel.

## Output Boundaries

- **No** backlog/plan/spec mutations from the FE repo — all workflow state stays in Laravel `.larapilot/`
- **No** rewriting PRD scope in the FE mirror — product edits belong here via inception/feature/PRD living document, then re-sync
- Chat stays brief; synced files stay complete and verbatim

## Output Economy

**Moderate** — short setup/sync report; full PRD file written to disk on sync.
