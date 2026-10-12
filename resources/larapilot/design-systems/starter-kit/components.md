# Laravel Starter Kit — components

Every `.sk-*` class in `tokens.css`, with its markup and the real component it stands for. "React" names are the
kit's `resources/js/components/…` files (Vue uses the same names in PascalCase `.vue` files); "Flux" is what the
Livewire kit renders. "Registry" means the component is not in the kit: add it with `npx shadcn@latest add <name>`
(`npx shadcn-vue@latest add` for Vue). Icons are inline Lucide SVGs: `<svg class="sk-icon" viewBox="0 0 24 24"
aria-hidden="true">…paths…</svg>` (sizes `sk-icon--3`, `--5`, `--6`).

## 1. App shell — sidebar layout

**Purpose:** the default authenticated shell. **React:** `AppSidebarLayout` + `AppSidebar` (`<Sidebar collapsible="icon" variant="inset">`) + `AppSidebarHeader`. **Flux:** `flux:sidebar` + `flux:main` (add `sk-flux` to `<body>` and drop `--inset`).
**Anatomy:** `.sk-shell.sk-shell--inset` > `aside.sk-sidebar` > `.sk-sidebar__inner` > `__header` / `nav.sk-sidebar__content` / `__footer`; `main.sk-inset#main` > `header.sk-inset__header` + `.sk-content`.
**Variants:** `--inset` (kit default: page on `--sidebar`, content in a rounded card), no modifier (`variant="sidebar"`, border-right), `--floating`; `.sk-sidebar--collapsed` (icon rail, 3rem). Below 768px the sidebar is hidden and the header trigger is the menu button.

```html
<div class="sk-shell sk-shell--inset">
  <aside class="sk-sidebar" aria-label="Sidebar"><div class="sk-sidebar__inner">…</div></aside>
  <main class="sk-inset" id="main">
    <header class="sk-inset__header">
      <button class="sk-sidebar-trigger" type="button" aria-label="Toggle sidebar"><svg class="sk-icon">…panel-left…</svg></button>
      <nav class="sk-breadcrumb" aria-label="Breadcrumb"><ol><li><span aria-current="page">Dashboard</span></li></ol></nav>
    </header>
    <div class="sk-content">…page…</div>
  </main>
</div>
```
Do keep breadcrumbs as the only header content (the kit has no page title bar). Don't put a second sidebar inside.

## 2. Sidebar brand, menu, footer, user

**React:** `AppLogo` in `SidebarMenuButton size="lg"`; `NavMain` (`SidebarGroup` "Platform" + `SidebarMenuButton isActive`); `NavFooter` (muted links, 20px icons); `NavUser` (avatar + name + `ChevronsUpDown`). **Flux:** `x-app-logo`, `flux:sidebar.group`, `flux:sidebar.item :current`, `flux:sidebar.profile`.
**States:** `.sk-sidebar__item.is-active` + `aria-current="page"`; `.sk-sidebar__item--muted` for footer links; `.sk-sidebar__user[aria-expanded="true"]`.

```html
<div class="sk-sidebar__header"><a class="sk-sidebar__brand" href="dashboard.html"><span class="sk-logo-mark"><svg>…logo…</svg></span><span class="sk-sidebar__brand-name">Ledgerly</span></a></div>
<nav class="sk-sidebar__content" aria-label="Main"><div class="sk-sidebar__group">
  <div class="sk-sidebar__group-label">Platform</div>
  <ul class="sk-sidebar__menu"><li><a class="sk-sidebar__item is-active" aria-current="page" href="#"><svg class="sk-icon">…</svg><span>Dashboard</span></a></li></ul>
</div></nav>
<div class="sk-sidebar__footer">
  <div class="sk-sidebar__group sk-sidebar__group--footer"><ul class="sk-sidebar__menu"><li><a class="sk-sidebar__item sk-sidebar__item--muted" href="#"><svg class="sk-icon sk-icon--5">…</svg><span>Help center</span></a></li></ul></div>
  <button class="sk-sidebar__user" type="button" aria-haspopup="menu"><span class="sk-avatar">MO</span><span class="sk-sidebar__user-meta"><span class="sk-sidebar__user-name">Maya Okafor</span></span><svg class="sk-icon sk-icon--chevrons">…</svg></button>
</div>
```
Don't show the email under the name in the sidebar button (the kit shows it only in the menu).

