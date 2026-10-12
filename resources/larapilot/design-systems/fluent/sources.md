# Fluent 2 — sources

Targeted version: **Fluent UI React v9** (`@fluentui/react-components` 9.x, `@fluentui/tokens` master as of October 2026),
**Fluent 2 design language** (fluent2.microsoft.design), **Fluent UI System Icons** (main branch, October 2026).
Values were read from the token pipeline output and the component `*.styles.ts` files on GitHub, and cross-checked
against the Fluent 2 design site.

## Official sources

- Fluent 2 design system — https://fluent2.microsoft.design (color, typography, shapes, elevation, motion, components)
  - Shapes — https://fluent2.microsoft.design/shapes
  - Elevation — https://fluent2.microsoft.design/elevation
  - Typography — https://fluent2.microsoft.design/typography
- Fluent UI React v9 docs and Storybook — https://react.fluentui.dev
- Fluent UI monorepo — https://github.com/microsoft/fluentui
  - Tokens: https://github.com/microsoft/fluentui/tree/master/packages/tokens/src (`global/colors.ts`,
    `global/brandColors.ts`, `global/fonts.ts`, `global/borderRadius.ts`, `global/spacings.ts`, `global/strokeWidths.ts`,
    `global/durations.ts`, `global/curves.ts`, `alias/lightColor.ts`, `alias/darkColor.ts`, `alias/lightColorPalette.ts`,
    `alias/darkColorPalette.ts`, `utils/shadows.ts`, `statusColorMapping.ts`)
  - Component styles: https://github.com/microsoft/fluentui/tree/master/packages/react-components (`react-button`,
    `react-input`, `react-field`, `react-label`, `react-combobox`, `react-checkbox`, `react-radio`, `react-switch`,
    `react-tabs`, `react-badge`, `react-avatar`, `react-persona`, `react-tags`, `react-card`, `react-table`,
    `react-message-bar`, `react-toast`, `react-tooltip`, `react-progress`, `react-menu`, `react-dialog`, `react-drawer`,
    `react-breadcrumb`, `react-nav`, `react-toolbar`)
  - Charts palette: https://github.com/microsoft/fluentui/blob/master/packages/charts/react-charts/library/src/utilities/colors.ts
- Fluent UI Blazor — https://www.fluentui-blazor.net
- Fluent UI Web Components — https://github.com/microsoft/fluentui/tree/master/packages/web-components
- Icons: **Fluent UI System Icons** — https://github.com/microsoft/fluentui-system-icons — **MIT License**. Copied path for
  path from `assets/<Name>/SVG/ic_fluent_<name>_<size>_<regular|filled>.svg`, `fill` replaced by `currentColor`.
- Font: **Segoe UI** is a Microsoft system font (not redistributable); the stack is the `fontFamilyBase` token.

## VERIFIED vs ASSUMED

