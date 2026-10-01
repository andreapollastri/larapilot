# Handbook

This folder holds the project's **living handbook**: a technical and functional
manual of the whole product — overview, architecture, features, API, CLI and
jobs, frontend, operations, releases — with diagrams where they help. It is
written for anyone who needs to understand the project, not only for the
people who maintain the code.

It stays empty while `settings.project_docs` is `NO` (the default). To turn it on:

```bash
php artisan larapilot:settings-set --project-docs=YES
```

then run `/larapilot-project-docs`. 📝 Albert writes the chapters from the PRD,
the specs, the plans and the git history, replaces this file with the handbook
index, and from then on updates the affected chapter whenever a change lands.

Not to be confused with `../devs/`, the developer domain docs: engineering-only,
always English, and never optional.