## 3. Header layout

**React:** `AppHeaderLayout` / `AppHeader` (`NavigationMenu`, active underline, search, tooltip icon links, avatar menu). **Flux:** `flux:header container` + `flux:navbar` + `flux:dropdown`.
**Anatomy:** `.sk-header-shell` > `header.sk-header` > `.sk-header__inner` (`.sk-header__menu` mobile button, `.sk-header__brand`, `nav.sk-header__nav` with `li.is-active` + `.sk-header__link.is-active`, `.sk-header__actions` with `.sk-icon-link` and `.sk-header__avatar`); `.sk-header__crumbs` (only when there are 2+ crumbs); `main.sk-header__content`.

```html
<header class="sk-header"><div class="sk-header__inner">
  <button class="sk-btn sk-btn--ghost sk-btn--icon sk-header__menu" type="button" aria-label="Open navigation menu">…menu…</button>
  <a class="sk-header__brand" href="#"><span class="sk-logo-mark">…</span><span class="sk-sidebar__brand-name">Ledgerly</span></a>
  <nav class="sk-header__nav" aria-label="Main"><ul><li class="is-active"><a class="sk-header__link is-active" aria-current="page" href="#"><svg class="sk-icon">…</svg>Accounts</a></li></ul></nav>
  <div class="sk-header__actions"><a class="sk-icon-link" href="#" aria-label="Help center">…</a><button class="sk-header__avatar" type="button" aria-label="Account menu"><span class="sk-avatar">MO</span></button></div>
</div></header>
```

## 4. Breadcrumb

**React:** `Breadcrumbs` → `Breadcrumb`, `BreadcrumbList`, `BreadcrumbLink`, `BreadcrumbPage`, `BreadcrumbSeparator`. **Flux:** `flux:breadcrumbs`.

```html
<nav class="sk-breadcrumb" aria-label="Breadcrumb"><ol>
  <li><a href="resource-list.html">Transactions</a></li>
  <li class="sk-breadcrumb__sep" role="presentation"><svg class="sk-icon">…chevron-right…</svg></li>
  <li><span aria-current="page">Summit Outfitters</span></li>
</ol></nav>
```

## 5. Heading and page header

**React:** `components/heading.tsx` (`variant="small"` for sections). **Flux:** `flux:heading size="xl"` + `flux:subheading`.
**Variants:** `.sk-heading` (text-xl semibold, mb-8), `.sk-heading--small` (text-base medium); `.sk-page-header` puts actions on the right.

```html
<div class="sk-page-header">
  <header class="sk-heading"><h1 class="sk-heading__title">Transactions</h1><p class="sk-heading__desc">248 transactions across 4 accounts</p></header>
  <div class="sk-page-header__actions"><a class="sk-btn" href="#"><svg class="sk-icon">…plus…</svg>Add transaction</a></div>
</div>
```

## 6. Card

**React:** `Card`, `CardHeader`, `CardTitle`, `CardDescription`, `CardContent`, `CardFooter` (rounded-xl, py-6, shadow-sm). **Flux:** `flux:card` (Pro) or a bordered `div`.
**Variants:** `.sk-card__header--row` (title left, action right), `.sk-card--flush` (content without padding).

```html
<section class="sk-card" aria-labelledby="t1">
  <div class="sk-card__header sk-card__header--row"><div class="sk-stack sk-stack--xs"><h2 class="sk-card__title" id="t1">Recent transactions</h2><p class="sk-card__desc">12 this month</p></div><a class="sk-btn sk-btn--outline sk-btn--sm" href="#">View all</a></div>
  <div class="sk-card__content">…</div>
</section>
```

