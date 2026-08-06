# Larapilot Runtime — Ship

Phase pack for **`larapilot-ship`**. Read `.larapilot/shared-runtime.md` (core) first. The ship skill keeps the phase sequence and AskQuestion gates; this file holds the canonical platform tables, runbooks, security assessment, and launch checklists.

## Infrastructure & Cloud _(Jack + Aurora own)_

**Never impose deploy target, edge provider, or cloud vendor by default.** **Jack** asks via **AskQuestion** during inception (downstream skills ask only if the PRD omits a choice). After the user's answers, **recommend AWS** for compute/data and **Cloudflare** for edge when feasible — existing stack, compliance, EU residency, budget, and delivery target may favor alternatives. Record each choice in the PRD under `## Technical Architecture` so all skills honor it instead of re-imposing defaults.

### Deploy platform _(Jack)_

| Option                   | When to recommend                                                                                            |
| ------------------------ | --------------------------------------------------------------------------------------------------------------|
| **Cipi**                 | Laravel VPS with `cipi/agent` webhook deploy — see [cipi.sh](https://cipi.sh)                                  |
| **Laravel Forge**        | Managed VPS, Git push deploy, Forge integrations (Aikido, …)                                                   |
| **Laravel Cloud**        | Official Laravel PaaS, Git-connected deploy                                                                    |
| **Ploi**                 | Managed VPS alternative to Forge                                                                               |
| **AWS** (ECS/EC2/Lambda) | Scalable compute with RDS/ElastiCache — **recommend when Tracked budget and scale needs make it feasible**     |
| **Kubernetes**           | Container orchestration at scale                                                                               |
| **DigitalOcean**         | Budget-conscious Droplets / App Platform / Managed DB                                                          |
| **Hetzner / OVH**        | EU data residency, cost-efficient VPS/cloud                                                                    |
| **Not defined yet**      | Defer deploy scaffolding until `larapilot-ship` or implementation bootstrap                                    |
| **Other**                | Custom VPS, GCP, Azure, Scaleway, existing team pipeline, …                                                    |

### Edge, CDN & WAF _(Jack + Lars)_

**Never assume Cloudflare.** Ask the user; **recommend Cloudflare** for public-facing apps when feasible (DNS, CDN, WAF, DDoS in one layer). Pair **AWS WAF + CloudFront** when the PRD chose an AWS-native stack.

| Option                            | Notes                                                                                                                                                          |
| --------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Cloudflare**                    | **Recommend when feasible** — document DNS cutover, SSL mode, cache rules, WAF managed rules; configure Laravel **trusted proxies** for Cloudflare IP ranges     |
| **AWS WAF + CloudFront**          | When compute is AWS-native or the user prefers AWS edge                                                                                                          |
| **Bunny CDN / Shield**            | Lightweight CDN + WAF alternative                                                                                                                                |
| **Akamai / Fastly**               | Enterprise / high-traffic edge                                                                                                                                   |
| **Existing provider / no change** | Brownfield — document current edge, do not rip-and-replace without user consent                                                                                  |
| **Not defined yet**               | Plan edge tasks at ship; Lars still requires WAF on public production traffic when budget allows                                                                 |
| **N/A (internal only)**           | Admin/API with no public web edge — Lars documents residual risk                                                                                                 |

**WAF is not optional** for production public apps when budget allows — at minimum OWASP Core Ruleset, bot management, and geo/rate limits on auth and API routes. Lars validates rule coverage against OWASP A05/A07. When Cloudflare or an equivalent edge is unsuitable, present **alternatives with the same capabilities** — never leave production exposed without edge protection when budget allows. **Cloudflare R2** remains a valid object-storage option in the optional-integrations table (`runtime-delivery.md`).

### Cloud / compute & data _(Jack + Aurora)_

**Never assume AWS.** Ask which provider backs managed compute, database, cache, object storage, and queues when not already fixed by the deploy platform.

| Option                         | When to recommend                                                                                                                                                                                                             |
| ------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **AWS**                        | **Recommend when Tracked budget and requirements make it feasible** — EC2/ECS/Lambda, RDS/Aurora, ElastiCache, S3, SES, SQS, Cognito, Secrets Manager; pair **AWS WAF + CloudFront** at edge when Cloudflare was not chosen       |
| **DigitalOcean**               | Droplets, Managed DB, Spaces, Kubernetes — global / budget-conscious                                                                                                                                                              |
| **Hetzner / OVH**              | EU data residency — **Violet** reviews subprocessors                                                                                                                                                                              |
| **Bundled with deploy target** | Forge, Cipi, Laravel Cloud, or Ploi host includes compute — record "bundled" and skip duplicate cloud scaffolding                                                                                                                 |
| **Not defined yet**            | Defer managed-service wiring until the user decides                                                                                                                                                                               |
| **Other**                      | GCP, Azure, Scaleway, Linode, on-prem, …                                                                                                                                                                                          |

Jack stays **open to other providers** when the PRD, compliance, or user preference requires it. **Aurora** validates every proposal against **Budget Sensitivity**; **Violet** flags EU residency and subprocessors when personal data is involved.

### Observability _(Jack + John)_

**Propose** an observability stack scaled to the delivery target; **ask via AskQuestion** when the PRD does not record a choice and the stack is not inferable from deploy/cloud answers. Plan it in architecture, plan tasks, and ship verification — not as an afterthought.

| Tier                          | Propose                                                                                                                                        |
| ----------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------|
| **Preferred (Laravel)**       | **[Laravel Nightwatch](https://nightwatch.laravel.com/)** — Laravel-native monitoring, logs, exceptions, performance                              |
| **Preferred (AWS stack)**     | **AWS CloudWatch** — metrics, logs, alarms, dashboards; X-Ray for traces when needed                                                              |
| **Alternatives**              | Datadog, New Relic, Grafana Cloud, Better Stack, OpenTelemetry collectors, Sentry (errors + performance)                                          |
| **Lightweight / self-hosted** | Laravel **Pulse** (dev/small prod), self-hosted Grafana + Prometheus, [boogle](https://github.com/andreapollastri/boogle) for errors/uptime       |

Coverage to plan: **application** (exceptions, slow queries, queue latency, failed jobs); **infrastructure** (CPU, memory, disk, HTTP 5xx, SSL cert expiry); **alerting** (PagerDuty, Slack, email, or CloudWatch alarms on error-rate spikes and downtime); **logs** (centralized retention aligned with Violet's policy; structured JSON where possible).

Ownership: **Jack** owns provider selection (per PRD choices), deploy runbooks, edge setup, and observability wiring; **Sarah** owns shell/deploy-hook scripts, systemd/cron, SSH/rsync glue, and CI deploy job scripts that those runbooks invoke; **Aurora** owns cost fit; **John** aligns architecture to cloud primitives and ensures apps emit observable signals.

## Deploy Runbooks _(Jack orchestrates — Sarah scripts; run only the runbook matching the recorded choice)_

### Cipi

Install the official Laravel companion: `composer require cipi/agent` ([docs](https://cipi.sh/docs/agent)).

| Capability     | How                                                                    |
| -------------- | ------------------------------------------------------------------------|
| Webhook deploy | `POST /cipi/webhook` — push triggers `.deploy-trigger` → Deployer       |
| Health check   | `GET /cipi/health` — app, DB, cache, queue, deploy commit               |
| MCP (optional) | `php artisan cipi:service mcp --enable` — remote deploy, logs, health   |
| Status         | `php artisan cipi:status` — verify `CIPI_*` env vars and connectivity   |
| Webhook token  | `cipi deploy {app} --webhook` on the server                             |

On Cipi-managed servers, `cipi app create` injects required `.env` variables. After adding `cipi/agent`, commit, push, and run one manual deploy (`cipi deploy {app}`) before the webhook route is live.

### Laravel Forge

1. Connect the Git repository in the Forge site settings
2. Deploy script: `cd $FORGE_SITE_PATH && git pull && $FORGE_COMPOSER install --no-dev && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan queue:restart`
3. Configure `.env` in Forge; enable SSL via Let's Encrypt
4. Deploy: push to the configured branch, or click **Deploy Now**
5. Zero-downtime: use [Envoyer](https://envoyer.io) linked to the same repo

### Laravel Cloud

1. Create a project in [Laravel Cloud](https://cloud.laravel.com) and connect the repository
2. Configure environment variables, database, and Redis in the dashboard
3. Set the deploy branch; push triggers automatic build and deploy
4. Post-deploy: verify queues and scheduled tasks are running in the Cloud dashboard

### Ploi

1. Create a site on the server; connect the Git repository
2. Configure the deploy script (similar to Forge: pull, composer, migrate, cache, queue restart)
3. Enable **Quick Deploy** on push or deploy manually from the Ploi panel
4. Optional: enable Ploi zero-downtime deployment for production sites

### Kubernetes

1. Build a container image (Dockerfile with PHP-FPM + nginx or Laravel Octane)
2. Push to a registry (GHCR, ECR, Docker Hub)
3. Apply manifests: Deployment, Service, Ingress (TLS via cert-manager)
4. Store secrets in K8s Secrets or an external vault; mount as env vars
5. Run migrations as a one-off Job before or during rollout: `php artisan migrate --force`
6. Roll out: `kubectl rollout status deployment/{name}`

### Custom / VPS

1. SSH access with a deploy user (not root)
2. Typical stack: nginx + PHP-FPM + Supervisor (queue workers)
3. Options: **[Deployer](https://deployer.org)** (`dep deploy production`) or manual `git pull && composer install --no-dev && php artisan migrate --force && php artisan optimize && supervisorctl restart all`
4. Ensure `storage/` and `bootstrap/cache/` permissions; never run queue workers as root

### Per-target deploy prep

**All targets:** confirm `APP_ENV=production`, `APP_DEBUG=false`, migrations reviewed, queue workers planned, OpenAPI matches routes, **edge/WAF per PRD** active on public traffic (Lars may waive only with explicit human acceptance), observability live, **`/.well-known/security.txt`** and **`SECURITY.md`** present, **CI pipeline** green (test + `composer audit`), **CHANGELOG** updated for the release, **Git tag** `vX.Y.Z` on `main` when shipping a versioned release.

- **Cipi:** `composer show cipi/agent`, `php artisan cipi:status`, `CIPI_DEPLOY_BRANCH`, webhook URL + token.
- **Forge / Ploi:** site connected, deploy script reviewed, SSL active, `.env` complete.
- **Laravel Cloud:** project linked, env vars set, database reachable.
- **Kubernetes:** image tag pinned, secrets mounted, migration Job defined, Ingress TLS ready.
- **Custom:** SSH access confirmed, Deployer/recipe or manual steps documented.

### Troubleshooting

| Symptom                  | Likely cause            | Fix                                                    |
| ------------------------ | ----------------------- | --------------------------------------------------------|
| Webhook 404 (Cipi)       | Agent not deployed yet  | Run `cipi deploy {app}` after adding `cipi/agent`        |
| Webhook 403 (Cipi)       | Secret mismatch         | Re-sync token via `cipi deploy {app} --webhook`          |
| 200 but no deploy (Cipi) | Branch filtered         | Check `CIPI_DEPLOY_BRANCH`                               |
| Forge/Ploi deploy fails  | Script or permissions   | Check deploy log; verify `storage/` writable             |
| K8s CrashLoopBackOff     | Missing env or migration | Check pod logs; run migration Job first                 |
| 500 after deploy         | Config cache stale      | `php artisan config:clear && php artisan config:cache`   |

## Security Assessment _(Oliver red team → Lars OWASP gate)_

### Red-team scope _(Oliver — pre-ship pass, minimum)_

Oliver performs an ethical-hacking pass on the staging or pre-production URL (and key API endpoints) to find exploitable flaws the blue-team review might miss:

- Authentication bypass, session hijacking, privilege escalation
- IDOR and horizontal/vertical access-control gaps
- Injection (SQL, XSS stored/reflected, command, SSTI)
- CSRF on state-changing routes; webhook signature bypass
- File upload abuse; path traversal
- Rate-limit and brute-force resistance on auth/API
- SSRF on outbound integrations (**Matt**'s webhooks/APIs)
- Information disclosure (debug endpoints, verbose errors, `.env` leaks)

Report to `{paths.security}/red-team-{release-id}.md` with severity (Critical|High|Medium|Low), PoC steps, and affected URL/route. Oliver does not fix code; Critical/High findings block ship until fixed or explicitly waived (see **Red Team & Penetration Testing** in `runtime-ops.md` for the cross-phase lifecycle).

### OWASP gate _(Lars — incorporates Oliver's report)_

Pre-deploy assessment mapped to **OWASP Top 10 (2021)** and Laravel-specific vectors:

| ID  | Focus                                                                                                                                                                        |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| A01 | Broken access control — policies, gates, route middleware, IDOR                                                                                                                |
| A02 | Cryptographic failures — `APP_KEY`, HTTPS, secrets at rest                                                                                                                     |
| A03 | Injection — SQL, mass assignment, Blade/command injection                                                                                                                      |
| A04 | Insecure design — missing rate limits, unsafe defaults                                                                                                                         |
| A05 | Security misconfiguration — `APP_DEBUG`, exposed `.env`, CORS, **WAF/CDN** per PRD edge choice (or equivalent) on public traffic                                               |
| A06 | Vulnerable components — `composer audit`, outdated packages                                                                                                                    |
| A07 | Auth failures — session fixation, password reset, **2FA enabled** (Fortify TOTP), `Password::defaults()` with `uncompromised()`, Argon2id hashing                              |
| A08 | Software/data integrity — webhook signatures, deploy token handling                                                                                                            |
| A09 | Logging & monitoring — auth failures, deploy events logged; **observability stack** live (Nightwatch, CloudWatch, or equivalent)                                               |
| A10 | SSRF — outbound HTTP from user-controlled input                                                                                                                                |

Also: run `composer audit` when available; when **Aikido** is connected, confirm repo scanning is active and review open Critical/High findings; run `php artisan checkpoint:scan` when [checkpoint](https://github.com/andreapollastri/checkpoint) is installed (treat FAIL as High unless waived); use Boost `Database Schema` and code review for access-control and injection checks; confirm new entities use UUID primary keys unless the PRD documents an exception.

Write the assessment to `{paths.security}/{release-id}.md`:

```markdown
# Security Assessment — {{RELEASE_ID}}

**Assessor:** Lars (Larapilot Security Expert) — incorporates 🎯 Oliver red-team report
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

**Gate rules:** **NO-GO** on any **Critical** or **High** finding — fix or get explicit human waiver before deploy; **Medium** findings documented with confirmed human acceptance; Lars presents the verdict before Jack proceeds.

## Privacy & Legal Compliance _(Violet owns)_

**Violet** evaluates **every legal and privacy surface** from inception through ship, and runs the full launch gate when the app processes personal data:

| Area                                 | Violet checks                                                                                                                                     |
| ------------------------------------ | -----------------------------------------------------------------------------------------------------------------------------------------------------|
| **Legal pages**                      | Privacy policy, Terms of Service, Cookie Policy — reachable, dated, localized when required                                                            |
| **Consent**                          | Cookie banner, granular opt-in/opt-out, marketing consent separate from essential cookies; lawful basis documented per data collection point           |
| **Data subject rights**              | Access, rectification, erasure, portability, objection — flows documented and operational                                                              |
| **Anonymization & pseudonymization** | PII minimization in analytics, logs, and exports; hashing where identification is not required                                                         |
| **Retention**                        | Defined periods for user data, logs, backups, audit trails; automated pruning where possible (align with `config/logging.php` and pruning jobs)        |
| **Processors & transfers**           | DPA status, subprocessor list, EU residency, SCCs for non-EU transfers                                                                                 |
| **Children / special categories**    | Heightened safeguards when applicable                                                                                                                  |
| **Marketing opt-out**                | Opt-out mechanisms for marketing email and non-essential tracking                                                                                      |
| **Digital accessibility**            | EAA / EN 301 549 / national law conformance documented; **accessibility statement** page reachable when required — coordinate with **Elise** + **Emma** |

Violet works with **Lars** on security controls that implement privacy (encryption, access control, breach logging) and with **Aurora** when compliance tooling has cost implications. At ship, Violet issues PASS / issues for launch blockers. **Emma/Lauren** ensure tracking respects consent; **Emily** aligns legal pages and consent copy per locale.

## Web Launch Checks _(public sites only)_

Skip for APIs, admin-only apps, or CLI tools with no public web presence. Document findings in `.larapilot/docs/launch/{release-id}.md` when issues are found.

**Emma — SEO, Analytics, performance:**

- URL structure — semantic paths, canonical URLs, no broken public routes
- Breadcrumbs visible on deep pages; **JSON-LD** `BreadcrumbList` valid
- `robots.txt` reachable; references sitemap; blocks admin/staging
- `sitemap.xml` reachable; lists all public indexable URLs; valid XML
- `llms.txt` reachable (`/llms.txt` or `/.well-known/llms.txt`); reflects current site scope
- Unique `<title>` and meta description on key pages; single `<h1>` per page; logical heading hierarchy
- HTTPS enforced; no mixed content; structured data (JSON-LD) where applicable
- Analytics live (per PRD choice) with consent where required; key tracking events firing (signup, purchase, CTA clicks)
- Lighthouse on critical pages (mobile): **Accessibility ≥ 90**, Performance ≥ 80
- **Mobile First** spot-check (Elise + Anne): primary journeys usable at 375 px; nav and CTAs reachable; no horizontal scroll; desktop layout enhanced, not divergent
- **WCAG 2.2 AA** spot-check: keyboard nav, focus visible, form labels, alt text, contrast in light/dark (Elise + Emma)
- **Accessibility statement** page reachable when Violet required it

**Lauren — social, marketing & distribution:**

- Open Graph tags (`og:title`, `og:description`, `og:image`, `og:url`) — **`og:image`** points to Elise's **1200×630** asset or client artwork
- Twitter/X card tags (`twitter:card`, `twitter:image`)
- **`favicon.svg`** linked in layout; **apple-touch-icon** present; **logo** visible in header with working light/dark variants
- Default share copy and launch campaign assets documented
- Newsletter / list signup path verified when in scope
- SEM landing URLs and UTM conventions match Emma's setup

**Emily — localization (when multi-market):**

- Locale switcher works; `lang/` strings complete for supported locales
- Currency and timezone display correct per user/market setting
- Legal pages localized where Violet required
- `hreflang` tags present and reciprocal (with Emma)

**Sophia — post-launch support prep:**

- Create or update `{paths.support}/runbook.md` — bug intake channel, severity definitions, escalation to Lars/Oliver for security
- Confirm README and OpenAPI docs match the deployed release
- Note known issues and maintenance backlog items for the next spec cycle
