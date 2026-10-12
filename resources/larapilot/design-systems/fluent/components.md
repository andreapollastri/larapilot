# Fluent 2 — components (`fl-` vocabulary)

Every class below is defined in `tokens.css` and drawn in `html/components.html`. Names follow Fluent UI React v9; the
table maps each block to React, Blazor and Web Components. Icons are Fluent UI System Icons inline
(`<svg class="fl-icon" viewBox="0 0 20 20" aria-hidden="true"><path d="…"/></svg>`, 20 px regular by default;
`fl-icon--12|16|24|32` for other sizes; the selected nav/tab item uses the *filled* variant).

| `fl-` block | React (`@fluentui/react-components`) | Blazor (`Fluent*`) | Web Components |
| --- | --- | --- | --- |
| `.fl-header`, `.fl-nav`, `.fl-nav__item` | app header (custom) + `NavDrawer`, `NavItem`, `NavSectionHeader` (`@fluentui/react-nav`) | `FluentHeader`, `FluentNavMenu`, `FluentNavLink` | — |
| `.fl-btn` | `Button`, `ToggleButton`, `MenuButton`, `SplitButton` | `FluentButton` | `<fluent-button>` |
| `.fl-link` | `Link` | `FluentAnchor` | `<fluent-link>` |
| `.fl-field`, `.fl-label` | `Field` (`label`, `required`, `hint`, `validationState`, `validationMessage`) | `FluentTextField Label Required` | `<fluent-field>` |
| `.fl-input` | `Input` (`appearance="outline|underline|filled-darker"`, `contentBefore/After`) | `FluentTextField` | `<fluent-text-input>` |
| `.fl-select` | `Select` | `FluentSelect` | `<fluent-select>` |
| `.fl-dropdown`, `.fl-listbox`, `.fl-option` | `Dropdown` / `Combobox`, `Option`, `OptionGroup` | `FluentCombobox`, `FluentListbox` | `<fluent-dropdown>`, `<fluent-option>` |
| `.fl-searchbox` | `SearchBox` | `FluentSearch` | `<fluent-text-input type="search">` |
| `.fl-textarea` | `Textarea` | `FluentTextArea` | `<fluent-textarea>` |
| `.fl-datepicker`, `.fl-calendar` | `DatePicker`, `Calendar` (`@fluentui/react-datepicker-compat`) | `FluentDatePicker`, `FluentCalendar` | — |
| `.fl-checkbox`, `.fl-radio`, `.fl-switch`, `.fl-slider` | `Checkbox`, `RadioGroup`/`Radio`, `Switch`, `Slider` | `FluentCheckbox`, `FluentRadioGroup`, `FluentSwitch`, `FluentSlider` | `<fluent-checkbox>`, `<fluent-radio-group>`, `<fluent-switch>`, `<fluent-slider>` |
| `.fl-tablist`, `.fl-tab` | `TabList`, `Tab` (`size`, `vertical`, `appearance="subtle"`) | `FluentTabs`, `FluentTab` | `<fluent-tablist>`, `<fluent-tab>` |
| `.fl-breadcrumb` | `Breadcrumb`, `BreadcrumbItem`, `BreadcrumbButton`, `BreadcrumbDivider` | `FluentBreadcrumb` | — |
| `.fl-badge`, `.fl-counter`, `.fl-presence` | `Badge`, `CounterBadge`, `PresenceBadge` | `FluentBadge`, `FluentCounterBadge`, `FluentPresenceBadge` | `<fluent-badge>`, `<fluent-counter-badge>` |
| `.fl-avatar`, `.fl-persona` | `Avatar` (`color="colorful"`, `badge`), `AvatarGroup`, `Persona` | `FluentPersona` | `<fluent-avatar>` |
| `.fl-tag` | `Tag`, `InteractionTag`, `TagGroup` | — | — |
| `.fl-card` | `Card` (`size`, `appearance="filled|filled-alternative|outline"`, `selected`), `CardHeader`, `CardFooter` | `FluentCard` | `<fluent-card>` |
| `.fl-datagrid` | `DataGrid`, `DataGridHeaderCell`, `DataGridRow`, `DataGridCell`, `TableCellLayout`, `TableSelectionCell` | `FluentDataGrid`, `PropertyColumn`, `SelectColumn` | — |
| `.fl-messagebar` | `MessageBar`, `MessageBarBody`, `MessageBarTitle`, `MessageBarActions` (`intent`) | `FluentMessageBar Intent` | `<fluent-message-bar>` |
| `.fl-toast` | `Toaster`, `Toast`, `ToastTitle`, `ToastBody`, `ToastFooter` | `FluentToastProvider` | — |
| `.fl-tooltip`, `.fl-popover` | `Tooltip`, `Popover`/`PopoverSurface` | `FluentTooltip`, `FluentPopover` | `<fluent-tooltip>` |
| `.fl-progress`, `.fl-spinner`, `.fl-skeleton` | `ProgressBar`, `Spinner`, `Skeleton`/`SkeletonItem` | `FluentProgress`, `FluentProgressRing`, `FluentSkeleton` | `<fluent-progress-bar>`, `<fluent-spinner>` |
| `.fl-menu`, `.fl-menuitem` | `Menu`, `MenuList`, `MenuItem`, `MenuItemRadio`, `MenuDivider`, `MenuGroupHeader` | `FluentMenu`, `FluentMenuItem` | `<fluent-menu>`, `<fluent-menu-item>` |
| `.fl-dialog` | `Dialog`, `DialogSurface`, `DialogTitle`, `DialogContent`, `DialogActions` | `FluentDialog` | `<fluent-dialog>` |
| `.fl-drawer` | `OverlayDrawer`, `InlineDrawer`, `DrawerHeader`, `DrawerBody`, `DrawerFooter` | `FluentDialog` (panel) | `<fluent-drawer>` |
| `.fl-accordion`, `.fl-divider` | `Accordion`, `AccordionItem`, `AccordionHeader`, `AccordionPanel`, `Divider` | `FluentAccordion`, `FluentDivider` | `<fluent-accordion>`, `<fluent-divider>` |
| `.fl-toolbar` | `Toolbar`, `ToolbarButton`, `ToolbarDivider` | `FluentToolbar` | `<fluent-toolbar>` |
| `.fl-chart`, `.fl-legend` | `@fluentui/react-charts` `LineChart`, `DonutChart`, `HorizontalBarChart` | — | — |
| Text presets `.fl-caption1` … `.fl-display` | `Caption1`, `Body1`, `Body1Strong`, `Subtitle2`, `Subtitle1`, `Title3`, `Title2`, `Title1`, `LargeTitle`, `Display` | `FluentLabel Typo` | — |

