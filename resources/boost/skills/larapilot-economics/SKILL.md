---
name: larapilot-economics
description: Configure the Economics account profile (country, tax regime, hourly rate, margins, SaaS prices) and present real quotes, payback, ARR, and forecasts. Use when the user runs /larapilot-economics, wants a preventivo, stima costi, partita IVA, forfettario, SRL, tasse, ARR, break-even clienti, or sales estimate. Requires settings.account=FREELANCE or COMPANY (not NONE). Italian triggers include "economics", "preventivo", "stima costi", "partita iva", "forfettario", "tasse", "quanto chiedere", "rientro costi", "quanti clienti", "ARR".
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
| 📒 **Lucille** | Hours already tracked vs quoted effort |
| 💎 **Mark** | Inception answers (kind, delivery target, deploy) that size the work |
| 🤖 **Zoey** | Frames trade-offs; never invents rates the catalogue does not have |

## Config & CLI

1. `php artisan larapilot:config-show` — `data.settings.account` must be `FREELANCE` or `COMPANY`
2. `php artisan larapilot:economics-show` — current snapshot
3. Persist answers with `php artisan larapilot:economics-set` (only answered flags)
4. Write the client document with `php artisan larapilot:economics-quote-write` (Aurora, step 4 below)
5. Re-run `economics-show` and confirm

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
  --product-model=saas \
  --price-monthly=29 \
  --churn=4
```

Pass **only** answered keys. On success, parse the JSON envelope (`kind: "economics"`).

### 3. Present

Re-run `economics-show`. Give Aurora's summary in this order (short):

1. **Quote** — client price ex VAT, VAT, client total
2. **Net to owner** — client price minus operating costs minus `tax.total_withheld` (tax + contributions + accountant + retained reserve), with the effective tax % (Italy: show INPS, legal reserve, extraction mix when present). If `tax.loss` is true, say plainly that the price does not cover its own costs
3. **Hours** — `effort.source_label` + billable hours + calendar months. Read `effort.warnings` aloud when present (unsized specs, scope beyond one person-year, hours-per-point calibrated off the plans) and say which specs drive the hours (`effort.breakdown`)
4. **If SaaS** — break-even customers, customers to recover in 12 months, ARR at planning, LTV:CAC, months to recover
5. Point at `/larapilot/economics` for the per-spec effort table, charts, and the 36-month forecast
6. One-line disclaimer: planning estimate, FY-2026 statutory rates, not tax advice

### 4. Write the client quote (Aurora) — in the user's language

The downloadable quote is a **document Aurora writes**, exactly like the PRD: same language the PRD is written in (any language — German, Portuguese, Dutch, Polish …), never a translation of a fixed template. Larapilot only ships an en/it/es/fr fallback for projects where nobody wrote one yet.

Write it after every material change to the numbers or the scope:

```bash
php artisan larapilot:economics-quote-write --file=<path-to-quote.md> --lang=<PRD language tag>
```

Rules for the document:

- **Language:** the PRD's. Match its register and its vocabulary. If there is no PRD yet, ask the user which language before writing.
- **Commercial, not technical.** No story points, task ids, spec codes, architecture diagrams, framework internals, or tax breakdown. One plain sentence about the platform is enough.
- **Numbers come only from `economics-show`** — `quote.gross`, `quote.vat`, `quote.client_total`, `quote.maintenance_year`, `effort.billable_hours` / `calendar_months`. Never invent or round to something nicer.
- **Sections** (name them in the user's language): offer summary · objectives · what the client gets (business-readable capability list from the backlog titles) · investment table (build, VAT, total, annual maintenance) · delivery timeline with phases · payment milestones · maintenance and support · what is included · what is not included · what the client provides · next steps · acceptance signatures.
- Express effort as **working days and elapsed months**, not raw hours.
- Keep the whole thing something a non-technical buyer can sign: no engineering jargon, no internal rates, no margin or tax figures.

Then confirm the path in one line and point at the download (`/larapilot/economics/quote.md`).

## Rules

- Do not change PRD, backlog, or code — economics profile and the quote document only
- Do not re-ask skipped questions; keep previous values
- If the user wants a single field changed, AskQuestion only that field
- Never invent persistence — CLI only
- Never claim these figures replace a commercialista / CPA
