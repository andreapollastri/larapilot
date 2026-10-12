# Laravel Nova 5 — components

Every class lives in `tokens.css`; `html/components.html` draws each one in its variants and states. Icons are Heroicons v2
inline SVG (`outline` 24 px = `.nv-icon`, `mini` 20 px = `.nv-icon--mini`, `micro` 16 px = `.nv-icon--micro`), always
`aria-hidden="true"`. State classes: `.is-active`, `.is-hover`, `.is-focus`, `.is-pressed`, `.is-error`, `.is-disabled`,
`.is-checked`, `.is-indeterminate`, `.is-selected`, `.is-current`, `.is-selecting`, `.is-uploading`.

## Mapping table — mockup → Nova

| Mockup | Nova (PHP / Vue) |
| --- | --- |
| `.nv-header`, `.nv-gsearch`, theme / bell / user menu | Nova layout `MainHeader.vue`; `Nova::withoutGlobalSearch()`, `Nova::withoutThemeSwitcher()`, `Nova::withoutNotificationCenter()`, `Nova::userMenu()` |
| `.nv-sidebar`, `.nv-menu-section`, `.nv-menu-group__head`, `.nv-menu-item` | `Nova::mainMenu(fn () => [MenuSection::make('Resources', [MenuGroup::make('CRM', [MenuItem::resource(Contact::class)])])->icon('rectangle-stack')->collapsable()])`; menu badge `->withBadge('4', 'info')` |
| `.nv-breadcrumbs` | `Nova::withBreadcrumbs()` |
| `.nv-btn` | `<Button>` from `laravel-nova-ui` (`variant`, `state`, `size`, `icon`, `trailing-icon`) |
| `.nv-badge` | `Badge::make('Lifecycle')->map([...])->withIcons()`; `Status::make()` |
| `.nv-bool` | `Boolean::make('VIP')`; `.nv-bool-group` = `BooleanGroup` |
| `.nv-card` | `Card.vue`; custom cards `nova:card` |
| `.nv-field` rows | `DefaultField.vue` — any `Field` on create / update; `->help()`, `->rules()`, `->readonly()`, `->stacked()`, `->fullWidth()` |
| `.nv-pitem` rows | `PanelItem.vue` — any `Field` on detail; `Panel::make('Address', [...])`; `Tab::group()` |
| `.nv-index-bar`, `.nv-toolbar`, `.nv-table`, `.nv-pagination` | Resource index of a `Resource`; `$perPageOptions`, `$tableStyle`, `$showColumnBorders`, `->sortable()`, `config('nova.pagination')` |
| `.nv-pop` filter menu | `Filter` classes (`nova:filter`, `BooleanFilter`, `DateFilter`) returned by `filters()` |
| lens menu | `Lens` classes returned by `lenses()` |
| action menu + `.nv-modal` | `Action` classes returned by `actions()`; `->confirmText()`, `->confirmButtonText()`, `->cancelButtonText()`, `->size('2xl')`, `->standalone()`, `->showInline()`, `DestructiveAction` |
| `.nv-metric` value / trend / partition / progress, `.nv-tmetric` | `Value`, `Trend`, `Partition`, `Progress`, `Table` metrics; `->width('1/3')`, `->icon()`, `->help()`, `ranges()` |
| `.nv-cards` grid, dashboard heading | `Laravel\Nova\Dashboard` subclasses in `Nova::dashboards([...])`; resource `cards()` |
| `.nv-notif-panel` | `NovaNotification::make()->message()->action()->icon()->type('success')` |
| `.nv-toast` | `Action::message()`, `Action::danger()`, `Nova.success()` / `Nova.error()` |
| `.nv-auth-card`, `.mock-field` | Nova's Login / ForgotPassword / TwoFactorChallenge pages (`->withAuthenticationRoutes()`, `->withPasswordResetRoutes()`) |
| `.nv-security` | Nova 5 User Security page — `Nova::fortify()->features([Features::updatePasswords(), Features::twoFactorAuthentication()])` |

---

## 1. App shell

**Purpose:** Nova's fixed layout — 56 px white header, 240 px sidebar on the gray-100 canvas, content `p-4 md:py-8 md:px-12`.
**Anatomy:** `.nv-app` › `header.nv-header` (`.nv-header__toggle`, `.nv-header__brand` › `.nv-header__home` › `.nv-logo`,
`.nv-header__main` › `.nv-gsearch` + `.nv-header__end`) · `nav.nv-sidebar` · `main.nv-main` (children spaced 32 px) › `.nv-footer`.
**Variants:** below 1024 px the sidebar hides and the bars-3 toggle shows; below 768 px the logo and user menu hide (Nova does the same).
**Do:** keep the footer "Powered by Laravel Nova". **Don't:** give the sidebar a background — it sits on the canvas.