| Value | Status | Source |
| --- | --- | --- |
| Token names (`--colorNeutralBackground1`, `--borderRadiusMedium`, `--shadow4`, `--fontSizeBase300`, …) | VERIFIED | `packages/tokens/src` |
| Brand ramp brandWeb 10–160, brand 80 = #0F6CBD | VERIFIED | `global/brandColors.ts` |
| webLightTheme / webDarkTheme alias colours (neutral, brand, stroke, subtle, status, palette) | VERIFIED | `alias/lightColor.ts`, `alias/darkColor.ts`, palettes resolved with `global/colors.ts` |
| Status mapping success → green, warning → orange, danger → cranberry | VERIFIED | `statusColorMapping.ts` |
| Radii 2 / 4 / 6 / 8 / circular | VERIFIED | `global/borderRadius.ts`; usage per fluent2.microsoft.design/shapes |
| Shadows 2/4/8/16/28/64 (ambient + key, light 12 %/14 %, dark 24 %/28 %) | VERIFIED | `utils/shadows.ts`, `alias/*Color.ts` |
| Type ramp Caption2 → Display (10/14 … 68/92), weights 400/600 | VERIFIED | `global/fonts.ts`, `global/typographyStyles.ts`, design site |
| Font stack `'Segoe UI', 'Segoe UI Web (West European)', -apple-system, …` | VERIFIED | `global/fonts.ts` |
| Spacing ramp 2/4/6/8/10/12/16/20/24/32, durations, curves | VERIFIED | `global/spacings.ts`, `durations.ts`, `curves.ts` |
| Button 32 px, padding 5 × 12, min-width 96, small 24 / large 40, icon-only 32, primary focus inner stroke | VERIFIED | `useButtonStyles.styles.ts` |
| Input 32 px, Stroke1 + StrokeAccessible bottom, 2 px CompoundBrandStroke underline, invalid PaletteRedBorder2 | VERIFIED | `useInputStyles.styles.ts` |
| Checkbox 16 px radius 2, Switch 40 × 20, Radio 16 px | VERIFIED | `useCheckboxStyles`, `useSwitchStyles`, `useRadioStyles` |
| Tab padding 12 × 10, 3 px indicator, selected semibold + brand icon | VERIFIED | `useTabStyles.styles.ts` |
| Badge 20 px circular caption1Strong; filled / tint / outline / ghost colours | VERIFIED | `useBadgeStyles.styles.ts` |
| Card radius 4 (medium) / 6 (large), padding 12 / 16, shadow4, hover shadow8 | VERIFIED | `useCardStyles.styles.ts` |
| DataGrid rows 44 px (small 34), cell padding 0 8, Stroke2 row border, header regular weight, selected SubtleBackgroundSelected | VERIFIED | `useTableCellStyles`, `useTableRowStyles`, `useTableHeaderCellStyles` |
| MessageBar 36 px, radius 4, Background3 / status Background1 + Border1 | VERIFIED | `useMessageBarStyles.styles.ts` |
| Dialog radius 8, padding 24, gap 8, shadow64, max 600 px, overlay 40 % / 50 % | VERIFIED | `useDialogSurfaceStyles`, `contexts/constants.ts` |
| Menu popover shadow16 radius 4 padding 4; MenuItem 32 px padding 6 | VERIFIED | `useMenuPopoverStyles`, `useMenuItemStyles` |
| Toast padding 12, radius 4, shadow8; Tooltip 4 11 6 padding, radius 4 | VERIFIED | `useToastStyles`, `useTooltipStyles` |
| NavDrawer 260 px on Background4, NavItem padding 10/8/10/10, gap 16, 4 × 20 indicator at −16 px | VERIFIED | `react-nav/…/sharedNavStyles.styles.ts` |
| Data-viz palette color1–4 (#637CEF, #E3008C, #2AA0A4, #9373C0; dark tints) | VERIFIED | `react-charts/…/colors.ts` |
| Suite bar 48 px, brand-coloured in light and #0A0A0A in dark, white search box | ASSUMED | Microsoft 365 web apps (no Fluent header component) |
| App canvas Background2, cards on Background1, page padding 24 (16 on phones), page title Title3 | ASSUMED | Microsoft 365 admin centres |
| Drop zone (dashed Stroke1, Background2) | ASSUMED | SharePoint / OneDrive upload areas |
| Product-page layout (54 px global header, local nav, 5 % gutters, 6-column footer, 4 px top accent on featured plan) | ASSUMED | microsoft.com product pages |
| Sign-in card 440 px, 44 px padding, underline fields, sign-in options card | ASSUMED | Microsoft account / Entra sign-in page, unbranded |
| Hero and sign-in background gradients | ASSUMED | brand 160 → neutral → lavender wash, Contoso choice |
| Checkbox check mark drawn as a data-URI SVG; Switch thumb as radial-gradient | ASSUMED | rendering technique; sizes/colours VERIFIED |
| Subtitle1 line height 28 px (design site lists 26 px) | VERIFIED (code wins) | `lineHeightBase500` = 28 px |

Contoso Workplace, its mark, people and data are fictional. No Microsoft logo or product name is used as a brand.
