# Filament — component & layout patterns

Reference for Elise when building admin mockups. Match the merged Figma kits ([Design System](https://www.figma.com/community/file/1413822581847485668/filament-3-design-system), [UI Kit Free](https://www.figma.com/community/file/1417716904167561805/filament-3-free)) and [Filament demo](https://demo.filamentphp.com/) — see `figma-sources.md`. Use `tokens.css` classes where possible.

**Packaged HTML:** full static screens live in `html/` — see `html/README.md` and open `html/index.html` for the catalog. Copy screens into `.larapilot/mockups/{spec}/` as starting points.

## App shell

```
┌─────────────┬──────────────────────────────────────┐
│  Sidebar    │  Topbar (user menu, notifications)   │
│  (nav)      ├──────────────────────────────────────┤
│             │  Page header (title + breadcrumbs)   │
│             │  ┌────────────────────────────────┐  │
│             │  │ Section(s) / table / form      │  │
│             │  └────────────────────────────────┘  │
└─────────────┴──────────────────────────────────────┘
```

- **Sidebar:** dark zinc background; brand at top; nav groups with icons + labels; active item uses primary background.
- **Topbar:** white (light) / gray-900 (dark); global search optional; user avatar dropdown on the right.
- **Content area:** gray-100 (light) / gray-900 (dark) canvas; white cards for sections.

HTML skeleton:

```html
<body class="fi-mockup">
  <div class="fi-layout">
    <aside class="fi-sidebar">…</aside>
    <div class="fi-main">
      <header class="fi-topbar">…</header>
      <main class="fi-content">…</main>
    </div>
  </div>
</body>
```

## Page types (map to Filament)

| Mockup screen | Filament implementation |
| --- | --- |
| Dashboard with stats/widgets | `Widgets` on a custom or default Dashboard page |
| Resource index (table + filters) | `Resource` list page + table columns + filters |
| Create / Edit record | `Resource` form schema (sections, tabs, repeater) |
| View record | `Resource` infolist / view page |
| Settings | Custom `Page` or `SettingsPage` plugin |
| Login (panel) | Filament auth pages (centered card on gray canvas) |

## Tables (index pages)

- Toolbar above table: search, filter trigger, **Create** primary button (top-right).
- Column headers: muted, semibold; row hover subtle gray background.
- Row actions: icon buttons or kebab menu (Edit, Delete, View).
- Bulk actions: checkbox column + bulk action bar when rows selected.
- Pagination footer: "Showing X to Y of Z" + page controls.
- Empty state: illustration/icon, short message, primary CTA to create first record.

## Forms

- Group fields in **sections** (`.fi-section`) with headings.
- Two-column layout on desktop for short fields; single column on mobile.
- Required fields: asterisk on label; inline validation message below control in danger color.
- Actions bar: sticky footer or section bottom — **Save**, **Cancel**, **Delete** (danger, separated).
- Use native-looking inputs from `tokens.css`; toggles/checkboxes follow Filament's rounded switch style in Figma.

## Actions & buttons

| Variant | Use |
| --- | --- |
| Primary (amber) | Main CTA — Save, Create, Submit |
| Secondary (outline) | Cancel, Back |
| Danger (red) | Delete, destructive confirm |
| Ghost/link | Tertiary actions in tables |

Icon-only actions need `aria-label` in mockup HTML and README.

## Notifications & badges

- **Badge** pills for status (success/warning/danger/info) — see `.fi-badge--*` in tokens.
- Toast notifications: top-right stack; auto-dismiss; success green left border.

## Auth (panel login)

- Centered card (~24rem wide) on full-viewport gray background.
- Logo + "Sign in" heading.
- Email + password fields; "Remember me" checkbox; primary **Sign in** full-width button.
- Optional: forgot password link below form.

## Responsive (admin)

| Breakpoint | Behavior |
| --- | --- |
| ≥1024px | Full sidebar visible |
| 768–1023px | Collapsible sidebar (icon-only or drawer — document choice in README) |
| <768px | Sidebar becomes horizontal scroll nav or hamburger drawer; table horizontal scroll; form single column |

Admin mockups are **desktop-first for density** but must still document mobile collapse behavior per shared-runtime Mobile First rules.

## UI Kit (Free) — additional patterns

Packaged HTML for frames emphasized in [UI Kit (Free)](https://www.figma.com/community/file/1417716904167561805/filament-3-free):

| Pattern | HTML | Filament implementation |
| --- | --- | --- |
| Widget dashboard | `html/widgets-dashboard.html` | Dashboard `Widget` classes + Chart widget |
| Multi-step wizard | `html/form-wizard.html` | Wizard component or stepped Livewire form |
| Relation manager | `html/relation-manager.html` | `RelationManager` on Resource edit page |
| Filter chips | `html/filters.html` | Table `->filters()` with indicators |
| Cards & nav groups | `html/cards-navigation.html` | Custom pages, grouped `navigationItems` |
| Toggle, file upload | `html/components.html` | Form `Toggle`, `FileUpload` fields |

