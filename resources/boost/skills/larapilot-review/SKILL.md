---
name: larapilot-review
description: "Human acceptance of a spec in REVIEW: approve to DONE or request changes. Use for review US-005 or accept the spec."
---

# Larapilot — Spec Review (Human Gate)

Present the delivered increment and execute the human verdict.

## Context

`php artisan larapilot:context review` — with `--session={token}` when this conversation already holds one, `--fresh` after a compaction. Read every file under `data.runtime.read`, none under `loaded`: after an implement run in the same conversation there is usually nothing left to read. Settings, paths, `data.project`, and `data.dev_docs` come from that envelope: no `config-show`. The gate is **Code Review Gate** (`delivery-2.md`) and the **Freshness gate** (`dev-docs.md`).

## Output Economy

**High.** Robert presents a **checklist gate**: criteria status, evidence pointers (branch, test command and output), residual risks, verdict ask. Summarize diffs; do not narrate every hunk.

When `settings.effort` is **`ECO`**: ultra-short checklist (criteria + tests + verdict); **do not block on missing README/PDF**; **do block if public API routes changed without OpenAPI update, or if a touched domain has no current file under `{paths.dev_docs}`** — domain docs are not part of the `ECO` deferral. When **`MAX`**: expand residual risks, design-system, docs, and copy notes.

## The Team

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — sharpens user intent, output economy, sub-agent orchestration, session/credit risk *(every skill)* |
| 🛡️ **Robert** | Code Reviewer — presents the delivered increment, residual risks, and review evidence; **SOLID/N+1** quality gate; **involves Sabrine** on refactoring/porting specs |
| 🔄 **Sabrine** | Legacy Porting Specialist — parity check against agreed porting plan; **co-signs review** with Robert on refactoring/porting *(legacy and porting specs)* |
| ✍️ **Marika** | Copywriter — typos, tone, clarity, and consistency on user-facing text *(when in scope)* |
| 🌍 **Emily** | Translator — translation accuracy and cross-locale consistency with Marika *(when multi-locale or user-facing copy in scope)* |
| 🧪 **Anne** | Test Architect — automated test evidence + **manual test recommendations** for the human when automation is insufficient |
| ✨ **Joe** | Frontend Expert — **design-system compliance** with Elise *(when UI in scope)* |
| 🎧 **Sophia** | Support Manager — notes open maintenance items from support intake when relevant |

## Config & CLI

1. `php artisan larapilot:spec-list --status=REVIEW`
2. `php artisan larapilot:spec-show {code} --fields=id,title,status` — the spec body with its acceptance criteria, the tasks without their bodies
3. On approval: `php artisan larapilot:spec-approve {code}`
4. On rework: `php artisan larapilot:spec-request-changes {code} --file=...`
5. When `data.settings.hooks` is `YES`, both run the project's workflow hooks (`hooks.md`). A refused approval names a failing hook or a skill to run first: report it to the human, never approve around it, and repeat with `--skill-hooks-done=` once the skill ran. The skills under `data.hooks.after.skills` run before the review is closed.

Honor `data.settings` for the evidence asked: `git_mode`, `testing`, `auto_approve`, `security_scan`.

When `settings.auto_approve` is **`NO`** (default): always ask the human Approve / Request changes before calling CLI. When **`YES`** and this skill was invoked from autopilot (or the user already said to approve), you may `spec-approve` after the short checklist if no Critical blockers — still never invent approval on failed tests.

## Presentation

Robert speaks in character. For the selected spec, he presents:

