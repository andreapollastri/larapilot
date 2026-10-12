# shadcn/ui — components

Every `.sc-*` class in `tokens.css`, with markup and the component it stands for. **React** imports come from
`@/components/ui/<name>` (`npx shadcn@latest add <name>`); **Vue** uses shadcn-vue with the same component names
(`npx shadcn-vue@latest add <name>`). Icons are inline Lucide SVGs:
`<svg class="sc-icon" viewBox="0 0 24 24" aria-hidden="true">…paths…</svg>` (`sc-icon--xs`, `--brand` for filled
logos, `--done` for the green check). Static states: `.is-active`, `.is-focused`, `.is-open`, `.is-disabled`,
`.is-loading`, `aria-invalid="true"`, `data-state="selected"`.

## 1. Sidebar shell (sidebar-07)

**React:** `SidebarProvider`, `Sidebar collapsible="icon"`, `SidebarHeader`, `SidebarContent`, `SidebarGroup`,
`SidebarGroupLabel`, `SidebarMenu`, `SidebarMenuItem`, `SidebarMenuButton`, `SidebarMenuSub`, `SidebarMenuSubButton`,
`SidebarMenuAction`, `SidebarFooter`, `SidebarInset`, `SidebarTrigger`. Block parts: `TeamSwitcher`, `NavMain`
(Collapsible), `NavProjects`, `NavUser`.
**Anatomy:** `.sc-shell` > `aside.sc-sidebar` (`__header`, `nav.__content` > `.sc-sidebar__group` > `__group-label` +
`ul.__menu` > `li.sc-sidebar__item` (`.is-open`) > `.sc-menu-btn` (`--lg`, `--muted`, `.is-active`, `__chevron`,
`__meta`) + `ul.sc-sidebar__sub` > `.sc-sub-btn.is-active` + `.sc-menu-action`; `__footer`) + `main.sc-inset`.
Hidden below 768px: the header's trigger opens it as a Sheet.

```html
<div class="sc-shell">
  <aside class="sc-sidebar" aria-label="Sidebar">
    <div class="sc-sidebar__header"><ul class="sc-sidebar__menu"><li class="sc-sidebar__item"><button class="sc-menu-btn sc-menu-btn--lg" type="button"><span class="sc-team-logo"><svg class="sc-icon">…</svg></span><span class="sc-menu-btn__meta"><strong>Halcyon Labs</strong><small>Business</small></span><svg class="sc-icon sc-ml-auto">…chevrons-up-down…</svg></button></li></ul></div>
    <nav class="sc-sidebar__content" aria-label="Main"><div class="sc-sidebar__group"><div class="sc-sidebar__group-label">Platform</div>
      <ul class="sc-sidebar__menu"><li class="sc-sidebar__item is-open"><button class="sc-menu-btn" type="button" aria-expanded="true"><svg class="sc-icon">…</svg><span>Issues</span><svg class="sc-icon sc-menu-btn__chevron">…chevron-right…</svg></button>
        <ul class="sc-sidebar__sub"><li><a class="sc-sub-btn is-active" aria-current="page" href="#"><span>All issues</span></a></li></ul></li></ul>
    </div></nav>
    <div class="sc-sidebar__footer">…NavUser…</div>
  </aside>
  <main class="sc-inset" id="main">…</main>
</div>
```
Do keep the group labels "Platform" / "Projects" style; don't style the active parent of an open sub-menu.

## 2. Inset header

**React:** the `<header>` of sidebar-07's `page.tsx`: `SidebarTrigger className="-ml-1"`, `Separator orientation="vertical"`, `Breadcrumb`.

