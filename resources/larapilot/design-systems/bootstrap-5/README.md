# Bootstrap 5 (mockup reference)

Visual contract for **marketing site** and **authenticated app** mockups when the PRD chose **Bootstrap 5** (or the project already uses Bootstrap).

## Source of truth

| Resource | URL |
| --- | --- |
| **Official docs** | [getbootstrap.com/docs/5.3](https://getbootstrap.com/docs/5.3/getting-started/introduction/) |
| **Components** | [Bootstrap components](https://getbootstrap.com/docs/5.3/components/buttons/) |
| **Examples** | [Bootstrap examples](https://getbootstrap.com/docs/5.3/examples/) |
| **Variant index** | `sources.md` in this folder |

`tokens.css` adds Larapilot layout helpers (`.bs-app`, `.bs-sidebar`, `.bs-hero`) on top of Bootstrap 5.3 CSS loaded from CDN in each HTML screen.

## When Elise must use this

Apply this design system when **all** of the following are true:

1. The screen is a **Bootstrap-based UI** — marketing landing, authenticated dashboard, or admin area built with Bootstrap 5.
2. The PRD `## Technical Architecture` records **Bootstrap 5** as the CSS framework (or `Application Info` shows Bootstrap installed and the PRD committed to it).

Do **not** use this for Filament panels, Starter Kit screens, or Nordic minimal Tailwind-only mockups — those follow their own design systems.

## Mockup implementation

1. **Load Bootstrap + tokens** — in every Bootstrap mockup HTML:

   ```html
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
   <link rel="stylesheet" href="bootstrap-tokens.css">
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
   ```

   Copy `tokens.css` into the mockup folder as `bootstrap-tokens.css` (or reference `.larapilot/design-systems/bootstrap-5/tokens.css`).

2. **Body class** — wrap app screens with `body class="bs-mockup"`.

3. **Layout** — follow `components.md` for app shell, marketing sections, forms, and tables. **Starting points:** copy or adapt screens from `{paths.design_systems}/bootstrap-5/html/` (catalog at `index.html`).

4. **Colors** — use Bootstrap CSS variables (`--bs-primary`, `--bs-body-bg`, etc.). Override `:root` in the mockup README when the PRD specifies a custom brand palette — Alex updates `resources/sass/app.scss` or equivalent.

5. **Dark mode** — Bootstrap 5.3 supports `[data-bs-theme="dark"]`. Mockups must show **light** as primary and at least one key screen in **dark** on `<html data-bs-theme="dark">`.

6. **Mobile** — Bootstrap grid + collapse components; document navbar collapse and stacked forms per shared-runtime Mobile First rules.

## README contract (mockup folder)

When using this design system, the mockup `README.md` must include:

- **Design system:** Bootstrap 5 — link to this folder and `sources.md`
- **Screen types:** marketing vs app shell
- **Theme tokens** — primary/brand overrides if any
- **Responsive & navigation** — navbar collapse, breakpoints
- **Accessibility** — focus rings, labels, semantic landmarks

## Downstream

- **Alex** implements with Bootstrap Blade components, `@vite` SCSS, or existing project Bootstrap setup — mockups are layout/flow references.
- **Anne** tests at mobile + desktop widths with Bootstrap collapse and form validation patterns.
