# Laravel Starter Kits (mockup reference)

Visual contract for **authenticated app UI** mockups when the PRD chose a **[Laravel Starter Kit](https://laravel.com/starter-kits)** variant (`livewire`, `react`, `vue`, or `svelte`).

## Source of truth

| Resource | URL |
| --- | --- |
| **Official overview** | [laravel.com/starter-kits](https://laravel.com/starter-kits) |
| **Docs** | [laravel.com/docs/starter-kits](https://laravel.com/docs/starter-kits) |
| **React kit** | [github.com/laravel/react-starter-kit](https://github.com/laravel/react-starter-kit) |
| **Vue kit** | [github.com/laravel/vue-starter-kit](https://github.com/laravel/vue-starter-kit) |
| **Svelte kit** | [github.com/laravel/svelte-starter-kit](https://github.com/laravel/svelte-starter-kit) |
| **Livewire kit** | [github.com/laravel/livewire-starter-kit](https://github.com/laravel/livewire-starter-kit) |
| **Variant index** | `sources.md` in this folder |

`tokens.css` mirrors the shadcn oklch variables from the official React starter kit `resources/css/app.css` (also used by Vue/Svelte). Livewire/Flux mockups reuse the same sidebar/surface tokens for visual parity.

## When Elise must use this

Apply this design system when **all** of the following are true:

1. The screen is **authenticated app UI** (dashboard, profile, settings, portal back-end, auth pages inside the kit shell).
2. The PRD `## Technical Architecture` records a **Starter Kit** variant (or `Application Info` shows the kit installed and the PRD already committed to it).

Do **not** use this for Filament admin panels or public marketing pages — those follow their own design systems in shared-runtime.

## Mockup implementation

1. **Link tokens** — in every Starter Kit mockup HTML, include:

   ```html
   <link rel="stylesheet" href="starter-kit-tokens.css">
   ```

   Copy `tokens.css` from this folder into the mockup directory as `starter-kit-tokens.css` (or reference `.larapilot/design-systems/starter-kit/tokens.css` from the mockup route).

2. **Font** — load **Instrument Sans** (all official kits):

   ```html
   <link rel="preconnect" href="https://fonts.bunny.net">
   <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
   ```

3. **Layout** — follow `components.md` for sidebar/header shell, auth layouts, settings, and forms. **Starting points:** copy or adapt screens from `{paths.design_systems}/starter-kit/html/` (catalog at `index.html`).

4. **Colors** — use CSS variables from `tokens.css`. Default palette matches the official kits:

   | Token | Light mode | Usage |
   | --- | --- | --- |
   | `primary` | Near-black neutral | Primary buttons, active emphasis |
   | `sidebar` | Off-white | Sidebar background |
   | `background` | White | Main canvas |
   | `muted-foreground` | Mid gray | Secondary text, breadcrumbs |
   | `destructive` | Red oklch | Delete, errors |
   | `border` | Light gray | Cards, inputs, dividers |

   Custom brand colors from the PRD override `:root` variables in the mockup README — Alex updates `resources/css/app.css` in the real app.

5. **Dark mode** — kits ship system/light/dark. Mockups must show **light** as primary and at least one key screen in **dark** (`class="dark"` on `<html>`). Verify contrast in both modes.

6. **Mobile** — sidebar collapses to a sheet/drawer on small viewports. Document hamburger trigger and stacked forms per shared-runtime Mobile First rules.

## README contract (mockup folder)

When using this design system, the mockup `README.md` must include:

- **Design system:** Laravel Starter Kit — variant (`livewire` / `react` / `vue` / `svelte`), link to this folder and `sources.md`
- **Layout variant:** sidebar (default), header, or inset/floating sidebar when relevant
- **Screens** mapped to kit routes/pages (dashboard, profile, settings, login)
- **Theme tokens** — primary/brand overrides if any
- **Responsive & navigation** — sidebar collapse, breakpoints
- **Accessibility** — focus rings, labels, sidebar `aria-label`, keyboard nav

## Downstream

- **Alex** implements with the kit's layouts and component library (Flux or shadcn) — mockups are layout/flow references, not pixel-perfect HTML to paste.
- **Anne** tests at mobile + desktop widths with Fortify auth flows in mind.
