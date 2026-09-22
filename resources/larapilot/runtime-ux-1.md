Part of this runtime pack. The pack file is an index. Read with the editor file-read tool, never `cat`.

## UX & Frontend Design _(Elise owns)_

Elise privileges the **Laravel frontend ecosystem** when topology is **`Laravel-coupled`** or **`SPA-in-Laravel`**. When topology is **`API + external frontend`**, Elise still owns UX/mockups as the shared contract; Joe maps them to the external stack.

### Technology preference (in order)

1. **Blade** — default templating; layouts, components (`<x-*>`), stacks, sections
2. **Livewire** — interactivity without a full SPA (forms, wizards, dashboards)
3. **Tailwind CSS** — preferred utility-first styling (detect project version via Boost)
4. **Bootstrap 5** — when the project already uses it, or for Filament-adjacent admin patterns
5. **Vue 3** — when the stack is Inertia/Vue or a SPA island is justified
6. **Flux UI** — when installed or when the PRD chose the **Livewire Starter Kit**
7. **Laravel Starter Kits** — when the PRD records a variant, align authenticated UI to the kit's component library: **Flux** (Livewire), **shadcn/ui** (React), **shadcn-vue** (Vue), or **shadcn-svelte** (Svelte) — see [starter-kits docs](https://laravel.com/docs/starter-kits)

Avoid introducing React, Svelte, Alpine-only bespoke stacks, or unrelated CSS frameworks unless the user explicitly requests them, the PRD chose **`SPA-in-Laravel`** / a matching Starter Kit variant, or topology is **`API + external frontend`** (the external stack is free). Authenticated app UI **in this Laravel repo**: ask Filament vs Starter Kit vs custom per **Vendor & Package Policy** in `runtime-delivery.md` — the recommendation follows the specific case and, above all, fidelity to the project mockups.

### Design systems _(detect from the PRD — read on demand)_

When the PRD `## Technical Architecture` records a UI framework — **Filament**, a **Laravel Starter Kit** variant, **Bootstrap 5**, **Tailwind CSS**, or **AdminLTE** — detect it and read the packaged reference **on demand**: `.larapilot/design-systems/{system}/README.md` + `components.md` (plus `tokens.css` and the `html/` screen catalog when present; `{paths.design_systems}/README.md` is the index; system folders: `filament/`, `starter-kit/`, `bootstrap-5/`, `tailwind/`, `adminlte/`). Shared rules for every system:

1. Follow that system's visual language on the scoped screens — **never mix systems** and never apply Nordic minimal over them; public marketing pages keep the Nordic minimal language unless scoped otherwise.
2. Copy or link the system `tokens.css` into the mockup folder; map each mockup screen to the system's concepts (resource list, dashboard, auth, settings, …) in the mockup README.
3. Show **light + dark** on at least one key screen; document sidebar/nav collapse on mobile.
4. Brand/theme colors from the PRD or client materials override system defaults — document RGB/hex for implementation.

When no design system is chosen, **`larapilot-design` runs the design-system gate** (AskQuestion, **`allow_multiple: true`** when comparing looks): packaged folders under `.larapilot/design-systems/`, any **user-added** system folders there, or a **new custom aesthetic from scratch** — before writing HTML. When the PRD or decision journal already locks the stack (e.g. Filament admin), the gate is skipped. Otherwise design in the agreed visual language — mockups inform the panel-route decision downstream (per **Vendor & Package Policy** in `runtime-delivery.md`), not the other way around.

**Multiple style variants:** when the user picks more than one system or aesthetic, Elise writes parallel trees under `.larapilot/mockups/{spec}/styles/{slug}/` (same screen filenames in each) and lists them in `styles.yaml`. `/larapilot/design` compares them side by side; the user locks the winner with **Use this style** or `larapilot:mockup-choose-style`. Alex implements only the chosen slug.

### Default visual language

Unless the user **explicitly** requests a different aesthetic, Elise applies:

- **Modern, light, minimal, clean** — generous whitespace, restrained palette
- **Nordic / Scandinavian influence** — muted neutrals, soft contrasts, calm typography, functional elegance
- **High design quality** — distinctive but not noisy; production-grade, not generic "AI slop"

Document the chosen tokens (colors, type scale, radius, spacing) in mockup READMEs so Alex implements consistently.

### Dark & light mode

**Always plan both themes** unless the user explicitly opts out:

- CSS variables or Tailwind `dark:` variant strategy
- Mockups show at least one key screen in **light** and **dark**
- Persist user preference (`localStorage` or account setting) when the app has auth
- Accessible contrast in **both** modes (WCAG AA minimum)

### Mobile first & responsive design _(Elise owns — Anne validates)_

**Mobile First is mandatory** for every UI Elise designs and every screen Alex implements. Design and build for the **smallest viewport first**, then progressively enhance for tablet and desktop — **never** ship a mobile layout that feels like a shrunken desktop page, and **never** treat desktop as an afterthought.

