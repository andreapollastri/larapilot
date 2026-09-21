# Developer Domain Docs

This folder is the **engineering explanation of the system**: one Markdown file per
domain, entity, or feature, describing how it actually works in the code — the
functional flow, the technical moving parts, the architectural choices that were
made, and the key decisions that keep it running.

It is written **for the people who will maintain this codebase**, not for the
client and not for the product owner. The client-facing documents live elsewhere
(`PRD.md`, `quote.md`, `_project_docs/`).

## Rules

1. **Always English.** Whatever language the PRD, the specs, or the conversation
   use, these files are English. They are read by whoever inherits the code.
2. **One file per domain / entity / feature**, `kebab-case.md` — `billing.md`,
   `user-authentication.md`, `webhook-ingestion.md`. An entity gets its own file
   only when it carries behavior beyond plain CRUD; otherwise it is a section
   inside its domain file.
3. **Always current.** The doc is updated in the **same spec** that changes the
   domain's behavior — never in a follow-up spec, never "later". A doc that
   describes code that no longer exists is a review finding.
4. **Explain the why, not just the what.** Anyone can read the code to learn what
   it does. These files exist for what the code cannot say: the alternatives that
   were rejected, the constraint that forced the shape, the invariant that will
   break if the next person "simplifies" it.
5. **No secrets, no machine-specific absolute paths.** These files are committed.
6. **Start from `TEMPLATE.md`.** The section skeleton is fixed so the files stay
   diffable and so a reader knows where to look.

## Index

Keep the table below current — it is the entry point for a new developer.

| Domain | What it covers | Last touched by |
| --- | --- | --- |
| _(none yet)_ | | |

## Who writes them

📝 **Albert** owns these files; 📐 **John** validates the architecture sections,
🗄️ **Mike** the data sections, 🛡️ **Robert** flags stale ones at review.
Full contract: `.larapilot/runtime-dev-docs.md`.
