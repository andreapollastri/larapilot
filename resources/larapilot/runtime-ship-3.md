Part of this runtime pack. The pack file is an index. Read with the editor file-read tool, never `cat`.

## Web Launch Checks _(public sites only)_

Skip for APIs, admin-only apps, or CLI tools with no public web presence. Document findings in `.larapilot/docs/launch/{release-id}.md` when issues are found.

**Emma — SEO, Analytics, performance:**

- URL structure — semantic paths, canonical URLs, no broken public routes
- Breadcrumbs visible on deep pages; **JSON-LD** `BreadcrumbList` valid
- `robots.txt` reachable; references sitemap; blocks admin/staging
- `sitemap.xml` reachable; lists all public indexable URLs; valid XML
- `llms.txt` reachable (`/llms.txt` or `/.well-known/llms.txt`); reflects current site scope
- Unique `<title>` and meta description on key pages; single `<h1>` per page; logical heading hierarchy
- HTTPS enforced; no mixed content; structured data (JSON-LD) where applicable
- Analytics live (per PRD choice) with consent where required; key tracking events firing (signup, purchase, CTA clicks)
- Lighthouse on critical pages (mobile): **Accessibility ≥ 90**, Performance ≥ 80
- **Mobile First** spot-check (Elise + Anne): primary journeys usable at 375 px; nav and CTAs reachable; no horizontal scroll; desktop layout enhanced, not divergent
- **WCAG 2.2 AA** spot-check: keyboard nav, focus visible, form labels, alt text, contrast in light/dark (Elise + Emma)
- **Accessibility statement** page reachable when Violet required it

**Lauren — social, marketing & distribution:**

- Open Graph tags (`og:title`, `og:description`, `og:image`, `og:url`) — **`og:image`** points to Elise's **1200×630** asset or client artwork
- Twitter/X card tags (`twitter:card`, `twitter:image`)
- **`favicon.svg`** linked in layout; **apple-touch-icon** present; **logo** visible in header with working light/dark variants
- Default share copy and launch campaign assets documented
- Newsletter / list signup path verified when in scope
- SEM landing URLs and UTM conventions match Emma's setup

**Emily — localization (when multi-market):**

- Locale switcher works; `lang/` strings complete for supported locales
- Currency and timezone display correct per user/market setting
- Legal pages localized where Violet required
- `hreflang` tags present and reciprocal (with Emma)

**Sophia — post-launch support prep:**

- Create or update `{paths.support}/runbook.md` — bug intake channel, severity definitions, escalation to Lars/Oliver for security
- Confirm README and OpenAPI docs match the deployed release
- Note known issues and maintenance backlog items for the next spec cycle
