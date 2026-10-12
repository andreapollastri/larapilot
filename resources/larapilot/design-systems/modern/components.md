# Modern — components

Every class lives in `tokens.css`; `html/components.html` draws each component below in its variants and states (same
numbering). Icons are **Lucide** inline SVG (`.mo-icon` 16 px, `--xs` 12, `--sm` 14, `--lg` 20, `--xl` 24), always
`aria-hidden="true"`, stroke `currentColor`. State classes: `.is-active`, `.is-hover`, `.is-focus`, `.is-invalid`,
`.is-selected`, `.is-mixed`, `.is-dragover`, `.is-loading`, `.is-disabled`, `.is-done`, `.is-current`, `.is-new`,
`.is-dim`, `.is-empty`. Body marker: `<body class="mo-mockup mo-body">` (add `mo-body--site` on public pages).

## Mapping — mockup → real code (Blade components suggested)

| Mockup | Build it as |
| --- | --- |
| `.mo-app`, `.mo-sidebar`, `.mo-main`, `.mo-topbar` | `resources/views/components/layouts/app.blade.php` with a `<x-mo.sidebar>` slot |
| `.mo-btn` | `<x-mo.button variant="primary|secondary|ghost|danger|inverse" size="xs|sm|lg|xl" icon kbd>` |
| `.mo-input`, `.mo-select`, `.mo-textarea`, `.mo-field` | `<x-mo.input>`, `<x-mo.select>`, `<x-mo.textarea>` wrapped in `<x-mo.field label help error>` |
| `.mo-check`, `.mo-radio`, `.mo-switch` | native inputs with `appearance: none` (keep them native for forms and Livewire `wire:model`) |
| `.mo-card`, `.mo-stat`, `.mo-plot`, `.mo-barlist` | `<x-mo.card>`, `<x-mo.stat :value :delta :series>`; charts with any SVG lib fed `--mo-c1…c4` |
| `.mo-table`, `.mo-bulkbar`, `.mo-pager` | Livewire table component (search, filters, sort in `#[Url]`), `$this->selected` drives the bulk bar |
| `.mo-cmd` | Alpine/Livewire modal on `⌘K`, or `cmdk` in React / a combobox in Vue under Inertia |
| `.mo-dialog`, `.mo-toast`, `.mo-menu` | `<x-mo.dialog>` (native `<dialog>`), toast stack fed by session flashes, Alpine dropdown |

---

## 1. App shell, navigation and command trigger

**Purpose:** the inset-panel layout — sidebar on the canvas, content in a rounded panel. **Anatomy:** `.mo-app` ›
`nav.mo-sidebar` (`.mo-sidebar__top` › `.mo-ws` + `.mo-menu-btn`; `.mo-cmdk`; `.mo-nav` › `.mo-nav__label`,
`.mo-nav__item` [+ `.mo-nav__count`, `.mo-nav__count--accent`, `.mo-dot--c1…c4`]; `.mo-sidebar__foot` › `.mo-usage`,
`.mo-user`) + `main.mo-main` › `header.mo-topbar` (`.mo-crumbs`, `.mo-topbar__end`) + `.mo-page`.
**Variants:** below 900 px the sidebar becomes a sticky glass top bar with the menu button; nav and footer hide.
**Do:** one active item with `aria-current="page"`. **Don't:** give the sidebar its own background — it sits on `--mo-bg`.

```html
<div class="mo-app">
  <nav class="mo-sidebar" aria-label="Workspace">
    <div class="mo-sidebar__top"><a class="mo-ws" href="dashboard.html"><span class="mo-logo">…</span><span class="mo-ws__text"><span class="mo-ws__name">Acme Inc.</span><span class="mo-ws__sub">Growth plan</span></span></a>
      <button type="button" class="mo-btn mo-btn--ghost mo-btn--icon mo-menu-btn" aria-label="Open navigation"><!-- menu --></button></div>
    <button type="button" class="mo-cmdk"><!-- search --><span>Search or jump to…</span><span class="mo-kbds"><kbd class="mo-kbd">⌘</kbd><kbd class="mo-kbd">K</kbd></span></button>
    <div class="mo-nav"><a class="mo-nav__item is-active" href="dashboard.html" aria-current="page"><!-- icon -->Overview</a></div>
  </nav>
  <main class="mo-main" id="main">
    <header class="mo-topbar"><nav class="mo-crumbs" aria-label="Breadcrumb">…</nav><div class="mo-topbar__end">…</div></header>
    <div class="mo-page">…</div>
  </main>
</div>
```

