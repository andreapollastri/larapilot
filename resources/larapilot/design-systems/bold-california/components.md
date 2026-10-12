# BOLD California — components

Every class lives in `tokens.css`; `html/components.html` draws the components below in their variants and states, with
the same numbers. Icons are **Lucide** (ISC) inline SVG drawn with `currentColor`: `<svg class="bc-i" viewBox="0 0 24 24"
aria-hidden="true">…paths…</svg>` — 20 px by default, `bc-i--xs` 14, `--sm` 16, `--lg` 24, `--xl` 32, `--bold` for a 2.5
stroke on buttons. Illustrations are inline SVG built from the fill classes `bc-f-*` and stroke classes `bc-s-*` (see 33).

State classes used across components: `is-active` (current nav item, tab, page; always with `aria-current`), `is-selected`
(chip, row, choice, theme card), `is-hover`, `is-focus`, `is-pressed` (static demos of interaction), `is-error` (fields),
`is-now` / `is-done` (schedule slots), `[disabled]` / `is-disabled`.

**House rules.** One sticker (ink outline + offset shadow) per group of things, never on every card. Sunset orange is the
primary action; the other hues are status or decoration. Text sits on hue *fills* in ink (`#22140f`), never white — white
text is reserved for the deep-sea gradient and the poppy danger button. Gradients: hero art, featured plan, swell card,
sign-in panel, nothing else.

## Mapping table — mockup → real code

| Mockup | Blade + Tailwind v4 (suggested component) | Livewire / Flux equivalent |
| --- | --- | --- |
| `.bc-app`, `.bc-sidebar`, `.bc-topbar` | `<x-bc.layout.app>` with `<x-bc.sidebar>` slot | layout `components/layouts/app.blade.php` + `flux:sidebar` restyled |
| `.bc-btn--*` | `<x-bc.button variant="primary" size="lg">` | `flux:button` with `variant` mapped to the classes |
| `.bc-badge--*` | `<x-bc.badge tone="palm">` — tone from a PHP enum (`BookingStatus::color()`) | `flux:badge color="…"` |
| `.bc-card`, `.bc-stat` | `<x-bc.card variant="sun">`, `<x-bc.stat :value :delta>` | Livewire component per widget (`#[Lazy]`) |
| `.bc-table` + toolbar + bulk bar | `<x-bc.table>` fed by a Livewire table (`WithPagination`, `#[Url]` filters) | `flux:table` restyled |
| `.bc-input`, `.bc-select`, `.bc-switch` | `<x-bc.input wire:model>` with `@error` → `is-error` + `.bc-error` | `flux:input`, `flux:select`, `flux:switch` |
| `.bc-modal`, `.bc-menu`, `.bc-toast` | Alpine `x-show` / `x-trap` around the same markup | `flux:modal`, `flux:dropdown`, `flux:toast` |
| `.bc-ticker`, `.bc-swell` | `<x-bc.ticker :items>`, `<x-bc.swell :report>` | plain Blade, data from a service class |

---

## 00. Tokens, type and colour

**Purpose:** the vocabulary every component reads. `--bc-*` custom properties on `:root`, re-declared for dark.
**Anatomy:** `.bc-display` (`--md`) 900 weight, −0.055 em tracking, 0.9 line height · `.bc-h1` 44 px · `.bc-h2` 28 px ·
`.bc-h3` 20 px · `.bc-h4` 17 px · `.bc-eyebrow` (`--sunset`, `--sea`, `--dot`) 12 px 800 uppercase +0.12 em · `.bc-lead`
(`--lg`) · `.bc-muted`, `.bc-subtle`, `.bc-small`, `.bc-xs`, `.bc-strong`, `.bc-mono`, `.bc-num` (tabular) · `.bc-link` ·
`.bc-mark` (gold highlighter) · `.bc-swash` (orange brush stroke under one word of a headline) · `.bc-text-sunset|sea|palm|poppy`.
**Do:** one display headline per screen, end it with a full stop in sunset (`.bc-text-sunset`). **Don't:** set body copy in
the display face or use `.bc-swash` on more than one word.

```html
<span class="bc-eyebrow bc-eyebrow--dot">Thursday, October 15 · Encinitas</span>
<h1 class="bc-display">Surf <span class="bc-swash">every</span> Sunday<span class="bc-text-sunset">.</span></h1>
<p class="bc-lead">Lessons, rentals and good company. <span class="bc-mark">Stand up on day one.</span></p>
```

## 01. Buttons

**Purpose:** actions. Pills, 44 px (`--sm` 36, `--lg` 52, `--xl` 62 for heroes), 15 px 800.
**Variants:** `--primary` (sunset fill, ink outline, 3 px sticker shadow — the one main action per view), `--sun`, `--sea`
(secondary stickers for marketing), `--ink` (inverts in dark), `--outline`, `--soft`, `--ghost`, `--danger` (poppy, white
text in light, ink in dark), `--danger-ghost`; modifiers `--icon` (square), `--block`; `.bc-btn__count` for a counter.
**States:** `:hover`/`.is-hover` lifts 1 px, `:active`/`.is-pressed` presses into the shadow, `.is-focus`/`:focus-visible`
3 px ocean-blue ring, `[disabled]` 45 % and no shadow.
**Do:** label with a verb ("Save & publish", "Check spots"); give icon-only buttons an `aria-label`.
**Don't:** put two primary buttons side by side, or a sticker shadow on ghost/soft buttons.

