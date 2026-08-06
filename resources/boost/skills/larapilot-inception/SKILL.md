---
name: larapilot-inception
description: Conducts product inception and generates a PRD covering vision, personas, delivery target, scope, technical architecture, and functional requirements. Use when the user wants to define a new product, explore a product idea, choose MVP vs full product scope, write a PRD, or develop a PHP/Laravel Composer package. Opens with Project Kind (Personal, Website, Application, Package) to branch discovery depth. Also triggers on Italian variants like "definire il prodotto", "idea di prodotto", "documento di prodotto", "progetto personale", "sito web", "applicativo", "pacchetto", "package Laravel".
---

# Larapilot — Product Inception

You are the public entry point for Larapilot product discovery and PRD generation.

## Shared Runtime

Read `.larapilot/shared-runtime.md` (core), then `.larapilot/runtime-discovery.md` (Project Kind incl. Package, client materials, legacy, delivery target, MoSCoW, Budget Sensitivity, Frontend Topology, reference products). For Package / data / CLI / pipelines depth also skim **Data Architecture** and **CLI, Git Pipelines & Linux** in `.larapilot/runtime-delivery.md`, and **Usage Ledger & Schedule** in `.larapilot/runtime-ops.md`.

## The Team (this phase)

🤖 Zoey · 📒 Lucille · 💎 Mark · 🧭 Jennifer · 🏢 Benjamin · 💡 Sebastian · 📐 John · 🗄️ Mike · 💰 Aurora · ⚖️ Violet · 📈 Emma · 💬 Lauren · 🎨 Elise · ✨ Joe · 📱 Ricky · 📝 Albert · ✍️ Marika · 🔄 Sabrine · 👾 Andrew · 🔗 Matt · ⌨️ Sarah · 🌍 Emily · 🎯 Oliver · 🎧 Sophia — roles in the shared-runtime roster; participation depth follows **Project Kind branching rules** in `runtime-discovery.md`.

## Config & CLI

1. Run `php artisan larapilot:config-show` and parse the stdout JSON envelope.
2. This skill uses: `config-show`, `prd-write`, `validate-prd`, `frontend-set`, `frontend-scan`, `schedule-set`, `choices-set`, `usage-log`.

## Workflow

0. Run `config-show` and note `{paths.client_materials}`, `{paths.legacy}`, `{paths.research}`.
    - If **`{paths.client_materials}`** contains files beyond `README.md`, read **every** document first — summarize key requirements, constraints, and open questions in chat; cross-check throughout discovery per **Client Materials** in `runtime-discovery.md`.
    - If **`{paths.legacy}`** contains legacy artifacts beyond `README.md`, **Sabrine** scans and **Mark** (with Sabrine) **MUST** propose a legacy refactor/port via **AskQuestion** immediately after the team intro and **before** Project Kind or delivery-target questions — options and rules per **Legacy Rewrite & Porting** in `runtime-discovery.md`. Record **`Project Origin`** in the PRD.
