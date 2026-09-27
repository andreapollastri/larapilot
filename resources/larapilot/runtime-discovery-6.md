Part of this runtime pack. The pack file is an index. Read with the editor file-read tool, never `cat`.

## Prior Art & Open-Source Alternatives _(Sebastian — `settings.prior_art`, default ON)_

Before the first functional requirement, the team checks whether the product — or its core — already exists as open source, as a maintained package, or as a product worth buying instead of building. The goal challenge asks "what do they do instead?"; this round verifies the answer against the world instead of trusting it. It runs in **`larapilot-inception`** right after the goal challenge and **before** scope. It is advisory: the team says what it found once, clearly, and the user decides.

**Consent first.** A search query can expose a confidential idea ("CRM for notaries in Veneto"). Before the first web call, **Sebastian** states in one line the queries he intends to run and asks via **AskQuestion**: `Search now` | `Search with generic terms only` | `Skip — I know the alternatives` | `Skip — confidential`. When `settings.prior_art` is `NO`, the round is skipped without asking and the PRD records `**Prior Art:** Not checked`.

**Where to look** — editor web tools (**WebSearch**, **WebFetch**, or equivalent), never Boost `Search Docs`:

| Project Kind | Sources | Depth |
| --- | --- | --- |
| **Package** | Packagist search, GitHub (`topic:laravel`, stars + last release), Spatie catalog, Laravel News packages, awesome-laravel | Mandatory — a package that duplicates a maintained one is dead on arrival |
| **Application** | GitHub topics and search, awesome-selfhosted, AlternativeTo / OpenAlternative, Product Hunt, Laravel-based OSS (search "laravel" + the domain) | One search round, three to five candidates |
| **Website** | Laravel CMS and commerce platforms (Statamic, Lunar, Bagisto, Aimeos), site builders | Only for E-commerce, Portal, or Documentation types |
| **Personal** | Skip unless the user asks | — |

**What to capture per candidate** (max five): name and URL · license · stack (Laravel or not) · last release date and issue-tracker health · what it covers of the goal · what it does not · adoption cost (hosting, lock-in, customization effort). Reuse the maintenance and security checks from **Vendor & Package Policy** in `runtime-delivery.md` — stars are not maintenance; a last release older than a year is a flag. **Violet** notes license compatibility with the Business Model (AGPL or BSL against a commercial SaaS, for example). **Andrew** vets Laravel fit. **Aurora** compares the adoption cost with the build in one line.

**Persist** the report to `{paths.research}/prior-art.md`:

```markdown
# Prior Art

**Date:** {{DATE}} · **Queries:** {{list}} · **Sources:** {{list}}

| Candidate | URL | License | Stack | Last release | Covers | Missing | Adoption cost |
| --- | --- | --- | --- | --- | --- | --- | --- |

## Verdict

{{one paragraph — what exists, the gap this product fills, or the honest conclusion that there is none}}
```

**Decide** via **AskQuestion** (Mark + Sebastian): `Build anyway` | `Adopt / fork` | `Integrate as dependency` | `Not checked`. Record `**Prior Art:** …` under `## MVP Scope`, list the candidates in `### Prior Art & Alternatives` with a one-line reason each, persist with `choices-set --prior-art="…"`, and log the decision in the journal.

| Verdict | What happens next |
| --- | --- |
| **Build anyway** | The differentiators against the closest candidate become the first `Must` FRs, and the **Success signal** measures them. |
| **Adopt / fork** | Laravel candidate → clone it and continue with `/larapilot-adopt` (the PRD is reverse-engineered from that code), or scope the fork as a legacy port with Sabrine. Non-Laravel candidate → say plainly that Larapilot is not the tool for it and end inception with the report. |
| **Integrate as dependency** | The candidate enters `### Integrations` (Matt) or `### Stack` (Andrew); FRs describe the glue and what stays custom. |
| **Not checked** | Web tools unavailable, consent withheld, or `prior_art: NO`. Write it as such — never as "nothing exists". |

No web tools in the editor → ask in chat which alternatives the user knows, record those, and write `Not checked (no web access)`.

Downstream: **`larapilot-spec`** reads the verdict — glue and integration specs first when a dependency was adopted; differentiator FRs first when building anyway. **`larapilot-feature`** runs a lighter version at feature level (**Andrew**: built-in → Spatie → package, per **Vendor & Package Policy**) before creating a new FR. **`larapilot-economics`** reads the adoption-cost line when it prices build against buy.

Ownership: **Sebastian** searches and writes the report; **Jennifer** reads positioning against what exists; **Andrew** vets Laravel fit; **Violet** licenses; **Aurora** cost; **Mark** owns the verdict.

## Domain Model & User Journeys _(Mark + Tom + Mike)_

A PRD that lists features without naming the nouns and the paths through them produces specs that invent both. Two sections close the gap, written **before** `## Functional Requirements` so every FR can cite them.

### User Journeys (`## User Journeys`)

One journey per way a persona gets value from the product — the unit `larapilot-spec` turns into a spec under `LEAN`/`STANDARD` backlog granularity. Three to seven journeys for an MVP; more only for Full Product / Enterprise.

```markdown
### J-001: {{Name}}

**Persona:** {{from ## User Personas}} · **Trigger:** {{what starts it}} · **Frequency:** {{daily / weekly / once}}
**Steps:** 1. … 2. … 3. …
**Success end-state:** {{what is true when it worked — observable}}
**Failure modes:** {{the two or three ways it goes wrong that the product must handle}}
**FRs:** FR-001, FR-004 · **MoSCoW:** Must
```

Rules: every persona appears in at least one journey; every journey cites at least one FR; every `Must` FR belongs to at least one journey. A `Must` FR with no journey is either a hidden journey or not a Must. The **core journey** — the one the Success signal measures — is listed first and marked as such.

### Domain Model (`## Domain Model`)

The ubiquitous language: entities, their key states, and the relationships that matter for scope. **Mike** turns it into schema; **Tom** uses the names in acceptance criteria; **Alex** names classes after it. It is not a schema — no columns, no indexes.

```markdown
| Entity | What it is | Key states / lifecycle | Relations | Owner persona |
| --- | --- | --- | --- | --- |
| Invoice | A bill issued to a client | Draft → Sent → Paid / Overdue → Archived | belongs to Client, has many Lines | Freelancer |

**Glossary:** {{term — meaning}} for every word the client uses differently from the team.
```

Rules: every entity named in an FR appears here; every state a journey reaches is listed; one meaning per term. When the product is multi-tenant, the tenant boundary is drawn here (which entities are per tenant, which are global). **Legacy** projects: the model starts from Sabrine's inventory and marks what is preserved, reorganized, or dropped with consent.

Ownership: **Mark** owns journeys; **Tom** checks the coverage rules; **Mike** owns the domain model with **John**; **Marika** keeps the glossary consistent with copy; **Sabrine** on legacy.
