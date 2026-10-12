# New York — Larapilot design system

An **original** design system, not a copy of a framework, a newspaper or a transit authority. It covers a **public website**
and a **backoffice** with one vocabulary (`ny-` classes, one stylesheet). The sample product is **Mulberry & Grand**, a
fictional restaurant group with five downtown restaurants: the same venues, reservations, dishes, staff and dates (Monday
12 – Sunday 18 October 2026) appear on every screen.

**Personality.** The downtown newspaper crossed with the subway signage manual. Headlines and big numbers are set in a
**Didone** (high-contrast serif, Bodoni / Didot), large and tight; structure and wayfinding are **Helvetica signage bands**:
white on black, with the thin white stripe along the top, and round **line bullets** in subway colours, each carrying its
letter. The paper is **newsprint** `#F3EFE6`, the ink is near-black `#111111`, and there is **one accent: taxi yellow
`#F7B500`**. Newspaper furniture does the layout work: nameplate, folio line (date · edition · price), kickers, deks, bylines,
hairline and double rules, drop caps, pull quotes, columns. A few city props are used with restraint: the green street-name
sign, the Times Square "zipper" news ticker, the checker strip from the side of an old cab. Dark mode is the **night
edition**: the whole page goes black like the signage, and taxi yellow may finally be used as ink.

The five restaurants are five lines. Use exactly these, everywhere:

| Bullet | Restaurant | Address | Kind | Covers |
| --- | --- | --- | --- | --- |
| **A** blue (`.ny-bullet--a`) | Astor Oyster Room | 412 Lafayette St | Raw bar | 64 |
| **M** orange (`.ny-bullet--m`) | Mulberry Trattoria — the flagship | 156 Mulberry St | Red-sauce Italian | 90 |
| **G** green (`.ny-bullet--g`) | Grand Street Grill | 288 Grand St | Chophouse | 110 |
| **L** grey (`.ny-bullet--l`) | Ludlow Counter | 97 Ludlow St | All-day diner | 48 |
| **7** purple (`.ny-bullet--7`) | Seventh Avenue Supper Club | 61 Seventh Ave S | Cocktails and supper | 72 |

## When to use it

- Restaurants, bars and hospitality groups; hotels with a dining room; food halls and markets.
- Food and culture media, magazines, newsletters, city guides, critics' columns — anything that is *read* before it is used.
- Editorial brands, publishers, bookshops, galleries and theatres that want a page that looks printed, not templated.
- Events, festivals and venues with a programme, a map and a "tonight" board; real estate and neighbourhood sites with a
  city voice.
- A public site and its backoffice that should feel like the same object: the site is the front page, the admin is the
  production desk.

## When not to use it

- Dense enterprise backoffices (ERP, accounting, logistics) where long data-entry sessions matter more than voice: use
  `fluent/` or `nordic-saas/`.
- A client with its own palette and typefaces: New York is built around taxi yellow and a Didone; swapping both leaves only
  the scaffolding. Interview for a custom system instead.
- Products already committed to Filament, Nova, AdminLTE, Bootstrap, Tailwind or a Starter Kit recorded in the PRD — use
  those systems, their look is fixed.
- More than five categorical series: the line palette identifies five things (here, restaurants). A sixth series needs a
  palette this system deliberately does not have.

## How a mockup uses it

1. Copy `tokens.css` next to the mockup as **`new-york-tokens.css`** and link it once — the only stylesheet. No CDN, no web
   font, no script.
2. `<body class="ny-mockup ny-body">` (`ny-mockup` lets the Larapilot dashboard recognise the system; `ny-body` carries the
   base styles). Add `<a class="ny-skip" href="#main">Skip to content</a>` first.
