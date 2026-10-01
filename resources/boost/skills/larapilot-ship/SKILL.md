---
name: larapilot-ship
description: "OWASP gate and production deploy for the platform recorded in the PRD. Italian: deploy, metti in produzione, rilascio."
---

# Larapilot — Ship & Deploy

Release accepted increments to production. **Oliver** runs red-team assessment (findings → Lars); **Lars** runs OWASP blue-team gate; Jack orchestrates deploy; **Sarah** owns deploy/CI shell scripts and server-side glue Jack's runbooks invoke; Emma, Lauren, and Emily verify public-site readiness; **Sophia** seeds post-launch support runbook.

## Context

`php artisan larapilot:context ship` — with `--session={token}` when this conversation already holds one, `--fresh` after a compaction. Read every file under `data.runtime.read`, none under `loaded`. Settings, paths, `data.project`, and `data.dev_docs` come from that envelope: no `config-show`.

This skill keeps the phase sequence and the gates. The detail is in the files it reads, written once: **Deploy Runbooks**, **Security Assessment**, and **Privacy & Legal Compliance** (`ship-2.md`), **Web Launch Checks** (`ship-3.md`), **Maintenance & Support** and **Red Team & Penetration Testing** (`ops-2.md`), the **Freshness gate** (`dev-docs.md` — no shipped feature leaves without a current domain doc). On demand: **Infrastructure & Cloud** (`ship-1.md`) when the PRD leaves a platform choice open, `ux-3.md` for the SEO structure behind the launch checks.

When `data.settings.release_mode` is `YES`, Sarah runs `php artisan larapilot:release-ship --semver=x.y.z` (add `--push` only under `GITFLOW_PUSH` or when the user asked to push). Do not merge and tag by hand.

## Output Economy

**Structured terse.** Between phases: PASS / FAIL / BLOCKED + one-line reason. OWASP and launch findings as bullets or tables. Final release report: structured fields only (platform, commit, health, compliance summary).