---

## 1. App shell — header (suite bar) and NavDrawer

**Purpose:** the frame of every back-office page. **Anatomy:** `.fl-app` › `header.fl-header` (`.fl-header__menu` hamburger
for < 1024 px, `.fl-header__btn` 48 × 48 icon buttons, `.fl-header__brand` › `.fl-logo` + `.fl-header__app`,
`.fl-header__center` › `.fl-header__search`, `.fl-header__actions`, `.fl-header__me` › `.fl-avatar`) + `.fl-shell` ›
`nav.fl-nav` (`.fl-nav__list`, `.fl-nav__item`, `.fl-nav__label`, `.fl-nav__section`, `.fl-nav__footer`) + `main.fl-main` ›
`.fl-page` (`.fl-page--narrow` for forms). **Variants:** `.fl-header--neutral` (grey bar). **States:** selected item
`.is-selected` + `aria-current="page"` → semibold, brand filled icon, 4 × 20 px indicator; `.is-hover`.
**Do:** keep the NavDrawer at 260 px on `colorNeutralBackground4`. **Don't:** put more than ~10 items per section.

```html
<body class="fl-mockup fl-body"><div class="fl-app">
  <header class="fl-header">
    <button type="button" class="fl-header__btn fl-header__menu" aria-label="Open navigation"><!-- navigation_20_regular --></button>
    <a class="fl-header__brand" href="dashboard.html"><svg class="fl-logo">…</svg><span class="fl-header__app">Contoso Workplace</span></a>
    <div class="fl-header__center"><div class="fl-header__search" role="search"><!-- search --><input type="search" aria-label="Search" placeholder="Search requests"></div></div>
    <div class="fl-header__actions">
      <a class="fl-header__btn" href="#n" aria-label="Notifications, 3 unread"><!-- alert --><span class="fl-counter">3</span></a>
      <a class="fl-header__me" href="settings.html" aria-label="Account manager"><span class="fl-avatar fl-avatar--brand" role="img" aria-label="Aiko Tanaka">AT</span></a>
    </div>
  </header>
  <div class="fl-shell">
    <nav class="fl-nav" aria-label="Main navigation"><div class="fl-nav__list">
      <a class="fl-nav__item is-selected" href="dashboard.html" aria-current="page"><!-- home_20_filled --><span class="fl-nav__label">Home</span></a>
      <a class="fl-nav__item" href="resource-list.html"><!-- ticket_diagonal_20_regular --><span class="fl-nav__label">Requests</span><span class="fl-counter fl-counter--informative">24</span></a>
    </div><div class="fl-nav__section">Administration</div></nav>
    <main class="fl-main" id="main"><div class="fl-page">…</div></main>
  </div>
</div></body>
```

