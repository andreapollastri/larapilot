# Larapilot

**From a rough product idea to reviewed Laravel code, with an AI product team that follows a real process.**

AI agents are fast, but isolated prompts are not a product process. Larapilot turns your assistant into a disciplined squad with a real workflow — discovery → backlog → plan → implement → review → ship — backed by version-controlled artifacts in `.larapilot/`.

Larapilot ports the [ARchetipo](https://github.com/techreloaded-ar/ARchetipo) spec-driven workflow to **Laravel and PHP**, integrated with [Laravel Boost](https://laravel.com/ai/boost). Artisan commands and Boost skills/MCP tools give your AI agent a disciplined product process and deep Laravel context.

See [Why Larapilot](https://larapilot.web.ap.it/#why), [How it works](https://larapilot.web.ap.it/#how-it-works), and the [end-to-end walkthrough](https://larapilot.web.ap.it/#walkthrough) for the full picture.

📖 **Full documentation:** [larapilot.web.ap.it](https://larapilot.web.ap.it)

---

## Requirements

- PHP **^8.3**
- Laravel **^12** or **^13**
- [Laravel Boost](https://laravel.com/ai/boost) `^2.0` (installed automatically)
- An AI editor with MCP support (Cursor, Claude Code, etc.)

---

## Quickstart

```bash
composer require andreapollastri/larapilot --dev
php artisan larapilot:install
php artisan boost:install
```

If needed, register both MCP servers in your editor:

```json
{
    "mcpServers": {
        "laravel-boost": {
            "command": "php",
            "args": ["artisan", "boost:mcp"]
        },
        "larapilot": {
            "command": "php",
            "args": ["artisan", "mcp:start", "larapilot"]
        }
    }
}
```

Start with `/larapilot-inception` in your AI editor, then follow the spec-driven loop for each user story.

Step-by-step guide and a full walkthrough (idea → PRD → backlog → code → ship): [Walkthrough](https://larapilot.web.ap.it/#walkthrough)

Other commands:

```bash
# Update
composer update andreapollastri/larapilot
php artisan larapilot:update

# Verify installation
php artisan larapilot:doctor
```

---

## License

MIT © Andrea Pollastri
