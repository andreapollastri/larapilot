# Berlin — components

Every class lives in `tokens.css`; `html/components.html` draws each component in its variants and states. Icons are
**Lucide** inline SVG (`<svg class="bn-i" viewBox="0 0 24 24" aria-hidden="true">…</svg>`, stroke 1.75, `currentColor`; sizes
`.bn-i--12/14/16/18/20/24/32/48`). In the snippets `<!-- name -->` stands for a Lucide icon of that name.

**State classes:** `.is-active`, `.is-hover`, `.is-focus`, `.is-pressed`, `.is-selected`, `.is-error`, `.is-mixed`,
`.is-disabled`, `.is-today`, `.is-dragover`, `.is-signal`. **Theme islands:** `.bn-inv` (inverted for the current theme),
`.bn-night` (always black), `.bn-sig` (always acid) re-point the semantic tokens for a block, so any component inside them just
works.

## Mapping table — mockup → real code

| Mockup | Blade component (suggested) | Notes |
| --- | --- | --- |
| `.bn-app`, `.bn-rail`, `.bn-topbar`, `.bn-main` | `<x-bn.layouts.app>` | Rail items from a `config('menu')` array with index numbers |
| `.bn-site`, `.bn-sitehead`, `.bn-sitefoot` | `<x-bn.layouts.site>` | Ticker from the next 5 public events |
| `.bn-btn` | `<x-bn.button variant size icon>` | `variant`: primary · signal · outline (default) · ghost · danger |
| `.bn-tag` | `<x-bn.tag :status>` | Map each enum case to one modifier (table in §5) |
| `.bn-grid` / `.bn-cell` | `<x-bn.grid>` / `<x-bn.cell span index title>` | Spans 2–12 |
| `.bn-stat`, `.bn-meter` | `<x-bn.stat>`, `<x-bn.meter :value>` | Meter widths via `.bn-w-*` or a `style="width"` in real code |
| `.bn-table`, `.bn-pager` | `<x-bn.table>`, `<x-bn.pager :paginator>` | Livewire: `wire:model` on row checkboxes |
| `.bn-field`, `.bn-input`, `.bn-select`… | `<x-bn.field>`, `<x-bn.input>` | Errors from `$errors` |
| `.bn-menu`, `.bn-dialog`, `.bn-toast` | `<x-bn.dropdown>`, `<x-bn.modal>`, `<x-bn.toast>` | Alpine for open / close |
| `.bn-tl` | `<x-bn.timeline :days :rooms :bookings>` | Blocks: `from` = weekday 1–7, `len` = days |
| `.bn-photo` | `<x-bn.photo kind alt>` / real `<img>` | Real images get a duotone CSS filter or a pre-processed duotone |

---

## 1. App shell (backoffice)

**Purpose:** the frame of every admin screen. A 248 px rail ruled off on the right, a 56 px topbar whose bottom rule continues
the rail's brand rule, and the content area. **Anatomy:** `.bn-app` › `aside.bn-rail` (`__brand` with `.bn-logo` + phone-only
`__menu`, `nav.__nav` with `__group` labels and `.bn-nav-item`s, `__foot` with `__coords` and the user) + `header.bn-topbar`
(`__meta` week/date/time, `__search` › `.bn-search`, `__actions` › square `.bn-topbar__btn` cells, `--signal` for "new",
`__badge` count) + `main.bn-main`. **Variants:** below 960 px the rail collapses to a top bar with the menu button; the topbar
hides. **Do:** keep the numbering continuous across groups. **Don't:** colour the rail.

```html
<body class="bn-mockup bn-body"><a class="bn-skip" href="#main">Skip to content</a>
<div class="bn-app">
  <aside class="bn-rail" aria-label="Backoffice">
    <div class="bn-rail__brand"><a class="bn-logo" href="dashboard.html"><!-- logo svg --><span class="bn-logo__text"><span class="bn-logo__word">Kollektiv Studio</span><span class="bn-logo__sub">Backoffice</span></span></a>
      <button type="button" class="bn-rail__menu" aria-label="Open menu" aria-expanded="false"><!-- menu --></button></div>
    <nav class="bn-rail__nav" aria-label="Main">
      <p class="bn-rail__group"><span>Studio</span><span>01–04</span></p>
      <a class="bn-nav-item is-active" aria-current="page" href="dashboard.html"><span class="bn-nav-item__idx">01</span><span class="bn-nav-item__label">Overview</span><span class="bn-nav-item__count">KW42</span></a>
      <a class="bn-nav-item" href="#"><span class="bn-nav-item__idx">03</span><span class="bn-nav-item__label">Invoices</span><span class="bn-count bn-count--alarm">3</span></a>
    </nav>
    <div class="bn-rail__foot"><p class="bn-rail__coords"><span>52.5065° N</span><span>13.4934° E</span></p>…</div>
  </aside>
  <header class="bn-topbar">
    <p class="bn-topbar__meta"><strong>KW 42</strong><span>Mo 12.10.2026</span></p>
    <div class="bn-topbar__search" role="search"><label class="bn-search"><!-- search --><span class="visually-hidden">Search</span><input class="bn-search__input" type="search" placeholder="Search projects…"><span class="bn-kbd">/</span></label></div>
    <div class="bn-topbar__actions"><a class="bn-topbar__btn bn-topbar__btn--signal" href="#" aria-label="New project"><!-- plus --></a><a class="bn-topbar__btn" href="#" aria-label="Notifications, 4 unread"><!-- bell --><span class="bn-topbar__badge" aria-hidden="true">4</span></a></div>
  </header>
  <main class="bn-main" id="main">…</main>
</div></body>
```

