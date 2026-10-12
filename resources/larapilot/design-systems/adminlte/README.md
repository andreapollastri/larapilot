# AdminLTE 4 (mockup reference)

Visual contract for **admin / control-panel** mockups when the PRD records **[AdminLTE](https://adminlte.io/)**. Targets **AdminLTE v4.10.0** (npm `admin-lte`, the `latest` tag on 2026-10-12), which ships **Bootstrap v5.3.8** inside its stylesheet, with **Bootstrap Icons v1.13** and no jQuery. Sample product: **Kestrel Inventory** — stock, warehouses and suppliers for a small logistics company.

A mockup drawn with this folder looks like a real AdminLTE 4 admin — light content area, white header, the classic dark `#343a40` sidebar with an open treeview, colour small-boxes, info-boxes, cards with tools — in light and dark mode, on desktop and phone. Its markup is real AdminLTE markup (`app-wrapper`, `app-header`, `app-sidebar`, `sidebar-menu`, `nav-treeview`, `small-box`, `card-tools`, `btn-tool`, `login-box`…), so a developer pastes it into a Blade layout that loads `admin-lte` and gets the same page.

## What is in the folder

| File | What it is |
| --- | --- |
| `tokens.css` | The only stylesheet: a self-contained subset of the official `adminlte.css` 4.10.0 (Bootstrap 5.3.8 + AdminLTE layout and components) — same class names, same values, same source order — plus mockup-only sections at the end (inline icons, drawn credential fields, the dark-mode contrast lift). No network, no fonts, no scripts. |
| `components.md` | 27 components with anatomy, variants, copy-paste HTML and do/don't. |
| `sources.md` | Official sources, what was verified, what is assumed. |
| `html/` | 9 static screens + catalog (`html/index.html`). Every screen links only `../tokens.css`. |

## When to use it

Use it when **both** are true:

1. The screen is an **admin panel** page — dashboard, CRUD list, create/edit form, settings, sign-in — inside the AdminLTE shell.
2. The PRD `## Technical Architecture` records **AdminLTE** (or the project already uses `admin-lte` / an AdminLTE Blade layout such as `jeroennoten/laravel-adminlte`).

Do **not** use it for Filament or Nova panels, Laravel Starter Kits, Tailwind-only UIs, or public marketing pages (a Bootstrap public site uses `../bootstrap-5/`). Use AdminLTE **4** only — AdminLTE 3 (Bootstrap 4, jQuery, `content-wrapper`, `main-sidebar`) is a different markup and is not covered.

## How a mockup uses it

1. Copy `tokens.css` into the mockup folder as **`adminlte-tokens.css`** and link it — nothing else:

   ```html
   <!doctype html>
   <html lang="en">
   <head>
     <meta charset="utf-8">
     <meta name="viewport" content="width=device-width, initial-scale=1">
     <title>Products · Kestrel Inventory</title>
     <link rel="stylesheet" href="adminlte-tokens.css">
   </head>
   <body class="lte-mockup layout-fixed sidebar-expand-lg bg-body-tertiary">
     <div class="app-wrapper">…</div>
   </body>
   </html>
   ```

   `lte-mockup` on `<body>` tells the dashboard which design system a mockup uses; `layout-fixed sidebar-expand-lg bg-body-tertiary` are AdminLTE's own body classes (sign-in: `login-page bg-body-secondary`). No CDN, no `<script>`, no OverlayScrollbars.
2. Start from the closest screen in `html/` (the catalog says which). Keep the shell — `app-header`, `app-sidebar` (with `data-bs-theme="dark"`), `app-main` (`app-content-header` + `app-content`), `app-footer` — and change the content.
3. Compose with the classes in `components.md`. Every class used must exist in `tokens.css` — it holds the documented vocabulary, not all of AdminLTE and Bootstrap. If a page really needs a missing class, copy its rule from the official `adminlte.css` 4.10.0 into the mockup's own copy and say so in the mockup README.
4. Interactive states are drawn: the open treeview is `li.nav-item.menu-open` (its `nav-treeview` shows, its `nav-arrow` turns), the current page is `nav-link active` + `aria-current="page"` (the parent link also gets `active`); a collapsed card is `card collapsed-card`; an open navbar dropdown is `.dropdown-menu.show` (in a page section add `position-static`); a modal is `.modal.d-block` after a `.modal-backdrop.show`; a toast has `.show`; a selected row has `.table-active`; an invalid field has `.is-invalid` + `.invalid-feedback`.
5. Sign-in screens **draw** the credentials inside AdminLTE's input groups: `<div class="input-group mb-3"><div class="mock-field" role="img" aria-label="Email field, filled in">…</div><div class="input-group-text">…icon…</div></div>`. `.mock-field` is the `.form-control` rule cloned (with AdminLTE's inset shadow), plus `--secret`, `--empty`, `--focus`, `--error`. No `<form>`, no text `<input>`, never `type="password"`; the submit is `<a href="dashboard.html" class="btn btn-primary" role="button">`.
6. Dark mode: light by default; dark when the OS is dark (catalog preview), or forced with `<html data-bs-theme="dark">` (see `html/dashboard-dark.html`). The sidebar is dark in both modes because it carries `data-bs-theme="dark"` itself — AdminLTE 4's default.
7. Phones: below `lg` the sidebar slides off-canvas (AdminLTE's own CSS) and the header's `bi-list` button is the menu; small-box icons hide below `sm`; tables sit in `.table-responsive`.

### Icons

Bootstrap Icons, inline. In the **real app** an icon is the font glyph, exactly as in the AdminLTE demo:

```html
<i class="nav-icon bi bi-box-seam" aria-hidden="true"></i>   <!-- bootstrap-icons package, imported in app.scss -->
```

The **mockup** inlines the same glyph with the same classes, so a developer swaps the tag and nothing else:

```html
<svg class="bi bi-box-seam" viewBox="0 0 16 16" aria-hidden="true"><path d="M8.186 1.113a.5.5 0 0 0-.372 0L1.846 3.5l2.404.961L10.404 2zm3.564 1.426L5.596 5 8 5.961 14.154 3.5zm3.25 1.7-6.5 2.6v7.922l6.5-2.6V4.24zM7.5 14.762V6.838L1 4.239v7.923zM7.443.184a1.5 1.5 0 0 1 1.114 0l7.129 2.852A.5.5 0 0 1 16 3.5v8.662a1 1 0 0 1-.629.928l-7.185 2.874a.5.5 0 0 1-.372 0L.63 13.09a1 1 0 0 1-.63-.928V3.5a.5.5 0 0 1 .314-.464z"/></svg>
```

(in the screens it reads `<svg class="nav-icon bi bi-box-seam" …>`). `.bi` sizes the SVG like the font (1em square, baseline −.125em). The same SVG takes AdminLTE's icon slots: `nav-icon`, `nav-arrow`, `small-box-icon`, `timeline-icon`, or sits inside `info-box-icon`. The `bi-*` names the screens use are declared in `tokens.css`; `html/icons.html` holds 144 more as `<svg class="bi" data-bi="truck">` — copy one as is (`data-bi` is the real-app name).

## Screens

| Screen | Shows | Start from it when |
| --- | --- | --- |
| `dashboard.html` | Full shell (header with search, badges, user menu; dark sidebar with open treeview, headers, badge), 4 small-boxes, SVG sales chart in a card with tools, latest-orders table with sparklines, coloured info-boxes, products list, direct chat | Any admin page |
| `dashboard-dark.html` | Warehouses overview, forced dark: icon info-boxes, bar chart + progress-groups, recap footer, timeline, donut, callout | Dark mode, v2-style dashboards, timelines |
| `resource-list.html` | Products card: header tools (search, filters, New), bulk bar, selected rows, sortable headers, stock bars, badges, row actions, pagination | Any index/table |
| `resource-form.html` | Edit product: outlined cards, horizontal rows, required marks, invalid/valid/disabled fields, input groups, switches, radios, upload zone, supplier table, `app-content-bottom-area` actions | Create/edit pages |
| `settings.html` | Profile box, list-group nav, pill tabs, horizontal form, notification switches, drawn password fields, danger card, toast | Account/preferences |
| `login.html` | `login-page` + `login-box`, drawn credentials | Any auth screen |
| `empty-state.html` | No records, no search results, setup checklist, tip callout | Lists that can be empty |
| `components.html` | Every component in its variants and states | Looking up a variant |
| `icons.html` | 144 Bootstrap Icons glyphs | Picking an icon |

## Fidelity notes

- **Values are AdminLTE's**: Bootstrap 5.3.8 compiled with AdminLTE's overrides — `$enable-shadows: true` (inset shadow on fields, highlight on buttons), font stack starting with **Source Sans 3**, `fs-7`/`fs-8`, navbar link padding 1rem — plus AdminLTE's layout (250 px sidebar, 3.5 rem brand row, `#343a40` sidebar with `#c2c7d0` links), card shadow `0 0 1px …, 0 1px 3px …`, `card-title` 1.1rem/400, small-box `h3` 2.2rem/700, info-box 80 px min-height. Rules were copied from `dist/css/adminlte.css` 4.10.0, minus legacy `-moz-`/`-ms-` duplicates and the per-component reduced-motion blocks (AdminLTE's own global reduced-motion rule is kept).
- **Font**: the real app loads **Source Sans 3** (`@fontsource/source-sans-3`); the mockup names it first and falls back to the system stack, so line lengths differ slightly where it is not installed.
- **Dark mode media query**: real AdminLTE switches with `data-bs-theme` (its colour-mode toggler); `tokens.css` also maps `prefers-color-scheme: dark` and `html.dark` to the same values.
- **Dark-mode contrast lift** (the one visual deviation): outline buttons, `.text-primary/secondary/success/danger` and `.link-*` use the `-text-emphasis` colour in dark mode so text passes WCAG AA; add the same rules (last section of `tokens.css`) to `resources/sass/app.scss` to match.
- **Small-box icons**: the AdminLTE 4 demo draws them with Heroicons-style SVGs; this kit uses Bootstrap Icons glyphs in the same `small-box-icon` slot (70 px, `rgba(0,0,0,.15)`).
- **Charts** are inline SVG with `text-*` colours; the real app uses ApexCharts / Chart.js (as the AdminLTE demo does). **Avatars** are initials SVGs; the real app uses `<img class="user-image rounded-circle">` / `direct-chat-img`.
- **Not in the subset**: sidebar-mini / collapsed rail, `sidebar-search`, `fixed-header`, ribbons, widget-user boxes, lockscreen, accordion, floating labels, offcanvas, carousel, tooltips/popovers, `col-xl-*`. See step 3 when one is needed.

