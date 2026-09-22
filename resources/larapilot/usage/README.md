# Usage ledger (Lucille)

Committed metrics for AI tokens and wall-clock time spent on this project.

| File | Purpose |
| ---- | ------- |
| `ledger.jsonl` | Append-only JSON lines (do not rewrite history) |
| `schedule.yaml` | Deadlines and milestones |

Log via `php artisan larapilot:usage-log`. Query/export via `php artisan larapilot:usage-report --insights` (filters: `--category=`, `--user=`, `--skill=`, `--spec=`, `--from=`, `--to=`). Interrogate in chat with `/larapilot-usage` (Lucille). Token charts are on the dashboard **Usage** page; deadlines and the Gantt are on **Plan**.

Never commit secrets, API keys, or full prompt transcripts here.
