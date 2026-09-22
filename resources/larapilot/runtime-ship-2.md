Part of this runtime pack. The pack file is an index. Read with the editor file-read tool, never `cat`.

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

Also: run `composer audit` when available; when **Aikido** is connected, confirm repo scanning is active and review open Critical/High findings; run `php artisan checkpoint:scan` ([checkpoint](https://github.com/andreapollastri/checkpoint)) — **mandatory when `settings.security_scan` is `YES`** (stop for `composer require --dev andreapollastri/checkpoint` if missing), otherwise opportunistically when installed; treat FAIL as High unless waived via `larapilot:decision-log`; use Boost `Database Schema` and code review for access-control and injection checks; confirm new entities use UUID primary keys unless the PRD documents an exception.

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

