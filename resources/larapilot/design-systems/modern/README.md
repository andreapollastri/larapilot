# Modern — Larapilot design system

An **original** design system (class prefix `mo-`) for products that want the look of 2025–2026 software at its best:
crisp neutrals, one electric violet accent, hairline borders with a soft inner highlight, translucent surfaces,
tight type, bento layouts, a command palette and keyboard hints everywhere. Dark-first, with a light theme designed
with the same care. It covers both a **backoffice** (app shell, tables, forms, settings) and a **public website**
(landing, pricing). Sample product: **Pulseboard**, a product-analytics SaaS used by Acme Inc. — the same people,
numbers and dates on every screen (48,312 people, 8.2M of 10M events, Growth plan at €249).

Inspirations and principles are in `sources.md`; every component, with snippets, is in `components.md`.

## When to use it

- The PRD asks for a **modern / contemporary / "like Linear, Vercel, Raycast"** look and the client brings no brand.
- Developer tools, analytics, SaaS dashboards, AI products, internal tools with power users (dense data, shortcuts).
- A product that needs a marketing site and an app that feel like one product.

Don't use it when the PRD records a framework look (Filament, Nova, Bootstrap, Material — use those folders), for
editorial / lifestyle / retail brands (see `eclectic/`), or for audiences that expect a conservative, form-heavy UI.

## How a mockup uses it

1. Copy `tokens.css` next to the mockup as **`modern-tokens.css`** and link it — it is the only stylesheet.
   No CDN, no web font, no script.
2. Body: `<body class="mo-mockup mo-body">` for app screens, `<body class="mo-mockup mo-body mo-body--site">` for public
   pages. `mo-mockup` is the marker the Larapilot dashboard uses to recognise the system — keep it on every screen.
3. App shell: `.mo-app` › `nav.mo-sidebar` + `main.mo-main` › `header.mo-topbar` + `.mo-page` (copy it from
   `html/dashboard.html`). The current nav item gets `.is-active` and `aria-current="page"`.
4. Compose with the classes in `components.md`; start from the closest screen below and change the data — never invent CSS.
5. Dark mode follows the OS. `<html class="dark">` forces dark, `<html class="light">` forces light.
6. Interactive states are drawn statically: open menu = `.mo-menu` inside `.mo-dropdown`; hover `.is-hover`; focus
   `.is-focus`; error `.is-invalid`; dialog or command palette inside `.mo-stage` (or `.mo-overlay` for a full-page layer);
   toasts in `.mo-toasts`.
7. Sign-in screens draw the credentials: `<span class="mock-label">` + `<div class="mock-field" role="img" aria-label="…">`,
   secret fields add `.mock-field--secret`; the submit is a link with `role="button"`. No `<form>`, no password input.
8. Charts are inline SVG with `preserveAspectRatio="none"` and non-scaling strokes inside `.mo-plot__area`; axis labels
   are HTML. Gradients come from the hidden `.mo-defs` SVG at the top of the dashboard (`#mo-grad-c1` …) — copy it too.

## Screens (`html/`)

| File | What it shows |
| --- | --- |
| `index.html` | Catalog: every screen, when to start from it, palette swatches |
| `dashboard.html` | Sidebar + topbar, filter chips, 4 KPI cards with sparklines, area chart with comparison and tooltip, bar list, funnel, retention heatmap, live events table |
| `dashboard-dark.html` | The dashboard forced dark (`html.dark`) |
| `resource-list.html` | People: saved views, search, filter chips, sort, 2 selected rows + floating bulk bar, row menu open, badges, activity minibars, pagination |
| `resource-form.html` | New funnel: sections, required marks, helper text, step builder with one error, disabled field, select, textarea, switches, checkbox, drop zone, live preview, sticky actions |
| `settings.html` | Settings sub-nav, workspace fields, members and roles, plan card with usage, invoices, API keys, danger zone, toast |
| `empty-state.html` | First-run alerts: illustration and actions, onboarding checklist, API snippet, "no results" state |
| `components.html` | Every component in its variants and states (21 sections) |
| `login.html` | Glass card on a glow: SSO and passkey links, drawn email and password |
| `landing.html` | Public home: glass header, gradient hero, HTML product shot, logo cloud, bento features, testimonials, pricing teaser, changelog, CTA, footer |
| `pricing.html` | Billing toggle, volume slider, 4 plans, comparison table, FAQ |

## Building it for real (Laravel)

- **Font**: load **Geist** and **Geist Mono** (SIL OFL, `npm i geist` or self-host the woff2) — they are first in the
  stacks. Inter is an acceptable second choice. Turn on `font-feature-settings: "cv11", "ss01"` for Inter.
- **Blade + Tailwind CSS v4**: copy sections 1–2 of `tokens.css` into `@theme` (`--color-mo-accent: …`, radii, shadows)
  and keep the dark block under `@custom-variant dark (&:where(.dark, .dark *))`. Turn each `mo-` block into a Blade
  component: `<x-mo.button variant="primary" kbd="N">`, `<x-mo.card>`, `<x-mo.stat>`, `<x-mo.badge tone="success">`,
  `<x-mo.chip>`, `<x-mo.table>`. Keep the class names as the component's root class so mockup and code stay greppable.
- **Livewire**: tables (`resource-list`) map to a Livewire component with `#[Url]` search, filters and sort; the bulk bar
  shows when `count($selected) > 0`; the command palette is one Livewire/Alpine modal opened on `⌘K` (`x-on:keydown.meta.k.window`).
- **Inertia (Vue/React)**: the same blocks become components; `cmdk` (React) or a headless combobox renders `.mo-cmd`;
  charts can be drawn with any SVG library as long as it uses the `--mo-c1…c4` tokens, 2 px lines and the 10% area wash.
- Glass needs `backdrop-filter`; `tokens.css` already falls back to solid surfaces where it is missing — keep that.

## Accessibility notes

- Text tokens pass WCAG AA on every surface in both themes (`--mo-text-3` is the lightest text: 4.7:1 on the hover
  surface in light, 4.9:1 in dark). The accent button is 5.9:1 (light) and 4.8:1 (dark) with white text.
- Status is never colour alone: badges carry a word, deltas an arrow, chart series a legend and direct labels.
- The chart palette order (violet, cyan, amber, pink) was validated for colour-vision deficiency in both themes.
- `:focus-visible` draws a 2 px accent ring; inputs show a 3 px ring. Skip link on every app screen.
- Icon-only buttons have `aria-label`; icons are `aria-hidden="true"`. Charts have a `role="img"` summary.
- Animations (live dot, caret, skeleton) stop under `prefers-reduced-motion`; `forced-colors` gets real borders.

## Do / don't

- **Do** keep one accent. Violet marks the primary action, the active item and series 1 — nothing else.
- **Do** use hairline borders and the inner highlight; shadows only for things that float (menus, dialogs, toasts).
- **Do** show a keyboard hint (`.mo-kbd`) next to frequent actions and in menus.
- **Don't** put glow on more than one card per view (`.mo-card--glow`), or gradient text on more than one line per page.
- **Don't** use colour for series 5+: fold them into "Other".
- **Don't** draw a password `<input>`; use `.mock-field--secret`.
