Part of this runtime pack. The pack file is an index. Read with the editor file-read tool, never `cat`.

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

