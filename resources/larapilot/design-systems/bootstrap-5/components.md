# Bootstrap 5 — components

Every component the kit documents, with the **real Bootstrap 5.3 class names**. Snippets paste into a mockup that links `bootstrap-5-tokens.css` and into a Blade view that loads Bootstrap — the markup is the same; only icons change tag (`<svg class="bi bi-…">` in the mockup, `<i class="bi bi-…">` in the app). See them all rendered in `html/components.html`; full screens are in `html/`.

Conventions: light by default, dark through `data-bs-theme="dark"` (page or element). Interactive states are drawn with the classes Bootstrap's JS would add (`.show`, `.active`, `.collapsed`, `.is-invalid`). Icon-only controls carry `aria-label`; decorative icons are `aria-hidden="true"`.

---

## 1. Public page shell

**Purpose:** marketing and public pages. **Anatomy:** `<header>` with a `navbar navbar-expand-lg` in a `container`; `<main>` of `section.py-5` bands (alternate `bg-body-tertiary border-top border-bottom`); `<footer class="pt-5 border-top bg-body-tertiary">`. **Variants:** sticky navbar (`sticky-top`), dark CTA band (`text-bg-dark rounded-4`). **Do:** one `container` per band, `g-4 g-lg-5` rows. **Don't:** put `g-5` rows straight in a container on phones (it overflows by 12 px).

```html
<header>
  <nav class="navbar navbar-expand-lg bg-body border-bottom sticky-top" aria-label="Main">
    <div class="container">
      <a class="navbar-brand fw-semibold" href="landing.html">Bramble Stays</a>
      <button class="navbar-toggler" type="button" aria-controls="siteNav" aria-expanded="false" aria-label="Open the menu"><span class="navbar-toggler-icon"></span></button>
      <div class="collapse navbar-collapse" id="siteNav">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link active" aria-current="page" href="landing.html">Find a stay</a></li>
          <li class="nav-item"><a class="nav-link" href="#hosts">Become a host</a></li>
        </ul>
        <a class="btn btn-primary" href="login.html">List your home</a>
      </div>
    </div>
  </nav>
</header>
<main>
  <section class="py-5 bg-body-tertiary border-bottom"><div class="container">…</div></section>
</main>
<footer class="pt-5 border-top bg-body-tertiary"><div class="container">…</div></footer>
```

## 2. App shell (signed-in)

**Purpose:** the host app frame. **Anatomy:** dark top `navbar navbar-expand sticky-top bg-dark` with `data-bs-theme="dark"`; a `container-fluid > row`; sidebar column `col-lg-2 p-0 bg-body-tertiary border-end` holding `nav.offcanvas-lg.offcanvas-start` (static from `lg`, hidden off-canvas below); `main.col-lg-10 ms-sm-auto px-3 px-md-4 py-4`. **States:** phone — sidebar hidden, a `bi-list` button in the navbar (`d-lg-none`) opens it. **Do:** active item `nav-link active` + `aria-current="page"`; inactive items `nav-link link-body-emphasis`. **Don't:** invent a `.sidebar` class.

```html
<header class="navbar navbar-expand sticky-top bg-dark flex-nowrap p-0 shadow" data-bs-theme="dark">
  <a class="navbar-brand col-lg-2 me-0 px-3 py-3 fs-6 fw-semibold" href="dashboard.html">Bramble Stays</a>
  <ul class="navbar-nav flex-row align-items-center ms-auto px-2">
    <li class="nav-item d-lg-none"><button class="nav-link px-2" type="button" aria-label="Open the host menu" aria-controls="hostMenu"><svg class="bi bi-list fs-4" viewBox="0 0 16 16" aria-hidden="true"><path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5"/></svg></button></li>
  </ul>
</header>
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-2 p-0 bg-body-tertiary border-end">
      <nav class="offcanvas-lg offcanvas-start bg-body-tertiary" tabindex="-1" id="hostMenu" aria-label="Host menu">
        <div class="offcanvas-header"><p class="offcanvas-title h5 mb-0">Bramble Stays</p><button type="button" class="btn-close" aria-label="Close the host menu"></button></div>
        <div class="offcanvas-body d-lg-flex flex-column p-0 pt-lg-3">
          <ul class="nav nav-pills flex-column gap-1 px-2">
            <li class="nav-item"><a class="nav-link active" aria-current="page" href="dashboard.html">Overview</a></li>
            <li class="nav-item"><a class="nav-link link-body-emphasis d-flex align-items-center gap-2" href="resource-list.html">Bookings <span class="badge rounded-pill text-bg-warning ms-auto">6</span></a></li>
          </ul>
        </div>
      </nav>
    </div>
    <main class="col-lg-10 ms-sm-auto px-3 px-md-4 py-4">…</main>
  </div>
</div>
```

