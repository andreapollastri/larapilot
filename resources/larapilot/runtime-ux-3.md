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
