# Material Design 3 — components

Every class lives in `tokens.css`; `html/components.html` shows each component in its variants and states.
Demo states: `is-hover`, `is-focus`, `is-pressed`, `is-dragged` (static state layers), `disabled` attribute or
`is-disabled`. Selected/current states use the real ARIA attribute where one exists (`aria-current="page"`,
`aria-pressed`, `aria-selected`, `aria-checked`). Icons are Material Symbols Outlined inline SVG with
`class="md3-icon" aria-hidden="true" fill="currentColor" viewBox="0 -960 960 960"`; below they are written as
`<svg class="md3-icon">…</svg>`.

Mapping columns: **NG** = Angular Material 18+ with an M3 theme (`matButton="…"` syntax from v20), **VU** = Vuetify 3,
**MW** = Material Web 2.x (`labs` = unstable).

---

## 1. App shell

**Purpose** — the window: navigation, top app bar, content, phone navigation bar.
**Anatomy** — `body.md3-mockup` › `.md3-app` (+ `md3-app--drawer` or `md3-app--rail`, + `md3-app--with-navbar` when a
`.md3-navbar` is present) › `.md3-drawer` | `.md3-rail` › `.md3-main` › `.md3-top-app-bar` + `main.md3-content`
(`--narrow` max 960 px, `--center`) › optional `.md3-fab--anchored.md3-compact-only` + `.md3-navbar`.
**Breakpoint** — ≥ 840 px shows drawer/rail and hides `.md3-compact-only`; < 840 px hides drawer/rail and
`.md3-expanded-only`, shows the navigation bar.

```html
<body class="md3-mockup">
<div class="md3-app md3-app--rail md3-app--with-navbar">
  <nav class="md3-rail" aria-label="Main">…</nav>
  <div class="md3-main">
    <header class="md3-top-app-bar">
      <a class="md3-icon-btn md3-icon-btn--on-surface md3-compact-only" href="#" aria-label="Open navigation menu"><svg class="md3-icon">…menu…</svg></a>
      <h1 class="md3-top-app-bar__title">Jobs</h1>
      <div class="md3-top-app-bar__actions">…</div>
    </header>
    <main class="md3-content">…</main>
  </div>
  <a class="md3-fab md3-fab--anchored md3-compact-only" href="#" aria-label="New job"><svg class="md3-icon">…add…</svg></a>
  <nav class="md3-navbar" aria-label="Main (compact)">…</nav>
</div>
</body>
```
**Maps to** — NG `<mat-sidenav-container>`; VU `<v-app>` + `<v-main>`; MW plain layout.
**Do** keep the drawer or rail and the navigation bar showing the same destinations. **Don’t** show both a drawer and a rail.

## 2. Top app bar — small, center-aligned, medium, scrolled

**Purpose** — screen title and the screen’s actions. 64 dp small, 112 dp medium (title on a second row,
headline-small), `--center` centres the title, `--scrolled` switches to surface-container when content scrolls under it.
**Anatomy** — `.md3-top-app-bar` › leading `.md3-icon-btn--on-surface` (menu / back / close), `.md3-top-app-bar__title`
(title-large), optional `.md3-top-app-bar__search` (a `.md3-search--compact`), `.md3-top-app-bar__actions`.
**Maps to** — NG `<mat-toolbar>`; VU `<v-app-bar>` / `<v-toolbar>`; MW none (spec layout).
**Do** use a close icon + text “Save” for full-screen create flows on phones. **Don’t** put more than three actions; overflow the rest into a menu.

## 3. Navigation drawer (standard)

**Purpose** — primary destinations on expanded windows. 360 dp, surface-container-low, corner-large-end, 56 dp pill items,
secondary-container active indicator, label-large.
**Anatomy** — `nav.md3-drawer` › `.md3-drawer__brand` (+ `.md3-brand-mark`), optional `.md3-drawer__fab` (extended FAB),
`ul.md3-drawer__list` › `a.md3-drawer__item[aria-current="page"]` › icon + `.md3-drawer__label` + `.md3-drawer__badge`;
`.md3-drawer__headline` (section title), `.md3-drawer__divider`, `.md3-drawer__dot(--tertiary|--error)`, `.md3-drawer__spacer`.

