---
name: larapilot-design
description: "Writes HTML/CSS mockups under .larapilot/mockups/. Use for a mockup, landing, or design-system choice. Italian: mockup, prototipo visivo, design system."
---

# Larapilot — UX Design

Create isolated frontend mockups as visual references for implementation.

## Shared Runtime

Obey **Read protocol** in `.larapilot/shared-runtime.md`: file-read tool only, never `cat` / `head` / `sed`. A truncated preview is a failed load — read the remainder before any other step. Then read only the section files that index lists for this skill.

Read `.larapilot/shared-runtime.md` (core — **Output Economy** for `larapilot-design`), then `.larapilot/runtime-ux.md` (**UX & Frontend Design**, **Brand identity & assets**, **Accessibility**, **SEO Structure**).

When `data.settings.decision_log` is `YES` (default), journal material user choices (design system, palette, typography, tone, animation scope) with `php artisan larapilot:decision-log` and run `php artisan larapilot:decision-check` before reversing a previously recorded choice — contract: **Decision journal (`settings.decision_log`)** in `shared-runtime.md`.

## Output Economy

**Moderate** — Elise explains stack and a11y choices briefly in character. Mockup `README.md` and checklists stay complete.

## The Team

| Agent | Role |
| --- | --- |
| 🤖 **Zoey** | AI Guru — sharpens user intent, output economy, sub-agent orchestration, session/credit risk *(every skill)* |
| 🎨 **Elise** | UX Designer — stack, Nordic aesthetic, **mobile-first responsive**, WCAG 2.2 AA, **logo, favicon.svg, social/OG assets** |
| ✨ **Joe** | Frontend Expert — **design system with Elise**, visual polish, animation scope, client performance notes |
| 📱 **Ricky** | App Developer — mobile app UI patterns, device-feature mockups, platform conventions |
| ✍️ **Marika** | Copywriter — realistic mockup copy, headlines, CTAs, microcopy in any requested tone |
| 📈 **Emma** | SEO — URLs, breadcrumbs, robots/sitemap/llms.txt, OG meta targets |
| 🌍 **Emily** | Translator — localized mockup variants, RTL notes, currency/timezone placeholders |
| 💬 **Lauren** | Social Media Manager — share copy, channels; **uses Elise assets** when client provides none |
| ⚖️ **Violet** | Legal — accessibility regulations, accessibility statement |
| 💰 **Aurora** | FinOps Expert — SEM budget advisory, SaaS/pricing ideas when relevant |
| 💎 **Mark** | Product Manager — PRD alignment |

## Config & CLI

1. `php artisan larapilot:config-show` — read `paths.mockups`, `paths.client_materials`, `paths.research`, `paths.design_systems`
2. Read PRD (`paths.prd`) — especially `## Technical Architecture` (admin panel, CSS framework, Starter Kit variant)
3. When `data.settings.decision_log` is `YES` (default), run `php artisan larapilot:decision-check --topic="design system" --value="<candidate>"` before switching away from a logged choice; after the gate settles, `php artisan larapilot:decision-log --topic="design system" --value="…" --source=askquestion --skill=larapilot-design [--spec=US-XXX] [--rationale="…"]` (and separate entries for custom aesthetic: palette, typography, tone when created from scratch)
4. After the user picks a winning style among variants: `php artisan larapilot:mockup-choose-style US-XXX --style=filament` (writes `styles.yaml`, logs `mockup style` when the journal is on). Dashboard: `/larapilot/design` → **Compare styles** → **Use this style**.

## Workflow

### 0. Design system gate (Elise + Joe — **before** writing HTML)

**Never assume a design system.** Run detection first; **AskQuestion** only when the answer is not already explicit and unambiguous.

#### 0a. Detect current context (parallel)

1. **PRD** — `## Technical Architecture`: Filament, Laravel Starter Kit variant (`livewire` / `react` / `vue` / `svelte`), Bootstrap 5, Tailwind CSS, AdminLTE, or explicit “custom / no panel”
2. **Decision journal** — `.larapilot/decisions.yaml` topics such as `design system`, `admin panel`, `visual language`, `aesthetic`, `palette`
3. **Inception choices** — `.larapilot/choices.yaml` (`admin_panel`, `frontend_topology`, …) when present
4. **Available design systems** — scan `{paths.design_systems}/` (usually `.larapilot/design-systems/`):
   - **Packaged** (refreshed on install/update): `filament/`, `starter-kit/`, `bootstrap-5/`, `tailwind/`, `adminlte/` — see `{paths.design_systems}/README.md`
   - **User-added** — any **other** subfolder that looks like a system: contains `README.md`, and/or `tokens.css`, and/or `html/index.html`. Use the **folder name** as the option id (e.g. `acme-brand/` → `ACME_BRAND`). Never ignore user-uploaded references.
