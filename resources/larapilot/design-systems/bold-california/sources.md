# BOLD California — sources, principles and scales

**Version:** 1.0 (October 2026). **Status:** original Larapilot system — not a copy of a product or a UI kit. Nothing below is
"verified against a vendor"; instead each decision is listed with the reference or reasoning behind it.

## Inspirations (honest list)

| Reference | URL | What we took |
| --- | --- | --- |
| *The Endless Summer* poster (John Van Hamersveld, 1964) | https://en.wikipedia.org/wiki/The_Endless_Summer | The big flat sun disc with hard-edged silhouettes: our logo mark and every illustration |
| Deborah Sussman / Sussman-Prejza, LA 1984 Olympics "Look" | https://en.wikipedia.org/wiki/Deborah_Sussman | Saturated warm-and-hot colour on light grounds, confetti of hues used as wayfinding, optimism as a system |
| California mid-century modern graphics and Case Study era optimism | https://en.wikipedia.org/wiki/Case_Study_Houses | Sun, sand and Pacific blues as a palette; flat shapes, no textures |
| Neo-brutalist web (Gumroad's 2021 redesign and its imitators) | https://gumroad.com | Ink outlines + offset "sticker" shadows on buttons and cards — softened here with big radii and warm ink instead of black |
| West-Coast DTC and launch pages (oversized grotesk headlines, marquees, round stickers) | https://www.awwwards.com/websites/e-commerce/ | Giant tight headlines, running tickers, rotated stickers, oversized numbers |
| Inter and Inter Display (Rasmus Andersson, SIL OFL 1.1) | https://rsms.me/inter/ · https://github.com/rsms/inter/blob/master/LICENSE.txt | The real fonts to load: Display for 800–900 headlines, Inter for UI |
| JetBrains Mono (SIL OFL 1.1) | https://www.jetbrains.com/lp/mono/ | Booking IDs and code in the catalog (optional) |
| **Lucide** icons v1.55 (ISC licence) | https://lucide.dev · https://github.com/lucide-icons/lucide/blob/main/LICENSE | Every UI icon, copied path for path, 24 px grid, 2 px stroke, `currentColor` |
| WCAG 2.2 — contrast minimum, non-text contrast, focus appearance | https://www.w3.org/TR/WCAG22/#contrast-minimum · https://www.w3.org/TR/WCAG22/#non-text-contrast | AA for every text pair in both themes; 3:1 for control borders; visible focus |
| `prefers-reduced-motion` | https://developer.mozilla.org/en-US/docs/Web/CSS/@media/prefers-reduced-motion | The ticker and hover lifts stop |
| Tailwind CSS v4 theme variables | https://tailwindcss.com/docs/theme | How the tokens map into a real Laravel app (README) |

## Principles

1. **Sun first.** Warm sand canvas (`#fff4e4`), warm ink (`#22140f`) instead of black, and hues that feel like late
   afternoon. Cold grays never appear; the dark theme is a warm night (cocoa-plum) with glowing hues.
2. **Say it big.** One huge display headline per screen and oversized numbers for the 3–4 metrics that matter. Everything
   else is calm Inter at 15 px.
3. **Stickers, sparingly.** The ink outline + offset shadow marks the *one* thing to touch (primary button, active nav item,
   current slot, featured plan). Everything else is soft: 1 px warm lines and a warm blur shadow.
4. **Ink on colour.** Hue fills carry ink text; hues as *text* use their 700 shade. That single rule keeps the palette loud
   and AA-compliant.
5. **Drawn, not photographed.** Flat SVG shapes (sun discs, wave bands, pill boards, palm fronds) with a 2.5 px ink outline
   stand in for photos, so mockups stay offline and on-brand.
6. **Round and friendly.** Pills for buttons, chips, tabs and badges; 16 px fields; 22–28 px cards; 40 px marketing blocks.

## Decisions → rationale

| Decision | Rationale / reference |
| --- | --- |
| Primary = sunset orange `#ff6a2b` with ink text (6.3:1) | The warmest saturated hue that still carries dark text at AA; white on it would be 2.9:1 |
| Gold `#ffc23a` for the hero stat and stickers | Highest-energy hue, 11:1 with ink; reads as "sun" |
| Pacific `#0fa3a3` / ocean `#2456e8` as the cool counterweights | Sea for "in progress / water", ocean as the focus ring colour — a blue ring is visible on every warm surface |
| Palm `#2fa85a` for success, poppy `#f2392c` for errors | Natural semantic mapping that still belongs to the palette |
| Hibiscus `#ff8bb0` | Gradient stop and playful accent; never a status |
| 2 px ink outline + 3/4/7 px offset shadow | Neo-brutalist "sticker" (Gumroad) made warmer with ink brown and rounded corners |
| Radii 8 · 12 · 16 · 22 · 28 · 40 px, pills 999 px | Softness balances the heavy type; controls 16, cards 28, marketing blocks 40 |
| Display 900 at −0.055 em, line height 0.84–0.95 | DTC headline style; tight tracking only at ≥ 36 px where it stays legible |
| Body Inter 15 px / 1.5 | Comfortable admin density without feeling cramped next to the big type |
| Dark canvas `#1c1215` (cocoa-plum), surfaces `#2a1a1e` / `#33212a` | "Bonfire night": warm, keeps the hues glowing, avoids the generic gray-900 look |
| Inputs 50 px tall, 2 px `#9c7a63` border | Chunky like the buttons; border ≥ 3:1 against the surface (non-text contrast) |
| Ticker 48 s linear marquee | Slow enough to read; duplicated track for a seamless loop; stops with reduced motion |

## Scales

**Type** (rem at 16 px root): 0.75 (eyebrow, axis) · 0.875 (small, table) · 0.9375 (body) · 1.0625 (h4, lead small) ·
1.25 (h3) · 1.375 (section title) · 1.75 (h2) · 2.75 (h1, 2.25 on phones) · 3.75–7.5 clamp (display) · 2.75–3.75 clamp (stat
value) · 3–5.75 clamp (big numbers) · 4–15 clamp (footer wordmark). Weights 500 / 600–650 / 700–750 / 800 / 900. Tracking:
+0.12 em caps, −0.01 to −0.03 em headings, −0.035 to −0.075 em display.

**Space** (4 px base): 4 · 8 · 12 · 16 · 20 · 24 · 32 · 40 · 56 · 72 · 96. Card padding 24 (20 on phones), section bands 88
(64 / 56), page gutter 32 → 24 → 20 → 16.

**Colour ramps.** Sand 0 `#fffdf8` · 50 `#fff8ee` · 100 `#fff4e4` · 200 `#fbe7cc` · 300 `#f3d3a8` · 400 `#e2b887`; ink 900
`#22140f` · 700 `#6b4a3a` · 500 `#86614d`. Hues as fill / text / tint: sunset `#ff6a2b` / `#b23f0c` / `#ffe3d2`; poppy
`#f2392c` (strong `#c8261a`) / `#b3261c` / `#ffddd8`; gold `#ffc23a` / `#8a5a00` / `#fff0c2`; Pacific `#0fa3a3` / `#0b6f70` /
`#d2f1ee`; ocean `#2456e8` / `#1f4fd6` / `#dfe7ff`; palm `#2fa85a` / `#1d6b3a` / `#d7f2df`; hibiscus `#ff8bb0` / `#a3214f` /
`#ffe1eb`. Night: canvas `#1c1215`, surface `#2a1a1e`, surface-2 `#33212a`, line `#47303a`, text `#fff0e3` / `#dcc0b4` /
`#c9a797`, hues brightened (sunset `#ff7f45`, gold `#ffcb52`, sea `#2cc7c2`, palm `#4ccb7c`, poppy `#ff6b5b`, ocean `#6e9bff`).

**Shape & depth.** Radius 8 / 12 / 16 / 22 / 28 / 999. Sticker shadows 3 · 4 · 7 px offsets in ink (night: `#0b0607`). Soft
shadow `0 12px 30px -16px` warm brown at 32 %.

**Shell.** Sidebar 272 px (240 under 1180 px, hidden under 960 px), floating 12 px from the edges; topbar 44 px controls;
container 1200 px; form aside 300 px; settings sub-nav 236 px.

## Contrast table (WCAG 2.x ratios, computed)

| Pair | Light | Dark |
| --- | --- | --- |
| Body text on canvas | `#22140f` on `#fff4e4` **16.4** | `#fff0e3` on `#1c1215` **16.4** |
| Body text on surface | `#22140f` on `#fffdf8` **17.6** | `#fff0e3` on `#2a1a1e` **14.9** |
| Secondary text | `#6b4a3a` on `#fffdf8` **7.8**, on canvas **7.2** | `#dcc0b4` on `#2a1a1e` **9.7**, on `#33212a` **8.8** |
| Tertiary / placeholder | `#86614d` on `#fffdf8` **5.4**, on canvas **5.0** | `#c9a797` on `#2a1a1e` **7.5**, on `#33212a` **6.8** |
| Ink on sunset (primary button) | `#22140f` on `#ff6a2b` **6.3** | `#1c1215` on `#ff7f45` **7.3** |
| Ink on gold | **11.1** | `#1c1215` on `#ffcb52` **12.1** |
| Ink on Pacific / palm / poppy fills | **5.8** / **5.9** / **4.6** (poppy fill carries icons, not text) | `#1c1215` on `#2cc7c2` **8.8** / on `#ff6b5b` **6.6** |
| Sunset text (links, eyebrows) | `#b23f0c` on canvas **5.4**, on surface **5.7** | `#ff9a66` on `#2a1a1e` **8.0**, on canvas **8.8** |
| Sea / palm / poppy text | `#0b6f70` **5.5** · `#1d6b3a` **6.0** · `#b3261c` **6.0** (on canvas) | `#4fd8d2` **10.5** · `#6bdb94` on surface **9.6** · `#ff8a7c` **8.0** (on canvas) |
| Badges (text on tint) | palm **5.5** · sun **5.2** · sea **5.0** · ocean **5.4** · poppy **5.2** · sunset **4.8** · hibiscus **6.0** | sun **8.0** · sea **7.1** · palm **7.3** · ocean **6.6** · poppy **6.0** · sunset **6.5** · hibiscus **8.1** |
| White on deep-sea gradient (swell card) | `#fff` on `#0b6f70` **6.0** → `#1f4fd6` **6.7** | on `#0e5f63` **7.4** → `#123f7a` **10.4** |
| Danger button | `#fff` on `#c8261a` **5.6** | `#1c1215` on `#ff6b5b` **6.6** |
| Selected row text | `#22140f` on `#fff1c9` **15.9** | `#fff0e3` on `#3d2a1c` **12.2** |
| Night bands / footer | `#fff4e4` on `#22140f` **16.4**; gold titles **11.1** | `#fff0e3` on `#110a0c` **17.6**; gold titles **12.2** |
| Control border (non-text, ≥ 3:1) | `#9c7a63` on `#fffdf8` **3.8**, on canvas **3.6** | `#8e6e75` on `#2a1a1e` **3.7**, on canvas **4.0** |
| Focus ring (non-text) | `#2456e8` on canvas **5.4** | `#8fb0ff` on canvas **8.6** |

## Icon set

**Lucide** v1.55.0 (`lucide-static`), ISC licence — https://lucide.dev, licence text at
https://github.com/lucide-icons/lucide/blob/main/LICENSE. Icons are copied path for path into the screens, with the SVG
attributes moved to the `.bc-i` class (`fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap/linejoin: round`).
Used: layout-dashboard, calendar-check, calendar, waves, sailboat, users, user-round, shopping-bag, settings, palette, search,
bell, moon, plus, menu, chevron-right/left/down, chevrons-up-down, arrow-up, arrow-right, arrow-up-right, arrow-down-up,
download, upload, list-filter, ellipsis, x, check, minus, star, sun, sunrise, flame, timer, map-pin, trending-up/down,
badge-dollar-sign, credit-card, send, copy, pencil, trash-2, info, circle-alert, circle-check, triangle-alert, shield-check,
mail, phone, camera, heart, undo-2. Brand logos are not part of Lucide; social links use generic icons (camera, mail, phone).

Illustrations (logo mark, sun, waves, boards, palm, rack, wetsuit) are original drawings made for this system and may be used
freely with it.