## 3. Page header

**Purpose:** title, context line and page actions. **Anatomy:** optional `breadcrumb small`; `d-flex flex-wrap justify-content-between align-items-center gap-3 pb-3 mb-4 border-bottom`; `h1.h2`; actions in `btn-toolbar gap-2`. **Do:** one primary action, the rest outline/secondary.

```html
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 pb-3 mb-4 border-bottom">
  <div>
    <h1 class="h2 mb-1">Bookings</h1>
    <p class="text-body-secondary mb-0">128 bookings across 4 listings</p>
  </div>
  <div class="btn-toolbar gap-2" role="toolbar" aria-label="Page actions">
    <div class="btn-group btn-group-sm" role="group" aria-label="Share and export">
      <button type="button" class="btn btn-outline-secondary">Share</button>
      <button type="button" class="btn btn-outline-secondary">Export</button>
    </div>
    <a class="btn btn-sm btn-primary" href="resource-form.html">New listing</a>
  </div>
</div>
```

## 4. Buttons

**Anatomy:** `btn` + one of `btn-primary|secondary|success|danger|warning|info|light|dark|link` or `btn-outline-*`; sizes `btn-lg`, `btn-sm`; states `active` (+ `aria-pressed`), `disabled` attribute; full width `w-100` or a `d-grid` wrapper; loading = `spinner-border spinner-border-sm` inside. **Do:** icon + text with `d-inline-flex align-items-center gap-2`. **Don't:** use `btn-outline-warning`/`btn-outline-info` (text under 2:1 on white).

```html
<button type="button" class="btn btn-primary">Save listing</button>
<button type="button" class="btn btn-outline-secondary">Cancel</button>
<button type="button" class="btn btn-danger btn-sm">Decline</button>
<button type="button" class="btn btn-primary" disabled>Save and publish</button>
<button type="button" class="btn btn-outline-secondary" aria-label="Edit listing"><svg class="bi bi-pencil" viewBox="0 0 16 16" aria-hidden="true"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/></svg></button>
<button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" disabled><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Saving…</button>
```

## 5. Button group, toolbar, toggle buttons

**Anatomy:** `btn-group` (`btn-group-sm`) with `role="group"` + `aria-label`; `btn-toolbar` to space groups; toggle sets use `input.btn-check` + `label.btn`. **States:** the pressed button is `active` / the checked `btn-check`.

```html
<div class="btn-group" role="group" aria-label="View">
  <button type="button" class="btn btn-outline-primary active" aria-pressed="true">List</button>
  <button type="button" class="btn btn-outline-primary">Map</button>
</div>
<div class="btn-group" role="group" aria-label="Nights">
  <input type="radio" class="btn-check" name="nights" id="n2" checked><label class="btn btn-outline-secondary" for="n2">2 nights</label>
  <input type="radio" class="btn-check" name="nights" id="n7"><label class="btn btn-outline-secondary" for="n7">A week</label>
</div>
```

## 6. Badges

**Anatomy:** `badge` + `text-bg-{color}` (solid, contrast-safe) or `bg-{color}-subtle text-{color}-emphasis border border-{color}-subtle` (soft); `rounded-pill` for counts; a notification dot is a `position-absolute top-0 start-100 translate-middle` badge inside a `position-relative` button. **Status mapping:** Confirmed → success, Request → warning, Awaiting payment → info, Cancelled → secondary, Overdue → danger.