## 2. Buttons

**Anatomy:** `.mo-btn` + one variant: `--primary` (accent, top sheen, glow on hover), `--secondary` (surface, hairline,
inner highlight), `--ghost`, `--inverse`, `--danger`, `--danger-ghost`. Sizes `--xs` 24, `--sm` 28, default 32, `--lg` 40,
`--xl` 46; `--icon` square; `--block` full width; `.mo-btn-group`. **States:** `:hover`/`.is-hover`, `:disabled` or
`aria-disabled="true"`, `.is-loading` (spinner). A `.mo-kbd` inside shows the shortcut.
**Do:** one primary per view. **Don't:** use primary for destructive actions.

```html
<button type="button" class="mo-btn mo-btn--primary"><!-- plus -->New<kbd class="mo-kbd">N</kbd></button>
<button type="button" class="mo-btn mo-btn--secondary mo-btn--sm">Share</button>
<button type="button" class="mo-btn mo-btn--ghost mo-btn--icon" aria-label="Settings"><!-- settings --></button>
<button type="button" class="mo-btn mo-btn--danger">Delete workspace</button>
```

## 3. Keyboard hints

**Anatomy:** `kbd.mo-kbd`, grouped in `.mo-kbds`. Inside primary buttons, the bulk bar and tooltips it inverts.
**Do:** show the real shortcut next to frequent actions and in menus (`.mo-menu__end`). **Don't:** invent shortcuts the app lacks.

```html
<span class="mo-kbds"><kbd class="mo-kbd">⌘</kbd><kbd class="mo-kbd">K</kbd></span>
```

## 4. Segmented control

**Anatomy:** `.mo-seg` › `button.mo-seg__item` (`.is-active` + `aria-pressed="true"`). `--lg` for forms, `--block` to fill.
**Do:** 2–5 options, date ranges, view modes. **Don't:** use it for navigation between pages (use tabs).

```html
<div class="mo-seg" role="group" aria-label="Date range"><button type="button" class="mo-seg__item">7d</button><button type="button" class="mo-seg__item is-active" aria-pressed="true">30d</button></div>
```

## 5. Tabs and saved views

**Anatomy:** `.mo-tabs` › `a.mo-tab` (+ `.mo-tab__count`; active has an accent underline with glow). Saved views:
`.mo-views` › `a.mo-view` (+ `.mo-dot--c1…c4`, `.mo-view__count`) — a pill row that scrolls sideways on phones.
**Do:** colour dots identify a saved view everywhere (sidebar and list). **Don't:** mix both rows on one level.

```html
<div class="mo-views" role="tablist" aria-label="Saved views">
  <a class="mo-view is-active" role="tab" aria-selected="true" href="#">All people<span class="mo-view__count">48,312</span></a>
  <a class="mo-view" role="tab" aria-selected="false" href="#"><span class="mo-dot mo-dot--c3"></span>Churn risk<span class="mo-view__count">612</span></a>
</div>
```

## 6. Filter chips

**Anatomy:** `.mo-chip` › `.mo-chip__key` (icon + property), `.mo-chip__op`, `.mo-chip__val`, `button.mo-chip__x`.
`--accent` for filters that differ from the saved view, `--add` (dashed) opens the property menu.
**Do:** read like a sentence ("Plan is Growth"). **Don't:** hide active filters in a popover.