```html
<a class="bc-btn bc-btn--primary" href="resource-form.html"><svg class="bc-i bc-i--bold" …/>New booking</a>
<button type="button" class="bc-btn bc-btn--outline">Save draft</button>
<button type="button" class="bc-btn bc-btn--ghost bc-btn--icon" aria-label="Notifications, 3 unread"><svg class="bc-i" …/></button>
<button type="button" class="bc-btn bc-btn--danger">Cancel and refund</button>
<button type="button" class="bc-btn bc-btn--primary" disabled>Saving…</button>
```

## 02. Status badges

**Purpose:** a record's state in tables, lists and headers. 26 px pills, 12 px 800, a dot in `currentColor`.
**Tones:** `--palm` (confirmed, paid, done), `--sun` (unpaid, pending, prepping), `--sea` (in progress, checked out),
`--ocean` (waitlist, info), `--poppy` (cancelled, failed), `--sunset` (new), `--hibiscus` (gift, promo), neutral default;
`--solid`, `--outline`, `--plain` (no dot), `--lg`.
**Do:** map each domain status to one tone once (an enum method) and reuse it. **Don't:** rely on colour alone — the word
is the information; the tone is a hint.

```html
<span class="bc-badge bc-badge--palm">Confirmed</span>
<span class="bc-badge bc-badge--sun">Unpaid</span>
<span class="bc-badge bc-badge--ocean">Waitlist #2</span>
<span class="bc-badge bc-badge--poppy">Rained out</span>
```

## 03. Stickers

**Purpose:** the brand's playful voice: "Best seller", "Ages 7–12", "94 % stand up on day one". Ink outline, offset
shadow, display type, rotated −4°.
**Variants:** gold default, `--hibiscus`, `--sea`, `--sunset`, `--paper`; `--round` (112 px disc with `.bc-sticker__big`),
`--tilt-r` (+5°), `--flat` (no rotation).
**Do:** at most two per viewport, on art or headers. **Don't:** use stickers for statuses in the back office — use badges.

```html
<span class="bc-sticker">Best seller</span>
<span class="bc-sticker bc-sticker--round bc-sticker--paper"><span><span class="bc-sticker__big">94%</span>stand up on day one</span></span>
```

## 04. Filter chips, tags, kbd, stars

**Chips** (`.bc-chip`, 36 px, 1.5 px sand outline) toggle filters; `.is-selected` turns ink with the value in gold
(`.bc-chip__value`) and a remove affordance (`.bc-chip__x`). **Tags** (`.bc-tag`, 24 px, 8 px radius, `--sea|sun|sunset|palm`)
are static attributes (level, duration). `.bc-kbd` draws a key; `.bc-stars` five filled Lucide stars with `role="img"`.
**Don't:** make tags clickable — clickable means chip.

```html
<button type="button" class="bc-chip is-selected" aria-pressed="true">Class: <span class="bc-chip__value">Lessons</span><span class="bc-chip__x" aria-hidden="true"><svg class="bc-i bc-i--xs" …/></span></button>
<button type="button" class="bc-chip"><svg class="bc-i bc-i--sm" …/>Instructor</button>
<span class="bc-tag bc-tag--sea">First waves</span> <span class="bc-kbd">⌘K</span>
<span class="bc-stars" role="img" aria-label="4.9 out of 5 stars"><svg class="bc-i" …star…/>…×5</span>
```

## 05. Cards

**Purpose:** group content. 28 px radius, 1 px warm line, soft warm shadow, 24 px padding (20 on phones).
**Variants:** `--sticker` (2 px ink outline + 7 px offset), `--sun` (gold fill, ink text — the hero stat), `--sunset`
(gradient, marketing and featured only), `--deep` (teal→blue gradient with white text — weather only), `--ink`, `--sand`
(flat grouping), `--flat`, `--flush` (no padding: tables). Parts: `__head`, `__title`, `__meta`, `__pad`, `__foot` (dashed rule).
**Do:** one coloured card per row. **Don't:** nest cards or put a sticker card inside a sticker card.

```html
<section class="bc-card" aria-labelledby="crew-title">
  <div class="bc-card__head"><div><h2 class="bc-card__title" id="crew-title">Crew</h2><p class="bc-card__meta">6 instructors on shift</p></div><span class="bc-badge bc-badge--palm">4 in the water</span></div>
  …
  <div class="bc-card__foot"><span class="bc-muted">3 boards due back by noon</span><a class="bc-link" href="#">Rentals</a></div>
</section>
```

## 06. Stat tiles

**Purpose:** oversized numbers — the system's signature. `.bc-stat` on a card: `__label` with a round `__icon`
(`--sea|sunset|palm|ink`), `__value` (44–60 px display 900, −0.06 em, `<small>` for units), `__delta` (`--down`, `--flat`),
`__foot`. A `.bc-progress` can replace the foot.
**Do:** round the number ("$52.4k") and put the exact figure in the meta or tooltip. **Don't:** show more than four tiles in a row.

```html
<article class="bc-card bc-card--sun bc-stat">
  <span class="bc-stat__label"><span class="bc-stat__icon bc-stat__icon--ink"><svg class="bc-i" …/></span>Bookings today</span>
  <p class="bc-stat__value">42</p>
  <div class="bc-stat__foot"><span class="bc-stat__delta"><svg class="bc-i bc-i--xs" …trending-up…/>+27%</span><span>6 walk-ins</span></div>
</article>
```