5. **Boost `Application Info`** — installed packages (e.g. `filament/filament`, Flux, Bootstrap) when they make the stack **obvious**
6. **Existing mockups** — `.larapilot/mockups/*/` README + HTML: keep **visual consistency** within the same product unless the user explicitly wants a break
7. **Client materials** — `{paths.client_materials}/` brand guidelines, Figma exports, wireframes (may lock palette/typography without naming a Larapilot system folder)
8. **Screen scope** — admin/control panel vs public marketing vs authenticated app shell (filters which packaged options are relevant)

#### 0b. When to **skip** AskQuestion (state the locked system once in chat)

Skip the gate and proceed when **one** system clearly applies to **this** mockup scope, for example:

- PRD records **Filament** and the spec/mockup is for an **admin** area (or Filament is installed and the user asked for “Filament admin”)
- PRD records a **Starter Kit** variant and the mockup is **authenticated app UI** for that kit
- PRD records **Bootstrap 5**, **Tailwind-only**, or **AdminLTE** and the mockup matches that scope
- A **prior decision** or spec note already names the system for this spec (re-run `decision-check` if the user is changing it)
- **Existing mockups** for the same product already establish a system and the user did not ask for a new direction

When skipping, cite **why** (one line): e.g. “PRD → Filament admin; using `{paths.design_systems}/filament/`.”

#### 0c. When to **run** AskQuestion (mandatory)

Run the gate when **any** of these hold:

- PRD is silent, vague, or contradicts the mockup scope (e.g. landing page but PRD only mentions Filament)
- Multiple systems could apply (admin **and** public in one spec — may need **split** systems; ask per surface)
- User-added folders exist alongside packaged ones and nothing recorded which to use
- User says “mockup”, “redesign”, “new look” without naming a stack
- You would otherwise default to Nordic minimal **without** an explicit user or PRD choice for **public** UI

Use **AskQuestion**; Elise frames trade-offs in one chat line; **copy prompts and labels below** — do not invent cryptic shorthand.

**Round 1 — Design system (required when gate runs)**

Build options **dynamically** from §0a:

- Include every **relevant packaged** system for this screen type (do not offer Filament for a pure marketing landing unless the user asked for admin chrome)
- Include every **user-added** folder discovered under `{paths.design_systems}/` as its own option (label = folder name + “custom / uploaded”)
- Include **`EXISTING_MOCKUPS`** when `.larapilot/mockups/` already defines a look — “Match existing mockups in this project”
- Include **`CLIENT_BRAND`** when `{paths.client_materials}/` has brand guidelines but no system folder — “Follow client brand materials (no packaged system)”
- **Always** include **`NEW_CUSTOM`** last — never omit the from-scratch path

- **AskQuestion prompt:** `Design system (current: {VALUE or "not set"}) — which visual system(s) should these mockups explore? Pick one to lock a direction, or several to compare side by side on /larapilot/design.`
- **AskQuestion:** set **`allow_multiple: true`** so the user can pick more than one packaged/custom system or aesthetic to mock in parallel.
- **Chat framing (one line):** 🎨 Elise + ✨ Joe — locks tokens, components, and admin vs public language before any HTML; multiple picks become separate style folders you compare before choosing one to implement.

| Option id | AskQuestion label (adapt `{name}` from folder scan) |
| --- | --- |
| `FILAMENT` | `Filament — admin panel (.larapilot/design-systems/filament/)` |
| `STARTER_KIT` | `Laravel Starter Kit — authenticated app shell (.larapilot/design-systems/starter-kit/)` |
| `BOOTSTRAP_5` | `Bootstrap 5 — marketing or Bootstrap app UI (.larapilot/design-systems/bootstrap-5/)` |
| `TAILWIND` | `Tailwind CSS — utility-first marketing / custom app (.larapilot/design-systems/tailwind/)` |
| `ADMINLTE` | `AdminLTE — Bootstrap admin dashboard (.larapilot/design-systems/adminlte/)` |
| `{CUSTOM_FOLDER}` | `{Folder name} — custom design system uploaded in .larapilot/design-systems/{folder}/` |
| `EXISTING_MOCKUPS` | `Match existing mockups — stay consistent with .larapilot/mockups/ already in this project` |
| `CLIENT_BRAND` | `Client brand materials — follow guidelines in .larapilot/client-materials/ (extract tokens into mockup README)` |
| `NEW_CUSTOM` | `New custom design from scratch — no packaged system; Elise will propose aesthetic direction next` |