```html
<span class="mo-chip mo-chip--accent"><span class="mo-chip__key"><!-- credit-card -->Plan</span><span class="mo-chip__op">is</span><span class="mo-chip__val">Pro, Growth</span><button type="button" class="mo-chip__x" aria-label="Remove plan filter"><!-- x --></button></span>
<button type="button" class="mo-chip mo-chip--add"><span><!-- list-filter -->Filter</span></button>
```

## 7. Text fields, input group, search, select, textarea

**Anatomy:** `.mo-field` › `label.mo-label` (+ `.mo-req`, `.mo-label__opt`) + control + `.mo-help` / `.mo-error`.
Controls: `.mo-input` (`--lg`, `--mono`), `.mo-input-group` › `.mo-input-group__addon` + `.mo-input` (+ `.mo-select`),
`.mo-search` (icon + input + kbd), `.mo-select` (native, custom chevron), `.mo-textarea`. **States:** `.is-focus`
(3 px ring), `.is-invalid` + `aria-invalid` + `aria-describedby`, `disabled`. **Do:** helper text under the control.
**Don't:** put the label inside the control as a placeholder.

```html
<div class="mo-field">
  <label class="mo-label" for="name">Name <span class="mo-req" aria-hidden="true">*</span></label>
  <input class="mo-input is-invalid" id="name" type="text" aria-invalid="true" aria-describedby="name-err">
  <p class="mo-error" id="name-err"><!-- circle-alert -->Give the funnel a name</p>
</div>
<div class="mo-input-group"><span class="mo-input-group__addon">pulseboard.app/</span><input class="mo-input" type="text" value="acme" aria-label="Workspace URL"></div>
```

## 8. Checkbox, radio, switch, choice, option card

**Anatomy:** native inputs `.mo-check` (`.is-mixed` for select-all), `.mo-radio`, `.mo-switch` (`role="switch"`), in a
`label.mo-choice` › `.mo-choice__text` › `.mo-choice__title` + `.mo-choice__help`. `.mo-option-card` (`.is-selected`) for
big radio choices. **Do:** switches for settings that apply immediately, checkboxes inside forms that need Save.

```html
<label class="mo-choice"><input class="mo-switch" type="checkbox" role="switch" checked><span class="mo-choice__text"><span class="mo-choice__title">Exclude internal traffic</span><span class="mo-choice__help">Drops @acme.io and office IPs.</span></span></label>
```

## 9. Drop zone and file row

**Anatomy:** `.mo-drop` › `.mo-icon-tile` + `.mo-drop__title` + text; `.is-dragover` (accent border, glow). Uploaded file:
`.mo-file` › icon + `.mo-file__meta` + remove button. **Don't:** hide the accepted types and size.

```html
<div class="mo-drop"><span class="mo-icon-tile mo-icon-tile--lg"><!-- cloud-upload --></span><p class="mo-drop__title">Drop a CSV to replace the steps</p><p>or <a class="mo-link" href="#">browse files</a> · .csv up to 1 MB</p></div>
```

## 10. Badges, tags, status, live, delta

**Anatomy:** `.mo-badge` (pill, `--success|warning|danger|info|accent|outline|solid`, `--lg`, optional `.mo-badge__dot`);
`.mo-tag` (mono key=value, `--accent`); `.mo-status--*` (dot + label); `.mo-live` › `.mo-live__dot` (pulsing);
`.mo-delta--up|down|flat` with an arrow icon. **Do:** always a word with the colour. **Don't:** use badges as buttons.

```html
<span class="mo-badge mo-badge--success"><span class="mo-badge__dot"></span>Active</span>
<span class="mo-tag">plan=growth</span>
<span class="mo-delta mo-delta--up"><!-- arrow-up-right -->12.4%</span>
<span class="mo-live"><span class="mo-live__dot"></span>Live · 1,284 events/min</span>
```

## 11. Avatars, icon tiles, logo

**Anatomy:** `.mo-avatar` (initials on a gradient `--1…6`; sizes `--sm` 20, default 24, `--lg` 32, `--xl` 44; `--sq`),
`.mo-avatar-stack`; `.mo-icon-tile` (`--lg`, `--neutral`); `.mo-logo` (`--lg`), `.mo-brand`.