```html
<span class="badge text-bg-success">Confirmed</span>
<span class="badge text-bg-warning">Request · 21 h left</span>
<span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">+17% on 2025</span>
<span class="badge rounded-pill text-bg-primary">14</span>
<button type="button" class="btn btn-outline-secondary position-relative" aria-label="Notifications, 3 unread">
  <svg class="bi bi-bell" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2M8 1.918l-.797.161A4 4 0 0 0 4 6c0 .628-.134 2.197-.459 3.742-.16.767-.376 1.566-.663 2.258h10.244c-.287-.692-.502-1.49-.663-2.258C12.134 8.197 12 6.628 12 6a4 4 0 0 0-3.203-3.92zM14.22 12c.223.447.481.801.78 1H1c.299-.199.557-.553.78-1C2.68 10.2 3 6.88 3 6c0-2.42 1.72-4.44 4.005-4.901a1 1 0 1 1 1.99 0A5 5 0 0 1 13 6c0 .88.32 4.2 1.22 6"/></svg><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">3</span>
</button>
```

## 7. Alerts

**Anatomy:** `alert alert-{color}` with `role="alert"` (or `status`); optional `alert-heading`, `alert-link`; dismissible = `alert-dismissible` + `btn-close`. With an icon: `d-flex align-items-baseline gap-2`. **Do:** keep the link inline in the sentence so it wraps on phones.

```html
<div class="alert alert-warning d-flex align-items-baseline gap-2" role="alert">
  <svg class="bi bi-exclamation-triangle-fill flex-shrink-0" viewBox="0 0 16 16" aria-hidden="true"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/></svg>
  <div>Two booking requests expire in less than 24 hours. <a href="resource-list.html" class="alert-link">Review requests</a></div>
</div>
<div class="alert alert-success alert-dismissible" role="alert">
  Payout of €1,036.00 sent.<button type="button" class="btn-close" aria-label="Close"></button>
</div>
```

## 8. Cards

**Anatomy:** `card` > `card-header` / `card-body` (`card-title`, `card-subtitle`, `card-text`, `card-link`) / `card-footer`; image on top `card-img-top`; whole-card link `stretched-link` inside a `position-relative` card; highlighted `border-primary` with a `text-bg-primary` header. **Stat card pattern:** label `small text-body-secondary`, number `fs-3 fw-semibold`, delta `text-success-emphasis`.

```html
<div class="card h-100">
  <div class="card-body">
    <p class="text-body-secondary small mb-1">Revenue · October</p>
    <p class="fs-3 fw-semibold mb-0">€18,420</p>
    <p class="small mb-0 mt-2"><span class="text-success-emphasis fw-semibold">↑ 12.4%</span> <span class="text-body-secondary">vs September</span></p>
  </div>
</div>
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h6 mb-0">Upcoming bookings</h2><a class="btn btn-sm btn-outline-secondary" href="resource-list.html">All bookings</a></div>
  <div class="card-body">…</div>
  <div class="card-footer text-body-secondary small">Updated 5 minutes ago</div>
</div>
```

## 9. List group

**Anatomy:** `list-group` (`list-group-flush` inside cards) of `list-group-item`; links get `list-group-item-action`; states `active` (+ `aria-current`), `disabled`. Rows with avatar + text + badge use `d-flex align-items-center gap-3`. Radio rows: `label.list-group-item` wrapping `input.form-check-input`.

```html
<ul class="list-group list-group-flush">
  <li class="list-group-item d-flex align-items-center gap-3 py-3">
    <div class="flex-grow-1 lh-sm"><div class="fw-semibold">Priya Raman</div><div class="small text-body-secondary">Willow Cottage · 2 guests</div></div>
    <span class="badge text-bg-success">In 15:00</span>
  </li>
</ul>
<div class="list-group">
  <a href="#" class="list-group-item list-group-item-action active" aria-current="true">Profile</a>
  <a href="#" class="list-group-item list-group-item-action">Notifications</a>
</div>
```

## 10. Tables

