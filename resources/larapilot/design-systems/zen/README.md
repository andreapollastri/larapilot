# Zen — Larapilot design system

An **original** design system, not a copy of a framework or a brand. It covers a **public website** and a **backoffice** with one
vocabulary (`zn-` classes, one stylesheet). The sample product is **Kanso**, a fictional ceramics and tea studio in Kyoto's
Higashiyama district: hand-thrown bowls and tea ware sold online and in the shop, tea and wheel-throwing workshops of eight
seats each. The same owner (Aiko Mori), pieces, orders in yen and dates in October 2026 appear on every screen.

**Personality.** Silence. **Black and white only**: `#FFFFFF` paper, `#000000` ink and five greys that exist only for
secondary text, hairlines and control edges — no accent, no red or green, no gradient, no shadow, no blur, no grey surface.
**Ma** (間, the empty interval) is the main material: a strict 8 px scale, 64 px page margins, a 68ch measure, few elements per
screen. Headings are large and **light** (300) in one sans family; body text is small (14 px); the only uppercase voice is an
11 px label tracked at .14em. **Hairlines** of 1 px separate things, and a 2 px ink rule is kept for emphasis (the active nav
item, a selected row, an error, the active tab). Status is told by **shape and a word**, never by colour. There is **one
ornament**, the **ensō** — a single brush circle, open at one point — used sparingly: the logo mark, empty states, the 404 and
the sign-in side; the small "stone" dot is its only echo. Corners are square. **Dark mode is the same page inverted**: pure
black, white ink, the greys mirrored, as calm as the light one.

It is deliberately different from `nordic-saas/` (warm stone neutrals and a fjord-blue accent) and `berlin/` (Swiss grid,
acid signal colour, mono micro-labels): Zen has no colour at all and fewer elements than either.

## When to use it

- Studios and ateliers, craft and makers (ceramics, wood, textiles, leather), architecture and design portfolios, galleries
  and small museums, photographers.
- Wellness and quiet hospitality: yoga and meditation, onsen and ryokan, spas, tea rooms and specialty coffee.
- Shops of few, well-photographed products, where the product supplies the colour and the interface should step back.
- Any brand that asks for silence, restraint or "less", and a public site and its admin that should feel like one object.

## When not to use it

- **Dense data backoffices** where colour-coded status speeds the work: logistics, monitoring, trading, support queues,
  anything scanned for "red". Zen's shapes are legible but slower to scan than colour in a 200-row table — use `nordic-saas/`,
  `berlin/` or a panel system.
- **The client has a brand palette** that must appear in the product: Zen has no slot for it. Start from `nordic-saas/` or let
  `/larapilot-design` write the client's own system.
- **A Laravel panel or a CSS framework is recorded in the PRD** (Filament, Nova, a Starter Kit, Bootstrap, AdminLTE, Tailwind
  utilities…): that system wins, its look is fixed.
- Charts with more than three series: Zen has ink, hatch and grey, nothing else.

## How a mockup uses it

1. Copy `tokens.css` next to the mockup as **`zen-tokens.css`** and link it — the only stylesheet. No CDN, no web font,
   no script.
2. `<body class="zn-mockup zn-body">` (`zn-mockup` lets the Larapilot dashboard recognise the system; `zn-body` carries the base
   styles). Add `<a class="zn-skip" href="#main">Skip to content</a>` first.
3. **Backoffice shell** (copy it exactly from `html/dashboard.html`): `.zn-app` › `aside.zn-rail` (ensō logo + "Kanso",
   `nav.zn-rail__nav` of `.zn-nav-item`s — Today, Orders, Workshops, Pieces, Customers, Journal, a `.zn-rail__gap`, Settings —
   and `__foot` with Aiko Mori) + `header.zn-topbar` (date, search, one primary action) + `main.zn-main` › `.zn-pagehead` and
   then content: `.zn-stats`, a `.zn-grid` of `.zn-block`s, `.zn-toolbar` + `.zn-table-wrap`, or `.zn-formsec` sections. The
   current nav item gets `.is-active` + `aria-current="page"`.
   **Public shell** (copy from `html/landing.html`): `.zn-site` › `header.zn-sitehead` + `main` of `.zn-hero` and `.zn-sec`
   sections + `footer.zn-sitefoot`.
