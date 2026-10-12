# New York — components

Every class lives in `tokens.css`; `html/components.html` draws each component in its variants and states. Icons are
**Lucide** inline SVG (`<svg class="ny-i" viewBox="0 0 24 24" aria-hidden="true">…</svg>`, stroke 1.75, `currentColor`; sizes
`.ny-i--12/14/16/18/20/24/32/48`, `.ny-i--bold` for 2.25). In the snippets `<!-- name -->` stands for a Lucide icon of that
name.

**State classes:** `.is-active`, `.is-hover`, `.is-focus`, `.is-pressed`, `.is-selected`, `.is-error`, `.is-mixed`,
`.is-disabled`, `.is-today`, `.is-now`, plus `.is-86` and `.is-full` on dishes and time slots. **Theme islands:** `.ny-night`
(always black signage), `.ny-inv` (inverted for the current theme), `.ny-taxi` (always yellow) re-point the semantic tokens for
a block, so any component inside them just works.

## Mapping table — mockup → real code

| Mockup | Blade component (suggested) | Notes |
| --- | --- | --- |
| `.ny-app`, `.ny-rail`, `.ny-topbar`, `.ny-main` | `<x-ny.layouts.app>` | Rail items from a `config('menu')` array; venues group from `Venue::all()` |
| `.ny-site`, `.ny-sitehead`, `.ny-masthead`, `.ny-sitefoot` | `<x-ny.layouts.site>` | Zipper from tonight's specials and events |
| `.ny-bullet` | `<x-ny.bullet :line="$venue->line" size>` | The letter comes from the model, never typed by hand |
| `.ny-band`, `.ny-streetsign` | `<x-ny.band size arrow>`, `<x-ny.street-sign>` | Wayfinding only |
| `.ny-btn` | `<x-ny.button variant size icon>` | `variant`: outline (default) · primary · taxi · ghost · quiet · danger · danger-solid |
| `.ny-tag`, `.ny-status` | `<x-ny.tag :status>`, `<x-ny.service-status :venue>` | One modifier per enum case (table in §7) |
| `.ny-grid` / `.ny-cell`, `.ny-card` | `<x-ny.grid>` / `<x-ny.cell span title>`, `<x-ny.card>` | Spans 2–12 |
| `.ny-stat`, `.ny-meter`, `.ny-load` | `<x-ny.stat>`, `<x-ny.meter :value line>` | Widths via `.ny-w-*` or `style="width"` in real code |
| `.ny-table`, `.ny-pager` | `<x-ny.table>`, `<x-ny.pager :paginator>` | Livewire: `wire:model` on row checkboxes |
| `.ny-field`, `.ny-input`, `.ny-select`, `.ny-seg` | `<x-ny.field>`, `<x-ny.input>`, `<x-ny.segmented>` | Errors from `$errors` |
| `.ny-menu`, `.ny-dialog`, `.ny-toast` | `<x-ny.dropdown>`, `<x-ny.modal>`, `<x-ny.toast>` | Alpine for open / close |
| `.ny-tl` | `<x-ny.timeline :venues :bookings from="17:00">` | Blocks: `from` = half-hour from 5:00 PM, `len` = half-hours |
| `.ny-board`, `.ny-dish`, `.ny-carte` | `<x-ny.board>`, `<x-ny.dish>`, `<x-ny.menu :courses>` | `is-86` from the dish's sold-out flag |
| `.ny-photo` | `<x-ny.photo kind alt>` / real `<img>` | Real images get a duotone filter or a pre-processed duotone |

---

## 1. App shell (backoffice)

**Purpose:** the frame of every admin screen. The rail is a signage panel — always black, with the thin white stripe along
the top — and the page beside it is the newspaper. **Anatomy:** `.ny-app` › `aside.ny-rail` (`__brand` with `.ny-logo` and the
phone-only `__menu`, `nav.__nav` with `__group` labels and `.ny-nav-item`s — icon or bullet, `__label`, `__count` or a
`.ny-count` — and `__foot` with `__service` and the user) + `header.ny-topbar` (`__folio` with `strong` for the date,
`__search` › `.ny-search`, `__actions` › round `.ny-topbar__btn`s with `__badge`, `.ny-topbar__user`) + `main.ny-main`
(optional `.ny-mainfoot`). **Variants:** below 960 px the rail becomes a top bar with the menu button; the topbar hides.
**Do:** keep the same eight items on every screen (Tonight, Reservations, Guests, Menus, 86 board, Venues, Staff, Settings).
**Don't:** colour the rail or put taxi anywhere in it except the active item.

```html
<body class="ny-mockup ny-body"><a class="ny-skip" href="#main">Skip to content</a>
<div class="ny-app">
  <aside class="ny-rail" aria-label="Backoffice">
    <div class="ny-rail__brand"><a class="ny-logo" href="dashboard.html"><span class="ny-logo__mark" aria-hidden="true">&amp;</span><span class="ny-logo__text"><span class="ny-logo__word">Mulberry <em>&amp;</em> Grand</span><span class="ny-logo__sub">Backoffice</span></span></a>
      <button type="button" class="ny-rail__menu" aria-label="Open menu" aria-expanded="false"><!-- menu --></button></div>
    <nav class="ny-rail__nav" aria-label="Main">
      <p class="ny-rail__group"><span>Service</span></p>
      <a class="ny-nav-item is-active" aria-current="page" href="dashboard.html"><!-- moon-star --><span class="ny-nav-item__label">Tonight</span><span class="ny-nav-item__count">412</span></a>
      <a class="ny-nav-item" href="resource-list.html"><!-- book-open --><span class="ny-nav-item__label">Reservations</span><span class="ny-count ny-count--taxi">6</span></a>
      <a class="ny-nav-item" href="#"><!-- ban --><span class="ny-nav-item__label">86 board</span><span class="ny-count ny-count--alarm">4</span></a>
      <p class="ny-rail__group"><span>Venues</span><span>5</span></p>
      <a class="ny-nav-item" href="#"><span class="ny-bullet ny-bullet--m">M</span><span class="ny-nav-item__label">Mulberry Trattoria</span></a>
    </nav>
    <div class="ny-rail__foot"><p class="ny-rail__service"><span class="ny-dot ny-dot--good"></span>Good service on all lines</p>…</div>
  </aside>
  <header class="ny-topbar">
    <p class="ny-topbar__folio"><strong>Mon., Oct. 12, 2026</strong><span>Late edition</span><span>Doors 5:00 PM</span></p>
    <div class="ny-topbar__search" role="search"><label class="ny-search"><!-- search --><span class="visually-hidden">Search</span><input class="ny-search__input" type="search" placeholder="Search guests, reservations, dishes…"><span class="ny-kbd">/</span></label></div>
    <div class="ny-topbar__actions"><a class="ny-topbar__btn" href="#" aria-label="Notifications, 3 unread"><!-- bell --><span class="ny-topbar__badge" aria-hidden="true">3</span></a>
      <a class="ny-topbar__user" href="settings.html"><span class="ny-av ny-av--sm ny-av--taxi">RV</span>Rosa Valentine</a></div>
  </header>
  <main class="ny-main" id="main">…</main>
</div></body>
```

