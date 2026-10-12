# AdminLTE 4 — components

Every component the kit documents, with the **real AdminLTE 4 / Bootstrap 5.3 class names**. Snippets paste into a mockup that links `adminlte-tokens.css` and into a Blade view that loads `admin-lte` — the markup is the same; only icons change tag (`<svg class="bi bi-…">` in the mockup, `<i class="bi bi-…">` in the app). All of them are rendered in `html/components.html`; full screens are in `html/`.

Conventions: light content, white header, dark sidebar (`data-bs-theme="dark"` on `.app-sidebar`); page dark mode through `data-bs-theme="dark"` on `<html>`. Interactive states are drawn with the classes AdminLTE's and Bootstrap's JS would add (`menu-open`, `active`, `collapsed-card`, `.show`). Icon-only controls carry `aria-label`; decorative icons are `aria-hidden="true"`.

---

## 1. App wrapper and body

**Purpose:** the page frame. **Anatomy:** `body.layout-fixed.sidebar-expand-lg.bg-body-tertiary` (+ `lte-mockup` in mockups) > `div.app-wrapper` (CSS grid) > `nav.app-header` · `aside.app-sidebar` · `main.app-main` · `footer.app-footer`. **Variants:** `sidebar-expand-md|xl` change the breakpoint (this kit ships `lg`). **Phone:** below `lg` the sidebar is off-canvas and the header's `bi-list` link opens it (`data-lte-toggle="sidebar"` in the real app).

```html
<body class="lte-mockup layout-fixed sidebar-expand-lg bg-body-tertiary">
  <div class="app-wrapper">
    <nav class="app-header navbar navbar-expand bg-body" aria-label="Top bar">…</nav>
    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">…</aside>
    <main class="app-main">
      <div class="app-content-header">…</div>
      <div class="app-content"><div class="container-fluid">…</div></div>
    </main>
    <footer class="app-footer"><div class="float-end d-none d-sm-inline">Kestrel Inventory 3.2</div><strong>Copyright &copy; 2026 Kestrel Logistics B.V.</strong> All rights reserved.</footer>
  </div>
</body>
```

## 2. Header (app-header)

**Anatomy:** `nav.app-header.navbar.navbar-expand.bg-body > .container-fluid` with a start `ul.navbar-nav` (sidebar toggle, `d-none d-md-block` links), optional `form.navbar-search` (`navbar-search-field` + `navbar-search-submit`, `d-none d-xl-block`), and an end `ul.navbar-nav.ms-auto` (icon links with `navbar-badge`, `user-menu` with `user-image`). **Do:** `aria-label` on every icon link (include the count).

```html
<nav class="app-header navbar navbar-expand bg-body" aria-label="Top bar">
  <div class="container-fluid">
    <ul class="navbar-nav">
      <li class="nav-item"><a class="nav-link" href="#" role="button" aria-label="Toggle sidebar"><svg class="bi bi-list" viewBox="0 0 16 16" aria-hidden="true"><path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5"/></svg></a></li>
      <li class="nav-item d-none d-md-block"><a href="dashboard.html" class="nav-link">Home</a></li>
    </ul>
    <ul class="navbar-nav ms-auto">
      <li class="nav-item"><a class="nav-link" href="#" aria-label="Notifications: 7 unread"><svg class="bi bi-bell-fill" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2m.995-14.901a1 1 0 1 0-1.99 0A5 5 0 0 0 3 6c0 1.098-.5 6-2 7h14c-1.5-1-2-5.902-2-7 0-2.42-1.72-4.44-4.005-4.901"/></svg><span class="navbar-badge badge text-bg-warning">7</span></a></li>
      <li class="nav-item dropdown user-menu"><a href="#" class="nav-link dropdown-toggle"><span class="d-none d-md-inline">Rosa Delgado</span></a></li>
    </ul>
  </div>
</nav>
```

## 3. Sidebar, brand and menu

**Anatomy:** `aside.app-sidebar.bg-body-secondary.shadow[data-bs-theme=dark]` > `div.sidebar-brand > a.brand-link` (`brand-image` + `brand-text fw-light`) and `div.sidebar-wrapper > nav > ul.nav.sidebar-menu.flex-column`. Items: `li.nav-item > a.nav-link` with `nav-icon` + `<p>` label; section titles `li.nav-header`; counters `span.nav-badge.badge` inside the `<p>`. **States:** current page `nav-link active` + `aria-current="page"`.