## Mockup → real code

| Mockup | Real app |
| --- | --- |
| `<link rel="stylesheet" href="adminlte-tokens.css">` | `npm i admin-lte@^4.10 bootstrap-icons @fontsource/source-sans-3`; `@import "admin-lte/dist/css/adminlte.css"` (or its `src/scss`) in `resources/sass/app.scss`, `import 'admin-lte'` in `resources/js/app.js`, `@vite([...])` in the layout |
| `<svg class="nav-icon bi bi-x">…</svg>` | `<i class="nav-icon bi bi-x"></i>` |
| Shell markup in every screen | One Blade layout (`layouts/admin.blade.php`) with `@yield('content')`; the sidebar from a menu config; `active`/`menu-open` from `request()->routeIs()` |
| `li.nav-item.menu-open` | `data-lte-toggle="treeview"` on `ul.sidebar-menu` (AdminLTE JS opens/closes) |
| Header `bi-list` link | `data-lte-toggle="sidebar"` (push menu) |
| `btn-tool` collapse / remove | `data-lte-toggle="card-collapse"` / `"card-remove"` |
| `.dropdown-menu.show` | `data-bs-toggle="dropdown"` on the toggle |
| `.modal.d-block` + backdrop | `.modal.fade` + `data-bs-toggle="modal"` |
| `.mock-field` in `input-group` | `<input type="email|password" class="form-control">` in a `<form method="POST">` with `@csrf` |
| `.is-invalid` + `.invalid-feedback` | `@error('field') is-invalid @enderror` + `{{ $message }}` |
| Inline SVG charts | ApexCharts / Chart.js in the same `card-body` |