## 2. Page header, Breadcrumb, Toolbar

`.fl-page-header` › `.fl-page-header__text` (`__title` = Title3 24/32, `__desc`, `__meta`) + `.fl-page-header__actions`.
`nav > ol.fl-breadcrumb` (`--small`) › `.fl-breadcrumb__item` › `.fl-breadcrumb__button` (`[aria-current="page"]` /
`.is-current` = semibold) with `.fl-breadcrumb__divider` (chevron_right 12/16). `.fl-toolbar` (`role="toolbar"`) holds subtle
buttons, `.fl-toolbar__spacer`, `.fl-toolbar__divider`; `.fl-selection-bar` (brand-tinted) shows `__count` and bulk commands.

```html
<nav aria-label="Breadcrumb"><ol class="fl-breadcrumb fl-breadcrumb--small">
  <li class="fl-breadcrumb__item"><a class="fl-breadcrumb__button" href="dashboard.html">Home</a></li>
  <li class="fl-breadcrumb__item fl-breadcrumb__divider" aria-hidden="true"><!-- chevron_right_12 --></li>
  <li class="fl-breadcrumb__item"><a class="fl-breadcrumb__button" href="resource-list.html" aria-current="page">Requests</a></li>
</ol></nav>
<div class="fl-page-header">
  <div class="fl-page-header__text"><h1 class="fl-page-header__title">Requests</h1><p class="fl-page-header__desc">Every HR and IT request.</p></div>
  <div class="fl-page-header__actions"><button type="button" class="fl-btn">Import</button><a class="fl-btn fl-btn--primary" href="resource-form.html">New request</a></div>
</div>
```

## 3. Button, SplitButton, ToggleButton, Link

**Anatomy:** `.fl-btn` (secondary, 32 px, min-width 96, padding 5 × 12, radius 4, body1Strong) + optional icon (`fl-icon`) and
`.fl-btn__chev`. **Appearance:** `--primary`, `--outline`, `--subtle`, `--transparent`; on brand surfaces `--on-brand`,
`--on-brand-outline`. **Size:** `--small` (24), default, `--large` (40). **Shape:** `--circular`. **Icon-only:** `--icon`
(+ `aria-label`). **States:** `:hover`/`.is-hover`, `:active`/`.is-pressed`, `:focus-visible`/`.is-focus`, `disabled`,
`.is-checked` (ToggleButton). SplitButton: `.fl-split` › two `.fl-btn`. Link: `.fl-link` (`--subtle`, `--inline`, `--chevron`).
**Do:** one primary per view. **Don't:** use primary for destructive actions — confirm in a Dialog instead.

```html
<button type="button" class="fl-btn fl-btn--primary"><!-- add_20_regular -->New request</button>
<button type="button" class="fl-btn">Cancel</button>
<button type="button" class="fl-btn fl-btn--subtle fl-btn--icon" aria-label="More actions"><!-- more_horizontal --></button>
<div class="fl-split"><button type="button" class="fl-btn fl-btn--primary">Save</button><button type="button" class="fl-btn fl-btn--primary" aria-label="More save options"><!-- chevron_down --></button></div>
<a class="fl-link fl-link--chevron" href="pricing.html">See plans and pricing<!-- chevron_right_16 --></a>
```

## 4. Field, Label, Input

**Anatomy:** `.fl-field` › `label.fl-label` (`.fl-label__required` red asterisk) + control + `.fl-field__msg`
(`--error|--warning|--success`, 12 px filled icon) + `.fl-field__hint`. `.fl-field--horizontal` puts the label in a 33 % column.
Input root `.fl-input` (32 px, Stroke1 border, StrokeAccessible bottom, 2 px brand underline on focus) › `input.fl-input__input`,
optional `.fl-input__before|__after` icons and `.fl-input__prefix`. **Appearance:** default outline, `--underline`,
`--filled-darker`. **Size:** `--small`, `--large`. **States:** `.is-focus`, `.is-invalid` (+ `aria-invalid`), `.is-disabled`.

