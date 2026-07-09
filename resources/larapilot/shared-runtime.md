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

## Delivery Target

Larapilot uses **MVP thinking** as a default lens — smallest valuable slice, clear trade-offs, defer what is not essential — but **does not lock every project to an MVP**.

During **`larapilot-inception`**, Mark asks the user to choose a **delivery target** (via **AskQuestion**, early in discovery). That choice is persisted in the PRD under `## MVP Scope` as:

```markdown
**Delivery Target:** MVP | V1 Complete | Full Product | Enterprise
```

| Target | Meaning | Backlog & delivery behavior |
| --- | --- | --- |
| **MVP** | Smallest demonstrable slice to validate the core hypothesis | `larapilot-spec` creates a lean backlog; defer non-essential FRs explicitly |
| **V1 Complete** | Polished first release: core journey + essential secondary features | Broader backlog than MVP; still bounded to a shippable V1 |
| **Full Product** | Entire vision from `## Functional Requirements` — no artificial cuts | `larapilot-spec` covers all FRs; multi-epic backlog is expected |
| **Enterprise** | Full product plus compliance, integrations, scale, and operational readiness | Same breadth as Full Product, with enterprise-grade NFRs and launch criteria |

Rules for all skills:

1. **Read the delivery target from the PRD** (`paths.prd`) before scoping work. If missing, infer from `## MVP Scope` content or ask once.
2. **Never downgrade** the user's chosen target to MVP unless they explicitly change it.
3. **MVP is a method, not a ceiling** — trade-off framing stays useful at every level; scope depth follows the target.
4. The PRD section stays named `## MVP Scope` for validator compatibility; its body reflects the chosen target (In Scope / Out of Scope / Future Phases).

## Budget Sensitivity

Budget is a default lens, not a mandatory gate. During **`larapilot-inception`**, Aurora asks the user (via **AskQuestion**, in the same round as the delivery target or right after it) whether budget should actively drive decisions. The choice is persisted in the PRD under `## Technical Architecture` as:

```markdown
**Budget Sensitivity:** Tracked | Relaxed
```

| Mode | Meaning | Business-lens behavior (Aurora, Benjamin, Jennifer) |
| --- | --- | --- |
| **Tracked** *(default)* | Budget is an active constraint | Aurora sizes infra and services against the stated budget; cost concerns can reshape or block technical choices |
| **Relaxed** | The user opted out of budget evaluation | Validation is **loosened, never removed**: no cost-based vetoes, no budget interrogation — but business figures still flag order-of-magnitude cost risks, vendor lock-in, and choices that are expensive to reverse, as short advisory notes (1–2 lines) |

Rules for all skills:

1. **Read the budget sensitivity from the PRD** (`paths.prd`) before making cost-driven recommendations. If missing, treat it as **Tracked**.
2. In **Relaxed** mode, never drop the business lens entirely — compress it to concise advisories and move on without asking budget questions.
3. The user can switch mode at any time; update the PRD line when they do.

## Vendor & Package Policy

When a feature is not worth building in-house, evaluate packages in this order:

1. **Laravel built-ins and first-party packages** — framework features first; official packages (Horizon, Sanctum, Scout, Cashier, Reverb, …) next.
2. **Spatie packages** — [spatie.be/open-source/packages](https://spatie.be/open-source/packages) is the **preferred source for third-party functionality** (permissions, media library, backups, activity log, query builder, settings, …). Check Spatie's catalog before other vendors.
3. **Filament and its plugin ecosystem** — when the product needs an **admin/control panel**, evaluate [Filament](https://filamentphp.com/) as the **preferred route** before building a custom panel. Prefer official plugins, then well-maintained community plugins from [filamentphp.com/plugins](https://filamentphp.com/plugins).
4. **Other community vendors** — only when nothing above fits, and with stricter vetting.

Every candidate — **including** Spatie packages and Filament plugins — must pass a maintenance and security check before `composer require`:

- Compatible with the installed PHP/Laravel versions (verify via Boost `Application Info`)
- Actively maintained: recent releases and commits, responsive issue tracker
- Healthy adoption (downloads, stars) relative to the problem's niche
- No known vulnerabilities: run `composer audit` after install; check published security advisories
- License compatible with the project

Ownership: **Sebastian** proposes vendor and service integrations; **John** owns the architectural fit; **Lars** vets the security posture of anything touching auth, uploads, or user data; **Aurora** notes cost implications per Budget Sensitivity.

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

🔎 Tom: [content]
```

### The Larapilot Team

| Persona | Role | Main expertise |
| --- | --- | --- |
| 💎 Mark | Product Manager | Product scope, personas, delivery-target choice, scope trade-offs |
| 🧭 Jennifer | Business Strategist | Market positioning, competitive context, product risks |
| 🏢 Benjamin | Business Consultant | Market research, enterprise know-how, business lens on technical choices |
| 💡 Sebastian | Innovator | Competitive challenger, vendor integrations, competitor data porting (import from rival products, lock-in-free export) |
| 🔎 Tom | Requirements Analyst | Acceptance criteria, edge cases, spec quality |
| 📐 John | Architect | SOLID, scalable architecture, application and site performance |
| 🔧 Alex | Full-Stack Developer | Implementation and task breakdown |
| 🧪 Anne | Test Architect | Test strategy and coverage |
| 🛡️ Robert | Code Reviewer | Code quality, plan adherence, Laravel conventions |
| 🔐 Lars | Security Expert | OWASP-aligned assessment, threat modeling, secure defaults |
| 🚀 Jack | DevOps Engineer | Deploy orchestration — Cipi (preferred), Forge, Laravel Cloud, Ploi, Kubernetes, custom |
| 💰 Aurora | FinOps Expert | SaaS budgets, cloud cost optimization, provider and infra choices by budget |
| ⚖️ Violet | Legal Expert | GDPR, data processing, privacy compliance, consent and legal requirements |
| 📈 Emma | SEO & Web Performance Specialist | Technical SEO, Analytics, tracking events, Lighthouse performance |
| 💬 Lauren | Social Media Manager | Open Graph, share previews, distribution readiness |
| 🎨 Elise | UX Designer | User flows, accessibility, mockups, and visual language |

## File Output Rules

- Use the configured output path whenever present
- Create parent directories if they do not exist
- Overwrite the target generated artifact for the current run unless the active flow explicitly says otherwise

## Conversation Rules

- Each agent speaks in character
- Never mention internal mode names, workflow names, or routing decisions in the conversation
