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
| `saas.*` | List price, churn, growth, infra, CAC, target customers (`price_annual` 0 = not decided, derived from the monthly price) |

`economics-set` refuses a non-numeric or out-of-range flag instead of casting it to 0: `--hourly-rate` 1–5000, `--margin` 0–300, `--churn` / `--maintenance` 0–100, `--payment-fee` 0–50, `--growth` 0–200, `--hours-per-day` 1–24, `--billable-days` 1–366, `--currency` a 3-letter ISO code. Changing `--country` moves the regime to that country's default unless `--regime` is passed in the same call.

`economics-show` returns the computed snapshot (quote, tax, payback, SaaS). Dashboard: `/larapilot/economics`. JSON: `GET /larapilot/api/economics`. Internal tax report: `--format=md`.

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
3. AskQuestion: country → regime (from `data.regime.options`) → hourly rate / margin / overhead → product model → SaaS prices when SaaS.
4. Persist with `larapilot:economics-set` (only answered keys).
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
```

Changing the country resets the regime to that country's default unless `--regime` is passed in the same call.