3. **Backoffice shell** (copy from `html/dashboard.html`): `.ny-app` › `aside.ny-rail` (always black, with the signage stripe:
   `.ny-logo`, `nav.ny-rail__nav` of `.ny-nav-item`s grouped by `.ny-rail__group`, `__foot` with `__service`) +
   `header.ny-topbar` (`__folio` date / edition, `__search` › `.ny-search`, `__actions` › round `.ny-topbar__btn`s,
   `.ny-topbar__user`) + `main.ny-main` › `header.ny-pagehead.ny-dbl-bottom` and then content: a `.ny-grid` of `.ny-cell`s,
   `.ny-strip` + `.ny-toolbar` + `.ny-table-wrap`, or a `.ny-form` of `.ny-formsec` sections. The current nav item gets
   `.is-active` (taxi) + `aria-current="page"`. The rail is the same on every screen: Tonight, Reservations, Guests, Menus,
   86 board, Venues, Staff, Settings.
   **Public shell** (copy from `html/landing.html`): `.ny-site` › `.ny-zipper` + `header.ny-sitehead` + `.ny-masthead`
   (nameplate between ears, folio under double rules) + `main` of `.ny-sec` sections (`.ny-sechead` under a double rule) +
   `footer.ny-sitefoot.ny-night`.
4. Compose with the classes in `components.md`. Start from the closest screen below and change the data — do not invent CSS.
   Photos are drawn: copy an SVG from `html/components.html` and change its `aria-label`; when a chart uses hatched bars the
   page needs the hidden `svg.ny-defs` block with `#ny-hatch` once.
5. Dark mode: the OS decides; `<html class="dark">` forces the night edition, `<html class="light">` opts out.
6. Interactive states are drawn statically: `.is-hover`, `.is-focus`, `.is-pressed`, `.is-selected`, `.is-now`; open menu =
   `.ny-menu` inside `.ny-pop`, modal = `.ny-overlay` › `.ny-dialog`, toast = `.ny-toasts`.
7. Sign-in screens draw the credentials with `.mock-label` + `.mock-field` (`role="img"`, `aria-label`), never a password
   input and never a `<form>`.

## Screens (`html/`)

| File | What it shows |
| --- | --- |
| `index.html` | Catalog: palette and line colours, type specimen (Didone, signage, text serif), every screen with when to start from it |
| `landing.html` | Public front page — zipper, masthead with nameplate and folio, hero with reservation call, the five restaurants as lines, platform signs, the house story with drop cap and pull quote, press, map, newsletter, footer |
| `menu.html` | Public menu (the carte) of Mulberry Trattoria — courses between double rules, dotted price leaders, signature and 86'd dishes, dietary marks, sticky reservation panel with party size, time slots and hours |
| `error-404.html` | Public 404 — oversized Didone code with a bullet for the zero, a "service change" notice, ways back as a numbered list |
| `dashboard.html` | Backoffice "Tonight" — covers and revenue stats (one taxi cell), service status per restaurant, covers by restaurant chart, Tonight's book timeline from 5:00 PM, the 86 board, staff on the floor, activity |
| `dashboard-dark.html` | The same overview forced dark (`<html class="dark">`): the night edition |
| `resource-list.html` | Reservations — section strip with counts, search, filter chips by restaurant, sort, selection + bulk bar, open row menu, status tags, pager |
| `resource-form.html` | Reservation / private-dining editor — numbered sections, required marks, error + summary, disabled field, selects, party-size segmented control, check grid, switches, drop zone, sticky form bar |
| `modal.html` | Reservations with the New reservation dialog (signage head) over the dimmed page |
| `settings.html` | Group settings — index nav, forms, venues and staff tables, switches, danger zone, toast |
| `empty-state.html` | Venues with no private-dining rooms yet (drawn plate, Didone title, next step) + compact empty cells |
| `login.html` | Sign in — black signage panel (Didone headline, the five bullets, night photo, bands), drawn email / password, keep me signed in, SSO |
| `components.html` | Every component in its variants and states |

## Fidelity notes