## Do / don't

- **Do** keep the AdminLTE shell order (`app-header`, `app-sidebar`, `app-main`, `app-footer` inside `app-wrapper`) and the body classes.
- **Do** put page titles in `app-content-header` (`h1.mb-0.fs-3` + `breadcrumb float-sm-end`) and content in `app-content > .container-fluid`.
- **Do** wrap content in cards: `card-header` with `card-title` + `card-tools`; tables in `card-body p-0` with `table m-0`; footers with `clearfix`.
- **Do** use `card-{color} card-outline` for forms, `text-bg-{color}` for small-boxes and coloured info-boxes, `callout callout-{color}` for page notes.
- **Do** give icon-only buttons (`btn-tool`, row actions) an `aria-label`.
- **Don't** use AdminLTE 3 classes (`content-wrapper`, `main-sidebar`, `brand-link` outside `sidebar-brand`, `nav-sidebar`, `bg-gradient-*`, Font Awesome `fas fa-*`).
- **Don't** load the CDN, Google Fonts or the icon font in a mockup; don't add `<script>`.
- **Don't** use `btn-outline-warning` / `btn-outline-info` or `.text-warning` for text — they fail contrast on white.
- **Don't** draw a real password input anywhere.

## README contract (mockup folder)

The mockup `README.md` names: **Design system:** AdminLTE 4 (link [adminlte.io](https://adminlte.io/), this folder and `sources.md`); panel screens mapped to AdminLTE page types; skin/theme overrides if any (primary colour, light sidebar); responsive behaviour (sidebar off-canvas below `lg`); accessibility notes.

## Downstream

- **Alex** implements with the `admin-lte` 4 package through Vite, a Blade admin layout and AdminLTE's JS plugins (`data-lte-toggle`).
- **Anne** tests the sidebar toggle and treeview, the off-canvas sidebar at 390 px, card tools, light and dark.
