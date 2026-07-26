# Larapilot Runtime — Ops & Lifecycle

Phase pack for **`larapilot-feature`**, **`larapilot-bug`**, **`larapilot-ship`**, and **`larapilot-backstage`**. Read `.larapilot/shared-runtime.md` (core) first.

Skill workflows are not repeated here: incremental feature intake lives in the **`larapilot-feature`** skill; bug triage and routing live in the **`larapilot-bug`** skill. This pack holds the shared lifecycle policies both depend on.

## PRD Living Document _(selective updates — not every change)_

The PRD is the **product contract** — what the product promises. It is **not** a maintenance log. Backlog specs, `{paths.support}/intake.md`, and the app `CHANGELOG.md` carry operational history.

### Two layers of truth

| Layer                | Artifact                                                | Updates when                                                            |
| -------------------- | ------------------------------------------------------- | -------------------------------------------------------------------------|
| **Product contract** | `{paths.prd}`                                           | Scope, FRs, MoSCoW, in/out of scope, architecture commitments change      |
| **Delivery & ops**   | `backlog.yaml`, specs, `intake.md`, code `CHANGELOG.md` | Every feature, bug, rework, hotfix                                        |

### When to update the PRD _(Mark owns)_

| Trigger                        | Update                                                       | Example                                          |
| ------------------------------ | ------------------------------------------------------------ | --------------------------------------------------|
| **New capability**             | New `### FR-XXX` + MoSCoW                                    | Export PDF fatture → `FR-011`                      |
| **Existing FR strengthened**   | Change MoSCoW on `FR-XXX`; optional bullet under that FR     | `Could` → `Must` for compliance                    |
| **Scope deferral / removal**   | `### Out of Scope` or `### Future Phases`                    | PDF deferred to V2                                 |
| **Architecture commitment**    | `## Technical Architecture`                                  | New required integration, tenancy pattern          |
| **Legacy parity change**       | PRD + `{paths.research}/legacy-parity.md`                    | New module in port scope                           |
| **Bug reveals requirement gap** | Clarify **parent FR** or NFR — **not** a "fix FR"           | Under `FR-003`: SSO must work on Safari 17+        |
| **Vision pivot**               | `/larapilot-inception` or major PRD revision                 | New product direction                              |

### When **not** to update the PRD

| Trigger                                            | Route instead                                       |
| -------------------------------------------------- | -----------------------------------------------------|
| Routine bug (restores expected behaviour)          | Spec fix / `spec-request-changes` + `intake.md`       |
| Review rework (implementation gap)                 | `spec-request-changes` only                           |
| Refactor, perf, tech debt (no user-facing change)  | Spec or plan only                                     |
| Hotfix production                                  | Spec + `hotfix/*`; app `CHANGELOG.md`                 |
| Regression on existing AC                          | Rework spec; regression test                          |

**Never** add `FR-XXX: Fix …` for bugs — fixes trace to existing FRs via spec **Type: Fix**.

### Decision gate _(when uncertain)_

**Mark** or **Sophia** asks via **AskQuestion** (one round, skippable):

- **Product requirement gap** — PRD should record this → update PRD (clarify FR / new FR)
- **Implementation fix** — behaviour already implied by PRD/spec → spec/rework only
- **Unsure** — default to **spec only**; note in chat to revisit the PRD after the fix if the gap persists

### How to apply a PRD update

1. Read the current PRD from `{paths.prd}`.
2. Apply the **minimal** edit — new/changed `FR-XXX`, `## MVP Scope`, or `## Technical Architecture` bullet.
3. Append one row to **`## PRD Revision History`** (create the section on the first post-inception edit):

```markdown
## PRD Revision History

| Date | Trigger | Summary |
| --- | --- | --- |
| {{DATE}} | larapilot-feature US-011 | Added FR-011 Export PDF (MoSCoW: Should) |
| {{DATE}} | larapilot-bug → FR-003 gap | SSO must work on Safari 17+ (macOS/iOS) |
```

4. `php artisan larapilot:prd-write` + `php artisan larapilot:validate-prd` (max 3 attempts).

### Per-skill PRD rules

