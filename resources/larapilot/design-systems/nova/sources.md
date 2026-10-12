# Laravel Nova 5 — sources

**Version targeted:** Laravel Nova **5.7** (5.7.6 checked) — Tailwind CSS 3.4, Vue 3.5, Inertia 2, `laravel-nova-ui` 0.4.12,
Heroicons 2.2. Nova 4 and 5 share the same visual language; where they differ (theme switcher, solid boolean icons, User
Security page, ring-style inputs) the mockups follow Nova 5.

## Official sources

| Source | URL | Used for |
| --- | --- | --- |
| Nova documentation (v5) | https://nova.laravel.com/docs/v5/installation | Version, branding (`brand.logo`, `brand.colors`) |
| Resources, fields, panels, relationships | https://nova.laravel.com/docs/v5/resources/the-basics · https://nova.laravel.com/docs/v5/resources/fields · https://nova.laravel.com/docs/v5/resources/panels · https://nova.laravel.com/docs/v5/resources/relationships | Field rows, detail panels, HasMany index |
| Filters, lenses, actions | https://nova.laravel.com/docs/v5/filters/defining-filters · https://nova.laravel.com/docs/v5/lenses/defining-lenses · https://nova.laravel.com/docs/v5/actions/defining-actions | Filter menu, lens selector, action modal |
| Metrics, dashboards, cards | https://nova.laravel.com/docs/v5/metrics/defining-metrics · https://nova.laravel.com/docs/v5/customization/dashboards · https://nova.laravel.com/docs/v5/customization/cards | Value / Trend / Partition / Progress / Table cards, 12-column grid |
| Menus, notifications, impersonation, search | https://nova.laravel.com/docs/v5/customization/menus · https://nova.laravel.com/docs/v5/customization/notifications · https://nova.laravel.com/docs/v5/customization/impersonation · https://nova.laravel.com/docs/v5/search/global-search | Sidebar sections / groups / badges, notification center, user menu, global search |
| Nova product screenshots (light and dark) | https://nova.laravel.com | Pixel-sampled colours, spacing and proportions of header, sidebar, index table, metrics, filter menu, form rows |
| `laravel-nova-ui` 0.4.12 (MIT; Nova's Button / Input / Checkbox / Icon package) | https://www.npmjs.com/package/laravel-nova-ui | Button variants, sizes, states; input styles; checkbox; icon sizes |
| Tailwind CSS v3 palette (sky, slate, green, red, yellow) | https://v3.tailwindcss.com/docs/customizing-colors | RGB triplets of `--colors-*` |
| Heroicons v2.2 (MIT) | https://heroicons.com · https://github.com/tailwindlabs/heroicons/blob/master/LICENSE | Every icon, copied path for path (outline 24, solid 24, mini 20, micro 16) |
| Nunito Sans (SIL OFL 1.1) | https://fonts.google.com/specimen/Nunito+Sans | The font Nova bundles (named first in the stack, not loaded) |

The Tailwind class strings of Nova's own Vue components and `tailwind.config.js` / `generators.js` (shipped in the licensed
`laravel/nova` package) were read to confirm class values; no Nova code is copied here — only the resulting sizes and colours.

## VERIFIED vs ASSUMED

