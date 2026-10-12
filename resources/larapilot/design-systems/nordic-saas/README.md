# Nordic SaaS (Larapilot's house design system)

Larapilot's own design system: the concrete form of the **Nordic minimal** default. Warm paper surfaces, a
stone neutral ramp, **one** fjord-blue accent, hairline borders, soft elevation, generous whitespace and a
calm type scale, in light and dark. It is original work (see `sources.md`), not a copy of a product.

The sample product is **Fjord**, invoicing for freelancers and small studios: a public site and an app used
by Ingrid Solberg of Solberg Studio (Bergen) to invoice clients such as Halden & Moe Arkitekter, Lumo
Interiors, Nordlys Festival and Kaskade Records.

## When to use it

- The client brings **no brand** and the PRD records no UI kit for the screens in scope: public site, app
  shell, dashboards, forms, auth.
- A first mockup round when the user asks for "something clean" and there is nothing to mirror yet.

Don't use it when a packaged system was chosen for those screens (Filament, Nova, AdminLTE, Starter Kit,
shadcn, Bootstrap, Tailwind, Material 3), when the client supplied brand materials, or for an adopted
project (mirror the as-is system instead). A later brand replaces the accent and the font; the rest holds.

## How a mockup uses it

1. Copy `tokens.css` into the mockup folder as **`nordic-saas-tokens.css`** and link it as the only stylesheet.
2. Put the marker on `<body>`: `class="ns-mockup"` (+ `ns-site` for public pages, `ns-auth` for sign-in).
3. Start from the closest screen in `html/` (`html/index.html` is the catalog) and compose with the `ns-`
   classes of `components.md`. Do not invent CSS; if something is missing, add a component to the copied
   tokens file in the same style (variables only, no raw colours).
4. Icons: Lucide, inline, `class="ns-icon"`, `aria-hidden="true"`. Charts: inline SVG (`components.md` § 22).
5. Draw every state statically: open menu, modal, toast, error, selected rows. No `<script>`. Sign-in
   screens draw credentials with `mock-field` (no form, no text input, no password type).
6. Light and dark come from the OS; `<html class="dark">` forces dark, `<html class="light">` forces light.

## Foundations

| Token group | Values |
| --- | --- |
| Neutral (stone) | `--ns-neutral-0 … 975`: `#fefdfb` paper, `#faf8f4` canvas, `#e2dfda` hairline, `#736e66` muted (AA), `#1b1916` ink |
| Accent (fjord blue) | `--ns-accent-50 … 950`; light primary `600 #0f6d82`, hover `700`; dark primary `400 #5babc2` |
| Status | moss `--ns-success-*`, ochre `--ns-warning-*`, brick `--ns-danger-*` (each 50/100/300/500/600/700) |
| Semantic roles | `--ns-canvas --ns-surface --ns-surface-raised --ns-surface-sunken --ns-line --ns-line-strong --ns-ink --ns-ink-2 --ns-muted --ns-accent --ns-accent-text --ns-focus` … components only read these |
| Type | Instrument Sans; 12 / 13 / 14 (app body) / 16 (site body) / 18 / 22 / 28 / 38 / 52 / 68 px; weights 400, 500, 600; headings −0.02 em, display −0.035 em; Newsreader-style serif for quotes and the hero accent |
| Space | 4 px grid, `--ns-space-1 … 32` |
| Radius | 4, 6, 8 (controls), 12 (cards), 16 (dialogs, plans), 24 (CTA panel), full |
| Elevation | hairline first; `--ns-shadow-xs … overlay`, deeper in dark |
| Focus | 2 px accent outline, offset 2 px; inputs get a 3 px accent ring |
| Layout | sidebar 15.5 rem, top bar 3.5 rem, content max 76 rem, site max 72 rem; breakpoints 640 / 1024 / 1280 px |

Dark mode swaps only the semantic roles (canvas `#110f0d`, sheet `#1b1916`, hairline `#2c2a26`, ink
`#f3f1ed`, muted `#a39d96`, accent `#5babc2`). The invoice document (`ns-doc`) stays paper-light on purpose.

