# Filament v4 — components

Every class below is defined in `tokens.css` and shown in `html/components.html`. Class names are the
`fi-*` names Filament 4 really renders (checked against `filament/*` v4.12 source CSS and Blade/PHP
views); a few BEM-style helpers of our own fill gaps where Filament renders third-party markup
(FilePond, TipTap, Chart.js). Each entry names the PHP that produces it, so a mockup translates
one-to-one into a Resource, Page, Widget or Action.

**Ground rules**

- Body: `<body class="fi-mockup fi-body">`. Panel screens add `fi-body-has-topbar fi-body-has-navigation`.
- Colour: write `fi-color fi-color-{primary|success|warning|danger|info}`. **Gray is the default** — a gray
  button, badge, link or icon button has no colour class (exactly like Filament). Filament computes the
  shade per component for WCAG contrast (amber buttons are `400` with `900` text, blue buttons `600` with
  white); the tokens carry those picks, so never hard-code a shade.
- Sizes: `fi-size-xs|sm|md|lg|xl` on buttons, links, badges, icon buttons and `fi-icon`.
- Icons: Heroicons v2, inline SVG, `aria-hidden="true"`, class `fi-icon fi-size-*`. Filament picks the
  variant by size: 16 px micro (`sm`), 20 px mini (`md`), 24 px outline or solid (`lg`).
- States are drawn statically: `fi-active`, `fi-selected`, `fi-disabled`, `fi-invalid`, `fi-focused`,
  `fi-hovered`, `fi-collapsed`, `fi-toggle-on|off`, `fi-indeterminate`.
- Grids: `fi-grid` + `fi-grid-cols-2|3|4` (from 1024 px, = `->columns(2)`), `fi-sm-grid-cols-2`,
  `fi-md-grid-cols-2|3`, `fi-xl-grid-cols-4`; children `fi-col-span-2|3|full` (= `->columnSpan()`).

---

## 1. Panel shell — topbar + sidebar + main

Purpose: the authenticated layout of every panel page (`Panel::make()`), v4 shape: a full-width white
topbar (logo left, global search, notifications bell, user avatar right) and a transparent sidebar below
it on the gray-50 canvas. Below 1024 px the sidebar disappears and the topbar shows the menu button.

Anatomy: `fi-topbar-ctn > nav.fi-topbar > (.fi-topbar-open-sidebar-btn, .fi-topbar-start > .fi-logo, .fi-topbar-end)`,
`.fi-layout > aside.fi-sidebar > nav.fi-sidebar-nav > ul.fi-sidebar-nav-groups > li.fi-sidebar-group`,
`.fi-main-ctn > main.fi-main > .fi-page`.

```html
<body class="fi-mockup fi-body fi-body-has-topbar fi-body-has-navigation">
<header class="fi-topbar-ctn"><nav class="fi-topbar" aria-label="Topbar">
  <button type="button" class="fi-icon-btn fi-size-lg fi-topbar-open-sidebar-btn" aria-label="Expand sidebar"><!-- o-bars-3 --></button>
  <div class="fi-topbar-start"><a href="dashboard.html" class="fi-logo">Northwind Ops</a></div>
  <div class="fi-topbar-end">
    <div class="fi-global-search-field"><div class="fi-input-wrp"><div class="fi-input-wrp-prefix fi-inline"><!-- m-magnifying-glass --></div><input type="search" class="fi-input fi-input-has-inline-prefix" placeholder="Search" aria-label="Global search"></div></div>
    <button type="button" class="fi-icon-btn fi-size-lg" aria-label="Notifications, 3 unread"><!-- o-bell --><span class="fi-icon-btn-badge-ctn"><span class="fi-badge fi-color fi-color-primary fi-size-xs">3</span></span></button>
    <button type="button" class="fi-user-menu-trigger" aria-label="User menu"><span class="fi-avatar fi-circular fi-user-avatar">ZH</span></button>
  </div>
</nav></header>
<div class="fi-layout">
  <aside class="fi-sidebar" aria-label="Sidebar"><nav class="fi-sidebar-nav" aria-label="Main"><ul class="fi-sidebar-nav-groups">
    <li class="fi-sidebar-group fi-collapsible">
      <div class="fi-sidebar-group-btn"><span class="fi-sidebar-group-label">Shop</span><button type="button" class="fi-icon-btn fi-size-sm fi-sidebar-group-collapse-btn" aria-label="Collapse Shop"><!-- m-chevron-up --></button></div>
      <ul class="fi-sidebar-group-items">
        <li class="fi-sidebar-item fi-active"><a href="resource-list.html" class="fi-sidebar-item-btn" aria-current="page"><!-- o-shopping-cart --><span class="fi-sidebar-item-label">Orders</span><span class="fi-sidebar-item-badge-ctn"><span class="fi-badge fi-color fi-color-primary">38</span></span></a></li>
      </ul>
    </li>
  </ul></nav></aside>
  <div class="fi-main-ctn"><main id="fi-main-content" class="fi-main"><!-- .fi-page --></main></div>
</div>
</body>
```

