# Nordic SaaS: components

Every component is an `ns-` class that reads the tokens in `tokens.css`, so one markup works in light and
dark. All of them are drawn in `html/components.html`; the screens show them in context. Icons are Lucide
(24 px viewBox) inline with `class="ns-icon"` (16 px, 1.75 stroke), `ns-icon--sm` 14, `--lg` 20, `--xl` 24,
always `aria-hidden="true"`.

Tone in one line: paper surfaces, hairlines before shadows, one fjord-blue accent for the primary action and
links, ink (not the accent) for "current" states, status colours only for status.

---

## 1. App shell

**Purpose** frame of every signed-in screen. **Anatomy** `ns-app` grid → `ns-sidebar` (brand, workspace
switcher, `ns-nav` groups, footer) on the canvas, and `ns-main`, a raised sheet with `ns-topbar` (menu button
and brand on phones, search with ⌘K, actions) and `main.ns-content`. **States** the current nav item has
`aria-current="page"` and becomes a white chip with an accent icon; `ns-nav__count--alert` for overdue counts.
Below 1024 px the sidebar disappears and the top bar shows `ns-topbar__menu` + `ns-topbar__brand`.

```html
<a class="ns-skip-link" href="#main">Skip to content</a>
<div class="ns-app">
  <aside class="ns-sidebar" aria-label="Sidebar">
    <a href="dashboard.html" class="ns-sidebar__brand"><svg class="ns-brand__mark" viewBox="0 0 28 28" aria-hidden="true">…</svg>Fjord</a>
    <nav aria-label="Main" class="ns-nav">
      <a href="dashboard.html" class="ns-nav__item" aria-current="page"><svg class="ns-icon" aria-hidden="true">…</svg>Overview</a>
      <a href="resource-list.html" class="ns-nav__item"><svg class="ns-icon" aria-hidden="true">…</svg>Invoices<span class="ns-nav__count">24</span></a>
    </nav>
  </aside>
  <div class="ns-main">
    <header class="ns-topbar">
      <button type="button" class="ns-btn ns-btn--ghost ns-btn--icon ns-topbar__menu" aria-label="Open menu">…</button>
      <a href="dashboard.html" class="ns-topbar__brand">Fjord</a>
      <label class="ns-search"><svg class="ns-icon" aria-hidden="true">…</svg><span class="visually-hidden">Search</span><input type="search" placeholder="Search invoices, clients, amounts"><span class="ns-kbd" aria-hidden="true">⌘K</span></label>
    </header>
    <main id="main" class="ns-content">…</main>
  </div>
</div>
```

**Do** keep at most two nav groups plus settings. **Don't** colour the sidebar: it lives on the canvas.

## 2. Page header and breadcrumb

**Purpose** title, context, actions. **Anatomy** optional `ns-breadcrumb` (slash separators), `ns-page-header`
with `__title` (28 px, weight 500), `__subtitle` (muted), `__actions` (secondary first, primary last).

```html
<nav class="ns-breadcrumb" aria-label="Breadcrumb"><a href="resource-list.html">Invoices</a><span class="ns-breadcrumb__sep" aria-hidden="true">/</span><span aria-current="page">INV-2026-041</span></nav>
<div class="ns-page-header">
  <div><h1 class="ns-page-header__title">Invoices</h1><p class="ns-page-header__subtitle">24 invoices in 2026 · €18,420 still open</p></div>
  <div class="ns-page-header__actions"><a href="#" class="ns-btn ns-btn--secondary">Export</a><a href="#" class="ns-btn ns-btn--primary">New invoice</a></div>
</div>
```

## 3. Button

**Purpose** actions. **Variants** `ns-btn--primary` (accent, one per view), `--secondary` (paper + hairline),
`--ghost` (toolbars, tables), `--danger`, `--danger-ghost`, `--inverse` / `--inverse-ghost` (on dark panels).
**Sizes** `--sm` 30 px, default 36 px, `--lg` 44 px; `--icon` square (needs `aria-label`), `--block` full width.
**States** hover (`is-hover` draws it statically), `:focus-visible` 2 px accent outline, `disabled` 45%.
Extras: `ns-btn__kbd` shortcut hint, `ns-btn-group` joined buttons.