```html
<header class="sc-header">
  <button class="sc-btn sc-btn--ghost sc-btn--icon-7 sc-sidebar-trigger" type="button" aria-label="Toggle sidebar"><svg class="sc-icon">…panel-left…</svg></button>
  <span class="sc-separator sc-separator--vertical sc-header__sep" role="presentation"></span>
  <nav aria-label="Breadcrumb"><ol class="sc-breadcrumb"><li class="sc-hidden-mobile"><a href="#">Issues</a></li><li class="sc-hidden-mobile" aria-hidden="true">…chevron…</li><li><span aria-current="page">All issues</span></li></ol></nav>
  <div class="sc-header__actions"><button class="sc-header__search" type="button">…search… Search issues… <kbd class="sc-kbd">⌘K</kbd></button></div>
</header>
<div class="sc-content">…</div>
```

## 3. Button

**React:** `Button variant="default|secondary|outline|ghost|destructive|link" size="xs|sm|default|lg|icon|icon-sm|icon-lg"`.
**Classes:** `.sc-btn`, `--secondary`, `--outline`, `--ghost`, `--destructive`, `--link`, `--xs`, `--sm`, `--lg`,
`--icon`, `--icon-sm`, `--icon-7` (SidebarTrigger), `--icon-xs`, `--danger-text`; `.sc-btn-group` (`--center`).

```html
<button class="sc-btn" type="button">Create issue</button>
<button class="sc-btn sc-btn--outline sc-btn--sm" type="button"><svg class="sc-icon">…plus…</svg>Add Issue</button>
<button class="sc-btn sc-btn--ghost sc-btn--icon-sm" type="button" aria-label="Open menu"><svg class="sc-icon">…ellipsis-vertical…</svg></button>
<button class="sc-btn sc-btn--destructive" type="button">Delete</button>
```
Don't add a shadow to the default button (v4 removed it); only outline has `shadow-xs`.

## 4. Badge

**React:** `Badge variant="default|secondary|destructive|outline"` (rounded-full). **Classes:** `.sc-badge` +
variants; `--muted` (dashboard-01 status/type badges: outline, px-1.5, muted text, icon), `--square` (faceted
filter chips), `--count` (tab counters), `--label`, `--removable`.

```html
<span class="sc-badge sc-badge--outline"><svg class="sc-icon">…trending-up…</svg>+12.5%</span>
<span class="sc-badge sc-badge--outline sc-badge--muted"><svg class="sc-icon sc-icon--done">…circle-check…</svg>Done</span>
<span class="sc-badge sc-badge--secondary sc-badge--count">3</span>
```

## 5. Card and CardAction

**React:** `Card`, `CardHeader`, `CardTitle`, `CardDescription`, `CardAction`, `CardContent`, `CardFooter`.
**Classes:** `.sc-card`, `__header` (`--center`), `__title`, `__description`, `__action`, `__content`
(`--chart`), `__footer`; `.sc-card--split` (login-04, no padding).

```html
<section class="sc-card"><div class="sc-card__header"><h3 class="sc-card__title">Create project</h3><p class="sc-card__description">Deploy your new project in one click.</p><div class="sc-card__action"><button class="sc-btn sc-btn--link sc-btn--sm" type="button">Docs</button></div></div>
  <div class="sc-card__content">…</div><div class="sc-card__footer sc-row--between">…</div></section>
```

## 6. Section cards (dashboard-01)

**React:** `SectionCards`: `Card` with `CardDescription`, `CardTitle className="text-2xl … @[250px]/card:text-3xl"`,
`CardAction` + `Badge variant="outline"`, `CardFooter className="flex-col items-start gap-1.5"`. Gradient
`from-primary/5 to-card` in light, plain card in dark. **Classes:** `.sc-stats` (1 / 2 / 4 columns by main width),
`.sc-stat`, `__value`, `__trend`, `__hint`.

```html
<div class="sc-stats"><section class="sc-card sc-stat" aria-label="Issues closed">
  <div class="sc-card__header"><p class="sc-card__description">Issues closed</p><p class="sc-card__title sc-stat__value">1,284</p><div class="sc-card__action"><span class="sc-badge sc-badge--outline">…+12.5%</span></div></div>
  <div class="sc-card__footer"><p class="sc-stat__trend">Trending up this cycle …icon…</p><p class="sc-stat__hint">Closed in the last 30 days</p></div>
</section></div>
```