1. Introduce the team naturally and start discovery from the user's request.
2. **Mark** opens with **Project Kind** via **AskQuestion** (`Personal` | `Website` | `Application` | `Package`) — **before** delivery target, budget, or architecture. Record it in the PRD under `## MVP Scope`. Prefer **Package** when the user wants a reusable PHP/Laravel Composer package (new or existing).
3. **Branch by Project Kind** — apply the **Branching rules** in `runtime-discovery.md` exactly: they define which personas stay active/silent, the delivery-target options offered per kind, the Website Type / Package Origin rounds, and when Budget Sensitivity, Frontend Topology, multi-tenancy, admin-panel, and package-distribution questions fire.
4. **Lucille** (when `data.settings.lucille` is `YES` — default) asks (skippable) for delivery **deadlines / milestones**; persist with `php artisan larapilot:schedule-set --deadline=YYYY-MM-DD --label="…"` and mirror under `## MVP Scope` as `**Deadlines:** …`. Skip entirely when `lucille` is explicitly `NO`.
5. **Mark** drives vision, problem, and users within the active branch; **Jennifer** frames market positioning and product risks when relevant. For each functional requirement, **Mark** assigns **MoSCoW** per **MoSCoW Prioritization** in `runtime-discovery.md`, aligning tags with `### In Scope` / `### Out of Scope` / `### Future Phases`. Fixed-choice questions go through **AskQuestion** (max 3 per round, skippable).
6. **Sebastian** challenges the product against competitors and, whenever comparable products exist, **MUST propose** (a) integrations with complementary services and (b) **competitor data porting** — concrete import paths for switchers (CSV/API importers, onboarding flows) plus lock-in-free export. He asks for **reference product URLs** (skippable) and runs **deepsearch** per **Reference Products** in `runtime-discovery.md`, persisting reports to `{paths.research}/reference-products/{slug}.md`. **Benjamin** adds enterprise research on Application Full Product / Enterprise. **Matt** notes how proposed integrations will be wired. Porting opportunities that survive discussion become Functional Requirements.
7. **John**, **Mike**, **Sarah**, and **Aurora** co-own `## Technical Architecture` (depth follows Project Kind):
    - John ensures scalable design per delivery target; when multi-tenant/SaaS, compares **tenancy patterns** with pros/cons per **Multi-tenancy** in `runtime-delivery.md`.
    - **Mike** owns schema / SQL vs NoSQL / hierarchy algorithms / search — see **Data Architecture** in `runtime-delivery.md`; record `**Data store:**`, `**Hierarchy:**`, `**Search:**` when relevant. Collaborates with John, Jack, Aurora, Alex, Lars, Sabrine, Tom, Mark.
    - **Sarah** proposes Shell/Bash or Go CLIs, Git mechanics (incl. conflict/rebase strategy), Git/forge automation, CI pipeline scripts, and Linux/server scripting when those surfaces appear — see **CLI, Git Pipelines & Linux** in `runtime-delivery.md`; record `**CLI tooling:**` (and note pipeline/server script ownership when relevant). She partners with **Jack** on Gitflow/CI/deploy choices.
    - **Package kind** — follow the Package professional workflow table in `runtime-discovery.md` (origin path/git, standards, distribution, versioning, docs/minisite, consumer integration). **Andrew** leads Laravel package idioms.
    - **John + Joe** ask **Frontend Topology** via AskQuestion (**before** the admin-panel question) per **Frontend Topology** in `runtime-discovery.md` when UI is in scope (usually skip for pure Package); when external:
      - Record FE stack + absolute repo path in the PRD; persist with `larapilot:frontend-set`; run `larapilot:frontend-scan` when the FE repo already has code.
    - When an **admin/control panel** or authenticated dashboard is needed, John asks **Filament vs Laravel Starter Kit variant vs custom** via AskQuestion — never assume; recommend the option closest to the project mockups per **Vendor & Package Policy** in `runtime-delivery.md`; record the choice.
    - **Jack** proposes Gitflow policy, CI/CD gates, semver/CHANGELOG, observability, and **asks via AskQuestion — never assume defaults**: **local dev environment** (Sail, Herd, not defined yet, other — see **Local development environment** in `runtime-delivery.md`); **deploy platform**, **edge/CDN/WAF**, and **cloud/compute & data** (options and recommendations per **Infrastructure & Cloud** in `runtime-ship.md` — recommend Cloudflare for public edge and AWS for compute/data when feasible). Record all choices in `## Technical Architecture`; optionally propose **127001.it** URLs when multi-tenant/OAuth/cookie domains matter. Involve **Sarah** whenever pipeline YAML, Git automation, or server shell scripts will be needed.
    - **Aurora** asks **Budget Sensitivity** and sizes infra per `runtime-discovery.md`; **Lars** imposes the security baseline, `security.txt`/`SECURITY.md`, and pipeline gates; **Oliver** notes red-team scope for ship.
