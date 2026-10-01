# Larapilot

**From product idea to reviewed Laravel code — with an AI product team that follows a real process.**

Larapilot is a spec-driven workflow for Laravel projects, built on [Laravel Boost](https://laravel.com/ai/boost). Install the package, run `/larapilot-*` skills in your AI editor, and get a PRD, a backlog, technical plans, and reviewed code — all versioned in `.larapilot/` next to your app.

**The agent proposes. You approve what ships.** Human-in-the-loop, always.

📖 **Documentation:** [larapilot.web.ap.it](https://larapilot.web.ap.it) · [Use cases](https://larapilot.web.ap.it/#examples) · [Custom skills](https://larapilot.web.ap.it/#example-custom-skill) · [API](https://larapilot.web.ap.it/#deep-dive-api)

---

## Contents

- [Why Larapilot](#why-larapilot)
- [Quickstart](#quickstart)
- [The core loop](#the-core-loop)
- [Skills](#skills)
- [Custom skills — your own slash commands](#custom-skills--your-own-slash-commands)
- [Workflow hooks — your commands on the loop](#workflow-hooks--your-commands-on-the-loop)
- [What lands in `.larapilot/`](#what-lands-in-larapilot)
- [Developer domain docs](#developer-domain-docs-docsdevs-always-on)
- [Project settings](#project-settings)
- [Dashboard & API](#dashboard--api-devstaging)
- [Economics](#economics-account-none-by-default)
- [Traceability](#traceability)
- [Security](#security)
- [Upgrades — Laravel, PHP, database](#upgrades--laravel-php-database)
- [Integrations](#integrations)
- [Artisan CLI & MCP](#artisan-cli--mcp)
- [Requirements](#requirements)
- [Learn more](#learn-more)

---

## Why Larapilot

AI agents are fast, but isolated prompts are not a product process. Larapilot gives your assistant a disciplined squad — discovery → backlog → plan → implement → review → ship — with **30 personas** (Mark for product, John for architecture, Robert for review, Lars for security, Sarah for CLI/Git/Linux, Lucille for time and deadlines, …) used as review lenses, not costumes. Apps, websites, and PHP/Laravel Composer packages are all first-class.

Three layers, each with one job:

| Layer | Job |
| --- | --- |
| **Boost skills** (`/larapilot-*`) | Drive the conversation in your editor |
| **Artisan commands** (`larapilot:*`) | Persist state and enforce the workflow — skills never write workflow files by hand |
| **MCP server** (`larapilot`) | Lets the agent read backlog, specs, and diagnostics mid-conversation |

The workflow engine blocks invalid transitions: no implement before plan, no approve before review, no approve with open `[blocks-merge]` feedback or unfinished tasks (unless you pass `--force`).

---

## Quickstart

```bash
composer require andreapollastri/larapilot --dev
php artisan larapilot:install
php artisan boost:install
```

`larapilot:install` scaffolds the `.larapilot/` workspace, and also **[Larastan](https://github.com/larastan/larastan) level 5+** and **[Laravel Pint](https://laravel.com/docs/pint)** (`phpstan.neon.dist`, `pint.json`, Composer scripts, dev dependencies). Run `php artisan larapilot:quality` before merge; `larapilot:doctor` fails when the gate is missing.

Already on Boost? Pick up the skills once with `php artisan boost:update --discover`.

If your editor does not list the MCP servers after `boost:install`, register them:

```json
{
  "mcpServers": {
    "laravel-boost": { "command": "php", "args": ["artisan", "boost:mcp"] },
    "larapilot":     { "command": "php", "args": ["artisan", "mcp:start", "larapilot"] }
  }
}
```

Then, in your editor:

| Your project | Type |
| --- | --- |
| A new idea — app, site, or Laravel package | `/larapilot-inception "your product idea"` |
| A Laravel app in production, built without Larapilot | `/larapilot-adopt` |
| A legacy system you are rewriting in Laravel | `/larapilot-inception` with a snapshot in `.larapilot/legacy/` |

### Upgrade

```bash
composer update andreapollastri/larapilot laravel/boost --with-dependencies
php artisan larapilot:update
php artisan larapilot:doctor
```

`larapilot:update` refreshes the runtime packs, `task-templates.md`, `integrations.md`, and the packaged design systems (`--preserve-design-systems` keeps yours), re-registers your custom skills, moves a handbook left in `_project_docs/` by an older version into `.larapilot/docs/handbook/`, **realigns the delivery forecast** of a project that is already planned, then runs `composer update laravel/boost` (skipped inside a Composer script) and `boost:update`. Runtime-only refresh: `php artisan larapilot:update --skip-boost`. It never touches `config.yaml` (except to repoint an old `paths.project_docs: _project_docs/`), the PRD, plans, domain docs, or custom skills. In the backlog and the schedule it writes only what takes no decision — an id and a plain date on a milestone that lacks them, the release a milestone is named after, the deadline of an epic on the specs of that epic that lack it — and it says what the forecast now misses: dates that do not hold and inputs it could not read are for `/larapilot-schedule`. `php artisan larapilot:schedule-apply --repair --dry-run` lists those repairs without writing them. Do not re-run `larapilot:install` on an existing project unless you mean `--force`.

**From 4.x to 5.0** — `composer update` stays inside the major your `composer.json` names, so raise the constraint first:

```bash
composer require andreapollastri/larapilot:^5.0 --dev --with-all-dependencies
php artisan larapilot:update
php artisan larapilot:doctor
```

If `composer why andreapollastri/larapilot` says *requires* rather than *requires (for development)*, Larapilot sits in `require`: drop `--dev`, or Composer moves it to `require-dev`. Nothing in `.larapilot/` is migrated: PRD, backlog, plans, decisions, and settings are read as they are. Three things to check: `spec-list` answers without the bodies (add `--full` where a script reads `body`); a custom skill that names a runtime part by its number should cite the heading, or start with `larapilot:context {name} --with=…`; `/larapilot-boogle` is `/larapilot-error`, and the `boogle-*` commands still answer. What v5 changes, measured: [Version 5 vs version 4](https://larapilot.web.ap.it/#v5). Step by step — a branch, a dry run, the output to read, the checks as `git grep` commands, and the way back: [Major releases](https://larapilot.web.ap.it/#upgrade-major).

---

## The core loop

Greenfield — repeat steps 3–5 per user story:

```
/larapilot-inception "…"  →  /larapilot-spec  →  /larapilot-plan US-XXX
  →  /larapilot-implement US-XXX  →  /larapilot-review US-XXX
```

Brownfield — adopt an app already in production:

```
php artisan larapilot:install  →  /larapilot-adopt  →  /larapilot-spec  →  (per-story loop)
```

| When | Start with |
| --- | --- |
| New product, site, app, PHP/Laravel package, pivot, or legacy rewrite | `/larapilot-inception` |
| Existing Laravel app in production, built without Larapilot, no PRD yet | `/larapilot-adopt` |
| One new capability on an existing product | `/larapilot-feature "…"` |
| Defect or regression | `/larapilot-bug "…"` |
| A request that may be either — a ticket, a client email | `/larapilot-triage "…"` |
| A change to the PRD that is neither — priorities, scope, a sharper requirement, a decision reversed, an older PRD brought up to date | `/larapilot-prd "…"` |
| Many planned stories at once | `/larapilot-autopilot US-004 US-005 …` |
| A team ritual the packaged skills don't cover | `/larapilot-custom-skill` |

Optional around the loop: `/larapilot-design` before plan · `/larapilot-ship` when the MVP stories are **DONE** · `/larapilot-settings` for project modes · `/larapilot-usage` for time and tokens · `/larapilot-schedule` to re-plan the order and the dates · `/larapilot-economics` for quotes and pricing.

**Status machine:** `TODO` → `PLANNED` → `IN PROGRESS` → `REVIEW` → `DONE`. A rejected review sends the story back to `TODO` with your feedback attached.

**Git** follows `settings.git_mode` (default **`GITFLOW`, no auto-push**): one `feature/US-XXX-*` branch per story, one atomic Conventional Commit per plan task; push and remote PRs only under `GITFLOW_PUSH`. With `release_mode=YES`, stories can branch from `release/x.y.z` instead of `develop`. Details: [Git workflow](https://larapilot.web.ap.it/#deep-dive-gitflow).

**Autopilot** chains plan + implement for several specs, one at a time. Under `STANDARD` or `MAX` each spec runs in a fresh writing sub-agent; your session keeps the CLI transitions, questions, and the Robert/Lars review. Under `ECO`, or in an editor without sub-agents, it stays inline. You still run `/larapilot-review` per story unless `auto_approve=YES`.

---

## Skills

Published by Laravel Boost after `php artisan boost:install`:

| Skill | Role |
| --- | --- |
| `/larapilot-inception` | Product discovery → PRD (includes **Frontend Topology**) |
| `/larapilot-adopt` | Reverse-engineer a PRD from an existing production codebase |
| `/larapilot-spec` | MoSCoW backlog from the PRD |
| `/larapilot-feature` | Mini-inception for one enhancement |
| `/larapilot-bug` | Bug triage → fix spec or rework, with redacted diagnostics |
| `/larapilot-triage` | Bug or feature? Classifies a request against the PRD and the backlog, then hands off to `/larapilot-bug` or `/larapilot-feature` |
| `/larapilot-aikido` | Downloads the open security findings of **Aikido**, confirms them with you, groups them by fix, and hands each group to `/larapilot-triage` (`aikido=YES`) |
| `/larapilot-error` | Asks which tracker records the **errors of production** — Boogle, Sentry, Bugsnag, Flare, Datadog, Rollbar, Honeybadger, or CloudWatch — when none is set, downloads the open ones, confirms them with you, groups them by place in the code, and hands each group to `/larapilot-triage` (`errors=YES`) |
| `/larapilot-vendor-check` | Checks every dependency — Composer, the JavaScript of the repository, the frontend companion — against **OSV.dev** for known vulnerabilities, confirms each vulnerable package with you, then updates it, hands it to `/larapilot-triage`, or waives it with a reason |
| `/larapilot-laravel-upgrade` | Upgrades Laravel to the version you name: readiness report and criticalities first, then one major at a time on its own branch — dependencies, the upgrade guide, Filament, Nova, Livewire, Inertia — with an upgrade report |
| `/larapilot-php-upgrade` | Upgrades PHP: the suite on the target version, the packages that exclude it, deprecated code, every file that pins PHP (Docker, CI, Vapor, Herd, Sail), and the server runbook |
| `/larapilot-db-upgrade` | Upgrades or switches the database — MySQL 5.7 → 8.0 → 8.4, MySQL → MariaDB or PostgreSQL, a PostgreSQL major — with portable SQL, a local rehearsal on the target engine, and the data and cutover runbook |
| `/larapilot-prd` | Revises the PRD when it is neither — sharpen, re-scope, re-model, re-decide, upgrade — and aligns the stories that cite what changed |
| `/larapilot-design` | Static HTML mockups from a design system, with style variants to compare |
| `/larapilot-plan` | Technical plan + tasks for a spec |
| `/larapilot-implement` | Code + tests + developer domain docs, one commit per task |
| `/larapilot-review` | Human gate → **DONE** or rework |
| `/larapilot-autopilot` | Batch plan + implement, one fresh context per spec |
| `/larapilot-ship` | Security gate + deploy runbook when the MVP is done |
| `/larapilot-settings` | Persist project modes (effort, backlog, git, testing, account, auth, forges, notifications, …) |
| `/larapilot-release` | Semver release ledger + Gitflow `release/x.y.z` branches (`release_mode=YES`) |
| `/larapilot-project-docs` | Living handbook in `.larapilot/docs/handbook/` (`project_docs=YES`) |
| `/larapilot-custom-skill` | Create your own skills under `.larapilot/skills/`, registered with Boost |
| `/larapilot-frontend-companion` | Link an external frontend repo or monorepo, name this product's projects, load its agent rules — driven from Laravel, or handed off to its team |
| `/larapilot-economics` | **Aurora + Jennifer + Benjamin** — quote, tax, payback, packaging, business plan, client quote (`account` ≠ NONE) |
| `/larapilot-usage` | **Lucille** — time/token ledger, deadlines, Markdown report |
| `/larapilot-schedule` | **Lucille** — re-plan a project that is already planned: order, estimates, epic deadlines, and milestones against the forecast, with a dry run before anything is written |
| `/larapilot-backstage` | Publish the repo into a **Backstage** developer portal |
| `/larapilot-tracker` | Mirror the backlog into **Linear · Asana · Jira · Trello · ClickUp · Monday** |

**Inception is a conversation, not a questionnaire.** AskQuestion is used only for the fixed choices Larapilot persists; every answer gets a reaction before the next question; and before any requirement is written, Mark, Jennifer, and Benjamin run at least two **challenge** exchanges on the goal itself (who has this problem and what they do instead, how you will know in 90 days, the riskiest assumption, what would make you stop). Four rounds always happen, legacy rewrites included: **Project Kind**, **Delivery Target**, **Business Model**, and **Operations & support**. A skipped round is recorded as `Not decided`, never as a guess.

**Inception verifies before it scopes, and writes a PRD a spec can be built from.** After the goal challenge, Sebastian runs a **prior-art check** (`prior_art`, ON by default): he states the queries, asks consent, searches GitHub, Packagist, and open-source catalogs for products or packages that already do it, writes `research/prior-art.md` (license, stack, last release, what it covers and lacks, adoption cost), and you record a verdict — `Build anyway`, `Adopt / fork`, `Integrate as dependency`, or `Not checked`. Then the PRD gets its nouns and paths before its features: `## User Journeys` (one per way a persona gets value — the unit a spec is cut from), `## Domain Model` (entities, states, relations, glossary), every `### FR-XXX` with a named actor and verifiable **Done means** bullets, `## Non-Functional Requirements` with a target and a verifier per row, and `## Risks & Assumptions` (riskiest assumption, kill condition, open questions with an owner, the `Not decided` list). Tom runs a ten-point **Definition of Ready**, Mark reads the decisions back in twelve lines, and only then is the PRD written. `validate-prd` reports the new sections as warnings, so older PRDs stay valid.

### Changing the PRD after inception

The PRD changes through three doors, and each one leaves a row in `## PRD Revision History`:

| The change is | Use | What it does to the PRD |
| --- | --- | --- |
| One new capability, or a shipped one that must now behave differently | `/larapilot-feature` | New `FR-XXX`, or the FR edited in place on a change request |
| A defect that shows a requirement was never written | `/larapilot-bug` | A done-means bullet under the parent FR, or an NFR row. Never a "fix FR" |
| Anything else | `/larapilot-prd` | See the revision kinds below |

| Revision kind | Example | Backlog impact |
| --- | --- | --- |
| **Editorial** | Typos, a clearer sentence, a glossary term | None |
| **Sharpen** | Done-means for an FR, a target for an NFR, an open question answered | Criteria added to open stories |
| **Re-scope** | MoSCoW change, In Scope ↔ Future Phases, Delivery Target, an FR retired | Stories created, deferred, or deleted |
| **Re-model** | Entity renamed, state added, journey split | Stories citing the old names |
| **Re-decide** | Business model, ops owner, topology, data store, prior-art verdict | Architecture stories; quote recomputed |
| **Upgrade** | An older PRD gains journeys, domain model, NFRs, risks | None |
| **Pivot** | New vision or target user | `/larapilot-inception`, current PRD as input |

- **Ids are permanent.** `FR-`, `J-`, `NFR-`, and `Q-` ids are never renumbered or reused. A dropped requirement keeps its heading with `MoSCoW: Won't` and a `Retired` line. `validate-prd` warns on `PRD_DUPLICATE_ID` and `PRD_DANGLING_REFERENCE`.
- **The backlog follows.** `php artisan larapilot:prd-impact --ids=FR-004,J-001` lists the stories that cite the ids, with the action each needs: `TODO` re-issued, `PLANNED` re-issued and planned again, `IN PROGRESS` on your consent, `REVIEW` sent back with `spec-request-changes`, `DONE` never reopened. Without `--ids` it traces the whole PRD and lists the Must requirements no story covers.
- **Nothing is written before the readback.** Mark shows before → after per changed item and asks Apply · Revise · Cancel.
- **You edited `PRD.md` by hand?** Run `/larapilot-prd "I edited the PRD by hand"`. It reads the git diff and adds what a hand edit skips: the history row, validation, the inception snapshot, and the backlog check.

### Context economy — what a skill loads

A skill opens with one command, **`php artisan larapilot:context {skill}`**. It answers the settings, the paths, what the PRD says about the project (kind, delivery target, budget, topology, …), and the runtime files that skill reads. Three things keep the context small, and keep an agent from applying a rule that is not the project's:

- **Only what the skill needs.** Every skill has its own list of packs. The heavy ones — tenancy, CI/CD, integrations, UX, deploy platforms — are named with the moment they apply and read only then.
- **Only what the settings call for.** The packs are compiled for the project into `.larapilot/cache/runtime/`: under `GITFLOW` an agent reads the `GITFLOW` rules and never sees the other two modes; a toggle that is off has no section at all. Change a setting and the next call hands out the files that changed.
- **Only once per conversation.** The call returns a session token. Passed back with `--session=`, it tells the next skill of the same conversation which files are already loaded: the skill reads the new ones and nothing else. After the conversation is compacted, `--fresh` reads everything again — a summary keeps the token and loses the rules.

| What is loaded (skill + runtime, default settings) | v4 | v5 |
| --- | --- | --- |
| `/larapilot-implement`, first skill of a conversation | ~43k tokens | ~15k |
| `/larapilot-review` after implement, same conversation | ~38k | ~2k |
| triage → bug → plan → implement → review, one conversation | ~173k | ~31k |

Commands answer with the slice, too: `spec-list` is the backlog without the bodies (`--full` for everything), and `prd-show` reads the PRD by the piece — its outline, `--ids=FR-004,J-001`, or `--section="Technical Architecture"`. A custom skill gets the same treatment: `larapilot:context my-skill --with=delivery-1,dev-docs`.

`.larapilot/shared-runtime.md` and the `runtime-*.md` files stay in the repository as the index for people, and as the fallback when the command cannot run. `.larapilot/cache/` is derived, ignores itself in git, and is rebuilt whenever it is stale.

---

## Custom skills — your own slash commands

The 29 packaged skills are the base layer. On top of them, each project can keep its **own** Boost skills in `.larapilot/skills/` — committed with the code, registered with Boost automatically, built on the same `larapilot:*` CLI. Good candidates: a pre-deploy GO/NO-GO gate, a compliance check, client release notes, your team's house rules for a Filament resource.

**Example — turn a pre-deploy checklist into `/acme-predeploy-gate`:**

```
/larapilot-custom-skill "A pre-deploy gate we run before every production release:
nothing left in review, doctor healthy, quality green, security scan when it's on, then GO or NO-GO"
```

1. **Zoey** interviews you in three rounds: one skill or a family · slash name and trigger description (the YAML `description` Boost routes on) · personas, runtime packs, and the CLI commands in order.
2. She shows the draft `SKILL.md` — front matter, Shared Runtime, Team, Config & CLI, Workflow, Output Economy — the same shape as a packaged skill.
3. **Sarah** saves it with one command, which validates it, writes the canonical copy, mirrors it into `.ai/skills/` and every agent skill folder that already exists (`.claude/skills/`, `.cursor/skills/`, …), and runs `boost:update`:

```bash
php artisan larapilot:custom-skill-add --name=acme-predeploy-gate --file=.larapilot/tmp-acme-predeploy-gate-SKILL.md
```

```json
{
  "schema": "larapilot/v1",
  "kind": "custom_skill",
  "data": {
    "skill": {
      "name": "acme-predeploy-gate",
      "relative_path": ".larapilot/skills/acme-predeploy-gate/SKILL.md",
      "registered": [".ai/skills/acme-predeploy-gate", ".claude/skills/acme-predeploy-gate"]
    },
    "boost_published": true
  }
}
```

4. Type `/acme-predeploy-gate` before the next deploy. Commit `.larapilot/skills/`; teammates run `php artisan larapilot:custom-skill-list` after `git pull` to register it on their machine.

The draft the example produces:

```markdown
---
name: acme-predeploy-gate
description: "Pre-deploy gate before a production release — GO or NO-GO. Use when someone says deploy, go live, release to production, or asks whether main is safe to ship."
---

# Acme — Pre-deploy gate

## Context
`php artisan larapilot:context acme-predeploy-gate` — read what `data.runtime.read` lists; `data.settings` is in that envelope.

## Workflow
1. `larapilot:spec-list --status=REVIEW` and `--status="IN PROGRESS"` — anything listed → NO-GO.
2. `larapilot:doctor` — `data.healthy` false → NO-GO.
3. `larapilot:quality` — an `E_QUALITY` error → NO-GO.
4. When `security_scan` is `YES`: `php artisan checkpoint:scan` — FAIL → NO-GO (or a logged waiver).
5. `larapilot:metrics` — one scope line; then GO / NO-GO, and `larapilot:notify` when notifications are on.

Stop at the first NO-GO. Never deploy, push, tag, or merge from this skill.
```

| Rule | Behavior |
| --- | --- |
| Name | kebab-case, 1–63 chars; `--name` overrides the front matter |
| Front matter | `description` is required. A file with no front matter gets a placeholder one — replace it |
| Overwrite | refused without `--force` |
| Input | `--file=`, `--content=`, or stdin |
| Register again | `custom-skill-list`, `larapilot:update`, and the dashboard **Skills** page re-register every skill on disk |
| Remove | delete the folder from the dashboard **File manager** — it removes the mirrors in `.ai/skills/` and the agent folders that still match the skill — then `php artisan boost:update`. By hand: delete `.larapilot/skills/{name}/` **and** its mirrors. Registration never deletes a copy |

Full walkthrough: [Your own skill](https://larapilot.web.ap.it/#example-custom-skill) · Contract: [Custom skills](https://larapilot.web.ap.it/#deep-dive-custom-skills).

---

## Workflow hooks — your commands on the loop

`hooks`, OFF by default. A story always moves through the same transitions — planned, started, task done, review, approved or sent back, released, shipped. **Hooks** attach your team's own commands and skills to those moments, in `.larapilot/hooks.yaml` (committed, so the team shares them):

```yaml
hooks:
  task.done:
    before:
      - name: Tests
        run: php artisan test --compact
        timeout: 900
  spec.review:
    before:
      - name: Static analysis
        run: vendor/bin/phpstan analyse --no-progress
      - skill: acme-a11y-check
  spec.approved:
    after:
      - name: Deploy to staging
        run: curl -fsS -X POST "$FORGE_STAGING_DEPLOY_URL"
  ship:
    before:
      - skill: acme-predeploy-gate
    after:
      - name: Deploy to production
        run: php vendor/bin/envoy run deploy
```

| Event | Fired by | Event | Fired by |
| --- | --- | --- | --- |
| `prd.written` | `prd-write` | `spec.review` | `spec-review` |
| `spec.added` | `spec-add` | `spec.approved` | `spec-approve` |
| `spec.planned` | `spec-plan` | `spec.changes_requested` | `spec-request-changes` |
| `spec.started` | `spec-start` | `release.shipped` | `release-ship` |
| `task.done` | `task-done` | `ship` | `/larapilot-ship`: `before` as the gate starts, `after` on a GO |

| | When | If it fails |
| --- | --- | --- |
| `before` | After the command's own checks, before anything is written | The transition is refused — `E_PRECONDITION` with `details.hooks` — and nothing is written. `blocking: false` only warns |
| `after` | Once the state is written | Reported under `data.hooks.after.warnings`; the transition stands |
| `run:` | Larapilot runs it from the project root, with the event in `LARAPILOT_HOOK_*` variables and as JSON on stdin | The answer carries the last 40 lines; the whole output is in `.larapilot/cache/hooks/` |
| `skill:` | The agent runs it. Before a transition, the command refuses until the agent reports it with `--skill-hooks-done=name`; after one, the answer lists it under `data.hooks.after.skills` | — |

What teams use them for:

- **Gates an agent cannot skip** — tests, Larastan, `npm run build`, `composer audit`, a coverage floor before `task.done` or `spec.review`. An instruction in a prompt can be forgotten; a hook runs inside the command.
- **Deploys** — staging after `spec.approved`, production after a GO from `/larapilot-ship` or after `release.shipped`.
- **Your rituals at the right moment** — a [custom skill](#custom-skills--your-own-slash-commands) as an architecture review after `spec.planned`, an accessibility pass before `spec.review`, client release notes after `release.shipped`.
- **What Larapilot does not integrate** — Teams, Mattermost, or email; a Toggl or Harvest timer on `spec.started`; a Confluence or Notion export after `prd.written`; an n8n, Zapier, or Make webhook.

```bash
php artisan larapilot:settings-set --hooks=YES
php artisan larapilot:hook-list            # what is defined, and what is wrong with the file
php artisan larapilot:hook-run task.done --phase=before --spec=US-001 --task=TASK-01 --dry-run
```

- **Nothing runs until you say so.** Install writes `hooks.yaml` with every event listed and every example commented out, and `hooks` is OFF. `LARAPILOT_HOOKS_ENABLED=false` in `.env` runs none on one machine — a CI runner, a teammate without the tools a hook calls.
- **A file with errors is a closed gate.** While hooks are on, every transition is refused until `hook-list` is clean or hooks are off; `doctor` reports it as `checks.hooks`.
- **Artisan only.** Hooks never run from the dashboard, the API, or MCP, where `hook-list` is read-only. `/larapilot/settings` shows the hooks of the project.
- **The hooks are yours.** Agents never edit a hook or turn hooks off to get past one unless you ask; `--force` on `spec-review` and `spec-approve` skips the task and feedback checks, never a hook.
- **Secrets stay in `.env`** — a hook reads them from its environment. Timeout 300 s by default (`LARAPILOT_HOOKS_TIMEOUT`), at most 3600 per hook.

---

## What lands in `.larapilot/`

| Path | Purpose |
| --- | --- |
| `config.yaml` | Connector, paths, and project `settings` — committed, so the team shares one mode |
| `docs/PRD.md` | Product Requirements Document — the living product contract |
| `backlog.yaml` · `specs/US-XXX.yaml` | User stories with their status machine |
| `plans/US-XXX-plan.yaml` | Technical plans and tasks per spec |
| `docs/devs/` | **Developer domain docs** — why the code is built the way it is (always on) |
| `docs/handbook/` | **Handbook** — the living technical + functional manual of the project (`project_docs=YES`); only its README until then |
| `docs/review/` · `test-results/` · `security/` · `launch/` · `support/` | Review findings, test evidence, OWASP assessments, launch checks, bug intake |
| `docs/quote.md` | Client quote in the PRD language (Economics) |
| `choices.yaml` | Snapshot of inception answers (kinds, targets, prior-art verdict, success signal, kill condition, ops) |
| `decisions.yaml` | Append-only journal of your explicit choices + regression guard (`decision_log`, ON) |
| `hooks.yaml` | Your commands and skills on the transitions of the loop — every example commented out until you write one (`hooks`, OFF) |
| `code-history.yaml` | Files and line ranges touched per spec/task (`code_history`, OFF) |
| `releases.yaml` | Semver release ledger (`release_mode`) |
| `tracker.yaml` | Spec → tracker issue ids — commit it, or every machine creates duplicates |
| `economics.yaml` · `economics.snapshot.yaml` · `economics.market.yaml` | Economics profile, last computed quote, researched competitors |
| `usage/` | Lucille ledger (`ledger.jsonl`) and schedule |
| `mockups/{spec}/` | Static HTML previews; `styles/{slug}/` for style variants |
| `internal-feedback/{code}.md` | PM/dev comments until **DONE** |
| `skills/{name}/SKILL.md` | Your custom Boost skills |
| `client-materials/` · `legacy/` · `research/` · `brand/` | Inputs for inception, legacy snapshots, prior-art / reference-product / parity reports, brand assets |
| `design-systems/` | Packaged references (Filament, Starter Kit, Bootstrap 5, Tailwind, AdminLTE) — add your own beside them |
| `shared-runtime.md` · `runtime-*.md` · `task-templates.md` · `integrations.md` | Rules and guides behind the skills — refreshed by `larapilot:update`, which also removes the runtime files a new version no longer ships |
| `cache/` | The runtime compiled for the settings of the project, and what each conversation already loaded — derived, never committed (it ignores itself) |
| `auth.yaml` | Hashed dashboard users — git-ignored |
| `techdocs/` | Generated Backstage TechDocs (after `larapilot:backstage-export --write`) |

---

## Developer domain docs (`docs/devs/`, always on)

The one place where the **reasoning** behind the implementation survives the session that produced it. `/larapilot-implement` writes a Markdown file per **domain / entity / feature** — `billing.md`, `user-authentication.md`, `webhook-ingestion.md` — with a fixed skeleton:

| Section | What it carries |
| --- | --- |
| `Purpose` | What the domain is responsible for, in business terms |
| `Functional flow` | How it behaves step by step at runtime, failure paths included |
| `Technical design` | Models, services, actions, jobs, events, routes, commands, config keys, tests — and where they live |
| `Architectural choices` | What was chosen, **what was rejected, and why** |
| `Key decisions & invariants` | The rules that must stay true, and what breaks when they do not |
| `Extension points & gotchas` | Where to plug new behavior in, and the traps |

Three rules make it useful instead of decorative:

- **Always English**, whatever language the PRD and the conversation use — these files address whoever inherits the codebase.
- **Updated in the same spec that changes the behavior**, inside the task commit. A task is not `task-done` while its domain doc describes the old code, and a domain whose code moved without its doc is a **High** review finding.
- **Never deferred.** Domain docs survive `effort: ECO` — the prose gets terse, the file still gets written.

There is no setting to turn them on: the folder ships with its contract (`README.md`) and skeleton (`TEMPLATE.md`), and `/larapilot-ship` blocks on a stale one. Path key: `paths.dev_docs`. Full contract: `.larapilot/runtime-dev-docs.md`.

**A project with no docs is brought level on the first change, not gradually.** `config-show` reports `data.dev_docs.documented`; when it is `false` and the codebase already has domains, the first spec, fix, or hotfix documents **every** existing domain, commits the backfill on its own (`docs(US-XXX): bring developer domain docs level`), and only then runs its own work. `/larapilot-adopt` does the same at the end of onboarding. Where the original reasoning is unrecoverable, the file says `<!-- TODO: verify -->` rather than inventing a motive.

This is not the handbook (`docs/handbook/`) — that optional manual is a mixed technical/functional manual for the whole project. `docs/devs/` is engineering-only and mandatory.

---

## Project settings

Set with `/larapilot-settings` or `php artisan larapilot:settings-set --{setting}=VALUE` (for example `--effort=ECO`); read with `php artisan larapilot:config-show --only=settings`.

| Group | Keys → default |
| --- | --- |
| Process | `effort` → `STANDARD` (`ECO` · `MAX`) · `backlog` → `STANDARD` (`LEAN` · `GRANULAR`) · `git_mode` → `GITFLOW` (`NO_GITFLOW` · `GITFLOW_PUSH`) · `testing` → `NORMAL` (`MINIMAL` · `BEST`) · `auto_approve` → `NO` |
| Tracking | `lucille` → `YES` (switched off by `ECO` unless you pass `--lucille=YES`) · `decision_log` → `YES` · `code_history` → `NO` |
| Discovery | `prior_art` → `YES` (Sebastian's existing-solutions search at inception, consent asked before every search) |
| Business | `account` → `NONE` (`FREELANCE` · `COMPANY` unlock Economics) |
| Delivery extras | `release_mode` → `NO` · `project_docs` → `NO` |
| Automation | `hooks` → `NO` ([workflow hooks](#workflow-hooks--your-commands-on-the-loop) from `.larapilot/hooks.yaml`) |
| Access & security | `comments` → `NO` · `dashboard_auth` → `NO` · `api_auth` → `NO` · `security_scan` → `NO` |
| Integrations | `github` · `gitlab` · `bitbucket` · `azure` · `notifications` · `notify_slack` · `notify_discord` · `notify_telegram` → all `NO` |

```bash
php artisan larapilot:settings-set --effort=ECO --testing=MINIMAL
php artisan larapilot:settings-set --release-mode=YES --comments=YES
```

`ECO` never spawns sub-agents and defers docs theater (README, PDFs, diagrams) — but still updates OpenAPI when an API changes, and still writes the developer domain docs. Playwright/E2E only runs under `testing=BEST`.

### Two configuration layers

| Layer | File | Owns | Changed via |
| --- | --- | --- | --- |
| **Laravel config** | `config/larapilot.php` (publishable) + `.env` | Environment toggles: routes, diagnostics, `LARAPILOT_API_TOKEN`, webhooks and tokens, Backstage and tracker credentials, package defaults | `php artisan vendor:publish --tag=larapilot-config`, env vars |
| **Project workflow** | `.larapilot/config.yaml` (committed) | Per-project `settings`, paths, statuses | `/larapilot-settings` or `larapilot:settings-set` |

The YAML wins for workflow settings; Laravel config only provides the defaults at install. Machine-specific paths (like an external frontend repo) live in `.env`, never in committed YAML.

---

## Dashboard & API (dev/staging)

Available when `APP_ENV` is `local`, `development`, `testing`, or `staging` — **never in production**.

| Page | URL | What you see |
| --- | --- | --- |
| Board | `/larapilot` | Kanban by status, with search and priority / epic / status filters; counts and metrics follow the cards on screen. **Download status (.md)** saves the board as it stands, filters included |
| PRD | `/larapilot/prd` | Rendered PRD with a **search** that looks in the PRD and nowhere else, decision journal timeline, **Download PRD (.md)**, and a **functional analysis summary** download (one Markdown file in the PRD language, requirements numbered by priority) |
| Inception | `/larapilot/inception` | Discovery choices snapshot |
| Plan | `/larapilot/plan` | Epics, milestones, schedule criticality, and the delivery forecast: a dependency-aware Gantt with open work queued from today, one spec at a time. Re-planned with `/larapilot-schedule`. **Download plan (.md)** saves the epics, every story with its status, priority, points, release, blockers, and forecast window, the tasks of each planned story, the milestones, and the delivery order |
| Design | `/larapilot/design` | Every screen first, as a card. A click opens that mockup as a site you browse, with **All screens** to come back; prev / next through every flow, style compare with **Use this style**, zip download |
| Settings | `/larapilot/settings` | Every project mode with its options explained |
| Skills | `/larapilot/skills` | Every skill the agents of the project can run, whoever brought it — the project, Larapilot, another package, Laravel Boost, a hand that dropped it into the folder of an agent — with which agent has it. Click one to **read it**. Under them, **what the agents are told**: `CLAUDE.md`, `AGENTS.md`, and the rules around them, one part for each author |
| File manager | `/larapilot/files` | The five material folders — `brand/`, `client-materials/`, `design-systems/`, `legacy/`, `skills/` — each with what it is for. Browse the tree, preview, read a PDF in the page, download, upload files or a whole folder (structure kept), rename, delete. A sixth folder, **Project**, shows the application itself, read only |
| Database | `/larapilot/database` | The tables and views of the database in `.env`, whatever the driver — MySQL, MariaDB, PostgreSQL, SQLite, SQL Server. Rows a page at a time with sort, search, and foreign keys that lead to the row they point at; the structure of each table; **Download SQL** for a dump of the whole database. Read only; passwords and tokens are never shown |
| Git | `/larapilot/git` | 12-month contribution heatmap, every branch measured against the branch it is heading for, and the history drawn as a graph with each commit on the branch it was made on. Filterable by developer |
| Usage | `/larapilot/usage` | Lucille's token and hour ledger + Markdown report |
| Security | `/larapilot/security` · `/larapilot/security/checkpoint` | Two tabs. **Aikido**: what Aikido found in the repository, the most severe first, with what was decided about each finding and the verdict of the ship gate. **Register for the client (.md)** downloads every finding — open, resolved, ignored with its reason. When the git remote matches no repository of Aikido, the page asks which one the project is. Always in the menu; with `aikido` off it says what Aikido is and how to connect it. **Checkpoint**: the last scan of [`andreapollastri/checkpoint`](https://github.com/andreapollastri/checkpoint) — verdict, checks by area (dependencies, configuration, code), every finding with its suppression hash, the trend of the scans — with **Run the scan** and **Download report (.md)**; when the package is missing it says how to install it |
| SBOM | `/larapilot/sbom` | Every package the project ships — Composer, the JavaScript of the repository, and the frontend companion — from the lockfiles: version, direct or transitive, production or development, license (copyleft flagged), abandoned packages. **Check vulnerabilities** asks OSV.dev; the vulnerable packages come grouped with the version that fixes them and the command to run, and what was decided about each. Downloads: **SBOM (.md)**, **CycloneDX (.json)**, **Vulnerabilities (.md)** |
| Errors | `/larapilot/errors` | What the running application threw, as the tracker of the project recorded it: one row for each bug, how many times it was thrown, and what was decided about it — day by day when the tracker records every throw. Always in the menu; with `errors` off it says what Boogle is, how to connect it, and which other trackers can be read instead |
| Economics | `/larapilot/economics` | What the project costs, what the client pays, what is left for you — every sum written as a receipt (`account` ≠ NONE) |
| Spec | `/larapilot/specs/{code}` | Story, plan, tasks, mockups, decisions, internal feedback. **Download spec (.md)** saves all of it, tasks included, in one file |
| API docs | `/larapilot/api/docs` | Swagger UI over the JSON API |
| Docs | `/larapilot/docs` | Delivery loop, packaged skills, persona roster |
| About | `/larapilot/about` | What the project runs on: Laravel, PHP, the database server, Node — each with its upstream support window drawn as a bar (bug fixes, security fixes, today) and an alert when it is past or near its end — the project, PHP extensions and limits, connections, drivers, the packages that shape an upgrade (Filament, Nova, Livewire, Inertia, …), frontend, CI and deploy, and every file that pins a version |

The dashboard follows the system theme; pin **light** or **dark** from the sidebar. Every page works on a phone.

### File manager

`/larapilot/files` manages what you hand the skills before they start:

| Folder | What it is for |
| --- | --- |
| `brand/` | Logo, palette, typography, and the brand guide |
| `client-materials/` | Briefs, analyses, and documents supplied by the client |
| `design-systems/` | Visual references and tokens the mockups are built on |
| `legacy/` | Snapshots of the old system to port or migrate |
| `skills/` | Your custom skills, one folder per slash command |
| `./` — **Project** | The Laravel application itself: code, config, routes, tests. **Read only** |

- **Adding, renaming, and deleting** files and folders work in the five material folders. **Project is read only**: you browse, preview, and download; the routes that write do not know the folder and the service refuses a write to it.
- **Folders that start with a dot are left out of Project** — `.git`, `.larapilot`, `.github`, `.idea`, at any depth. Files that start with a dot are shown. `vendor/` and `node_modules/` can be opened and are left out of the count.
- **Credentials show their keys, never their values.** `.env`, `.env.*`, `auth.json`, `.npmrc`, `.netrc`, `.pgpass` are shown with every value replaced by `*****************`, on screen and in the download; a template such as `.env.example` is shown as it is. A key or certificate is one line of asterisks; a database (`.sqlite`, `.db`) is listed and neither shown nor downloaded.
- **A PDF is read in the page**: one page or two side by side, zoom, page jump, full screen. The reader is PDF.js from cdnjs, checked against its hash; where it cannot load, the file opens in its own tab.
- **Upload a folder** and it keeps its structure; an existing file is kept unless **Replace existing files** is on. One file is limited by `LARAPILOT_FILE_MANAGER_MAX_UPLOAD_KB` (default 50 MB) and by PHP's `upload_max_filesize` / `post_max_size`, whichever is lower.
- **Local by default.** The file manager is open in `local`, `development`, and `testing`. On any other environment (`staging`) it is served only when `dashboard_auth` is `YES` — client documents and legacy snapshots stay behind a sign-in.
- Nothing outside the six folders can be reached, a symlink is never followed, and an uploaded `.html`, `.js`, or `.svg` is never run in the dashboard. `LARAPILOT_FILE_MANAGER=false` removes the page.

### Database

`/larapilot/database` shows the application's own database — the connection named by `DB_CONNECTION`, with the `DB_*` values in `.env` — through Laravel's schema and query builders, so the page is the same on MySQL, MariaDB, PostgreSQL, SQLite, and SQL Server. It reads, and never writes.

- **The list**: every table and view, with its size where the driver reports one, and the driver, database, and host on top — never the password. On MySQL/MariaDB only the database in `.env` is listed; on PostgreSQL every schema is, a table outside `public` named `schema.table`. A connection `prefix` is left out of the names.
- **Rows**, 50 to a page (`LARAPILOT_DATABASE_VIEWER_PER_PAGE`), by primary key. Click a column to sort; the search looks in the text columns, without regard to case; a foreign key value links to the row it points at. Click a row to open it whole — long text in full, JSON indented, **Copy as JSON**. Binary is shown as hex with its size.
- **Structure**: columns (type, null, default, primary key, auto increment, comment), indexes, foreign keys with their on update / on delete.
- **Credentials are never shown.** A column named like a password, token, or secret (`*password*`, `remember_token`, `token`, `*_token`, `*secret*`, `two_factor_recovery_codes`, `*api_key*`, `*private_key*`) is shown as `*****************` and is never searched, sorted, or filtered on. Add patterns in `database_viewer.masked_columns` of `config/larapilot.php`.
- **Download SQL** writes the whole database as one `.sql` file in the dialect of its driver — structure, every row, then indexes, keys, sequences, and views — to restore with `mysql`, `psql -f`, `sqlite3`, or `sqlcmd`. MySQL, MariaDB, and SQLite give their own `CREATE` statements; PostgreSQL is rebuilt from its catalogs like `pg_dump` (schemas, enum types, serial and identity columns with their next value); SQL Server from Laravel's schema builder. Each table and view is dropped first if it exists. It is written while it downloads, from one read-only snapshot.
- **The dump leaves credentials out**: the hidden columns are written as `NULL`, or `''` where `NULL` is not allowed — `hidden-1`, `hidden-2`, … where a unique index holds the column, so the file still restores — and listed at the top of the file. **Include passwords and tokens** puts them in — offered only in `local`, `development`, and `testing`.
- **Local by default**, like the file manager: open in `local`, `development`, and `testing`; elsewhere served only when `dashboard_auth` is `YES`. `LARAPILOT_DATABASE_VIEWER_CONNECTION` reads another connection; `LARAPILOT_DATABASE_VIEWER=false` removes the page.

### JSON API

| Endpoint | Returns |
| --- | --- |
| `GET /larapilot/api/board` | Metrics and specs grouped by status |
| `GET /larapilot/api/specs` · `/specs/{code}` | Specs with task progress, mockups, feedback (paginated, `?status=`) |
| `POST /larapilot/api/specs/{code}/comments` | Append internal feedback (`comments=YES`) |
| `GET /larapilot/api/prd` | PRD Markdown and heading index |
| `GET /larapilot/api/metrics` | Delivery snapshot + effort timing |
| `GET /larapilot/api/economics` | Economics snapshot; what-if parameters (`?hourly_rate=70&discount_pct=10&tier=premium`) are computed, never stored |
| `GET /larapilot/api/diagnostics` | Read-only runtime snapshot for bug triage |
| `GET /larapilot/api/backstage` · `/backstage/catalog-info.yaml` | Backstage entities and delivery snapshot |
| `GET /larapilot/api/openapi.json` · `/docs` | OpenAPI 3 document and Swagger UI |

Rate limited per IP (`LARAPILOT_API_RATE_LIMIT`, default `120,1`), mutating requests audited to `.larapilot/api-audit.log`, `ETag` / `304` on the read endpoints. Workflow **state** changes only through skills and Artisan — never from the dashboard or the API.

---

## Economics (`account`, NONE by default)

`settings.account` is `NONE` | `FREELANCE` | `COMPANY`. Freelance uses sole-trader regimes (Italian forfettario, IRPEF, autónomo, …); company uses corporate tax plus dividend extraction (SRL, SPA, Ltd, GmbH, C-Corp, …), FY-2026 rates across 38 countries. Either unlocks `/larapilot-economics` and `/larapilot/economics`.

```bash
php artisan larapilot:settings-set --account=FREELANCE
php artisan larapilot:economics-set --country=IT --regime=forfettario_15 --hourly-rate=55
php artisan larapilot:economics-set --product-model=saas --price-monthly=29 --churn=4
```

- **The page is written for someone who has never read a balance sheet.** The answer comes before the detail, no figure appears without the sum that produced it, and a word of finance appears only in the glossary.
  - **The short answer** — one sentence and four figures: what the client pays, what you keep (and how much of every 100 of the price that is), the time to deliver, the upkeep each year.
  - **Two receipts, each under a bar drawn to scale** — *how the price is built* (work + running costs + margin − discount = client price, + VAT = what the client pays) and *where the money goes* (client price − running costs − tax and contributions − accountant = what you keep).
  - **A subscription, one step at a time** — what one customer leaves, what the product costs every month, how many customers it takes with the division written out, and a chart of **when the money comes back** that reads any month by pointer or keyboard and tells the same story in sentences. Three forecasts side by side, each with a verdict in words.
  - **Words used here** — VAT, margin, net, fixed costs, churn, break-even, and the rest, each with the figure it has in this project.
- **A console of dropdowns** (rate, margin, discount, team size, regime, how it is sold, price line, scenario, …) recomputes everything server side. Nothing is written from the browser: a simulation prints the `economics-set` command that would make it real.
- **Hours come from the backlog** — planned task hours per spec, story points where no plan exists — and the snapshot refreshes itself whenever specs, plans, the PRD, or inception change.
- **Sold as** `fixed` · `saas` · `ecommerce` · `package` changes how payback is read, never the hours or the build price. On `auto` it follows the Business Model answer from inception.
- **Market research** from Jennifer and Benjamin (`larapilot:economics-market-write`) is plotted, never invented; skipped on a one-off client delivery unless you ask.
- **The maintenance retainer** is priced from the inception answers (delivery target, who runs the server, support window, ship method) from a 12% baseline, and the page shows the arithmetic.
- **The client quote** is written by `/larapilot-economics` in the PRD's own language (`.larapilot/docs/quote.md`, download at `/larapilot/economics/quote.md` or `economics-show --format=quote`), with infrastructure and security chapters derived from the project's real settings. A built-in template covers `en` · `it` · `es` · `fr` · `de` · `pt` · `nl` · `pl` until one is written.

Figures are planning estimates, not tax advice.

---

## Traceability

### Decision journal & regression guard (`decision_log`, ON by default)

Every explicit choice — a fixed-choice answer or a free-text directive like _"the background must be orange"_ — is appended, with a timestamp, to `.larapilot/decisions.yaml`:

```bash
php artisan larapilot:decision-log --topic="background color" --value="orange" --source=chat --skill=larapilot-inception
```

Before a later phase records a **different** value for the same topic, it runs `larapilot:decision-check --topic="background color" --value="red"`. If that contradicts an earlier decision, the skill asks you to confirm — _"on 2026-05-01 you chose **orange**; confirm **red** supersedes it"_ — and re-logs with `--supersedes=<id>`. The file is never rewritten. Turn it off with `--decision-log=NO`.

### Code change history (`code_history`, OFF by default)

```bash
php artisan larapilot:settings-set --code-history=YES
# after each task-done, /larapilot-implement runs:
php artisan larapilot:code-log --spec=US-014 --task=TASK-03 --skill=larapilot-implement
php artisan larapilot:code-history --file=app/Models/Post.php   # where has this file been worked on?
```

---

## Security

| Gate | Default | Turn it on |
| --- | --- | --- |
| **Dashboard auth** — HTTP Basic Auth on the `/larapilot` UI | OFF | `larapilot:dashboard-user add andrea` then `--dashboard-auth=YES` |
| **API token** — bearer token or `X-Larapilot-Token` on `/larapilot/api/*` | enforced when `LARAPILOT_API_TOKEN` is set | set the env var |
| **API auth** — token mandatory; fails closed (`503`) with no token configured | OFF | `--api-auth=YES` |
| **Security scan** — [`andreapollastri/checkpoint`](https://github.com/andreapollastri/checkpoint) in review and pre-ship; the last scan on **Security → Checkpoint** | OFF | `composer require --dev andreapollastri/checkpoint` then `--security-scan=YES` |
| **Vulnerable dependencies** — every package of the SBOM against [OSV.dev](https://osv.dev), at the ship gate and on the **SBOM** page | on demand | nothing: `php artisan larapilot:vendor-audit` or `/larapilot-vendor-check` |
| **Aikido** — the findings of [Aikido](https://www.aikido.dev/) for the repository, in triage and at the ship gate | OFF | credentials in `.env`, then `--aikido=YES` |
| **Production errors** — what the running application throws, read from [Boogle](https://boogle.web.ap.it/), Sentry, Bugsnag, Flare, Datadog, Rollbar, Honeybadger, or CloudWatch, in triage and on the dashboard | OFF | a credential that reads the tracker in `.env`, then `--errors=YES --errors-provider=…` |

- Dashboard credentials are argon2id/bcrypt hashes in `.larapilot/auth.yaml` (git-ignored, no database, no `User` model); failed sign-ins are rate-limited per IP (`LARAPILOT_DASHBOARD_AUTH_MAX_ATTEMPTS`, default 30/min). The dashboard gate never touches the API or MCP, and the API gate never touches the dashboard.
- Without a token, API reads stay open in the allowed environments but **writes are refused** outside local/development/testing.
- The dashboard **file manager** and **database** pages are stricter than the rest of the UI: outside local/development/testing they answer `404` — reads and writes alike — until `dashboard_auth` is `YES`.
- With `security_scan=YES`, review and ship run `larapilot:checkpoint-scan`: `FAIL` findings block the review (fix them, or log a waiver with `larapilot:decision-log`); `WARN` findings become notes. Larapilot never bundles the scanner; with the setting off it runs only when someone asks — the command, or **Run the scan** on the dashboard. The result stays in `.larapilot/cache/checkpoint/`, out of git: the details can quote code.

### Checkpoint and the SBOM

```bash
php artisan larapilot:checkpoint-scan --report        # runs checkpoint:scan --json, keeps it for Security → Checkpoint
php artisan larapilot:checkpoint-scan --only="Hardcoded Secrets" --gate   # exit 1 when a check fails
php artisan larapilot:sbom                            # inventories, totals, licenses, abandoned packages
php artisan larapilot:sbom --write=both               # docs/security/sbom.md and sbom.cdx.json (CycloneDX 1.5)
php artisan larapilot:vendor-audit --report --gate    # OSV.dev; exit 1 on an open advisory at --fail-on=high or above
php artisan larapilot:vendor-link GHSA-xxxx-xxxx-xxxx --spec=US-012      # the spec that fixes it
php artisan larapilot:vendor-link GHSA-xxxx-xxxx-xxxx --waive --reason="Never fed user input."
```

- **The SBOM** reads `composer.lock`, the JavaScript lockfile of the repository (`package-lock.json`, `pnpm-lock.yaml`, `yarn.lock`, `bun.lock`), and the one of the **frontend companion** when it is linked — its own, or the workspace's in a monorepo. Nothing is installed or downloaded.
- **The vulnerability check** sends the name and the version of each package — nothing else — to OSV.dev, the open database behind the GitHub advisories, FriendsOfPHP, and npm. No account, no key. Severity is the advisory's word, else its CVSS 3 score; each package gets the version that fixes all its advisories and the command that moves to it (`composer update …`, `npm update …`, or a new constraint when the current one does not allow the fix).
- **Decisions** — the spec that fixes an advisory, or a waiver with its reason — live in `.larapilot/vendor-audit.yaml` with the trend of the checks: commit it. The advisories are cached in `.larapilot/cache/`. **`/larapilot-ship`** runs `vendor-audit --gate`: an open advisory at `high` or above that was not waived stops the release; one in the backlog counts until the update is merged.

### Aikido

[Aikido](https://www.aikido.dev/) scans the repository on its side — dependencies, code, secrets, infrastructure. Larapilot runs no scanner and installs nothing: it **reads what Aikido found** over the public REST API, brings it into the workflow, and **tells Aikido what you decided** about each finding.

```dotenv
LARAPILOT_AIKIDO_CLIENT_ID=
LARAPILOT_AIKIDO_CLIENT_SECRET=
LARAPILOT_AIKIDO_REGION=eu        # eu · us · au · me
LARAPILOT_AIKIDO_REPOSITORY=      # id or name in Aikido, for this machine; empty = chosen or found from the git remote
LARAPILOT_AIKIDO_FAIL_ON=high     # critical · high · medium · low · none
LARAPILOT_AIKIDO_PUSH_DECISIONS=true  # false = decisions stay in the project
```

```bash
php artisan larapilot:settings-set --aikido=YES
php artisan larapilot:aikido-status                  # setting, credentials, repository, last scan
php artisan larapilot:aikido-issues --new --report   # what nobody decided about; writes docs/security/aikido.md
php artisan larapilot:aikido-plan --ids=24,31        # group confirmed ids by kind and fix before triage
php artisan larapilot:aikido-link 24 --spec=US-012   # the spec that fixes it; leaves a note in Aikido
php artisan larapilot:aikido-link 40 --waive --reason="Internal tool, never distributed."   # ignores it in Aikido, with the reason
php artisan larapilot:aikido-push                    # tell Aikido the decisions it was not told yet
php artisan larapilot:aikido-repos                   # the repositories of the workspace; --use=12 says which one this project is
php artisan larapilot:aikido-register                # the register for the client: open, resolved, ignored with reason
php artisan larapilot:aikido-issues --gate           # exit 1 when the gate fails — for CI
php artisan larapilot:aikido-scan                    # ask Aikido to scan again
```

- **Create the credentials** in Aikido under *Settings → Integrations → Public REST API*, with the `issues:read` and `repositories:read` scopes, `issues:write` to tell Aikido your decisions, and `repositories:write` to ask for a scan. The repository has to be connected in Aikido, through the git provider.
- **The repository is asked for when it is not found.** Larapilot finds it from the git remote, by address or by name. When the code is scanned under another repository — a fork, a mirror, a different name — it does not guess: `/larapilot-aikido` asks which one it is, `/larapilot/security` shows the list with a form, and `larapilot:aikido-repos --use=12` keeps the choice in `.larapilot/aikido.yaml` for every machine. `LARAPILOT_AIKIDO_REPOSITORY` in `.env` names it for one machine.
- **`/larapilot-aikido`** downloads the open findings, lets you **confirm each one** (resolve, waive, or skip), runs **`larapilot:aikido-plan`** on the ids you chose to fix, and hands **each resolution group** to **`/larapilot-triage`** with an *Aikido finding* block — same kind and same fix together, secrets never merged. Triage measures it against the PRD like any request — a known vulnerability in shipped code is a bug, a requirement gap when no requirement names security — and `/larapilot-bug` writes the fix spec. The link between finding and spec is recorded.
- **The ship gate** stops on an open finding at `LARAPILOT_AIKIDO_FAIL_ON` or above that was not waived. A finding in the backlog is not fixed: it counts until Aikido no longer reports it, after the fix is merged and scanned.
- **A waiver needs a reason**, in a sentence, and only the user gives it.
- **Decisions are told to Aikido.** A waiver **ignores the finding in Aikido**, with the reason as its comment; a spec leaves a note on the finding; `--forget` takes a waiver back. A finding that is in several repositories of the workspace is ignored in this one only. When Aikido refuses — credentials without `issues:write` — or cannot be reached, the decision is kept and `larapilot:aikido-push` tells it later. `--local` keeps one decision in the project, `LARAPILOT_AIKIDO_PUSH_DECISIONS=false` all of them.
- **The register for the client.** `larapilot:aikido-register`, or **Register for the client (.md)** on `/larapilot/security`, gives one Markdown document with every finding of the repository: open with the fix that is planned, resolved with the date, ignored with the date and the reason, and a count by severity — what a client or an auditor asks for, in the language of the PRD. A finding ignored by hand in Aikido is listed too; its reason stays in Aikido, which does not give it back.
- **What is kept**: `.larapilot/aikido.yaml` holds the decisions — ids, the spec, the reason, whether Aikido was told — and the repository that was chosen, and is meant to be committed. The credentials stay in `.env`; the access token lives in the cache and is never written to a file of the project.

### Production errors

Larapilot reads the errors the running application throws from **one tracker** and brings each bug into the workflow. `settings.errors` turns it on, and `settings.errors_provider` names the tracker. Run **`/larapilot-error`**: when no tracker is set, it asks which one — Boogle or any of the others — and turns the errors on.

| Provider | What is read | In `.env` | Closed from Larapilot |
| --- | --- | --- | --- |
| `boogle` *(default)* | Every throw the self-hosted [Boogle](https://boogle.web.ap.it/) recorded, and the outages its uptime monitor found | `LARAPILOT_BOOGLE_URL` · `LARAPILOT_BOOGLE_TOKEN` | Yes |
| `sentry` | The unresolved issues of a project | `LARAPILOT_SENTRY_AUTH_TOKEN` · `LARAPILOT_SENTRY_ORGANIZATION` · `LARAPILOT_SENTRY_PROJECT` | Yes |
| `bugsnag` | The open errors of a project | `LARAPILOT_BUGSNAG_AUTH_TOKEN` · `LARAPILOT_BUGSNAG_PROJECT_ID` | Yes |
| `flare` | The open errors of a [Flare](https://flareapp.io/) project | `LARAPILOT_FLARE_TOKEN` · `LARAPILOT_FLARE_PROJECT_ID` | Yes |
| `datadog` | The open issues of [Datadog](https://www.datadoghq.com/) Error Tracking — or the error logs, with `LARAPILOT_DATADOG_SOURCE=logs` | `LARAPILOT_DATADOG_API_KEY` · `LARAPILOT_DATADOG_APP_KEY` | Yes — not with logs |
| `rollbar` | The active items of a project | `LARAPILOT_ROLLBAR_ACCESS_TOKEN` | Yes |
| `honeybadger` | The unresolved faults of a project | `LARAPILOT_HONEYBADGER_AUTH_TOKEN` · `LARAPILOT_HONEYBADGER_PROJECT_ID` | Yes |
| `cloudwatch` | The error lines of an AWS CloudWatch log group, through the AWS CLI signed in on the machine | `LARAPILOT_CLOUDWATCH_LOG_GROUP` | No |

Every credential is one that **reads** the tracker — never the key the application reports with (`FLARE_KEY`, `BUGSNAG_API_KEY`, `ROLLBAR_TOKEN`, `HONEYBADGER_API_KEY`). The optional variables of each tracker are in `.larapilot/integrations.md` → *Production errors*, and `larapilot:errors-status` names every one that is missing.

```bash
php artisan larapilot:settings-set --errors=YES --errors-provider=sentry
php artisan larapilot:errors-status                                # setting, tracker, credentials, project, what is missing
php artisan larapilot:errors-list --new --kind=error --report      # what nobody decided about; writes docs/support/errors.md
php artisan larapilot:errors-plan --codes=BUG12,BUG21              # group the confirmed codes before triage
php artisan larapilot:errors-link BUG12 --spec=US-012              # the spec that fixes the bug that code belongs to
php artisan larapilot:errors-link BUG21 --ignore --reason="The mail provider was down on its side."
php artisan larapilot:errors-resolve BUG12                         # once the fix is released: closes it in the tracker
```

The skill and the commands are the same for every tracker. The names they had when Boogle was the only one still answer — `boogle-status`, `boogle-errors`, `boogle-plan`, `boogle-link`, `boogle-resolve` — and the ledger keeps its name, `.larapilot/boogle.yaml`. `--boogle=YES` still works: it means `--errors=YES --errors-provider=boogle`, and a project that turned Boogle on before 4.1.3 has nothing to change.

**Boogle** is the self-hosted one — an exception tracker and uptime monitor the application sends to with [`andreapollastri/boogle-client`](https://github.com/andreapollastri/boogle):

```dotenv
LARAPILOT_BOOGLE_URL=https://boogle.example.com   # empty = taken from BOOGLE_SERVER
LARAPILOT_BOOGLE_TOKEN=                            # the token of an admin user of Boogle
LARAPILOT_BOOGLE_PROJECT=                          # id or title; empty = found from BOOGLE_PROJECT_KEY, then APP_URL
```

- **One entry for each bug.** Boogle and logs keep a row for each time an exception is thrown: Larapilot puts together the rows that share the exception, the file, and the line, and says how many times and on how many routes. Sentry, Bugsnag, Flare, Rollbar, Honeybadger, and Datadog Error Tracking group by themselves: their grouping and their count are kept. A file of the server (`/home/forge/…/releases/…/app/Services/X.php`) is read as the file of the repository it is.
- **`/larapilot-error`** downloads the open errors, lets you **confirm each bug** — resolve, ignore with a reason, or skip — runs **`larapilot:errors-plan`** on the codes you chose to fix, and hands **each resolution group** to **`/larapilot-triage`** with a *Production error* block. The same exception in the same folder of the application goes together, so one spec fixes it; **outages**, errors in a **package**, and application code never merge. `/larapilot-bug` then writes the fix spec, with a test that throws the same exception before the fix.
- **A decision is about the bug**, not about one time it was thrown: the next time it happens it is not handed over again. `.larapilot/boogle.yaml` holds the decisions and is meant to be committed.
- **Back after the fix.** An error closed with `errors-resolve` and thrown again is shown as such, first in the list: the fix did not hold.
- **Personal data stays in the tracker.** The user, the query string, and the payload of a request are never read into a file, a report, the cache, or the chat. Larapilot keeps the exception, the message with addresses and long secrets masked, the file and line, the method and the path — with ids and tokens in the path replaced by `{id}` and `{token}`.
- **Writing to the tracker is asked for.** `errors-resolve` is the only command that writes there; it runs when you say so, is not allowed through the MCP tool, and refuses where nothing can be closed — CloudWatch, and the logs of Datadog.
- **On the dashboard**, `/larapilot/errors` shows every bug with how many times it was thrown and what was decided. A tracker that records every throw also gets the chart of the last two weeks, day by day.

### Diagnostics (bug triage)

A read-only runtime snapshot — app info, health checks (`storage_writable`, `cache`, `database`, `queue`, `log_file`), and a log tail with **secrets redacted** — that never mutates workflow state.

| Surface | How |
| --- | --- |
| CLI | `php artisan larapilot:diagnostics` (`--lines=`, `--no-logs`) — no token needed |
| MCP | the `diagnostics` tool |
| API | `GET /larapilot/api/diagnostics?lines=100&no_logs=1` — same gate as the rest of the API; `404` when `LARAPILOT_DIAGNOSTICS_ENABLED=false` |

---

## Upgrades — Laravel, PHP, database

Three skills move the project to a new version, and all three start the same way: a **readiness report**, then the **criticalities** — `blocker`, `high`, `medium`, `low`, `info` — and nothing changes until you choose **upgrade now**, **add it to the backlog**, or **stop at the report**.

```bash
php artisan larapilot:stack                          # Laravel, PHP, database, packages, frontend, pins — with support windows
php artisan larapilot:upgrade-check --laravel=13 --report
php artisan larapilot:upgrade-check --php=8.4        # --php-from=8.2 when composer.json does not say
php artisan larapilot:upgrade-check --db=pgsql:17 --db-from=mysql:8.0
php artisan larapilot:upgrade-check --laravel=13 --offline   # the lock only, no Packagist
```

- **`upgrade-check`** reads `composer.lock` and asks Packagist which release of each direct dependency supports the target Laravel — following `self.version` into the packages of the same vendor, so Filament is measured by `filament/support` — on the PHP that Laravel needs. Each package gets a verdict: `ok`, `update` (fits the constraint), `bump` (a new constraint, often a major: read its guide), `blocker` (no release supports the target yet), `abandoned`, `private` (Nova, Spark, a Satis: Packagist cannot see it). It lists every file that pins a version (Dockerfile and compose images, CI matrices, `vapor.yml`, `.php-version`, `.nvmrc`, `config.platform.php`, `phpstan.neon`, `rector.php`), scans the code for what the target PHP deprecates and for SQL the target database does not speak, and writes the report to `.larapilot/docs/upgrades/` (`paths.upgrades`). Composer has the last word: the report names the `--dry-run` that asks it.
- **Upgrade now** runs on its own branch (by `git_mode`), after a baseline of the suite: PHP first when the target Laravel needs it, **one Laravel major at a time**, the database last; each step is the Composer change, the upgrade guide of that version (read through Boost `Search Docs`, never from memory), the package playbooks — Filament's upgrade script, `livewire:upgrade`, Inertia server and client together, Nova's guide — the gates (`about`, `route:list`, `config:cache`, tests, Pint, Larastan, the build), and one commit. The end is an **upgrade report** with the deploy runbook and the rollback.
- **`/larapilot-db-upgrade`** never touches a production or shared database: it makes the code portable, proves it on a local rehearsal against a scratch database, and writes the data move (pgloader for MySQL → PostgreSQL) and the cutover.
- **About** on the dashboard shows the same facts: each version with its support window, and the command that checks the next upgrade.

## Integrations

All optional, all OFF until configured. Credentials live in `.env` — never in `.larapilot/`. Setup guide: `.larapilot/integrations.md`.

### Forges & notifications

`github` / `gitlab` / `bitbucket` / `azure` add PR/MR URLs through `gh`, `glab`, the Bitbucket Cloud API, or `az` / the Azure DevOps REST API — orthogonal to `git_mode`. Probe with `larapilot:github-status` (and `gitlab-`, `bitbucket-`, `azure-status`). `notifications` plus `notify_slack` / `notify_discord` / `notify_telegram` fan out task, spec, PR, schedule, and ship events through `larapilot:notify` (`LARAPILOT_SLACK_WEBHOOK_URL`, `LARAPILOT_DISCORD_WEBHOOK_URL`, `LARAPILOT_TELEGRAM_BOT_TOKEN` + `_CHAT_ID`).

### Frontend companion — split repo

When **Frontend Topology** is `API + external frontend`, **Laravel stays the only cockpit**: PRD, backlog, plans, and every `/larapilot-*` command run in the backend workspace, and the frontend repo is a linked write target — a single app, or a monorepo shared with other products, in Angular, React / Next.js, Vue / Nuxt, or Svelte / SvelteKit.

```bash
php artisan larapilot:frontend-set --path=/absolute/path/to/fe-repo     # writes LARAPILOT_FRONTEND_REPO_PATH to .env
php artisan larapilot:frontend-scan                                     # workspace, projects, agent rules, conventions, commands
php artisan larapilot:frontend-set --project=portal --project=admin     # the projects of this product in a monorepo
php artisan larapilot:frontend-rules --file=apps/portal/src/app/orders/order-list.ts   # the rules that govern a file
php artisan larapilot:frontend-brief US-012                             # handoff: the brief the frontend team builds from
```

- **Workspaces** — Nx (the graph of `nx graph` when Nx is installed, cached until the workspace moves; the files otherwise, plugin-inferred targets included), Angular CLI, pnpm / yarn / npm / bun workspaces with Turborepo or Lerna, Rush, or one app. Each project comes with its type, tags, targets, stack and installed version, and what it depends on.
- **An app kept in its own repository and built inside a monorepo** — a `project.json` with no `nx.json`, a `tsconfig` that extends a file two folders up: the scan finds the monorepo among the parent folders (or `frontend-set --workspace=/absolute/path` links it, in `.env`), runs the commands there, and commits in the app's own repository. An app that is a workspace of its own but sits inside a monorepo reads that monorepo's agent rules too. Every path of the scan is relative to `root`; commands run in `run_in`.
- **Target projects and write scope** — in a monorepo the user names this product's projects. Libraries only they use are *owned*; a library another app also uses is *shared* and changes only when a task names it.
- **The frontend team's rules** — `AGENTS.md` at any depth, `CLAUDE.md` with its `@imports`, `GEMINI.md`, Cursor (`.cursorrules`, `.cursor/rules/*.mdc` with `globs` / `alwaysApply`), Copilot (`copilot-instructions.md`, `*.instructions.md` with `applyTo`), Windsurf, Cline, Junie, Kiro, Amazon Q, Roo, JetBrains AI. The editor never loads them from the Laravel workspace, so implement reads them on purpose, and they win on code.
- **What the code already does** — measured on the target projects: standalone or NgModule components, `@if` or `*ngIf`, signal inputs, `inject()`, zoneless, `<script setup>`, Pinia store style, Svelte runes, test naming, styling. Recent files of each kind are the models for new ones.
- **Commands and generators** — `nx run portal:test`, `nx affected`, `ng test --watch=false` with Karma headless (the launcher the karma config defines, or `ChromeHeadless`), `turbo run --filter`, `pnpm --filter`, … with the package manager of the lockfile; the team's own Nx generators before the plugins'. A target the installed CLI can no longer run — the TSLint builder since Angular CLI 13, Protractor since 19, or one the installed package does not ship — is left out and named. A project with no spec, or a team whose generators skip them, is a question for the user.
- **Commits in the team's style** — the scan reads the history: Conventional Commits or not, types, scopes, the language of the subjects, commitlint and hooks. A task's commit follows it with `{code} TASK-NN` in the subject. Vendored packages (built code copied into the repository) are listed and never edited.
- **API client** — orval, openapi-generator, ng-openapi-gen, hey-api, openapi-typescript, kubb, RTK Query codegen: regenerated from the product OpenAPI, never edited.
- **Playbooks** — Angular, React, Vue, and Svelte defaults by major version, for what the rules and the code leave open.
- **Handoff** — `frontend-set --mode=handoff` when the frontend team builds in its own repository: `frontend-brief` writes the story, the frontend tasks, the API operations they call, and the mockups to `.larapilot/docs/frontend-briefs/`. `task-done` finds a frontend task's commit in the frontend repository.

UI tasks in a plan carry `repo: frontend` (and `project:` in a monorepo); implement writes under the env-resolved path and commits there, hooks on. Or run `/larapilot-frontend-companion`. Details: [Frontend companion](https://larapilot.web.ap.it/#deep-dive-frontend-companion).

### Project trackers — Linear, Asana, Jira, Trello, ClickUp, Monday

Mirrors the backlog into the tool the rest of the organisation already uses. **`.larapilot/` stays the source of truth** — the tracker is a window, not a second workflow.

```bash
php artisan larapilot:tracker-status --ping   # provider, status map, credentials check
php artisan larapilot:tracker-push --dry-run  # what would change, no API calls
php artisan larapilot:tracker-push            # backlog → tracker
php artisan larapilot:tracker-pull            # tracker → drift report (read-only)
php artisan larapilot:tracker-pull --apply    # write mapped statuses back
```

| Provider | Auth | Destination | Subtasks | Status maps to |
| --- | --- | --- | --- | --- |
| **Linear** | personal API key | team key | sub-issues | workflow state |
| **Jira** (Cloud, REST v2) | email + API token | project key | subtasks | status, via a workflow transition |
| **Asana** | personal access token | project gid | subtasks | section |
| **Trello** | key + token | board id | checklist items | list |
| **ClickUp** | personal token | list id | subtasks | list status |
| **Monday** | API token | board id | subitems | status-column label |

Stories become issues titled `US-XXX — Title`; plan tasks become **native** subtasks. Push is authoritative; pull is a report and changes the backlog only with `--apply`. Pull never sets a spec to **DONE** (that stays a human review gate) and never changes spec text. Set `LARAPILOT_TRACKER_ENABLED=true`, `LARAPILOT_TRACKER_PROVIDER=…`, and the provider's credentials (`LARAPILOT_LINEAR_API_KEY` / `_TEAM`, `LARAPILOT_JIRA_BASE_URL` / `_EMAIL` / `_API_TOKEN` / `_PROJECT`, …); status maps live in `config/larapilot.php`. Or run `/larapilot-tracker`. Details: [Project trackers](https://larapilot.web.ap.it/#deep-dive-tracker).

### Developer portal — Backstage

Publishes `.larapilot/` into [Backstage](https://backstage.io) — one way; the workspace stays the source of truth.

```bash
php artisan larapilot:backstage-export           # preview the bundle (writes nothing)
php artisan larapilot:backstage-export --write   # catalog-info.yaml + mkdocs.yml + .larapilot/techdocs/
```

`catalog-info.yaml` and `mkdocs.yml` are never overwritten without `--force`; TechDocs pages are regenerated. Set at least `LARAPILOT_BACKSTAGE_OWNER` (also `_SYSTEM`, `_LIFECYCLE`, `_COMPONENT_TYPE`, `_BASE_URL`). For a portal plugin, `GET /larapilot/api/backstage` returns entities and a lean delivery snapshot — call it through the Backstage backend proxy so the API token stays server-side. Or run `/larapilot-backstage`. Details: [Backstage portal](https://larapilot.web.ap.it/#deep-dive-backstage).

---

## Artisan CLI & MCP

Skills call these for you — run them by hand for scripting, CI, or debugging. Every command prints one JSON envelope: `{"schema":"larapilot/v1","kind":"…","data":{…}}`, or `"kind":"error"` with a stable `code` (`E_INVALID_INPUT` → exit 2, `E_CONNECTOR` → 3, `E_PRECONDITION` / `E_NOT_FOUND` → 4).

| Area | Commands |
| --- | --- |
| Setup & health | `install` · `update` · `doctor` (`--human`) · `context {skill}` (`--session=`, `--fresh`, `--with=`) · `config-show` (`--only=settings,paths,frontend,tracker,dev_docs,backstage,workflow,personas`) · `settings-set` · `quality` (`--fix`) |
| Access | `dashboard-user {list\|add\|remove}` |
| Discovery | `prd-write` · `validate-prd` · `prd-show` (`--ids=`, `--section=`) · `prd-impact` (`--ids=`) · `choices-set` · `frontend-set` (`--project=`, `--mode=`) · `frontend-scan` (`--project=`, `--full`, `--no-cli`, `--fresh`) · `frontend-rules` (`--file=`) · `frontend-brief` |
| Backlog | `spec-list` (`--status=`, `--full`) · `spec-add` · `spec-show` (`--task=`, `--fields=`) · `spec-next` · `spec-delete` · `validate-spec` · `spec-comment` |
| Design | `mockup-choose-style US-XXX --style=` |
| Plan & build | `validate-plan` · `spec-plan` · `spec-start` · `task-done` · `spec-review` |
| Review | `spec-approve` (`--force`) · `spec-request-changes` (`--include-feedback`) |
| Traceability | `decision-log` · `decision-check` · `code-log` · `code-history` |
| Metrics & usage | `metrics` · `usage-log` · `usage-report` (`--insights`, `--format=json\|md\|human`) · `schedule-set` (`--release=` for a milestone of one release) |
| Schedule | `schedule-show` (`--only=queue,epics,deadlines,releases,alerts,findings`) · `schedule-apply --file=` (`--dry-run`) |
| Economics | `economics-set` · `economics-show` (`--format=json\|md\|quote`) · `economics-market-write` · `economics-quote-write` |
| Releases | `release-list` · `release-add` · `release-set` · `release-cut` · `release-feature` · `release-sync` · `release-ship` (`--push`) · `release-import` |
| Custom skills | `custom-skill-list` · `custom-skill-add` (`--name=`, `--file=` / `--content=` / stdin, `--force`) |
| Hooks | `hook-list` (`--event=`) · `hook-run {event}` (`--phase=before\|after`, `--spec=`, `--task=`, `--release=`, `--dry-run`) · `--skill-hooks-done=` on every command that fires an event |
| Stack & upgrades | `stack` (`--only=`, `--no-db`) · `upgrade-check` (`--laravel=`, `--php=`, `--php-from=`, `--db=`, `--db-from=`, `--offline`, `--report`, `--gate`) |
| Dependencies | `sbom` (`--full`, `--write=md\|cyclonedx\|both`) · `vendor-audit` (`--cached`, `--new`, `--limit=`, `--report`, `--fail-on=`, `--gate`) · `vendor-link` (`--spec=`, `--waive --reason=`, `--clear`) · `checkpoint-scan` (`--only=`, `--skip=`, `--cached`, `--report`, `--gate`, `--fail-on-warn`) |
| Security | `aikido-status` · `aikido-issues` (`--new`, `--severity=`, `--type=`, `--report`, `--gate`) · `aikido-plan` (`--ids=`) · `aikido-link` (`--spec=`, `--waive --reason=`, `--forget`, `--local`) · `aikido-push` · `aikido-repos` (`--search=`, `--use=`, `--forget`) · `aikido-register` · `aikido-scan` |
| Errors | `errors-status` · `errors-list` (`--new`, `--kind=error\|outage`, `--limit=`, `--report`) · `errors-plan` (`--codes=`) · `errors-link` (`--spec=`, `--ignore --reason=`, `--forget`) · `errors-resolve` (`--status=FIXED\|DONE`, `--comment=`) — old names `boogle-status` · `boogle-errors` · `boogle-plan` · `boogle-link` · `boogle-resolve` |
| Integrations | `github-status` · `gitlab-status` · `bitbucket-status` · `azure-status` · `notify` · `tracker-status` · `tracker-push` · `tracker-pull` · `backstage-export` |
| Runtime | `diagnostics` (`--lines=`, `--no-logs`) |

All commands are prefixed `larapilot:`. Release commands need `release_mode=YES` and take `--semver=` (Artisan reserves `--version`); nothing is pushed without `--push`.

The **`larapilot` MCP server** exposes four tools: `BacklogListTool`, `SpecShowTool`, `DiagnosticsTool`, and `RunArtisanTool`, which runs only read and validate commands (`config-show`, `spec-list`, `spec-show`, `spec-next`, `metrics`, `usage-report`, `decision-check`, `code-history`, `prd-show`, `prd-impact`, the forge probes, the three validators, `doctor`, `diagnostics`, `quality`, `frontend-scan`, `frontend-rules`, `backstage-export`, `tracker-status`, `hook-list`, `context`, `schedule-show`, `stack`, `upgrade-check`, `sbom`, `vendor-audit`, `aikido-status` / `aikido-issues` / `aikido-plan` / `aikido-repos`, `errors-status` / `errors-list` / `errors-plan`, and their old names `boogle-status` / `boogle-errors` / `boogle-plan`). The parameters are checked too: each command takes through MCP only the ones that read, and an option that writes a file is refused — `quality --fix`, `backstage-export --write` / `--force` / `--catalog=` / `--mkdocs=` / `--file=`, `usage-report --output=`, `aikido-issues --report`, `aikido-repos --use=` / `--forget`, `errors-list --report`, `upgrade-check --report`, `sbom --write=`, `vendor-audit --report`. Run directly with Artisan, the commands take every option as before. All four tools are annotated as read-only (`readOnlyHint`).

---

## Requirements

- PHP **^8.1** (8.2+ recommended)
- Laravel **^10.49** · **^11.45.3** · **^12** · **^13**
- [Laravel Boost](https://laravel.com/ai/boost) **^1** or **^2** (Composer resolves Boost 1 on Laravel 10/11 and Boost 2 on Laravel 12+; kept current by `larapilot:update`)
- An MCP-capable editor (Claude Code, Cursor, VS Code, …)

On Laravel 10/11 the MCP stack pulls `illuminate/json-schema` — use a recent framework patch (Laravel **11.47+** recommended on 11.x).

Laravel **10** and **11** are past their security-fix window. Composer 2.9+ refuses every `laravel/framework` 10.x/11.x release because open advisories have no patched line (fixes shipped in Laravel **12.60+** / **13**). Larapilot's CI still runs those majors by ignoring only `laravel/framework` advisories on the 10/11 jobs. Apps still on 10/11 that fail `composer update` with *affected by security advisories* need the same ignore (`composer config --json policy.advisories.ignore '["laravel/framework"]'`) or should upgrade to Laravel 12+.

---

## Learn more

- [Version 5 vs version 4](https://larapilot.web.ap.it/#v5) — the context an agent loads, measured, and what to check when upgrading
- [How it works](https://larapilot.web.ap.it/#deep-dive-how-it-works) — skills, artifacts, CLI, runtime packs
- [Eleven use cases](https://larapilot.web.ap.it/#examples) — new product, adopt an app, Laravel package, legacy porting, feature, bug, frontend companion, tracker sync, team server, SSH & tmux, your own skill
- [Custom skills](https://larapilot.web.ap.it/#deep-dive-custom-skills) — anatomy, validation, lifecycle
- [Developer domain docs](https://larapilot.web.ap.it/#deep-dive-dev-docs)
- [Project settings](https://larapilot.web.ap.it/#deep-dive-settings)
- [Economics](https://larapilot.web.ap.it/#deep-dive-economics)
- [Design systems](https://larapilot.web.ap.it/#deep-dive-design-systems)
- [Team personas](https://larapilot.web.ap.it/#deep-dive-personas)
- [Changelog](CHANGELOG.md)

---

## License

MIT © [Andrea Pollastri](https://web.ap.it)