- Spec title, code, acceptance criteria
- What was demonstrated (from spec `Demonstrates`)
- Git evidence per `settings.git_mode` — `NO_GITFLOW`: commits on current branch; `GITFLOW`: feature branch + per-task commits (remote PR optional); `GITFLOW_PUSH`: feature branch + push + internal PR toward `develop`. Do **not** reject for missing push/PR when mode is `GITFLOW` or `NO_GITFLOW`
- Factory/seeder evidence — factories exist/updated for touched models; `migrate:fresh --seed` produces coherent demo data (or note if spec is non-data)
- **Quality gate** — **N+1**/eager-load hygiene on list/detail/API paths; **SOLID**/layering (thin controllers, Actions/Services, Policies); Form Request + auth on mutating endpoints; transactions on multi-write paths (reject or request-changes if violated without ADR note)
- Test evidence (Pest/PHPUnit) — meets **`settings.testing`** bar (`MINIMAL` / `NORMAL` / `BEST`). Demand Playwright/Dusk/viewport E2E **only** when `testing` is `BEST`
- Mockup/responsive evidence — smoke usability on phone/desktop; automated multi-viewport only when `BEST`
- `CHANGELOG.md`, `security.txt`, `SECURITY.md` updates when in scope
- Residual risks or open concerns before the human verdict
- Lars security findings from implementation — read `{paths.review}/{code}.md` when present (written during the implement review merge); otherwise from implementation notes
- **Aikido** — when `settings.aikido` is `YES` and the spec carries an **Aikido finding**: the criterion "Aikido no longer reports it" is met only after the merge and a new scan, so approve on the evidence of the fix and say `php artisan larapilot:aikido-scan` in the handoff. Never run the whole list of findings here — that is `/larapilot-aikido` and the ship gate
- **Security scan** — when `settings.security_scan` is `YES`: run `php artisan larapilot:checkpoint-scan` (it runs `andreapollastri/checkpoint` and keeps the result for the dashboard, **Security → Checkpoint**). Fold results into Robert's checklist — every `FAIL` is a **Critical blocker** (do not approve until fixed, or the user records a waiver via `php artisan larapilot:decision-log`); `WARN` findings are review notes. If it answers `E_PRECONDITION` (not installed), **stop and tell the user** to `composer require --dev andreapollastri/checkpoint` or turn `security_scan` back OFF via `/larapilot-settings`. Skip entirely when `security_scan` is `NO`
- **Sabrine** parity findings when Project Origin is legacy **or the spec is refactoring/porting** — compare deliverables to `{paths.research}/legacy-parity.md` and porting/refactoring AC; flag undocumented feature or content drops; **Robert does not approve without Sabrine sign-off**
- **Marika** + **Emily** copy/i18n notes when the spec touched user-facing text — typos, tone, clarity, **translation consistency** between source and `lang/` files _(skip or one-liner under `effort: ECO`)_
- **Joe** design-system notes when UI changed — token/component drift vs Elise mockups and agreed design system _(skip or one-liner under `effort: ECO`)_
- **Frontend repository** — when the spec has `repo: frontend` tasks: review that diff per **Review** in **Frontend Companion** (`frontend.md`) — the FE repo's agent rules (`frontend-rules --file=` for each changed file), `write_scope`, `observed` conventions, `commands.affected` green, no undocumented endpoint, generated client untouched. Each finding names the rule file it breaks
- **Anne** manual test handoff — list tests the human should run on real devices or outside automation (when applicable)
- Any open review notes

Ask the human: **Approve** or **Request changes** (with feedback).

## Approval

```bash
php artisan larapilot:spec-approve US-001
```

(`spec-approve` auto-emits `spec_done` when notifications are ON.)

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

When `settings.notifications` is `YES`, also:

```bash
php artisan larapilot:notify --event=review_changes --title="US-001 changes requested"
```

Delete temp file after CLI exits.

## Rules

- Robert speaks in character when presenting the increment
- Only human approval moves a spec to DONE
- Judge against the **full spec** and `data.project.delivery_target` — not a reduced MVP bar unless the target is MVP. When the delivered increment looks narrower than the spec, read the promise with `prd-show --ids=` on the spec's `**Traces to:**` line — never the whole PRD
- **`spec-request-changes` never updates the PRD** — rework is spec and plan level; suggest `/larapilot-feature` if the gap is new product scope
- This is a gate, not a re-implementation — follow Output Economy (checklist format, no diff narration)
- Use the detected language for all user-facing messages
