# Filament (mockup reference)

Visual contract for **admin/control panel** mockups when the PRD chose **Filament**.

## Source of truth

| Resource | URL |
| --- | --- |
| **Figma — Design System** | [Design System](https://www.figma.com/community/file/1413822581847485668/filament-3-design-system) (Giovanni Zanin, CC BY 4.0) |
| **Figma — UI Kit (Free)** | [UI Kit (Free)](https://www.figma.com/community/file/1417716904167561805/filament-3-free) (VhiWEB community) |
| **Merge index** | `figma-sources.md` in this folder |
| **Filament docs** | [Themes & colors](https://filamentphp.com/docs/panels/themes) |
| **Live reference** | [Filament demo](https://demo.filamentphp.com/) |

Both Figma files are unofficial community kits replicated from the Filament demo. Larapilot merges them into one reference — see `figma-sources.md` for which kit owns which frames. When in doubt, prefer the demo and Filament docs.

## When Elise must use this

Apply this design system when **all** of the following are true:

1. The screen is an **admin/control panel** (dashboard, CRUD list, form, settings, auth inside the panel).
2. The PRD `## Technical Architecture` records **Filament** as the panel choice (or `Application Info` shows Filament installed and the PRD already committed to it).

Do **not** use this for public marketing pages, storefronts, or custom panels — those follow the Nordic minimal language in shared-runtime unless the PRD says otherwise.

## Mockup implementation

1. **Link tokens** — in every Filament admin mockup HTML, include:

   ```html
   <link rel="stylesheet" href="filament-tokens.css">
   ```

   Copy `tokens.css` from this folder into the mockup directory as `filament-tokens.css` (or reference it with a relative path to `.larapilot/design-systems/filament/tokens.css` if served from the mockup route).

2. **Font** — load **Inter** (Filament default):

   ```html
   <link rel="preconnect" href="https://fonts.bunny.net">
   <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
   ```

3. **Layout** — follow `components.md` for shell (sidebar + topbar), page header, sections, tables, forms, actions, and empty states. **Starting points:** copy or adapt screens from `{paths.design_systems}/filament/html/` (catalog at `index.html`).

4. **Colors** — use CSS variables from `tokens.css`. Default semantic palette matches Filament out of the box:

   | Token | Tailwind base | Usage |
   | --- | --- | --- |
   | `primary` | Amber | Primary buttons, active nav, links |
   | `success` | Green | Success badges, notifications |
   | `warning` | Amber | Warnings |
   | `danger` | Red | Destructive actions, errors |
   | `info` | Blue | Info badges, hints |
   | `gray` | Zinc | Surfaces, borders, secondary text |

   If the PRD or client materials specify a custom Filament theme (e.g. primary = Indigo), override `--color-primary-*` in the mockup README and document the hex/RGB values Alex will register in the Panel provider.

5. **Dark mode** — Filament supports system/light/dark. Mockups must show **light** as primary and at least one key admin screen in **dark** (`class="dark"` on `<html>` or a toggle). Verify contrast in both modes.

6. **Mobile** — Filament panels collapse the sidebar on small viewports. Admin mockups still follow Mobile First: document collapsed sidebar (drawer/hamburger), stacked form fields, and horizontal scroll only for data tables (`overflow-x-auto`).

## README contract (mockup folder)

When using this design system, the mockup `README.md` must include:

- **Design system:** Filament — link to this folder, `figma-sources.md`, and both Figma files
- **Panel screens** mapped to Filament concepts (Resource list, Create/Edit form, Relation manager, Widget dashboard, Settings page)
- **Theme tokens** — primary color overrides if any
- **Responsive & navigation** — sidebar collapse behavior, breakpoints
- **Accessibility** — focus rings, form labels, table headers (standard Filament patterns satisfy most WCAG AA checks; note any custom widgets)

## Downstream

- **Alex** implements with Filament Resources, Pages, Widgets, and Actions — mockups are layout/flow references, not pixel-perfect HTML to paste.
- **Anne** tests at mobile + desktop widths with the panel auth flow in mind.
