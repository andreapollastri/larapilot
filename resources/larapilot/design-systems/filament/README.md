# Filament (mockup reference)

A static, offline replica of a **Filament 4** admin panel (current 4.x look: v4.12; Filament 5 renders the
same UI). Use it to draw admin mockups that pass for screenshots of the real panel in light and dark mode,
on desktop and on phones, and that a developer can translate one-to-one into Resources, Pages, Widgets
and Actions. Sample product throughout: **Northwind Ops**, a wholesale coffee and kitchen-goods distributor
(orders, customers, products, shipments).

## When to use it

Use it when the screen is an **admin / back-office panel** and the PRD's Technical Architecture chose
Filament (or the app already runs a Filament panel). Do **not** use it for public marketing pages,
storefronts or a hand-built Livewire/Blade app UI — pick another packaged system for those.

## How a mockup uses it

1. Copy `tokens.css` next to the mockup as **`filament-tokens.css`** and link only that:
   `<link rel="stylesheet" href="filament-tokens.css">` (the dashboard rewrites the link to the packaged file).
2. Body class **`fi-mockup`** is the marker (keep it); panel screens use
   `<body class="fi-mockup fi-body fi-body-has-topbar fi-body-has-navigation">`, auth screens `fi-mockup fi-body`.
3. Start from the closest screen in `html/` (open `html/index.html`), keep the shell, swap the content.
   Compose with the classes in `components.md` only; never add a second stylesheet, CDN, font or script.
4. Icons are inline Heroicons (outline 24 for navigation, mini 20 in buttons and inputs, micro 16 in
   badges and table actions), `aria-hidden="true"`, inside `class="fi-icon fi-size-…"`.
5. Sign-in screens draw the credentials with `.mock-field` (never `<input type="password">`).

### Palette and dark mode

- Default colours are Filament's: primary **Amber**, gray **Zinc**, success Green, warning Amber,
  danger Red, info Blue — as the OKLCH values Filament writes on `:root` (`--primary-50 … --primary-950`).
- Another primary: `<html data-fi-primary="blue">` (also `emerald`, `indigo`, `rose`, `violet`, `zinc`) ⇔
  `->colors(['primary' => Color::Blue])`. Another gray: `data-fi-gray="slate|gray"`. Each palette block also
  carries Filament's **contrast picks** (e.g. amber buttons are amber-400 with amber-900 text; blue buttons
  blue-600 with white text), so components stay faithful after a switch. A brand hex: override
  `--primary-50 … 950` (and, if needed, the `--fi-primary-*` picks) in a `<style>` of the mockup README's choice
  — and record the value Alex registers in the panel provider.
