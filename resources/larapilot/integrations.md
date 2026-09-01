# Larapilot — Optional Integrations

All integrations below are **opt-in** and **OFF by default**. Toggle them with `/larapilot-settings` or:

```bash
php artisan larapilot:settings-set --github=YES
php artisan larapilot:settings-set --gitlab=YES
php artisan larapilot:settings-set --bitbucket=YES
php artisan larapilot:settings-set --azure=YES
php artisan larapilot:settings-set --dashboard-auth=YES
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

## Dashboard access (`settings.dashboard_auth`)

Optional **HTTP Basic Auth** in front of the `/larapilot` dashboard **UI**. OFF by default — the dashboard is open in the allowed environments. This gate is **UI-only**: it never touches `/larapilot/api/*` (protect that with `LARAPILOT_API_TOKEN`) or the MCP server.

Credentials are stored as argon2id/bcrypt **hashes** in `.larapilot/auth.yaml` — no database, no `User` model, no cleartext. The file is appended to the project `.gitignore` the first time a user is written and must never be committed.

### Setup

1. Add one or more users (the `add` action prompts for the password, or pass `--password=`):

```bash
php artisan larapilot:dashboard-user add andrea
php artisan larapilot:dashboard-user add reviewer --password='…'
php artisan larapilot:dashboard-user list
php artisan larapilot:dashboard-user remove reviewer
```

2. Enable the gate:

```bash
php artisan larapilot:settings-set --dashboard-auth=YES
```

3. Optional env tuning (`config/larapilot.php` → `dashboard_route.auth`):

```
LARAPILOT_DASHBOARD_AUTH_REALM=Larapilot          # Basic Auth realm shown by the browser
LARAPILOT_DASHBOARD_AUTH_MAX_ATTEMPTS=30          # failed sign-ins per minute per IP; 0 disables throttling
```

### Notes

- With `dashboard_auth=YES` and **no** users configured, the dashboard returns **HTTP 500** (fail-closed) until a user is added or the setting is turned back OFF.
- Basic Auth transmits credentials on every request — always serve the dashboard over **HTTPS** on shared/staging hosts.
- The dashboard is still never served in `production` regardless of this setting.

---

## API access (`settings.api_auth`)

A shared-token gate on the `/larapilot/api/*` JSON API — the API **only**. It never touches the `/larapilot` dashboard UI (that is `dashboard_auth`) or the MCP server, and the API is never served in `production`.

The token lives in the environment, never in `.larapilot/`:

```
LARAPILOT_API_TOKEN=your-long-random-string
```

Send it on every request as either header:

```
Authorization: Bearer your-long-random-string
X-Larapilot-Token: your-long-random-string
```

### Setup

1. Set `LARAPILOT_API_TOKEN` in the dev/staging environment.
2. Make the token mandatory for the whole API:

```bash
php artisan larapilot:settings-set --api-auth=YES
```

### Behaviour

| `api_auth` | `LARAPILOT_API_TOKEN` set | Result |
| --- | --- | --- |
| `NO` (default) | no  | Reads open in dev/staging; writes refused outside `local`/`development`/`testing`. |
| `NO` (default) | yes | Every request (read + write) must carry the token. |
| `YES` | no  | **HTTP 503** — the API fails closed until the token is configured. |
| `YES` | yes | Every request (read + write) must carry the token. |

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

Fetching the OpenAPI contract itself also needs the token when `api_auth=YES`:

```bash
curl -sS -H "X-Larapilot-Token: $LARAPILOT_API_TOKEN" \
  "$LARAPILOT_BASE_URL/larapilot/api/openapi.json" > larapilot-openapi.json
```

### Notes

- `api_auth=YES` protects **every** endpoint in the group — `/board`, `/specs`, `/specs/{code}`, `/specs/{code}/comments`, `/prd`, `/metrics`, **`/diagnostics`**, `/backstage`, `/backstage/catalog-info.yaml`, `/openapi.json`, and the Swagger UI at `/docs`.
- A wrong or missing token returns **HTTP 401**; with `api_auth=YES` and no `LARAPILOT_API_TOKEN` configured on the server the endpoints return **HTTP 503** (fail-closed).
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

## Security scan (`settings.security_scan`)

Folds a **static Laravel security scan** into `/larapilot-review` and the pre-ship gate. OFF by default. Larapilot does **not** bundle a scanner and never runs one on its own — the scan happens only when this setting is `YES` **and** the optional dev package is installed.

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

- `/larapilot-review` runs `php artisan checkpoint:scan --json` and folds the results into the review:
  - `FAIL` → **review blocker**. Fix it, or record an explicit waiver with `php artisan larapilot:decision-log` before `/larapilot-ship`.
  - `WARN` → review note, surfaced but non-blocking.
- If the package is **not installed**, the review skill stops and asks the user to `composer require --dev andreapollastri/checkpoint` (or to turn the setting back OFF).
- Owned by **Lars** (Security Expert), together with `dashboard_auth` and `api_auth`.
- checkpoint ships its own GitHub Actions / GitLab CI scaffold (`checkpoint:scan` in CI) — use that for pipeline enforcement rather than re-wrapping it here.