```html
<body class="nv-mockup"><div class="nv-app">
  <header class="nv-header">
    <button type="button" class="nv-btn nv-btn--action nv-header__toggle" aria-label="Toggle Sidebar"><!-- bars-3 --></button>
    <div class="nv-header__brand"><a class="nv-header__home" href="dashboard.html"><span class="nv-logo">…</span></a></div>
    <div class="nv-header__main">
      <div class="nv-gsearch" role="search"><span class="nv-gsearch__icon"><!-- magnifying-glass mini --></span>
        <input type="search" class="nv-gsearch__input" placeholder="Press / to search" aria-label="Search"></div>
      <div class="nv-header__end">
        <button type="button" class="nv-btn nv-btn--action" aria-label="Theme: System"><!-- computer-desktop --></button>
        <div class="nv-bell"><a class="nv-btn nv-btn--action" href="notifications.html" aria-label="Notifications, unread"><!-- bell --></a><span class="nv-bell__dot"></span></div>
        <div class="nv-header__user nv-dropdown"><button type="button" class="nv-btn nv-btn--ghost nv-btn--tight">
          <span class="nv-flex nv-items-center nv-gap-2"><span class="nv-user-avatar"><span class="nv-avatar nv-avatar--b nv-avatar--7">PR</span></span><span class="nv-nowrap">Priya Raman</span></span><!-- chevron-down mini --></button></div>
      </div></div></header>
  <nav class="nv-sidebar" aria-label="Main menu">…</nav>
  <main class="nv-main" id="main">… <footer class="nv-footer">…</footer></main>
</div></body>
```

## 2. Sidebar menu

**Anatomy:** `.nv-menu` › `.nv-menu-section` (`button.nv-menu-section__head` with `__icon` (outline 24), `__label` (16 px),
`__chev`) › `.nv-menu-section__items` › `h4.nv-menu-group__head` (uppercase 12 px bold) and `a.nv-menu-item` (14 px, a 24 px
`.nv-menu-spacer` indent). **States:** `.is-active` (bold primary) + `aria-current="page"`; collapsed section: `.nv-menu-section__chev.is-collapsed`.
Badge: `.nv-menu-item__badge` › `.nv-badge`.

```html
<div class="nv-menu-section">
  <button type="button" class="nv-menu-section__head" aria-expanded="true"><span class="nv-menu-section__icon"><!-- rectangle-stack --></span>
    <span class="nv-menu-section__label">Resources</span><span class="nv-menu-section__chev"><!-- chevron-down mini --></span></button>
  <div class="nv-menu-section__items">
    <h4 class="nv-menu-group__head"><span class="nv-menu-spacer"></span><span class="nv-menu-group__label">CRM</span></h4>
    <a class="nv-menu-item is-active" href="resource-list.html" aria-current="page"><span class="nv-menu-spacer"></span><span class="nv-menu-item__label">Contacts</span></a>
    <a class="nv-menu-item" href="#"><span class="nv-menu-spacer"></span><span class="nv-menu-item__label">Tasks</span><span class="nv-menu-item__badge"><span class="nv-badge nv-badge--info">4</span></span></a>
  </div>
</div>
```

## 3. Breadcrumbs and headings

`.nv-breadcrumbs` › `ol` › `li` › `.nv-breadcrumbs__item` (link `.nv-link`, separator micro chevron `.nv-breadcrumbs__sep`; last
item `aria-current="page"`). Headings mirror `Heading.vue`: `.nv-h1` (20 px normal, page / panel titles), `.nv-h3` (12 px bold
uppercase, modal headers, filter titles), `.nv-h4` (24 px). `.nv-heading-row` = heading + `mb-3`. **Don't** put headings inside cards — Nova puts them above.

```html
<nav class="nv-breadcrumbs" aria-label="breadcrumb"><ol>
  <li><div class="nv-breadcrumbs__item"><span>Resources</span><!-- chevron-right micro .nv-breadcrumbs__sep --></div></li>
  <li><div class="nv-breadcrumbs__item"><a class="nv-link" href="resource-list.html">Contacts</a><!-- sep --></div></li>
  <li aria-current="page"><div class="nv-breadcrumbs__item"><span>Create Contact</span></div></li>
</ol></nav>
<h1 class="nv-h1 nv-heading-row">Contacts</h1>
```