**Anatomy:** `table-responsive` > `table` + `table-hover`, `table-striped`, `table-sm`, `align-middle`, `text-nowrap` (phones scroll the table); `thead` with `th scope="col"`; selected row `table-active`; numbers `text-end`; sortable header = a `link-body-emphasis text-decoration-none` link with an arrow icon and `aria-sort`; selection = `form-check-input` in the first column; inside a card add `mb-0`. **Bulk bar:** a `card-header bg-primary-subtle border-primary-subtle` with the count and actions.

```html
<div class="table-responsive">
  <table class="table table-hover align-middle text-nowrap mb-0">
    <thead><tr><th scope="col"><input class="form-check-input" type="checkbox" aria-label="Select all"></th><th scope="col" aria-sort="ascending">Check-in</th><th scope="col">Status</th><th scope="col" class="text-end">Total</th></tr></thead>
    <tbody>
      <tr class="table-active"><td><input class="form-check-input" type="checkbox" checked aria-label="Select BR-20477"></td><td>Mon 12 Oct</td><td><span class="badge text-bg-success">Confirmed</span></td><td class="text-end">€1,036.00</td></tr>
      <tr><td><input class="form-check-input" type="checkbox" aria-label="Select BR-20511"></td><td>Sun 18 Oct</td><td><span class="badge text-bg-warning">Request</span></td><td class="text-end">€298.00</td></tr>
    </tbody>
  </table>
</div>
```

## 11. Navs & tabs

**Anatomy:** `nav` + `nav-tabs` | `nav-pills` | `nav-underline`; `nav-item` > `nav-link` (`active` + `aria-current="page"`, `disabled` + `aria-disabled`). Counts are badges inside the link. On phones a long tab row gets `flex-nowrap overflow-x-auto text-nowrap`.

```html
<ul class="nav nav-tabs mb-4">
  <li class="nav-item"><a class="nav-link active" aria-current="page" href="#">Details</a></li>
  <li class="nav-item"><a class="nav-link" href="#">Photos <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">14</span></a></li>
  <li class="nav-item"><a class="nav-link disabled" aria-disabled="true">Channel sync</a></li>
</ul>
<ul class="nav nav-underline flex-nowrap overflow-x-auto text-nowrap">
  <li class="nav-item"><a class="nav-link active" aria-current="page" href="#">All <span class="badge rounded-pill text-bg-secondary">128</span></a></li>
  <li class="nav-item"><a class="nav-link link-body-emphasis" href="#">Requests <span class="badge rounded-pill text-bg-warning">6</span></a></li>
</ul>
```

## 12. Breadcrumb

**Anatomy:** `nav[aria-label="breadcrumb"] > ol.breadcrumb > li.breadcrumb-item`; last item `active` + `aria-current="page"`.

```html
<nav aria-label="breadcrumb">
  <ol class="breadcrumb small mb-2">
    <li class="breadcrumb-item"><a href="empty-state.html">Listings</a></li>
    <li class="breadcrumb-item active" aria-current="page">Willow Cottage</li>
  </ol>
</nav>
```

## 13. Pagination

**Anatomy:** `nav > ul.pagination` (`pagination-sm`) of `page-item` > `page-link`; current `active` + `aria-current="page"`; unavailable `disabled`. Pair with "Showing 1–8 of 128" in a `card-footer d-flex justify-content-between`.

```html
<nav aria-label="Bookings pages">
  <ul class="pagination pagination-sm mb-0">
    <li class="page-item disabled"><a class="page-link" aria-disabled="true">Previous</a></li>
    <li class="page-item active" aria-current="page"><a class="page-link" href="#">1</a></li>
    <li class="page-item"><a class="page-link" href="#">2</a></li>
    <li class="page-item"><a class="page-link" href="#">Next</a></li>
  </ul>
</nav>
```

## 14. Form controls

**Anatomy:** `form-label` + `form-control` (`form-control-sm|lg`) / `form-select` / `textarea.form-control` / `input.form-control[type=file]`; help `form-text` linked with `aria-describedby`; disabled = `disabled` attribute; read-only text = `form-control-plaintext`. Required mark: `<span class="text-danger" aria-hidden="true">*</span>` + `required`. Layout with `row g-3` and `col-md-*`; sections are cards with a `card-header bg-body py-3` title + `small text-body-secondary` description.

