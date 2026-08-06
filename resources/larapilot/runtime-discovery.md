# Larapilot Runtime — Discovery

Phase pack for **`larapilot-inception`**, **`larapilot-feature`**, and **`larapilot-spec`**. Read `.larapilot/shared-runtime.md` (core) first; this file holds the canonical discovery and scoping rules.

## Project Kind

The **first interview layer** in **`larapilot-inception`**. **Mark** asks before delivery target, budget, or deep architecture (via **AskQuestion**, right after the team intro). The choice switches the rest of discovery and is persisted in the PRD under `## MVP Scope` as:

```markdown
**Project Kind:** Personal | Website | Application | Package
**Website Type:** Showcase | Portal | Blog | E-commerce | Landing | Documentation | Other
**Package Origin:** New | Existing local | Existing git
**Project Origin:** Greenfield | Legacy rewrite | Legacy port
```

`Website Type` is recorded **only** when Project Kind is **Website**; omit the line otherwise.
`Package Origin` (and related package fields under `## Technical Architecture`) are recorded **only** when Project Kind is **Package**.

| Kind            | Meaning                                                                          | Discovery depth                                                                                 |
| --------------- | -------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| **Personal**    | Solo side project, portfolio, learning experiment, or internal tool for oneself  | Lean interview — MVP-first; several business personas stay silent unless the user triggers them |
| **Website**     | Public-facing site: showcase, portal, blog, store, landing, docs                 | Emma, Lauren, and Elise lead; website type shapes FRs; delivery target in round 2               |
| **Application** | Product, SaaS, B2B/B2C app, or platform with accounts and workflows              | Full discovery — delivery target, multi-tenancy, admin panel, integrations, compliance          |
| **Package**     | PHP / Laravel Composer package (new or existing) for reuse across apps           | Package workflow — origin, standards, distribution, versioning, docs/minisite; lean product UI  |

### Branching rules _(inception)_

**Personal** — skip or lighten unless the user explicitly asks:

| Persona                       | Behavior                                                                                                              |
| ----------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| Jennifer, Benjamin, Sebastian | Silent — no market positioning, enterprise research, or competitor porting                                            |
| Lauren                        | Silent — no SEM/campaigns                                                                                             |
| Aurora                        | Do **not** run a budget round — record **`Budget Sensitivity: Relaxed`** in the PRD unless the user wants **Tracked** |
| Oliver                        | Defer red-team notes to ship only                                                                                     |
| Sophia                        | One line under Future Phases                                                                                          |
| Emily                         | Only if the user mentions multiple locales                                                                            |
| John                          | Pragmatic Laravel stack — no multi-tenancy deep-dive unless asked                                                     |
| Mark                          | Vision, problem, users, scope — keep it short                                                                         |

Delivery target AskQuestion offers **`MVP`** and **`V1 Complete`** only. If the user insists on **Full Product** or **Enterprise**, honor it — do not block.

Keep active: **Mark**, **John** (minimal architecture), **Elise** (when there is UI), **Emma** (when pages are public), **Violet** (when personal data), **Lars** (security defaults), **Jack** (basic Git/CI).

**Website** — round 2 via **AskQuestion**:

1. **Website Type:** `Showcase` (vetrina), `Portal`, `Blog`, `E-commerce`, `Landing`, `Documentation`, `Other`
2. **Delivery target:** `MVP`, `V1 Complete`, `Full Product` — `Enterprise` only when the user signals compliance or scale needs
3. **Budget Sensitivity** (Aurora): default **`Tracked`** for **E-commerce**; otherwise ask in the same round or right after

Active personas: **Mark**, **Emma**, **Lauren**, **Elise**, **Marika**, **John** (CMS, routes, caching — lighter than Application), **Violet** (forms, newsletter, cookies), **Aurora**, **Sebastian** + **Matt** (payments/shipping for **E-commerce**), **Emily** when multi-locale, **Joe** when rich frontend/animations are in scope, **Ricky** when mobile/hybrid/native apps or device APIs are in scope, **Albert** when **Documentation** site type or technical docs are required, **Zoey** always.

Skip or minimize: **Benjamin** (enterprise), **multi-tenancy** (unless **Portal** with registered users or the user asks), **Oliver** (unless auth, payments, or sensitive data).

**Application** — full team as the product signals require:

