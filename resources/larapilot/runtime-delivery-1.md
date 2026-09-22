Part of this runtime pack. The pack file is an index. Read with the editor file-read tool, never `cat`.

## Architecture Standards _(John owns)_

John designs **scalable, complete products** whose depth matches the **delivery target** — never a throwaway MVP stack when the target is V1 Complete, Full Product, or Enterprise.

| Delivery target  | Architecture depth                                                                                                                    |
| ---------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| **MVP**          | Thin vertical slice: core domain model, minimal API surface if needed, queues only where sync would block UX                           |
| **V1 Complete**  | Service boundaries, versioned HTTP API (Sanctum/Passport), queues for mail/webhooks/heavy work, structured logging                     |
| **Full Product** | Full API catalog, rate limiting, Horizon/workers, event-driven integrations, DTOs at integration boundaries                            |
| **Enterprise**   | Above plus audit trails, multi-tenant isolation, ADRs, **full observability** (metrics, traces, alerting), disaster-recovery posture   |

**Always apply when architecting and planning:**

1. **SOLID** — design modules, services, and boundaries per SOLID. Prefer small Actions/Services over god classes; depend on abstractions only when there are real alternate implementations or test seams; keep controllers thin and domain rules out of HTTP/UI layers. Document intentional deviations in plan/ADR notes — never silent SOLID violations.
2. **Query performance & N+1** — every list/detail/API endpoint that loads relations must plan **eager loading** (`with` / `loadMissing`), selective columns, and indexes for filter/sort/FK columns. Flag loops that would query per row; prefer chunking/cursors for bulk work; never design "load then foreach → `$model->relation`" without an eager-load strategy. Record expected query shape in plan tasks when the path is hot.
3. **Queues & jobs** — offload email, webhooks, imports, reports, and any I/O-heavy work to Laravel queues (`ShouldQueue` jobs, Horizon in production). Never block HTTP requests on slow external calls.
4. **Logging** — structured application logging (`Log` channels, context arrays); log auth failures, payment events, and integration errors; define retention aligned with Violet's policy.
5. **Service integration** — encapsulate third-party APIs in dedicated service classes; use Events/Listeners for side effects; prefer Spatie packages or Laravel first-party over ad-hoc HTTP in controllers.
6. **DTOs & boundaries** — use Data objects / DTOs (Spatie Laravel Data, readonly PHP classes, or Form Request → DTO mappers) at API and integration boundaries when payloads are non-trivial; keep Eloquent models out of external contracts.
7. **Quality bar** — clear layers (Controller → Action/Service → Model); **fail-fast validation** at the edge (Form Requests); **idempotent** writes where retries are possible (webhooks, jobs); **transaction boundaries** around multi-model mutations; **authorization at the policy/gate layer** (not only UI); explicit error/domain exceptions over silent failure; migrations that own indexes and constraints with the schema change.
8. **Technical debt** — one migration per concern; explicit interfaces only when multiple implementations exist; document trade-offs in plan/ADR notes instead of hidden shortcuts; prefer readable Laravel idioms over premature abstraction.
9. **Documentation** — keep docs current with code in the same spec that changes the API or integration, and update the touched **developer domain docs** under `paths.dev_docs` in that same spec (see **Technical Documentation** below, including the `ECO` gate, and `.larapilot/runtime-dev-docs.md`).