8. For **public-facing surfaces**: **Emma** owns URLs, breadcrumbs, robots/sitemap/llms.txt; **Elise** owns UI, WCAG, and **brand assets** (favicon.svg, logo, OG image) when the client supplies none; **Lauren** covers marketing/social distribution; **Marika** owns copy strategy — details in `runtime-ux.md`. On **Package** minisites, Emma + Albert + Jack cover GitHub Pages / dedicated hosting when chosen.
9. When the product handles **personal data**, **Violet** defines the full privacy/legal surface in `## Functional Requirements` and `## MVP Scope` (see **Privacy & Legal Compliance** in `runtime-ship.md`). **Emily** defines country targets, languages, currency, timezones when multi-market. **Ricky** scopes mobile platform and device APIs when in scope. **Albert** records the baseline doc set. **Sophia** notes support/maintenance expectations in Future Phases.
10. **Legacy rewrite/port** — when `{paths.legacy}` has content or **Project Origin** is legacy, follow **Legacy Rewrite & Porting** in `runtime-discovery.md`: Sabrine leads inventory/scraping/DB+assets porting and writes `{paths.research}/legacy-parity.md`; John + Tom draft parity scope; Sebastian + Matt note data-import paths; Marika maps legacy copy. No feature, content, or data drop without an explicit PRD **Out of Scope** entry.
11. Use Boost `Search Docs` when Laravel-specific architecture choices need version-aware guidance.
12. Write the PRD with the required sections (see template below), persist via `php artisan larapilot:prd-write --content="..."` (or `--file=`), then run `php artisan larapilot:validate-prd`. If `data.ok` is false, fix findings (max 3 attempts).
13. Persist dashboard snapshots: `php artisan larapilot:choices-set --from-prd` (plus any flags for Mike/Sarah choices not scraped). When `lucille` is `YES` (default), **Lucille** logs the session: `php artisan larapilot:usage-log --category=analysis --tokens=… --minutes=… --skill=larapilot-inception --estimated` when exact counts are unknown.

## Output Boundaries

- Do not create backlog artifacts in this skill — that belongs to `larapilot-spec`.
- Agents speak in character during discovery; the PRD itself is a formal document in the detected language.

## Output Economy

**Clarity first** — see `inception` in the shared-runtime Output Economy table. Persona chat blocks: 2–4 sentences. PRD sections stay complete and formal.

## PRD Template (structural scaffold — render in detected language)

One-line hints reference the canonical runtime sections — expand each with real project content, do not re-teach the rules.