## 7. Chart (ChartAreaInteractive)

**React:** `ChartContainer`, `AreaChart`, `CartesianGrid vertical={false}`, `XAxis`, `ChartTooltip` +
`ChartTooltipContent indicator="dot"`, `ChartLegend` + `ChartLegendContent`, series colours `var(--chart-n)`.
**Classes:** `.sc-chart-wrap` > `svg.sc-chart` (250px, `preserveAspectRatio="none"`): `.sc-chart__grid`,
`.sc-chart__area` (gradient `stop.sc-stop-1|2`), `.sc-chart__line--1|2`; `.sc-chart-tooltip` (`__label`, `.sc-dot--1|2`),
`.sc-chart-x`; `.sc-legend` (`__item`, `__swatch--1…5`); range picker `.sc-toggle-group` / mobile `.sc-select-trigger`.

```html
<div class="sc-card__content sc-card__content--chart"><div class="sc-chart-wrap">
  <svg class="sc-chart" viewBox="0 0 800 250" preserveAspectRatio="none" role="img" aria-label="Issues closed, last 3 months …"><line class="sc-chart__grid" x1="0" x2="800" y1="125" y2="125"/><path class="sc-chart__line sc-chart__line--1" d="M0,120 C…"/></svg>
  <div class="sc-chart-x" aria-hidden="true"><span>Jul 14</span><span>Oct 6</span></div></div>
  <div class="sc-legend"><span class="sc-legend__item"><i class="sc-legend__swatch sc-legend__swatch--1"></i>Bugs</span></div></div>
```
Do give every chart an `aria-label` that states the data; don't use more than five series.

## 8. Table and data table

**React:** `Table`, `TableHeader`, `TableBody`, `TableFooter`, `TableRow`, `TableHead`, `TableCell`, `TableCaption`
with TanStack Table (`DataTableColumnHeader`, `DataTableToolbar`, `DataTableFacetedFilter`, `DataTableViewOptions`,
`DataTablePagination`). **Classes:** `.sc-table-wrap` (`--lg`), `.sc-table` (`--dash`: muted sticky head), cells
`.is-check`, `.is-drag` (+ `.sc-drag`), `.is-right`, `.is-actions`, `.is-wrap`; `tr[data-state="selected"]`;
`.sc-sort` (`.is-sorted`), `.sc-cell-link`, `.sc-cell-main`, `.sc-cell-sub`, `.sc-cell-user`, `.sc-status`
(`--done` …), `.sc-key`, `.sc-input--inline` (editable cell).

```html
<div class="sc-table-wrap"><table class="sc-table">
  <thead><tr><th class="is-check"><input class="sc-checkbox" type="checkbox" aria-label="Select all"></th><th><button class="sc-sort is-sorted" type="button">Title<svg class="sc-icon">…arrow-up…</svg></button></th><th>Status</th></tr></thead>
  <tbody><tr data-state="selected"><td class="is-check"><input class="sc-checkbox" type="checkbox" checked aria-label="Select ORB-1294"></td><td class="is-wrap"><div class="sc-row"><span class="sc-badge sc-badge--outline">Feature</span><a class="sc-cell-main sc-truncate" href="#">Offline sync for field notes</a></div></td><td><div class="sc-status"><svg class="sc-icon">…timer…</svg><span>In Progress</span></div></td></tr></tbody>
</table></div>
```

## 9. Data-table toolbar, faceted filter, action bar

**Classes:** `.sc-toolbar` (`__left`, `__right`), `.sc-input--filter`, `.sc-facet` (`__sep`, `__count`, `__chips`),
`.sc-action-bar` (`__count`) for the floating selection bar.