## Fonts

The real face is **Instrument Sans** (OFL, the Laravel starter kits' font), with **Newsreader** (OFL) as the
serif for quotes and the italic accent in the hero. Mockups load no web font: the stacks list the real faces
first and fall back to the system sans and serif. In the app Alex loads them (Bunny Fonts or
`@fontsource`) and keeps the same variables.

## Recolour for a client

Change the eleven `--ns-accent-*` values to the brand's ramp (keep the lightness steps: 600 for light
buttons with white text at AA, 400 for dark mode with dark text). Optionally swap `--ns-font-sans`. Do not
add a second accent: status colours stay status colours.

## Screens (`html/`)

| File | What it shows |
| --- | --- |
| `index.html` | Catalog: every screen with when to start from it, neutral and accent ramps, status pills |
| `landing.html` | Public site: header, hero with a paper invoice and a payment toast, wordmark strip, six features, cash-forecast split, figures, three plans, serif quote, FAQ, CTA panel, footer |
| `dashboard.html` | App shell, overdue alert, four stats, SVG income chart with tooltip, upcoming payments, recent invoices |
| `dashboard-dark.html` | The dashboard with `<html class="dark">` |
| `resource-list.html` | Invoices: status tabs with counts, search, filter chips, sortable headers, selection, an open row menu, the floating bulk bar, pagination |
| `resource-form.html` | Invoice editor: error summary, required marks, a field in error, a disabled field, selects, line items with totals, note, upload, switches, sticky summary and actions |
| `resource-view.html` | Invoice detail: paper document, payment card with copy link, activity timeline, client facts |
| `settings.html` | Section sub-navigation, stacked form sections, logo upload, input group, drawn sign-in fields, blocked destructive action, sticky save bar |
| `login.html` | Centered sign-in card with drawn credentials, magic link and passkey, contour artwork |
| `empty-state.html` | Recurring invoices on first run: dashed empty panel, suggestions, setup checklist |
| `components.html` | Every component of `components.md` in its variants and states |

## Mockup → real code

| In the mockup | In the app |
| --- | --- |
| `nordic-saas-tokens.css` | The same file in `resources/css` (or the variables moved into `@theme` for Tailwind: `--color-accent-600: #0f6d82` …) |
| `ns-btn ns-btn--primary` | A Blade component `<x-ns.button variant="primary">` that renders the same classes |
| `ns-input`, `ns-select`, `ns-switch` | Real inputs with the same classes; `aria-invalid` and `@error` messages as `ns-error-text` |
| `mock-field` sign-in fields | Real `ns-input` fields (`type="email"`, `type="password"`) |
| Drawn menu / modal / toast | Alpine `x-show` or Livewire state around the same markup |
| Lucide inline SVG | `blade-lucide-icons` (`<x-lucide-file-text class="ns-icon" />`) or the same inline SVG |
| SVG chart | The same SVG rendered from data |

## Fidelity notes

- Original system: nothing is traced from another product. The inspirations and the principles taken from
  them are listed in `sources.md`.
- Hover, open and selected states are drawn with `is-hover`, `is-active`, `is-selected`, `is-focus`,
  `aria-expanded`, `aria-pressed`; the CSS also has the real `:hover` / `:focus-visible` rules.
- Every text pair meets WCAG AA in both themes (muted `#736e66` on paper 4.8:1; dark muted on canvas 7.1:1).

## Do / don't

- **Do** keep one primary button per view and the accent for actions and links only.
- **Do** let hairlines and whitespace separate things before reaching for shadows or fills.
- **Do** right-align money in tabular figures and write dates as "12 Oct 2026".
- **Don't** add colours outside the tokens or a second accent.
- **Don't** use heavy uppercase labels in the app; small caps-like labels belong to the invoice document.
- **Don't** load web fonts, CDNs or remote images in a mockup.