```html
<div class="fl-field">
  <label class="fl-label" for="title">Title<span class="fl-label__required" aria-hidden="true">*</span></label>
  <span class="fl-input is-invalid"><input class="fl-input__input" id="title" aria-invalid="true" aria-describedby="title-e"></span>
  <span class="fl-field__msg fl-field__msg--error" id="title-e"><!-- error_circle_12_filled -->Enter a title of 5 to 120 characters.</span>
</div>
<div class="fl-field"><label class="fl-label" for="asset">Asset tag</label>
  <span class="fl-input"><span class="fl-input__prefix">CW-</span><input class="fl-input__input" id="asset" value="LT-08812"></span>
  <span class="fl-field__hint">On the sticker under the device.</span></div>
```

## 5. Select, Textarea, SearchBox

All share the Input root: `.fl-input.fl-select` › `select.fl-input__input` + `.fl-select__icon` (chevron);
`.fl-input.fl-textarea` › `textarea.fl-input__input`; `.fl-input.fl-searchbox` (search icon before, dismiss button after,
`--compact` = 200 px in toolbars).

```html
<span class="fl-input fl-select"><select class="fl-input__input" id="sub"><option>Laptop</option></select><span class="fl-select__icon"><!-- chevron_down --></span></span>
<span class="fl-input fl-textarea"><textarea class="fl-input__input" rows="4" aria-label="Description"></textarea></span>
<div class="fl-input fl-searchbox fl-searchbox--compact" role="search"><!-- search --><input class="fl-input__input" type="search" aria-label="Search requests"></div>
```

## 6. Dropdown, Combobox, Listbox, Option

Trigger `button.fl-input.fl-dropdown` (`role="combobox"`, `aria-expanded`) › `.fl-dropdown__value` | `.fl-dropdown__placeholder`
+ `.fl-dropdown__chevron`. Popup `.fl-listbox` (`--popup` positions it under the trigger inside `.fl-dropdown-wrap`; shadow16)
› `.fl-option-group__label`, `.fl-option` (`.is-selected` shows the checkmark, `.is-active` = keyboard focus, `.is-disabled`)
› `.fl-option__check` + text + `.fl-option__secondary`.

```html
<div class="fl-field fl-dropdown-wrap"><label class="fl-label" for="grp">Assignment group</label>
  <button type="button" class="fl-input fl-dropdown is-focus" id="grp" role="combobox" aria-expanded="true" aria-controls="grp-list"><span class="fl-dropdown__value">Endpoint · Madrid</span><span class="fl-dropdown__chevron"><!-- chevron_down --></span></button>
  <div class="fl-listbox fl-listbox--popup" role="listbox" id="grp-list">
    <div class="fl-option-group__label">Suggested</div>
    <div class="fl-option is-selected is-active" role="option" aria-selected="true"><span class="fl-option__check"><!-- checkmark --></span>Endpoint · Madrid<span class="fl-option__secondary">4 online</span></div>
    <div class="fl-option" role="option" aria-selected="false"><span class="fl-option__check"><!-- checkmark --></span>Service desk</div>
  </div>
</div>
```

## 7. DatePicker and Calendar

`.fl-input.fl-datepicker` › input + transparent icon button (calendar). Popup `.fl-calendar` › `.fl-calendar__head`
(`__nav`), `.fl-calendar__grid` › `.fl-calendar__dow`, `.fl-calendar__day` (`--out`, `--today`, `--selected`), `.fl-calendar__foot`.

```html
<span class="fl-input fl-datepicker"><input class="fl-input__input" value="Tue, Oct 14, 2026" aria-label="Needed by"><button type="button" class="fl-btn fl-btn--transparent fl-btn--icon fl-btn--small" aria-label="Open calendar"><!-- calendar --></button></span>
<div class="fl-calendar" role="dialog" aria-label="October 2026"><div class="fl-calendar__head"><span>October 2026</span><span class="fl-calendar__nav">…</span></div>
  <div class="fl-calendar__grid"><span class="fl-calendar__dow">Su</span>…<span class="fl-calendar__day fl-calendar__day--today">12</span><span class="fl-calendar__day fl-calendar__day--selected">14</span></div></div>
```

## 8. Checkbox, RadioGroup, Switch, Slider

`label.fl-checkbox` › `input.fl-checkbox__input` (16 px, radius 2; `checked`, `.is-mixed`, `disabled`) + `.fl-checkbox__label`
(`<small>` = secondary line). `.fl-radio-group` (`--horizontal`) › `label.fl-radio` › `input.fl-radio__input`.
`label.fl-switch` › `input.fl-switch__input role="switch"` (40 × 20 track). `.fl-slider` › `__rail` › `__fill` + `__thumb`.
Disabled wrappers add `.is-disabled`.

