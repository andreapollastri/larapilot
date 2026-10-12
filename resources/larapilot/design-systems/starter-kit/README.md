# Laravel Starter Kit (mockup reference)

The authenticated app UI of the official **Laravel 12 / 13 starter kits**: React and Vue on **shadcn/ui**
(Inertia 2, Tailwind 4), Svelte on shadcn-svelte, Livewire 4 on **Flux UI**. Targeted snapshot: the `main`
branches of `laravel/react-starter-kit`, `laravel/vue-starter-kit` and `laravel/livewire-starter-kit` on
2026-10-12 (the Livewire kit requires `laravel/framework ^13.17`, `livewire/livewire ^4.1`, `livewire/flux ^2.13.1`).
Every screen is drawn for **Ledgerly**, a fictional personal-finance app (accounts, transactions, budgets).

`tokens.css` is the only stylesheet. It carries the kit's real `app.css` variables (oklch, `neutral`, radius
0.625rem), the kit's component metrics, and the `.sk-*` classes the screens use. Sources and the list of what was
verified or assumed: `sources.md`. Every class with its markup and its React / Vue / Flux counterpart: `components.md`.

## When to use it

- The PRD records a **Starter Kit** variant (`react`, `vue`, `svelte`, `livewire`) and the screen lives inside the
  kit's authenticated shell: dashboard, settings, Fortify auth pages, and the app's own pages built on that shell
  (lists, forms, detail pages, empty states).
- Not for a Filament or Nova panel, not for a marketing site (use `tailwind/`, `nordic-saas/` or `shadcn/landing.html`),
  not for a product that replaced the kit's layout with a different shadcn block (use `shadcn/`).

## What the kit ships, and what Ledgerly adds

The kit's dashboard (`pages/dashboard.tsx`, `dashboard.blade.php`) is **three placeholder boxes and one tall box
filled with `PlaceholderPattern` stripes**: no stats, no chart, no table. `dashboard.html` shows what a real app puts
in that slot (cards, a bar chart, budgets, recent transactions) using only kit components plus registry components
Alex adds with `npx shadcn@latest add …`. The placeholder pattern itself is drawn in `components.html`
(`.sk-placeholder`). The kit's sidebar has one item (Dashboard) and two footer links (Repository, Documentation);
Ledgerly replaces them with its own items, and replaces the Laravel mark in `AppLogoIcon` with its own logo.

## Kit variants and how they map

| In the mockup | React (`resources/js/…`) | Vue | Livewire (`resources/views/…`) |
| --- | --- | --- | --- |
| `.sk-shell.sk-shell--inset` + `.sk-sidebar` + `.sk-inset` | `layouts/app/app-sidebar-layout.tsx`, `components/app-sidebar.tsx` (`<Sidebar collapsible="icon" variant="inset">`) | `layouts/app/AppSidebarLayout.vue`, `AppSidebar.vue` | `layouts/app/sidebar.blade.php`: `flux:sidebar`, `flux:sidebar.item`, `flux:main` — use `body.sk-flux` + `.sk-shell` (see `dashboard-livewire.html`) |
| `.sk-shell` without `--inset`, `.sk-shell--floating` | `variant="sidebar"` / `variant="floating"` | same prop | — |
| `.sk-header-shell` | `layouts/app/app-header-layout.tsx`, `components/app-header.tsx` | `AppHeaderLayout.vue` | `layouts/app/header.blade.php` (`flux:header`, `flux:navbar`) |
| `.sk-auth` / `.sk-auth--card` / `.sk-split` | `layouts/auth/auth-simple-layout.tsx` / `auth-card-layout.tsx` / `auth-split-layout.tsx`, chosen in `layouts/auth-layout.tsx` | `AuthSimpleLayout.vue` … in `AuthLayout.vue` | `layouts/auth/simple|card|split.blade.php`, chosen in `layouts/auth.blade.php` |
| `.sk-settings` | `layouts/settings/layout.tsx` + `pages/settings/profile|security|appearance.tsx` | `layouts/settings/Layout.vue` | `pages/settings/layout.blade.php` (`flux:navlist`) |

Settings today are **Profile, Security, Appearance** (Security holds Update password, two-factor authentication and
passkeys). Older kit releases split Security into Password and Two-Factor Auth pages; draw what the installed kit has.

## How a mockup uses it

