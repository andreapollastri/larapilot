# Tailwind CSS — component & layout patterns

Reference for Elise when building Tailwind-only mockups. Use **utility classes directly** in HTML — no `.fi-*` or `.sk-*` wrappers.

**Packaged HTML:** static screens live in `html/` — open `html/index.html` for the catalog.

## App shell (sidebar layout)

```
┌──────────────┬────────────────────────────────────────┐
│  Sidebar     │  Top bar (breadcrumb, actions)         │
│  w-64 white  ├────────────────────────────────────────┤
│  border-r    │  Main on slate-50 canvas                │
│  Nav links   │  White rounded-xl cards                │
└──────────────┴────────────────────────────────────────┘
```

Tailwind skeleton:

```html
<body class="min-h-screen bg-slate-50 font-sans text-slate-900">
  <div class="flex min-h-screen">
    <aside class="hidden w-64 shrink-0 flex-col border-r border-slate-200 bg-white md:flex">…</aside>
    <div class="flex min-w-0 flex-1 flex-col">
      <header class="sticky top-0 z-10 flex h-14 items-center border-b border-slate-200 bg-white px-6">…</header>
      <main class="flex-1 p-6">…</main>
    </div>
  </div>
</body>
```

- **Sidebar:** `bg-white border-r border-slate-200`; active link `bg-indigo-50 text-indigo-700`.
- **Top bar:** `sticky top-0 bg-white shadow-sm`.
- **Cards:** `rounded-xl border border-slate-200 bg-white p-6 shadow-sm`.

## Marketing site shell

- **Header:** `flex items-center justify-between px-6 py-4` with logo, nav links (`hidden md:flex`), CTA button.
- **Hero:** `py-20 text-center` with `text-4xl font-bold tracking-tight`, lead `text-lg text-slate-600`.
- **Features:** `grid gap-8 md:grid-cols-3` with icon circles `rounded-lg bg-indigo-50 p-3 text-indigo-600`.
- **Footer:** `border-t border-slate-200 bg-slate-100 py-10`.

## Page types

| Mockup screen | Tailwind implementation |
| --- | --- |
| Landing | Blade layout + utility classes |
| Dashboard | Sidebar + stat grid + responsive table |
| Login | `min-h-screen grid place-items-center` + card |
| Settings | Form sections in stacked cards inside app shell |
| Modal | Fixed overlay + centered panel (`fixed inset-0 z-50`) |

## Common utilities

| Element | Classes |
| --- | --- |
| Primary button | `rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600` |
| Secondary button | `rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50` |
| Text input | `block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500` |
| Badge success | `inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800` |
| Alert | `rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800` |

## Dark mode

Add `class="dark"` to `<html>`. Pair light/dark utilities: `bg-white dark:bg-slate-900`, `text-slate-900 dark:text-slate-50`.

## Mobile

- Hide sidebar on mobile: `hidden md:flex`; show hamburger `md:hidden`.
- Stack stat cards: `grid gap-4 sm:grid-cols-2 xl:grid-cols-4`.
- Table wrapper: `overflow-x-auto`.
