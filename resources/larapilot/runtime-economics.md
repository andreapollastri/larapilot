# Larapilot — Economics runtime

Loaded by **`/larapilot-economics`** and by **`/larapilot-settings`** when `settings.account` is not `NONE`. Aurora (💰 FinOps) owns the numbers; Lucille supplies hours; Mark supplies inception answers.

These are **planning estimates** from statutory FY-2026 rates. They are not personalised tax advice and they do not file a return.

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

## The client quote document

The downloadable quote is a **document written by `/larapilot-economics`**, like the PRD — so it speaks whatever language the PRD speaks, not only the languages Larapilot ships strings for.

| | |
| --- | --- |
| File | `.larapilot/docs/quote.md` (`paths.economics_quote`) |
| Written by | `php artisan larapilot:economics-quote-write --file=… --lang=…` |
| Download | `/larapilot/economics/quote.md`, dashboard **Download quote**, or `economics-show --format=quote` |
| Fallback | A built-in template in `en` / `it` / `es` / `fr` renders the download while no document exists |

Larapilot stamps front matter on the stored document: `lang`, `generated_at`, and the `inputs` fingerprint it was written against. When the backlog, plans, or PRD move on, the dashboard marks the document **outdated** and asks for a rewrite — the numbers in a client document are never silently patched.

Content rules (enforced socially by the skill, not by the engine): commercial register, no story points or task ids, no tax or margin figures, effort in working days and elapsed months, and every amount taken from the snapshot. The engine only checks that the document has a level-1 heading and enough substance to be a client document.

Two chapters sit between the capability list and the price, where value belongs:

- **Infrastructure and hosting** — rendered **only when inception decided one** (`deploy_platform`, `server_management`, `ops_owner`, `support_window`). Platform, who manages the server, who is on the hook, backups, monitoring, certificates and domain, support window, and the estimated running cost. What the document may promise follows who operates: we run the server → daily backups with restore testing, uptime and error alerting, automatic TLS renewal; managed platform → the platform covers the machine; the client's IT → those lines are theirs. It closes on two facts — the running cost is billed by the provider and is not in the build price, and the environment, domain, and data stay in the client's name. Nothing decided, no chapter: the quote never invents a hosting arrangement.
- **Security and quality** — the chapter that sells what a demo cannot show, in a buyer's language. Every claim is derived from what this project's settings actually do: the testing bar from `settings.testing` (`BEST` / `NORMAL` / `MINIMAL`), the security scan only under `security_scan: YES`, the traceable history only under `git_mode: GITFLOW`, numbered releases only under `release_mode: YES`, and the GDPR line only when the PRD says personal data is in scope. Independent review, acceptance criteria, OWASP practice, no credentials in the code, and security updates inside the retainer are always true of a Larapilot delivery. It closes on ownership and no lock-in.

Language detection exists only for prose **Larapilot itself writes**: the built-in fallback template, the design presentation index, and the download filename. It reads **function-word frequency** from the PRD, so an Italian PRD full of English technical vocabulary is still Italian, and falls back to English when there is no PRD to read. A document written by an agent carries its own `lang` and never goes through detection.

## How the quote is built

1. **Hours** — per spec, in this order: sum plan-task `estimate_hours` when the plan exists; otherwise story points × hours-per-point for that spec only; otherwise (no plan, no points) the spec counts as 3 points so it is never free. `effort.breakdown` lists every spec with the source of its hours. If the backlog is empty, a scope heuristic applies.
2. **Hours per point** — `ECO` 3, `STANDARD` 4, `MAX` 5.5, but once at least two specs with points carry plans, the rate those plans imply replaces the constant (clamped to 0.5–12h). `effort.hours_per_point_source` says which was used.
3. **Multipliers** — delivery/kind/type multipliers apply **only to the heuristic fallback** (100h floor × kind × delivery target × product type), each exactly once. Spec-backed hours (`plan_hours`, `story_points`, `mixed`) get the 15% PM/QA buffer only — no double inflation.
4. **Warnings** — `effort.warnings` flags unsized specs, an unsized backlog, a calibration that disagrees with the effort setting, and any scope beyond one person-year of the configured capacity.
5. **Labor** = hours × hourly rate. **Overhead** = monthly overhead × calendar months (+ allocated compliance).
6. **Margin** on that direct cost. **Gross** is the client price ex VAT. VAT applies unless the regime is exempt (Italian forfettario, US federal, French micro).
7. **Tax** — `TaxEngine` + FY-2026 catalogue. Italy forfettario: INPS Gestione Separata deducted from substitute-tax base. Italy SRL: IRES + IRAP (production value) + Gestione Commercianti for working shareholders + legal reserve + optimised director pay / dividends. Contributions are charged only when the owner actually works in the company (`owner_working`).
8. **What you keep** — `total_tax` is tax + contributions; `total_withheld` adds compliance (accountant, filings) and any retained legal reserve. The identity always holds: `net_to_owner = revenue − operating costs − total_withheld`. A price that cannot carry its own costs returns a negative net with `loss: true` instead of a reassuring zero. `effective_rate_pct` is tax over revenue; `withheld_rate_pct` is everything over revenue.
9. **Payback** — utilization vs annual capacity (above 100% when the project does not fit in a year), fractional projects/year at capacity, and for SaaS the customers needed to recover the build in 12/18/24 months.

## SaaS

When `product_model` is `saas`, or inception / PRD mention SaaS, subscription, MRR, or ARR:

- **Break-even customers** cover hosting + maintenance + allocated overhead.
- **Customers to recover** the build in 12 / 18 / 24 months.
- **ARR / MRR**, contribution margin, LTV, CAC (default 28% of LTV), LTV:CAC.
- **Leads** from the conversion % to reach the planning customer count.
- **Infra** inferred from the PRD deploy platform (Forge, Vapor, VPS, AWS, …) unless `saas.infrastructure_monthly` is set.
- **36-month forecast** applies growth and churn; green on the dashboard when cumulative net has repaid the build.

## Skill contract

1. `larapilot:config-show` — require `data.settings.account` ≠ `NONE` (else stop and send the user to `/larapilot-settings`).
2. `larapilot:economics-show` — current snapshot (empty profile still computes on catalogue defaults).
3. AskQuestion: country → regime (from `data.regime.options`) → hourly rate / margin / overhead / discount / team size → product model → SaaS prices when SaaS.
4. Persist with `larapilot:economics-set` (only answered keys). For a subscription product, research the market with Jennifer and Benjamin and persist it with `larapilot:economics-market-write`.
5. Re-run `economics-show` and summarise: client price, net to owner, effective tax %, effort source + `effort.warnings`, and (if SaaS) break-even customers + ARR.
6. Write the client document in the PRD language with `larapilot:economics-quote-write` — commercial register, numbers from the snapshot only.
7. Point at `/larapilot/economics` for the per-spec effort table and charts. Never invent tax rates — the catalogue is the source of truth.

## CLI

```bash
php artisan larapilot:settings-set --account=FREELANCE
php artisan larapilot:economics-set --country=IT --regime=forfettario_15 --hourly-rate=55
php artisan larapilot:economics-set --product-model=saas --price-monthly=29 --churn=4 --target-customers=80
php artisan larapilot:economics-show
php artisan larapilot:economics-show --format=md
php artisan larapilot:economics-show --format=quote
php artisan larapilot:economics-quote-write --file=quote.md --lang=it
php artisan larapilot:economics-market-write --file=market.yaml
```

Changing the country resets the regime to that country's default unless `--regime` is passed in the same call.