Variants: active item (`fi-active` + `aria-current`), nav badge (`->navigationBadge()`), collapsed group
(`fi-collapsed`), nested items (`fi-sidebar-sub-group-items` + `fi-sidebar-item-grouped-border`), user menu
open (`.fi-dropdown-panel` after the trigger, see 11). PHP: `->navigationGroup()`, `->navigationIcon(Heroicon::OutlinedShoppingCart)`,
`->navigationSort()`, `->brandName()` / `->brandLogo()`, `->globalSearch()`, `->databaseNotifications()`.
Do: keep the sidebar transparent and the topbar white. Don't: draw a dark sidebar or a sidebar header
on desktop — that is v3 / other kits.

## 2. Page header & breadcrumbs

Purpose: heading, optional subheading, breadcrumbs and header actions of every page (`getHeaderActions()`).

```html
<div class="fi-page"><div class="fi-page-header-main-ctn">
  <header class="fi-header fi-header-has-breadcrumbs">
    <div>
      <nav class="fi-breadcrumbs" aria-label="Breadcrumbs"><ol class="fi-breadcrumbs-list">
        <li class="fi-breadcrumbs-item"><a href="resource-list.html">Orders</a></li>
        <li class="fi-breadcrumbs-item"><!-- m-chevron-right class="fi-icon fi-breadcrumbs-item-separator" --><span class="fi-breadcrumbs-item-label" aria-current="page">List</span></li>
      </ol></nav>
      <h1 class="fi-header-heading">Orders</h1>
    </div>
    <div class="fi-header-actions-ctn">
      <button type="button" class="fi-btn">Export</button>
      <a href="resource-create.html" class="fi-btn fi-color fi-color-primary">New order</a>
    </div>
  </header>
  <div class="fi-page-main"><div class="fi-page-content"><!-- tables, forms, widgets --></div></div>
</div></div>
```

`fi-header-subheading` adds a lead paragraph. Breadcrumbs hide below 640 px (Filament does the same).

## 3. Button — `fi-btn`

`Action::make()->color()->size()->icon()->outlined()->badge()`; also `x-filament::button`.

```html
<button type="button" class="fi-btn fi-color fi-color-primary">New order</button>
<button type="button" class="fi-btn"><!-- m-arrow-down-tray --> Export</button>
<button type="button" class="fi-btn fi-color fi-color-danger fi-size-sm">Delete</button>
<button type="button" class="fi-btn fi-outlined fi-color fi-color-primary">Outlined</button>
<button type="button" class="fi-btn" disabled>Disabled</button>
<div class="fi-btn-group"><button class="fi-btn">Day</button><button class="fi-btn">Week</button></div>
```

States: `fi-hovered` (demo), `fi-processing` (with a spinning `fi-loading-indicator` svg), `disabled` /
`fi-disabled`, `fi-block` (full width, login). Badge: `<span class="fi-btn-badge-ctn"><span class="fi-badge …">2</span></span>`.
Do: one primary per action group. Don't: add `fi-color-gray` — gray is the default.

## 4. Icon button — `fi-icon-btn`

`Action::make()->iconButton()`, `x-filament::icon-button`. 36 px hit area with a negative margin like Filament
(`fi-static` cancels it in free layouts). Colours via `fi-color`, sizes `fi-size-xs|sm|lg`, badge via
`fi-icon-btn-badge-ctn`.

```html
<button type="button" class="fi-icon-btn" aria-label="More actions"><!-- m-ellipsis-vertical --></button>
<button type="button" class="fi-icon-btn fi-color fi-color-danger fi-size-sm" aria-label="Delete row"><!-- m-trash --></button>
```

Always give icon-only buttons an `aria-label`.

## 5. Link — `fi-link`

`Action::make()->link()` (table row actions, notification actions, hints). Default gray-700; coloured
links use the contrast pick (amber links are `700`).

```html
<a href="resource-edit.html" class="fi-link fi-color fi-color-primary fi-size-sm"><!-- c-pencil-square --> Edit</a>
<button type="button" class="fi-link fi-color fi-color-danger">Deselect all</button>
```

## 6. Badge — `fi-badge`

`TextColumn::make()->badge()`, `TextEntry::make()->badge()`, navigation and tab badges, filter indicators.
Rounded-md, xs text, inset ring. Sizes `fi-size-xs` (counters) and `fi-size-sm` (tabs).

