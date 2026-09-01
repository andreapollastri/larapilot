# prj-ai — shared VPS for Laravel + Claude Code + Larapilot

Scripts for an Ubuntu 24.04 / 26.04 LTS VPS that hosts N Laravel projects
(PHP 8.3/8.4/8.5, MySQL, Redis, Nginx, Supervisor, cron) where N developers
work over SSH with Claude Code and **their own Claude plan**.

The git provider is chosen in `prj-ai config` and can be changed later without
re-provisioning: **GitHub, GitLab, Bitbucket Cloud or Azure DevOps**. All three
CLIs (`gh`, `glab`, `az`) are installed; Bitbucket uses its REST API.

> Generate this file with `php artisan larapilot:vps-provision` (writes
> `provision.sh` into the current directory), then copy it to the server.

## Contents

**One file to copy to the server: `provision.sh`.** It embeds and generates, at
provisioning time, every other component:

| Generated component | Destination | Role |
| --- | --- | --- |
| `prj-ai` | `/usr/local/sbin/prj-ai` | admin CLI (root) + `workspace-init` |
| `prj-work` | `/usr/local/bin/prj-work` | developer project menu |
| `prj-pr` | `/usr/local/bin/prj-pr` | open a PR/MR on **any** provider from the workspace |
| `prj-token` | `/usr/local/bin/prj-token` | developer saves **their own** git token (commits/PRs attributed to them) |
| `zz-prj-ai.sh` | `/etc/profile.d/` | hooks the menu into SSH login |
| `99-prj-ai.ini` | `/etc/php/*/fpm/conf.d/` | shared OPcache / realpath tuning |
| `prj-deploy@` | `/etc/systemd/system/` | optional per-project auto-deploy timer + oneshot service |
| `prj-preview-reap` | `/etc/systemd/system/` | daily timer that tears down stale developer previews |

Deploys are **atomic and zero-downtime**: each deploy builds a new
`releases/<timestamp>-<sha>/` directory and only flips the live `current`
symlink after every build step (composer, assets, `artisan optimize`,
migrations) succeeds. A failed build never reaches production;
`prj-ai rollback <project>` flips back to the previous release instantly.

Re-running `provision.sh` is safe (idempotent) and is also how you upgrade the
CLIs to a newer version of the script.

## Quickstart

```bash
# on your machine
php artisan larapilot:vps-provision            # writes ./provision.sh
scp provision.sh root@<vps>:

# on the VPS, as root
bash provision.sh
prj-ai config        # provider, base domain, service token, preview Basic Auth + TTL, defaults
prj-ai user-add      # one user per dev (paste the PUBLIC SSH key the dev sends you)
prj-ai add           # per project: name = subdomain label, PHP, repo, branch, previews y/n

# then each developer, on first SSH login:
prj-token            # save their own git token (must cover the projects they work on)
```

DNS: point a wildcard `*.<base-domain>` A record at the VPS — it covers both
`<project>.<domain>` and the per-developer `<user>-<project>.<domain>` previews.

### Transferring to the server — do NOT paste the script into a terminal

The script must be **transferred as a file and executed** (`bash provision.sh`),
never copy/pasted into a terminal: web terminals (AWS/Azure console, EC2
Instance Connect, serial) drop characters on large pastes and the shell runs
line by line — the result is a cascade of errors. Valid methods:

```bash
# A) scp with the instance key (e.g. EC2 Ubuntu)
scp -i key.pem provision.sh ubuntu@<ip>:
ssh -i key.pem ubuntu@<ip>
sudo bash provision.sh

# B) curl from a private repo/gist where you uploaded the file
curl -fsSL -o provision.sh "<raw-file-url>"
sudo bash provision.sh
```

Verify integrity after transfer, BEFORE running:

```bash
bash -n provision.sh && wc -l provision.sh
# no syntax error and the line count must match the original
# (also compare: shasum -a 256 provision.sh on both sides)
```