## 2. Page head and breadcrumbs

**Purpose:** title block of a backoffice view. **Anatomy:** `header.bn-pagehead` › `__eyebrow` (micro labels or a
`nav.bn-crumbs`), the `h1.bn-h1` (optionally in `__title` with `__count`), `__lede`, `__actions`. **Do:** put the index number
in the eyebrow ("02 — Projects"). **Don't:** add a second H1 or an icon to the title.

```html
<header class="bn-pagehead">
  <nav class="bn-pagehead__eyebrow bn-crumbs" aria-label="Breadcrumb"><a href="dashboard.html">Backoffice</a><span class="bn-crumbs__sep" aria-hidden="true">/</span><span aria-current="page">02 — Projects</span></nav>
  <div class="bn-pagehead__title"><h1 class="bn-h1">Projects</h1><span class="bn-pagehead__count">38</span></div>
  <div class="bn-pagehead__actions"><a class="bn-btn" href="#"><!-- download --> Export CSV</a><a class="bn-btn bn-btn--signal" href="#"><!-- plus --> New project</a></div>
</header>
```

## 3. Typography and marks

**Purpose:** the voice. **Anatomy:** `.bn-wordmark` (uppercase, spans the width; `--program`, `--xl`, `--sm` size it for other
words), `.bn-display`, `.bn-h1`–`.bn-h4`, `.bn-lede`, `.bn-micro` (+ `--ink`), `.bn-idx`, `.bn-mono`, `.bn-tnum`, `.bn-mark`
(acid highlighter), `.bn-link`, `.bn-arrowlink`, `.bn-muted`, `.bn-soft`, `.bn-alarm-text`, `.bn-signal-text`, `.bn-strike`.
**Do:** micro-labels for structure only. **Don't:** colour text acid in light mode — use `.bn-mark` or `.bn-signal-text`.

```html
<h1 class="bn-wordmark">Kollektiv<span class="visually-hidden"> Studio</span></h1>
<p class="bn-micro bn-micro--ink">Agency &amp; venue — est. 2014</p>
<p class="bn-lede">We design identities, spaces and nights for the city's <span class="bn-mark">independent culture</span>.</p>
<p>Read the <a class="bn-link" href="#">brief</a> or <a class="bn-arrowlink" href="#">see all projects <!-- arrow-right --></a></p>
<span class="bn-idx">P—036</span> <span class="bn-mono">€48,320</span>
```

## 4. Buttons

**Purpose:** actions. Square, 40 px (`--sm` 32, `--lg` 56), 1 px ink border. **Variants:** default outline (fills ink on hover),
`--primary` (ink; acid on hover), `--signal` (acid + hard 3 px offset shadow; one per view), `--ghost`, `--danger` (alarm
outline), `--danger-solid`; `--icon` (square, needs `aria-label`), `--block`, `--between` (label left, icon right);
`.bn-btngroup` joins buttons (`.is-active` = selected); `.bn-btn__count`. **States:** `.is-hover`, `.is-focus`, `.is-pressed`,
`[disabled]` (dashed, concrete text). **Don't:** round, gradient or soft-shadow a button.

```html
<a class="bn-btn bn-btn--signal" href="#"><!-- plus --> New project</a>
<button type="button" class="bn-btn bn-btn--primary">Save draft</button>
<button type="button" class="bn-btn">Export</button>
<button type="button" class="bn-btn bn-btn--ghost">Cancel</button>
<button type="button" class="bn-btn bn-btn--danger">Archive</button>
<button type="button" class="bn-btn bn-btn--sm bn-btn--icon" aria-label="More actions"><!-- ellipsis --></button>
<a class="bn-btn bn-btn--signal bn-btn--lg bn-btn--block bn-btn--between" href="#" role="button">Sign in <!-- arrow-right --></a>
<div class="bn-btngroup" role="group" aria-label="Range"><a class="bn-btn bn-btn--sm is-active" aria-current="true" href="#">Week</a><a class="bn-btn bn-btn--sm" href="#">Month</a></div>
```

## 5. Status tags

**Purpose:** status by **shape + text**. 22 px, mono 11 uppercase. **Variants:** default outline, `--solid`, `--signal`,
`--muted` (dashed), `--hatch` (diagonal hatch), `--alarm`, `--alarm-solid`, `--plain` (soft border, metadata), `--lg`;
`.bn-tag__dot` (filled square), `.bn-tag__ring` (hollow square), a 12 px Lucide icon. `.bn-tags` lays out a group.
**Mapping (Kollektiv):** Pitch = `--muted` · Briefing = outline + ring · In production = `--solid` + dot · Review = `--signal`
· On hold = `--hatch` + pause · Delivered = outline + check · Paid = `--solid` + check · Overdue = `--alarm` + triangle-alert ·
Few left = `--signal` · Sold out = `--muted` + `.bn-strike`. **Don't:** invent colours per status.

