## Multi-tenancy _(John owns — always evaluate pros & cons)_

When the product serves **multiple customers, workspaces, or isolated environments**, John **must** compare tenancy patterns in the PRD `## Technical Architecture` (or a linked ADR) — never assume single-tenant by default if the brief implies SaaS, agencies, or per-client isolation.

| Pattern | How it works | Pros | Cons | Best when |
| --- | --- | --- | --- | --- |
| **A — Distributed monolith** | **One repo**, same Laravel monolith **deployed to N servers** (or N Cipi/Forge sites); **custom subdomain** (or domain) per tenant; optional **central SSO** in front (Cloudflare Access, Keycloak, Auth0, Sanctum central IdP) | Strong runtime isolation, per-tenant scaling, simple mental model, easy custom domains, blast-radius containment | N deploy pipelines to patch, config drift if not automated, higher base infra cost | Few–medium tenants, enterprise clients, strict isolation without microservices |
| **B — Row-level (`tenant_id`)** | Single deploy, single DB; `tenant_id` on rows; global scopes / middleware | Cheapest, fastest MVP, one migration path | Weakest isolation, IDOR risk if scopes fail, noisy-neighbor on shared DB | Many small tenants, early B2B SaaS, MVP validation |
| **C — Database-per-tenant** | Single deploy; separate DB (or connection) per tenant | Strong data isolation, clean export/delete per tenant | Connection management, many DBs to migrate/backup | Compliance-heavy (GDPR erasure), medium tenant count |
| **D — Schema-per-tenant** | Single DB, separate PostgreSQL schema per tenant | Balance of isolation and shared infra | PostgreSQL-only, migration fan-out complexity | Medium tenants on PostgreSQL |
| **E — Package-driven** | [stancl/tenancy](https://tenancyforlaravel.com/) or [spatie/laravel-multitenancy](https://github.com/spatie/laravel-multitenancy) — subdomain identification, bootstrapped tenant context | Laravel-native, community patterns, less bespoke glue | Package constraints, learning curve | Greenfield multi-tenant Laravel with subdomain routing |

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
| --- | --- | --- | --- |
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

## Vendor & Package Policy

When a feature is not worth building in-house, evaluate packages in this order:

1. **Laravel built-ins and first-party packages** — framework features first; official packages (Horizon, Sanctum, Scout, Cashier, Reverb, …) next.
2. **Spatie packages** — [spatie.be/open-source/packages](https://spatie.be/open-source/packages) is the **preferred source for third-party functionality**. Check Spatie's catalog before other vendors.
3. **Frontend Topology first** — when UI is in scope, honor the topology recorded in the PRD (`Laravel-coupled` | `SPA-in-Laravel` | `API + external frontend` — see **Frontend Topology** in `runtime-discovery.md`) before picking panel frameworks.
4. **Authenticated app UI route** — when the product needs an **admin/control panel**, customer dashboard, or portal back-end **in this Laravel repo**, never impose a single stack: **explicitly ask the user** (via AskQuestion) among:
    - **[Filament](https://filamentphp.com/)** — dedicated admin panel; best for internal ops and standard back-office CRUD (also preferred ops admin when topology is **`API + external frontend`**)
    - **[Laravel Starter Kits](https://laravel.com/starter-kits)** — first-party app scaffold with auth, dashboard, profile/settings: **Livewire** (Flux UI), **React**, **Vue**, or **Svelte** (Inertia + shadcn variants); best when authenticated UI is the main product surface in this repo
    - **Custom panel** — bespoke Blade/Livewire/Inertia without Filament or starter-kit conventions
      Recommend the best-fit option for the specific case — above all the one **closest to the project mockups** (heavy custom design → custom; standard resource CRUD → Filament; customer app with auth + dashboard in-repo → Starter Kit variant matching the PRD stack). Record the choice in the PRD under `## Technical Architecture` (`Admin panel: Filament | Starter Kit (livewire|react|vue|svelte) | custom`) so downstream skills honor it instead of re-asking. When Filament is chosen, prefer official plugins, then well-maintained community plugins from [filamentphp.com/plugins](https://filamentphp.com/plugins). When a Starter Kit is chosen, scaffold per [starter-kits docs](https://laravel.com/docs/starter-kits) and align to Flux or shadcn per variant — do not mix unrelated UI libraries on top.
5. **Other community vendors** — only when nothing above fits, and with stricter vetting.

Every candidate — **including** Spatie packages and Filament plugins — must pass a maintenance and security check before `composer require`: compatible with the installed PHP/Laravel versions (verify via Boost `Application Info`); actively maintained (recent releases, responsive issue tracker); healthy adoption relative to the niche; no known vulnerabilities (`composer audit` after install); license compatible with the project.

Ownership: **Sebastian** proposes vendor and service integrations; **Matt** owns hands-on delivery; **John** owns architectural fit; **Andrew** vets Laravel-ecosystem fit; **Lars** vets anything touching auth, uploads, or user data; **Aurora** notes cost implications per Budget Sensitivity.

## Local development environment _(Jack / John own)_

**Never impose a local stack by default.** **Jack** presents the options below via **AskQuestion** during inception (downstream skills ask only if the PRD omits the choice). Recommend the best fit for the team, OS, and services the PRD needs — do not default to Sail. Record the choice in the PRD under `## Technical Architecture` → `Local dev` so downstream skills honor it instead of re-imposing Docker.

| Option | When to recommend |
| --- | --- |
| **Laravel Sail (Docker)** | Containerized parity with production, multiple services (MySQL, Redis, Mailpit, MinIO), reproducible onboarding for mixed OS teams |
| **Laravel Herd** | macOS/Windows, native PHP/nginx, no Docker overhead — see [herd.laravel.com](https://herd.laravel.com/) |
| **Not defined yet** | Brownfield, unknown team setup, or defer local-stack scaffolding until implementation bootstrap |
| **Other** | User names a specific alternative (Valet, WSL + native PHP, existing team stack, …) |

After the choice: **Sail** — `composer require laravel/sail --dev` + `php artisan sail:install`; document `sail up` / `sail artisan …` in README ([Sail docs](https://laravel.com/docs/sail)). **Herd** — document Herd setup in README; use `*.test` domains where helpful. **Not defined yet** — README documents generic `php artisan` workflow only; **do not** add Sail/Herd install tasks until the user decides. **Other** — document the named stack; no Sail/Herd scaffolding unless chosen later.

**Local URLs** _(optional second AskQuestion when multi-tenant, OAuth, or cookie domains matter)_ — besides `localhost`, `*.test`, and `/etc/hosts`, Jack may propose **[127001.it](https://127001.it/)** wildcard DNS (`*.127001.it` → `127.0.0.1`) for shareable dev URLs without hosts-file edits (e.g. `APP_URL=http://myapp.127001.it`).
