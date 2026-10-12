# shadcn/ui — sources

Version targeted: shadcn/ui **new-york-v4** (Tailwind CSS v4, oklch, base colour `neutral`), registry at
`shadcn-ui/ui` `main` and the theming docs as fetched on **2026-10-12**. Icons: Lucide (`lucide-static` 1.55.0).

## Official sources

| Source | URL | Used for |
| --- | --- | --- |
| Theming docs ("Default Theme CSS") | https://ui.shadcn.com/docs/theming | Every `:root` / `.dark` variable, radius scale |
| Theming source | https://github.com/shadcn-ui/ui/blob/main/apps/v4/content/docs/(root)/theming.mdx | Same values, verbatim |
| Registry components | https://github.com/shadcn-ui/ui/tree/main/apps/v4/registry/new-york-v4/ui | Class strings of button, badge, card, table, input, textarea, select, native-select, checkbox, radio-group, switch, tabs, toggle-group, dialog, alert-dialog, sheet, dropdown-menu, popover, tooltip, command, sonner, alert, avatar, breadcrumb, pagination, progress, skeleton, empty, field, input-group, kbd, separator, sidebar |
| Blocks | https://ui.shadcn.com/blocks — sources in https://github.com/shadcn-ui/ui/tree/main/apps/v4/registry/new-york-v4/blocks | sidebar-07 (`app-sidebar`, `team-switcher`, `nav-main`, `nav-projects`, `nav-user`, `page`), dashboard-01 (`section-cards`, `chart-area-interactive`, `data-table`, `site-header`, `page`), login-03, login-04 |
| Examples | https://ui.shadcn.com/examples/tasks and https://ui.shadcn.com/examples/forms | Data-table toolbar / faceted filter / view options / pagination; settings layout and profile form copy |
| Charts | https://ui.shadcn.com/charts | Area chart interactive with `--chart-1` / `--chart-2`, legend and tooltip anatomy |
| Vue port | https://www.shadcn-vue.com | Component names for the Vue mapping |
| Icons | https://lucide.dev and https://www.npmjs.com/package/lucide-static (1.55.0) | Paths copied path for path; license ISC (https://github.com/lucide-icons/lucide/blob/main/LICENSE) |

## Verified against the source

