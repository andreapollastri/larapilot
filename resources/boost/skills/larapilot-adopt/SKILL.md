---
name: larapilot-adopt
description: "Reverse-engineers a PRD from an existing Laravel app with no Larapilot PRD. Not for greenfield ideas or a non-Laravel rewrite. Italian: adotta progetto esistente, PRD dal codice."
---

# Larapilot — Adopt (brownfield onboarding)

You bring an **existing, running Laravel project** under Larapilot. The codebase stays exactly as it is — you read it, reconstruct the product intent behind it, and persist a **complete PRD** plus a codebase analysis report. After this skill the normal loop (`/larapilot-spec → plan → implement → review → ship`) works on the project.

## Not this skill

| Situation | Use instead |
| --- | --- |
| Greenfield product / idea, no code yet | `/larapilot-inception` |
| Rewrite or port **away from** a legacy non-Laravel system (parity contract, DB/asset migration) | `/larapilot-inception` + drop snapshots in `.larapilot/legacy/` (Sabrine leads) |
| A PRD already exists, you want to add one capability | `/larapilot-feature` |
| A PRD already exists, product scope shifted | `/larapilot-prd` |

## Context

`php artisan larapilot:context adopt` — with `--session={token}` when this conversation already holds one, `--fresh` after a compaction. Read every file under `data.runtime.read`, none under `loaded`. Settings, paths, `data.project`, `data.dev_docs`, and `data.frontend` come from that envelope: no `config-show`.

The rest is read when its step arrives: `discovery-6.md` and `discovery-7.md` at step 5, before the PRD is written; `discovery-5.md`, `delivery-3.md`, and `ship-1.md` only when the repository leaves the frontend, the data architecture, or the deploy platform open. The catch-up of step 6 is **First-change catch-up** (`dev-docs-catchup.md`).

When `data.settings.decision_log` is `YES`, journal the choices the user makes about adopted scope with `decision-log`, and run `decision-check` before reversing a recorded one (**Decision journal**, Project Settings).

## The Team (this phase)

🤖 Zoey · 💎 Mark · 🔎 Tom · 📐 John · 🗄️ Mike · 👾 Andrew · ⌨️ Sarah · 🚀 Jack · 🔐 Lars · ⚖️ Violet · 📈 Emma · 🎨 Elise · ✨ Joe · 📱 Ricky · ✍️ Marika · 🌍 Emily · 📝 Albert · 📒 Lucille — roles in `core-personas.md`. Participation depth follows the **Project Kind branching rules** (`discovery-2.md`), applied to what the code actually contains.

- **Mark** owns the PRD and product framing; every functional requirement is inferred from shipped behavior.
- **John + Mike + Andrew** map architecture, data model, and Laravel-idiom / package inventory from the code.
- **Tom** turns observed behavior into acceptance-style requirement statements and flags ambiguous / dead code.
- **Sarah** reads CLI commands, scheduler, CI pipelines, and Git/branching setup; **Jack** reads deploy + observability config.
- **Lars** notes the security surface (auth stack, guards, sensitive routes); **Violet** flags personal-data handling already present.
- **Emma / Elise / Joe / Ricky / Marika / Emily** join only when public web surfaces, UI, mobile, copy, or multi-locale support exist in the repo.
- **Sabrine is silent** here — there is no legacy parity contract; the Laravel code *is* the product.

## Config & CLI

1. This skill uses: `context`, `prd-write`, `validate-prd`, `choices-set`, `frontend-set` (only if an external FE repo is discovered), `schedule-set` (only if the user gives deadlines), `usage-log`, `release-import`, `release-add`, `release-list`, `settings-set --release-mode=YES` (when enabling release mode).
2. **Never** create backlog, plan, or spec artifacts here — that is `/larapilot-spec`.

## Preconditions

- Larapilot is installed (`.larapilot/config.yaml` present).
- **No PRD yet** (`data.project.prd` is `false`). If a PRD already exists, stop and route to `/larapilot-feature` or `/larapilot-prd`.
- If `data.paths.legacy` contains artifacts beyond `README.md`, ask (AskQuestion) whether this is really an **adopt** (keep and document the Laravel app) or a **legacy rewrite** (→ `/larapilot-inception`). Do not assume.

## Workflow

### 0. Context load

From the `context` envelope note `{paths.prd}`, `{paths.research}`, `{paths.client_materials}`, `{paths.legacy}`, `data.settings` (honor `effort`, `lucille`, `backlog`). Zoey posts the start **Context estimate** line.