```html
<nav class="md3-drawer" aria-label="Main">
  <a class="md3-drawer__brand" href="#"><span class="md3-brand-mark"><svg class="md3-icon">…grass…</svg></span>Meadow Field</a>
  <a class="md3-fab md3-fab--extended md3-fab--lowered md3-drawer__fab" href="#"><svg class="md3-icon">…add…</svg>New job</a>
  <ul class="md3-drawer__list">
    <li><a class="md3-drawer__item" href="#" aria-current="page"><svg class="md3-icon">…space_dashboard-fill…</svg><span class="md3-drawer__label">Dashboard</span></a></li>
    <li><a class="md3-drawer__item" href="#"><svg class="md3-icon">…home_repair_service…</svg><span class="md3-drawer__label">Jobs</span><span class="md3-drawer__badge">38</span></a></li>
  </ul>
</nav>
```
**Maps to** — NG `<mat-sidenav mode="side" opened>` + `<mat-nav-list>` / `<a mat-list-item [activated]="true">`;
VU `<v-navigation-drawer>` + `<v-list nav>` (`:active`); MW labs `<md-navigation-drawer>`.
**Do** use the filled icon for the active item. **Don’t** use the drawer on phones (navigation bar instead).

## 4. Navigation rail

**Purpose** — 3–7 destinations on medium/expanded windows, 80 dp wide; menu button and FAB on top.
**Anatomy** — `nav.md3-rail` › `.md3-icon-btn` (menu), `.md3-fab.md3-rail__fab`, `ul.md3-rail__list` ›
`a.md3-rail__item[aria-current="page"]` › `.md3-rail__indicator` (56×32, holds the icon) + label (label-medium); `.md3-rail__spacer`.
**Maps to** — NG custom (no rail component); VU `<v-navigation-drawer rail>`; MW none.
**Don’t** drop the labels: M3 rails keep a label under every icon.

## 5. Navigation bar

**Purpose** — 3–5 destinations on phones. 80 dp, surface-container, 64×32 indicator, label-medium, fixed at the bottom.
**Anatomy** — `nav.md3-navbar` › `a.md3-navbar__item[aria-current="page"]` › `.md3-navbar__indicator` (icon +
optional `.md3-badge--anchored`) + label.

```html
<nav class="md3-navbar" aria-label="Main (compact)">
  <a class="md3-navbar__item" href="#" aria-current="page"><span class="md3-navbar__indicator"><svg class="md3-icon">…space_dashboard-fill…</svg></span>Home</a>
  <a class="md3-navbar__item" href="#"><span class="md3-navbar__indicator"><svg class="md3-icon">…home_repair_service…</svg><span class="md3-badge md3-badge--anchored">3</span></span>Jobs</a>
</nav>
```
**Maps to** — NG custom; VU `<v-bottom-navigation>`; MW labs `<md-navigation-bar>` + `<md-navigation-tab>`.

## 6. Floating action button

**Purpose** — the screen’s primary constructive action. 56 dp corner-large primary-container level 3; `--small` 40 dp,
`--large` 96 dp (36 dp icon), `--extended` 56 dp with label, `--lowered` level 1 (inside drawer/rail),
colours `--secondary`, `--tertiary`, `--surface`; `--anchored` fixes it above the navigation bar on phones.
**Anatomy** — `.md3-fab` › `.md3-icon` (+ label text for extended); always `aria-label` when icon-only.
**Maps to** — NG `<button matFab>`, `matMiniFab`, `matFab extended`; VU `<v-fab>` / `<v-btn icon size="x-large">`;
MW `<md-fab size="small|medium|large" variant="primary|secondary|tertiary|surface" label lowered>`.
**Do** one FAB per screen. **Don’t** use a FAB for destructive or minor actions.

## 7. Common buttons

**Purpose** — actions. 40 dp, corner-full, label-large, 18 dp icon. Padding 24 (16 with a leading icon); text
buttons 12 (12/16 with icon).
**Variants** — `--filled` (primary), `--tonal` (secondary-container), `--elevated` (surface-container-low, level 1),
`--outlined` (1 dp outline), `--text`, `--error` (destructive filled), `--text-error` (error label), `--icon`
(leading icon), `--trailing-icon`, `--block`. **States** — hover (state layer 8 % + level 1 on filled/tonal),
focus (3 dp secondary ring, 2 dp offset), pressed 12 %, disabled (12 % container, 38 % label).

```html
<a class="md3-btn md3-btn--filled md3-btn--icon" href="#"><svg class="md3-icon">…add…</svg>New job</a>
<a class="md3-btn md3-btn--tonal" href="#">Call customer</a>
<a class="md3-btn md3-btn--outlined" href="#">Save draft</a>
<a class="md3-btn md3-btn--text" href="#">Cancel</a>
<button class="md3-btn md3-btn--filled" type="button" disabled>Create job</button>
```
**Maps to** — NG `matButton="filled|tonal|elevated|outlined"` and `matButton` (text) — before v20 `mat-flat-button`,
`mat-raised-button`, `mat-stroked-button`, `mat-button`; VU `<v-btn variant="flat|tonal|elevated|outlined|text">`;
MW `<md-filled-button>`, `<md-filled-tonal-button>`, `<md-elevated-button>`, `<md-outlined-button>`, `<md-text-button>`.
**Do** one filled button per view, right-most in a button row. **Don’t** use elevated buttons on elevated surfaces.