```html
<button type="button" class="ns-btn ns-btn--primary"><svg class="ns-icon" aria-hidden="true">…</svg>Send invoice</button>
<button type="button" class="ns-btn ns-btn--secondary">Save draft</button>
<button type="button" class="ns-btn ns-btn--ghost ns-btn--icon" aria-label="Download PDF"><svg class="ns-icon" aria-hidden="true">…</svg></button>
<button type="button" class="ns-btn ns-btn--danger" disabled>Close workspace</button>
```

**Do** use `<a class="ns-btn" role="button">` only on sign-in screens. **Don't** put two primaries side by side.

## 4. Badge (status pill)

**Purpose** status. **Anatomy** `ns-badge` + `ns-badge__dot`. **Variants** `--success` (Paid), `--danger`
(Overdue), `--warning` (Due soon, Partially paid), `--accent` (Sent, Viewed), none (Draft), `--outline`, `--lg`.

```html
<span class="ns-badge ns-badge--success"><span class="ns-badge__dot"></span>Paid</span>
<span class="ns-badge ns-badge--danger"><span class="ns-badge__dot"></span>Overdue</span>
<span class="ns-badge"><span class="ns-badge__dot"></span>Draft</span>
```

## 5. Delta, dot, kbd

`ns-delta--up` / `--down` for changes (moss / brick, with a trending icon); `ns-dot` + `--success --warning
--danger --accent` for inline status; `ns-kbd` for shortcuts.

```html
<span class="ns-delta ns-delta--up"><svg class="ns-icon ns-icon--sm" aria-hidden="true">…</svg>18%</span>
<span class="ns-dot ns-dot--danger"></span> 2 invoices · oldest 14 days
<span class="ns-kbd">⌘K</span>
```

## 6. Avatar

Initials in a circle (`ns-avatar`, 32 px; `--sm` 24, `--lg` 40, `--xl` 64), tones `--sand --moss --clay
--stone` (default is accent), `--tile` for a square client mark, `ns-avatar-group` overlaps with a paper ring.
Always `aria-hidden="true"` next to the visible name.

```html
<span class="ns-avatar ns-avatar--sand" aria-hidden="true">IS</span>
<div class="ns-avatar-group"><span class="ns-avatar" aria-hidden="true">KH</span><span class="ns-avatar ns-avatar--clay" aria-hidden="true">MO</span></div>
```

## 7. Card

`ns-card` (hairline, 12 px radius) with `__header` (+ `--plain` without rule), `__title`, `__subtitle`,
`__body` (`--flush` for tables), `__footer` (canvas tint). `--raised` adds a shadow, `--sunken` uses the canvas.

```html
<section class="ns-card" aria-labelledby="coming-title">
  <div class="ns-card__header"><div><h2 id="coming-title" class="ns-card__title">Coming in</h2><p class="ns-card__subtitle">€15,270 due in the next 3 weeks</p></div></div>
  <div class="ns-card__body">…</div>
  <div class="ns-card__footer"><a href="#" class="ns-link ns-text-sm">All open invoices</a></div>
</section>
```

## 8. Stats

`ns-stats` (a `dl`, 1 px rules between tiles; 1, 2 then 4 columns) of `ns-stat`: `__label` (icon + text),
`__value` (28 px, tabular, `small` for units), `__meta` (delta or dot + context). Always fill the row.

```html
<dl class="ns-stats">
  <div class="ns-stat"><dt class="ns-stat__label">Outstanding</dt><dd class="ns-stat__value">€18,420</dd><dd class="ns-stat__meta">6 open invoices</dd></div>
  <div class="ns-stat"><dt class="ns-stat__label">Average time to pay</dt><dd class="ns-stat__value">11<small>days</small></dd><dd class="ns-stat__meta"><span class="ns-delta ns-delta--up">3 days</span>faster than Q2</dd></div>
</dl>
```