## 4. Buttons

**Purpose:** `laravel-nova-ui` `<Button>` — `border rounded text-sm font-bold`, 36 px (`h-9 px-3`), small 28 px.
**Variants:** `.nv-btn--solid` (primary, shadow; white text, gray-900 in dark), `+ .nv-btn--danger`, `.nv-btn--ghost`
(Cancel, toolbar triggers), `.nv-btn--outline` (empty-state Create, Reload), `.nv-btn--link` (+ `--mellow`, `--danger`),
`.nv-btn--action` (36 × 36 icon-only row controls). **Sizes / padding:** `--sm`, `--tight`, `--icon`, `--block`, `--create` (Create button `px-4`).
**States:** `.is-hover`, `.is-focus` (3 px primary-200 ring), `.is-pressed`, `[disabled]` (50 % opacity).
**Do:** icon-only buttons need `aria-label`. **Don't:** use rounded-full or coloured outline buttons — Nova has neither.

```html
<a class="nv-btn nv-btn--solid nv-btn--create" href="resource-form.html"><span class="nv-hidden-mobile">Create Contact</span><span class="nv-only-mobile">Create</span></a>
<button type="button" class="nv-btn nv-btn--ghost">Cancel</button>
<button type="button" class="nv-btn nv-btn--outline">Create Call</button>
<button type="button" class="nv-btn nv-btn--link nv-btn--mellow">Cancel</button>
<button type="button" class="nv-btn nv-btn--solid nv-btn--danger">Delete</button>
<button type="button" class="nv-btn nv-btn--action" aria-label="View"><!-- eye --></button>
<button type="button" class="nv-btn nv-btn--solid nv-btn--tight" aria-label="2 filters applied"><!-- funnel --><span class="nv-btn__count">2</span><!-- chevron-down mini --></button>
```

## 5. Badge, Status, Boolean, Tag, Avatar

**Badge** (`Badge.vue`): 24 px pill, uppercase 12 px bold — `.nv-badge--success | --info | --warning | --danger`, neutral by
default; `withIcons()` adds `.nv-badge__icon` (solid mini check-circle / information-circle / exclamation-circle).
**Status** field: `.nv-badge` with an arrow-path icon (loading), success (finished), danger (failed). **CircleBadge**:
`.nv-circle-badge` (counts in the select-all menu). **Boolean**: `.nv-bool--yes | --no | --null` around a solid 24 px icon,
`role="img"` + `aria-label`. **BooleanGroup**: `ul.nv-bool-group`. **Tag** field: `.nv-tags` › `.nv-tag`. **Avatar**: `.nv-avatar`
(32 px; `--7` 28 px, `--lg` 48 px; tones `--a … --g`).

```html
<span class="nv-badge nv-badge--success"><span class="nv-badge__icon"><!-- check-circle solid mini --></span>Customer</span>
<span class="nv-badge nv-badge--warning">Prospect</span>
<span class="nv-bool nv-bool--yes" role="img" aria-label="Yes"><!-- check-circle solid --></span>
<span class="nv-bool nv-bool--no" role="img" aria-label="No"><!-- x-circle solid --></span>
<div class="nv-tags"><span class="nv-tag">Fleet</span><span class="nv-tag">Enterprise</span></div>
<span class="nv-avatar nv-avatar--a" aria-hidden="true">AO</span>
```

## 6. Card

`.nv-card` — white / gray-800, `rounded-lg`, `shadow`, `overflow: hidden` (`.nv-card--visible` when a dropdown must escape).
`.nv-card--pad` (px-6 py-4), `.nv-card--panel` (detail panel: mt-3 py-2 px-6), `.nv-divide` (1 px gray-100 rules between children).
**Don't:** add a card header bar — Nova cards have no title strip.

## 7. Text inputs (Text, Email, Number, Date, Currency, Textarea)

**Anatomy:** `.nv-input` = Nova's `form-control form-input form-control-bordered`: 36 px, `rounded`, `shadow` + 1 px
`ring-gray-950/10`, 14 px gray-600 text. **Sizes:** `--sm` 32 px (filters), `--xs` 28 px (action selector), `--xxs` 24 px
(metric ranges). **States:** `:focus` / `.is-focus` (2 px primary ring), `.is-error` (red-400 ring) + `.nv-help--error`,
`[disabled]` / `[readonly]` (gray-50, gray-500 text). **Variants:** `.nv-textarea`, `.nv-input--date` (w-56),
`.nv-input-group` + `.nv-input-group__addon` (Currency), `.nv-counter` (`->maxlength()->enforceMaxlength()`).

