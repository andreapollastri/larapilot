# Zen — components

Every class lives in `tokens.css`; `html/components.html` draws each component in its variants and states. Icons are
**Lucide** inline SVG (`<svg class="zn-i" viewBox="0 0 24 24" aria-hidden="true">…</svg>`, stroke 1.5, `currentColor`; sizes
`.zn-i--12/14/16/18/20/24/32/48`, `.zn-i--thin` for 1.25). In the snippets `<!-- name -->` stands for a Lucide icon of that
name, and `d="…bold cut…"` / `d="…regular cut…"` for the ensō path data copied from `tokens.css` section 7.

**State classes:** `.is-active`, `.is-hover`, `.is-focus`, `.is-pressed`, `.is-selected`, `.is-error`, `.is-disabled`,
`.is-filled`, `.is-mixed`, `.is-dragover`, `.is-sorted`. **Theme islands:** `.zn-inv` (the other theme for a block) and
`.zn-night` (always black) re-point the semantic tokens, so any component inside them just works. The toast, tooltip, bulk bar
and skip link are inverted islands by default.

## Mapping table — mockup → real code

| Mockup | Blade component (suggested) | Notes |
| --- | --- | --- |
| `.zn-app`, `.zn-rail`, `.zn-topbar`, `.zn-main` | `<x-zn.layouts.app>` | Nav items from a `config('menu')` array; one topbar action per page |
| `.zn-site`, `.zn-sitehead`, `.zn-sitefoot` | `<x-zn.layouts.site>` | Cart count from the session |
| `.zn-btn` | `<x-zn.button variant size icon>` | `variant`: outline (default) · primary · ghost · quiet · danger |
| `.zn-status`, `.zn-dot` | `<x-zn.status :state>` | Map each enum case to one shape (table in §7) |
| `.zn-tag` | `<x-zn.tag>` | Metadata only, not status |
| `.zn-stat`, `.zn-meter`, `.zn-seats` | `<x-zn.stat>`, `<x-zn.meter :value>`, `<x-zn.seats :taken :total>` | Meter widths via `.zn-w-*` or a `style="width"` in real code |
| `.zn-block`, `.zn-card` | `<x-zn.block title link>`, `<x-zn.card>` | A block needs a title |
| `.zn-table`, `.zn-pager` | `<x-zn.table>`, `<x-zn.pager :paginator>` | Livewire: `wire:model` on row checkboxes |
| `.zn-field`, `.zn-input`, `.zn-select`… | `<x-zn.field>`, `<x-zn.input>` | Errors from `$errors` |
| `.zn-menu`, `.zn-dialog`, `.zn-toast` | `<x-zn.dropdown>`, `<x-zn.modal>`, `<x-zn.toast>` | Alpine for open / close |
| `.zn-enso` | `<x-zn.enso size>` | Bold cut ≤ 64 px, regular above |
| `.zn-photo` | `<x-zn.photo kind alt>` / real `<img>` | Real images: monochrome or desaturated product shots |

---

## 1. App shell (backoffice)

**Purpose:** the frame of every admin screen. A 240 px rail with a hairline on the right, a 72 px topbar with a hairline
below, and the content area with 64 px side padding and a 1200 px maximum. **Anatomy:** `.zn-app` › `aside.zn-rail`
(`__brand` with `.zn-logo` + phone-only `__menu`; `nav.__nav` of `.zn-nav-item`s with an icon, `__label` and an optional
`__count`; `.zn-rail__gap` before Settings; `__foot` with `.zn-av`, `__who` › `__name` + `__role`, and a sign-out icon
button) + `header.zn-topbar` (`__date`, `__search` › `.zn-search`, `__actions` with one primary button) + `main.zn-main`.
**States:** the active item is ink, weight 500, with a 2 px ink rule at the rail edge; hover underlines the label.
**Variants:** 768–1199 px the rail collapses to 72 px icons (items keep a `title`); below 768 px it becomes a top bar with
the menu button. **Do:** copy the shell from `html/dashboard.html` exactly. **Don't:** add group headings, icons in colour or a
second action to the topbar.

```html
<body class="zn-mockup zn-body"><a class="zn-skip" href="#main">Skip to content</a>
<div class="zn-app">
  <aside class="zn-rail" aria-label="Backoffice">
    <div class="zn-rail__brand"><a class="zn-logo" href="dashboard.html" aria-label="Kanso backoffice, today"><svg class="zn-logo__mark" viewBox="0 0 100 100" aria-hidden="true"><path d="…bold cut…"/></svg><span class="zn-logo__word">Kanso</span></a>
      <button type="button" class="zn-rail__menu" aria-label="Open menu" aria-expanded="false"><!-- menu --></button></div>
    <nav class="zn-rail__nav" aria-label="Main">
      <a class="zn-nav-item is-active" aria-current="page" href="dashboard.html" title="Today"><!-- sun --><span class="zn-nav-item__label">Today</span></a>
      <a class="zn-nav-item" href="resource-list.html" title="Orders"><!-- package --><span class="zn-nav-item__label">Orders</span><span class="zn-nav-item__count">2</span></a>
      <span class="zn-rail__gap" aria-hidden="true"></span>
      <a class="zn-nav-item" href="settings.html" title="Settings"><!-- settings-2 --><span class="zn-nav-item__label">Settings</span></a>
    </nav>
    <div class="zn-rail__foot"><span class="zn-av" aria-hidden="true">AM</span><span class="zn-rail__who"><span class="zn-rail__name">Aiko Mori</span><span class="zn-rail__role">Owner</span></span>
      <a class="zn-btn zn-btn--ghost zn-btn--icon zn-btn--sm" href="login.html" aria-label="Sign out, Aiko Mori"><!-- log-out --></a></div>
  </aside>
  <header class="zn-topbar">
    <p class="zn-topbar__date"><time datetime="2026-10-12">Monday, 12 October 2026</time></p>
    <div class="zn-topbar__search" role="search"><label class="zn-search"><!-- search --><span class="visually-hidden">Search orders, pieces and customers</span><input class="zn-search__input" type="search" placeholder="Search orders, pieces, customers"><span class="zn-kbd" aria-hidden="true">/</span></label></div>
    <div class="zn-topbar__actions"><a class="zn-btn zn-btn--primary" href="resource-form.html"><!-- plus -->New order</a></div>
  </header>
  <main class="zn-main" id="main">…</main>
</div></body>
```


