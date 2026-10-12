# Tailwind CSS (Larapilot design system `tailwind`)

Mockups for a **Laravel app styled with Tailwind CSS v4** (v4.3 targeted) and no component kit on top: a
custom marketing site, a custom dashboard, Blade + Livewire or Inertia screens built from utilities. The
sample product is **Tidewater**, subscription analytics for SaaS teams (public site + app), used by the
fictional customer workspace **Lumenfield**.

Use it when the PRD's `## Technical Architecture` records **Tailwind CSS** for the screens in scope.
Don't use it when the screens are a **Filament** panel, a **Laravel Starter Kit** page (Flux / shadcn), a
**Nova** panel or a **Bootstrap** app: those have their own folders. If the client brings no brand and no
stack preference for a public page, Larapilot's house system `nordic-saas/` is the default instead.

## Real utilities, packaged subset

A Tailwind mockup is written in **real Tailwind v4 class names**, exactly as Alex will type them in Blade:
`rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500`. Nothing is
renamed, nothing is wrapped in a `.tw-*` component class.

What makes it render without Tailwind is `tokens.css`: the slice of Tailwind's **own compiled output** that
the packaged screens and `components.md` use, plus their obvious siblings (spacing steps, the status colour
set, responsive display utilities …). It was produced by the tailwindcss 4.3.3 compiler and then trimmed
and sectioned by hand, so every declaration is Tailwind's: `--spacing: 0.25rem`, `.mt-6 { margin-top:
calc(var(--spacing) * 6) }`, `--color-indigo-600: oklch(51.1% 0.262 276.966)`, `.shadow-xs`, `.inset-ring`,
`.hover\:bg-gray-50:hover` inside `@media (hover: hover)` and so on. No CDN, no Node, no build, no
JavaScript: a mockup opened from disk or inside the Larapilot dashboard's sandboxed iframe looks the same
for everyone.

`tokens.css` (≈ 120 KB, budget 170 KB) is laid out like Tailwind's own output:

| Section | Content |
| --- | --- |
| 1 Layer order | `@layer properties, theme, base, components, utilities;` (same cascade as Tailwind) |
| 2 Theme | `@theme` variables: fonts, **every shade** of each colour family used (gray, indigo, sky, green, yellow, red), spacing, breakpoints, containers, type scale, radius, shadows, easing |
| 3 Dark tokens | none: Tailwind keeps one palette; dark mode is the `dark:` variant |
| 4 Property defaults | initial values of the `--tw-*` properties (`--tw-border-style: solid`, `--tw-shadow: 0 0 #0000` …) |
| 5 Preflight | Tailwind's base reset |
| 6–10 Utilities | plain utilities, state variants, responsive `sm md lg xl`, `dark:` keyed to `html.dark`, `dark:` keyed to the OS |
| 12–14 Helpers | `.mock-*` drawn fields, `.visually-hidden`, reduced motion |

A screen therefore needs a class to be **in the file**. Every class used by `html/*.html` and by the
snippets in `components.md` is there; the Larapilot checker verifies it.

## How a mockup uses it

1. Copy `tokens.css` into the mockup folder as **`tailwind-tokens.css`** and link it as the only stylesheet:
   `<link rel="stylesheet" href="tailwind-tokens.css">`.
2. Put the marker class on `<body>`: `<body class="tw-mockup h-full">`. The dashboard recognises the system
   from it.
3. Start the `<html>` like the packaged screens: `<html lang="en" class="h-full scheme-light bg-gray-50
   antialiased dark:scheme-dark dark:bg-gray-950">` (app) or `bg-white … dark:bg-gray-900` (marketing).
4. Start from the closest screen in `html/` (catalog: `html/index.html`) and the patterns in
   `components.md`. Copy class lists rather than inventing new ones.
5. Draw every state statically: open dropdown, modal over the page, toast, error message, selected rows.
   No `<script>`. Sign-in screens draw the credentials (`components.md` § 20).
6. Icons: inline Heroicons (24 px outline in navigation, 20 px mini in buttons and inputs, 16 px micro in
   badges) with `aria-hidden="true"`. Charts: inline SVG (`components.md` § 19).

## Adding a missing utility

If a mockup needs a class that is not in `tokens.css` (the checker lists it), add it to the copied
`tailwind-tokens.css`, never as inline style. Write it the way Tailwind writes it; the value comes from
<https://tailwindcss.com/docs> (each utility page shows the generated CSS):

```css
/* section 6, inside @layer utilities */
.mt-7 { margin-top: calc(var(--spacing) * 7); }
.text-teal-700 { color: var(--color-teal-700); }          /* + add the 11 --color-teal-* variables to section 2 */
.bg-indigo-600\/15 { background-color: color-mix(in oklab, var(--color-indigo-600) 15%, transparent); }

/* section 7: a state variant */
@media (hover: hover) { .hover\:bg-indigo-700:hover { background-color: var(--color-indigo-700); } }
.focus\:ring-2:focus { --tw-ring-shadow: var(--tw-ring-inset,) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color, currentcolor); box-shadow: var(--tw-inset-shadow), var(--tw-inset-ring-shadow), var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow); }

/* section 8: responsive, inside the matching media block */
@media (width >= 48rem) { .md\:grid-cols-5 { grid-template-columns: repeat(5, minmax(0, 1fr)); } }

/* sections 9 AND 10: a dark variant is written twice */
.dark\:bg-teal-950:where(html.dark, html.dark *) { background-color: var(--color-teal-950); }
@media (prefers-color-scheme: dark) { .dark\:bg-teal-950:where(html:not(.light), html:not(.light) *) { background-color: var(--color-teal-950); } }
```

