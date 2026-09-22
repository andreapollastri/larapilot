Part of this runtime pack. The pack file is an index. Read with the editor file-read tool, never `cat`.

## Decision Journal _(inception — `settings.decision_log`, default ON)_

When `data.settings.decision_log` is `YES`, every fixed-choice **AskQuestion** answer and every explicit free-text directive/preference the user gives during discovery is recorded to `.larapilot/decisions.yaml`:

- After the user settles a choice, run `php artisan larapilot:decision-log --topic="…" --value="…" --source=askquestion|chat --skill=larapilot-inception [--rationale="…"]`. Log the durable choices — Project Kind, Delivery Target, Frontend Topology, admin panel, data store, tenancy pattern, deadlines, brand/UX preferences (colors, tone), explicit exclusions — not every conversational aside.
- When a later round revisits a topic already decided, first `php artisan larapilot:decision-check --topic="…" --value="<new>"`; if `data.has_regression` is `true`, replay the earlier choice(s) via **AskQuestion** and, once the user confirms the change, re-log with `--supersedes=<id>`.
- This runs **in addition to** `larapilot:choices-set` (which keeps the current dashboard snapshot) — the journal keeps the history and the regression signal. Full contract: **Decision journal (`settings.decision_log`)** in `shared-runtime.md`. Skip entirely when the setting is `NO`.

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
2b. **The legacy proposal does not replace the core rounds** — it only changes their order. After **Project Origin** is settled, continue with **Delivery Target**, **Business Model**, and **Operations & support** (see **Core rounds**) before deep architecture. On a rewrite the server question is more urgent, not less: there is already a machine running the old system, and who keeps it alive during and after the cutover is part of the scope. Ask explicitly whether the legacy infrastructure stays, is replaced, or runs in parallel during migration, and record it under `**Server Management:**`.
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

## Business Model _(Mark + Aurora — core round 3)_

**How far** the product goes (Delivery Target) and **how it makes money** are two different questions, and they are routinely conflated. "SaaS" is not a delivery target — it is a business model, and a SaaS can perfectly well be delivered as an MVP.

Asked via **AskQuestion** in the same round as the delivery target or right after it, for every Project Kind:

```markdown
**Business Model:** Client project | SaaS subscription | E-commerce | Licensed package | Internal tool | Not decided
```

| Option | Meaning | What it switches on |
| ------ | ------- | ------------------- |
| **Client project** | Built once for one client, paid on delivery | Quote, payment milestones, maintenance retainer |
| **SaaS subscription** | Many customers pay monthly or yearly | Tenancy question (John), pricing tiers, churn/ARR maths, break-even customers |
| **E-commerce** | Revenue through orders on the product itself | Payments, catalogue, order flow, take-rate maths |
| **Licensed package** | Sold or distributed as a reusable product | Distribution, versioning, licence price, units to recover the build |
| **Internal tool** | No revenue — cost centre | No pricing round; Economics still sizes cost and effort |
| **Not decided** | Genuinely open | Ask again before the quote; Economics reads the PRD instead |

Persist with `php artisan larapilot:choices-set --business-model="…"`. **`/larapilot-economics` reads it** and sets the product model from it — a stated answer always beats guessing from PRD keywords. When it is `SaaS subscription`, Economics prices the BASE / PRO / PREMIUM lines and the three business-plan scenarios; when it is `Client project`, it prices one delivery plus the maintenance retainer.

