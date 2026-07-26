# Larapilot Runtime — UX & Web

Phase pack for **`larapilot-design`**, **`larapilot-plan`** (specs with UI), and **`larapilot-ship`**. Read `.larapilot/shared-runtime.md` (core) first.

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

When no design system is chosen, design in the project's visual language — mockups inform the panel-route decision downstream (per **Vendor & Package Policy** in `runtime-delivery.md`), not the other way around.

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

**Emma — SEO overlap (accessible = discoverable):** semantic HTML and heading structure; descriptive link anchor text (never generic "click here" alone); image `alt` aligned with SEO keywords where natural (no stuffing); accessible page `<title>` and unique meta description; Lighthouse **Accessibility** score ≥ 90 on critical pages (ship gate); structured data must not replace visible accessible content.

**Violet — regulations & compliance:**

| Context           | Violet evaluates                                                                                                                                       |
| ----------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------|
| **EU / EEA**      | European Accessibility Act (EAA), **EN 301 549**, accessibility statement when required                                                                  |
| **Italy**         | Legge 4/2004 (Stanca) for public administration and contracted entities                                                                                  |
| **US**            | ADA / Section 508 when the product serves US public sector or market                                                                                     |
| **Documentation** | Publish an **accessibility statement** page (reachability, contact, conformance level, known gaps) when legally required                                 |

Elise, Emma, and Violet **triangulate** in inception (PRD NFRs), plan (a11y tasks), design (mockup README), implement, and ship. Violet can flag launch blockers on legal a11y gaps; Emma flags Lighthouse/SEO-a11y failures; Elise flags WCAG design gaps.

### Brand identity & assets _(Elise owns — supplies Lauren when client does not)_

Elise **always** plans brand touchpoints for public-facing products — not only UI screens.

**When the client provides** logo, favicon, or social artwork → use client assets; document paths and license in PRD/README. **When the client does not**, **Elise creates** a coherent minimal identity aligned with the Nordic visual language:

| Asset                       | Format                        | Notes                                                                                                       |
| --------------------------- | ----------------------------- | -------------------------------------------------------------------------------------------------------------|
| **Favicon**                 | **`favicon.svg`** (mandatory) | Crisp at any size; works in light/dark browser chrome; place in `public/favicon.svg`                          |
| **Logo**                    | **SVG** (`logo.svg`)          | Wordmark and/or mark; readable small; variants for light/dark backgrounds                                     |
| **Coordinated brand image** | SVG or PNG                    | Hero/empty-state illustration or abstract mark extending logo palette — same radius, stroke, and neutrals     |
| **Apple touch icon**        | PNG 180×180                   | Generated from logo mark                                                                                      |
| **OG / social share**       | PNG **1200×630**              | Default Open Graph + Twitter/X/LinkedIn share image for **Lauren**                                            |
| **Social profile square**   | PNG **400×400** optional      | Avatar-style crop of logo mark for social channels                                                            |

Deliverables live in `public/` (favicon, touch icon) and `.larapilot/brand/` or `public/images/brand/` (logo, OG template, brand guide snippet) until Alex wires them into the app layout.

Rules:

1. **Always** include `favicon.svg` in inception/plan/implement for public sites — link in the root Blade layout (`<link rel="icon" href="/favicon.svg" type="image/svg+xml">`).
2. Logo and social assets must match **dark + light** UI tokens (provide `logo-dark.svg` / `logo-light.svg` or a single SVG with `currentColor` where possible).
3. Keep assets **simple and scalable** — geometric, typographic, or abstract Nordic marks; avoid raster-only logos.
4. Document palette, typography, and logo usage (clear space, minimum size) in `.larapilot/brand/README.md` or the mockup README.

Ownership: **Elise** creates logo, favicon, and coordinated imagery; **Lauren** applies social assets to campaigns and meta (`og:image`, `twitter:image`, newsletter headers); **Alex** commits files to `public/` and layout; **Emma** validates OG tags reference live asset URLs.

## Frontend Engineering & Visual Impact _(Joe owns)_

**Joe** owns the **implementable design system** in lockstep with **Elise** — tokens (color, type, spacing, radius), component library, motion rules — documented in mockup READMEs and `.larapilot/design-systems/` when applicable. He covers web frontend stacks (Blade, Livewire, Tailwind, Inertia SPA, Vite), animations (including **Three.js** when scoped), client-side API consumption (auth flows, Echo/Reverb, error/loading states), and client performance (bundle size, lazy loading, image strategy, Core Web Vitals).

Rules:

1. **Design** — Joe co-authors the design system with Elise; advises on implementable patterns and animation scope in mockup READMEs; Filament/Starter Kit references stay token-consistent.
2. **Plan** — design-system scaffold tasks (shared components, `tokens.css`, Vite/Tailwind theme) plus frontend architecture when the spec requires them.
3. **Implement** — Joe guides Alex on design-system usage, client code quality, performance budgets, and visual fidelity to mockups; blocks drift from agreed tokens/components.
4. **Review** — **mandatory design-system check**: flags token/component drift, visual regressions, broken responsive behavior, and client-side performance issues.

## Mobile & Device Engineering _(Ricky owns)_

**Ricky** owns **native and hybrid mobile applications** (Flutter, React Native, Capacitor/Ionic; platform-native Swift/Kotlin when the PRD requires it) and every **device capability**: camera, microphone, sensors, GPS, Bluetooth LE, NFC/RFID, biometrics, push notifications, background tasks — plus web device APIs (MediaDevices, Geolocation, Web Bluetooth, Push, PWA install/offline) when the product is web-first but needs hardware.

