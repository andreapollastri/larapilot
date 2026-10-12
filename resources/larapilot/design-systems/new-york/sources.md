# New York — sources, principles and scales

New York is an **original** system inspired by public references: the 1970 subway signage manual, the city's street signs and
cabs, and the broadsheet newspaper tradition. It is **not affiliated with, endorsed by or derived from the MTA, New York City
Transit, NYC DOT, the Taxi & Limousine Commission or any newspaper**. No logo, trademark, masthead, route sign, map or
licensed typeface is reproduced: the bullets are plain discs carrying the initials of fictional restaurants, the "subway"
colours are used as a five-colour categorical palette, and every name in the nameplate is invented. Version: **1.0** (October
2026), written for Larapilot 5.1 mockups.

## Inspirations

| Reference | URL | What New York takes from it |
| --- | --- | --- |
| *New York City Transit Authority Graphics Standards Manual*, Unimark International — Massimo Vignelli and Bob Noorda, 1970 | https://en.wikipedia.org/wiki/Unimark_International · https://en.wikipedia.org/wiki/Bob_Noorda | Black signage bands with the white stripe along the top, Helvetica set medium and tight, round route discs with one letter, arrows in white discs, "one sign, one message" |
| The full-size reissue of the manual, Standards Manual (Jesse Reed and Hamish Smyth), 2014, licensed from the MTA; compact edition later | https://standardsmanual.com · https://standardsmanual.com/products/nyctacompactedition | Access to the manual's pages: band proportions, arrow and disc construction, the discipline of a written standard |
| Vignelli Center for Design Studies, RIT | https://www.rit.edu/vignellicenter/ | The Vignelli canon: few typefaces, few sizes, a grid, the same rules applied everywhere |
| MTA line colours (Line Colors page of the MTA developer resources, reproduced by Metro Route Atlas; MTA Colors open dataset) | http://web.mta.info/developers/resources/line_colors.htm · https://metrorouteatlas.net/color_notes.html · https://catalog.data.gov/dataset/mta-colors | The five line hues used as the restaurant palette (A blue, M orange, G green, L grey, 7 purple) |
| New York City street-name signs (NYC DOT; MUTCD mixed-case rule of 2010, Clearview lettering) | https://www.nyc.gov/html/dot/html/home/home.shtml · https://mutcd.fhwa.dot.gov · https://en.wikipedia.org/wiki/Clearview_(typeface) | `.ny-streetsign`: green plate, white mixed-case letters, a thin white inner border, stacked plates for a corner |
| New York taxi yellow (1967 rule: medallion cabs painted "Dupont M6284 or its equivalent") | https://time.com/4640097/yellow-taxi-cabs-history/ · https://www.nyc.gov/site/tlc/index.page | The one accent, `#F7B500`, and the idea that a single colour makes a thing recognisable across a whole city |
| The Checker cab's chequered band | https://en.wikipedia.org/wiki/Checker_Marathon | `.ny-checker`, used once or twice per page at most |
| The Times Square "zipper" news ticker (One Times Square, from 1928) | https://en.wikipedia.org/wiki/One_Times_Square | `.ny-zipper`: a running line of news in lit capitals |
| Didone typefaces — Bodoni, Didot | https://en.wikipedia.org/wiki/Didone_(typography) | Headlines, big numbers and prices in a high-contrast serif with hairline serifs |
| Broadsheet newspaper anatomy (nameplate, ears, folio, kicker, dek, byline, drop cap, pull quote, columns) | https://en.wikipedia.org/wiki/Nameplate_(publishing) · https://en.wikipedia.org/wiki/Newspaper | The furniture that structures every page; the public site is a front page, the admin a production desk |
| Restaurant menus of the city's red-sauce and chophouse tradition (general genre) | — | The carte: courses between double rules, dotted price leaders, prices without the dollar sign |
| WCAG 2.2 — contrast (1.4.3, 1.4.11), use of colour (1.4.1), focus (2.4.7, 2.4.13) | https://www.w3.org/TR/WCAG22/ | The Taxi rule, the Line rule, lettered discs, the double focus ring |
| Lucide icons 1.x (ISC licence) | https://lucide.dev · https://github.com/lucide-icons/lucide/blob/main/LICENSE | Every icon, copied path for path, stroke 1.75 |
| Bodoni Moda (SIL OFL 1.1) | https://fonts.google.com/specimen/Bodoni+Moda | Intended display face (Didone with optical sizes) |
| Playfair Display (SIL OFL 1.1) · Libre Bodoni (SIL OFL 1.1) | https://fonts.google.com/specimen/Playfair+Display · https://fonts.google.com/specimen/Libre+Bodoni | Free alternatives for the display face |
| Helvetica Neue (Monotype, commercial) · Inter (SIL OFL 1.1) | https://www.monotype.com/fonts/helvetica · https://rsms.me/inter/ | Signage and UI; Inter is the free alternative |
| Source Serif 4 (SIL OFL 1.1) | https://fonts.google.com/specimen/Source+Serif+4 | Text serif for deks, prose and captions |
| IBM Plex Mono (SIL OFL 1.1) | https://fonts.google.com/specimen/IBM+Plex+Mono | Mono for codes, file names, keys |

