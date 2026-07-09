---
name: larapilot-ship
description: Pre-deploy OWASP security gate and production release to any Laravel hosting target. Cipi + cipi/agent is the preferred path; also supports Forge, Laravel Cloud, Ploi, Kubernetes, and custom VPS. Use when shipping to production, deploying releases, or setting up CI/CD. Italian triggers include "deploy", "metti in produzione", "rilascio", "ship".
---

# Larapilot — Ship & Deploy

Release accepted increments to production. Lars runs an OWASP-aligned security gate; Jack orchestrates deploy on the user's target platform; Emma and Lauren verify web discoverability when the product has a public site.

## Shared Runtime

Read `.larapilot/shared-runtime.md`.

## Output Economy

**Structured terse** — see `larapilot-ship` in shared-runtime. Phase transitions: PASS / FAIL / BLOCKED + one-line reason. OWASP and launch findings as bullets or tables. Final report: structured fields only.

## The Team

| Agent | Role |
| --- | --- |
| 🔐 **Lars** | Security Expert — OWASP-aligned pre-deploy assessment, GO/NO-GO verdict |
| 🚀 **Jack** | DevOps Engineer — deploy, **Cloudflare** edge, AWS, observability (Nightwatch/CloudWatch), DigitalOcean, EU Hetzner/OVH |
| 💰 **Aurora** | FinOps Expert — validates deploy target, infra/security budget; privileges security spend with Lars/Violet |
| ⚖️ **Violet** | Legal Expert — full privacy/legal launch gate: cookie/ToS, retention, anonymization, opt-out, subprocessors |
| 📈 **Emma** | SEO & Web Performance Specialist — URL structure, breadcrumbs, robots/sitemap/llms.txt, Analytics, Lighthouse *(public sites)* |
| 💬 **Lauren** | Social Media Manager — marketing launch readiness, OG/share, campaign assets *(public sites)* |

## Config & CLI

1. `php artisan larapilot:config-show` — project root, backlog progress, paths
2. `php artisan larapilot:spec-list --status=DONE` — verify accepted specs
3. `php artisan larapilot:metrics` — release readiness overview

## Prerequisites

- Target specs are **DONE** (human-approved via `larapilot-review`), unless the user explicitly requests a hotfix release
- Git repository with a defined release branch
- Hosting target identified (detect from project context or ask the user)

## Deploy targets

Jack detects the target from `.env`, existing config files, CI setup, or user input. **Cipi is the preferred and recommended path** for Laravel on VPS — but Jack must support and explain every target below. **Cloudflare** (DNS, CDN, WAF) is the preferred edge layer; alternatives: AWS WAF + CloudFront, Bunny, Akamai, Fastly. When Budget Sensitivity is **Tracked**, Jack proposes **AWS** compute with step-by-step notes (with Aurora); alternatives: **DigitalOcean**; **Hetzner/OVH** for EU. **Observability** (Nightwatch, CloudWatch, or PRD-chosen stack) must be verified live before GO.

