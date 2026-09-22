Part of this runtime pack. The pack file is an index. Read with the editor file-read tool, never `cat`.

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

## MoSCoW Prioritization _(Functional Requirements)_

Every functional requirement in the PRD carries a **MoSCoW** priority — the per-FR scope lens that complements **Delivery Target** (macro) and backlog **Priority** (implementation order).

During **`larapilot-inception`**, **Mark** assigns MoSCoW while drafting `## Functional Requirements` (negotiate trade-offs in discovery when the target is **MVP** or **V1 Complete**). Persist on each FR as:

```markdown
### FR-001: {{REQUIREMENT}}

**MoSCoW:** Must | Should | Could | Won't
```

Use the English labels **Must**, **Should**, **Could**, **Won't** in every locale — MoSCoW is a standard acronym; requirement text stays in the detected artifact language.

| Label      | Meaning                                                                                             |
| ---------- | --------------------------------------------------------------------------------------------------- |
| **Must**   | Non-negotiable for the chosen delivery target — launch fails without it                             |
| **Should** | Important but not vital for the current target — include when target is **V1 Complete** or broader  |
| **Could**  | Desirable if time/budget allows — defer unless target is **Full Product** or **Enterprise**         |
| **Won't**  | Explicitly out of this release — document in `### Out of Scope`, not cancelled forever              |

When to tag: **all projects** — every `### FR-XXX` gets a `**MoSCoW:**` line. **Personal** — lean tagging is fine (mostly Must and Won't). **MVP / V1 Complete** — Mark must negotiate Must vs Should vs Could in the interview. **Full Product / Enterprise** — default surviving FRs to **Must**; use **Could** only for genuinely optional polish; **Won't** only with user consent.

Alignment with `## MVP Scope`: **Must** FRs → reflected in `### In Scope`; **Should**/**Could** FRs deferred under **MVP** → listed in `### Future Phases` (not silently dropped); **Won't** FRs → listed in `### Out of Scope` with brief rationale.

### Backlog mapping (`larapilot-spec`)

When bootstrapping from the PRD, read each FR's MoSCoW tag (fallback: infer from delivery target and `## MVP Scope` when a tag is missing — legacy PRDs).

| MoSCoW     | MVP                              | V1 Complete           | Full Product / Enterprise |
| ---------- | -------------------------------- | --------------------- | ------------------------- |
| **Must**   | Create spec                      | Create spec           | Create spec               |
| **Should** | Defer → Future Phases            | Create spec           | Create spec               |
| **Could**  | Defer → Future Phases            | Defer → Future Phases | Create spec               |
| **Won't**  | Skip — verify `### Out of Scope` | Skip                  | Skip                      |

Default backlog **Priority** from MoSCoW when creating specs: **Must** → `HIGH` (compliance/security-critical FRs → `CRITICAL`); **Should** → `MEDIUM`; **Could** → `LOW`. Tom/Mark may override per spec.

Downstream: **`larapilot-spec`** — primary input for bootstrap/deferral; never create specs for **Won't** FRs. **`larapilot-plan`** — plans only exist for specced FRs. **`larapilot-review`** — judge delivered scope against FR MoSCoW + delivery target.

Ownership: **Mark** assigns MoSCoW at inception and reconciles tags when extending the backlog; **Tom** preserves FR traceability in spec bodies.

## Budget Sensitivity

Budget is a default lens, not a mandatory gate. During **`larapilot-inception`**, Aurora asks the user (via **AskQuestion**, in the same round as the delivery target or right after it) whether budget should actively drive decisions — **except** for **Personal** projects, where **`Relaxed`** is the default and Aurora only asks if the user wants **Tracked**. Persist in the PRD under `## Technical Architecture` as:

```markdown
**Budget Sensitivity:** Tracked | Relaxed
```

| Mode                    | Meaning                                  | Business-lens behavior (Aurora, Benjamin, Jennifer)                                                                                                                                                                                                        |
| ----------------------- | ---------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Tracked** _(default)_ | Budget is an active constraint           | Aurora sizes infra and services against the stated budget; cost concerns can reshape or block technical choices                                                                                                                                            |
| **Relaxed**             | The user opted out of budget evaluation  | Validation is **loosened, never removed**: no cost-based vetoes, no budget interrogation — but business figures still flag order-of-magnitude cost risks, vendor lock-in, and choices that are expensive to reverse, as short advisory notes (1–2 lines)   |

Rules for all skills:

1. **Read the budget sensitivity from the PRD** (`paths.prd`) before making cost-driven recommendations. If missing, treat it as **Tracked**.
2. In **Relaxed** mode, never drop the business lens entirely — compress it to concise advisories and move on without asking budget questions.
3. The user can switch mode at any time; update the PRD line when they do.

### Security budget _(Aurora + Lars + Violet)_

1. **Security is never the first cost cut** — when Budget Sensitivity is **Tracked**, Aurora sizes Aikido, **edge WAF** (per PRD — e.g. Cloudflare when chosen), secrets management, backup, observability, and monitoring against budget but **always recommends privileging security** over nice-to-have features. If trade-offs are unavoidable, present options with security impact explicit.
2. **Lars** reviews every security-related spend for cybersecurity best practice (OWASP, supply chain, auth hardening, encryption at rest/transit).
3. **Violet** reviews security and data-processing choices against applicable regulations (GDPR, ePrivacy, sector rules) — retention, subprocessors, cross-border transfers, consent.
4. The trio collaborates at inception (PRD `## Technical Architecture`), during planning (security/infra specs), and at ship (pre-deploy gate). Aurora owns the cost frame; Lars and Violet can escalate **NO-GO** on compliance or critical security gaps regardless of budget pressure.

### SaaS, pricing & proactive infra sizing _(Aurora owns)_

Beyond budget gates, **Aurora** brings deep **SaaS product and go-to-market** literacy — pricing models, packaging, onboarding, churn signals, customer analytics, and the operational stack around them (billing, metering, support tooling; brand assets in context with **Elise** and **Lauren**).

| Area                         | Aurora's role                                                                                                                                                                                        |
| ---------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Iteration proposals**      | When opportunity arises during feature/plan/implement cycles, proactively suggests ideas on **pricing**, **product packaging**, **marketing spend**, and **customer statistics** — short advisories, not scope creep |
| **Storage & compute sizing** | Asks for **specific requirements** per tenant/workload: expected users, file uploads, retention, background jobs, peak concurrency; runs **order-of-magnitude calculations**                         |
| **Market solutions**         | Proposes **standard market options** (managed DB, object storage tiers, queue workers, CDN/cache) **or** deliberate non-standard/self-hosted paths when quality or residency demands it              |
| **Cost–quality balance**     | Optimizes infra and recurring SaaS spend **without sacrificing product quality** — flags over- and under-provisioning; pairs with **Jack** on deploy/cloud choices                                   |

Rules: record baseline sizing assumptions in PRD `## Technical Architecture` when Application/SaaS (with **John** on architecture fit); when a spec changes data volume, concurrency, or billing surfaces, Aurora revisits sizing and cost notes per **Budget Sensitivity**; in **Relaxed** mode still surface material infra risks and SaaS opportunities as 1–2 line advisories — never block on cost alone. **Jack** implements Aurora-approved infra choices; **Jennifer** and **Lauren** consume pricing/marketing proposals when the user wants to explore them.