Available commands: `config`, `list`, `add`, `del <p>`, `php <p> [ver]`,
`user-add`, `user-del <u>`, `deploy <p>`, `rollback <p>`,
`preview {list|up <user> <project>|down <user> <project> [--drop-db]|reap}`.

## Architecture

```
/srv/prj/<project>/
  repo/                     SOURCE checkout — fetch/merge target, clone source for
                            developer workspaces; NEVER served
  releases/<ts>-<sha>/      one built, immutable release per deploy
  current -> releases/…     atomically-swapped symlink — the live release
  shared/.env               staging env, symlinked into every release (640)
  shared/storage/           uploads / logs / cache, symlinked into every release
  logs/                     php / queue / schedule / deploy logs
/home/<dev>/work/<project>  the developer's personal workspace
/etc/prj-ai/                config, project registry, service git token (root only)
```

- **Nginx** serves `current/public` and passes `$realpath_root` to PHP-FPM, so
  OPcache keys on a fresh path every deploy — new code is live the instant the
  symlink flips, with **no FPM reload and no dropped request**.
- The site is updated only by `prj-ai deploy` (or automatically every 60s by the
  `prj-deploy@<name>.timer`, if enabled in `add`). Each deploy is
  build-then-flip: a broken build is discarded and `current` never moves. The PM
  watches `https://<name>.<domain>/larapilot` and sees state after every merge.
- `prj-ai rollback <project>` flips `current` back to the previous release
  (kept: `RELEASES_KEEP`, default 3) and restarts the queue worker. It does
  **not** reverse migrations — see *Zero-downtime deploys* below.
- Each project has: a dedicated PHP-FPM pool (own user), an Nginx vhost,
  `queue:work` under Supervisor (runs `current/artisan`), `schedule:run` in
  `/etc/cron.d/` (from `current/`), its own MySQL database and user, and a
  Let's Encrypt certificate (if enabled).
- `prj-ai php <project> 8.5` moves the FPM pool, cron and Supervisor to the new
  version without touching Nginx (the socket path is unchanged).

## Zero-downtime deploys

`prj-ai deploy` (manual, timer, or from CI) does, in order:

1. `git fetch` + `merge --ff-only` into `repo/` (not served — safe).
2. `git archive HEAD | tar -x` into a fresh `releases/<ts>-<sha>/`.
3. Symlink `shared/.env` and `shared/storage` into the release; carry
   `vendor/` + `node_modules/` + `public/build/` forward from the current
   release so a code-only deploy is a ~3–5 s build.
4. `composer install` (only if `composer.lock` changed, else `dump-autoload`),
   `npm ci && npm run build` (only if assets changed), `artisan optimize`.
5. `artisan migrate --force` (only if `database/migrations/` changed).
6. **Atomic flip:** `ln -s` the new release to `current.new`, then
   `mv -T current.new current` — a `rename(2)`, so no request ever sees a
   missing target.
7. Restart the `queue:work` worker so it runs the new code too. Prune old
   releases to `RELEASES_KEEP`.

**Any failure in steps 2–5 aborts the deploy:** the half-built release is
deleted, `current` keeps serving the previous release, the systemd unit is
marked failed (`systemctl status prj-deploy@<project>`), and a line lands in
`/srv/prj/<project>/logs/deploy.log`.

**Migrations are the one non-atomic part.** They run against the shared
database just before the flip, so for a moment the new schema is live with the
old code still in some FPM workers. This is safe for *additive* migrations
(new table/column). For destructive ones:

- Use the **expand → contract** pattern: deploy the additive change and the
  code that tolerates both shapes first; drop the old column in a later deploy.
- Or answer **yes** to *"Maintenance mode during DB migrations?"* at
  `prj-ai add` (stored as `MIGRATE_MAINTENANCE`). The deploy then runs
  `artisan down` before migrating and `artisan up` after the flip — a short,
  branded `503` instead of possible `500`s.

