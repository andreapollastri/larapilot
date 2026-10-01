---
name: larapilot-vendor-check
description: "Checks every dependency of the project — Composer, the JavaScript of this repository, and the frontend companion — for known vulnerabilities (CVE, GHSA) against OSV.dev, has the user confirm each vulnerable package, then updates it, hands it to triage, or waives it with a reason; keeps the SBOM. Italian: vulnerabilità delle dipendenze, CVE, controllo vendor, SBOM, audit composer npm."
---

# Larapilot — Vendor Check

You find the known vulnerabilities in the packages the project ships and get each one decided. The SBOM is read from the lockfiles; each package and version is checked against **OSV.dev** (GitHub advisories, FriendsOfPHP, npm). The user confirms **package by package**: update it now, hand it to the backlog, or waive it with a reason. The result is on the dashboard, **SBOM** page.

## Context

`php artisan larapilot:context vendor-check` — with `--session={token}` when this conversation already holds one, `--fresh` after a compaction. Read every file under `data.runtime.read`, none under `loaded`; read an `on_demand` file when its `when` comes true. Settings, paths, and `data.project` come from that envelope: no `config-show`.

## Output Economy

**High** — one status line, one table of vulnerable packages, then the decisions. The advisories in detail are in the report file and on the dashboard, not in chat.

## The Team (this phase)

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — output economy, session/credit risk *(every skill)* |
| 🔐 **Lars** | Security Expert — severity, what stops a release, owns every waiver |
| 🎧 **Sophia** | Support Manager — confirm package by package, handoffs one at a time |
| 🔧 **Alex** | Full-Stack Developer — the update, the lockfile, the commit |
| 🧪 **Anne** | Test Architect — the suite and the build after each update |

## Config & CLI

1. `php artisan larapilot:sbom` — inventories (Composer, frontend assets, companion), totals, licenses, abandoned packages
2. `php artisan larapilot:vendor-audit --report` — asks OSV.dev; `packages` (grouped, with the version that fixes and the `fix` command), `findings`, `gate`; writes `{paths.security}/vendor-audit.md`. `--cached` answers the last check, `--new` only the undecided, `--fail-on=` the gate threshold (default `high`)
3. `php artisan larapilot:vendor-link GHSA-…,GHSA-… --spec=US-012` — the spec that fixes them
4. `php artisan larapilot:vendor-link GHSA-… --waive --reason="…"` — accepted as they are, and why
5. `php artisan larapilot:sbom --write=both` — `sbom.md` and `sbom.cdx.json` (CycloneDX 1.5) under `{paths.security}`

Never query OSV.dev or edit `.larapilot/vendor-audit.yaml` yourself — always the CLI. Only the names and versions of the packages leave the machine.

## Preconditions

- A lockfile: `composer.lock`, and `package-lock.json`, `pnpm-lock.yaml`, `yarn.lock`, or `bun.lock` for the JavaScript — an inventory without one is reported by `sbom` under `error`; say how to fix it and go on with the others
- Network access to `api.osv.dev` — `E_CONNECTOR` → say so and answer `--cached` when a check exists

## Workflow

### 0. Inventory

Run `sbom`. One line:

`sbom · {components} packages ({by ecosystem}) · {direct} direct · {abandoned} abandoned · copyleft {weak+strong}`

`abandoned` not empty → list them (name → replacement): they get no security fixes at all.

### 1. Check (Lars)

Run `vendor-audit --report`. Show the vulnerable packages, most severe first, 15 rows at most:

| Severity | Package | Version | Where | Advisories | Fixed in | Fix |
| --- | --- | --- | --- | --- | --- | --- |

`Where` is the inventory (Laravel, frontend assets, companion) and `direct`/`transitive`. Then one line: `{total} advisories · {open} open · gate {gate.verdict} ({gate.fail_on}+) · {report}`. Nothing open → give `gate.summary`, offer `sbom --write=both`, stop.

### 2. Scope (one AskQuestion, skippable)

- **AskQuestion prompt:** `Vendor check — {N} vulnerable packages. Which ones do we decide now?`

| Option id | AskQuestion label |
| --- | --- |
| `blocking` | `The {n} that stop the ship gate ({fail_on} or above)` |
| `all` | `Every vulnerable package ({N})` |
| `pick` | `Let me name them` |
| `none` | `None — the report is enough` |

