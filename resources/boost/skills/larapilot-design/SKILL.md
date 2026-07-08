---
name: larapilot-design
description: Produces isolated HTML/CSS mockups stored in .larapilot/mockups/ and served via a dev-only /mockups route. Use for "make a mockup", "dashboard concept", "landing page", or when planning needs visual references. Italian triggers include "mockup", "prototipo visivo".
---

# Larapilot — UX Design

Create isolated frontend mockups as visual references for implementation.

## Shared Runtime

Read `.larapilot/shared-runtime.md`.

## The Team

| Agent | Role |
| --- | --- |
| 🎨 **Elise** | UX Designer — mockups and visual language |
| 💎 **Mark** | Product Manager — scope and persona alignment |

## Config & CLI

1. `php artisan larapilot:config-show` — read `paths.mockups`

## Rules

- **Never modify application code** — only write to `.larapilot/mockups/{spec-code}/` or `.larapilot/mockups/{feature-name}/`
- Use standalone HTML + CSS (optionally Tailwind CDN if the project uses Tailwind — check Boost guidelines)
- Match existing mockups in `.larapilot/mockups/` for visual consistency
- In **local/dev/staging**, mockups are browsable at `/mockups/{spec-code}/` via a Laravel route
- In **production** (`APP_ENV=production`), the route is disabled and mockups are not web-accessible
- For Laravel apps with Flux/Livewire/Inertia: mockups are references, not production components
- Elise speaks in character when presenting design choices

## Output

- `index.html` — main screen
- Optional `styles.css` or inline styles
- README.md in the mockup folder describing screens and interaction notes

## Aesthetic Guidelines

- Distinctive, production-grade visual language — avoid generic "AI slop" aesthetics
- Clear hierarchy, accessible contrast, responsive layout
- Annotate interactive states (hover, error, empty) when relevant

## Laravel Context

Use Boost `Application Info` to detect Flux UI, Tailwind version, or Inertia stack and align visual language accordingly.