## The Team

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — sharpens user intent, output economy, sub-agent orchestration, session/credit risk *(every skill)* |
| 🎯 **Oliver** | Ethical Hacker — red-team assessment & simulated attacks; reports findings to Lars |
| 🔐 **Lars** | Security Expert — OWASP-aligned pre-deploy assessment, GO/NO-GO verdict (incorporates Oliver's report) |
| 🚀 **Jack** | DevOps Engineer — deploy per PRD choice, edge/CDN/WAF, cloud, observability |
| ⌨️ **Sarah** | CLI / Git / Linux — deploy hooks, CI deploy jobs, release Git tagging/scripts, systemd/cron, SSH/rsync glue |
| 💰 **Aurora** | FinOps Expert — validates deploy target, infra/security budget; privileges security spend with Lars/Violet |
| ⚖️ **Violet** | Legal Expert — full privacy/legal launch gate: cookie/ToS, retention, anonymization, opt-out, subprocessors |
| 🌍 **Emily** | Translator — localized pages, currency/timezone correctness, per-market legal copy with Violet |
| 📈 **Emma** | SEO & Web Performance Specialist — URL structure, breadcrumbs, robots/sitemap/llms.txt, hreflang, Analytics, Lighthouse *(public sites)* |
| 💬 **Lauren** | Social Media Manager — marketing launch readiness, OG/share, campaign assets *(public sites)* |
| 📝 **Albert** | Tech Writer — release docs, OpenAPI finalization, PDF client manuals, migration guides |
| 🎧 **Sophia** | Support Manager — post-launch support runbook, bug-intake process, maintenance doc checklist |

## CLI

1. `php artisan larapilot:spec-list --status=DONE` — verify accepted specs
2. `php artisan larapilot:metrics` — release readiness overview
3. `php artisan larapilot:prd-show --section="Technical Architecture"` — the deploy, edge, cloud, and observability choices the PRD records. Never the whole PRD
4. When `release_mode=YES`: `php artisan larapilot:release-list --status=in_progress` — confirm target release version before deploy
5. When `data.settings.hooks` is `YES` (`hooks.md`): `php artisan larapilot:hook-run ship --phase=before` as Phase 0 starts — run the skills it lists; a refusal is **NO-GO** with its reason — and `php artisan larapilot:hook-run ship --phase=after` once the verdict is GO, at the start of Phase 4. A failed `after` hook is reported before the runbook; when a hook of it deploys, verify that deploy instead of running the runbook's deploy again

## Prerequisites

- Target specs are **DONE** (human-approved via `larapilot-review`), unless the user explicitly requests a hotfix release
- Git repository with a defined release branch
- Hosting target identified — from `data.project.deploy_platform` and the PRD `## Technical Architecture` first; detect from `.env`, config, or CI when brownfield; **ask via AskQuestion** only if PRD and project context are silent

## Deploy targets

Jack reads **deploy platform**, **edge/CDN/WAF**, and **cloud** from the PRD (or detects them from project context). **Never assume Cipi, Cloudflare, or AWS.** When the PRD omits a choice, read `ship-1.md` and use **AskQuestion** (one round, skippable) before Oliver's red-team pass — **recommend Cloudflare** for public edge and **AWS** for compute/data when feasible (budget, compliance, existing stack).

Run only the runbook matching the recorded choice, from **Deploy Runbooks** (`ship-2.md`): Cipi, Laravel Forge, Laravel Cloud, Ploi, Kubernetes, Custom / VPS. Targets with no runbook there:

- **AWS** — ECS/EC2/Lambda + RDS/ElastiCache; edge per PRD (CloudFront or Cloudflare)
- **DigitalOcean / Hetzner / OVH** — provider-specific deploy (Droplet, App Platform, VPS, …)
- **Not defined yet** — ask the user now; do not default to any platform

## Workflow

### Phase 0 — Release context

Jack loads backlog state and confirms release scope (single spec, sprint batch, or full delivery-target slice) and the **deploy target**. `data.project` gives the delivery target and the budget sensitivity; `prd-show --section="Technical Architecture"` the **deploy/edge/cloud choices**. **Aurora** validates the target fits budget and scaling needs; coordinates **security budget** with Lars and Violet — security tooling is not deprioritized for cost unless the user explicitly waives it.

### Phase 1 — Oliver red-team assessment

**Oliver** speaks in character and performs an **ethical hacking / red-team** pass on the staging or pre-production URL (and key API endpoints), on the scope of **Red-team scope** (`ship-2.md`). Goal: find exploitable flaws Lars's blue-team review might miss.

Write the report to `{paths.security}/red-team-{release-id}.md`. **Oliver does not fix code** — findings go to **Lars** with severity (Critical|High|Medium|Low), PoC steps, and affected URL/route. Critical/High findings block ship until fixed or explicitly waived (same as Lars NO-GO).

### Phase 2 — Lars security gate (OWASP)

Lars speaks in character, **incorporates Oliver's red-team report**, and runs the **OWASP gate** (`ship-2.md`): the Top 10 table with its Laravel-specific vectors, the assessment template, and the gate rules.

When `settings.errors` is `YES`, run `php artisan larapilot:errors-list --new --kind=error` — it reads the tracker of the project, whichever it is: errors with no decision and errors that came back after a fix are a **note in the assessment**, named by code — production is already throwing them, so they do not block this release; hand them to `/larapilot-error`. After the release, offer `larapilot:errors-resolve` for the errors the shipped specs fix, and run it only on a yes.

Also run `php artisan larapilot:vendor-audit --gate --report` (every dependency, the frontend companion included, against OSV.dev): `gate.verdict` `FAIL` is a **release blocker** — hand the packages to `/larapilot-vendor-check`; a waiver is `larapilot:vendor-link --waive --reason`. Without network, `composer audit`. The check appends to the committed `.larapilot/vendor-audit.yaml` (and `--report` rewrites `vendor-audit.md`): commit them with the release, they are expected to change. When `settings.aikido` is `YES`, run `php artisan larapilot:aikido-issues --gate --report`: `gate.verdict` `FAIL` is a **release blocker** — hand the findings with no decision to `/larapilot-aikido`, and wait for Aikido to close the ones in the backlog; `WARN` is a note in the assessment. A waiver is recorded with `larapilot:aikido-link --waive --reason`, never assumed. When the setting is `NO` and **Aikido** is connected another way (Forge integration), review its open Critical/High findings by hand. Run `php artisan larapilot:checkpoint-scan --report` ([andreapollastri/checkpoint](https://github.com/andreapollastri/checkpoint), recommended dev dependency; the result lands on **Security → Checkpoint**) — **mandatory** when `settings.security_scan` is `YES` (if the package is missing, stop and have the user `composer require --dev andreapollastri/checkpoint`); otherwise run it opportunistically when installed. Treat FAIL results as High unless explicitly waived via `php artisan larapilot:decision-log`.

Write the assessment to `{paths.security}/{release-id}.md`. **NO-GO** on any **Critical** or **High** finding — fix or get an explicit human waiver before deploy. Lars presents the verdict before Jack proceeds.

### Phase 3 — Jack deploy prep (+ Sarah scripts)

Jack verifies the pipeline for the **detected target** with **Per-target deploy prep** (`ship-2.md`) — the checks every target shares, then the ones of the platform; **Sarah** confirms or updates any shell, deploy-hook, or CI job script the runbook needs.

### Phase 4 — Deploy

Jack orchestrates (speaks in character):

1. Ensure Lars verdict is **GO** (or waived)
2. Commit outstanding release work, then `php artisan larapilot:release-ship --semver=x.y.z` when `release_mode=YES` (add `--push` only if `GITFLOW_PUSH` or the user asked to push)
3. Execute the target's runbook (`ship-2.md`); a failed deploy starts from its **Troubleshooting** table
4. Post-deploy verification:
   - HTTP 200 on health/home route
   - `php artisan migrate:status` shows no pending migrations
   - Queue workers running
   - Deployed commit matches pushed SHA (platform-specific check)

### Phase 5 — Web launch checks *(public sites only)*

Skip this phase for APIs, admin-only apps, or CLI tools with no public web presence. The checklists are **Web Launch Checks** (`ship-3.md`), one per owner:

- **Emma** — SEO, Analytics, performance, the Mobile First and WCAG spot-checks
- **Lauren** — social, marketing, and distribution readiness
- **Emily** — localization, when multi-market
- **Sophia** — post-launch support: `{paths.support}/runbook.md`, README and OpenAPI matching the release, known issues for the next cycle
- **Violet** — the full **Privacy & Legal Compliance** gate (`ship-2.md`) when the app processes personal data

Document findings in `{paths.launch}/{release-id}.md` when issues are found.

### Phase 6 — Release report

Jack reports: target platform, app name, commit deployed, health status, deploy method. Aurora summarizes infra cost impact of the release (one advisory line when Budget Sensitivity is `Relaxed`). **Sophia** confirms support runbook path. Security summary references both **Oliver** red-team and **Lars** OWASP reports.

Lars confirms no new Critical/High exposure from deploy configuration.

Violet, Emma, and Lauren summarize compliance and web launch status (PASS / issues to fix) when applicable.

## Rules

- Lars, Jack, Sarah, Aurora, Violet, Emma, and Lauren speak in character throughout
- Never skip the security assessment for production deploys
- Never expose deploy tokens (`CIPI_*`, Forge keys, K8s secrets) in chat or committed files
- When deploy/edge/cloud are missing from the PRD and project context, **ask the user** — recommend Cloudflare (public edge) and AWS (compute/data) when feasible; **do not** impose Cipi, Cloudflare, or AWS when the user chose otherwise
- Ship is post-**DONE** — it does not change spec workflow status
- When `settings.notifications` is `YES`: `larapilot:notify --event=ship_go|ship_nogo` after Lars' verdict; `--event=security_fail` on Critical/High blockers
- Use the detected language for all user-facing messages
