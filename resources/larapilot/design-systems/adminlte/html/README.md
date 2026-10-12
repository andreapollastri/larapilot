# AdminLTE 4 — packaged screens

Open `index.html` for the catalog. Every screen links only `../tokens.css` (a self-contained AdminLTE 4.10 + Bootstrap 5.3.8 subset) — no CDN, no fonts, no scripts, no OverlayScrollbars — and follows the OS colour scheme; `dashboard-dark.html` is forced dark with `data-bs-theme="dark"`.

| File | Purpose |
| --- | --- |
| `dashboard.html` | Full shell, small-boxes, sales chart card with tools, latest orders, info-boxes, products list, direct chat |
| `dashboard-dark.html` | Warehouses overview in dark: info-boxes, bar chart, progress-groups, timeline, donut, callout |
| `resource-list.html` | Products table in a card: search/filters, bulk bar, selected rows, badges, row actions, pagination |
| `resource-form.html` | Edit product: outlined cards, horizontal rows, validation, upload zone, bottom action bar |
| `settings.html` | Profile box, section nav, pill tabs, switches, drawn password fields, danger card, toast |
| `login.html` | `login-page` / `login-box` with drawn credentials |
| `empty-state.html` | No records, no results, setup checklist |
| `components.html` | Every component and state |
| `icons.html` | 144 Bootstrap Icons glyphs, inline SVG |

Copy a screen into `.larapilot/mockups/{spec}/`, link `adminlte-tokens.css` (copied next to it) instead of `../tokens.css`, and adapt the content.