## 2. Page head, breadcrumbs, sections, page foot

**Purpose:** the title block of a backoffice view and the rhythm below it. **Anatomy:** `header.zn-pagehead` › optional
`__eyebrow` (a `nav.zn-crumbs` with `__sep`), `__title` (`h1.zn-h1` + `__count`), `__lede`, `__actions`; `--rule` adds a
hairline under it. Content groups are `section.zn-section` (`--rule` starts with a hairline); `footer.zn-mainfoot` closes the
page with place and time. **Do:** one sentence of lede that says what matters today. **Don't:** add a second H1, an icon to the
title, or more than two actions.

```html
<header class="zn-pagehead">
  <nav class="zn-pagehead__eyebrow zn-crumbs" aria-label="Breadcrumb"><a href="dashboard.html">Today</a><span class="zn-crumbs__sep" aria-hidden="true">/</span><span aria-current="page">Orders</span></nav>
  <div><div class="zn-pagehead__title"><h1 class="zn-h1">Orders</h1><span class="zn-pagehead__count">48</span></div><p class="zn-pagehead__lede">Two to pack before the 16:00 pickup.</p></div>
  <div class="zn-pagehead__actions"><a class="zn-btn" href="#"><!-- download -->Export</a><a class="zn-btn zn-btn--primary" href="resource-form.html"><!-- plus -->New order</a></div>
</header>
<section class="zn-section zn-section--rule" aria-labelledby="s1"><h2 id="s1" class="zn-h2">This week</h2>…</section>
<footer class="zn-mainfoot"><span>Kanso · Higashiyama, Kyoto</span><span>Updated 13:20</span></footer>
```


## 3. Typography

**Purpose:** the voice — quiet, light, exact. **Anatomy:** `.zn-display` (serif, public site only), `.zn-h1`–`.zn-h3` (sans
300), `.zn-h4` (400), `.zn-lede` (+ `--lg`), `.zn-label` (the one uppercase voice: 11 px, 500, .14em; `--ink`, `--soft`),
`.zn-prose` (68ch, 16 px), `.zn-link`, `.zn-arrowlink`, `.zn-text-xs/sm/md/lg`, `.zn-soft`, `.zn-muted`, `.zn-strong`,
`.zn-light`, `.zn-mono`, `.zn-tnum`, `.zn-strike`. **States:** links underline in `--zn-ctl` and thicken to ink on hover
(`.is-hover`); the arrow of `.zn-arrowlink` moves 3 px. **Do:** use size and weight for hierarchy, not colour. **Don't:** set
sentences in uppercase, body text in 300 or in the serif, or `--zn-line` greys as text.

```html
<h1 class="zn-display">Bowls made slowly, for tea taken slowly.</h1>
<p class="zn-label">Higashiyama, Kyoto — since 2014</p>
<p class="zn-lede">Hand-thrown tea ware, fired four times a year.</p>
<div class="zn-prose"><p>Every chawan is thrown on a kick wheel and glazed with ash from our own kiln.</p></div>
<p>Read the <a class="zn-link" href="#">care notes</a> or <a class="zn-arrowlink" href="#">see the collection <!-- arrow-right --></a></p>
<span class="zn-mono">K-1048</span> <span class="zn-tnum">¥18,700</span> <span class="zn-strike zn-muted">¥22,000</span>
```


## 4. Ornaments — the ensō, stones, rules

**Purpose:** the one ornament and its echoes. **Anatomy:** `svg.zn-enso` (`viewBox="0 0 100 100"`, one filled path, sizes
`--24/32/64/128/200/320`, `--soft` grey, `--inline`); `.zn-logo__mark` is the same path at 28 px (`--lg` 40). **Two cuts:**
the **bold** path up to 64 px (logo, compact empty states), the **regular** path above (empty states, 404, sign-in) — copy
them verbatim from the comment in `tokens.css` section 7. `.zn-stone` (6 px dot; `--sm`, `--lg`, `--ring`) inside `.zn-stones`
is the only other motif. Rules: `hr.zn-rule` (hairline), `--ink`, `--thick` (2 px), `--short` (48 px, 2 px — under a hero
title), `--dashed`. **Do:** one ensō per screen at most, always `aria-hidden="true"`. **Don't:** rotate, colour, animate or
tile it, or use it as a loader or a bullet.

```html
<svg class="zn-enso zn-enso--128" viewBox="0 0 100 100" aria-hidden="true"><path d="…regular cut…"/></svg>
<svg class="zn-enso zn-enso--32 zn-enso--inline" viewBox="0 0 100 100" aria-hidden="true"><path d="…bold cut…"/></svg>
<span class="zn-stones" aria-hidden="true"><span class="zn-stone"></span><span class="zn-stone zn-stone--sm"></span><span class="zn-stone zn-stone--ring"></span></span>
<hr class="zn-rule zn-rule--short">
```


## 5. Layout — wrap, grid, stack, row, block, card

**Purpose:** space as structure. `.zn-wrap` (max 1360, gutter; `--narrow` 760) · `.zn-grid` (12 columns, 32 px columns, 80 px
rows; `--tight`) with `.zn-col-2…9`, `.zn-col-12` (3 → 6 → 12 as the screen narrows) · `.zn-stack` (8 px; `--4/16/24/32/48/64`)
· `.zn-row` (`--top`, `--base`, `--wrap`, `--between`, `--end`). **Block** — the default dashboard unit, no box:
`.zn-block` › `__head` (hairline below; `h2.__title` label, `__meta`, `__link` with an arrow) + content. **Card** — the same
content inside a 1 px hairline box, for settings panels and pickers: `.zn-card` (`--ink`, `--flush`) › `__head`, `__title`,
`__text`, `__foot`; `a.zn-card` turns its edge ink on hover. **Do:** prefer blocks; leave rows of the grid unbalanced rather
than filling them. **Don't:** nest cards, or put a card on a grey surface.