```html
<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
  <div class="sidebar-brand"><a href="dashboard.html" class="brand-link"><span class="brand-text fw-light">Kestrel Inventory</span></a></div>
  <div class="sidebar-wrapper">
    <nav class="mt-2" aria-label="Main navigation">
      <ul class="nav sidebar-menu flex-column">
        <li class="nav-header">INVENTORY</li>
        <li class="nav-item"><a href="resource-list.html" class="nav-link active" aria-current="page"><svg class="nav-icon bi bi-box-seam" viewBox="0 0 16 16" aria-hidden="true"><path d="M8.186 1.113a.5.5 0 0 0-.372 0L1.846 3.5l2.404.961L10.404 2zm3.564 1.426L5.596 5 8 5.961 14.154 3.5zm3.25 1.7-6.5 2.6v7.922l6.5-2.6V4.24zM7.5 14.762V6.838L1 4.239v7.923zM7.443.184a1.5 1.5 0 0 1 1.114 0l7.129 2.852A.5.5 0 0 1 16 3.5v8.662a1 1 0 0 1-.629.928l-7.185 2.874a.5.5 0 0 1-.372 0L.63 13.09a1 1 0 0 1-.63-.928V3.5a.5.5 0 0 1 .314-.464z"/></svg><p>Products</p></a></li>
        <li class="nav-item"><a href="#" class="nav-link"><svg class="nav-icon bi bi-cart-plus" viewBox="0 0 16 16" aria-hidden="true"><path d="M9 5.5a.5.5 0 0 0-1 0V7H6.5a.5.5 0 0 0 0 1H8v1.5a.5.5 0 0 0 1 0V8h1.5a.5.5 0 0 0 0-1H9z"/><path d="M.5 1a.5.5 0 0 0 0 1h1.11l.401 1.607 1.498 7.985A.5.5 0 0 0 4 12h1a2 2 0 1 0 0 4 2 2 0 0 0 0-4h7a2 2 0 1 0 0 4 2 2 0 0 0 0-4h1a.5.5 0 0 0 .491-.408l1.5-8A.5.5 0 0 0 14.5 3H2.89l-.405-1.621A.5.5 0 0 0 2 1zm3.915 10L3.102 4h10.796l-1.313 7zM6 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0m7 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/></svg><p>Purchase orders<span class="nav-badge badge text-bg-warning me-3">12</span></p></a></li>
      </ul>
    </nav>
  </div>
</aside>
```

## 4. Treeview (open sub-menu)

**Anatomy:** parent `li.nav-item.menu-open > a.nav-link.active` with `<p>` + `i.nav-arrow.bi-chevron-right` (rotates when open), then `ul.nav.nav-treeview` of `li.nav-item > a.nav-link` with `nav-icon bi-circle`. Closed = no `menu-open` (the treeview is hidden). **Real app:** `data-lte-toggle="treeview"` on the `ul.sidebar-menu`.

```html
<li class="nav-item menu-open">
  <a href="dashboard.html" class="nav-link active"><svg class="nav-icon bi bi-speedometer" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 2a.5.5 0 0 1 .5.5V4a.5.5 0 0 1-1 0V2.5A.5.5 0 0 1 8 2M3.732 3.732a.5.5 0 0 1 .707 0l.915.914a.5.5 0 1 1-.708.708l-.914-.915a.5.5 0 0 1 0-.707M2 8a.5.5 0 0 1 .5-.5h1.586a.5.5 0 0 1 0 1H2.5A.5.5 0 0 1 2 8m9.5 0a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 0 1H12a.5.5 0 0 1-.5-.5m.754-4.246a.39.39 0 0 0-.527-.02L7.547 7.31A.91.91 0 1 0 8.85 8.569l3.434-4.297a.39.39 0 0 0-.029-.518z"/><path fill-rule="evenodd" d="M6.664 15.889A8 8 0 1 1 9.336.11a8 8 0 0 1-2.672 15.78zm-4.665-4.283A11.95 11.95 0 0 1 8 10c2.186 0 4.236.585 6.001 1.606a7 7 0 1 0-12.002 0"/></svg><p>Dashboard<svg class="nav-arrow bi bi-chevron-right" viewBox="0 0 16 16" aria-hidden="true"><path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708"/></svg></p></a>
  <ul class="nav nav-treeview">
    <li class="nav-item"><a href="dashboard.html" class="nav-link active" aria-current="page"><svg class="nav-icon bi bi-circle" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/></svg><p>Overview</p></a></li>
    <li class="nav-item"><a href="dashboard-dark.html" class="nav-link"><svg class="nav-icon bi bi-circle" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/></svg><p>Warehouses</p></a></li>
  </ul>
</li>
```

## 5. Content header and breadcrumb

**Anatomy:** `div.app-content-header > .container-fluid > .row` with `col-sm-6 > h1.mb-0.fs-3` and `col-sm-6 > nav > ol.breadcrumb.float-sm-end` (AdminLTE styles the links with the hover colour for contrast).

```html
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h1 class="mb-0 fs-3">Products</h1></div>
      <div class="col-sm-6"><nav aria-label="breadcrumb"><ol class="breadcrumb float-sm-end"><li class="breadcrumb-item"><a href="dashboard.html">Home</a></li><li class="breadcrumb-item active" aria-current="page">Products</li></ol></nav></div>
    </div>
  </div>
</div>
```

