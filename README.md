# Larapilot

**From product idea to reviewed Laravel code — with an AI product team that follows a real process.**

Larapilot is a spec-driven workflow for Laravel projects, built on [Laravel Boost](https://laravel.com/ai/boost). Install the package, run `/larapilot-*` skills in your AI editor, and get a PRD, a backlog, technical plans, and reviewed code — all versioned in `.larapilot/` next to your app.

**The agent proposes. You approve what ships.** Human-in-the-loop, always.

[![The Larapilot dashboard: the board of Fernway, the demo app of the documentation, sixteen stories from TODO to DONE](https://larapilot.web.ap.it/img/dashboard/board.webp)](https://larapilot.web.ap.it/#tour)

📖 **Documentation:** [larapilot.web.ap.it](https://larapilot.web.ap.it) · [Use cases](https://larapilot.web.ap.it/#examples) · [Custom skills](https://larapilot.web.ap.it/#example-custom-skill) · [API](https://larapilot.web.ap.it/#deep-dive-api)

---

## Contents

- [Why Larapilot](#why-larapilot)
- [Quickstart](#quickstart)
- [The core loop](#the-core-loop)
- [Studio — Larapilot for the whole team](#studio--larapilot-for-the-whole-team)
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

`larapilot:install` scaffolds the `.larapilot/` workspace, and also **[Larastan](https://github.com/larastan/larastan) level 5+** and **[Laravel Pint](https://laravel.com/docs/pint)** (`phpstan.neon.dist`, `pint.json`, Composer scripts). When the project has neither package it adds them with `composer require --dev`, which leaves the formatting of `composer.json` alone, and Composer picks the versions your PHP and Laravel take — Larastan 2 on PHP 8.1 / Laravel 10, Larastan 3 from PHP 8.2 / Laravel 11.15. Run `php artisan larapilot:quality` before merge; `larapilot:doctor` fails when the gate is missing. On an app with years of code never formatted, see [An existing codebase](#an-existing-codebase).

Already on Boost? Run `php artisan larapilot:update`: it adds `andreapollastri/larapilot` to the package list of `boost.json` when there is one — Boost publishes the guidelines, and on Boost 2 the skills, of the listed packages only — and republishes. By hand, choose the package in `boost:install`, or run `boost:update --discover` (Boost 2.3.2 and later).

The dashboard is at `/larapilot`: open on your machine (`APP_ENV=local`), behind a sign-in on any other environment — see [Dashboard sign-in & roles](#dashboard-sign-in--roles).

If your editor does not list the MCP servers after `boost:install`, register them:

```json
{
  "mcpServers": {
    "laravel-boost": { "command": "php", "args": ["artisan", "boost:mcp"] },
    "larapilot":     { "command": "php", "args": ["artisan", "mcp:start", "larapilot"] }
  }
}
```

With [Sail](#sail-and-docker), the command is `vendor/bin/sail` and the arguments start with `artisan`.

Then, in your editor:

| Your project | Type |
| --- | --- |
| A new idea — app, site, or Laravel package | `/larapilot-inception "your product idea"` |
| A Laravel app in production, built without Larapilot | `/larapilot-adopt` |
| A legacy system you are rewriting in Laravel | `/larapilot-inception` with a snapshot in `.larapilot/legacy/` |

### An existing codebase

`larapilot:quality` asks Pint and Larastan for a clean result before every task is done. On an app that was never formatted, or that has Larastan errors today, that gate stays red. Two settings make it fair, and `/larapilot-adopt` proposes both:

```bash
php artisan larapilot:settings-set --quality-scope=CHANGED   # Pint reads only the PHP files the branch changed
php artisan larapilot:quality --baseline                     # phpstan-baseline.neon: today's errors stay out, new ones do not
```

`CHANGED` measures a branch against the branch it came from (`develop` under Git Flow, else the remote default, `main`, `master`) — committed, staged, unstaged, and untracked files — so a task stays checked after its commit; on the base branch itself it is what is not pushed yet. `--fix` then formats only those files: a full reformat is a commit of its own, when you decide it. Larastan still reads the whole project, because a change can break a caller it did not touch; the baseline keeps the errors that were already there.

### Laravel 10, PHP 8.1, and Boost 1

Boost 2 needs PHP 8.2 and Laravel 11.45.3 or later, so a Laravel 10 or PHP 8.1 app runs Boost 1.x. Larapilot works there, with three differences:

- **Skills** — Boost 1 publishes guidelines, not skills, and its 1.x line will not get them. `larapilot:install` and `larapilot:update` copy the `/larapilot-*` skills into the skill folders of the agents `boost.json` names (`.claude/skills`, `.cursor/skills`, `.github/skills`, …) and of the agent folders already in the project, rewriting only what changed and removing a skill the package retired. `larapilot:doctor` reports a copy that is missing or out of date.
- **Guidelines** — Boost 1 adds a third-party guideline to `CLAUDE.md` / `AGENTS.md` only when the package is in the `guidelines` list of `boost.json`. Choose `andreapollastri/larapilot` in `boost:install`; `larapilot:update` adds it when the list exists without it.
- **Updates** — `larapilot:update` runs `composer update laravel/boost` only when Boost 2 can install. On Boost 1 that it cannot leave, `composer.lock` is left untouched, and `boost:update` still refreshes the guidelines.

`boost:update --discover` is a Boost 2 option (2.3.2 and later): on Boost 1, run `boost:install` again to change what is published.

### Sail and Docker

When Artisan runs in a container, the agent on the host has to go through it, and cannot open the container's paths (`/var/www/html/...`). Larapilot follows Boost: with `"sail": true` in `boost.json` (Boost asks during `boost:install`), every skill runs `vendor/bin/sail artisan`, and the JSON envelopes give paths relative to the project root. Any other setup names its command in `.env`:

```dotenv
LARAPILOT_ARTISAN="docker compose exec app php artisan"   # empty: Sail when boost.json says so, else php artisan
LARAPILOT_AGENT_PATHS=auto                                 # auto · relative · absolute
```

The Larapilot guideline tells the agent the command (re-run `php artisan larapilot:update` after changing it), and every `larapilot:context` envelope carries it as `data.artisan`. `auto` gives relative paths whenever Artisan runs through another command or inside Sail; a dev container, where the agent runs inside too, keeps absolute ones.

Sail publishes its port on every interface, so a dashboard left open on `local` is reachable from your whole network. Bind it to your machine: `APP_PORT=127.0.0.1:80` in `.env`.

### Upgrade

```bash
composer update andreapollastri/larapilot laravel/boost --with-dependencies
php artisan larapilot:update
php artisan larapilot:doctor
```

`larapilot:update` refreshes the runtime packs, `task-templates.md`, `integrations.md`, and the packaged design systems (`--preserve-design-systems` keeps yours), re-registers your custom skills, moves a handbook left in `_project_docs/` by an older version into `.larapilot/docs/handbook/`, **realigns the delivery forecast** of a project that is already planned, then runs `composer update laravel/boost` (skipped inside a Composer script, and on a Boost 1 that cannot reach Boost 2) and `boost:update` — and, on Boost 1, copies the skills itself. It does not touch `composer.json`. Runtime-only refresh: `php artisan larapilot:update --skip-boost`. It never touches `config.yaml` (except to repoint an old `paths.project_docs: _project_docs/`), the PRD, plans, domain docs, or custom skills. In the backlog and the schedule it writes only what takes no decision — an id and a plain date on a milestone that lacks them, the release a milestone is named after, the deadline of an epic on the specs of that epic that lack it — and it says what the forecast now misses: dates that do not hold and inputs it could not read are for `/larapilot-schedule`. `php artisan larapilot:schedule-apply --repair --dry-run` lists those repairs without writing them. Do not re-run `larapilot:install` on an existing project unless you mean `--force`.

**From 4.x to 5.0** — `composer update` stays inside the major your `composer.json` names, so raise the constraint first:

```bash
composer require andreapollastri/larapilot:^5.0 --dev --with-all-dependencies
php artisan larapilot:update
php artisan larapilot:doctor
```

If `composer why andreapollastri/larapilot` says *requires* rather than *requires (for development)*, Larapilot sits in `require`: drop `--dev`, or Composer moves it to `require-dev`. Nothing in `.larapilot/` is migrated: PRD, backlog, plans, decisions, and settings are read as they are. Three things to check: `spec-list` answers without the bodies (add `--full` where a script reads `body`); a custom skill that names a runtime part by its number should cite the heading, or start with `larapilot:context {name} --with=…`; `/larapilot-boogle` is `/larapilot-error`, and the `boogle-*` commands still answer. What v5 changes, measured: [Version 5 vs version 4](https://larapilot.web.ap.it/#v5). Step by step — a branch, a dry run, the output to read, the checks as `git grep` commands, and the way back: [Major releases](https://larapilot.web.ap.it/#upgrade-major).

**From 5.0 to 5.1** — `composer update` and `larapilot:update` are enough, but three things change on a shared host:

- **The dashboard asks for a user outside `local`.** HTTP Basic Auth and the `dashboard_auth` setting are gone. Every environment but `local`, `testing`, and `production` — `staging`, `qa`, `preview`, and `development` too, which 5.0 treated as a developer's machine — shows a sign-in page. Until a user exists there, the dashboard answers **HTTP 503**. Make the first admin on that server: `php artisan larapilot:dashboard-user add andrea --role=admin`. `larapilot:update` rewrites an `.larapilot/auth.yaml` of before 5.1 and gives every user in it the `admin` role — the only one there was. See [Dashboard sign-in & roles](#dashboard-sign-in--roles).
- **The API needs its token outside `local`.** It answers **HTTP 503** until one exists: make it on the **API token** page or with `php artisan larapilot:api-token generate`. Once a token exists, every request needs it — reads included, on a developer's machine too. `LARAPILOT_API_TOKEN` in `.env` keeps working.
- **`/larapilot-aikido` is `/larapilot-security`.** The `aikido-*` commands and `--aikido=YES` still work; a `config.yaml` with `aikido: true` reads as `security_provider: aikido`.

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

## Studio — Larapilot for the whole team

Larapilot runs where your agent runs: an editor or a terminal. Not everyone on a product team opens one. **[Studio](https://studio.web.ap.it/)** — by the author of Larapilot, open source — puts the same skills, the same backlog, and the same approvals on a server, behind a web page: for the product manager who decides, the developer who builds, and the client who follows.

One Ubuntu server, one script, one wildcard DNS record. Every person on the team gets a workspace on that server — their clone, their database, **their own Claude Code** — and a chat that runs the Larapilot skills of the project. You ask in plain words; Claude writes the story, plans it, implements it on a branch, and opens the pull request, while the live preview of that branch sits beside the chat.

- **The loop, as buttons** — New idea, Feedback, Status, and Approve under the chat; every `/larapilot-*` skill, and every skill of the project, typed as a slash command.
- **The backlog beside the chat** — Board, preview, changes, files, and a terminal in one pane; the site of the project serves `/larapilot` to the team signed in to Studio.
- **Projects born with Larapilot** — a repository Studio creates gets Laravel, Laravel Boost, and Larapilot, installed and committed; an existing one that runs Larapilot is cloned as it is.
- **Four roles, one Claude per person** — administrator, PM, developer, client; each conversation runs on that person's own Claude plan.
- **The forges Larapilot knows** — GitHub, GitLab, Bitbucket, or Azure DevOps; a push to the deploy branch redeploys the site.

[![Studio: Claude writes and plans US-014 with the Larapilot commands and asks before running npm run build; the preview of the branch on the right](https://larapilot.web.ap.it/img/studio/chat.webp)](https://studio.web.ap.it/#how-it-works)

```bash
# a fresh Ubuntu 24.04 or 26.04 server
wget -qO- https://raw.githubusercontent.com/andreapollastri/studio/refs/heads/main/installer/install.sh | sudo bash
```

📖 **Studio:** [studio.web.ap.it](https://studio.web.ap.it/) · [See a conversation](https://studio.web.ap.it/#how-it-works) · [GitHub](https://github.com/andreapollastri/studio)

---

## Skills

Published by Laravel Boost after `php artisan boost:install`:

| Skill | Role |
| --- | --- |
| `/larapilot-inception` | Product discovery → PRD (includes **Frontend Topology**) |
| `/larapilot-adopt` | Reverse-engineer a PRD from an existing production codebase; derives the design system of the app as it is, so its mockups mirror the running product |
| `/larapilot-spec` | MoSCoW backlog from the PRD |
| `/larapilot-feature` | Mini-inception for one enhancement |
| `/larapilot-bug` | Bug triage → fix spec or rework. Reads the logs of the application every time, secrets redacted |
| `/larapilot-triage` | Bug or feature? Classifies a request against the PRD and the backlog, then hands off to `/larapilot-bug` or `/larapilot-feature` |
| `/larapilot-security` | Reads the security scanner the project associates — **Aikido** or **Hackly** — and asks which one when none is set. Downloads its open findings, confirms them with you, groups them by fix, and hands each group to `/larapilot-triage` (`security=YES`). Was `/larapilot-aikido` before 5.1 |
| `/larapilot-error` | Asks which tracker records the **errors of production** — Boogle, Sentry, Bugsnag, Flare, Datadog, Rollbar, Honeybadger, or CloudWatch — when none is set, downloads the open ones, confirms them with you, groups them by place in the code, and hands each group to `/larapilot-triage` (`errors=YES`) |
| `/larapilot-vendor-check` | Checks every dependency — Composer, the JavaScript of the repository, the frontend companion — against **OSV.dev** for known vulnerabilities, confirms each vulnerable package with you, then updates it, hands it to `/larapilot-triage`, or waives it with a reason |
| `/larapilot-laravel-upgrade` | Upgrades Laravel to the version you name: readiness report and criticalities first, then one major at a time on its own branch — dependencies, the upgrade guide, Filament, Nova, Livewire, Inertia — with an upgrade report |
| `/larapilot-php-upgrade` | Upgrades PHP: the suite on the target version, the packages that exclude it, deprecated code, every file that pins PHP (Docker, CI, Vapor, Herd, Sail), and the server runbook |
| `/larapilot-db-upgrade` | Upgrades or switches the database — MySQL 5.7 → 8.0 → 8.4, MySQL → MariaDB or PostgreSQL, a PostgreSQL major — with portable SQL, a local rehearsal on the target engine, and the data and cutover runbook |
| `/larapilot-prd` | Revises the PRD when it is neither — sharpen, re-scope, re-model, re-decide, upgrade — and aligns the stories that cite what changed |
| `/larapilot-design` | Static HTML mockups from a design system, with style variants to compare — the contract the frontend is built to. Presents the screens, takes changes at any later moment, and can interview you to build a design system of the client's own |
| `/larapilot-plan` | Technical plan + tasks for a spec; every UI task names the mockup screens it mirrors; proposes a sprint when `sprints=YES` |
| `/larapilot-implement` | Code + tests + developer domain docs, one commit per task; the UI mirrors the approved mockups, and a deviation is asked before it is coded |
| `/larapilot-review` | Human gate → **DONE** or rework; a silent deviation from the mockups is a **High** finding |
| `/larapilot-autopilot` | Batch plan + implement, one fresh context per spec |
| `/larapilot-ship` | Security gate + deploy runbook when the MVP is done |
| `/larapilot-settings` | Persist project modes (effort, backlog, git, testing, account, API token, sprints, public roadmap, security scanner, forges, notifications, …) |
| `/larapilot-release` | The versions of the product (`release_mode=YES`): which story targets which version, where a version stands, its release notes, the ship gate, and Gitflow `release/x.y.z` branches; keeps the public wording of the [roadmap](#roadmap) current — [Versions](#versions) |
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

### Versions and sprints in the skills

- **With `release_mode` on**, `/larapilot-inception` proposes the release roadmap — which stories go in which version, the MVP first — and asks for the starting release. `/larapilot-feature` proposes a target version for every new spec: an open release, or a future one. The spec carries it as `**Release:** x.y.z`, and the Board, the Plan, the [roadmap](#roadmap), and the API read it from there. `/larapilot-release` keeps the public wording of the roadmap current after every release change. From there every skill works inside a version — see [Versions](#versions).
- **With `sprints` on**, `/larapilot-feature` and `/larapilot-plan` propose the two-week sprint a spec belongs to and write it with `php artisan larapilot:spec-sprint US-012 3` (`none` clears it). With the setting off, nobody asks.

### Mockups are the contract of the frontend

`/larapilot-design`, `/larapilot-plan`, `/larapilot-implement`, and `/larapilot-review` read the same rules:

- **The build mirrors the approved mockups** — layout, components, copy, states, responsive behaviour, dark mode. Every UI task of a plan names the screens it mirrors on a `Mirrors:` line, and implement opens them before the first line of frontend code.
- **A deviation is asked before it is coded.** When the code cannot follow a mockup — a technical limit, accessibility, a mockup that contradicts an acceptance criterion — the agent says what and why in chat, and you accept it, keep the mockup, or have the mockup changed first. An accepted deviation is logged in the decision journal and in the `## Deviations` table of the mockup README. A deviation nobody agreed to is a **High** review finding.
- **The mockups stay the mirror of the product.** After drawing, `/larapilot-design` presents the screens and asks for feedback; you can ask for changes at any later moment, and each one is recorded in the `## Revisions` log of the mockup README.
- **A design system of the client's own.** `/larapilot-design` offers an interview — brand, references, links, the files you drop in `.larapilot/brand/` and `client-materials/`, tone, density, components, accessibility, dark mode — and writes `.larapilot/design-systems/{slug}/` in the shape of the packaged ones. You approve it before any mockup uses it.
- **An adopted project mirrors what exists.** `/larapilot-adopt` derives the design system of the app from its views, CSS, brand assets, and navigation, and every mockup of that project is drawn in it. A redesign happens only when you ask for it, and is discussed first.

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

A custom skill never replaces one it did not write. A name of a Larapilot skill (`larapilot-*`) or of a skill Boost ships is refused — on the command line and in the file manager — and a folder of the same name that Larapilot did not put in an agent skill folder is left as it is, and reported. Larapilot knows its own copies by a `.larapilot-custom-skill` file inside them; `custom-skill-add --force` takes the name over.

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
| `docs/releases/vX.Y.Z.md` | The release notes of each version, kept by `release-notes` (`paths.release_notes`) |
| `tracker.yaml` | Spec → tracker issue ids — commit it, or every machine creates duplicates |
| `economics.yaml` · `economics.snapshot.yaml` · `economics.market.yaml` | Economics profile, last computed quote, researched competitors |
| `usage/` | Lucille ledger (`ledger.jsonl`) and schedule |
| `mockups/{spec}/` | Static HTML previews; `styles/{slug}/` for style variants |
| `internal-feedback/{code}.md` | PM/dev comments until **DONE** |
| `skills/{name}/SKILL.md` | Your custom Boost skills |
| `client-materials/` · `legacy/` · `research/` · `brand/` | Inputs for inception, legacy snapshots, prior-art / reference-product / parity reports, brand assets |
| `design-systems/` | The 15 packaged design systems (see [Design systems](#design-systems)) — add your own beside them |
| `shared-runtime.md` · `runtime-*.md` · `task-templates.md` · `integrations.md` | Rules and guides behind the skills — refreshed by `larapilot:update`, which also removes the runtime files a new version no longer ships |
| `cache/` | The runtime compiled for the settings of the project, and what each conversation already loaded — derived, never committed (it ignores itself) |
| `auth.yaml` | Dashboard users — password hashes, roles, authenticator secrets encrypted with `APP_KEY` — and the hash of the API token. Git-ignored |
| `auth-audit.log` | One line per sign-in, failure, second factor, user change, token, anonymization map, and file-manager zip — never a password, a code, or a token. Git-ignored |
| `anonymization.yaml` | What each column of the database becomes in an anonymized copy, and the Faker locale — committed |
| `roadmap.yaml` · `roadmap-link.yaml` | The public wording of the roadmap (committed) · the token of this host's public link (git-ignored) |
| `aikido.yaml` · `hackly.yaml` | Decisions about the findings of the security scanner and the repository chosen — committed, never a credential |
| `techdocs/` | Generated Backstage TechDocs (after `larapilot:backstage-export --write`) |

### Design systems

`/larapilot-design` draws every mockup in a design system: one of the 15 packaged in `.larapilot/design-systems/`, one built for the client from an interview, or the design system of an adopted app as it is. When you pick several, each becomes a style of the same screens on `/larapilot/design`, and **Use this style** keeps one. The frontend is then built to mirror the chosen mockups.

| Kind | Systems |
| --- | --- |
| Laravel panels and kits — they look like the real product | **Filament** 4, **Laravel Nova** 5, the **Laravel Starter Kits** (React, Vue, Svelte, and the Livewire kit's Flux look), **shadcn/ui** |
| CSS frameworks — real class names you can copy | **Bootstrap 5**, **AdminLTE 4**, **Tailwind CSS** 4, **Material Design 3** |
| Enterprise — backoffice and product site | **Fluent 2** — the look of Microsoft 365 and the Azure portal, with Fluent's own token names |
| Aesthetic — backoffice and public site | **Nordic SaaS** (the Nordic minimal default), **Modern** (Linear / Vercel-like, dark first), **Bold California** (sunny, heavy type, pill buttons), **Berlin** (Swiss grid, monochrome and one acid signal colour), **New York** (Didone headlines on newsprint, subway signage, taxi yellow), **Zen** (black and white only, empty space, hairlines) |

Every system has the same shape: a README with the mapping to real code, `components.md`, `sources.md` with what was verified and what was assumed, one `tokens.css`, and static screens in `html/` — at least a dashboard, a resource list, a resource form, sign-in, settings, the components, and an empty state, in light and dark. Screens render offline: no CDN, no web font, no script. Sign-in screens draw their fields, so password managers leave them alone. Open `html/index.html` of any system as its catalog, or browse them on `/larapilot/design`. The index of every screen is `.larapilot/design-systems/README.md`.

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
| Process | `effort` → `STANDARD` (`ECO` · `MAX`) · `backlog` → `STANDARD` (`LEAN` · `GRANULAR`) · `git_mode` → `GITFLOW` (`NO_GITFLOW` · `GITFLOW_PUSH`) · `testing` → `NORMAL` (`MINIMAL` · `BEST`) · `quality_scope` → `ALL` (`CHANGED`: [an existing codebase](#an-existing-codebase)) · `auto_approve` → `NO` |
| Tracking | `lucille` → `YES` (switched off by `ECO` unless you pass `--lucille=YES`) · `decision_log` → `YES` · `code_history` → `NO` |
| Discovery | `prior_art` → `YES` (Sebastian's existing-solutions search at inception, consent asked before every search) |
| Business | `account` → `NONE` (`FREELANCE` · `COMPANY` unlock Economics) |
| Delivery extras | `release_mode` → `NO` · `project_docs` → `NO` · `sprints` → `NO` (two-week sprints on the Board, the Plan, and in planning) · `public_roadmap` → `NO` (a public link to the [roadmap](#roadmap)) |
| Automation | `hooks` → `NO` ([workflow hooks](#workflow-hooks--your-commands-on-the-loop) from `.larapilot/hooks.yaml`) |
| Access & security | `comments` → `NO` · `api_auth` → `NO` (the API token is required on a developer's machine too; elsewhere it always is) · `security_scan` → `NO` · `security` → `NO` with `security_provider` `aikido` · `hackly` ([security scanners](#security-scanners--aikido-or-hackly)) · `errors` → `NO` with `errors_provider` ([production errors](#production-errors)) |
| Integrations | `github` · `gitlab` · `bitbucket` · `azure` · `notifications` · `notify_slack` · `notify_discord` · `notify_telegram` → all `NO` |

```bash
php artisan larapilot:settings-set --effort=ECO --testing=MINIMAL
php artisan larapilot:settings-set --release-mode=YES --comments=YES
php artisan larapilot:settings-set --sprints=YES --public-roadmap=YES --security-provider=hackly
```

There is no `dashboard_auth` setting any more: who gets into the dashboard follows `APP_ENV` — [Dashboard sign-in & roles](#dashboard-sign-in--roles). A `dashboard_auth` line left in `config.yaml` is ignored, and `--dashboard-auth` is no longer a flag.

`ECO` never spawns sub-agents and defers docs theater (README, PDFs, diagrams) — but still updates OpenAPI when an API changes, and still writes the developer domain docs. Playwright/E2E only runs under `testing=BEST`.

### Two configuration layers

| Layer | File | Owns | Changed via |
| --- | --- | --- | --- |
| **Laravel config** | `config/larapilot.php` (publishable) + `.env` | Environment toggles: routes, diagnostics, `LARAPILOT_API_TOKEN`, webhooks and tokens, Backstage and tracker credentials, package defaults | `php artisan vendor:publish --tag=larapilot-config`, env vars |
| **Project workflow** | `.larapilot/config.yaml` (committed) | Per-project `settings`, paths, statuses | `/larapilot-settings` or `larapilot:settings-set` |

The YAML wins for workflow settings; Laravel config only provides the defaults at install. Machine-specific paths (like an external frontend repo) live in `.env`, never in committed YAML.

A few switches live in `.env` alone, because they decide what the package registers before any YAML is read: `LARAPILOT_ENABLED=false` keeps the package to its Artisan commands (no routes, no views, no MCP server, no recorders); `LARAPILOT_DASHBOARD_ROUTE=false` and `LARAPILOT_MOCKUPS_ROUTE=false` leave the dashboard and the mockups unregistered; `LARAPILOT_FORCE_IN_PROD=true` serves the dashboard, the mockups, and the API in `production`, behind a password and an authenticator for every user; `LARAPILOT_API_AUDIT=false` stops the audit log of the API (`LARAPILOT_API_AUDIT_FILE` moves it). Every other variable is documented beside its key in `config/larapilot.php`, and the ones of the integrations in `.larapilot/integrations.md` after install.

---

## Dashboard & API (dev/staging)

**Larapilot · Dashboard** at `/larapilot`. Who gets in follows `APP_ENV` alone: open on a developer's machine (`local`, `testing`), a sign-in page on every other environment (`staging`, `qa`, `preview`, `development`, …), and **not served in `production`** unless `LARAPILOT_FORCE_IN_PROD=true` — then every user signs in with a password **and** an authenticator app. Each user has a role, and sees the pages of that role: [Dashboard sign-in & roles](#dashboard-sign-in--roles). The mockups at `/mockups` and their assets sit behind the same sign-in, for every role that sees **Design**.

The sidebar groups the pages as **Workflow** (Board, PRD, Inception, Plan, Versions, Roadmap, Design), **Workspace** (About, Settings, Skills, File manager, Database, Logs, Laravel, Git), **Insights** (Forecast, Usage, Economics, Security, SBOM, Errors), **Reference** (API, Docs), and **Access** (Users, API token, Anonymization — admins). Its foot shows the signed-in user, who opens **Account**, and **Sign out**; the version number links to [larapilot.web.ap.it](https://larapilot.web.ap.it).

| Page | URL | What you see |
| --- | --- | --- |
| Board | `/larapilot` | Kanban by status, with search and priority / epic / status filters; **Group by: Status · Version · Sprint** and the **version scope** — one version, several, or the stories with none ([versions](#versions)); counts and metrics follow the cards on screen. **Download status (.md)** saves the board as it stands, filters included; **Download epics (.md)** saves the same stories as an outline — the project, each epic with its story points, its user stories with theirs, and the tasks of each with their hours |
| PRD | `/larapilot/prd` | Rendered PRD with a **search** that looks in the PRD and nowhere else, decision journal timeline, **Download PRD (.md)**, and a **functional analysis summary** download (one Markdown file in the PRD language, requirements numbered by priority) |
| Inception | `/larapilot/inception` | Discovery choices snapshot |
| Versions | `/larapilot/versions` | Every version with its progress, its forecast against its milestone, and the open stories no version claims; one page per version with its stories, epics, and release notes — [Versions](#versions) |
| Roadmap | `/larapilot/roadmap` | The versions of the release ledger as **Shipped · Now · Next · Later**, with their stories in public words, and the public link when `public_roadmap` is on — [Roadmap](#roadmap) |
| Plan | `/larapilot/plan` | Epics — or **Group by: Version · Sprint**, and the version scope — milestones, schedule criticality, and the delivery forecast: a dependency-aware Gantt with open work queued from today, one spec at a time; a milestone that names a release is done once that release is shipped. Re-planned with `/larapilot-schedule`. **Download plan (.md)** saves the epics, every story with its status, priority, points, release, blockers, and forecast window, the tasks of each planned story, the milestones, and the delivery order |
| Design | `/larapilot/design` | Every screen first, as a card. A click opens that mockup as a site you browse, with **All screens** to come back; prev / next through every flow, style compare with **Use this style**, zip download |
| Settings | `/larapilot/settings` | Every project mode with its options explained |
| Skills | `/larapilot/skills` | Every skill the agents of the project can run, whoever brought it — the project, Larapilot, another package, Laravel Boost, a hand that dropped it into the folder of an agent — with which agent has it. Click one to **read it**. Under them, **what the agents are told**: `CLAUDE.md`, `AGENTS.md`, and the rules around them, one part for each author |
| File manager | `/larapilot/files` | **Admins only.** The five material folders — `brand/`, `client-materials/`, `design-systems/`, `legacy/`, `skills/` — each with what it is for. Browse the tree, preview, read a PDF in the page, download, upload files or a whole folder (structure kept), rename, delete. A sixth folder, **Project**, shows the application itself, read only. **Download zip** on every folder |
| Database | `/larapilot/database` | The tables and views of the database in `.env`, whatever the driver — MySQL, MariaDB, PostgreSQL, SQLite, SQL Server. Rows a page at a time with sort, search, and foreign keys that lead to the row they point at; the structure of each table; a **diagram** of the tables and the foreign keys between them; the **migrations** that ran and the ones still to run; **Download SQL** for a dump of the whole database, and **Other formats** for its structure alone, for Laravel **migrations**, and for **seeders** with its rows — the dump and the seeders with **Anonymize personal data**. Read only; passwords and tokens are never shown. Admins and developers; a developer in production sees the rows anonymized only |
| Logs | `/larapilot/logs` | The log files of the application read as entries, the newest first: by level, by period, searched, or with the repeats counted — one row for each thing logged. An exception shows where it was thrown, and the frames of your own code apart from the framework's. **Download log** for the file. Read only; passwords, tokens, and keys are shown as `[REDACTED]` |
| Laravel | `/larapilot/laravel` | How the framework is set up in the application and what it is doing, in five tabs. **Overview**: the drivers in use — database, cache, session, queue, mail, filesystem, broadcasting, logging — and what is cached: configuration, routes, events, compiled views, the application cache. **Schedule**: the scheduled tasks, the next one due first. **Queue**: the jobs that wait, are delayed, or are held by a worker, and the ones that failed. **Mail**: the mail the application sent, as it left. **Dumps**: what `dump()` and `dd()` printed, with the line that dumped it. Nothing of the application is changed |
| Git | `/larapilot/git` | 12-month contribution heatmap, every branch measured against the branch it is heading for, and the history drawn as a graph with each commit on the branch it was made on. Filterable by developer |
| Forecast | `/larapilot/forecast` | The story points of the backlog, the two-week sprints one person needs, and the same work with Larapilot, by epic — **Download report.md** — [Forecast](#forecast) |
| Usage | `/larapilot/usage` | Lucille's token and hour ledger + Markdown report, and **Estimate vs build**: every delivered spec with the hours it was estimated at, the time it spent `IN PROGRESS`, the wait in review, how many times it was sent back, and its tokens |
| Security | `/larapilot/security` · `/larapilot/security/checkpoint` | Two tabs. The first is the scanner the project associates — **Aikido** or **Hackly**: what it found in the repository (with Hackly, in the domains linked to it too), the most severe first, with what was decided about each finding and the verdict of the ship gate. **Register for the client (.md)** downloads every finding — open, resolved, ignored with its reason. When the git remote matches no repository of the scanner, the page asks which one the project is. Always in the menu; with no scanner associated it describes both and how to associate either. **Checkpoint**: the last scan of [`andreapollastri/checkpoint`](https://github.com/andreapollastri/checkpoint) — verdict, checks by area (dependencies, configuration, code), every finding with its suppression hash, the trend of the scans — with **Run the scan** and **Download report (.md)**; when the package is missing it says how to install it |
| SBOM | `/larapilot/sbom` | Every package the project ships — Composer, the JavaScript of the repository, and the frontend companion — from the lockfiles: version, direct or transitive, production or development, license (copyleft flagged), abandoned packages. **Check vulnerabilities** asks OSV.dev; the vulnerable packages come grouped with the version that fixes them and the command to run, and what was decided about each. Downloads: **SBOM (.md)**, **CycloneDX (.json)**, **Vulnerabilities (.md)** |
| Errors | `/larapilot/errors` | What the running application threw, as the tracker of the project recorded it: one row for each bug, how many times it was thrown, and what was decided about it — day by day when the tracker records every throw. Always in the menu; with `errors` off it says what Boogle is, how to connect it, and which other trackers can be read instead |
| Economics | `/larapilot/economics` | What the project costs, what the client pays, what is left for you — every sum written as a receipt (`account` ≠ NONE) |
| Spec | `/larapilot/specs/{code}` | Story, plan, tasks, mockups, decisions, internal feedback. The story keeps its lines as written, and its `**Status:**` follows the backlog. **Download spec (.md)** saves all of it, tasks included, in one file |
| API docs | `/larapilot/api/docs` | Swagger UI over the JSON API |
| Docs | `/larapilot/docs` | Delivery loop, packaged skills, persona roster |
| Account | `/larapilot/account` | Your password, and your authenticator turned on or off — every signed-in user |
| Users | `/larapilot/users` | **Admins.** Create users with a role, change roles and passwords, rename, reset a lost authenticator, remove |
| API token | `/larapilot/api-token` | **Admins.** Generate, rotate, or revoke the token of the JSON API — shown once |
| Anonymization | `/larapilot/anonymization` | **Admins.** What every column of the database becomes in an anonymized copy — [Anonymization](#anonymization) |
| About | `/larapilot/about` | What the project runs on: Laravel, PHP, the database server, Node — each with its upstream support window drawn as a bar (bug fixes, security fixes, today) and an alert when it is past or near its end — the project, PHP extensions and limits, connections, drivers, the packages that shape an upgrade (Filament, Nova, Livewire, Inertia, …), frontend, CI and deploy, and every file that pins a version. The host, port, and name of the database are shown to admins only |

The dashboard follows the system theme; pin **light** or **dark** from the sidebar. Every page works on a phone. A page your role does not see is not in your menu, and answers **403** with the reason.

| Plan | Spec | Database |
| --- | --- | --- |
| [![The Plan: release milestones and the Gantt by epic](https://larapilot.web.ap.it/img/dashboard/plan.webp)](https://larapilot.web.ap.it/img/dashboard/plan.webp) | [![A spec in review with its story, criteria, and mockup](https://larapilot.web.ap.it/img/dashboard/spec.webp)](https://larapilot.web.ap.it/img/dashboard/spec.webp) | [![The diagram of the tables and their foreign keys](https://larapilot.web.ap.it/img/dashboard/database-diagram.webp)](https://larapilot.web.ap.it/img/dashboard/database-diagram.webp) |
| **Logs** | **Laravel** | **Git** |
| [![An exception opened on the frames of the application](https://larapilot.web.ap.it/img/dashboard/logs.webp)](https://larapilot.web.ap.it/img/dashboard/logs.webp) | [![A booking confirmation as the application sent it](https://larapilot.web.ap.it/img/dashboard/laravel-mail.webp)](https://larapilot.web.ap.it/img/dashboard/laravel-mail.webp) | [![The history graph with Gitflow branches](https://larapilot.web.ap.it/img/dashboard/git.webp)](https://larapilot.web.ap.it/img/dashboard/git.webp) |

Taken on Fernway, the demo app of the documentation — more on the [tour](https://larapilot.web.ap.it/#tour).

### Dashboard sign-in & roles

There is no `dashboard_auth` setting and no HTTP Basic Auth. The dashboard has its own sign-in page, and what it asks follows `APP_ENV`:

| `APP_ENV` | What the dashboard does |
| --- | --- |
| `local`, `testing` | Open. No sign-in: whoever reaches it acts as an admin |
| any other but `production` — `staging`, `qa`, `preview`, `development`, … | A sign-in page: username and password. An authenticator app is optional — each user turns it on from **Account** |
| `production` | Not served (404), unless `LARAPILOT_FORCE_IN_PROD=true`. Then every user signs in with a password **and** an authenticator app, and enrols one at the first sign-in if they have none |

| Sign-in | Users | Account |
| --- | --- | --- |
| [![The sign-in page of the dashboard on a staging host: username and password, no sign-up](https://larapilot.web.ap.it/img/dashboard/login.webp)](https://larapilot.web.ap.it/img/dashboard/login.webp) | [![The Users page: one user per role, two with the authenticator on](https://larapilot.web.ap.it/img/dashboard/users.webp)](https://larapilot.web.ap.it/img/dashboard/users.webp) | [![Account: setting up the authenticator from a QR code drawn on the server](https://larapilot.web.ap.it/img/dashboard/account.webp)](https://larapilot.web.ap.it/img/dashboard/account.webp) |

**There is no sign-up and no password reset.** An admin creates the users and sets their passwords, on the **Users** page or from the shell. The first admin of a server is always made from the shell: until a user exists, a shared host answers **HTTP 503** with the command to run.

```bash
php artisan larapilot:dashboard-user add andrea --role=admin          # asks for the password (12+ characters)
php artisan larapilot:dashboard-user add giulia --role=pm --password='…'
php artisan larapilot:dashboard-user role giulia dev                  # admin · dev · pm · ext
php artisan larapilot:dashboard-user password giulia                  # a new password; her open sessions end
php artisan larapilot:dashboard-user rename giulia giulia.rossi
php artisan larapilot:dashboard-user totp-reset giulia                # a lost phone: she enrols again
php artisan larapilot:dashboard-user remove giulia
php artisan larapilot:dashboard-user list
```

`--role` defaults to `admin`. Users live in `.larapilot/auth.yaml` — password hashes (bcrypt or argon2id), roles, and the authenticator secrets encrypted with `APP_KEY` — git-ignored, with no database table and no `User` model. Each server has its own users.

| Role | Sees |
| --- | --- |
| `admin` | Everything: every page, **Users**, **API token**, **Anonymization**, the file manager, and where the database lives |
| `dev` | Everything but Users, API token, Anonymization, and the file manager. Never the host, port, or name of the database. In `production`, the Database page and every download from it are **anonymized**, always |
| `pm` | Workflow (Board, PRD, Inception, Plan, Versions, Roadmap, Design), Insights (Forecast, Usage, Economics, Security, SBOM, Errors), and Reference (API, Docs). Not the Workspace — but the Logs outside `production` |
| `ext` | Workflow only: Board, PRD, Inception, Plan, Versions, Roadmap, Design and its mockups — for a client or an outside stakeholder |

Every role has **Account**. The menu shows each role its pages; any other page answers **403** and says why.

- **Users** (admins) creates users with a role, changes roles and passwords, renames, resets a lost authenticator, and removes users. An admin never acts on themself there — another admin does — and can act on every other admin. **Account** is every user's own: their password, and their authenticator turned on or off.
- **A change takes effect at once.** A new role, a new password, or a removed user applies on the next request of every open session.
- **The authenticator** is any TOTP app (RFC 6238) — Google Authenticator, Microsoft Authenticator, 1Password, Authy. It is enrolled from a QR code drawn on the server; the secret is kept encrypted with `APP_KEY` and never leaves the server. A code is good once, thirty seconds either side of the clock. The app shows the account under `LARAPILOT_DASHBOARD_AUTH_ISSUER` (default `APP_NAME`).
- **Passwords** have at least twelve characters and are not the username. A username is letters, digits, and `. _ @ + -`, up to 64 characters.
- **Throttling.** Wrong passwords are limited per address — `LARAPILOT_DASHBOARD_AUTH_MAX_ATTEMPTS`, 20 a minute — and per name, 5 a minute from anywhere; wrong codes per name. Behind a load balancer or a CDN, have the application trust the proxy (`TrustProxies`), or every visitor shares one address.
- **Sessions.** The sign-in and sign-out are forms with CSRF; the session is regenerated at every sign-in.
- **Audit trail.** `.larapilot/auth-audit.log`, git-ignored, keeps one JSON line per sign-in, failure, second factor, user change, API token, anonymization map, and file-manager zip — never a password, a code, or a token. `LARAPILOT_DASHBOARD_AUTH_AUDIT=false` turns it off; `LARAPILOT_DASHBOARD_AUTH_AUDIT_FILE` moves it.
- **From 5.0.** An `auth.yaml` written before 5.1 is read as it is, every user an admin, and `larapilot:update` rewrites it with roles and says whom it changed.
- **In production** the package must be installed on the server: a deploy with `composer install --no-dev` leaves out a Larapilot required with `--dev`. Serve the dashboard over **HTTPS** on every shared host.

### File manager

`/larapilot/files` manages what you hand the skills before they start. It is for **admins only**, on every environment — on a developer's machine, where the dashboard is open, that is whoever uses it:

| Folder | What it is for |
| --- | --- |
| `brand/` | Logo, palette, typography, and the brand guide |
| `client-materials/` | Briefs, analyses, and documents supplied by the client |
| `design-systems/` | Visual references and tokens the mockups are built on |
| `legacy/` | Snapshots of the old system to port or migrate |
| `skills/` | Your custom skills, one folder per slash command |
| `./` — **Project** | The Laravel application itself: code, config, routes, tests. **Read only** |

- **Adding, renaming, and deleting** files and folders work in the five material folders. **Project is read only**: you browse, preview, and download; the routes that write do not know the folder and the service refuses a write to it.
- **Folders that start with a dot are left out of Project** — `.git`, `.larapilot`, `.github`, `.idea`, at any depth — and so are the caches of the framework, `bootstrap/cache/` and `storage/framework/`: a cached configuration holds every value of `.env`. Files that start with a dot are shown. `vendor/` and `node_modules/` can be opened and are left out of the count.
- **Credentials show their keys, never their values.** `auth.json`, `.npmrc`, `.yarnrc`, `.netrc`, `.pgpass` are shown with every value replaced by `*****************`, on screen and in the download. **`.env` and `.env.*` are shown as they are** outside `production`, and to an admin in `production`; masked for anyone else. A template such as `.env.example` is shown as it is. A key or certificate is one line of asterisks; a database (`.sqlite`, `.db`) is listed and neither shown nor downloaded. A `.log` file is shown and downloaded with the passwords, tokens, and keys it quotes replaced by `[REDACTED]`, as on the [Logs](#logs) page — which reads a log whole, by entries, where the file manager shows its first 256 KB.
- **A PDF is read in the page**: one page or two side by side, zoom, page jump, full screen. The reader is PDF.js from cdnjs, checked against its hash; where it cannot load, the file opens in its own tab.
- **Download zip** on every folder, the root of each included: what the folder shows, at any depth. Databases are left out, keys are their mask, logs are redacted, and the folders a listing hides stay out — dot folders and the caches of the framework in Project. `vendor/` and `node_modules/` are zipped only when you open them and zip them themselves, never with a parent. The archive stops at `LARAPILOT_FILE_MANAGER_MAX_ZIP_MB` (default 512) and says so. Every zip is written to the audit trail.
- **Upload a folder** and it keeps its structure; an existing file is kept unless **Replace existing files** is on. One file is limited by `LARAPILOT_FILE_MANAGER_MAX_UPLOAD_KB` (default 50 MB) and by PHP's `upload_max_filesize` / `post_max_size`, whichever is lower.
- **Behind the sign-in and the role.** Client documents, legacy snapshots, and the files of the project are an admin's: a developer, a product manager, or an external user does not see the page, and its addresses answer 403.
- Nothing outside the six folders can be reached, a symlink is never followed, and an uploaded `.html`, `.js`, or `.svg` is never run in the dashboard. `LARAPILOT_FILE_MANAGER=false` removes the page.

### Database

`/larapilot/database` shows the application's own database — the connection named by `DB_CONNECTION`, with the `DB_*` values in `.env` — through Laravel's schema and query builders, so the page is the same on MySQL, MariaDB, PostgreSQL, SQLite, and SQL Server. It reads, and never writes.

- **The list**: every table and view, with its size where the driver reports one, and the driver, database, and host on top — never the password; a developer sees the driver alone, and never the host, port, or name of the database, not even in the message of a failed connection. On MySQL/MariaDB only the database in `.env` is listed; on PostgreSQL every schema is, a table outside `public` named `schema.table`. A connection `prefix` is left out of the names.
- **Rows**, 50 to a page (`LARAPILOT_DATABASE_VIEWER_PER_PAGE`), by primary key. Click a column to sort; the search looks in the text columns, without regard to case; a foreign key value links to the row it points at. Click a row to open it whole — long text in full, JSON indented, **Copy as JSON**, and **Copy as SQL INSERT**: the row as one `INSERT` statement of the driver, its columns named, ready to run on a database with the same table. Binary is shown as hex with its size.
- **Structure**: columns (type, null, default, primary key, auto increment, comment), indexes, foreign keys with their on update / on delete, and the **create statement** — the `CREATE TABLE` (or `CREATE VIEW`) that builds it, with its indexes and keys, in the SQL of the driver, to copy.
- **Diagram**, the second view of the list (`/larapilot/database?view=diagram`): a box for each table with its columns — `PK`, `FK`, the type — and a line for each foreign key, from the column to the one it references, with an arrow on that end. A table stands to the right of the tables it points at; the ones no foreign key touches are in rows below. **Keys only** leaves the keys and counts the rest. Click a table to keep only its relations lit; its name opens it. `−`, `+`, **Fit**, Ctrl/⌘ with the wheel, and a drag move around it. Drawn on the server as plain SVG — no library, nothing fetched — for up to 300 tables and views; the structure alone, never a row. **Download PDF** saves the diagram as it is shown — all columns or keys only — on one page as large as the drawing, in vectors, with the database, the counts, and the day on top (`/larapilot/database-diagram.pdf`).
- **Migrations**, the third view (`/larapilot/database?view=migrations`): which migrations ran and which are still to run, as `php artisan migrate:status` tells it — the files of the application and of its packages against the `migrations` table. The pending ones come first, then the ones that ran with their batch, the newest first; a migration that ran from a file no longer there is marked **Ran · file gone**. Before the first `migrate` every file is pending. Nothing is run from the dashboard.
- **Credentials are never shown.** A column named like a password, token, or secret (`*password*`, `remember_token`, `token`, `*_token`, `*secret*`, `two_factor_recovery_codes`, `*api_key*`, `*private_key*`) is shown as `*****************` and is never searched, sorted, or filtered on. Add patterns in `database_viewer.masked_columns` of `config/larapilot.php`.
- **Download SQL** writes the whole database as one `.sql` file in the dialect of its driver — structure, every row, then indexes, keys, sequences, and views — to restore with `mysql`, `psql -f`, `sqlite3`, or `sqlcmd`. MySQL, MariaDB, and SQLite give their own `CREATE` statements; PostgreSQL is rebuilt from its catalogs like `pg_dump` (schemas, enum types, serial and identity columns with their next value); SQL Server from Laravel's schema builder. Each table and view is dropped first if it exists. It is written while it downloads, from one read-only snapshot.
- **The dump leaves credentials out**: the hidden columns are written as `NULL`, or `''` where `NULL` is not allowed — `hidden-1`, `hidden-2`, … where a unique index holds the column, so the file still restores — and listed at the top of the file. **Include passwords and tokens** puts them in — offered only in `local` and `testing`. The same holds for the seeders and for **Copy as SQL INSERT**, which never carries them.
- **Anonymize personal data**, beside **Download SQL** and **Laravel seeders**, writes every row through the [anonymization map](#anonymization): names, emails, phones, addresses, and free text replaced, keys and record dates kept, so the copy still imports. The migrations are the structure alone and have no row to replace. A **developer in `production`** always gets the anonymized rows — on screen and in every download — and cannot turn it off; search and filters are off while the rows are anonymized, so a match never tells a value. An admin sees the real rows and ticks the box for an anonymized download.
- **Other formats**, beside **Download SQL**:
  - **SQL, structure only** — the same file with no rows: the tables, their keys and indexes, and the views, and no sequence left at the number the rows had reached.
  - **Laravel migrations** — a `.zip` to unpack in the root of a project: a file in `database/migrations` for each table, written with the Blueprint method that makes each column (`id()`, `timestamps()`, `softDeletes()`, `rememberToken()` where a table carries them), its indexes, and its foreign keys. A table comes after the ones it points at; the keys that close a circle are in a last migration of their own, and the views in one that runs their SQL. What Blueprint has no word for — a type of one database only, an index on an expression — is written as the nearest thing, with a comment above it. The `migrations` table is left out.
  - **Laravel seeders** — a `.zip` with a class in `database/seeders` for each table that holds rows, and `DatabaseDataSeeder`, which calls them in the order of the migrations: `php artisan db:seed --class=DatabaseDataSeeder` on tables that are there and empty. Bytes are kept through base64, the next id is set on PostgreSQL, and the archive is written while it downloads, so a large table fills neither the memory nor the disk.
  - The migrations and the seeders are read from Laravel's schema builder, so they are the same from every driver, and what one database wrote runs on another — but for a view and a generated column, which are the SQL of the database they came from.
- The downloads are at `/larapilot/database-export/sql`, `/migrations`, and `/seeders` — no extension, since a web server may keep `.sql` and `.zip` for itself. `/larapilot/database.sql` still answers.
- **Admins and developers** see the page, wherever the dashboard is served. `LARAPILOT_DATABASE_VIEWER_CONNECTION` reads another connection; `LARAPILOT_DATABASE_VIEWER=false` removes the page.

### Anonymization

`/larapilot/anonymization` (admins) is the map of what the personal data of the database becomes in an anonymized copy: on the Database page for a developer in production, and in every SQL dump or seeder downloaded with **Anonymize personal data**. It lists every column of every table with what it becomes and an example beside it.

[![Anonymization: the users table of Fernway with each column, what it leaves as, and an example](https://larapilot.web.ap.it/img/dashboard/anonymization.webp)](https://larapilot.web.ap.it/img/dashboard/anonymization.webp)

| A column can be | What it writes |
| --- | --- |
| **Keep** | The value as it is |
| **Hide** | `NULL`, or empty where `NULL` is not allowed |
| **Same shape** | New characters in the same pattern — letters for letters, digits for digits, the separators kept |
| **A Faker formatter** | Any formatter of [fakerphp.org](https://fakerphp.org/formatters/), grouped as the site groups them: Person (name, first and last name, title, date of birth), Address, Phone, Company, Internet (email, username, URL, IP, user agent), Text, Date and time, Payment (IBAN, SWIFT/BIC, card number), Identifiers (UUID, EAN-13, ISBN-13), Miscellaneous |

- **The Faker locale** is chosen among every locale the installed Faker ships — 73 in Faker 1.24 — and writes names, addresses, and phones of that country. A locale adds formatters of its own — an Italian *codice fiscale*, a French *NIR*, a Spanish *DNI* — listed apart.
- **Safe defaults.** A column with no line of its own follows a default that errs on the side of privacy: a name, an email, a phone, an address, an identifier, free text are replaced; keys, record dates, statuses, enumerations, and flags are kept, so the copy still imports and works. The tables of the framework are known by name: `migrations` and cache locks kept, queue payloads, failed-job exceptions, cache values, and session payloads hidden, the address and browser of a session replaced.
- **Passwords, tokens, and secrets** stay hidden whatever the map says.
- **The same value always becomes the same replacement** on one install — seeded with an HMAC of the value keyed with `APP_KEY` — so a foreign key written as text still matches and relations survive. No hash of a real value is ever written.
- **A replacement fits its column**: its type and its length.
- **The map is `.larapilot/anonymization.yaml`**, committed, so every environment anonymizes the same way. Saving it, and changing the locale, go to the audit trail.

### Logs

`/larapilot/logs` reads what the application wrote to `storage/logs` — every `.log` file, the one Laravel writes to now opened first, or the newest when it writes to none of them. It reads, and never writes. A file has its address without the extension — `/larapilot/logs/laravel` for `laravel.log` — because a web server may refuse an address that ends in `.log`, or look for a file of its own there; the address with it leads to the one without.

- **Entries, not lines.** An entry is what Laravel wrote between one `[date] env.LEVEL:` and the next, its stack trace included; the newest comes first. Each one shows its level and message and, for an exception, the class, **where it was thrown** as a file of the project — a path of the server is read as the file it is in this checkout — the exception that caused it, and the context as indented JSON.
- **Your code apart from the framework's.** A stack opens on the frames of the application; the ones of the framework and the packages are one click away. A file that is in the checkout opens in the file manager.
- **By level, by period, by words.** A level returns itself and every one more severe (*Error and worse*), and the count of each level sits above the list. The search wants every word, in any case; quotes keep a phrase together, and a class name is found with its backslashes as the page shows them or as the log doubles them. The period is the last hour, day, week, or month.
- **Repeats counted** turns the list into one row for each thing logged — the same message with its numbers, ids, and quoted values taken out, or the same exception thrown from the same line — with how many times and since when, the most repeated first. A log where nothing repeats is counted up to 10,000 different things, and the page says so.
- **Any size.** A file is read from its end backwards — 32 MB for a request (`LARAPILOT_LOG_VIEWER_SCAN_MB`), 50 entries to a page (`LARAPILOT_LOG_VIEWER_PER_PAGE`) — so the newest entries of a log of gigabytes come at once; **Older** and **Keep reading older** go further back, and a page stays the same however much is written after it. A file that is not in Laravel's format, such as the output of a worker, is read a line at a time.
- **Secrets are never shown.** Passwords, tokens, keys, cookies, and `Authorization` headers are `[REDACTED]` on screen, and a search never finds one. In a context written as JSON the value under such a key is hidden whatever it is — a string, the list a header comes as, a number — and so is the JSON of a request body logged as text. A line the redaction cannot check is hidden whole. **Download log** gives the file redacted the same way, with a line longer than 1 MB cut at that size; **Secrets as written** gives it as it is, and is offered only in `local` and `testing`.
- **Admins and developers** see the page wherever the dashboard is served; **product managers** too, outside `production`. `LARAPILOT_LOG_VIEWER_PATH` reads another folder — absolute, or from the root of the project; `LARAPILOT_LOG_VIEWER=false` removes the page. Only the `.log` files of that folder are opened, three folders deep, and a symlink is never followed.
- **For the skills**, `php artisan larapilot:logs` reads the same files — see [Diagnostics and logs](#diagnostics-and-logs-bug-triage). `/larapilot-bug` and `/larapilot-error` run it every time.

### Laravel

`/larapilot/laravel` shows how the framework is set up in the application and what it is doing now. Five tabs; nothing of the application is changed from any of them — no cache is cleared, no job retried, no mail sent again.

- **Overview.** Five numbers that lead to the other tabs — scheduled tasks, jobs waiting, failed jobs, mail kept, dumps kept — then two panels. **Drivers**: for the database, the cache, the session, the queue, the mail, the filesystem, broadcasting, logging, and hashing (Scout and Octane when installed), the store, connection, or mailer in use, its driver, and what says where it points — host and port, bucket, table, prefix — never a password or a key. A driver that keeps nothing is said so: with `sync` a job runs inside the request, with `log` a mail reaches nobody. **Caches**: whether the configuration, the routes, and the events are cached, how many views are compiled, and the cache of the application — its store, whether it answers, and how much it holds where that can be counted (`file`, `database`) — each with the artisan command that builds it and the one that empties it. On a developer's machine a cached configuration carries a warning: a change to `.env` is not read until it is cleared.
- **Schedule.** The tasks of the scheduler as `php artisan schedule:list` prints them, read from `routes/console.php`, `withSchedule()`, or the console kernel: the command, the description, the cron expression, the next run, and its options — no overlap, one server, background, in maintenance, the environments it is limited to. A task that does not run in this environment is greyed. The page cannot tell whether the cron of the server calls `schedule:run`.
- **Queue.** On the default connection — or another one of `config/queue.php`, a click away — how many jobs wait, are delayed, or are held by a worker, queue by queue, and the first 50 jobs in the order a worker takes them: class, queue, state, attempts, since when. The jobs are listed for the `database` and `redis` drivers — on Redis the queues are the one of the connection, the ones Horizon is told to work, and any other that holds a job; SQS, Beanstalkd, and the others give the numbers only. **Failed jobs** lists the last 50 with the first line of the exception, secrets redacted, and the `queue:retry` command for each; open batches are counted.
- **Mail.** Every mail the application sends is kept and listed, the newest first: subject, recipients, the mailable or notification that built it, the request or command it was sent during. A mail opens on its headers, the names and sizes of its attachments — not the files — and the message: the HTML inside a frame that runs no script, where a link opens in a new tab, and the plain text. It listens to what Laravel says it sent, so it works with every mailer, `log` and `array` included, and changes nothing of the mail or of where it goes.
- **Dumps.** What `dump()` and `dd()` printed, the newest first: the value as text, the file and line that dumped it — the Blade template, for a dump in a view — and the request or command it happened in. A dump still shows where it always did; here it stays to be read, also when it came from a job, an API call, or a response nobody saw. While Laravel Herd is watching the dumps it takes them all, and the page says so.
- **Where they are kept.** The last 100 mails and the last 100 dumps (`LARAPILOT_LARAVEL_VIEWER_KEEP`) sit in `storage/larapilot/`, a folder that ignores itself in git (`LARAPILOT_LARAVEL_VIEWER_PATH` moves it). **Forget them all** empties a list. They are kept **on a developer's own machine only** — `local` and `development`, and not while the tests of the project run: a mail carries reset links and personal data, a dump whatever the code was holding. `LARAPILOT_LARAVEL_VIEWER_MAIL=true` or `LARAPILOT_LARAVEL_VIEWER_DUMPS=true` keeps them wherever the page is served; `false` never.
- **Admins and developers** see the page, wherever the dashboard is served. `LARAPILOT_LARAVEL_VIEWER=false` removes the page.

### Roadmap

`/larapilot/roadmap`, in the **Workflow** menu and seen by every role, is the product roadmap: the versions of the release ledger as **Shipped · Now · Next · Later** — shipped, in progress, the next planned release, the ones after it — each with its stories in public words and its target. It is built from `.larapilot/releases.yaml`, the ledger `release_mode` keeps: with no release in it, the roadmap is empty.

| Roadmap | The public link |
| --- | --- |
| [![The Roadmap in the dashboard: 0.1.0 shipped and 0.2.0 being built, each story in public words](https://larapilot.web.ap.it/img/dashboard/roadmap.webp)](https://larapilot.web.ap.it/img/dashboard/roadmap.webp) | [![The public roadmap link: the same lanes with no code, person, or hour](https://larapilot.web.ap.it/img/dashboard/roadmap-public.webp)](https://larapilot.web.ap.it/img/dashboard/roadmap-public.webp) |

- **The public link.** With `public_roadmap` on (`php artisan larapilot:settings-set --public-roadmap=YES`), the page shows a link to send outside: `/larapilot/roadmap/p/{token}`. It shows the same lanes and leaves out spec and epic codes, people, hours, points, costs and tokens, branches, tags and commits, comments and blockers, mockups, and the stories a release does not claim or that are hidden. The token is unguessable and **regenerated with one click** — the old link stops at once. It is a secret of each host, kept in `.larapilot/roadmap-link.yaml`, out of Git. The link answers **even in `production`**, where the dashboard is hidden, as long as the setting is on and the package is installed there; it is throttled to 60 requests a minute.
- **Public wording.** `.larapilot/roadmap.yaml`, committed, keeps what the public reads in place of the ledger: an intro, a title, a one-line summary, and a target for a version; a title and a summary for a story; what is hidden.

```bash
php artisan larapilot:roadmap-set --intro="What we are building for our members."
php artisan larapilot:roadmap-set --semver=0.2.0 --title="Waitlist" --summary="Join a full class and get the seat when one frees up." --target="Q1 2027"
php artisan larapilot:roadmap-set --semver=0.2.0 --item=US-014 --title="Book from the calendar"
php artisan larapilot:roadmap-set --semver=0.2.0 --item=US-019 --hide          # --show puts it back
php artisan larapilot:roadmap-show                                              # what the public sees
```

`--target` takes a quarter, a month, or `YYYY-MM-DD`, for a version only; an empty value restores the ledger's title or clears the field. `/larapilot-release` runs `roadmap-show` after a release changes, and words what reads as internal.

### Versions

With `release_mode` on, the version is a dimension of the whole product: of every page that reads the backlog, and of every step of the workflow.

- **The Versions page** — `/larapilot/versions`, in **Workflow**, for every role. Each version as a card, the open ones first: status, stories done out of all and the story points, a bar of what is done and in progress, the day its last open story is forecast to land on the Plan, its milestone with **Late** or **Overdue** when the forecast misses it, its release branch (admins and developers), and links that open the Board, the Plan, and the Forecast on that version. Above them: the version the work is on, the next milestone, and the open stories **no version claims**, listed. A version opens on its own page — `/larapilot/versions/1.2.0` — with its stories in the order they are picked up, its epics, what it moved out when it shipped, and its **release notes**, with **Release notes (.md)**.
- **The version scope** — on the Board, the Plan, the Forecast, and the Versions page, one chip for each version and one for **No version**: a click adds or removes it, so the page shows one version, several, or the stories with none. The scope **stays** while you move between those pages, until **All versions** clears it, and it travels in the address (`?version=1.2.0,1.3.0`, `?version=none`, `?version=all`). The Board's figures, its downloads, the Plan's Gantt and milestones, and the Forecast's figures and `report.md` follow it.
- **Forecast by version** — the Forecast has a **By version** table: story points, the hours left for one person and with Larapilot, the sprints left, the forecast day, and the milestone of each version. The rate a point takes with Larapilot is measured on the whole backlog, even when the page is scoped.
- **On a spec** — the spec page shows the version it targets, linked to the version.
- **Group by** on the Board — **Status · Version · Sprint** — and on the Plan — **Epic · Version · Sprint**. **Version** is offered when `release_mode` is on or the ledger holds releases; a version column links to its page and shows its progress. The version of a spec is the release that claims it, or its `**Release:**` line.
- **In the skills** — every skill names the version it works in (`v1.2.0 3/5 (60%)`). Inception and adopt leave no story without a version; feature, spec, and bug give each new story one — a fix of a shipped version goes in its next patch; `spec-next` picks the version the work is on first; implement stops on a story with no version and asks; review closes with how far the version is, and says when it delivered its last story; autopilot works one version at a time and stops at its end; ship refuses a version with open stories until they are delivered, moved (`release-ship --defer-to=1.3.0` or `--defer-to=backlog`), or shipped anyway (`--force`), and keeps the release notes before the ceremony. The rules are in `.larapilot/runtime-versions.md`.
- **From the shell** — `release-show [--semver=]` (one version: stories, open ones, forecast, risk), `release-notes [--semver=] [--file=] [--write]` (the notes, drafted from the stories — Added, Changed, Fixed, Not delivered yet, Moved — or kept in `.larapilot/docs/releases/vX.Y.Z.md`), `release-list` with each version's `progress`, `spec-list --release=1.2.0|none`, `spec-next --release=`, and `release-set --add-spec=` that moves a story out of any other open version (`--remove-spec=` drops one). `larapilot:context` gives every skill `release`: the version the work is on, each open version's progress, the stories with none.
- **API** — `GET /larapilot/api/versions` and `/versions/{version}`, and `?version=` on `/specs`.

### Sprints on the Board and the Plan

- **Sprints** (`php artisan larapilot:settings-set --sprints=YES`) split the work into two-week sprints of one person. A spec carries the sprint someone decided — `php artisan larapilot:spec-sprint US-012 3`, `none` to clear it — or, until someone does, the sprint it is **proposed** for: the open specs laid one after another in delivery order into what one person does in a sprint (the sprint hours over the hours a point stands for, as on [Forecast](#forecast)). Sprint 1 starts on the first day the ledger recorded work. A proposed sprint is marked **proposed**.
- The API returns the release and the sprint of each spec: `release`, `sprint`, and `sprint_proposed` on `/specs` and `/specs/{code}`.

### Forecast

`/larapilot/forecast`, in **Insights** before Usage (admins, developers, product managers), answers how long the backlog takes — by a person, and with Larapilot. Every number says where it comes from.

[![The Forecast of Fernway: 76 story points, three sprints for one person, and the same work with Larapilot](https://larapilot.web.ap.it/img/dashboard/forecast.webp)](https://larapilot.web.ap.it/img/dashboard/forecast.webp)

| Figure | How it is computed |
| --- | --- |
| **Story points** | The points of the backlog: done, under way (`IN PROGRESS` and `REVIEW`), to do. A spec with no points is counted apart |
| **Hours a point stands for** | The rate the plans imply — task hours over points — once at least two specs and five points are planned; until then the `effort` setting: 3 (`ECO`), 4 (`STANDARD`), 5.5 (`MAX`) |
| **A sprint** | Two weeks of five days of one person: 60 hours with the six-hour delivery day, or the day of the Economics profile when one is set |
| **By a person** | Points × hours per point; sprints = those hours ÷ the hours of a sprint, the last one started counted whole |
| **With Larapilot, measured** | Once delivered specs carry hours in Lucille's ledger: the hours they took ÷ their points, applied to the backlog. Called a first reading under three specs |
| **With Larapilot, modelled** | Before that, a range: the build work of a person ÷ 10 (optimistic) or ÷ 5 (realistic), plus 0.5 or 1 hour of a person per spec for reading the plan, reviewing, and approving |

The same figures come by epic, and **Download report.md** saves them with their assumptions.

### JSON API

| Endpoint | Returns |
| --- | --- |
| `GET /larapilot/api/board` | Metrics and specs grouped by status |
| `GET /larapilot/api/specs` · `/specs/{code}` | Specs with task progress, mockups, feedback, release, and sprint (paginated, `?status=`, `?version=1.2.0,none`) |
| `GET /larapilot/api/versions` · `/versions/{version}` | Every version with its progress, forecast, milestone, and risk; one version with its stories, epics, and release notes |
| `POST /larapilot/api/specs/{code}/comments` | Append internal feedback (`comments=YES`) |
| `GET /larapilot/api/prd` | PRD Markdown and heading index |
| `GET /larapilot/api/metrics` | Delivery snapshot + effort timing + `build`: the estimates of the delivered specs beside the time they took |
| `GET /larapilot/api/economics` | Economics snapshot; what-if parameters (`?hourly_rate=70&discount_pct=10&tier=premium`) are computed, never stored |
| `GET /larapilot/api/diagnostics` | Read-only runtime snapshot for bug triage |
| `GET /larapilot/api/backstage` · `/backstage/catalog-info.yaml` | Backstage entities and delivery snapshot |
| `GET /larapilot/api/openapi.json` · `/docs` | OpenAPI 3 document and Swagger UI |

**The API token.** An admin makes it on the **API token** page (Access group) or from the shell; it is shown **once**, and only its hash is kept in `.larapilot/auth.yaml`. Rotating it ends the old one at once. A token in `.env` (`LARAPILOT_API_TOKEN=…`) works too. Send it on every request as `Authorization: Bearer <token>` or `X-Larapilot-Token: <token>`.

```bash
php artisan larapilot:api-token generate     # show · generate · rotate · revoke
```

| Where | No token exists | A token exists |
| --- | --- | --- |
| `local` / `testing`, `api_auth=NO` (default) | Open | Every request needs it, reads included |
| `local` / `testing`, `api_auth=YES` | **HTTP 503**, with how to make one | Every request needs it |
| any other environment | **HTTP 503**, with how to make one | Every request needs it |
| `production` | Not served (404), unless `LARAPILOT_FORCE_IN_PROD=true`; then as above | |

`api_auth` matters on a developer's machine only: elsewhere the token is always required. The dashboard sign-in does not close the API: on `local` with no token, anyone who reaches the port reads it. Make a token, or turn `api_auth` on, whenever the machine is reachable from others — a Sail port on every interface included. With the API open, a write (`POST …/comments`) is accepted only as JSON and not from another site (`Content-Type: application/json`, no cross-site `Origin` or `Sec-Fetch-Site`): a web page in your browser cannot post a comment the agent would then read. A wrong or missing token is **HTTP 401**. A user signed in on the dashboard whose role sees the API (admin, dev, pm) reads it from the browser — the Swagger UI included — without the token; never a write, the group has no CSRF. Call the API from a server, never from browser code that would carry the token.

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
- **What the delivered work took** — under the hours, one line for you alone: the delivered specs as the quote counts them, and the time the agent took to build them (the time they were `IN PROGRESS`, pauses included). Your own hours on them are not in it. It is on the page and in the internal report, never in the client quote. Spec by spec: **Estimate vs build** on Usage.
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
| **Dashboard sign-in** — a sign-in page, roles, and an authenticator on `/larapilot` and the mockups | follows `APP_ENV`: open in `local`; a sign-in elsewhere; hidden in `production` | `larapilot:dashboard-user add andrea --role=admin` on each shared host; `LARAPILOT_FORCE_IN_PROD=true` to serve it in production, password + authenticator for all |
| **API token** — bearer token or `X-Larapilot-Token` on `/larapilot/api/*` | required wherever a token exists; outside `local` the API answers `503` until one does | the **API token** page, `larapilot:api-token generate`, or `LARAPILOT_API_TOKEN` |
| **API auth** — the token required on a developer's machine too | OFF | `--api-auth=YES` |
| **Security scan** — [`andreapollastri/checkpoint`](https://github.com/andreapollastri/checkpoint) in review and pre-ship; the last scan on **Security → Checkpoint** | OFF | `composer require --dev andreapollastri/checkpoint` then `--security-scan=YES` |
| **Vulnerable dependencies** — every package of the SBOM against [OSV.dev](https://osv.dev), at the ship gate and on the **SBOM** page | on demand | nothing: `php artisan larapilot:vendor-audit` or `/larapilot-vendor-check` |
| **Security scanner** — the findings of [Aikido](https://www.aikido.dev/) or [Hackly](https://hackly.web.ap.it/) for the repository, in triage and at the ship gate | OFF | credentials in `.env`, then `--security-provider=aikido` or `hackly` |
| **Production errors** — what the running application throws, read from [Boogle](https://boogle.web.ap.it/), Sentry, Bugsnag, Flare, Datadog, Rollbar, Honeybadger, or CloudWatch, in triage and on the dashboard | OFF | a credential that reads the tracker in `.env`, then `--errors=YES --errors-provider=…` |

- **The dashboard and the API have separate doors.** The sign-in never opens the API to a client without the token — only the browser of a signed-in user whose role sees the API, for reads — and the token never opens the dashboard. Neither touches MCP. Details: [Dashboard sign-in & roles](#dashboard-sign-in--roles), [JSON API](#json-api).
- **Create the first admin before a shared host sees the dashboard.** With no user, every page answers **HTTP 503** and gives the command; nothing of the board, the PRD, the specs, the git history, the security findings, or the production errors is served. The mockups at `/mockups` and their assets sit behind the same sign-in and are served with `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, and a `frame-ancestors 'self'` policy.
- **The pages that read the machine follow the role.** The file manager and **Anonymization** are an admin's; **Database** and **Laravel** an admin's and a developer's; **Logs** also a product manager's outside `production`. A developer never sees where the database lives, and in `production` sees its rows anonymized only. Mail and dumps are recorded only in `local` and `development` unless `LARAPILOT_LARAVEL_VIEWER_MAIL` / `_DUMPS` say otherwise.
- **Secrets as they are** — **Include passwords and tokens** on a database download, **Secrets as written** on a log — are offered only in `local` and `testing`, whatever the role.
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

### Security scanners — Aikido or Hackly

`security` and `security_provider`, OFF by default. The project associates **one** scanner; it scans on its side, and Larapilot runs none and installs nothing: it **reads what the scanner found**, brings it into the workflow, and **tells the scanner what you decided** about each finding.

| Scanner | What it scans | Ids |
| --- | --- | --- |
| **Aikido** — SaaS, [aikido.dev](https://www.aikido.dev/) | The repository, through the git provider: dependencies, code, secrets, infrastructure as code, containers | Numbers (`24`) |
| **Hackly** — self-hosted, [hackly.web.ap.it](https://hackly.web.ap.it/) | A clone of the repository (Semgrep, Trivy, Gitleaks, Composer audit, Checkpoint, Laravel checks) **and the domains linked to it** (TLS, headers, exposed paths, Nuclei, ZAP) | UUIDs |

```bash
php artisan larapilot:settings-set --security-provider=aikido   # or hackly; none turns the findings off
php artisan larapilot:security-status
```

`--aikido=YES` is the name the setting had before 5.1 and still means `--security-provider=aikido`; a `config.yaml` with `aikido: true` reads as Aikido. `/larapilot-security` asks which scanner when none is set. The same commands work for both:

| Command (`php artisan larapilot:`) | What it does |
| --- | --- |
| `security-status` | Scanner, credentials, repository, last scan, `hints` |
| `security-issues` (`--new`, `--severity=`, `--type=`, `--limit=`, `--report`, `--gate`, `--detail={id}`) | The open findings with their state — `new`, `in_backlog`, `waived`, `false_positive` — and the verdict of the gate; `--report` writes `docs/security/security-report.md`; `--gate` exits 1 on `FAIL`; `--detail` gives one finding as a brief and a fix prompt |
| `security-plan --ids=…` | Groups the confirmed ids by kind and fix before triage — a leaked secret never merges |
| `security-link {ids}` (`--spec=US-XXX`, `--waive --reason="…"`, `--false-positive --reason="…"`, `--forget`, `--local`) | What was decided, kept in `.larapilot/aikido.yaml` or `hackly.yaml` (committed: ids and decisions, never a credential) and told to the scanner; `--local` tells it nothing |
| `security-push` | Tells the scanner the decisions it was not told (`unsent`) |
| `security-repos` (`--search=`, `--use={id}`, `--forget`) | Which repository of the scanner this project is, when the git remote finds none |
| `security-scan` | Asks for a new scan — Hackly: `--profile=quick\|standard\|deep`, `--include-targets` |
| `security-register` | `docs/security/security-register.md` for the client: every finding open, resolved, or ignored with its reason, in the language of the PRD, naming its scanner and its scale |

The `aikido-*` commands are the older names of the same Aikido steps and still answer.

- **`/larapilot-security`** downloads the open findings, lets you **confirm each one** (resolve, waive, or skip), runs **`security-plan`** on the ids you chose to fix, and hands **each resolution group** to **`/larapilot-triage`** — same kind and same fix together, secrets never merged. Triage measures it against the PRD like any request — a known vulnerability in shipped code is a bug, a requirement gap when no requirement names security — and `/larapilot-bug` writes the fix spec. The link between finding and spec is recorded.
- **The repository is asked for when it is not found.** Larapilot finds it from the git remote, by address or by name. When the code is scanned under another repository — a fork, a mirror, a different name — it does not guess: `/larapilot-security` asks which one it is, `/larapilot/security` shows the list with a form, and `security-repos --use=…` keeps the choice in the committed ledger for every machine. `LARAPILOT_AIKIDO_REPOSITORY` or `LARAPILOT_HACKLY_REPOSITORY` in `.env` names it for one machine.
- **The ship gate** stops on an open finding at the `FAIL_ON` severity or above that was not waived. A finding in the backlog is not fixed: it counts until the scanner no longer reports it, after the fix is merged and scanned. The gate is computed on this project's findings.
- **A waiver needs a reason**, in a sentence, and only the user gives it. When the scanner refuses a decision or cannot be reached, the decision is kept as `unsent` and `security-push` tells it later. `--local` keeps one decision in the project; `PUSH_DECISIONS=false` all of them.
- **The register for the client.** `security-register`, or **Register for the client (.md)** on `/larapilot/security`, gives one Markdown document with every finding: open with the fix that is planned, resolved with the date, ignored with the date and the reason, and a count by severity — what a client or an auditor asks for.
- **What is kept**: the ledger holds the decisions — ids, the spec, the reason, whether the scanner was told — and the repository chosen, and is meant to be committed. The credentials stay in `.env`.

#### Aikido

```dotenv
LARAPILOT_AIKIDO_CLIENT_ID=
LARAPILOT_AIKIDO_CLIENT_SECRET=
LARAPILOT_AIKIDO_REGION=eu        # eu · us · au · me
LARAPILOT_AIKIDO_REPOSITORY=      # id or name in Aikido, for this machine; empty = chosen or found from the git remote
LARAPILOT_AIKIDO_FAIL_ON=high     # critical · high · medium · low · none
LARAPILOT_AIKIDO_PUSH_DECISIONS=true  # false = decisions stay in the project
```

- **Create the credentials** in Aikido under *Settings → Integrations → Public REST API*, with the `issues:read` and `repositories:read` scopes, `issues:write` to tell Aikido your decisions, and `repositories:write` to ask for a scan. The repository has to be connected in Aikido, through the git provider.
- **Decisions are told to Aikido.** A waiver **ignores the finding in Aikido**, with the reason as its comment; a spec leaves a note on the finding; `--forget` takes a waiver back. A finding that is in several repositories of the workspace is ignored in this one only.
- A finding ignored by hand in Aikido is listed in the register too; its reason stays in Aikido, which does not give it back. The access token lives in the cache and is never written to a file of the project.

#### Hackly

[Hackly](https://hackly.web.ap.it/) is **self-hosted**: Larapilot reads **your** instance. `hackly.web.ap.it` is its documentation site, not an instance — `security-status` says so when `LARAPILOT_HACKLY_URL` points at it.

1. In your Hackly, add the repository (Repositories → New, with a GitHub token that reads it). Link its domains to it for their findings too — each verified by DNS in Hackly.
2. Create a personal token: Profile → API tokens, or on the Hackly server `php artisan hackly:token you@example.com --ability=write`. `read` lists; `write` lets Larapilot tell Hackly a decision and ask for a scan.
3. Put the address of the instance and the token in `.env`, then `--security-provider=hackly` and `security-status`:

```dotenv
LARAPILOT_HACKLY_URL=https://hackly.example.com   # your instance; /api is added
LARAPILOT_HACKLY_TOKEN=hk_…
LARAPILOT_HACKLY_REPOSITORY=                       # uuid or owner/name, for this machine; empty = chosen or found from the git remote
LARAPILOT_HACKLY_INCLUDE_TARGETS=true              # read the findings of the linked domains too
LARAPILOT_HACKLY_FAIL_ON=high                      # high · medium · low · none
LARAPILOT_HACKLY_SCAN_PROFILE=standard             # quick · standard · deep
LARAPILOT_HACKLY_PUSH_DECISIONS=true               # false = Hackly is told nothing
```

| Decision | In Hackly |
| --- | --- |
| `security-link {id} --spec=US-012` | **Acknowledged**; the spec is kept in `.larapilot/hackly.yaml` |
| `security-link {id} --waive --reason="…"` | **Acknowledged** — Hackly has no "accepted risk"; the reason is kept here and in the register |
| `security-link {id} --false-positive --reason="…"` | **False positive**, with the reason as its note (8–2000 characters) |
| `security-link {id} --forget` | **Open** again |

- **What is read**: the findings of the repository — and of its linked domains, unless `LARAPILOT_HACKLY_INCLUDE_TARGETS=false` — in the states `open` and `ack`. Severities are Hackly's three: `high`, `medium`, `low`; reachable code comes first within a severity.
- **The gate is this project's.** It is computed here, on the findings of this repository and its domains; Hackly's own CI gate looks at the whole workspace.
- **The fix prompt.** `security-issues --detail={uuid}` quotes Hackly's own brief and fix prompt, and that prompt is what triage hands to the fix.
- **Scans.** `security-scan` asks for a repository scan with the profile of `LARAPILOT_HACKLY_SCAN_PROFILE`, or `--profile=quick|standard|deep`; `--include-targets` deep-scans the verified linked domains too. Target scans and penetration tests are started in Hackly.
- The register lists fixed findings as resolved and false positives as ignored, with the note written in Hackly when none was written here.

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

### Diagnostics and logs (bug triage)

**The logs, read for an agent.** `php artisan larapilot:logs` reads the log files of the application as entries, with **secrets redacted**: the message, the exception and where it was thrown, and the frames of the application — not the sixty of the framework under them. `/larapilot-bug` runs it **every time**, whatever the report says, and `/larapilot-error` for every group it hands to triage.

| Option | What it does |
| --- | --- |
| `--group` | One row for each thing logged, with how many times and since when — the most repeated first |
| `--level=warning` | That level and every one more severe |
| `--search="…"` | Every word must be in the entry; quotes keep a phrase together. A redacted value is never found |
| `--since=7d` | Minutes, hours, or days back (`30m`, `1h`, `7d`), or a date |
| `--limit=` | 20 by default, 100 at most |
| `--files` · `--file=` | The log files there are · another one than the file the application writes to now. A file that is not in Laravel's format has no levels and no dates: `--level` and `--since` are left out, and the answer says so |

It answers with what the file holds — the count of each level and the period — before the entries, and with an empty list when nothing was logged. Also through the MCP `RunArtisanTool`; on the dashboard the same reader is the [Logs](#logs) page.

**The health of the runtime.** A read-only snapshot — app info, health checks (`storage_writable`, `cache`, `database`, `queue`, `log_file`), and a log tail with **secrets redacted** — that never mutates workflow state. `LARAPILOT_DIAGNOSTICS_ENABLED=false` turns it off, and `larapilot:logs` with it.

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
| Setup & health | `install` · `update` · `doctor` (`--human`) · `context {skill}` (`--session=`, `--fresh`, `--with=`) · `config-show` (`--only=settings,paths,frontend,tracker,dev_docs,backstage,workflow,personas`) · `settings-set` · `quality` (`--fix`, `--baseline`) |
| Access | `dashboard-user {list\|add\|remove\|role\|password\|rename\|totp-reset}` (`--role=admin\|dev\|pm\|ext`, `--password=`) · `api-token {show\|generate\|rotate\|revoke}` |
| Discovery | `prd-write` · `validate-prd` · `prd-show` (`--ids=`, `--section=`) · `prd-impact` (`--ids=`) · `choices-set` · `frontend-set` (`--project=`, `--mode=`) · `frontend-scan` (`--project=`, `--full`, `--no-cli`, `--fresh`) · `frontend-rules` (`--file=`) · `frontend-brief` |
| Backlog | `spec-list` (`--status=`, `--release=`, `--full`) · `spec-add` · `spec-show` (`--task=`, `--fields=`) · `spec-next` (`--release=`) · `spec-delete` · `validate-spec` · `spec-comment` · `spec-sprint {code} {n\|none}` |
| Design | `mockup-choose-style US-XXX --style=` |
| Plan & build | `validate-plan` · `spec-plan` · `spec-start` · `task-done` · `spec-review` |
| Review | `spec-approve` (`--force`) · `spec-request-changes` (`--include-feedback`) |
| Traceability | `decision-log` · `decision-check` · `code-log` · `code-history` |
| Metrics & usage | `metrics` · `usage-log` · `usage-report` (`--insights`, `--format=json\|md\|human`) · `schedule-set` (`--release=` for a milestone of one release) |
| Schedule | `schedule-show` (`--only=queue,epics,deadlines,releases,alerts,findings`) · `schedule-apply --file=` (`--dry-run`) |
| Economics | `economics-set` · `economics-show` (`--format=json\|md\|quote`) · `economics-market-write` · `economics-quote-write` |
| Releases | `release-list` · `release-show` · `release-notes` (`--file=`, `--write`) · `release-add` · `release-set` (`--add-spec=`, `--remove-spec=`) · `release-cut` · `release-feature` · `release-sync` · `release-ship` (`--push`, `--defer-to=`, `--force`) · `release-import` |
| Roadmap | `roadmap-set` (`--intro=`, `--semver=`, `--item=`, `--title=`, `--summary=`, `--target=`, `--hide`, `--show`) · `roadmap-show` |
| Custom skills | `custom-skill-list` · `custom-skill-add` (`--name=`, `--file=` / `--content=` / stdin, `--force`) |
| Hooks | `hook-list` (`--event=`) · `hook-run {event}` (`--phase=before\|after`, `--spec=`, `--task=`, `--release=`, `--dry-run`) · `--skill-hooks-done=` on every command that fires an event |
| Stack & upgrades | `stack` (`--only=`, `--no-db`) · `upgrade-check` (`--laravel=`, `--php=`, `--php-from=`, `--db=`, `--db-from=`, `--offline`, `--report`, `--gate`) |
| Dependencies | `sbom` (`--full`, `--write=md\|cyclonedx\|both`) · `vendor-audit` (`--cached`, `--new`, `--limit=`, `--report`, `--fail-on=`, `--gate`) · `vendor-link` (`--spec=`, `--waive --reason=`, `--clear`) · `checkpoint-scan` (`--only=`, `--skip=`, `--cached`, `--report`, `--gate`, `--fail-on-warn`) |
| Security | `security-status` · `security-issues` (`--new`, `--severity=`, `--type=`, `--limit=`, `--detail=`, `--report`, `--gate`) · `security-plan` (`--ids=`) · `security-link` (`--spec=`, `--waive --reason=`, `--false-positive --reason=`, `--forget`, `--local`) · `security-push` · `security-repos` (`--search=`, `--use=`, `--forget`) · `security-register` (`--fresh`) · `security-scan` (`--profile=`, `--include-targets`) — Aikido's old names `aikido-status` · `aikido-issues` · `aikido-plan` · `aikido-link` · `aikido-push` · `aikido-repos` · `aikido-register` · `aikido-scan` |
| Errors | `errors-status` · `errors-list` (`--new`, `--kind=error\|outage`, `--limit=`, `--report`) · `errors-plan` (`--codes=`) · `errors-link` (`--spec=`, `--ignore --reason=`, `--forget`) · `errors-resolve` (`--status=FIXED\|DONE`, `--comment=`) — old names `boogle-status` · `boogle-errors` · `boogle-plan` · `boogle-link` · `boogle-resolve` |
| Integrations | `github-status` · `gitlab-status` · `bitbucket-status` · `azure-status` · `notify` · `tracker-status` · `tracker-push` · `tracker-pull` · `backstage-export` |
| Runtime | `logs` (`--group`, `--level=`, `--search=`, `--since=`, `--limit=`, `--files`, `--file=`) · `diagnostics` (`--lines=`, `--no-logs`) |

All commands are prefixed `larapilot:`. Release commands need `release_mode=YES` and take `--semver=` (Artisan reserves `--version`); nothing is pushed without `--push`.

The **`larapilot` MCP server** exposes four tools: `BacklogListTool`, `SpecShowTool`, `DiagnosticsTool`, and `RunArtisanTool`, which runs only read and validate commands (`config-show`, `spec-list`, `spec-show`, `spec-next`, `release-list`, `release-show`, `release-notes`, `metrics`, `usage-report`, `decision-check`, `code-history`, `prd-show`, `prd-impact`, the forge probes, the three validators, `doctor`, `diagnostics`, `logs`, `quality`, `frontend-scan`, `frontend-rules`, `backstage-export`, `tracker-status`, `hook-list`, `context`, `schedule-show`, `stack`, `upgrade-check`, `sbom`, `vendor-audit`, `security-status` / `security-issues` / `security-plan` / `security-repos` (for Aikido and Hackly), their Aikido names `aikido-status` / `aikido-issues` / `aikido-plan` / `aikido-repos`, `errors-status` / `errors-list` / `errors-plan`, and their old names `boogle-status` / `boogle-errors` / `boogle-plan`). The parameters are checked too: each command takes through MCP only the ones that read, and an option that writes a file is refused — `quality --fix` / `--baseline`, `backstage-export --write` / `--force` / `--catalog=` / `--mkdocs=` / `--file=`, `usage-report --output=`, `aikido-issues --report`, `security-issues --report`, `aikido-repos --use=` / `--forget`, `security-repos --use=` / `--forget`, `errors-list --report`, `upgrade-check --report`, `sbom --write=`, `vendor-audit --report`, `release-notes --write` / `--file=`. Run directly with Artisan, the commands take every option as before. All four tools are annotated as read-only (`readOnlyHint`). The commands that decide, write, or scan — `security-link`, `security-push`, `security-scan`, `security-register` — are not allowed through MCP.

---

## Requirements

- PHP **^8.1** (8.2+ recommended)
- Laravel **^10.49** · **^11.45.3** · **^12** · **^13**
- [Laravel Boost](https://laravel.com/ai/boost) **^1** or **^2** — Boost 2 needs PHP 8.2 and Laravel 11.45.3+ (12.41.1+ on 12); Composer resolves Boost 1 below that ([what changes](#laravel-10-php-81-and-boost-1)). Kept current by `larapilot:update`
- `laravel/mcp` **^0.5.1 … ^1.0**, the line the installed Boost brings
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
