# Filament — Figma sources (merged)

Larapilot ships **one** merged Filament reference (`filament/`) built from two unofficial community Figma kits plus the official demo. Neither file is official Filament — when frames disagree, prefer [demo.filamentphp.com](https://demo.filamentphp.com/) and [Filament docs](https://filamentphp.com/docs/panels/themes).

## Community kits

| Kit | Figma | Author / license | Strength |
| --- | --- | --- | --- |
| **Design System** | [Design System](https://www.figma.com/community/file/1413822581847485668/filament-3-design-system) | Giovanni Zanin · CC BY 4.0 | End-to-end **admin flows** replicated from the Filament demo (list, create, edit, view, settings) |
| **UI Kit (Free)** | [UI Kit (Free)](https://www.figma.com/community/file/1417716904167561805/filament-3-free) | VhiWEB community · free tier | **Atomic components** — buttons, forms, navigation, cards, icons, dashboard widgets |

## How Larapilot merges them

| Layer | Source priority |
| --- | --- |
| **Page flows** (CRUD, auth, settings) | Design System frames → packaged `html/*.html` |
| **Dashboard widgets & charts** | UI Kit (Free) → `html/widgets-dashboard.html` |
| **Component inventory** (toggles, uploads, wizards, filters) | UI Kit (Free) + Design System → `html/components.html`, `components.md` |
| **CSS tokens & shell** | Filament defaults + both kits → `tokens.css` |

Packaged HTML is a **static merge** — not a Figma export. Elise uses it when the PRD chose Filament; adapt screens per spec, do not clone competitor products.

## Packaged HTML catalog

See `html/README.md` and open `html/index.html`. Screens tagged **DS** map primarily to the Design System file; **Free** map primarily to the UI Kit (Free) file; **Both** appear in both kits.

## Attribution

- Giovanni Zanin — [Design System](https://www.figma.com/community/file/1413822581847485668/filament-3-design-system) (CC BY 4.0)
- VhiWEB — [UI Kit (Free)](https://www.figma.com/community/file/1417716904167561805/filament-3-free) (community free kit)

Support the original creators in Figma when you use their frames for client work.
