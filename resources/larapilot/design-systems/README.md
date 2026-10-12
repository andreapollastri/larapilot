# Design systems

Packaged visual references for Larapilot mockups. `larapilot:install` copies them to `.larapilot/design-systems/`, and `larapilot:update` refreshes them (`--preserve-design-systems` keeps local changes).

Every folder has the same shape, so `/larapilot-design` reads any of them the same way:

- `README.md` — what it is, when to use it and when not, how a mockup uses it, fidelity notes, and the mapping from mockup to real code.
- `components.md` — every component: anatomy, variants and states, an HTML snippet, do and don't.
- `sources.md` — the official sources with links, the icon set and its licence, and what was verified against a source versus assumed. Filament keeps `figma-sources.md` as well.
- `tokens.css` — the only stylesheet: tokens, dark tokens, the shell, the components, and the `.mock-*` helpers that draw credential fields.
- `html/` — static screens, starting from the `index.html` catalog.

A mockup copies `tokens.css` next to itself as `{slug}-tokens.css` and links it once. Screens are self-contained: system fonts (the README names the real font the app loads), no CDN, no web font, no script, light and dark. Sign-in screens draw their fields: never a `<form>` or a password `<input>`.

Every system ships at least `dashboard`, `resource-list`, `resource-form` (Filament: `resource-create` and `resource-edit`), `login`, `settings`, `components`, and `empty-state`. Each sample product is fictional.

## Laravel panels and kits — mirror the real product

| System | Folder | When to use | Sample product | Screens |
| --- | --- | --- | --- | --- |
| **Filament** | `filament/` | Filament 4 panels: the PRD records Filament as the panel. Primary and gray palettes switch with `data-fi-primary` / `data-fi-gray`. | Northwind Ops | `index`, `auth-register`, `cards-navigation`, `components`, `dashboard`, `dashboard-dark`, `empty-state`, `filters`, `form-wizard`, `login`, `modal`, `notifications`, `profile`, `relation-manager`, `resource-create`, `resource-edit`, `resource-list`, `resource-view`, `settings`, `slide-over`, `widgets-dashboard` |
| **Laravel Nova** | `nova/` | Nova 5 panels: the project licenses Nova. | Harbor CRM | `index`, `components`, `dashboard`, `dashboard-dark`, `empty-state`, `filters`, `login`, `modal`, `notifications`, `resource-form`, `resource-list`, `resource-view`, `settings` |
| **Laravel Starter Kits** | `starter-kit/` | The authenticated UI of a Starter Kit: React, Vue, Svelte (shadcn) or Livewire (Flux). | Ledgerly | `index`, `auth-register`, `auth-split`, `components`, `dashboard`, `dashboard-dark`, `dashboard-livewire`, `empty-state`, `header-layout`, `login`, `resource-form`, `resource-list`, `settings`, `settings-appearance`, `settings-security` |
| **shadcn/ui** | `shadcn/` | Inertia product UI beyond the kit pages: data tables, sheets, dialogs, command palette. | Orbit | `index`, `auth-register`, `components`, `dashboard`, `dashboard-dark`, `empty-state`, `kanban`, `landing`, `login`, `modal`, `resource-form`, `resource-list`, `settings`, `slide-over` |

## CSS frameworks — real class names, copy-paste-able

| System | Folder | When to use | Sample product | Screens |
| --- | --- | --- | --- | --- |
| **Bootstrap 5** | `bootstrap-5/` | A site or app on Bootstrap 5.3: a subset of the official `bootstrap.css`. | Bramble Stays | `index`, `components`, `dashboard`, `dashboard-dark`, `empty-state`, `icons`, `landing`, `login`, `modal`, `resource-form`, `resource-list`, `settings` |
| **AdminLTE 4** | `adminlte/` | An admin panel on AdminLTE 4 (Bootstrap 5.3): a subset of the official `adminlte.css`. | Kestrel Inventory | `index`, `components`, `dashboard`, `dashboard-dark`, `empty-state`, `icons`, `login`, `resource-form`, `resource-list`, `settings` |
| **Tailwind CSS** | `tailwind/` | A site or custom app in Tailwind v4 utilities, with no panel or kit: a compiled subset of the utilities. | Tidewater | `index`, `components`, `dashboard`, `dashboard-dark`, `empty-state`, `landing`, `login`, `resource-form`, `resource-list`, `settings` |
| **Material Design 3** | `material-3/` | Angular Material or Vuetify companions, Android-like products: the 49 M3 colour roles from one seed. | Meadow Field | `index`, `components`, `dashboard`, `dashboard-dark`, `dialog`, `empty-state`, `landing`, `login`, `resource-form`, `resource-list`, `resource-view`, `settings` |