```html
<label class="fl-checkbox"><input type="checkbox" class="fl-checkbox__input" checked><span class="fl-checkbox__label">Stay signed in</span></label>
<div class="fl-radio-group" role="radiogroup" aria-label="Business impact">
  <label class="fl-radio"><input type="radio" class="fl-radio__input" name="impact" checked><span class="fl-radio__label">One person</span></label>
  <label class="fl-radio"><input type="radio" class="fl-radio__input" name="impact"><span class="fl-radio__label">A team</span></label>
</div>
<label class="fl-switch"><input type="checkbox" role="switch" class="fl-switch__input" checked><span class="fl-switch__label">Notify the requester's manager<small>Sent once per request.</small></span></label>
```

## 9. TabList / Tab

`.fl-tablist` (`role="tablist"`; `--bordered`, `--small`, `--subtle`, `--vertical`) › `button.fl-tab` (`role="tab"`;
`.is-selected` + `aria-selected="true"` → semibold, brand icon, 3 px rounded indicator; `.is-hover`, `.is-disabled`), with
optional icon and `.fl-counter`. Vertical tabs become a horizontal scroller on phones.

```html
<div class="fl-tablist fl-tablist--bordered" role="tablist" aria-label="Views">
  <button type="button" class="fl-tab is-selected" role="tab" aria-selected="true">All open<span class="fl-counter fl-counter--informative">214</span></button>
  <button type="button" class="fl-tab" role="tab" aria-selected="false">Assigned to me</button>
</div>
```

## 10. Badge, CounterBadge, PresenceBadge

`.fl-badge` (20 px, circular, caption1Strong) = Fluent `appearance` × `color`: colour classes `--danger|--important|
--informative|--severe|--subtle|--success|--warning` (brand is the default) and appearance classes `--tint`, `--outline`,
`--ghost` (filled is the default). Sizes `--small`, `--large`, `--xl`, `--dot`; shape `--rounded`. `.fl-counter`
(`--brand`, `--informative`, `--medium`). `.fl-presence` (`--away`, `--busy`, `--offline`) wraps the presence icon.
Contoso uses tint badges for status (`In progress` brand, `Waiting on approval` warning, `Resolved` success) and
filled danger for `Urgent` / `Escalated`.

```html
<span class="fl-badge fl-badge--severe fl-badge--tint">High</span>
<span class="fl-badge fl-badge--success fl-badge--tint fl-badge--rounded">Resolved</span>
<span class="fl-badge fl-badge--danger">Urgent</span>
<span class="fl-counter">3</span>
```

## 11. Avatar, AvatarGroup, Persona

`.fl-avatar` (32 px default; `--20|24|36|40|48|56|72|96`; colours `--brand`, `--cranberry`, `--pumpkin`, `--gold`, `--forest`,
`--teal`, `--lavender`; `--square`, `--icon`) with initials and optional `.fl-presence`. `.fl-avatar-group`.
`.fl-persona` (`--large`) › avatar + `.fl-persona__text` › `__primary`, `__secondary`, `__tertiary`.

```html
<span class="fl-persona fl-persona--large"><span class="fl-avatar fl-avatar--56 fl-avatar--pumpkin" role="img" aria-label="Diego Ramírez">DR<span class="fl-presence" role="img" aria-label="Available"><!-- presence_available_10_filled --></span></span>
  <span class="fl-persona__text"><span class="fl-persona__primary">Diego Ramírez</span><span class="fl-persona__secondary">Account executive · Sales Iberia</span><span class="fl-persona__tertiary">Available · Madrid</span></span></span>
```

## 12. Tag

`.fl-tag` (32 px, radius 4, Background3; `--small`, `--outline`, `--brand`, `--circular`) with optional icon and
`button.fl-tag__dismiss`; `.fl-tag-group`. Used for active filters and metadata chips.

```html
<div class="fl-tag-group" aria-label="Active filters"><span class="fl-tag fl-tag--small fl-tag--brand">Priority: High, Urgent<button type="button" class="fl-tag__dismiss" aria-label="Remove priority filter"><!-- dismiss_16 --></button></span></div>
```

## 13. Card and KPI tile

`.fl-card` (radius 4, padding 12, shadow4; `--small`, `--large` = radius 6 / padding 16; `--alt`, `--outline`,
`--interactive` + `.is-hover`, `.is-selected`) › `.fl-card__header` (`.fl-card__icon` (+`--teal|--purple|--pumpkin`),
`__header-text` › `__title` (`--lg`) + `__desc`, `__action`), `.fl-card__body`, `.fl-card__footer`, `.fl-card__section`.
KPI: `.fl-kpi` › `__label`, `__value` (Bahnschrift numerals) + `__unit`, `__delta` (`--up` good, `--down` bad).

