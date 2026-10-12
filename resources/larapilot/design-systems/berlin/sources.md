# Berlin — sources, principles and scales

Berlin is an **original** system: nothing here copies a product. This file lists the honest inspirations (movements, studios,
public references), the principles distilled from them, and every scale with the reason behind it. Version: **1.0**
(October 2026), written for Larapilot 5.1 mockups.

## Inspirations

| Reference | URL | What Berlin takes from it |
| --- | --- | --- |
| Bauhaus-Archiv / Museum für Gestaltung, Berlin | https://www.bauhaus.de | Primary forms (square, circle), type as structure, the idea that one school designs the poster *and* the building |
| Josef Müller-Brockmann, *Grid Systems in Graphic Design* (Niggli) | https://www.niggli.ch | The strict 12-column grid, flush-left ragged-right text, numbered sections, hierarchy by size not decoration |
| International Typographic Style (overview) | https://en.wikipedia.org/wiki/International_Typographic_Style | Objective photography, asymmetric layouts, sans-serif grotesks, rules as dividers |
| Akzidenz-Grotesk — H. Berthold AG, Berlin, 1898 | https://bertholdtypes.com · https://en.wikipedia.org/wiki/Akzidenz-Grotesk | The intended typeface: the Berlin-born grotesk that preceded Helvetica |
| Experimental Jetset (Amsterdam), Whitney graphic identity and posters | https://www.experimentaljetset.nl | Big type that spans the format, black / white / one colour, the "responsive W" idea of an identity that is a system |
| Pentagram — identity case studies | https://www.pentagram.com | Rigour in applying one rule across a public site and an internal tool |
| Berlin club-flyer and techno-poster culture (archives) | https://www.residentadvisor.net · https://www.are.na/search/berlin%20techno%20flyer | Acid / fluorescent signal on black, mono running orders, tickers, photocopied halftones |
| Halftone and duotone printing (overview) | https://en.wikipedia.org/wiki/Halftone | Photo placeholders as dot patterns in two inks |
| Printer's marks: crop and registration marks | https://en.wikipedia.org/wiki/Crop_marks | Ornaments that show the page is a made object |
| WCAG 2.2 — contrast (1.4.3, 1.4.11) and focus (2.4.7, 2.4.13) | https://www.w3.org/TR/WCAG22/ | The signal-as-surface rule, 3:1 edges, the double focus ring |
| Lucide icons 1.x (ISC licence) | https://lucide.dev · https://github.com/lucide-icons/lucide/blob/main/LICENSE | Every icon, copied path for path, stroke 1.75 |
| Inter Tight (SIL OFL 1.1) | https://fonts.google.com/specimen/Inter+Tight | Free alternative to Akzidenz-Grotesk |
| Schibsted Grotesk (SIL OFL 1.1) | https://fonts.google.com/specimen/Schibsted+Grotesk | Second free alternative with more character |
| JetBrains Mono (SIL OFL 1.1) | https://www.jetbrains.com/lp/mono/ | Mono for micro-labels, indices, numbers |
| IBM Plex Mono (SIL OFL 1.1) | https://www.ibm.com/plex/ | Alternative mono |

All companies, people, clients, places and numbers of the sample product **Kollektiv Studio** are fictional. Real places
appear only as public landmarks (the TV tower silhouette, the Spree, the S-Bahn ring) in the drawn map and photos.

## Principles

1. **Borders are the layout.** Content sits in cells of a 12-column grid; neighbours share one 1 px rule (grid gap over the
   rule colour). No cards floating on shadows.
2. **One signal, used like a highlighter.** Acid marks what must not be missed: the current item, today, the main action,
   live / few left, a selected row. Everything else is ink, paper and concrete.
3. **Structure is mono, content is grotesk.** Indices, labels, dates, codes and money in mono; sentences and headlines in the
   neo-grotesk.
4. **Numbers are big and tabular.** KPIs at 40–60 px, room capacities at 56 px, dates in timetables at 36 px.
5. **Severe but legible at 3 a.m.** Contrast is never sacrificed for mood; every pair is listed below.
6. **Make the page look made.** Crop marks, registration marks, index numbers, a ticker, halftone photos — in moderation, one or
   two per view.
7. **Dark mode is native.** The club version of the same system: black canvas, paper ink, grey rules, acid allowed as ink.

## Decisions → rationale