```html
<input class="nv-input" type="text" value="Amara Okafor" aria-label="Name">
<input class="nv-input is-error" type="email" value="amara.okafor@brightline.co" aria-label="Email" aria-invalid="true">
<p class="nv-help nv-help--error">The email has already been taken.</p>
<div class="nv-input-group"><span class="nv-input-group__addon">$</span><input class="nv-input" value="42,000.00" aria-label="Amount"></div>
```

## 8. Select, BelongsTo, searchable select

`SelectControl` → `.nv-select` › `select.nv-input` + `.nv-select__arrow` (mini chevron-down at right 11 px). Metric ranges:
`.nv-select.nv-select--metric` with `.nv-input--xxs`. Searchable `BelongsTo` / `MorphTo` (`->searchable()`) →
`.nv-combobox` › `.nv-combobox__control.nv-input` (+ `__value` / `__placeholder` "Click to choose") and, open,
`.nv-combobox__menu` › `input.nv-combobox__search` + `.nv-combobox__option` (`.is-selected` = solid primary row).
`->showCreateRelationButton()` → a `.nv-btn--link.nv-btn--icon` with plus-circle in `.nv-combo-row`; trashed toggle =
`.nv-check` "With Trashed".

```html
<div class="nv-select"><select class="nv-input" aria-label="Lifecycle Stage"><option selected>Customer</option></select>
  <span class="nv-select__arrow"><!-- chevron-down mini --></span></div>
<div class="nv-combobox"><div class="nv-input nv-combobox__control" role="combobox" aria-expanded="false" aria-label="Company" tabindex="0">
  <span class="nv-combobox__value">Brightline Logistics</span></div><span class="nv-select__arrow"><!-- chevron-down mini --></span></div>
```

## 9. Checkbox (Boolean field, selection, filters)

`laravel-nova-ui` `Checkbox`: 16 px, `rounded`, `border-gray-950/20`; checked / indeterminate = solid primary with a white
tick (gray-900 in dark). Draw it as `.nv-checkbox` (`role="checkbox"`, `aria-checked`, `tabindex="0"`) inside `.nv-check`
when it has a label. **States:** `.is-checked`, `.is-indeterminate`, `.is-focus`, `.is-disabled`. A real input can use
`input[type=checkbox].nv-checkbox-input`.

```html
<span class="nv-check"><span class="nv-checkbox is-checked" role="checkbox" aria-checked="true" tabindex="0">
  <svg class="nv-checkbox__tick" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true"><path d="M3.708 5.293a1 1 0 1 0-1.415 1.416l2 2a1 1 0 0 0 1.414 0l4-4a1 1 0 0 0-1.414-1.416L5.001 6.587 3.708 5.293Z"/></svg>
  <svg class="nv-checkbox__dash" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true"><path d="M9.999 6a1 1 0 0 1-.883.993L8.999 7h-6a1 1 0 0 1-.117-1.993L2.999 5h6a1 1 0 0 1 1 1Z"/></svg>
</span><span>Remember me</span></span>
```

## 10. Field row (create / update forms)

**Anatomy:** `.nv-card.nv-divide` › `.nv-field` (py-5) › `.nv-field__label` (1/5, `label.nv-label` + `.nv-req` "*") and
`.nv-field__control` (3/5; `--full` = 4/5 for `->fullWidth()`), help `.nv-help` (12 px italic), error `.nv-help--error`.
**Variants:** `.nv-field--stacked` (`->stacked()`), `.nv-field--compact`, `.nv-field--heading` (`Heading` field). Panels:
heading `.nv-h1` above each card, `.nv-panel-help` for `Panel::help()`. Footer: `.nv-form-actions` = Cancel (ghost),
Create & Add Another (solid), Create Contact (solid), right-aligned.