```html
<div class="mb-3">
  <label for="title" class="form-label">Listing title <span class="text-danger" aria-hidden="true">*</span></label>
  <input type="text" class="form-control" id="title" value="Willow Cottage" required aria-describedby="titleHelp">
  <div id="titleHelp" class="form-text">Up to 60 characters.</div>
</div>
<div class="row g-3">
  <div class="col-md-6"><label for="type" class="form-label">Property type</label><select class="form-select" id="type"><option>Cottage</option></select></div>
  <div class="col-md-6"><label for="lid" class="form-label">Listing ID</label><input type="text" class="form-control" id="lid" value="BR-L-0042" disabled></div>
</div>
```

## 15. Checks, radios, switches

**Anatomy:** `form-check` > `form-check-input` + `form-check-label`; inline `form-check-inline`; switch = `form-check form-switch` + `role="switch"`; states `checked`, `disabled`. A description goes in `span.d-block.small.text-body-secondary` inside the label.

```html
<div class="form-check"><input class="form-check-input" type="checkbox" id="wifi" checked><label class="form-check-label" for="wifi">Wi-Fi</label></div>
<div class="form-check"><input class="form-check-input" type="radio" name="policy" id="flex" checked><label class="form-check-label" for="flex">Flexible <span class="d-block small text-body-secondary">Full refund up to 7 days before arrival</span></label></div>
<div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="instant" checked><label class="form-check-label" for="instant">Instant booking</label></div>
```

## 16. Input group

**Anatomy:** `input-group` (`input-group-sm`) of `input-group-text`, `form-control`, `form-select`, `btn`. Stepper = outline buttons around a centred control. With validation add `has-validation` and put `invalid-feedback` last.

```html
<div class="input-group">
  <span class="input-group-text">€</span>
  <input type="text" class="form-control" value="142.00" aria-label="Nightly price">
  <span class="input-group-text">/ night</span>
</div>
<div class="input-group">
  <input type="search" class="form-control" placeholder="Promo code" aria-label="Promo code">
  <button class="btn btn-outline-secondary" type="button">Apply</button>
</div>
```

## 17. Floating labels

**Anatomy:** `form-floating` > `form-control|form-select` (needs a `placeholder`) + `label` after it.

```html
<div class="form-floating">
  <input type="text" class="form-control" id="town" placeholder="Town" value="Ambleside">
  <label for="town">Town</label>
</div>
```

## 18. Validation states

**Anatomy:** `is-invalid` / `is-valid` on the control; message in `invalid-feedback` / `valid-feedback` right after it (shown by the state class); a summary `alert alert-danger` at the top links to the field. Works on `form-control`, `form-select`, `form-check-input`, inside `input-group has-validation` and `form-floating`.

```html
<label for="minStay" class="form-label">Minimum stay <span class="text-danger" aria-hidden="true">*</span></label>
<div class="input-group has-validation">
  <input type="text" class="form-control is-invalid" id="minStay" value="0" aria-describedby="minStayFeedback">
  <span class="input-group-text">nights</span>
  <div id="minStayFeedback" class="invalid-feedback">Enter at least 1 night.</div>
</div>
<input type="text" class="form-control is-valid" value="LA22 0EH" aria-label="Postcode">
<div class="valid-feedback">Postcode found: Ambleside.</div>
```

## 19. Drawn credential fields (`.mock-*`, mockup only)

**Purpose:** sign-in screens never take real credentials. **Anatomy:** `span.mock-label` (= `.form-label`), `div.mock-field` (= `.form-control`, cloned rule for rule) with `role="img"` and an `aria-label`; modifiers `mock-field--secret` (bullets), `--empty` (placeholder colour), `--focus` (focus ring), `--error` (invalid border + icon; a following `invalid-feedback` shows), `--disabled`. Works inside `input-group`. Submit is an `a.btn` with `role="button"`. **Real code:** `<input class="form-control" type="email">` / `type="password"`.

