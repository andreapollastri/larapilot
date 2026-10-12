# Nordic SaaS: sources

Nordic SaaS is an **original** design system written for Larapilot (version 1.0, October 2026). No product's
CSS, components or screens were copied. The sources below are the inspirations, the standards it follows and
the open assets it names.

## Inspirations and the principle taken from each

| Source | Principle borrowed (not the look) |
| --- | --- |
| Linear, <https://linear.app> | Sidebar on the canvas with the page on a raised sheet; inverse floating bulk-action bar |
| Vercel Geist, <https://vercel.com/geist/introduction> | Hairline-first separation, restrained radii, a small set of semantic colour roles |
| Stripe invoicing, <https://stripe.com/invoicing> | The invoice as a paper document that stays light, with totals right-aligned in tabular figures |
| Scandinavian design tradition (Kinfolk-era editorial print) | Warm paper neutrals, generous whitespace, a serif for quotes only |
| Refactoring UI, <https://www.refactoringui.com> | Fewer borders, more spacing; emphasis by weight and colour, not size alone |

## Standards and tools

| Topic | Source |
| --- | --- |
| Contrast targets (AA, 4.5:1 text, 3:1 UI) | <https://www.w3.org/TR/WCAG22/#contrast-minimum> |
| Colour space used to build the ramps (OKLCH, converted to hex) | <https://www.w3.org/TR/css-color-4/#ok-lab> |
| Switch semantics (`role="switch"`) | <https://www.w3.org/WAI/ARIA/apg/patterns/switch/> |
| Menu button pattern | <https://www.w3.org/WAI/ARIA/apg/patterns/menu-button/> |

## Assets

| Asset | Licence | Source |
| --- | --- | --- |
| Lucide icons 1.55 (copied path for path, inline, `currentColor`) | ISC | <https://lucide.dev> · <https://github.com/lucide-icons/lucide> |
| Instrument Sans (real UI face; not loaded in mockups) | SIL Open Font License | <https://fonts.google.com/specimen/Instrument+Sans> |
| Newsreader (serif for quotes and the hero accent; not loaded) | SIL Open Font License | <https://fonts.google.com/specimen/Newsreader> |
| Blade components for Lucide in the app | MIT | <https://github.com/mallardduck/blade-lucide-icons> |

## Verified vs assumed

| Item | Status | Note |
| --- | --- | --- |
| Neutral, accent and status ramps | VERIFIED (own calculation) | built in OKLCH (neutral hue 75–85°, accent hue 218°) and converted to sRGB hex |
| Text contrast in light (ink 17.3:1, ink-2 6.7:1, muted 4.8:1 on paper; white on accent 6.0:1) | VERIFIED | computed with the WCAG 2.2 formula |
| Text contrast in dark (ink 17:1, muted 7.1:1, accent 10.4:1 on canvas; status text ≥ 7:1 on its tint) | VERIFIED | computed with the WCAG 2.2 formula |
| Lucide path data and ISC licence | VERIFIED | `lucide-static@1.55.0` package |
| Font licences | VERIFIED | Google Fonts specimen pages |
| Type scale, spacing, radii, elevation, layout sizes | ASSUMED | design decisions of this system, documented in README.md |
| Fonts as named in the stacks | ASSUMED | product choice; mockups render with system fonts |
| Fjord, Solberg Studio and all clients, people, numbers | ASSUMED | fictional sample content |