## 8. Icon buttons

**Purpose** — compact actions. 40 dp target, 24 dp icon, on-surface-variant. Variants `--filled`, `--tonal`,
`--outlined`, `--on-surface` (leading navigation icons), toggle `.is-selected` (primary; outlined selected = inverse-surface).
**Anatomy** — `.md3-icon-btn[aria-label]` › `.md3-icon` (+ `.md3-badge--anchored`).
**Maps to** — NG `<button matIconButton>` (v20) / `mat-icon-button`; VU `<v-btn icon variant="text">`;
MW `<md-icon-button>`, `<md-filled-icon-button>`, `<md-filled-tonal-icon-button>`, `<md-outlined-icon-button toggle selected>`.
**Don’t** ship an icon button without `aria-label`.

## 9. Segmented buttons

**Purpose** — 2–5 related options (view, period, units). 40 dp, 1 dp outline, corner-full, selected secondary-container
with an 18 dp check. Equal-width segments; `--block` fills the width.

```html
<div class="md3-segmented" role="group" aria-label="Period">
  <button class="md3-segmented__btn" type="button" aria-pressed="true"><svg class="md3-icon">…check…</svg>Today</button>
  <button class="md3-segmented__btn" type="button" aria-pressed="false">Week</button>
  <button class="md3-segmented__btn" type="button" aria-pressed="false">Month</button>
</div>
```
**Maps to** — NG `<mat-button-toggle-group>`; VU `<v-btn-toggle divided variant="outlined" rounded="xl">`;
MW labs `<md-outlined-segmented-button-set>` + `<md-outlined-segmented-button>`.

## 10. Chips — assist, filter, input, suggestion

**Purpose** — compact actions, filters and entered values. 32 dp, corner-small, 1 dp outline, label-large, 18 dp icons.
**Anatomy** — `.md3-chip-set` › `.md3-chip` + type `--assist` (primary icon), `--filter`
(`aria-pressed="true"` → secondary-container + check), `--input` (removable value; `--avatar` with a 24 dp
`.md3-avatar--sm`; `.md3-chip__remove` link), `--suggestion`; spacing `--leading` / `--trailing` when an icon is
present; `--elevated` (level 1, no outline); `.md3-chip__count`.

```html
<div class="md3-chip-set" role="group" aria-label="Filters">
  <button class="md3-chip md3-chip--filter md3-chip--leading" type="button" aria-pressed="true"><svg class="md3-icon">…check…</svg>This week</button>
  <button class="md3-chip md3-chip--filter" type="button" aria-pressed="false">Overdue <span class="md3-chip__count">3</span></button>
  <button class="md3-chip md3-chip--filter md3-chip--trailing" type="button" aria-pressed="false">Technician<svg class="md3-icon">…arrow_drop_down…</svg></button>
  <span class="md3-chip md3-chip--input">Condensate pump<a class="md3-chip__remove" href="#" aria-label="Remove"><svg class="md3-icon">…close…</svg></a></span>
</div>
```
**Maps to** — NG `<mat-chip-set>`/`<mat-chip>`, `<mat-chip-listbox>`/`<mat-chip-option>` (filter),
`<mat-chip-grid>`/`<mat-chip-row>` (input); VU `<v-chip variant="outlined" filter closable>` in `<v-chip-group>`;
MW `<md-chip-set>` with `<md-assist-chip>`, `<md-filter-chip selected>`, `<md-input-chip>`, `<md-suggestion-chip>`.

## 11. Status chip and priority (composite)

**Purpose** — job status in tables and lists, always icon + label. 24 dp, corner-small, label-medium, 16 dp icon.
`.md3-status--scheduled` (outline-variant ring), `--enroute` (tertiary-container), `--progress` (primary-container),
`--completed` (surface-container-highest), `--overdue` (error-container). `.md3-priority` (+ `--high` in error colour)
with `keyboard_double_arrow_up / drag_handle / keyboard_double_arrow_down`.

```html
<span class="md3-status md3-status--progress"><svg class="md3-icon">…build…</svg>In progress</span>
<span class="md3-status md3-status--overdue"><svg class="md3-icon">…assignment_late…</svg>Overdue</span>
<span class="md3-priority md3-priority--high"><svg class="md3-icon">…keyboard_double_arrow_up…</svg>High</span>
```
**Maps to** — NG `<mat-chip disableRipple>` with a role-coloured class; VU `<v-chip size="small" color="…" variant="tonal">`;
MW `<md-assist-chip>` styled with tokens. **Don’t** rely on colour alone.