`rollback` never touches the database: if the release you roll back to predates
a schema change, undo that migration by hand.

## Per-developer preview environments

Alongside the shared project URL `<project>.<domain>` (the deploy branch), each
developer who has **opened a project on the server** gets their own live URL:

```
<user>-<project>.<domain>   ->  /home/<user>/work/<project>/public
```

serving **that developer's current working tree** (checked-out branch +
uncommitted changes) with **their own MySQL database**, seeded once from the
project's staging DB. Maria on two projects gets two previews, each with its own
DB. `marco-vodafone.<domain>` does not exist because Marco never opened Vodafone.

### How it works

- **Enabled per project** — `prj-ai add` asks *"Per-developer preview URLs?"*
  (default yes). Toggle it later by editing `PREVIEWS=` in
  `/etc/prj-ai/projects/<project>.env`.
- **Created automatically** the first time the developer picks the project in
  `prj-work` (`workspace-init` calls `preview_up`): per-dev DB
  `prv_<project>_<user>` (dumped from the staging DB), the developer's workspace
  `.env` pointed at it (`APP_URL`, `DB_*`, `CACHE_PREFIX`, `REDIS_PREFIX`,
  `SESSION_COOKIE`), an `npm run build`, an **on-demand** FPM pool running as the
  developer's uid (`pm = ondemand` → zero memory when idle), an Nginx vhost, and
  a per-host Let's Encrypt cert.
- **Behind Basic Auth** — a single shared credential in
  `/etc/prj-ai/preview.htpasswd` (set in `prj-ai config`). Mandatory, because a
  preview serves uncommitted code that usually has `APP_DEBUG=true`.
- **DNS** — one wildcard `*.<domain>` A record covers both `<project>.<domain>`
  and `<user>-<project>.<domain>`. No per-project or per-developer DNS.
- **Home permission** — serving from `/home/<user>` requires it to be `0711`
  (traverse, not list); `~/.ssh` (700), `~/.git-credentials` (600) and
  `~/.claude` (700) keep their own restrictive modes. If that trade-off is not
  acceptable, bind-mount `~/work/<project>` under a world-traversable path
  instead.
- **Reaper** — `prj-preview-reap.timer` runs daily: a preview idle longer than
  `PREVIEW_TTL_DAYS` (default 14, set in `prj-ai config`) is torn down (DB kept
  — the developer may return); a preview whose workspace or user is gone is torn
  down with its DB.

### Managing them

```bash
sudo prj-ai preview list                        # every preview + last-seen
sudo prj-ai preview up <user> <project>          # (re)provision one by hand
sudo prj-ai preview down <user> <project>        # stop serving, keep the DB
sudo prj-ai preview down <user> <project> --drop-db
sudo prj-ai preview reap                         # run the idle sweep now
```

`prj-ai del <project>` and `prj-ai user-del <user>` remove all matching previews
(and their DBs); `prj-ai php <project> <ver>` moves the preview pools too.

### Assets

`preview_up` builds assets once. As the developer works they either run
`npm run build` in their tmux, or `npm run dev` (Vite dev server) and point their
browser at the dev-server port for HMR — the preview URL always serves the last
`public/build/`.

## The four guarantees

### 1. Automatic workspace at login

At SSH login, `/etc/profile.d/zz-prj-ai.sh` launches the `prj-work` menu (only
for interactive sessions from group `prjdev`; scp/rsync/VS Code skip it, and
`PRJ_NO_MENU=1` disables it). When the dev picks a project:

1. `prj-work` calls `sudo prj-ai workspace-init <project>` — the **only**
   command group `prjdev` may run as root (sudoers NOPASSWD).
2. If `~/work/<project>` is missing it is **cloned from the local canonical**
   (instant, no network, no token): remote `canonical` = local repo, remote
   `origin` = the real git URL (no credentials). It copies `.env` and runs
   `composer install`. If it exists it is simply reused.