All companies, people, restaurants, dishes, phone numbers and figures of the sample product **Mulberry & Grand** are
fictional. Real streets (Mulberry St, Grand St, Lafayette St, Ludlow St, Seventh Ave S, Bowery, Spring St, Canal St) appear
only as addresses and on the drawn map.

## Principles

1. **Front page and platform.** Content reads like a newspaper (Didone headlines, deks, rules, columns); wayfinding reads like
   the subway (black bands, Helvetica, discs, arrows). Each job has its voice; they never swap.
2. **One accent, used as a surface.** Taxi yellow marks what must not be missed: the current item, now, the main action, a
   selected row, the rush hour. Everything else is ink, newsprint and greys.
3. **Five lines identify five things.** Line colours are categorical (restaurants, chart series), always with their letter,
   never a status.
4. **Status is a word with a shape.** Tags and service status carry text, a fill and often an icon; green and red are
   functional and never decorative.
5. **Numbers are set like headlines.** Covers, revenue, prices and error codes in the Didone at 40–260 px, lining and tabular.
6. **Rules are the layout.** Hairlines between cells, a 2 px rule under heads, a double rule under page heads and over section
   heads. No floating cards on soft shadows.
7. **The night edition is native.** In dark mode the page becomes the platform: black canvas, newsprint ink, taxi allowed as
   text.

## Decisions → rationale

| Decision | Rationale / reference |
| --- | --- |
| Accent = taxi yellow `#F7B500` | The most recognisable single colour in the city; it reads as a highlighter on newsprint and glows on the black signage |
| Taxi is a surface in light, ink in dark (`--ny-accent-text`) | 1.58:1 on newsprint vs 10.40:1 under ink and 10.32:1 as text on `#121211` (WCAG 1.4.3) |
| Taxi fills on paper get an ink edge (`--ny-accent-edge`) | Checkbox, radio, switch, live dot, chart "now" bar and taxi tag stay visible as shapes (WCAG 1.4.11) |
| Line hues from the subway palette, letters = restaurant initials | Instant recognition as "lines"; the letters make colour redundant (WCAG 1.4.1) |
| Black letters on M, G, L discs, white on A and 7 | White on orange is 2.98:1; black reaches 6.34 / 8.17 / 8.02:1. A deliberate departure from the real signs |
| A line drawn `#4D7FE0` in dark charts | `#0039A6` on `#121211` is 1.91:1; the lighter blue gives 4.84:1 |
| Street green `#0B6B4A` as "good", red `#B3261E` / `#FF7B6E` as "alarm" | Conventional cues that stay readable as text (5.69:1, 5.70:1, 7.42:1) and never collide with a line colour of the same name |
| Newsprint `#F3EFE6` canvas, sheet `#FBF9F4` controls, pulp `#EAE4D6` sunken | Warm paper reads as print; controls sit on a lighter sheet without shadows |
| Ink `#111111`, not pure black | Printed ink on newsprint; keeps 16.46:1 on the canvas |
| Rail, signage bands, dialog heads and toasts always black, with an inset white stripe at 6–8 px | The manual's band, reused for every piece of wayfinding chrome |
| Didone for headlines, numbers, prices; Helvetica for UI and signage; a text serif for long reads | Three voices with three jobs; the Didone's hairlines need ≥ 20 px |
| Radius 2 px on controls, 4 px on signs, full circles for discs, avatars, chips and the pager | Printed corners; the circle belongs to the discs and anything that behaves like a token |
| One soft lift (`--ny-lift`) for menus, dialogs, toasts | Things that float over the page; nothing else casts a shadow |
| Focus: 3 px outline, 2 px offset, a contrasting 2 px gap | Ink + taxi gap on paper, taxi + black gap on black: visible on every surface (WCAG 2.4.7 / 2.4.13) |
| Duotone SVG photos with fixed colours | Offline, identical everywhere; photos do not invert in dark mode, like real photographs |
| `ny-` prefix, theme islands `.ny-night` / `.ny-inv` / `.ny-taxi` | Any component works inside a black, inverted or yellow block without extra CSS |