## 2. Page head, breadcrumbs, sections

**Purpose:** the headline of a backoffice view. **Anatomy:** `header.ny-pagehead` (+ `.ny-dbl-bottom` for the newspaper double
rule) › `__eyebrow` (a `.ny-kicker` or `nav.ny-crumbs` with `__sep`), `__title` › `h1.ny-h1` + `__count` (Didone number),
`__dek` (text serif), `__actions`. Content below goes in `.ny-section` (`--rule`, `--flush`) with a `__head` (2 px rule).
**Do:** write the dek like a newspaper subhead — one sentence of context. **Don't:** add a second H1 or an icon to the title.

```html
<header class="ny-pagehead ny-dbl-bottom">
  <div class="ny-pagehead__eyebrow"><nav class="ny-crumbs" aria-label="Breadcrumb"><a href="dashboard.html">Tonight</a><span class="ny-crumbs__sep" aria-hidden="true">/</span><span aria-current="page">Reservations</span></nav></div>
  <div><div class="ny-pagehead__title"><h1 class="ny-h1">Reservations</h1><span class="ny-pagehead__count">184</span></div>
    <p class="ny-pagehead__dek">Every table on the book this week across the five restaurants, from Monday to Sunday.</p></div>
  <div class="ny-pagehead__actions"><a class="ny-btn" href="#"><!-- download --> Export</a><a class="ny-btn ny-btn--taxi" href="#"><!-- plus --> New reservation</a></div>
</header>
```

## 3. Typography

**Purpose:** the voice. **Anatomy:** `.ny-nameplate` (centred Didone wordmark, `--sm`), `.ny-amp` (italic Didone ampersand),
`.ny-display`, `.ny-h1`, `.ny-h2` (Didone, `em` turns italic), `.ny-h3` / `.ny-h4` (Helvetica bold), `.ny-h3--serif`,
`.ny-kicker` (`--muted`, `--bar` with an accent dash), `.ny-label` (`--ink`), `.ny-dek` (`--italic`), `.ny-lede`, `.ny-byline`
(`strong` in small caps), `.ny-prose` (`--cols` for two newspaper columns), `.ny-dropcap`, `.ny-quote` + `__cite`, `.ny-mark`
(taxi highlighter), `.ny-link`, `.ny-arrowlink`, `.ny-strike`. Helpers: `.ny-didone`, `.ny-serif`, `.ny-italic`, `.ny-upper`,
`.ny-muted`, `.ny-soft`, `.ny-alarm-text`, `.ny-good-text`, `.ny-accent-text`, `.ny-strong`, `.ny-medium`, `.ny-tnum`,
`.ny-mono`, `.ny-text-xs/sm/md/lg`. **Do:** Didone for headlines, numbers and prices; Helvetica for everything that guides.
**Don't:** colour text taxi in light mode — use `.ny-mark` or `.ny-accent-text`.

```html
<p class="ny-kicker ny-kicker--bar">Dining · Nolita</p>
<h2 class="ny-display">Ninety covers, <em>one</em> red sauce.</h2>
<p class="ny-dek">How the flagship on Mulberry Street still makes its gravy at <span class="ny-mark">six every morning</span>.</p>
<p class="ny-byline">By <strong>Rosa Valentine</strong> · Oct. 12, 2026</p>
<div class="ny-prose ny-prose--cols"><p class="ny-dropcap">The pots go on before the bread arrives…</p><p>…</p></div>
<blockquote class="ny-quote">The sauce is the house. Everything else is furniture.<cite class="ny-quote__cite">Sal Ferrante, chef</cite></blockquote>
<a class="ny-arrowlink" href="menu.html">See the menu <!-- arrow-right --></a>
```

## 4. Signage: line bullets, bands, street signs, zipper, checker

**Purpose:** wayfinding. **Bullets:** `.ny-bullet` + one line (`--a` blue, `--m` orange, `--g` green, `--l` grey, `--7` purple)
or `--taxi`, `--street`, `--ink`, `--ring`; sizes `--sm` 18, default 26, `--md` 32, `--lg` 44, `--xl` 72, `--xxl` 132;
`--diamond` for an express / limited service (a seasonal pop-up, a one-night event); `.ny-bullets` groups them.
**Band:** `.ny-band` (always black, white stripe along the top; `--sm`, `--lg`, `--flat`) › bullets, `__arrow` (white disc with
an arrow), `__text` + `__sub`, `__end` › `__tag` (taxi). **Street sign:** `.ny-streetsign` (green plate, white letters, inner
white border; `small` for "St" / "Ave", `__num` for the block number), stacked in `.ny-streetsigns` for a corner.
**Zipper:** `.ny-zipper` › `__label` (taxi) + `__track` of `__item`s and round `__sep`s, duplicated once inside an `aria-hidden`
span for the loop. **Checker:** `hr.ny-checker` (`--lg`, `--v` vertical). **Do:** one band = one destination. **Don't:** show a
disc without its letter, or use a line colour for a status.

```html
<span class="ny-bullets" aria-label="Restaurants"><span class="ny-bullet ny-bullet--a">A</span><span class="ny-bullet ny-bullet--m">M</span><span class="ny-bullet ny-bullet--g">G</span><span class="ny-bullet ny-bullet--l">L</span><span class="ny-bullet ny-bullet--7">7</span></span>
<div class="ny-band ny-band--lg"><span class="ny-band__arrow"><!-- arrow-down-right --></span><span class="ny-bullet ny-bullet--md ny-bullet--m">M</span><span class="ny-band__text">Mulberry Trattoria<span class="ny-band__sub">156 Mulberry St · at Grand St</span></span><span class="ny-band__end"><span class="ny-band__tag">Open till 11</span></span></div>
<span class="ny-streetsigns"><span class="ny-streetsign">Mulberry <small>St</small></span><span class="ny-streetsign"><span class="ny-streetsign__num">100–199</span>Grand <small>St</small></span></span>
<div class="ny-zipper" role="region" aria-label="Tonight at Mulberry &amp; Grand"><span class="ny-zipper__label"><!-- radio --><span>Tonight</span></span>
  <p class="ny-zipper__track"><span class="ny-zipper__item"><span class="ny-bullet ny-bullet--7">7</span>Live trio at the Supper Club from 9 PM<span class="ny-zipper__sep"></span></span><span aria-hidden="true">…same items…</span></p></div>
<hr class="ny-checker">
```

## 5. Newspaper rules, folio, ornaments

**Purpose:** the paper's structure. `hr.ny-rule` (`--soft`, `--thick`), `hr.ny-rule2` (3 px over 1 px), `.ny-dbl-bottom` /
`.ny-dbl-top` add a double rule to any block (`--ny-dbl-pad` sets the space above the bottom rule), `.ny-folio` (dateline
between rules; `--double` puts the double rule on top; on phones only its first item stays), `.ny-fleuron` (a centred glyph
between hairlines), `.ny-star` / `.ny-stars` (`.is-off` for an empty star). **Do:** use a double rule once per block, under the
thing it closes. **Don't:** stack more than two rules.

