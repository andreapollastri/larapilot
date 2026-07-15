# Legacy Project

Place here snapshots of the **legacy system** to rewrite, port, or migrate from.

Larapilot treats this folder as the **parity contract** — no feature or data loss unless explicitly scoped out in the PRD.

## Suggested contents

- Legacy codebase copy or submodule reference (`SOURCE.md` with repo URL + commit/tag)
- Database schema dumps or ERD exports (sanitized — no production secrets)
- Sample exports of critical tables (CSV/JSON)
- Migration notes: known quirks, cron jobs, integrations, report logic
- Screenshots or screen recordings of critical flows
- Exported media/uploads inventory (paths, sizes, formats)
- `.env.example` from the legacy app (redacted)

## Workflow

1. **Inception** maps legacy scope; **Sabrine** leads analysis (including **content scraping** and **DB/assets porting** plans) and asks clarifying questions.
2. **Spec** creates parity and migration specs from Sabrine's legacy inventory.
3. **Plan / implement** verify behavior, content, and data against materials here; **Sabrine** checks review parity.

## Improvements

Upgrades (UX, performance, security, stack) are welcome — but **never** as a reason to drop legacy features or data.