```html
<span class="fi-badge fi-color fi-color-warning"><!-- c-arrow-path --><span>Processing</span></span>
<span class="fi-badge">Wholesale</span>
<span class="fi-badge">Milan<button type="button" class="fi-badge-delete-btn" aria-label="Remove Milan"><!-- c-x-mark --></button></span>
```

Order status vocabulary used in every screen: New `info` + sparkles, Processing `warning` + arrow-path,
Shipped `success` + truck, Delivered `success` + check-badge, Cancelled `danger` + x-circle (a
`HasColor`/`HasIcon`/`HasLabel` enum in PHP).

## 7. Avatar — `fi-avatar`

ui-avatars default: gray-950 circle, white initials. `fi-circular`, `fi-size-sm|lg`, `fi-tone-1..4`
for customer avatars, `fi-avatar-stack` for `ImageColumn::make()->circular()->stacked()`.

```html
<span class="fi-avatar fi-circular fi-user-avatar" role="img" aria-label="Zainab Haddad">ZH</span>
```

## 8. Section — `fi-section`

`Section::make()->description()->icon()->aside()->compact()->collapsible()->collapsed()->secondary()`.

```html
<section class="fi-section">
  <header class="fi-section-header">
    <div class="fi-section-header-text-ctn"><h2 class="fi-section-header-heading">Shipping address</h2><p class="fi-section-header-description">Defaults to the customer’s primary address.</p></div>
    <div class="fi-section-header-after-ctn"><button type="button" class="fi-link fi-color fi-color-danger fi-size-sm">Reset</button></div>
  </header>
  <div class="fi-section-content-ctn"><div class="fi-section-content"><!-- fields --></div></div>
</section>
```

Variants: `fi-aside` (heading column + card, settings pages), `fi-compact`, `fi-secondary`, `fi-collapsed`
(+ `fi-section-collapse-btn`), `fi-section-not-contained`, `fi-divided`, `fi-section-footer`. A section
without header: drop the `<header>` and give the section an `aria-label`. `Fieldset::make()` → `fieldset.fi-fieldset > legend`.

## 9. Form field — `fi-fo-field`

The wrapper every form component renders (label column, content column, helper, error).

```html
<div class="fi-fo-field">
  <div class="fi-fo-field-label-col"><div class="fi-fo-field-label-ctn">
    <label for="street" class="fi-fo-field-label"><span class="fi-fo-field-label-content">Street address<sup class="fi-fo-field-label-required-mark">*</sup></span></label>
  </div></div>
  <div class="fi-fo-field-content-col">
    <div class="fi-input-wrp fi-invalid"><input id="street" class="fi-input" aria-invalid="true" aria-describedby="street-error"></div>
    <p id="street-error" class="fi-fo-field-wrp-error-message">The street address field is required.</p>
  </div>
</div>
```

Helper text (`->helperText()`) is `<span class="fi-sc-text">…</span>` in the content column; a hint
(`->hint()`) is any element after the label inside `fi-fo-field-label-ctn` (pushed to the end). Inline
label: `fi-fo-field-has-inline-label`. Disabled: `fi-disabled` on the field, `fi-disabled` on the wrapper
and `disabled` on the input (`->disabled()` drops the required mark, like Filament).

## 10. Input wrapper, input, select, textarea — `fi-input-wrp`

`TextInput`, `Select` (native and searchable), `DatePicker`, `Textarea`, table search, global search.

```html
<div class="fi-input-wrp"><div class="fi-input-wrp-prefix"><span class="fi-input-wrp-label">$</span></div><input class="fi-input" value="32.00" aria-label="Price"></div>
<div class="fi-input-wrp"><input class="fi-input" value="Oct 16, 2026" aria-label="Delivery date"><div class="fi-input-wrp-suffix fi-inline"><!-- m-calendar --></div></div>
<div class="fi-input-wrp"><select class="fi-select-input" aria-label="Carrier"><option>DHL Express</option></select></div>
<div class="fi-input-wrp"><button type="button" class="fi-select-input-btn" aria-label="Customer"><span class="fi-select-input-placeholder">Select an option</span></button></div>
<div class="fi-input-wrp fi-fo-textarea"><textarea rows="3" aria-label="Notes"></textarea></div>
```

States: `fi-focused` (focus ring demo), `fi-invalid`, `fi-disabled`. Prefix/suffix with a border
(`->prefix('$')`) or inline (`->prefixIcon()` → `fi-inline`). Multi-select values: `fi-select-input-value-badges-ctn`
with `fi-badge` + delete buttons. Tags: `fi-input-wrp fi-fo-tags-input` + `fi-fo-tags-input-tags-ctn`.
Key-value: `fi-input-wrp fi-fo-key-value` wrapping a table of `fi-input`.

## 11. Dropdown — `fi-dropdown-panel`