Escape `:` `/` `.` `[` `]` in selectors with a backslash. Keep the section order: a responsive rule must come
after the plain one, a `dark:` rule after both. If you have Node at hand you can also regenerate the subset
with the real compiler: `npx @tailwindcss/cli -i in.css -o out.css` where `in.css` holds
`@import "tailwindcss" source(none); @source inline("…classes…");` and the dark variant below.

## Dark mode

Every screen follows the OS (`prefers-color-scheme`) unless `<html class="light">` pins it light; `<html
class="dark">` forces dark (`html/dashboard-dark.html`). In the real app the same behaviour is one variant
in `resources/css/app.css`:

```css
@import "tailwindcss";
@custom-variant dark {
  &:where(html.dark, html.dark *) { @slot; }
  @media (prefers-color-scheme: dark) { &:where(html:not(.light), html:not(.light) *) { @slot; } }
}
@theme { --font-sans: InterVariable, Inter, ui-sans-serif, system-ui, sans-serif; }
```

Tidewater's dark look: page `gray-950`, sidebar, top bar and cards `gray-900`, hairlines `white/10`, text
`white` / `gray-400`, primary `indigo-500` (buttons) and `indigo-400` (links, chart line).

## Fonts

Tidewater uses **Inter** (rsms.me/inter, OFL), the Tailwind Plus default. The mockup loads no web font:
`--font-sans` lists `InterVariable, Inter` first, so a machine with Inter installed shows it and everyone
else gets the system stack Tailwind 4.3 ships. In the app Alex loads Inter (`@fontsource-variable/inter`
or the Bunny Fonts link) and keeps the `@theme` line above.

## Recolour

The primary is the `indigo` family. For a client brand, pick the closest Tailwind family (or define a custom
`--color-brand-50 … 950` set in `@theme`), add its 11 variables to section 2 of the copied file, replace
`indigo-` with the new family name in the mockup, then add the utilities the checker reports as missing.
Neutral stays `gray` unless the brand calls for `slate`, `zinc` or `stone`.

## Screens (`html/`)

| File | What it shows |
| --- | --- |
| `index.html` | Catalog: every screen with one line on when to start from it, indigo and gray swatches |
| `landing.html` | Public site: header, hero with an app screenshot, logo cloud, feature grid, cohort split section, stats, pricing tiers with a frequency toggle, testimonial, FAQ, CTA panel, footer |
| `dashboard.html` | App shell, KPI stats with trends, SVG area chart with tooltip, MRR movements, events table |
| `dashboard-dark.html` | The dashboard with `<html class="dark">` |
| `resource-list.html` | Customers: breadcrumb, status tabs, search, filter buttons with applied-filter chips, bulk action bar, sortable header, selection, status badges, row actions, pagination |
| `resource-form.html` | Edit customer: error summary, two-column sections, required marks, add-ons, a disabled field, a field in error, select, textarea, logo, upload drop zone with a file, checkboxes, toggles, sticky actions |
| `settings.html` | Account settings: tabs, avatar, a drawn sign-in email, radio group, toggle card, two-factor status, sessions, a disabled destructive action with its reason |
| `login.html` | Split sign-in with drawn credentials, remember me, SSO and passkey, brand panel with a metric card |
| `empty-state.html` | Reports with nothing scheduled: dashed empty state and template starting points |
| `components.html` | Every pattern of `components.md` in its variants and states |

## Fidelity notes

- **Values are Tailwind's.** The CSS comes from the tailwindcss 4.3.3 compiler; only presentation changed:
  colour-mix fallbacks for old browsers were removed (the `oklab` branch is kept), `@property`
  registrations became one plain defaults block (same initial values), and rules were grouped per section.
- **The look is Tailwind Plus style, not Tailwind Plus code.** Tailwind Plus (formerly Tailwind UI) is a
  paid product; the screens follow its public conventions (inset rings, `text-sm/6`, `outline-1
  -outline-offset-1` inputs, `gap-px` stat grids) with markup written for Tidewater.
- **Interactivity is drawn.** Menus, the modal, the toast and selected rows are static. In the app they are
  Alpine.js / Livewire state (`x-show`, `wire:model`), with the same classes.
- **Placeholders are `gray-500`** (Tailwind Plus uses `gray-400`) to keep WCAG AA contrast on white.
- **Images are drawn**: avatars are initials, logos are SVG marks with a text wordmark, the hero screenshot is
  HTML. Replace them with real assets in the app.

## Mockup → real code

| In the mockup | In the app |
| --- | --- |
| `tailwind-tokens.css` | `resources/css/app.css` with `@import "tailwindcss"`, the `@custom-variant dark` and `@theme` lines above, compiled by Vite |
| A repeated class list (button, badge, input) | A Blade component (`<x-button variant="primary">`) that holds the same class list |
| Drawn open dropdown / modal / toast | Alpine `x-data` + `x-show` (or Livewire state) around the same markup |
| `mock-field` sign-in fields | Real `<input>` with the input classes of `components.md` § 5 |
| Heroicons inline SVG | `blade-ui-kit/blade-heroicons` (`<x-heroicon-o-home class="size-6" />`) or the same inline SVG |
| SVG chart | The same SVG rendered from data, or a chart library styled with the same tokens |

## Do / don't

- **Do** pair every light colour with its `dark:` counterpart in the same class attribute.
- **Do** keep numbers `tabular-nums` and right-aligned in tables.
- **Do** keep `aria-current="page"` on the active nav item and `aria-label` on icon-only buttons.
- **Don't** add `<style>` blocks or inline styles for reusable things: add the utility to the tokens file.
- **Don't** use arbitrary values (`w-[37rem]`) when a theme step exists.
- **Don't** load the Tailwind CDN, a web font or remote images in a mockup.