```html
<div class="sc-toolbar"><div class="sc-toolbar__left"><input class="sc-input sc-input--sm sc-input--filter" type="search" placeholder="Filter issues..." aria-label="Filter issues">
  <button class="sc-btn sc-btn--outline sc-btn--sm sc-facet" type="button"><svg class="sc-icon">…circle-plus…</svg>Status<span class="sc-facet__sep" aria-hidden="true"></span><span class="sc-badge sc-badge--secondary sc-badge--square sc-facet__count">2</span><span class="sc-facet__chips"><span class="sc-badge sc-badge--secondary sc-badge--square">In Progress</span></span></button></div></div>
<div class="sc-action-bar" role="toolbar" aria-label="Bulk actions"><span class="sc-action-bar__count">2 selected</span><button class="sc-btn sc-btn--secondary sc-btn--sm" type="button">Export</button></div>
```

## 10. Pagination

**React:** `DataTablePagination` (summary, rows per page `Select size="sm"`, "Page 1 of 13", icon buttons) and
`Pagination` / `PaginationLink` / `PaginationPrevious` / `PaginationNext` / `PaginationEllipsis`.
**Classes:** `.sc-pagination` (`__summary`, `__controls`, `__rows`, `__page`, `__buttons`), `.sc-btn--edge` (first /
last, hidden below lg); `.sc-pager` (`__edge`, `__ellipsis`).

```html
<div class="sc-pagination"><p class="sc-pagination__summary">2 of 128 row(s) selected.</p><div class="sc-pagination__controls"><p class="sc-pagination__page">Page 1 of 13</p><div class="sc-pagination__buttons"><button class="sc-btn sc-btn--outline sc-btn--icon-sm is-disabled" type="button" aria-label="Go to previous page">…</button><button class="sc-btn sc-btn--outline sc-btn--icon-sm" type="button" aria-label="Go to next page">…</button></div></div></div>
```

## 11. Field, Label, Input, Textarea

**React:** `Field`, `FieldLabel`, `FieldDescription`, `FieldError`, `FieldGroup`, `FieldSet`, `FieldLegend`,
`FieldSeparator`, `FieldContent`, `Input`, `Textarea`, `Label`. **Classes:** `.sc-field-group` (`--inner`),
`.sc-field-set`, `.sc-field-legend`, `.sc-field` (`--horizontal`, `--card`, `.is-invalid`, `.is-disabled`),
`.sc-field__content`, `.sc-label`, `.sc-required`, `.sc-field__description` (`--tight`), `.sc-field__error`,
`.sc-field-separator` (`--card`), `.sc-input` (`--sm`, `.is-focused`), `.sc-textarea`; `.sc-form` (`--loose` =
FormItem spacing of the forms example), `.sc-form-grid`, `.sc-form-page`, `.sc-form-actions`.

```html
<div class="sc-field is-invalid"><label class="sc-label" for="title">Title <span class="sc-required" aria-hidden="true">*</span></label><input class="sc-input" id="title" value="Sync" aria-invalid="true" aria-describedby="title-err"><p class="sc-field__error" id="title-err">Title must be at least 8 characters.</p></div>
<div class="sc-field-separator sc-field-separator--card"><span>Or continue with</span></div>
```

## 12. Select, NativeSelect, date picker

**React:** `Select` + `SelectTrigger size="sm|default"` + `SelectContent` + `SelectItem`; `NativeSelect`; date
picker = `Popover` + `Button variant="outline"` + `Calendar`. **Classes:** `.sc-select-trigger` (`--sm`,
`--placeholder`), `.sc-select` (native), `.sc-date-btn`; an open list is `.sc-menu` with `role="listbox"`.

```html
<button class="sc-select-trigger sc-w-full" type="button" aria-haspopup="listbox"><span class="sc-row"><svg class="sc-icon sc-muted">…</svg>Atlas Mobile</span><svg class="sc-icon">…chevron-down…</svg></button>
<select class="sc-select" id="status"><option>Todo</option></select>
<button class="sc-btn sc-btn--outline sc-date-btn" type="button"><svg class="sc-icon">…calendar…</svg><span>October 19th, 2026</span></button>
```

## 13. InputGroup

**React:** `InputGroup`, `InputGroupAddon align="inline-start|inline-end"`, `InputGroupInput`.

