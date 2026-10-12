# Fernway — the demo behind the dashboard screenshots

Every picture in `docs/img/dashboard/` (and `docs/img/og-card.png`) is taken on **Fernway**, a fictional
class-booking app for independent gyms, built here from nothing and replayed through the **real**
`php vendor/bin/testbench larapilot:*` commands of this repository. Nothing on a picture is drawn by hand:
the backlog, the plans, the releases, the git history, the logs, the mail, the queue, and the dumps are what
the package and the app wrote. Rebuild it whenever the dashboard changes, and the docs and the README keep
pictures that agree with each other and with their captions.

This folder is left out of the Composer archive (`.gitattributes`) and out of Netlify (it is not in `docs/`).

## The story

- **The product** — a PRD (`story/PRD.md`), 16 stories in 5 epics (`story/backlog.py`), 76 story points,
  55 planned tasks. Releases **0.1.0** *internal demo* (shipped), **0.2.0** *beta for 10 studios* (in progress,
  due 16 October), **0.3.0** *public launch* (6 November).
- **The board** — 3 TODO (10 SP), 2 PLANNED (6 SP), 2 IN PROGRESS (8 SP), 1 REVIEW with a blocking comment
  (US-006, 5 SP), 8 DONE (47 SP), each DONE with the merge commit of its pull request. US-005 was sent back once.
- **The people** — Maya Chen, Luca Moretti, Sam Okafor (developers), Priya Raman (product owner), all
  `@fernway.example`. On the staging dashboard: `maya` admin, `luca` and `sam` dev, `priya` pm, `erin` ext
  (a beta studio owner). Passwords are `fernway-demo-<name>`: demo values, not secrets.
- **Five weeks of Gitflow** — `main`, `develop`, `release/0.1.0` (shipped, tag `v0.1.0`), `release/0.2.0`,
  a feature branch per story, pull requests merged by Priya (`Merge pull request #48 from fernway/…`).
  `feature/US-007-…` is pushed, `feature/US-008-…` (checked out) is on Sam's machine only. The remote reads
  `git@github.com:fernway/fernway.git`; pushes go to a bare repository beside the app.
- **The app** — `fernway/`: Laravel 13 code staged task by task (models, actions, jobs, mailables,
  controllers, scheduled tasks, 11 migrations), `composer.lock` resolved from Packagist (139 packages; the
  Larapilot entry says 5.1.0, the release these pictures document).
- **A weekend of traffic** — `bin/traffic.py` books, cancels, checks in, joins waitlists, and hits a full class
  through the app's own routes, then runs queue workers: the log, the 25 mails, the 5 dumps, the jobs waiting
  and delayed, and the 2 failed Stripe jobs are the app's. Members are Faker `en_GB` names at `@members.example`.
- **Settings** — Gitflow, release mode, sprints, public roadmap, comments, code history, Lucille, decision
  journal, `COMPANY` account (UK limited company, £49 a month), Hackly as the security scanner.

### Dates

The story is written for the week of **Monday 12 October 2026**: inception on Monday 7 September, the last
commits on Friday 9 October, the traffic over the weekend. The Plan, the Roadmap, and the Forecast read
"today" from PHP's own clock (`new DateTimeImmutable('today')`), which nothing can freeze here (Herd's PHP is a
hardened binary: no libfaketime; no timecop). So `bin/history.py` **moves every date by whole weeks** to the
week you build in, and the pictures look the same. Captions that name a date (*on track for 16 October*) then
need the same shift — or build with `DEMO_KEEP_DATES=1` during the week of 12 October 2026 (or again any week,
accepting that "today" on the Plan is the real one).

Everything Carbon reads uses the story's clock: `DEMO_NOW` for a command, `X-Demo-Now` on a request of the
traffic. What PHP stamps with its own clock is put back by the kit: the kept mail and dumps (`bin/retime.py`),
the `updated_at` of `choices.yaml`, the mtime of the log and of the workspace files (`bin/finish.sh`).

## Build and shoot

Needs: `composer install` in the repository, PHP 8.4+, Python 3 (standard library only — no PyYAML: payloads are
JSON, which is valid YAML), Node 24 (global `WebSocket`), Google Chrome, git, rsync, network once for the OSV.dev
check of the SBOM (skipped offline). About three minutes.

```bash
tools/docs-demo/bin/build.sh "$TMPDIR/fernway-demo"            # never inside the repository
tools/docs-demo/bin/serve-all.sh "$TMPDIR/fernway-demo"        # 8841 local · 8842 staging · 8843 Hackly stub
node tools/docs-demo/shots.mjs tools/docs-demo/shots.json /tmp/shots --login=maya:fernway-demo-maya
node tools/docs-demo/shots.mjs tools/docs-demo/og-card.json /tmp/shots --format=png   # after copying board.webp
tools/docs-demo/bin/stop.sh "$TMPDIR/fernway-demo"
```