| Value | Source file |
| --- | --- |
| Light: `--background oklch(1 0 0)`, `--foreground 0.145`, `--primary 0.205`, `--secondary/--muted/--accent 0.97`, `--muted-foreground 0.556`, `--destructive oklch(0.577 0.245 27.325)`, `--border/--input 0.922`, `--ring 0.708`, `--chart-1…5`, `--sidebar 0.985`, `--sidebar-ring 0.708`, `--radius 0.625rem` | theming.mdx |
| Dark: `--card/--popover 0.205`, `--primary 0.922`, `--destructive oklch(0.704 0.191 22.216)`, `--border oklch(1 0 0 / 10%)`, `--input 15%`, `--ring 0.556`, dark `--chart-*`, `--sidebar-primary oklch(0.488 0.243 264.376)` | theming.mdx |
| Radius scale `sm = radius × 0.6`, `md × 0.8`, `lg = radius`, `xl × 1.4` | theming.mdx |
| Button: h-9 px-4 (has svg px-3), sm h-8 px-3, xs h-6 text-xs, lg h-10 px-6, icon size-9 / 8 / 6; default without shadow; outline `bg-background shadow-xs`, dark `bg-input/30 border-input`; ghost dark `hover:bg-accent/50`; destructive dark `bg-destructive/60` | ui/button.tsx |
| Badge rounded-full px-2 py-0.5 text-xs, outline `border-border` | ui/badge.tsx |
| Card rounded-xl py-6 gap-6 shadow-sm; header grid gap-2 with CardAction column; title leading-none semibold | ui/card.tsx |
| Input h-9 px-3 rounded-md shadow-xs, dark `bg-input/30`, focus `ring-[3px] ring-ring/50`, invalid `ring-destructive/20` (dark /40) | ui/input.tsx |
| Field: flex-col gap-3; FieldGroup gap-7 (nested gap-4); FieldSet gap-6; legend mb-3 text-base; FieldDescription text-sm muted; FieldError destructive; FieldSeparator h-5 with centred content | ui/field.tsx |
| InputGroup one bordered box h-9, addon text-sm medium muted | ui/input-group.tsx |
| SelectTrigger h-9 / sm h-8 px-3 gap-2, chevron opacity-50; NativeSelect pr-9 | ui/select.tsx, ui/native-select.tsx |
| Checkbox size-4 rounded-[4px], check size-3.5; RadioGroup dot size-2; Switch h-[1.15rem] w-8 thumb size-4, dark thumb foreground / primary-foreground | ui/checkbox.tsx, radio-group.tsx, switch.tsx |
| Tabs list `bg-muted rounded-lg p-[3px] h-9`, trigger `text-foreground/60`, active `bg-background shadow-sm`, dark `border-input bg-input/30`; line variant underline | ui/tabs.tsx |
| ToggleGroup outline, spacing 0, items joined with shared borders | ui/toggle-group.tsx, ui/toggle.tsx |
| Table head h-10 px-2 `text-foreground`, cells p-2, row hover `muted/50`, selected `bg-muted`, footer `bg-muted/50`, caption mt-4 muted | ui/table.tsx |
| Dialog / AlertDialog / Sheet overlay `bg-black/50`; dialog max-w-lg p-6 gap-4 rounded-lg; title text-lg; footer flex-col-reverse → row; AlertDialog sizes default / sm (max-w-xs, 2-column footer), media size-16 rounded-md bg-muted; Sheet right w-3/4 sm:max-w-sm, header/footer p-4, footer stacked | ui/dialog.tsx, alert-dialog.tsx, sheet.tsx |
| DropdownMenu p-1 rounded-md shadow-md, item px-2 py-1.5 rounded-sm, destructive item, checkbox item pl-8 with indicator at left-2, shortcut text-xs tracking-widest | ui/dropdown-menu.tsx |
| Tooltip `bg-foreground text-background text-xs px-3 py-1.5` + rotated arrow; Popover w-72 p-4; Kbd h-5 min-w-5 rounded-sm bg-muted | ui/tooltip.tsx, popover.tsx, kbd.tsx |
| Command input h-9 border-b, list max-h-[300px], heading text-xs muted, item selected `bg-accent` | ui/command.tsx |
| Sonner uses `--popover`, `--popover-foreground`, `--border`, `--radius` | ui/sonner.tsx |
| Alert grid with 16px icon column, title medium tracking-tight, destructive on `bg-card` | ui/alert.tsx |
| Avatar size-8 rounded-full, fallback `bg-muted text-sm text-muted-foreground`; group `-space-x-2` with ring-background | ui/avatar.tsx |
| Progress h-2 `bg-primary/20`; Skeleton `bg-accent animate-pulse`; Empty media size-10 rounded-lg, title text-lg medium | ui/progress.tsx, skeleton.tsx, empty.tsx |
| Sidebar 16rem / icon 3rem; hidden below 768px; group p-2; label h-8 text-xs `/70`; menu button h-8 p-2, lg h-12; sub `mx-3.5 px-2.5 border-l`, sub button h-7; menu action top-1.5 right-1 w-5 | ui/sidebar.tsx |
| sidebar-07: team logo size-8 rounded-lg `bg-sidebar-primary`; header h-16 with trigger -ml-1 + vertical separator + breadcrumb; content `p-4 pt-0`; NavUser avatar rounded-lg | blocks/sidebar-07 |
| dashboard-01: section cards gradient `from-primary/5 to-card` (dark `bg-card`), `shadow-xs`, `text-2xl @[250px]/card:text-3xl`, grid `@xl/main:2 @5xl/main:4`; chart height 250px, gradients 5% → 95%, `CartesianGrid vertical={false}`; table head `bg-muted`, drag handle size-7 icon size-3, inline inputs h-8 w-16, reviewer `SelectTrigger size="sm" w-38`, actions `EllipsisVertical` | blocks/dashboard-01 |
| login-03 (bg-muted, max-w-sm, brand size-6 `bg-primary`, card title text-xl, Apple + Google buttons, FieldSeparator on `bg-card`, terms line) and login-04 (max-w-4xl, card p-0, two columns, form p-6 md:p-8, h1 text-2xl bold, three icon buttons, image panel) | blocks/login-03, login-04 |

## Assumed

| Value | Why |
| --- | --- |
| `Geist` first in the font stack | shadcn ships no font; Geist is what ui.shadcn.com uses |
| Area chart series as `--chart-1` / `--chart-2` with a legend (dashboard-01 uses `--primary` for both) | Two colours keep the series distinguishable; matches the charts page example |
| Chart curve, grid count (5), tooltip position, x tick spacing | Drawn, not rendered by Recharts |
| Floating selection action bar | Common TanStack data-table pattern, not a registry primitive |
| Kanban board, tag input, drop zone, file row, landing sections, product shot | App compositions of registry parts |
| Green done icon in lists (`--sc-done`) | dashboard-01 uses `fill-green-500 dark:fill-green-400` on a filled Tabler icon; Lucide's outline check is used here |
| Sonner geometry (356px, 16px padding, 13px, `0 4px 12px` shadow, 24px offset) | Sonner's default stylesheet, not re-measured |
| Kbd text, muted text in selected rows and shortcuts on a highlighted item at oklch 0.52 (`--sc-muted-on-muted`) instead of `--muted-foreground` | Accessibility: muted-foreground on muted is 4.3:1; the contract asks for WCAG AA. Tokens themselves are unchanged |
| Orbit / Halcyon Labs names, people, issues and numbers | Fictional |