```html
<div class="zn-grid">
  <section class="zn-block zn-col-8" aria-labelledby="recent">
    <div class="zn-block__head"><h2 id="recent" class="zn-block__title">Recent orders</h2><a class="zn-block__link" href="resource-list.html">All orders <!-- arrow-right --></a></div>
    …
  </section>
  <section class="zn-block zn-col-4" aria-labelledby="low"><div class="zn-block__head"><h2 id="low" class="zn-block__title">Running low</h2><span class="zn-block__meta">4 pieces</span></div>…</section>
</div>
<div class="zn-card"><div class="zn-card__head"><h3 class="zn-card__title">Shop hours</h3><a class="zn-link zn-text-sm" href="#">Edit</a></div><p class="zn-card__text">Thursday to Monday, 11:00–18:00.</p></div>
```


## 6. Buttons

**Purpose:** actions, square, 40 px (`--sm` 32, `--lg` 48). **Variants:** default = 1 px ink outline (fills ink on hover —
black ↔ white), `--primary` = solid ink with paper text (inverts to outline on hover; one per view), `--ghost` = text that
underlines on hover, `--quiet` = grey outline that turns ink on hover, `--danger` = 2 px edge (combine with `--primary` for a
destructive confirm; the word says "Delete", not the colour); `--icon` (square, needs `aria-label`), `--block`;
`.zn-btngroup` joins buttons (`.is-active` = ink); `.zn-btn__count`; `.zn-actions` lays a row. **States:** `.is-hover`,
`.is-focus`, `.is-pressed` (1 px down), `[disabled]` / `.is-disabled` (dashed grey edge, ink-3 text). **Don't:** round, colour,
shadow or gradient a button.

```html
<a class="zn-btn zn-btn--primary" href="resource-form.html"><!-- plus -->New order</a>
<button type="button" class="zn-btn">Export</button>
<button type="button" class="zn-btn zn-btn--quiet">Duplicate</button>
<button type="button" class="zn-btn zn-btn--ghost">Cancel</button>
<button type="button" class="zn-btn zn-btn--danger">Delete piece</button>
<button type="button" class="zn-btn" disabled>Ship</button>
<button type="button" class="zn-btn zn-btn--sm zn-btn--icon" aria-label="More actions"><!-- ellipsis --></button>
<div class="zn-btngroup" role="group" aria-label="View"><a class="zn-btn zn-btn--sm is-active" aria-current="true" href="#">List</a><a class="zn-btn zn-btn--sm" href="#">Grid</a></div>
```


## 7. Status — shape and word

**Purpose:** say the state of a thing without colour. **Anatomy:** `.zn-status` › `span.zn-status__mark` (`aria-hidden`) +
the word. **Variants:** `--done` ● filled, `--pending` ○ ring, `--progress` ◐ half, `--cancelled` × cross with the word struck
through in ink-3, `--alert` ◆ diamond (needs attention), `--muted` (ink-3), `--lg` (10 px mark, 14 px text). The bare mark is
`.zn-dot` (`--done`, `--ring`, `--half`, `--cross`, `--diamond`, `--lg`) for timelines and tight cells.
**Mapping (Kanso):** Orders — New = pending · Packing = progress · Shipped = done · Cancelled = cancelled · Payment failed =
alert. Workshops — Scheduled = pending · Running = progress · Finished = done · Cancelled = cancelled. Pieces — In stock =
done · In the kiln = progress · Low = alert · Sold out = muted. **Do:** always print the word. **Don't:** use a shape alone,
add a colour per state, or invent a sixth shape.

```html
<span class="zn-status zn-status--pending"><span class="zn-status__mark" aria-hidden="true"></span>New</span>
<span class="zn-status zn-status--progress"><span class="zn-status__mark" aria-hidden="true"></span>Packing</span>
<span class="zn-status zn-status--done"><span class="zn-status__mark" aria-hidden="true"></span>Shipped</span>
<span class="zn-status zn-status--cancelled"><span class="zn-status__mark" aria-hidden="true"></span>Cancelled</span>
<span class="zn-status zn-status--alert"><span class="zn-status__mark" aria-hidden="true"></span>Payment failed</span>
<span class="zn-dot zn-dot--half" aria-hidden="true"></span>
```


## 8. Tags and counts

**Purpose:** metadata and small numbers — not status. `.zn-tag` (24 px, `--zn-line-2` edge, ink-2 text; `--ink`, `--solid`,
`--plain`, `--label` uppercase) in `.zn-tags`, with an optional 12 px icon. `.zn-count` (plain number), `--solid` (black pill),
`--ring` (outlined pill). **Do:** tags for material, glaze, size, channel. **Don't:** use tags as status, or more than three
on a row.

```html
<span class="zn-tags"><span class="zn-tag">Stoneware</span><span class="zn-tag">Ash glaze</span><span class="zn-tag zn-tag--ink"><!-- store -->Shop</span></span>
<span class="zn-tag zn-tag--solid">One of a kind</span>
<span class="zn-count zn-count--solid">2</span> <span class="zn-count zn-count--ring">12</span>
```


## 9. Seats, avatars, person, kbd

**Purpose:** small markers. `.zn-seats` › `.zn-seats__group` › `.zn-seat` (8 px ring; `.is-filled` = taken) — a workshop of
eight seats at a glance; give the group `role="img"` and an `aria-label` that reads the count. `.zn-av` round initials
(outlined; `--solid`, `--soft`, sizes `--sm` 24, default 32, `--lg` 48, `--xl` 80), `.zn-avstack`, `.zn-person` (avatar +
name). `.zn-kbd` (mono, hairline box). **Do:** print the number next to the seats somewhere. **Don't:** fill avatars with
photos of different tones in a list — initials keep the page quiet.

```html
<span class="zn-seats" role="img" aria-label="6 of 8 seats taken"><span class="zn-seats__group"><span class="zn-seat is-filled"></span><span class="zn-seat is-filled"></span><span class="zn-seat is-filled"></span><span class="zn-seat is-filled"></span><span class="zn-seat is-filled"></span><span class="zn-seat is-filled"></span><span class="zn-seat"></span><span class="zn-seat"></span></span></span>
<span class="zn-person"><span class="zn-av zn-av--sm zn-av--solid" aria-hidden="true">AM</span>Aiko Mori</span>
<span class="zn-avstack"><span class="zn-av">HI</span><span class="zn-av">ST</span><span class="zn-av zn-av--soft">+3</span></span>
<span class="zn-kbd">/</span>
```


