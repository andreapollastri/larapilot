# Material Design 3 — mockup design system

Static, offline pictures of a **Material Design 3** product, drawn with one stylesheet (`tokens.css`) and the real
M3 token names. A screen made from this folder should pass, at a glance, for a screenshot of an Angular Material
(M3 theme), Vuetify 3 or Material Web app — light and dark, desktop and phone.

The sample product is **Meadow Field**, a field-service app: jobs (`JB-2417 Replace boiler pressure valve`),
customers, sites and technicians, dated October 2026. Reuse the shapes, replace the content.

## Version targeted

| Layer | Version the values come from |
| --- | --- |
| Design language | Material 3 (m3.material.io), baseline component specs, not the 2025 “Expressive” update |
| Component tokens | `@material/web` 2.x token files (`tokens/versions/v0_192`) |
| Colour | `@material/material-color-utilities` 0.4.0, `SchemeTonalSpot`, spec 2021, contrast level 0 |
| Implementations it maps to | Angular Material 18+ with an M3 theme (20+ for `matButton="…"`), Vuetify 3.x, Material Web 2.x |
| Icons | Material Symbols Outlined, weight 400 (`@material-symbols/svg-400`) |

## When to use it — and when not

Use it when the PRD records **Material Design 3**, **Angular Material**, **Vuetify** or **Material Web**, or the
product must feel like an Android / Google app: tonal surfaces, pill buttons, a navigation drawer, rail or bar,
filled and outlined text fields, 28 dp dialogs.

Do not use it for Filament, Nova, AdminLTE, shadcn/ui or Starter Kit products (they have their own folders), and do
not mix its chrome with theirs. A Bootstrap or Tailwind project that only wants “something clean” should use
`nordic-saas/` or `tailwind/`.

## How a mockup uses it

1. Copy `tokens.css` next to the mockup as `material-3-tokens.css` and link only that file.
2. Put `class="md3-mockup"` on `<body>` (the dashboard detects the system from it).
3. Open `html/index.html`, pick the closest screen, copy its markup and change the content. Every class is
   documented in `components.md`; `html/components.html` shows each one in its states.
4. App shell: `.md3-app` + `.md3-drawer` (360 dp) **or** `.md3-rail` (80 dp), `.md3-main`, `.md3-top-app-bar`,
   `.md3-content`, and a `.md3-navbar` for phones (add `md3-app--with-navbar`). Above 840 px the drawer or rail
   shows; below, the top app bar shows its menu icon (`md3-compact-only`) and the navigation bar sits at the bottom.
5. Dark mode follows the OS; force it with `<html class="dark">` (or `class="light"` to opt out).
6. Sign-in screens draw the credentials: `.mock-field` / `.mock-field--secret` inside an
   `md3-field md3-field--outlined` wrapper with a `.mock-label`. Never a `<form>`, never a password input.

Icons are inline SVG copied path-for-path from Material Symbols Outlined:
`<svg class="md3-icon" aria-hidden="true" fill="currentColor" viewBox="0 -960 960 960"><path d="…"/></svg>`. Use the
`-fill` variant for the selected destination of a drawer, rail or navigation bar.

## Colour: the seed and how to re-seed

Seed **#4C7A34** (“meadow green”, HCT hue 138, chroma 44). `SchemeTonalSpot` turns it into every role:
primary `#446732` / dark `#AAD291`, secondary `#55624C`, tertiary `#386666`, error `#BA1A1A`, surface `#F8FAF0` /
dark `#11140E`, the five surface containers, surface-dim/bright, inverse roles, outline, outline-variant, scrim,
shadow and all `*-fixed` roles.

To brand a project: generate a scheme from the client colour with the Material Theme Builder (export “Web (CSS)”) or
`SchemeTonalSpot` from material-color-utilities, then replace the `--md-sys-color-*` lines in three places: `:root`
(light), `html.dark` and the `prefers-color-scheme` block (dark). Nothing else refers to hex values. In code the same
seed goes into `ng generate @angular/material:theme-color` (Angular), `createVuetify({ theme })` (Vuetify) or the
`--md-sys-color-*` custom properties (Material Web).

Status colours use roles, not extra hues: Scheduled outline, En route tertiary-container, In progress
primary-container, Completed surface-container-highest, Overdue error-container — always with an icon and a label.

## Screens (`html/`)