```html
<div class="sc-input-group"><input class="sc-input-group__control" id="estimate" value="8"><span class="sc-input-group__addon">points</span></div>
<div class="sc-input-group"><span class="sc-input-group__addon">https://</span><input class="sc-input-group__control" value="orbit.dev/halcyon"></div>
```

## 14. Checkbox, RadioGroup, choice cards, Switch

**React:** `Checkbox`, `RadioGroup` + `RadioGroupItem`, `FieldLabel` wrapping a `Field` (choice card), `Switch`.
**Classes:** `.sc-checkbox`, `.sc-radio`, `.sc-check-row` (`__meta`), `.sc-check-group`, `.sc-choice-cards`,
`.sc-choice-card` (`__body`), `.sc-switch`.

```html
<label class="sc-check-row"><input class="sc-checkbox" type="checkbox" checked><span class="sc-check-row__meta"><span class="sc-label">Add to cycle 42</span><span class="sc-field__description">31 of 40 points planned</span></span></label>
<label class="sc-choice-card"><input class="sc-radio" type="radio" name="prio" checked><span class="sc-choice-card__body"><strong>High</strong><small>Before the release</small></span></label>
<label class="sc-field sc-field--horizontal sc-field--card"><span class="sc-field__content"><span class="sc-label">Notify subscribers</span><span class="sc-field__description">Email the 6 watchers.</span></span><input class="sc-switch" type="checkbox" role="switch" checked></label>
```

## 15. Tag input, drop zone, file row (compositions)

Badges in an input frame (`.sc-tag-input`, `__placeholder`), dashed upload area (`.sc-dropzone`), attached file
(`.sc-file-row`, `__name`, `__meta`). Not registry primitives: build from `Badge`, `Input type="file"`, `Button`.

```html
<div class="sc-tag-input"><span class="sc-badge sc-badge--secondary sc-badge--removable">mobile</span><span class="sc-tag-input__placeholder">Add label…</span></div>
<div class="sc-dropzone"><svg class="sc-icon">…upload…</svg><p><strong>Drop files here</strong> or <a href="#">browse</a></p></div>
```

## 16. Tabs and ToggleGroup

**React:** `Tabs`, `TabsList variant="default|line"`, `TabsTrigger`; `ToggleGroup type="single" variant="outline"`
+ `ToggleGroupItem`. **Classes:** `.sc-tabs`, `.sc-tabs__list` (`--line`), `.sc-tabs__trigger.is-active`;
`.sc-toggle-group` (shown from md; `--show` always), `__item.is-active`.

```html
<div class="sc-tabs__list" role="tablist" aria-label="Views"><button class="sc-tabs__trigger is-active" role="tab" aria-selected="true" type="button">All issues</button><button class="sc-tabs__trigger" role="tab" aria-selected="false" type="button">In review <span class="sc-badge sc-badge--secondary sc-badge--count">3</span></button></div>
<div class="sc-toggle-group" role="group" aria-label="Time range"><button class="sc-toggle-group__item is-active" type="button" aria-pressed="true">Last 3 months</button><button class="sc-toggle-group__item" type="button" aria-pressed="false">Last 30 days</button></div>
```

## 17. Breadcrumb

**React:** `Breadcrumb`, `BreadcrumbList`, `BreadcrumbItem`, `BreadcrumbLink`, `BreadcrumbPage`, `BreadcrumbSeparator`.

```html
<nav aria-label="Breadcrumb"><ol class="sc-breadcrumb"><li><a href="#">Halcyon Labs</a></li><li aria-hidden="true"><svg class="sc-icon">…chevron-right…</svg></li><li><span aria-current="page">ORB-1294</span></li></ol></nav>
```

## 18. DropdownMenu

**React:** `DropdownMenu`, `DropdownMenuContent`, `DropdownMenuLabel`, `DropdownMenuItem variant="destructive"`,
`DropdownMenuCheckboxItem`, `DropdownMenuSeparator`, `DropdownMenuShortcut`. **Classes:** `.sc-anchor`
(`--end`) > trigger + `.sc-menu` (`--up`, `--static`), `__label` (`--muted`), `__item` (`.is-focused`, `--inset`,
`--destructive`, `--team`), `__check`, `__shortcut`, `__separator`, `__user`; `.sc-team-mini` (`--add`).

