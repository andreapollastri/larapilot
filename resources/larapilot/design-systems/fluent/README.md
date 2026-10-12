# Fluent 2 — Larapilot design system

Static, self-contained pictures of a **Fluent 2 (web)** product: the visual language of Microsoft 365 web apps, the Azure
portal and Dynamics admin centres for the back-office, and of microsoft.com-style product pages for the public site.
Targeted: **Fluent UI React v9** (`@fluentui/react-components` 9.x, `@fluentui/tokens` webLightTheme / webDarkTheme,
brand ramp `brandWeb` with communication blue **#0F6CBD** as brand 80) and **Fluent UI System Icons**.

The sample product is **Contoso Workplace**, an internal HR and IT service portal — requests, service catalog, devices,
onboarding — with the same people and numbers on every screen (214 open requests, 96.4% SLA attainment, REQ-24817
"Laptop battery swelling" raised by Diego Ramírez, handled by Aiko Tanaka). No Microsoft logo or name is used as a brand:
the product is Contoso's, with its own mark.

## When to use it

- Enterprise intranets and employee portals (HR, IT service desk, facilities, finance approvals).
- B2B admin consoles, partner portals and line-of-business apps that sit next to Microsoft 365, Teams, SharePoint or
  Dynamics, where users expect the same controls, density and focus behaviour.
- Regulated organizations that need WCAG 2.1/2.2 AA, keyboard-first navigation and high-contrast readiness out of the box.
- A public product site that should feel like a microsoft.com product page (hero, feature rows, plans, FAQ, big footer).

Do **not** use it for consumer-brand marketing that needs its own personality, or when the stack is Filament / Nova /
Bootstrap (use those systems).

## How a mockup uses it

1. Copy `tokens.css` next to the mockup as **`fluent-tokens.css`** and link it — the only stylesheet. No CDN, no web font,
   no script.
2. Back-office page: `<body class="fl-mockup fl-body">`, then the shell copied from `html/dashboard.html`:
   `.fl-app` › `header.fl-header` (suite bar) + `.fl-shell` › `nav.fl-nav` (NavDrawer) + `main.fl-main` › `.fl-page`.
   The current nav item gets `.is-selected` **and** `aria-current="page"` (and the filled icon variant).
   Public page: `<body class="fl-mockup fl-site">` with `header.fl-uhf`, `nav.fl-localnav`, sections, `footer.fl-footer`
   (copy from `html/landing.html`). Sign-in: `<body class="fl-mockup fl-body">` › `.fl-signin` (copy `html/login.html`).
   `fl-mockup` is the marker the Larapilot dashboard uses to recognise a Fluent mockup — keep it on every page.
3. Compose with the classes in `components.md`; start from the closest screen below and change the data. Never invent CSS:
   colours, radii, shadows and type come from the Fluent tokens (`var(--colorNeutralForeground3)`, `var(--shadow16)`, …).
4. Interactive states are drawn statically: open Dropdown = `.fl-listbox.fl-listbox--popup` inside `.fl-dropdown-wrap`;
   open Menu = `.fl-menu`; hover `.is-hover`; pressed `.is-pressed`; keyboard focus `.is-focus`; selected row `.is-selected`
   + `aria-selected="true"`; open Dialog = `.fl-dialog-backdrop` › `.fl-dialog`; Toast = `.fl-toaster` › `.fl-toast`.
5. Sign-in screens draw the credentials: `<span class="mock-label">` + `<div class="mock-field mock-field--underline"
   role="img" aria-label="Email field, filled in">`, the secret one adds `.mock-field--secret`. No `<form>`, no password input.

**Dark mode.** Light tokens (webLightTheme) live on `:root`; webDarkTheme values are applied by `<html class="dark">` or,
without a class, by the OS preference (`<html class="light">` opts out). `dashboard-dark.html` is the forced sample. In
dark the suite bar turns neutral (#0A0A0A) as in Microsoft 365 dark mode; the brand accents switch to brand 100 (#479EF5).

**Font.** Fluent's web font is **Segoe UI** (Segoe UI Variable on Windows 11). The stack is the real `fontFamilyBase`
token — `'Segoe UI', 'Segoe UI Web (West European)', -apple-system, BlinkMacSystemFont, Roboto, 'Helvetica Neue'` — so
Windows shows the real thing and macOS falls back to SF. Alex does not load a web font: the real app uses the same stack.

**Spacing.** Fluent's 4 px ramp: `spacingHorizontal|Vertical` XXS 2 · XS 4 · SNudge 6 · S 8 · MNudge 10 · M 12 · L 16 ·
XL 20 · XXL 24 · XXXL 32. Radii: Small 2 · Medium 4 (buttons, inputs, cards, menus) · Large 6 · XLarge 8 (dialogs, site
tiles) · Circular. Elevation: `--shadow2/4/8/16/28/64` (cards 4, toasts 8, menus 16, dialogs 64).

## Screens (`html/`)

| File | What it shows |
| --- | --- |
| `index.html` | Catalog: every screen with when to start from it, brand / neutral / status / data-viz swatches |
| `dashboard.html` | Header + NavDrawer shell, warning MessageBar, KPI cards, line chart with tooltip, donut, SLA bars, DataGrid "Needs attention", onboarding list with ProgressBar |
| `dashboard-dark.html` | The same page forced dark (`html.dark`) |
| `resource-list.html` | Requests DataGrid: breadcrumb, view TabList with counters, selection toolbar (2 selected), SearchBox, Filter with count, filter Tags, sortable headers, row overflow Menu open, pagination |
| `resource-form.html` | New request: error MessageBar, Field/Label/required/hint, invalid Input, disabled Input, open Dropdown + Listbox, Select, RadioGroup, Switches, DatePicker, Textarea, prefix Input, drop zone + files, sticky footer |
| `resource-view.html` | Request detail: header meta badges, actions, TabList, activity timeline with comment box, Persona card, property lists, SLA ProgressBar, Toast |
| `settings.html` | Vertical TabList, success MessageBar, form cards, Switches, RadioGroup, Slider, theme picker cards, sticky footer |
| `empty-state.html` | First-run "all caught up", zero-results search, error MessageBar + Skeleton, first-run setup |
| `login.html` | Unbranded account sign-in card (underline fields, drawn), verification options card, sign-in options |
| `components.html` | Every component of `components.md` in its variants and states |
| `landing.html` | Product page in the microsoft.com style: global header, local nav, hero with app preview, logos, feature rows, stats, tiles, quote, plans, FAQ, CTA band, six-column footer |
| `pricing.html` | Billing toggle, plan cards, comparison table, add-ons, FAQ, CTA |

## Mockup → real code

Every `fl-` block maps to a Fluent component (full table in `components.md`):

- **React** — `@fluentui/react-components`: `FluentProvider theme={webLightTheme | webDarkTheme}`, `Button`
  (`appearance="primary|secondary|outline|subtle|transparent"`, `size`, `icon`), `Field` + `Input` / `Textarea` / `Select` /
  `Dropdown` / `Combobox` / `SearchBox`, `@fluentui/react-datepicker-compat` `DatePicker`, `Checkbox`, `RadioGroup`,
  `Switch`, `Slider`, `TabList`/`Tab`, `Breadcrumb`, `Badge` / `CounterBadge` / `PresenceBadge`, `Avatar`, `Persona`, `Tag`,
  `Card`/`CardHeader`, `DataGrid` (`createTableColumn`, `selectionMode="multiselect"`, `sortable`), `MessageBar`, `Toaster` +
  `useToastController`, `Tooltip`, `ProgressBar`, `Spinner`, `Skeleton`, `Menu`/`MenuList`/`MenuItem`, `Popover`, `Dialog`,
  `OverlayDrawer` / `InlineDrawer`, `Accordion`, `Divider`, `Toolbar`, `NavDrawer`/`NavItem` (`@fluentui/react-nav`), and
  `@fluentui/react-charts` (`LineChart`, `DonutChart`, `HorizontalBarChart`). Icons: `@fluentui/react-icons`
  (`HomeRegular`, `HomeFilled`, `bundleIcon`).
- **Blazor** — Fluent UI Blazor (`Microsoft.FluentUI.AspNetCore.Components`): `FluentButton Appearance`, `FluentTextField`,
  `FluentSelect`, `FluentCombobox`, `FluentDatePicker`, `FluentCheckbox`, `FluentRadioGroup`, `FluentSwitch`, `FluentTabs`,
  `FluentBreadcrumb`, `FluentBadge`, `FluentPersona`, `FluentCard`, `FluentDataGrid`, `FluentMessageBar`, `FluentToast`,
  `FluentDialog`, `FluentNavMenu`, `FluentProgress`, `FluentAccordion`.
- **Web Components** — Fluent UI Web Components v3 (`@fluentui/web-components`): `<fluent-button appearance>`,
  `<fluent-text-input>`, `<fluent-dropdown>`, `<fluent-checkbox>`, `<fluent-radio-group>`, `<fluent-switch>`,
  `<fluent-tablist>`, `<fluent-badge>`, `<fluent-avatar>`, `<fluent-dialog>`, `<fluent-drawer>`, `<fluent-menu>`,
  `<fluent-message-bar>`, `<fluent-progress-bar>`, `<fluent-accordion>`. They read the same CSS variables as this file
  (`--colorNeutralBackground1`, `--borderRadiusMedium`…), so a Blade/Livewire app can load the web components and style
  its own markup with these tokens.
- **Laravel without React** — Blade components named after the Fluent ones (`<x-fluent.button appearance="primary">`)
  rendering the `fl-` markup, with this token file as the theme.

## Fidelity notes (what is approximated, and why)

- **Focus.** Fluent draws a 2 px `colorStrokeFocus2` ring through tabster; here it is `:focus-visible` (and `.is-focus`
  for drawn focus). Primary buttons show the inner white stroke as in `useButtonStyles`.
- **Input underline.** The 2 px brand underline is the real `::after` with `clip-path`; the animation is kept.
- **Checkbox / Switch / Radio** use native inputs with `appearance: none` and drawn indicators; sizes and colours follow
  `useCheckboxStyles` / `useSwitchStyles` / `useRadioStyles`.
- **Suite bar.** Fluent has no header component; the 48 px brand bar and the 260 px NavDrawer reproduce Microsoft 365
  web apps (ASSUMED values are listed in `sources.md`). `.fl-header--neutral` gives the light-grey variant.
- **Charts** are inline SVG with the Fluent data-viz palette (`color1` #637CEF, `color2` #E3008C, `color3` #2AA0A4,
  `color4` #9373C0) and 2 px lines; hover tooltips are drawn statically.
- **Drop zone** is not a Fluent component; it follows the dashed "Drag files here" area of SharePoint/OneDrive (ASSUMED).

## Do / don't

- **Do** use one primary button per view; secondary for the rest; subtle for toolbars and icon-only buttons.
- **Do** keep sentence case everywhere ("New request", not "New Request"); Fluent avoids all caps.
- **Do** put the Field label above the control (horizontal only for dense settings) and the validation message under it.
- **Do** use status colour only with text or an icon (Badge label, MessageBar icon).
- **Don't** round cards beyond `--borderRadiusMedium`/`Large` in the app; 8 px is for dialogs and marketing tiles.
- **Don't** use Microsoft logos, the four-square mark or Microsoft product names as your brand.
- **Don't** add shadows to inputs or colour the NavDrawer background — it is `colorNeutralBackground4`.
