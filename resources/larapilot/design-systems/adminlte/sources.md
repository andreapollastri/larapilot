# AdminLTE 4 — sources

Version targeted: **AdminLTE v4.10.0** (npm `admin-lte@4.10.0`, the `latest` tag on 2026-10-12; `legacy` = 4.8.0, `v2-latest` = 2.4.18), which compiles **Bootstrap v5.3.8**; icons **Bootstrap Icons v1.13.2**. All MIT licensed.

## Official sources

| Source | URL | Used for |
| --- | --- | --- |
| AdminLTE site | https://adminlte.io/ | Product, licence, version line |
| GitHub repository | https://github.com/ColorlibHQ/AdminLTE | Source of truth for v4 (`src/scss`, `src/html`) |
| Compiled CSS 4.10.0 | https://cdn.jsdelivr.net/npm/admin-lte@4.10.0/dist/css/adminlte.css | Every rule in `tokens.css` (copied, then subset) |
| SCSS 4.10.0 | https://cdn.jsdelivr.net/npm/admin-lte@4.10.0/src/scss/ | `_variables.scss`, `_variables-dark.scss`, `_bootstrap-overrides.scss`, `_app-sidebar.scss`, `_cards.scss`, `_small-box.scss`, `_info-box.scss`, `_timeline.scss`, `_direct-chat.scss`, `pages/_login_and_register.scss` |
| Live demo (v4) | https://adminlte-v4.netlify.app/ | Dashboard (index), dashboard v2, info box, small box, cards, timeline, users, settings, login markup |
| Login example | https://adminlte-v4.netlify.app/examples/login | `login-page` / `login-box` anatomy |
| Bootstrap 5.3 docs | https://getbootstrap.com/docs/5.3/getting-started/introduction/ | Underlying components and utilities |
| Bootstrap Icons | https://icons.getbootstrap.com/ | Glyphs (sprite 1.13.2) |
| Icons sprite | https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.2/bootstrap-icons.svg | Path data inlined in the screens |
| AdminLTE licence | https://github.com/ColorlibHQ/AdminLTE/blob/master/LICENSE | MIT notice in `tokens.css` |

## Verified vs assumed

| Item | Value in the kit | Status |
| --- | --- | --- |
| Bootstrap overrides | `$enable-shadows: true`, `$enable-negative-margins: true`, font stack `"Source Sans 3", system-ui, …`, `fs-7`/`fs-8`, navbar link padding 1rem, modal colour inherited | VERIFIED (`_bootstrap-overrides.scss`) |
| Light / dark `--bs-*` tokens | copied from `adminlte.css` 4.10.0 | VERIFIED |
| Sidebar | width 250 px (`--lte-sidebar-width`), brand row 3.5 rem, dark skin `#343a40` (`bg-body-secondary` under `data-bs-theme="dark"`), links `#c2c7d0`, hover/active `rgba(255,255,255,.1)` + `#fff`, header `nav-header` .9rem | VERIFIED |
| Header | `app-header navbar navbar-expand bg-body`, link height 2.5 rem, `navbar-badge` .6rem at 9 px/5 px, pill `navbar-search` 14 → 18 rem | VERIFIED |
| Content | `app-content-header` 1rem .5rem, breadcrumb line-height 2.5rem with hover-colour links, `app-content` padding 0 .5rem, footer 1rem with top border | VERIFIED |
| Cards | shadow `0 0 1px rgba(body,.125), 0 1px 3px rgba(body,.2)`, `card-title` 1.1rem / 400, `card-outline` 3 px top border, `btn-tool` .875rem tertiary colour | VERIFIED |
| Small box / info box | `h3` 2.2rem / 700, icon 70 px at 15 % black, footer `rgba(0,0,0,.07)`; info-box min-height 80 px, icon 70 px wide 1.875rem | VERIFIED |
| Timeline, direct chat, callouts, toasts, progress sizes, login box (400 px) | copied | VERIFIED |
| Demo markup patterns (shell, treeview, small-box footer links, card tools, users table, settings list-group, login) | from the v4 demo pages | VERIFIED |
| Small-box icons as Bootstrap Icons glyphs | the demo uses Heroicons-style inline SVGs in the same slot | ASSUMED (consistency with the icon set) |
| `prefers-color-scheme` + `html.dark` mapping | same values as `[data-bs-theme=dark]` | ASSUMED (mockup addition) |
| Dark-mode contrast lift for outline buttons / `.text-*` / `.link-*` | `-text-emphasis` colours | ASSUMED (mockup addition for WCAG AA) |
| `.mock-*` drawn fields | clones of `.form-control` / `.form-label` | VERIFIED values, ASSUMED class names |
| `svg.timeline-icon` 7 px padding | keeps a 16 px glyph in the 30 px disc, as the icon font does | ASSUMED |
| Removed from the copy | `-moz-`/`-ms-` duplicates, per-component reduced-motion blocks, sidebar-mini/collapse, sidebar search, ribbons, widget-user, lockscreen, accordion, floating labels, offcanvas | ASSUMED (out of the documented vocabulary) |
| Sample product, people, numbers | Kestrel Inventory (fictional) | ASSUMED |
