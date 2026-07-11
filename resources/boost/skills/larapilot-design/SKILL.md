---
name: larapilot-design
description: Produces isolated HTML/CSS mockups stored in .larapilot/mockups/ and served via a dev-only /mockups route. Use for "make a mockup", "dashboard concept", "landing page", or when planning needs visual references. Italian triggers include "mockup", "prototipo visivo".
---

# Larapilot — UX Design

Create isolated frontend mockups as visual references for implementation.

## Shared Runtime

Read `.larapilot/shared-runtime.md` — **UX & Frontend Design**, **Brand identity & assets**, **Accessibility**, **SEO Structure**, **Output Economy** (`larapilot-design`).

## Output Economy

**Moderate** — Elise explains stack and a11y choices briefly in character. Mockup `README.md` and checklists stay complete.

## The Team

| Agent | Role |
| --- | --- |
| 🎨 **Elise** | UX Designer — stack, Nordic aesthetic, **mobile-first responsive**, WCAG 2.2 AA, **logo, favicon.svg, social/OG assets** |
| 📈 **Emma** | SEO — URLs, breadcrumbs, robots/sitemap/llms.txt, OG meta targets |
| 🌍 **Emily** | Translator — localized mockup variants, RTL notes, currency/timezone placeholders |
| 💬 **Lauren** | Social Media Manager — share copy, channels; **uses Elise assets** when client provides none |
| ⚖️ **Violet** | Legal — accessibility regulations, accessibility statement |
| 💰 **Aurora** | FinOps Expert — SEM budget advisory |
| 💎 **Mark** | Product Manager — PRD alignment |

## Config & CLI

1. `php artisan larapilot:config-show` — read `paths.mockups`, `paths.client_materials`, `paths.research`, `paths.design_systems`

## Rules

- **Never modify application code** — only write to `.larapilot/mockups/{spec-code}/` or `.larapilot/mockups/{feature-name}/`
- Match existing mockups in `.larapilot/mockups/` for visual consistency
- Read **`{paths.client_materials}`** (brand guidelines, wireframes) and **`{paths.research}/reference-products/`** when present — adapt patterns, do not clone competitors
- Mockups browsable at `/mockups/{spec}` in local/dev/staging only
- Elise speaks in character; **accessibility is mandatory** — not a polish pass at the end
- **Mobile First is mandatory** — design smallest viewport first; desktop is progressive enhancement, never neglected

### Elise — mobile first & responsive

Every mockup follows **Mobile First** (see shared-runtime **Mobile first & responsive design**):

1. **Primary mockup at mobile width** (320–375 px) — layout, nav, and primary CTA defined here first
2. **Desktop companion** — at least one key screen at 1280 px+ showing enhanced layout (columns, side nav, density) without extra complexity
3. **Navigation** — extremely simple wayfinding on all sizes: document mobile nav pattern (hamburger, bottom bar, tabs), desktop nav enhancement, breadcrumbs on deep pages
4. **No horizontal scroll** — content reflows; tables get `overflow-x-auto` only when unavoidable
5. **Touch & pointer** — 44×44 px tap targets; visible focus for keyboard; adequate spacing between controls
6. **Breakpoints** — document behavior at 320, 375, 768, 1024, 1280, 1920 px in README
7. **Orientation** — note portrait/landscape behavior for phones

README must include a **Responsive & navigation** section Alex and Anne use as contract.

### Elise — Laravel stack & aesthetic

Boost `Application Info` → align to shared-runtime stack order: Blade → Livewire → Tailwind → Bootstrap → Vue → Flux/Filament.

### Elise — Filament admin mockups

When the PRD `## Technical Architecture` records **Filament** as the panel choice (or the spec is explicitly for a Filament admin area), admin/control panel mockups **must** follow the packaged design system — read shared-runtime **Filament admin mockups** and:

1. `{paths.design_systems}/filament/README.md` — rules and Figma links ([Design System](https://www.figma.com/community/file/1413822581847485668/filament-3-design-system), [UI Kit Free](https://www.figma.com/community/file/1417716904167561805/filament-3-free))
2. `{paths.design_systems}/filament/figma-sources.md` — merge index (which kit owns which frames)
3. `{paths.design_systems}/filament/tokens.css` — copy into mockup folder as `filament-tokens.css`
4. `{paths.design_systems}/filament/components.md` — shell, tables, forms, actions
5. `{paths.design_systems}/filament/html/` — packaged static screens (start from `index.html` catalog; copy/adapt into project mockups)

Use Filament's visual language (sidebar, topbar, sections, amber primary by default) — **not** the Nordic minimal aesthetic on admin screens. Public-facing pages in the same spec keep Nordic minimal unless the PRD scopes them as part of the Filament panel.

When Filament is **not** chosen, design admin/dashboard screens in the project's visual language; mockups inform the panel-route decision downstream (per Vendor & Package Policy), not the other way around.

### Elise — Laravel Starter Kit mockups

When the PRD `## Technical Architecture` records a **[Laravel Starter Kit](https://laravel.com/starter-kits)** variant (`livewire`, `react`, `vue`, or `svelte`) for authenticated app UI, admin/dashboard mockups **must** follow the packaged design system — read shared-runtime **Starter Kit app UI** and:

1. `{paths.design_systems}/starter-kit/README.md` — rules and official kit links
2. `{paths.design_systems}/starter-kit/sources.md` — variant index (React/Vue/Svelte/Livewire repos)
3. `{paths.design_systems}/starter-kit/tokens.css` — copy into mockup folder as `starter-kit-tokens.css`
4. `{paths.design_systems}/starter-kit/components.md` — sidebar/header shell, auth layouts, settings
5. `{paths.design_systems}/starter-kit/html/` — packaged static screens (start from `index.html` catalog; copy/adapt into project mockups)

Use the kit's visual language (light sidebar, Instrument Sans, neutral primary, shadcn/Flux patterns) — **not** the Filament design system and not Nordic minimal on authenticated screens. Public-facing pages in the same spec keep Nordic minimal unless scoped as part of the authenticated shell.

When a Starter Kit is **not** chosen, do not impose Flux/shadcn starter-kit patterns from this section.

Default aesthetic for public UI: **Nordic minimal, modern, elegant**. **Dark + light** unless user opts out.

### Elise — accessibility (WCAG 2.2 AA)

Every mockup must demonstrate:

- Semantic HTML (`header`, `nav`, `main`, `footer`, one `h1`)
- Visible **focus** styles on interactive elements
- Form `<label>` + error state examples
- Sufficient **contrast** in light and dark (WCAG AA)
- Skip link, keyboard-friendly nav
- `alt` placeholders on images; `aria-live` notes for dynamic regions
- `prefers-reduced-motion` noted in README when animations exist

Annotate in README what Alex must preserve in Blade/Livewire.

### Elise — brand identity & assets *(when client does not provide)*

Elise **always** plans and, when needed, **creates** brand assets for public products:

| Deliverable | Path (design phase) | Spec |
| --- | --- | --- |
| **Favicon** | `favicon.svg` in mockup folder → `public/favicon.svg` | SVG, works light/dark, simple mark |
| **Logo** | `logo.svg` (+ optional `logo-dark.svg` / `logo-light.svg`) | Wordmark and/or icon; `currentColor` or dual variants |
| **Coordinated brand image** | `brand-hero.svg` or PNG | Abstract/hero visual matching logo palette |
| **OG / social share** | `og-default.png` **1200×630** | For Lauren — default Open Graph / X / LinkedIn |
| **Apple touch icon** | `apple-touch-icon.png` **180×180** | Cropped from logo mark |
| **Brand guide** | `README.md` or `.larapilot/brand/README.md` | Palette, type, logo clear space, asset inventory |

If the **client supplies** logo/favicon/social art → document paths in README and reference in mockup header; do not replace without approval.

Show logo + favicon in mockup `index.html` header. Lauren notes default share copy referencing `og-default.png`.

### Emma — SEO & a11y overlap

Document in README:

- URL path, breadcrumbs (+ JSON-LD)
- `robots.txt` / `sitemap.xml` / `llms.txt` updates needed
- Unique `<title>`, meta description, descriptive link text
- Lighthouse targets: Accessibility ≥ 90, Performance ≥ 80

### Violet — regulatory notes

When product is EU/public sector, note in README:

- Applicable standard (EAA, EN 301 549, Legge Stanca, ADA)
- Whether an **accessibility statement** page is required
- Open issues / known gaps for launch checklist

## Output

- `index.html` — **mobile-first** primary view (320–375 px frame or responsive with mobile as default); light theme (+ `dark.html` or toggle); embed **logo** and **favicon** preview
- `desktop.html` or responsive breakpoint demo — key screen at desktop width when layout differs materially
- `favicon.svg`, `logo.svg` — when Elise creates brand assets
- `og-default.png` (1200×630) — when social share image needed for Lauren
- Optional: `apple-touch-icon.png`, `brand-hero.svg`
- README.md — stack mapping, theme tokens, **responsive & navigation contract**, a11y checklist, **brand asset list**, Emma SEO notes, Lauren share notes, Violet regulatory notes

## Aesthetic Guidelines

- Nordic minimal; annotate hover, **focus**, error, empty, loading states
- **Mobile First** — design narrow first, enhance wide; 44×44 px minimum touch targets; navigable and simple on **any device and resolution**