## 6. Small box

**Purpose:** headline KPI tiles. **Anatomy:** `div.small-box.text-bg-{color}` > `div.inner` (`h3` number, optional `sup.fs-5`, `p` label) + `small-box-icon` (70 px, 15 % black) + `a.small-box-footer` (`link-light` on dark colours, `link-dark` on warning/info, `link-underline-opacity-0 link-underline-opacity-50-hover`). Layout `col-lg-3 col-6`. **Phone:** icon hides below `sm`, text centres.

```html
<div class="col-lg-3 col-6">
  <div class="small-box text-bg-warning">
    <div class="inner"><h3>38</h3><p>SKUs below reorder point</p></div>
    <svg class="small-box-icon bi bi-exclamation-triangle-fill" viewBox="0 0 16 16" aria-hidden="true"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/></svg>
    <a href="resource-list.html" class="small-box-footer link-dark link-underline-opacity-0 link-underline-opacity-50-hover">More info <svg class="bi bi-link-45deg" viewBox="0 0 16 16" aria-hidden="true"><path d="M4.715 6.542 3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1 1 0 0 0-.154.199 2 2 0 0 1 .861 3.337L6.88 11.45a2 2 0 1 1-2.83-2.83l.793-.792a4 4 0 0 1-.128-1.287z"/><path d="M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 3.55a2 2 0 1 1 2.83 2.83l-.793.792c.112.42.155.855.128 1.287l1.372-1.372a3 3 0 1 0-4.243-4.243z"/></svg></a>
  </div>
</div>
```

## 7. Info box

**Anatomy:** `div.info-box` > `span.info-box-icon` (colour with `text-bg-{color} shadow-sm`) + `div.info-box-content` (`info-box-text`, `info-box-number`). **Variants:** whole box coloured `info-box text-bg-{color}` (optionally `bg-gradient`) with a 2 px `progress > progress-bar` and `progress-description`; shadows `shadow-none|sm|lg`.

```html
<div class="info-box">
  <span class="info-box-icon text-bg-primary shadow-sm"><svg class="bi bi-buildings" viewBox="0 0 16 16" aria-hidden="true"><path d="M14.763.075A.5.5 0 0 1 15 .5v15a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5V14h-1v1.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V10a.5.5 0 0 1 .342-.474L6 7.64V4.5a.5.5 0 0 1 .276-.447l8-4a.5.5 0 0 1 .487.022M6 8.694 1 10.36V15h5zM7 15h2v-1.5a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 .5.5V15h2V1.309l-7 3.5z"/><path d="M2 11h1v1H2zm2 0h1v1H4zm-2 2h1v1H2zm2 0h1v1H4zm4-4h1v1H8zm2 0h1v1h-1zm-2 2h1v1H8zm2 0h1v1h-1zm2-2h1v1h-1zm0 2h1v1h-1zM8 7h1v1H8zm2 0h1v1h-1zm2 0h1v1h-1zM8 5h1v1H8zm2 0h1v1h-1zm2 0h1v1h-1zm0-2h1v1h-1z"/></svg></span>
  <div class="info-box-content"><span class="info-box-text">Rotterdam DC</span><span class="info-box-number">91<small>%</small> full</span></div>
</div>
<div class="info-box text-bg-success">
  <span class="info-box-icon"><svg class="bi bi-graph-up-arrow" viewBox="0 0 16 16" aria-hidden="true"><path fill-rule="evenodd" d="M0 0h1v15h15v1H0zm10 3.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0V4.9l-3.613 4.417a.5.5 0 0 1-.74.037L7.06 6.767l-3.656 5.027a.5.5 0 0 1-.808-.588l4-5.5a.5.5 0 0 1 .758-.06l2.609 2.61L13.445 4H10.5a.5.5 0 0 1-.5-.5"/></svg></span>
  <div class="info-box-content">
    <span class="info-box-text">Fill rate</span><span class="info-box-number">96.4%</span>
    <div class="progress"><div class="progress-bar" style="width: 96%"></div></div>
    <span class="progress-description">+1.2 points in 30 days</span>
  </div>
</div>
```

## 8. Card with card tools

**Anatomy:** `card` (AdminLTE adds the soft shadow) > `card-header` (`h3.card-title` floats left; `div.card-tools` floats right with badges and `button.btn.btn-tool`) / `card-body` / `card-footer` (`clearfix` when it floats buttons). **States:** `collapsed-card` hides body and footer (the tool shows `bi-plus-lg`). **Real app:** `data-lte-toggle="card-collapse"` / `"card-remove"` / `"card-maximize"`.