```html
<span class="mo-avatar mo-avatar--lg mo-avatar--2">LS</span>
<span class="mo-icon-tile"><!-- zap --></span>
```

## 12. Cards and bento grid

**Anatomy:** `.mo-card` › `.mo-card__head` (`--bordered`) › `.mo-card__title` + `.mo-card__sub`; `.mo-card__body`
(`--flush`); `.mo-card__foot`. Variants: `--glass` (translucent, solid fallback), `--glow` (gradient hairline + glow, one
per view), `--inset`, `--hover`. Layout: `.mo-bento` (12 columns) with `.mo-col-3…12`, `.mo-row-2`.

```html
<section class="mo-bento"><article class="mo-card mo-col-8"><div class="mo-card__head"><h2 class="mo-card__title">Daily active users</h2></div><div class="mo-card__body">…</div></article></section>
```

## 13. Stat card with sparkline

**Anatomy:** `.mo-card` › `.mo-stat` › `.mo-stat__label` (icon) + `.mo-stat__row` (`.mo-stat__value` + `.mo-delta`) +
`.mo-stat__note` + `.mo-stat__spark` (SVG area + line, flush to the card edge). Spark gradients need the `.mo-defs` SVG.
**Do:** label in sentence case, a named comparison period. **Don't:** add axes to sparklines.

```html
<article class="mo-card"><div class="mo-stat">
  <p class="mo-stat__label"><!-- users-round -->Weekly active users</p>
  <div class="mo-stat__row"><span class="mo-stat__value">48,312</span><span class="mo-delta mo-delta--up">12.4%</span></div>
  <p class="mo-stat__note">vs 42,980 in the previous period</p>
  <div class="mo-stat__spark"><svg class="mo-spark" viewBox="0 0 120 40" preserveAspectRatio="none" aria-hidden="true"><path class="mo-spark__area" d="M0,28 … L120,40 L0,40Z"/><path class="mo-spark__line" d="M0,28 …"/></svg></div>
</div></article>
```

## 14. Area / line chart with tooltip and legend

**Anatomy:** `.mo-plot` › `.mo-plot__y` (5 labels, top to bottom), `.mo-plot__area[role=img]` › SVG (`viewBox="0 0 300 100"`,
`preserveAspectRatio="none"`, `.mo-plot__line`, `--muted` for the comparison, `.mo-spark__area` wash) + `.mo-plot__cursor`,
`.mo-plot__dot`, `.mo-tooltip`; `.mo-plot__x`. `.mo-legend` › `.mo-legend__item` › `.mo-legend__line` / `.mo-dot`.
**Do:** 2 px lines, 10–28% area wash, one axis. **Don't:** dual axes, dashed gridlines, numbers on every point.

```html
<div class="mo-plot">
  <div class="mo-plot__y" aria-hidden="true"><span>48K</span><span>36K</span><span>24K</span><span>12K</span><span>0</span></div>
  <div class="mo-plot__area" role="img" aria-label="Daily active users rose from 31,200 to 46,300">
    <svg viewBox="0 0 300 100" preserveAspectRatio="none" aria-hidden="true"><path class="mo-plot__line mo-plot__line--muted" d="…"/><path class="mo-spark__area" d="…"/><path class="mo-plot__line" d="…"/></svg>
    <span class="mo-plot__cursor" style="left:72%"></span><span class="mo-plot__dot" style="left:72%;top:10%"></span>
  </div>
  <div class="mo-plot__x" aria-hidden="true"><span>12 Sep</span><span>11 Oct</span></div>
</div>
```

## 15. Columns, bar list, funnel, donut, heatmap