```html
<div class="nv-card nv-divide">
  <div class="nv-field"><div class="nv-field__label"><label class="nv-label"><span>Email</span><span class="nv-req">*</span></label></div>
    <div class="nv-field__control"><input class="nv-input is-error" type="email" value="amara.okafor@brightline.co" aria-label="Email" aria-invalid="true">
      <p class="nv-help nv-help--error">The email has already been taken.</p></div></div>
  <div class="nv-field"><div class="nv-field__label"><label class="nv-label"><span>Phone</span></label></div>
    <div class="nv-field__control"><input class="nv-input" type="tel" placeholder="Phone" aria-label="Phone">
      <p class="nv-help">Include the country code.</p></div></div>
</div>
<div class="nv-form-actions"><a class="nv-btn nv-btn--ghost" href="resource-list.html">Cancel</a>
  <button type="button" class="nv-btn nv-btn--solid">Create &amp; Add Another</button><button type="button" class="nv-btn nv-btn--solid">Create Contact</button></div>
```

## 11. Markdown, File / Image, KeyValue fields

**Markdown** (`Markdown::make()`): `.nv-md` › `.nv-md__bar` (`.nv-md__tab` Write / Preview, `.is-active` bold primary;
`.nv-md__tools` 40 px icon buttons) + `textarea.nv-md__body` + `.nv-md__hint`. **File / Image / Avatar** (`DropZone.vue`):
`.nv-dropzone` (4 px dashed, gray-50) › `.nv-dropzone__row` › solid "Choose File" + `.nv-dropzone__hint`; previews `.nv-files` ›
`.nv-file` (`__thumb`, `.is-uploading` + `.nv-progress.nv-file__progress`, `__remove`, `__name`, `__meta`). **KeyValue**:
`.nv-kv` (`__head`, `__row`) + `.nv-kv-add` link button "Add row".

```html
<div class="nv-dropzone" role="button" tabindex="0"><div class="nv-dropzone__row">
  <span class="nv-btn nv-btn--solid"><!-- arrow-up-tray mini -->Choose File</span><p class="nv-dropzone__hint">Drop file or click to choose</p></div></div>
```

## 12. Resource index — search bar, toolbar, table, pagination

**Anatomy:** `.nv-h1.nv-heading-row` · `.nv-index-bar` (`.nv-isearch` pill input 1/3 width, `.nv-index-bar__actions`:
standalone action ghost button + Create) · `.nv-card` › `.nv-toolbar` (`.nv-toolbar__start` select-all ghost button with
`.nv-checkbox` + chevron; `.nv-toolbar__end`: `.nv-toolbar__actions` action selector (`--xs` select "Actions", only when rows
are selected), lens (`queue-list`), filter (`funnel`, solid + count when applied), delete (`trash`)) · `.nv-table-scroll` ›
`table.nv-table` · `.nv-pagination`. **Selection:** `.nv-select-all.is-selecting` + `.nv-selected` "2 selected" + x-circle.
**Cells:** `.nv-cell-check` (pl-5 pr-5), IDs and BelongsTo as `.nv-link`, `.nv-cell-controls` › `.nv-row-controls` (inline
action dropdown, view, edit, delete). **Sorting:** `.nv-sort` with the 8 × 14 arrows, `.is-on` on the active arrow.
**Variants:** `.nv-table--tight` (`$tableStyle = 'tight'`), `.nv-table--borders` (`$showColumnBorders`), row `.is-hover`.
Pagination `simple` (default) = Previous / count / Next; `links` = `.nv-pagination--links` › `.nv-pages` › `.nv-page`
(`.is-current`, `--arrow`). On phones the table scrolls inside the card.

```html
<div class="nv-card">
  <div class="nv-toolbar"><div class="nv-toolbar__row"><div class="nv-toolbar__start">…select-all…</div>
    <div class="nv-toolbar__end"><div class="nv-dropdown"><button type="button" class="nv-btn nv-btn--ghost nv-btn--tight" aria-label="Filter Dropdown"><!-- funnel --><!-- chevron-down mini --></button></div></div></div></div>
  <div class="nv-table-scroll"><table class="nv-table">
    <thead><tr><th class="nv-cell-check"><span class="visually-hidden">Selected Resources</span></th><th><button type="button" class="nv-sort" aria-sort="descending"><span>ID</span><!-- sort svg --></button></th><th>Name</th><th>Lifecycle</th><th><span class="visually-hidden">Controls</span></th></tr></thead>
    <tbody><tr><td class="nv-cell-check">…checkbox…</td><td><a class="nv-link" href="resource-view.html">248</a></td><td><span class="nv-text-strong">Amara Okafor</span></td>
      <td><span class="nv-badge nv-badge--success">Customer</span></td><td class="nv-cell-controls"><div class="nv-row-controls">…action buttons…</div></td></tr></tbody>
  </table></div>
  <div class="nv-pagination"><nav class="nv-pagination__nav" aria-label="Pagination"><button type="button" class="nv-pagination__btn" disabled>Previous</button>
    <span class="nv-pagination__count">1-10 of 248</span><button type="button" class="nv-pagination__btn">Next</button></nav></div>
</div>
```