```html
<div class="card mb-4">
  <div class="card-header">
    <h3 class="card-title">Latest orders</h3>
    <div class="card-tools">
      <span class="badge text-bg-primary">3 new</span>
      <button type="button" class="btn btn-tool" aria-label="Collapse card"><svg class="bi bi-dash-lg" viewBox="0 0 16 16" aria-hidden="true"><path fill-rule="evenodd" d="M2 8a.5.5 0 0 1 .5-.5h11a.5.5 0 0 1 0 1h-11A.5.5 0 0 1 2 8"/></svg></button>
      <button type="button" class="btn btn-tool" aria-label="Remove card"><svg class="bi bi-x-lg" viewBox="0 0 16 16" aria-hidden="true"><path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/></svg></button>
    </div>
  </div>
  <div class="card-body">…</div>
  <div class="card-footer clearfix"><a href="#" class="btn btn-sm btn-secondary float-end">View all orders</a></div>
</div>
```

## 9. Card variants

**Anatomy:** `card card-{color} card-outline` = 3 px coloured top border (forms, profiles); `card card-{color}` = solid coloured header (its `btn-tool`s turn light); `card text-bg-{color}` = whole card coloured. Colours: primary, secondary, success, info, warning, danger, dark.

```html
<div class="card card-primary card-outline mb-4"><div class="card-header"><h3 class="card-title">Product details</h3></div><div class="card-body">…</div></div>
<div class="card card-success mb-4"><div class="card-header"><h3 class="card-title">Received</h3></div><div class="card-body">…</div></div>
<div class="card card-warning collapsed-card mb-4"><div class="card-header"><h3 class="card-title">Collapsed</h3><div class="card-tools"><button type="button" class="btn btn-tool" aria-label="Expand card"><svg class="bi bi-plus-lg" viewBox="0 0 16 16" aria-hidden="true"><path fill-rule="evenodd" d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2"/></svg></button></div></div><div class="card-body">…</div></div>
```

## 10. Callout

**Anatomy:** `div.callout.callout-{info|success|warning|danger|primary}` with `h5` + `p` (subtle background, 4 px left border, emphasis text). Page-level notes that are not alerts.

```html
<div class="callout callout-info">
  <h5>Cycle count week</h5>
  <p>Zone C in Rotterdam DC is frozen for counting until Wednesday 14 October.</p>
</div>
```

## 11. Alerts

**Anatomy:** Bootstrap `alert alert-{color}` (+ `alert-dismissible` and `btn-close`), heading `h5.alert-heading`, icon first. Use for form-level errors and system messages.

```html
<div class="alert alert-danger alert-dismissible" role="alert">
  <h5 class="alert-heading fs-6 fw-bold mb-1"><svg class="bi bi-exclamation-octagon me-1" viewBox="0 0 16 16" aria-hidden="true"><path d="M4.54.146A.5.5 0 0 1 4.893 0h6.214a.5.5 0 0 1 .353.146l4.394 4.394a.5.5 0 0 1 .146.353v6.214a.5.5 0 0 1-.146.353l-4.394 4.394a.5.5 0 0 1-.353.146H4.893a.5.5 0 0 1-.353-.146L.146 11.46A.5.5 0 0 1 0 11.107V4.893a.5.5 0 0 1 .146-.353zM5.1 1 1 5.1v5.8L5.1 15h5.8l4.1-4.1V5.1L10.9 1z"/><path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0M7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0z"/></svg> The product was not saved</h5>
  Fix 1 field: <a href="#reorder" class="alert-link">Reorder point</a>.
  <button type="button" class="btn-close" aria-label="Close"></button>
</div>
```

## 12. Buttons and badges

**Anatomy:** `btn btn-{color}` / `btn-outline-{primary|secondary|success|danger}` / `btn-sm|lg` / `disabled`; row actions `btn-group btn-group-sm` of outline icon buttons; `btn-tool` for card headers. Status badges `badge text-bg-{color}`: In stock → success, Low stock → warning, Out of stock → danger, Counting → info, Discontinued → secondary. AdminLTE keeps Bootstrap's shadows on buttons (`$enable-shadows`).

```html
<button type="button" class="btn btn-primary">Save product</button>
<button type="button" class="btn btn-secondary">Cancel</button>
<div class="btn-group btn-group-sm"><a href="#" class="btn btn-outline-secondary" aria-label="Edit FAS-M8-40-A2"><svg class="bi bi-pencil" viewBox="0 0 16 16" aria-hidden="true"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/></svg></a><button type="button" class="btn btn-outline-danger" aria-label="Delete FAS-M8-40-A2"><svg class="bi bi-trash" viewBox="0 0 16 16" aria-hidden="true"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button></div>
<span class="badge text-bg-warning">Low stock</span>
```

## 13. Tables in cards

**Anatomy:** `card-body p-0` > `table-responsive` > `table m-0` (+ `table-striped`, `table-hover`, `table-bordered`, `table-sm`, `align-middle`); AdminLTE pads the first/last cells of a `card-body.p-0` table by 1.5 rem. Selected rows `table-active`; checkboxes `form-check-input`; inline stock bars `progress progress-xs`; pagination in `card-footer clearfix` with `pagination pagination-sm m-0 float-end`. A bulk bar sits between header and table: `bg-primary-subtle border-bottom border-primary-subtle`.