## 9. Field, input, select, textarea

`ns-field` stacks `ns-label` (+ `ns-label__req` red asterisk with `aria-hidden`, `ns-label__opt` "Optional"),
the control and `ns-hint` / `ns-error-text`. Controls: `ns-input` (36 px, `--sm`, `--num` right-aligned
tabular), `ns-select` (chevron from a token), `ns-textarea`. **States** focus = accent border + 3 px ring
(`is-focus` to draw it), invalid = `aria-invalid="true"` (brick border + ring), `disabled` = sunken.

```html
<div class="ns-field">
  <label class="ns-label" for="billing-email">Billing email <span class="ns-label__req" aria-hidden="true">*</span></label>
  <input id="billing-email" type="email" class="ns-input" placeholder="billing@nordlysfestival.no" required aria-invalid="true" aria-describedby="billing-email-error">
  <p id="billing-email-error" class="ns-error-text"><svg class="ns-icon ns-icon--sm" aria-hidden="true">…</svg>Add the address the invoice should go to.</p>
</div>
<div class="ns-field">
  <label class="ns-label" for="terms">Payment terms</label>
  <select id="terms" class="ns-select"><option>Net 14</option><option>Net 30</option></select>
  <p class="ns-hint">Due Monday 26 October 2026.</p>
</div>
```

## 10. Input group and input with icon

`ns-input-group` joins `ns-input-group__addon` (prefix/suffix: €, "per hour", "INV-2026-") to a borderless
input, with one focus ring around the group. `ns-input-icon` puts a 16 px icon inside the field.

```html
<div class="ns-input-group"><span class="ns-input-group__addon">€</span><input id="rate" class="ns-input ns-input--num" value="95.00" aria-label="Rate"><span class="ns-input-group__addon">per hour</span></div>
<div class="ns-input-icon"><svg class="ns-icon" aria-hidden="true">…</svg><input type="search" class="ns-input" placeholder="Number, client or amount" aria-label="Search invoices"></div>
```

## 11. Checkbox, radio, switch

Native inputs restyled: `ns-checkbox` (`is-mixed` draws the indeterminate dash), `ns-radio`, `ns-switch`
(`role="switch"`). Wrap with `label.ns-check` + `ns-check__text`; for settings rows use `ns-setting`
(`__title`, `__text`, switch on the right). Disabled states included.

```html
<label class="ns-check"><input type="checkbox" class="ns-checkbox" checked><span class="ns-check__text">Remember this device for 30 days</span></label>
<div class="ns-setting">
  <div><p class="ns-setting__title" id="remind-label">Send polite reminders</p><p class="ns-setting__text" id="remind-desc">3, 7 and 14 days after the due date.</p></div>
  <input type="checkbox" role="switch" class="ns-switch" checked aria-labelledby="remind-label" aria-describedby="remind-desc">
</div>
```

## 12. Dropzone and file row

`ns-dropzone` (dashed, canvas tint) with `__icon`, `__title`, `__link` (a `label` for a visually hidden file
input); `ns-file` lists an uploaded file with `__icon`, `__name`, `__meta` and a remove button.

```html
<div class="ns-dropzone"><span class="ns-dropzone__icon"><svg class="ns-icon ns-icon--lg" aria-hidden="true">…</svg></span><p class="ns-dropzone__title">Drop files here or <label for="attach" class="ns-dropzone__link">browse</label></p><p>PDF, PNG or JPG, up to 10 MB each</p><input id="attach" type="file" class="visually-hidden"></div>
<div class="ns-file"><span class="ns-file__icon">…</span><div class="ns-grow"><p class="ns-file__name">nordlys-poster-proofs.pdf</p><p class="ns-file__meta">2.4 MB</p></div><button type="button" class="ns-btn ns-btn--ghost ns-btn--icon ns-btn--sm" aria-label="Remove file">…</button></div>
```

## 13. Form layout