## 07. Charts

**Purpose:** inline SVG charts styled by classes, so dark mode follows. **Bars:** `.bc-chart__bar` (sunset), `--sea`
(weekends / second series), `--sun`, `--soft`, `--today` (ink), rounded `rx="8"`; `.bc-chart__grid` dashed lines,
`.bc-chart__base`, `.bc-chart__axis` (11 px, `--strong`), `.bc-chart__label` (value on top), `.bc-chart__flag` +
`__flag-text` (pill callout). **Line/area:** `.bc-chart__line` (`--sea`, `--ghost` dashed target), `.bc-chart__area`
(`--sea`), `.bc-chart__dot`. **Donut:** `.bc-donut` › `__wrap` › `svg.bc-donut__svg` (r = 60, circumference 377,
segments by `stroke-dasharray`/`-dashoffset`) + `__center`/`__value`; **legend** `.bc-legend` (`--row`) › `__item` › `__swatch`
(`--sea|sun|hibiscus|ink|ghost`). **Split stats** `.bc-split-stats` under a chart.
**Do:** give every chart `role="img"` and an `aria-label` that states the takeaway. **Don't:** use more than three hues in one chart.

```html
<svg class="bc-chart" viewBox="0 0 640 232" role="img" aria-label="Daily revenue Oct 2–15; peak Monday Oct 12 at $6.1k">
  <line class="bc-chart__grid" x1="48" y1="54" x2="632" y2="54"/><line class="bc-chart__base" x1="48" y1="200" x2="632" y2="200"/>
  <rect class="bc-chart__bar" x="56" y="149" width="26" height="51" rx="8"/>
  <rect class="bc-chart__bar bc-chart__bar--today" x="589" y="117" width="26" height="83" rx="8"/>
  <text class="bc-chart__axis" x="69" y="222" text-anchor="middle">F 2</text>
</svg>
```

## 08. Swell widget

**Purpose:** the product's live-conditions card (any "weather / status of the world" widget). `.bc-card.bc-card--deep.bc-swell`
› `__top` (eyebrow, `__big` height, drawn `__sun`), `__grid` of three `__cell` (bold value + label), `__tide` SVG
(`__tide-area`, `__tide-line`, `__tide-now`, `__tide-text`), `__window` gold pill.
**Do:** keep it to one per screen. **Don't:** put body text smaller than 12 px on the gradient.

```html
<section class="bc-card bc-card--deep bc-swell" aria-labelledby="swell">
  <div class="bc-swell__top"><div><h2 class="bc-eyebrow" id="swell">Swell · Moonlight Beach</h2><p class="bc-swell__big">3–4<small>ft</small></p></div></div>
  <div class="bc-swell__grid"><div class="bc-swell__cell"><b>6 mph</b><span>Offshore</span></div>…</div>
  <p class="bc-swell__window"><svg class="bc-i" …timer…/>Best window 7–10:30 am</p>
</section>
```

## 09. Ticker (marquee)

**Purpose:** a running strip of short facts — live conditions in the app, offers on the site. `.bc-ticker` › two
`.bc-ticker__track` (the second `aria-hidden="true"`) › `.bc-ticker__item` separated by a sun icon. Tones: ink default, `--sun`,
`--sunset`, `--sea`; shapes `--round` (pill), `--tilt` (−2°, full bleed inside a `.bc-ticker-zone` that clips it), `--sm`.
Animates with `bc-marquee` (48 s); `prefers-reduced-motion` stops it.
**Do:** 3–6 items, under 24 characters each. **Don't:** put links or the only copy of important information in a ticker.

```html
<div class="bc-ticker bc-ticker--sea bc-ticker--sm bc-ticker--round" role="marquee" aria-label="Live conditions">
  <div class="bc-ticker__track"><span class="bc-ticker__item">Moonlight 3–4 ft <svg class="bc-i" …sun…/></span>…</div>
  <div class="bc-ticker__track" aria-hidden="true">…same items…</div>
</div>
```

## 10. Schedule slots

**Purpose:** a day's timeline (classes, appointments, shifts). `ol.bc-schedule` › `li.bc-slot` (`--sea|sun|palm|hibiscus`
colour the 4 px time rule) › `__time` (+`<small>` am/pm), `__body` › `__title`, `__meta`; `__spots` (badge, count or
progress). States: `.is-now` (sticker, gold tint), `.is-done` (faded).

```html
<ol class="bc-schedule">
  <li class="bc-slot is-now"><span class="bc-slot__time">9:00<small>am</small></span><div class="bc-slot__body"><p class="bc-slot__title">Learn to Surf · Group</p><p class="bc-slot__meta"><span><svg class="bc-i bc-i--sm" …/>Mateo Reyes</span></p></div><div class="bc-slot__spots"><span class="bc-badge bc-badge--sea">In the water</span><span>6/8 spots</span></div></li>
</ol>
```

## 11. Progress

`.bc-progress` (10 px pill track) › `.bc-progress__bar` with a width utility `.bc-w-10 … .bc-w-100`; tones `--sea|sun|palm|poppy`;
`--lg` adds an ink outline and candy stripes (goals). Give it `role="img"` + `aria-label` ("31 of 48 boards out").

```html
<div class="bc-progress bc-progress--sea" role="img" aria-label="31 of 48 boards out"><span class="bc-progress__bar bc-w-65"></span></div>
```

