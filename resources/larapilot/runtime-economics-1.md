Part of this runtime pack. The pack file is an index. Read with the editor file-read tool, never `cat`.

## Account mode

`settings.account` in `.larapilot/config.yaml`:

| Value | Who | Typical regimes |
| --- | --- | --- |
| `NONE` | Nobody | Economics stays dark |
| `FREELANCE` | Partita IVA, sole trader, autónomo, Freiberufler | Italy forfettario 5/15, IRPEF ordinario; country catalogue otherwise |
| `COMPANY` | SRL / SPA / Ltd / GmbH / C-Corp / BV | Corporate tax + local tax + dividend extraction |

Set only via `php artisan larapilot:settings-set --account=…`. Never hand-edit `config.yaml` from a skill.

Every catalogue figure — brackets, caps, hourly rates, and `compliance_annual` — is expressed in the **country's own currency**, never converted to EUR.

## Profile file

`.larapilot/economics.yaml` (path `paths.economics`) holds the **account profile**. Persist only through `larapilot:economics-set`. Keys:

The **computed snapshot** (quote, tax, effort, sales, scenarios, SaaS forecast) is written automatically to `.larapilot/economics.snapshot.yaml` (`paths.economics_snapshot`) whenever `economics-show`, the dashboard, the API, or `economics-set` runs.

It also refreshes **by itself**: every command that changes a quote input — `spec-add`, `spec-plan`, `task-done`, `spec-start` / `spec-review` / `spec-approve` / `spec-request-changes`, `spec-delete`, `prd-write`, `choices-set`, `settings-set`, `usage-log`, `tracker-pull` — recomputes the snapshot when the inputs fingerprint (backlog, plans, PRD, inception, usage ledger, profile, settings) changed. Nobody has to trigger the cost board by hand, and `/larapilot/economics` always computes live on load.

Profile keys:

| Key | Meaning |
| --- | --- |
| `country` | `IT` `DE` `FR` `ES` `GB` `US` `NL` `PT` `CH` `AT` `BE` `IE` |
| `regime` | Catalogue id for that country × account (`forfettario_15`, `srl`, `ltd`, …) |
| `hourly_rate` | Billable rate in `currency` |
| `currency` | ISO code (defaults from the country) |
| `billable_days_per_year` | Capacity (default 220) |
| `hours_per_day` | Focused delivery hours (default 6) |
| `margin_target_pct` | Markup on labor + overhead (freelance 30, company 35) |
| `maintenance_annual_pct` | Suggested retainer vs the build quote (default 15) |
| `overhead_monthly` | Tools, coworking, accountant share |
| `vat_registered` | `true` / `false` / omit to infer from the regime |
| `vat_mode` | `domestic` (22% IT) or `eu_b2b` (reverse charge, no VAT on invoice) |
| `owner_working` | Company only: habitual/prevalent working shareholder → Gestione Commercianti (default `true`) |
| `extraction` | Company only: `auto` (best mix), `dividends`, or `mixed` |
| `product_model` | `auto` `fixed` `saas` `ecommerce` `package` |
| `discount_pct` | Commercial discount taken off the list price (0–90). It comes out of the margin, never out of the cost |
| `team_size` | People working in parallel (0.25–50). It compresses the timeline, not the price |
| `saas.*` | List price, churn, growth, infra, CAC, target customers (`price_annual` 0 = not decided, derived from the monthly price) |

`economics-set` refuses a non-numeric or out-of-range flag instead of casting it to 0: `--hourly-rate` 1–5000, `--margin` 0–300, `--churn` / `--maintenance` 0–100, `--payment-fee` 0–50, `--growth` 0–200, `--hours-per-day` 1–24, `--billable-days` 1–366, `--currency` a 3-letter ISO code. Changing `--country` moves the regime to that country's default unless `--regime` is passed in the same call.

`economics-show` returns the computed snapshot (quote, tax, payback, SaaS). Dashboard: `/larapilot/economics`. JSON: `GET /larapilot/api/economics`. Internal tax report: `--format=md`.

## The pricing tool

`/larapilot/economics` is a console, not a report. Every dropdown at the top of the page — hourly rate, margin, discount, team size, overhead, maintenance, account type, country, regime, VAT, how it is sold, list price, price line, market scenario, churn, growth, planning customers — recomputes the whole page, the charts, and the downloadable quote.

| | |
| --- | --- |
| Where it computes | Server side, through the same `EconomicsService::snapshot()` the CLI uses — the tax engine is never reimplemented in the browser |
| How | Each change fetches `/larapilot/economics/panel` with the changed values as query parameters and swaps the panel; without JavaScript the same form submits to `/larapilot/economics` |
| What persists | **Nothing.** A simulation prints the `economics-set` command that would make it real, with a copy button |
| Out of range | Refused and dropped, not clamped silently: an edited URL cannot push a value the engine would not accept |
| Agents | `GET /larapilot/api/economics?hourly_rate=70&tier=premium&discount_pct=10` prices a scenario without touching the profile |

Only values that differ from the saved profile travel in the URL, so the query is readable and the dot next to a control means it really was changed. While a simulation is on screen the quote download serves the built-in template with the simulated numbers — an agent-written client document is never rewritten around a dropdown.

**Discount** is applied to the list price (`quote.list_price` → `quote.discount` → `quote.gross`). The cost underneath does not move, so `margin_after_discount` falls and `below_cost` turns true when the price stops covering labour and overhead. **Team size** divides the calendar months only: person-months, overhead, and the client price are identical for one person or four.