## 12. Badge

**Purpose** — counts and dots on icons. Small 6 dp dot, large 16 dp (label-small), error / on-error.
`.md3-badge`, `--small`, `--anchored` (over an icon inside an icon button or navigation indicator).
**Maps to** — NG `matBadge="3"` (`matBadgeSize="small"`); VU `<v-badge content="3">` / `dot`; MW labs `<md-badge value="3">`.

## 13. Avatar

**Purpose** — people and accounts. 40 dp monogram on primary-container (list leading-avatar token); `--secondary`,
`--tertiary`, `--inverse` (primary); sizes `--sm` 24, `--md` 32, `--lg` 56; `.md3-avatar__presence` adds a status dot.
**Maps to** — NG custom; VU `<v-avatar color="primary-container">`; MW custom. Use `role="img"` + `aria-label` when standalone.

## 14. Cards — elevated, filled, outlined

**Purpose** — group one subject. corner-medium (12 dp), 16 dp padding.
`.md3-card--elevated` (surface-container-low, level 1), `--filled` (surface-container-highest), `--outlined`
(surface + outline-variant), `--container` (surface-container-low, no shadow), `--highlight` (primary-container),
`--flush` (no padding, for lists/tables), `--xl` (28 dp corners, 24 dp padding — marketing), `--interactive` (whole card is a link).
**Anatomy** — `.md3-card__header` (+ `--pad`), `.md3-card__titles`, `.md3-card__title` (`--large`), `.md3-card__subtitle`,
`.md3-card__media` (`--tertiary`), `.md3-card__content`, `.md3-card__actions` (`--pad`).

```html
<section class="md3-card md3-card--outlined md3-card--flush" aria-labelledby="t">
  <div class="md3-card__header md3-card__header--pad">
    <div class="md3-card__titles"><h3 class="md3-card__title" id="t">Technicians</h3><p class="md3-card__subtitle">12 of 15 on shift</p></div>
    <a class="md3-icon-btn" href="#" aria-label="Open live map"><svg class="md3-icon">…map…</svg></a>
  </div>
  <ul class="md3-list">…</ul>
  <div class="md3-card__actions md3-card__actions--pad"><a class="md3-btn md3-btn--text" href="#">Manage team</a></div>
</section>
```
**Maps to** — NG `<mat-card appearance="raised|outlined">` (`mat-card-header`, `mat-card-title`, `mat-card-actions`);
VU `<v-card variant="elevated|flat|outlined|tonal">`; MW labs `<md-elevated-card>`, `<md-filled-card>`, `<md-outlined-card>`.
**Don’t** nest cards in cards.

## 15. Lists

**Purpose** — rows of related items. One line 56 dp, two lines 72 dp, three lines 88 dp; 16 dp leading, 24 dp trailing;
headline body-large, supporting body-medium, trailing text label-small.
**Anatomy** — `ul.md3-list` (`--flush`, `--dense` 40 dp rows, `--nav` pill items) › `.md3-list-item`
(`--two-line`, `--three-line`, `--interactive`, `--stack` = trailing chip wraps under the text on phones, `.is-selected`)
› leading icon / `.md3-avatar` / `.md3-list-thumb` / `.md3-list-item__leading-time`, `.md3-list-item__content` ›
`__overline`, `__headline`, `__supporting`; trailing `.md3-list-item__trailing-text`, a switch, checkbox or status chip.
`.md3-list-subheader` for section titles.

```html
<ul class="md3-list">
  <li><a class="md3-list-item md3-list-item--two-line md3-list-item--interactive md3-list-item--stack" href="#">
    <span class="md3-list-item__leading-time">08:30</span>
    <span class="md3-list-item__content"><span class="md3-list-item__headline">Replace boiler pressure valve</span><span class="md3-list-item__supporting">JB-2417 · Hollis Bakery · Mateo Alvarez</span></span>
    <span class="md3-status md3-status--progress"><svg class="md3-icon">…build…</svg>In progress</span></a></li>
</ul>
```
**Maps to** — NG `<mat-list>` / `<mat-nav-list>` / `<mat-selection-list>` with `matListItemTitle`, `matListItemLine`,
`matListItemAvatar`, `matListItemMeta`; VU `<v-list lines="two">` + `<v-list-item title subtitle prepend-avatar>`;
MW `<md-list>` + `<md-list-item>` (slots `headline`, `supporting-text`, `start`, `end`).

