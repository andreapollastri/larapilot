## Developer Portal — Backstage _(Matt owns — integration surface)_

**Backstage** (backstage.io) is an **org-level portal**: a catalog of every service, its owner, docs, and APIs. Larapilot is a **repo-level** workflow. The integration publishes the repo's `.larapilot/` truth into the portal — it never moves the workflow into Backstage.

**Direction is one-way.** `.larapilot/` is the source of truth; Backstage renders it. Workflow state changes only through skills and `larapilot:*` commands — never from the portal.

### What gets generated

| Artifact | Path | Purpose |
| --- | --- | --- |
| Catalog descriptor | `catalog-info.yaml` (repo root) | `Component` entity for the app, plus an `API` entity per registered OpenAPI contract |
| TechDocs config | `mkdocs.yml` (repo root) | Points Backstage TechDocs at the generated docs directory |
| TechDocs sources | `.larapilot/techdocs/` | `index.md` (delivery snapshot), `prd.md`, `backlog/index.md`, `backlog/US-XXX.md` |
| Live snapshot | `GET {api}/backstage` | Metrics, per-status counts, blocking feedback, lean story list for a portal plugin |

All of it comes from `php artisan larapilot:backstage-export` — never hand-write these files from a skill.

### Ownership rules

| Rule | Why |
| --- | --- |
| `catalog-info.yaml` and `mkdocs.yml` are **never overwritten** without `--force` | A project may already own them (existing catalog entry, existing MkDocs site) |
| Everything under `.larapilot/techdocs/` **is** overwritten, and stale story pages pruned | Generated output; editing it by hand is a mistake, not a customization |
| Identity (`owner`, `system`, `lifecycle`, `component_type`) lives in **Laravel config / `.env`**, not `.larapilot/config.yaml` | It describes the org's catalog, not the delivery workflow — `settings-set` must not be used for it |
| Backstage needs a resolvable **owner** | Entities whose owner is not an existing Group/User show as dangling in the portal |

### Regeneration cadence

Regenerate after PRD edits, backlog changes, or plan/task completion — otherwise the portal drifts from the repo. A CI step on the default branch is the reliable option (**Jack**); manual re-runs of `/larapilot-backstage` work for low-frequency projects.

### Security boundary _(Lars + Matt)_

The Larapilot API is a **dev/staging surface** and returns `404` in production. A Backstage plugin must call it through the **Backstage backend proxy** so `LARAPILOT_API_TOKEN` stays server-side — never from browser code, and never pointed at a production host. When no environment is reachable from the portal, ship the committed `catalog-info.yaml` and TechDocs instead of the live endpoints.

Set `LARAPILOT_API_TOKEN` on the dev/staging host and turn on the `api_auth` project setting (`php artisan larapilot:settings-set --api-auth=YES`) so every `/larapilot/api/*` call — the Backstage endpoints included — requires the token and the API fails closed (HTTP 503) if the env var is ever missing. This never affects the `/larapilot` dashboard UI (`dashboard_auth`) or the MCP server.

Ownership: **Matt** owns the catalog mapping and portal integration; **Jack** owns the CI regeneration step; **Albert** owns TechDocs readability; **Lars** owns the token/proxy boundary.