```html
<p class="ny-folio ny-folio--double"><span>Mon., Oct. 12, 2026</span><span>Late edition · Five restaurants</span><span>Price: one good appetite</span></p>
<p class="ny-fleuron" aria-hidden="true">❦</p>
<span class="ny-stars" role="img" aria-label="3 of 4 stars"><span class="ny-star"></span><span class="ny-star"></span><span class="ny-star"></span><span class="ny-star is-off"></span></span>
```

## 6. Buttons

**Purpose:** actions. 40 px (`--sm` 32, `--lg` 52), 2 px corners, 1.5 px ink border, Helvetica bold. **Variants:** default
outline (sunken on hover), `--primary` (ink), `--taxi` (the one main action per view, ink edge; black on yellow inside
`.ny-taxi`), `--ghost`, `--quiet` (hairline border), `--danger`, `--danger-solid`; `--pill`, `--upper`, `--icon` (needs
`aria-label`), `--block`, `--between` (label left, icon right); `.ny-btngroup` joins buttons (`.is-active` = ink);
`.ny-btn__count`; `.ny-actions` lays a row. **States:** `.is-hover`, `.is-focus`, `.is-pressed`, `[disabled]` / `.is-disabled`
(dashed, muted). **Don't:** put two taxi buttons in one view or add a gradient.

```html
<a class="ny-btn ny-btn--taxi" href="#"><!-- plus --> New reservation</a>
<button type="button" class="ny-btn ny-btn--primary">Seat party</button>
<button type="button" class="ny-btn">Export</button>
<button type="button" class="ny-btn ny-btn--ghost">Cancel</button>
<button type="button" class="ny-btn ny-btn--danger">Mark no-show</button>
<button type="button" class="ny-btn ny-btn--sm ny-btn--icon" aria-label="More actions"><!-- ellipsis --></button>
<div class="ny-btngroup" role="group" aria-label="Service"><a class="ny-btn ny-btn--sm is-active" aria-current="true" href="#">Dinner</a><a class="ny-btn ny-btn--sm" href="#">Brunch</a></div>
```

## 7. Status tags and service status

**Purpose:** status by **fill + words**, never by a line colour. `.ny-tag` (22 px, 10.5 px uppercase; `--solid`, `--taxi`,
`--street`, `--good`, `--muted` dashed, `--alarm`, `--alarm-solid`, `--plain`, `--lg`) with `__dot`, `__ring` or a 12 px icon;
`.ny-tags` lays out a group. **Mapping (reservations):** Requested = `--muted` · Waitlist = outline + ring · Confirmed = `--solid`
+ dot · Seated = `--taxi` · Finished = `--good` + check · Late = `--alarm` + clock-alert · No-show = `--alarm-solid` · Cancelled =
`--muted` + `.ny-strike` · Private dining = `--plain` + lock. `--street` is for place labels (Patio, Bar, Nolita), like a sign.
**Service status** (one per restaurant, the subway board metaphor): `.ny-status--good` "Good service", `--delays` (taxi)
"Delays" when waits pass 20 minutes, `--planned` (ink ring) "Planned work" for a private event or a closed room, `--closed`
(alarm) "Closed"; always with an icon.

```html
<span class="ny-tag ny-tag--solid"><span class="ny-tag__dot"></span>Confirmed</span>
<span class="ny-tag ny-tag--taxi">Seated</span>
<span class="ny-tag"><span class="ny-tag__ring"></span>Waitlist</span>
<span class="ny-tag ny-tag--alarm"><!-- clock-alert --> 15 min late</span>
<span class="ny-tag ny-tag--muted"><span class="ny-strike">Cancelled</span></span>
<span class="ny-status ny-status--delays"><!-- clock --> Delays · 25 min wait</span>
<span class="ny-status ny-status--good"><!-- circle-check --> Good service</span>
```

## 8. Counts, dots, avatars, kbd

**Purpose:** small markers. `.ny-count` (+ `--alarm`, `--taxi`, `--outline`); `.ny-dot` (+ `--live` taxi with an ink edge,
`--good`, `--alarm`, `--ring`); `.ny-av` round initials (+ `--taxi`, `--paper`, `--serif` italic Didone; sizes `--sm` 22,
default 30, `--lg` 42, `--xl` 72), `.ny-avstack`, `.ny-person-inline`; `.ny-kbd`. **Do:** show the name next to an avatar
somewhere. **Don't:** use a bullet as an avatar — discs with letters are restaurants.

```html
<span class="ny-count ny-count--alarm">4</span>
<span class="ny-person-inline"><span class="ny-av ny-av--sm ny-av--taxi">RV</span>Rosa Valentine</span>
<span class="ny-avstack"><span class="ny-av">SF</span><span class="ny-av ny-av--paper">MO</span><span class="ny-av ny-av--paper">+6</span></span>
<span class="ny-row"><span class="ny-dot ny-dot--live"></span>Live</span> <span class="ny-kbd">/</span>
```

## 9. Ruled grid, cells and cards

**Purpose:** newspaper columns. `.ny-grid` is a 12-column grid with a 1 px gap over the hairline colour, so neighbours share
one rule (`--boxed` adds the frame, `--ink` uses ink rules). Children: `.ny-cell` with `.ny-span-2…12` (3 → 6 → 12 and 4/5 → 6 at
1180 px, all → 12 at 640 px), `--flush`, `--tight`; `.ny-cell__head` (`--rule` adds a 2 px rule) › `__title` (small caps, with
a bullet or icon) or `__headline` (Didone), `__link`; `__pad`. Add `.ny-taxi`, `.ny-night` or `.ny-inv` to a cell to make it
yellow, black or inverted. `.ny-card` is a clipping on a sheet (3 px ink top rule, hairline sides; `--flush`, `--lift`,
`__head`) for content outside the grid. **Do:** fill each row to 12 columns. **Don't:** add margins between cells.

```html
<div class="ny-grid">
  <section class="ny-cell ny-span-3 ny-taxi" aria-labelledby="k1"><div class="ny-stat"><p class="ny-stat__label"><span id="k1" class="ny-label ny-label--ink">Covers tonight</span><!-- users --></p><p class="ny-stat__value">412<span class="ny-stat__of">/474</span></p><p class="ny-stat__foot">87% of seats across five rooms</p></div></section>
  <section class="ny-cell ny-span-3">…</section>
  <section class="ny-cell ny-span-6"><div class="ny-cell__head ny-cell__head--rule"><h2 class="ny-cell__title"><!-- train-front --> Service status</h2><a class="ny-cell__link" href="#">All venues <!-- arrow-right --></a></div>…</section>
</div>
```

## 10. Stat, delta, meter, load row