## 16. Divider

`hr.md3-divider` 1 dp outline-variant; `--inset` (16 dp start), `--middle` (16 dp both sides).
**Maps to** — NG `<mat-divider [inset]>`; VU `<v-divider inset>`; MW `<md-divider inset>`.

## 17. Text fields — filled and outlined

**Purpose** — data entry. 56 dp, body-large input, body-small floating label, 16 dp side padding, supporting text
body-small 4 dp below. Filled: surface-container-highest, corner-extra-small-top, 1 dp active indicator (2 dp primary
on focus). Outlined: 1 dp outline, corner-extra-small, label notched into the outline (the label’s background is
`--md3-field-surface`, which cards, dialogs and panels set for you).
**Anatomy** — `.md3-field` + `--filled` | `--outlined` (+ `--leading`, `--textarea`, `--select`) and state `is-empty`
(resting label), `is-focused`, `is-error`, `is-disabled`, `is-hover` › `.md3-field__container` ›
`.md3-field__leading`, `label.md3-field__label` (+ `.md3-field__required`), `.md3-field__input`
(`input`/`select`/`textarea`), `.md3-field__prefix`/`__suffix`, `.md3-field__trailing` (icon or an `.md3-icon-btn`)
› `.md3-field__supporting` (+ `.md3-field__counter`). `.md3-field__value` draws a custom select value (avatar + text).

```html
<div class="md3-field md3-field--outlined is-error">
  <div class="md3-field__container">
    <label class="md3-field__label" for="phone">Phone<span class="md3-field__required" aria-hidden="true">*</span></label>
    <input class="md3-field__input" id="phone" type="tel" required aria-invalid="true" aria-describedby="phone-err" value="+1 555 01">
    <span class="md3-field__trailing"><svg class="md3-icon">…error-fill…</svg></span>
  </div>
  <div class="md3-field__supporting" id="phone-err">Enter a full number, e.g. +1 555 0187</div>
</div>
```
**Maps to** — NG `<mat-form-field appearance="fill|outline">` + `<mat-label>`, `<input matInput>`, `<mat-hint>`,
`<mat-error>`, `matIconPrefix`/`matTextSuffix`; VU `<v-text-field variant="filled|outlined" label hint counter
:error-messages>`, `<v-textarea>`; MW `<md-filled-text-field>`, `<md-outlined-text-field label supporting-text error error-text>`.
**Do** one style per form in production. **Don’t** use placeholder text as the label.

## 18. Select and menu

**Purpose** — pick one value; menus list actions. Select = a text field with `--select` and an `arrow_drop_down`
trailing icon (use `arrow_drop_up` when drawn open). Menu: surface-container, corner-extra-small, level 2, 48 dp items.
**Anatomy** — `ul.md3-menu` › `.md3-menu__item` (`.is-selected` secondary-container, `--danger`) › icon,
`.md3-menu__label`, `.md3-menu__trailing` (shortcut); `.md3-menu__divider`.

```html
<ul class="md3-menu" role="menu" aria-label="Job actions">
  <li><a class="md3-menu__item" role="menuitem" href="#"><svg class="md3-icon">…assignment_ind…</svg><span class="md3-menu__label">Reassign</span></a></li>
  <li class="md3-menu__divider" role="separator"></li>
  <li><a class="md3-menu__item md3-menu__item--danger" role="menuitem" href="#"><svg class="md3-icon">…delete…</svg><span class="md3-menu__label">Cancel job</span></a></li>
</ul>
```
**Maps to** — NG `<mat-select>` in a form field, `<mat-menu>` + `[matMenuTriggerFor]` + `mat-menu-item`;
VU `<v-select>`, `<v-menu>` + `<v-list>`; MW `<md-outlined-select>` + `<md-select-option>`, `<md-menu>` + `<md-menu-item>`.

## 19. Search bar

**Purpose** — the entry point to search. 56 dp (`--compact` 48 dp for top app bars), corner-full,
surface-container-high, leading icon, body-large input, trailing icon buttons or avatar.
**Anatomy** — `.md3-search[role=search]` › `.md3-search__leading`, visually hidden label, `input.md3-search__input`, `.md3-search__trailing`.
**Maps to** — NG `<mat-form-field>` + custom; VU `<v-text-field variant="solo" rounded="pill" prepend-inner-icon>`; MW none.

## 20. Checkbox

18 dp box, 2 dp on-surface-variant outline, corner 2 dp; checked/indeterminate primary with on-primary mark; `is-error`;
disabled 38 %. Real `<input type="checkbox" class="md3-checkbox">` (`is-indeterminate` draws the dash). Wrap with
`label.md3-check` for a label, `.md3-selection` (40 dp state layer, `--primary` when selected) for demo states.
**Maps to** — NG `<mat-checkbox>`; VU `<v-checkbox>`; MW `<md-checkbox>`.