```html
<span class="bn-tag bn-tag--muted">Pitch</span>
<span class="bn-tag"><span class="bn-tag__ring"></span>Briefing</span>
<span class="bn-tag bn-tag--solid"><span class="bn-tag__dot"></span>Production</span>
<span class="bn-tag bn-tag--signal">Review</span>
<span class="bn-tag bn-tag--hatch"><!-- pause --> On hold</span>
<span class="bn-tag bn-tag--alarm"><!-- triangle-alert --> 12 d late</span>
```

## 6. Counts, dots, avatars, kbd

**Purpose:** small markers. `.bn-count` (+ `--alarm`, `--signal`, `--outline`); `.bn-dot` (+ `--live`, `--alarm`, `--ring`);
`.bn-av` square initials (+ `--signal`, `--concrete`, `--outline`; sizes `--sm` 22, default 28, `--lg` 40, `--xl` 64),
`.bn-avstack`, `.bn-person-inline` (avatar + name); `.bn-kbd`. **Do:** always show the name next to an avatar somewhere.
**Don't:** use round avatars — the only circle in Berlin is the radio button.

```html
<span class="bn-count bn-count--alarm">3</span>
<span class="bn-person-inline"><span class="bn-av bn-av--sm bn-av--signal">LD</span>Leyla D.</span>
<span class="bn-avstack"><span class="bn-av">FH</span><span class="bn-av bn-av--concrete">TW</span><span class="bn-av bn-av--outline">+5</span></span>
<span class="bn-kbd">/</span>
```

## 7. Bordered grid and cells

**Purpose:** borders as layout. `.bn-grid` is a 12-column grid with a 1 px gap over the rule colour, so neighbouring cells share
exactly one rule; `--boxed` adds the outer frame, `--soft` uses the light rule. Children: `.bn-cell` with `.bn-span-2…12`
(3 → 6 → 12 as the screen narrows), `--flush`, `--tight`; `.bn-cell__head` › `.bn-cell__title` (index + mono title) and
`.bn-cell__link`. Add `.bn-sig` or `.bn-inv` to a cell to make it acid or inverted. **Do:** fill each row to 12 columns —
an incomplete row shows the rule colour. **Don't:** nest grids with margins between cells.

```html
<div class="bn-grid">
  <section class="bn-cell bn-span-3" aria-labelledby="k1"><div class="bn-stat"><p class="bn-stat__label"><span id="k1" class="bn-micro bn-micro--ink">Outstanding</span><span class="bn-idx">02</span></p><p class="bn-stat__value"><span class="bn-stat__unit">€</span>48,320</p><p class="bn-stat__foot"><span class="bn-tag bn-tag--alarm">3 overdue</span><span>7 invoices open</span></p></div></section>
  <section class="bn-cell bn-span-3 bn-sig" aria-label="Tickets">…</section>
  <section class="bn-cell bn-span-6"><div class="bn-cell__head"><h2 class="bn-cell__title"><span class="bn-idx">05</span>Invoiced vs. paid</h2><a class="bn-cell__link" href="#">Invoices <!-- arrow-right --></a></div>…</section>
</div>
```

## 8. Stat, delta, meter, load row

**Purpose:** big numbers. `.bn-stat` › `__label`, `__value` (tabular, −0.05em), `__unit` (raised €), `__of` ("/450"), `__foot`;
`.bn-delta` (+ `--down` alarm) with an arrow icon; `.bn-meter` (+ `--signal`, `--over` hatched alarm, `--thick`) ›
`.bn-meter__fill.bn-w-0…100`; `.bn-load` (avatar, `__name`, `__val` / `--alarm`, meter). **Do:** state the base ("of 40 h").
**Don't:** rely on the red hatch alone — print the percentage.

```html
<div class="bn-stat"><p class="bn-stat__label"><span class="bn-micro bn-micro--ink">Tickets · Fr</span><span class="bn-idx">04</span></p>
  <p class="bn-stat__value">412<span class="bn-stat__of">/450</span></p>
  <div class="bn-meter bn-meter--thick"><span class="bn-meter__fill bn-w-90"></span></div>
  <p class="bn-stat__foot"><span class="bn-delta"><!-- arrow-up-right --> 12%</span><span>vs. Q3 2025</span></p></div>
<div class="bn-load"><span class="bn-av bn-av--sm">AB</span><span class="bn-load__name">Aiko Brandt</span><span class="bn-load__val bn-load__val--alarm">104%</span><div class="bn-meter bn-meter--over"><span class="bn-meter__fill bn-w-100"></span></div></div>
```

## 9. Charts (inline SVG)

**Purpose:** bars and lines drawn from tokens. `.bn-chart-wrap` (scrolls on phones, the SVG keeps ≥ 560 px) › `svg.bn-chart`
with `role="img"` and an `aria-label` that reads the data. Parts: `.bn-ch-grid`, `.bn-ch-base`, `.bn-ch-axis` (+ `--now`),
`.bn-ch-bar` (ink), `--open` (hatched via `#bn-hatch`), `--now` (acid with ink edge), `.bn-ch-val`, `.bn-ch-line` (+ `--dash`),
`.bn-ch-dot`, `.bn-ch-flag`; `.bn-legend` › `__item` › `__key--solid / --hatch / --signal`. Three series maximum: solid, hatch,
signal. **Don't:** use acid for a thin line on paper.