`ns-form-section` = description column + fields (`ns-form-grid`, two columns from 640 px, `ns-col-full` to
span). `--stack` keeps the title above the fields (inside settings next to `ns-subnav`). Save bar:
`ns-sticky-actions` at the end of the page content.

```html
<section class="ns-form-section" aria-labelledby="s-business">
  <div><h2 id="s-business" class="ns-form-section__title">Business profile</h2><p class="ns-form-section__text">Printed at the top of every invoice.</p></div>
  <div class="ns-form-grid"><div class="ns-field">…</div><div class="ns-field">…</div><div class="ns-field ns-col-full">…</div></div>
</section>
<div class="ns-sticky-actions"><button type="button" class="ns-btn ns-btn--ghost">Discard</button><button type="button" class="ns-btn ns-btn--primary">Save changes</button></div>
```

## 14. Table, toolbar, chips, bulk bar, pagination

`ns-table-wrap` (relative, scrolls sideways) + `ns-table`: muted headers, hairline rows, `is-num` cells,
`is-check` and `is-actions` columns, `tr.is-selected` (accent tint + edge), `ns-cell` / `__title` / `__meta`,
sortable headers with `ns-sort` and `aria-sort`. Above: `ns-toolbar` with a search and `ns-chip` filters
(dashed = add filter, `is-active` = applied). Selection shows the inverse `ns-bulkbar` in `ns-bulkbar-dock`.
Below: `ns-pagination` with `ns-page` buttons.

```html
<div class="ns-table-wrap">
  <table class="ns-table">
    <thead><tr><th scope="col" class="is-check"><input type="checkbox" class="ns-checkbox is-mixed" aria-label="Select all"></th><th scope="col">Invoice</th><th scope="col" aria-sort="descending"><a href="#" class="ns-sort">Issued</a></th><th scope="col" class="is-num">Amount</th><th scope="col">Status</th></tr></thead>
    <tbody>
      <tr class="is-selected"><td class="is-check"><input type="checkbox" class="ns-checkbox" checked aria-label="Select INV-2026-044"></td><td><a href="#" class="ns-cell__title ns-num">INV-2026-044</a></td><td class="ns-muted">12 Oct</td><td class="is-num">€1,920.00</td><td><span class="ns-badge ns-badge--accent"><span class="ns-badge__dot"></span>Sent</span></td></tr>
    </tbody>
  </table>
</div>
<div class="ns-bulkbar-dock"><div class="ns-bulkbar" role="region" aria-label="Bulk actions"><span class="ns-bulkbar__count">2 selected</span><button type="button" class="ns-bulkbar__btn">Send reminder</button><span class="ns-bulkbar__sep" aria-hidden="true"></span><button type="button" class="ns-bulkbar__btn" aria-label="Clear selection">…</button></div></div>
```

## 15. Tabs, segmented control, sub-navigation

`ns-tabs` / `ns-tab` (ink underline on `aria-current="page"`, `ns-tab__count`, `--alert`), `ns-segmented` /
`__item` (`aria-pressed="true"` = selected) for switching a view in place, `ns-subnav` / `__item` for a
section menu (horizontal scroll on phones, vertical from 1024 px).

```html
<nav class="ns-tabs" aria-label="Invoice status"><a href="#" class="ns-tab" aria-current="page">All<span class="ns-tab__count">24</span></a><a href="#" class="ns-tab">Overdue<span class="ns-tab__count ns-tab__count--alert">2</span></a></nav>
<div class="ns-segmented" role="group" aria-label="Period"><button type="button" class="ns-segmented__item">Quarter</button><button type="button" class="ns-segmented__item" aria-pressed="true">Year</button></div>
```

## 16. Alert

`ns-alert` (neutral) + `--info --success --warning --danger`; icon, `__title`, `__body`, optional
`__actions`. Errors take `role="alert"`, the rest `role="status"` or nothing.

