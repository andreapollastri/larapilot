# Bootstrap 5 — packaged screens

Open `index.html` for the catalog. Every screen links only `../tokens.css` (a self-contained Bootstrap 5.3.8 subset) — no CDN, no fonts, no scripts — and follows the OS colour scheme; `dashboard-dark.html` is forced dark with `data-bs-theme="dark"`.

| File | Purpose |
| --- | --- |
| `landing.html` | Public site — navbar, hero with search, press strip, stay cards, features, testimonials, host pricing, FAQ accordion, footer |
| `dashboard.html` / `dashboard-dark.html` | Host app — dark navbar, offcanvas-lg sidebar, stat cards, SVG chart, today list, bookings table |
| `resource-list.html` | Bookings index — tabs, filters, selection + bulk bar, open row menu, pagination |
| `modal.html` | The bookings list with a confirmation modal open |
| `resource-form.html` | Listing editor — sections, validation, floating labels, input groups, upload zone, sticky actions |
| `settings.html` | Profile, notifications, payouts, security, danger zone, toast |
| `login.html` | Split sign-in with drawn credentials |
| `empty-state.html` | First-run and no-results states |
| `components.html` | Every component and state |
| `icons.html` | 144 Bootstrap Icons glyphs, inline SVG |

Copy a screen into `.larapilot/mockups/{spec}/`, rename the stylesheet link to `bootstrap-5-tokens.css` (copied next to it), and adapt the content.