```html
<div class="bn-chart-wrap"><svg class="bn-chart" viewBox="0 0 640 250" role="img" aria-label="Invoiced per month: April 58.2k paid; October 31.4k, 22.9k open">
  <line x1="48" y1="220" x2="632" y2="220" class="bn-ch-base"/><text x="38" y="224" text-anchor="end" class="bn-ch-axis">0k</text>
  <rect x="70" y="93" width="40" height="127" class="bn-ch-bar"/>
  <rect x="570" y="201" width="40" height="19" class="bn-ch-bar bn-ch-bar--now"/><rect x="570" y="152" width="40" height="49" class="bn-ch-bar bn-ch-bar--open"/>
  <text x="590" y="240" text-anchor="middle" class="bn-ch-axis bn-ch-axis--now">OCT</text></svg></div>
<p class="bn-legend"><span class="bn-legend__item"><span class="bn-legend__key bn-legend__key--solid"></span>Paid</span><span class="bn-legend__item"><span class="bn-legend__key bn-legend__key--hatch"></span>Open</span></p>
```

## 10. Toolbar, chips, status strip, bulk bar

**Purpose:** find and act on many rows. `.bn-statstrip` › `__item` (`__label` + big `__num`; `.is-active` acid) works as status
tabs. `.bn-toolbar` › `.bn-search`, `.bn-chip` (`.is-active`, `__count`), `.bn-chip--applied` (acid, with `.bn-chip__x`),
`__spacer`, `__break` (forces a second line), `__label`, `__clear`, sort `.bn-selectbox`. `.bn-bulkbar.bn-inv` › `__count` with
`.bn-count--signal`, small buttons, `__spacer`. **Do:** show the bulk bar only while rows are selected.

```html
<div class="bn-toolbar" role="search">
  <label class="bn-search bn-search--lg"><!-- search --><span class="visually-hidden">Search projects</span><input class="bn-search__input" type="search" placeholder="Search title, client, code"></label>
  <button type="button" class="bn-chip"><!-- building-2 --> Client <!-- chevron-down --></button>
  <span class="bn-toolbar__spacer"></span>
  <label class="bn-row"><span class="bn-toolbar__label">Sort</span><span class="bn-selectbox"><select class="bn-select bn-select--sm"><option>Deadline, soonest</option></select><!-- chevron-down --></span></label>
  <span class="bn-toolbar__break"></span><span class="bn-toolbar__label">Filters</span>
  <span class="bn-chip bn-chip--applied">Discipline: Print<button type="button" class="bn-chip__x" aria-label="Remove filter"><!-- x --></button></span>
</div>
<div class="bn-bulkbar bn-inv" role="region" aria-label="Bulk actions"><p class="bn-bulkbar__count"><span class="bn-count bn-count--signal">3</span>projects selected</p><button type="button" class="bn-btn bn-btn--sm">Archive</button></div>
```

## 11. Table