```html
<div class="ns-alert ns-alert--warning" role="status">
  <svg class="ns-icon" aria-hidden="true">…</svg>
  <div class="ns-grow"><p class="ns-alert__title">2 invoices are overdue, €3,150 in total</p><p class="ns-alert__body">A polite reminder goes out tomorrow at 09:00.</p></div>
  <a href="resource-list.html" class="ns-btn ns-btn--sm ns-btn--secondary">Review</a>
</div>
```

## 17. Menu (dropdown)

`ns-menu-anchor` (relative) + `ns-menu` (`--end` aligns right, `--static` in flow): `__label`, `__item`
(`is-active` = highlighted, `--danger`), `__hint` shortcut, `__sep`. Drawn open in mockups.

```html
<div class="ns-menu-anchor">
  <button type="button" class="ns-btn ns-btn--secondary" aria-expanded="true" aria-haspopup="menu">Actions</button>
  <div class="ns-menu" role="menu" aria-label="Invoice actions">
    <a href="#" role="menuitem" class="ns-menu__item is-active">View invoice</a>
    <a href="#" role="menuitem" class="ns-menu__item">Record payment<span class="ns-menu__hint">P</span></a>
    <div class="ns-menu__sep" role="separator"></div>
    <a href="#" role="menuitem" class="ns-menu__item ns-menu__item--danger">Void invoice</a>
  </div>
</div>
```

## 18. Modal

`ns-overlay` (fixed, blurred dark wash; `--contained` inside a demo box) + `ns-modal`: `__header` with
`__icon` (`--danger`) and `__title`, `__body`, `__footer` (canvas tint, safe action left, consequential right).

```html
<div class="ns-overlay">
  <div class="ns-modal" role="dialog" aria-modal="true" aria-labelledby="void-title">
    <div class="ns-modal__header"><span class="ns-modal__icon ns-modal__icon--danger">…</span><h2 id="void-title" class="ns-modal__title">Void INV-2026-038?</h2></div>
    <p class="ns-modal__body">Atelier Rousseau will see it as cancelled.</p>
    <div class="ns-modal__footer"><button type="button" class="ns-btn ns-btn--secondary">Keep it</button><button type="button" class="ns-btn ns-btn--danger">Void invoice</button></div>
  </div>
</div>
```

## 19. Toast

Inverse `ns-toast` with an icon, `__title`, `__body`, optional `__action`; `role="status"`. Bottom-right in
the app, drawn in place in mockups.

```html
<div class="ns-toast" role="status"><svg class="ns-icon ns-icon--lg" aria-hidden="true">…</svg><div><p class="ns-toast__title">Invoice sent</p><p class="ns-toast__body">INV-2026-044 to Tundra Labs</p></div><a href="#" class="ns-toast__action">View</a></div>
```

## 20. Empty state and steps

`ns-empty` (dashed panel): `__icon`, `__title`, `__text`, `__actions`. `ns-steps` / `ns-step` (`is-done`)
with `__num`, `__title`, `__text`, `__action` for setup checklists and suggestions.

```html
<div class="ns-empty">
  <span class="ns-empty__icon"><svg class="ns-icon ns-icon--xl" aria-hidden="true">…</svg></span>
  <h2 class="ns-empty__title">No recurring invoices yet</h2>
  <p class="ns-empty__text">Set up a retainer once and Fjord drafts the invoice every month.</p>
  <div class="ns-empty__actions"><button type="button" class="ns-btn ns-btn--primary">Create a schedule</button></div>
</div>
```

## 21. Progress, description list, timeline

`ns-progress` + `ns-progress__bar` (`--success`, `--warning`; width inline); `ns-dl` two-column facts;
`ns-timeline` / `__item` / `__dot` (`--accent` upcoming, `--success` done) / `__title` / `__meta`.