```html
<div class="fl-card fl-card--large"><div class="fl-kpi">
  <span class="fl-kpi__label"><!-- shield_checkmark -->SLA attainment</span>
  <span class="fl-kpi__value">96.4<span class="fl-kpi__unit">%</span></span>
  <span class="fl-kpi__delta fl-kpi__delta--up"><!-- arrow_up_16 -->0.6 pt<span class="fl-fg3">&nbsp;target 95%</span></span>
</div></div>
```

## 14. DataGrid

`.fl-datagrid-wrap` (horizontal scroll) › `table.fl-datagrid` (`--small` 34 px rows) › `tr.fl-datagrid__row`
(`.is-selected` + `aria-selected`, `.is-hover`) › `th.fl-datagrid__hcell` (`--sortable`, `.is-sorted` + `aria-sort`,
`--num`) › `button.fl-datagrid__hbtn` + `.fl-datagrid__sort` · `td.fl-datagrid__cell` (`--num`, `--muted`), selection
column `.fl-datagrid__select`, actions column `.fl-datagrid__actions-cell`. Cell content: `.fl-cell-layout` (`--clamp`) ›
`__media`, `__main` › `__primary` + `__desc`. Around it: `.fl-toolbar`, `.fl-selection-bar`, `.fl-tag-group`,
`.fl-pagination` (`__pages`, `__page` with `aria-current`). A row menu drawn open goes in `.fl-grid-menu` inside
`.fl-surface.fl-surface--anchor`.

```html
<div class="fl-datagrid-wrap"><table class="fl-datagrid" aria-label="Requests" aria-multiselectable="true">
  <thead><tr class="fl-datagrid__row">
    <th class="fl-datagrid__hcell fl-datagrid__select" scope="col"><input type="checkbox" class="fl-checkbox__input is-mixed" aria-label="Select all rows"></th>
    <th class="fl-datagrid__hcell fl-datagrid__hcell--sortable" scope="col"><button type="button" class="fl-datagrid__hbtn">Request</button></th>
    <th class="fl-datagrid__hcell fl-datagrid__hcell--sortable is-sorted" scope="col" aria-sort="ascending"><button type="button" class="fl-datagrid__hbtn">SLA due<span class="fl-datagrid__sort"><!-- arrow_sort_up_16 --></span></button></th>
  </tr></thead>
  <tbody><tr class="fl-datagrid__row is-selected" aria-selected="true">
    <td class="fl-datagrid__cell fl-datagrid__select"><input type="checkbox" class="fl-checkbox__input" checked aria-label="Select REQ-24816"></td>
    <td class="fl-datagrid__cell"><div class="fl-cell-layout fl-cell-layout--clamp"><div class="fl-cell-layout__main"><a class="fl-cell-layout__primary fl-link" href="resource-view.html">Access to the Finance Close site</a><span class="fl-cell-layout__desc">REQ-24816 · Access &amp; accounts</span></div></div></td>
    <td class="fl-datagrid__cell">Due tomorrow</td>
  </tr></tbody>
</table></div>
```

## 15. MessageBar

`.fl-messagebar` (36 px, radius 4; intents `--success`, `--warning`, `--error`, default info; `--multiline`) ›
`.fl-messagebar__icon` (filled 20 px), `.fl-messagebar__body` › `.fl-messagebar__title` + text, `.fl-messagebar__actions`
(small buttons + dismiss). **Do:** put it at the top of the page or form it concerns. **Don't:** stack more than three.

```html
<div class="fl-messagebar fl-messagebar--error fl-messagebar--multiline" role="alert">
  <span class="fl-messagebar__icon"><!-- error_circle_20_filled --></span>
  <div class="fl-messagebar__body"><span class="fl-messagebar__title">1 field needs your attention</span>Add a short title. <a class="fl-link fl-link--inline" href="#title">Go to Title</a></div>
  <button type="button" class="fl-btn fl-btn--subtle fl-btn--icon fl-btn--small" aria-label="Dismiss"><!-- dismiss_16 --></button>
</div>
```

## 16. Toast, Tooltip, ProgressBar, Spinner, Skeleton