1. Copy `tokens.css` next to the mockup as `starter-kit-tokens.css` and link only that file.
2. `<body class="sk-mockup">` (Livewire look: `class="sk-mockup sk-flux"`). Start from the closest screen in `html/`
   and keep its shell markup; compose content only with classes listed in `components.md`.
3. Sign-in, register, reset and password-change blocks **draw** their fields: `.mock-label` + `.mock-field`
   (`--secret`, `--focus`, `--error`, `--empty`) inside `.sk-password` for the eye toggle. No `<form>`, no password
   input. Other forms may use real `<input>`, `<select>`, `<textarea>`.
4. Dark mode follows the OS; add `class="dark"` on `<html>` to force it (the kit's appearance setting does the same).

**Font.** The kits load **Instrument Sans** (400/500/600) through Vite (`@fonts` / Bunny). Mockups never load web
fonts: the stack starts with `"Instrument Sans"` and falls back to the system sans. Alex keeps the kit's font setup.

## Screens (`html/`)

| Screen | Shows |
| --- | --- |
| `dashboard.html` | Inset sidebar layout, breadcrumbs, stat cards with sparkline and progress, cash-flow bar chart, budget meters, recent transactions, accounts list |
| `dashboard-dark.html` | The same with `html.dark` forced |
| `dashboard-livewire.html` | The same in the Livewire / Flux look (`.sk-flux`) |
| `header-layout.html` | Header layout: nav with active underline, breadcrumb bar, user menu open, account detail page |
| `settings.html`, `settings-security.html`, `settings-appearance.html` | Settings sub-navigation; profile + delete account + toast; drawn password fields with error, 2FA recovery codes, passkeys; Light/Dark/System toggle |
| `login.html`, `auth-split.html`, `auth-register.html` | Fortify auth in the simple, split and card layouts, with passkey button, failed login, taken email |
| `resource-list.html` | Transactions index: search, filters, date range, sort, selection + bulk bar, row menu, badges, pagination |
| `resource-form.html` | Edit form in cards: required marks, error, disabled field, radio cards, select, textarea, switches, upload, sticky actions |
| `empty-state.html` | First-run budgets page and a no-results search |
| `components.html` | Every component in its variants and states |

## Fidelity notes (what is approximated)

- Fonts fall back to the system sans when Instrument Sans is not installed: widths differ by a few pixels.
- Charts are inline SVG drawn to look like Recharts inside a shadcn `ChartContainer`; tick labels are HTML so they
  never scale. In the app use `npx shadcn@latest add chart` (Recharts) or Flux Pro `flux:chart`.
- Open menus, dialogs, toasts and focus rings are drawn statically; the mobile sidebar is shown closed (the trigger
  in the header opens a `Sheet` in the app).
- Status colours (green / amber / blue badges, green amounts) are Ledgerly's: the kit's `Badge` only has default,
  secondary, destructive, outline. Table, Pagination, Tabs, Switch, Progress, Empty, InputGroup, Textarea and
  RadioGroup are not in the kit; their styles follow the current shadcn new-york-v4 registry.
- `.sk-flux` reproduces Flux's measured metrics (40px controls, white current-item pill, zinc palette) but not every
  Flux component; Livewire screens keep the same content classes.

## How Alex builds it

React / Vue: keep the kit; edit `components/app-sidebar.tsx` (`mainNavItems`, `footerNavItems`) and
`components/app-logo-icon.tsx`; add registry components as needed
(`npx shadcn@latest add table tabs switch progress pagination textarea radio-group input-group empty chart`, or
`npx shadcn-vue@latest add …`); build each page as an Inertia page with `AppLayout` and `breadcrumbs`. Livewire: keep
`layouts/app/sidebar.blade.php`, add `flux:sidebar.item`s, build pages with Flux components (`flux:input`,
`flux:select`, `flux:button`, `flux:badge`, `flux:modal`, `flux:dropdown`); table, chart and date picker are Flux Pro.

## Do / don't

- Do keep the kit's shell, settings and auth layouts exactly; put app content inside `.sk-content`.
- Do use `Heading` (`.sk-heading`) for page titles and `--small` for section titles, as the kit does.
- Don't add colours outside the tokens; the kit is neutral. Brand colours go in `--primary` / `--chart-*` overrides.
- Don't draw a real password input, a `<form>`, a web font or a script in a mockup.
