# BOLD California — Larapilot design system

An **original** Larapilot design system (v1.0, October 2026) for a public website **and** its back office. Sun-drenched,
optimistic, confident: West-Coast direct-to-consumer brands and tech launches. Heavy display type with tight tracking,
oversized numbers, a saturated warm palette — sunset orange, poppy red, golden yellow, Pacific teal, ocean blue, palm green,
hibiscus pink — on warm off-white sand, generous radii, pill buttons, ink-outlined "sticker" shadows, a running ticker and flat
illustrations drawn in inline SVG. Dark mode is a **warm night** (cocoa-plum canvas, glowing hues), never plain gray.

The sample product is **Sunday Surf Co.**, a surf school with rentals and a small shop at Moonlight Beach, Encinitas,
California: the public site (lessons, gear, reviews, pricing) and **Sunday HQ**, the staff app (bookings, classes, crew,
quiver, the 5:30 am weather call). The same people and numbers appear on every screen: 42 bookings today, $52.4k revenue
Oct 1–15, 31 of 48 boards out, 4.9 stars from 2,318 reviews, owner Kai Nakamura, coaches Leilani Torres, Mateo Reyes,
Priya Shah, Ama Owusu, Jonah Whitfield.

## When to use it

- Consumer brands that sell an **experience or a lifestyle** and want to feel sunny and fearless: surf, skate, bike and yoga
  studios, outdoor schools and rentals, camps, cafés and juice bars, festivals, travel and tour operators, DTC products
  (apparel, sunscreen, drinks), community sports clubs, kids' activities, food trucks, creator shops.
- Launch pages and their admin: a marketing site plus a booking, ordering or membership back office that the same small team
  runs from a phone on the beach.
- Teams that want the back office to carry the brand (the stat tiles, ticker and stickers make HQ feel like *their* tool).

**Not** for: finance, health records, legal, government or enterprise B2B where calm and density beat personality (use
`nordic-saas`, `shadcn` or `filament`); dense data tools with 15-column tables; brands whose identity is muted or luxury.
If the PRD names a framework look (Filament, Nova, Material), use that system instead.

## How a mockup uses it

1. Copy `tokens.css` next to the mockup as **`bold-california-tokens.css`** and link it — the only stylesheet. No CDN, no
   web font, no script.
2. `<body class="bc-mockup bc-body">` (`bc-mockup` is the marker the Larapilot dashboard uses to detect the system).
   - Back office: `.bc-app` › `aside.bc-sidebar` + `.bc-main` › `header.bc-topbar` + `main` (copy from `html/dashboard.html`).
   - Public site: `.bc-site` › `p.bc-announce` + `header.bc-sitenav` + `main` + `footer.bc-footer` (copy from `html/landing.html`).
   The current nav item gets `.is-active` **and** `aria-current="page"`.
3. Compose with the classes in `components.md`; start from the closest screen below and change the data. Never invent CSS:
   if something is missing, combine utilities (`.bc-grid--12` + `.bc-span-*`, `.bc-row`, `.bc-stack`, `.bc-mt-*`).
4. **Dark mode** follows the OS (`prefers-color-scheme`); `<html class="dark">` (or `data-theme="dark"`) forces the warm
   night, `<html class="light">` opts out. `dashboard-dark.html` is the forced sample.
5. Interactive states are drawn statically: open menu = `.bc-menu` inside `.bc-dropdown` with `aria-expanded="true"`; hover =
   `.is-hover`; focus = `.is-focus`; pressed = `.is-pressed`; modal = `.bc-backdrop` + `.bc-modal-wrap`; toast = `.bc-toasts`.
6. Sign-in screens draw the credentials with `.mock-label` / `.mock-field` (`--secret`, `--focus`, `--error`, `--empty`): no
   `<form>`, no text or password inputs, the submit is an `<a role="button">`. Other forms may use real inputs.

## Screens (`html/`)

| File | What it shows | Start from it for |
| --- | --- | --- |
| `index.html` | Catalog: every screen, palette strip | — |
| `landing.html` | Hero with drawn booker and stickers, tilted ticker, class cards, big numbers, steps, gear, reviews, photo grid, newsletter, footer | any public page |
| `pricing.html` | Plans with a featured sunset card, comparison table, FAQ (`<details>`), CTA | plans, packs, memberships |
| `dashboard.html` | Shell, live ticker, stat tiles, revenue bars, swell widget, schedule, crew, latest bookings, donut | app home, reports |
| `dashboard-dark.html` | The dashboard forced into the night theme | checking dark |
| `resource-list.html` | Bookings: tabs with counts, search, chips, sort, 3 selected + bulk bar, open row menu, badges, pagination | any index |
| `resource-form.html` | Class editor: numbered sections, required marks, one error, disabled field, choice cards, switch, drop zone, sticky actions, live preview | create / edit |
| `settings.html` | Sub-nav, business profile, switch rows, theme cards, checkboxes, danger zone, toast | settings, preferences |
| `empty-state.html` | Illustrated first run, three compact empty states, a loud alert | zero-data pages |
| `login.html` | Split sign-in (sunset panel), drawn fields, reset and two-factor cards | auth pages |
| `components.html` | Every component in its variants and states | reference |

