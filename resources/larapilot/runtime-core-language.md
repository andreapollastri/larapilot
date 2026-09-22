Section of the Larapilot runtime. Index: `.larapilot/shared-runtime.md`. Read this file with the editor file-read tool, never `cat`.

## Language Policy

Detect the output language from the strongest available source, in priority order:

1. Language of the backlog (if a backlog exists and is readable)
2. Language of the PRD (if no backlog is available)
3. Language of the user's current conversation

Apply the detected language to all user-facing output: messages, document section headers, error messages, and opening announcements. **English is the default fallback** when the language cannot be determined. Artifacts can be written in **any language**: the required **structure** stays the same; only heading labels and body text change. Keep the same language across PRD → backlog → specs → plans.

**One exception: developer domain docs.** Files under `paths.dev_docs` (default `.larapilot/docs/devs/`) are **always written in English**, whatever the detected language — they address whoever inherits the codebase, not the client. See `.larapilot/runtime-dev-docs.md`.

Each section must be introduced with a markdown heading (`## Title` or `**Title**`) — a passing mention in prose is not enough. The CLI validator checks structure in two steps:

1. **Known translations** — it recognizes common heading names (English, Italian, Spanish, French, …) for each required section.
2. **Heading count fallback** — if a heading is not recognized word-for-word, validation still passes when the artifact has enough marked headings: **PRD** 6 headings (`## …`); **spec body** 3 headings (`## …` or `**…**` — User Story, Demonstrates, Acceptance Criteria); **plan task** 1 heading (`## …` — Description per task).

### Template Rendering Rule

Templates and example text in skill files are **structural guides written in English**. When generating the final artifact, render every static element in the detected language:

1. Keep every `{{PLACEHOLDER}}` token **unchanged**.
2. Keep code blocks, file paths, CLI commands, and identifiers unchanged.
3. Keep technical terms with no natural translation (e.g. "MVP", "ADR", "CI/CD", "Eloquent") unchanged unless the target language has a standard equivalent already used in the existing artifact.
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