| Value | Status | Source / note |
| --- | --- | --- |
| Palette exposed as `--colors-<name>-<shade>` with "R, G, B" strings, used as `rgba(var(--colors-…))`; primary = sky, gray = slate | VERIFIED | Nova `generators.js`, `tailwind.config.js` |
| `brand.colors` overrides keys 400 / 500 / 600 | VERIFIED | `config/nova.php` stub |
| Font "Nunito Sans"; body `text-sm font-medium text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-900` | VERIFIED | Nova layout Blade + Tailwind config |
| Header `h-14` white / gray-800 (+ gray-700 bottom border in dark); logo area `lg:w-60`; search `max-w-xs`, pill `h-8 pl-10 bg-gray-100` | VERIFIED | `MainHeader.vue`, `GlobalSearch.vue`; screenshot sampling (#ffffff / #f1f5f9, dark #1e293b / #0f172a) |
| Sidebar `w-60 px-3 py-8`, sections `space-y-6`, section label 16 px, item 14 px, active `font-bold text-primary-500`, groups 12 px bold uppercase | VERIFIED | `MenuSection.vue`, `MenuGroup.vue`, `MenuItem.vue` |
| Content `p-4 md:py-8 md:px-12 lg:ml-60 space-y-8`; footer text-xs centred | VERIFIED | `AppLayout.vue`, `Footer.vue` |
| Card `rounded-lg shadow bg-white dark:bg-gray-800 overflow-hidden` | VERIFIED | `Card.vue` |
| Buttons: `border rounded text-sm font-bold`, `h-9 px-3` / `h-7 px-2 text-xs`; solid `bg-primary-500 text-white dark:text-gray-900 shadow`, hover 400; ghost hover `bg-gray-700/5`; outline `border-gray-300`; link / mellow / danger; action `h-9 w-9 text-gray-500` | VERIFIED | `laravel-nova-ui` `useButtonStyles()` |
| Inputs `h-9 rounded shadow ring-1 ring-gray-950/10`, focus `ring-2 ring-primary-500`, error `ring-red-400`, disabled `bg-gray-50 text-gray-400`; select arrow at right 11 px | VERIFIED | `form.css`, `useInputStyles()`, `SelectControl.vue` |
| Checkbox 16 px `rounded border-gray-950/20`, checked primary with white tick (gray-900 in dark) | VERIFIED | `laravel-nova-ui` Checkbox, `form.css` |
| Field rows `py-5`, label `md:w-1/5 md:px-8 md:mt-2`, control `md:w-3/5 md:px-8`, help 12 px italic gray-500 | VERIFIED | `DefaultField.vue`, `nova.css` `.help-text` |
| Detail rows: label `md:w-1/4 md:py-3`, value `md:w-3/4`; panel card `mt-3 py-2 px-6 divide-y` | VERIFIED | `PanelItem.vue`, `fields/Detail/Panel.vue` |
| Index: search `md:w-1/3` pill; toolbar `py-3 border-b`; th 11 px uppercase tracking-wide `bg-gray-50`; td `py-2 px-2`; rows `divide-gray-100`; checkbox cell `pl-5 pr-5`; controls view / edit / delete 36 px | VERIFIED | `Index.vue`, `ResourceTable*.vue`, `IndexSearchInput.vue` |
| Pagination default `simple`: Previous / Next `text-xs font-bold py-3 px-4`, disabled gray-300; count `text-xs` | VERIFIED | `config/nova.php` `'pagination' => 'simple'`, `PaginationSimple.vue` |
| Badge `min-h-6 px-2 rounded-full uppercase text-xs font-bold`; types success / info / danger / warning colours | VERIFIED | `Badge.vue`, `Laravel\Nova\Badge::$types` |
| Boolean icons: solid check-circle green-500 / x-circle red-500 / minus-circle gray-200 | VERIFIED | `IconBoolean.vue` |
| Dropdown menu `bg-white dark:bg-gray-900 shadow-lg rounded-lg border-gray-200`; items `px-3 py-2 text-sm rounded`; filter menu width 260 | VERIFIED | `DropdownMenu*.vue`, `FilterMenu.vue`, `FilterContainer.vue` |
| Metrics: `px-6 py-4`, head `h-6 mb-4`, title 14 px bold, value 36 px, icon tile 56 px primary `rounded-lg`, trend chart from 60 % down, partition donut 90 px / 10 px ring, progress bar `h-4 rounded-full bg-gray-200` (green-500 > 60 %), card min height 160 px, `md:grid-cols-12 gap-6` | VERIFIED | `Base*Metric.vue`, `ProgressBar.vue`, `Cards.vue`, `CardWrapper.vue` |
| Partition slice colours `#F5573B #F99037 #F2CB22 #8FC15D #098F56 #47C1BF #1693EB #6474D7` | VERIFIED | `BasePartitionMetric.vue` |
| Partition percentages rounded to 0 decimals; total printed without separators | VERIFIED | `PartitionResult` (`roundingPrecision = 0`), `Nova.formatNumber` |
| Modal backdrop `bg-gray-500/75`, header Heading 3 `py-4 px-8 border-b`, footer `bg-gray-100 dark:bg-gray-700 px-6 py-3`; action modal default size `2xl`, delete modal `sm` | VERIFIED | `Modal*.vue`, `ConfirmActionModal.vue`, `DeleteResourceModal.vue`, `Action::$modalSize` |
| Notification panel 20 rem, header h-14, unread 5 px primary dot, Mark Read / Delete small mellow links | VERIFIED | `NotificationCenter.vue`, `NotificationList.vue`, `MessageNotification.vue` |
| Toasts: `font-bold py-2 px-5 rounded-lg shadow`, success `text-green-600 bg-green-50`, error `text-red-500 bg-red-50`, position bottom-right | VERIFIED | `nova.css` toasted "nova" theme, `nova.js` |
| Login card `p-8 max-w-[25rem] shadow rounded-lg`, "Welcome Back!" 24 px, 100 × 2 divider `#D8E3EC` | VERIFIED | `Login.vue`, `DividerLine.vue`, `Auth.vue` |
| User Security layout (3-column grid, card + gray footer bar, 2FA texts and buttons) | VERIFIED | `UserSecurity*.vue` |
| Light-theme primary 400/500/600 = sky-600/700/800 (Harbor CRM brand) | ASSUMED (deliberate) | AA: white on sky-500 is 2.77:1; done through Nova's own `brand.colors` |
| Canvas text `rgb(91,107,130)`, placeholders gray-500, badge text one shade darker, error text red-600 / red-400, booleans green-600, dark danger button red-400 | ASSUMED (deliberate) | AA nudges; Nova uses gray-500 / gray-400 / 600-shades / red-500 / green-500 |
| Avenir Next as the macOS fallback for Nunito Sans | ASSUMED | Closest preinstalled face; real Nova always serves Nunito Sans |
| Harbor CRM logo (sail + waves mark) | ASSUMED | Stands in for `brand.logo`; Nova shows its own logo when unbranded |
| Initials avatars | ASSUMED | Nova shows Gravatar / uploaded images |
| Dark badge colours (info `sky-400 / sky-950`, danger text `red-950`, success text `green-950`) | ASSUMED (deliberate) | Nova: info `sky-600 / sky-900` (2.31:1), danger `red-400 / red-900`, success `green-500 / green-900` |
| Markdown hint bar, KeyValue table spacing, tab underline | ASSUMED | Read from screenshots, not measured in source |