## 13. Dropdown menus (actions, lenses, select-all, theme, user)

`DropdownMenu.vue` → `.nv-pop` (white / gray-900, `rounded-lg`, `shadow-lg`, gray-200 border) positioned with `--end` /
`--start` under a `.nv-dropdown` trigger; widths `--200`, `--250`, `--260`, `--auto`; `--static` inside galleries.
Content: `.nv-pop__nav` › `.nv-pop__item` (`--sm`, `--icon`, `.is-hover`, `.is-disabled`, `--danger`), section headings
`.nv-pop__heading` ("Actions", "User Actions"), sections `.nv-pop__sections`, select-all body `.nv-pop__checks`.

```html
<div class="nv-pop nv-pop--end nv-pop--auto" role="menu"><nav class="nv-pop__nav"><div class="nv-pop__sections">
  <div><h3 class="nv-pop__heading">Actions</h3><div class="nv-pop__nav"><button type="button" class="nv-pop__item" role="menuitem">Preview</button><button type="button" class="nv-pop__item" role="menuitem">Replicate</button></div></div>
  <div><h3 class="nv-pop__heading">User Actions</h3><div class="nv-pop__nav"><button type="button" class="nv-pop__item" role="menuitem">Send Follow-up Email</button></div></div>
</div></nav></div>
```

## 14. Filter menu

`FilterMenu.vue` → `.nv-pop.nv-pop--end.nv-pop--260` › `.nv-filter-scroll` › `.nv-pop__sections`: a `.nv-filter-reset` bar
("Reset Filters", only when filters apply), then one `.nv-filter` per Filter (`.nv-filter__title` uppercase, `.nv-filter__body`
with an `--sm` select, a date input, or `.nv-filter__options` checkboxes for a `BooleanFilter`), Trashed and Per Page last.
The trigger turns `.nv-btn--solid` with `.nv-btn__count`.

```html
<div class="nv-filter"><h3 class="nv-filter__title">Lifecycle Stage</h3><div class="nv-filter__body">
  <div class="nv-select nv-w-full"><select class="nv-input nv-input--sm" aria-label="Lifecycle Stage"><option>—</option><option selected>Customer</option></select><span class="nv-select__arrow"><!-- chevron-down mini --></span></div>
</div></div>
```

## 15. Detail page — panels, PanelItem, relationships, tabs

`.nv-detail-head` (`.nv-detail-head__title` › `.nv-h1` "Contact Details: Amara Okafor"; `.nv-detail-head__actions`: ghost
ellipsis = `DetailActionDropdown`, ghost pencil = edit) · `.nv-card.nv-card--panel.nv-divide` › `.nv-pitem`
(`__label` 1/4, `__value` 3/4). Copyable values: `.nv-copy` + `.nv-copy__icon`; long text: `.nv-show-all` / "Show Content" link.
`HasMany` / `BelongsToMany` render a full resource index (heading + `.nv-collapse`, search, Create, table, pagination) under the
panels. `Tab::group()` → `.nv-tabs` › `.nv-tab` (`.is-active`).

```html
<div class="nv-card nv-card--panel nv-divide">
  <div class="nv-pitem"><div class="nv-pitem__label"><h4>Company</h4></div><div class="nv-pitem__value"><a class="nv-link" href="#">Brightline Logistics</a></div></div>
  <div class="nv-pitem"><div class="nv-pitem__label"><h4>VIP</h4></div><div class="nv-pitem__value"><span class="nv-bool nv-bool--yes" role="img" aria-label="Yes"><!-- check-circle solid --></span></div></div>
</div>
```

## 16. Metrics — Value, Trend, Partition, Progress, Table