**Purpose:** big numbers in the Didone. `.ny-stat` › `__label`, `__value` (Didone, lining tabular), `__unit` (raised "$"),
`__of` ("/474"), `__foot`; `.ny-delta` (green pill; `--down` alarm, `--flat`) with an arrow icon; `.ny-meter` (`--lg`) ›
`.ny-meter__fill` (ink; `--taxi`, `--alarm`, or a line `--a/m/g/l/7` when the bar belongs to a restaurant) + `.ny-w-0…100`;
`.ny-load` (bullet or avatar, name, meter, `__num`). **Do:** state the base ("of 474 seats"). **Don't:** let a meter be the
only copy of a number — print it.

```html
<div class="ny-stat"><p class="ny-stat__label"><span class="ny-label ny-label--ink">Revenue · Sunday</span></p>
  <p class="ny-stat__value"><span class="ny-stat__unit">$</span>48,920</p>
  <p class="ny-stat__foot"><span class="ny-delta"><!-- arrow-up-right --> 9%</span><span>vs. last Sunday</span></p></div>
<div class="ny-load"><span class="ny-bullet ny-bullet--g">G</span><span>Grand Street Grill</span><div class="ny-meter"><span class="ny-meter__fill ny-meter__fill--g ny-w-95"></span></div><span class="ny-load__num">104/110</span></div>
```

## 11. Service board

**Purpose:** the subway status board, one row per restaurant. `.ny-service` › `.ny-service__row` (bullet, `__name` with
`strong` + `span`, a `.ny-status`). **Do:** keep the five rows in the A–M–G–L–7 order everywhere.

```html
<div class="ny-service">
  <div class="ny-service__row"><span class="ny-bullet ny-bullet--md ny-bullet--m">M</span><p class="ny-service__name"><strong>Mulberry Trattoria</strong><span>86 of 90 seated · 11 on the waitlist</span></p><span class="ny-status ny-status--delays"><!-- clock --> Delays</span></div>
  <div class="ny-service__row"><span class="ny-bullet ny-bullet--md ny-bullet--7">7</span><p class="ny-service__name"><strong>Seventh Avenue Supper Club</strong><span>Private party in the back room until 10 PM</span></p><span class="ny-status ny-status--planned"><!-- construction --> Planned work</span></div>
</div>
```

## 12. Charts (inline SVG)

