## Output Economy

Brevity applies to **chat, status messages, and CLI envelopes**. Persisted artifacts stay complete. Drop filler; keep decisions, risks, blockers, and next steps. This is **not** telegraphic or broken-English compression — stay professional in the detected language. The economy level of a skill, and the shape of its chat, are in that skill's **Output Economy** section.

### Global rules (every skill)

1. **No filler** — skip openers ("Sure!", "I'd be happy to…"), restating the user's request, and closing pleasantries unless the user asked for them.
2. **Persona labels stay** — each agent speaks in character with its `icon + name:` prefix; compress the body, not the speaker.
3. **AskQuestion unchanged** — persona intro in chat; options only in the tool. Never shorten question prompts at the cost of clarity.
4. **Artifacts stay formal** — PRD, backlog specs, plan bodies, task bodies, mockup READMEs, and launch reports keep full structure on disk. Do not paste those files back into chat.
5. **Quote exactly, do not replay** — code, file paths, commands, and error strings you cite are exact. Do not paste a JSON envelope, a diff, or a test run the command already returned.
6. **Read once, read the slice** — what this conversation already holds — a runtime file, an envelope, an artifact — is not read again, and a command is asked for the slice the step needs (**Read the slice, not the document**, CLI contract).
7. **Skip empty voices** — if a persona has nothing new to add in a round, do not speak for them.
8. **Context estimate (Zoey)** — one line at skill start and skill end (see below). Not optional.
9. **Language** — chat in the user's language; artifacts follow **Language Policy** (`runtime-core-language.md`); developer domain docs are always English.
10. **No routing talk** — never mention internal mode names, workflow names, or routing decisions in the conversation.

<!-- when: lucille=YES -->
**Usage log (Lucille)** — at skill end, once: the `usage_log` command of the `context` envelope (**Lucille**, Project Settings).
<!-- end -->

<!-- when: notifications=YES -->
**Notifications** — emit skill-level events via `larapilot:notify` (**Notifications**, Project Settings). Hard events from `task-done` / `spec-approve` fire automatically.
<!-- end -->

### Context estimate (Zoey — every skill)

Zoey posts **exactly one line** at skill **start** (after the `context` call and its reads) and again at skill **end** (success, handoff, or blocked). It is a rough loaded-context estimate, not provider billing tokens.

`🤖 Zoey: context ≈ {N}k · phase={start|end} · packs={files read, or —} · artifacts={comma-list or —} · effort={ECO|STANDARD|MAX}`

- **Start** — `N` is `data.runtime.tokens.total` of the `context` envelope, rounded to the nearest **0.5k**. Never count by hand what the envelope counted.
- **End** — the start figure plus what the run read since: artifacts (PRD slices, specs, plans, review notes) and on-demand files, at characters ÷ 4.
- **Start and end only.** Autopilot and batch skills: one start and one end for the batch, not per spec. One optional line after a large new batch of artifacts.
- **No methodology chatter, and never a blocker** — when a size is unclear, post a best-effort `≈` and continue.

<!-- when: view=full -->
### Levels by skill

The behavior behind each level is the **Output Economy** section of the skill, the one place it is written.

- **Clarity first** — **`larapilot-inception`**, **`larapilot-adopt`**
- **Moderate** — **`larapilot-feature`**, **`larapilot-bug`**, **`larapilot-prd`**, **`larapilot-spec`**, **`larapilot-design`**, **`larapilot-laravel-upgrade`**, **`larapilot-php-upgrade`**, **`larapilot-db-upgrade`**
- **Split** — **`larapilot-plan`**
- **High** — **`larapilot-triage`**, **`larapilot-aikido`**, **`larapilot-error`**, **`larapilot-vendor-check`**, **`larapilot-implement`**, **`larapilot-review`**, **`larapilot-settings`**, **`larapilot-usage`**, **`larapilot-schedule`**, **`larapilot-economics`**, **`larapilot-tracker`**, **`larapilot-backstage`**
- **Structured terse** — **`larapilot-ship`**
- **Minimal** — **`larapilot-autopilot`**
<!-- end -->

### Do not compress

- Legal, privacy, and compliance obligations (Violet)
- Security **NO-GO** rationale (Lars)
- Acceptance criteria and rework feedback
- Multi-option architecture comparisons when the user must choose (John)
- Anything that would hide a material risk or make AskQuestion ambiguous
