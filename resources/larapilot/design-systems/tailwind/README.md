# Tailwind CSS (mockup reference)

Visual contract for **marketing site** and **authenticated app** mockups when the PRD chose **Tailwind CSS** without Filament, Starter Kits, or Bootstrap.

## Source of truth

| Resource | URL |
| --- | --- |
| **Official docs** | [tailwindcss.com/docs](https://tailwindcss.com/docs) |
| **UI patterns** | [Tailwind UI](https://tailwindui.com/) (inspiration only — do not copy paid components verbatim) |
| **Variant index** | `sources.md` in this folder |

Screens use **pure Tailwind utility classes** loaded via the Tailwind CDN in each HTML file. `tokens.css` is optional — only for the catalog gallery helpers.

## When Elise must use this

Apply this design system when **all** of the following are true:

1. The screen uses **Tailwind CSS** as the styling approach — public marketing pages, custom dashboards, or app UI **without** Filament or a Laravel Starter Kit.
2. The PRD `## Technical Architecture` records **Tailwind** (and not Bootstrap 5, Filament, or a Starter Kit variant) for the relevant screens.

Do **not** use this for Filament admin panels or Starter Kit authenticated UI — those have dedicated design systems.

## Mockup implementation

1. **Load Tailwind CDN** — in every Tailwind mockup HTML:

   ```html
   <script src="https://cdn.tailwindcss.com"></script>
   <script>
     tailwind.config = {
       theme: {
         extend: {
           fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
           colors: { brand: { 50: '#eef2ff', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca' } }
         }
       }
     }
   </script>
   <link rel="preconnect" href="https://fonts.bunny.net">
   <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
   ```

2. **Utility-first** — build layouts with Tailwind classes directly in HTML (`flex`, `grid`, `rounded-lg`, `bg-white`, etc.). Avoid custom component CSS unless documenting a reusable Blade partial.

3. **Layout** — follow `components.md` for marketing and app shells. **Starting points:** copy or adapt screens from `{paths.design_systems}/tailwind/html/` (catalog at `index.html`).

4. **Colors** — default palette uses **slate** neutrals + **indigo** brand accent. Override `tailwind.config` in the mockup README when the PRD specifies custom brand colors — Alex mirrors in `tailwind.config.js`.

5. **Dark mode** — use `class="dark"` on `<html>` with `dark:` variants. Show **light** as primary and at least one key screen in **dark**.

6. **Mobile** — mobile-first utilities (`sm:`, `md:`, `lg:`); document sidebar drawer and stacked layouts in README.

## README contract (mockup folder)

When using this design system, the mockup `README.md` must include:

- **Design system:** Tailwind CSS — link to this folder
- **Screen types:** marketing vs app shell
- **Brand tokens** — `tailwind.config` overrides if any
- **Responsive & navigation** — breakpoints, mobile nav pattern
- **Accessibility** — focus rings (`focus-visible:ring`), labels, semantic landmarks

## Downstream

- **Alex** implements with project `tailwind.config.js` and Blade/Livewire/Vue components — mockups are layout/flow references.
- **Anne** tests responsive breakpoints and keyboard focus on interactive elements.