## 10. Stats, delta, meter

**Purpose:** a few big, light numbers. `.zn-stats` (4 columns; `--3`) › `.zn-stat` (hairline on top) › `__label`, `__value`
(40 px, 300, tabular), `__unit` (raised ¥), `__of` ("/16"), `__foot`; `.zn-delta` with an arrow icon (the arrow's direction
and the word say up or down — there is no green or red). `.zn-meter` (2 px hairline track, ink fill; `--thick` 4 px,
`--outline` 8 px box) › `.zn-meter__fill.zn-w-0…100`; `.zn-meterrow` puts a label and a value above a meter. **Do:** say the
base ("on the 30 days before", "of 16"). **Don't:** show more than four figures in a row.

```html
<div class="zn-stats">
  <div class="zn-stat"><p class="zn-stat__label">Revenue, 30 days</p><p class="zn-stat__value"><span class="zn-stat__unit">¥</span>2,390,700</p><p class="zn-stat__foot"><span class="zn-delta"><!-- arrow-up-right -->15%</span><span>on the 30 days before</span></p></div>
  <div class="zn-stat"><p class="zn-stat__label">Workshop seats today</p><p class="zn-stat__value">14<span class="zn-stat__of">/16</span></p>…</div>
</div>
<div class="zn-meterrow"><span>Kiln load, firing of 19 Oct</span><span class="zn-tnum">70%</span><span class="zn-meter zn-meter--thick" aria-hidden="true"><span class="zn-meter__fill zn-w-70"></span></span></div>
```


## 11. Charts (inline SVG) and legend

**Purpose:** lines and bars drawn from tokens, in ink only. `.zn-chart-wrap` (scrolls on phones; the SVG keeps ≥ 560 px) ›
`svg.zn-chart` with `role="img"` and an `aria-label` that reads the data. Parts: `.zn-ch-grid` (hairline), `.zn-ch-base`,
`.zn-ch-axis` (+ `--ink` for "Today"), `.zn-ch-line` (1.5 px ink), `--compare` (dotted ink-3 — the previous period),
`.zn-ch-target` (dashed), `.zn-ch-now`, `.zn-ch-bar` (ink), `--muted` (grey), `--open` (outlined), `--hatch` (needs
`#zn-hatch`), `.zn-ch-dot` (+ `--solid`), `.zn-ch-val`. `.zn-legend` › `__item` › `__key` (line), `--compare`, `--target`,
`--solid`, `--muted`, `--hatch`. Three series at most: solid, hatch, grey (or line, dotted, dashed). **Don't:** fill the area
under a line or add gridlines every 10 units.

```html
<svg class="zn-defs" aria-hidden="true" focusable="false"><defs><pattern id="zn-hatch" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><rect width="1" height="6" class="zn-ch-hatch"/></pattern></defs></svg>
<div class="zn-chart-wrap"><svg class="zn-chart" viewBox="0 0 720 224" role="img" aria-label="Daily revenue, 13 September to 12 October 2026: ¥2,390,700 in total, best day 4 October at ¥118,000.">
  <line x1="48" y1="76" x2="712" y2="76" class="zn-ch-grid"/><text x="0" y="80" class="zn-ch-axis">100k</text>
  <line x1="48" y1="188" x2="712" y2="188" class="zn-ch-base"/>
  <path d="M48 94 …" class="zn-ch-line zn-ch-line--compare"/><path d="M48 90 …" class="zn-ch-line"/>
  <circle cx="528.8" cy="55.8" r="3" class="zn-ch-dot"/><text x="528.8" y="42" text-anchor="middle" class="zn-ch-val">118k</text>
  <text x="712" y="214" text-anchor="end" class="zn-ch-axis zn-ch-axis--ink">Today</text></svg></div>
<p class="zn-legend"><span class="zn-legend__item"><span class="zn-legend__key"></span>Last 30 days</span><span class="zn-legend__item"><span class="zn-legend__key zn-legend__key--compare"></span>The 30 days before</span></p>
```


## 12. Toolbar, chips, select, bulk bar

**Purpose:** find and act on many rows. `.zn-toolbar` › `.zn-search` (underline field; `--box` for a full frame), `.zn-chips` ›
`.zn-chip` (32 px, line-2 edge; hover = ink edge; `.is-active` = solid ink; `__count`), `.zn-chip--applied` (ink edge, with
`.zn-chip__x`), `.zn-filter` (small label + control), `__label`, `__spacer`, `__break` (forces a second line); sort with
`.zn-selectbox` › `select.zn-select` (`--sm`, `--block`) + chevron. `.zn-bulkbar` is an inverted strip › `__count`, small
buttons, `__spacer`. **Do:** show the bulk bar only while rows are selected. **Don't:** show more than five chips; move the rest
into a select.

```html
<div class="zn-toolbar" role="search">
  <label class="zn-search"><!-- search --><span class="visually-hidden">Search orders</span><input class="zn-search__input" type="search" placeholder="Customer, piece or number"></label>
  <div class="zn-chips"><a class="zn-chip is-active" href="#" aria-current="true">All <span class="zn-chip__count">48</span></a><a class="zn-chip" href="#">New <span class="zn-chip__count">2</span></a><a class="zn-chip" href="#">Packing <span class="zn-chip__count">1</span></a></div>
  <span class="zn-toolbar__spacer"></span>
  <label class="zn-row"><span class="zn-toolbar__label">Sort</span><span class="zn-selectbox"><select class="zn-select zn-select--sm"><option>Newest first</option></select><!-- chevron-down --></span></label>
  <span class="zn-toolbar__break"></span>
  <span class="zn-chip zn-chip--applied">Channel: Shop<button type="button" class="zn-chip__x" aria-label="Remove filter Channel: Shop"><!-- x --></button></span>
</div>
<div class="zn-bulkbar" role="region" aria-label="Bulk actions"><p class="zn-bulkbar__count">3 orders selected</p><button type="button" class="zn-btn zn-btn--sm">Print packing slips</button><span class="zn-bulkbar__spacer"></span><button type="button" class="zn-btn zn-btn--sm zn-btn--ghost">Clear</button></div>
```


## 13. Table