## 12. Avatars, people, lists, feed

`.bc-avatar` (36 px, ink outline, initials, `--sm` 28 / `--lg` 48 / `--xl` 72, tones `--a … --f`), `.bc-avatars` stack +
`__more`; `.bc-person` › avatar + `__text` › `__name`, `__sub`; `.bc-list` › `__item` (+`__end`); `.bc-feed` › `__item` ›
`__icon` (`--palm|sun|poppy|sea`) + text + `__time`; `.bc-dl` key/value pairs; `.bc-dot` (`--live` pulse ring).
**Don't:** use photos in mockups — initials keep them offline and anonymous.

```html
<span class="bc-person"><span class="bc-avatar bc-avatar--b" aria-hidden="true">MR</span><span class="bc-person__text"><span class="bc-person__name">Mateo Reyes</span><span class="bc-person__sub">Learn to Surf · 6 guests</span></span></span>
```

## 13. Table

**Anatomy:** `.bc-table-card` › `.bc-table-wrap` (scrolls horizontally on phones) › `table.bc-table` (min 820 px;
`--compact` 520 px) with a `.visually-hidden` caption; `th` 11 px uppercase; `.bc-sort` buttons in headers, `th.is-sorted` +
`aria-sort`; cells `.bc-table__check`, `__num` (right, tabular), `__id` (mono), `__strong`, `__sub` (second line), `__actions`,
`__nowrap`. Rows: `:hover`/`.is-hover`, `.is-selected` (gold tint + 5 px sunset edge). `.bc-table-foot` holds pagination.
**Do:** put secondary data on a second line (`__sub`) instead of adding columns. **Don't:** zebra-stripe.

```html
<div class="bc-table-card"><div class="bc-table-wrap"><table class="bc-table">
  <caption class="visually-hidden">Upcoming bookings</caption>
  <thead><tr><th scope="col" class="bc-table__check"><input class="bc-checkbox" type="checkbox" aria-label="Select all"></th><th scope="col" class="is-sorted" aria-sort="ascending"><button type="button" class="bc-sort">Starts<svg class="bc-i" …arrow-up…/></button></th><th scope="col" class="bc-table__num">Total</th><th scope="col">Status</th></tr></thead>
  <tbody><tr class="is-selected"><td class="bc-table__check"><input class="bc-checkbox" type="checkbox" checked aria-label="Select SSC-2041"></td><td class="bc-table__strong">Thu Oct 15<span class="bc-table__sub">4:30 pm · 90 min</span></td><td class="bc-table__num bc-table__strong">$178</td><td><span class="bc-badge bc-badge--palm">Confirmed</span></td></tr></tbody>
</table></div><div class="bc-table-foot">…pagination…</div></div>
```

## 14. Tabs, segmented control, search, toolbar

`.bc-tabs` › `a.bc-tab` (`.is-active` = white sticker, `aria-current="page"`) + `.bc-tab__count`. `.bc-segmented` ›
`button.bc-segmented__item` (`.is-active`, `aria-pressed`); `--ink` variant for marketing toggles. `.bc-search` (pill, icon,
`__input`, `__kbd`). `.bc-toolbar` (+ `__end`) lays out search, chips and sort.
**Do:** tabs for saved views with counts, segmented for display options. **Don't:** put more than five tabs.

```html
<nav class="bc-tabs" aria-label="Booking views"><a class="bc-tab is-active" href="#" aria-current="page">Upcoming<span class="bc-tab__count">316</span></a><a class="bc-tab" href="#">Today<span class="bc-tab__count">42</span></a></nav>
<div class="bc-toolbar"><div class="bc-search" role="search"><svg class="bc-i" …search…/><input class="bc-search__input" type="search" placeholder="Guest, email or booking #" aria-label="Search bookings"><span class="bc-kbd bc-search__kbd">/</span></div></div>
```

## 15. Bulk action bar, pagination

`.bc-bulkbar` (ink pill with sticker shadow, `role="region"`) › `__count` (gold number) + `__actions` (ghost buttons that
turn light, one `--sun` destructive). `.bc-pagination` › `.bc-page-btn` (`.is-active` sunset sticker + `aria-current`,
`--gap` for …) and `.bc-pagination__info`.

```html
<div class="bc-bulkbar" role="region" aria-label="Bulk actions"><span class="bc-bulkbar__count"><b>3</b>bookings selected</span><div class="bc-bulkbar__actions"><button type="button" class="bc-btn bc-btn--ghost bc-btn--sm">Send reminder</button><button type="button" class="bc-btn bc-btn--sun bc-btn--sm">Cancel 3</button></div></div>
<nav class="bc-pagination" aria-label="Pagination"><a class="bc-page-btn is-active" href="#" aria-current="page">1</a><a class="bc-page-btn" href="#">2</a><span class="bc-page-btn bc-page-btn--gap" aria-hidden="true">…</span><a class="bc-page-btn" href="#">36</a></nav>
```

## 16. Dropdown menu, tooltip

`.bc-dropdown` (relative) › trigger with `aria-haspopup="menu"` `aria-expanded="true"` › `.bc-menu` (`role="menu"`, ink outline,
sticker shadow; `--left`, `--static` for docs) › `__label`, `a.bc-menu__item` (`role="menuitem"`, `.is-hover`, `--danger`,
`__kbd`), `__sep`. `.bc-tooltip` (`role="tooltip"`) ink bubble with a caret.