**Anatomy:** stacked columns `.mo-cols` › `.mo-cols__col` (`.is-current`, `.is-dim`) › `i.mo-cols__seg.mo-dot--cN.mo-pct-NN`
+ `.mo-cols-x`; bar list `.mo-barlist` › `.mo-barlist__row.mo-pct-NN` (`--c2`) › `__label` + `__value`; funnel `.mo-funnel`
(`--3` for three steps) › `.mo-funnel__step` › `__track` › `__bar.mo-pct-NN`, `__value`, `__name`, `__rate`; donut `.mo-donut`
(SVG circles with `pathLength="100"`) + `.mo-donut__center`; heatmap `table.mo-heat` with `td.mo-heat-1…9` (sequential
violet; 8–9 switch the text colour). `.mo-pct-0…100` (step 5) sets the size of any bar.
**Do:** categorical order violet → cyan → amber → pink, always with a legend. **Don't:** a 5th hue — fold into "Other".

```html
<div class="mo-barlist"><div class="mo-barlist__row mo-pct-65"><span class="mo-barlist__label"><!-- icon -->button_clicked</span><span class="mo-barlist__value">1.57M</span></div></div>
<table class="mo-heat"><tbody><tr><td class="mo-heat__cohort">7 Sep · 3,042</td><td class="mo-heat-9">100%</td><td class="mo-heat-6">61%</td><td class="is-empty"></td></tr></tbody></table>
```

## 16. Data table, row selection, bulk bar, pagination

**Anatomy:** `.mo-table-wrap` (scrolls sideways) › `table.mo-table` (`--compact`); header `button.mo-sort` (`.is-sorted`
+ `aria-sort` on the `th`); `.mo-table__check`, `.mo-table__actions`, `.mo-num`, `.mo-table__strong`; rows `.is-selected`
(accent wash + left rule), `.is-hover`; `.mo-cell-user` (avatar + name + email); `.mo-minibars` (14-day activity).
`.mo-bulkbar` (inverse floating pill: count, actions, `.mo-bulkbar__danger`, clear) and `nav.mo-pager` › `.mo-pager__page`.

```html
<div class="mo-table-wrap"><table class="mo-table"><caption class="visually-hidden">People</caption>
  <thead><tr><th scope="col" class="mo-table__check"><input class="mo-check is-mixed" type="checkbox" aria-label="Select all"></th><th scope="col" aria-sort="descending"><button type="button" class="mo-sort is-sorted">Events<!-- arrow-down --></button></th></tr></thead>
  <tbody><tr class="is-selected"><td><input class="mo-check" type="checkbox" checked aria-label="Select Lucía Serrano"></td><td class="mo-num">12,904</td></tr></tbody>
</table></div>
<div class="mo-bulkbar" role="toolbar" aria-label="Bulk actions"><span class="mo-bulkbar__count">2 selected</span><button type="button" class="mo-btn mo-btn--ghost mo-btn--sm">Export</button></div>
```

## 17. Dropdown menu, tooltip, popover

**Anatomy:** `.mo-dropdown` (`--left`) › trigger + `.mo-menu[role=menu]` › `.mo-menu__label`, `button.mo-menu__item`
(`.is-active`, `--danger`, `.mo-menu__end` for kbd), `.mo-menu__sep`. `.mo-tip[role=tooltip]` (inverse). `.mo-popover`.

```html
<div class="mo-dropdown"><button type="button" class="mo-btn mo-btn--ghost mo-btn--icon mo-btn--xs" aria-expanded="true" aria-label="Row actions"><!-- ellipsis --></button>
  <div class="mo-menu" role="menu"><button type="button" class="mo-menu__item is-active" role="menuitem"><!-- eye -->Open profile<span class="mo-menu__end"><kbd class="mo-kbd">↵</kbd></span></button><div class="mo-menu__sep"></div><button type="button" class="mo-menu__item mo-menu__item--danger" role="menuitem"><!-- trash-2 -->Delete</button></div></div>
```

## 18. Command palette

**Anatomy:** `.mo-cmd[role=dialog]` › `.mo-cmd__search` (icon, `.mo-cmd__scope`, query, `.mo-cmd__caret`) +
`.mo-cmd__list` › `.mo-cmd__label`, `.mo-cmd__item` (`.is-active` with accent rule, `.mo-mark` for the match,
`.mo-cmd__hint`) + `.mo-cmd__foot` (key legend). Show it in `.mo-stage` (inline) or `.mo-overlay` (full page).