```html
<div class="card-body p-0">
  <div class="table-responsive">
    <table class="table table-hover align-middle m-0">
      <thead><tr class="text-nowrap"><th scope="col">SKU</th><th scope="col">On hand</th><th scope="col">Status</th></tr></thead>
      <tbody>
        <tr class="table-active"><td>FAS-M8-40-A2</td><td><div class="fw-medium">18,400</div><div class="progress progress-xs"><div class="progress-bar text-bg-success" style="width: 82%"></div></div></td><td><span class="badge text-bg-success">In stock</span></td></tr>
      </tbody>
    </table>
  </div>
</div>
<div class="card-footer clearfix">
  <ul class="pagination pagination-sm m-0 float-end"><li class="page-item active" aria-current="page"><a class="page-link" href="#">1</a></li><li class="page-item"><a class="page-link" href="#">2</a></li></ul>
</div>
```

## 14. Card header tools: search and filters

**Anatomy:** in a `card-header`, a `row g-2 align-items-center` with the `card-title` and a `d-flex flex-wrap justify-content-md-end gap-2` of `input-group input-group-sm w-auto`, `form-select form-select-sm w-auto` and a `btn-sm` primary action (the AdminLTE "Users" page pattern).

```html
<div class="card-header">
  <div class="row g-2 align-items-center">
    <div class="col-12 col-md-4"><h3 class="card-title">Products</h3></div>
    <div class="col-12 col-md-8"><div class="d-flex flex-wrap justify-content-md-end gap-2">
      <div class="input-group input-group-sm w-auto"><span class="input-group-text"><svg class="bi bi-search" viewBox="0 0 16 16" aria-hidden="true"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/></svg></span><input type="search" class="form-control" placeholder="SKU or name" aria-label="Search products"></div>
      <select class="form-select form-select-sm w-auto" aria-label="Warehouse"><option>All warehouses</option></select>
      <a href="resource-form.html" class="btn btn-sm btn-primary">New product</a>
    </div></div>
  </div>
</div>
```

## 15. Forms (stacked and horizontal)

**Anatomy:** stacked `form-label` + `form-control|form-select|textarea`; horizontal rows `row mb-3` with `label.col-sm-3.col-form-label` + `div.col-sm-9`; help `form-text`; required `span.text-danger` + `required`; disabled attribute; sizes `form-control-sm`. Forms live in `card card-primary card-outline` with buttons in `card-footer` or the page's `app-content-bottom-area`.

```html
<div class="row mb-3">
  <label for="cost" class="col-sm-3 col-form-label">Unit cost <span class="text-danger" aria-hidden="true">*</span></label>
  <div class="col-sm-9"><div class="input-group"><span class="input-group-text">€</span><input type="text" class="form-control" id="cost" value="0.09"><span class="input-group-text">per piece</span></div></div>
</div>
<div class="mb-3"><label for="sku" class="form-label">SKU</label><input type="text" class="form-control" id="sku" value="FAS-M8-40-A2" disabled><div class="form-text">Fixed after the first receipt.</div></div>
```

## 16. Checks, radios, switches

**Anatomy:** `form-check` (+ `form-check-inline`) > `form-check-input` + `form-check-label`; switch `form-check form-switch` + `role="switch"`; settings rows `d-flex justify-content-between align-items-start py-2 border-bottom` with the switch at the end and a `visually-hidden` label.

```html
<div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="active" checked><label class="form-check-label" for="active">Active — can be ordered</label></div>
<div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="valuation" id="fifo" checked><label class="form-check-label" for="fifo">FIFO</label></div>
```

## 17. Validation states

**Anatomy:** `is-invalid` / `is-valid` on the control, `invalid-feedback` / `valid-feedback` after it; in input groups add `has-validation` and keep the feedback last; a dismissible `alert alert-danger` at the top links to the field.

```html
<div class="input-group has-validation">
  <input type="text" class="form-control is-invalid" id="reorder" value="25,000" aria-describedby="reorderFeedback">
  <span class="input-group-text">pieces</span>
  <div id="reorderFeedback" class="invalid-feedback">Must be lower than the maximum stock of 20,000 pieces.</div>
</div>
```

## 18. Upload zone and file list

**Anatomy (pattern):** a `label` for the file input styled `d-block text-center border border-2 rounded p-4 bg-body-tertiary` with a `bi-cloud-arrow-up` icon, then `input.form-control[type=file]`, then a `list-group` of files with a `btn-tool` remove button.