If `{paths.client_materials}` has real documents, read them first — they are stronger evidence of product intent than code comments; reconcile against the code during discovery.

### 1. Codebase discovery (read-only)

Reconstruct the system from the repository. Use Boost tools when available — `Application Info` (Laravel / PHP version, installed packages), `Database Schema` (tables, columns, indexes, relationships), `Search Docs` for version-aware idioms — and fall back to `php artisan about --json`, `php artisan route:list --json`, `php artisan db:show` / `db:table` otherwise.

**Effort gate (Sub-agents):** under `effort: ECO` do all of this inline. Under `STANDARD`/`MAX`, for a large or unfamiliar repo spawn **one read-only `Explore` sub-agent** to map structure (handoff: repo root, "inventory models/routes/jobs/config/packages/tests/CI, no edits, report paths + one-line purpose each"). The parent still writes every artifact.

Inventory, with file-path evidence for each item:

| Area | Look at |
| --- | --- |
| **Stack & topology** | `composer.json`, `package.json`, `vite.config.*`, Livewire / Inertia / Blade-only / Filament / API-only; external FE repo references |
| **Domain model** | `app/Models/*`, `database/migrations/*`, `database/factories/*`, `database/seeders/*` → entities, fields, relationships, enums |
| **Behavior / FRs** | `routes/*.php`, controllers, actions, `app/Livewire/*`, Filament resources, Form Requests, Policies / Gates, `app/Jobs/*`, `app/Events/*` + listeners, `app/Notifications/*`, `app/Mail/*` |
| **Auth & security** | Fortify / Breeze / Jetstream / Sanctum / Passport / Socialite, guards, middleware, roles & permissions packages, 2FA, signed routes |
| **CLI & ops** | `app/Console/Kernel.php` schedule, custom `artisan` commands, queues / horizon config, `.github/workflows` or other CI, deploy config, `config/*` + `.env.example` keys |
| **Integrations** | Third-party SDKs in `composer.json`, HTTP clients, webhooks, payment / mail / storage / search / analytics / error-tracking config |
| **Frontend & UX** | Public routes, sitemap / robots, `resources/views`, `resources/js`, locale files `lang/*`, theme / design system, accessibility affordances |
| **Quality signals** | `tests/*` coverage breadth, Pest/PHPUnit, static analysis (`phpstan`/`larastan`), Pint, observability (Sentry/Flare/Telescope/Pulse), `README` / `docs/` |

### 2. Derive the product model

The team converts the inventory into product terms:

- **Project Kind** (`discovery-2.md`) — infer: `Application` for an app with accounts/workflows; `Package` when the repo is a Composer library (`type: library`, `src/` + provider, no `artisan`); `Website` when it is mostly public content pages; `Personal` only if the user says so. State the inference; confirm in step 3.
- **User Personas** — from guards, role/permission definitions, distinct auth flows, admin panels, API-token consumers. Name each and give Role / Goals / Pain Points inferred from the features they can reach.
- **Functional Requirements** — one `### FR-XXX` per coherent shipped capability (a route group + its controller/job/policy cluster), not one per class. Cite the evidence paths in the FR body. Group into `### In Scope` (in production today), `### Future Phases` (half-built / feature-flagged / TODO-heavy code), `### Out of Scope` (dead or deprecated code you recommend retiring — needs user confirmation).
- **MoSCoW** — everything already running in production is **Must** (it exists and users depend on it). Tag experimental / partial / flagged code **Should** or **Could**. Nothing is **Won't** unless the user wants it removed.
- **Technical Architecture** — John/Mike/Andrew/Sarah/Jack/Lars record the *actual* stack, data store, hierarchy/search patterns, CLI tooling, local-dev method, deploy/edge/cloud, observability, packages, and integrations found in the repo. Where the repo is silent (e.g. no deploy config), mark `**Not recorded in repo — confirm**` rather than guessing.
- **Delivery Target (forward)** — propose from maturity signals (tests + CI + observability + docs): thin → `MVP` hardening; solid core, gaps → `V1 Complete`; broad & mature → `Full Product` / `Enterprise`. Confirm in step 3.

### 3. Confirmation interview (AskQuestion — max 3 per round, skippable)

Ask **only** what the code cannot tell you. Persona intro in chat; options in AskQuestion.

**Round 1 — Product identity (Mark)**

- **Product name & one-line pitch** — free text if unknown from `composer.json` / `README`.
- **Primary goal / who it serves & who pays** — pick from inferred personas or `Other`.
- **Project Kind** — confirm the inference (`Application` | `Website` | `Package` | `Personal`).

**Round 2 — Forward scope (Mark + Tom)**

