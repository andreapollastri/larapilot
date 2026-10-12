# Berlin ("Berlino") — Larapilot design system

An **original** design system, not a copy of a framework. It covers a **public website** and a **backoffice** with one
vocabulary (`bn-` classes, one stylesheet). The sample product is **Kollektiv Studio**, a fictional creative agency and events
venue in Berlin-Lichtenberg: the same projects, invoices, rooms, people and dates (week 42, 12–18 October 2026) appear on every
screen.

**Personality.** Berlin design-scene rigour: a Swiss / Bauhaus 12-column grid, a neo-grotesk set big and tight, mono
micro-labels as structure, sharp 0 px corners and 1 px hairlines used *as* layout (cells share one rule, like a printed
timetable). Ink, paper and concrete greys, and **one signal colour — acid `#E4FF1A`** — borrowed from club flyers rather than
Bauhaus red. Brutalist touches are part of the kit: an oversized wordmark that spans the page, a running ticker in mono, crop
marks and registration marks, index numbers "01 / 02", halftone duotone photographs. Dark mode is native, not an afterthought:
black canvas, paper ink, grey rules, and acid that may finally be used as text.

## When to use it

- Agencies and studios, architects, galleries, museums and Kunstvereine, cultural venues and clubs, festivals, record labels,
  publishers, fashion and print shops — anyone whose brand is *editorial* and who wants a page that looks designed, not templated.
- B2B tools and backoffices that want a severe, high-contrast, information-dense look (programming, bookings, production,
  finance) and teams who like keyboard-first, table-heavy screens.
- A public site and its admin that should feel like the same object.

## When not to use it

- Consumer apps that need warmth, softness or many brand colours (health, kids, banking onboarding): use `nordic-saas/` or the
  client's own brand.
- Products already committed to Filament, Nova, AdminLTE or the Starter Kits — use those systems, their look is fixed.
- Data visualisation with many categorical series: Berlin has ink, hatch and one signal; more than three series need a palette
  the system deliberately does not have.

## How a mockup uses it

1. Copy `tokens.css` next to the mockup as **`berlin-tokens.css`** and link it — the only stylesheet. No CDN, no web font,
   no script.
2. `<body class="bn-mockup bn-body">` (`bn-mockup` lets the Larapilot dashboard recognise the system; `bn-body` carries the base
   styles). Add `<a class="bn-skip" href="#main">Skip to content</a>` first.
3. **Backoffice shell** (copy from `html/dashboard.html`):
   `.bn-app` › `aside.bn-rail` (brand, numbered `.bn-nav-item`s, foot) + `header.bn-topbar` (meta, search, square icon cells)
   + `main.bn-main` › `.bn-pagehead` and then content: `.bn-grid` of `.bn-cell`s, `.bn-toolbar` + `.bn-table-wrap`, or
   `.bn-formsec` sections. The current nav item gets `.is-active` + `aria-current="page"`.
   **Public shell** (copy from `html/landing.html`): `.bn-site` › `.bn-ticker` + `header.bn-sitehead` + `main` of `.bn-sec`
   sections (`.bn-sechead` with a huge index) + `footer.bn-sitefoot.bn-night`.
4. Compose with the classes in `components.md`. Start from the closest screen below and change the data — do not invent CSS.
   Photos are drawn: copy an SVG from `html/components.html` (section 12) and change its `aria-label`; it needs the hidden
   `svg.bn-defs` pattern block once per page.
5. Dark mode: the OS decides; `<html class="dark">` forces dark, `<html class="light">` opts out.
6. Interactive states are drawn statically: `.is-hover`, `.is-focus`, `.is-pressed`, `.is-selected`, open menu = `.bn-menu`
   inside `.bn-pop`, modal = `.bn-overlay` › `.bn-dialog`, toast = `.bn-toasts`.
7. Sign-in screens draw the credentials with `.mock-label` + `.mock-field` (`role="img"`, `aria-label`), never a password input.

## Screens (`html/`)

| File | What it shows |
| --- | --- |
| `index.html` | Catalog: palette, type specimen, every screen with when to start from it |
| `landing.html` | Public home — ticker, wordmark hero, next-event card, work grid, six theses, timetable, rooms, team, clients, contact + map + newsletter, footer |
| `program.html` | Public events program — filters, featured night (poster, running order, prices), timetable by calendar week, access / awareness / tickets |
| `error-404.html` | Public 404 — oversized code cut by a rule, registration mark, numbered ways back |
| `dashboard.html` | Backoffice overview — KPI cells (one acid), invoiced-vs-paid bars, open invoices, room week timeline, deadlines, team load, activity |
| `dashboard-dark.html` | The same overview forced dark (`<html class="dark">`) |
| `resource-list.html` | Projects index — status strip, search, filter chips, sort, selection + bulk bar, open row menu, tags, pager |
| `resource-form.html` | Project brief editor — numbered sections, required marks, error + summary, disabled field, selects, check grid, radio row, switches, drop zone, sticky bar |
| `modal.html` | Bookings week with the New booking dialog over a dotted backdrop |
| `settings.html` | Studio settings — numbered index, forms, team table, switches, danger zone, toast |
| `empty-state.html` | Room with no bookings (giant 0 with crop marks) + three compact empty cells |
| `login.html` | Sign in — black art panel, drawn email / password, remember me, SSO |
| `components.html` | Every component in its variants and states |