3. You enter `tmux new-session -A -s <project>`: attach if the session exists,
   create it otherwise.

These are **per-dev clones**, not `git worktree` in the strict sense — a real
worktree would share the `.git` directory between users and break permission
isolation. The flow is: work in the workspace → push → merge → the canonical
pulls → dashboard updated.

### 2. Claude works ONLY inside its project

Three layers, strongest first:

- **Unix permissions (hard guarantee):** home at `700` → a user cannot touch
  another's files; the canonicals in `/srv/prj` are writable only by their
  system users (devs have group-read, needed for the clone). Whatever Claude
  does, it runs with the dev's uid.
- **Claude Code guardrails (regenerated on every login):** `workspace-init`
  writes a `.claude/settings.local.json` in each workspace with `deny` rules
  blocking Read/Edit/Write on `/srv/**`, `/etc/prj-ai/**`, `~/.ssh`,
  `~/.git-credentials`, `~/.claude` and on **every other workspace**
  (`~/work/<other-project>/**`). The list updates itself as projects are
  added/removed.
- **Default confinement:** Claude Code starts with cwd in the workspace and
  asks for approval before writing outside the working directory.

Honest limit: Bash commands Claude runs are not path-confinable, so between two
workspaces *of the same user* the barrier is the guardrail + human approval,
not the kernel. Between different users and toward the canonicals the barrier
is the filesystem. For kernel isolation between same-dev projects too, the next
step is a container / bubblewrap per workspace — can be added later.

### 3. One user cannot see another's credentials

- Home `700` (or `711` when previews are on — traverse only): `~/.claude` (plan
  session), `~/.git-credentials` (personal token), `~/.ssh`, `~/.azure` keep
  their own `600`/`700` modes and stay readable only by the owner.
- The "service" token used for the canonicals lives in
  `/etc/prj-ai/git-credentials` (600, root) and never appears in remote URLs
  or in the workspaces.
- Each dev saves their **own** personal token with `prj-token` and commits with
  the git identity set in `user-add`. **The token is mandatory and
  repo-scoped:** opening a project runs `git ls-remote` with the developer's
  token and refuses to create the workspace (or preview) unless the token can
  see that project's repository. A developer literally cannot start work on a
  repo they have no access to.

### 4. Disconnect ≠ lost task

- All work happens **inside tmux**: if SSH drops, the session (and the running
  Claude task) keeps going on the server.
- `provision.sh` sets `KillUserProcesses=no` in logind, so logout does not kill
  user processes.
- On reconnect the menu marks the project with `<< active session: resume
  where you were`; selecting it re-attaches exactly (`tmux new -A`).
  `claude --resume` inside the workspace reopens previous conversations if
  needed.

## Git providers

Choose (and change) with `sudo prj-ai config` → `GIT_PROVIDER`
(`github` / `gitlab` / `bitbucket` / `azure`; `devops` is an alias for
`azure`). The service token must come from a dedicated service/bot account, it
expires, and is rotated by re-running `prj-ai config`.

| Provider | Service token | Personal PR/MR path |
| --- | --- | --- |
| **GitHub** | PAT — classic scope `repo`, or fine-grained Contents R/W | `prj-pr`, or `gh pr create` after `gh auth login`. Larapilot's `github` toggle opens PRs via `gh`. |
| **GitLab** | PAT — scopes `read_repository`, `write_repository` | `prj-pr`, or `glab mr create` after `glab auth login`. Larapilot's `gitlab` toggle opens MRs via `glab`. |
| **Bitbucket Cloud** | App Password — Repositories R/W + Pull requests R/W | `prj-pr` (REST API — no CLI). Larapilot's `bitbucket` toggle opens PRs via REST when `LARAPILOT_BITBUCKET_*` is set. |
| **Azure DevOps** | PAT — scope Code (Read & Write) | `prj-pr` (`az repos pr create`), or `az devops login`. Larapilot's `azure` toggle opens PRs via REST/`az` when `LARAPILOT_AZURE_DEVOPS_PAT` is set. |

