# Design systems

Packaged visual references for Larapilot mockups. Copied to `.larapilot/design-systems/` on `larapilot:install` and refreshed on `larapilot:update`.

| System | Path | When to use |
| --- | --- | --- |
| **Filament** | `filament/` | Admin/control panel mockups when the PRD records **Filament** as the panel choice |
| **Laravel Starter Kits** | `starter-kit/` | Authenticated app UI when the PRD records a **Starter Kit** variant (`livewire`, `react`, `vue`, `svelte`) |

Filament includes packaged static HTML screens in `filament/html/` — merged from [Design System](https://www.figma.com/community/file/1413822581847485668/filament-3-design-system) and [UI Kit (Free)](https://www.figma.com/community/file/1417716904167561805/filament-3-free). Open `index.html` locally as a visual catalog. See `filament/figma-sources.md`.

**Starter Kits** include `tokens.css`, `components.md`, `sources.md`, and **7 static HTML screens** in `starter-kit/html/` — derived from the [official Laravel kits](https://laravel.com/starter-kits) (shadcn/Flux tokens). Open `starter-kit/html/index.html` as a visual catalog.

Skills read these files via `php artisan larapilot:config-show` → `paths.design_systems`.
