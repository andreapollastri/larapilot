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