**Purpose:** ruled data with air. `.zn-table-wrap` (scrolls horizontally) › `table.zn-table` (`--page` keeps ≥ 760 px,
`--compact`, `--plain` drops the last rule). Heads are 11 px labels with a hairline; rows are separated by hairlines only.
Header buttons `.zn-th-sort` (`.is-sorted` + `aria-sort` on the `th`); cells `.zn-td-check`, `.zn-td-code` (mono),
`.zn-td-title` + `.zn-td-sub`, `.zn-td-num`, `.zn-td-nowrap`, `.zn-td-actions` › `.zn-rowbtn`. **States:** hover underlines
the title (`.is-hover`); `.is-selected` draws a 2 px ink rule at the row's left edge; select-all uses `.zn-checkbox.is-mixed`.
**Do:** right-align money; put status in its own column as `.zn-status`. **Don't:** zebra-stripe, tint selected rows, or add
vertical rules.

```html
<div class="zn-table-wrap"><table class="zn-table zn-table--page">
  <thead><tr><th scope="col" class="zn-td-check"><input class="zn-checkbox is-mixed" type="checkbox" aria-label="Select all"></th><th scope="col">Order</th><th scope="col">Customer</th><th scope="col" class="zn-td-num">Total</th><th scope="col" aria-sort="descending"><button type="button" class="zn-th-sort is-sorted">Date <!-- arrow-down --></button></th><th scope="col">Status</th><th scope="col" class="zn-td-actions"><span class="visually-hidden">Actions</span></th></tr></thead>
  <tbody><tr class="is-selected"><td><input class="zn-checkbox" type="checkbox" checked aria-label="Select K-1048"></td><td class="zn-td-code">K-1048</td><td><a class="zn-td-title" href="#">Haruka Ito</a><span class="zn-td-sub">Shiro chawan · Osaka</span></td><td class="zn-td-num">¥18,700</td><td class="zn-td-nowrap">12 Oct</td><td><span class="zn-status zn-status--pending"><span class="zn-status__mark" aria-hidden="true"></span>New</span></td><td class="zn-td-actions"><button type="button" class="zn-rowbtn" aria-label="Actions for K-1048"><!-- ellipsis --></button></td></tr></tbody>
</table></div>
```


## 14. Pagination

**Purpose:** page through results. `.zn-pager` › `__info` (`strong` for numbers) + `ul.__list` › `.zn-pager__link`
(`.is-active` = 1 px ink box + `aria-current="page"`, `.is-disabled` grey) and `.zn-pager__gap`.

```html
<nav class="zn-pager" aria-label="Pagination"><p class="zn-pager__info">Showing <strong>1–20</strong> of <strong>48</strong></p>
  <ul class="zn-pager__list"><li><span class="zn-pager__link is-disabled" aria-disabled="true"><!-- arrow-left --><span class="visually-hidden">Previous page</span></span></li><li><a class="zn-pager__link is-active" href="#" aria-current="page">1</a></li><li><a class="zn-pager__link" href="#">2</a></li><li><a class="zn-pager__link" href="#">3</a></li><li><a class="zn-pager__link" href="#" aria-label="Next page"><!-- arrow-right --></a></li></ul></nav>
```


## 15. Form fields and form sections

**Purpose:** calm forms: section head left (title, one-line description), fields right, a hairline between sections.
`.zn-form` › `.zn-formsec` › `__head` (`h2.__title`, `__desc`) + `__body` (2 columns; `.zn-field--full` spans both).
`.zn-field` › `.zn-label-row` (optional) › `label.zn-flabel` (`.zn-req` "*" + visually-hidden "(required)",
`.zn-flabel__meta` counter or unit) + control + `.zn-help` or `.zn-error` (icon + sentence, 2 px left rule, linked by
`aria-describedby`). Controls: `.zn-input` (`--mono`, `--line` underline only), `.zn-textarea`, `.zn-selectbox` ›
`.zn-select`, `.zn-inputgroup` › `.zn-affix` (+ `--end`). **States:** hover and focus turn the edge ink; `.zn-field.is-error`
/ `.zn-input.is-error` add the 2 px inner left rule; `[disabled]` / `.is-disabled` dashed grey; `[readonly]` hairline. Above
the form, `.zn-errorsum` (left rule, icon, list) names what to fix. **Don't:** use the placeholder as the label, or a red
anything.

```html
<div class="zn-errorsum" role="alert"><!-- circle-alert --><div><strong>One thing to fix</strong><ul><li><a class="zn-link" href="#f-ship">Ship date is before the order date</a></li></ul></div></div>
<section class="zn-formsec" aria-labelledby="s2">
  <div class="zn-formsec__head"><h2 id="s2" class="zn-formsec__title">Payment and delivery</h2><p class="zn-formsec__desc">Prices include consumption tax.</p></div>
  <div class="zn-formsec__body">
    <div class="zn-field"><div class="zn-label-row"><label class="zn-flabel" for="f-total">Total<span class="zn-req" aria-hidden="true">*</span><span class="visually-hidden"> (required)</span></label><span class="zn-flabel__meta">incl. tax</span></div><span class="zn-inputgroup"><span class="zn-affix">¥</span><input id="f-total" class="zn-input zn-tnum" type="text" value="18,700"></span><p class="zn-help">Shipping is added at checkout.</p></div>
    <div class="zn-field is-error"><label class="zn-flabel" for="f-ship">Ship date</label><input id="f-ship" class="zn-input" type="text" value="10 Oct 2026" aria-invalid="true" aria-describedby="f-ship-e"><p id="f-ship-e" class="zn-error"><!-- circle-alert -->Ship date must be after the order date, 12 Oct.</p></div>
    <div class="zn-field"><label class="zn-flabel" for="f-no">Order number</label><input id="f-no" class="zn-input zn-input--mono" type="text" value="K-1049" disabled></div>
    <div class="zn-field"><label class="zn-flabel" for="f-carrier">Carrier</label><span class="zn-selectbox zn-selectbox--block"><select id="f-carrier" class="zn-select"><option>Yamato, cool box</option></select><!-- chevron-down --></span></div>
  </div>
</section>
```


## 16. Checkbox, radio, switch, segmented, choices