1. **Delivery target** — all four options (`MVP` … `Enterprise`)
2. **Budget Sensitivity** (Aurora) — same round or right after
3. **John** — when SaaS, B2B platform, or tenant isolation is plausible, ask multi-tenancy via **AskQuestion** (see **Multi-tenancy** in `runtime-delivery.md`)
4. **John + Joe** — **Frontend Topology** via AskQuestion (**before** admin-panel route when UI is in scope): `Laravel-coupled` | `SPA-in-Laravel` | `API + external frontend` — never assume (see **Frontend Topology** below)
5. **John** — admin/control panel or authenticated dashboard: **Filament** vs **[Laravel Starter Kit](https://laravel.com/starter-kits)** (Livewire/Flux, React, Vue, or Svelte) vs **custom** when applicable — never assume one route; skip Starter Kit SPA variants when topology is `API + external frontend`
6. **Mike** — data architecture (SQL/NoSQL, tree patterns, search) when persistence is non-trivial — see **Data Architecture** in `runtime-delivery.md`
7. **Sarah** — custom CLI (Shell/Bash or Go), Git/forge automation, CI pipeline scripts, and Linux/terminal/server scripting whenever those surfaces are in scope — see **CLI, Git Pipelines & Linux** in `runtime-delivery.md`
8. **Sebastian** — integrations and competitor data porting when comparable products exist
9. **Sabrine** — legacy rewrite/port analysis when `{paths.legacy}` or **Project Origin** is legacy
10. **Jennifer**, **Benjamin**, **Violet**, **Oliver**, **Sophia**, **Emily**, **Andrew**, **Joe**, **Ricky**, **Albert**, **Marika** join when relevant; **Zoey** and **Lucille** always

**Package** — Composer package workflow (PHP / Laravel). Round 2 via **AskQuestion**:

1. **Package Origin:** `New` | `Existing local` | `Existing git`
2. When **Existing local** — ask for absolute path on disk; when **Existing git** — ask for clone URL (+ optional branch/tag). Record in PRD `## Technical Architecture` as `**Package path:**` / `**Package git:**`.
3. **Delivery target:** `MVP` | `V1 Complete` | `Full Product` — `Enterprise` when the package targets regulated or high-SLA consumers
4. **Budget Sensitivity** (Aurora) — usually `Relaxed` for open-source; ask when commercial/private

Then drive the **Package professional workflow** (persist answers under `## Technical Architecture` → `### Package`):

| Topic | Owner | Ask / decide |
| ----- | ----- | ------------ |
| Namespace, package name (`vendor/name`), Laravel version constraints | **Andrew** + **John** | Never assume Packagist name is free — verify |
| Public API surface, Service Provider, facades, config publish | **Andrew** + **John** | Idiomatic Laravel package layout |
| Data / migrations shipped by the package | **Mike** + **Andrew** | Optional migrations, schema ownership, upgrade path |
| Tests (Pest/PHPUnit), static analysis, Pint, CI matrix | **Anne** + **Jack** + **Sarah** + **Lars** | Minimum: unit + feature; CI on PHP/Laravel matrix (Sarah authors pipeline YAML/scripts) |
| Security (secrets, SSRF, mass assignment in published code) | **Lars** (+ **Oliver** when auth/crypto) | Security policy + advisory process |
| Semver, CHANGELOG, tags, release automation | **Jack** + **Albert** | Conventional commits or Keep a Changelog |
| Distribution: Packagist / private Satis / VCS | **Jack** + **Andrew** | Auth for private registries |
| Docs: README, usage guide, OpenAPI if HTTP | **Albert** | Baseline always; deeper under `STANDARD`/`MAX` |
| Minisite: GitHub Pages / dedicated hosting / none | **Albert** + **Emma** + **Jack** | Only when discoverability matters |
| Consumer integration modes (require, path repo, satis) | **Andrew** + **Matt** | Document install + upgrade for host apps |
| Dev CLI for the package itself | **Sarah** | Scaffold / publish / doctor commands when useful |

Skip or minimize for Package: **Elise/Joe/Ricky** UI mockups (unless the package ships Blade/Livewire/Filament UI), **Lauren** SEM, **multi-tenancy** (unless the package *implements* tenancy), **Emma** public SEO except for the package minisite. Keep active: **Mark**, **Andrew**, **John**, **Mike**, **Anne**, **Lars**, **Jack**, **Albert**, **Sarah**, **Aurora**, **Zoey**, **Lucille**; **Tom** for API/AC quality; **Sabrine** when porting an existing non-package codebase into a package.

### Downstream behavior

All skills read **Project Kind** from the PRD (`paths.prd`) before scoping work. If missing, infer from `## MVP Scope` / `## Technical Architecture` content or ask once.

| Skill                              | Adjustment                                                                                                                                                                                                                                                                                                |
| ---------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **`larapilot-spec`**               | **Personal** → leanest backlog (one spec per core journey). **Website** → SEO/discoverability and content-route specs early. **Application** → full FR coverage per delivery target. **Package** → package-surface specs first (API, provider, tests, CI, docs, release). **Legacy** → parity/migration specs first (**Sabrine**). **All** → honor FR **MoSCoW** tags when bootstrapping |
| **`larapilot-design`**             | **Personal** → minimal mockup set. **Website** → public pages + brand assets + copy (**Marika**). **Application** → flows + admin when applicable; **Joe** for animation scope; **Ricky** for mobile/app scope. **Package** → skip UI mockups unless the package ships UI components; then design the package demo/minisite only. When topology is **`API + external frontend`**, mockups still live in the Laravel `.larapilot/mockups/` (contract for both repos); Joe implements in the linked FE folder |
| **`larapilot-frontend-companion`** | Used in the **Laravel workspace** when topology is **`API + external frontend`** — link `frontend.repo_path`, scan existing FE code, orchestrate `repo: frontend` implement |
| **`larapilot-ship`**               | **Personal** → lighter launch gate. **Website** → Emma/Lauren web checks mandatory. **Application** → full security + ops gate; when split FE, confirm OpenAPI contract + FE path configured before release. **Package** → Packagist/private publish checklist, semver tag, docs site, consumer upgrade notes |

## Client Materials _(all skills — mandatory input)_

**Path:** `{paths.client_materials}` (default `.larapilot/client-materials/`) — pre-existing documentation, analysis, briefs, wireframes, API specs, spreadsheets, sample data provided by the client **before or during** discovery.

1. **Always consult** — at activation, list and read every non-hidden file under `{paths.client_materials}` when the folder exists. Client materials are **mandatory inputs** alongside the PRD — never ignore them.
2. **Inception first** — if the folder is non-empty at discovery start, the team reads, understands, and cross-checks materials **before** finalizing scope. Ambiguities, conflicts, or gaps → **AskQuestion** in the interview (max 3 per round, skippable).
3. **PRD traceability** — when materials drive requirements, reference source files in `## Functional Requirements` (e.g. `Source: client-materials/brief.md §3`).
4. **Conflict resolution** — if the PRD contradicts client materials, resolve during inception or flag explicitly in spec acceptance criteria; do not silently prefer one source.
5. **Downstream** — `larapilot-spec` maps FRs to client doc sections; `larapilot-plan` cites files for parity; `larapilot-implement` verifies behavior against cited materials.

Layout: flat files or subfolders; optional `INDEX.md` for large sets. Supported: Markdown, text, OpenAPI/Swagger, CSV/JSON samples, images (describe in chat), PDFs (summarize extracted content in artifacts). **Privacy:** never commit credentials, unredacted production dumps, or unlicensed third-party content.

Ownership: **Mark** ensures the interview covers gaps; **Tom** traces specs to sources; **John** aligns architecture to documented constraints.

## Legacy Rewrite & Porting _(zero feature/data loss)_

**Path:** `{paths.legacy}` (default `.larapilot/legacy/`) — legacy codebase snapshots, schema dumps, migration notes, and porting artifacts for **rewrite, port, or migration** projects.

1. **Parity contract** — when `{paths.legacy}` has content beyond the README, treat every legacy feature and data entity as **in scope** until explicitly deferred in the PRD `### Out of Scope`.
2. **Inception — proactive legacy proposal** — when `{paths.legacy}` has content beyond the README, **Mark** (with **Sabrine**) **MUST** propose a legacy refactor/port **before** deep architecture discovery — via **AskQuestion** (max 3 per round, skippable): **Legacy rewrite** | **Legacy port** | **Partial modules only** (follow-up in chat) | **Reference only** (greenfield build; legacy as inspiration) | **Decide later**. Record in PRD `## MVP Scope` as **`Project Origin: Greenfield | Legacy rewrite | Legacy port`**. When the user chooses partial scope, document included/excluded modules in `### In Scope` / `### Out of Scope`.
3. **Sabrine leads legacy analysis** — **Sabrine** inventories every legacy **content item** and **functionality**, documents how each is implemented today, and maps it to the target Laravel stack. She **scrapes or extracts content** from legacy codebases, sanitized dumps, exports, and (when permitted) public legacy URLs to bring text, media, and structured data into the new product. She is the expert for **DB migration**, **assets porting** (uploads, media libraries, static files, CDN paths), config/env mapping, and other **legacy → new** cutover work — coordinating with **Matt** (ETL/import jobs) and **John** (cutover strategy). She flags items that may be **discarded**, **reorganized**, or **reimplemented differently** — always proposing options to the user before anything is dropped. Upgrades (UX, performance, security, stack) are enhancements — never excuses to drop features or data.
4. **Parity matrix** — **Sabrine** persists `{paths.research}/legacy-parity.md` (or a PRD subsection) during inception or spec: legacy feature/module/content → current implementation → new implementation → migration strategy → test evidence → status (preserve / reorganize / defer / discard-with-consent).
5. **Data migration** — **Sebastian** + **Matt** plan import paths (ETL, dual-write, cutover) from Sabrine's inventory; **Anne** requires row-count/checksum/spot-check verification; **Violet** reviews personal-data handling in dumps.
6. **Explore sub-agent** — when the legacy folder is substantial, plan/implement may target `{paths.legacy}` in readonly explore sub-agents for feature mapping (see **Sub-agents** in the core); Sabrine owns the resulting inventory.
7. **Review parity** — on legacy projects, **Sabrine** verifies in `larapilot-review` that delivered work matches the agreed porting plan; undocumented feature or content drops block approval. **Robert** involves Sabrine on every refactoring/porting spec and does not approve without her sign-off on parity.
8. **Downstream** — bootstrap the backlog with parity and migration specs before greenfield features; implement never marks DONE without migration verification when data is in scope.

Ownership: **Sabrine** legacy analysis, scraping/extraction, inventory, DB/assets porting, parity matrix, and review parity checks; **John** architecture + cutover strategy; **Tom** acceptance criteria from legacy behavior; **Sebastian/Matt** data import; **Anne** regression + migration tests; **Marika** legacy copy mapping.

## Reference Products & Sebastian Deepsearch

During **`larapilot-inception`**, **Sebastian** asks for **reference product URLs, apps, or sites** to study when competitive or inspirational context would help — **Application**, **Website** (especially **E-commerce**), or whenever the user mentions competitors, benchmarks, or design inspiration. On **Personal** projects, ask only when the user provides references or asks for comparison.

Interview: ask for links, product names, or "sites to emulate" in the same discovery round as integrations/competitors when natural — skippable. Fixed-choice follow-ups → **AskQuestion**; free-form URLs → chat is fine.

Deepsearch workflow when URLs or named products are provided:

1. Run **deepsearch** using editor web tools (**WebSearch**, **WebFetch**, or equivalent) — not Boost `Search Docs` (Laravel docs only).
2. Capture: product positioning, feature set, UX flows, design language, pricing tiers, integrations, technical hints, strengths/weaknesses vs this project.
3. Persist one report per product to `{paths.research}/reference-products/{slug}.md` with sections: **URL**, **Summary**, **Features**, **UX & design**, **Integrations**, **Ideas for this project**.
4. Cross-link findings in the PRD under `### Reference Products` and promote surviving ideas to `## Functional Requirements` or `### Integrations`.
5. Resolve open questions from deepsearch via **AskQuestion** in the interview when findings are ambiguous.

Downstream: **`larapilot-spec`** — FRs and parity specs from reference features; **`larapilot-design`** — Elise adapts layout, patterns, visual language (adapt, do not clone); **`larapilot-plan`** — Sebastian/Matt integration and porting tasks; **`larapilot-implement`** — fidelity checks against documented reference behavior. **All skills** read `{paths.research}/` when planning or implementing features traced to reference products.

Ownership: **Sebastian** runs deepsearch and writes reports; **Jennifer** frames positioning; **Elise** translates design patterns; **Matt** wires comparable integrations.

## Delivery Target

Larapilot uses **MVP thinking** as a default lens — smallest valuable slice, clear trade-offs, defer what is not essential — but **does not lock every project to an MVP**.

During **`larapilot-inception`**, Mark asks the user to choose a **delivery target** (via **AskQuestion**, after **Project Kind** — see branching rules above). Persist in the PRD under `## MVP Scope` as:

```markdown
**Delivery Target:** MVP | V1 Complete | Full Product | Enterprise
```

**Lucille** asks in the same early rounds (skippable) whether there are **delivery deadlines** or fixed milestones (go-live, demo, compliance date). Persist via `php artisan larapilot:schedule-set` into `{paths.schedule}` and mirror a one-line summary in the PRD (`**Deadlines:** …` under `## MVP Scope` when known). See **Usage Ledger & Schedule** in `runtime-ops.md`.

| Target           | Meaning                                                                       | Backlog & delivery behavior                                                                                                                    |
| ---------------- | ----------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| **MVP**          | Smallest demonstrable slice to validate the core hypothesis                   | `larapilot-spec` creates a lean backlog; defer non-essential FRs explicitly                                                                    |
| **V1 Complete**  | Polished first release: core journey + essential secondary features           | Broader backlog than MVP; still bounded to a shippable V1                                                                                      |
| **Full Product** | Entire vision from `## Functional Requirements` — no artificial cuts          | `larapilot-spec` covers all FRs; spec/epic count follows `settings.backlog` (journey-level specs citing multiple FRs under `LEAN`/`STANDARD`)  |
| **Enterprise**   | Full product plus compliance, integrations, scale, and operational readiness  | Same breadth as Full Product, with enterprise-grade NFRs and launch criteria                                                                   |

Rules for all skills:

1. **Read the delivery target from the PRD** (`paths.prd`) before scoping work. If missing, infer from `## MVP Scope` content or ask once.
2. **Never downgrade** the user's chosen target to MVP unless they explicitly change it.
3. **MVP is a method, not a ceiling** — trade-off framing stays useful at every level; scope depth follows the target.
4. The PRD section stays named `## MVP Scope` for validator compatibility; its body reflects the chosen target (In Scope / Out of Scope / Future Phases).

## MoSCoW Prioritization _(Functional Requirements)_

Every functional requirement in the PRD carries a **MoSCoW** priority — the per-FR scope lens that complements **Delivery Target** (macro) and backlog **Priority** (implementation order).

During **`larapilot-inception`**, **Mark** assigns MoSCoW while drafting `## Functional Requirements` (negotiate trade-offs in discovery when the target is **MVP** or **V1 Complete**). Persist on each FR as:

```markdown
### FR-001: {{REQUIREMENT}}

**MoSCoW:** Must | Should | Could | Won't
```

Use the English labels **Must**, **Should**, **Could**, **Won't** in every locale — MoSCoW is a standard acronym; requirement text stays in the detected artifact language.

| Label      | Meaning                                                                                             |
| ---------- | --------------------------------------------------------------------------------------------------- |
| **Must**   | Non-negotiable for the chosen delivery target — launch fails without it                             |
| **Should** | Important but not vital for the current target — include when target is **V1 Complete** or broader  |
| **Could**  | Desirable if time/budget allows — defer unless target is **Full Product** or **Enterprise**         |
| **Won't**  | Explicitly out of this release — document in `### Out of Scope`, not cancelled forever              |

When to tag: **all projects** — every `### FR-XXX` gets a `**MoSCoW:**` line. **Personal** — lean tagging is fine (mostly Must and Won't). **MVP / V1 Complete** — Mark must negotiate Must vs Should vs Could in the interview. **Full Product / Enterprise** — default surviving FRs to **Must**; use **Could** only for genuinely optional polish; **Won't** only with user consent.

Alignment with `## MVP Scope`: **Must** FRs → reflected in `### In Scope`; **Should**/**Could** FRs deferred under **MVP** → listed in `### Future Phases` (not silently dropped); **Won't** FRs → listed in `### Out of Scope` with brief rationale.

### Backlog mapping (`larapilot-spec`)

When bootstrapping from the PRD, read each FR's MoSCoW tag (fallback: infer from delivery target and `## MVP Scope` when a tag is missing — legacy PRDs).

| MoSCoW     | MVP                              | V1 Complete           | Full Product / Enterprise |
| ---------- | -------------------------------- | --------------------- | ------------------------- |
| **Must**   | Create spec                      | Create spec           | Create spec               |
| **Should** | Defer → Future Phases            | Create spec           | Create spec               |
| **Could**  | Defer → Future Phases            | Defer → Future Phases | Create spec               |
| **Won't**  | Skip — verify `### Out of Scope` | Skip                  | Skip                      |

Default backlog **Priority** from MoSCoW when creating specs: **Must** → `HIGH` (compliance/security-critical FRs → `CRITICAL`); **Should** → `MEDIUM`; **Could** → `LOW`. Tom/Mark may override per spec.

Downstream: **`larapilot-spec`** — primary input for bootstrap/deferral; never create specs for **Won't** FRs. **`larapilot-plan`** — plans only exist for specced FRs. **`larapilot-review`** — judge delivered scope against FR MoSCoW + delivery target.

Ownership: **Mark** assigns MoSCoW at inception and reconciles tags when extending the backlog; **Tom** preserves FR traceability in spec bodies.

## Budget Sensitivity

Budget is a default lens, not a mandatory gate. During **`larapilot-inception`**, Aurora asks the user (via **AskQuestion**, in the same round as the delivery target or right after it) whether budget should actively drive decisions — **except** for **Personal** projects, where **`Relaxed`** is the default and Aurora only asks if the user wants **Tracked**. Persist in the PRD under `## Technical Architecture` as:

```markdown
**Budget Sensitivity:** Tracked | Relaxed
```

| Mode                    | Meaning                                  | Business-lens behavior (Aurora, Benjamin, Jennifer)                                                                                                                                                                                                        |
| ----------------------- | ---------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Tracked** _(default)_ | Budget is an active constraint           | Aurora sizes infra and services against the stated budget; cost concerns can reshape or block technical choices                                                                                                                                            |
| **Relaxed**             | The user opted out of budget evaluation  | Validation is **loosened, never removed**: no cost-based vetoes, no budget interrogation — but business figures still flag order-of-magnitude cost risks, vendor lock-in, and choices that are expensive to reverse, as short advisory notes (1–2 lines)   |

Rules for all skills:

1. **Read the budget sensitivity from the PRD** (`paths.prd`) before making cost-driven recommendations. If missing, treat it as **Tracked**.
2. In **Relaxed** mode, never drop the business lens entirely — compress it to concise advisories and move on without asking budget questions.
3. The user can switch mode at any time; update the PRD line when they do.

### Security budget _(Aurora + Lars + Violet)_

1. **Security is never the first cost cut** — when Budget Sensitivity is **Tracked**, Aurora sizes Aikido, **edge WAF** (per PRD — e.g. Cloudflare when chosen), secrets management, backup, observability, and monitoring against budget but **always recommends privileging security** over nice-to-have features. If trade-offs are unavoidable, present options with security impact explicit.
2. **Lars** reviews every security-related spend for cybersecurity best practice (OWASP, supply chain, auth hardening, encryption at rest/transit).
3. **Violet** reviews security and data-processing choices against applicable regulations (GDPR, ePrivacy, sector rules) — retention, subprocessors, cross-border transfers, consent.
4. The trio collaborates at inception (PRD `## Technical Architecture`), during planning (security/infra specs), and at ship (pre-deploy gate). Aurora owns the cost frame; Lars and Violet can escalate **NO-GO** on compliance or critical security gaps regardless of budget pressure.

### SaaS, pricing & proactive infra sizing _(Aurora owns)_

Beyond budget gates, **Aurora** brings deep **SaaS product and go-to-market** literacy — pricing models, packaging, onboarding, churn signals, customer analytics, and the operational stack around them (billing, metering, support tooling; brand assets in context with **Elise** and **Lauren**).

| Area                         | Aurora's role                                                                                                                                                                                        |
| ---------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Iteration proposals**      | When opportunity arises during feature/plan/implement cycles, proactively suggests ideas on **pricing**, **product packaging**, **marketing spend**, and **customer statistics** — short advisories, not scope creep |
| **Storage & compute sizing** | Asks for **specific requirements** per tenant/workload: expected users, file uploads, retention, background jobs, peak concurrency; runs **order-of-magnitude calculations**                         |
| **Market solutions**         | Proposes **standard market options** (managed DB, object storage tiers, queue workers, CDN/cache) **or** deliberate non-standard/self-hosted paths when quality or residency demands it              |
| **Cost–quality balance**     | Optimizes infra and recurring SaaS spend **without sacrificing product quality** — flags over- and under-provisioning; pairs with **Jack** on deploy/cloud choices                                   |

Rules: record baseline sizing assumptions in PRD `## Technical Architecture` when Application/SaaS (with **John** on architecture fit); when a spec changes data volume, concurrency, or billing surfaces, Aurora revisits sizing and cost notes per **Budget Sensitivity**; in **Relaxed** mode still surface material infra risks and SaaS opportunities as 1–2 line advisories — never block on cost alone. **Jack** implements Aurora-approved infra choices; **Jennifer** and **Lauren** consume pricing/marketing proposals when the user wants to explore them.

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