```html
<div class="mb-3">
  <span class="mock-label">Email address</span>
  <div class="mock-field" role="img" aria-label="Email field, filled in">amara.okafor@brambleandco.uk</div>
</div>
<div class="mb-3">
  <span class="mock-label">Password</span>
  <div class="mock-field mock-field--secret mock-field--focus" role="img" aria-label="Password field, filled in">••••••••••••</div>
</div>
<div class="mock-field mock-field--error" role="img" aria-label="Email field, with an error">amara@bramble</div>
<div class="invalid-feedback">Enter a full email address.</div>
<a href="dashboard.html" class="btn btn-primary btn-lg w-100" role="button">Sign in</a>
```

## 20. Dropdown (drawn open)

**Anatomy:** `dropdown` > toggle `btn dropdown-toggle` (`show`, `aria-expanded="true"`) + `ul.dropdown-menu.show` with **`data-bs-popper="static"`** (Bootstrap then positions it under the toggle without Popper); `dropdown-menu-end` aligns right; items `dropdown-item` (`active`, `disabled`), `dropdown-header`, `dropdown-divider`. Leave room below an open menu (it is absolutely positioned). Row menus inside `table-responsive` sit in an upper row.

```html
<div class="dropdown">
  <button class="btn btn-secondary dropdown-toggle show" type="button" aria-expanded="true">Sort by</button>
  <ul class="dropdown-menu show" data-bs-popper="static">
    <li><h6 class="dropdown-header">Order</h6></li>
    <li><a class="dropdown-item active" aria-current="true" href="#">Check-in date</a></li>
    <li><a class="dropdown-item disabled" aria-disabled="true">Rating</a></li>
    <li><hr class="dropdown-divider"></li>
    <li><a class="dropdown-item text-danger" href="#">Cancel booking</a></li>
  </ul>
</div>
```

## 21. Modal (drawn open)

**Anatomy:** `div.modal-backdrop.fade.show` then `div.modal.fade.show.d-block` (`role="dialog" aria-modal="true" aria-labelledby`) > `modal-dialog` (`modal-dialog-centered`, `modal-sm|lg`) > `modal-content` > `modal-header` (`modal-title` + `btn-close`) / `modal-body` / `modal-footer`. For a modal inside a page section (catalog), give the wrapper `position-relative` and the backdrop/modal `position-absolute`. **Don't** stack two modals.

```html
<div class="modal-backdrop fade show"></div>
<div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="cancelTitle">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h2 class="modal-title fs-5" id="cancelTitle">Cancel 2 bookings?</h2><button type="button" class="btn-close" aria-label="Close"></button></div>
      <div class="modal-body"><p class="mb-0">Both guests are refunded in full.</p></div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary">Keep bookings</button><button type="button" class="btn btn-danger">Cancel and refund €1,632</button></div>
    </div>
  </div>
</div>
```

## 22. Toasts

**Anatomy:** `toast-container position-fixed bottom-0 end-0 p-3` > `toast.show` (`role="status" aria-live="polite"`) > `toast-header` (icon, `strong.me-auto`, time, `btn-close`) + `toast-body`. Coloured variant: `toast text-bg-primary border-0` with `btn-close-white`.

```html
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div class="toast show" role="status" aria-live="polite" aria-atomic="true">
    <div class="toast-header"><strong class="me-auto">Profile saved</strong><small class="text-body-secondary">just now</small><button type="button" class="btn-close" aria-label="Close"></button></div>
    <div class="toast-body">Guests will see your new profile on all 4 listings.</div>
  </div>
</div>
```

## 23. Progress & spinners

**Anatomy:** `div.progress` with `role="progressbar"` + `aria-valuenow/min/max` + `aria-label` > `progress-bar` (`bg-success|warning|…`, `progress-bar-striped`); width via `w-25|50|75|100` or `style="width: 82%"`; thin bars `style="height: 6px"` on `.progress`; stacked = `progress-stacked`. Spinners: `spinner-border` / `spinner-grow` (+ `-sm`) with a `visually-hidden` label.

```html
<div class="d-flex justify-content-between small mb-1"><span>Willow Cottage</span><span class="fw-semibold">91%</span></div>
<div class="progress mb-3" role="progressbar" aria-label="Willow Cottage occupancy" aria-valuenow="91" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width: 91%"></div></div>
<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
```