| Principle                | Requirement                                                                                                                                                                                                                                                |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Design order**         | Start at **320–375 px** width; define layout, navigation, and primary actions there first; then scale up with `sm:` / `md:` / `lg:` / `xl:` (Tailwind) or equivalent breakpoints                                                                             |
| **Desktop parity**       | Large screens get **enhanced** layouts (multi-column, side nav, data density) — not a different product. Core journeys must remain **equally simple** on phone, tablet, and desktop                                                                          |
| **Navigation**           | **Extremely navigable** on every device: clear IA, visible wayfinding, persistent or obvious menu access, breadcrumbs on deep pages (desktop/tablet), mobile-friendly nav (hamburger, bottom bar, or tab bar — pick one pattern per app and document it)     |
| **Simplicity**           | One primary action per screen where possible; minimal cognitive load; progressive disclosure for secondary actions                                                                                                                                           |
| **Touch & pointer**      | 44×44 px minimum tap targets on touch devices; adequate spacing between controls; hover/focus states for mouse/keyboard on desktop                                                                                                                           |
| **Content**              | No horizontal scroll on any breakpoint; text readable without zoom (≥16 px base on mobile); images and tables responsive (`overflow-x-auto` only as last resort for data tables)                                                                             |
| **Breakpoints to cover** | At minimum: **320**, **375**, **768**, **1024**, **1280**, **1920** px — verify layout, nav, and forms at each                                                                                                                                               |
| **Orientation**          | Portrait and landscape on phones/tablets — no broken layouts on rotation                                                                                                                                                                                     |
| **Mockups**              | **Mobile screen is mandatory** (primary reference); include at least one **desktop** key screen; README documents breakpoint behavior and nav pattern                                                                                                        |

Elise annotates in the mockup README: mobile nav pattern, breakpoint strategy, which content hides/collapses vs reflows, and desktop enhancements. Alex implements the same contract; Anne tests it (automated matrix only under `settings.testing: BEST` — see **Responsive & UI testing** in `runtime-delivery.md`).

### Accessibility _(Elise leads — Emma & Violet collaborate)_

Accessibility is **not optional** for public-facing products. Elise designs for it from the first mockup; Emma and Violet cover SEO and legal dimensions together.

**Elise — design & implementation standards:**

| Area             | Requirement                                                                                                 |
| ---------------- | -------------------------------------------------------------------------------------------------------------|
| **WCAG**         | Target **WCAG 2.2 Level AA** (AAA for contrast where feasible)                                                |
| **Semantics**    | Correct landmarks (`header`, `nav`, `main`, `footer`), heading hierarchy (one H1), native HTML before ARIA    |
| **Keyboard**     | Full keyboard operability; visible `:focus` / `focus-visible`; skip-to-content link                           |
| **Forms**        | `<label>` associated with every control; errors linked via `aria-describedby`; logical tab order              |
| **Media**        | Meaningful `alt` on images; captions/transcripts for video/audio                                              |
| **Motion**       | Respect `prefers-reduced-motion`                                                                              |
| **Touch**        | Minimum 44×44 px tap targets on mobile                                                                        |
| **Live regions** | `aria-live` for dynamic Livewire updates when content changes without full reload                             |
| **Themes**       | Contrast verified in **both** light and dark modes                                                            |

Mockups annotate focus states, error states, and screen-reader-only text where non-obvious.

**Sign-in / auth screens in static mockups** — HTML previews are not real login forms. Do **not** use `type="password"`, credential `autocomplete` tokens, or field labels/names/ids containing `password`, `username`, or `user`. Package templates use **Work email** + **Access code** (`type="text"`, `.demo-secret-field` for masked dots, form `autocomplete="off"`). State in copy that credentials are not checked. Implementation (Fortify, Filament panel login, etc.) uses real field names — that happens in Alex's pass, not in Elise's mockup HTML.

**Emma — SEO overlap (accessible = discoverable):** semantic HTML and heading structure; descriptive link anchor text (never generic "click here" alone); image `alt` aligned with SEO keywords where natural (no stuffing); accessible page `<title>` and unique meta description; Lighthouse **Accessibility** score ≥ 90 on critical pages (ship gate); structured data must not replace visible accessible content.

**Violet — regulations & compliance:**

| Context           | Violet evaluates                                                                                                                                       |
| ----------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------|
| **EU / EEA**      | European Accessibility Act (EAA), **EN 301 549**, accessibility statement when required                                                                  |
| **Italy**         | Legge 4/2004 (Stanca) for public administration and contracted entities                                                                                  |
| **US**            | ADA / Section 508 when the product serves US public sector or market                                                                                     |
| **Documentation** | Publish an **accessibility statement** page (reachability, contact, conformance level, known gaps) when legally required                                 |

Elise, Emma, and Violet **triangulate** in inception (PRD NFRs), plan (a11y tasks), design (mockup README), implement, and ship. Violet can flag launch blockers on legal a11y gaps; Emma flags Lighthouse/SEO-a11y failures; Elise flags WCAG design gaps.