```html
<label for="files" class="d-block text-center border border-2 rounded p-4 mb-3 bg-body-tertiary">
  <svg class="bi bi-cloud-arrow-up fs-2 text-primary" viewBox="0 0 16 16" aria-hidden="true"><path fill-rule="evenodd" d="M7.646 5.146a.5.5 0 0 1 .708 0l2 2a.5.5 0 0 1-.708.708L8.5 6.707V10.5a.5.5 0 0 1-1 0V6.707L6.354 7.854a.5.5 0 1 1-.708-.708z"/><path d="M4.406 3.342A5.53 5.53 0 0 1 8 2c2.69 0 4.923 2 5.166 4.579C14.758 6.804 16 8.137 16 9.773 16 11.569 14.502 13 12.687 13H3.781C1.708 13 0 11.366 0 9.318c0-1.763 1.266-3.223 2.942-3.593.143-.863.698-1.723 1.464-2.383m.653.757c-.757.653-1.153 1.44-1.153 2.056v.448l-.445.049C2.064 6.805 1 7.952 1 9.318 1 10.785 2.23 12 3.781 12h8.906C13.98 12 15 10.988 15 9.773c0-1.216-1.02-2.228-2.313-2.228h-.5v-.5C12.188 4.825 10.328 3 8 3a4.53 4.53 0 0 0-2.941 1.1z"/></svg>
  <span class="d-block fw-bold mt-1">Drop files or click to upload</span>
  <span class="d-block small text-secondary">JPG, PNG or PDF, up to 10 MB</span>
</label>
<input type="file" class="form-control mb-3" id="files" multiple>
```

## 19. Drawn credential fields (`.mock-*`, mockup only)