Grid: `.nv-cards` (12 columns from 768 px, 24 px gap); widths `.nv-w-1-4 | 1-3 | 1-2 | 2-3 | 3-4 | full` (`->width()`).
Card: `.nv-card.nv-metric.nv-metric--fixed` (min-h 160, px-6 py-4) › `.nv-metric__head` (`.nv-metric__title` 14 px bold,
range `.nv-select--metric`); help icon `.nv-metric__help`.
**Value:** `.nv-value` › `.nv-value__icon` (56 px primary tile, `->icon()`), `.nv-value__number` (36 px), `.nv-value__suffix`,
`.nv-value__change--up | --down | --none` ("12.5% Increase", "No Prior Data"). **Trend:** `.nv-trend__value` + `.nv-trend__chart`
(SVG: `path.nv-chart-area`, `path.nv-chart-line`, `line.nv-chart-dot` per point, `preserveAspectRatio="none"`).
**Partition:** `.nv-partition__head` + `.nv-partition__total` "(116 total)", `.nv-partition__body` › `ul.nv-legend`
(`.nv-legend__dot--1…8`) + `svg.nv-donut` (one `circle.nv-slice-N` per slice, dasharray = share × 251.33). **Progress:**
`.nv-progress-metric` (`.nv-progress-metric__target`, `__pct`) + `.nv-progress` › `.nv-progress__bar--green | --yellow-300 | …`
(width inline). **Table:** `.nv-card.nv-tmetric` › `.nv-tmetric__head` + table rows (`.nv-tmetric__icon--success|danger|warning|info`,
`__title`, `__sub`, `__actions`), empty `.nv-tmetric__empty`.

```html
<div class="nv-card nv-metric nv-metric--fixed nv-w-1-3">
  <div class="nv-metric__head"><h3 class="nv-metric__title">New Contacts</h3>
    <div class="nv-select nv-select--metric"><select class="nv-input nv-input--xxs" aria-label="Select Ranges"><option selected>30 Days</option></select><span class="nv-select__arrow"><!-- chevron-down mini --></span></div></div>
  <div class="nv-value"><div class="nv-value__icon"><!-- user-plus --></div>
    <div><p class="nv-value__number"><span>1,284</span></p><p class="nv-value__change nv-value__change--up"><!-- trend svg --><span>12.5% Increase</span></p></div></div>
</div>
<div class="nv-card nv-metric nv-metric--fixed nv-progress-metric nv-w-1-3">
  <div class="nv-metric__head"><h3 class="nv-metric__title">Quarterly Revenue Goal</h3><span class="nv-progress-metric__target">$482.6k</span></div>
  <p class="nv-progress-metric__pct">64%</p>
  <div class="nv-progress" role="progressbar" aria-valuenow="64" aria-valuemin="0" aria-valuemax="100" aria-label="Quarterly Revenue Goal"><div class="nv-progress__bar nv-progress__bar--green" style="width:64%"></div></div>
</div>
```

## 17. Dashboards and cards

A `Dashboard` page = `.nv-space-y-3` › `.nv-heading-row` › `.nv-h1` (dashboard name) + `.nv-cards`. Resource cards
(`Resource::cards()`) use the same grid above the index heading (add `.nv-mt-6` to the heading). Nova's default help card:
`.nv-help-card` (`__grid`, `__item`, `__icon`).

## 18. Modals (actions, delete, restore)

Layers: `.nv-backdrop` (gray-500/75, gray-900/75 dark) + `.nv-modal-wrap` › `.nv-modal` (`--sm` 384 px delete, `--2xl`
672 px actions; `.nv-modal--stack` = 24 px between parts). Parts: `.nv-modal__header` (uppercase 12 px bold, bottom rule),
`.nv-modal__text` (`--danger` for destructive confirmText), action fields as `.nv-field` rows, `.nv-modal__content`,
`.nv-modal__footer` (gray-100 / gray-700) › `.nv-modal__footer-end`: Cancel (link mellow) + run button (solid, `--danger` for
`DestructiveAction`). Gallery version: `.nv-modal-demo`.

```html
<div class="nv-backdrop" aria-hidden="true"></div>
<div class="nv-modal-wrap"><div class="nv-modal nv-modal--sm" role="alertdialog" aria-modal="true" aria-labelledby="del">
  <h3 id="del" class="nv-modal__header">Delete Resource</h3>
  <div class="nv-modal__content"><p>Are you sure you want to delete the selected resources?</p></div>
  <div class="nv-modal__footer"><div class="nv-modal__footer-end"><button type="button" class="nv-btn nv-btn--link nv-btn--mellow">Cancel</button>
    <button type="button" class="nv-btn nv-btn--solid nv-btn--danger">Delete</button></div></div>
</div></div>
```

## 19. Notification center and toasts

