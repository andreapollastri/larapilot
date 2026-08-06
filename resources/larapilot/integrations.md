# Larapilot — Optional Integrations

All integrations below are **opt-in** and **OFF by default**. Toggle them with `/larapilot-settings` or:

```bash
php artisan larapilot:settings-set --github=YES
php artisan larapilot:settings-set --gitlab=YES
php artisan larapilot:settings-set --bitbucket=YES
php artisan larapilot:settings-set --notifications=YES --notify-slack=YES --notify-discord=NO --notify-telegram=NO
```

**Never put secrets in `.larapilot/config.yaml`.** Use `.env` (or the host secret store).

Remote forge toggles (`github` / `gitlab` / `bitbucket`) are **orthogonal** to `settings.git_mode`. Enable the forge that matches your `origin` host. You can leave unused forges OFF.

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