`ActionGroup::make()`, user menu, bulk actions menu, table filters (`->filtersLayout(FiltersLayout::Dropdown)`).
Drawn open, absolutely placed under its `fi-dropdown` wrapper (end-aligned; `fi-align-start` for start).

```html
<div class="fi-dropdown">
  <button type="button" class="fi-icon-btn" aria-label="Actions" aria-haspopup="menu"><!-- m-ellipsis-vertical --></button>
  <div class="fi-dropdown-panel" role="menu">
    <div class="fi-dropdown-list">
      <a href="resource-view.html" class="fi-dropdown-list-item" role="menuitem"><!-- m-eye --><span class="fi-dropdown-list-item-label">View</span></a>
      <button type="button" class="fi-dropdown-list-item fi-color fi-color-danger" role="menuitem"><!-- m-trash --><span class="fi-dropdown-list-item-label">Delete</span></button>
    </div>
  </div>
</div>
```

`fi-dropdown-header` (user name), `fi-theme-switcher` + `fi-theme-switcher-btn fi-active`, widths
`fi-width-xs|sm|md`, `fi-static` for an in-flow demo.

## 12. Checkbox, radio, checkbox list

`Checkbox`, `CheckboxList::make()->descriptions()`, `Radio::make()->descriptions()->inline()`.

```html
<label class="fi-fo-field-label"><input type="checkbox" class="fi-checkbox-input" checked><span class="fi-fo-field-label-content">This product will be shipped</span></label>
<div class="fi-fo-checkbox-list"><div class="fi-fo-checkbox-list-options fi-cols-2">
  <label class="fi-fo-checkbox-list-option"><input type="checkbox" class="fi-checkbox-input" checked><span class="fi-fo-checkbox-list-option-text"><span class="fi-fo-checkbox-list-option-label">Low stock</span><span class="fi-fo-checkbox-list-option-description">Under the security stock.</span></span></label>
</div></div>
<div class="fi-fo-radio fi-inline"><label class="fi-fo-radio-label"><input type="radio" name="terms" class="fi-radio-input" checked><span class="fi-fo-radio-label-text">Net 30</span></label></div>
```

Static indeterminate: add `fi-indeterminate`; error: `fi-invalid`.

## 13. Toggle & toggle buttons

`Toggle::make()->onColor()->offColor()`, `ToggleColumn`, `ToggleButtons::make()->inline()->grouped()`.

```html
<label class="fi-fo-field-label"><button type="button" class="fi-toggle fi-toggle-on" role="switch" aria-checked="true" aria-label="Visible"><span></span></button><span class="fi-fo-field-label-content">Visible</span></label>
<div class="fi-fo-toggle-buttons fi-btn-group" role="radiogroup" aria-label="Weight unit">
  <button type="button" class="fi-btn fi-color fi-color-primary" role="radio" aria-checked="true">Kilograms</button>
  <button type="button" class="fi-btn" role="radio" aria-checked="false">Pounds</button>
</div>
```

Coloured toggles: `fi-toggle-on fi-color fi-color-success`; off colour: `fi-toggle-off fi-color fi-color-danger`.

## 14. File upload — `fi-fo-file-upload` (FilePond, drawn)

`FileUpload::make()->multiple()->image()->panelLayout('grid')`.

```html
<div class="fi-fo-file-upload"><div class="fi-fo-file-upload-dropzone">
  <div class="fi-fo-file-upload-label">Drag &amp; Drop your files or&nbsp;<span class="fi-fo-file-upload-browse">Browse</span></div>
  <div class="fi-fo-file-upload-items">
    <div class="fi-fo-file-upload-item fi-uploaded"><button type="button" class="fi-fo-file-upload-item-remove" aria-label="Remove logo.svg"><!-- m-x-mark --></button><div class="fi-fo-file-upload-item-info"><span class="fi-fo-file-upload-item-name">logo.svg</span><span class="fi-fo-file-upload-item-size">14 KB</span></div><div class="fi-fo-file-upload-item-status">Upload complete</div></div>
  </div>
</div></div>
```

Image grid: `fi-fo-file-upload-items fi-grid-2` with `figure.fi-fo-file-upload-thumb` (+ `fi-fo-file-upload-thumb-bar`).

## 15. Rich editor — `fi-fo-rich-editor`

`RichEditor::make()->toolbarButtons([...])` (TipTap in v4).

```html
<div class="fi-input-wrp fi-fo-rich-editor"><div class="fi-grow">
  <div class="fi-fo-rich-editor-toolbar" role="toolbar" aria-label="Formatting">
    <div class="fi-fo-rich-editor-toolbar-group"><button type="button" class="fi-fo-rich-editor-tool fi-active" aria-label="Bold"><!-- m-bold --></button><button type="button" class="fi-fo-rich-editor-tool" aria-label="Italic"><!-- m-italic --></button></div>
  </div>
  <div class="fi-fo-rich-editor-content" role="textbox" aria-multiline="true" aria-label="Description"><p>Hand-glazed in Arita.</p></div>
</div></div>
```