```html
<div class="bc-dropdown"><button type="button" class="bc-btn bc-btn--outline bc-btn--icon bc-btn--sm" aria-label="Actions for SSC-2036" aria-haspopup="menu" aria-expanded="true"><svg class="bc-i" …ellipsis…/></button>
  <div class="bc-menu" role="menu"><a class="bc-menu__item" role="menuitem" href="#">Edit booking<span class="bc-menu__kbd">E</span></a><div class="bc-menu__sep" role="separator"></div><a class="bc-menu__item bc-menu__item--danger" role="menuitem" href="#">Cancel and refund</a></div></div>
```

## 17. Alerts, toasts

**Alert** `.bc-alert` (gold tint default, `--sea`, `--poppy`, `--palm`, `--loud` adds the sticker) › `__icon` (round,
outlined), `__title`, `__body`, `__actions`; a third grid column holds a dismiss button (or an empty `<span>`). `role="status"`,
`role="alert"` for errors. **Toast** `.bc-toasts` (fixed bottom-right; `--top`, `--static` for docs) › `.bc-toast` (`--ink`) ›
`__icon` (`--poppy`, `--sun`), `__title`, `__text`, close or undo button.

```html
<div class="bc-alert bc-alert--sea" role="status"><span class="bc-alert__icon"><svg class="bc-i" …info…/></span><div><p class="bc-alert__title">8 guests are booked on the next date</p><p class="bc-alert__body">Moving the time sends them an update.</p></div><button type="button" class="bc-btn bc-btn--ghost bc-btn--icon bc-btn--sm" aria-label="Dismiss"><svg class="bc-i" …x…/></button></div>
<div class="bc-toasts" role="status"><div class="bc-toast"><span class="bc-toast__icon"><svg class="bc-i bc-i--bold" …check…/></span><div><p class="bc-toast__title">Booking confirmed</p><p class="bc-toast__text">Hannah gets a text in a sec.</p></div><button type="button" class="bc-btn bc-btn--ghost bc-btn--icon bc-btn--sm" aria-label="Dismiss"><svg class="bc-i bc-i--sm" …x…/></button></div></div>
```

## 18. Modal

`.bc-backdrop` (55 % ink, slight blur) + `.bc-modal-wrap` › `.bc-modal` (`role="dialog"`, `aria-modal="true"`,
`aria-labelledby`; 520 px, ink outline, big sticker) › optional `__art` strip (illustration), `__close`, `__head`, `__body`,
`__foot` (right-aligned, full-width buttons on phones). `--static` places it in the flow for the catalog.
**Do:** a confirmation names the consequence and the amount. **Don't:** stack modals.

```html
<div class="bc-backdrop"></div>
<div class="bc-modal-wrap"><div class="bc-modal" role="dialog" aria-modal="true" aria-labelledby="m-title">
  <button type="button" class="bc-btn bc-btn--outline bc-btn--icon bc-btn--sm bc-modal__close" aria-label="Close"><svg class="bc-i" …x…/></button>
  <div class="bc-modal__head"><h2 class="bc-h2" id="m-title">Cancel 3 bookings?</h2></div>
  <div class="bc-modal__body"><p>Hannah, Tomás and Diego get a full refund of $693.</p></div>
  <div class="bc-modal__foot"><button type="button" class="bc-btn bc-btn--ghost">Keep them</button><button type="button" class="bc-btn bc-btn--danger">Cancel and refund</button></div>
</div></div>
```

## 19. Text fields — input, select, textarea, input group

`.bc-field` (`--full` spans the row) › `label.bc-label` (+ `.bc-req` orange asterisk with `aria-hidden`, real `required` on
the control; `.bc-label__opt` for "Optional") › `.bc-input` / `.bc-select` (native select, drawn chevron) / `.bc-textarea` ›
`.bc-help` or `.bc-error` (icon + text, linked with `aria-describedby`, field gets `.is-error` + `aria-invalid`). 50 px tall,
16 px radius, 2 px warm-brown border (3:1 against the surface). Focus: ink border + 4 px sunset halo. Disabled/readonly: dashed
border, sand fill. `.bc-input-group` + `__addon` (`--end`) for $, /classes/, units. `.bc-field__foot` + `.bc-counter`.
Layout: `.bc-fields` (2 columns), `--3`.

```html
<div class="bc-fields">
  <div class="bc-field"><label class="bc-label" for="cap">Spots <span class="bc-req" aria-hidden="true">*</span></label><input class="bc-input is-error" id="cap" type="number" value="10" required aria-invalid="true" aria-describedby="cap-err"><p class="bc-error" id="cap-err"><svg class="bc-i" …circle-alert…/>Only 8 longboards in the quiver.</p></div>
  <div class="bc-field"><label class="bc-label" for="price">Price</label><div class="bc-input-group"><span class="bc-input-group__addon">$</span><input class="bc-input" id="price" value="105.00"></div></div>
  <div class="bc-field"><label class="bc-label" for="slug">Web address</label><input class="bc-input" id="slug" value="sunrise-longboard-clinic" disabled><p class="bc-help">Locked after the first booking.</p></div>
</div>
```

## 20. Checkbox, radio, switch, choice cards