```html
<div class="mo-cmd" role="dialog" aria-label="Command palette">
  <div class="mo-cmd__search"><!-- search --><span>churn</span><span class="mo-cmd__caret"></span></div>
  <div class="mo-cmd__list"><p class="mo-cmd__label">Saved views</p><div class="mo-cmd__item is-active"><!-- users-round --><span><mark class="mo-mark">Churn</mark> risk</span><span class="mo-cmd__hint">Open<kbd class="mo-kbd">↵</kbd></span></div></div>
  <div class="mo-cmd__foot"><span><kbd class="mo-kbd">↑</kbd><kbd class="mo-kbd">↓</kbd>Navigate</span><span><kbd class="mo-kbd">esc</kbd>Close</span></div>
</div>
```

## 19. Dialog and toasts

**Anatomy:** `.mo-dialog[role=dialog][aria-modal]` › `.mo-dialog__head` (`.mo-dialog__title`, `.mo-dialog__desc`),
`.mo-dialog__body`, `.mo-dialog__foot`. Toasts: `.mo-toasts[role=status]` › `.mo-toast` (`--success`, `--danger`) ›
icon + `.mo-toast__body` (`__title`, `__text`, `__actions`). **Do:** confirm destructive actions by typing the name.

```html
<div class="mo-toasts" role="status"><div class="mo-toast mo-toast--success"><!-- circle-check --><div class="mo-toast__body"><p class="mo-toast__title">Invitation sent</p><p class="mo-toast__text">wei.chen@acme.io will get an email.</p></div></div></div>
```

## 20. Callouts, meters, code

**Anatomy:** `.mo-callout` (`--info|warning|danger|accent`) › icon + text with `.mo-callout__title`. `.mo-meter`
(`--warning|danger|lg`) › `.mo-meter__bar.mo-pct-NN` (violet→pink gradient). Code: `.mo-code-head` + `pre.mo-code` with
`.mo-code__k|s|f|n|c` tokens; `.mo-inline-code`; `.mo-secret` › `.mo-secret__mask` for API keys.

```html
<div class="mo-callout mo-callout--warning"><!-- triangle-alert --><p><span class="mo-callout__title">80% of your events used.</span> Overage starts on 28 Oct.</p></div>
<div class="mo-meter"><div class="mo-meter__bar mo-pct-80"></div></div>
```

## 21. Empty state, onboarding steps, skeleton

**Anatomy:** `.mo-empty` › `.mo-empty__art` (SVG with `.mo-art-*` fills, glow behind) or an `.mo-icon-tile`,
`.mo-empty__title`, `.mo-empty__text`, `.mo-empty__actions`. `.mo-steps` › `.mo-step` (`.is-done`, `.is-current`) ›
`.mo-step__num`, `__title`, `__text`. `.mo-skel` (`--lg`, `--block`, `--w40|60|80`) shimmers unless reduced motion.
**Do:** say what the feature does and offer one primary action. **Don't:** show an empty chart frame.

```html
<div class="mo-empty"><h1 class="mo-empty__title">No alerts yet</h1><p class="mo-empty__text">Alerts watch any metric and ping Slack the minute something drifts.</p><div class="mo-empty__actions"><a class="mo-btn mo-btn--primary" href="#">Create your first alert</a></div></div>
```

## 22. Settings layout, plan card, step builder, sticky actions

**Anatomy:** `.mo-settings` › `nav.mo-subnav` (`__label`, `__item.is-active`) + sections `.mo-pref` (`__title`, `__desc`
on the left, card on the right); `.mo-formgrid` (`--1`, `--3`, `.mo-span-2`); `.mo-list` › `.mo-list__item`;
`.mo-plan-card` (`__price`); `.mo-danger-zone`. Forms: `.mo-builder` › `.mo-builder__step` (`.is-invalid`; `__grip`,
`__num`, `__fields`, `__rate`) and `.mo-sticky-actions` (+ `__note`) at the bottom of `.mo-page`.