## 16. Repeater & table repeater

`Repeater::make()->schema([...])->collapsible()->reorderable()->cloneable()` (cards) and
`Repeater::make()->table([...])` (v4 table repeater).

```html
<div class="fi-fo-repeater"><ul class="fi-fo-repeater-items">
  <li class="fi-fo-repeater-item">
    <div class="fi-fo-repeater-item-header"><div class="fi-fo-repeater-item-header-start-actions"><button type="button" class="fi-icon-btn fi-size-sm" aria-label="Move"><!-- m-arrows-up-down --></button></div><h4 class="fi-fo-repeater-item-header-label">Ceramic Pour-Over Dripper</h4><div class="fi-fo-repeater-item-header-end-actions"><button type="button" class="fi-icon-btn fi-color fi-color-danger fi-size-sm" aria-label="Delete"><!-- m-trash --></button></div></div>
    <div class="fi-fo-repeater-item-content"><!-- fi-grid of fields --></div>
  </li>
</ul><div class="fi-fo-repeater-add"><button type="button" class="fi-btn fi-size-sm">Add to items</button></div></div>
```

Table form: `fi-fo-table-repeater > fi-fo-table-repeater-ctn > table` (th required mark
`fi-fo-table-repeater-header-required-mark`, action cell `fi-fo-table-repeater-actions`). Collapsed item: `fi-collapsed`.

## 17. Tabs — `fi-tabs`

List-page tabs (`getTabs()`), relation-manager tabs, `Tabs::make()` in schemas, sub-navigation.

```html
<nav class="fi-tabs" aria-label="Order status">
  <a href="resource-list.html" class="fi-tabs-item fi-active" aria-current="page"><span class="fi-tabs-item-label">All</span><span class="fi-badge fi-size-sm">1,284</span></a>
  <a href="resource-list.html" class="fi-tabs-item"><span class="fi-tabs-item-label">New</span><span class="fi-badge fi-size-sm fi-color fi-color-info">38</span></a>
</nav>
```

Schema tabs inside a card: `.fi-sc-tabs.fi-contained > nav.fi-tabs.fi-contained + .fi-sc-tabs-tab`. Vertical: `fi-vertical`.

## 18. Table — `fi-ta`

`$table->columns()->filters()->recordActions()->toolbarActions()->headerActions()`; list pages, relation
managers and `TableWidget`.

```html
<div class="fi-ta"><div class="fi-ta-ctn">
  <div class="fi-ta-header-toolbar">
    <div class="fi-ta-actions fi-align-start"><button type="button" class="fi-btn"><!-- m-ellipsis-vertical -->Bulk actions</button></div>
    <div><div class="fi-ta-search-field"><div class="fi-input-wrp"><div class="fi-input-wrp-prefix fi-inline"><!-- m-magnifying-glass --></div><input type="search" class="fi-input fi-input-has-inline-prefix" placeholder="Search" aria-label="Search"></div></div>
      <button type="button" class="fi-icon-btn" aria-label="Filter"><!-- m-funnel --><span class="fi-icon-btn-badge-ctn"><span class="fi-badge fi-color fi-color-primary fi-size-xs">2</span></span></button></div>
  </div>
  <div class="fi-ta-selection-indicator" role="status"><div><span>3 records selected</span></div><div><div class="fi-ta-selection-indicator-actions-ctn"><button type="button" class="fi-link fi-color fi-color-primary">Select all 147</button><button type="button" class="fi-link fi-color fi-color-danger">Deselect all</button></div></div></div>
  <div class="fi-ta-content-ctn"><table class="fi-ta-table">
    <thead><tr>
      <th class="fi-ta-cell fi-ta-selection-cell" scope="col"><input type="checkbox" class="fi-ta-page-checkbox fi-checkbox-input fi-indeterminate" aria-label="Select all"></th>
      <th class="fi-ta-header-cell fi-ta-header-cell-sorted" scope="col" aria-sort="descending"><button type="button" class="fi-ta-header-cell-sort-btn"><span>Placed</span><!-- c-chevron-down --></button></th>
      <th class="fi-ta-actions-header-cell" scope="col"><span class="fi-sr-only">Actions</span></th>
    </tr></thead>
    <tbody><tr class="fi-ta-row fi-selected">
      <td class="fi-ta-cell fi-ta-selection-cell"><input type="checkbox" class="fi-ta-record-checkbox fi-checkbox-input" checked aria-label="Select NW-10492"></td>
      <td class="fi-ta-cell"><div class="fi-ta-col"><div class="fi-ta-text fi-ta-text-has-descriptions"><span class="fi-ta-text-item">Oct 12, 2026</span><span class="fi-ta-text-description">9:41 AM</span></div></div></td>
      <td class="fi-ta-cell fi-ta-actions-cell"><div class="fi-ta-actions"><a href="resource-edit.html" class="fi-link fi-color fi-color-primary fi-size-sm">Edit</a></div></td>
    </tr></tbody>
  </table></div>
  <!-- .fi-pagination -->
</div></div>
```