Native inputs restyled with `appearance: none`: `.bc-checkbox` (24 px, 8 px radius, sunset fill + ink tick when checked,
indeterminate dash), `.bc-radio`, `.bc-switch` (`role="switch"`, 56 × 32, sunset when on). Wrappers: `label.bc-check` (+
`__text`, `__sub`), `label.bc-toggle` (`__text`, `__title`, `__sub`). **Choice cards** `.bc-choices` › `label.bc-choice`
(`.is-selected` gold tint + sticker) › hidden radio, `__icon`, `__title`, `__sub`, `__mark`.
**Do:** use choice cards for 2–4 options that deserve an icon (level, plan, theme). **Don't:** use a switch inside a form that
has a Save button for something that only applies on save — use a checkbox.

```html
<label class="bc-check"><input class="bc-checkbox" type="checkbox" checked><span class="bc-check__text">Wetsuit<span class="bc-check__sub">4/3 till May</span></span></label>
<label class="bc-toggle"><span class="bc-toggle__text"><span class="bc-toggle__title">Weather call</span><span class="bc-toggle__sub">Auto-cancel when it’s flat.</span></span><input class="bc-switch" type="checkbox" role="switch" checked></label>
<div class="bc-choices"><label class="bc-choice is-selected"><input type="radio" name="level" checked><span class="bc-choice__icon"><svg class="bc-i" …waves…/></span><span class="bc-choice__title">Green waves</span><span class="bc-choice__sub">Paddles out alone</span></label>…</div>
```

## 21. Drop zone, file row

`.bc-dropzone` (dashed, 28 px radius, `.is-hover` = sunset tint for drag-over) › `__art` (small SVG), `__title`, `__sub`.
`.bc-file` › `__thumb` (gradient fallback + illustration), `__name`, `__meta`, remove button.

```html
<div class="bc-dropzone"><p class="bc-dropzone__title">Drop a photo, or <span class="bc-link">browse</span></p><p class="bc-dropzone__sub">JPG, PNG or HEIC · up to 12 MB</p></div>
<div class="bc-file"><span class="bc-file__thumb">…svg…</span><span><span class="bc-file__name">moonlight-dawn-oct.jpg</span><span class="bc-file__meta">2400 × 1350 · 3.1 MB</span></span><button type="button" class="bc-btn bc-btn--ghost bc-btn--icon bc-btn--sm" aria-label="Remove moonlight-dawn-oct.jpg"><svg class="bc-i bc-i--sm" …trash-2…/></button></div>
```

## 22. Form sections, action bar, preview

`.bc-form-layout` (form + 300 px `.bc-form-aside`, stacks under 1180 px). `.bc-section` (numbered card: `__num` disc
`--sea|sunset|hibiscus` holding "01" or an icon, `__head`, `__title`, `__desc`, `__body`). `.bc-actionbar` — sticky pill
at the bottom with `__note` and `__btns` (full width on phones). `.bc-preview` (`__art`, `__body`) shows the record as guests
will see it. `.bc-divider` dashed rule.

```html
<section class="bc-section" aria-labelledby="s1"><span class="bc-section__num" aria-hidden="true">01</span>
  <div class="bc-section__head"><h2 class="bc-section__title" id="s1">The basics</h2><p class="bc-section__desc">What guests see first.</p></div>
  <div class="bc-section__body"><div class="bc-fields">…</div></div></section>
<div class="bc-actionbar"><span class="bc-actionbar__note">1 field needs a look</span><div class="bc-actionbar__btns"><a class="bc-btn bc-btn--ghost" href="#">Discard</a><button type="button" class="bc-btn bc-btn--primary">Save &amp; publish</button></div></div>
```

## 23. Drawn fields (sign-in mockups)

Credentials are never real inputs. `.mock-label` + `.mock-field` (`role="img"`, `aria-label="… field, filled in"`) look
exactly like `.bc-input`; `--secret` (spaced dots), `--focus`, `--error`, `--empty`, `--disabled`; `.mock-field__caret`,
`.mock-field__end`. Submit is `<a … role="button">`.

```html
<span class="mock-label">Email</span><div class="mock-field" role="img" aria-label="Email field, filled in">kai@sundaysurf.co</div>
<span class="mock-label">Password</span><div class="mock-field mock-field--secret mock-field--focus" role="img" aria-label="Password field, filled in">••••••••••••<span class="mock-field__caret"></span></div>
<a href="dashboard.html" class="bc-btn bc-btn--primary bc-btn--lg bc-btn--block" role="button">Sign in</a>
```

## 24. Empty states

`.bc-empty` (centred, generous) › `__art` (illustration, ≤ 340 px) or `__icon` (64 px sticker disc, `--sea`, `--hibiscus`),
`__title` (display 900), `__text`, `__actions`. `--compact` inside cards. Copy: say what will appear here and the one action
that makes it appear. **Don't:** blame the user ("You have no…").

```html
<div class="bc-empty"><svg class="bc-empty__art" viewBox="0 0 340 200" role="img" aria-label="An empty surfboard rack">…</svg>
  <h2 class="bc-empty__title">The rack is empty. Let’s fill it.</h2><p class="bc-empty__text">Add your first board and it shows up in the booking widget.</p>
  <div class="bc-empty__actions"><a class="bc-btn bc-btn--primary bc-btn--lg" href="#">Add a board</a></div></div>
```

## 25. App shell — sidebar, topbar, page head, breadcrumbs