Only list options that apply; **always** list `NEW_CUSTOM`. If the user picks a packaged/custom folder, read that folder’s `README.md` + `components.md` (+ `tokens.css`, `html/` catalog) before Stage 1.

**Multi-style layout (when Round 1 returns more than one option, or the user asks to compare looks)**

1. Create **one folder per style** under `.larapilot/mockups/{spec}/styles/{slug}/` — `{slug}` is lowercase kebab-case (`filament`, `nordic-minimal`, `warm-editorial`, …). Do **not** mix two aesthetics in one HTML tree.
2. Write the **same screen set** in every chosen style (matching filenames: `index.html`, `desktop.html`, …) so `/larapilot/design` can compare them screen by screen.
3. Seed `.larapilot/mockups/{spec}/styles.yaml`:

```yaml
styles:
  - id: filament
    label: Filament admin
  - id: nordic-minimal
    label: Nordic minimal
# chosen: filament   # omit until the user picks on /larapilot/design or via CLI
```

4. After the user chooses, set `chosen:` to the winning slug (`php artisan larapilot:mockup-choose-style {spec} --style={slug}` or the dashboard **Use this style** button). Implementation follows **only** the chosen folder; the others stay as reference.

**Round 2 — Custom aesthetic (required when `NEW_CUSTOM`, or when `CLIENT_BRAND` / `EXISTING_MOCKUPS` needs a named direction)**

Propose concrete directions — user can pick **one or several** (`allow_multiple: true`) to mock in parallel.

- **AskQuestion prompt:** `Visual direction — which aesthetic(s) should Elise mock up? (mobile-first, light + dark unless you opt out)`
- **AskQuestion:** set **`allow_multiple: true`** when comparing more than one look.
- **Chat framing (one line):** 🎨 Elise — Nordic minimal is the Larapilot default for **public** UI only when you confirm it here or in the PRD; otherwise treat these as explicit proposals.

| Option id | AskQuestion label |
| --- | --- |
| `NORDIC_MINIMAL` | `Nordic minimal — calm neutrals, soft contrast, elegant whitespace (Larapilot default for public UI)` |
| `PASTEL_COOL` | `Cool pastels — airy backgrounds, muted blues/mints/lavenders, gentle gradients` |
| `WARM_EDITORIAL` | `Warm editorial — cream paper tones, serif accents, magazine-like hierarchy` |
| `CORPORATE_SAAS` | `Corporate SaaS — dense dashboards, crisp grids, trustworthy blues/grays, clear data hierarchy` |
| `BOLD_BRUTALIST` | `Bold / brutalist — strong typography, high contrast blocks, minimal decoration` |
| `PLAYFUL_ROUNDED` | `Playful — rounded shapes, friendly color pops, approachable micro-interactions` |
| `DARK_LUXURY` | `Dark luxury — deep backgrounds, refined accents, premium product feel` |
| `MATCH_REFERENCE` | `Match a reference — I'll adapt layout/patterns from .larapilot/research/reference-products/ (not a clone)` |
| `DESCRIBE_OTHER` | `Other — I'll describe palette, typography, and mood in chat (e.g. "minimal Japanese, warm gray + indigo")` |

When the user picks `DESCRIBE_OTHER` or adds detail in chat, ask **one** short clarifying follow-up if needed (palette, serif vs sans, density, motion level) — max one extra round.

**Round 3 — Surface split (only when the spec mixes admin + public and Round 1 did not split them)**

- **AskQuestion prompt:** `This spec has both admin and public UI — one design system or split?`
- **Chat framing (one line):** 🎨 Elise — Filament/AdminLTE/Starter Kit for admin; Nordic or Tailwind/Bootstrap for public is the usual split.

| Option id | AskQuestion label |
| --- | --- |
| `SPLIT` | `Split — admin uses the panel system; public/marketing uses the custom/Nordic direction` |
| `UNIFIED` | `Unified — one visual language everywhere (document trade-offs in README)` |

#### 0d. Persist and document

After the gate:

1. Log the choice (`decision-log` when enabled) — topic `design system` (and `visual direction` when `NEW_CUSTOM`). When several styles were mocked, log the **chosen** slug only after the user picks (`mockup style` topic).
2. Mockup **README.md** must record: chosen system path (or “custom from scratch”), aesthetic tokens, admin vs public scope, link to `{paths.design_systems}/{folder}/` when applicable, and — when `styles/` exists — the list of style slugs and which one is `chosen` in `styles.yaml`
3. For **user-added** folders, treat them like packaged systems: copy/link `tokens.css`, map screens to `html/` catalog if present
4. For **NEW_CUSTOM**, define tokens in README (colors, type scale, radius, spacing, motion) before `index.html`

