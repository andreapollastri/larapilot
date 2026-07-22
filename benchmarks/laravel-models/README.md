# Laravel × Cursor model benchmark
#
# Compares Cursor agent models on the same Laravel coding tasks (Pest verifies),
# then writes a markdown leaderboard for article writing.

## What you get

- Isolated Laravel fixture (created once via Composer)
- 6 Laravel-focused tasks (Eloquent, Form Request/API, Job/Mail, N+1, Policy, service refactor)
- One fresh workdir per **model × task**
- Metrics: verify pass/fail, duration, token usage, files changed
- Weighted score + `REPORT.md` ready for the article

## Setup

```bash
cd benchmarks/laravel-models
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt

cp config.example.yaml config.yaml
# edit models: — pin IDs from your account
export CURSOR_API_KEY="cursor_..."
```

Requires: PHP 8.2+, Composer, and a Cursor API key ([Dashboard → Integrations](https://cursor.com/dashboard/integrations)).

## Commands

```bash
# See model IDs available on your key
python run.py list-models

# Build the reusable Laravel app under fixtures/laravel-app
python run.py prepare-fixture

# Smoke test: one model, one task
python run.py run --model composer-2.5 --task eloquent_scope --stream

# Full matrix from config.yaml
python run.py run

# Rebuild markdown from a previous run
python run.py report results/20260722T120000Z/results.json
```

## Output

Each run writes:

```
results/<timestamp>/
  results.json   # raw metrics
  REPORT.md      # leaderboard + per-task table
```

Workdirs stay in `.work/` when `keep_workdirs: true` so you can inspect diffs for the article.

## Scoring (article-friendly)

| Signal | Default weight | Meaning |
| --- | ---: | --- |
| pass | 50 | Pest/verify green |
| speed | 20 | Faster among finishers of the same task |
| tokens | 15 | Fewer tokens among finishers |
| cleanliness | 15 | Fewer changed files among finishers |

Edit weights under `scoring.weights` in `config.yaml`.

## Adding a task

Drop a YAML file in `tasks/`:

```yaml
id: my_task
title: Short title
category: eloquent
difficulty: medium
timeout_s: 600
prompt: |
  Implement …
verify:
  - php artisan test --filter=MyTest
# optional seed files before the agent runs:
# setup:
#   files:
#     app/Http/Controllers/Foo.php: |
#       <?php
#       ...
```

## Notes for a fair article

1. Pin exact model IDs from `list-models` (lists evolve).
2. Use the same fixture Laravel version for the whole batch.
3. Prefer sequential runs (`parallel_models: false`) for cleaner timing.
4. Re-run flaky tasks; report pass rate + median duration, not a single lucky shot.
5. SDK usage is billed like IDE/Cloud agent runs — start with `--task` / `--model` smoke tests.

## Layout

```
benchmarks/laravel-models/
  run.py
  config.example.yaml
  requirements.txt
  tasks/*.yaml
  src/{tasks,fixture,runner,scoring}.py
  fixtures/laravel-app/   # generated
  .work/                  # generated per run
  results/                # generated reports
```
