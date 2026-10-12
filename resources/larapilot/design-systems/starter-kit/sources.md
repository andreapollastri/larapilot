# Laravel Starter Kit — sources

Version targeted: the `main` branches of the official kits as fetched on **2026-10-12** (Laravel 13.x; the
Livewire kit's `composer.json` requires `laravel/framework ^13.17`, `livewire/livewire ^4.1`, `livewire/flux ^2.13.1`).
The same layouts ship with Laravel 12 kits; only details noted below differ.

## Official sources

| Source | URL | Used for |
| --- | --- | --- |
| Starter kits overview | https://laravel.com/starter-kits | Variants and stacks |
| Docs (12.x / 13.x) | https://laravel.com/docs/12.x/starter-kits | Sidebar / header layouts, sidebar variants, auth layout variants (simple, card, split), Fortify features |
| React kit | https://github.com/laravel/react-starter-kit | `resources/css/app.css`, `components/app-sidebar.tsx`, `nav-main.tsx`, `nav-footer.tsx`, `nav-user.tsx`, `user-info.tsx`, `user-menu-content.tsx`, `app-sidebar-header.tsx`, `app-header.tsx`, `app-content.tsx`, `heading.tsx`, `text-link.tsx`, `password-input.tsx`, `passkey-verify.tsx`, `passkey-item.tsx`, `manage-passkeys.tsx`, `manage-two-factor.tsx`, `two-factor-recovery-codes.tsx`, `delete-user.tsx`, `appearance-tabs.tsx`, `components/ui/*.tsx`, `layouts/**`, `pages/dashboard.tsx`, `pages/auth/login.tsx`, `register.tsx`, `pages/settings/*.tsx` |
| Vue kit | https://github.com/laravel/vue-starter-kit | `resources/css/app.css` (same palette in HSL, radius 0.5rem), `layouts/settings/Layout.vue` |
| Livewire kit | https://github.com/laravel/livewire-starter-kit | `resources/css/app.css` (zinc remapped to neutral, accent), `layouts/app/sidebar.blade.php`, `layouts/app/header.blade.php`, `components/desktop-user-menu.blade.php`, `pages/settings/layout.blade.php`, `dashboard.blade.php` |
| Flux UI | https://github.com/livewire/flux (stubs) and https://fluxui.dev | `sidebar/index`, `sidebar/item`, `input/index`, `button/index`, `main` class strings |
| shadcn/ui registry | https://github.com/shadcn-ui/ui/tree/main/apps/v4/registry/new-york-v4/ui | Components the kit does not ship (Table, Tabs, Switch, Progress, Pagination, Empty, InputGroup, Textarea, RadioGroup, Field) |
| Icons | https://lucide.dev — `lucide-static` 1.55.0 from https://www.npmjs.com/package/lucide-static | Inline SVG paths, copied path for path. License: ISC (https://github.com/lucide-icons/lucide/blob/main/LICENSE). Lucide is what the kits import (`lucide-react`, `lucide-vue-next`; Flux icons for Livewire). |

## Verified against the source

| Value | Where it comes from |
| --- | --- |
| All `:root` / `.dark` colour variables, `--radius: 0.625rem`, `--radius-md/-sm = radius - 2px / - 4px` | React kit `resources/css/app.css` |
| `--destructive-foreground` equals `--destructive` in light (red text), white text on destructive buttons | React kit `app.css`, `components/ui/button.tsx` |
| Sidebar 16rem, icon rail 3rem, mobile 18rem; hidden below 768px (`md`) | `components/ui/sidebar.tsx`, `use-mobile` |
| Inset variant: wrapper `bg-sidebar`, sidebar `p-2`, content `md:m-2 md:ml-0 rounded-xl shadow-sm` | `components/ui/sidebar.tsx`, `app-sidebar.tsx` |
| Menu button h-8 p-2 gap-2 rounded-md text-sm; size lg h-12; group label h-8 text-xs `sidebar-foreground/70` | `components/ui/sidebar.tsx` |
| Logo square size-8 rounded-md `bg-sidebar-primary`, icon size-5 white / black in dark; name text-sm semibold | `components/app-logo.tsx` |
| Footer links `text-neutral-600 dark:text-neutral-300`, icons h-5 w-5, group p-2 | `components/nav-footer.tsx` |
| Avatar fallback `bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white`, size-8 rounded-full | `components/user-info.tsx` |
| Header h-16, `border-sidebar-border/50`, px-6 / md:px-4, trigger -ml-1 size-7 | `components/app-sidebar-header.tsx`, `ui/sidebar.tsx` |
| Header layout: max-w-7xl, h-16, active underline 2px black / white, crumb bar h-12 `text-neutral-500`, icon links size-9 icons size-5 opacity-80, avatar button size-10 | `components/app-header.tsx` |
| Content `flex-1 gap-4 rounded-xl p-4`; settings `px-4 py-6`, nav `lg:w-48`, panel `max-w-xl space-y-12` | `pages/dashboard.tsx`, `layouts/settings/layout.tsx` |
| Settings sections Profile / Security / Appearance; Security = Update password + Two-factor + Passkeys | `layouts/settings/layout.tsx`, `pages/settings/security.tsx` |
| Heading text-xl semibold tracking-tight mb-8; small text-base medium | `components/heading.tsx` |
| Card rounded-xl border py-6 gap-6 shadow-sm, header gap-1.5 px-6, title leading-none semibold | `components/ui/card.tsx` |
| Button h-9 px-4 (icon px-3) rounded-md shadow-xs; sm h-8; lg h-10; icon size-9 | `components/ui/button.tsx` |
| Badge rounded-md px-2 py-0.5 text-xs medium | `components/ui/badge.tsx` |
| Input h-9 px-3 py-1 rounded-md border-input shadow-xs, focus ring 3px `ring/50`, invalid `ring-destructive/20` | `components/ui/input.tsx` |
| Checkbox size-4 rounded-[4px], check size-3.5 | `components/ui/checkbox.tsx` |
| Dropdown content p-1 rounded-md shadow-md, item px-2 py-1.5 rounded-sm, destructive item | `components/ui/dropdown-menu.tsx` |
| Dialog overlay `bg-black/80`, content max-w-lg p-6 gap-4 rounded-lg, title text-lg | `components/ui/dialog.tsx` |
| Appearance toggle `bg-neutral-100 p-1 gap-1 rounded-lg`, item `px-3.5 py-1.5`, active white + shadow-xs | `components/appearance-tabs.tsx` |
| Delete account box `border-red-100 bg-red-50 text-red-600` (dark `red-200/10`, `red-700/10`, `red-100`) | `components/delete-user.tsx` |
| Auth simple: max-w-sm, gap-8, logo size-9, h1 text-xl medium; card: bg-muted, max-w-md, card header px-10 pt-8, content px-10 py-8; split: zinc-900 panel, form 350px | `layouts/auth/*.tsx` |
| Login: passkey outline button + "Or continue with email" separator, Remember me, submit mt-4 w-full, Sign up TextLink | `pages/auth/login.tsx`, `passkey-verify.tsx` |
| Password input pr-10 with Eye toggle | `components/password-input.tsx` |
| Passkey row: icon box size-10 rounded-xl, uppercase 11px authenticator chip, "Added … / Last used …" | `components/passkey-item.tsx` |
| Placeholder pattern path and stroke `neutral-900/20` (dark `neutral-100/20`) | `components/ui/placeholder-pattern.tsx`, `pages/dashboard.tsx` |
| Registry Table h-10 head p-2 cell, Tabs `bg-muted p-[3px] h-9`, Switch h-[1.15rem] w-8, Progress h-2 `bg-primary/20`, Empty media size-10 | shadcn new-york-v4 `ui/*.tsx` |
| Livewire look: sidebar `bg-zinc-50 border-e border-zinc-200` (dark `zinc-900`, `zinc-700`), body `bg-white dark:bg-zinc-800`, zinc = neutral hex scale, accent neutral-800 / white | `layouts/app/sidebar.blade.php`, `resources/css/app.css` |
| Flux sidebar w-64 p-4 gap-4; item h-8 gap-3 rounded-lg px-3, current `bg-white border-zinc-200` (dark `white/7%`); input and button h-10 rounded-lg; main p-6 lg:p-8 | `livewire/flux` stubs |

## Assumed

| Value | Why |
| --- | --- |
| Font fallback to the system sans | Mockups load no web font by contract; the real app loads Instrument Sans |
| Status tints (green / amber / blue), green income amounts, amber review alert | Ledgerly conventions; the kit has no success or warning variant |
| Bar chart geometry (24px bars, 3px radius, 5 grid lines) and sparkline | Drawn to resemble Recharts in a shadcn chart; not measured |
| Sonner metrics (356px, 16px padding, 13px text, `0 4px 12px` shadow) | Sonner's default stylesheet, not re-checked for this version |
| Older kits splitting Security into Password and Two-Factor Auth pages | From memory of the 2025 releases |
| Flux group heading colour, sidebar profile, card shadow in `.sk-flux` | Not in the fetched stubs |
| Which Flux components are Pro (table, chart, date picker, file upload, pagination, tabs) | From the Flux site's component list, not re-verified |
| Status green `--sk-success` = green-700 in light (the kit uses green-600) | Accessibility: green-600 on white is 3.2:1; the contract asks for WCAG AA |
| Appearance-toggle inactive text and muted text on muted surfaces (selected rows, bulk bar) at oklch 0.52 instead of neutral-500 | Accessibility: neutral-500 on neutral-100 is 4.3:1 |
| Ledgerly logo, data and copy | Fictional |