**Purpose:** choices, square and black. `input.zn-checkbox` (16 px square, fills ink with a white tick; `.is-mixed`),
`input.zn-radio` (circle with an ink dot), `input.zn-switch` (`role="switch"`, square track, round knob; checked = ink track)
inside `label.zn-check` (+ `.zn-check__sub`) in `.zn-checks`, or `label.zn-switchrow` (`__text` with `strong` + `span`,
hairlines between rows). `.zn-segmented` › `__item` (`.is-active` = ink fill) for two to four options; `.zn-choices` ›
`.zn-choice` (`.is-active` = 2 px ink edge) for larger radio cards. Disabled = dashed grey edge.

```html
<div class="zn-checks"><label class="zn-check"><input class="zn-checkbox" type="checkbox" checked><span>Gift wrap in washi<span class="zn-check__sub">Plain white, no ribbon</span></span></label></div>
<fieldset class="zn-field"><legend class="zn-flabel">Channel</legend><div class="zn-choices">
  <label class="zn-choice is-active"><input class="zn-radio" type="radio" name="ch" checked><span>Online</span></label>
  <label class="zn-choice"><input class="zn-radio" type="radio" name="ch"><span>Shop</span></label></div></fieldset>
<label class="zn-switchrow"><span class="zn-switchrow__text"><strong>Email the customer</strong><span>When the order ships.</span></span><input class="zn-switch" type="checkbox" role="switch" checked></label>
<div class="zn-segmented" role="group" aria-label="Range"><a class="zn-segmented__item is-active" href="#" aria-current="true">7 days</a><a class="zn-segmented__item" href="#">30 days</a><a class="zn-segmented__item" href="#">Year</a></div>
```


## 17. Drop zone, file list, form bar

**Purpose:** uploads and saving. `label.zn-drop` (dashed `--zn-ctl` edge; hover / `.is-dragover` = solid ink) wraps a
visually-hidden file input; `.zn-files` › `.zn-file` (`__ext` box with the extension in mono, `__name`, `__meta` with an
inline `.zn-meter`; `.is-error` thickens the box to 2 px ink and the meta says why). `.zn-formbar` sticks to the bottom:
`__status` (last save) + `.zn-actions`.

```html
<label class="zn-drop" for="photos"><!-- upload --><span class="zn-drop__title">Drop photos here or <u>browse</u></span><span class="zn-help">JPG or PNG, on white, up to 10 MB</span><input id="photos" class="visually-hidden" type="file" multiple></label>
<ul class="zn-files"><li class="zn-file"><span class="zn-file__ext">JPG</span><span class="zn-stack zn-stack--4"><span class="zn-file__name">shiro-chawan-front.jpg</span><span class="zn-file__meta"><span class="zn-meter"><span class="zn-meter__fill zn-w-65"></span></span>64%</span></span><button type="button" class="zn-rowbtn" aria-label="Cancel upload"><!-- x --></button></li></ul>
<div class="zn-formbar"><p class="zn-formbar__status"><!-- check --> Draft saved 13:18</p><div class="zn-actions"><a class="zn-btn zn-btn--ghost" href="resource-list.html">Cancel</a><button type="submit" class="zn-btn zn-btn--primary">Create order</button></div></div>
```


## 18. Dropdown menu and tooltip

**Purpose:** row actions, user menu. `.zn-pop` (relative) › trigger (`aria-expanded="true"`) + `.zn-menu` (`role="menu"`,
paper, 1 px ink edge, no shadow; `--left`, `--static` for documentation) › `__label`, `__item` (hover / `.is-hover` = ink
fill inverted, `.is-active` 500, `--danger` 500 — destructive items are named plainly, not coloured), `__kbd`, `__sep`.
Tooltip: `.zn-tip-host` › trigger + `.zn-tooltip` (`role="tooltip"`, inverted). **Do:** open menus on an upper row — the
table wrapper clips anything below its last row.

```html
<div class="zn-pop"><button type="button" class="zn-rowbtn is-active" aria-label="Actions for K-1047" aria-expanded="true"><!-- ellipsis --></button>
  <div class="zn-menu" role="menu" aria-label="K-1047 actions"><p class="zn-menu__label">K-1047 · Daniel Weiss</p>
    <a class="zn-menu__item" role="menuitem" href="#"><!-- printer --> Packing slip<span class="zn-menu__kbd">P</span></a>
    <div class="zn-menu__sep" role="separator"></div>
    <a class="zn-menu__item zn-menu__item--danger" role="menuitem" href="#"><!-- x --> Cancel order</a></div></div>
<span class="zn-tip-host"><button type="button" class="zn-btn zn-btn--ghost zn-btn--icon" aria-describedby="tip1" aria-label="Copy tracking number"><!-- copy --></button><span id="tip1" class="zn-tooltip" role="tooltip">Copy</span></span>
```


## 19. Dialog

**Purpose:** focused tasks and confirmations. `.zn-overlay` (fixed; a near-opaque paper backdrop in light, black in dark —
no blur; `--static` for documentation) › `section.zn-dialog` (`role="dialog"`, `aria-modal`, `aria-labelledby`; 560 px,
`--sm` 440; 1 px ink edge, no shadow) › `__head` (`h2.__title` + `__close` icon button), `__body` (sentence, optional `__grid`
for two-column fields), `__foot` (actions right; `--between` puts meta left). **Do:** one primary action, named by what it
does. **Don't:** stack dialogs or open one for a single yes/no that can be undone instead.

```html
<div class="zn-overlay"><section class="zn-dialog zn-dialog--sm" role="dialog" aria-modal="true" aria-labelledby="dlg">
  <header class="zn-dialog__head"><h2 id="dlg" class="zn-dialog__title">Cancel order K-1044?</h2><button type="button" class="zn-btn zn-btn--ghost zn-btn--icon zn-dialog__close" aria-label="Close dialog"><!-- x --></button></header>
  <div class="zn-dialog__body"><p>Clara Moreau is refunded ¥31,900 and the tea set returns to stock.</p></div>
  <footer class="zn-dialog__foot"><button type="button" class="zn-btn zn-btn--ghost">Keep order</button><button type="button" class="zn-btn zn-btn--primary zn-btn--danger">Cancel and refund</button></footer>
</section></div>
```


## 20. Toast and notice