### Personal token — `prj-token` (mandatory)

Every developer runs `prj-token` once (and again when the token expires or they
gain access to a new repo). It writes `https://<user>:<token>@<host>` to
`~/.git-credentials` (mode 600) — never seen by other users or the deploy
service account. The admin can also paste it during `prj-ai user-add`, but
`prj-token` keeps the secret off the admin's screen.

**Enforced at project activation:** `prj-work` → `workspace-init` runs
`GIT_TERMINAL_PROMPT=0 git ls-remote --heads <repo>` as the developer. If their
token cannot read the repo, no workspace and no preview are created and the menu
shows *"your git token cannot access …"*. Use a token (classic PAT, or
fine-grained/App Password) that covers **every** project the developer works on.

`prj-pr` is the **provider-agnostic** command and the one `workspace-init`
teaches Claude (via a managed `CLAUDE.local.md`). From a workspace:

```bash
prj-pr "Title" [--target <branch>] [--draft] [--desc "text"]
```

It reads org/repo from the `origin` remote, pushes the current branch, opens
the request against the project's deploy branch, and uses the personal token
from `~/.git-credentials` — so the commit, the push and the PR are all
attributed to that developer.

> Repo URLs: pasting `https://ORG@dev.azure.com/...` is fine — `prj-ai add`
> strips the embedded username. Self-managed GitLab: `prj-pr` recognises hosts
> whose name contains `gitlab`; otherwise use `glab` directly.

**Automatic deploy (all providers):** the timer polls every 60s and builds an
atomic release only when `origin/<branch>` moved, running
`composer install` / `npm run build` / `migrate` only when the relevant paths
changed. Alternatively, call `prj-ai deploy <p>` over SSH from a pipeline
(Azure Pipelines / GitHub Actions / GitLab CI) after merge for push-based
deploys. Either way a failed build leaves the current release serving.

## Efficiency notes

- **Release build:** `composer install --no-dev --prefer-dist --no-progress
  --optimize-autoloader` (or a bare `dump-autoload` when `composer.lock` did not
  change), then `artisan optimize` inside the new release directory. A failed
  `optimize` leaves the caches cleared, never half-cached — and the release is
  discarded rather than flipped.
- **Incremental deploy:** `prj-ai deploy` diffs the incoming commits and skips
  Composer, npm and migrations when nothing relevant changed. `vendor/`,
  `node_modules/` and `public/build/` are copied forward from the live release,
  so a code-only deploy is a ~3–5 s build — the 60s auto-deploy timer is nearly
  free on a quiet branch.
- **Front-end:** `npm ci && npm run build` runs on the first release and on
  deploy only when `package.json` / lockfile / `vite.config.*` /
  `resources/{js,css,…}` changed, and only if a `build` script exists.
  `node_modules/` is dropped from every release but the live one.
- **OPcache/realpath:** nginx passes `$realpath_root`, so each release is a
  distinct path and OPcache picks it up with **no FPM reload**.
  `validate_timestamps=1` stays on only so in-place edits in a developer
  workspace are still seen.
- **Disk:** `RELEASES_KEEP` (default 3) releases are retained for fast
  rollback; older ones are pruned after each successful deploy.
- **PHP-FPM pools:** `pm.max_requests=500` recycles workers; `memory_limit` and
  idle timeout are pinned per pool.
- **Nginx:** long-lived cache headers + `access_log off` for static assets;
  `favicon.ico` / `robots.txt` never hit PHP.
- **systemd auto-deploy:** `Nice=10`, `IOSchedulingClass=idle`,
  `RandomizedDelaySec` so many projects don't stampede at the same second.

## Developer onboarding (share with the team)

