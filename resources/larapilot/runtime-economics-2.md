Part of this runtime pack. The pack file is an index. Read with the editor file-read tool, never `cat`.

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
