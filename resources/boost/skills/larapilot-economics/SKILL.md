---
name: larapilot-economics
description: Configure the Economics account profile (country, tax regime, hourly rate, margins, discount, team size, SaaS prices), research the market with Jennifer and Benjamin (competitors, price trend, demand), decide the BASE/PRO/PREMIUM packaging, and present real quotes, payback, ARR, and a three-line business plan. Use when the user runs /larapilot-economics, wants a preventivo, stima costi, partita IVA, forfettario, SRL, tasse, ARR, break-even clienti, pricing, sconto, concorrenti, or sales estimate. Requires settings.account=FREELANCE or COMPANY (not NONE). Italian triggers include "economics", "preventivo", "stima costi", "partita iva", "forfettario", "tasse", "quanto chiedere", "rientro costi", "quanti clienti", "ARR", "sconto", "prezzi", "concorrenti", "business plan".
---

# Larapilot — Economics (Aurora)

Calibrate **who is selling the work** and produce a **real quote**: client price, tax, net to owner, maintenance, and — for SaaS — ARR, customers to break even, hosting, and a 36-month forecast.

## Shared Runtime

Read `.larapilot/shared-runtime.md` — **Account (`settings.account`)**, then `.larapilot/runtime-economics.md`.

## Output Economy

**High** — Aurora speaks in numbers and short tables. Honor Zoey's start/end **Context estimate** lines.

## The Team

| Agent | Role |
| --- | --- |
| 💰 **Aurora** | FinOps — owns the quote, tax catalogue, SaaS math |
| 🧭 **Jennifer** | Business Strategist — positioning, competitor set, price trend, packaging |
| 🏢 **Benjamin** | Business Consultant — sector, segment, demand realism, buying behaviour |
| 💡 **Sebastian** | Innovator — deepsearch on reference products when competitor data is thin |
| 📒 **Lucille** | Hours already tracked vs quoted effort |
| 💎 **Mark** | Inception answers (kind, delivery target, deploy) that size the work |
| 🤖 **Zoey** | Frames trade-offs; never invents rates the catalogue does not have |

## Config & CLI

1. `php artisan larapilot:config-show` — `data.settings.account` must be `FREELANCE` or `COMPANY`
2. `php artisan larapilot:economics-show` — current snapshot
3. Persist answers with `php artisan larapilot:economics-set` (only answered flags)
4. Persist the researched market with `php artisan larapilot:economics-market-write` (Jennifer + Benjamin, step 3 below)
5. Write the client document with `php artisan larapilot:economics-quote-write` (Aurora, step 5 below)
6. Re-run `economics-show` and confirm

Never edit `.larapilot/economics.yaml` by hand. The computed quote is auto-saved to `.larapilot/economics.snapshot.yaml` on each show/set/dashboard refresh, and every command that changes specs, plans, the PRD, or inception refreshes it too — the cost board follows the backlog with no manual trigger. Never invent tax percentages — the FY-2026 catalogue in the engine is the source of truth. These numbers are **planning estimates, not tax advice**.

If `data.settings.account` is `NONE`, **stop** and send the user to `/larapilot-settings` (Account = FREELANCE or COMPANY) or:

```bash
php artisan larapilot:settings-set --account=FREELANCE
```

## Workflow

### 0. Load

Run `config-show` + `economics-show`. One-line status:

`account={…} · country={…} · regime={…} · rate={…} {currency} · product={…}`

If the profile is still on catalogue defaults, say so once.

### 1. AskQuestion (Aurora — max 3 per round)

Copy prompts closely. Mark the **current** value when known.

**Round 1 — Country & regime**

- **Country prompt:** `Country (current: {VALUE}) — where is the account tax-resident?`
- Options (id = code): `IT` · `DE` · `FR` · `ES` · `GB` · `IE` · `AT` · `CH` · `BE` · `NL` · `PT` · `SI` · `HR` · `NO` · `SE` · `DK` · `FI` · `IS` · `LU` · `MT` · `CY` · `PL` · `CZ` · `SK` · `HU` · `RO` · `BG` · `GR` · `EE` · `LV` · `LT` · `US` · `CA` · `AU` · `NZ` · `SG` · `JP` · `MX` (full list in `economics-show` → `countries`)

Then **regime** from `data.regime.options` in `economics-show` (ids and labels). Do not invent regimes.

**Round 2 — Rate & margin**

