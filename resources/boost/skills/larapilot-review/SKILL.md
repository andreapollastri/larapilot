---
name: larapilot-review
description: Facilitates human acceptance of a spec in REVIEW. Present deliverables and execute approve (DONE) or request-changes (back to TODO). Triggers include "review US-005", "accept the spec", "what's waiting for review".
---

# Larapilot — Spec Review (Human Gate)

Present the delivered increment and execute the human verdict.

## Shared Runtime

Read `.larapilot/shared-runtime.md`.

## The Team

| Agent | Role |
| --- | --- |
| 🛡️ **Robert** | Code Reviewer — presents the delivered increment, residual risks, and review evidence |

## Config & CLI

1. `php artisan larapilot:config-show`
2. `php artisan larapilot:spec-list --status=REVIEW`
3. `php artisan larapilot:spec-show {code}`
4. On approval: `php artisan larapilot:spec-approve {code}`
5. On rework: `php artisan larapilot:spec-request-changes {code} --file=...`

## Presentation

Robert speaks in character. For the selected spec, he presents:

- Spec title, code, acceptance criteria
- What was demonstrated (from spec `Demonstrates`)
- Git diff summary (if available)
- Test evidence (Pest/PHPUnit output)
- Residual risks or open concerns before the human verdict
- Lars security findings from implementation (if documented)
- Any open review notes

Ask the human: **Approve** or **Request changes** (with feedback).

## Approval

```bash
php artisan larapilot:spec-approve US-001
```

## Request Changes

Write feedback to `.larapilot/tmp-feedback-{code}.yaml`:

```yaml
markdown: |
  - file:line — comment
  - file:line — comment
```

Then:

```bash
php artisan larapilot:spec-request-changes US-001 --file=.larapilot/tmp-feedback-US-001.yaml
```

Delete temp file after CLI exits.

## Rules

- Robert speaks in character when presenting the increment
- Only human approval moves a spec to DONE
- Judge against the **full spec** and PRD delivery target — not a reduced MVP bar unless the PRD says MVP. Read `paths.prd` (from `config-show`) when the delivered increment looks narrower than the spec and you need to confirm that's actually the chosen target
- Be concise; this is a gate, not a re-implementation
- Use the detected language for all user-facing messages (see Language Policy in shared runtime)