- Dark mode: tokens use `light-dark()`; `<html class="dark">` forces dark (Filament's own switch), the OS
  preference applies otherwise, `<html class="light">` pins light. Show at least one key screen in dark.

## Screens (`html/`)

| File | What it shows | Filament PHP |
| --- | --- | --- |
| `index.html` | Catalog, palette swatches, links to every screen | — |
| `dashboard.html` | Account + info widgets, 4-stat overview with sparklines, line and bar chart widgets, latest-orders table widget | `Dashboard`, `AccountWidget`, `StatsOverviewWidget`, `ChartWidget`, `TableWidget` |
| `widgets-dashboard.html` | Analytics page: filters form, stats with icons, wide comparison chart, doughnut, stacked bars, top products table | `Page` + `HasFiltersForm`, widgets |
| `dashboard-dark.html` | Dashboard forced dark, user menu open (profile, theme switcher, sign out) | `->darkMode()`, user menu |
| `resource-list.html` | Orders: header actions, status tabs with counts, bulk actions, search, filter badge, selection + filter indicators, sortable columns, badges with icons, money/date columns, row actions, summary, pagination | `ListRecords`, `getTabs()`, `Table` |
| `filters.html` | Filters dropdown open (deferred, Apply filters) with indicators | `$table->filters()` |
| `resource-create.html` | Create order: sections with descriptions, 2/3 + 1/3 grid, required marks, helper text, error, disabled field, selects, toggle buttons, table repeater, rich editor, upload drop zone, checkbox list, toggles, footer actions | `CreateRecord`, `Schema` |
| `resource-edit.html` | Edit product (demo-style): header View/Delete, rich editor, image grid upload, pricing error, aside status/associations, multi-select badges | `EditRecord` |
| `resource-view.html` | View order infolist: sections, badges, repeatable table with totals, customer card, timeline | `ViewRecord`, infolist entries |
| `relation-manager.html` | Edit customer + relation-manager tabs + orders table (Associate / New, Dissociate) | `RelationManager` |
| `form-wizard.html` | Contained wizard (completed, active, upcoming), repeater cards with an error | `Wizard`, `HasWizard` |
| `modal.html` | Form action modal with icon over the list + backdrop | `Action::make()->form()` |
| `slide-over.html` | Slide-over action with sticky header/footer, callout, radios | `->slideOver()` |
| `notifications.html` | Database notifications slide-over + toast | `Notification`, `->databaseNotifications()` |
| `empty-state.html` | Table empty state with actions, card and compact empty states | `emptyStateHeading()` |
| `settings.html` | Settings page with aside sections | custom `Page` with a form |
| `profile.html` | Edit profile, drawn passwords, multi-factor rows | `->profile(isSimple: false)`, MFA |
| `cards-navigation.html` | Cluster with start sub-navigation, image + toggle columns | `Cluster`, `SubNavigationPosition::Start` |
| `components.html` | Every component in variants and states | — |
| `login.html`, `auth-register.html` | Simple-layout auth cards with drawn credentials | `->login()`, `->registration()` |

## Mockup → code mapping

- Page = `fi-page` with `fi-header` → a Resource page (`ListRecords`, `CreateRecord`, `EditRecord`,
  `ViewRecord`) or a custom `Page`; header buttons → `getHeaderActions()`.
- `fi-ta` table → `Table` columns (`TextColumn` with `->description()`, `->badge()`, `->money()`, `->dateTime()`,
  `IconColumn`, `ToggleColumn`, `ImageColumn`), `->filters()`, `->recordActions()`, `->toolbarActions()`
  (bulk), `->headerActions()`, `->summaries`; tabs → `getTabs()`.
- `fi-section` / `fi-fo-field` → `Section`, `Grid`, `Fieldset`, `TextInput`, `Select`, `Toggle`, `ToggleButtons`,
  `CheckboxList`, `Radio`, `FileUpload`, `RichEditor`, `Repeater` (`->table()`), `KeyValue`, `TagsInput`, `DatePicker`.
- `fi-in-entry` → infolist `TextEntry`, `IconEntry`, `RepeatableEntry`.
- `fi-wi-*` → widgets; `fi-modal` → action modals; `fi-no` → notifications; `fi-sc-wizard` → `Wizard`.
- `components.md` names the exact PHP for each class.

## Fidelity notes (what is approximated)

- Charts are inline SVG (Chart.js canvases in the app): same colours, grid, legend and point style; ticks are HTML.
- FilePond and the TipTap editor are drawn with our own `fi-fo-file-upload-*` / `fi-fo-rich-editor-*` helpers
  matching their look; searchable selects use a drawn `fi-select-input-btn`.
- Real Filament adds contrast-computed shade utilities (`fi-bg-color-400`, `hover:fi-text-color-800`, …);
  mockups write `fi-color fi-color-{name}` and the tokens hold those picks. Avatars are initials, not ui-avatars images.
- Inter is the panel font (served by Filament itself). Mockups do not load it: Inter is first in the stack,
  system UI fonts otherwise. The brand mark next to "Northwind Ops" stands for `->brandLogo()`.
- The sidebar is hidden below 1024 px (the off-canvas drawer is not drawn); the topbar shows the menu button.
- Filament 3 notes: v3 put the logo in a white sidebar header and the topbar to its right, used semibold
  white-text buttons on amber-600, `primary-600` for active nav and gray-500 helper text. Apply those by hand
  only if the project is pinned to v3; everything else (class vocabulary, spacing, radii) carries over.

## Do / don't

- Do keep one primary action per area, gray for the rest; use status badges with icons.
- Do keep tables in `fi-ta-content-ctn` (they scroll on phones) and widgets in `fi-wi`.
- Don't invent colours or shades; don't add shadows or radii Filament does not use (cards are `rounded-xl`,
  controls `rounded-lg`, badges `rounded-md`).
- Don't use `<form>` or password inputs on auth screens; draw them.

Sources and the verified / assumed table: `sources.md`. Community Figma kits: `figma-sources.md`.