Rules:

1. **Inception** — Ricky scopes mobile platform choice (`hybrid` / `native` / `web+PWA` / `web-only`), required device APIs, and store-distribution constraints in the PRD. Mobile work is always scoped explicitly in the PRD.
2. **Plan** — mobile shell tasks, permission flows, device-feature specs, store-release checklist, and cross-platform test matrix when in scope — with **John** (API/sync) and **Matt** (third-party SDKs); security review with **Lars** for sensitive permissions.
3. **Implement** — guide Alex on permission handling, graceful degradation when hardware is unavailable, and API contracts for device data; honor platform UX conventions (iOS/Android) with **Elise**.
4. **Review** — flag broken permissions, store-policy violations, and device-specific regressions; **Anne** tests on device viewports and permission flows.

## SEO Structure & Discoverability _(Emma owns)_

For **every public-facing website**, Emma owns structural SEO — not only meta tags. These artifacts are **mandatory** and must stay **updated** when routes, pages, or content change (the same spec that adds a page updates the files).

### URL structure

- Semantic, readable paths: lowercase, hyphens, no trailing junk (`/products/acme-widget`, not `/p?id=42`)
- Stable canonical URLs; avoid duplicate content across aliases
- Logical hierarchy reflected in paths (`/blog/category/post-slug`)
- Locale prefix strategy documented when i18n (`/en/…`, `/it/…`) — coordinate with Violet and Emily

### Breadcrumbs

- Visible breadcrumb trail on all pages deeper than home (except flat landing pages where redundant)
- **JSON-LD** `BreadcrumbList` structured data on every page with breadcrumbs
- Labels match page `<title>` / H1 semantics; last item is current page (not linked)

### Mandatory files _(keep current)_

| File              | Location                                           | Purpose                                                                                                             |
| ----------------- | -------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------|
| **`robots.txt`**  | `public/robots.txt` or dynamic route               | Crawl rules; reference sitemap URL; block staging/admin paths                                                         |
| **`sitemap.xml`** | `public/sitemap.xml` or generated route/command    | All public indexable URLs; `lastmod` when content changes; split sitemap index when >50k URLs                         |
| **`llms.txt`**    | `public/llms.txt` or `public/.well-known/llms.txt` | LLM/crawler guidance (allowed paths, site summary, contact) — structural counterpart to `robots.txt` for AI agents    |

Rules:

1. Scaffold all three at inception or first public-site spec — never defer to ship-only.
2. Update in the **same PR/spec** that adds, removes, or renames public routes.
3. Ship gate: all three reachable over HTTPS; sitemap validates; `llms.txt` reflects current site purpose and key URLs.
4. Register the sitemap in `robots.txt` (`Sitemap: https://domain/sitemap.xml`).

Ownership: **Emma** owns URL design, breadcrumbs, and the three files; **John** aligns route naming; **Elise** reflects hierarchy in accessible UX; **Lauren/Emma** coordinate campaign landing URLs.

## Copywriting & Content _(Marika owns)_

**Marika** crafts and refines user-facing text for **websites** and **applications** — headlines, body copy, CTAs, microcopy, empty states, onboarding, notifications, in-app messaging — in any tone the user requests (professional, creative, playful, technical, minimal, premium, …). She also audits existing texts in the codebase, mockups, PRD, or legacy system.

Rules:

1. **Inception** — Marika joins **Website** and **Application** when copy strategy matters; reviews client materials and legacy content inventories (with **Sabrine** on ports — map every legacy string to its new home).
2. **Design** — mockups carry realistic placeholder copy Marika can refine before implementation.
3. **Plan / implement** — copy tasks are explicit (Blade views, `lang/` files, Filament labels, notifications).
4. **Review** — with **Emily**, Marika verifies **typos**, tone, clarity, and **cross-locale copy consistency** when the spec touches user-facing text; flag mismatches between source copy and translations.
5. Never ship generic filler ("Lorem ipsum", "Click here", "Welcome to our app") on public or product surfaces unless the user explicitly accepts placeholders.

Ownership: **Marika** owns copy creation and review; **Lauren** owns campaign/channel distribution; **Emily** owns translation; **Elise** aligns copy length with layout; **Violet** approves legal strings.

## Marketing & Growth _(Lauren + Emma + Elise + Aurora)_

**Lauren** (Social Media Manager) drives **marketing initiatives**, not only share metadata:

- **Newsletter** — list growth, onboarding sequences, launch announcements (coordinate with the newsletter stack from **Optional integrations** in `runtime-delivery.md`)
- **Campaigns** — social content calendar, launch posts, community channels
- **SEM / paid acquisition** — Google Ads, Meta Ads, LinkedIn Ads when budget allows — **always aligned with Aurora's budget** and Emma's conversion/tracking setup

Lauren collaborates with **Emma** (SEO, Analytics, UTM strategy, landing-page performance) and **Elise** (campaign landing UX, accessible forms, logo/favicon/social assets when the client does not supply them). Initiatives scale with delivery target: MVP may defer paid SEM; V1+ should document channel strategy in the PRD. **Aurora** approves or defers spend per Budget Sensitivity; **Emma/Lauren** ensure tracking respects consent (Violet).
