# AdminLTE (mockup reference)

Visual contract for **admin dashboard / control panel** mockups when the PRD chose **[AdminLTE](https://adminlte.io/)** (Bootstrap 5.3 admin template).

## Source of truth

| Resource | URL |
| --- | --- |
| **Official site** | [adminlte.io](https://adminlte.io/) |
| **GitHub** | [ColorlibHQ/AdminLTE](https://github.com/ColorlibHQ/AdminLTE) |
| **Docs (v4)** | [AdminLTE 4 Getting Started](https://adminlte-v4.netlify.app/docs/getting-started) |
| **Layout blueprint** | [Layout Blueprint](https://adminlte-v4.netlify.app/docs/layout-blueprint) |
| **Variant index** | `sources.md` in this folder |

AdminLTE **v4** ships on Bootstrap **5.3**, vanilla TypeScript (no jQuery), dark sidebar, `app-wrapper` grid layout, and plugins wired via `data-lte-*` attributes. New projects should use v4 — not AdminLTE 3 (Bootstrap 4).

## When Elise must use this

Apply this design system when **all** of the following are true:

1. The screen is an **admin/control panel** (dashboard, CRUD list, forms, settings, login inside the admin theme).
2. The PRD `## Technical Architecture` records **AdminLTE** as the admin UI choice (or the project already uses `admin-lte` / AdminLTE Blade layouts).

Do **not** use this for Filament panels, Laravel Starter Kits, generic Bootstrap marketing pages, or Nordic minimal public UI — those follow their own design systems.

## Mockup implementation

1. **Load AdminLTE CDN** — in every AdminLTE mockup HTML:

   ```html
   <!-- CSS -->
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/css/adminlte.min.css">

   <!-- JS (before </body>) -->
   <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js"></script>
   <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js"></script>
   <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/js/adminlte.min.js"></script>
   ```

   Copy `tokens.css` into the mockup folder as `adminlte-tokens.css` only when using gallery helpers.

2. **Body classes** — app shell: `layout-fixed sidebar-expand-lg bg-body-tertiary`. Login: `login-page bg-body-secondary`.

3. **Layout** — follow `components.md` for `app-wrapper`, dark `app-sidebar`, `app-header`, `app-main`, cards, `small-box` widgets, and tables. **Starting points:** copy or adapt screens from `{paths.design_systems}/adminlte/html/` (catalog at `index.html`).

4. **Icons** — use **Bootstrap Icons** (`bi bi-*`) as in official AdminLTE demos.

5. **Dark mode** — sidebar uses `data-bs-theme="dark"` on `.app-sidebar`; document light/dark content toggles when the PRD requires both.

6. **Mobile** — `data-lte-toggle="sidebar"` on header hamburger; `sidebar-expand-lg` controls breakpoint. Document collapse behavior in mockup README.

## README contract (mockup folder)

When using this design system, the mockup `README.md` must include:

- **Design system:** AdminLTE v4 — link to [adminlte.io](https://adminlte.io/) and this folder
- **Panel screens** mapped to AdminLTE page types (dashboard, login, settings, resource list)
- **Skin / theme** — color skin or CSS variable overrides if any
- **Responsive & navigation** — sidebar collapse, treeview menus
- **Accessibility** — labels, focus, table headers, `aria-label` on icon buttons

## Downstream

- **Alex** implements with `admin-lte` npm/Composer package, Blade layouts, and Laravel AdminLTE integrations — mockups are layout/flow references.
- **Anne** tests sidebar toggle, mobile overlay, and form validation at mobile + desktop widths.