```html
<section class="mo-pref"><div><h2 class="mo-pref__title">General</h2><p class="mo-pref__desc">Name and defaults.</p></div><div class="mo-card"><div class="mo-card__body">…</div></div></section>
<div class="mo-sticky-actions"><a class="mo-btn mo-btn--ghost" href="#">Cancel</a><button type="button" class="mo-btn mo-btn--primary">Create funnel<span class="mo-kbds"><kbd class="mo-kbd">⌘</kbd><kbd class="mo-kbd">↵</kbd></span></button></div>
```

## 23. Drawn fields (sign-in mockups)

**Anatomy:** `.mock-group` › `span.mock-label` (+ link) + `div.mock-field[role=img][aria-label]` (`--secret`, `--empty`,
`--focus` with caret, `--error`, `.mock-field__end` for a trailing icon). Identical to `.mo-input` at 38 px.

```html
<div class="mock-group"><span class="mock-label">Password <a href="#">Forgot?</a></span><div class="mock-field mock-field--secret" role="img" aria-label="Password field, filled in">••••••••</div></div>
<a href="dashboard.html" class="mo-btn mo-btn--primary mo-btn--lg mo-btn--block" role="button">Sign in</a>
```

## 24. Auth card and SSO

**Anatomy:** `.mo-auth` (glow + `.mo-grid-bg`) › `.mo-auth__top`, `.mo-auth__center` › `.mo-auth__card` (glass) ›
`.mo-auth__head`, `.mo-sso` (secondary `--lg` buttons with `.mo-brand-glyph`, "Last used" badge), `.mo-divider`, fields,
submit; `.mo-auth__foot`.

```html
<div class="mo-sso"><a href="dashboard.html" class="mo-btn mo-btn--secondary mo-btn--lg" role="button"><svg class="mo-brand-glyph" viewBox="0 0 24 24" aria-hidden="true">…</svg>Continue with Google<span class="mo-badge mo-badge--accent">Last used</span></a></div>
<p class="mo-divider">or with email</p>
```

## 25. Marketing site blocks

**Anatomy:** `.mo-site-header` (glass, sticky) › `.mo-site-header__inner` › `.mo-brand`, `.mo-site-nav`,
`.mo-site-header__end` (`.mo-site-menu` on phones); `.mo-hero` (`::before` glow, `.mo-grid-bg`) › `.mo-announce`,
`.mo-display` with `.mo-fade-text` / `.mo-gradient-text`, `.mo-lead`, `.mo-hero__ctas`, `.mo-hero__note`; `.mo-shot`
(`__frame`, `__bar`, `__lights`, `__url`, `__body`, `__side`, `__main`) — a product shot built from the app classes;
`.mo-logos` (`.mo-wordmark--serif|wide|mono|round|light`); `.mo-band` (`--tight`, `--line`) › `.mo-band__head`
(`--left`) + `.mo-eyebrow` + `.mo-title-site`; `.mo-feature` (`--hero`, `__visual`, `__title`, `__text`) with
`.mo-stream`, `.mo-msg`; `.mo-quote` (`--lg`, `__metric`); `.mo-plans` (`--4`) › `.mo-plan` (`--featured`);
`.mo-slider`; `table.mo-compare`; `.mo-faq` › `details.mo-faq__item`; `.mo-changelog`; `.mo-cta`; `.mo-footer`.

```html
<section class="mo-hero"><div class="mo-grid-bg" aria-hidden="true"></div><div class="mo-container mo-hero__inner">
  <a class="mo-announce" href="#"><span class="mo-badge mo-badge--solid">New</span>Session replay is in public beta</a>
  <h1 class="mo-display"><span class="mo-fade-text">Know what your product</span><br><span class="mo-gradient-text">is really doing.</span></h1>
  <p class="mo-lead">…</p><div class="mo-hero__ctas"><a class="mo-btn mo-btn--primary mo-btn--xl" href="#">Start free</a></div>
</div></section>
```
