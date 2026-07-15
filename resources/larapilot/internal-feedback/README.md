# Internal feedback

PM and developer comments for each user story, stored as append-only markdown:

```
.larapilot/internal-feedback/US-001.md
```

- Comments are allowed in every workflow phase until the spec reaches **DONE**.
- Use `[blocks-merge]` (via `--blocks-merge` or the dashboard checkbox) to flag review blockers.
- Promote blocking comments into formal rework feedback with:

```bash
php artisan larapilot:spec-request-changes US-001 --file=.larapilot/tmp-feedback.yaml --include-feedback
```

Disable comments globally with `LARAPILOT_COMMENTS_ENABLED=false`.
