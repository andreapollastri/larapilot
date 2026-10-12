# Material Design 3 — sources

Version targeted: **Material 3 baseline** (m3.material.io component specs before the 2025 “Expressive” update), with
component values taken from **Material Web 2.x** token files (`@material/web` 2.5.0, `tokens/versions/v0_192`), colour
computed with **material-color-utilities 0.4.0**, and the Angular Material 18+ (M3 theme) / Vuetify 3 renderings for the
pieces Material Web does not ship (top app bar, rail, data table, paginator, snackbar, tooltip, expansion panel).
Checked October 2026.

## Official sources

| Source | Used for |
| --- | --- |
| https://m3.material.io/styles/color/roles | Colour roles, surface containers, `*-fixed` roles |
| https://m3.material.io/styles/typography/type-scale-tokens | Type scale names and roles |
| https://m3.material.io/styles/shape/corner-radius-scale | Shape scale (4 / 8 / 12 / 16 / 28 / full) |
| https://m3.material.io/styles/elevation/applying-elevation | Elevation levels and tonal surfaces |
| https://m3.material.io/foundations/interaction/states/state-layers | State layers (hover, focus, pressed, dragged) |
| https://m3.material.io/components | Component anatomy: top app bar, navigation drawer / rail / bar, FAB, buttons, chips, cards, lists, text fields, menus, dialogs, snackbar, tabs, segmented buttons, switch, checkbox, radio, slider, progress, badges, search |
| https://github.com/material-components/material-web | `tokens/versions/v0_192/_md-comp-*.scss`, `_md-sys-*.scss`, `elevation/internal/_elevation.scss`, `field/internal/*` (Apache-2.0) |
| https://github.com/material-foundation/material-color-utilities | `SchemeTonalSpot`, `MaterialDynamicColors`, HCT (Apache-2.0) |
| https://material-foundation.github.io/material-theme-builder/ | How a project re-seeds the scheme |
| https://fonts.google.com/icons | Material Symbols (Outlined, weight 400); SVGs from https://github.com/marella/material-symbols (`@material-symbols/svg-400` 0.48.0), Apache-2.0 |
| https://material.angular.dev/components/categories | Angular Material component names and M3 theming |
| https://github.com/angular/components | `src/material/button/button.md` (`matButton="filled|tonal|outlined|elevated"`), `src/material/table/_m3-table.scss`, `src/material/paginator/_m3-paginator.scss`, `paginator-intl.ts` |
| https://vuetifyjs.com/en/components/buttons/ | Vuetify `v-btn` variants and the other `v-*` names in components.md |

Licences: Material Web, material-color-utilities and Material Symbols are Apache-2.0. Icon paths are copied verbatim.

## Colour: seed and algorithm

- Seed **#4C7A34** (meadow green; HCT hue 138.1, chroma 44.2, tone 46.6), chosen for a field-service product.
- `new SchemeTonalSpot(Hct.fromInt(seed), isDark, 0)` — spec version 2021 (the library default), platform phone,
  contrast level 0 — then `MaterialDynamicColors.<role>.getArgb(scheme)` for all 49 roles, light and dark.
- Key results: primary #446732 / #AAD291, on-primary #FFFFFF / #173807, primary-container #C5EFAB / #2D4F1C,
  secondary #55624C / #BDCBB0, tertiary #386666 / #A0CFCF, error #BA1A1A / #FFB4AB, surface #F8FAF0 / #11140E,
  surface-container-lowest…highest #FFFFFF #F2F5EA #EDEFE5 #E7E9DF #E1E4D9 / #0C0F09 #191D16 #1D211A #282B24 #32362F,
  outline #74796D / #8D9286, outline-variant #C3C8BB / #43483E.
- Contrast check (WCAG 2.x): every on-colour / container pair used for text is ≥ 4.5:1 in both themes (lowest:
  inverse-primary on inverse-surface 5.0:1 dark). `outline` is used for borders only (4.2:1 on surface in light).

## VERIFIED vs ASSUMED