`.bc-app` (272 px column + content) › `aside.bc-sidebar` (floating sand panel, 28 px radius) › `__brand` (`.bc-logo`),
`nav.bc-nav` › `__label`, `a.bc-nav__item` (`.is-active` = sunset sticker pill + `aria-current="page"`, `__count`),
`__card` (a live widget), `__user`. `.bc-main` › `header.bc-topbar` (`__menu` + `__brand` show under 960 px, `__search`,
`__actions`, `.bc-bell` + `__dot`) › `main` › `.bc-page-head` (`__title`, `__sub`, `__actions`), `.bc-crumbs`. Under 960 px the
sidebar hides and the topbar sticks with a menu button.

```html
<body class="bc-mockup bc-body"><div class="bc-app">
  <aside class="bc-sidebar" aria-label="Sunday HQ"><nav class="bc-nav" aria-label="Main"><a class="bc-nav__item is-active" aria-current="page" href="dashboard.html"><svg class="bc-i" …/>Today</a><a class="bc-nav__item" href="resource-list.html"><svg class="bc-i" …/>Bookings<span class="bc-nav__count">42</span></a></nav></aside>
  <div class="bc-main"><header class="bc-topbar">…</header><main id="main"><div class="bc-page-head"><div class="bc-page-head__title"><h1 class="bc-h1">Bookings</h1></div></div>…</main></div>
</div></body>
```

## 26. Site nav, announcement, footer

`.bc-site` (clips decorative overflow) › `p.bc-announce` (night strip, gold link) › `header.bc-sitenav` (sticky, blurred) ›
`.bc-container.bc-sitenav__inner` › logo, `nav.bc-sitenav__links` (white pill; `a.bc-sitenav__link.is-active` ink),
`__end` (primary CTA, `__menu` on phones). `footer.bc-footer` (night, rounded top) › `__grid`, `__title` (gold), `__links`,
`__link`, `__text`, `.bc-socials`, the giant `__word`, `__bottom`.

```html
<header class="bc-sitenav"><div class="bc-container bc-sitenav__inner"><a class="bc-logo" href="landing.html">…</a>
  <nav class="bc-sitenav__links" aria-label="Main"><a class="bc-sitenav__link is-active" aria-current="page" href="landing.html">Surf school</a><a class="bc-sitenav__link" href="pricing.html">Pricing</a></nav>
  <div class="bc-sitenav__end"><a class="bc-btn bc-btn--primary" href="pricing.html">Book a lesson</a><button type="button" class="bc-btn bc-btn--outline bc-btn--icon bc-sitenav__menu" aria-label="Open menu"><svg class="bc-i" …menu…/></button></div></div></header>
```

## 27. Hero and booker

`.bc-hero` › `__grid` (text + art) › `h1.bc-display.bc-hero__title` with `__line` spans, `__lede`, `__ctas`, `__proof`
(avatars + stars). `__art` (sky gradient, ink outline, 44 px radius) holds a full-bleed `__scene` SVG, up to two `__sticker`s
(`--a` top-left, `--b`) and `__booker`. `.bc-booker` — a drawn quick-booking bar: three `__field`s (`__label`, `__value`) and
a button; 2 × 2 on phones.

```html
<div class="bc-hero__art"><svg class="bc-hero__scene" viewBox="0 0 400 410" preserveAspectRatio="xMidYMid slice" role="img" aria-label="…">…</svg>
  <span class="bc-hero__sticker bc-hero__sticker--a bc-sticker bc-sticker--round bc-sticker--paper"><span><span class="bc-sticker__big">94%</span>stand up on day one</span></span>
  <div class="bc-hero__booker bc-booker" role="group" aria-label="Quick booking"><div class="bc-booker__field"><span class="bc-booker__label">Lesson</span><span class="bc-booker__value">Beginner</span></div>…<a class="bc-btn bc-btn--ink" href="#">Check spots</a></div></div>
```

## 28. Class card, product card, price

`.bc-cards-3` / `.bc-cards-4` grids. `.bc-class-card` (sticker; hover tilts) › `__art` (`--sea|sunset|sun|hibiscus` +
illustration + optional sticker), `__body`, `__title`, `__meta` (tags), `__foot` with `.bc-price` (+`<small>` unit) and a small
primary button. `.bc-products` › `a.bc-product` › `__art` (`--sun|hibiscus|sunset`, badge top-left), `__row`, `__name`, `__meta`.

```html
<article class="bc-class-card"><div class="bc-class-card__art bc-class-card__art--sea">…svg…<span class="bc-sticker">Best seller</span></div>
  <div class="bc-class-card__body"><h3 class="bc-class-card__title">Learn to Surf</h3><div class="bc-class-card__meta"><span class="bc-tag bc-tag--sea">First waves</span><span class="bc-tag">2 h</span></div>
  <div class="bc-class-card__foot"><p class="bc-price">$89<small>/ guest</small></p><a class="bc-btn bc-btn--primary bc-btn--sm" href="#">Book</a></div></div></article>
```

## 29. Big numbers, steps, reviews, photo grid, newsletter

`.bc-band` sections (`--tight`, `--sand`, `--paper`, `--ink` night, `--sun`, `--round` inset with outline) › `.bc-container`
› `.bc-band__head`. `.bc-bignums` › `.bc-bignum` (`__value` up to 92 px, `<sup>` units, `__label`) separated by 2 px rules.
`.bc-steps` › `.bc-step` (`--sun`, `--sea`) › `__num`. `.bc-reviews` › `figure.bc-review` (`--sticker` tilted) › stars,
`__quote`, `__who`. `.bc-gram` › `__tile` (`--sea|sunset|hibiscus|ink|sky`) + illustration + `__tag`. `.bc-newsletter` (gold
block) › `__form` (real `type="email"` input `__input` + ink button), `__note`.