## 21. Radio button

20 dp ring, primary when checked with a 10 dp dot. `<input type="radio" class="md3-radio">` inside `label.md3-check`;
group with `fieldset.md3-radio-group` + `legend.md3-form-label`.
**Maps to** — NG `<mat-radio-group>` + `<mat-radio-button>`; VU `<v-radio-group>` + `<v-radio>`; MW `<md-radio>`.

## 22. Switch

52×32 track; off: surface-container-highest + 2 dp outline, 16 dp outline handle; on: primary track, 24 dp on-primary
handle; `--icons` shows check/close in the handle; `is-hover`, `is-pressed` (28 dp handle), `disabled`.

```html
<button class="md3-switch md3-switch--icons" type="button" role="switch" aria-checked="true" aria-labelledby="n1">
  <span class="md3-switch__handle"><svg class="md3-icon">…check…</svg></span>
</button>
```
**Maps to** — NG `<mat-slide-toggle>` (icons by default, `hideIcon`); VU `<v-switch inset>`; MW `<md-switch icons selected>`.

## 23. Slider

4 dp tracks (primary active, surface-container-highest inactive), 20 dp primary handle level 1, value label 28 dp.
`.md3-slider` with `style="--md3-value: 40%"` › optional `.md3-slider__label` › `__label-text`, a real
`input[type=range].md3-slider__input`; `.md3-slider__scale` for end labels.
**Maps to** — NG `<mat-slider discrete><input matSliderThumb></mat-slider>`; VU `<v-slider thumb-label>`; MW `<md-slider labeled>`.

## 24. Progress indicators

Linear: native `<progress class="md3-progress">` 4 dp, primary on a secondary-container track (`--rounded`);
`div.md3-progress.md3-progress--indeterminate` draws the moving bar frozen. Circular: inline SVG `.md3-circular`
(48 dp, `--small` 24 dp) with `.md3-circular__track` and `.md3-circular__indicator` (`stroke-dasharray` = value × 125.7).
**Maps to** — NG `<mat-progress-bar mode>`, `<mat-progress-spinner>`; VU `<v-progress-linear>`, `<v-progress-circular>`;
MW `<md-linear-progress value>`, `<md-circular-progress>`.

## 25. Tabs

Primary tabs 48 dp (64 dp `--stacked` with icon), title-small, active label primary with a 3 dp indicator as wide as the
label; `.md3-tabs--secondary`: 2 dp full-width indicator, active label on-surface. `.md3-tabs--start` packs tabs left.
**Anatomy** — `nav.md3-tabs` › `a.md3-tab[role=tab][aria-selected="true"]` › `.md3-tab__content` (+ `.md3-tab__badge`).
**Maps to** — NG `<mat-tab-group>` / `<nav mat-tab-nav-bar>`; VU `<v-tabs>`; MW `<md-tabs>` + `<md-primary-tab>` / `<md-secondary-tab>`.

## 26. Data table, selection bar and paginator

**Purpose** — the mat-table look. Header 56 dp title-small on-surface, rows 52 dp body-medium, outline-variant rules,
hover 8 % on-surface, selected rows tinted secondary-container. Cells don’t wrap; the table scrolls in
`.md3-table-scroll` on phones. `.md3-table--compact` = 48/44 dp.
**Anatomy** — `section.md3-table-card` › `.md3-table-toolbar` (normal: `__title` + icon buttons;
`--selection`: close, “2 selected”, bulk actions) › `.md3-table-scroll` › `table.md3-table` (`th.md3-col-check`,
`.md3-th-sort.is-sorted` + `aria-sort`, `.md3-col-num`, `.md3-col-actions`, cell helpers `.md3-cell-stack`,
`.md3-cell-primary`, `.md3-cell-secondary`, `.md3-cell-person`; `tr.is-selected[aria-selected]`, `tr.is-hover`)
› `.md3-paginator` (`__size`, `__size-label` “Items per page:”, `__select`, `__range` “1 – 10 of 248”, `__actions`).