**Purpose:** dense, ruled data. `.bn-table-wrap` (scrolls horizontally) › `table.bn-table` (`--page` aligns first/last column
with the page padding and keeps ≥ 860 px, `--cell` aligns to a cell's padding, `--compact`). Header buttons `.bn-th-sort`
(`.is-sorted` + `aria-sort` on the `th`); cells `.bn-td-check`, `.bn-td-code`, `.bn-td-title` + `.bn-td-sub`, `.bn-td-num`,
`.bn-td-nowrap`, `.bn-td-actions` › `.bn-rowbtn`. Rows: `.is-selected` (soft acid + 4 px ink bar), `.is-hover`. Select-all uses
`.bn-checkbox.is-mixed`. **Do:** right-align money. **Don't:** zebra-stripe; rules already separate rows.

```html
<div class="bn-table-wrap"><table class="bn-table bn-table--page">
  <thead><tr><th scope="col" class="bn-td-check"><input class="bn-checkbox is-mixed" type="checkbox" aria-label="Select all"></th><th scope="col">Code</th><th scope="col">Project / client</th><th scope="col" class="bn-td-num">Budget</th><th scope="col" aria-sort="ascending"><button type="button" class="bn-th-sort is-sorted">Due <!-- arrow-down --></button></th><th scope="col">Status</th><th scope="col" class="bn-td-actions"><span class="visually-hidden">Actions</span></th></tr></thead>
  <tbody><tr class="is-selected"><td><input class="bn-checkbox" type="checkbox" checked aria-label="Select P-036"></td><td class="bn-td-code">P-036</td><td><a class="bn-td-title" href="#">Tiefgarage Records — label &amp; sleeves</a><span class="bn-td-sub">Tiefgarage Records · Identity, Print</span></td><td class="bn-td-num bn-mono">€14,200</td><td class="bn-mono">23.10.2026</td><td><span class="bn-tag bn-tag--signal">Review</span></td><td class="bn-td-actions"><button type="button" class="bn-rowbtn" aria-label="Actions for P-036"><!-- ellipsis --></button></td></tr></tbody>
</table></div>
```

## 12. Pagination

**Purpose:** page through results. `.bn-pager` › `__info` (mono, `strong` for numbers) + `ul.__list` › `.bn-pager__link`
(`.is-active` + `aria-current="page"`, `.is-disabled` dashed) and `.bn-pager__gap`. Joined squares, numbers zero-padded.

```html
<nav class="bn-pager" aria-label="Pagination"><p class="bn-pager__info">Showing <strong>01–10</strong> of <strong>23</strong></p>
  <ul class="bn-pager__list"><li><span class="bn-pager__link is-disabled" aria-disabled="true"><!-- arrow-left --><span class="visually-hidden">Previous page</span></span></li><li><a class="bn-pager__link is-active" href="#" aria-current="page">01</a></li><li><a class="bn-pager__link" href="#">02</a></li><li><span class="bn-pager__gap">…</span></li><li><a class="bn-pager__link" href="#" aria-label="Next page"><!-- arrow-right --></a></li></ul></nav>
```

## 13. Form fields and form sections

**Purpose:** Swiss-style forms: section head left (big index, title, description), fields right. `.bn-formsec` › `__head`
(`__idx`, `h2.bn-h3`, `__desc`) + `__body` (2 columns, `.bn-field--full` spans both). `.bn-field` › `label.bn-label`
(`.bn-req` "*" + visually-hidden "(required)", `.bn-label__meta` counter) + control + `.bn-help` or `.bn-error` (icon + text,
linked by `aria-describedby`). Controls: `.bn-input` (`--mono`), `.bn-textarea`, `.bn-selectbox` › `.bn-select` + chevron,
`.bn-inputgroup` › `.bn-affix` (+ `--end`). **States:** `.bn-field.is-error` / `.bn-input.is-error` (2 px alarm),
`[disabled]` / `[readonly]` (dashed, sunken), `.is-focus`. Above the form, `.bn-errorsum` lists what to fix.
**Don't:** use placeholder text as the label.

```html
<section class="bn-formsec" aria-labelledby="s2">
  <div class="bn-formsec__head"><p class="bn-formsec__idx" aria-hidden="true">02</p><h2 id="s2" class="bn-h3">Budget &amp; dates</h2><p class="bn-formsec__desc">Net amounts in euro.</p></div>
  <div class="bn-formsec__body">
    <div class="bn-field"><label class="bn-label" for="f-budget"><span>Budget<span class="bn-req" aria-hidden="true">*</span><span class="visually-hidden"> (required)</span></span><span class="bn-label__meta">net</span></label><span class="bn-inputgroup"><span class="bn-affix">EUR</span><input id="f-budget" class="bn-input bn-input--mono" type="text" value="9,600.00"></span><p class="bn-help">96 h at the editorial day rate.</p></div>
    <div class="bn-field is-error"><label class="bn-label" for="f-del">Delivery</label><input id="f-del" class="bn-input bn-input--mono" type="text" value="06.10.2026" aria-invalid="true" aria-describedby="f-del-e"><p id="f-del-e" class="bn-error"><!-- circle-alert -->Delivery must be after the kick-off (19.10.2026).</p></div>
    <div class="bn-field"><label class="bn-label" for="f-code">Project code</label><input id="f-code" class="bn-input bn-input--mono" type="text" value="P-2026-037" disabled></div>
  </div>
</section>
```

## 14. Checkbox, radio, switch, check grid, radio row

**Purpose:** choices. `input.bn-checkbox` (square; `.is-mixed` for indeterminate), `input.bn-radio` (the only circle),
`input.bn-switch` (`role="switch"`; square knob turns acid on ink when on) inside `label.bn-check` (+ `.bn-check__sub`) or
`label.bn-switchrow` (`__text` with `strong` + `span`). `.bn-checkgrid` lays checkboxes in ruled cells (`.is-active` tints the
checked cell); `.bn-radiorow` is a segmented radio group (`.is-active` acid). Disabled = dashed.

```html
<label class="bn-check"><input class="bn-checkbox" type="checkbox" checked><span>Keep me signed in</span></label>
<fieldset class="bn-field"><legend class="bn-label">Confidentiality</legend><div class="bn-radiorow">
  <label class="bn-check"><input class="bn-radio" type="radio" name="conf"><span>Public<span class="bn-check__sub">Can be named in pitches</span></span></label>
  <label class="bn-check is-active"><input class="bn-radio" type="radio" name="conf" checked><span>Until launch</span></label></div></fieldset>
<label class="bn-switchrow"><span class="bn-switchrow__text"><strong>Show on the public site</strong><span>After delivery.</span></span><input class="bn-switch" type="checkbox" role="switch" checked></label>
```

## 15. Drop zone, file list, form bar

**Purpose:** uploads and saving. `label.bn-drop` (dashed; `.is-dragover` = acid, solid) wraps a visually-hidden file input;
`.bn-files` › `.bn-file` (`__ext` square with the extension, `--signal` while uploading; `__name`; `__meta` with an inline
`.bn-meter`; `.is-error`). `.bn-formbar` is sticky at the bottom: `__status` (mono, last save) + `.bn-actions`.

```html
<label class="bn-drop" for="files"><!-- upload --><span class="bn-drop__title">Drop files here or <u>browse</u></span><span class="bn-micro">PDF · JPG · ZIP</span><input id="files" class="visually-hidden" type="file" multiple></label>
<ul class="bn-files"><li class="bn-file"><span class="bn-file__ext bn-file__ext--signal">ZIP</span><span class="bn-stack bn-stack--4"><span class="bn-file__name">Installation_photos_raw.zip</span><span class="bn-file__meta"><span class="bn-meter"><span class="bn-meter__fill bn-w-65"></span></span>64%</span></span><button type="button" class="bn-rowbtn" aria-label="Cancel upload"><!-- x --></button></li></ul>
<div class="bn-formbar"><p class="bn-formbar__status"><!-- circle-check --> Draft saved 14:28</p><div class="bn-actions"><a class="bn-btn bn-btn--ghost" href="#">Cancel</a><button type="submit" class="bn-btn bn-btn--signal">Save &amp; send brief <!-- arrow-right --></button></div></div>
```

## 16. Dropdown menu and tooltip

**Purpose:** row actions, user menu. `.bn-pop` (relative) › trigger (`aria-expanded="true"`) + `.bn-menu` (`role="menu"`, white,
1 px ink, hard 4 px shadow; `--left`, `--static` for documentation) › `__label`, `__item` (`.is-hover` acid, `.is-active` bold,
`--danger`), `__kbd`, `__sep`. Tooltip: `.bn-tip-host` › trigger + `.bn-tooltip` (`role="tooltip"`). **Do:** open menus on an
upper row — the table wrapper clips anything below its last row.

```html
<div class="bn-pop"><button type="button" class="bn-rowbtn is-active" aria-label="Actions for P-037" aria-expanded="true"><!-- ellipsis --></button>
  <div class="bn-menu" role="menu" aria-label="P-037 actions"><p class="bn-menu__label">P-037 · Grauzone</p>
    <a class="bn-menu__item" role="menuitem" href="#"><!-- pencil-line --> Edit brief<span class="bn-menu__kbd">E</span></a>
    <div class="bn-menu__sep" role="separator"></div>
    <a class="bn-menu__item bn-menu__item--danger" role="menuitem" href="#"><!-- archive --> Archive project</a></div></div>
```

## 17. Dialog

**Purpose:** focused tasks and confirmations. `.bn-overlay` (fixed, dotted halftone backdrop) › `section.bn-dialog`
(`role="dialog"`, `aria-modal`, `aria-labelledby`; `--sm`; `--alarm` red head for destructive) › `__head` (mono path + close
icon button), `__body` (`__title`, optional `__grid` for two-column fields), `__foot` (sunken, meta left, actions right).
Hard 8 px offset shadow (acid in dark). **Don't:** stack two dialogs.

```html
<div class="bn-overlay"><section class="bn-dialog" role="dialog" aria-modal="true" aria-labelledby="dlg">
  <header class="bn-dialog__head"><p class="bn-micro bn-micro--ink">05 — Bookings / New</p><button type="button" class="bn-btn bn-btn--ghost bn-btn--icon" aria-label="Close dialog"><!-- x --></button></header>
  <div class="bn-dialog__body"><h2 id="dlg" class="bn-dialog__title">Book a room</h2><div class="bn-dialog__grid">…fields…</div></div>
  <footer class="bn-dialog__foot"><span class="bn-micro">2 days</span><span class="bn-actions"><button type="button" class="bn-btn bn-btn--ghost">Cancel</button><button type="button" class="bn-btn bn-btn--signal">Hold the Galerie</button></span></footer>
</section></div>
```

## 18. Toast and notice

**Purpose:** feedback. `.bn-toasts` (fixed bottom-right; `--top` under the topbar; `--static` in docs) › `.bn-toast.bn-inv`
(`__icon` acid square or `--alarm`, `__body` › `__title` + `__meta`, `__close`), acid offset shadow. Inline `.bn-notice`
(default ink frame, `--quiet`, `--alarm` with a 4 px bar and soft red, or with `.bn-sig` / `.bn-inv`) › icon + `__body` ›
`__title`. Use `role="status"` for success, `role="alert"` for failures.

```html
<div class="bn-toasts" role="status"><div class="bn-toast bn-inv"><span class="bn-toast__icon"><!-- check --></span><div class="bn-toast__body"><span class="bn-toast__title">Studio profile saved</span><span class="bn-toast__meta">14:31</span></div><button type="button" class="bn-toast__close" aria-label="Dismiss"><!-- x --></button></div></div>
<div class="bn-notice bn-notice--alarm"><!-- octagon-alert --><div class="bn-notice__body"><span class="bn-notice__title">3 invoices overdue</span>€14,040 in total, the oldest 26 days.</div></div>
```

## 19. Tabs and index nav

**Purpose:** sub-navigation. `.bn-tabs` › `.bn-tab` (`__idx`, `.is-active` = 4 px ink underline, `aria-current`). Settings
use `.bn-split` › `nav.bn-index` › `__list` (sticky) › `.bn-index__item` (index, label, optional count; `.is-active` acid,
`--alarm` for the danger zone); on phones it becomes a horizontal scroller.

```html
<nav class="bn-tabs" aria-label="Month"><a class="bn-tab" href="#"><span class="bn-tab__idx">10</span>October</a><a class="bn-tab is-active" href="#" aria-current="page"><span class="bn-tab__idx">11</span>November</a></nav>
<div class="bn-split"><nav class="bn-index" aria-label="Settings sections"><div class="bn-index__list"><a class="bn-index__item is-active" href="#invoicing" aria-current="true"><span class="bn-idx">02</span>Invoicing<span class="bn-count">1</span></a></div></nav><div>…sections…</div></div>
```

## 20. Room timeline (bookings)

**Purpose:** a week of rooms at a glance. `.bn-tl-wrap` (scrolls) › `.bn-tl` (`role="table"`, min 780 px) › `__head`
(`__corner`, `__day` with `strong` date; `.is-today` acid) and `__row`s (`__room` + blocks). Blocks `.bn-tl__block` (ink =
confirmed) `--event` (acid, public), `--hold` (hatched option), `--setup` (sunken with an ink bar) placed with
`.bn-from-1…7` (weekday) + `.bn-len-1…7` (days); `strong` + `.bn-tl__meta`.

```html
<div class="bn-tl-wrap"><div class="bn-tl" role="table" aria-label="Room bookings, week 42">
  <div class="bn-tl__head" role="row"><span class="bn-tl__corner bn-micro" role="columnheader">Room</span><span class="bn-tl__day is-today" role="columnheader"><strong>12</strong>Mo</span>…</div>
  <div class="bn-tl__row" role="row"><p class="bn-tl__room" role="rowheader"><strong>Halle A</strong><span class="bn-micro">450 standing</span></p>
    <p class="bn-tl__block bn-tl__block--hold bn-from-1 bn-len-2" role="cell"><strong>Option: test projection</strong><span class="bn-tl__meta">10–22</span></p>
    <p class="bn-tl__block bn-tl__block--event bn-from-5 bn-len-2" role="cell"><strong>Tiefgarage Night 07</strong><span class="bn-tl__meta">Fr 23:00 → Sa 10:00</span></p></div>
</div></div>
```

## 21. Activity log, lists, definition lists

`.bn-log` › `__item` (`__time` mono, `__text`; `.is-signal` highlights the time) · `.bn-list` › `__row` (`__main` › `__title` +
mono `__sub`, `__end` for amount + tag) · `.bn-dl` (mono `dt`, right-aligned `dd`; `--stack` puts values left).

```html
<ol class="bn-log"><li class="bn-log__item is-signal"><span class="bn-log__time">14:21</span><p class="bn-log__text"><strong>Leyla D.</strong> moved P-036 to Review</p></li></ol>
<ul class="bn-list"><li class="bn-list__row"><span class="bn-list__main"><span class="bn-list__title">Tiefgarage Records</span><span class="bn-list__sub">KS-2026-118 · due 30.09</span></span><span class="bn-list__end"><span class="bn-mono">€6,400</span><span class="bn-tag bn-tag--alarm">12 d late</span></span></li></ul>
<dl class="bn-dl"><dt>Floor</dt><dd>640 m²</dd><dt>Height</dt><dd>9.2 m</dd></dl>
```

## 22. Photo placeholders (duotone SVG)

**Purpose:** pictures without loading anything. Put the hidden pattern block (`svg.bn-defs` with `#bn-dk1/2/3`, `#bn-ds1/2`,
`#bn-dw1/2`, `#bn-lk`, `#bn-ls`, `#bn-win`, `#bn-tile`, `#bn-hatch`) once per page — copy it from any screen. `figure.bn-photo`
(`--1x1`, `--3x4`, `--3x2`, `--16x9`, `--21x9`, `--fill`) › `svg` (`viewBox`, `preserveAspectRatio="xMidYMid slice"`,
`role="img"`, `aria-label` describing the photo) + optional `figcaption.bn-photo__cap` (`--top`). Shapes use the fixed
`.bn-ph-k / -s / -c / -c2 / -g / -w` fills, `.bn-ph-line-*` strokes, `.bn-ph-type` / `.bn-ph-mono` text. Scenes in the kit: crowd,
facade, gallery, talk, pool, vinyl, books, cinema, portrait (4 hair variants), map, poster. Photos keep their colours in dark
mode, like real photographs. **Don't:** use `<img src="https://…">`.

```html
<figure class="bn-photo bn-photo--3x2"><svg viewBox="0 0 400 300" preserveAspectRatio="xMidYMid slice" role="img" aria-label="Photo: a speaker at a lectern in a yellow spotlight, audience in silhouette.">
  <rect width="400" height="300" class="bn-ph-k"/><polygon points="196,0 212,0 300,236 110,236" class="bn-ph-s"/><polygon points="196,0 212,0 300,236 110,236" fill="url(#bn-dk1)"/>
  <g class="bn-ph-k"><circle cx="206" cy="120" r="15"/><rect x="186" y="136" width="40" height="70" rx="14"/></g></svg>
  <figcaption class="bn-photo__cap">Fig. 07 — Dachterrasse</figcaption></figure>
```

## 23. Ornaments: crop marks, registration mark, vertical label, ticker

`.bn-crops` draws four print crop marks 14 px outside any box (keep 16 px free around it). `svg.bn-reg` (`--lg`) is a
registration mark (circle + cross + `.bn-reg__dot`). `.bn-vlabel` rotates a mono label 90°. `.bn-ticker` (black in light, acid
in dark) › `__label` (acid "Next") + `p.__track` with `__item`s and square `__sep`s; duplicate the items once inside an
`aria-hidden` span for the loop — `prefers-reduced-motion` stops it.

```html
<div class="bn-ticker" role="region" aria-label="Next at the venue"><span class="bn-ticker__label"><!-- radio --> Next</span>
  <p class="bn-ticker__track"><span class="bn-ticker__item">Fr 16.10 23:00 — Tiefgarage Night 07 — Halle A<span class="bn-ticker__sep"></span></span><span aria-hidden="true">…same items…</span></p></div>
<p class="bn-empty__glyph bn-crops" aria-hidden="true">0</p>
<svg class="bn-reg" viewBox="0 0 28 28" aria-hidden="true"><circle cx="14" cy="14" r="7"/><circle cx="14" cy="14" r="2.5" class="bn-reg__dot"/><path d="M14 0v28M0 14h28"/></svg>
```

## 24. Public site blocks

`.bn-sitehead` (12-col: `__brand` 3, `.bn-sitenav` 6 with `__link` + index, `__end` 3 with `.bn-lang` and Tickets;
`__menu` on phones) · `.bn-hero` › `__meta`, `__mark` (wordmark), `__body` (`__statement`, `__disc`, `__aside` › `.bn-nextcard.bn-sig`),
`__photo` · `.bn-sec` › `.bn-sechead` (`__idx` huge number, `__title`, `__aside`) + `__body` · `.bn-works` › `a.bn-work`
(`__meta`, `__title` highlights on hover, `__client`) · `.bn-theses` › `.bn-thesis` (`__n`, `__title`, `__text`) · `.bn-tt`
timetable (`--bleed`; `__head`, `__row` `.is-today`, `__date` › `__day`, `__time`, `__title`, `__sub`, `__cell`, `__end`;
`.bn-tt__hide-md / -sm` drop columns) · `.bn-rooms` › `.bn-room` · `.bn-people` › `.bn-person` · `.bn-clients` › `.bn-client` ·
`.bn-contact` (`__map`, `__addr` › `address.bn-address`, `__news` › `.bn-newsletter`) · `.bn-feature` (`__media`, `__body`,
`.bn-lineup`) · `.bn-weekhead` · `.bn-infogrid` › `.bn-info` · `.bn-sitefoot.bn-night` (`__cols`, `__col`, `__mark` cropped
wordmark, `__bottom`).

```html
<div class="bn-tt bn-tt--bleed" role="table" aria-label="Program, October">
  <div class="bn-tt__row is-today" role="row"><p class="bn-tt__date" role="cell"><span class="bn-tt__day">12</span><span class="bn-micro bn-micro--ink">Today</span></p><p class="bn-tt__time" role="cell">18:00</p>
    <p role="cell"><a class="bn-tt__title" href="#">Risograph open lab</a><span class="bn-tt__sub">Print a two-colour poster in three hours.</span></p>
    <p class="bn-tt__cell bn-tt__hide-md bn-tt__hide-sm" role="cell">Workshop</p><p class="bn-tt__cell" role="cell">Studio 2</p><p class="bn-tt__cell bn-tt__hide-sm" role="cell">€15</p>
    <p class="bn-tt__end" role="cell"><span class="bn-tag bn-tag--solid"><span class="bn-tag__dot"></span>2 places left</span></p></div>
</div>
```

## 25. Empty state and error page

`.bn-empty` (two columns: `__glyph` huge number with `.bn-crops`, `__body` › micro, `__title`, `__text`, `.bn-actions`, help);
`.bn-empty--compact` inside cells (glyph 72 px: `0`, `?`, `—`, `✓`). `.bn-err` › `__code` (cut by a rule), `__text`, `__links`.
**Do:** say why it is empty and offer the next step.

```html
<section class="bn-empty" aria-labelledby="e"><p class="bn-empty__glyph bn-crops" aria-hidden="true">0</p>
  <div class="bn-empty__body"><p class="bn-micro bn-micro--ink">November 2026 · 0 bookings</p><h2 id="e" class="bn-empty__title">No bookings on the roof in November.</h2>
  <p class="bn-empty__text">The Dachterrasse closes for winter from 01.11 to 28.02.</p><div class="bn-actions"><a class="bn-btn bn-btn--signal" href="#">Book the Galerie instead</a></div></div></section>
```

## 26. Sign-in and drawn fields

`.bn-auth` (7/5 split) › `aside.bn-auth__art.bn-night` (`__arttop`, `__photo`, `__mark` wordmark) + `main.bn-auth__panel` ›
`section.bn-auth__form` (`__row`, `__or`) + `__foot`. Credentials are drawn: `.mock-label` + `.mock-field` (`role="img"`,
`aria-label`), `--secret`, `--empty`, `--focus` (focus ring + caret), `--error`. The submit is a link with `role="button"`.

```html
<div><span class="mock-label">Email</span><div class="mock-field" role="img" aria-label="Email field, filled in">mira@kollektiv.studio</div></div>
<div><span class="mock-label">Password</span><div class="mock-field mock-field--secret mock-field--focus" role="img" aria-label="Password field, filled in">••••••••••••</div></div>
<a href="dashboard.html" class="bn-btn bn-btn--signal bn-btn--lg bn-btn--block bn-btn--between" role="button">Sign in <!-- arrow-right --></a>
```

## Utilities

`.bn-wrap` (max 1520, gutter), `.bn-cols` + `.bn-col-2…12` (12-col gap grid), `.bn-row` (`--top`, `--base`, `--wrap`,
`--between`, `--end`), `.bn-stack` (`--4`, `--12`, `--16`, `--24`), `.bn-gap-*`, `.bn-mt-*`, `.bn-ml-auto`, `.bn-fill`,
`.bn-right`, `.bn-center`, `.bn-nowrap`, `.bn-truncate`, `.bn-hide-sm`, `.bn-only-sm`, `.bn-bt`, `.bn-bb`, `.bn-w-0…100` (steps
of 5), `.bn-actions`, `.visually-hidden`. Documentation helpers: `.bn-demo*`, `.bn-cat__*`, `.bn-swatch*`, `.bn-specimen*`,
`.bn-screen*`, `.bn-grouphead`.