**Purpose:** bars and lines drawn from tokens. `.ny-chart-wrap` (scrolls on phones) › `svg.ny-chart` with `role="img"` and an
`aria-label` that reads the data. Parts: `.ny-ch-grid`, `.ny-ch-base`, `.ny-ch-axis` (+ `--now`), `.ny-ch-bar` (ink), `--soft`,
`--now` (taxi with ink edge), `--open` (hatched: needs `#ny-hatch` in the page's `svg.ny-defs`, its strokes use `.ny-ch-hatch`),
`.ny-ch-val`, `.ny-ch-line` with one line per restaurant (`--a/m/g/l/7`; `--dash` for last week), `.ny-ch-area` (taxi wash under
one line), `.ny-ch-dot`, line-end discs `.ny-ch-disc--a…7` + `.ny-ch-disctext` (`--w` white letter for A and 7, `--k` black for
M, G, L), `.ny-ch-end` (end label), `.ny-ch-now` (dashed "now" rule) + `.ny-ch-flag` / `.ny-ch-flagtext`. `.ny-legend` ›
`__item` › `__key` (`--soft`, `--hatch`, `--taxi`) or a `.ny-bullet--sm`. **Do:** end every restaurant line in its lettered
disc — M, G and L are under 3:1 on newsprint, the disc is what identifies them. **Don't:** use taxi for a thin line on paper.

```html
<div class="ny-chart-wrap"><svg class="ny-chart" viewBox="0 0 640 240" role="img" aria-label="Covers per night, Mon to Sun: Mulberry Trattoria 72, 80, 84, 88, 90, 90, 86; Ludlow Counter 30, 34, 31, 40, 46, 48, 44">
  <line x1="40" y1="210" x2="600" y2="210" class="ny-ch-base"/><text x="32" y="214" text-anchor="end" class="ny-ch-axis">0</text>
  <polyline points="60,62 150,48 240,41 330,34 420,30 510,30 580,37" class="ny-ch-line ny-ch-line--m"/>
  <circle cx="600" cy="37" r="10" class="ny-ch-disc--m"/><text x="600" y="41" class="ny-ch-disctext ny-ch-disctext--k">M</text>
  <line x1="420" y1="20" x2="420" y2="210" class="ny-ch-now"/><text x="420" y="228" text-anchor="middle" class="ny-ch-axis ny-ch-axis--now">FRI</text></svg></div>
<p class="ny-legend"><span class="ny-legend__item"><span class="ny-bullet ny-bullet--sm ny-bullet--m">M</span>Mulberry Trattoria</span><span class="ny-legend__item"><span class="ny-legend__key ny-legend__key--taxi"></span>Tonight</span></p>
```

## 13. Toolbar, chips, section strip, bulk bar

**Purpose:** find and act on many rows. `.ny-strip` › `.ny-strip__item` (small caps + Didone `__num`; `.is-active` = 3 px ink
underline) works as status tabs, like newspaper sections. `.ny-toolbar` › `.ny-search` (`--box` for a squared field),
`.ny-chip` (pill; a bullet inside for a restaurant filter; `.is-hover`), `.ny-chip--applied` (ink, with `.ny-chip__x`),
`.ny-chip--dashed` ("Add filter"), `.ny-selectbtn` (label + value + chevron), `__end`. `.ny-bulkbar` (black with a taxi edge) ›
`__count` (`strong` in taxi), small buttons, `__end`. **Do:** show the bulk bar only while rows are selected.

```html
<nav class="ny-strip" aria-label="Reservation status"><a class="ny-strip__item is-active" href="#" aria-current="page">All <span class="ny-strip__num">184</span></a><a class="ny-strip__item" href="#">Waitlist <span class="ny-strip__num">11</span></a></nav>
<div class="ny-toolbar" role="search">
  <label class="ny-search ny-search--box"><!-- search --><span class="visually-hidden">Search reservations</span><input class="ny-search__input" type="search" placeholder="Guest, phone, table"></label>
  <span class="ny-chip ny-chip--applied"><span class="ny-bullet ny-bullet--m">M</span>Mulberry Trattoria<button type="button" class="ny-chip__x" aria-label="Remove filter"><!-- x --></button></span>
  <button type="button" class="ny-chip ny-chip--dashed"><!-- plus --> Add filter</button>
  <div class="ny-toolbar__end"><button type="button" class="ny-selectbtn"><span class="ny-label">Sort</span>Time, earliest <!-- chevron-down --></button></div>
</div>
<div class="ny-bulkbar" role="region" aria-label="Bulk actions"><p class="ny-bulkbar__count"><strong>3</strong> reservations selected</p><div class="ny-bulkbar__end"><button type="button" class="ny-btn ny-btn--sm">Send reminder</button></div></div>
```

## 14. Table

**Purpose:** dense, ruled data. `.ny-table-wrap` (scrolls horizontally) › `table.ny-table` (first and last columns align with
the page padding; `--inset` aligns to a cell's padding; `--compact`). Heads are small caps over a 2 px ink rule;
`.ny-table__sort` inside the `th` (+ `aria-sort`); cells `.ny-table__check`, `.ny-table__primary` (`strong` + `span`),
`.ny-table__name` (Didone, for a dish or a venue), `.ny-num` (right, tabular), `.ny-table__actions`; rows `.is-selected` (soft
taxi + 4 px taxi bar) and `.is-hover`; `tfoot` totals over a 2 px rule. Select-all uses `.ny-checkbox.is-mixed`. **Do:**
right-align money and covers. **Don't:** zebra-stripe.

```html
<div class="ny-table-wrap"><table class="ny-table">
  <thead><tr><th scope="col" class="ny-table__check"><input class="ny-checkbox is-mixed" type="checkbox" aria-label="Select all"></th><th scope="col" aria-sort="ascending"><a class="ny-table__sort" href="#">Time <!-- arrow-down --></a></th><th scope="col">Guest</th><th scope="col">Restaurant</th><th scope="col" class="ny-num">Party</th><th scope="col">Status</th><th scope="col" class="ny-table__actions"><span class="visually-hidden">Actions</span></th></tr></thead>
  <tbody><tr class="is-selected"><td><input class="ny-checkbox" type="checkbox" checked aria-label="Select Okafor, 7:30 PM"></td><td class="ny-tnum">7:30 PM</td><td><span class="ny-table__primary"><strong>Adaeze Okafor</strong><span>(646) 555-0148 · anniversary</span></span></td><td><span class="ny-row"><span class="ny-bullet ny-bullet--sm ny-bullet--m">M</span>Mulberry Trattoria</span></td><td class="ny-num">4</td><td><span class="ny-tag ny-tag--solid"><span class="ny-tag__dot"></span>Confirmed</span></td><td class="ny-table__actions"><button type="button" class="ny-btn ny-btn--ghost ny-btn--sm ny-btn--icon" aria-label="Actions for Okafor"><!-- ellipsis --></button></td></tr></tbody>
</table></div>
```

## 15. Pagination

**Purpose:** page through results. `.ny-pager` › info text (`strong` for numbers) + `.ny-pager__pages` › round `.ny-pager__page`
(`.is-active` ink + `aria-current="page"`) and `.ny-pager__gap`.

```html
<nav class="ny-pager" aria-label="Pagination"><p>Showing <strong>1–25</strong> of <strong>184</strong></p>
  <div class="ny-pager__pages"><a class="ny-pager__page" href="#" aria-label="Previous page"><!-- chevron-left --></a><a class="ny-pager__page is-active" href="#" aria-current="page">1</a><a class="ny-pager__page" href="#">2</a><span class="ny-pager__gap">…</span><a class="ny-pager__page" href="#">8</a><a class="ny-pager__page" href="#" aria-label="Next page"><!-- chevron-right --></a></div></nav>
```

## 16. Form fields and form sections

**Purpose:** forms set like an editorial page: the section head left (italic Didone number, Didone title, description), fields
right. `.ny-form` › `.ny-formsec` (4/8 split) › `__aside` (`__num`, `__title`, `__desc`) + `__body` (6 columns; `.ny-fs-2/3/4/6`
spans). `.ny-field` › `label.ny-field__label` (`.ny-req` "*" + visually-hidden "(required)", or `.ny-field__opt`) + control +
`.ny-help` or `.ny-error` (icon + text, linked by `aria-describedby`). Controls: `.ny-input` (`--lg`, `--serif` for a dish
name), `.ny-select` (native, drawn chevron), `.ny-textarea`, `.ny-inputgroup` › `__affix` ("$", "covers"). **States:**
`.is-error` (alarm border, soft red), `[disabled]` (dashed), `.is-focus`. Above the form, `.ny-errsum` lists what to fix.
**Don't:** use placeholder text as the label.

```html
<section class="ny-formsec" aria-labelledby="s2">
  <div class="ny-formsec__aside"><p class="ny-formsec__num" aria-hidden="true">No. 2</p><h2 id="s2" class="ny-formsec__title">Party &amp; table</h2><p class="ny-formsec__desc">Large parties over 8 go to private dining.</p></div>
  <div class="ny-formsec__body">
    <div class="ny-field ny-fs-3"><label class="ny-field__label" for="f-date"><span>Date<span class="ny-req" aria-hidden="true">*</span><span class="visually-hidden"> (required)</span></span></label><input id="f-date" class="ny-input" type="text" value="Fri., Oct. 16, 2026"></div>
    <div class="ny-field ny-fs-3 is-error"><label class="ny-field__label" for="f-time">Time</label><input id="f-time" class="ny-input is-error" type="text" value="11:45 PM" aria-invalid="true" aria-describedby="f-time-e"><p id="f-time-e" class="ny-error"><!-- circle-alert -->The kitchen closes at 11:00 PM on Fridays.</p></div>
    <div class="ny-field ny-fs-3"><label class="ny-field__label" for="f-dep">Deposit</label><span class="ny-inputgroup"><span class="ny-inputgroup__affix">$</span><input id="f-dep" class="ny-input ny-tnum" type="text" value="200.00"></span><p class="ny-help">$25 a head for parties of 8 or more.</p></div>
    <div class="ny-field ny-fs-3"><label class="ny-field__label" for="f-ref">Reference</label><input id="f-ref" class="ny-input" type="text" value="MT-2026-1016-07" disabled></div>
  </div>
</section>
```

## 17. Checkbox, radio, switch, check grid, segmented

**Purpose:** choices. `input.ny-checkbox` (taxi when checked, ink edge; `.is-mixed`), `input.ny-radio`, inside `label.ny-check`
› `.ny-check__text` (+ `small`); `label.ny-switch` › `input.ny-switch__input` (`role="switch"`, taxi track when on) + text;
`.ny-checkgrid` lays checks in bordered tiles (a checked tile tints soft taxi); `.ny-seg` › `label.ny-seg__opt` › radio input
(checked or `.is-active` = ink) for party size, service or view. Disabled = dashed.

```html
<label class="ny-check"><input class="ny-checkbox" type="checkbox" checked><span class="ny-check__text">Text the guest a reminder<small>At 3:00 PM on the day</small></span></label>
<fieldset class="ny-field"><legend class="ny-field__label">Party size</legend><div class="ny-seg">
  <label class="ny-seg__opt"><input type="radio" name="party" value="2">2</label><label class="ny-seg__opt"><input type="radio" name="party" value="4" checked>4</label><label class="ny-seg__opt"><input type="radio" name="party" value="6">6</label><label class="ny-seg__opt"><input type="radio" name="party" value="8">8+</label></div></fieldset>
<label class="ny-switch"><input class="ny-switch__input" type="checkbox" role="switch" checked>Accept walk-ins at the bar</label>
```

## 18. Drop zone, file list, form bar

**Purpose:** uploads and saving. `label.ny-drop` (dashed; `.is-dragover` = soft taxi, solid edge) › icon, `.ny-drop__title`,
help, visually-hidden file input; `.ny-files` › `.ny-file` (`__thumb` with a small drawn SVG, name + meta, an action).
`.ny-formbar` is sticky at the bottom over a 2 px rule: status text + `__end` actions.

```html
<label class="ny-drop" for="files"><!-- upload --><span class="ny-drop__title">Drop the floor plan here or <u>browse</u></span><span class="ny-help">PDF · JPG · up to 20 MB</span><input id="files" class="visually-hidden" type="file"></label>
<ul class="ny-files"><li class="ny-file"><span class="ny-file__thumb"><!-- tiny svg --></span><span class="ny-stack ny-stack--4"><strong>back-room-plan.pdf</strong><span class="ny-help">1.2 MB · uploaded 2:14 PM</span></span><span class="ny-tag ny-tag--good"><!-- check --> Ready</span><button type="button" class="ny-btn ny-btn--ghost ny-btn--sm ny-btn--icon" aria-label="Remove file"><!-- x --></button></li></ul>
<div class="ny-formbar"><p class="ny-help"><!-- circle-check --> Draft saved 2:28 PM</p><div class="ny-formbar__end"><a class="ny-btn ny-btn--ghost" href="#">Cancel</a><button type="submit" class="ny-btn ny-btn--taxi">Confirm reservation</button></div></div>
```

## 19. Dropdown menu and tooltip

**Purpose:** row actions, user menu. `.ny-pop` › trigger (`aria-expanded="true"`) + `.ny-menu` (`role="menu"`, sheet, 1 px ink,
lift; `--left`, `--static` for documentation) › `__label`, `__item` (hover = taxi; `--danger`, red on hover), `__kbd`, `__sep`.
Tooltip: `.ny-tip` (`role="tooltip"`, black with a pointer). **Do:** open menus on an upper row — the table wrapper clips
anything below its last row.

```html
<div class="ny-pop"><button type="button" class="ny-btn ny-btn--ghost ny-btn--sm ny-btn--icon is-active" aria-label="Actions for Okafor" aria-expanded="true"><!-- ellipsis --></button>
  <div class="ny-menu" role="menu" aria-label="Okafor, 7:30 PM"><p class="ny-menu__label">Okafor · party of 4</p>
    <a class="ny-menu__item" role="menuitem" href="#"><!-- armchair --> Seat now<span class="ny-menu__kbd">S</span></a>
    <a class="ny-menu__item" role="menuitem" href="#"><!-- message-square --> Text the guest</a>
    <div class="ny-menu__sep" role="separator"></div>
    <a class="ny-menu__item ny-menu__item--danger" role="menuitem" href="#"><!-- user-x --> Mark no-show</a></div></div>
<span class="ny-tip" role="tooltip">Regular · 14 visits since 2023</span>
```

## 20. Dialog

**Purpose:** focused tasks and confirmations. `.ny-overlay` (fixed, dim backdrop; `--static` in documentation) ›
`section.ny-dialog` (`role="dialog"`, `aria-modal`, `aria-labelledby`; `--wide`) › `__head` (a signage band: black, white
stripe, `h2.__title` in Helvetica, round `__close`), `__body`, `__foot` (the first child sits left, actions right).
**Don't:** stack two dialogs.

```html
<div class="ny-overlay"><section class="ny-dialog" role="dialog" aria-modal="true" aria-labelledby="dlg">
  <header class="ny-dialog__head"><span class="ny-bullet ny-bullet--md ny-bullet--m">M</span><h2 id="dlg" class="ny-dialog__title">New reservation</h2><button type="button" class="ny-dialog__close" aria-label="Close dialog"><!-- x --></button></header>
  <div class="ny-dialog__body">…fields…</div>
  <footer class="ny-dialog__foot"><span class="ny-help">Table 12 · window</span><button type="button" class="ny-btn ny-btn--ghost">Cancel</button><button type="button" class="ny-btn ny-btn--taxi">Book the table</button></footer>
</section></div>
```

## 21. Toast and notice

**Purpose:** feedback. `.ny-toasts` (fixed bottom-right; `--static` in docs) › `.ny-toast` (black signage with the stripe) ›
icon, body (`__title`, `__text`, `__actions` with taxi links), `__close`. Inline `.ny-notice` (`--taxi`, `--alarm` and `--good`
with a 5 px bar, `--quiet`) › icon + `__body` › `__title`. Use `role="status"` for success, `role="alert"` for failures.

```html
<div class="ny-toasts" role="status"><div class="ny-toast"><!-- circle-check --><div><p class="ny-toast__title">Okafor seated at table 12</p><p class="ny-toast__text">Mulberry Trattoria · 7:34 PM</p><p class="ny-toast__actions"><a href="#">Undo</a></p></div><button type="button" class="ny-toast__close" aria-label="Dismiss"><!-- x --></button></div></div>
<div class="ny-notice ny-notice--alarm" role="alert"><!-- octagon-alert --><div class="ny-notice__body"><span class="ny-notice__title">4 dishes 86'd at Grand Street Grill</span>The porterhouse for two sold out at 7:10 PM.</div></div>
<div class="ny-notice ny-notice--taxi"><!-- construction --><div class="ny-notice__body"><span class="ny-notice__title">Service change</span>The Supper Club back room is closed for a private party until 10 PM.</div></div>
```

## 22. Tabs and index nav

**Purpose:** sub-navigation. `.ny-tabs` › `.ny-tab` (`.is-active` = 3 px ink underline, `aria-current`). Settings use `.ny-split`
› `nav.ny-index` › `__list` (sticky) › `.ny-index__item` (`__num` italic Didone, label, optional count; `.is-active` = soft taxi +
4 px taxi bar); on phones it becomes a horizontal scroller.

```html
<nav class="ny-tabs" aria-label="Service"><a class="ny-tab is-active" href="#" aria-current="page">Dinner</a><a class="ny-tab" href="#">Brunch</a><a class="ny-tab" href="#">Private dining</a></nav>
<div class="ny-split"><nav class="ny-index" aria-label="Settings sections"><div class="ny-index__list"><a class="ny-index__item is-active" href="#group" aria-current="true"><span class="ny-index__num">1</span>Group</a><a class="ny-index__item" href="#venues"><span class="ny-index__num">2</span>Venues<span class="ny-count ny-count--outline">5</span></a></div></nav><div>…sections…</div></div>
```

## 23. Tonight's book (timeline)

**Purpose:** the evening of every restaurant at a glance. `.ny-tl-wrap` (scrolls) › `.ny-tl` (`role="table"`, min 860 px; 12
half-hour columns from 5:00 PM to 11:00 PM) › `__head` (`__corner` + `__hour` cells — `.ny-len-2` makes one span an hour —
`.is-now` taxi) and `__row`s (`__venue` with a bullet, `strong` + `span`, then blocks). Blocks `.ny-tl__block` with the
restaurant's edge `--a/m/g/l/7`, `--private` (ink, taxi bar), `--closed` (hatched), `--peak` (taxi: the rush), placed with
`.ny-from-1…12` (half-hour, 1 = 5:00 PM) + `.ny-len-1…12`; `.ny-tl__r2` puts a block on a second line; `strong` +
`.ny-tl__meta`. The now line is `.ny-tl__nowline.ny-at-N` (N half-hours after 5:00 PM, `.ny-at-half` adds a quarter hour).

```html
<div class="ny-tl-wrap"><div class="ny-tl" role="table" aria-label="Tonight's book, Monday, Oct. 12">
  <div class="ny-tl__head" role="row"><span class="ny-tl__corner ny-label" role="columnheader">Restaurant</span><span class="ny-tl__hour ny-len-2" role="columnheader">5 PM</span><span class="ny-tl__hour ny-len-2" role="columnheader">6 PM</span><span class="ny-tl__hour ny-len-2 is-now" role="columnheader">7 PM</span>…</div>
  <div class="ny-tl__row" role="row"><p class="ny-tl__venue" role="rowheader"><span class="ny-bullet ny-bullet--m">M</span><span><strong>Mulberry Trattoria</strong><span>90 covers</span></span></p>
    <p class="ny-tl__block ny-tl__block--m ny-from-3 ny-len-2" role="cell"><strong>First seating</strong><span class="ny-tl__meta">38 covers</span></p>
    <p class="ny-tl__block ny-tl__block--peak ny-tl__block--m ny-from-5 ny-len-4" role="cell"><strong>The rush</strong><span class="ny-tl__meta">86 seated · 11 waiting</span></p>
    <p class="ny-tl__block ny-tl__block--private ny-tl__r2 ny-from-6 ny-len-5" role="cell"><strong>Ferrante wedding, back room</strong><span class="ny-tl__meta">22 guests · 7:30–10:00 PM</span></p></div>
  <span class="ny-tl__nowline ny-at-5 ny-at-half" aria-hidden="true"></span>
</div></div>
```

## 24. Activity log, definition lists, 86 board

`.ny-log` › `__item` (`__time`, a bullet or avatar, `__text` with `strong` and `__sub`) · `.ny-dl` (small-caps `dt`, value `dd`;
stacks on phones) · `.ny-board` › `__item` (bullet, `__dish` struck through in red, `__note` with the time it ran out).

```html
<ol class="ny-log"><li class="ny-log__item"><span class="ny-log__time">7:34 PM</span><span class="ny-bullet ny-bullet--sm ny-bullet--m">M</span><p class="ny-log__text"><strong>Sal Ferrante</strong> seated Okafor, party of 4<span class="ny-log__sub">Table 12 · 4 min after booking time</span></p></li></ol>
<dl class="ny-dl"><dt>Covers</dt><dd>110</dd><dt>Private room</dt><dd>The Vault, 24 seated</dd></dl>
<ul class="ny-board"><li class="ny-board__item"><span class="ny-bullet ny-bullet--g">G</span><span class="ny-board__dish">Porterhouse for two</span><span class="ny-board__note">86 at 7:10 PM</span></li></ul>
```

## 25. Photo placeholders (duotone SVG)

**Purpose:** pictures without loading anything. `figure.ny-photo` (4:3 default; `--169`, `--sq`, `--tall` 4:5, `--wide` 21:9) ›
`svg` (`viewBox`, `preserveAspectRatio="xMidYMid slice"`, `role="img"`, an `aria-label` that describes the picture) + optional
`figcaption.ny-photo__cap` (black label on the picture), or a `.ny-caption` below (italic text serif + `__credit`). Shapes use
the fixed fills `.ny-ph-k` (ink), `-d` (dark), `-g` (grey), `-s` (stone), `-p` (newsprint), `-w` (white), `-t` (taxi), `-b`
(brick), `-sky`, and strokes `.ny-ph-line-k / -p / -t`. Scenes in the kit: storefront at night, dining room, oysters on ice,
plated dish, bar, kitchen pass, portrait, street with a cab. Photos keep their colours in dark mode. **Don't:** use
`<img src="https://…">`.

```html
<figure class="ny-photo ny-photo--169"><svg viewBox="0 0 400 225" preserveAspectRatio="xMidYMid slice" role="img" aria-label="Photo: a dozen oysters on crushed ice under a lamp at the Astor raw bar.">
  <rect width="400" height="225" class="ny-ph-d"/><ellipse cx="200" cy="150" rx="150" ry="52" class="ny-ph-s"/><g class="ny-ph-p"><ellipse cx="150" cy="140" rx="26" ry="14"/><ellipse cx="214" cy="132" rx="26" ry="14"/></g><circle cx="200" cy="20" r="12" class="ny-ph-t"/></svg>
  <figcaption class="ny-photo__cap">Fig. 3 — Astor Oyster Room</figcaption></figure>
<p class="ny-caption">Oysters from Peconic Bay, Oct. 2026.<span class="ny-caption__credit">Photo · M&amp;G</span></p>
```

## 26. Public site blocks

`.ny-site` › `.ny-zipper` + `header.ny-sitehead` (3 columns: `nav.ny-sitenav` › `__link` (`.is-active` taxi underline),
`__brand` centred logo, `__end` with `__phone` and the Reserve button; `__menu` on phones) · `.ny-masthead` › `__ears`
(`.ny-ear`, `--right`, either side of `__name` › `.ny-nameplate`) + `.ny-folio` · `.ny-hero` › `__copy` (7 columns, ruled right;
kicker, display, dek, `__actions`, `__facts` › `__fact` with a Didone `strong`) + `__media` (5) · `.ny-sec` (`--tight`, `--rule`)
› `.ny-sechead` (double rule on top; `__title`, `__aside`) · `.ny-venues` › `a.ny-venue` (`__top` bullet + status, `__name`,
`__what`, `__meta`, `__foot`) · `.ny-platform` › `__row` of `.ny-band`s (the platform signs) · `.ny-story` › `__main`
(`__headline`, `.ny-prose` with `.ny-dropcap`) + `__aside` (`.ny-quote`) · `.ny-press` › `__item` (`__outlet`, `__quote`,
`.ny-stars`) · `.ny-map` › `svg` (`.ny-map-water`, `-land`, `-block`, `-park`, `-street`, `-avenue`, `-route--a…7`, `-stop`,
`-label` + `--muted` / `--water`) · `.ny-feature` › `__media` + `__body` · `.ny-newsletter` › copy + `__form` (label + input +
button) · `footer.ny-sitefoot.ny-night` › `__cols` › `__col` (`--wide`), `__plate` (street signs), `__bottom`.

```html
<section class="ny-sec" aria-labelledby="v"><div class="ny-sechead"><div class="ny-sechead__title"><p class="ny-kicker">The lines</p><h2 id="v" class="ny-h2">Five restaurants, <em>one</em> neighbourhood.</h2></div><p class="ny-sechead__aside">All within a ten-minute walk of Grand St station.</p></div>
  <div class="ny-venues"><a class="ny-venue" href="menu.html"><span class="ny-venue__top"><span class="ny-bullet ny-bullet--lg ny-bullet--m">M</span><span class="ny-status ny-status--good"><!-- circle-check --> Open</span></span><span class="ny-venue__name">Mulberry Trattoria</span><span class="ny-venue__what">Red sauce, since the first pot.</span><span class="ny-venue__meta"><span>156 Mulberry St</span><span>(212) 555-0156</span></span><span class="ny-venue__foot"><span class="ny-label">90 covers</span><!-- arrow-right --></span></a>…</div></section>
```

## 27. The carte, reservation panel, hours

**Purpose:** the printed menu and the way to book. `.ny-carte` › `.ny-course` › `__head` (double rules either side of
`h2.ny-course__title`), `__note`, `.ny-dish` (`--signature` adds a taxi dot; `.is-86` strikes name and price) › `__name` (small
caps, with `.ny-diet` marks such as V, GF), `__leader` (dotted), `__price` (Didone), `__desc` (italic text serif); `.ny-diets`
explains the marks. `.ny-reserve` (sticky) › `.ny-seg` for party size, `.ny-slots` › `.ny-slot` (`.is-selected` taxi, `.is-full`
dashed and struck), `dl.ny-hours` (`.is-today` on the row).

```html
<section class="ny-course" aria-labelledby="c2"><div class="ny-course__head"><h2 id="c2" class="ny-course__title">Secondi</h2></div><p class="ny-course__note">Served with escarole or roasted potatoes.</p>
  <div class="ny-dish ny-dish--signature"><span class="ny-dish__name">Chicken scarpariello <span class="ny-diet" title="Gluten free">GF</span></span><span class="ny-dish__leader" aria-hidden="true"></span><span class="ny-dish__price">34</span><p class="ny-dish__desc">Sausage, cherry peppers, white wine, the pan juices.</p></div>
  <div class="ny-dish is-86"><span class="ny-dish__name">Veal chop parmigiana</span><span class="ny-dish__leader" aria-hidden="true"></span><span class="ny-dish__price">48</span><p class="ny-dish__desc">Sold out tonight.</p></div></section>
<div class="ny-slots" role="group" aria-label="Times on Fri., Oct. 16"><a class="ny-slot" href="#">6:30</a><a class="ny-slot is-selected" href="#" aria-current="true">7:00</a><span class="ny-slot is-full" aria-disabled="true">7:30</span></div>
<dl class="ny-hours"><dt>Mon–Thu</dt><dd>5:00–10:30 PM</dd><dt class="is-today">Fri</dt><dd class="is-today">5:00–11:00 PM</dd></dl>
```

## 28. Empty state and error page

`.ny-empty` (centred: `svg.ny-empty__art` drawn with `.ny-e-plate`, `.ny-e-rim`, `.ny-e-ink`, `.ny-e-taxi`, or a Didone
`__num`; `__title`, `__text`, `.ny-actions`); `.ny-empty--compact` inside cells. `.ny-err` › `__main` (`__code` — Didone digits
with a bullet for the zero — then copy) + `__aside` › `.ny-ways` › `a.ny-way` (bullet, `strong` + `span`, arrow). **Do:** say why
it is empty and offer the next step.

```html
<section class="ny-empty" aria-labelledby="e"><svg class="ny-empty__art" viewBox="0 0 220 140" role="img" aria-label="An empty plate with a folded napkin."><ellipse cx="110" cy="80" rx="90" ry="44" class="ny-e-plate"/><ellipse cx="110" cy="80" rx="60" ry="28" class="ny-e-rim"/><rect x="150" y="30" width="34" height="22" class="ny-e-taxi"/></svg>
  <p class="ny-kicker ny-kicker--muted">Venues · private dining</p><h2 id="e" class="ny-empty__title">No private-dining rooms yet.</h2>
  <p class="ny-empty__text">Add the back room at the Supper Club or the Vault at Grand Street to take parties of 12 and more.</p><div class="ny-actions"><a class="ny-btn ny-btn--taxi" href="#"><!-- plus --> Add a room</a></div></section>
<h1 class="ny-err__code"><span class="visually-hidden">Error 404</span><span aria-hidden="true">4</span><span class="ny-bullet ny-bullet--xxl ny-bullet--ring" aria-hidden="true">0</span><span aria-hidden="true">4</span></h1>
```

## 29. Sign-in and drawn fields

`.ny-auth` (7/5 split) › `aside.ny-auth__art.ny-night` (`__arttop` with the logo, `__headline` with a taxi `em`, the five bullets,
a photo, `__bands` of `.ny-band`s) + `main.ny-auth__panel` › `section.ny-auth__form` (`__row`, `__or`) + `__foot`. Credentials
are drawn: `.mock-label` + `.mock-field` (`role="img"`, `aria-label`), `--secret`, `--empty`, `--focus` (focus ring + caret),
`--error`. The submit is a link with `role="button"`; no `<form>`, no text `<input>`.

```html
<div><span class="mock-label">Email</span><div class="mock-field" role="img" aria-label="Email field, filled in">rosa.valentine@mulberryandgrand.com</div></div>
<div><span class="mock-label">Password</span><div class="mock-field mock-field--secret mock-field--focus" role="img" aria-label="Password field, filled in">••••••••••••</div></div>
<a href="dashboard.html" class="ny-btn ny-btn--taxi ny-btn--lg ny-btn--block ny-btn--between" role="button">Sign in <!-- arrow-right --></a>
```

## Utilities

`.ny-wrap` (max 1440, gutter; `--narrow` 920), `.ny-cols` + `.ny-col-2…9`, `.ny-col-12` (12-col gap grid), `.ny-row` (`--top`,
`--base`, `--wrap`, `--between`, `--end`, `--center`), `.ny-stack` (`--4`, `--12`, `--16`, `--24`, `--32`), `.ny-gap-4/12/16/24`,
`.ny-mt-4/8/12/16/24/32/auto`, `.ny-ml-auto`, `.ny-fill`, `.ny-none`, `.ny-right`, `.ny-center`, `.ny-nowrap`, `.ny-truncate`,
`.ny-block`, `.ny-relative`, `.ny-maxw-40/60`, `.ny-bt` / `.ny-bb` (ink) and `.ny-bt-soft` / `.ny-bb-soft`, `.ny-pt-16`,
`.ny-p-0`, `.ny-w-0…100` (steps of 5), `.ny-only-sm`, `.ny-hide-sm`, `.ny-actions`, `.ny-skip`, `.visually-hidden`.
Documentation helpers: `.ny-demo*`, `.ny-cat__*`, `.ny-swatch*` / `.ny-sw-*`, `.ny-specimen*`, `.ny-screen*`, `.ny-grouphead`.