## 24. Accordion

**Anatomy:** `accordion` > `accordion-item` > `h3.accordion-header` > `button.accordion-button` (`collapsed` when closed, `aria-expanded`) + `accordion-collapse collapse` (`show` when open) > `accordion-body`. `accordion-flush` drops outer borders. Draw exactly one item open.

```html
<div class="accordion" id="faq">
  <div class="accordion-item">
    <h3 class="accordion-header"><button class="accordion-button" type="button" aria-expanded="true" aria-controls="q1">Is there a booking fee?</button></h3>
    <div id="q1" class="accordion-collapse collapse show"><div class="accordion-body">No. Guests pay the nightly price and cleaning fee only.</div></div>
  </div>
  <div class="accordion-item">
    <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" aria-expanded="false" aria-controls="q2">Can I bring my dog?</button></h3>
    <div id="q2" class="accordion-collapse collapse"><div class="accordion-body">860 homes welcome dogs.</div></div>
  </div>
</div>
```

## 25. Offcanvas

**Anatomy:** `offcanvas offcanvas-start|end|bottom` > `offcanvas-header` (`offcanvas-title`, `btn-close`) + `offcanvas-body`. Drawn open inside a page: add `show position-static mw-100` in a bordered wrapper. Responsive sidebar: `offcanvas-lg` (static from `lg`, see App shell).

```html
<div class="border rounded-3 overflow-hidden d-flex">
  <div class="offcanvas offcanvas-start show position-static mw-100" tabindex="-1" aria-labelledby="filtersTitle">
    <div class="offcanvas-header border-bottom"><h3 class="offcanvas-title h5" id="filtersTitle">Filters</h3><button type="button" class="btn-close" aria-label="Close"></button></div>
    <div class="offcanvas-body"><button type="button" class="btn btn-primary w-100">Show 6 bookings</button></div>
  </div>
</div>
```

## 26. Placeholders (loading)

**Anatomy:** `placeholder-glow` / `placeholder-wave` on the parent; `span.placeholder` sized with `col-*`; `placeholder-lg|sm|xs`; image boxes = `ratio ratio-16x9 > span.placeholder`. Hide from assistive tech with `aria-hidden="true"`.

```html
<div class="card" aria-hidden="true">
  <div class="card-body">
    <h3 class="card-title placeholder-glow h5"><span class="placeholder col-6"></span></h3>
    <p class="card-text placeholder-glow"><span class="placeholder col-7"></span> <span class="placeholder col-4"></span></p>
    <a class="btn btn-primary disabled placeholder col-4" aria-disabled="true"></a>
  </div>
</div>
```

## 27. Empty state

**Anatomy (pattern, utilities only):** `text-center bg-body-tertiary border rounded-3 py-5 px-3`; icon in `d-inline-flex p-3 rounded-circle bg-primary-subtle text-primary-emphasis fs-2`; `h2.h4`; one sentence in `text-body-secondary col-md-10 col-lg-8 mx-auto`; primary + secondary action. A filtered table with no rows shows the same inside its `card-body` with a "Clear filters" button.

```html
<div class="text-center bg-body-tertiary border rounded-3 py-5 px-3">
  <span class="d-inline-flex p-3 mb-3 rounded-circle bg-primary-subtle text-primary-emphasis fs-2"><svg class="bi bi-house-add" viewBox="0 0 16 16" aria-hidden="true"><path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L2 8.207V13.5A1.5 1.5 0 0 0 3.5 15h4a.5.5 0 1 0 0-1h-4a.5.5 0 0 1-.5-.5V7.207l5-5 6.646 6.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293z"/><path d="M16 12.5a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0m-3.5-2a.5.5 0 0 0-.5.5v1h-1a.5.5 0 0 0 0 1h1v1a.5.5 0 1 0 1 0v-1h1a.5.5 0 1 0 0-1h-1v-1a.5.5 0 0 0-.5-.5"/></svg></span>
  <h2 class="h4 fw-semibold">List your first stay</h2>
  <p class="text-body-secondary col-md-10 col-lg-8 mx-auto">Add photos, set a nightly price and choose when guests can book.</p>
  <a class="btn btn-primary" href="resource-form.html">Create a listing</a>
</div>
```