```html
<div class="sc-anchor sc-anchor--end"><button class="sc-btn sc-btn--outline sc-btn--sm is-open" type="button" aria-haspopup="menu" aria-expanded="true"><svg class="sc-icon">…settings-2…</svg>View</button>
  <div class="sc-menu sc-w-40" role="menu"><p class="sc-menu__label">Toggle columns</p><div class="sc-menu__separator" role="separator"></div>
    <button class="sc-menu__item sc-menu__item--inset" role="menuitemcheckbox" aria-checked="true" type="button"><span class="sc-menu__check"><svg class="sc-icon">…check…</svg></span>Status</button></div></div>
```

## 19. Dialog and AlertDialog

**React:** `Dialog`, `DialogContent`, `DialogHeader`, `DialogTitle`, `DialogDescription`, `DialogFooter`;
`AlertDialog` (`size="default|sm"`), `AlertDialogMedia`, `AlertDialogAction`, `AlertDialogCancel`.
**Classes:** `.sc-overlay` (`--nested`, `--static`), `.sc-dialog` (`--static`, `--alert`, `--sm`), `__header`,
`__title`, `__description`, `__footer`, `__close`; `.sc-alert-dialog__header`, `__media`.

```html
<div class="sc-overlay" aria-hidden="true"></div>
<div class="sc-dialog" role="dialog" aria-modal="true" aria-labelledby="t"><div class="sc-dialog__header"><h2 class="sc-dialog__title" id="t">Invite to Halcyon Labs</h2><p class="sc-dialog__description">They get an email with a link.</p></div>…<div class="sc-dialog__footer"><button class="sc-btn sc-btn--outline" type="button">Cancel</button><button class="sc-btn" type="button">Send invites</button></div><button class="sc-dialog__close" type="button" aria-label="Close">…x…</button></div>
```

## 20. Sheet

**React:** `Sheet`, `SheetContent side="right"`, `SheetHeader`, `SheetTitle`, `SheetDescription`, `SheetFooter`
(stacked buttons). **Classes:** `.sc-sheet` (`__header`, `__title`, `__description`, `__body`, `__footer`),
`.sc-activity` (`__list`) for an activity feed inside.

```html
<div class="sc-overlay" aria-hidden="true"></div>
<div class="sc-sheet" role="dialog" aria-modal="true" aria-labelledby="sh"><div class="sc-sheet__header"><h2 class="sc-sheet__title" id="sh">Edit issue</h2><p class="sc-sheet__description">Make changes to this issue.</p></div><div class="sc-sheet__body">…fields…</div><div class="sc-sheet__footer"><button class="sc-btn" type="button">Save changes</button><button class="sc-btn sc-btn--outline" type="button">Cancel</button></div></div>
```

## 21. Popover, Tooltip, Kbd

**React:** `Popover` + `PopoverContent` (w-72 p-4), `Tooltip` + `TooltipContent` (bg-foreground, arrow), `Kbd`.
**Classes:** `.sc-popover` (`--static`, `__grid`), `.sc-tooltip` (+ `.sc-tooltip-demo`), `.sc-kbd` (`--tooltip`).

```html
<span class="sc-tooltip" role="tooltip">Add to library <kbd class="sc-kbd sc-kbd--tooltip">⌘L</kbd></span>
<div class="sc-popover sc-popover--static"><p class="sc-font-medium">Dimensions</p><div class="sc-popover__grid"><label class="sc-label" for="w">Width</label><input class="sc-input sc-input--sm" id="w" value="100%"></div></div>
```

## 22. Command

