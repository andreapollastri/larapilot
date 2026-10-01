# Larapilot Runtime — Upgrades

The protocol and the playbooks behind **`larapilot-laravel-upgrade`**, **`larapilot-php-upgrade`**, and **`larapilot-db-upgrade`**: how an upgrade is checked, planned, run step by step, verified, and reported, and what each version of Laravel, PHP, and each database engine changes. The skills keep the workflow; the detail is here, written once.

This file is an index for people, and for a session that cannot run `php artisan larapilot:context`. A skill reads the parts that command lists for it — never every part by default. Without the command: find the heading the skill cites in the table below and read that part, whole, with the editor file-read tool (**Read protocol**, `.larapilot/shared-runtime.md`).

| Part | Headings |
| --- | --- |
| `.larapilot/runtime-upgrade-1.md` | Upgrade Protocol (readiness, criticality levels, run modes, branch and baseline, one step at a time, gates, environments, report, rollback, follow-ups) |
| `.larapilot/runtime-upgrade-2.md` | Laravel Upgrades (the ladder, per-major notes, tools), Package Playbooks (Filament, Nova, Livewire, Inertia, first-party, Spatie, tests and quality, frontend) |
| `.larapilot/runtime-upgrade-3.md` | PHP Upgrades (per-version notes, where PHP is pinned, tools), Database Upgrades (engine and version playbooks, moving the data, rehearsal, cutover) |