4. Compose with the classes in `components.md`. Start from the closest screen below and change the data — do not invent CSS.
   When a class is missing, compose from existing ones and the utilities; never write `style="…"` with a colour.
5. Photos are drawn: a flat grey SVG of bowls, cups, stones and tables inside `.zn-photo`, with the fixed `.zn-ph-*` fills.
   Charts with a hatched bar need the hidden `svg.zn-defs` block that defines `#zn-hatch` once per page.
6. Dark mode: the OS decides; `<html class="dark">` forces dark, `<html class="light">` opts out.
7. Interactive states are drawn statically: `.is-hover`, `.is-focus`, `.is-pressed`, `.is-selected`, `.is-disabled`; open menu =
   `.zn-menu` inside `.zn-pop`, modal = `.zn-overlay` › `.zn-dialog`, toast = `.zn-toasts`.
8. Sign-in screens draw the credentials with `.mock-label` + `.mock-field` (`role="img"`, `aria-label`), never a password input.

## Screens (`html/`)

| File | What it shows |
| --- | --- |
| `index.html` | Catalog: paper, ink and the grey ramp in both themes, the type specimen (light sans, the display serif, mono), every screen with when to start from it |
| `landing.html` | Public home of Kanso — a quiet hero, six pieces, tea and wheel workshops with their seats, the studio's story, visiting hours, the journal, the newsletter, the footer |
| `error-404.html` | Public 404 — the large ensō around the code, one serif line, one sentence, two ways back, inside the site header and footer |
| `dashboard.html` | Backoffice **Today** — four figures (orders, revenue in ¥, workshop seats as stones, pieces in stock), thirty days of revenue against the thirty before, today's workshops on a timeline with a "now" line, recent orders with status shapes, pieces running low |
| `dashboard-dark.html` | The same overview forced dark (`<html class="dark">`) |
| `resource-list.html` | **Orders** — a quiet toolbar with search and three filters, the inverted bulk bar for two selected orders, ten October orders with shape-based status, pagination |
| `resource-form.html` | **Order K-1047** being edited — customer, pieces with quantities, shipping with one postcode in error, a gift note, packing options, the invoice drop zone, a sticky Cancel / Save bar |
| `modal.html` | The **New order** dialog over the Orders page — customer, a piece picker, quantity, channel, a note, Cancel and Create, and a toast |
| `settings.html` | Studio settings — studio profile, shop currency and shipping zones, workshop defaults, notifications, the team of three, a quiet danger zone |
| `empty-state.html` | **Workshops** with nothing scheduled for winter — the ensō, one sentence, a primary action, and last winter's workshops below |
| `login.html` | Sign in — a black side with the ensō and one serif line; a white panel with drawn email and password, keep me signed in, and a passkey |
| `components.html` | Every component in its variants and states |

## Building it for real (Blade + Tailwind v4, or Livewire)

- **Tokens → Tailwind v4 `@theme`.** Clear the default palette first, so no colour can sneak in, then copy section 1 of
  `tokens.css`:

  ```css
  @import "tailwindcss";
  @theme {
    --color-*: initial; --shadow-*: initial; --radius-*: initial;
    --color-paper: #FFFFFF; --color-ink: #000000;
    --color-grey-100: #E6E6E6; --color-grey-300: #BDBDBD; --color-grey-500: #8C8C8C;
    --color-grey-600: #6B6B6B; --color-grey-700: #4D4D4D;
    --font-sans: "Inter", "Helvetica Neue", Helvetica, Arial, system-ui, sans-serif;
    --font-serif: "Iowan Old Style", "Hiragino Mincho ProN", "Yu Mincho", Georgia, serif;
    --font-mono: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
    --spacing: 4px; /* use multiples of 2 only: the scale is 8 px */
  }
  ```

  Keep the semantic layer (`--zn-bg`, `--zn-ink`, `--zn-ink-2`, `--zn-ink-3`, `--zn-ctl`, `--zn-line`, `--zn-line-2`) as plain
  CSS variables and flip them under `.dark` / `@media (prefers-color-scheme: dark)` exactly as section 2 does; use them through
  utilities such as `bg-(--zn-bg)`, `text-(--zn-ink-3)` or `border-(--zn-line)`. Never use `text-grey-300` or `-100` for text.