Columns: `fi-ta-text` (+`fi-align-end`, `fi-ta-text-has-badges`, `fi-ta-text-with-image` + `fi-ta-image-thumb`),
`fi-ta-icon` + `fi-ta-icon-item fi-color`, `fi-ta-toggle`, `fi-ta-placeholder`. Header with heading/actions:
`fi-ta-ctn-with-header` + `fi-ta-header fi-ta-header-adaptive-actions-position`. Indicators:
`fi-ta-filter-indicators`. Summary: `tfoot > tr.fi-ta-summary-row`. Filters form: `fi-ta-filters`
(`fi-ta-filters-header`, `fi-ta-filters-heading`, `fi-ta-filters-actions-ctn`) inside `fi-dropdown-panel`.
Column manager: `fi-ta-col-manager*`. Do: keep tables inside `fi-ta-content-ctn` so they scroll on phones.

## 19. Pagination — `fi-pagination`

```html
<nav class="fi-pagination" aria-label="Pagination navigation">
  <span class="fi-pagination-overview">Showing 1 to 10 of 147 results</span>
  <div class="fi-pagination-records-per-page-select-ctn"><label class="fi-pagination-records-per-page-select fi-input-wrp"><span class="fi-input-wrp-prefix"><span class="fi-input-wrp-label">Per page</span></span><select class="fi-select-input" aria-label="Per page"><option>10</option></select></label></div>
  <button type="button" class="fi-btn fi-pagination-next-btn">Next</button>
  <ol class="fi-pagination-items"><li class="fi-pagination-item fi-active"><button type="button" class="fi-pagination-item-btn" aria-current="page"><span class="fi-pagination-item-label">1</span></button></li></ol>
</nav>
```

Like Filament (container query `@4xl`), the page numbers and overview appear only when the table is at least
56rem wide; narrower tables show Previous/Next and a compact per-page select (`fi-compact`).

## 20. Empty state — `fi-ta-empty-state`, `fi-empty-state`

`$table->emptyStateHeading()->emptyStateDescription()->emptyStateActions()`, `x-filament::empty-state`.

```html
<div class="fi-ta-empty-state"><div class="fi-ta-empty-state-content">
  <div class="fi-ta-empty-state-icon-bg"><!-- o-truck --></div>
  <h2 class="fi-ta-empty-state-heading">No shipment exceptions</h2>
  <p class="fi-ta-empty-state-description">Every parcel is moving on schedule.</p>
  <div class="fi-ta-actions"><a href="modal.html" class="fi-btn fi-color fi-color-primary">New shipment</a></div>
</div></div>
```

Card version: `section.fi-empty-state` (+`fi-compact`, coloured icon `fi-empty-state-icon-bg fi-color fi-color-primary`).

## 21. Stats overview widget — `fi-wi-stats-overview`

`StatsOverviewWidget` with `Stat::make()->description()->descriptionIcon()->color()->chart()->icon()`.

```html
<div class="fi-wi-stats-overview"><div class="fi-wi-stats-overview-stats-ctn fi-cols-4">
  <div class="fi-wi-stats-overview-stat">
    <div class="fi-wi-stats-overview-stat-content">
      <div class="fi-wi-stats-overview-stat-label-ctn"><span class="fi-wi-stats-overview-stat-label">Revenue</span></div>
      <div class="fi-wi-stats-overview-stat-value">$184,240</div>
      <div class="fi-wi-stats-overview-stat-description fi-color fi-color-success"><span>12% increase</span><!-- m-arrow-trending-up --></div>
    </div>
    <div class="fi-wi-stats-overview-stat-chart fi-color fi-color-success"><svg viewBox="0 0 100 24" preserveAspectRatio="none" aria-hidden="true"><path class="fi-chart-area" d="M0 20C30 18 60 12 100 6V24H0Z"/><path class="fi-chart-line" d="M0 20C30 18 60 12 100 6"/></svg></div>
  </div>
</div></div>
```

Widgets grid: `.fi-wi` (2 columns from 768 px); a widget spanning the row: `fi-wi-widget fi-col-span-full`.

## 22. Chart widget — `fi-wi-chart` + `fi-chart`

