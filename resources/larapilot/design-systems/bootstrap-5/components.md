# Bootstrap 5 — component & layout patterns

Reference for Elise when building Bootstrap mockups. Use native Bootstrap classes first; add `.bs-*` helpers from `tokens.css` for app/marketing shells.

**Packaged HTML:** static screens live in `html/` — open `html/index.html` for the catalog.

## App shell (sidebar layout)

```
┌──────────────┬────────────────────────────────────────┐
│  Sidebar     │  Topbar (breadcrumb, user menu)        │
│  (light bg)  ├────────────────────────────────────────┤
│  Brand       │  Content on tertiary-bg canvas         │
│  Nav groups  │  Cards, tables, forms                  │
└──────────────┴────────────────────────────────────────┘
```

- **Sidebar:** white body background, right border; active nav uses primary tint (`rgba(primary, 0.12)`).
- **Topbar:** sticky, border-bottom, breadcrumbs left, actions right.
- **Content:** `.bs-content` on `--bs-tertiary-bg` with white cards inside.

HTML skeleton:

```html
<body class="bs-mockup">
  <div class="bs-app">
    <aside class="bs-sidebar" aria-label="Sidebar">…</aside>
    <div class="bs-main">
      <header class="bs-topbar">…</header>
      <main class="bs-content">…</main>
    </div>
  </div>
</body>
```

## Marketing site shell

- **Header:** `.navbar.navbar-expand-lg` with logo, nav links, CTA button.
- **Hero:** `.bs-hero` with headline, lead, primary + outline buttons.
- **Features:** 3-column `.row` with `.bs-feature-icon` + card copy.
- **Footer:** `.bs-site-footer` with links and copyright.

## Page types

| Mockup screen | Bootstrap implementation |
| --- | --- |
| Landing | Blade layout + Bootstrap grid + utility classes |
| Dashboard | Sidebar shell + `.card` stat widgets + `.table` |
| Login | Centered `.card` on tertiary background |
| Settings | Form sections in cards inside app shell |
| Modal confirm | Bootstrap modal component |

## Buttons & forms

| Variant | Class | Use |
| --- | --- | --- |
| Primary | `btn btn-primary` | Save, CTA, Submit |
| Secondary | `btn btn-outline-secondary` | Cancel, Back |
| Danger | `btn btn-danger` | Delete |
| Link | `btn btn-link` | Tertiary actions |

- Use `.form-label`, `.form-control`, `.form-select`, `.form-check`.
- Validation: `.is-invalid` + `.invalid-feedback`.
- Input groups for search bars and prefixed fields.

## Components to reuse

| Component | Bootstrap class |
| --- | --- |
| Status badge | `badge text-bg-success` / `warning` / `danger` |
| Alert banner | `alert alert-info` |
| Toast | `toast` + `toast-container` |
| Pagination | `pagination` |
| Dropdown | `dropdown` |
| Tabs | `nav nav-tabs` + `tab-content` |

## Dark mode

Set `data-bs-theme="dark"` on `<html>`. Verify card, table, and sidebar contrast.

## Mobile

- Sidebar becomes horizontal scroll nav below 768px (see `tokens.css`).
- Navbar uses `navbar-toggler` + `collapse`.
- Stack grid columns; tables in `.table-responsive`.