**SSO / social login** — prefer **[Laravel Socialite](https://laravel.com/docs/socialite)** with official drivers; for providers beyond the core set use **[Socialite Providers](https://socialiteproviders.com/)** — never roll custom OAuth unless no provider exists. Store provider IDs on the User model (UUID PK); link accounts; respect Violet's consent requirements.

### Multi-tenancy _(John owns — always evaluate pros & cons)_

When the product serves **multiple customers, workspaces, or isolated environments**, John **must** compare tenancy patterns in the PRD `## Technical Architecture` (or a linked ADR) — never assume single-tenant by default if the brief implies SaaS, agencies, or per-client isolation.

| Pattern                         | How it works                                                                                                                                                                                                                     | Pros                                                                                                              | Cons                                                                                | Best when                                                                       |
| ------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------- |
| **A — Distributed monolith**    | **One repo**, same Laravel monolith **deployed to N servers** (or N Cipi/Forge sites); **custom subdomain** (or domain) per tenant; optional **central SSO** in front (Cloudflare Access, Keycloak, Auth0, Sanctum central IdP)   | Strong runtime isolation, per-tenant scaling, simple mental model, easy custom domains, blast-radius containment   | N deploy pipelines to patch, config drift if not automated, higher base infra cost   | Few–medium tenants, enterprise clients, strict isolation without microservices   |
| **B — Row-level (`tenant_id`)** | Single deploy, single DB; `tenant_id` on rows; global scopes / middleware                                                                                                                                                          | Cheapest, fastest MVP, one migration path                                                                          | Weakest isolation, IDOR risk if scopes fail, noisy-neighbor on shared DB             | Many small tenants, early B2B SaaS, MVP validation                               |
| **C — Database-per-tenant**     | Single deploy; separate DB (or connection) per tenant                                                                                                                                                                              | Strong data isolation, clean export/delete per tenant                                                              | Connection management, many DBs to migrate/backup                                    | Compliance-heavy (GDPR erasure), medium tenant count                             |
| **D — Schema-per-tenant**       | Single DB, separate PostgreSQL schema per tenant                                                                                                                                                                                   | Balance of isolation and shared infra                                                                              | PostgreSQL-only, migration fan-out complexity                                        | Medium tenants on PostgreSQL                                                     |
| **E — Package-driven**          | [stancl/tenancy](https://tenancyforlaravel.com/) or [spatie/laravel-multitenancy](https://github.com/spatie/laravel-multitenancy) — subdomain identification, bootstrapped tenant context                                          | Laravel-native, community patterns, less bespoke glue                                                              | Package constraints, learning curve                                                  | Greenfield multi-tenant Laravel with subdomain routing                           |

**John's decision rules:**

1. **Always present at least two options** (typically **A** and **B** or **E**) with explicit trade-offs and Aurora cost notes.
2. **Pattern A** — recommend when: few tenants, high isolation need, custom domains per client, or central SSO gateway. Document: subdomain DNS (per PRD edge provider), deploy automation (same artifact → N targets), env/secrets per instance, shared vs per-tenant DB choice.
3. **Central SSO in front of A** — propose when tenants share an identity plane: OAuth/OIDC gateway, JWT to Laravel, or Socialite against a central IdP; use `*.127001.it` or `*.app.test` locally.
4. **Never skip tenant context** in auth policies, queues, and file storage — every pattern needs explicit `TenantScope`, disk prefix, or connection resolver.
5. Scale pattern choice to **delivery target**: MVP may start with **B** or **E** with a documented migration path to **A** or **C** for Enterprise.

Ownership: **John** selects and documents the pattern; **Andrew** validates Laravel-native tenancy packages; **Lars** reviews isolation and IDOR; **Violet** reviews data residency per tenant; **Jack** automates N-deploy or connection routing.

## Data Architecture _(Mike owns)_

**Mike** is the authority on **schema shape, database engine choice, relationships, migrations, and search/indexing**. John designs application architecture; Mike decides how data is stored and queried when the choice is material. They collaborate with **Jack** (ops/backups), **Aurora** (cost), **Lars** (injection, tenancy isolation, PII at rest), **Alex** (Eloquent usage), **Andrew** (Laravel idioms), **Sabrine** (legacy schema port), **Tom** (data ACs), and **Mark** (scope vs delivery target).

### Decision lens

Evaluate every non-trivial persistence choice against: **performance**, **usability** (query/API ergonomics), **maintainability**, **scalability**, **dev experience**, **cost**, and **security** — scaled to **Delivery Target** (MVP may accept a simpler model with a documented upgrade path; Enterprise must justify isolation, indexes, and operational load).

### Tree / hierarchy patterns _(choose explicitly — never invent ad-hoc)_

| Pattern | Pros | Cons | Prefer when |
| ------- | ---- | ---- | ----------- |
| **Adjacency List** (`parent_id`) | Simple writes, intuitive | Expensive deep reads without recursion/CTE | Shallow trees, frequent moves |
| **Nested Sets** | Fast subtree reads | Expensive writes/rebuilds | Read-heavy catalogs, rare moves |
| **Path Enumeration** / materialized path | Fast ancestors/descendants with `LIKE`/`ltree` | Path renames on move | Medium depth, PostgreSQL `ltree` available |
| **Closure Table** | Flexible queries both ways | Extra table + write amplification | Complex graph-like hierarchies |
| **Other** (graph DB, JSON document) | Domain-fit | Ops/skill cost | Only when relational fit is poor |

### Engine & search

1. **SQL first** for relational Laravel apps (MySQL/MariaDB/PostgreSQL per Jack/infra). Document engine-specific features (`jsonb`, `ltree`, full-text).
2. **NoSQL / document / key-value** only with a clear access pattern (session/cache ≠ primary domain store unless justified).
3. **Search engines** (Elasticsearch, OpenSearch, Meilisearch, Typesense, Scout drivers) when full-text/facet needs exceed SQL FTS — size cost with Aurora; never duplicate source of truth without sync strategy.
4. **Migrations** — Mike owns migration design with Alex: one concern per migration, indexes with schema change, reversible when safe, data backfills as explicit tasks. No silent schema drift.
5. Record choices in PRD `## Technical Architecture` (e.g. `**Data store:** PostgreSQL`, `**Hierarchy:** Closure Table`, `**Search:** Meilisearch via Scout`).

Ownership: **Mike** decides; **John** integrates with app boundaries; **Alex** implements; **Anne** tests migration + query correctness; **Sabrine** ports legacy schemas; **Lars** reviews sensitive data paths.