Look at every picture before copying it to `docs/img/dashboard/` (same names). `shots.mjs` prints, per job,
the HTTP status of the page, sideways scroll, console errors, and failed requests. Pick other ports with
`serve-all.sh <dir> <local> <staging> <hackly>` and `shots.mjs --local=… --staging=…` if those are taken
(`lsof -nP -iTCP -sTCP:LISTEN`); the Hackly port must match the one `finish.sh` wrote in `.env`.

| Step | What it does |
| --- | --- |
| `bin/setup-app.sh <dir>` | The app from the testbench skeleton in `<dir>/fernway`, the working path in `<dir>/work` (`testbench.yaml`, `prepend.php`, a `vendor` symlink), `.env` with SQLite |
| `bin/tb <dir> <command>` | `php vendor/bin/testbench …` on that app, with the prepend file |
| `bin/history.py <dir>` | Five weeks of work: settings, PRD, choices, decisions, specs, releases, milestones, sprints, economics, plans, task commits, reviews, merges, the ship of 0.1.0, comments, usage ledger, roadmap wording |
| `bin/agents.sh <dir>` | `boost:install` (Cursor here) and the package's skills in `.claude/skills` and `.cursor/skills` — 30 and 35 |
| `bin/seed.sh <dir>` | Migrations in seven batches, the rows (`php/SeedCommand.php`), the traffic, the retime, the anonymization map |
| `bin/finish.sh <dir>` | Dashboard users and the authenticator of two of them, the API token, the OSV.dev check, Hackly's address, file times |
| `shots.mjs` + `shots.json` | Headless Chrome over CDP: 1440×900 at 1.5 (2160×1350 WebP), 1440×1000 for Plan and Spec, 390×844 at 2 for phones, dark by emulated media. Chrome asks for `fernway.test` / `staging.fernway.test` and the kit answers from the local servers with that Host, so the links Laravel draws read `fernway.test`. Staging jobs sign in once through the form |
| `php/hackly-stub.php` | A Hackly instance for the Security page: the API shapes of `HacklyClient` and `tests/Feature/HacklyTest.php`, Fernway's findings, no CVE ids |
| `og-card.html` | The social card around `board.webp` |

`php/` holds the kit-only provider (`FernwayDemo\…`): the story clock for log lines, the demo sign-in of the
traffic (`X-Demo-Member`), a Stripe that answers 401 — none of it is in the app's git history or on its pages.

## Traps met on the way

- Keep `bootstrap/.testbench-default-skeleton` in the copied skeleton (without it testbench recurses until
  out of memory), delete its `migrations/` and `routes/.gitignore`, and empty `storage/framework/views` (a copied
  compiled view is newer than the package's Blade and is served stale). Never write into
  `vendor/orchestra/testbench-core/laravel`: the test suite wipes it between tests.
- `.larapilot/` is committed on the release lines, never in feature commits, and is the one source of truth:
  every branch switch saves and restores it, or git swaps in the snapshot of the branch. Save it again after
  `release-ship`, which checks out `main` and `develop` itself.
- `release-add --status=in_progress` cuts the branch at once: add as planned, then `release-cut --no-checkout`.
  `release-feature --no-checkout`, then checkout yourself (a dirty tree blocks its checkout).
- After `spec-request-changes` the spec is TODO: re-plan (`spec-plan`) before `spec-start`.
- `schedule-set` adds a milestone; to mark one done use `schedule-apply` with its `id`.
- Invokable controllers are checked when the routes load: `routes/web.php` grows feature by feature
  (`fernway/.versions/routes/web.php/*`), so every branch can boot.
- The traffic needs classes still on unmerged branches (the waitlist of US-006): `seed.sh` copies the full
  source over the working tree for the run and puts the branch back. The waitlist migration therefore shows
  **Ran · file gone** on the Migrations tab — what a developer switching branches really sees.
- Boost runs artisan in subprocesses without the prepend: run it before the seed, or its "class not found"
  lands in the log the Logs page shows.
- `php -S` writes output unbuffered (a `dump()` breaks headers: the prepend opens a buffer); OPcache in
  `php -S` takes a few seconds to reread an edited file.
- A session cookie copied from curl into Chrome does not authenticate; signing in through the form does.
- With an API token made, the API answers 401 on a developer's machine too — the Swagger page included —
  unless someone is signed in: the API docs picture is taken on staging, signed in.
- The 404 Chrome reports on the first page is `/favicon.ico`.
