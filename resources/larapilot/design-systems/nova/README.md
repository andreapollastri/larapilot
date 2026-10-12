# Laravel Nova 5 — Larapilot design system

Static, self-contained pictures of a **Laravel Nova 5** admin panel (targeted: Nova **5.7**, Tailwind 3.4, Vue 3, Inertia 2,
`laravel-nova-ui` 0.4). A mockup drawn with these classes should pass for a screenshot of a real Nova panel, in light and dark,
on desktop and phone. The sample product is **Harbor CRM** — contacts, companies, deals — with the same people and numbers on
every screen (248 contacts, $482.6k revenue won this quarter, 37 deals won).

## When to use it

- The PRD records **Laravel Nova** as the admin panel, or the project already licenses Nova.
- Back-office screens that Nova draws natively: resource index / detail / create / update, filters, lenses, actions,
  metrics, dashboards, notifications, the login and User Security pages.

Do **not** use it for a public site, a customer-facing app, or a Filament panel (use `filament/`). Do not restyle Nova into
something else: Nova's look is fixed by the package, only `brand.colors` and `brand.logo` change in `config/nova.php`.

## How a mockup uses it

1. Copy `tokens.css` next to the mockup as `nova-tokens.css` and link it — the only stylesheet. No CDN, no web font, no script.
2. `<body class="nv-mockup">` (the marker Larapilot uses to recognise a Nova mockup), then the shell: `.nv-app` › `header.nv-header` + `nav.nv-sidebar` + `main.nv-main` (copy it from
   `html/dashboard.html`). The menu item of the current page gets `.is-active` and `aria-current="page"`.
3. Compose the page with the classes in `components.md`. Start from the closest screen in `html/` (list below) and change the
   data — never invent CSS. Forced dark: `<html class="dark">`; otherwise the OS preference decides (`<html class="light">` opts out).
4. Interactive states are drawn statically: open dropdown = `.nv-pop` inside `.nv-dropdown`; hover = `.is-hover`; focus =
   `.is-focus`; open modal = `.nv-backdrop` + `.nv-modal-wrap`; toast = `.nv-toasts`.
5. Sign-in screens draw the credentials: `<span class="mock-label">` + `<div class="mock-field" role="img" aria-label="…">`,
   secret fields add `.mock-field--secret`. No `<form>`, no password input anywhere.

## Screens (`html/`)

| File | What it shows |
| --- | --- |
| `index.html` | Catalog: every screen with when to start from it, palette swatches |
| `dashboard.html` | Main dashboard — Value ×3, Trend, Partition, Progress, Table ×2 metrics |
| `dashboard-dark.html` | Pipeline dashboard forced dark (`html.dark`), moon theme icon, notification count |
| `resource-list.html` | Contacts index — resource cards, search, Create, 2 selected, action selector, lens / filter / delete menus, row controls, Previous / Next |
| `filters.html` | Filter menu open with two filters applied |
| `resource-view.html` | Contact detail — panels, Address panel, HasMany Deals, empty HasMany Calls, success toast |
| `resource-form.html` | Create Contact — field rows, one validation error, BelongsTo search, Select, Boolean, Date, read-only, Markdown, Image, error toast |
| `modal.html` | Action modal with fields over the index |
| `notifications.html` | Notification center slide-over |
| `empty-state.html` | "No Contact matched the given criteria." + the index error dialog |
| `login.html` | Welcome Back! login (drawn), plus Forgot Password and Two-factor Confirmation cards |
| `settings.html` | Nova 5 User Security page (Update Password, Two Factor Authentication), user menu open |
| `components.html` | Every component of `components.md` in its variants and states |

## Mockup → real code

Every `nv-` block maps to a Nova PHP class or a Nova Vue component (table in `components.md`). In short: the shell is Nova's
layout (`Nova::mainMenu()`, `MenuSection`, `MenuGroup`, `MenuItem`, `Nova::userMenu()`); tables, toolbars and pagination are
generated from a `Resource` (`fields()`, `filters()`, `lenses()`, `actions()`, `cards()`); field rows come from `Field` classes
(`Text`, `Email`, `BelongsTo`, `Select`, `Boolean`, `Date`, `Markdown`, `Image`, `Badge`, `KeyValue`); metric cards from
`Value`, `Trend`, `Partition`, `Progress`, `Table`; dashboards from `Laravel\Nova\Dashboard`; toasts from `Action::message()`,
`Action::danger()` or `Nova.success()`; notifications from `NovaNotification`. Alex does not write CSS for Nova screens: the
mockup tells which Nova class, option and data to use.

## Fidelity notes (what is approximated, and why)

- **Font.** Nova bundles **Nunito Sans** (`fontFamily.sans` in Nova's Tailwind config). The mockup cannot load fonts, so the
  stack is `"Nunito Sans", "Avenir Next", Avenir, system-ui…`: a machine with Nunito Sans installed shows the real thing; macOS
  falls back to Avenir Next (closest preinstalled match), other systems to their UI sans. Body text is 14 px weight 500, as Nova.
- **Brand colour.** Nova's default primary is Tailwind **sky** and white text on sky-500 is 2.77:1 (below WCAG AA). Harbor CRM
  therefore brands the panel the way Nova allows — `brand.colors` 400/500/600 in `config/nova.php` — with sky-600/700/800 in the
  light theme; dark mode keeps stock sky (it passes AA there). Delete section 2 of `tokens.css` to see stock Nova.
- **Other AA nudges.** Text on the gray-100 canvas uses `rgb(91,107,130)` instead of gray-500 (4.34:1 → 4.95:1); placeholders
  are gray-500 instead of gray-400; badge text is one shade darker; error text red-600; boolean icons green-600. Everything else is
  Nova's own class value.
- **Images.** Avatar / Image fields show photos in Nova; mockups draw initials (`.nv-avatar--a…g`) so nothing is loaded.
- **Charts.** Nova uses Chartist. Trend = inline SVG polyline + 10 % area + 6 px dots, flush to the card from 60 % down, no axes
  (Nova shows none); Partition = 90 px donut with a 10 px ring in Nova's slice palette; Progress = `ProgressBar` (green-500
  above 60 %, yellow-300 below, reversed with `avoid()`).
- **Popovers.** Nova teleports dropdowns and modals to `<body>`; mockups place `.nv-pop` under its trigger and modals as fixed
  layers, so a full-page screenshot shows them over the content.
- **Toasts** sit bottom-right (`right: 5%; bottom: 7%`, Nova's Toasted position); `.nv-toasts--low` moves one lower where it
  would cover the form buttons in a full-page capture.
- **Date inputs** use the browser's native control, so the format follows the viewer's locale, as in Nova.

## Do / don't

- **Do** keep Nova's structure: heading above the card, search left / Create right, toolbar inside the card, Previous / Next.
- **Do** use uppercase pill badges from the Badge field, solid check / x icons for Boolean, `link-default` (`.nv-link`) for IDs
  and BelongsTo values.
- **Do** keep field rows label-left (1/5) and control (3/5) on desktop; they stack on phones.
- **Don't** add card titles, icons in table headers, zebra rows or coloured sidebars — Nova has none.
- **Don't** use Filament's `fi-` classes or shadcn components inside a Nova mockup.
- **Don't** draw a password `<input>`; use `.mock-field--secret`.