`ChartWidget` (`getType()` line / bar / doughnut, `getFilters()`). Chart.js in the app; inline SVG here,
drawn in a 0–100 viewBox with `preserveAspectRatio="none"` and non-scaling strokes; HTML axis labels.

```html
<section class="fi-section fi-wi-chart">
  <header class="fi-section-header"><div class="fi-section-header-text-ctn"><h2 class="fi-section-header-heading">Orders per month</h2></div></header>
  <div class="fi-section-content-ctn"><div class="fi-section-content">
    <div class="fi-chart" role="img" aria-label="Bar chart: orders per month">
      <div class="fi-chart-y" aria-hidden="true"><span>125</span><span>0</span></div>
      <div class="fi-chart-plot"><svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><path class="fi-chart-grid" d="M0 0H100"/><g class="fi-color fi-color-primary"><path class="fi-chart-bar" d="M2 100V40H6V100"/></g></svg></div>
      <div class="fi-chart-x fi-chart-x-bands" aria-hidden="true"><span>Nov</span></div>
      <div class="fi-chart-legend" aria-hidden="true"><span class="fi-chart-legend-item fi-color fi-color-primary">Orders</span></div>
    </div>
  </div></div>
</section>
```

Pieces: `fi-chart-line` (+`fi-dashed`), `fi-chart-area`, `fi-chart-points` (zero-length segments `M x y h0`),
`fi-chart-bar` (+`fi-solid` for stacked), doughnut `fi-chart-donut` with `fi-chart-slice fi-color …`, `fi-chart-wide`.
Header filter select: `fi-input-wrp fi-wi-chart-filter`. Account widget: `fi-account-widget` (see dashboard).

## 23. Infolist entries — `fi-in-entry`

`TextEntry`, `IconEntry`, `RepeatableEntry::make()->table()` on view pages.

```html
<dl class="fi-grid fi-md-grid-cols-3">
  <div class="fi-in-entry"><dt class="fi-in-entry-label">Customer</dt><dd class="fi-in-entry-content-col"><span class="fi-in-text-item"><a href="relation-manager.html">Kettle &amp; Crumb</a></span><span class="fi-in-text-description">Milan, Italy</span></dd></div>
  <div class="fi-in-entry"><dt class="fi-in-entry-label">Tracking number</dt><dd class="fi-in-entry-content-col"><span class="fi-in-placeholder">Not shipped yet</span></dd></div>
</dl>
```

Also `fi-in-text-list` (badges), `fi-in-repeatable-table`, `fi-in-key-value`, `fi-font-mono`, `fi-size-lg fi-font-bold fi-color`.

## 24. Modal & slide-over — `fi-modal`