- **Fonts are system stacks.** The mockups load nothing. On a stock Mac the display face renders in **Didot** or **Bodoni 72**,
  the signage in **Helvetica Neue**, the text serif in **Iowan Old Style** or **Charter**, mono in **SF Mono** / **Menlo** —
  close to the intended look. On Windows the Didone falls back to **Bodoni MT** (if Office installed it) or **Times New Roman**,
  the sans to **Arial**: the layout holds, the headlines lose their hairlines. Judge the look on a Mac or with the real fonts
  installed.
- **Photos are drawings.** Duotone SVG scenes in fixed ink, grey and taxi tones; they do not flip in dark mode, like real
  photographs.
- **Line colours are restaurant codes.** They borrow the subway palette for recognition; the letters are the restaurants'
  initials, not routes, and no real map, logo or route sign is reproduced.

## Night edition (dark mode)

Canvas `#121211`, surfaces `#1A1A18`, ink `#F1ECE1`, structural rules soften to `#8E877A` so a full white grid does not glare.
`--ny-accent-text` and `--ny-accent-edge` switch to taxi, so "accent text", focus rings and accent edges turn yellow; alarm
lightens to `#FF7B6E` and good to `#6FD4A0`, and solid alarm fills take black text. The A line, too dark on black, draws charts
in `#4D7FE0`. The rail, signage bands, dialog heads and toasts were black already, so they stay put: in the night edition the
whole page simply becomes the platform.

## Building it for real (Blade + Tailwind v4, or Livewire)

- **Tokens → Tailwind v4 `@theme`.** Copy section 1 of `tokens.css` into `resources/css/app.css` as theme variables:
  `--color-ink: #111111; --color-newsprint: #F3EFE6; --color-sheet: #FBF9F4; --color-taxi: #F7B500; --color-street: #0B6B4A;`
  `--color-line-a: #0039A6; --color-line-m: #FF6319; --color-line-g: #6CBE45; --color-line-l: #A7A9AC; --color-line-7: #B933AD;`
  `--color-grey-100 … -900; --color-alarm: #B3261E; --font-display: "Bodoni Moda", …; --font-sans: "Helvetica Neue", …;`
  `--font-serif: "Source Serif 4", …; --font-mono: "IBM Plex Mono", …; --radius-sm: 2px; --radius-sign: 4px;`. Keep the
  semantic layer (`--ny-bg`, `--ny-ink`, `--ny-rule`, `--ny-accent-text` …) as plain CSS variables and switch them under
  `.dark` / `@media (prefers-color-scheme: dark)` exactly as section 2 does; use them through utilities such as
  `bg-(--ny-bg)` or `border-(--ny-rule)`.
- **Blade components.** One component per block: `<x-ny.bullet line="m" />`, `<x-ny.band size="lg" arrow="right">`,
  `<x-ny.button variant="taxi" icon="plus">`, `<x-ny.tag :status="$reservation->status" />`,
  `<x-ny.service-status :venue="$venue" />`, `<x-ny.cell span="3" title="Covers tonight">`, `<x-ny.stat value="412" of="474" />`,
  `<x-ny.table>` / `<x-ny.pager :paginator="$reservations" />`, `<x-ny.field label="Party size" required :error="$errors->first('party')">`,
  `<x-ny.timeline :venues="$venues" :bookings="$tonight" />`, `<x-ny.dish :dish="$dish" />`. A `Venue` model owns its `line`
  letter and the bullet reads it, so colour and letter can never drift apart. The class names in `components.md` are the
  contract; components only add props.
- **Livewire.** Bulk selection = `wire:model="selected"` on the row checkboxes and an `@if(count($selected))` around
  `.ny-bulkbar`; filters = `wire:model.live` on the search and `.ny-chip--applied` rendered from the active filter array; the
  86 board = `wire:poll.30s`; modals = a `$showDialog` flag around `.ny-overlay`.
