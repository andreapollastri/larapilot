# shadcn/ui (mockup reference)

Product UI built with **shadcn/ui, style `new-york-v4`**: Tailwind CSS v4, oklch colours, base colour `neutral`,
`--radius: 0.625rem`, Lucide icons. Snapshot of the registry (`apps/v4/registry/new-york-v4`) and of the docs'
"Default Theme CSS" taken on **2026-10-12**. The screens reuse the official blocks **sidebar-07** (shell),
**dashboard-01** (section cards, interactive area chart, data table), **login-03** and **login-04**, plus the
**tasks** data-table and **forms** examples. Every screen is drawn for **Orbit**, a fictional issue tracker
(projects, cycles, issues, team) used by the workspace *Halcyon Labs*.

`tokens.css` is the only stylesheet. Its variables have shadcn's real names (`--background`, `--foreground`, `--card`,
`--popover`, `--primary`, `--secondary`, `--muted`, `--accent`, `--destructive`, `--border`, `--input`, `--ring`,
`--chart-1…5`, `--sidebar-*`, `--radius`) and values, so the `:root` and `.dark` blocks paste straight into a real
`globals.css` / `app.css`. Component classes are `.sc-*`, one per shadcn component part: `components.md` lists each
with its React import and Vue equivalent; `sources.md` says what was verified and what was assumed.

## When to use it

- The PRD records **shadcn/ui** (React with Inertia, Next.js, or Vue with shadcn-vue) for the product UI, and the
  screens go beyond the Laravel Starter Kit's own pages: data tables, boards, sheets, command menus, dashboards, a
  marketing page made of shadcn components.
- If the app is a Laravel Starter Kit and the screen is the kit's shell, settings or auth, use `starter-kit/`
  (its components are an older snapshot of the same registry: rounded-md badges, shadow on default buttons, 80%
  dialog overlay, dark cards equal to the background).
- Not for Filament, Nova, AdminLTE or Bootstrap products.

## How a mockup uses it

1. Copy `tokens.css` next to the mockup as `shadcn-tokens.css` and link only that file; `<body class="sc-mockup">`.
2. Start from the nearest screen in `html/`; keep the sidebar-07 shell markup (`.sc-shell` > `aside.sc-sidebar` +
   `main.sc-inset` > `header.sc-header` + `.sc-content`) and compose content only with classes from `components.md`.
3. Dark mode follows the OS; `class="dark"` on `<html>` forces it, exactly like shadcn's `.dark` variant.
4. Credentials are drawn: `.mock-label` + `.mock-field` (`--secret`, `--focus`, `--invalid`, `--empty`). Login and
   sign-up screens have no `<form>`, no text input and never a password input.
5. Open states (menus, dialogs, sheets, toasts, tooltips) are drawn statically, one per screen where possible.

**Font.** shadcn ships no font; ui.shadcn.com and the Next.js templates use **Geist** / Geist Mono. The stack starts
with `Geist`, then the system sans. Alex loads the font the project chose (Geist, Inter or the brand font).

## Screens (`html/`)

| Screen | Built from |
| --- | --- |
| `dashboard.html`, `dashboard-dark.html` | sidebar-07 shell (team switcher, collapsible NavMain, NavProjects, NavUser) + dashboard-01 SectionCards (trend badges), ChartAreaInteractive (stacked areas, ToggleGroup), DataTable (tabs, drag handles, inline inputs, reviewer select, row menu open) |
| `resource-list.html` | tasks data table: filter input, faceted filters with chips, column visibility menu open, sortable headers, selection, floating action bar, pagination |
| `resource-form.html` | Field / FieldSet / FieldLegend / FieldSeparator form: error, disabled field, Select, date picker, choice cards, InputGroup, labels, Switch card, Checkbox, drop zone, sticky actions |
| `kanban.html` | Cycle board: Tabs, Progress, columns with counts, issue Cards with Badges, Avatar, priority and due date, a card mid-drag |
| `settings.html` | The forms example (settings nav + profile form) inside the shell, with an error and a Sonner toast |
| `empty-state.html` | Empty: first-run project, no search results, invite with an avatar group |
| `slide-over.html` | Sheet (side right) editing an issue over the list |
| `modal.html` | Dialog (invite) with a nested AlertDialog `size="sm"` |
| `login.html`, `auth-register.html` | login-03 (brand + card + social buttons) and login-04 (two-column card with art panel) |
| `landing.html` | Marketing page: header, hero with product shot, logos, features, pricing with Tabs, testimonial, Accordion FAQ, CTA, footer |
| `components.html` | Every component in its variants and states |

## Mockup → real code

Each `.sc-` block maps to one component, e.g. `.sc-btn.sc-btn--outline.sc-btn--sm` → `<Button variant="outline"
size="sm">`, `.sc-card__header` + `.sc-card__action` → `<CardHeader>` + `<CardAction>`, `.sc-sidebar__sub` →
`<SidebarMenuSub>`, `.sc-sheet` → `<SheetContent side="right">`. Install with `npx shadcn@latest add <name>`
(React) or `npx shadcn-vue@latest add <name>` (Vue); blocks with `npx shadcn@latest add sidebar-07 dashboard-01
login-03`. In a Laravel + Inertia app the components land in `resources/js/components/ui`; `components.json` uses
`"style": "new-york"`, `"baseColor": "neutral"`, `"cssVariables": true`, `"iconLibrary": "lucide"`.

## Fidelity notes

- Charts are inline SVG shaped like Recharts in `ChartContainer` (stacked natural areas with gradients, horizontal
  grid at border/50, 12px muted ticks, tooltip card); in the app use the registry `chart` component.
- dashboard-01 uses Tabler icons; Orbit uses the Lucide equivalents, shadcn's default icon library.
- Section cards switch to 2 and 4 columns with container queries on the main column (`@xl/main`, `@5xl/main`), as
  the block does; the card value grows at 250px card width.
- The floating selection bar follows the common data-table action-bar pattern (not a registry primitive); the
  kanban board, drop zone, tag input, landing sections and product shot are app compositions of registry parts.
- Some forms use a native `<select>` styled like the trigger; `.sc-select-trigger` draws the Radix Select trigger.

## Do / don't

- Do keep tokens untouched; brand a product by changing `--primary`, `--ring`, `--chart-*`, `--sidebar-primary`.
- Do use `CardAction` for header buttons and badges, `Field` for every label + control pair, `Badge variant="outline"`
  with a muted icon for statuses (as dashboard-01 and the tasks table do).
- Don't mix Starter Kit (`.sk-*`) and shadcn (`.sc-*`) classes in one screen.
- Don't add shadows, gradients or colours the registry doesn't have; shadcn is flat and neutral by design.