## Type scale

| Token / class | Size / line / tracking | Face, weight | Use |
| --- | --- | --- | --- |
| `.ny-nameplate` | clamp(56, 10.4vw, 168) / 0.9 / −0.025em (`--sm` clamp(36, 5vw, 64)) | Didone 400 | The masthead wordmark |
| `.ny-err__code` | clamp(120, 17vw, 260) / 0.8 / −0.04em | Didone 400 | 404 |
| `.ny-empty__num` | clamp(120, 14vw, 200) / 0.8 / −0.04em | Didone 400 | Empty-state number |
| `.ny-display` | clamp(44, 6.4vw, 104) / 0.96 / −0.015em | Didone 400 | Hero lines |
| `.ny-auth__headline` | clamp(48, 5.6vw, 92) / 0.95 / −0.02em | Didone 400 | Sign-in panel |
| `.ny-stat__value` | clamp(40, 3.9vw, 60) / 0.95 / −0.02em | Didone 400, lining tabular | KPIs |
| `.ny-h1` | clamp(36, 3.9vw, 56) / 1.02 / −0.012em | Didone 400 | Page titles |
| `.ny-h2` | clamp(28, 2.7vw, 38) / 1.08 / −0.01em | Didone 400 | Section titles |
| `.ny-h3--serif`, `.ny-cell__headline`, `.ny-formsec__title` | 26 / 1.1 | Didone 400 | Card and form-section titles |
| `.ny-band` | clamp(18, 1.7vw, 24) / 1.15 / −0.01em (`--lg` clamp(24, 2.6vw, 38)) | Helvetica 500 | Signage |
| `.ny-dek` | clamp(18, 1.6vw, 22) / 1.4 | Text serif 400 | Subheads |
| `.ny-h3` / `.ny-h4` | 20 / 1.2 · 16 / 1.3 | Helvetica 700 | UI headings |
| `.ny-prose`, `.ny-lede` | 17 / 1.6 · 17 / 1.45 | Text serif · Helvetica | Long reads, intros |
| body | 15 / 1.5 | Helvetica 400 | UI text |
| `.ny-field__label`, `.ny-help` | 13 / 1.3–1.4 | Helvetica 700 / 400 | Form labels, helper text |
| `.ny-kicker`, `.ny-label`, folio, table heads | 10.5–11 / 1.3 / +0.1em, uppercase | Helvetica 700 | Structure only |

Stacks: display `"Bodoni Moda", "Playfair Display", Didot, "Bodoni 72", "Bodoni MT", "Libre Bodoni", "Times New Roman", serif`;
sans `"Helvetica Neue", Helvetica, Inter, "Nimbus Sans", Arial, system-ui, sans-serif`; text `"Source Serif 4", "Iowan Old
Style", Charter, Georgia, "Times New Roman", serif`; mono `"IBM Plex Mono", ui-monospace, "SF Mono", Menlo, Consolas,
monospace`. On a stock Mac the mockups render in Didot / Bodoni 72, Helvetica Neue, Iowan Old Style and SF Mono / Menlo, which
keeps the intended character.

## Colour scale

| Token | Light | Night edition | Role |
| --- | --- | --- | --- |
| `--ny-bg` | `#F3EFE6` newsprint | `#121211` | Canvas |
| `--ny-surface` | `#FBF9F4` sheet | `#1A1A18` | Inputs, menus, dialogs, cards |
| `--ny-sunken` | `#EAE4D6` pulp | `#24231F` | Hover rows, affixes, quiet notices |
| `--ny-ink` | `#111111` | `#F1ECE1` | Text, rules, solid fills |
| `--ny-ink-2` | `#4A453D` | `#CFC8BA` | Secondary text, deks |
| `--ny-ink-3` | `#6A6358` | `#9C9587` | Muted text, labels, input borders |
| `--ny-rule` | `#111111` | `#8E877A` | Structural rules |
| `--ny-rule-2` | `#D3CCBD` | `#34322D` | Hairlines (decorative) |
| `--ny-accent` (`--ny-taxi`) | `#F7B500` | `#F7B500` | Taxi surface; hover `--ny-taxi-deep` `#E0A300` |
| `--ny-accent-text` / `--ny-accent-edge` | `#111111` | `#F7B500` | "Accent" text and the edge around taxi fills |
| `--ny-accent-soft` | `#FCEBB6` | `#2E2507` | Selected rows, checked tiles, active index item |
| `--ny-good` / `--ny-good-soft` | `#0B6B4A` / `#DCEBE2` | `#6FD4A0` / `#12261D` | Good service, finished, ready |
| `--ny-alarm` / `--ny-alarm-soft` | `#B3261E` / `#F6DEDA` | `#FF7B6E` / `#2C1311` | Errors, late, no-show, destructive |
| Lines | A `#0039A6` · M `#FF6319` · G `#6CBE45` · L `#A7A9AC` · 7 `#B933AD` | same (A charts `#4D7FE0`) | Restaurants and chart series only |
| Letters on discs | A white · M ink · G ink · L ink · 7 white | same | `--ny-on-*` |
| Greys | 100 `#E4DED1` · 200 `#D3CCBD` · 300 `#BDB5A5` · 400 `#9E9687` · 500 `#807869` · 600 `#6A6358` · 700 `#4A453D` · 800 `#2C2A26` · 850 `#201F1C` · 900 `#181816` | | Warm, like newsprint greys |

