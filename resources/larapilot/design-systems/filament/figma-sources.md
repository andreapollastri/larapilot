# Filament — Figma sources

Two unofficial community Figma kits exist for Filament. Both were drawn from **Filament 3** and its demo;
Larapilot's packaged `filament/` reference now targets **Filament 4** and was rebuilt from Filament's own
source CSS and documentation screenshots (see `sources.md`). Use the kits for extra frames or a designer
hand-off, not as the source of truth: when a kit and `tokens.css` disagree, `tokens.css` (v4) wins.

| Kit | Figma | Author / licence | Useful for |
| --- | --- | --- | --- |
| Filament 3 Design System | https://www.figma.com/community/file/1413822581847485668/filament-3-design-system | Giovanni Zanin · CC BY 4.0 | End-to-end admin flows replicated from the demo (list, create, edit, view, settings) |
| Filament 3 UI Kit (Free) | https://www.figma.com/community/file/1417716904167561805/filament-3-free | VhiWEB community · free | Atomic components: buttons, inputs, navigation, cards, widgets |

## Known v3 → v4 differences to correct when copying a kit frame

- v4 topbar spans the full width with the logo; the sidebar sits below it on the gray canvas (v3: logo in a white sidebar header).
- v4 buttons are medium weight; amber primary buttons are amber-400 with dark amber text (v3: semibold, amber-600, white text).
- v4 table header row is gray-50; helper text is gray-600; active navigation text is primary-700.
- v4 adds callouts, the table repeater, the multi-factor profile section and deferred table filters.

## Attribution

- Giovanni Zanin — Filament 3 Design System (CC BY 4.0).
- VhiWEB — Filament 3 UI Kit (Free).

Credit the authors when their frames are reused for client work.
