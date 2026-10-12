# Modern — sources, principles and scales

**Version:** 1.0 (Larapilot 5.1, October 2026). **Status:** an original system. Nothing below is copied from a product's
CSS; the products are references for the *feel*, and every value was chosen and checked for this system.

## Inspirations (honest list)

| Reference | URL | What we took from it |
| --- | --- | --- |
| Linear | https://linear.app | Inset content panel on a darker canvas, quiet sidebar, saved views with colour dots, keyboard-first everything |
| Linear — "A calmer interface" (2024 redesign notes) | https://linear.app/now/how-we-redesigned-the-linear-ui | Fewer colours, more contrast in neutrals, hairline structure instead of boxes |
| Vercel / Geist design system | https://vercel.com/geist/introduction | Neutral scale discipline, Geist type, 1 px borders, gradient-to-gray headlines |
| Raycast | https://www.raycast.com | Command palette anatomy, key-legend footer, glassy floating surfaces |
| Arc browser | https://arc.net | Soft translucent chrome, glow as a brand gesture used sparingly |
| Stripe Dashboard and stripe.com | https://stripe.com | Data density with breathing room, sparkline KPI cards, product shots drawn as real UI |
| PostHog / Mixpanel / Amplitude | https://posthog.com | Analytics vocabulary: live events, funnels, retention cohorts, breakdowns |
| Refactoring UI (Wathan & Schoger) | https://www.refactoringui.com | Shadows only for elevation, hierarchy by weight and colour rather than size |
| Bento grids (Apple keynote slides, bentogrids.com) | https://bentogrids.com | Feature storytelling in a 12-column grid of unequal tiles |

## Principles

1. **Hairlines, not boxes.** Structure is 1 px borders plus a 1 px inner top highlight; shadows only for things that float.
2. **One accent.** Electric violet (`#5b45f0` light / `#6a55ff` dark) marks primary actions, the current item and series 1.
3. **Glow is punctuation.** One glow per view (hero, featured plan, live preview). Glass only over something worth seeing.
4. **Keyboard is visible.** Every frequent action shows its shortcut (`.mo-kbd`); ⌘K reaches everything.
5. **Data first.** Tabular numbers in tables, proportional figures in headline values, thin marks, recessive grids.
6. **Dark-first, light-equal.** Both themes are hand-tuned token sets, not an inversion.

## Scales and rationale

| Scale | Values | Why |
| --- | --- | --- |
| Type (px) | 11 · 12 · 13 · 14 · 16 · 18 · 20 · 24 · 32 · 44 · 64 | App UI lives at 13/14 (dense, like Linear); marketing jumps to 44/64 with −0.035 to −0.04 em tracking |
| Weights | 400 · 500 · 600 | No bold: 600 reads strong enough in Geist/Inter and keeps the UI calm |
| Spacing | 4 px grid: 4 · 8 · 12 · 16 · 20 · 24 · 32 · 40 · 48 · 64 · 96 | Controls 24–46 px tall in 4 px steps; sections breathe at 96–104 px on the site |
| Radii | 4 · 6 · 8 · 10 · 12 · 16 · 20 · pill | 8 for controls, 12 for cards, 16 for dialogs/palette, 20 for the auth card and CTA |
| Neutrals (light) | bg `#f4f4f6`, panel `#fff`, surface-2 `#f9f9fa`, surface-3 `#f1f1f4`, border `#e5e5ea`, text `#0e0e12` / `#4c4c55` / `#6a6a74` | Slightly cool zinc; three text steps, the lightest still ≥ 4.7:1 everywhere |
| Neutrals (dark) | bg `#08080a`, panel `#0e0e11`, surface `#131317`, surface-3 `#1e1e24`, border `#26262d`, text `#ededf0` / `#a6a6b0` / `#8a8a95` | Near-black with a blue cast; raised surfaces get lighter, never shadow-only |
| Accent | `#5b45f0` (white text 5.9:1) / dark `#6a55ff` (4.8:1), text accent `#5038e6` / `#a99cff` | Violet reads "electric" without colliding with status colours |
| Chart order | violet `#5b45f0`, cyan `#0891b2`, amber `#d97706`, pink `#db2777` (dark: `#8676ff`, `#0f9cb0`, `#c7860f`, `#e0509a`) | Validated with a CVD/lightness/contrast checker in both themes (adjacent ΔE ≥ 8) |
| Sequential | violet mixed into the surface at 9–100% (`.mo-heat-1…9`) | One hue light→dark; text flips at levels 8–9 to stay ≥ 4.5:1 |
| Status | success / warning / danger / info, soft background + dark text (≥ 4.9:1) | Reserved for state, never reused as a series |
| Elevation | xs · sm · card · md · lg · xl | Dark mode shadows are deeper and carry an inner highlight instead of a lighter halo |

## Fonts and icons

| Asset | Licence | URL | Use |
| --- | --- | --- | --- |
| Geist, Geist Mono (Vercel) | SIL OFL 1.1 | https://vercel.com/font | First in the stacks; load it in the app (`npm i geist`). Not loaded by the mockups |
| Inter (Rasmus Andersson) | SIL OFL 1.1 | https://rsms.me/inter/ | Second choice; same metrics family |
| Lucide icons 1.55 | ISC | https://lucide.dev · https://github.com/lucide-icons/lucide/blob/main/LICENSE | Every icon, copied path for path, inline SVG, stroke 1.75 |
| Google "G" and GitHub marks | Vendor trademarks, brand guidelines | https://developers.google.com/identity/branding-guidelines · https://github.com/logos | Only on the SSO buttons of `login.html` |

## Verified vs assumed

| Item | Status | Note |
| --- | --- | --- |
| Text contrast ≥ 4.5:1 for every text token on every surface, both themes | VERIFIED | Computed with a WCAG luminance script while building |
| Chart palette CVD separation, lightness band, ≥ 3:1 against surfaces | VERIFIED | Categorical palette validator, light (`#fff`) and dark (`#131317`) |
| No horizontal scroll at 390 px; no remote request; no script | VERIFIED | Headless Chrome screenshots + `ds-check.py` |
| Geist renders as drawn | ASSUMED | Machines without Geist/Inter show the OS UI font (SF Pro, Segoe UI); spacing was tuned to look right with both |
| `backdrop-filter` glass | ASSUMED | Supported in current Chrome, Safari, Firefox; `@supports not` falls back to solid surfaces |
| `color-mix()` for tints and heatmap | ASSUMED | Baseline 2023; older browsers lose some tints but keep text contrast |