**Notifications:** `.nv-notif-layer` (backdrop `__backdrop`) › `aside.nv-notif-panel` (320 px, right) › `.nv-notif-panel__head`
("NOTIFICATIONS" `.nv-h3` + ghost ellipsis) and `.nv-notif-list` › `.nv-notif` (`.nv-notif__unread` dot, `.nv-notif__row` with
an icon tinted `.nv-notif__icon--success|info|warning|error`, `.nv-notif__message`, `.nv-notif__time`, small solid action button,
`.nv-notif__links` Mark Read / Delete). Empty: `.nv-notif-empty`. Bell: `.nv-bell__dot` or `.nv-bell__count`.
**Toasts:** `.nv-toasts` (fixed bottom-right; `--low`, `--static`) › `.nv-toast` (`--success`, `--error`, `--warning`, default
info) — bold text on a tinted pill, `rounded-lg shadow`.

```html
<div class="nv-toasts" role="status"><div class="nv-toast nv-toast--success">The contact was updated!</div></div>
```

## 20. Global search

`.nv-gsearch` (pill input, 320 px max) and, open, `.nv-gsearch-results` › `h3.nv-gsearch-results__group` (resource name, gray-300
bar) › `button.nv-gsearch-results__item` (`.is-selected`; avatar + title + `.nv-gsearch-results__sub`). Driven by
`Resource::$search`, `$globallySearchable`, `subtitle()`.

## 21. Empty and error dialogs

`IndexEmptyDialog` → `.nv-empty` › `.nv-empty__body` (65 × 51 table-with-plus SVG `.nv-empty__art`, `h3.nv-empty__title`
"No Contact matched the given criteria.") + outline Create button. `IndexErrorDialog` → wrap in `.nv-error-dialog`, title
"Failed to load Contacts!", outline "Reload".

```html
<div class="nv-empty"><div class="nv-empty__body"><svg class="nv-empty__art" width="65" height="51" viewBox="0 0 65 51" aria-hidden="true">…</svg>
  <h3 class="nv-empty__title">No Contact matched the given criteria.</h3></div>
  <a class="nv-btn nv-btn--outline" href="resource-form.html">Create Contact</a></div>
```

## 22. Auth pages and drawn credentials

`main.nv-auth` › `.nv-auth__logo` (`.nv-logo--lg`) › `section.nv-auth-card` (400 px, p-8) › `.nv-auth__title` "Welcome Back!",
`.nv-divider` (100 × 2 px), `.nv-auth__group` (label + field), `.nv-auth__row` (Remember me + `.nv-auth__forgot`), full-width
solid `a[role=button]`. Fields are drawn: `.mock-label` + `.mock-field` (`--secret`, `--empty`, `--focus`, `--error`,
`--disabled`, `--textarea`) — identical to `.nv-input`. Same card for Forgot Password and Two-factor Confirmation.

```html
<div class="nv-auth__group"><span class="mock-label">Email Address</span>
  <div class="mock-field" role="img" aria-label="Email field, filled in">priya.raman@harborcrm.app</div></div>
<div class="nv-auth__group"><span class="mock-label">Password</span>
  <div class="mock-field mock-field--secret" role="img" aria-label="Password field, filled in">••••••••••••</div></div>
<a href="dashboard.html" class="nv-btn nv-btn--solid nv-btn--block" role="button"><span>Log In</span></a>
```

## 23. User Security (Nova 5)

`.nv-security` › `.nv-security__title` · `.nv-security__row` (3 columns from 768 px): `.nv-security__aside` (`.nv-h3` +
`.nv-security__desc`) and `.nv-card.nv-security__card` (`--pad` when there is no bar) › `.nv-security__form` (6-column grid,
fields `.nv-col-4`, `.nv-security__lead` 18 px) + `.nv-security__bar` (gray-100 footer with Save). 2FA extras: `.nv-qr` (21 × 21
cells, `i.o` = light), `.nv-codes` (recovery codes), outline "Regenerate Recovery Codes" + solid danger "Disable".

## 24. Tooltip, loader, utilities

`.nv-tooltip` › `.nv-tooltip__bubble` (`role="tooltip"`, `--static`); `.nv-loader` (three dots), `.nv-loading` (card overlay),
`.nv-progress-top` (NProgress bar). Layout helpers used by the screens: `.nv-flex`, `.nv-items-center`, `.nv-gap-*`, `.nv-mt-*`,
`.nv-mb-*`, `.nv-space-y-*`, `.nv-hidden-mobile` / `.nv-only-mobile`, `.nv-text-strong`, `.nv-text-xs`, `.nv-mono`, `.nv-nowrap`.