```
0. on YOUR machine: ssh-keygen -t ed25519
   send the admin ONLY the file ~/.ssh/id_ed25519.pub
   (the private key stays with you and is never shared)
1. ssh <user>@<vps>
2. prj-token                        # REQUIRED first — paste your git token
                                    # (must cover every project you work on)
3. pick the project from the menu   # workspace + preview created on first open;
                                    # refused if your token can't see the repo
4. claude                           # first run: log in with YOUR OWN plan
5. work normally (/larapilot-* skills, git, artisan…)
6. open a PR/MR:  prj-pr "Title"
7. see your preview at  https://<user>-<project>.<domain>  (shared Basic Auth)
8. if you drop: reconnect and reselect the project -> you're back where you were
   (inside tmux: Ctrl+B then D to detach on purpose)
```

VS Code users: Remote-SSH to the VPS, open `~/work/<project>`, use the Claude
Code extension (runs on the server with your personal login). The menu does not
interfere with VS Code sessions.

## Working as a team without conflicts

Every developer has their **own clone** (`~/work/<project>`), their own tmux
session, their own Claude plan and their own git identity/token. Nothing on the
filesystem collides. Conflicts, when they happen, are one of four kinds — and
each has a simple rule.

### 1. Source code — one story, one branch, short-lived

Follow the Larapilot loop and let it drive the branching:

- **Claim the story first.** Run `php artisan larapilot:spec-list` /
  `larapilot:spec-next`, pick a `US-XXX` that is still `TODO`/`PLANNED`, and
  `larapilot:spec-start US-XXX` — that moves it to `IN PROGRESS` so no one else
  picks it up. Never `spec-start` a story someone else already started.
- **One branch per story.** With `git_mode=GITFLOW` (default) each story is a
  `feature/US-XXX-*` branch off the deploy branch. Don't share a branch between
  two people.
- **Rebase before you push.** `git fetch canonical && git rebase
  canonical/<deploy-branch>` (the local mirror updates on every `prj-work`
  login and every deploy). Resolve conflicts in your workspace, never on the
  server's `repo/`.
- **Keep PRs small and merge often.** `prj-pr "US-XXX: …"` per story. The
  longer a branch lives, the more it drifts. Review + merge daily.
- **Protect the deploy branch** on the provider (require PR + green CI, linear
  history). The auto-deploy always builds whatever is on `origin/<deploy-branch>`
  at poll time, so a messy branch = a messy release.

### 2. Database

- **With previews ON (default):** each developer's workspace `.env` points at
  their **own** DB `prv_<project>_<user>`, seeded once from staging. Your
  migrations, seeders and `tinker` only touch your copy — full isolation. Re-seed
  from staging by dropping it (`prj-ai preview down <you> <project> --drop-db`)
  and reopening the project.
- **With previews OFF:** all workspaces share the **canonical's** DB — **no
  isolation**. Never run `migrate:fresh` / `migrate:rollback` / `db:wipe` or a
  destructive `tinker` there; point your `.env` at your own `DB_DATABASE` if you
  need to experiment.
- **Coordinate migrations** either way: one migration per story; rebase so files
  land in linear order; don't write a migration that assumes another unmerged
  one ran first. Additive migrations on auto-deploy branches; destructive
  changes go through **expand → contract** (see *Zero-downtime deploys*).

### 3. Config and environment

- Your workspace `.env` is a **personal copy** — it is never deployed. Real,
  shared configuration goes into `config/*.php` and `.env.example` **in the
  PR**, not into your local `.env`.
- The live `.env` is `/srv/prj/<project>/shared/.env`, edited only by the admin.
  Ask for a key to be added there; don't expect a workspace `.env` change to
  reach production.

### 4. The running site (deploys)

- Deploys are **serialised and atomic**: the timer runs one `prj-ai deploy` at a
  time, and each one either fully succeeds (flip) or fully aborts (no flip). Two
  PRs merged seconds apart just mean the next poll deploys both — nothing is
  lost, nothing half-applies.