```html
<section class="md3-table-card" aria-label="Jobs">
  <div class="md3-table-toolbar md3-table-toolbar--selection">
    <a class="md3-icon-btn" href="#" aria-label="Clear selection"><svg class="md3-icon">…close…</svg></a>
    <p class="md3-table-toolbar__title">2 selected</p>
    <a class="md3-icon-btn" href="#" aria-label="Delete selected jobs"><svg class="md3-icon">…delete…</svg></a>
  </div>
  <div class="md3-table-scroll"><table class="md3-table">
    <thead><tr><th class="md3-col-check"><input class="md3-checkbox is-indeterminate" type="checkbox" aria-label="Select all"></th>
      <th aria-sort="ascending"><a class="md3-th-sort is-sorted" href="#">Scheduled<svg class="md3-icon">…arrow_upward…</svg></a></th><th>Status</th></tr></thead>
    <tbody><tr class="is-selected" aria-selected="true"><td class="md3-col-check"><input class="md3-checkbox" type="checkbox" checked aria-label="Select JB-2421"></td>
      <td>Today, 11:00</td><td><span class="md3-status md3-status--scheduled"><svg class="md3-icon">…event…</svg>Scheduled</span></td></tr></tbody>
  </table></div>
  <div class="md3-paginator" role="group" aria-label="Pagination">
    <div class="md3-paginator__size"><span class="md3-paginator__size-label">Items per page:</span><span class="md3-paginator__select">10<svg class="md3-icon">…arrow_drop_down…</svg></span></div>
    <span class="md3-paginator__range" role="status">1 – 10 of 248</span>
    <div class="md3-paginator__actions"><button class="md3-icon-btn" type="button" disabled aria-label="Previous page"><svg class="md3-icon">…chevron_left…</svg></button><button class="md3-icon-btn" type="button" aria-label="Next page"><svg class="md3-icon">…chevron_right…</svg></button></div>
  </div>
</section>
```
**Maps to** — NG `<table mat-table [dataSource] matSort>` + `SelectionModel` + `<mat-paginator [pageSizeOptions]>`;
VU `<v-data-table show-select :items-per-page>`; MW none.

## 27. Dialog

Basic dialog: surface-container-high, corner-extra-large (28 dp), level 3, 24 dp padding, optional secondary icon
(centres the headline), headline-small, body-medium supporting text, text-button actions at the end; scrim 32 %.
`.md3-scrim` (fixed, full page) or `.md3-dialog-stage` (in-page demo) › `.md3-dialog[role=dialog]` (`--narrow`) ›
`__icon`, `__headline`, `__content` (`--list` for a full-bleed list), `__actions`.

```html
<div class="md3-scrim">
  <main class="md3-dialog" role="dialog" aria-modal="true" aria-labelledby="h">
    <div class="md3-dialog__icon"><svg class="md3-icon">…assignment_ind…</svg></div>
    <h1 class="md3-dialog__headline" id="h">Reassign 2 jobs?</h1>
    <p class="md3-dialog__content">JB-2421 and JB-2423 move to the technician you pick.</p>
    <div class="md3-dialog__actions"><a class="md3-btn md3-btn--text" href="#">Cancel</a><a class="md3-btn md3-btn--text" href="#">Reassign</a></div>
  </main>
</div>
```
**Maps to** — NG `MatDialog.open()` with `mat-dialog-title`, `<mat-dialog-content>`, `<mat-dialog-actions align="end">`;
VU `<v-dialog max-width="560">` + `<v-card>`; MW `<md-dialog>` (slots `icon`, `headline`, `content`, `actions`).

## 28. Snackbar

inverse-surface, inverse-on-surface body-medium, corner-extra-small, level 3, 48 dp; action in inverse-primary, optional
close icon. `.md3-snackbar--floating` pins it bottom-centre (above the navigation bar on phones); add
`.md3-content--snackbar` to the page content so it does not hide the last block.
**Maps to** — NG `MatSnackBar.open('Settings saved', 'Undo')`; VU `<v-snackbar>`; MW none.

## 29. Tooltips

Plain `.md3-tooltip` (inverse-surface, corner-extra-small, body-small); rich `.md3-tooltip--rich`
(surface-container, corner-medium, level 2, `__subhead`, `__actions`).
**Maps to** — NG `matTooltip`; VU `<v-tooltip>`; MW none.

## 30. Expansion panel

`.md3-accordion` (surface, corner-medium, level 1) › `details.md3-expansion[open]` › `summary.md3-expansion__header`
(64 dp, title-medium) › `__title` + `__chevron` (`keyboard_arrow_down`, rotates when open) and `.md3-expansion__body`.
Works without script.
**Maps to** — NG `<mat-accordion>` + `<mat-expansion-panel>`; VU `<v-expansion-panels>`; MW none.

## 31. Banner

`.md3-banner` (surface-container-high, corner-medium; `--error`) › `__icon` (40 dp circle), `__body`, `__actions` (text buttons).
**Maps to** — NG custom; VU `<v-banner>`; MW none.

