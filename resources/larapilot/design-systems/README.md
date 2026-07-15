# Design systems

Packaged visual references for Larapilot mockups. Copied to `.larapilot/design-systems/` on `larapilot:install` and refreshed on `larapilot:update`.

| System | Path | When to use |
| --- | --- | --- |
| **Filament** | `filament/` | Admin/control panel mockups when the PRD records **Filament** as the panel choice |
| **Laravel Starter Kits** | `starter-kit/` | Authenticated app UI when the PRD records a **Starter Kit** variant (`livewire`, `react`, `vue`, `svelte`) |
| **Bootstrap 5** | `bootstrap-5/` | Marketing site or app UI when the PRD records **Bootstrap 5** as the CSS framework |
| **Tailwind CSS** | `tailwind/` | Marketing site or custom app UI when the PRD records **Tailwind CSS** (without Filament or a Starter Kit) |
| **AdminLTE** | `adminlte/` | Admin/control panel mockups when the PRD records **[AdminLTE](https://adminlte.io/)** (Bootstrap 5.3 admin template) |

Filament includes packaged static HTML screens in `filament/html/` — merged from [Design System](https://www.figma.com/community/file/1413822581847485668/filament-3-design-system) and [UI Kit (Free)](https://www.figma.com/community/file/1417716904167561805/filament-3-free). Open `index.html` locally as a visual catalog. See `filament/figma-sources.md`.

**Starter Kits** include `tokens.css`, `components.md`, `sources.md`, and **7 static HTML screens** in `starter-kit/html/` — derived from the [official Laravel kits](https://laravel.com/starter-kits) (shadcn/Flux tokens). Open `starter-kit/html/index.html` as a visual catalog.

**Bootstrap 5** includes `tokens.css`, `components.md`, `sources.md`, and **6 static HTML screens** in `bootstrap-5/html/` — marketing landing, app dashboard, auth, settings, and components. Open `bootstrap-5/html/index.html` as a visual catalog.

**Tailwind CSS** includes `components.md`, `sources.md`, optional `tokens.css`, and **6 static HTML screens** in `tailwind/html/` — pure utility-class mockups (landing, dashboard, login, settings, components). Open `tailwind/html/index.html` as a visual catalog.

**AdminLTE** includes `tokens.css`, `components.md`, `sources.md`, and **6 static HTML screens** in `adminlte/html/` — derived from [AdminLTE 4](https://adminlte.io/) (Bootstrap 5.3, dark sidebar, `small-box` widgets). Open `adminlte/html/index.html` as a visual catalog.

Skills read these files via `php artisan larapilot:config-show` → `paths.design_systems`.