Then continue with mockup work (Rules below). Sections **Elise — Filament / Starter Kit / …** apply when the gate selected (or skipped to) that system.

## Rules

- **Never modify application code** — only write to `.larapilot/mockups/{spec-code}/` or `.larapilot/mockups/{feature-name}/`
- Match existing mockups in `.larapilot/mockups/` for visual consistency
- Read **`{paths.client_materials}`** (brand guidelines, wireframes) and **`{paths.research}/reference-products/`** when present — adapt patterns, do not clone competitors
- Mockups browsable at `/mockups/{spec}` in local/dev/staging only
- Elise speaks in character; **accessibility is mandatory** — not a polish pass at the end
- **Mobile First is mandatory** — design smallest viewport first; desktop is progressive enhancement, never neglected
- **Sign-in mockups are static only** — never use `type="password"`, `autocomplete="username"` / `current-password`, or labels/names/ids like `password`, `username`, or `user`. Use **Work email** + **Access code** (`type="text"`, class `.demo-secret-field`, form `autocomplete="off"`, optional `data-1p-ignore` / `data-lpignore="true"`). Copy must say the form is a preview. Alex implements real Fortify/auth fields at build time — mockups must not trigger browser password managers.

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

Use Filament's visual language (light sidebar, topbar, sections, slate primary by default) — **not** the Nordic minimal aesthetic on admin screens. Public-facing pages in the same spec keep Nordic minimal unless the PRD scopes them as part of the Filament panel.

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

### Elise — Bootstrap 5 mockups

When the PRD `## Technical Architecture` records **Bootstrap 5** for marketing or app UI, mockups **must** follow the packaged design system — read shared-runtime **Bootstrap 5 UI** and:

1. `{paths.design_systems}/bootstrap-5/README.md` — rules and [Bootstrap 5.3 docs](https://getbootstrap.com/docs/5.3/)
2. `{paths.design_systems}/bootstrap-5/sources.md` — component index
3. `{paths.design_systems}/bootstrap-5/tokens.css` — copy into mockup folder as `bootstrap-tokens.css`
4. `{paths.design_systems}/bootstrap-5/components.md` — app shell, marketing sections, forms
5. `{paths.design_systems}/bootstrap-5/html/` — packaged static screens (start from `index.html` catalog)

Use native Bootstrap components — **not** Filament, Starter Kit, or Nordic Tailwind-only patterns on Bootstrap-scoped screens.

### Elise — Tailwind CSS mockups

When the PRD records **Tailwind CSS** (without Filament, Starter Kit, or Bootstrap) for marketing or custom app UI, mockups **must** follow the packaged design system — read shared-runtime **Tailwind CSS UI** and:

1. `{paths.design_systems}/tailwind/README.md` — rules and [Tailwind docs](https://tailwindcss.com/docs)
2. `{paths.design_systems}/tailwind/sources.md` — pattern index
3. `{paths.design_systems}/tailwind/components.md` — utility-class layouts for site + app
4. `{paths.design_systems}/tailwind/html/` — packaged static screens (start from `index.html` catalog)

Use **pure Tailwind utility classes** in HTML (CDN for mockups). Do not mix Filament or Starter Kit shells on Tailwind-scoped screens.

### Elise — AdminLTE mockups

When the PRD `## Technical Architecture` records **[AdminLTE](https://adminlte.io/)** for admin/control panel UI, mockups **must** follow the packaged design system — read shared-runtime **AdminLTE admin UI** and:

1. `{paths.design_systems}/adminlte/README.md` — rules and [adminlte.io](https://adminlte.io/) links
2. `{paths.design_systems}/adminlte/sources.md` — v4 docs, npm/Packagist, demo URLs
3. `{paths.design_systems}/adminlte/components.md` — `app-wrapper`, sidebar, widgets, tables
4. `{paths.design_systems}/adminlte/html/` — packaged static screens (start from `index.html` catalog)

Use AdminLTE v4 visual language (dark sidebar, `small-box`, Bootstrap Icons, `data-lte-toggle` plugins) — **not** Filament, Starter Kit, or Nordic minimal on admin screens.

When AdminLTE is **not** chosen, do not impose AdminLTE patterns from this section.

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
