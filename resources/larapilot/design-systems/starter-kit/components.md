# Laravel Starter Kits — component & layout patterns

Reference for Elise when building authenticated-app mockups. Match the official kits — see `sources.md` and [starter-kits docs](https://laravel.com/docs/starter-kits). Use `tokens.css` classes where possible.

**Packaged HTML:** static screens live in `html/` — see `html/README.md` and open `html/index.html` for the catalog.

## App shell (sidebar layout — default)

```
┌──────────────┬────────────────────────────────────────┐
│  Sidebar     │  SidebarInset (main)                   │
│  (light bg)  │  ┌─ SidebarHeader (breadcrumbs) ─────┐ │
│  Logo        │  │                                   │ │
│  Nav groups  │  │  Page content / cards / forms     │ │
│  Footer links│  │                                   │ │
│  User menu   │  └───────────────────────────────────┘ │
└──────────────┴────────────────────────────────────────┘
```

- **Sidebar:** light `sidebar` background; logo at top; nav groups with small uppercase labels; active item uses `sidebar-accent` subtle fill (not Filament's dark sidebar + amber).
- **Sidebar footer:** external links (Repository, Documentation) + user avatar dropdown (Settings, Log out).
- **Main inset:** `background` canvas; optional subtle border radius on inset variant.
- **Header strip:** breadcrumbs (muted) + optional actions — not a heavy topbar like Filament.

HTML skeleton:

```html
<body class="sk-mockup">
  <div class="sk-layout">
    <aside class="sk-sidebar" aria-label="Sidebar">…</aside>
    <div class="sk-inset">
      <header class="sk-inset-header">…</header>
      <main class="sk-inset-content">…</main>
    </div>
  </div>
</body>
```

## App shell (header layout)

- Horizontal top nav with logo left, main links center/right, user menu far right.
- Content full-width below — see `html/header-layout.html`.

## Page types (map to starter kit)

| Mockup screen | Kit implementation |
| --- | --- |
| Dashboard (placeholder grid) | `resources/js/pages/dashboard.tsx` or Livewire dashboard route |
| Login (card) | Fortify login page — `auth/login` Inertia page or Livewire auth view |
| Auth split | Kit auth layout variant (split panel) |
| Settings / Profile | Profile, password, appearance settings pages |
| Resource list (custom) | New Inertia/Livewire pages inside `AppLayout` — not Filament Resources |

## Auth layouts

Official kits ship three auth layout styles:

| Layout | Use |
| --- | --- |
| **Simple** | Centered card on neutral background — `html/login.html` |
| **Card** | Card with subtle border/shadow on `muted` canvas |
| **Split** | Brand/illustration panel + form column — `html/auth-split.html` |

- Email + password fields with `Label` above `Input`.
- Primary **Log in** button full width (`sk-btn--primary`).
- **Remember me** checkbox row.
- Secondary links: Forgot password, Sign up (when registration enabled).

## Settings pages

- Vertical nav or tab list: Profile, Password, Appearance (matches kit defaults).
- Sections in **cards** (`.sk-card`) with heading + description.
- Profile: name, email; Password: current + new + confirm; Appearance: light/dark/system toggle.
- Save actions use primary button; destructive actions use `sk-btn--destructive`.

## Buttons & inputs

| Variant | Class | Use |
| --- | --- | --- |
| Primary | `sk-btn--primary` | Log in, Save, Submit |
| Outline | `sk-btn--outline` | Secondary actions |
| Ghost | `sk-btn--ghost` | Tertiary / icon triggers |
| Destructive | `sk-btn--destructive` | Delete account, remove |
| Link | `sk-btn--link` | Inline text actions |

Inputs: `sk-input` with visible focus ring (`ring` token). Checkboxes use `sk-checkbox`.

## Cards & placeholders

- Dashboard default shows **placeholder pattern** — dashed diagonal grid in a rounded card (`.sk-placeholder`).
- Stat cards and content blocks use `.sk-card` with `border` + `shadow-xs` feel.

## Breadcrumbs

- Muted small text; current page semibold/darker.
- Separator `/` or chevron between crumbs — see dashboard header.

## Responsive

| Breakpoint | Behavior |
| --- | --- |
| ≥1024px | Sidebar visible; `sk-layout` side-by-side |
| <1024px | Sidebar hidden; `sk-sidebar-toggle` opens drawer/sheet overlay |

Authenticated mockups follow **Mobile First** per shared-runtime — document drawer behavior in README.

## Variant-specific notes

| Variant | Elise notes in README |
| --- | --- |
| **livewire** | Map to Flux components (`flux:sidebar`, `flux:button`, …) |
| **react** | Map to shadcn/ui (`Sidebar`, `Button`, `Input`, …) |
| **vue** | Map to shadcn-vue equivalents |
| **svelte** | Map to shadcn-svelte equivalents |

Visual mockups stay identical across variants — only the implementation mapping differs.