- **Delivery Target going forward** — `MVP (harden what exists)` | `V1 Complete` | `Full Product` | `Enterprise`.
- **Business Model** — `Client project` | `SaaS subscription` | `E-commerce` | `Licensed package` | `Internal tool` | `Not decided`. An adopted codebase already makes money somehow (or deliberately does not); ask rather than infer. Persist with `choices-set --business-model="…"` — Economics prices the product from it.
- **Half-built areas** — for each ambiguous module: `Keep & finish (Future Phases)` | `Ship as-is (In Scope)` | `Retire (Out of Scope)`.
- **Deadlines?** — skip unless `data.settings.lucille` is `YES` and the user has dates; persist with `larapilot:schedule-set`.

**Round 3 — Gaps the repo left blank (John + Jack, only if unresolved)**

- **Deploy platform / edge / cloud** when no infra config exists — never assume Sail, Cloudflare, or AWS (**Infrastructure & Cloud**, `ship-1.md`).
- **Operations & support** — always ask, even when the repo shows the platform: who manages the server, who is on the hook when it is down, and what support window is promised (`--server-management=`, `--ops-owner=`, `--support-window=`; options in **Operations & Support**, `discovery-8.md`). On an adopted codebase there is already a machine in production and the arrangement around it is usually undocumented — it is also what prices the maintenance retainer in Economics.
- **External frontend repo** if the API has no coupled UI — AskQuestion until path is known; run `larapilot:frontend-set --path=…` (writes `LARAPILOT_FRONTEND_REPO_PATH` in `.env`) + `larapilot:frontend-scan`; a monorepo (`targets.needs_project`) needs the user to name this product's projects (`frontend-set --project=…`), and the user says who builds the frontend (`--mode=driven|handoff`) (**Frontend Topology**, `discovery-5.md`).

**Release mode (brownfield)** — when `release_mode` is `NO`, AskQuestion once whether to enable semver release tracking. When `YES` (or just enabled): run `release-import`, present imported tags, AskQuestion for **current production version** and any **in_progress** release branch; persist with `release-add` / `release-set` per `release.md`.

### 4. Codebase analysis report

Write `{paths.research}/codebase-analysis.md` (create parent dirs). This is the evidence base the PRD cites — keep it factual and path-anchored:

```markdown
# Codebase Analysis — {{PRODUCT}}

**Author:** Larapilot (larapilot-adopt)
**Date:** {{DATE}}
**Commit:** {{GIT_SHA}}

## Stack
- Laravel {{VERSION}}, PHP {{VERSION}}, {{topology}}, {{admin panel}}, {{FE build}}

## Domain Model
| Entity | Table | Key fields | Relationships | Source |

## Feature Inventory (→ FRs)
| Capability | Routes / entrypoints | Controllers / jobs / policies | Maps to | Notes |

## Integrations
| Service | Purpose | Config / package | Env keys |

## CLI, Scheduler & CI
| Command / job / workflow | Trigger | Purpose |

## Security Surface
- Auth stack, guards, roles/permissions, sensitive routes, 2FA, personal-data touchpoints (Violet)

## Quality & Risk
- Test coverage snapshot, static analysis, observability, notable tech debt / dead code / TODOs
```

### 5. Write the PRD

Use the **PRD Template in `/larapilot-inception`** (section rules in `discovery-6.md` and `discovery-7.md` — read them now) — same required sections, rendered in the detected language. Adopt-specific requirements:

- Under `## MVP Scope` record:
  - `**Project Kind:** …` (confirmed)
  - `**Project Origin:** Adopted (existing codebase)`
  - `**Delivery Target:** …` (forward-looking, from Round 2)
  - `**Business Model:** …` (from Round 2)
  - `**Prior Art:** Not checked (adopted codebase)` — the product already exists; do not run the search
- `## Domain Model` from the Eloquent models, enums, and state columns found in the repo (entity, states, relations, owner persona); `## User Journeys` from routes, controllers, and feature tests — one `### J-XXX` per journey the code already serves, the core one first. Both cite evidence paths.
- `## Non-Functional Requirements` records only what the repo proves (rate limits, queues, caching, WCAG tooling, backups in CI); unknown targets are rows with `Not recorded in repo — confirm`, never invented numbers.
- `## Risks & Assumptions` lists every `Not recorded in repo — confirm` item as an open question with an owner, plus the risks the analysis report flagged under **Quality & Risk**.
- Add a short lead paragraph under `## Functional Requirements`: *"Requirements FR-001…FR-NNN are reverse-engineered from the running codebase at commit {{GIT_SHA}}; see `research/codebase-analysis.md` for evidence."*
- Every FR body cites its evidence paths and carries a **MoSCoW** tag (production code → Must).
- `## Technical Architecture` reflects the **real** stack, plus `**Server Management:**`, `**Ops Owner:**`, and `**Support Window:**` from Round 3; unknowns are marked `Not recorded in repo — confirm`, never invented. Do not propose migrations or rewrites here.
- `## PRD Revision History` first row: `| {{DATE}} | larapilot-adopt | Initial PRD reverse-engineered from codebase @ {{GIT_SHA}} |`.