## Building it for real (Blade + Tailwind v4, or Livewire)

- **Tokens → Tailwind v4 `@theme`.** Copy section 1 of `tokens.css` into `resources/css/app.css` as theme variables:
  `--color-ink: #0A0A0A; --color-paper: #F4F4F0; --color-acid: #E4FF1A; --color-concrete-100 … -900; --color-alarm: #B8241A;`
  `--font-sans: "Akzidenz-Grotesk Pro", "Inter Tight", "Helvetica Neue", …; --font-mono: "JetBrains Mono", …; --radius-*: 0;`.
  Keep the semantic layer (`--bn-bg`, `--bn-ink`, `--bn-rule`, `--bn-signal-text` …) as plain CSS variables and switch them
  under `.dark` / `@media (prefers-color-scheme: dark)` exactly as section 2 does; use them through utilities such as
  `bg-(--bn-bg)` or `border-(--bn-rule)`.
- **Blade components.** One component per block: `<x-bn.button variant="signal" size="lg" icon="arrow-right">`,
  `<x-bn.tag status="review" />`, `<x-bn.cell span="3" index="01" title="Outstanding">`, `<x-bn.stat value="48,320" unit="€" />`,
  `<x-bn.table>` / `<x-bn.pager :paginator="$projects" />`, `<x-bn.field label="Budget" required :error="$errors->first('budget')">`,
  `<x-bn.photo kind="crowd" alt="…" />`, `<x-bn.timeline :bookings="$week" />`. The class names in `components.md` are the
  contract; components only add props.
- **Livewire.** Bulk selection = `wire:model="selected"` on the row checkboxes and an `@if(count($selected))` around
  `.bn-bulkbar`; filters = `wire:model.live` on the search and `.bn-chip--applied` rendered from the active filter array;
  modals = a `$showDialog` flag around `.bn-overlay`.
- **Fonts.** Akzidenz-Grotesk Pro (Berthold, commercial) is the intended face — buy a web licence. Free alternative: **Inter
  Tight** (SIL OFL) or **Schibsted Grotesk** (OFL); mono: **JetBrains Mono** (OFL) or **IBM Plex Mono** (OFL). Self-host
  them with `@font-face` in the real app; the stacks already name them first, so a machine that has them shows them.
- **Icons.** Lucide (ISC): `npm i lucide-static` or the `mallardduck/blade-lucide-icons` package; stroke 1.75, `currentColor`.

## Accessibility notes for the bold choices

- **Signal as surface.** Acid on paper is 1.02:1 — invisible as text. In light mode acid is only ever a fill under black text
  (17.55:1), a highlighter (`.bn-mark`), a selected row (`--bn-signal-soft`, 18.5:1) or a button. Anything that wants
  "signal-coloured text" uses `--bn-signal-text`: ink in light, acid in dark (17.55:1 on `#0A0A0A`). Acid fills that sit on
  paper always carry a 1 px ink edge (`--bn-signal-edge`) so the shape is visible (WCAG 1.4.11).
- **Mono micro-labels** are 11 px uppercase with +0.08em tracking: fine for short structural labels (column heads, indices,
  meta), never for sentences or the only copy of important information. Body text is 15 px, helper text 13 px.
- **Focus** is a 3 px outline with a 2 px offset filled by a contrasting gap (ink + acid gap in light, acid + black gap in dark):
  impossible to miss on any surface. Never remove it.
- **Uppercase only for short labels** (≤ 4 words). Headlines stay sentence case.
- **Hairlines are decoration, not state.** Selection also tints the row and adds a 4 px ink bar; errors add an icon and text;
  status tags differ in shape (dashed / ring / solid / acid / hatched / check), not only in colour; disabled controls turn
  dashed.
- **Alarm red** (`#B8241A` light, `#FF6B5E` dark) is the one functional exception to "one colour": errors, overdue, destructive.
  It is never decorative.
- Photos are SVGs with `role="img"` and a sentence describing the picture. The ticker's duplicated loop is `aria-hidden`, and
  `prefers-reduced-motion` stops it.

## Do / don't

- **Do** let rules do the work: put content in `.bn-cell`s of a `.bn-grid` so neighbours share one line.
- **Do** number things — sections, nav items, screens — with `.bn-idx` / `.bn-sechead__idx`.
- **Do** use one `.bn-btn--signal` per view, for the action that matters (the shell's acid "+" cell and the site header's
  Tickets button are part of the chrome and do not count).
- **Do** keep numbers tabular (`.bn-mono`, `.bn-tnum`) and dates in `DD.MM.YYYY`, times 24 h.
- **Don't** round corners, add soft shadows or gradients; the only shadow is the hard offset block.
- **Don't** set acid text, icons or thin acid lines on paper.
- **Don't** introduce a second accent colour; if a chart needs more than paid / open / now, rethink the chart.
- **Don't** draw a password `<input>` in a mockup; use `.mock-field--secret`.
