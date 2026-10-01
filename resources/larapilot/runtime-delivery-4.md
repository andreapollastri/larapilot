## CLI, Git Pipelines & Linux _(Sarah owns — steps in wherever these surfaces appear)_

**Sarah** is the squad expert for **custom CLIs**, **Git in general**, **Git/forge automation**, **CI pipeline scripts**, and **Linux / terminal / server shell** work. She **must participate** whenever a plan or implement task touches any of those — not only when a dedicated "CLI tooling" FR exists. On merge/rebase conflicts, dirty history, or tricky Git recovery, **Sarah leads** the resolution (Alex owns the code content; Sarah owns the Git mechanics).

| Surface | Sarah does | Partners with |
| --- | --- | --- |
| **Custom CLIs** | Decide Bash vs Go vs Artisan; write/maintain the tool | **Andrew** (Artisan vs external), **Albert** (usage docs) |
| **Git (general)** | Conflict resolution, rebase vs merge, interactive rebase, cherry-pick, bisect, reflog recovery, history hygiene, submodule/worktree pitfalls | **Alex** (file content during conflicts), **Jack** (branch policy), **Robert** (rejects messy multi-task commits) |
| **Git / forge automation** | Hooks, `gh`/`glab`/`az repos`/API scripts, branch helpers, release tagging scripts | **Jack** (Gitflow policy), **Alex** (per-task discipline) |
| **CI pipelines** | Workflow YAML, job scripts, matrix runners, cache, artifacts | **Jack** (required gates / merge blockers), **Anne** (test commands), **Lars** (audit/security steps) |
| **Linux / terminal / server** | Shell scripts, systemd units, cron, deploy hooks, SSH/rsync glue, VPS bootstrap | **Jack** (deploy platform & orchestration), **Lars** (secrets / hardening) |

Stack defaults: **Shell/Bash** for thin wrappers and host automation; **Go** when the binary must be portable, fast, single-file, or used outside PHP runtime. Prefer Laravel Artisan for in-app commands; escalate to a standalone CLI when the tool must run without bootstrapping the full app, ship to many machines, or serve non-PHP consumers.

Rules:

1. Propose a CLI only when there is a recurring workflow (scaffold, doctor, migrate-helper, release, env bootstrap) — not for one-off chat instructions.
2. Choose **Bash** for short, readable glue that calls `composer`/`php`/`git`/`docker`. Choose **Go** for cross-platform binaries, concurrent I/O, or tools distributed via GitHub Releases.
3. On CI/pipeline or server-script tasks: Sarah drafts the scripts; Jack confirms gates, environments, and deploy orchestration; Lars reviews secret handling (no secrets in argv/logs/committed files).
4. Coordinate with **Lucille** (time spent on tooling is logged under `feature` or `support`).
5. Record in PRD/plan: tool/pipeline/script name, language, install path, and who runs it (dev / CI / ops).

## CI/CD Pipeline _(Jack imposes minimum gates; Sarah authors pipeline scripts)_

Every project gets a pipeline scaffold (GitHub Actions, GitLab CI, Bitbucket Pipelines — match the host). **Jack** defines required stages and merge blockers; **Sarah** authors and maintains the YAML/jobs/shell steps and any helper scripts the pipeline calls.

**Minimum stages:**

```yaml
# Conceptual minimum — adapt to host
- lint: vendor/bin/pint --test  (or ./vendor/bin/pint --dirty)
- analyse: vendor/bin/phpstan analyse --no-progress --memory-limit=1G  # Larastan level 5+ — never lower without human waiver
- test: php artisan test --parallel
- audit: composer audit
- security: php artisan checkpoint:scan # when checkpoint installed
- build: npm ci && npm run build # when Vite frontend exists
- deploy: only from main/tags; Lars GO + Jack orchestration (Sarah writes deploy hook scripts when needed)
```

Rules: pipeline runs on every PR to `develop`/`main`; failing **Pint**, **Larastan (level 5+)**, tests, or `composer audit` block merge; deploy to production only after Lars ship GO (or explicit waiver). Involve **Sarah** on every plan/implement task that adds or changes workflow files, job scripts, or server-side shell.

