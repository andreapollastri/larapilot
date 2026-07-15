# AdminLTE — component & layout patterns

Reference for Elise when building AdminLTE admin mockups. Match [AdminLTE 4 docs](https://adminlte-v4.netlify.app/docs/getting-started) and the [live demo](https://adminlte-v4.netlify.app/). Use native AdminLTE + Bootstrap classes — not Filament or Starter Kit shells.

**Packaged HTML:** static screens live in `html/` — open `html/index.html` for the catalog.

## App shell (v4 grid layout)

```
┌──────────────────────────────────────────────────────┐
│  .app-header (navbar, sidebar toggle, user menu)     │
├────────────┬─────────────────────────────────────────┤
│ .app-      │  .app-main                              │
│ sidebar    │  ├─ .app-content-header (title/breadcrumb)│
│ (dark)     │  └─ .app-content (cards, widgets, tables) │
│ brand +    │                                         │
│ tree nav   │                                         │
└────────────┴─────────────────────────────────────────┘
         wrapped in .app-wrapper
```

- **Body:** `layout-fixed sidebar-expand-lg bg-body-tertiary`
- **Sidebar:** `app-sidebar bg-body-secondary shadow` with `data-bs-theme="dark"`; contains `sidebar-brand` + `sidebar-wrapper` with `nav sidebar-menu`
- **Header:** `app-header navbar`; hamburger uses `data-lte-toggle="sidebar"`
- **Main:** `app-main` → `app-content-header` + `app-content` with `container-fluid`

HTML skeleton:

```html
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
  <div class="app-wrapper">
    <nav class="app-header navbar navbar-expand bg-body">…</nav>
    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">…</aside>
    <main class="app-main">…</main>
  </div>
</body>
```

## Navigation

- Top-level items: `nav sidebar-menu` → `li.nav-item` → `a.nav-link` with `i.nav-icon.bi` + `p` label
- Active item: `nav-link active`
- Treeview (nested): `data-lte-toggle="treeview"` on `ul`; parent links get chevron via AdminLTE JS
- Nav header labels: `li.nav-header` for section titles

## Page types

| Mockup screen | AdminLTE implementation |
| --- | --- |
| Dashboard | `small-box` widgets + `.card` tables/charts |
| Resource list | Card with `.table.table-bordered.table-striped` + toolbar |
| Login | `body.login-page` + `.login-box` + `.login-card-body` |
| Settings | Cards with form groups inside `app-content` |
| Modals / confirms | Bootstrap `.modal` or SweetAlert2 (document if used) |

## Signature widgets

| Widget | Markup |
| --- | --- |
| **Small box** | `.small-box.text-bg-primary` with `.inner`, `.small-box-icon`, `.small-box-footer` |
| **Info box** | `.info-box` with `.info-box-icon`, `.info-box-content`, `.info-box-number` |
| **Card tools** | `.card-tools` with `data-lte-toggle="card-collapse"` / `card-remove` |
| **Badge in nav** | `.badge.text-bg-danger` inside `p` on nav link |

## Buttons & forms

| Variant | Class | Use |
| --- | --- | --- |
| Primary | `btn btn-primary` | Save, Create, Sign in |
| Secondary | `btn btn-secondary` | Cancel |
| Danger | `btn btn-danger` | Delete |
| Tool (icon) | `btn btn-tool` | Card header actions |

- Forms: `.form-label`, `.form-control`, `.input-group`, `.form-check`
- Validation: `.is-invalid` + `.invalid-feedback`

## Tables (index pages)

- Wrap in `.card` → `.card-body.table-responsive` or `.card-body.p-0` + full-width table
- Toolbar: `.card-header` with title left, `.card-tools` right (search input, Create button)
- Status: `badge text-bg-success` / `warning` / `danger`
- Pagination: Bootstrap `.pagination` in `.card-footer`

## Login page

```html
<body class="login-page bg-body-secondary">
  <div class="login-box">
    <div class="login-logo"><a href="#"><b>App</b>Name</a></div>
    <div class="card">
      <div class="card-body login-card-body">…</div>
    </div>
  </div>
</body>
```

No `app-wrapper` on login — standalone centered card.

## Mobile

- Sidebar collapses via PushMenu (`data-lte-toggle="sidebar"`)
- `sidebar-expand-lg` on body — sidebar visible from `lg` breakpoint up
- Tables: always `.table-responsive`

## Do not mix

- **Filament** amber sidebar / section components on AdminLTE-scoped screens
- **Starter Kit** shadcn/Flux patterns
- Generic Bootstrap marketing hero — use `bootstrap-5/` design system instead