**Purpose:** feedback. `.zn-toasts` (fixed bottom-right; `--static` in docs) › `.zn-toast` (inverted: black in light, white
in dark) › `__icon` (on the Lucide svg), `__body` › `__title` + `__meta`, optional `__action` (Undo), `__close`. Inline
`.zn-notice` (hairline box) › icon + `__body` › `__title`; `--error` (2 px left rule), `--ink` (ink edge), `--quiet` (no box).
Use `role="status"` for success, `role="alert"` for failures. **Don't:** stack more than one toast, or use a toast for an
error the person must act on — put a notice next to the thing instead.

```html
<div class="zn-toasts" role="status"><div class="zn-toast"><svg class="zn-i zn-toast__icon" viewBox="0 0 24 24" aria-hidden="true"><!-- check --></svg><div class="zn-toast__body"><span class="zn-toast__title">Studio details saved</span><span class="zn-toast__meta">13:24</span></div><a class="zn-toast__action" href="#">Undo</a><button type="button" class="zn-toast__close" aria-label="Dismiss"><!-- x --></button></div></div>
<div class="zn-notice zn-notice--error" role="alert"><!-- circle-alert --><div class="zn-notice__body"><span class="zn-notice__title">Payment failed for K-1043</span>The card was declined. The customer has been emailed.</div></div>
```


## 21. Tabs and sub-navigation

**Purpose:** sub-navigation. `.zn-tabs` › `.zn-tab` (ink-3; hover ink; `.is-active` = ink with a 2 px ink underline,
`aria-current`; `__count`). Settings use `.zn-subnav` › `.zn-subnav__item` (`.is-active` = 500 with a 2 px ink rule on the left,
the same mark as the rail).

```html
<nav class="zn-tabs" aria-label="Order views"><a class="zn-tab is-active" href="#" aria-current="page">Open <span class="zn-tab__count">3</span></a><a class="zn-tab" href="#">Shipped</a><a class="zn-tab" href="#">All</a></nav>
<nav class="zn-subnav" aria-label="Settings sections"><a class="zn-subnav__item is-active" href="#studio" aria-current="true">Studio</a><a class="zn-subnav__item" href="#shop">Shop and shipping</a><a class="zn-subnav__item" href="#danger">Close the shop</a></nav>
```


## 22. Timeline, activity log, definition list, list

**Purpose:** time and short lists. `.zn-timeline` › `li.zn-tl__item` (`__time`, a `.zn-dot` in the shape of the state, `__body` ›
`__title` + `__meta`) and one `li.zn-tl__now` (time label + ink line across) for "now". `.zn-log` › `__item` (`__time`,
`__text` with `strong`). `.zn-dl` (label left, value right, hairlines; `--stack` puts values left). `.zn-list` › `__row`
(`__main` › `__title` + `__sub`, `__end`, optional full-width `.zn-meter`).

```html
<ol class="zn-timeline">
  <li class="zn-tl__item"><span class="zn-tl__time">10:00</span><span class="zn-dot zn-dot--done" aria-hidden="true"></span><div class="zn-tl__body"><p class="zn-tl__title">Usucha for beginners</p><p class="zn-tl__meta"><span>Tea room · 90 min</span><span>8 of 8 seats</span><span>Finished</span></p></div></li>
  <li class="zn-tl__now"><span>13:20</span></li>
  <li class="zn-tl__item"><span class="zn-tl__time">14:00</span><span class="zn-dot zn-dot--ring" aria-hidden="true"></span><div class="zn-tl__body"><p class="zn-tl__title">Throwing a first chawan</p><p class="zn-tl__meta"><span>Studio · 2 h</span><span>Next</span></p></div></li>
</ol>
<ol class="zn-log"><li class="zn-log__item"><span class="zn-log__time">12:58</span><p class="zn-log__text"><strong>Haruka Ito</strong> ordered a Shiro chawan</p></li></ol>
<dl class="zn-dl"><dt>Clay</dt><dd>Shigaraki stoneware</dd><dt>Diameter</dt><dd>12 cm</dd></dl>
<ul class="zn-list"><li class="zn-list__row"><span class="zn-list__main"><a class="zn-list__title" href="#">Ash glaze kyusu</a><span class="zn-list__sub">Next firing 26 Oct</span></span><span class="zn-list__end">1 left</span><span class="zn-meter" aria-hidden="true"><span class="zn-meter__fill zn-w-10"></span></span></li></ul>
```


## 23. Photo placeholders (monochrome SVG)

**Purpose:** pictures without loading anything. `.zn-photo` (`#EDEDED` ground; `--1x1`, `--3x4`, `--4x5`, `--3x2`, `--16x9`,
`--21x9`, `--fill`) › `svg` (`viewBox`, `preserveAspectRatio="xMidYMid slice"`, `role="img"`, `aria-label` describing the
photo). Shapes use only the fixed fills `.zn-ph-0` (paper wall) · `-1` (light glaze, linen) · `-2` (raw clay, shadow) · `-3`
(ash glaze) · `-4` (wood, stone) · `-5` (kuro glaze) · `-k` · `-w`, and the strokes `.zn-ph-line`, `-line-w`, `-line-soft`.
Wrap in `figure.zn-figure` with `figcaption.zn-figure__cap` for a caption. Photos keep their greys in dark mode, like real
photographs. **Do:** draw one object, centred low, with a lot of wall. **Don't:** use `<img src="https://…">` or draw scenes
with many objects.

```html
<figure class="zn-figure"><div class="zn-photo zn-photo--4x5"><svg viewBox="0 0 400 500" preserveAspectRatio="xMidYMid slice" role="img" aria-label="Photo: a white chawan on pale linen against a paper wall.">
  <rect width="400" height="500" class="zn-ph-0"/><rect y="350" width="400" height="150" class="zn-ph-1"/>
  <ellipse cx="200" cy="356" rx="112" ry="12" class="zn-ph-2"/><path d="M92 250h216c-6 64-48 104-108 104S98 314 92 250z" class="zn-ph-w"/><path d="M92 250h216" class="zn-ph-line-soft"/></svg></div>
  <figcaption class="zn-figure__cap">Shiro chawan, white shino, 12 cm</figcaption></figure>
```


## 24. Public site blocks