| Value | Status | Source |
| --- | --- | --- |
| All `--md-sys-color-*` roles (light, dark) | VERIFIED (computed) | material-color-utilities 0.4.0, SchemeTonalSpot |
| Type scale: sizes, line heights, weights, tracking (display 57/45/36 … label 14/12/11) | VERIFIED | `_md-sys-typescale.scss` v0_192 |
| Shape: 0 / 4 / 8 / 12 / 16 / 28 / 9999 px, large-end, extra-small-top | VERIFIED | `_md-sys-shape.scss` |
| Elevation shadows (key 30 % + ambient 15 %, per-level offsets) | VERIFIED | `elevation/internal/_elevation.scss` |
| State layers hover 0.08, focus 0.12, pressed 0.12, dragged 0.16 | VERIFIED | `_md-sys-state.scss` v0_192 (newer M3 guidance is reported to use 10 % for focus/pressed; not confirmed here because m3.material.io renders client-side, so the shipped Material Web 2.x values are used) |
| Focus ring 3 px, secondary, 2 px outward offset | VERIFIED | `_md-comp-focus-ring.scss` |
| Motion durations and easings | VERIFIED | `_md-sys-motion.scss` |
| Buttons 40 dp, corner-full, label-large, 18 dp icon, padding 24 (16 with icon), text 12 (12/16) | VERIFIED | `_md-comp-filled-button.scss`, `_md-comp-text-button.scss` |
| Icon button 40 dp / 24 dp icon; FAB 56 / 40 / 96, corner-large / medium / extra-large, level 3 | VERIFIED | `_md-comp-icon-button`, `_md-comp-fab-primary*` |
| Chips 32 dp, corner-small, 16 dp padding (8 with icon), 18 dp icons, outline | VERIFIED | `_md-comp-assist-chip`, `_md-comp-filter-chip` |
| Text fields 56 dp, label body-small populated / body-large resting, 16 dp padding, supporting text 4 dp below | VERIFIED | `_md-comp-filled-field`, `_md-comp-outlined-field`, `_md-comp-*-text-field` |
| Field focus indicator 2 px (Material Web draws 3 px) | VERIFIED (spec token) | v0_192 `focus-active-indicator-height: 2px` |
| Navigation drawer 360 dp, 56 dp items, secondary-container indicator, corner-large-end | VERIFIED | `_md-comp-navigation-drawer.scss` |
| Navigation rail 80 dp, 56×32 indicator; navigation bar 80 dp, 64×32 indicator, surface-container | VERIFIED | `_md-comp-navigation-rail`, `_md-comp-navigation-bar` |
| Top app bar 64 / 112 dp, title-large / headline-small, scrolled = surface-container | VERIFIED | `_md-comp-top-app-bar-*` |
| Dialog surface-container-high, 28 dp, level 3, headline-small; scrim 32 % | VERIFIED | `_md-comp-dialog`, `_md-comp-scrim` |
| Menu surface-container, 4 dp, level 2, 48 dp items | VERIFIED | `_md-comp-menu`, `_md-comp-*-select` |
| Snackbar inverse-surface, 4 dp, 48 dp, inverse-primary action | VERIFIED | `_md-comp-snackbar.scss` |
| Switch 52×32, handles 16 / 24 / 28 dp; checkbox 18 dp corner 2; radio 20 dp | VERIFIED | `_md-comp-switch`, `_md-comp-checkbox`, `_md-comp-radio-button` |
| Slider 4 dp tracks, 20 dp handle, 28 dp label | VERIFIED | `_md-comp-slider.scss` |
| Tabs 48 dp (64 with icon), 3 dp primary indicator; secondary 2 dp | VERIFIED | `_md-comp-primary-navigation-tab`, `_md-comp-secondary-navigation-tab` |
| Data table header 56 / rows 52, title-small / body-medium; paginator body-small, 56 dp, “Items per page:”, “1 – 10 of 248” | VERIFIED | `_m3-table.scss`, `_m3-paginator.scss`, `paginator-intl.ts` (Angular Material) |
| Search bar 56 dp, corner-full, surface-container-high, no shadow | VERIFIED / ASSUMED | token says level 3 tonal elevation; drawn as the surface-container-high tone without a shadow |
| Linear/circular progress track secondary-container | ASSUMED | 2024 spec update; v0_192 uses surface-container-highest |
| Table row rule outline-variant (Angular uses `outline`), selected row secondary-container tint | ASSUMED | v0_192 data-table token / visual choice |
| Status chips, timeline, stat, meter, map, drop zone, empty-state art | ASSUMED (composites) | composed from M3 roles, not library components |
| Shell breakpoint at 840 px (drawer/rail ↔ navigation bar) | ASSUMED | M3 window classes: compact < 600, medium < 840, expanded ≥ 840 |
| Roboto as the product font | VERIFIED | `_md-ref-typeface.scss` (brand and plain = Roboto); not bundled, system fallback |
