# Bootstrap 5 — sources

Version targeted: **Bootstrap v5.3.8** (npm `bootstrap@5.3.8`, the `latest` tag on 2026-10-12; `6.0.0-alpha.1` is the `next` tag and is not targeted) and **Bootstrap Icons v1.13.2**. Both MIT licensed.

## Official sources

| Source | URL | Used for |
| --- | --- | --- |
| Bootstrap docs 5.3 | https://getbootstrap.com/docs/5.3/getting-started/introduction/ | Component anatomy, markup patterns |
| Compiled CSS 5.3.8 | https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.css | Every rule in `tokens.css` (copied, then subset) |
| Source SCSS | https://github.com/twbs/bootstrap/tree/v5.3.8/scss | Variable names, colour-mode mixin, dark values |
| Colour modes | https://getbootstrap.com/docs/5.3/customize/color-modes/ | `data-bs-theme`, dark palette, `-emphasis` / `-subtle` colours |
| Examples | https://getbootstrap.com/docs/5.3/examples/ | Dashboard (dark navbar + sidebar), Sidebars (`nav-pills` + `link-body-emphasis`), Pricing, Features, Footers, Sign-in |
| Navbar / offcanvas | https://getbootstrap.com/docs/5.3/components/offcanvas/#responsive | `offcanvas-lg` responsive sidebar |
| Dropdowns | https://getbootstrap.com/docs/5.3/components/dropdowns/ | `data-bs-popper="static"` positioning |
| Bootstrap Icons | https://icons.getbootstrap.com/ | Glyphs (sprite `bootstrap-icons.svg` 1.13.2), SVG embedding |
| Icons package | https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.2/bootstrap-icons.svg | Path data inlined in the screens |
| License | https://github.com/twbs/bootstrap/blob/main/LICENSE | MIT notice kept at the top of `tokens.css` |

## Verified vs assumed

| Item | Value in the kit | Status |
| --- | --- | --- |
| Light tokens (`--bs-*` on `:root, [data-bs-theme=light]`) | copied from `bootstrap.css` 5.3.8 | VERIFIED (byte-for-byte values) |
| Dark tokens (`[data-bs-theme=dark]`) | `#212529` body, `#2b3035` tertiary, `#343a40` secondary bg, `#dee2e6` text, `#495057` borders, `#6ea8fe` links | VERIFIED |
| Component rules (buttons, forms, cards, navbar, nav, tables, dropdown, modal, toast, accordion, offcanvas, list group, pagination, badges, alerts, progress, spinners, placeholders) | copied in source order | VERIFIED |
| Utilities and grid | copied; subset to the documented vocabulary | VERIFIED |
| Font | system stack, no web font (Bootstrap default) | VERIFIED |
| Radii, focus ring, shadows | `.375rem` / `.25rem` / `.5rem`; `0 0 0 .25rem rgba(13,110,253,.25)`; `0 .125rem .25rem rgba(0,0,0,.075)` | VERIFIED |
| Inline icon metrics | `.bi` = 1em square, `vertical-align: -.125em` (matches the icon font's `::before`) | VERIFIED against `bootstrap-icons.css` |
| Glyph paths | copied from the 1.13.2 sprite | VERIFIED |
| `prefers-color-scheme` + `html.dark` mapping | same values as `[data-bs-theme=dark]` | ASSUMED (mockup addition; real Bootstrap switches with the attribute only) |
| Dark-mode contrast lift for outline buttons / `.text-*` / `.link-*` | `-text-emphasis` colours | ASSUMED (mockup addition for WCAG AA; documented in README) |
| `.mock-*` drawn fields | clones of `.form-control` / `.form-label` rules | VERIFIED values, ASSUMED class names (mockup only) |
| Removed from the copy | `-moz-`/`-ms-` duplicates, `::-webkit-file-upload-button`, per-component reduced-motion blocks (one global rule instead), print utilities beyond `d-print-none` | ASSUMED harmless for static screenshots |
| Host app shell layout | Bootstrap "Dashboard" + "Sidebars" examples composed with utilities | VERIFIED pattern, ASSUMED content |
| Sample product, people, prices | Bramble Stays (fictional) | ASSUMED |