```html
<div class="ns-progress"><div class="ns-progress__bar" style="width: 70%"></div></div>
<dl class="ns-dl"><dt>Issued</dt><dd>12 Oct 2026</dd><dt>Due</dt><dd>26 Oct 2026 · Net 14</dd></dl>
<ol class="ns-timeline"><li class="ns-timeline__item"><span class="ns-timeline__dot ns-timeline__dot--success"></span><p class="ns-timeline__title">Viewed by Kari Halden</p><p class="ns-timeline__meta">Sat 3 Oct, 14:12</p></li></ol>
```

## 22. Chart

`ns-chart`: HTML y-axis (`__y`), `__plot` with an SVG (`__svg`, `preserveAspectRatio="none"`) of
`ns-chart__grid` lines and `ns-chart__bar` / `--muted` rects, HTML x labels (`__x`, `.is-current`), a static
`ns-chart__tip`, and `ns-legend` / `__key`. `role="img"` + an `aria-label` that states the numbers.

```html
<div class="ns-chart">
  <div class="ns-chart__y" aria-hidden="true"><span>€20k</span><span>€10k</span><span>€0</span></div>
  <div class="ns-chart__plot"><svg class="ns-chart__svg" viewBox="0 0 1000 208" preserveAspectRatio="none" role="img" aria-label="Income paid per month in 2026"><line class="ns-chart__grid" x1="0" x2="1000" y1="104" y2="104" vector-effect="non-scaling-stroke"/><rect class="ns-chart__bar" x="26" y="106" width="48" height="92" rx="3"/></svg></div>
  <div></div><div class="ns-chart__x" aria-hidden="true"><span>Jan</span><span class="is-current">Oct</span></div>
</div>
```

## 23. Invoice document, line items, totals

`ns-doc` is the paper invoice (stays light in dark mode): `__head`, `__title`, `__number`, `__from`,
`__parties` with `__label`, `__lines` table (`__desc` under a line), `__summary`, `__foot`, `__stamp`.
`ns-lines` is the editing table (`is-qty`, `is-rate`, `is-amount`, `ns-lines__amount`); `ns-totals` / `__row`
(`--total`, `--due`) sums both.

```html
<div class="ns-totals">
  <div class="ns-totals__row"><span>Subtotal</span><span>€2,200.00</span></div>
  <div class="ns-totals__row"><span>VAT 25%</span><span>€550.00</span></div>
  <div class="ns-totals__row ns-totals__row--total"><span>Total</span><span>€2,750.00</span></div>
</div>
```

## 24. Marketing sections and auth

Site: `ns-site`, `ns-site-header` / `__inner` / `ns-site-nav`, `ns-container`, `ns-section` (`--tight`,
`--line`, `__head`), `ns-hero` / `__grid` / `__actions` / `__note` / `__visual`, `ns-float`, `ns-announce`,
`ns-eyebrow`, `ns-display` (with a serif `em`), `ns-h2`, `ns-h3`, `ns-lead`, `ns-logos` + `ns-logo-word`
variants, `ns-features` / `ns-feature`, `ns-split`, `ns-checklist`, `ns-figures`, `ns-plans` / `ns-plan`
(`--featured`), `ns-quote`, `ns-faq` (`details`), `ns-cta` with `ns-cta__art`, `ns-footer`. Sign-in:
`ns-auth` / `__top` / `__main` / `__card` / `__divider` / `__foot` / `__art`, with the drawn fields below.

```html
<section class="ns-auth__card" aria-labelledby="signin-title">
  <h1 id="signin-title" class="ns-auth__title">Welcome back</h1>
  <span class="mock-label">Email</span><div class="mock-field" role="img" aria-label="Email field, filled in">ingrid@solbergstudio.no</div>
  <span class="mock-label">Password</span><div class="mock-field mock-field--secret" role="img" aria-label="Password field, filled in">••••••••••••</div>
  <a href="dashboard.html" class="ns-btn ns-btn--primary ns-btn--lg ns-btn--block" role="button">Sign in</a>
</section>
```

**Mock fields** (`mock-label`, `mock-field`, `--secret`, `--empty`, `--focus`, `--error`, `--disabled`) look
exactly like `ns-input` and are the only way to draw credentials: no `<form>`, no text input, no password type.