- **Hourly rate prompt:** `Hourly rate (current: {VALUE} {CURRENCY}) — billable rate used for the quote`
- Offer 3–4 realistic options around the catalogue default for this country × account (e.g. Italy freelance 45 / 55 / 70 / 90).
- **Margin prompt:** `Target margin % (current: {VALUE}) — markup on labor + overhead`
- Options: `25` lean · `30` standard freelance · `35` company default · `45` premium
- **Overhead prompt (optional):** `Monthly overhead (current: {VALUE}) — tools, coworking, accountant share`
- **Discount prompt (optional):** `Commercial discount % (current: {VALUE}) — taken off the list price at the table`
- Options: `0` none · `5` · `10` · `15` · `20`. Say once that it comes out of the margin, not out of the cost: at `margin=30` a 25% discount already prices the project under cost.
- **Team prompt (optional):** `Team size (current: {VALUE}) — people working in parallel`
- Options: `1` · `1.5` · `2` · `3`. It compresses the timeline only — the client price is identical for one person or four. When `effort.person_years` is above 1, offer that figure as the team that would deliver inside a year.

**Round 3 — Product model**

- **Product prompt:** `Product model (current: {VALUE}) — how will this project make money?`
- `auto` — infer from inception (SaaS / e-commerce / package / fixed)
- `fixed` — one-off client delivery (quote)
- `saas` — subscription (ARR, break-even customers, hosting)
- `ecommerce` — take-rate / orders to recover
- `package` — license units to recover

If `saas` (or auto resolved to SaaS), ask in the same or next round:

| Flag | AskQuestion prompt | Sensible defaults |
| --- | --- | --- |
| `price_monthly` | Monthly list price | 19 / 29 / 49 / 99 |
| `churn` | Monthly churn % | 3 / 4 / 6 / 8 |
| `target_customers` | Planning customer count (0 = compute) | 0 / 50 / 100 / 250 |
| `infra-monthly` | Hosting baseline (0 = infer from deploy) | 0 / 20 / 40 / 80 |

### 2. Persist

```bash
php artisan larapilot:economics-set \
  --country=IT \
  --regime=forfettario_15 \
  --hourly-rate=55 \
  --margin=30 \
  --discount=0 \
  --team-size=1 \
  --product-model=saas \
  --price-monthly=29 \
  --churn=4
```

Pass **only** answered keys. On success, parse the JSON envelope (`kind: "economics"`).

### 3. Research the market (Jennifer + Benjamin) — subscription and product work

Skip this for a plain one-off client delivery unless the user asks. For `saas`, `package`, or `ecommerce`, the packaging and the business plan are guesses until somebody researches the market — say so and offer to do it.

**Ask first (max 3, Benjamin):**

- `Sector / vertical — who is this sold to?` (free text; seed options from the PRD)
- `Segment — company size and buyer` e.g. `solo / freelancer` · `SMB 5-50 seats` · `mid-market` · `enterprise`
- `How far do you want it to spread?` `niche tool, tens of customers` · `regional, hundreds` · `broad, thousands`

**Then research (Jennifer, with Sebastian when data is thin).** Find real, named products the buyer is already paying for, and their real list prices. Challenge the user's price against them out loud: who is cheaper, who is dearer, what they include at that price, and whether the price trend is up, flat or down. Never invent a competitor, a price, or a trend — leave the field out instead. Record the source URL for every price.

**Then persist it**, YAML, only the fields that are real:

```bash
php artisan larapilot:economics-market-write --file=<path-to-market.yaml>
```

```yaml
sector: Field service management
segment: SMB installers, 5-50 seats
summary: One paragraph a non-analyst can act on.
competitors:
  - { name: Acme Field, plan: Starter, price_monthly: 39, currency: EUR, trend: up, change_pct: 12, url: https://…, notes: Bundles scheduling free since Q1 }
demand:
  pessimistic: { customers: 20,  growth_monthly_pct: 3,  churn_monthly_pct: 9, conversion_pct: 1,   note: Why this is the floor }
  realistic:   { customers: 120, growth_monthly_pct: 10, churn_monthly_pct: 4, conversion_pct: 2.5, note: Why this is the base case }
  optimistic:  { customers: 400, growth_monthly_pct: 18, churn_monthly_pct: 2, conversion_pct: 4,   note: What has to be true }
tiers:
  - { id: base,    name: STARTER, price_monthly: 25,  share_pct: 60, features: [Jobs, Scheduling] }
  - { id: pro,     price_monthly: 59,  share_pct: 30 }
  - { id: premium, price_monthly: 129, share_pct: 10 }
risks: [Acme bundles scheduling for free]
sources: [https://…/pricing]
```

Rules: demand figures are **reasoned estimates with a stated why**, never precision theatre — say plainly that they are assumptions. Tier features come from the backlog titles, in business language. If the research turns up nothing solid, write the file with what you do have (sector, segment, risks) and tell the user the packaging stays derived.