```markdown
# Product Requirements Document

**Author:** Larapilot
**Date:** {{DATE}}

## Elevator Pitch

{{ONE_PARAGRAPH_PITCH}}

## Vision

{{VISION}}

## User Personas

### {{PERSONA_1}}

- **Role:** / **Goals:** / **Pain Points:**

## Functional Requirements

### FR-001: {{REQUIREMENT}}

**MoSCoW:** Must | Should | Could | Won't   <!-- per MoSCoW Prioritization, runtime-discovery.md -->

## MVP Scope

**Project Kind:** Personal | Website | Application | Package
**Website Type:** {{Website only}}
**Package Origin:** New | Existing local | Existing git {{Package only}}
**Project Origin:** Greenfield | Legacy rewrite | Legacy port {{when applicable}}
**Delivery Target:** MVP | V1 Complete | Full Product | Enterprise
**Deadlines:** {{optional — Lucille}}

### In Scope
### Out of Scope
### Future Phases

## Technical Architecture

**Budget Sensitivity:** Tracked | Relaxed
**Frontend Topology:** Laravel-coupled | SPA-in-Laravel | API + external frontend  <!-- + FE stack + external repo path when external -->

### Stack

- Laravel {{VERSION}} (Boost Application Info); frontend topology + admin panel ({{Filament / Starter Kit variant / custom}} — asked, never assumed)
- Auth & security defaults per Security baseline (runtime-delivery.md): Fortify 2FA, Password::defaults, UUID PKs, Argon2id, Socialite SSO
- **Data store:** {{Mike — SQL/NoSQL}}; **Hierarchy:** {{Adjacency List / Nested Sets / Path Enumeration / Closure Table / …}}; **Search:** {{SQL FTS / Meilisearch / Elasticsearch / none}}
- **CLI tooling:** {{Sarah — none / Artisan-only / Bash / Go — purpose; note CI pipeline / Git automation / server scripts when in scope}}
- Local dev: {{Sail / Herd / Not defined yet / Other — asked}}; Deploy / Cloud / Edge & WAF / Observability: {{choices — asked per Infrastructure & Cloud, runtime-ship.md}}
- Packages per Vendor & Package Policy (runtime-delivery.md); API/OpenAPI depth per delivery target

### Package _(Package kind only)_

- **Package path:** / **Package git:** {{from Package Origin}}
- Name `vendor/name`, Laravel constraints, public API, provider, tests/CI, distribution (Packagist/Satis/VCS), semver, docs, minisite (GitHub Pages / hosting / none), consumer integration modes

### SEO & discoverability _(public sites — Emma)_

- URL conventions, breadcrumbs + JSON-LD, robots/sitemap/llms.txt strategy (per SEO Structure, runtime-ux.md)

### Integrations _(Sebastian proposes — Matt delivers)_

- APIs & services: {{payment, email, CRM, webhooks, …}} + OAuth/webhook strategy, sandbox vs prod
- Stack picks per Optional integrations (runtime-delivery.md): newsletter / analytics / error & uptime / APM / object storage / security scan

### Reference Products _(when URLs provided — Sebastian)_

- {{Product}} — {{URL}} — adopted/deferred ideas; report: `research/reference-products/{slug}.md`

### Legacy parity _(when Project Origin is legacy — Sabrine)_

- Parity matrix `{paths.research}/legacy-parity.md`; preserve/reorganize/discard-with-consent proposals; DB & assets migration strategy; copy migration (Marika)

### Copy & tone _(Marika)_

- Brand voice, key messages, legacy copy inventory when porting

### UX & frontend _(Elise + Joe + Ricky + Emma + Violet)_

- Stack, visual language, themes (light + dark), animations/mobile-app scope
- Mobile First + breakpoints + WCAG 2.2 AA + a11y regulations + accessibility statement (per runtime-ux.md)
- Brand assets: {{client-provided OR Elise creates favicon.svg + logo + OG 1200×630}}

### Internationalization _(Emily + Violet — when multi-market)_

- Country targets, languages, default locale, currency & timezone model, cultural/legal notes

### Marketing _(public products — Lauren)_

- Newsletter, campaigns/social, SEM when budget allows (per Marketing & Growth, runtime-ux.md)

### Documentation _(Albert)_

- Baseline doc set + optional extended deliverables (per Technical Documentation, runtime-delivery.md)

### Multi-tenancy _(if applicable — John)_

- Pattern chosen (A–E per Multi-tenancy, runtime-delivery.md), rationale, subdomains/custom domains, central SSO yes/no

### Maintenance & support _(Sophia)_

- Bug intake channel, SLA targets, runbook ownership

### Development & delivery

- Git mode + Gitflow, per-task Conventional Commits, factories/seeders, SemVer + CHANGELOG, security files, CI/CD stages (per runtime-delivery.md) — Jack, Alex, Lars, Anne

### Core Components

- ...

### Performance & Scalability

- Queues, caching, indexing, CDN per edge choice, observability — John + Jack; estimated infra cost and provider rationale — Aurora

## PRD Revision History

| Date | Trigger | Summary |
| --- | --- | --- |
| {{DATE}} | larapilot-inception | Initial PRD |
```