Theme islands: `.ny-night` and the rail use `#111111` / `#0A0A09` canvases with `#F5F1E8` ink and `#A39C8E` muted text;
`.ny-taxi` uses taxi with ink text, `#3E3A31` muted text and `#7A1510` alarm.

## Spacing, shape and shell

| Scale | Values | Rationale |
| --- | --- | --- |
| Space | 4 · 8 · 12 · 16 · 24 · 32 · 48 · 64 · 96 · 128 px (`--ny-s1…s10`) | 4 px base; big jumps between groups keep pages airy |
| Gutter / page padding | 40 / 32 px desktop · 28 / 24 px ≤ 1180 · 16 / 16 px ≤ 640 | Every section's text edge lines up |
| Controls | 40 px default · 32 px small · 52 px large | Touch-safe default, dense toolbars |
| Shell | rail 256 px, topbar 60 px; public header 68 px; zipper 38 px | The rail's white stripe sits at the same height as the bands' |
| Grid | 12 columns; ruled grid spans 3 → 6 → 12 at 1180 / 640; rail collapses at 960 | Newspaper columns that collapse predictably |
| Timeline | 12 half-hour columns from 5:00 PM, venue column 210 px, min width 860 px | One dinner service on one screen |
| Radius | 2 px controls · 4 px signs, dialogs, toasts · 50% discs, avatars, pills | Printed corners, round tokens |
| Shadows | `--ny-lift` only (menus, dialogs, toasts, `.ny-card--lift`) | — |

## Contrast (measured, WCAG relative luminance)

| Text / foreground | Background | Light | Night edition |
| --- | --- | --- | --- |
| Ink | Canvas | `#111111` on `#F3EFE6` **16.46:1** | `#F1ECE1` on `#121211` **15.91:1** |
| Ink | Surface / sunken | **17.95:1** / **14.90:1** | **14.79:1** / **13.35:1** |
| Ink-2 | Canvas | `#4A453D` **8.28:1** | `#CFC8BA` **11.27:1** |
| Ink-3 (muted, labels) | Canvas / surface / sunken | `#6A6358` **5.17:1** / **5.64:1** / **4.68:1** | `#9C9587` **6.30:1** / **5.86:1** / **5.29:1** |
| Ink | Taxi | **10.40:1** (both themes); hover `#E0A300` **8.46:1** | same |
| Taxi text | Newsprint | **1.58:1 — forbidden** (on sheet 1.73:1) | — |
| Taxi text | Black band / night canvas / night surface | **10.40:1** | **10.32:1** / **9.60:1** |
| Text on selected row | Accent-soft | `#111111` on `#FCEBB6` **15.91:1**; muted **5.00:1** | `#F1ECE1` on `#2E2507` **12.88:1**; muted **5.10:1** |
| Good text | Canvas / good-soft | `#0B6B4A` **5.69:1** / **5.29:1** | `#6FD4A0` **10.35:1** / **8.78:1** |
| Alarm text | Canvas / alarm-soft | `#B3261E` **5.70:1** / **5.10:1** | `#FF7B6E` **7.42:1** / **6.88:1** |
| Text on alarm fill | Alarm | white on `#B3261E` **6.54:1** | black on `#FF7B6E` **7.47:1** (white would be 2.53:1) |
| Text on street green | `#0B6B4A` | white **6.53:1** | same |
| Disc letters | Line colour | white on A **9.83:1** · ink on M **6.34:1** · ink on G **8.17:1** · ink on L **8.02:1** · white on 7 **5.07:1** | same |
| Band text / sub | Black | `#FFFFFF` **18.88:1** / `#C9C3B6` **10.76:1** | same |
| Night island muted | `#111111` | `#A39C8E` **6.93:1** | same |
| Toast text / meta | `#111111` | `#D2CBBD` **11.71:1** / `#B5AEA0` **8.57:1** | same |
| Inside `.ny-taxi` | Taxi | ink-2 `#2C2A26` **7.89:1** · muted `#3E3A31` **6.24:1** · alarm `#7A1510` **5.95:1** | same |
| Structural rule (non-text) | Canvas | ink **16.46:1** | `#8E877A` **5.26:1** |
| Chart lines (non-text) | Canvas | A **8.57:1** · 7 **4.42:1** · M `#D2490F` **3.89:1** · L `#75777B` **3.91:1** · G `#3E8A2C` **3.75:1** — the orange, grey and green lines draw a shade deeper than their bullets on newsprint; each line still ends in its lettered disc | A (`#4D7FE0`) **4.84:1** · M **6.29:1** · G **8.11:1** · L **7.96:1** · 7 **3.70:1** |