**React:** `Command`, `CommandInput`, `CommandList`, `CommandGroup`, `CommandItem`, `CommandSeparator`,
`CommandShortcut`, `CommandDialog`. **Classes:** `.sc-command` (`--static`), `__input-wrap`, `__input`, `__list`,
`__group`, `__heading`, `__separator`; items reuse `.sc-menu__item` and `.sc-menu__shortcut`.

```html
<div class="sc-command sc-command--static" role="dialog" aria-label="Command menu"><div class="sc-command__input-wrap"><svg class="sc-icon">…search…</svg><input class="sc-command__input" aria-label="Search" placeholder="Type a command or search..."></div>
  <div class="sc-command__list"><div class="sc-command__group"><p class="sc-command__heading">Suggestions</p><div class="sc-menu__item is-focused">Go to Cycle 42<span class="sc-menu__shortcut">G C</span></div></div></div></div>
```

## 23. Toast (Sonner) and Alert

**React:** `Toaster` (`components/ui/sonner.tsx`) + `toast()`; `Alert variant="default|destructive"`, `AlertTitle`,
`AlertDescription`. **Classes:** `.sc-toaster` (bottom-right), `.sc-toast` (`__body`, `__title`, `__description`,
`__action`); `.sc-alert` (`--destructive`, `__title`, `__description`).

```html
<ol class="sc-toaster" aria-label="Notifications"><li class="sc-toast"><svg class="sc-icon">…circle-check…</svg><div class="sc-toast__body"><p class="sc-toast__title">Issue ORB-1294 moved</p><p class="sc-toast__description">In Progress → In Review</p></div><button class="sc-btn sc-btn--xs sc-toast__action" type="button">Undo</button></li></ol>
<div class="sc-alert sc-alert--destructive" role="alert"><svg class="sc-icon">…circle-alert…</svg><p class="sc-alert__title">Unable to process your payment.</p><div class="sc-alert__description"><p>Please verify your billing information.</p></div></div>
```

## 24. Avatar, Separator, Progress, Skeleton

**React:** `Avatar` + `AvatarFallback` (+ `AvatarGroup`), `Separator`, `Progress`, `Skeleton`. **Classes:**
`.sc-avatar` (`--xs`, `--sm`, `--lg`, `--rounded`, `--dashed`, `--more`), `.sc-avatar-group`, `.sc-separator`
(`--vertical`), `.sc-progress` + `__bar` (+ `.sc-progress-row`), `.sc-skeleton` (`--circle`, `--48`, `--line`, `--short`).

```html
<div class="sc-avatar-group"><span class="sc-avatar">KW</span><span class="sc-avatar">AN</span><span class="sc-avatar sc-avatar--more">+2</span></div>
<div class="sc-progress" role="progressbar" aria-label="Cycle progress" aria-valuenow="62" aria-valuemin="0" aria-valuemax="100"><div class="sc-progress__bar" style="width:62%"></div></div>
```

## 25. Empty

**React:** `Empty`, `EmptyHeader`, `EmptyMedia variant="icon"`, `EmptyTitle`, `EmptyDescription`, `EmptyContent`.
**Classes:** `.sc-empty` (`--plain` without the dashed border), `__header`, `__media` (`--icon`), `__title`,
`__description`, `__content`.

```html
<div class="sc-empty"><div class="sc-empty__header"><div class="sc-empty__media sc-empty__media--icon"><svg class="sc-icon">…inbox…</svg></div><h2 class="sc-empty__title">No issues in this cycle yet</h2><p class="sc-empty__description">Pull issues from the backlog or create one.</p></div><div class="sc-empty__content"><div class="sc-btn-group"><a class="sc-btn" href="#">Create issue</a></div></div></div>
```

## 26. Accordion

**React:** `Accordion type="single" collapsible`, `AccordionItem`, `AccordionTrigger`, `AccordionContent`.

```html
<div class="sc-accordion"><div class="sc-accordion__item is-open"><h3><button class="sc-accordion__trigger" type="button" aria-expanded="true">Can I import from another tracker?<svg class="sc-icon">…chevron-down…</svg></button></h3><div class="sc-accordion__content"><p>Yes. Orbit imports issues, comments and attachments.</p></div></div></div>
```