Persist: `php artisan larapilot:prd-write --file=…` (or `--content=`), then `php artisan larapilot:validate-prd`. If `data.ok` is false, fix findings (max 3 attempts); clear `PRD_RECOMMENDED_SECTION` warnings too — an adopted PRD carries every section a fresh one does.

### 6. Developer domain docs (retroactive bootstrap)

Albert writes one best-effort file per domain under `{paths.dev_docs}` (default `.larapilot/docs/devs/`), derived from the analysis report's **Domain Model** and **Feature Inventory** — **English**, from `TEMPLATE.md`, with anything inferred rather than verified marked `<!-- TODO: verify -->`. Keep the folder `README.md` index in sync. Cover **every** domain the analysis found, not a sample: this is the project's catch-up, and a project adopted through this skill must reach its first spec already level. Accuracy is best-effort — `<!-- TODO: verify -->` is the honest marker, and the next spec that touches a domain corrects its file. Contract: **First-change catch-up** (`dev-docs-catchup.md`).

### 7. Dashboard snapshot & ledger

- `php artisan larapilot:choices-set --from-prd` (plus flags for any architecture choice not scraped).
- When `data.settings.lucille` is `YES`: the `usage_log` command of the `context` envelope.
- Zoey posts the end **Context estimate** line.

### 8. Next steps

Offer, in order:

- `/larapilot-spec` — bootstrap the backlog from the reverse-engineered PRD. **Call out** that most FRs describe already-shipped work: the user typically wants to either (a) generate the backlog then close already-delivered stories, or (b) scope `/larapilot-spec` to `Future Phases` / gaps only. Recommend (b) for a large mature app.
- `/larapilot-feature "…"` — if the user's immediate need is one new capability rather than a full backlog.
- `/larapilot-settings` — set `git_mode`, `testing`, `backlog` granularity before the first spec.

## Output Boundaries

- Read-only on the application codebase — no refactors, migrations, renames, or "cleanup" in this skill.
- No backlog / spec / plan artifacts — only the PRD, `codebase-analysis.md`, and the developer domain docs bootstrap.
- Do not fabricate architecture the repo does not show — mark it `confirm` and ask, or leave it for `/larapilot-inception`-style discovery later.
- Not a substitute for `/larapilot-inception` on true greenfield or legacy-rewrite work.
- Agents speak in character during discovery; the PRD and analysis report are formal documents in the detected language.

## Output Economy

**Clarity first.** Report what the code shows with file-path evidence, then ask only the gaps (3 questions per round at most). Persona chat blocks: 2–4 sentences. `codebase-analysis.md` and the reverse-engineered PRD stay complete and formal.

## Example

**Invoke:** `/larapilot-adopt` in a 3-year-old Laravel 11 invoicing SaaS with no `.larapilot/docs/PRD.md`.

1. `context` → no PRD, `lucille: YES`, `effort: STANDARD`.
2. Discovery: Livewire + Filament admin, Sanctum API, 14 models, Stripe + Mailgun + S3, Horizon queues, GitHub Actions CI, Sentry. `Explore` sub-agent maps `app/`.
3. Derived: Project Kind **Application**; personas **Account Owner**, **Team Member**, **API Consumer**, **Admin**; 22 FRs (all Must — in production); half-built "recurring invoices" behind a feature flag → Future Phases.
4. Interview: name **"Bilo"**, pitch confirmed; forward Delivery Target **V1 Complete**; recurring invoices → *Keep & finish*; deploy platform not in repo → user says Forge + AWS.
5. Write `research/codebase-analysis.md` (entity table, feature inventory, integrations, CI, security surface, 3 tech-debt notes).
6. Write PRD — `Project Origin: Adopted (existing codebase)`, FRs cite paths, architecture = real stack, deploy = Forge/AWS. `validate-prd` → ok.
7. `choices-set --from-prd`; `usage-log`. Next: `/larapilot-spec` scoped to Future Phases (recurring invoices) + any gaps.