## 7. Button

**React:** `Button variant size` (`default`, `secondary`, `outline`, `ghost`, `destructive`, `link`; `sm`, `lg`, `icon`). **Flux:** `flux:button variant="primary|filled|outline|ghost|danger"`.
**Classes:** `.sk-btn` + `--secondary|--outline|--ghost|--destructive|--link`, sizes `--sm|--lg|--icon|--icon-sm`, `--block`, `--danger-text`; states `:disabled`, `.is-loading` (+ `.sk-spinner` icon), `.is-focus` (focus ring demo), `.is-open`.

```html
<button class="sk-btn" type="button">Save</button>
<button class="sk-btn sk-btn--outline" type="button"><svg class="sk-icon">…download…</svg>Export</button>
<button class="sk-btn sk-btn--ghost sk-btn--icon-sm" type="button" aria-label="Actions"><svg class="sk-icon">…ellipsis…</svg></button>
<button class="sk-btn sk-btn--destructive" type="button">Delete account</button>
```
Don't put two primary buttons side by side; the second is `--outline`.

## 8. Badge and status badge

**React:** `Badge variant` (the kit's is rounded-md). **Flux:** `flux:badge color size="sm"`.
**Variants:** default, `--secondary`, `--outline`, `--destructive`; Ledgerly tints `--success`, `--warning`, `--info`; `--status` (outline, muted text, coloured icon: `.sk-status-icon--ok|--warn|--muted`).

```html
<span class="sk-badge sk-badge--outline">Groceries</span>
<span class="sk-badge sk-badge--outline sk-badge--status"><svg class="sk-icon sk-status-icon--ok">…circle-check…</svg>Cleared</span>
<span class="sk-badge sk-badge--warning">Near limit</span>
```

## 9. Avatar

**React:** `Avatar` + `AvatarFallback` (initials; `bg-neutral-200 dark:bg-neutral-700`). **Flux:** `flux:avatar :initials`.
**Sizes:** `.sk-avatar--sm`, default 32px, `--lg`; `.sk-avatar-group` overlaps them.

```html
<span class="sk-avatar">MO</span>
<div class="sk-avatar-group"><span class="sk-avatar">MO</span><span class="sk-avatar">JL</span><span class="sk-avatar">+2</span></div>
```

## 10. Field: Label, Input, InputError

**React:** `<div className="grid gap-2"><Label/><Input/><InputError/></div>`. **Flux:** `flux:input label="…"` (renders `flux:field`, `flux:label`, `flux:error`).
**States:** placeholder, `.sk-input--focus` (drawn focus), `aria-invalid="true"` + `.sk-error`, `.sk-field.is-disabled` + `disabled`, `.sk-label__required`, `.sk-helper`.

```html
<div class="sk-field">
  <label class="sk-label" for="category">Category <span class="sk-label__required" aria-hidden="true">*</span></label>
  <input class="sk-input" id="category" value="Grocerys" aria-invalid="true" aria-describedby="category-error">
  <p class="sk-error" id="category-error">Choose a category from the list.</p>
</div>
<div class="sk-input-wrap"><svg class="sk-icon">…search…</svg><input class="sk-input sk-input--search" type="search" placeholder="Search payee or note" aria-label="Search"></div>
```

## 11. InputGroup (registry)

One bordered box with an inline addon (currency, unit). **Registry:** `InputGroup` + `InputGroupAddon` + `InputGroupInput`. **Flux:** `flux:input` with `flux:input.group.prefix`.

```html
<div class="sk-input-group"><span class="sk-input-group__prefix">$</span><input class="sk-input" id="amount" inputmode="decimal" value="129.95"></div>
```

## 12. Select, Textarea, date picker trigger

**React:** `Select` + `SelectTrigger` (the mockup uses a native `select` styled the same), registry `Textarea`, `Popover` + `Calendar` date picker. **Flux:** `flux:select`, `flux:textarea`, `flux:date-picker` (Pro).

```html
<div class="sk-select-wrap"><select class="sk-select" id="account"><option>Rewards Visa — Cobalt Card ••7731</option></select><svg class="sk-icon">…chevron-down…</svg></div>
<textarea class="sk-textarea" id="notes" rows="3">Rain shell for the Mount Hood trip.</textarea>
<button class="sk-btn sk-btn--outline sk-date-btn" type="button"><svg class="sk-icon">…calendar…</svg><span>October 6th, 2026</span></button>
```

## 13. Checkbox, Radio, radio cards

**React:** `Checkbox` (kit), registry `RadioGroup`; choice cards follow the registry `Field` pattern (checked = `border-primary bg-primary/5`). **Flux:** `flux:checkbox`, `flux:radio.group variant="cards"`.

```html
<div class="sk-check"><input class="sk-checkbox" id="remember" type="checkbox" checked><label for="remember">Remember me</label></div>
<div class="sk-radio-cards">
  <label class="sk-radio-card"><input class="sk-radio" type="radio" name="type" checked><svg class="sk-icon">…</svg><span>Expense</span></label>
  <label class="sk-radio-card"><input class="sk-radio" type="radio" name="type"><svg class="sk-icon">…</svg><span>Income</span></label>
</div>
```

## 14. Switch and switch row (registry)

**Registry:** `Switch`. **Flux:** `flux:switch` / `flux:field variant="inline"`.

```html
<div class="sk-switch-row"><div class="sk-check__text"><label class="sk-label" for="recurring">Repeats monthly</label><p class="sk-helper">Adds it again on the 6th.</p></div><input class="sk-switch" id="recurring" type="checkbox" role="switch"></div>
```

## 15. Drop zone and file list

App-level (an `Input type="file"` behind a dashed area). **Flux:** `flux:file-upload` (Pro).

```html
<button class="sk-dropzone" type="button"><span class="sk-dropzone__icon"><svg class="sk-icon">…upload…</svg></span><span><strong>Click to upload</strong> or drag and drop</span><span class="sk-small">PDF, JPG or PNG up to 10 MB</span></button>
<ul class="sk-file-list"><li><svg class="sk-icon">…file-text…</svg><span>summit-receipt-1006.pdf</span><span class="sk-muted sk-small">182 KB</span><button class="sk-btn sk-btn--ghost sk-btn--icon-sm" type="button" aria-label="Remove file">…x…</button></li></ul>
```

## 16. Form layout and sticky actions

`.sk-form` (gap 1.5rem), `.sk-form-grid.sk-form-grid--2`, `.sk-field--span`, `.sk-form-layout` (main column + `.sk-form-side` on lg), `.sk-form-actions` (sticky footer with `__note` and `__spacer`), `.sk-actions` (the kit's "Save" row).

```html
<div class="sk-form-actions"><span class="sk-small sk-muted sk-form-actions__note">Last edited Oct 6, 2026</span><span class="sk-form-actions__spacer"></span><a class="sk-btn sk-btn--outline" href="#">Cancel</a><button class="sk-btn" type="button">Save transaction</button></div>
```

## 17. Table (registry)

**Registry:** `Table`, `TableHeader`, `TableRow`, `TableHead`, `TableCell` (h-10 head, p-2 cells, hover `muted/50`, selected `muted`). **Flux:** `flux:table` (Pro).
**Classes:** `.sk-table-wrap` (border + horizontal scroll), `.sk-col-check`, `.sk-num` (right, tabular), `.sk-col-actions`, `tr.is-selected`, `.sk-table__sort.is-sorted`, `.sk-cell-payee` + `.sk-cat-icon`, `.sk-amount` / `--in` / `--out`.

```html
<div class="sk-table-wrap"><table class="sk-table">
  <thead><tr><th class="sk-col-check"><input class="sk-checkbox" type="checkbox" aria-label="Select all"></th><th scope="col"><button class="sk-table__sort is-sorted" type="button">Date<svg class="sk-icon sk-icon--3">…arrow-down…</svg></button></th><th scope="col" class="sk-num">Amount</th></tr></thead>
  <tbody><tr class="is-selected"><td class="sk-col-check"><input class="sk-checkbox" type="checkbox" checked aria-label="Select row"></td><td class="sk-muted">Oct 11</td><td class="sk-num sk-amount">&minus;$84.12</td></tr></tbody>
</table></div>
```

## 18. Toolbar, bulk bar, pagination

`.sk-toolbar` (`__search`, `__select`, `__right`), `.sk-bulk-bar` (`__sep`, `__actions`), `.sk-table-footer` (`__per-page`), `.sk-pager` (`__btn`, `.is-active`, `.is-disabled`, `__label`, `__ellipsis`) = registry `Pagination` (ghost links, active outline). **Flux:** `flux:pagination` (Pro).

```html
<div class="sk-bulk-bar" role="region" aria-label="Bulk actions"><strong>3 selected</strong><div class="sk-bulk-bar__actions"><button class="sk-btn sk-btn--outline sk-btn--sm" type="button">Categorize</button><button class="sk-btn sk-btn--destructive sk-btn--sm" type="button">Delete</button></div></div>
<nav aria-label="Pagination"><ul class="sk-pager"><li><a class="sk-pager__btn is-active" href="#" aria-current="page">1</a></li><li><a class="sk-pager__btn" href="#">2</a></li></ul></nav>
```

## 19. Tabs (registry) and appearance toggle (kit)

**Registry:** `Tabs` / `TabsList` / `TabsTrigger`. **Kit:** `components/appearance-tabs.tsx` (`.sk-segmented`). **Flux:** `flux:tabs` (Pro) / `flux:radio.group variant="segmented"` (the kit's Livewire appearance page).

```html
<div class="sk-tabs" role="tablist" aria-label="Period"><button class="sk-tabs__tab is-active" role="tab" aria-selected="true" type="button">October</button><button class="sk-tabs__tab" role="tab" aria-selected="false" type="button">September</button></div>
<div class="sk-segmented" role="radiogroup" aria-label="Theme"><button class="sk-segmented__btn is-active" type="button" role="radio" aria-checked="true"><svg class="sk-icon">…sun…</svg><span>Light</span></button></div>
```

## 20. Dropdown menu

**React:** `DropdownMenu` + `UserMenuContent`. **Flux:** `flux:dropdown` + `flux:menu`.
**Classes:** `.sk-menu-anchor` > trigger + `.sk-menu.sk-menu--open` (`--up`, `--static`, `--user`), `.sk-menu__label`, `.sk-menu__item` (`.is-highlighted`, `--destructive`), `.sk-menu__sep`, `.sk-menu__shortcut`.

```html
<div class="sk-menu-anchor"><button class="sk-btn sk-btn--ghost sk-btn--icon-sm is-open" type="button" aria-haspopup="menu" aria-expanded="true" aria-label="Actions">…</button>
  <div class="sk-menu sk-menu--open" role="menu"><a class="sk-menu__item" role="menuitem" href="#"><svg class="sk-icon">…pencil…</svg>Edit</a><div class="sk-menu__sep" role="separator"></div><button class="sk-menu__item sk-menu__item--destructive" role="menuitem" type="button">Delete</button></div>
</div>
```

## 21. Dialog

**React:** `Dialog` (the kit's overlay is `bg-black/80`), as in `delete-user.tsx`. **Flux:** `flux:modal`.

```html
<div class="sk-dialog-demo"><div class="sk-dialog" role="dialog" aria-modal="true" aria-labelledby="d1">
  <button class="sk-dialog__close" type="button" aria-label="Close">…x…</button>
  <div class="sk-dialog__header"><h3 class="sk-dialog__title" id="d1">Are you sure you want to delete your account?</h3><p class="sk-dialog__desc">…</p></div>
  <div class="sk-dialog__footer"><button class="sk-btn sk-btn--secondary" type="button">Cancel</button><button class="sk-btn sk-btn--destructive" type="button">Delete account</button></div>
</div></div>
```

## 22. Toast, Alert, status line, danger box

**React:** `Toaster` (`components/ui/sonner.tsx`, bottom-right, 356px), `Alert` / `AlertTitle` / `AlertDescription`, the green status `<p>` of auth pages, the `delete-user.tsx` box. **Flux:** `flux:toast`, `flux:callout`.

```html
<div class="sk-toast-stack" role="status"><div class="sk-toast sk-toast--success"><svg class="sk-icon sk-toast__icon">…circle-check…</svg><div class="sk-toast__body"><p class="sk-toast__title">Profile updated.</p></div></div></div>
<div class="sk-alert sk-alert--icon sk-alert--destructive" role="alert"><svg class="sk-icon">…</svg><p class="sk-alert__title">Unable to import file</p><div class="sk-alert__desc"><p>Row 14 has no date.</p></div></div>
<div class="sk-danger-box"><div class="sk-danger-box__text"><strong>Warning</strong><p>Please proceed with caution, this cannot be undone.</p></div><button class="sk-btn sk-btn--destructive" type="button">Delete account</button></div>
```
`.sk-alert--warning` is a Ledgerly tint for review notices.

## 23. Empty (registry), Skeleton, Progress

**Registry:** `Empty` (EmptyMedia icon, EmptyTitle, EmptyDescription, EmptyContent), `Skeleton` (kit), `Progress`.

```html
<div class="sk-empty sk-empty--fill"><span class="sk-empty__icon"><svg class="sk-icon sk-icon--6">…piggy-bank…</svg></span><h2 class="sk-empty__title">No budgets yet</h2><p class="sk-empty__desc">Start with the categories you spend the most on.</p><div class="sk-row sk-row--center"><button class="sk-btn" type="button">Create from September</button></div></div>
<div class="sk-progress" role="progressbar" aria-valuenow="70" aria-valuemin="0" aria-valuemax="100" aria-label="Budget used"><div class="sk-progress__bar" style="width:70%"></div></div>
```

## 24. Dashboard widgets

`.sk-grid-3`, `.sk-dash-2`, `.sk-stats` (`--3`); stat card `.sk-card.sk-stat` (`__label`, `__value`, `__delta--up|--down`) with `.sk-sparkline` (`__line--accent`, `__area`); bar chart `.sk-chart-frame` > `.sk-chart-y` + `.sk-chart-plot` > `svg.sk-chart-svg` (`.sk-chart__grid`, `__baseline`, `__bar--1|--2`) + `.sk-chart-x`; `.sk-chart-legend` (`i.is-1|is-2`); `.sk-kpis`; `.sk-meter-list` (`.sk-meter__row`, `__track`, `__fill`); `.sk-list` (`__item`, `__icon`, `__title`, `__meta`, `__value`); `.sk-mini-list`. In the app: `Card` + registry `Chart` (Recharts `BarChart`, `ChartContainer`, `ChartLegend`).

```html
<section class="sk-card sk-stat"><div class="sk-stat__label"><h2>Net worth</h2><svg class="sk-icon">…</svg></div><p class="sk-stat__value">$48,213.90</p><p class="sk-stat__delta sk-stat__delta--up"><svg class="sk-icon">…trending-up…</svg><strong>+2.7%</strong> since September</p></section>
```
Do label both series in a legend; don't use more than the five `--chart-*` colours.

## 25. Settings layout

**React:** `layouts/settings/layout.tsx` (Heading "Settings", ghost `Button size="sm"` nav with `bg-muted` on the current item, `Separator` on mobile, `max-w-xl space-y-12`). **Flux:** `pages/settings/layout.blade.php` (`flux:navlist`).

```html
<div class="sk-settings"><aside class="sk-settings__aside"><nav class="sk-settings__nav" aria-label="Settings"><a class="sk-btn sk-btn--ghost sk-btn--sm is-active" aria-current="page" href="settings.html">Profile</a><a class="sk-btn sk-btn--ghost sk-btn--sm" href="settings-security.html">Security</a></nav></aside>
<hr class="sk-separator sk-settings__sep"><div class="sk-settings__content"><section class="sk-settings__stack">…</section></div></div>
```

## 26. Auth layouts and drawn credentials

`.sk-auth` (simple), `.sk-auth--card` + `.sk-auth__card`, `.sk-split` (`__panel`, `__brand`, `__quote`, `__side`, `__form`, `__mobile-brand`, `__titles`); `.sk-auth__brand`, `__titles`, `__title`, `__desc`, `__form`, `__submit`, `__footer`, `__legal`; `.sk-passkey-verify` + `.sk-or-divider--caps`; `.sk-text-link`; `.sk-status`. Fields are pictures: `.mock-label`, `.mock-field` (`--secret`, `--focus`, `--error`, `--empty`), `.sk-password` + `.sk-password__toggle`.

```html
<div class="sk-field"><span class="mock-label">Email address</span><div class="mock-field" role="img" aria-label="Email field, filled in">maya@okafor.family</div></div>
<div class="sk-field"><div class="sk-field__row"><span class="mock-label">Password</span><a class="sk-text-link sk-text-link--sm" href="#">Forgot your password?</a></div>
  <div class="sk-password"><div class="mock-field mock-field--secret" role="img" aria-label="Password field, filled in">••••••••••••</div><span class="sk-password__toggle" aria-hidden="true">…eye…</span></div></div>
<a class="sk-btn sk-btn--block sk-auth__submit" href="dashboard.html" role="button">Log in</a>
```
Never a `<form>`, a text input or a password input on these screens.

## 27. Passkeys and recovery codes

**React:** `manage-passkeys.tsx`, `passkey-item.tsx`, `two-factor-recovery-codes.tsx`. Classes: `.sk-passkey-list`, `.sk-passkey` (`__main`, `__icon`, `__text`, `__name`, `__chip`, `__meta`, `__slash`, `__remove`), `.sk-recovery-codes`.

```html
<div class="sk-passkey-list"><div class="sk-passkey"><div class="sk-passkey__main"><span class="sk-passkey__icon">…key-round…</span><div class="sk-passkey__text"><p class="sk-passkey__name">MacBook Air<span class="sk-passkey__chip">iCloud Keychain</span></p><p class="sk-passkey__meta">Added 2 months ago<span class="sk-passkey__slash" aria-hidden="true">/</span>Last used today</p></div></div><button class="sk-btn sk-btn--ghost sk-btn--sm sk-passkey__remove" type="button">…trash…<span class="visually-hidden">Remove</span></button></div></div>
```

## 28. Placeholder pattern and Livewire look

`.sk-placeholder` + `svg.sk-placeholder__pattern` is the kit's `PlaceholderPattern` (draw it only to show the untouched kit). `body.sk-flux` switches every token and control metric to the Livewire kit (Flux): see `dashboard-livewire.html`.

## Utilities

`.sk-muted`, `.sk-small`, `.sk-mono`, `.sk-row` (`--between`, `--wrap`, `--center`), `.sk-stack` (`--xs`, `--tight`, `--loose`, `--start`), `.sk-w-full`, `.sk-section` + `__title`, `.visually-hidden`, `.sk-skip`. Catalog only: `.sk-gallery*`, `.sk-demo*`, `.sk-swatch*`, `.sk-sidebar-demo`.