## 28. Icons (Bootstrap Icons)

**Anatomy:** mockup `svg.bi.bi-{name}` with `viewBox="0 0 16 16"` and `aria-hidden="true"`, glyph paths copied from Bootstrap Icons 1.13 (`html/icons.html` has 144). Size with `fs-*`, colour with `text-*`; feature tiles use `d-inline-flex text-bg-primary bg-gradient fs-4 rounded-3 p-3`. **Real app:**

```html
<i class="bi bi-people" aria-hidden="true"></i>
<!-- mockup: --><svg class="bi bi-people" viewBox="0 0 16 16" aria-hidden="true"><path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1zm-7.978-1L7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002-.014.002zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4m3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0M6.936 9.28a6 6 0 0 0-1.23-.247A7 7 0 0 0 5 9c-4 0-5 3-5 4q0 1 1 1h4.216A2.24 2.24 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816M4.92 10A5.5 5.5 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0m3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4"/></svg>
<div class="d-inline-flex align-items-center justify-content-center text-bg-primary bg-gradient fs-4 rounded-3 p-3"><svg class="bi bi-house-check" viewBox="0 0 16 16" aria-hidden="true"><path d="M7.293 1.5a1 1 0 0 1 1.414 0L11 3.793V2.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v3.293l2.354 2.353a.5.5 0 0 1-.708.708L8 2.207l-5 5V13.5a.5.5 0 0 0 .5.5h4a.5.5 0 0 1 0 1h-4A1.5 1.5 0 0 1 2 13.5V8.207l-.646.647a.5.5 0 1 1-.708-.708z"/><path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m1.679-4.493-1.335 2.226a.75.75 0 0 1-1.174.144l-.774-.773a.5.5 0 0 1 .708-.707l.547.547 1.17-1.951a.5.5 0 1 1 .858.514"/></svg></div>
```

## 29. Charts (inline SVG)

**Purpose:** dashboards without canvas. **Anatomy:** `svg.d-block.w-100.h-auto` with a `viewBox`, `role="img"` and an `aria-label` that states the numbers; grid + labels in a `g.text-body-secondary` (`stroke="currentColor" stroke-opacity=".18"`, `fill="currentColor"` text, 11 px); series are paths with `class="text-primary"` (headline, gradient area via a `linearGradient class="text-primary"` with `stop-color="currentColor"`) and `class="text-secondary"` (comparison, `stroke-dasharray`). Legend: `bi-circle-fill text-primary` + label. **Real app:** Chart.js/ApexCharts with `--bs-primary`.

```html
<svg class="d-block w-100 h-auto" viewBox="0 0 560 250" role="img" aria-label="Weekly revenue, July to October 2026: from €3.1k to €4.9k">
  <g class="text-body-secondary" font-size="11"><path d="M40 218H538" stroke="currentColor" stroke-opacity=".18"/><text x="32" y="222" text-anchor="end" fill="currentColor">€0</text></g>
  <path class="text-primary" d="M40 120 C120 110 200 80 280 90 S440 70 538 60" fill="none" stroke="currentColor" stroke-width="2.5"/>
</svg>
```

## 30. Avatars and photos (mockup stand-ins)

**Anatomy:** initials avatar = `svg.rounded-circle.bg-{color}-subtle.text-{color}-emphasis` (`width`/`height` 28–64) with a centred `text fill="currentColor"`; photo = an inline SVG illustration with `card-img-top` or `d-block w-100 h-auto rounded-4`. **Real app:** `<img class="rounded-circle" width="32" height="32" src="…" alt="">` and `<img class="card-img-top" src="…" alt="…">`.

```html
<svg class="rounded-circle bg-success-subtle text-success-emphasis flex-shrink-0" width="36" height="36" viewBox="0 0 36 36" aria-hidden="true"><text x="18" y="23" text-anchor="middle" font-size="14" font-weight="600" fill="currentColor">AO</text></svg>
```
