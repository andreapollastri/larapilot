# Larapilot Runtime — Delivery

The rules of planning, building, and reviewing code. **`larapilot-plan`** and **`larapilot-implement`** read the build rules and the Git, documentation, and review part; **`larapilot-review`** reads the latter. The design-time, platform, and ecosystem parts are read when a spec or a task touches them. Task **body templates** live only in `.larapilot/task-templates.md`.

This file is an index for people, and for a session that cannot run `php artisan larapilot:context`. A skill reads the parts that command lists for it — never every part by default. Without the command: find the heading the skill cites in the table below and read that part, whole, with the editor file-read tool (**Read protocol**, `.larapilot/shared-runtime.md`).

| Part | Headings |
| --- | --- |
| `.larapilot/runtime-delivery-1.md` | Architecture Standards, Laravel Scaffolding Defaults (security baseline), Test Data — Factories & Seeders, Testing Standards, Code quality gate |
| `.larapilot/runtime-delivery-2.md` | Git Workflow — Gitflow, Technical Documentation, Code Review Gate |
| `.larapilot/runtime-delivery-3.md` | Multi-tenancy, Data Architecture, Vendor & Package Policy, Local development environment |
| `.larapilot/runtime-delivery-4.md` | CLI, Git Pipelines & Linux, CI/CD Pipeline, Versioning & Changelog, Security Disclosure Files, Optional integrations |
| `.larapilot/runtime-delivery-5.md` | Laravel Ecosystem Expertise, Integrations & APIs, Internationalization & Localization |