`.zn-site` › `header.zn-sitehead` (96 px; `--rule`; `.zn-logo`, `nav.zn-sitenav` › `__link` (`.is-active` underlined),
`__end` with `.zn-cartlink`, phone-only `__menu`) · `.zn-hero` (5/7 split; `--center`) › `__body` (`__title` serif, `__lede`,
`__actions`) + `__media` · `.zn-sec` (128 px vertical; `--rule`, `--tight`) › `.zn-sechead` (`__title`, `__aside`) ·
`.zn-collection` (4 columns; `--3`) › `a.zn-piece` (photo, `__info` › `__name`, `__price`, `__meta`; hover underlines the name)
· `.zn-feature` (`--reverse`; `__media`, `__body` › `__title`, `__text`) · `.zn-story` (centred serif paragraph, `__text`,
`__sign`) · `.zn-journal` › `a.zn-entry` (`__date`, `__title`, `__text`) · `.zn-quote` (`blockquote` › `__text` serif +
`__cite`) · `.zn-newsletter` (underline field + ghost button) · `footer.zn-sitefoot` (`__cols` › `__col` / `--wide`,
`__bottom`). **Do:** one section, one idea; 128 px between sections. **Don't:** add carousels, badges or a sale banner.

```html
<section class="zn-hero">
  <div class="zn-hero__body"><p class="zn-label">Autumn firing · 2026</p><h1 class="zn-hero__title">Bowls made slowly.</h1><p class="zn-hero__lede">Hand-thrown tea ware from a small studio in Higashiyama, Kyoto.</p>
    <div class="zn-hero__actions"><a class="zn-btn zn-btn--primary zn-btn--lg" href="#">See the collection</a><a class="zn-arrowlink" href="#">Book a workshop <!-- arrow-right --></a></div></div>
  <div class="zn-hero__media"><div class="zn-photo zn-photo--4x5">…</div></div>
</section>
<div class="zn-collection"><a class="zn-piece" href="#"><div class="zn-photo zn-photo--1x1">…</div><span class="zn-piece__info"><span class="zn-piece__name">Kuro yunomi</span><span class="zn-piece__price">¥4,950</span><span class="zn-piece__meta">Black glaze · 3 left</span></span></a></div>
<div class="zn-newsletter"><label class="visually-hidden" for="nl">Email</label><input id="nl" class="zn-newsletter__input" type="email" placeholder="Your email, four letters a year"><button type="button" class="zn-btn zn-btn--ghost">Subscribe</button></div>
```


## 25. Empty state and error page

`.zn-empty` (centred, generous padding) › `svg.zn-enso.zn-empty__enso` (regular cut, 128 px), `h2.__title`, `__text`,
`__actions`; `--compact` (left-aligned, 48 px bold ensō) inside a block. `.zn-err` › `.zn-sitehead` + `main.zn-err__main` ›
`.zn-err__figure` (a large regular ensō with `.zn-err__code` set inside it as real text) + `__title`, `__text`, `__links`.
**Do:** say why it is empty and offer one next step. **Don't:** add an illustration, a joke or more than two actions.

```html
<section class="zn-empty" aria-labelledby="e">
  <svg class="zn-enso zn-empty__enso" viewBox="0 0 100 100" aria-hidden="true"><path d="…regular cut…"/></svg>
  <h2 id="e" class="zn-empty__title">No workshops scheduled for winter.</h2>
  <p class="zn-empty__text">The kiln rests from December to February. Add a date when the studio opens again.</p>
  <div class="zn-empty__actions"><a class="zn-btn zn-btn--primary" href="#"><!-- plus -->Schedule a workshop</a></div>
</section>
<div class="zn-err__figure"><svg class="zn-enso" viewBox="0 0 100 100" aria-hidden="true"><path d="…regular cut…"/></svg><p class="zn-err__code">404</p></div>
```


## 26. Sign-in and drawn fields

`.zn-auth` (1/1 split) › `aside.zn-auth__art.zn-night` (always black: `.zn-logo`, `__center` with a `.zn-enso.zn-auth__enso`
and one serif `__line`, `__meta`) + `main.zn-auth__panel` › `section.zn-auth__form` (`h1.__title`, `.mock-form`, `__row`,
`__or`) + `__foot`. Credentials are drawn: `.mock-label` + `.mock-field` (`role="img"`, `aria-label`), `--secret`, `--empty`,
`--focus` (focus ring + `.mock-field__caret`), `--error`, `--disabled`, `--line`, `__end` ("Show"). The submit is a link with
`role="button"`. Only checkboxes may be real inputs.

```html
<div class="mock-form">
  <div><span class="mock-label">Email</span><div class="mock-field" role="img" aria-label="Email field, filled in">aiko@kanso.studio</div></div>
  <div><span class="mock-label">Password</span><div class="mock-field mock-field--secret mock-field--focus" role="img" aria-label="Password field, filled in">••••••••••••<span class="mock-field__caret"></span></div></div>
  <div class="zn-auth__row"><label class="zn-check"><input class="zn-checkbox" type="checkbox" checked><span>Keep me signed in</span></label><a class="zn-link" href="#">Forgot password?</a></div>
  <a href="dashboard.html" class="zn-btn zn-btn--primary zn-btn--lg zn-btn--block" role="button">Sign in</a>
</div>
```


## Utilities

`.zn-wrap` (`--narrow`), `.zn-grid` + `.zn-col-*`, `.zn-row` (`--top`, `--base`, `--wrap`, `--between`, `--end`), `.zn-stack`
(`--4`, `--16`, `--24`, `--32`, `--48`, `--64`), `.zn-gap-4/12/16/24/32`, `.zn-mt-8/16/24/32/48/64`, `.zn-mt-auto`,
`.zn-mb-16/32`, `.zn-ml-auto`, `.zn-fill`, `.zn-none`, `.zn-right`, `.zn-center`, `.zn-nowrap`, `.zn-truncate`,
`.zn-block-el`, `.zn-relative`, `.zn-maxw-40/60`, `.zn-bt`, `.zn-bb`, `.zn-hide-sm`, `.zn-only-sm`, `.zn-w-0…100` (steps of 5),
`.zn-actions`, `.visually-hidden`. Documentation helpers: `.zn-cat__*`, `.zn-swatch*`, `.zn-sw-*`, `.zn-specimen*`,
`.zn-screen*`, `.zn-grouphead`, `.zn-demo*`.
