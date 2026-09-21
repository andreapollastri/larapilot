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

## Profile file

`.larapilot/economics.yaml` (path `paths.economics`) holds the **account profile**. Persist only through `larapilot:economics-set`. Keys:

The **computed snapshot** (quote, tax, effort, sales, scenarios, SaaS forecast) is written automatically to `.larapilot/economics.snapshot.yaml` (`paths.economics_snapshot`) whenever `economics-show`, the dashboard, the API, or `economics-set` runs — so the last preventivo stays on disk and refreshes when specs or plans change.

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
| `saas.*` | List price, churn, growth, infra, CAC, target customers |

`economics-show` returns the computed snapshot (quote, tax, payback, SaaS). Dashboard: `/larapilot/economics`. JSON: `GET /larapilot/api/economics`. Client-facing Markdown proposal (PRD language, totals, maintenance, Gantt): `php artisan larapilot:economics-show --format=quote` or `/larapilot/economics/quote.md`. Internal tax report: `--format=md`.

## How the quote is built

1. **Hours** — per spec: sum plan-task `estimate_hours` when the plan exists; otherwise story points × hours/point for that spec only (`ECO` 3, `STANDARD` 4, `MAX` 5.5). If nothing is sized yet, a delivery-target heuristic applies.
2. **Multipliers** — delivery/kind/type multipliers apply **only to the heuristic fallback**. Spec-backed hours (`plan_hours`, `story_points`, `mixed`) get the 15% PM/QA buffer only — no double inflation.
3. **Labor** = hours × hourly rate. **Overhead** = monthly overhead × calendar months (+ allocated compliance).
4. **Margin** on that direct cost. **Gross** is the client price ex VAT. VAT applies unless the regime is exempt (Italian forfettario, US federal, French micro).
5. **Tax** — `TaxEngine` + FY-2026 catalogue. Italy forfettario: INPS Gestione Separata deducted from substitute-tax base. Italy SRL: IRES + IRAP (production value) + Gestione Commercianti for working shareholders + legal reserve + optimised director pay / dividends. Net to owner is after all taxes, social, retained reserve, and operating costs (compliance is shown separately).
6. **Payback** — utilization vs annual capacity; projects/year at capacity; for SaaS, customers needed to recover the build in 12/18/24 months.

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
3. AskQuestion: country → regime (from `data.regime.options`) → hourly rate / margin / overhead → product model → SaaS prices when SaaS.
4. Persist with `larapilot:economics-set` (only answered keys).
5. Re-run `economics-show` and summarise: client price, net to owner, effective tax %, and (if SaaS) break-even customers + ARR.
6. Point at `/larapilot/economics` for charts. Never invent tax rates — the catalogue is the source of truth.

## CLI

```bash
php artisan larapilot:settings-set --account=FREELANCE
php artisan larapilot:economics-set --country=IT --regime=forfettario_15 --hourly-rate=55
php artisan larapilot:economics-set --product-model=saas --price-monthly=29 --churn=4 --target-customers=80
php artisan larapilot:economics-show
php artisan larapilot:economics-show --format=md
php artisan larapilot:economics-show --format=quote
```