## Enterprise — backoffice and product sites

| System | Folder | When to use | Sample product | Screens |
| --- | --- | --- | --- | --- |
| **Fluent 2** | `fluent/` | Microsoft-style enterprise: intranets, B2B portals, Microsoft 365 / Azure-like admin and product pages. Real Fluent token names; no Microsoft logo. | Contoso Workplace | `index`, `components`, `dashboard`, `dashboard-dark`, `empty-state`, `landing`, `login`, `pricing`, `resource-form`, `resource-list`, `resource-view`, `settings` |

## Aesthetic systems — a strong look, backoffice and site

| System | Folder | When to use | Sample product | Screens |
| --- | --- | --- | --- | --- |
| **Nordic SaaS** | `nordic-saas/` | Larapilot's house system and the Nordic minimal default: warm stone neutrals, one fjord-blue accent, hairlines. | Fjord | `index`, `components`, `dashboard`, `dashboard-dark`, `empty-state`, `landing`, `login`, `resource-form`, `resource-list`, `resource-view`, `settings` |
| **Modern** | `modern/` | SaaS and developer tools in the Linear / Vercel manner: dark-first, glass surfaces, bento grids, command palette, `kbd` hints. | Pulseboard | `index`, `components`, `dashboard`, `dashboard-dark`, `empty-state`, `landing`, `login`, `pricing`, `resource-form`, `resource-list`, `settings` |
| **Bold California** | `bold-california/` | Consumer brands, leisure, DTC shops: heavy display type, sunset palette on sand, pill buttons, sticker shadows. | Sunday Surf Co. | `index`, `components`, `dashboard`, `dashboard-dark`, `empty-state`, `landing`, `login`, `pricing`, `resource-form`, `resource-list`, `settings` |
| **Berlin** | `berlin/` | Agencies, studios, culture, venues: Swiss / Bauhaus grid, monochrome and one signal colour, hairlines, mono micro-labels. | Kollektiv Studio | `index`, `components`, `dashboard`, `dashboard-dark`, `empty-state`, `error-404`, `landing`, `login`, `modal`, `program`, `resource-form`, `resource-list`, `settings` |
| **New York** | `new-york/` | Restaurants and hospitality, food and city media, editorial brands with a city voice: Didone headlines on newsprint, Helvetica signage bands, round subway-line bullets, one taxi-yellow accent. | Mulberry & Grand | `index`, `components`, `dashboard`, `dashboard-dark`, `empty-state`, `error-404`, `landing`, `login`, `menu`, `modal`, `resource-form`, `resource-list`, `settings` |
| **Zen** | `zen/` | Studios, craft, wellness, portfolios, brands that want silence: black and white only, wide empty space, light headings, hairlines, status by shape, one ensō ornament. | Kanso | `index`, `components`, `dashboard`, `dashboard-dark`, `empty-state`, `error-404`, `landing`, `login`, `modal`, `resource-form`, `resource-list`, `settings` |

## Choosing

- **The PRD decides first.** A panel or a CSS framework recorded in `## Technical Architecture` picks its system; `/larapilot-design` skips the question.
- **Otherwise `/larapilot-design` asks**, and you may pick several to compare: each becomes a style folder of the same screens on `/larapilot/design`, and **Use this style** keeps one.
- **The client has a brand.** `/larapilot-design` can interview you and write `.larapilot/design-systems/{slug}/` in this same shape. An adopted project gets the design system of the app as it is.
- **Never mix two systems** on one screen. Public pages keep Nordic minimal unless the PRD scopes them otherwise.

Skills find this folder through `php artisan larapilot:config-show` → `paths.design_systems`.
