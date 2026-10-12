# Bootstrap 5 (mockup reference)

Visual contract for **marketing sites** and **signed-in app screens** when the PRD records **Bootstrap 5** as the CSS framework (or the project already ships Bootstrap). Targets **Bootstrap v5.3.8** (latest 5.x, 2025) and **Bootstrap Icons v1.13**. Sample product: **Bramble Stays** — holiday bookings, a public site for guests and a host app.

A mockup drawn with this folder looks like a real Bootstrap 5.3 site in light and dark mode, on desktop and phone, **and** its markup is real Bootstrap markup: `.btn btn-primary`, `.card`, `.form-control`, `.navbar navbar-expand-lg`, `.offcanvas-lg`, `data-bs-theme`… A developer pastes it into a Blade view that loads Bootstrap and gets the same page.

## What is in the folder

| File | What it is |
| --- | --- |
| `tokens.css` | The only stylesheet: a self-contained subset of the official `bootstrap.css` 5.3.8 — same class names, same values, same source order — plus a few mockup-only sections at the end (inline icons, drawn credential fields, the dark-mode contrast lift). No network, no fonts, no scripts. |
| `components.md` | 30 components with anatomy, variants, copy-paste HTML and do/don't. |
| `sources.md` | Official sources, what was verified against them, what is assumed. |
| `html/` | 12 static screens + catalog (`html/index.html`). Every screen links only `../tokens.css`. |

## When to use it

Use it when **both** are true:

1. The screen is a Bootstrap UI — a public/marketing page, a signed-in dashboard, a CRUD list or form, settings, auth.
2. The PRD `## Technical Architecture` records **Bootstrap 5** (or `Application Info` shows Bootstrap installed and the PRD committed to it).

Do **not** use it for Filament or Nova panels, Laravel Starter Kit screens, Tailwind-only UIs, or an **admin panel built on AdminLTE** — AdminLTE has its own folder (`../adminlte/`) with the app shell, small-boxes and treeview sidebar.

## How a mockup uses it

1. Copy `tokens.css` into the mockup folder as **`bootstrap-5-tokens.css`** and link it — nothing else:

   ```html
   <!doctype html>
   <html lang="en">
   <head>
     <meta charset="utf-8">
     <meta name="viewport" content="width=device-width, initial-scale=1">
     <title>Bookings · Bramble Stays Host</title>
     <link rel="stylesheet" href="bootstrap-5-tokens.css">
   </head>
   <body class="bs-mockup">…</body>
   </html>
   ```

   `bs-mockup` on `<body>` tells the dashboard which design system a mockup uses. No CDN link, no `<script>`.
2. Start from the closest screen in `html/` (the catalog says which), keep its shell, change the content.
3. Compose with the classes in `components.md`. Every class used must exist in `tokens.css` — it holds the documented vocabulary, not all of Bootstrap. If a screen really needs a Bootstrap class that is missing, copy that rule from the official `bootstrap.css` 5.3.8 into the mockup's own copy and say so in the mockup README.
4. Interactive states are drawn, not scripted: an open dropdown is `.dropdown-menu.show` with `data-bs-popper="static"`, a modal is `.modal.fade.show.d-block` after a `.modal-backdrop.fade.show`, an open accordion item has `.collapse.show`, a toast has `.show`, a selected row has `.table-active`, an invalid field has `.is-invalid` + `.invalid-feedback`.
5. Sign-in screens **draw** the credentials: `<span class="mock-label">` + `<div class="mock-field" role="img" aria-label="Email field, filled in">…</div>` (`.mock-field--secret`, `--empty`, `--focus`, `--error`). They are the `.form-control` box cloned rule for rule. No `<form>`, no text `<input>`, never `type="password"`; the submit is `<a href="dashboard.html" class="btn btn-primary" role="button">`.
6. Dark mode: light by default; dark when the OS is dark (catalog preview), or forced with `<html data-bs-theme="dark">` (see `html/dashboard-dark.html`). `data-bs-theme="dark"` also works on one element — the host app's top navbar uses it.