- **Fonts.** Display: **Bodoni Moda** (SIL OFL, optical sizes) or **Playfair Display** (OFL); Didot / Bodoni 72 cover Macs
  already. UI and signage: **Helvetica Neue** (Monotype, commercial — buy a web licence) or the free **Inter** (OFL). Long text:
  **Source Serif 4** (OFL). Mono: **IBM Plex Mono** (OFL). Self-host them with `@font-face`; the stacks already name them first,
  so a machine that has them shows them.
- **Icons.** Lucide (ISC): `npm i lucide-static` or the `mallardduck/blade-lucide-icons` package; stroke 1.75, `currentColor`.

## Accessibility notes for the bold choices

- **The Taxi rule.** Taxi on newsprint is **1.58:1** — invisible as text. In light mode taxi is only a surface under ink
  (**10.40:1**): the active nav item, a selected row, the one main button, a highlighter (`.ny-mark`), the live dot. Anything
  that wants "accent-coloured text" uses `--ny-accent-text`: ink in light, taxi in dark (**10.32:1** on `#121211`, **10.40:1**
  on the black bands). Taxi fills on paper carry an ink edge (`--ny-accent-edge`) so the shape is visible (WCAG 1.4.11). The
  taxi underline of `.ny-arrowlink`, `.ny-cell__link` and the crumbs is decoration: the link is told by its ink text, capitals
  and arrow.
- **The Line rule.** Line colours identify restaurants and chart series, never a status. A bullet always carries its letter
  (white on A **9.83:1** and 7 **5.07:1**, ink on M **6.34:1**, G **8.17:1**, L **8.02:1**), so colour is never the only cue.
  As chart strokes on newsprint, M, G and L fall under 3:1, so every line ends in its lettered disc (`.ny-ch-disc--*` +
  `.ny-ch-disctext`) and the chart's `aria-label` reads the numbers.
- **Status is text + shape.** Tags differ by fill (outline / solid / taxi / dashed / green / red) and carry a word, a dot or a
  ring; service status adds an icon; disabled controls turn dashed. Street green (`#0B6B4A`, **5.69:1**) is "good", red
  (`#B3261E`, **5.70:1**; `#FF7B6E` in the night edition, **7.42:1**) is "alarm", always with an icon and words.
- **Small caps labels** (kickers, folio, table heads) are 10.5–11 px bold uppercase with +0.1em tracking: for short structure
  only, never sentences. Body is 15 px, long reads 17 px in the text serif.
- **Focus** is a 3 px outline with a 2 px offset and a contrasting gap: ink + taxi gap in light, taxi + black gap on black and in
  the night edition. Never remove it.
- **Didone hairlines** thin out below ~20 px: Didone is for headlines, numbers and prices, never for labels or body text.
- **Motion.** The zipper scrolls; its duplicated loop is `aria-hidden` and `prefers-reduced-motion` stops it. Photos and maps
  are SVGs with `role="img"` and a sentence that describes them.

## Do / don't

- **Do** let newspaper rules do the work: a double rule under the page head and over section heads, hairlines between cells.
- **Do** put a bullet next to every restaurant name — in nav, tables, timelines, charts and filters.
- **Do** use one `.ny-btn--taxi` per view, for the action that matters (the active nav item and the site header's Reserve button
  are chrome and do not count).
- **Do** write dates and times the New York way: "Mon., Oct. 12", "7:30 PM", "$1,284", phone numbers in 212 / 646.
- **Do** keep signage bands for wayfinding — a section's name, a platform sign, a dialog title — not for decoration.
- **Don't** set taxi text, icons or thin taxi lines on newsprint.
- **Don't** use a line colour for a status (no "green = confirmed"), and never show a disc without its letter.
- **Don't** add a second accent, gradients or soft glows; the only shadow is the lift under menus, dialogs and toasts.
- **Don't** set labels, table cells or body copy in the Didone.
- **Don't** reproduce real logos, route signs, maps or mastheads; the system evokes them, it does not copy them.
- **Don't** draw a password `<input>` in a mockup; use `.mock-field--secret`.