- **Blade components.** One component per block: `<x-zn.button variant="primary" size="lg" icon="plus">`,
  `<x-zn.status :state="$order->status" />` (enum → `done` / `pending` / `progress` / `cancelled` / `alert`),
  `<x-zn.stat label="Revenue, 30 days" value="2,390,700" unit="¥" />`, `<x-zn.block title="Recent orders" :link="route('orders.index')">`,
  `<x-zn.table>` / `<x-zn.pager :paginator="$orders" />`, `<x-zn.field label="Customer" required :error="$errors->first('customer')">`,
  `<x-zn.seats :taken="6" :total="8" />`, `<x-zn.enso size="128" />` (picks the bold cut up to 64 px, the regular one above),
  `<x-zn.photo kind="chawan" alt="…" />`. The class names in `components.md` are the contract; components only add props.
- **Livewire.** Bulk selection = `wire:model="selected"` on the row checkboxes and an `@if(count($selected))` around
  `.zn-bulkbar`; filters = `wire:model.live` on the search and `.zn-chip.is-active` rendered from the active filter;
  modals = a `$showDialog` flag around `.zn-overlay`; toasts from a session flash.
- **Fonts** (decided once in the `tokens.css` header). **Inter** (SIL OFL 1.1) is the one sans: self-host it with `@font-face`
  at 300, 400 and 500 (or the variable file). The display serif is used only by `.zn-display`, `.zn-hero__title`, `.zn-story`,
  `.zn-quote` and `.zn-auth__line`: the stack names system faces (Iowan Old Style and Hiragino Mincho ProN ship with Apple
  systems, Yu Mincho with Windows and macOS), which the app may not embed as web fonts; to load one, use **Shippori Mincho** or
  **Noto Serif JP** (both OFL) when Japanese text appears, **Source Serif 4** (OFL) for Latin only. Mono stays the system
  `ui-monospace` stack (no download); **JetBrains Mono** (OFL) if a brand wants one. The mockups load nothing: on a stock Mac
  they render in Helvetica Neue and Iowan Old Style, which keeps the intended character.
- **Icons.** Lucide (ISC): `npm i lucide-static` or the `mallardduck/blade-lucide-icons` package; stroke 1.5 (1.25 in the nav
  and at 32 px, 1 at 48 px), round caps, `currentColor`, `aria-hidden="true"`.

## Fidelity notes

- The ensō is one filled SVG path in two cuts (bold ≤ 64 px, regular above), copied verbatim from the `tokens.css` section 7
  comment. It suggests a brush stroke; it is not a scanned calligraphy. A real brand can replace both paths with its own
  hand-drawn circle at the same `viewBox="0 0 100 100"`.
- Photos are flat grey vector shapes (`.zn-ph-0…5`, `-k`, `-w`): they show where a picture goes and its proportion, not its
  light. They keep their greys in dark mode, like real photographs. Real product photography should be shot on white or
  black, with soft daylight and no props, or it will fight the system.
- Hover, focus and open states are drawn with state classes; motion is limited to 0.18 s colour transitions and a 3 px arrow
  nudge, all stopped by `prefers-reduced-motion`.
- Phones: the rail becomes a top bar with a menu button below 768 px; between 768 and 1199 px it collapses to a 72 px icon rail.

## Accessibility notes for the minimal choices