### Icons

Bootstrap Icons, inline. In the **real app** an icon is the font glyph:

```html
<i class="bi bi-people" aria-hidden="true"></i>   <!-- npm i bootstrap-icons; @import "bootstrap-icons/font/bootstrap-icons.css" -->
```

The **mockup** inlines the same glyph, path for path, with the same classes, so a developer swaps the tag and nothing else:

```html
<svg class="bi bi-people" viewBox="0 0 16 16" aria-hidden="true"><path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1zm-7.978-1L7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002-.014.002zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4m3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0M6.936 9.28a6 6 0 0 0-1.23-.247A7 7 0 0 0 5 9c-4 0-5 3-5 4q0 1 1 1h4.216A2.24 2.24 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816M4.92 10A5.5 5.5 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0m3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4"/></svg>
```

`.bi` sizes the SVG like the font (1em square, baseline −.125em); size with `fs-*`, colour with `text-*`. `html/icons.html` holds 144 glyphs ready to copy; tokens.css lists every `bi-*` name the screens use (a new glyph name goes into that list, or the SVG keeps only `class="bi"`).

## Screens

| Screen | Shows | Start from it when |
| --- | --- | --- |
| `landing.html` | Navbar, hero with search card, press strip, stay cards, features, testimonials, host pricing, FAQ accordion, CTA band, footer | Any public or marketing page |
| `dashboard.html` | Host app shell (dark `navbar` + `offcanvas-lg` sidebar with `nav-pills`), stat cards, SVG chart, list group, bookings table, progress | Any signed-in overview |
| `dashboard-dark.html` | The same, forced dark | Checking dark mode |
| `resource-list.html` | Tabs with counts, search + filters, selected rows + bulk bar, sortable headers, open row menu, badges, pagination | Any index/table page |
| `modal.html` | The list with a confirmation modal over the backdrop | Destructive confirmations, dialogs |
| `resource-form.html` | Tabs, card sections, required marks, helper text, invalid + valid + disabled fields, floating labels, input groups, checks, switches, radios, upload zone, sticky actions | Create/edit pages |
| `settings.html` | List-group section nav, profile form, notification switches, payout radios, security rows, danger zone, toast | Account/preferences |
| `login.html` | Split sign-in with drawn credentials | Any auth screen |
| `empty-state.html` | First-run page, "no results" table, onboarding checklist, placeholders | Lists that can be empty |
| `components.html` | Every component in its variants and states | Looking up a variant |
| `icons.html` | 144 Bootstrap Icons glyphs | Picking an icon |

## Fidelity notes

- **Values are Bootstrap's**: colours, radii (`.375rem` default), spacing, type scale, shadows, focus rings (`0 0 0 .25rem rgba(13,110,253,.25)`), the dark palette (`#212529` body, `#2b3035` tertiary, `#dee2e6` text). Rules were copied from `dist/css/bootstrap.css` 5.3.8, minus legacy `-moz-`/`-ms-` duplicates and the per-component reduced-motion blocks (one global rule replaces them).
- **Font**: Bootstrap ships no web font; `system-ui` and the native stack render as on a real Bootstrap site.
- **Dark mode media query**: real Bootstrap switches only with `data-bs-theme`; `tokens.css` also maps `prefers-color-scheme: dark` (and `html.dark`) to the same values so catalog previews follow the OS. `<html class="light">` or `data-bs-theme="light"` opts out.
- **Dark-mode contrast lift** (the one visual deviation): in dark mode Bootstrap leaves outline buttons, `.text-primary/secondary/success/danger` and `.link-*` at the base colour (≈3.4:1 on `#212529`). The mockups lift that text to the `-text-emphasis` colour so it passes WCAG AA. Add the same eight rules (last section of `tokens.css`) to `resources/sass/app.scss` inside `@include color-mode(dark) { … }` to match.
- **Photos** are inline SVG illustrations (no remote images); avatars are initials in `rounded-circle bg-*-subtle text-*-emphasis` SVGs. In the real app use `<img class="rounded-circle" width="32" height="32">`.
- **Charts** are inline SVG drawn with `text-primary` / `text-secondary` + `currentColor`; the real app draws them with Chart.js or ApexCharts using `--bs-primary`.
- **Not in the subset**: carousel, popovers, tooltips, scrollspy, `col-xl-*`/`col-xxl-*`, `navbar-expand-md`, `offcanvas-md`, `dropstart/dropend`, colour variants of table rows and list-group items. See step 3 above when one is needed.