`.fl-toaster` (fixed bottom-right; `--static` inline) › `.fl-toast` (`--inverted`) › `__icon`, `__title`, `__action`,
`__body`, `__subtitle`, `__footer`. `.fl-tooltip` (`--inverted`). `.fl-progress` (2 px; `--large` 4 px; `--warning`,
`--error`) › `__bar`, with `.fl-progress-field` label row. `.fl-spinner` › `__ring`. `.fl-skeleton` (`--line`, `--circle`).

```html
<div class="fl-toaster" role="status" aria-live="polite"><div class="fl-toast">
  <span class="fl-toast__icon fl-icon--success"><!-- checkmark_circle_20_filled --></span><span class="fl-toast__title">Comment sent to Diego</span>
  <a class="fl-link fl-toast__action" href="#undo">Undo</a><span class="fl-toast__subtitle">He'll get it by email and in chat.</span>
</div></div>
<div class="fl-progress fl-progress--large fl-progress--warning" role="progressbar" aria-valuenow="72" aria-valuemin="0" aria-valuemax="100" aria-label="SLA elapsed"><span class="fl-progress__bar" style="width:72%"></span></div>
```

## 17. Menu and Popover

`.fl-menu` (shadow16, radius 4, padding 4) › `.fl-menu__group-header`, `button.fl-menuitem` (`role="menuitem"`;
`.is-hover`, `.is-checked`, `.is-disabled`) › `.fl-menuitem__check` | `.fl-menuitem__icon`, `__content`, `__secondary`
(shortcut), `__chev` (submenu); `.fl-menu__divider`. `.fl-popover` (`--brand`). Anchor next to a trigger with `.fl-anchor`.

```html
<div class="fl-menu" role="menu" aria-label="Actions for REQ-24812">
  <button type="button" class="fl-menuitem" role="menuitem"><span class="fl-menuitem__icon"><!-- open --></span><span class="fl-menuitem__content">Open</span></button>
  <button type="button" class="fl-menuitem is-hover" role="menuitem"><span class="fl-menuitem__icon"><!-- person --></span><span class="fl-menuitem__content">Assign to me</span></button>
  <div class="fl-menu__divider" role="separator"></div>
  <button type="button" class="fl-menuitem" role="menuitem"><span class="fl-menuitem__icon"><!-- link --></span><span class="fl-menuitem__content">Copy link</span><span class="fl-menuitem__secondary">Ctrl+Shift+C</span></button>
</div>
```

## 18. Dialog and Drawer

`.fl-dialog-backdrop` (overlay 40 % / 50 % dark; `--static` inside a `.fl-stage` preview) › `.fl-dialog` (radius 8,
padding 24, shadow64, max 600 px) › `.fl-dialog__title` (+ `.fl-dialog__close`), `.fl-dialog__content`, `.fl-dialog__actions`
(primary last). `.fl-drawer.fl-drawer--overlay` (320 px, shadow64) › `__header` › `__title`, `__body`, `__footer`.

```html
<div class="fl-dialog-backdrop"><div class="fl-dialog" role="dialog" aria-modal="true" aria-labelledby="dlg-t">
  <div class="fl-dialog__title" id="dlg-t">Close 2 requests?<button type="button" class="fl-btn fl-btn--subtle fl-btn--icon fl-dialog__close" aria-label="Close"><!-- dismiss --></button></div>
  <div class="fl-dialog__content"><p>The requesters get a satisfaction survey.</p></div>
  <div class="fl-dialog__actions"><button type="button" class="fl-btn">Cancel</button><button type="button" class="fl-btn fl-btn--primary">Close requests</button></div>
</div></div>
```

## 19. Accordion, Divider, lists, timeline, property list, empty state

`.fl-accordion` › `.fl-accordion__item` (`.is-open`) › `button.fl-accordion__header` (`.fl-accordion__chev` rotates) +
`.fl-accordion__panel`. `.fl-divider` (text in the middle), `hr.fl-hr`. `.fl-list` › `.fl-list__item` › `.fl-list__main`.
`dl.fl-props` (label/value pairs). `ol.fl-timeline` › `.fl-timeline__item` › `.fl-timeline__marker` (`--brand`, `--success`)
+ `.fl-timeline__body` (`__time`, `__note`). `.fl-empty` › `.fl-empty__art` (inline SVG drawn from tokens), `__title`,
`__text`, `__actions`.

```html
<div class="fl-empty"><svg class="fl-empty__art" viewBox="0 0 160 120" aria-hidden="true">…</svg>
  <h2 class="fl-empty__title">You're all caught up</h2><p class="fl-empty__text">Nothing is assigned to you right now.</p>
  <div class="fl-empty__actions"><a class="fl-btn fl-btn--primary" href="resource-list.html">View unassigned (17)</a></div></div>
```

