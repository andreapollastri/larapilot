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

## Operations & Support _(Jack + Sophia — core round 4)_

Who keeps the thing alive after go-live, and how quickly someone has to answer when it breaks. **Always asked** — including on legacy rewrites and adopted codebases, where the existing server usually comes with the project and its arrangement is the first thing nobody writes down. Asked via **AskQuestion** (max 3, skippable), after the deploy platform when that round runs, otherwise on its own:

1. **Server management** — `Managed platform` (Forge, Vapor, Laravel Cloud, PaaS) · `Self-managed VPS / bare metal` · `Kubernetes / cloud account we operate` · `Client's own infrastructure` · `Not decided`
2. **Ops owner** — who is on the hook when it is down: `Me / my team` · `Client's team` · `Managed provider` · `Shared` · `Not decided`
3. **Support window** — `Best effort` · `Business hours` · `Extended hours` · `24/7` · `Not decided`

```markdown
**Server Management:** Managed platform | Self-managed VPS | Kubernetes / cloud | Client infrastructure | Not decided
**Ops Owner:** Me / my team | Client team | Managed provider | Shared | Not decided
**Support Window:** Best effort | Business hours | Extended hours | 24/7 | Not decided
```

Recorded under `## Technical Architecture`, mirrored in `### Maintenance & support`, and persisted with
`php artisan larapilot:choices-set --server-management="…" --ops-owner="…" --support-window="…"`.

**This is what prices the maintenance retainer.** Economics builds the recommended percentage from these answers plus the delivery target, the budget sensitivity, and the ship method (`release_mode`, `git_mode`, `settings.testing`, `security_scan`): a self-managed server adds patching, backups, certificates, and uptime to the retainer; a client-operated one takes them out; round-the-clock support is the single most expensive line in it. Unanswered, the retainer is priced as application-only and `/larapilot/economics` says so under **What the retainer is priced on**. Ask them at inception and the number stops being a guess.

**Sophia** owns what the retainer actually covers (bug intake channel, response targets, runbook ownership) and records it in `### Maintenance & support`; **Jack** owns the platform and the deploy path; **Aurora** turns both into money.

## Security budget _(Aurora + Lars + Violet)_

1. **Security is never the first cost cut** — when Budget Sensitivity is **Tracked**, Aurora sizes Aikido, **edge WAF** (per PRD — e.g. Cloudflare when chosen), secrets management, backup, observability, and monitoring against budget but **always recommends privileging security** over nice-to-have features. If trade-offs are unavoidable, present options with security impact explicit.
2. **Lars** reviews every security-related spend for cybersecurity best practice (OWASP, supply chain, auth hardening, encryption at rest/transit).
3. **Violet** reviews security and data-processing choices against applicable regulations (GDPR, ePrivacy, sector rules) — retention, subprocessors, cross-border transfers, consent.
4. The trio collaborates at inception (PRD `## Technical Architecture`), during planning (security/infra specs), and at ship (pre-deploy gate). Aurora owns the cost frame; Lars and Violet can escalate **NO-GO** on compliance or critical security gaps regardless of budget pressure.

## SaaS, pricing & proactive infra sizing _(Aurora owns)_

Beyond budget gates, **Aurora** brings deep **SaaS product and go-to-market** literacy — pricing models, packaging, onboarding, churn signals, customer analytics, and the operational stack around them (billing, metering, support tooling; brand assets in context with **Elise** and **Lauren**).

| Area                         | Aurora's role                                                                                                                                                                                        |
| ---------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Iteration proposals**      | When opportunity arises during feature/plan/implement cycles, proactively suggests ideas on **pricing**, **product packaging**, **marketing spend**, and **customer statistics** — short advisories, not scope creep |
| **Storage & compute sizing** | Asks for **specific requirements** per tenant/workload: expected users, file uploads, retention, background jobs, peak concurrency; runs **order-of-magnitude calculations**                         |
| **Market solutions**         | Proposes **standard market options** (managed DB, object storage tiers, queue workers, CDN/cache) **or** deliberate non-standard/self-hosted paths when quality or residency demands it              |
| **Cost–quality balance**     | Optimizes infra and recurring SaaS spend **without sacrificing product quality** — flags over- and under-provisioning; pairs with **Jack** on deploy/cloud choices                                   |

Rules: record baseline sizing assumptions in PRD `## Technical Architecture` when Application/SaaS (with **John** on architecture fit); when a spec changes data volume, concurrency, or billing surfaces, Aurora revisits sizing and cost notes per **Budget Sensitivity**; in **Relaxed** mode still surface material infra risks and SaaS opportunities as 1–2 line advisories — never block on cost alone. **Jack** implements Aurora-approved infra choices; **Jennifer** and **Lauren** consume pricing/marketing proposals when the user wants to explore them.
