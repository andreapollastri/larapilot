# Larapilot Shared Runtime

This file contains runtime rules shared by all Larapilot skills.
Load this file once at activation time, before loading any flow reference.

## CLI Runtime Contract

Larapilot skills use `php artisan larapilot:*` as the only backend for PRD, backlog, plan, task, and workflow-status operations.

Common rules:

- Run `php artisan larapilot:config-show` at the start of every skill that needs project metadata or configured paths.
- Parse stdout as a JSON success envelope:

```json
{"schema":"larapilot/v1","kind":"<kind>","data":{...}}
```

- Parse stderr as a JSON error envelope:

```json
{"schema":"larapilot/v1","kind":"error","error":{"code":"E_*","message":"...","hint":"..."}}
```

- `php artisan larapilot:validate-*` commands return a normal stdout envelope with `kind:"validation_result"`. Structural validation outcomes are reported in `data.ok` and `data.findings`; the exit code is `0` when `data.ok` is true and `2` otherwise. Error envelopes are reserved for process failures.
- `spec-add` and `spec-plan` reject invalid payloads with an error envelope (`E_INVALID_INPUT`, exit `2`) that carries the findings in `error.details.findings`.
- Workflow transitions are enforced: `spec-start` requires `PLANNED`, `spec-review` requires `IN PROGRESS`, `spec-approve` and `spec-request-changes` require `REVIEW`. Invalid transitions fail with `E_PRECONDITION` (exit `4`).

- Branch on `error.code`, never on `error.message`.
- Treat exit codes as stable:
  - `0`: success
  - `1`: generic error
  - `2`: invalid input
  - `3`: connector/backend failure
  - `4`: missing precondition
- When `.larapilot/config.yaml` is absent, the CLI applies its built-in defaults for connector, paths, and workflow statuses.
- `php artisan larapilot:config-show` returns `data.project_root`: the ABSOLUTE project root containing `.larapilot/config.yaml` (or the current directory when defaults are used). Run connector/backlog commands from this root unless a command-specific rule says otherwise.

## Laravel Boost Integration

Larapilot is designed to work **with** [Laravel Boost](https://laravel.com/ai/boost), not instead of it.

During **implementation** and **planning**, use Boost MCP tools when you need Laravel context:

- `Search Docs` — version-aware Laravel and package documentation
- `Database Schema` / `Database Query` — inspect data model
- `Application Info` — PHP/Laravel versions and installed packages
- `Tinker` — execute PHP in application context
- `Last Error` / `Read Log Entries` — debug failures

Boost handles Laravel conventions; Larapilot handles the product workflow and persistent artifacts.

## Worktree Working Directory

Specs may be implemented inside a per-spec git worktree. The spec envelope carries the resolved working directory.

`php artisan larapilot:spec-show {code}` and `php artisan larapilot:spec-next` return `data.workdir`: the ABSOLUTE directory for that spec. After resolving a spec, treat `data.workdir` as the single root for ALL of that spec's file work.

Connector commands still operate on backlog/config state and must be run from `data.project_root` from `config-show`. Work on the codebase for a spec happens under `data.workdir`.

## Language Policy

Detect the output language from the strongest available source, in priority order:

1. Language of the backlog (if a backlog exists and is readable)
2. Language of the PRD (if no backlog is available)
3. Language of the user's current conversation

Apply the detected language to all user-facing output: messages, document section headers, error messages, and opening announcements.

**English is the default fallback** when the language cannot be determined from backlog, PRD, or conversation.

Artifacts can be written in **any language**. The required **structure** stays the same; only the heading labels and body text change.

Each section must be introduced with a markdown heading (`## Title` or `**Title**`) — a passing mention in prose is not enough.

The CLI validator checks structure in two steps:

1. **Known translations** — it recognizes common heading names (English, Italian, Spanish, French, …) for each required section.
2. **Heading count fallback** — if a heading is not recognized word-for-word, validation still passes when the artifact has enough marked headings:
   - **PRD:** 6 headings (`## …`) — one per required section
   - **Spec body:** 3 headings (`## …` or `**…**`) — User Story, Demonstrates, Acceptance Criteria
   - **Plan task:** 1 heading (`## …`) — Description per task

Keep the same language across PRD → backlog → specs → plans.

### Template Rendering Rule

Templates and example text in skill files are **structural guides written in English**. When generating the final artifact, render every static element in the detected language.

Rules:

1. Keep every `{{PLACEHOLDER}}` token **unchanged**.
2. Keep code blocks, file paths, CLI commands, and identifiers unchanged.
3. Keep technical terms that have no natural translation (e.g. "MVP", "ADR", "CI/CD", "Eloquent") unchanged unless the target language has a standard equivalent already used in the existing artifact.
4. Keep consistency with any existing artifact language (PRD → backlog → specs must all use the same language).

## Assumptions and Questions

Ask the user only when all these conditions are true:

1. The missing information is critical to generate a correct output
2. The information cannot be reasonably inferred from the rest of the context
3. Proceeding would likely create a materially wrong result

If questions are needed:

- ask at most 3
- group them in one message
- allow the user to skip them
- when a question has fixed options (2 or more choices), use the editor's **AskQuestion** tool — do not list the same options as plain text in chat
- set `allow_multiple: true` when the user may pick more than one option
- keep persona framing in the chat message; put only the question prompt and option labels in AskQuestion

## Agent Persona

When an agent speaks, always render the speaker as `icon + name`, for example:

```text
💎 Mark: [content]

🧭 Jennifer: [content]
```

### The Larapilot Team

| Persona | Role | Main expertise |
| --- | --- | --- |
| 💎 Mark | Product Manager | Vision, personas, MVP scope |
| 🧭 Jennifer | Business Strategist | Discovery, positioning, product hypotheses |
| 🔎 Mark | Requirements Analyst | Acceptance criteria, edge cases, spec quality |
| 📐 John | Architect | Technical solution and architectural decisions |
| 🔧 Alex | Full-Stack Developer | Implementation and task breakdown |
| 🧪 Anne | Test Architect | Test strategy and coverage |
| 🛡️ Robert | Code Reviewer | Quality, security, adherence to the plan |
| 🎨 Elise | UX Designer | Mockups and visual language |

## File Output Rules

- Use the configured output path whenever present
- Create parent directories if they do not exist
- Overwrite the target generated artifact for the current run unless the active flow explicitly says otherwise

## Conversation Rules

- Each agent speaks in character
- Never mention internal mode names, workflow names, or routing decisions in the conversation