**Purpose:** sign-in and password screens never take real credentials. **Anatomy:** `span.mock-label` (= `.form-label`), `div.mock-field` (= `.form-control` with AdminLTE's inset shadow, cloned rule for rule) with `role="img"` and an `aria-label`; modifiers `mock-field--secret`, `--empty`, `--focus`, `--error` (a following `invalid-feedback` shows), `--disabled`. Works inside `input-group` next to `input-group-text`. **Real code:** `<input class="form-control" type="email|password">`.

```html
<div class="input-group mb-3">
  <div class="mock-field" role="img" aria-label="Email field, filled in">rosa.delgado@kestrel-logistics.eu</div>
  <div class="input-group-text"><svg class="bi bi-envelope" viewBox="0 0 16 16" aria-hidden="true"><path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1zm13 2.383-4.708 2.825L15 11.105zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741M1 11.105l4.708-2.897L1 5.383z"/></svg></div>
</div>
<div class="input-group mb-3">
  <div class="mock-field mock-field--secret" role="img" aria-label="Password field, filled in">••••••••••••</div>
  <div class="input-group-text"><svg class="bi bi-lock-fill" viewBox="0 0 16 16" aria-hidden="true"><path fill-rule="evenodd" d="M8 0a4 4 0 0 1 4 4v2.05a2.5 2.5 0 0 1 2 2.45v5a2.5 2.5 0 0 1-2.5 2.5h-7A2.5 2.5 0 0 1 2 13.5v-5a2.5 2.5 0 0 1 2-2.45V4a4 4 0 0 1 4-4m0 1a3 3 0 0 0-3 3v2h6V4a3 3 0 0 0-3-3"/></svg></div>
</div>
<span class="mock-label">Confirm new password</span>
<div class="mock-field mock-field--secret mock-field--error" role="img" aria-label="Confirm password field, with an error">•••••••••••</div>
<div class="invalid-feedback">The passwords do not match.</div>
```

## 20. Login box

**Anatomy:** `body.login-page.bg-body-secondary` > `main.login-box` > `h1.login-logo > a` (`<b>Brand</b> Name`) + `card > card-body.login-card-body` (`p.login-box-msg`, drawn input groups, a `row` with remember-me `col-7` and the submit `col-5 > d-grid`, `social-auth-links text-center mb-3 d-grid gap-2`, forgot/register links).

```html
<body class="lte-mockup login-page bg-body-secondary">
<main class="login-box">
  <h1 class="login-logo"><a href="dashboard.html"><b>Kestrel</b> Inventory</a></h1>
  <div class="card"><div class="card-body login-card-body">
    <p class="login-box-msg">Sign in to start your session</p>
    <div class="row align-items-center">
      <div class="col-7"><div class="form-check"><input class="form-check-input" type="checkbox" id="remember"><label class="form-check-label" for="remember">Remember me</label></div></div>
      <div class="col-5"><div class="d-grid gap-2"><a href="dashboard.html" class="btn btn-primary" role="button">Sign In</a></div></div>
    </div>
    <p class="mb-1"><a href="#">I forgot my password</a></p>
  </div></div>
</main>
</body>
```

## 21. Progress and progress groups

**Anatomy:** Bootstrap `progress > progress-bar` (AdminLTE: 1 px radius), sizes `progress-sm` (10 px), `progress-xs` (7 px), `progress-xxs` (3 px); `progress-group` = label + `float-end` value + a small bar; bar colours `text-bg-{color}`; widths `w-25|50|75|100` or `style`.

```html
<div class="progress-group">
  Rotterdam DC <span class="float-end"><b>1,840</b>/2,000</span>
  <div class="progress progress-sm"><div class="progress-bar text-bg-primary" style="width: 92%"></div></div>
</div>
```

## 22. Timeline

**Anatomy:** `div.timeline` > `div.time-label > span.text-bg-{color}` (date) and plain `div`s each holding `timeline-icon` (+ `text-bg-{color}`) and `div.timeline-item` (`span.time`, `h3.timeline-header` with a link, `timeline-body`, `timeline-footer` with `btn-sm`s). End with a lone clock icon. In the mockup the icon is the SVG itself (`svg.timeline-icon` gets 7 px padding so the glyph stays 16 px in the 30 px disc).

```html
<div class="timeline">
  <div class="time-label"><span class="text-bg-danger">12 Oct 2026</span></div>
  <div>
    <svg class="timeline-icon bi bi-truck text-bg-primary" viewBox="0 0 16 16" aria-hidden="true"><path d="M0 3.5A1.5 1.5 0 0 1 1.5 2h9A1.5 1.5 0 0 1 12 3.5V5h1.02a1.5 1.5 0 0 1 1.17.563l1.481 1.85a1.5 1.5 0 0 1 .329.938V10.5a1.5 1.5 0 0 1-1.5 1.5H14a2 2 0 1 1-4 0H5a2 2 0 1 1-3.998-.085A1.5 1.5 0 0 1 0 10.5zm1.294 7.456A2 2 0 0 1 4.732 11h5.536a2 2 0 0 1 .732-.732V3.5a.5.5 0 0 0-.5-.5h-9a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .294.456M12 10a2 2 0 0 1 1.732 1h.768a.5.5 0 0 0 .5-.5V8.35a.5.5 0 0 0-.11-.312l-1.48-1.85A.5.5 0 0 0 13.02 6H12zm-9 1a1 1 0 1 0 0 2 1 1 0 0 0 0-2m9 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2"/></svg>
    <div class="timeline-item">
      <span class="time"><svg class="bi bi-clock-fill" viewBox="0 0 16 16" aria-hidden="true"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71z"/></svg> 08:14</span>
      <h3 class="timeline-header"><a href="#">PO-20931</a> arrived at dock 4</h3>
      <div class="timeline-body">26 pallets, 1,180 cartons.</div>
      <div class="timeline-footer"><a class="btn btn-primary btn-sm" href="#">Open receipt</a></div>
    </div>
  </div>
  <div><svg class="timeline-icon bi bi-clock-fill text-bg-secondary" viewBox="0 0 16 16" aria-hidden="true"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71z"/></svg></div>
</div>
```

## 23. Direct chat

**Anatomy:** `card.direct-chat.direct-chat-primary` > `card-body > div.direct-chat-messages` (fixed 250 px, scrolls) of `div.direct-chat-msg` (`.end` for your own) each with `direct-chat-infos clearfix` (`direct-chat-name`, `direct-chat-timestamp` floated), `direct-chat-img` (40 px avatar) and `direct-chat-text` (bubble; primary for `.end`); `card-footer` with an `input-group`. Keep messages short so the drawn log is not cut mid-bubble.

```html
<div class="card direct-chat direct-chat-primary mb-4">
  <div class="card-header"><h3 class="card-title">Warehouse chat</h3></div>
  <div class="card-body">
    <div class="direct-chat-messages" role="log" aria-label="Chat messages">
      <div class="direct-chat-msg end">
        <div class="direct-chat-infos clearfix"><span class="direct-chat-name float-end">Rosa Delgado</span><span class="direct-chat-timestamp float-start">12 Oct 08:16</span></div>
        <div class="direct-chat-text">Yes, PO-20931. M8 bolts to bin A-14.</div>
      </div>
    </div>
  </div>
  <div class="card-footer"><div class="input-group"><input type="text" class="form-control" placeholder="Type a message…" aria-label="Message"><button type="button" class="btn btn-primary">Send</button></div></div>
</div>
```

## 24. Navbar dropdown (drawn open)

**Anatomy:** `dropdown-menu dropdown-menu-lg` (AdminLTE: 280 px) with `span.dropdown-item.dropdown-header`, `dropdown-divider`s, `a.dropdown-item` rows (icon, text, `float-end text-secondary small` time) and `a.dropdown-item.dropdown-footer`. In the header it is `dropdown-menu-end` under the icon link; drawn open inside a page add `show position-static mw-100`.

```html
<div class="dropdown-menu dropdown-menu-lg show position-static mw-100" aria-label="Notifications">
  <span class="dropdown-item dropdown-header">7 notifications</span>
  <div class="dropdown-divider"></div>
  <a href="#" class="dropdown-item"><svg class="bi bi-exclamation-triangle me-2" viewBox="0 0 16 16" aria-hidden="true"><path d="M7.938 2.016A.13.13 0 0 1 8.002 2a.13.13 0 0 1 .063.016.15.15 0 0 1 .054.057l6.857 11.667c.036.06.035.124.002.183a.2.2 0 0 1-.054.06.1.1 0 0 1-.066.017H1.146a.1.1 0 0 1-.066-.017.2.2 0 0 1-.054-.06.18.18 0 0 1 .002-.183L7.884 2.073a.15.15 0 0 1 .054-.057m1.044-.45a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767z"/><path d="M7.002 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0M7.1 5.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0z"/></svg> 4 SKUs below reorder point <span class="float-end text-secondary small">3 min</span></a>
  <div class="dropdown-divider"></div>
  <a href="#" class="dropdown-item dropdown-footer">See all notifications</a>
</div>
```

## 25. Toasts

**Anatomy:** `toast-container position-fixed bottom-0 end-0 p-3` > `toast.show` with AdminLTE colour variants `toast-success` / `toast-danger` (coloured header, subtle body) > `toast-header` + `toast-body`.

```html
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div class="toast toast-success show" role="status" aria-live="polite" aria-atomic="true">
    <div class="toast-header"><strong class="me-auto">Settings saved</strong><small>just now</small><button type="button" class="btn-close" aria-label="Close"></button></div>
    <div class="toast-body">Low-stock alerts now go to rosa.delgado@kestrel-logistics.eu.</div>
  </div>
</div>
```

## 26. Modal (drawn open)

**Anatomy:** `div.modal-backdrop.show` then `div.modal.d-block` (`role="dialog" aria-modal="true" aria-labelledby`) > `modal-dialog modal-dialog-centered` > `modal-content` > `modal-header` / `modal-body` / `modal-footer justify-content-between` (AdminLTE's demo puts Cancel left, the action right). Inside a page section: wrapper `position-relative` with a min-height, backdrop and modal `position-absolute`.

```html
<div class="modal-backdrop show"></div>
<div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="mTitle">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h3 class="modal-title fs-5" id="mTitle">Discontinue 3 products?</h3><button type="button" class="btn-close" aria-label="Close"></button></div>
    <div class="modal-body">Open sales orders keep their lines.</div>
    <div class="modal-footer justify-content-between"><button type="button" class="btn btn-secondary">Cancel</button><button type="button" class="btn btn-danger">Discontinue</button></div>
  </div></div>
</div>
```

## 27. Empty state, charts, icons

**Empty state (pattern):** in a `card-body text-center py-5`: icon disc `d-inline-flex rounded-circle text-bg-primary bg-gradient fs-1 p-4 shadow-sm`, `h2.fs-4`, one sentence `text-secondary`, primary + outline action; a filtered table shows a single `td colspan` row. **Charts:** inline `svg.d-block.w-100.h-auto` with `role="img"` + a numeric `aria-label`, grid in `text-body-secondary`, series `text-primary` / `text-secondary` / `text-info` with `currentColor` (area, bars, donut via `stroke-dasharray`); legends `bi-square-fill text-primary`. **Icons:** `svg.bi.bi-{name}` (Bootstrap Icons 1.13) — real app `<i class="bi bi-{name}"></i>`.

```html
<div class="card-body text-center py-5">
  <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-bg-primary bg-gradient fs-1 p-4 mb-3 shadow-sm"><svg class="bi bi-truck" viewBox="0 0 16 16" aria-hidden="true"><path d="M0 3.5A1.5 1.5 0 0 1 1.5 2h9A1.5 1.5 0 0 1 12 3.5V5h1.02a1.5 1.5 0 0 1 1.17.563l1.481 1.85a1.5 1.5 0 0 1 .329.938V10.5a1.5 1.5 0 0 1-1.5 1.5H14a2 2 0 1 1-4 0H5a2 2 0 1 1-3.998-.085A1.5 1.5 0 0 1 0 10.5zm1.294 7.456A2 2 0 0 1 4.732 11h5.536a2 2 0 0 1 .732-.732V3.5a.5.5 0 0 0-.5-.5h-9a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .294.456M12 10a2 2 0 0 1 1.732 1h.768a.5.5 0 0 0 .5-.5V8.35a.5.5 0 0 0-.11-.312l-1.48-1.85A.5.5 0 0 0 13.02 6H12zm-9 1a1 1 0 1 0 0 2 1 1 0 0 0 0-2m9 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2"/></svg></span>
  <h2 class="fs-4">No suppliers yet</h2>
  <p class="text-secondary col-md-10 col-xl-8 mx-auto">Add the companies you buy from to raise purchase orders.</p>
  <a href="resource-form.html" class="btn btn-primary">Add a supplier</a>
</div>
```
