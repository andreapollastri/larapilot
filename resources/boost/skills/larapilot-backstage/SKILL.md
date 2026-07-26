---
name: larapilot-backstage
description: Publishes this Laravel repo into a Backstage (backstage.io) developer portal — generates catalog-info.yaml (Component + API entities), TechDocs sources from the PRD and backlog, and wires the live delivery snapshot endpoint. Use when the user mentions Backstage, software catalog, developer portal, TechDocs, catalog-info, entity provider, or Italian triggers like "portale sviluppatori", "catalogo servizi", "registra su Backstage", "techdocs".
---

# Larapilot — Backstage Integration

You publish the `.larapilot/` workspace into an **organization-level developer portal**. Backstage renders; Larapilot remains the source of truth. You never move workflow state into the portal.

## Shared Runtime

Read `.larapilot/shared-runtime.md` (core) and `.larapilot/runtime-ops.md` → **Developer Portal — Backstage** (canonical ownership, regeneration, and security rules — do not restate them, apply them).

## The Team (this phase)

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — intent + output economy |
| 🔗 **Matt** | Integration Manager — owns the catalog mapping (owner, system, lifecycle, APIs) |
| 🚀 **Jack** | DevOps — CI regeneration, environment reachability |
| 📝 **Albert** | Tech Writer — TechDocs nav and readability |
| 🔐 **Lars** | Security Expert — token/proxy boundary; never a production data source |

## Preconditions

- Larapilot installed (`.larapilot/config.yaml` exists) — otherwise run `php artisan larapilot:install` first
- A PRD and ideally a backlog: TechDocs is thin without them (still valid — say so rather than inventing content)
- The user has (or is setting up) a Backstage instance — if not, explain what the generated files are for and stop

## Config & CLI

1. `php artisan larapilot:config-show` — read `data.backstage` (entity ref, owner, system, lifecycle, techdocs, catalog path/existence)
2. `php artisan larapilot:backstage-export` — preview the bundle (read-only, writes nothing)
3. `php artisan larapilot:backstage-export --write [--force] [--no-techdocs]` — generate the files

Never hand-write `catalog-info.yaml`, `mkdocs.yml`, or anything under `.larapilot/techdocs/` — always the CLI.

## Workflow

### 0. Read current state

Run `config-show`. Report one line:

`entity={data.backstage.entity_ref} · owner={…} · system={…} · lifecycle={…} · techdocs={…} · catalog_exists={…}`

If `enabled` is `false`, tell the user to set `LARAPILOT_BACKSTAGE_ENABLED=true` and stop.

### 1. Catalog identity (Matt)

Backstage entity identity lives in **Laravel config / `.env`** — not `.larapilot/config.yaml`. Never use `larapilot:settings-set` for it.

**Free-text values — ask in chat** (not AskQuestion), one short message, current value in brackets:

- **Owner** — the Backstage Group or User that owns this component (e.g. `platform`, `group:default/payments`). Required: Backstage flags entities whose owner does not resolve.
- **System** _(optional)_ — parent System entity when the org uses them (e.g. `commerce`).

**Enumerated values — AskQuestion (max 3 per round), only when the user wants to change them:**

**1. Lifecycle**

- **AskQuestion prompt:** `Lifecycle (current: {VALUE}) — how the org should read this component's maturity`

| Option id | AskQuestion label |
| --- | --- |
| `experimental` | `experimental — prototype or early delivery; no consumer guarantees (default)` |
| `production` | `production — live and supported; other teams may depend on it` |
| `deprecated` | `deprecated — still catalogued, being retired` |

**2. Component type**

- **AskQuestion prompt:** `Component type (current: {VALUE}) — how Backstage classifies this repo`

| Option id | AskQuestion label |
| --- | --- |
| `service` | `service — backend/API application (default for Laravel)` |
| `website` | `website — user-facing site delivered from this repo` |
| `library` | `library — package consumed by other repos` |

**3. TechDocs**

- **AskQuestion prompt:** `TechDocs (current: {VALUE}) — publish the PRD and backlog as a docs site inside Backstage?`

| Option id | AskQuestion label |
| --- | --- |
| `YES` | `YES — generate mkdocs.yml + .larapilot/techdocs/ so the portal renders PRD, backlog, and plans (default)` |
| `NO` | `NO — catalog entity only; no docs site` |

Only ask about the **Larapilot workflow API** entity if the user brings up exposing the board API (off by default — it is dev-only tooling, not a product contract).

### 2. Persist identity

Write the answered keys to `.env` (and mirror them in `.env.example` when present), leaving unanswered keys untouched:

```dotenv
LARAPILOT_BACKSTAGE_OWNER=platform
LARAPILOT_BACKSTAGE_SYSTEM=commerce
LARAPILOT_BACKSTAGE_LIFECYCLE=production
LARAPILOT_BACKSTAGE_COMPONENT_TYPE=service
LARAPILOT_BACKSTAGE_BASE_URL=https://staging.example.com
```

Set `LARAPILOT_BACKSTAGE_BASE_URL` only when a **non-production** environment is reachable — it feeds catalog links and annotations. Never write a production URL there.

Re-run `config-show` and confirm the values took effect.

### 3. Generate

```bash
php artisan larapilot:backstage-export --write
```

Add `--force` only when the user accepts overwriting an existing `catalog-info.yaml` / `mkdocs.yml` — the envelope's `data.hint` names the files that were kept. Add `--no-techdocs` when the user answered `NO` in step 1.

Parse the envelope (`kind: "backstage-export"`) and report:

`Generated → {data.catalog.path} · {data.techdocs.pages|count} TechDocs pages · entities: {data.entity_refs}`

### 4. Register in Backstage

Give the user the concrete next steps (short list, no lecture):

1. Commit `catalog-info.yaml` and `mkdocs.yml` (plus `.larapilot/techdocs/`) on the default branch.
2. In Backstage: **Create → Register existing component** → paste the repo URL of `catalog-info.yaml`. Orgs with catalog discovery configured skip this.
3. TechDocs builds from `mkdocs.yml`; the `backstage.io/techdocs-ref: dir:.` annotation is already set.

### 5. Keep it fresh (Jack — optional)

Offer, do not silently configure:

- A CI job on the default branch running `php artisan larapilot:backstage-export --write --force` and committing the diff
- Or re-running `/larapilot-backstage` after PRD/backlog milestones

If the user wants a **live** board card in Backstage, point at `GET {api}/backstage` and state the boundary once: call it through the Backstage backend proxy so `LARAPILOT_API_TOKEN` stays server-side, and never target production (the API answers `404` there).

## Rules

- No PRD, backlog, plan, or code changes — this skill publishes, it does not author
- Never invent owners, systems, or team names — ask, or leave the configured default
- Never present the portal as a place to change workflow state
- Never edit generated TechDocs pages by hand to "fix" content — fix the PRD or the spec, then regenerate

## Output Economy

**High** — short confirmations, the generated file list, and the registration steps. Honor Zoey's start/end **Context estimate** lines from shared-runtime.
