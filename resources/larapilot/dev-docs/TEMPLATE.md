# {Domain name}

> Copy this file to `{domain-slug}.md` and fill every section. Delete the
> instructional blockquotes as you go. English only. Keep it current with the
> code — update it in the same spec that changes the behavior.

| | |
| --- | --- |
| **Status** | Active \| Deprecated \| Planned |
| **Introduced by** | `US-XXX` |
| **Last updated by** | `US-XXX` — YYYY-MM-DD |
| **Owner persona** | 📐 John \| 🗄️ Mike \| 🔧 Alex \| ✨ Joe \| 🔗 Matt |

## Purpose

> What this domain is responsible for, in two or three sentences, in business
> terms. What breaks for the user if it stops working.

## Functional flow

> How it behaves at runtime, step by step: the entry point (route, command,
> job, webhook, event), what happens in order, what the outcome is, and what
> happens on the failure paths. A Mermaid diagram is welcome when the flow
> branches; prose alone is fine when it does not.

## Technical design

> The moving parts, so a reader can find them without grepping blind.

| Piece | Where | Role |
| --- | --- | --- |
| Model / table | `app/Models/…`, `…_table` migration | |
| Service / Action | `app/…` | |
| Controller / route | `routes/…` | |
| Job / event / listener | `app/Jobs/…` | |
| Command | `php artisan …` | |
| Config keys | `config/…` | |
| Tests | `tests/…` | |

> Then the parts a table cannot carry: the data model and its relations, the
> transaction boundaries, what is queued and why, the external services it
> talks to, the indexes that matter.

## Architectural choices

> What was chosen, **what was rejected, and why**. One entry per decision that a
> future maintainer might otherwise undo by accident.

| Choice | Alternatives considered | Why this one |
| --- | --- | --- |
| | | |

## Key decisions & invariants

> The rules that make the system work — the things that must stay true. State
> each one as a fact plus its consequence if violated. Reference
> `.larapilot/decisions.yaml` ids when the decision was logged there.

- **Invariant —** … _Breaks if:_ …

## Extension points & gotchas

> Where to plug new behavior in, and the traps: the ordering that looks
> arbitrary but is not, the nullable column that carries meaning, the retry that
> must stay idempotent, the known limitation.

## Related

- Specs: `US-XXX`
- Plans: `.larapilot/plans/US-XXX-plan.yaml`
- Review: `.larapilot/docs/review/US-XXX.md`
- Other domains: `other-domain.md`
