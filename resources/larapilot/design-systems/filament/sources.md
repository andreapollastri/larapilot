# Filament — sources

Version targeted: **Filament 4.x** (checked against v4.12 source; Filament 5 keeps the same UI). Built
2026-10-12.

## Official sources

| Source | URL | Used for |
| --- | --- | --- |
| Filament documentation 4.x | https://filamentphp.com/docs/4.x | component APIs, labels, layout behaviour |
| Docs screenshots (light/dark) | https://filamentphp.com/docs/images/4.x/light/tables/example.jpg (and `…/panels/styling/sidebar-width.jpg`, `…/widgets/stats-overview/chart.jpg`, `…/forms/overview.jpg`, `…/actions/modal/slide-over.jpg`, `…/schemas/layout/wizard/descriptions.jpg`, `…/notifications/statuses.jpg`, `…/infolists/overview.jpg`) | visual comparison of tables, shell, stats, forms, modals, wizard, notifications |
| Source repository | https://github.com/filamentphp/filament | source CSS (`packages/*/resources/css`), Blade/PHP markup, translations |
| Panel source CSS | https://github.com/filamentphp/filament/tree/4.x/packages/panels/resources/css | shell: sidebar, topbar, page, header |
| Support source CSS | https://github.com/filamentphp/filament/tree/4.x/packages/support/resources/css | button, badge, input wrapper, dropdown, modal, pagination, tabs, toggle, section |
| Colour palettes | https://github.com/filamentphp/filament/blob/4.x/packages/support/src/Colors/Color.php | OKLCH palettes, defaults (`ColorManager`: primary Amber, gray Zinc) |
| Contrast colour maps | https://github.com/filamentphp/filament/tree/4.x/packages/support/src/View/Components | per-component shade picks (`ButtonComponent`, `BadgeComponent`, `LinkComponent`, …), executed to produce the tokens |
| Live demo | https://demo.filamentphp.com | product form and order list patterns |
| Heroicons v2 (MIT, Tailwind Labs) | https://heroicons.com · https://github.com/tailwindlabs/heroicons | every icon, copied path-for-path (via blade-heroicons) |
| Tailwind CSS v4 theme | https://tailwindcss.com/docs/theme | shadow-xs/sm/lg/xl, radius, spacing, type scale used by Filament |

Icon licence: Heroicons are MIT licensed © Tailwind Labs; Filament is MIT licensed © Dan Harrin.
Figma community kits used earlier are listed in `figma-sources.md` (unofficial, Filament 3).

## Verified vs assumed

| Value | Status | Source |
| --- | --- | --- |
| Primary Amber, gray Zinc, success Green, warning Amber, danger Red, info Blue (OKLCH values) | VERIFIED | `Color.php`, `ColorManager.php` |
| Component shade picks (button amber 400/900 → dark 600/950; badge text 700/400; link 700/400; toggle 600; red button 600 + white) | VERIFIED | colour-map classes executed against v4.12 |
| Sidebar width 20rem, topbar min-h 4rem, content max-w-7xl, main px-4/6/8 | VERIFIED | `sidebar.css`, `topbar.css`, `layout.css`, `base.blade.php` |
| v4 shell: full-width topbar with logo, transparent sidebar below it, sidebar header hidden on desktop | VERIFIED | `sidebar.css`, `topbar.blade.php`, docs screenshot |
| Sidebar item p-2 rounded-lg, icon 24 gray-400, label text-sm medium gray-700, active bg gray-100 + primary-700 | VERIFIED | `sidebar.css` |
| Header heading 2xl/3xl bold tracking-tight, actions `sm:mt-7` with breadcrumbs | VERIFIED | `header.css` |
| Section rounded-xl, shadow-sm, ring gray-950/5, header px-6 py-4, content p-6, border gray-200 | VERIFIED | `section.css` |
| Input wrapper rounded-lg shadow-xs ring gray-950/10, focus ring-2 primary-600, invalid danger-600, input px-3 py-1.5 leading-6 | VERIFIED | `input/wrapper.css`, `input/index.css` |
| Button rounded-lg px-3 py-2 text-sm **medium**, gray = white + ring gray-950/10 | VERIFIED | `button.css` |
| Badge rounded-md px-2 py-1 text-xs medium, ring-inset colour-600/10, dark colour-400/10 + 400/30 | VERIFIED | `badge.css` |
| Table: thead bg gray-50, header cell px-3 py-3.5 semibold, text column px-3 py-4, edge padding 24 px, row 57 px, selected row primary bar | VERIFIED | `table.css`, `header-cell.css`, `cell.css`, `row.css`, docs screenshot measured |
| Toolbar, selection indicator, filter indicators, pagination grid and `@4xl` breakpoint | VERIFIED | `container.css`, `pagination.css` |
| Modal rounded-xl shadow-xl, header px-6 pt-6, icon bg colour-100, backdrop gray-950/50 (dark /75) | VERIFIED | `modal.css` |
| Notification rounded-xl p-4 shadow-lg max-w-sm, icon colour-400 | VERIFIED | `notification.css` |
| Stats: p-6 rounded-xl, value 3xl semibold, chart h-6 at the bottom | VERIFIED | `stats-overview-widget.css` |
| Chart colours: border colour-500 (dark 400), fill colour-50 (dark 400/10), grid gray-200 (dark 800), ticks gray-500 | VERIFIED | `chart-widget.css` |
| Wizard step circles size-10 border-2, separator path `M0 -2L20 40L0 82` | VERIFIED | `wizard.css`, `Wizard.php` |
| Dark palette: body gray-950, surfaces gray-900, rings white/10, inputs white/5 + ring white/20 | VERIFIED | source CSS `dark:` utilities |
| English strings (Search, Active filters, Select all :count, Deselect all, Per page, Showing … results, Sign in, Forgot password?) | VERIFIED | `lang/en/*.php` |
| Shadows (xs/sm/lg/xl) and radii (md .375, lg .5, xl .75) | VERIFIED | Tailwind v4 theme in Filament's `dist/theme.css` |
| Avatar initials size and weight (ui-avatars renders an SVG) | ASSUMED | approximated as 13 px medium white on gray-950 |
| Global search field width (~13.25rem) | ASSUMED | measured from the docs screenshot (intrinsic input width) |
| FilePond item colours (#64605e-like panel, green when uploaded) and drop label height | ASSUMED | FilePond defaults as seen in docs screenshots |
| Rich editor toolbar/icon set (TipTap tools) | ASSUMED | `rich-editor.css` sizes verified; tool icons approximated with Heroicons |
| Chart geometry (point radius 3, line width 2, legend square, bar 72% of band, smooth tension) | ASSUMED | Chart.js defaults read from screenshots |
| Brand mark next to the brand name, catalog page, timeline list, progress bar | ASSUMED | own helpers (Filament has no equivalents) |
| Exact Inter rendering | ASSUMED | depends on Inter being installed; system UI fallback |