## 27. Auth blocks (login-03, login-04)

**Classes:** `.sc-auth` (bg-muted page), `.sc-auth__wrap` (`--wide`), `__brand`, `__logo`, `__split`, `__pane`,
`__intro`, `__title`, `__art` (+ `.sc-art__*`), `__quote`; `.sc-link-muted`; fields drawn with `.mock-label` /
`.mock-field` (`--secret`, `--focus`, `--invalid`, `--empty`).

```html
<div class="sc-field"><span class="mock-label">Email</span><div class="mock-field" role="img" aria-label="Email field, filled in">sofia@halcyonlabs.io</div></div>
<div class="sc-field"><div class="sc-row"><span class="mock-label">Password</span><a class="sc-link-muted sc-ml-auto" href="#">Forgot your password?</a></div><div class="mock-field mock-field--secret" role="img" aria-label="Password field, filled in">••••••••••••</div></div>
<a class="sc-btn sc-w-full" href="dashboard.html" role="button">Login</a>
```

## 28. Kanban, settings, landing (compositions)

Kanban: `.sc-kanban` > `section.sc-kanban__col` > `.sc-kanban__head` + `.sc-kanban__cards` > `article.sc-card.sc-kanban__card`
(`.is-dragging`, `__title`, `__meta`). Settings (forms example): `.sc-settings-page` (`__head`, `__title`),
`.sc-settings` > `.sc-settings__aside` > `nav.sc-settings__nav` > `.sc-btn.sc-btn--ghost.sc-settings__link.is-active` +
`.sc-settings__panel`. Landing: `.sc-site`, `.sc-site-header` (`__inner`), `.sc-site-brand`, `.sc-site-nav`,
`.sc-container` (`--narrow`), `.sc-hero` (`__inner`, `__title`, `__lead`), `.sc-announce`, `.sc-shot` (`__bar`,
`__body`, `__side`, `__nav`, `__main`, `__list`, `__row`), `.sc-logos` (`__row`, `__item`), `.sc-section`
(`--muted`, `--tight`, `__head`, `__title`, `__lead`), `.sc-features` + `.sc-feature` (`__icon`), `.sc-plans` +
`.sc-plan` (`--featured`, `__price`, `__list`), `.sc-testimonial`, `.sc-cta`, `.sc-site-footer` (`__grid`, `__col`,
`__bottom`).

```html
<article class="sc-card sc-kanban__card"><div class="sc-row sc-row--between"><span class="sc-key">ORB-1294</span><span class="sc-avatar sc-avatar--xs">KW</span></div><h3 class="sc-kanban__title"><a href="#">Offline sync for field notes</a></h3><div class="sc-kanban__meta"><span class="sc-status">…arrow-up…High</span><span class="sc-status">…calendar…Oct 16</span></div></article>
```

## Utilities

`.sc-muted`, `.sc-text-xs|sm|lg|xl`, `.sc-text-center`, `.sc-text-destructive`, `.sc-font-medium|semibold`,
`.sc-tabular`, `.sc-truncate`, `.sc-key`, `.sc-row` (`--between`, `--wrap`, `--tight`), `.sc-stack` (`--tight`),
`.sc-grid-2`, `.sc-grid-3` (`--keep`), `.sc-form-grid--keep`, `.sc-flex-1`, `.sc-ml-auto`, `.sc-my-6`, `.sc-px-6`,
widths `.sc-w-full|18|20|32|38|40|48|56` (Tailwind `w-*`), `.sc-hidden-mobile`, `.sc-only-mobile`,
`.sc-only-mobile-flex` (md = 768px), `.sc-page-header`, `.sc-page-title`, `.sc-page-desc`, `.visually-hidden`,
`.sc-skip-link`. Catalog only: `.sc-gallery*`, `.sc-catalog*`, `.sc-swatch*`, `.sc-demo-row`, `.sc-select-demo`,
`.sc-sidebar-demo` (`__menu`), `.sc-tooltip-demo`.
