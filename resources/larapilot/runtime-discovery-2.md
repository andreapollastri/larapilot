Part of this runtime pack. The pack file is an index. Read with the editor file-read tool, never `cat`.

## Project Kind

The **first interview layer** in **`larapilot-inception`**. **Mark** asks before delivery target, budget, or deep architecture (via **AskQuestion**, right after the team intro). The choice switches the rest of discovery and is persisted in the PRD under `## MVP Scope` as:

```markdown
**Project Kind:** Personal | Website | Application | Package
**Website Type:** Showcase | Portal | Blog | E-commerce | Landing | Documentation | Other
**Package Origin:** New | Existing local | Existing git
**Project Origin:** Greenfield | Legacy rewrite | Legacy port | Adopted (existing codebase)
```

`Project Origin: Adopted (existing codebase)` is written by **`larapilot-adopt`** when a running Laravel app is brought under Larapilot with a reverse-engineered PRD — there is no legacy parity contract and Sabrine stays silent; downstream skills treat it like Greenfield for scoping (the FRs simply describe already-shipped work).

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

### Core rounds _(always asked, whatever the branch)_

Branching changes **how deep** discovery goes, never **whether** these four are asked. A legacy proposal, a client brief, an adopted codebase, or an impatient user changes *when* they are asked — not *whether*:

| # | Round | Owner | Persisted as |
| - | ----- | ----- | ------------ |
| 1 | **Project Kind** | Mark | `**Project Kind:**` in `## MVP Scope` |
| 2 | **Delivery Target** — how far this has to go | Mark | `**Delivery Target:**` in `## MVP Scope` |
| 3 | **Business Model** — how it makes money | Mark + Aurora | `**Business Model:**` in `## MVP Scope` |
| 4 | **Operations & support** — who runs the server, how fast support answers | Jack + Sophia | `**Server Management:**`, `**Ops Owner:**`, `**Support Window:**` in `## Technical Architecture` |

Before writing the PRD, check all four. If one is still missing, ask it then. If the user skips it, write **`Not decided`** — never a guess — and say once, in a line, what that costs downstream:

- No **Delivery Target** → the backlog is scoped as V1 Complete.
- No **Business Model** → Economics falls back to reading the PRD for keywords and may price a subscription product as a one-off.
- No **Server Management** / **Ops Owner** → the maintenance retainer is priced as application-only, with nothing for patching, backups, or uptime.
- No **Support Window** → the retainer assumes business hours, best effort.

### Downstream behavior

All skills read **Project Kind** from the PRD (`paths.prd`) before scoping work. If missing, infer from `## MVP Scope` / `## Technical Architecture` content or ask once.

| Skill                              | Adjustment                                                                                                                                                                                                                                                                                                |
| ---------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **`larapilot-spec`**               | **Personal** → leanest backlog (one spec per core journey). **Website** → SEO/discoverability and content-route specs early. **Application** → full FR coverage per delivery target. **Package** → package-surface specs first (API, provider, tests, CI, docs, release). **Legacy** → parity/migration specs first (**Sabrine**). **All** → honor FR **MoSCoW** tags when bootstrapping |
| **`larapilot-design`**             | **Personal** → minimal mockup set. **Website** → public pages + brand assets + copy (**Marika**). **Application** → flows + admin when applicable; **Joe** for animation scope; **Ricky** for mobile/app scope. **Package** → skip UI mockups unless the package ships UI components; then design the package demo/minisite only. When topology is **`API + external frontend`**, mockups still live in the Laravel `.larapilot/mockups/` (contract for both repos); Joe implements in the linked FE folder |
| **`larapilot-frontend-companion`** | Used in the **Laravel workspace** when topology is **`API + external frontend`** — link `frontend.repo_path`, scan existing FE code, orchestrate `repo: frontend` implement |
| **`larapilot-ship`**               | **Personal** → lighter launch gate. **Website** → Emma/Lauren web checks mandatory. **Application** → full security + ops gate; when split FE, confirm OpenAPI contract + FE path configured before release. **Package** → Packagist/private publish checklist, semver tag, docs site, consumer upgrade notes |