## Icon set

**Lucide 1.x** (ISC licence, © Lucide Contributors; parts derived from Feather, MIT) — https://lucide.dev,
licence: https://github.com/lucide-icons/lucide/blob/main/LICENSE. Icons are inlined path for path with
`stroke="currentColor"`, stroke width 1.75 (1.5 at 32 px, 1.25 at 48 px, 2.25 with `.ny-i--bold`), round caps and joins,
always `aria-hidden="true"`. Typical set: menu, search, plus, bell, moon-star, book-open, users, utensils-crossed, ban, store,
chef-hat, settings, log-out, arrow-right / left / down / up-right / down-right, chevron-down / left / right, x, check,
circle-check, circle-alert, triangle-alert, octagon-alert, clock, clock-alert, construction, train-front, map-pin, phone,
calendar, armchair, message-square, user-x, lock, download, upload, ellipsis, pencil-line, trash-2, radio, wine, fish, beef.

## Verified vs assumed

| Item | Status | Note |
| --- | --- | --- |
| The 1970 manual: Unimark International, Massimo Vignelli and Bob Noorda, commissioned by the NYCTA in 1967 | VERIFIED | Auction catalogue and publisher descriptions of the original and the reissue |
| The reissue: Standards Manual (Jesse Reed, Hamish Smyth), full size, licensed from the MTA, Kickstarter | VERIFIED | Sources disagree on 2014 (publication) vs early 2015 (delivery) |
| Line hex values A `#0039A6`, M `#FF6319`, G `#6CBE45`, L `#A7A9AC`, 7 `#B933AD` | VERIFIED | Against the MTA Line Colors page as reproduced by Metro Route Atlas (accessed 2019); an older 2012 MTA CSV lists the A/C/E blue as `#2850AD` |
| Black letters on M, G, L discs; A charts in `#4D7FE0` in the dark | ASSUMED | Our accessibility choice; the real signs use white letters on these colours |
| NYC taxi yellow is "Dupont M6284 or its equivalent" (1967) | VERIFIED | Press sources quoting the TLC; the regulatory text itself was not checked |
| `#F7B500` as the screen value of taxi yellow | ASSUMED | No official sRGB value exists; chosen by eye for both newsprint and black |
| Street signs: green with white mixed-case letters (MUTCD 2010, Clearview in NYC) | VERIFIED | Press sources; `#0B6B4A` is ASSUMED — a darker green than the sheeting so it also works as text (5.69:1) |
| Contrast figures in this file | VERIFIED | Computed with the WCAG relative-luminance formula from the hex values in `tokens.css` |
| M, G, L chart strokes under 3:1 on newsprint | VERIFIED | Mitigated by lettered end discs and data in `aria-label`; not a full pass of WCAG 1.4.11 for the strokes alone |
| Bodoni Moda, Playfair Display, Libre Bodoni under SIL OFL 1.1 | VERIFIED | Google Fonts listing |
| Source Serif 4, IBM Plex Mono, Inter under SIL OFL 1.1; Helvetica Neue commercial | ASSUMED | From the projects' published licences, not re-checked for this release |
| Look with system fonts (Didot / Bodoni 72, Helvetica Neue) | ASSUMED | Matches the intended faces on a Mac; Windows falls back to Bodoni MT or Times New Roman and Arial |
| The MTA requires a licence for its logos, maps and symbols | VERIFIED | Quoted from the MTA site by the MTA Colors project (https://www.jsvine.com/mta-colors/); hence no route signs, map or official discs here |
| Sample product, people, dishes, numbers | ASSUMED | Mulberry & Grand is fictional |