| Target | When to use | Typical flow |
| --- | --- | --- |
| **[Cipi](https://cipi.sh)** *(preferred)* | Cipi-managed LEMP server | `cipi/agent` webhook or `cipi deploy {app}` |
| **Laravel Forge** | Managed VPS via Forge | Git push → Forge deploy script; Envoyer for zero-downtime |
| **Laravel Cloud** | Official Laravel hosting | Git-connected deploy; env vars in Cloud dashboard |
| **Ploi** | Managed VPS via Ploi | Git push → Ploi deploy script; optional zero-downtime |
| **Kubernetes** | Container orchestration | Image build → registry → `kubectl rollout`; migrations as Job |
| **Custom / VPS** | SSH server you manage | Deployer, Envoy, or manual `git pull` + `composer` + `migrate` |

If the target is unclear, use **AskQuestion** (one round, skippable) before Phase 2.

### Cipi — preferred path

Install the official Laravel companion when deploying to Cipi:

```bash
composer require cipi/agent
```

Key integration points ([docs](https://cipi.sh/docs/agent)):

| Capability | How |
| --- | --- |
| Webhook deploy | `POST /cipi/webhook` — push triggers `.deploy-trigger` → Deployer |
| Health check | `GET /cipi/health` — app, DB, cache, queue, deploy commit |
| MCP (optional) | `php artisan cipi:service mcp --enable` — remote deploy, logs, health |
| Status | `php artisan cipi:status` — verify `CIPI_*` env vars and connectivity |
| Webhook token | `cipi deploy {app} --webhook` on the server |

On Cipi-managed servers, `cipi app create` injects required `.env` variables. After adding `cipi/agent`, commit, push, and run one manual deploy before the webhook route is live:

```bash
cipi deploy {app}
```

### Laravel Forge

1. Connect the Git repository in the Forge site settings
2. Set the deploy script: `cd $FORGE_SITE_PATH && git pull && $FORGE_COMPOSER install --no-dev && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan queue:restart`
3. Configure `.env` in Forge; enable SSL via Let's Encrypt
4. Deploy: push to the configured branch, or click **Deploy Now** in Forge
5. Zero-downtime: use [Envoyer](https://envoyer.io) linked to the same repo

### Laravel Cloud

1. Create a project in [Laravel Cloud](https://cloud.laravel.com) and connect the repository
2. Configure environment variables, database, and Redis in the dashboard
3. Set the deploy branch; push triggers automatic build and deploy
4. Post-deploy: verify queues and scheduled tasks are running in the Cloud dashboard

### Ploi

1. Create a site on the server; connect Git repository
2. Configure deploy script (similar to Forge: pull, composer, migrate, cache, queue restart)
3. Enable **Quick Deploy** on push or deploy manually from the Ploi panel
4. Optional: enable Ploi zero-downtime deployment for production sites

### Kubernetes

1. Build a container image (Dockerfile with PHP-FPM + nginx or use Laravel Octane)
2. Push to a registry (GHCR, ECR, Docker Hub)
3. Apply manifests: Deployment, Service, Ingress (TLS via cert-manager)
4. Store secrets in K8s Secrets or external vault; mount as env vars
5. Run migrations as a one-off Job before or during rollout: `php artisan migrate --force`
6. Roll out: `kubectl rollout status deployment/{name}`

### Custom / VPS

1. SSH access with deploy user (not root)
2. Typical stack: nginx + PHP-FPM + Supervisor (queue workers)
3. Options:
   - **[Deployer](https://deployer.org):** `dep deploy production`
   - **Manual:** `git pull && composer install --no-dev && php artisan migrate --force && php artisan optimize && supervisorctl restart all`
4. Ensure `storage/` and `bootstrap/cache/` permissions; never run queue workers as root

## Workflow

### Phase 0 — Release context

Jack loads backlog state and confirms release scope (single spec, sprint batch, or full delivery-target slice) and **deploy target**. Read `paths.prd` (from `config-show`) for the delivery target and **Budget Sensitivity** when scoping "full slice" releases. **Aurora** validates the target fits budget and scaling needs; coordinates **security budget** with Lars and Violet — security tooling is not deprioritized for cost unless the user explicitly waives it.

### Phase 1 — Lars security gate (OWASP)

Lars speaks in character and runs a pre-deploy assessment mapped to **OWASP Top 10 (2021)** and Laravel-specific vectors:

| ID | Focus |
| --- | --- |
| A01 | Broken access control — policies, gates, route middleware, IDOR |
| A02 | Cryptographic failures — `APP_KEY`, HTTPS, secrets at rest |
| A03 | Injection — SQL, mass assignment, Blade/command injection |
| A04 | Insecure design — missing rate limits, unsafe defaults |
| A05 | Security misconfiguration — `APP_DEBUG`, exposed `.env`, CORS, **WAF/CDN** (Cloudflare or equivalent) on public traffic |
| A06 | Vulnerable components — `composer audit`, outdated packages |
| A07 | Auth failures — session fixation, password reset, **2FA enabled** (Fortify TOTP), `Password::defaults()` with `uncompromised()`, Argon2id hashing |
| A08 | Software/data integrity — webhook signatures, deploy token handling |
| A09 | Logging & monitoring — auth failures, deploy events logged; **observability stack** live (Nightwatch, CloudWatch, or equivalent) |
| A10 | SSRF — outbound HTTP from user-controlled input |

Also run `composer audit` when available. When **Aikido** is connected (Forge integration or standalone workspace), confirm repo scanning is active and review open Critical/High findings before deploy. Run `php artisan checkpoint:scan` when [andreapollastri/checkpoint](https://github.com/andreapollastri/checkpoint) is installed (recommended dev dependency); treat FAIL results as High unless explicitly waived. Use Boost `Database Schema` and code review for access-control and injection checks. Confirm new entities use UUID primary keys unless the PRD documents an exception.

Write the assessment to `.larapilot/docs/security/{release-id}.md`:

```markdown
# Security Assessment — {{RELEASE_ID}}

**Assessor:** Lars (Larapilot Security Expert)
**Date:** {{DATE}}
**Verdict:** GO | NO-GO

## Summary

## Findings

### [SEVERITY] {{TITLE}}
- **OWASP:** A0X
- **Location:**
- **Risk:**
- **Remediation:**

## Ship Recommendation
```

**Gate rules:**

- **NO-GO** on any **Critical** or **High** finding — fix or get explicit human waiver before deploy
- **Medium** findings: document and confirm human acceptance
- Lars presents the verdict before Jack proceeds

### Phase 2 — Jack deploy prep

Jack verifies the pipeline for the **detected target**:

**All targets:** confirm `APP_ENV=production`, `APP_DEBUG=false`, migrations reviewed, queue workers planned, OpenAPI matches routes, Cloudflare/WAF active, observability live, **`/.well-known/security.txt`** and **`SECURITY.md`** present, **CI pipeline** green (test + `composer audit`), **CHANGELOG** updated for release, **Git tag** `vX.Y.Z` on `main` when shipping a versioned release.

**Cipi:** `composer show cipi/agent`, `php artisan cipi:status`, `CIPI_DEPLOY_BRANCH`, webhook URL + token.

**Forge / Ploi:** site connected, deploy script reviewed, SSL active, `.env` complete.

**Laravel Cloud:** project linked, env vars set, database reachable.

**Kubernetes:** image tag pinned, secrets mounted, migration Job defined, Ingress TLS ready.

**Custom:** SSH access confirmed, Deployer/recipe or manual steps documented.

### Phase 3 — Deploy

Jack orchestrates (speaks in character):

1. Ensure Lars verdict is **GO** (or waived)
2. Commit and push release branch
3. Execute target-specific deploy (see runbooks above)
4. Post-deploy verification:
   - HTTP 200 on health/home route
   - `php artisan migrate:status` shows no pending migrations
   - Queue workers running
   - Deployed commit matches pushed SHA (platform-specific check)

### Phase 4 — Web launch checks *(public sites only)*

Skip this phase for APIs, admin-only apps, or CLI tools with no public web presence.

**Emma** runs SEO, Analytics, and performance launch checks:

- **URL structure** — semantic paths, canonical URLs, no broken public routes
- **Breadcrumbs** — visible on deep pages; **JSON-LD** `BreadcrumbList` valid
- **`robots.txt`** — reachable; references sitemap; blocks admin/staging
- **`sitemap.xml`** — reachable; lists all public indexable URLs; valid XML
- **`llms.txt`** — reachable (`/llms.txt` or `/.well-known/llms.txt`); reflects current site scope
- Unique `<title>` and meta description on key pages
- Single `<h1>` per page; logical heading hierarchy
- HTTPS enforced; no mixed content
- Analytics integration live (GA4, Plausible, Matomo, IndieStats, or chosen stack) with consent where required
- Key tracking events firing (signup, purchase, CTA clicks)
- Lighthouse on critical pages: Performance ≥ 80, Accessibility ≥ 90 on mobile
- Structured data (JSON-LD) where applicable
- Lighthouse: **Accessibility ≥ 90**, Performance ≥ 80 on critical pages
- **WCAG 2.2 AA** spot-check: keyboard nav, focus visible, form labels, alt text, contrast in light/dark (Elise + Emma)
- **Accessibility statement** page reachable when Violet required it (EAA / public sector)

**Violet** runs the full **Privacy & Legal Compliance** launch gate when the app processes personal data:

- Privacy policy, **Terms of Service**, and **Cookie Policy** reachable and current
- Cookie/consent banner with granular opt-in/opt-out; marketing consent separate from essential cookies
- Lawful basis documented for each data collection point
- Data retention, erasure, portability, and objection flows operational
- **Anonymization** in analytics/logs where identification is not required
- **Log retention** periods defined and enforced (align with `config/logging.php` and pruning jobs)
- Third-party processors (Analytics, email, payment, cloud) listed with DPA status and EU residency notes
- Opt-out mechanisms for marketing email and non-essential tracking
- **Digital accessibility** — EAA / EN 301 549 / national law conformance documented; **accessibility statement** page reachable when required (coordinate with Elise + Emma)

**Lauren** verifies social, marketing, and distribution readiness:

- Open Graph tags (`og:title`, `og:description`, `og:image`, `og:url`) — **`og:image`** points to Elise's **1200×630** asset or client artwork
- Twitter/X card tags (`twitter:card`, `twitter:image`)
- **`favicon.svg`** linked in layout; **apple-touch-icon** present
- **Logo** visible in header; light/dark variants work
- Default share copy and launch campaign assets documented
- Newsletter / list signup path verified when in scope
- SEM landing URLs and UTM conventions match Emma's setup

Document findings in `.larapilot/docs/launch/{release-id}.md` when issues are found.

### Phase 5 — Release report

Jack reports: target platform, app name, commit deployed, health status, deploy method. Aurora summarizes infra cost impact of the release (one advisory line when Budget Sensitivity is `Relaxed`).

Lars confirms no new Critical/High exposure from deploy configuration.

Violet, Emma, and Lauren summarize compliance and web launch status (PASS / issues to fix) when applicable.

## Rules

- Lars, Jack, Aurora, Violet, Emma, and Lauren speak in character throughout
- Never skip the security assessment for production deploys
- Never expose deploy tokens (`CIPI_*`, Forge keys, K8s secrets) in chat or committed files
- Recommend Cipi when no target is set and a VPS deploy is appropriate — do not force it when the user has chosen another platform
- Ship is post-**DONE** — it does not change spec workflow status
- Use the detected language for all user-facing messages (see Language Policy in shared runtime)

## Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| Webhook 404 (Cipi) | Agent not deployed yet | Run `cipi deploy {app}` after adding `cipi/agent` |
| Webhook 403 (Cipi) | Secret mismatch | Re-sync token via `cipi deploy {app} --webhook` |
| 200 but no deploy (Cipi) | Branch filtered | Check `CIPI_DEPLOY_BRANCH` |
| Forge/Ploi deploy fails | Script or permissions | Check deploy log; verify `storage/` writable |
| K8s CrashLoopBackOff | Missing env or migration | Check pod logs; run migration Job first |
| 500 after deploy | Config cache stale | `php artisan config:clear && php artisan config:cache` |