| Skill                                         | PRD                                                                                   |
| --------------------------------------------- | ---------------------------------------------------------------------------------------|
| **`larapilot-inception`**                     | Create / full rewrite                                                                   |
| **`larapilot-feature`**                       | Update when scope changes (new FR, MoSCoW, in/out of scope)                             |
| **`larapilot-bug`**                           | **No** by default; update only on **requirement gap** (clarify parent FR)               |
| **`larapilot-spec`**                          | Read-only — trace specs to FRs; suggest `larapilot-feature` for scoped additions        |
| **`larapilot-plan` / `implement` / `review`** | Read-only — **never** `prd-write`                                                       |
| **`spec-request-changes`**                    | **Never** — rework lives in spec + plan                                                 |

Ownership: **Mark** owns PRD scope edits; **Sophia** flags requirement gaps from bugs; **Tom** ensures spec AC align with FRs after PRD edits.

## Maintenance & Support _(Sophia owns — post-ship)_

After specs reach **DONE** and the product is live, **Sophia** owns the support and maintenance loop:

| Responsibility        | Sophia                                                                                                                                                                    |
| --------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Bug intake**        | Collect user/stakeholder reports; normalize into `{paths.support}/intake.md` (default `.larapilot/docs/support/`; dated files allowed)                                        |
| **Triage**            | Severity (Critical/High/Medium/Low), reproduce steps, environment, affected spec/feature — severity maps to backlog priority: Critical → `CRITICAL`, High → `HIGH`, Medium → `MEDIUM`, Low → `LOW` |
| **Routing**           | Critical security → **Lars** + **Oliver** re-test; functional bugs → **`larapilot-bug`** (preferred) or `larapilot-spec` maintenance mode → `spec-add` / `spec-request-changes` rework |
| **Documentation**     | Keep README, OpenAPI, runbooks, and `CHANGELOG.md` current with every maintenance release                                                                                     |
| **Software updates**  | Coordinate dependency patches (`composer update`, security advisories) with **Lars** and **Jack**; feature maintenance with **Alex** via planned specs                        |
| **Long-term hygiene** | Scheduled reviews: stale integrations (**Matt**), locale drift (**Emily**), test debt (**Anne**)                                                                              |

Sophia does not bypass the workflow — every fix goes through spec → plan → implement → review like greenfield work, but may use `hotfix/*` Gitflow branches for Critical production issues (**Jack**). Maintenance/fix specs reuse the existing Maintenance epic when present.

Ownership: **Sophia** owns intake, triage, and maintenance backlog hygiene; **Lars** owns security patch priority; **Jack** owns the hotfix/release process; **Alex** implements; **Emily** keeps translations/docs in sync per locale.

## Red Team & Penetration Testing _(Oliver owns — reports to Lars)_

**Oliver** performs active security assessments and simulated attacks against the application and public site to find vulnerabilities **before** attackers do. Findings are reported to **Lars**, who prioritizes remediation and coordinates with Alex.

| Phase                | Oliver's role                                                                                                                                |
| -------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------|
| **Pre-ship**         | Mandatory red-team pass in `larapilot-ship` before Lars GO — scope checklist in **Security Assessment** in `runtime-ship.md`                   |
| **Post-integration** | Targeted pass when Matt ships high-risk integrations (payments, webhooks, OAuth, file import)                                                  |
| **Maintenance**      | Re-test after Sophia routes critical security bugs or Lars requests regression                                                                 |

Oliver does **not** fix code — he documents attack paths, PoC steps, severity, and affected endpoints in `{paths.security}/red-team-{release-or-spec}.md` (path from `config-show`). Lars merges Oliver's report with the blue-team OWASP review; Critical/High findings block ship until fixed or explicitly waived.

Ownership: **Oliver** owns offensive testing and red-team reports; **Lars** owns remediation priority, security gates, and GO/NO-GO; **Alex** fixes; **Anne** adds regression tests for confirmed vulnerabilities.

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

Ownership: **Matt** owns the catalog mapping and portal integration; **Jack** owns the CI regeneration step; **Albert** owns TechDocs readability; **Lars** owns the token/proxy boundary.