## 32. Composites: timeline, details list, chart, meter, stat, empty state, drop zone, map

Built from M3 roles; the libraries do not ship them.
- **Timeline** `ol.md3-timeline` › `.md3-timeline__item` › `__marker` (`--primary`, `--tertiary`), `__body` › `__title`, `__meta`, `__note`.
- **Details** `dl.md3-details` (`--compact` for money rows, `.md3-details__total`, `dd.md3-col-num`).
- **Chart** `.md3-chart` › `.md3-chart__y`, `.md3-chart__plot` › `svg.md3-chart__svg` (`preserveAspectRatio="none"`;
  `.md3-chart__grid`, `__area`, `__line`) + `.md3-chart__marker` / `.md3-tooltip.md3-chart__tip` positioned with
  `style="left:…;top:…"`; `.md3-chart__x`. Legends: `.md3-legend` › `__item` › `__key` (`--primary-container`,
  `--tertiary-container`, `--outline`, `--error`).
- **Meter** `.md3-meter` › `.md3-meter__seg` (`--primary-container`, `--tertiary`, `--outline`, `--error`) with `style="width:…%"`.
- **Stat** `.md3-stat` › `__label`, `__value` (+ `__unit`), `__delta` (`--up`, `--down`).
- **Empty state** `.md3-empty` › `__art` (rotated primary-container blob, `--neutral` circle, `__accent`), `__title`, `__text`, `__actions`.
- **Drop zone** `.md3-dropzone` (`is-dragover`) › `__icon`, `__title`; uploaded file row `.md3-file` › `__name`.
- **Map** `svg.md3-map` with `__land`, `__park`, `__water`, `__road` (`--minor`), `__halo`, `__pin`.

```html
<div class="md3-empty">
  <span class="md3-empty__art"><svg class="md3-icon">…home_repair_service…</svg><span class="md3-empty__accent"><svg class="md3-icon">…bolt-fill…</svg></span></span>
  <h2 class="md3-empty__title">No jobs yet</h2>
  <p class="md3-empty__text">Create the first job by hand, or import open work orders from a spreadsheet.</p>
  <div class="md3-empty__actions"><a class="md3-btn md3-btn--filled" href="#">Create a job</a></div>
</div>
```

## 33. Drawn credential fields (`.mock-*`)

Sign-in screens only. Outlined M3 field look: 56 dp, 4 dp corners, outline colour, notched floating label; states
`--focus` (2 dp primary + caret), `--error`, `--empty` (resting label), `--secret` (bullets). Inside
`md3-field md3-field--filled` they take the filled look.

```html
<div class="md3-field md3-field--outlined">
  <span class="mock-label">Password</span>
  <div class="mock-field mock-field--secret mock-field--focus" role="img" aria-label="Password field, filled in"><span class="mock-field__value">••••••••••••</span></div>
</div>
<a href="dashboard.html" class="md3-btn md3-btn--filled" role="button">Sign in</a>
```
**Never** a `<form>`, a text input or `type="password"` on these screens.

## 34. Typography, layout and page utilities

- Type: the Material Web classes `.md-typescale-{display,headline,title,body,label}-{large,medium,small}`.
- Page: `.md3-page-header` (`__titles`, `__title`, `__subtitle`), `.md3-section-label`, `.md3-form-section`
  (`__intro`, `__title`, `__text`), `.md3-form-grid` (`__full`), `.md3-form-label`, `.md3-form-footer` (`__note`).
- Layout: `.md3-row` (`--wrap`, `--between`, `--gap-16`), `.md3-stack` (`--tight`, `--loose`), `.md3-grow`,
  `.md3-grid` (`--2`, `--3`, `--4`, `--stats`, `--main-side`, `--side-main`, `--settings`, `--gap-24`),
  `.md3-compact-only`, `.md3-expanded-only`, spacing `.md3-mt-8/16/24`, `.md3-mb-16`, `.md3-pad-16/24`.
- Text: `.md3-text-variant`, `.md3-text-error`, `.md3-text-center`, `.md3-nums`, `.md3-nowrap`, `.md3-link`, `.md3-code`.
- Brand: `.md3-brand-mark` (`--lg`). Landing: `.md3-landing`, `__bar`, `__nav`, `__section`, `__inner`, `.md3-hero`
  (`__title`, `__lead`, `__actions`, `__visual`, `__float`), `.md3-logos`, `.md3-feature-icon`, `.md3-price`,
  `.md3-checklist`, `.md3-quote`, `.md3-tone-section`, `.md3-footer`. Auth: `.md3-auth` (`__card`, `__intro`, `__title`,
  `__fields`, `__actions`, `__footer`, `__links`, `__lang`).