| Decision | Rationale / reference |
| --- | --- |
| Signal = acid `#E4FF1A` instead of Bauhaus red | Club-flyer Berlin rather than museum Bauhaus; it reads as a highlighter on paper *and* glows on black; red is already the universal error colour, so using it as brand would blur meaning |
| Acid is a surface in light, ink in dark (`--bn-signal-text`) | 1.02:1 on paper vs 17.55:1 under black text and 17.55:1 as text on `#0A0A0A` (WCAG 1.4.3) |
| Acid fills on paper get a 1 px ink edge (`--bn-signal-edge`) | Acid vs paper is 1.02:1; the edge gives the component a ≥ 3:1 boundary (WCAG 1.4.11) |
| Alarm red as the only second colour, functional only | Errors and destructive actions need a conventional cue; always paired with icon + text |
| Paper `#F4F4F0` canvas, white `#FFFFFF` controls | Warm off-white reads as print; inputs on pure white stand out without shadows |
| Structural rules: ink in light, `#8A8A84` in dark; row rules concrete 300 / `#2E2E2C` | Full-strength lines on paper are the identity; on black a full white grid glares, mid-grey keeps the structure calm |
| 0 px radius everywhere, the radio is the only circle | Swiss rigour; the circle stays meaningful (radio = one of many) |
| Hard offset shadows only (3–8 px, ink in light, acid in dark) | Printed / sticker feel; no soft elevation |
| Focus: 3 px outline, 2 px offset, gap filled with the contrasting colour | Visible on paper, acid, black and photos alike (WCAG 2.4.7 / 2.4.13) |
| Mono micro-labels 11 px uppercase +0.08em; body 15 px | Labels are short structure; real reading text stays ≥ 13 px |
| Tabular numerals on all numbers | Columns of money and dates align |
| Duotone SVG photos with fixed colours | Offline, identical everywhere; photos do not invert in dark mode, like real photographs |
| `bn-` prefix, theme islands `.bn-inv` / `.bn-night` / `.bn-sig` | Any component works inside an inverted or acid block without extra CSS |

## Type scale

| Token / class | Size / line / tracking | Weight | Use |
| --- | --- | --- | --- |
| `.bn-wordmark` | 18.6vw (≈ 238 px at 1280) / 0.78 / −0.065em, uppercase | 700 | Oversized wordmark spanning the page |
| `.bn-display` | clamp(44, 7vw, 112) / 0.92 / −0.045em | 700 | Event titles, hero lines |
| `.bn-sechead__idx` | clamp(56, 6.4vw, 104) / 0.76 / −0.06em | 700 | Section numbers |
| `.bn-h1` | clamp(34, 3.8vw, 52) / 1 / −0.035em | 600 | Page titles |
| `.bn-stat__value` | clamp(38, 3.9vw, 58) / 0.92 / −0.05em | 600 | KPIs |
| `.bn-h2` | clamp(26, 2.6vw, 36) / 1.06 / −0.028em | 600 | Section titles |
| `.bn-lede` | clamp(18, 1.7vw, 23) / 1.3 / −0.016em | 400 | Intros |
| `.bn-h3` | 20 / 1.2 / −0.018em | 600 | Card and form-section titles |
| body | 15 / 1.5 / −0.006em | 400 | Reading text |
| `.bn-label`, `.bn-help` | 13 / 1.3–1.4 | 600 / 400 | Form labels, helper text |
| `.bn-micro`, `.bn-idx`, table heads | 11 / 1.4 / +0.08em, uppercase mono | 500 | Structure only |

Stacks: `"Akzidenz-Grotesk Pro", "Akzidenz-Grotesk", "Inter Tight", "Helvetica Neue", Helvetica, Arial, system-ui, sans-serif`
and `"JetBrains Mono", "IBM Plex Mono", ui-monospace, "SF Mono", Menlo, Consolas, monospace`. On a stock Mac the mockups render
in Helvetica Neue and Menlo / SF Mono, which keeps the intended character.

## Colour scale

| Token | Light | Dark | Role |
| --- | --- | --- | --- |
| `--bn-bg` | `#F4F4F0` paper | `#0A0A0A` | Canvas |
| `--bn-surface` | `#FFFFFF` | `#141413` | Inputs, menus, dialogs |
| `--bn-sunken` | `#E8E8E3` | `#1C1C1B` | Hover rows, disabled fields, dialog foot |
| `--bn-ink` | `#0A0A0A` | `#F4F4F0` | Text, rules, solid fills |
| `--bn-ink-2` | `#3A3A36` | `#C8C8C1` | Secondary text |
| `--bn-ink-3` | `#5E5E58` | `#93938D` | Muted text, micro-labels |
| `--bn-rule` | `#0A0A0A` | `#8A8A84` | Structural rules |
| `--bn-rule-2` | `#C4C4BD` | `#2E2E2C` | Row rules (decorative) |
| `--bn-signal` | `#E4FF1A` | `#E4FF1A` | Acid surface |
| `--bn-signal-text` | `#0A0A0A` | `#E4FF1A` | "Signal" text |
| `--bn-signal-soft` | `#F2FFA8` | `#22260A` | Selected rows, checked cells |
| `--bn-alarm` | `#B8241A` | `#FF6B5E` | Errors, overdue, destructive |
| `--bn-alarm-soft` | `#F8E1DE` | `#2A1210` | Error summary, alarm notice |
| Concrete scale | 100 `#E8E8E3` · 200 `#D6D6D0` · 300 `#C4C4BD` · 400 `#A3A39C` · 500 `#8A8A84` · 600 `#5E5E58` · 700 `#3A3A36` · 800 `#262624` · 850 `#1C1C1B` · 900 `#141413` | | Greys with a faint warm cast, like poured concrete |