- **Status by shape, never by colour.** Filled dot ● = done / active, ring ○ = pending, half ◐ = in progress, cross × with
  strike = cancelled, diamond ◆ = needs attention — always followed by the word (`.zn-status`). The mark is `aria-hidden`; the
  word is the accessible name. Because there is no colour to lose, the rule holds for colour-blind users, grayscale print,
  e-ink and screen readers alike (WCAG 1.4.1 Use of Color). The half, the cross and the diamond are distinct silhouettes;
  the ring and the filled dot differ only by fill at 8 px, which is one more reason the word is mandatory.
- **Errors** are a 2 px ink rule on the left, an icon and a sentence (`.zn-error`, `.zn-errorsum`, `.zn-notice--error`), with
  `aria-invalid` and `aria-describedby` on the control (WCAG 3.3.1). An error is never just a thicker border.
- **Contrast** (measured, WCAG relative luminance; text ≥ 4.5:1 in both themes):

  | Token | Light on `#FFFFFF` | Dark on `#000000` | Use |
  | --- | --- | --- | --- |
  | `--zn-ink` | `#000000` **21.00:1** | `#FFFFFF` **21.00:1** | Text, rules, solid fills |
  | `--zn-ink-2` | `#4D4D4D` **8.45:1** | `#B3B3B3` **10.02:1** | Secondary text |
  | `--zn-ink-3` | `#6B6B6B` **5.33:1** | `#949494` **6.92:1** | Muted text, labels, helper text |
  | `--zn-ctl` | `#8C8C8C` **3.36:1** | `#767676` **4.62:1** | Control edges (non-text, ≥ 3:1, WCAG 1.4.11) |
  | `--zn-line-2` | `#BDBDBD` 1.88:1 | `#4A4A4A` 2.37:1 | Dashed and disabled edges, tags — **never text** |
  | `--zn-line` | `#E6E6E6` 1.25:1 | `#262626` 1.39:1 | Hairlines — **never text, never a control's only edge** |

  Inverted islands (`.zn-inv`, toast, tooltip, bulk bar, skip link) use the other theme's values, so the same ratios hold.
  Chips and tags with a `--zn-line-2` edge are identified by their words, not by the edge.
- **Focus** is a 2 px ink outline with a 2 px offset — black in light, white in dark, 21:1 against the page (WCAG 2.4.7,
  2.4.13). Never remove it; inputs also darken their border to ink.
- **Light weight only where it is large.** 300 is used at 20 px and above; body text is 400 at 14 px, helper text 12–13 px, and
  pure ink at 21:1 keeps thin strokes legible.
- **Uppercase only on 11 px labels** of a few words (`.zn-label` and its siblings), never for sentences or the only copy of
  important information.
- **The ensō and the stones are decoration**: always `aria-hidden="true"`. The 404 code, seat counts and empty-state titles are
  real text next to them; `.zn-seats` carries a `role="img"` with an `aria-label` such as "6 of 8 seats taken".
- Photos are SVGs with `role="img"` and a sentence describing the picture. Every control has a label; placeholders are never
  labels.

## Do / don't

- **Do** remove before adding: one primary button per view, one ensō per screen at most, a block only when it has a title.
- **Do** keep text to the 68ch measure and let the margins stay empty; align everything on the 8 px scale.
- **Do** say status with `.zn-status` (shape + word), and errors with the left rule + icon + sentence.
- **Do** keep numbers tabular (`.zn-tnum`, or the sans with tabular figures that tables already set) and prices as `¥18,700`.
- **Don't** add a colour — not for status, not for links, not for charts, not "just for the delete button".
- **Don't** add shadows, gradients, blur, rounded corners or grey surfaces; circles are only dots, stones, radios, avatars and
  the ensō.
- **Don't** set body text in 300 or in the serif; the serif is for rare, large lines on the public site and the sign-in side.
- **Don't** use `--zn-line` or `--zn-line-2` for text, or the ensō as a bullet, a loader or a background pattern.
- **Don't** draw a password `<input>` in a mockup; use `.mock-field--secret`.