## Mockup → real code

| Mockup | Real app |
| --- | --- |
| `<link rel="stylesheet" href="bootstrap-5-tokens.css">` | `npm i bootstrap @popperjs/core bootstrap-icons`, then `@import "bootstrap/scss/bootstrap";` in `resources/sass/app.scss` and `@vite(['resources/sass/app.scss', 'resources/js/app.js'])` in the layout; `import 'bootstrap'` in `app.js` |
| `<svg class="bi bi-x">…</svg>` | `<i class="bi bi-x"></i>` |
| `.dropdown-menu.show` + `data-bs-popper="static"` | `.dropdown-menu` + `data-bs-toggle="dropdown"` on the toggle (Popper places it) |
| `.modal.fade.show.d-block` + backdrop | `.modal.fade` + `data-bs-toggle="modal" data-bs-target="#id"` |
| `.accordion-collapse.collapse.show` | the same, plus `data-bs-toggle="collapse" data-bs-target="#id"` on the button |
| `.toast.show` | `.toast` + `bootstrap.Toast.getOrCreateInstance(el).show()` (Laravel flash → toast) |
| `.offcanvas-lg` sidebar + toggler | same markup, `data-bs-toggle="offcanvas" data-bs-target="#hostMenu"` on the toggler |
| `.mock-field` | `<input class="form-control" type="email|password" …>` inside a real `<form method="POST">` with `@csrf` |
| `.is-invalid` + `.invalid-feedback` | `@error('field') is-invalid @enderror` and `<div class="invalid-feedback">{{ $message }}</div>` |
| SVG illustration / avatar | `<img class="card-img-top" src="{{ $listing->cover_url }}" alt="…">` |
| Inline SVG chart | Chart.js / ApexCharts canvas in the same `.card-body` |

## Do / don't

- **Do** use only Bootstrap classes and utilities; keep `style=""` for progress widths and one-off sizes (≤ 12 per screen).
- **Do** put tables in `.table-responsive` and use `text-nowrap` so phones scroll the table, not the page; use `g-4` (not `g-5`) on rows directly inside `.container` on phones.
- **Do** use `-emphasis` / `-subtle` colours (`text-success-emphasis`, `bg-warning-subtle`) for text on tinted backgrounds — they follow dark mode.
- **Do** give icon-only buttons an `aria-label`, mark the active nav item `aria-current="page"`, keep `<header>`, `<nav>`, `<main>` landmarks.
- **Don't** link the Bootstrap CDN, Google Fonts or icon fonts from a mockup; don't add `<script>`.
- **Don't** invent component classes (`.sidebar`, `.feature-icon`, `.stat-card`) — compose utilities instead; the real app would need extra CSS.
- **Don't** use `btn-outline-warning` / `btn-outline-info` or `.text-warning` for text — they fail contrast on white.
- **Don't** draw a real password input anywhere.

## README contract (mockup folder)

The mockup `README.md` names: **Design system:** Bootstrap 5 (link this folder and `sources.md`); screen types (public vs app shell); theme overrides if the PRD brands the palette (`$primary` in Sass); responsive behaviour (navbar collapse at `lg`, offcanvas sidebar); accessibility notes.

## Downstream

- **Alex** implements with Bootstrap 5.3 via Vite (Sass), Blade components and Bootstrap's JS — the mockup markup is the starting markup.
- **Anne** tests at 390 px and desktop, light and dark, keyboard focus on every control.
