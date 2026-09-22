Part of this runtime pack. The pack file is an index. Read with the editor file-read tool, never `cat`.

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