`Action::make()->form()->modalHeading()->modalDescription()->modalIcon()->slideOver()->requiresConfirmation()`.
Placed after `.fi-layout` (the builder's `<!--after-->`), with the backdrop.

```html
<div class="fi-modal fi-modal-open" role="presentation">
  <div class="fi-modal-close-overlay" aria-hidden="true"></div>
  <div class="fi-modal-window-ctn">
    <div class="fi-modal-window fi-width-md fi-align-center fi-modal-window-has-icon" role="dialog" aria-modal="true" aria-labelledby="del-h">
      <div class="fi-modal-header"><div class="fi-modal-icon-ctn"><div class="fi-modal-icon-bg fi-color fi-color-danger"><!-- o-trash --></div></div><div><h2 id="del-h" class="fi-modal-heading">Delete selected orders</h2><p class="fi-modal-description">Are you sure you would like to do this?</p></div><button type="button" class="fi-icon-btn fi-size-lg fi-modal-close-btn" aria-label="Close"><!-- o-x-mark --></button></div>
      <div class="fi-modal-footer fi-align-center"><div class="fi-modal-footer-actions"><button type="button" class="fi-btn">Cancel</button><button type="button" class="fi-btn fi-color fi-color-danger">Delete</button></div></div>
    </div>
  </div>
</div>
```

Form modal: `fi-align-start fi-modal-window-has-content` + `fi-modal-content`; slide-over: add
`fi-modal-slide-over` to `.fi-modal`; widths `fi-width-sm…4xl`. In-page demo: wrap in `.fi-modal-demo` and add `fi-modal-static`.

## 25. Notifications — `fi-no`

Toasts (`Notification::make()->title()->body()->success()->actions()->send()`) and the database
notifications slide-over (`->databaseNotifications()`).

```html
<div class="fi-no fi-align-end" role="status" aria-live="polite">
  <div class="fi-no-notification"><!-- o-check-circle class="fi-icon fi-size-lg fi-no-notification-icon fi-color fi-color-success" -->
    <div class="fi-no-notification-main"><div class="fi-no-notification-text"><h3 class="fi-no-notification-title">Saved</h3><div class="fi-no-notification-body"><p>Kettle &amp; Crumb will receive the tracking link.</p></div></div>
      <div class="fi-ac"><a href="resource-view.html" class="fi-btn fi-color fi-color-primary fi-size-sm">View order</a></div></div>
    <button type="button" class="fi-icon-btn fi-size-sm fi-no-notification-close-btn" aria-label="Close"><!-- m-x-mark --></button>
  </div>
</div>
```

`fi-vertical-align-end` puts the stack at the bottom; `fi-static` keeps it in flow; tinted variant
`fi-color fi-color-danger fi-tinted`. Database list: `fi-no-database` slide-over, `fi-no-notifications`,
`fi-no-notification fi-inline`, unread marker `fi-no-notification-unread-ctn`, group label `fi-no-group-label`.

## 26. Wizard — `fi-sc-wizard`

`Wizard::make([Step::make()->description()->icon()])`, `CreateRecord` with `HasWizard`.

```html
<form class="fi-sc-wizard fi-contained" aria-label="Create order">
  <ol class="fi-sc-wizard-header">
    <li class="fi-sc-wizard-header-step fi-completed"><button type="button" class="fi-sc-wizard-header-step-btn"><span class="fi-sc-wizard-header-step-icon-ctn"><!-- o-check --></span><span class="fi-sc-wizard-header-step-text"><span class="fi-sc-wizard-header-step-label">Order details</span></span></button><svg class="fi-sc-wizard-header-step-separator" viewBox="0 0 22 80" preserveAspectRatio="none" fill="none" aria-hidden="true"><path d="M0 -2L20 40L0 82" stroke="currentcolor" vector-effect="non-scaling-stroke"/></svg></li>
    <li class="fi-sc-wizard-header-step fi-active" aria-current="step"><button type="button" class="fi-sc-wizard-header-step-btn"><span class="fi-sc-wizard-header-step-icon-ctn"><span class="fi-sc-wizard-header-step-number">02</span></span><span class="fi-sc-wizard-header-step-text"><span class="fi-sc-wizard-header-step-label">Order items</span></span></button></li>
  </ol>
  <div class="fi-sc-wizard-step"><!-- fields --></div>
  <div class="fi-sc-wizard-footer"><button type="button" class="fi-btn">Back</button><button type="button" class="fi-btn fi-color fi-color-primary">Next</button></div>
</form>
```

## 27. Callout — `fi-callout` (v4)

`Callout::make()->heading()->description()->color()->icon()->footer()`.

```html
<div class="fi-callout fi-color fi-color-warning" role="note"><!-- o-exclamation-triangle class="fi-icon fi-size-lg fi-callout-icon" --><div class="fi-callout-main"><div class="fi-callout-text"><p class="fi-callout-heading">Customs hold</p><p class="fi-callout-description">DHL expects release within 24 hours.</p></div></div></div>
```

## 28. Simple layout (auth) & drawn credentials — `fi-simple-*`, `.mock-*`

Login, register, password reset (`->login()`, `->registration()`, `->passwordReset()`). Credentials are
never real inputs in a mockup:

```html
<div class="fi-simple-layout"><main class="fi-simple-main-ctn"><div class="fi-simple-main"><div class="fi-simple-page-content">
  <header class="fi-simple-header"><span class="fi-logo">Northwind Ops</span><h1 class="fi-simple-header-heading">Sign in</h1></header>
  <div class="mock-form">
    <span class="mock-label">Email address</span>
    <div class="mock-field" role="img" aria-label="Email field, filled in">zainab@northwind.example</div>
    <div class="mock-field mock-field--secret" role="img" aria-label="Password field, filled in">••••••••</div>
    <a href="dashboard.html" class="fi-btn fi-color fi-color-primary fi-block" role="button">Sign in</a>
  </div>
</div></div></main></div>
```

`.mock-field--empty`, `--focus`, `--error`, `--disabled`; `.mock-field-affix` adds the reveal-eye suffix.

## Utilities used by the screens

`fi-sc` (schema stack, 24 px gap; `fi-dense`), `fi-ac` (actions row; `fi-align-end|center|between`,
`fi-sticky`), `fi-sc-text` (helper text), `fi-flex`, `fi-flex-between`, `fi-stack`, `fi-grow`, `fi-dl`,
`fi-divided-list`, `fi-timeline` (+`fi-timeline-dot`, a custom infolist view), `fi-progress` (+`fi-w-*`),
`fi-code`, `fi-kbd`, `fi-text-muted|soft|strong|end`, `fi-tabular`, `fi-font-mono`, `fi-address`,
`fi-sr-only` / `visually-hidden`, catalog `fi-catalog*`, `fi-swatch*`, `fi-demo-*`.