## Versioning & Changelog

- **Semantic Versioning** ([SemVer](https://semver.org/)): `MAJOR.MINOR.PATCH` — bump in `release/*` branches.
- **`CHANGELOG.md`** at repo root — [Keep a Changelog](https://keepachangelog.com/) format (`Added`, `Changed`, `Fixed`, `Removed`, `Security`); update on every release; Unreleased section during development.
- **Git tags** `vX.Y.Z` on `main` after each production release.
- Laravel apps: align `composer.json` version or package release notes when shipping libraries.

## Security Disclosure Files _(Lars imposes)_

| File | Location | Purpose |
| --- | --- | --- |
| **`security.txt`** | `public/.well-known/security.txt` | [RFC 9116](https://www.rfc-editor.org/rfc/rfc9116.html) — `Contact`, `Expires`, `Preferred-Languages`, `Policy` (link to SECURITY.md) |
| **`SECURITY.md`** | Repository root | Coordinated disclosure policy, supported versions, response SLA, scope |

Ship gate: both files present and reachable on public apps (`https://domain/.well-known/security.txt`). Lars plans them when missing.

## Optional integrations _(Sebastian proposes alongside well-known options)_

Always present **both** mainstream SaaS/managed options and the self-hosted open-source alternatives below. Let the user choose; do not silently omit either category.

| Need | Well-known options | Also propose (open-source / self-hosted) |
| --- | --- | --- |
| **Security audit** | **[Aikido](https://www.aikido.dev/)** (SAST + SCA, auto-triage, PR checks, Laravel/Forge integration — **propose first when Budget Sensitivity is Tracked**), `composer audit`, GitHub Dependabot, Enlightn | [andreapollastri/checkpoint](https://github.com/andreapollastri/checkpoint) — `php artisan checkpoint:scan`; optional local/CI gate before deploy |
| **Newsletter / email lists** | Mailchimp, Brevo, ConvertKit, Customer.io, MailerLite | [andreapollastri/newsletter](https://github.com/andreapollastri/newsletter) — self-hosted newsletter system |
| **Web analytics** | GA4, Plausible, Matomo, Fathom, PostHog | [andreapollastri/indiestats](https://github.com/andreapollastri/indiestats) — privacy-friendly, self-hosted analytics |
| **Error & uptime monitoring** | Sentry, Bugsnag, Flare, Larabug | [andreapollastri/boogle](https://github.com/andreapollastri/boogle) — self-hosted bug & uptime monitor (`boogle-client` in apps) |
| **Observability / APM** | **[Laravel Nightwatch](https://nightwatch.laravel.com/)** (preferred for Laravel), **AWS CloudWatch** (preferred on AWS), Datadog, New Relic, Grafana Cloud, Better Stack, OpenTelemetry | Laravel **Pulse**, self-hosted Grafana/Prometheus |
| **Edge / CDN / WAF** | **[Cloudflare](https://www.cloudflare.com/)** (DNS, CDN, WAF — **recommend when feasible**), AWS WAF + CloudFront, Bunny CDN/Shield, Akamai, Fastly | nginx rate limiting, ModSecurity on VPS _(only when managed WAF budget unavailable)_ |
| **Object storage (S3)** | AWS S3, Cloudflare R2, DigitalOcean Spaces, Backblaze B2, MinIO | [andreapollastri/johnny](https://github.com/andreapollastri/johnny) — self-hosted S3-compatible storage with panel and backups |

**Aikido** — when the project has budget (**Budget Sensitivity: Tracked**) or deploys via **Laravel Forge**, propose it as the primary managed AppSec layer: repo SAST, lockfile SCA, supply-chain alerts, optional AutoFix PRs. Enable via [Forge Integrations](https://forge.laravel.com/docs/integrations/aikido) or connect the Git provider directly. Pair with **Checkpoint** for a free local/CI scan.

**Checkpoint** is optional but recommended: `composer require --dev andreapollastri/checkpoint`, run before ship, wire into CI when Jack sets up pipelines. **Boogle client** — when chosen, register `Boogle::handle($e)` in `bootstrap/app.php` (`withExceptions`) or `app/Exceptions/Handler.php` per Laravel version.