## 20. Charts

Inline SVG `svg.fl-chart` with `.fl-chart__grid` lines, caption axis text, `.fl-chart__line--1|2` (2 px), `.fl-chart__area--1`,
`.fl-chart__dot--1|2`, `.fl-chart__crosshair`, donut segments `circle.fl-chart__seg.fl-chart__seg--1…4`, centre
`.fl-chart__big`. Legend `.fl-legend` (`--stacked`) › `.fl-legend__item` › `.fl-legend__swatch` (`--2…4`, `--line`) +
`.fl-legend__value`. Static tooltip `.fl-chart-tip` (inside `.fl-chart-box`), summary `.fl-chart-stats`, horizontal bars
`.fl-hbar`. Colour order is the Fluent data-viz palette: never reuse status colours for series.

## 21. Public site pieces

`header.fl-uhf` › `.fl-container.fl-uhf__bar` (`__logo`, `__nav` › `__link` (`aria-current`), `__actions` › `__signin`);
`nav.fl-localnav` (`__title`, `__links`, `__cta`); `.fl-hero` (`__grid`, `__eyebrow`, `__title`, `__lead`, `__actions`, `__note`,
`__media` with `.fl-mock-window` and `.fl-float-card--a|b`); `.fl-section` (`--alt`, `--tight`) › `.fl-section__head`
(`__title`, `__lead`); `.fl-feature-row` (`--reverse`; `__media`, `__text`, `__title`) + `.fl-checklist`; `.fl-logos` ›
`.fl-logo-mark`; `.fl-stats` › `.fl-stat__value|__label`; `.fl-tiles` › `.fl-tile`; `figure.fl-quote`; `.fl-plans` ›
`.fl-plan` (`--featured`, `__flag`, `__name`, `__desc`, `__price`, `__amount`, `__per`, `__actions`, `__features`);
`.fl-billing-toggle`; `table.fl-compare` (`__group`); `.fl-cta-band`; `footer.fl-footer` (`__cols`, `__title`, `__list`,
`__bottom`, `__locale`, `__legal`).

```html
<section class="fl-hero"><div class="fl-container fl-hero__grid"><div>
  <h1 class="fl-hero__title">One front door for every HR and IT request</h1>
  <p class="fl-hero__lead">Contoso Workplace gives employees a single place to ask for help.</p>
  <div class="fl-hero__actions"><a class="fl-btn fl-btn--primary fl-btn--large" href="login.html">Try free for 30 days</a></div>
</div><div class="fl-hero__media">…</div></div></section>
```

## 22. Sign-in and drawn fields

`.fl-signin` › `main.fl-signin__main` › `.fl-signin__card` (440 px, 44 px padding) › `__brand`, `__title`, `__fields`,
`__links`, `__actions`; `.fl-signin__identity` (back + account), `.fl-signin__options`, `footer.fl-signin__footer`.
Drawn fields: `.mock-label`, `.mock-field` (outline Input look) / `.mock-field--underline` (sign-in look), `--secret`,
`--empty`, `--focus`, `--error`, `.mock-field__caret`.

```html
<span class="mock-label" id="l-pass">Password</span>
<div class="mock-field mock-field--underline mock-field--secret mock-field--focus" role="img" aria-label="Password field, filled in">••••••••••••<span class="mock-field__caret"></span></div>
<a class="fl-btn fl-btn--primary" href="dashboard.html" role="button">Sign in</a>
```

## Utilities

Text presets `.fl-caption2`, `.fl-caption1`, `.fl-caption1-strong`, `.fl-body1`, `.fl-body1-strong`, `.fl-body2`,
`.fl-subtitle2`, `.fl-subtitle1`, `.fl-title3`, `.fl-title2`, `.fl-title1`, `.fl-large-title`, `.fl-display`; colours
`.fl-fg1…4`, `.fl-fg-danger|-warning`; `.fl-semibold`, `.fl-nowrap`, `.fl-mono`, `.fl-numeric`. Layout: `.fl-stack`
(`--4|8|16|20`), `.fl-row` (`--wrap`, `--between`, `--center`, `--top`, `--4|12|16`), `.fl-grow`, `.fl-ml-auto`, `.fl-grid`
(`--2|3|4`, `--main-side`, `--side-main`, `--gap-24`), `.fl-span-2`, `.fl-span-all`, `.fl-form-grid`, `.fl-form-footer`
(sticky), `.fl-surface`, `.fl-hide-sm` / `.fl-show-sm` (phone), `.visually-hidden`.