## Spacing, shape and shell

| Scale | Values | Rationale |
| --- | --- | --- |
| Space | 4 · 8 · 12 · 16 · 24 · 32 · 48 · 64 · 96 · 128 px (`--bn-s1…s10`) | 4 px base; big jumps between groups keep pages airy |
| Gutter / page padding | 40 / 32 px desktop · 28 / 24 px ≤ 1180 · 16 px ≤ 640 | The text edge of every section lines up |
| Controls | 40 px default · 32 px small · 56 px large | Touch-safe default, dense toolbars |
| Shell | rail 248 px, bars 56 px; public header 64 px | Rail brand rule and topbar rule form one continuous line |
| Grid | 12 columns; bordered grid spans 3 → 6 → 12 at 1180 / 640 | Müller-Brockmann grid that collapses predictably |
| Radius | 0 everywhere | — |
| Shadows | 3 px (signal button), 4 px (menus, toasts), 8 px (dialogs), hard, no blur | — |

## Contrast (measured, WCAG relative luminance)

| Text / foreground | Background | Light | Dark |
| --- | --- | --- | --- |
| Ink | Canvas | `#0A0A0A` on `#F4F4F0` **17.96:1** | `#F4F4F0` on `#0A0A0A` **17.96:1** |
| Ink | Surface | on `#FFFFFF` **19.80:1** | on `#141413` **16.72:1** |
| Ink-2 | Canvas | `#3A3A36` **10.36:1** | `#C8C8C1` **11.77:1** |
| Ink-3 (muted, micro) | Canvas | `#5E5E58` **5.92:1** | `#93938D` **6.41:1** |
| Ink-3 | Surface / sunken | **6.53:1** / **5.31:1** | **5.97:1** / **5.52:1** |
| Black text | Acid surface | **17.55:1** (both themes) | **17.55:1** |
| Muted text inside `.bn-sig` | Acid | `#3A3A36` **10.13:1** | same |
| Acid text | Paper | **1.02:1 — forbidden** | — |
| Acid text | Black canvas / surface | — | **17.55:1** / **16.34:1** |
| Text on selected row | Signal-soft | `#0A0A0A` on `#F2FFA8` **18.52:1** | `#F4F4F0` on `#22260A` **14.11:1** |
| Alarm text | Canvas | `#B8241A` **5.76:1** | `#FF6B5E` **7.09:1** |
| Alarm text | Alarm-soft | **5.09:1** | **6.30:1** |
| Text on alarm fill | Alarm | paper on `#B8241A` **5.76:1** | black on `#FF6B5E` **7.09:1** |
| Muted text in `.bn-inv` / `.bn-night` | Black | `#A3A39C` **7.80:1** / `#93938D` **6.41:1** | `#5E5E58` on paper **5.92:1** |
| Structural rule (non-text) | Canvas | ink **17.96:1** | `#8A8A84` **5.70:1** |
| Concrete avatar text | `#C4C4BD` | black **11.29:1** | same |

## Icon set

**Lucide 1.x** (ISC licence, © Lucide Contributors; parts derived from Feather, MIT) — https://lucide.dev,
licence: https://github.com/lucide-icons/lucide/blob/main/LICENSE. Icons are inlined path for path with
`stroke="currentColor"`, stroke width 1.75 (1.5 at 32 px, 1.25 at 48 px), round caps and joins, always `aria-hidden="true"`.
Used: menu, search, plus, bell, sun-moon, settings-2, log-out, arrow-right / left / down / up-right / down-right,
arrow-right-left, chevron-down, x, check, circle-check, circle-alert, triangle-alert, octagon-alert, info, download, upload,
file-down, calendar-plus, list-plus, building-2, user-round, user-round-plus, rows-3, rows-4, archive, pause, ellipsis,
pencil-line, copy, receipt-euro, trash-2, rotate-ccw, history, eye, scroll-text, key-round, shield-check, radio, accessibility,
hand-heart, ticket, megaphone, moon.
