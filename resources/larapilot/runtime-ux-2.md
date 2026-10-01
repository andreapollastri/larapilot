## Brand identity & assets _(Elise owns — supplies Lauren when client does not)_

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