```html
<div class="bc-bignums"><div class="bc-bignum"><p class="bc-bignum__value">94<sup>%</sup></p><p class="bc-bignum__label">of first-timers stand up on day one</p></div>…</div>
<figure class="bc-review bc-review--sticker"><span class="bc-stars" role="img" aria-label="5 stars">…</span><blockquote class="bc-review__quote">“The sunrise clinic is the best two hours of my week.”</blockquote><figcaption class="bc-review__who">…</figcaption></figure>
```

## 30. Pricing plans, comparison, FAQ, CTA

`.bc-pricing` › `article.bc-plan` (`--featured` sunset gradient, raised 14 px, ink checks; `--sea`) › `__ribbon` (sticker),
`__name`, `__price` (`<sup>$</sup>`), `__per`, `__list` › `__item` (`--off` struck), block button. `.bc-billing` centres a
segmented toggle. `table.bc-table.bc-compare` (row headers `th scope="row"`, `__hl` column tint, `__yes`/`__no` icons with
visually-hidden Yes/No). `.bc-faq` › `details.bc-faq__item` › `summary.bc-faq__q` (plus icon rotates when open), `__a` —
works without script. `.bc-cta` sunset band with one ink button.

```html
<article class="bc-plan bc-plan--featured"><span class="bc-plan__ribbon bc-sticker">Most popular · save $50</span><h2 class="bc-plan__name">5-lesson pack</h2>
  <div><p class="bc-plan__price"><sup>$</sup>395</p><p class="bc-plan__per">$79 per lesson</p></div>
  <ul class="bc-plan__list"><li class="bc-plan__item"><svg class="bc-i" …check…/>One filmed wave per lesson</li></ul>
  <a class="bc-btn bc-btn--ink bc-btn--lg bc-btn--block" href="#">Get the pack</a></article>
<details class="bc-faq__item" open><summary class="bc-faq__q">What if it’s flat?<svg class="bc-i" …plus…/></summary><p class="bc-faq__a">Free reschedule or a full refund.</p></details>
```

## 31. Settings layout

`.bc-settings` (236 px `.bc-subnav` + content; the subnav becomes a horizontal scroller on phones) › `a.bc-subnav__item`
(`.is-active` white sticker). Rows `.bc-setting` (text left, control right). Theme picker: `.bc-theme-card` (`.is-selected`)
› `__preview` (`--day`, `--night`, `--auto`) + `__label` with a radio. `.bc-danger-zone` dashed poppy section.

```html
<label class="bc-setting"><span class="bc-toggle__text"><span class="bc-toggle__title">Waitlist</span><span class="bc-toggle__sub">Offer freed spots for 2 hours.</span></span><input class="bc-switch" type="checkbox" role="switch" checked></label>
<label class="bc-theme-card is-selected"><span class="bc-theme-card__preview bc-theme-card__preview--day" aria-hidden="true"></span><span class="bc-theme-card__label"><input class="bc-radio" type="radio" name="theme" checked>Sunny</span></label>
```

## 32. Auth layout

`.bc-auth` (split; stacks under 960 px) › `section.bc-auth__art` (sunset gradient panel: logo, `__headline`, `__quote`,
`__scene` SVG) + `main.bc-auth__panel` › `__card` › sticker, `__title`, `__stack` of drawn fields, `__row`, `__or`, `__foot`.
`.bc-auth__more` shows the other steps (reset, two-factor) as cards.

## 33. Logo and illustrations

`.bc-logo` (`--lg`, `--inverse`) › `svg.bc-logo__mark` (orange disc, gold sun setting into a teal wave, ink ring) +
`__word` (`<small>` tagline). Illustrations are flat shapes with a 2.5 px ink outline: fills `bc-f-sunset | sun | sea | ocean |
palm | poppy | hibiscus | ink | paper | sand | sunset-soft | none`, strokes `bc-s-ink | paper | sea | sun` with `bc-s-thin`,
`bc-s-thick`. They read the theme tokens, so the night theme recolours them; `bc-f-paper` stays cream on purpose (boards and
faces stay "printed"). Decorative art is `aria-hidden="true"`; meaningful art gets `role="img"` + `aria-label`.
**Do:** compose from circles, waves (`c27 0 27-14 53-14s27 14 53 14…`) and pill boards. **Don't:** add gradients or
shadows inside illustrations, or more than four fills in one picture.

```html
<a class="bc-logo" href="landing.html" aria-label="Sunday Surf Co. home"><svg class="bc-logo__mark" viewBox="0 0 48 48" aria-hidden="true"><circle class="bc-f-sunset" cx="24" cy="24" r="22"/><path class="bc-f-sun" d="M13 31a11 11 0 0 1 22 0z"/><path class="bc-f-sea" d="M3.1 31C7 28 10 28 13.5 31S20 34 24 31S31 28 34.5 31S41 34 44.9 31A22 22 0 0 1 3.1 31z"/><circle class="bc-f-none bc-s-ink" cx="24" cy="24" r="22"/></svg><span class="bc-logo__word">Sunday<small>Surf Co.</small></span></a>
```
