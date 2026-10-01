# Larapilot Runtime — Ops & Lifecycle

The lifecycle policies behind **`larapilot-feature`**, **`larapilot-bug`**, **`larapilot-prd`**, **`larapilot-ship`**, **`larapilot-usage`**, **`larapilot-schedule`**, **`larapilot-backstage`**, and **`larapilot-tracker`**. Each part has one audience: no skill reads them all. Logging a session to Lucille needs none of them — the command is in the `context` envelope.

This file is an index for people, and for a session that cannot run `php artisan larapilot:context`. A skill reads the parts that command lists for it — never every part by default. Without the command: find the heading the skill cites in the table below and read that part, whole, with the editor file-read tool (**Read protocol**, `.larapilot/shared-runtime.md`).

| Part | Headings |
| --- | --- |
| `.larapilot/runtime-ops-1.md` | PRD Living Document (when to update the PRD, per-skill PRD rules, PRD Revision History) |
| `.larapilot/runtime-ops-2.md` | Maintenance & Support, Red Team & Penetration Testing |
| `.larapilot/runtime-ops-3.md` | PRD Revision — kinds, identifier stability, impact on the backlog, procedure |
| `.larapilot/runtime-ops-4.md` | Usage Ledger & Schedule |
| `.larapilot/runtime-ops-5.md` | Developer Portal — Backstage |
| `.larapilot/runtime-ops-6.md` | Project Trackers — Linear, Asana, Jira, Trello, ClickUp, Monday |