- If a deploy ships a bad release, **anyone with SSH** can
  `sudo prj-ai rollback <project>` to the previous release immediately, then fix
  forward on a branch.
- Watch `tail -f /srv/prj/<project>/logs/deploy.log` for the `OK` / `FAILED`
  line per deploy.

### tmux note

`prj-work` names the session after the project, **per user**. Two developers on
the same project each get their own `<project>` session — no collision. But if
*you* open the same project from two SSH connections you re-attach the *same*
session and share one screen; use a second session name
(`tmux new -s <project>-2 -c ~/work/<project>`) if you want two independent
panes.

### Resolving a conflict when it does happen

Governing rule: resolve **source code** with git as usual; for `.larapilot/`
artifacts **do not hand-edit the conflict markers** — take the other side and
**re-run the Larapilot command** that produced your change. The commands own the
shape of those files; git is only transport. The developer whose PR is behind
does the work, in **their own** workspace.

```bash
# A) source code — real merge conflict
git fetch canonical
git rebase canonical/<deploy-branch>        # resolve the markers (Claude can help)
php artisan larapilot:quality --fix && php artisan test
git push --force-with-lease                 # PR updates, merges clean

# B) a .larapilot/ file conflicts (backlog.yaml, a plan, decisions.yaml)
git checkout --theirs .larapilot/backlog.yaml      # accept the already-merged state
php artisan larapilot:spec-start US-015            # re-apply YOUR change on top
#   ^ or task-done / spec-review / decision-log — whatever you were running
git add .larapilot/ && git rebase --continue
```

| Situation | How to resolve |
| --- | --- |
| Same source lines | Recipe A. Deep logic clash → `/larapilot-review` catches it (`spec-request-changes`), rework rebases. |
| `backlog.yaml` / plan conflict (two stories advanced in parallel) | Recipe B — `--theirs`, then re-run `spec-start` / `task-done` / `spec-review` for **your** story. Larapilot rewrites the file canonically; the YAML is never left broken. |
| Same `US-XXX` code (both ran `spec-add` offline) | One dev deletes `.larapilot/specs/US-013.yaml`, re-runs `larapilot:spec-add` (next free code), renames the branch. Prevention: run `/larapilot-spec` from one branch, merge first, then pick stories. |
| `decisions.yaml` / append-only file | `git checkout --theirs`, then `larapilot:decision-log …` to re-append — order carries no meaning. |
| Migrations from two branches meet at merge | One migration per story, rebased into linear timestamp order; destructive changes via expand → contract. Previews already isolate the data before the merge. |
| Two PRs merged seconds apart, one bad release | A failed build leaves `current` on the last good release. If a bad one slipped through: `sudo prj-ai rollback <project>`, then fix forward. |

What keeps it rare: one story = one branch = one dev (`spec-start` locks it to
`IN PROGRESS`); small PRs merged daily; a protected deploy branch; a human
approving the final merged state through `/larapilot-review`.

## Warnings

- Written for Ubuntu 24.04/26.04 LTS with PHP from the `ondrej/php` PPA (other
  versions: `PRJ_FORCE=1 bash provision.sh`, untested): **try it on a
  throwaway VPS first**.
- `prj-ai del` / `user-del` also remove the personal workspaces **and every
  matching preview + preview DB**: unpushed work is lost (both ask for explicit
  confirmation; the project DB is dropped only on `yes`).
- Previews require a wildcard `*.<domain>` DNS record and set `/home/<user>` to
  `0711`. They serve uncommitted, usually `APP_DEBUG=true` code behind one shared
  Basic Auth credential — put the box behind a VPN if the code is sensitive.
- Larapilot dashboard exposed on staging: set `LARAPILOT_API_TOKEN` in the
  `.env` and consider `dashboard_auth` / a VPN in front of the vhosts.
- After loading everyone's SSH keys: `PasswordAuthentication no` in
  `/etc/ssh/sshd_config`.