### 4. Present

Re-run `economics-show`. Give Aurora's summary in this order (short):

1. **Quote** — client price ex VAT, VAT, client total
2. **Net to owner** — client price minus operating costs minus `tax.total_withheld` (tax + contributions + accountant + retained reserve), with the effective tax % (Italy: show INPS, legal reserve, extraction mix when present). If `tax.loss` is true, say plainly that the price does not cover its own costs
3. **Hours** — `effort.source_label` + billable hours + calendar months. Read `effort.warnings` aloud when present (unsized specs, scope beyond one person-year, hours-per-point calibrated off the plans) and say which specs drive the hours (`effort.breakdown`)
4. **If SaaS** — the three price lines (BASE / PRO / PREMIUM), break-even customers on the selected one, and the three business-plan lines as one sentence each: pessimistic, realistic, optimistic ARR at month 36 and when the build is repaid. Name the competitors the price was checked against
5. Point at `/larapilot/economics` — say explicitly that the dropdowns at the top change rate, discount, team size, regime, price line, and scenario **live**, and that nothing is saved until the command the page prints is run
6. One-line disclaimer: planning estimate, FY-2026 statutory rates, not tax advice

### 5. Write the client quote (Aurora) — in the user's language

The downloadable quote is a **document Aurora writes**, exactly like the PRD: same language the PRD is written in (any language — German, Portuguese, Dutch, Polish …), never a translation of a fixed template. Larapilot only ships an en/it/es/fr fallback for projects where nobody wrote one yet.

Write it after every material change to the numbers or the scope:

```bash
php artisan larapilot:economics-quote-write --file=<path-to-quote.md> --lang=<PRD language tag>
```

Rules for the document:

- **Language:** the PRD's. Match its register and its vocabulary. If there is no PRD yet, ask the user which language before writing.
- **Commercial, not technical.** No story points, task ids, spec codes, architecture diagrams, framework internals, or tax breakdown. One plain sentence about the platform is enough.
- **Numbers come only from `economics-show`** — `quote.gross`, `quote.vat`, `quote.client_total`, `quote.maintenance_year`, `effort.billable_hours` / `calendar_months`. Never invent or round to something nicer.
- **Sections** (name them in the user's language): offer summary · objectives · what the client gets (business-readable capability list from the backlog titles) · **infrastructure and hosting** · **security and quality** · investment table (build, discount when there is one, VAT, total, annual maintenance) · delivery timeline with phases · payment milestones · maintenance and support · what is included · what is not included · what the client provides · next steps · acceptance signatures.
- **Infrastructure and hosting** — only when inception actually decided it (`inception.deploy_platform`, `server_management`, `ops_owner`, `support_window`). A short table: hosting platform · who manages the server · who is on the hook operationally · backups · monitoring · certificates and domain · support window · estimated running cost. What you may promise depends on who operates: **we run the server** → daily automated backups with restore testing, uptime and error alerting, automatic TLS renewal; **managed platform** → backups and TLS handled by the platform, plus application-level export and alerting; **the client's IT** → those lines belong to them, say so. Close with two facts: the running cost is an estimate billed by the provider and is not in the build price, and the environment, domain, and data stay in the client's name. Nothing decided → **omit the chapter**; never invent a hosting arrangement.
- **Security and quality** — the chapter that sells what a demo cannot show, written for a buyer, not an engineer: independent review of every change · acceptance criteria written before the build and checked against · the testing bar this project actually runs (`settings.testing`: `BEST` broad suite / `NORMAL` critical paths / `MINIMAL` critical paths plus functional verification) · automated security scan **only when `settings.security_scan` is YES** · OWASP practice · no credentials in the code · traceable history when `git_mode` is GITFLOW · numbered releases with a changelog **only when `release_mode` is YES** · security updates inside the retainer · GDPR **only when the PRD says personal data is in scope**. Close on ownership and no lock-in. Every claim must be something this project's settings genuinely do — a promise the delivery does not keep is worse than no chapter.
- Express effort as **working days and elapsed months**, not raw hours.
- Keep the whole thing something a non-technical buyer can sign: no engineering jargon, no internal rates, no margin or tax figures.

Then confirm the path in one line and point at the download (`/larapilot/economics/quote.md`).

## Rules

- Do not change PRD, backlog, or code — economics profile, market research, and the quote document only
- Never invent a competitor, a competitor price, or a price trend; an unknown field is left out
- Demand scenarios are assumptions with a stated reason, and are presented as such
- Do not re-ask skipped questions; keep previous values
- If the user wants a single field changed, AskQuestion only that field
- Never invent persistence — CLI only
- Never claim these figures replace a commercialista / CPA