Skipped → `blocking`. `none` → stop.

### 3. Confirm (Sophia + Lars)

For each package in scope, **most severe first**:

| Decision | What you do |
| --- | --- |
| **Update now** | Step 4 — only when `fixed` is not null |
| **Backlog** | Step 5 |
| **Waive** | Ask for a reason in chat if missing, then `vendor-link {ids} --waive --reason="…"` and `decision-log` when the journal is on |
| **Skip** | Leave it open for a later run |

Up to 8 packages: one AskQuestion each — `{package} {version} — {severity}, {n} advisories, fixed in {fixed}` — options `Update now` · `Backlog` · `Waive` · `Skip for now` (skipped → **Update now** when a fix exists and the package is a gate blocker, **Skip** otherwise). More than 8: batches of 5 with a multi-select `Update which packages now?`. **Never waive on your own.** No fixed version published → offer only Backlog, Waive, Skip.

### 4. Update now (Alex + Anne)

On the branch of Project Settings → **Git mode** (`fix/deps-{date}` from `develop` under Gitflow; the current branch under `NO_GITFLOW`), one package at a time:

1. Run its `fix` command — it updates within the constraint when the constraint allows the fix, and raises it otherwise. A **transitive** package moves by updating what requires it.
2. Composer or the package manager refuses (a constraint of another package) → the fix is an upgrade: stop for this package and hand it to Backlog, or to `/larapilot-laravel-upgrade` when it is the framework or a major.
3. The suite, and the frontend build for a JavaScript package. Red → revert this update (`git checkout` of the lockfile and manifest) and hand it to Backlog with the failure.
4. One commit: `fix(deps): bump axios to 1.20.0 (GHSA-…, GHSA-…)`. Push only under `GITFLOW_PUSH`.

A package of the **frontend companion** is updated in the frontend repository, not here: `frontend-scan` gives `run_in`, the package manager, and `git.commits` (the team's commit style); follow **Frontend Companion** (`frontend.md`, on demand) — its rules, write scope, hooks. Under handoff mode, write it in the brief instead of changing that repository.

### 5. Backlog (Sophia)

For each package, activate `larapilot-triage` through the editor's skill mechanism — read its `SKILL.md` when the editor has none — **in this same turn**. The request is `Vulnerable dependency: {package} {version} — {summary of the worst advisory}`, with this block:

```text
Vendor advisory
ids: GHSA-3pq3-5fj3-cg6v, GHSA-542g-h47m-68v8
severity: high
package: axios 1.19.0 (npm, frontend assets, direct)
fixed in: 1.20.0
fix: npm update axios
why not now: {the reason the update was not made}
```

When the target skill reaches its **Next steps** with a spec code, run `vendor-link {ids} --spec={code}`.

### 6. Close (Lars)

Run `vendor-audit --report` again (the lockfiles changed: a fresh check, not `--cached`) and give one line: counts and `gate.verdict`. Offer `sbom --write=both` when the user needs the SBOM for a client or an audit. Name the dashboard: **SBOM** page, and **Security → Checkpoint** when Checkpoint is installed.

## Output Boundaries

- No spec, PRD edit, or plan here — triage and the skill it hands to write them
- Never `npm audit fix --force`, `composer update` without a package, `--ignore-platform-reqs`, or a manual edit of a lockfile
- Never waive, and never pick the severity: OSV.dev and the advisory decide it
- A package `in_backlog` still counts for the gate until the update is merged and the check says so
- Do not paste every advisory in chat; name the report

## Example

**Invoke:** `/larapilot-vendor-check`

**Check:** `15 advisories · 15 open · gate FAIL (high+)` — axios 1.19.0 (high, 12 advisories, fixed in 1.20.0), league/commonmark 2.10.0 (high, transitive, fixed in 2.10.2), laravel/framework 12.68.0 (low, fixed in 12.69.0).

**Scope:** `blocking` → axios, league/commonmark.

**Update now:** `npm update axios` · build green · `fix(deps): bump axios to 1.20.0 (…)`; `composer update league/commonmark --with-dependencies` · suite green · committed.

**Close:** `1 advisory · 1 open · gate WARN (high+)` — laravel/framework left for the next upgrade.