## Maintenance follows the inception answers

The retainer is not a flat percentage of the build. `maintenance.recommended_pct` is built from what inception already decided, and `/larapilot/economics` shows the arithmetic under **What the retainer is priced on**:

| Answer | Effect on the retainer |
| --- | --- |
| **Delivery Target** | Enterprise +8 · Full Product +3 · V1 Complete ±0 · MVP −2 |
| **Server Management / Ops Owner** | You operate the server +4 (patching, backups, certificates, uptime enter the retainer) · managed platform −1 · the client's own team operates it −3 |
| **Budget Sensitivity** | Tracked −2 (itemised and lean) · Relaxed ±0 (can carry proactive work) |
| **Ship method** | `release_mode` +2 (tagged releases, changelog, upgrade notes) · `git_mode: GITFLOW` +1 (hotfix branch) |
| **Testing** | `BEST` −1 · `NONE` +3 (every change verified by hand) |
| **Security scan** | +1 (findings triaged inside the retainer) |
| **Support Window** | 24/7 +6 · Extended +3 · Business hours ±0 · Best effort −2 |

The baseline is 12% and the result is clamped to 5–40%. `maintenance.covers` lists what the client gets for it, `maintenance.drivers` each answer with its delta, and `maintenance.gaps` the questions inception never asked — an unanswered server question prices the retainer as application-only and says so. A profile nobody has configured **adopts** the recommendation (`maintenance.adopted`); once `--maintenance` is set the user's figure wins and the dashboard reports the divergence.

Economics also reads `business_model` from the inception choices: a stated answer (`SaaS subscription`, `Client project`, `E-commerce`, `Licensed package`, `Internal tool`) sets the product model directly, and only an unanswered one falls back to reading the PRD for keywords.

## Packaging and the business plan

When the product is sold as a subscription the page carries three price lines and three readings of the market.

- **BASE / PRO / PREMIUM** — researched tiers win; otherwise PRO is the configured list price, BASE and PREMIUM are derived from it (0.6× / 2.2×, snapped to a shelf price) and the backlog is cut into three cumulative feature sets in backlog order. Each tier reports contribution per customer, break-even customers, and customers needed to repay the build in 12 months.
- **Price line** — which tier the forecast runs on. Switching it recomputes every projection below.
- **Pessimistic / realistic / optimistic** — a full 36-month forecast each. Researched demand wins; otherwise the realistic line is the profile's own churn, growth, and target, and the other two bend it by a fixed amount (pessimistic: half the growth, 1.6× the churn, 0.4× the ambition). A dropdown the user actually moved always wins over both.

## Language

The page follows the **PRD language** the client quote already follows — `ArtifactLanguage::detect()` over the PRD, resolved once per `snapshot()` and carried on the payload as `language`. Section headings, captions, banners, the glossary, and every sentence the engine writes into the snapshot (effort notes and warnings, maintenance drivers and gaps, tier notes, business-plan notes, hosting notes, the market hint) come from `Larapilot\Support\EconomicsStrings`, which ships `en` · `it` · `es` · `fr` · `de` · `pt` · `nl` · `pl` and falls back to English key by key.

Larapilot writes prose in **three** places, each with its own vocabulary: this one, the client quote (`EconomicsQuoteWriter::strings()`), and the design presentation (`MockupPackageService::copy()`). A language belongs in `ArtifactLanguage::SUPPORTED` only once it is complete in all three — `ArtifactStringsCoverageTest` fails the build otherwise, because a half-translated language renders English in one artifact and not the others, which is worse than not offering it.

The **pricing console is deliberately English**: its field labels, option labels, and the `economics-set` command it prints are operator controls. Never localize a control label into the strings table — the command a user copies has to match the CLI.

## Market research

`.larapilot/economics.market.yaml` (`paths.economics_market`) holds what **Jennifer** (positioning) and **Benjamin** (market) researched during `/larapilot-economics`. Larapilot computes none of it and invents none of it; it normalizes and plots what they wrote.

```bash
php artisan larapilot:economics-market-write --file=market.yaml
```

```yaml
sector: Field service management
segment: SMB installers, 5-50 seats
summary: Crowded in the mid-market, thin under €30.
competitors:
  - name: Acme Field
    plan: Starter
    price_monthly: 39
    currency: EUR
    trend: up          # up | flat | down
    change_pct: 12     # year-on-year price move, when known
    url: https://…
    notes: Bundles scheduling for free since Q1.
demand:
  pessimistic: { customers: 20,  growth_monthly_pct: 3,  churn_monthly_pct: 9, conversion_pct: 1 }
  realistic:   { customers: 120, growth_monthly_pct: 10, churn_monthly_pct: 4, conversion_pct: 2.5 }
  optimistic:  { customers: 400, growth_monthly_pct: 18, churn_monthly_pct: 2, conversion_pct: 4 }
tiers:
  - { id: base,    name: STARTER, price_monthly: 25,  share_pct: 60, features: [Jobs, Scheduling] }
  - { id: pro,     price_monthly: 59,  share_pct: 30 }
  - { id: premium, price_monthly: 129, share_pct: 10 }
risks:
  - Acme is bundling scheduling for free.
sources:
  - https://…/pricing
```

The dashboard shows the competitor table with its price trend, where the selected price line sits against the researched set (cheaper/pricier count, range, median, delta), the risks, and the sources. The file is stamped with the inputs fingerprint, so the dashboard marks the research **outdated** once the backlog moves on. Without the file the page says so and falls back to derived tiers and bent scenarios.

