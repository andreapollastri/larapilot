# Larapilot — Optional Integrations

All integrations below are **opt-in** and **OFF by default**. Toggle them with `/larapilot-settings` or:

```bash
php artisan larapilot:settings-set --github=YES
php artisan larapilot:settings-set --gitlab=YES
php artisan larapilot:settings-set --bitbucket=YES
php artisan larapilot:settings-set --azure=YES
php artisan larapilot:settings-set --notifications=YES --notify-slack=YES --notify-discord=NO --notify-telegram=NO
```

**Never put secrets in `.larapilot/config.yaml`.** Use `.env` (or the host secret store).

Remote forge toggles (`github` / `gitlab` / `bitbucket` / `azure`) are **orthogonal** to `settings.git_mode`. Enable the forge that matches your `origin` host. You can leave unused forges OFF.

---

## GitHub (`settings.github`)

Uses the [GitHub CLI](https://cli.github.com/) (`gh`).

### Setup

1. Install `gh` from https://cli.github.com/
2. Authenticate: `gh auth login` (or export `GH_TOKEN`)
3. Ensure `origin` points at a `github.com` repository
4. Enable: `php artisan larapilot:settings-set --github=YES`
5. Probe: `php artisan larapilot:github-status`

### When ON

- Skills may call `gh pr create` / `gh pr view` / update after push (still respecting `git_mode`)
- Always surface the PR URL in chat
- Emit `pr_opened` / `pr_updated` via `larapilot:notify` when notifications are enabled

---

## GitLab (`settings.gitlab`)

Uses the [GitLab CLI](https://gitlab.com/gitlab-org/cli) (`glab`). Works with gitlab.com and self-hosted GitLab when `glab` is configured for that host.

### Setup

1. Install `glab`: https://gitlab.com/gitlab-org/cli
2. Authenticate: `glab auth login` (or export `GITLAB_TOKEN` / `GLAB_TOKEN`)
3. Ensure `origin` points at a GitLab repository
4. Enable: `php artisan larapilot:settings-set --gitlab=YES`
5. Probe: `php artisan larapilot:gitlab-status`

### When ON

- Skills may call `glab mr create` / `glab mr view` / update after push (still respecting `git_mode`)
- Always surface the **merge request** URL in chat
- Emit `pr_opened` / `pr_updated` via `larapilot:notify` when notifications are enabled (same event names; body may say MR)

---

## Bitbucket Cloud (`settings.bitbucket`)

Uses the [Bitbucket Cloud REST API](https://developer.atlassian.com/cloud/bitbucket/rest/) with an access token or app password (no required first-party CLI).

### Setup

1. Create a [Bitbucket app password](https://support.atlassian.com/bitbucket-cloud/docs/app-passwords/) (scopes: `repository`, `pullrequest` write) **or** a repository/workspace access token
2. Add to `.env` (either form):

```
# Preferred
BITBUCKET_ACCESS_TOKEN=...

# Or username + app password
BITBUCKET_USERNAME=your-bitbucket-username
BITBUCKET_APP_PASSWORD=...
```

(`LARAPILOT_BITBUCKET_*` aliases are also accepted.)

3. Ensure `origin` points at a `bitbucket.org` repository (`workspace/repo`)
4. Enable: `php artisan larapilot:settings-set --bitbucket=YES`
5. Probe: `php artisan larapilot:bitbucket-status`

### When ON

- Skills create/update pull requests via the Bitbucket Cloud API after push (still respecting `git_mode`), for example:

```bash
# Access token
curl -sS -X POST \
  -H "Authorization: Bearer $BITBUCKET_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  "https://api.bitbucket.org/2.0/repositories/{workspace}/{repo_slug}/pullrequests" \
  -d '{"title":"US-001 TASK-01","source":{"branch":{"name":"feature/US-001-…"}},"destination":{"branch":{"name":"develop"}}}'

# Or app password
curl -sS -u "$BITBUCKET_USERNAME:$BITBUCKET_APP_PASSWORD" -X POST \
  -H "Content-Type: application/json" \
  "https://api.bitbucket.org/2.0/repositories/{workspace}/{repo_slug}/pullrequests" \
  -d '…'
```

- Always surface the PR URL from the API response (`links.html.href`)
- Emit `pr_opened` / `pr_updated` via `larapilot:notify` when notifications are enabled

---

## Azure DevOps (`settings.azure`)

Uses the [Azure CLI](https://learn.microsoft.com/cli/azure/) (`az`) with the [`azure-devops` extension](https://learn.microsoft.com/azure/devops/cli/) for Azure Repos pull requests. A personal access token (PAT) is also accepted for the [Azure DevOps REST API](https://learn.microsoft.com/rest/api/azure/devops/git/pull-requests) when `az` is not available.

### Setup

1. Install the Azure CLI: https://learn.microsoft.com/cli/azure/install-azure-cli
2. Add the DevOps extension: `az extension add --name azure-devops`
3. Authenticate: `az login` **or** create a [PAT](https://learn.microsoft.com/azure/devops/organizations/accounts/use-personal-access-tokens-to-authenticate) (scope: `Code (read & write)`) and add it to `.env`:

```
# Preferred
AZURE_DEVOPS_EXT_PAT=...

# Also accepted
AZURE_DEVOPS_PAT=...
LARAPILOT_AZURE_DEVOPS_PAT=...
```

4. Ensure `origin` points at an Azure DevOps repository — `https://dev.azure.com/{org}/{project}/_git/{repo}`, `git@ssh.dev.azure.com:v3/{org}/{project}/{repo}`, or the legacy `https://{org}.visualstudio.com/{project}/_git/{repo}`
5. Enable: `php artisan larapilot:settings-set --azure=YES`
6. Probe: `php artisan larapilot:azure-status`

### When ON

- Skills open/update pull requests after push (still respecting `git_mode`), for example:

```bash
# az CLI (azure-devops extension)
az repos pr create \
  --organization "https://dev.azure.com/{org}" \
  --project "{project}" --repository "{repo}" \
  --source-branch "feature/US-001-…" --target-branch "develop" \
  --title "US-001 TASK-01"

# Or REST API with a PAT
curl -sS -u ":$AZURE_DEVOPS_EXT_PAT" -X POST \
  -H "Content-Type: application/json" \
  "https://dev.azure.com/{org}/{project}/_apis/git/repositories/{repo}/pullrequests?api-version=7.1" \
  -d '{"sourceRefName":"refs/heads/feature/US-001-…","targetRefName":"refs/heads/develop","title":"US-001 TASK-01"}'
```

- Always surface the PR URL (`https://dev.azure.com/{org}/{project}/_git/{repo}/pullrequest/{id}`)
- Emit `pr_opened` / `pr_updated` via `larapilot:notify` when notifications are enabled

---

## Notifications (`settings.notifications`)

Master switch. When OFF, `larapilot:notify` is a no-op (exit 0). Enable individual channels with `notify_slack` / `notify_discord` / `notify_telegram`.

### Events

| Event | Typical source |
| --- | --- |
| `task_done` | Auto from `larapilot:task-done` |
| `spec_done` | Auto from `larapilot:spec-approve` |
| `pr_opened` / `pr_updated` | Implement skill when github/gitlab/bitbucket is YES |
| `spec_review` | Implement handoff → REVIEW |
| `spec_blocked` / `review_changes` | Review skill |
| `schedule_drift` | Lucille |
| `ship_go` / `ship_nogo` | Ship skill |
| `security_fail` | Quality / OWASP gate |
| `doctor_fail` | Doctor (skill-opt-in) |
| `custom` | Any skill |

Manual send:

```bash
php artisan larapilot:notify \
  --event=custom \
  --title="Hello from Larapilot" \
  --body="Optional details" \
  --url="https://example.com"
```

### Slack

1. Create an [Incoming Webhook](https://api.slack.com/messaging/webhooks) for the target channel
2. Add to `.env`:

```
LARAPILOT_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...
```

3. Enable:

```bash
php artisan larapilot:settings-set --notifications=YES --notify-slack=YES
```

### Discord

1. Server Settings → Integrations → Webhooks → New Webhook → copy URL
2. Add to `.env`:

```
LARAPILOT_DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/...
```

3. Enable:

```bash
php artisan larapilot:settings-set --notifications=YES --notify-discord=YES
```

### Telegram

1. Talk to [@BotFather](https://t.me/BotFather) → `/newbot` → copy the bot token
2. Start a chat with your bot (or add it to a group)
3. Get `chat_id` (e.g. message [@userinfobot](https://t.me/userinfobot), or call `https://api.telegram.org/bot<TOKEN>/getUpdates` after sending a message)
4. Add to `.env`:

```
LARAPILOT_TELEGRAM_BOT_TOKEN=123456:ABC...
LARAPILOT_TELEGRAM_CHAT_ID=123456789
```

5. Enable:

```bash
php artisan larapilot:settings-set --notifications=YES --notify-telegram=YES
```

### Missing credentials

If a channel is ON but its env vars are missing, that channel is **skipped with a warning** — the workflow never fails because of notification delivery.

---

## Dashboard sign-in (follows `APP_ENV`)

There is no `dashboard_auth` setting. Who gets into `/larapilot` follows the environment:

| `APP_ENV` | What the dashboard does |
| --- | --- |
| `local` (and `testing`) | Open. No sign-in: whoever reaches it acts as an admin. |
| anything else that is not `production` (`staging`, `qa`, `preview`, …) | A sign-in page — username and password. An authenticator (TOTP) is optional, each user turns it on from **Account**. |
| `production` | Not served, unless `LARAPILOT_FORCE_IN_PROD=true`. Then every user signs in with a password **and** an authenticator, enrolled at the first sign-in if needed. |

Users live in `.larapilot/auth.yaml`: password hashes (bcrypt/argon2id), roles, and the authenticator secret encrypted with `APP_KEY` — no database, no `User` model, no cleartext. The file is git-ignored and never committed. There is **no sign-up and no password reset**: an admin creates users and sets passwords, on the **Users** page or from the shell — the first admin of a server always from the shell.

```bash
php artisan larapilot:dashboard-user add andrea --role=admin      # prompts for the password (12+ characters)
php artisan larapilot:dashboard-user add giulia --role=pm --password='…'
php artisan larapilot:dashboard-user role giulia dev
php artisan larapilot:dashboard-user password giulia               # a new password; her sessions end
php artisan larapilot:dashboard-user rename giulia giulia.rossi
php artisan larapilot:dashboard-user totp-reset giulia             # lost phone: she enrols again
php artisan larapilot:dashboard-user remove giulia
php artisan larapilot:dashboard-user list
```

| Role | Sees |
| --- | --- |
| `admin` | Everything: every page, **Users**, **API token**, **Anonymization**, the file manager, where the database lives. |
| `dev` | Everything but Users, API token, Anonymization, and the file manager; never the host or name of the database. In production the Database page and its downloads are **anonymized**. |
| `pm` | Workflow, Insights, Reference — not the Workspace (About, Settings, Skills, file manager, Database, Laravel, Git); the Logs outside production only. |
| `ext` | Workflow only: Board, PRD, Inception, Plan, Roadmap, Design and its mockups. |

- An admin cannot remove or rename themself, nor change their own role; another admin can, and an admin can act on every other admin.
- A changed password, a removed user, or a changed role takes effect on the next request of every open session.
- Wrong passwords are throttled per address (`LARAPILOT_DASHBOARD_AUTH_MAX_ATTEMPTS`, default 20 a minute) and per name (5 a minute); wrong codes per name. A code is good once.
- `.larapilot/auth-audit.log` (git-ignored) keeps one JSON line per sign-in, failure, second factor, user change, token, anonymization map, and file-manager zip — never a password, a code, or a token. `LARAPILOT_DASHBOARD_AUTH_AUDIT=false` turns it off.
- With no user yet, a shared host answers **HTTP 503** and says how to create the first admin.
- `.larapilot/auth.yaml` written before 5.1 (`users: {name: hash}`) is read as it is — every user an admin — and `larapilot:update` rewrites it with roles.
- The authenticator app shows the account under `LARAPILOT_DASHBOARD_AUTH_ISSUER` (default `APP_NAME`). The QR code is drawn on the server; the secret never leaves it.
- Serve the dashboard over **HTTPS** on every shared host, and behind a proxy trust it (`TrustProxies`) so the limits see the visitor's address.

---

## API access (`settings.api_auth`)

The token of the `/larapilot/api/*` JSON API. An admin makes it on the **API token** page of the dashboard, or from the shell; it is shown **once**, and only its hash is kept in `.larapilot/auth.yaml`. Rotating it ends the old one at once.

```bash
php artisan larapilot:api-token generate   # or: rotate, revoke, show
```

A token set in the environment works too (`LARAPILOT_API_TOKEN=…` in `.env`). Send it on every request as either header:

```
Authorization: Bearer <token>
X-Larapilot-Token: <token>
```

### Behaviour

| Where | No token exists | A token exists |
| --- | --- | --- |
| `local` / `testing`, `api_auth=NO` (default) | Open | Every request needs it, reads included |
| `local` / `testing`, `api_auth=YES` | **HTTP 503** with how to make one | Every request needs it |
| any other environment | **HTTP 503** with how to make one | Every request needs it |
| `production` | Hidden (404) unless `LARAPILOT_FORCE_IN_PROD=true`; then as above | |

A user signed in on the dashboard whose role sees the API reference reads it from the browser without the token — never a write, the group has no CSRF.

### Calling the API (client side)

Every `/larapilot/api/*` endpoint accepts the token in **either** of two headers — pick one:

```
Authorization: Bearer <LARAPILOT_API_TOKEN>
X-Larapilot-Token: <LARAPILOT_API_TOKEN>
```

Keep the token in the caller's own environment (never hard-code it). Examples below assume `LARAPILOT_API_TOKEN` and `LARAPILOT_BASE_URL` (e.g. `https://staging.acme.test`) are exported.

**curl / shell**

```bash
curl -sS -H "Authorization: Bearer $LARAPILOT_API_TOKEN" \
  "$LARAPILOT_BASE_URL/larapilot/api/board"

curl -sS -H "Authorization: Bearer $LARAPILOT_API_TOKEN" \
  "$LARAPILOT_BASE_URL/larapilot/api/diagnostics?no_logs=1"

# write endpoint (internal feedback)
curl -sS -X POST \
  -H "Authorization: Bearer $LARAPILOT_API_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"author":"CI","message":"Nightly smoke passed."}' \
  "$LARAPILOT_BASE_URL/larapilot/api/specs/US-001/comments"
```

**PHP (Laravel HTTP client)**

```php
use Illuminate\Support\Facades\Http;

$larapilot = Http::baseUrl(rtrim(env('LARAPILOT_BASE_URL'), '/').'/larapilot/api')
    ->withToken(env('LARAPILOT_API_TOKEN'))   // sends Authorization: Bearer …
    ->acceptJson();

$board = $larapilot->get('/board')->throw()->json();
$diag  = $larapilot->get('/diagnostics', ['no_logs' => 1])->throw()->json();
```

**JavaScript / Node (fetch)** — server-side only; never ship the token to a browser bundle:

```js
const base = `${process.env.LARAPILOT_BASE_URL}/larapilot/api`;
const headers = { Authorization: `Bearer ${process.env.LARAPILOT_API_TOKEN}` };

const board = await fetch(`${base}/board`, { headers }).then(r => r.json());
```

**CI (GitHub Actions)** — store the token as an encrypted secret and pass it through the environment:

```yaml
- name: Larapilot delivery snapshot
  env:
    LARAPILOT_API_TOKEN: ${{ secrets.LARAPILOT_API_TOKEN }}
    LARAPILOT_BASE_URL: https://staging.acme.test
  run: |
    curl -sS --fail -H "Authorization: Bearer $LARAPILOT_API_TOKEN" \
      "$LARAPILOT_BASE_URL/larapilot/api/board" > board.json
```

**Backstage** — set `larapilot.io/api-url` on the entity and let the Backstage **backend proxy** attach `LARAPILOT_API_TOKEN` server-side (see `.larapilot/runtime-ops.md`); the browser plugin never sees the token.

Fetching the OpenAPI contract itself also needs the token once one exists:

```bash
curl -sS -H "X-Larapilot-Token: $LARAPILOT_API_TOKEN" \
  "$LARAPILOT_BASE_URL/larapilot/api/openapi.json" > larapilot-openapi.json
```

### Notes

- The token protects **every** endpoint in the group — `/board`, `/specs`, `/specs/{code}`, `/specs/{code}/comments`, `/prd`, `/metrics`, **`/diagnostics`**, `/backstage`, `/backstage/catalog-info.yaml`, `/openapi.json`, and the Swagger UI at `/docs`.
- A wrong or missing token returns **HTTP 401**; where a token is required and none exists the endpoints return **HTTP 503** (fail-closed): a JSON `message` that says how to make one, or the same explanation as a page when a browser opens the API docs.
- The token is sent on every request — always serve the API over **HTTPS** on shared/staging hosts.
- Call the API through a server-side proxy so the token never reaches browser code.
- The `php artisan larapilot:diagnostics` **CLI** command and the MCP `diagnostics` tool are local and need no token — only the HTTP endpoint is gated.

### Rate limit, audit log & caching

Independent of `api_auth`, always on:

- **Rate limit** — `/larapilot/api/*` is throttled per IP by `larapilot.api.rate_limit` (`"max,minutes"`, default `120,1`). Over the limit → **HTTP 429** with `Retry-After`. Set `LARAPILOT_API_RATE_LIMIT=0` (or empty) to disable.
- **Audit log** — every **mutating** request (`POST /specs/{code}/comments`) appends one JSON line to `.larapilot/api-audit.log` (timestamp, method, path, IP, whether a token was sent, status — never bodies). The file is git-ignored automatically. Disable with `LARAPILOT_API_AUDIT=false`; relocate with `LARAPILOT_API_AUDIT_FILE=`.
- **Conditional requests** — `GET /board`, `/specs`, `/specs/{code}`, `/prd`, `/metrics` return an `ETag`. Send it back as `If-None-Match` to get **HTTP 304** when nothing changed — ideal for pollers.
- **Pagination** — `GET /specs` takes `?page` (1-based) and `?per_page` (1-200, default 50); the body carries `total`, `page`, `per_page`, `total_pages`.
- **Security headers** — every dashboard and API response carries `X-Content-Type-Options: nosniff` and `Referrer-Policy: no-referrer`; dashboard pages also send `X-Frame-Options: DENY`.

## Security scanners (`settings.security` + `settings.security_provider`)

Brings the open findings of a security scanner into the workflow. OFF by default. The user associates **one** scanner; it scans on its side, Larapilot runs none and installs nothing:

| Scanner | What it scans | Ids |
| --- | --- | --- |
| **Aikido** — SaaS, [aikido.dev](https://www.aikido.dev/) | The repository through the git provider: dependencies, code, secrets, IaC, containers | Numbers (`24`) |
| **Hackly** — self-hosted, [hackly.web.ap.it](https://hackly.web.ap.it/) | A clone of the repository (Semgrep, Trivy, Gitleaks, Composer audit, Checkpoint, Laravel checks) and the verified domains linked to it (TLS, headers, exposed paths, Nuclei, ZAP) | UUIDs |

```bash
php artisan larapilot:settings-set --security-provider=aikido   # or hackly; none turns it off
php artisan larapilot:security-status
```

`--aikido=YES` is the name the setting had before 5.1 and still means `--security-provider=aikido`. The same commands work for both scanners:

| Command (`php artisan larapilot:`) | What it does |
| --- | --- |
| `security-status` | Scanner, credentials, repository, last scan, `hints` |
| `security-issues [--new] [--severity=] [--report] [--gate] [--detail={id}]` | The open findings with `state` `new` · `in_backlog` · `waived` · `false_positive`, and `gate.verdict`; `--detail` gives one finding as a brief and a fix prompt |
| `security-plan --ids=…` | Groups confirmed ids by kind and fix for triage (a leaked secret never merges) |
| `security-link {ids} --spec=US-XXX` · `--waive --reason="…"` · `--false-positive --reason="…"` · `--forget` | What was decided, kept in `.larapilot/aikido.yaml` or `.larapilot/hackly.yaml` (committed: ids and decisions, never a credential) and told to the scanner; `--local` tells nothing |
| `security-push` | Tells the scanner the decisions it was not told (`unsent`) |
| `security-repos [--search=]` · `--use={id}` · `--forget` | Which repository of the scanner this project is, when the git remote finds none |
| `security-scan` | Asks for a new scan (Hackly: `--profile=quick\|standard\|deep`, `--include-targets`) |
| `security-register` | `{paths.security}/security-register.md` for the client: every finding open, resolved, or ignored with its reason, in the PRD language |

The `aikido-*` commands are the older names of the same Aikido steps. Through the MCP `RunArtisanTool` an agent may run `security-status`, `security-issues` (not `--report`), `security-plan`, and `security-repos --search` — the reads; every command that decides, writes, or scans runs with Artisan. `/larapilot-security` downloads, confirms, groups, and hands each group to `/larapilot-triage`; `/larapilot-ship` runs `security-issues --gate --report`; `/larapilot/security` shows the findings, the decisions, and the verdict of the gate.

### Aikido (`security_provider: aikido`)

Brings the findings of [Aikido](https://www.aikido.dev/) into the workflow. OFF by default. **Aikido scans the repository on its side**; Larapilot runs no scanner and installs nothing: it reads what Aikido found, over the public REST API, and tells Aikido what the user decided about each finding.

#### Setup

1. Connect the repository in Aikido, through the git provider (GitHub, GitLab, Bitbucket, Azure DevOps). Larapilot cannot do this step.
2. In Aikido, **Settings → Integrations → Public REST API**, create a client. Reading needs the `issues:read` and `repositories:read` scopes; telling Aikido a decision needs `issues:write`; asking for a scan needs `repositories:write`.
3. Put the credentials in `.env` — never in `.larapilot/`, which is committed:

```dotenv
LARAPILOT_AIKIDO_CLIENT_ID=
LARAPILOT_AIKIDO_CLIENT_SECRET=
LARAPILOT_AIKIDO_REGION=eu            # eu (default) · us · au · me
LARAPILOT_AIKIDO_REPOSITORY=          # id or name in Aikido, for this machine; empty = chosen or found from the git remote
LARAPILOT_AIKIDO_FAIL_ON=high         # critical · high · medium · low · none
LARAPILOT_AIKIDO_PUSH_DECISIONS=true  # false = decisions stay in the project, Aikido is told nothing
```

4. Turn it on and check:

```bash
php artisan larapilot:settings-set --security-provider=aikido
php artisan larapilot:security-status
```

#### The repository

Larapilot finds the repository of the workspace from the git remote of the project, by its address or by its name. When the code is scanned under another repository — a fork, a mirror, a monorepo, a name that differs — nothing matches: `security-status` answers `needs_repository: true`, and **the user is asked which one it is**. `/larapilot-security` asks in chat, `/larapilot/security` shows the list with a form, and from the terminal:

```bash
php artisan larapilot:security-repos                # the repositories of the workspace
php artisan larapilot:security-repos --search=shop  # only the names that hold these letters
php artisan larapilot:security-repos --use=12       # this project is repository 12 (or its exact name)
php artisan larapilot:security-repos --forget       # back to the git remote
```

The choice is kept in `.larapilot/aikido.yaml`, which is committed, so every machine reads the same repository. `LARAPILOT_AIKIDO_REPOSITORY` in `.env` names it for one machine and wins there. Nothing is ever chosen for the user: a name that looks alike is not a match.

#### Decisions are told to Aikido

What the user decides here is sent to Aikido, so the workspace says the same as the project:

| Decision | In Aikido |
| --- | --- |
| `security-link 40 --waive --reason="…"` | The finding is **ignored**, with the reason as its comment |
| `security-link 24 --spec=US-012` | A **note** on the finding: the spec that fixes it |
| `security-link 40 --forget` | The waiver is taken back: the finding is open again |

- A finding can be in several repositories of the workspace. When it is, only the issues of **this** repository are ignored, and the other projects keep theirs.
- The credentials need the `issues:write` scope. When Aikido refuses, or cannot be reached, **the decision is kept** and listed as `unsent`; `php artisan larapilot:security-push` tells it later. It also tells the decisions taken with an earlier version.
- A waiver Aikido would not take back is not forgotten here, so the two never disagree.
- `--local` on `security-link` keeps one decision in the project; `LARAPILOT_AIKIDO_PUSH_DECISIONS=false` keeps them all.
- A waived finding leaves the open ones as soon as Aikido ignores it, and is listed under *No longer open in Aikido* with its reason.

#### The register for the client

`php artisan larapilot:security-register` writes `{paths.security}/security-register.md`, and **Register for the client (.md)** on `/larapilot/security` downloads it: every finding of the repository — **open** with the fix that is planned, **resolved** with the date, **ignored** with the date and the reason — and a count by severity. It is the document a client or an auditor asks for, written in the language of the PRD.

The reason of a finding waived here is the one the user wrote. Aikido does not give back the reason of a finding ignored there by hand: the register lists it, and says the reason is kept in Aikido.

#### When ON

- `/larapilot-security` downloads the open findings, has the user confirm each one (resolve, waive, or skip), groups the confirmed ids with `larapilot:security-plan`, and hands each resolution group to `/larapilot-triage`, which routes it to `/larapilot-bug` or `/larapilot-feature`. The spec that fixes a finding is recorded with `larapilot:security-link`.
- `/larapilot-ship` runs `php artisan larapilot:security-issues --gate --report`: `FAIL` is a release blocker, `WARN` a note.
- `/larapilot/security` shows the findings, what was decided about each, and the verdict of the gate.
- `{paths.security}/security-report.md` is the report for the team: every open finding with its decision. `{paths.security}/security-register.md` is the register for the client.
- `.larapilot/aikido.yaml` keeps the decisions, whether Aikido was told, and the repository that was chosen. Commit it, so a finding handed to the backlog on one machine is not handed over again on another.

#### What it never does

- It never calls Aikido while it is not the associated scanner.
- It never writes a credential or an access token to a file of the project: the token lives in the cache for as long as Aikido says it lasts.
- It never marks a finding as fixed. A finding leaves the list when Aikido no longer reports it — after the fix is merged and scanned.
- It never waives a finding. Only the user does, with a reason of at least a sentence — and only then is Aikido told to ignore it.
- It never writes to Aikido anything but a decision of the user: no finding is closed, snoozed, or rated again.
- It never chooses the repository: when the git remote finds none, the user names it.

The address of the token endpoint is derived from the region (`https://app.{region}.aikido.dev/api/oauth/token`). Set `LARAPILOT_AIKIDO_BASE_URL` when the workspace is reached through another address.

### Hackly (`security_provider: hackly`)

Hackly is **self-hosted**: Larapilot reads **your** instance. [hackly.web.ap.it](https://hackly.web.ap.it/) is its documentation, never an instance — the status says so when `LARAPILOT_HACKLY_URL` points at it.

#### Setup

1. In your Hackly, add the repository (Repositories → New, with a GitHub token that reads it). Link its domains to it if you want their findings too — they must be verified by DNS in Hackly.
2. Create a personal token: Profile → API tokens, or on the Hackly server `php artisan hackly:token you@example.com --ability=write`. `read` lists; `write` lets Larapilot tell Hackly a decision and ask for a scan.
3. Put the address of the instance and the token in `.env`:

```dotenv
LARAPILOT_HACKLY_URL=https://hackly.example.com   # your instance; /api is added
LARAPILOT_HACKLY_TOKEN=hk_…
LARAPILOT_HACKLY_REPOSITORY=                       # uuid or owner/name, for this machine; empty = chosen or found from the git remote
LARAPILOT_HACKLY_INCLUDE_TARGETS=true              # read the findings of the linked domains too
LARAPILOT_HACKLY_FAIL_ON=high                      # high, medium, low, or none
LARAPILOT_HACKLY_SCAN_PROFILE=standard             # quick, standard, or deep
LARAPILOT_HACKLY_PUSH_DECISIONS=true               # false = Hackly is told nothing
```

4. `php artisan larapilot:settings-set --security-provider=hackly`, then `php artisan larapilot:security-status`.

#### Decisions are told to Hackly

| Decision | In Hackly |
| --- | --- |
| `security-link {id} --spec=US-012` | The finding is **acknowledged** — the spec is kept in `.larapilot/hackly.yaml` |
| `security-link {id} --waive --reason="…"` | **Acknowledged** — Hackly has no "accepted risk"; the reason is kept here and in the register |
| `security-link {id} --false-positive --reason="…"` | **False positive**, with the reason as its note (8–2000 characters) |
| `security-link {id} --forget` | **Open** again |

Hackly refusing (a `read` token, no network) undoes nothing: the decision stays `unsent` until `security-push`.

#### What is read, and how the gate is made

- The findings of the repository — and of the domains linked to it, unless `LARAPILOT_HACKLY_INCLUDE_TARGETS=false` — in the states `open` and `ack`. Passed checks and scan deltas are left out.
- Severities are Hackly's three: `high`, `medium`, `low`. Reachable code comes first among the same severity.
- The gate is computed here, on the findings of this project: Hackly's own CI gate (`/v1/gates/ci`) looks at the whole workspace.
- `security-issues --detail={uuid}` quotes Hackly's own brief and fix prompt: that prompt is what triage hands to the fix.
- `security-scan` asks for a repository scan; `--include-targets` deep-scans the verified linked domains too. Target scans and penetration tests are started in Hackly: each needs its DNS check.
- The register lists fixed findings as resolved and false positives as ignored, with the note written in Hackly when none was written here.

#### What it never does

- It never calls Hackly while it is not the associated scanner, and never with the token printed or written under `.larapilot/`.
- It never marks a finding as fixed: a finding leaves the list when Hackly no longer reports it — after the fix is merged and scanned.
- It never waives or calls a finding a false positive: only the user does, with a reason.
- It never chooses the repository: when the git remote finds none, the user names it.

## Production errors (`settings.errors` + `settings.errors_provider`)

Brings the errors the running application throws into the workflow. OFF by default. **One tracker at a time** records them; Larapilot reads the open ones back, puts them together into bugs, has you confirm them, plans resolution groups with `larapilot:errors-plan`, and hands **each group** to `/larapilot-triage`.

### Choose the tracker

`/larapilot-error` asks which one, Boogle included, when none is set — or name it yourself:

```bash
php artisan larapilot:settings-set --errors=YES --errors-provider=sentry
php artisan larapilot:errors-status
```

| Provider | The application sends with | Larapilot reads | One row is | Closes from Larapilot |
| --- | --- | --- | --- | --- |
| **boogle** (default) | `andreapollastri/boogle-client` | The admin API of the [Boogle](https://boogle.web.ap.it/) the team hosts | a throw | Yes |
| **sentry** | `sentry/sentry-laravel` | The unresolved issues of a project | a bug | Yes |
| **bugsnag** | `bugsnag/bugsnag-laravel` | The open errors of a project | a bug | Yes |
| **flare** | `spatie/laravel-flare`, or Ignition | The open errors of a project | a bug | Yes |
| **datadog** | the Datadog agent, or the logs | The open issues of Error Tracking — or the error logs | a bug — a throw with logs | Yes — No with logs |
| **rollbar** | `rollbar/rollbar-laravel` | The active items of a project | a bug | Yes |
| **honeybadger** | `honeybadger-io/honeybadger-laravel` | The unresolved faults of a project | a bug | Yes |
| **cloudwatch** | the log channel, shipped to AWS | The log events that match an error filter, through the AWS CLI | a throw | No |

`--boogle=YES` is the name the setting had when Boogle was the only tracker: it is `--errors=YES --errors-provider=boogle`, and `settings.boogle` is kept in step with the two. Turning `errors` off keeps the tracker that was named.

- **One row is a throw** — Larapilot puts together the rows that share the exception, the file, and the line, counts them, and draws them day by day on `/larapilot/errors`.
- **One row is a bug** — the tracker grouped already: its grouping and its count are kept. The page has no day chart, since the tracker says when a bug was last thrown and not each time.

### What goes in `.env`

Always a credential that **reads** the tracker — never the key the application reports with (`SENTRY_LARAVEL_DSN`, `BUGSNAG_API_KEY`, `FLARE_KEY`, `ROLLBAR_TOKEN`, `HONEYBADGER_API_KEY`). The variables of each tracker are in its section below, and `errors-status` names every one that is missing. Two are shared:

```dotenv
LARAPILOT_ERRORS_CACHE=300     # seconds the dashboard keeps what it read
LARAPILOT_ERRORS_TIMEOUT=15    # seconds a call to the tracker may take
```

### When ON

- `/larapilot-error` — the same skill for every tracker; it asks which one when none is set — downloads the open errors, writes `{paths.support}/errors.md`, has you confirm each bug (resolve, ignore with a reason, or skip), groups the confirmed codes with `larapilot:errors-plan`, and hands each group to `/larapilot-triage`. The spec that fixes it is recorded with `larapilot:errors-link`.
- `/larapilot/errors` shows the bugs, how many times each was thrown, and what was decided. **Download report (.md)** saves the report.
- `.larapilot/boogle.yaml` keeps the decisions — the file keeps its name, whichever the tracker. Commit it, so a bug handed to the backlog on one machine is not handed over again on another, nor the next time it is thrown.
- `larapilot:errors-resolve` closes an error in the tracker once its fix is released, where the tracker allows it. An error closed this way and thrown again is shown as **back after the fix**.
- `larapilot:boogle-status`, `boogle-errors`, `boogle-plan`, `boogle-link`, and `boogle-resolve` are the old names of `errors-status`, `errors-list`, `errors-plan`, `errors-link`, and `errors-resolve`, and still answer.

### What it never does

- It never calls a tracker while `errors` is `NO`.
- It never reads the user, the query string, or the payload of a request into a file, a report, the cache, or the chat. It keeps the exception, the message with addresses and long secrets masked, the file and line, the method and the path.
- It never writes to a tracker by itself: `errors-resolve` runs when the user asks, and is not allowed through the MCP tool. `larapilot:errors-plan` only reads, and is.
- It never leaves an error as it is. Only the user does, with a reason of at least a sentence.

Laravel Nightwatch is not among the trackers: it publishes no API to read exceptions with.

## Boogle (`errors_provider: boogle`)

[Boogle](https://boogle.web.ap.it/) is the exception tracker and uptime monitor the team hosts; the application sends its exceptions there with `andreapollastri/boogle-client`. Larapilot reads them back over the admin API.

1. Have the application send its exceptions to Boogle: `composer require andreapollastri/boogle-client`, then `php artisan boogle:install`. Larapilot does not do this step.
2. In Boogle, as an **admin** user, create a token in the profile under **API tokens**. The admin API answers to admin users only.
3. Put the address and the token in `.env` — never in `.larapilot/`, which is committed:

```dotenv
LARAPILOT_BOOGLE_URL=https://boogle.example.com   # empty = taken from BOOGLE_SERVER
LARAPILOT_BOOGLE_TOKEN=
LARAPILOT_BOOGLE_PROJECT=                          # id or title in Boogle; empty = found from BOOGLE_PROJECT_KEY, then APP_URL
```

4. Turn it on and check:

```bash
php artisan larapilot:settings-set --errors=YES --errors-provider=boogle   # or --boogle=YES
php artisan larapilot:errors-status
```

- What is open is `OPEN` and `READ` in Boogle: seen is not fixed. Codes are the ones of Boogle (`#BUG12`).
- Boogle also watches uptime: the times the application did not answer (`#OUT…`) are listed apart, as **outages**, and handed to triage only when asked.
- `errors-resolve` closes every open occurrence of the bug as `FIXED` (`--status=DONE` for the other word Boogle has), with the spec that fixed it in the history.
- The key and the token of a project, which Boogle sends with the list of projects, are never kept: they are compared with `BOOGLE_PROJECT_KEY` and dropped.

The token reads **every project** of that Boogle, because Boogle gives tokens to users and not to projects. Keep it in `.env` and in the secrets of the CI.

## Sentry (`errors_provider: sentry`)

```dotenv
LARAPILOT_SENTRY_AUTH_TOKEN=             # or SENTRY_AUTH_TOKEN — scope event:read; event:write to close an issue
LARAPILOT_SENTRY_ORGANIZATION=           # or SENTRY_ORG — the slug of the organization
LARAPILOT_SENTRY_PROJECT=                # or SENTRY_PROJECT — the slug of the project
LARAPILOT_SENTRY_URL=https://sentry.io   # a self-hosted Sentry, or a region such as https://de.sentry.io
```

Reads the unresolved issues of the project, the ones thrown the most first, a hundred at most. Codes are the short ids of Sentry (`SHOP-1A`). `errors-resolve` marks the issue resolved.

## Bugsnag (`errors_provider: bugsnag`)

```dotenv
LARAPILOT_BUGSNAG_AUTH_TOKEN=     # a personal auth token: My account → Personal auth tokens
LARAPILOT_BUGSNAG_PROJECT_ID=     # Project settings → General
LARAPILOT_BUGSNAG_PROJECT_NAME=   # optional: the title of the report
```

Reads the open errors of the project over the Data Access API, a hundred at most. `errors-resolve` marks the error fixed.

## Flare (`errors_provider: flare`)

```dotenv
LARAPILOT_FLARE_TOKEN=            # a personal access token: scope read; write to resolve an error
LARAPILOT_FLARE_PROJECT_ID=
LARAPILOT_FLARE_PROJECT_NAME=     # optional: the title of the report
```

Reads the errors of the [Flare](https://flareapp.io/) project and leaves out the ones resolved or snoozed there. `errors-resolve` resolves the error.

## Datadog (`errors_provider: datadog`)

```dotenv
LARAPILOT_DATADOG_API_KEY=               # or DD_API_KEY
LARAPILOT_DATADOG_APP_KEY=               # or DD_APP_KEY
LARAPILOT_DATADOG_SITE=datadoghq.com     # or DD_SITE: datadoghq.eu, us3.datadoghq.com, …
LARAPILOT_DATADOG_SERVICE=               # the service the application is tagged with; empty = APP_NAME
LARAPILOT_DATADOG_SOURCE=error_tracking  # or logs
LARAPILOT_DATADOG_TRACK=trace            # the track of Error Tracking: trace (APM), logs, or rum
```

With `error_tracking`, reads the open issues of the service in [Datadog](https://www.datadoghq.com/) Error Tracking over the last two weeks; `errors-resolve` sets the issue to resolved. With `logs`, reads the error logs of the service (`status:error`) over the last two weeks from Log Management, one row for each throw, and nothing is closed from Larapilot.

## Rollbar (`errors_provider: rollbar`)

```dotenv
LARAPILOT_ROLLBAR_ACCESS_TOKEN=   # a project access token: scope read; write to resolve an item
LARAPILOT_ROLLBAR_PROJECT_NAME=   # optional: the title of the report
```

Reads the active items of the project the token belongs to. Codes are the numbers of the items (`#RB57`). `errors-resolve` resolves the item.

## Honeybadger (`errors_provider: honeybadger`)

```dotenv
LARAPILOT_HONEYBADGER_AUTH_TOKEN=     # the personal authentication token of a user: profile → Authentication
LARAPILOT_HONEYBADGER_PROJECT_ID=     # as in the address of the page of the project
LARAPILOT_HONEYBADGER_PROJECT_NAME=   # optional: the title of the report
```

Reads the faults that are neither resolved nor ignored, the most frequent first, a hundred at most. `errors-resolve` marks the fault resolved.

## AWS CloudWatch Logs (`errors_provider: cloudwatch`)

```dotenv
LARAPILOT_CLOUDWATCH_LOG_GROUP=          # the log group the application writes to
LARAPILOT_CLOUDWATCH_REGION=eu-west-1    # or AWS_DEFAULT_REGION
LARAPILOT_CLOUDWATCH_PROFILE=            # optional: a profile of the AWS CLI
LARAPILOT_CLOUDWATCH_FILTER="?ERROR ?Exception ?CRITICAL"
```

No key of AWS is kept by Larapilot: it runs `aws logs filter-log-events` with the AWS CLI signed in on the machine (`aws configure`, or the keys in the environment), over the last two weeks, three hundred events at most. The exception and the place in the code are read from the text of each line. Nothing is closed from Larapilot.

## Security scan (`settings.security_scan`)

Folds a **static Laravel security scan** into `/larapilot-review` and the pre-ship gate. OFF by default. Larapilot does **not** bundle a scanner and never runs one on its own: the setting makes review and ship run it; otherwise it runs when someone asks — `php artisan larapilot:checkpoint-scan`, or *Run the scan* on the dashboard (**Security → Checkpoint**).

Backed by [`andreapollastri/checkpoint`](https://github.com/andreapollastri/checkpoint) (MIT) — 26 static checks (secrets, SQLi/XSS/CSRF/SSRF/path-traversal patterns, crypto, session/cookie config, EOL PHP/Laravel) plus `composer` / `npm` dependency auditing, via `php artisan checkpoint:scan`.

### Setup

1. Install the scanner as a dev dependency in the **target app** (not a Larapilot dependency):

```bash
composer require --dev andreapollastri/checkpoint
```

2. Enable the gate:

```bash
php artisan larapilot:settings-set --security-scan=YES
```

3. Optional — publish checkpoint's own config to suppress false positives, whitelist packages, or exclude folders:

```bash
php artisan vendor:publish --tag=checkpoint-config
```

### When ON

- `/larapilot-review` runs `php artisan larapilot:checkpoint-scan` (Checkpoint's `checkpoint:scan --json`, kept for the dashboard) and folds the results into the review:
  - `FAIL` → **review blocker**. Fix it, or record an explicit waiver with `php artisan larapilot:decision-log` before `/larapilot-ship`.
  - `WARN` → review note, surfaced but non-blocking.
- If the package is **not installed**, the review skill stops and asks the user to `composer require --dev andreapollastri/checkpoint` (or to turn the setting back OFF).
- Owned by **Lars** (Security Expert), together with the dashboard sign-in and `api_auth`.
- checkpoint ships its own GitHub Actions / GitLab CI scaffold (`checkpoint:scan` in CI) — use that for pipeline enforcement rather than re-wrapping it here.

### On the dashboard

**Security → Checkpoint** shows the last scan whatever the setting: the verdict, the checks by area (dependencies, configuration, code), each finding with its suppression hash, and the trend of the scans. The result stays in `.larapilot/cache/checkpoint/` on the machine that ran it — out of git, because the details can quote code. `php artisan larapilot:checkpoint-scan` takes `--only=` / `--skip=` (check names), `--report` (`{paths.security}/checkpoint.md`), `--cached`, and `--gate` (`--fail-on-warn`).

## SBOM and vulnerable dependencies (OSV.dev)

No setting: the SBOM is read from the lockfiles, and the vulnerability check runs only when asked.

- **`php artisan larapilot:sbom`** — every package of `composer.lock`, of the JavaScript lockfile of this repository (`package-lock.json`, `pnpm-lock.yaml`, `yarn.lock`, `bun.lock`), and of the frontend companion when one is linked (its own lockfile, or the workspace's in a monorepo): version, direct or transitive, production or development, license, package URL. `--write=md|cyclonedx|both` saves `sbom.md` and `sbom.cdx.json` (CycloneDX 1.5) under `{paths.security}`.
- **`php artisan larapilot:vendor-audit`** — sends the name and the version of each package, nothing else, to [OSV.dev](https://osv.dev) (`POST /v1/querybatch`, then `GET /v1/vulns/{id}`): the GitHub advisories, FriendsOfPHP, and npm. No account, no key. Each advisory comes with its severity (the advisory's word, else its CVSS 3 score), its CVE aliases, and the version that fixes it. The answer is cached in `.larapilot/cache/vendor-audit.json`; advisories in `.larapilot/cache/osv/`. `--gate` exits 1 on an open advisory at `--fail-on` (default `high`) or above; `--report` writes `{paths.security}/vendor-audit.md`.
- **`php artisan larapilot:vendor-link {ids} --spec=US-012 | --waive --reason="…" | --clear`** — the decisions, in `.larapilot/vendor-audit.yaml` with the trend of the checks: commit it.
- **`/larapilot-vendor-check`** runs the check, has the user confirm package by package, and updates, hands to triage, or waives. `/larapilot-ship` runs `vendor-audit --gate`. The dashboard page **SBOM** shows it all and exports Markdown and CycloneDX.

## Upgrade readiness (Packagist)

**`php artisan larapilot:upgrade-check --laravel=13 | --php=8.4 | --db=pgsql:17`** reads `composer.lock` and asks Packagist (`https://repo.packagist.org/p2/{vendor}/{package}.json`, the public metadata Composer itself reads) which release of each direct dependency supports the target. Answers are cached for a day in `.larapilot/cache/packagist/`; `--offline` reads the lock only. A package served from a private repository (Nova, Spark, a Satis) is reported as `private`: Packagist cannot see it. Composer has the last word: the check lists the `--dry-run` that asks it. Used by `/larapilot-laravel-upgrade`, `/larapilot-php-upgrade`, and `/larapilot-db-upgrade`; the dashboard page **About** shows the versions and their support windows.