## Building it for real

**Fonts.** Load **Inter Display** (headlines, 800–900) and **Inter** (UI, 400–800) — both SIL OFL, from rsms.me/inter or
self-hosted via `@fontsource/inter` — and optionally **JetBrains Mono** for IDs. The stacks in `tokens.css` already name them
first; without them SF Pro / Segoe UI stand in, which is what the mockups show.

**Blade + Tailwind v4.** Copy section 1–2 of `tokens.css` into `resources/css/app.css` and expose them to Tailwind with
`@theme` (e.g. `--color-sunset: var(--bc-primary); --color-sand: var(--bc-canvas); --radius-pill: 999px;
--font-display: var(--bc-font-display);`), keep the dark block under `@custom-variant dark (&:where(.dark, .dark *))` plus the
media query. Build Blade components that own the class strings — `<x-bc.button variant="primary">`, `<x-bc.badge tone>`,
`<x-bc.card variant>`, `<x-bc.stat>`, `<x-bc.table>`, `<x-bc.field>` — so the sticker shadow (`shadow-[3px_3px_0_var(--bc-shadow-ink)]`)
and pill shapes live in one place. Status tones come from enums (`BookingStatus::Confirmed->tone() === 'palm'`).
Alternatively keep the `bc-` classes as a component layer (`@layer components`) and use Tailwind only for layout.

**Livewire.** Tables are Livewire components (`WithPagination`, `#[Url]` for search, tabs and chips; `wire:model.live` on the
search; selected IDs in an array that shows `.bc-bulkbar` when non-empty). Forms use Livewire form objects; `@error('capacity')`
adds `is-error`, `aria-invalid` and the `.bc-error` line. Menus, modals and toasts are Alpine (`x-data`, `x-show`, `x-trap`) or
Flux components restyled with these tokens. The ticker is a Blade component fed by a service (cache it). Charts: render the
same SVG server-side, or use a chart library with the palette from `--bc-*`.

## Accessibility notes for the bold choices

- **Text on colour is ink, not white.** Ink on sunset 6.3:1, on gold 11.1:1, on Pacific 5.8:1. White appears only on the
  deep-sea gradient (≥ 5.9:1) and the light-theme danger button (5.6:1). Hue *text* uses the 700 shades (sunset-700 on sand
  5.3:1, Pacific-700 5.5:1, palm-700 6.0:1). The full table is in `sources.md`.
- **Sticker shadows are decoration**: focus is a separate 3 px ocean-blue outline with 3 px offset, visible on orange, sand and
  night. Form controls have a 2 px border at ≥ 3.6:1 against the surface (WCAG 1.4.11).
- **Display type**: 900 weight with −0.055 em tracking only at ≥ 36 px; body text stays Inter 15 px / 1.5. Headlines are real
  `<h1>`–`<h3>`, ticker copy is duplicated text with the copy `aria-hidden`, and the ticker stops under `prefers-reduced-motion`.
- **Stickers rotate** a few degrees — never more than ±5°, and never on body copy or form labels.
- **Colour is never the only signal**: badges carry words, selected rows have a checkbox and an edge, charts have labels and an
  `aria-label` with the takeaway.
- Icons are `aria-hidden="true"`; icon-only buttons have `aria-label`; illustrations with meaning use `role="img"`.

## Fidelity notes

This is an original system, so the mockups *are* the reference. What a browser shows offline is approximated: Inter is
replaced by the OS UI font; photographs are flat illustrations; the ticker animates but screenshots freeze it mid-way; native
`<select>`, `type="time"` and `type="number"` controls keep their platform details inside the drawn border.

## Do / don't

- **Do** keep one primary (sunset sticker) button per view, one display headline per screen, one coloured card per row.
- **Do** use oversized numbers for the 3–4 metrics that matter, and say the exact value in the meta line.
- **Do** write warm, short copy ("The rack is empty. Let's fill it.").
- **Don't** put sticker shadows on everything — the effect dies after three.
- **Don't** mix in `fi-`, `nv-` or Bootstrap classes, or swap the sand canvas for white.
- **Don't** set white text on orange, gold, teal or green, and don't use gradients outside the four places listed in components.md.
