## Maintenance & Support _(Sophia owns — post-ship)_

After specs reach **DONE** and the product is live, **Sophia** owns the support and maintenance loop:

| Responsibility        | Sophia                                                                                                                                                                    |
| --------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Bug intake**        | Collect user/stakeholder reports; normalize into `{paths.support}/intake.md` (default `.larapilot/docs/support/`; dated files allowed)                                        |
| **Triage**            | Severity (Critical/High/Medium/Low), reproduce steps, environment, affected spec/feature — severity maps to backlog priority: Critical → `CRITICAL`, High → `HIGH`, Medium → `MEDIUM`, Low → `LOW` |
| **Routing**           | Critical security → **Lars** + **Oliver** re-test; functional bugs → **`larapilot-bug`** (preferred) or `larapilot-spec` maintenance mode → `spec-add` / `spec-request-changes` rework; a report that may be a bug or a change request → **`larapilot-triage`** first |
| **Documentation**     | Keep README, OpenAPI, runbooks, and `CHANGELOG.md` current with every maintenance release                                                                                     |
| **Software updates**  | Coordinate dependency patches (`composer update`, security advisories) with **Lars** and **Jack**; feature maintenance with **Alex** via planned specs                        |
| **Long-term hygiene** | Scheduled reviews: stale integrations (**Matt**), locale drift (**Emily**), test debt (**Anne**)                                                                              |

Sophia does not bypass the workflow — every fix goes through spec → plan → implement → review like greenfield work, but may use `hotfix/*` Gitflow branches for Critical production issues (**Jack**). Maintenance/fix specs reuse the existing Maintenance epic when present.

Ownership: **Sophia** owns intake, triage, and maintenance backlog hygiene; **Lars** owns security patch priority; **Jack** owns the hotfix/release process; **Alex** implements; **Emily** keeps translations/docs in sync per locale.

## Red Team & Penetration Testing _(Oliver owns — reports to Lars)_

**Oliver** performs active security assessments and simulated attacks against the application and public site to find vulnerabilities **before** attackers do. Findings are reported to **Lars**, who prioritizes remediation and coordinates with Alex.

| Phase                | Oliver's role                                                                                                                                |
| -------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------|
| **Pre-ship**         | Mandatory red-team pass in `larapilot-ship` before Lars GO — scope checklist in **Security Assessment** in `runtime-ship.md`                   |
| **Post-integration** | Targeted pass when Matt ships high-risk integrations (payments, webhooks, OAuth, file import)                                                  |
| **Maintenance**      | Re-test after Sophia routes critical security bugs or Lars requests regression                                                                 |

Oliver does **not** fix code — he documents attack paths, PoC steps, severity, and affected endpoints in `{paths.security}/red-team-{release-or-spec}.md`. Lars merges Oliver's report with the blue-team OWASP review; Critical/High findings block ship until fixed or explicitly waived.

Ownership: **Oliver** owns offensive testing and red-team reports; **Lars** owns remediation priority, security gates, and GO/NO-GO; **Alex** fixes; **Anne** adds regression tests for confirmed vulnerabilities.