| File | Shows | Start from it for |
| --- | --- | --- |
| `index.html` | Catalog, colour-role swatches, links | Finding the right starting screen |
| `dashboard.html` / `dashboard-dark.html` | Drawer, top app bar with search, stat cards, area chart, status meter, lists, FAB | Home and overview screens |
| `resource-list.html` | mat-table look: search, filter chips, sort, row selection, contextual “2 selected” bar, paginator | Any index / table |
| `resource-form.html` | Filled + outlined fields, error, disabled, select, date, segmented, switch, chips, drop zone, sticky footer | Create / edit |
| `resource-view.html` | Header actions, chips, tabs, checklist, details list, timeline, map and estimate cards | Detail pages |
| `dialog.html` | Basic dialog with radio list over a scrim | Confirmations, pickers |
| `settings.html` | Section list, fields, radios, sliders, switch list, snackbar | Preferences |
| `empty-state.html` | No results + first run | Empty lists |
| `login.html` | Drawn sign-in fields | Auth screens |
| `landing.html` | Marketing page: hero, logos, features, pricing, testimonial, FAQ, footer | Public pages |
| `components.html` | Every component and state | Looking up a class |

## Fidelity notes (what is approximated)

- **Roboto is not bundled** (no web fonts allowed): the stack is `Roboto, "Roboto Flex", system-ui, …`. The real
  app loads Roboto (Angular Material’s `ng add` adds it; Vuetify via `@fontsource/roboto`; Material Web via Google
  Fonts). Machines without Roboto show the system sans, slightly wider.
- **Ripples are static.** States are the M3 state layers (hover 8 %, focus 12 %, pressed 12 %, dragged 16 %, the
  Material Web 2.x values) drawn with `::before`; demo classes `is-hover`, `is-focus`, `is-pressed`, `is-dragged`.
- **Elevation is a shadow pair** reproducing `md-elevation` (key 30 %, ambient 15 %). Surfaces are tonal: the
  surface-container roles do the separation, shadows only on elevated cards, FABs, menus, dialogs, snackbars.
- **Components that the libraries do not ship** (status chip, timeline, stat, meter, map, drop zone) are composed
  from M3 roles and tokens and are marked as composites in `components.md`.
- Charts are inline SVG in role colours; no hover tooltips (no script), one sample tooltip is drawn.
- Material Web 2.x has no top app bar, rail, data table, snackbar or tooltip; those follow the M3 spec and the
  Angular Material / Vuetify renderings.

## Mockup → real code

| Mockup | Angular Material (M3) | Vuetify 3 | Material Web |
| --- | --- | --- | --- |
| `.md3-btn--filled` | `<button matButton="filled">` (v20) / `mat-flat-button` | `<v-btn variant="flat">` | `<md-filled-button>` |
| `.md3-btn--tonal` | `matButton="tonal"` | `variant="tonal"` | `<md-filled-tonal-button>` |
| `.md3-btn--outlined` | `matButton="outlined"` / `mat-stroked-button` | `variant="outlined"` | `<md-outlined-button>` |
| `.md3-field--outlined` | `<mat-form-field appearance="outline">` | `<v-text-field variant="outlined">` | `<md-outlined-text-field>` |
| `.md3-field--filled` | `<mat-form-field appearance="fill">` | `<v-text-field variant="filled">` | `<md-filled-text-field>` |
| `.md3-table` + `.md3-paginator` | `<table mat-table matSort>` + `<mat-paginator>` | `<v-data-table show-select>` | — |
| `.md3-drawer` | `<mat-sidenav>` + `<mat-nav-list>` | `<v-navigation-drawer>` | `<md-navigation-drawer>` (labs) |
| `.md3-navbar` | custom | `<v-bottom-navigation>` | `<md-navigation-bar>` (labs) |
| `.md3-dialog` | `MatDialog` (`mat-dialog-title`, `mat-dialog-actions`) | `<v-dialog>` + `<v-card>` | `<md-dialog>` |

The full mapping per component is in `components.md`.

## Do / don’t

- Do keep one filled button per view; secondary actions are tonal, outlined or text.
- Do use the colour roles (`--md-sys-color-*`) for every new rule; never a raw hex.
- Do pick one text-field style per form in production (the sample form shows both to document them).
- Don’t add borders to separate surfaces: use the surface-container steps.
- Don’t use the error colour for anything but errors and destructive actions.
- Don’t invent CSS in a mockup: if a class is missing, compose it from the documented ones.
