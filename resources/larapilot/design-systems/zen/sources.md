# Zen — sources, principles and scales

Zen is an **original** system: nothing here copies a product, and it is not affiliated with, endorsed by or modelled on any
brand, shop or studio named below. This file lists the honest inspirations (ideas, designers, public references), the
principles distilled from them, and every scale with the reason behind it. Version: **1.0** (October 2026), written for
Larapilot 5.1 mockups.

## Inspirations

| Reference | URL | What Zen takes from it |
| --- | --- | --- |
| **Ma** (間), the Japanese sense of interval and empty space | https://en.wikipedia.org/wiki/Ma_(negative_space) | Empty space as the main material: wide margins, few elements, pauses between groups |
| **Kanso** (簡素), simplicity — one of the seven principles in Zen aesthetics (Hisamatsu) | https://en.wikipedia.org/wiki/Japanese_aesthetics | Remove until only the necessary remains; the sample product is named after it |
| **Wabi-sabi** (侘寂), beauty in the imperfect and the plain | https://en.wikipedia.org/wiki/Wabi-sabi · https://plato.stanford.edu/entries/japanese-aesthetics/ | Monochrome, natural materials in the photos, the ensō left open rather than closed |
| **Ensō** (円相), the circle drawn in one brush stroke in Zen calligraphy | https://en.wikipedia.org/wiki/Ens%C5%8D | The single ornament: one stroke, heavy at the start, released into a dry tail, open at one point |
| Jun'ichirō Tanizaki, *In Praise of Shadows* (1933) | https://en.wikipedia.org/wiki/In_Praise_of_Shadows | Restraint and quiet surfaces; the dark theme as a calm room, not a neon one |
| International Typographic Style (overview) | https://en.wikipedia.org/wiki/International_Typographic_Style | One sans-serif family, flush-left text, hierarchy by size and space, a grid |
| Josef Müller-Brockmann, *Grid Systems in Graphic Design* (Niggli) | https://www.niggli.ch | The 12-column grid and the strict spacing scale, used here with far fewer elements |
| Dieter Rams, ten principles for good design | https://www.vitsoe.com/gb/about/good-design | "As little design as possible", unobtrusive, honest; every element must earn its place |
| Product restraint in the manner of Muji (*mujirushi ryōhin*, "no-brand quality goods") | https://en.wikipedia.org/wiki/Muji | Unbranded calm: no decoration, the product speaks; an attitude, not a visual borrowed from the company |
| Kenya Hara, *Designing Design* / *White* (Lars Müller) | https://www.lars-mueller-publishers.com | White as emptiness that can be filled, not as absence |
| WCAG 2.2 — use of colour (1.4.1), contrast (1.4.3, 1.4.11), focus (2.4.7, 2.4.13), errors (3.3.1) | https://www.w3.org/TR/WCAG22/ | Status by shape + word, the grey ramp limits, the 2 px focus outline, the error rule + icon + text |
| WebAIM contrast checker | https://webaim.org/resources/contrastchecker/ | Cross-check of the ratios below |
| Lucide icons (ISC licence) | https://lucide.dev · https://github.com/lucide-icons/lucide/blob/main/LICENSE | Every icon, copied path for path, stroke 1.5 |
| Inter, Rasmus Andersson (SIL OFL 1.1) | https://rsms.me/inter/ · https://github.com/rsms/inter | The one sans, at 300 / 400 / 500 |
| Shippori Mincho · Noto Serif JP · Source Serif 4 (SIL OFL 1.1) | https://fonts.google.com/specimen/Shippori+Mincho · https://fonts.google.com/noto/specimen/Noto+Serif+JP · https://fonts.google.com/specimen/Source+Serif+4 | Web-font options for the display serif in a real app |
| System faces in the stacks: Helvetica Neue, Iowan Old Style, Hiragino Mincho ProN (Apple), Yu Mincho (Microsoft / Apple) | https://developer.apple.com/fonts/system-fonts/ · https://learn.microsoft.com/en-us/typography/font-list/yu-mincho | What the mockups render with on a stock machine, with nothing downloaded |

All people, pieces, orders, prices and addresses of the sample product **Kanso** are fictional. Higashiyama and Kyoto appear only
as a real district and city.

## Principles

1. **Ma first.** Empty space is the main material. When in doubt, remove: a block without a title, a second button, a
   decorative line.
2. **No colour.** Paper, ink and five greys. The greys exist for secondary text, hairlines and control edges only; no surface is
   grey, nothing is tinted.
3. **Light and large, small and exact.** Headings at 300 and 28–56 px, body at 400 and 14 px, labels at 11 px uppercase. Size
   and space make the hierarchy.
4. **Hairlines separate, the thick rule points.** 1 px grey between things; 2 px ink only for what is current, selected or
   wrong.
5. **Shape and word for status.** Filled, ring, half, cross, diamond — always followed by the word.
6. **One ornament.** The ensō, once per screen at most; the stone dot is its only echo.
7. **Dark is the same page inverted.** Black paper, white ink, mirrored greys; the same calm.

## Decisions → rationale

| Decision | Rationale / reference |
| --- | --- |
| Pure `#FFFFFF` / `#000000`, no warm or cool cast | Unambiguous black and white; a cast would be a colour, and it would make Zen another `nordic-saas/` |
| No accent colour, no red for errors | The brief is black and white only; errors keep a conventional cue through a 2 px rule, an icon and a sentence (WCAG 1.4.1, 3.3.1) |
| Five greys per theme, each with one job | ink-2 and ink-3 are text (≥ 5.33:1); ctl is the edge of a control (≥ 3:1, WCAG 1.4.11); line-2 and line are decoration only |
| Dark mode mirrors the ramp rather than reusing it | `#6B6B6B` on black is 3.94:1, too low for text; `#949494` (6.92:1) keeps the same role |
| Radius 0; circles only for dots, stones, radios, avatars and the ensō | Square is quiet; the circle stays meaningful and echoes the ensō |
| No shadows, no blur; the dialog backdrop is near-opaque paper | Elevation by edge and space, not by depth effects |
| Hover = underline or ink fill inverted | The only two motions the system needs; both work without colour |
| Focus = 2 px ink outline, 2 px offset | 21:1 against the page in both themes (WCAG 2.4.7, 2.4.13) |
| Inter at 300 / 400 / 500, one family | One voice; 300 only at 20 px and above so thin strokes stay legible |
| A serif only for rare large lines (`.zn-display`, `.zn-hero__title`, `.zn-story`, `.zn-quote`, `.zn-auth__line`) | A mincho-like serif gives the public site and the sign-in side a calligraphic note, without entering the backoffice |
| Mono only for codes and kbd; figures in the sans with tabular numerals | Mono would add a second voice to every number |
| The ensō as a filled path in two cuts (bold ≤ 64 px, regular above) | One stroke with varying width reads as a brush; a thinner cut at small sizes would break up |
| Flat grey SVG photos with fixed fills | Offline and identical everywhere; photos do not invert in dark mode, like real photographs |
| `zn-` prefix, islands `.zn-inv` / `.zn-night` | Any component works inside an inverted or always-black block without extra CSS |

## Type scale

| Token / class | Size / line / tracking | Weight | Use |
| --- | --- | --- | --- |
| `.zn-display`, `.zn-hero__title` | clamp(44, 5.6vw, 80) / 1.08 / −0.01em, serif | 400 | Public hero lines |
| `.zn-quote__text` | clamp(28, 3.2vw, 44) / 1.3, serif | 400 | A single quote |
| `.zn-h1` | clamp(40, 4vw, 56) / 1.08 / −0.02em | 300 | Page titles |
| `.zn-stat__value`, `.zn-sechead__title`, `.zn-auth__title` | 40 / 1–1.15 / −0.02 to −0.03em | 300 | Figures, public section titles, sign-in title |
| `.zn-h2`, `.zn-dialog__title`, `.zn-empty__title` | 28 / 1.2–1.25 / −0.015em | 300 | Section and dialog titles |
| `.zn-h3`, `.zn-card__title`, `.zn-formsec__title` | 20 / 1.3 / −0.01em | 300 | Card and form-section titles |
| `.zn-h4`, `.zn-lede`, `.zn-prose` | 16 / 1.4–1.75 | 400 | Sub-titles, intros, reading text on the site |
| body | 14 / 1.6 | 400 | Backoffice text, tables |
| `.zn-flabel`, `.zn-help`, meta | 13 / 12 | 500 / 400 | Form labels, helper text, sub-lines |
| `.zn-label`, table heads, `.zn-stat__label` | 11 / 1.4 / +0.14em, uppercase | 500 | Structure only |

Stacks: `"Inter", "Helvetica Neue", Helvetica, Arial, system-ui, sans-serif`; display
`"Iowan Old Style", "Hiragino Mincho ProN", "Yu Mincho", "YuMincho", Georgia, serif`; mono
`ui-monospace, "SF Mono", SFMono-Regular, Menlo, Consolas, monospace`. On a stock Mac the mockups render in Helvetica Neue,
Iowan Old Style and SF Mono.

## Colour scale

| Token | Light | Dark | Role |
| --- | --- | --- | --- |
| `--zn-bg` | `#FFFFFF` | `#000000` | Page, controls, menus, dialogs — the only surface |
| `--zn-ink` | `#000000` | `#FFFFFF` | Text, rules, solid fills, focus |
| `--zn-ink-2` | `#4D4D4D` | `#B3B3B3` | Secondary text |
| `--zn-ink-3` | `#6B6B6B` | `#949494` | Muted text, labels, helper text, placeholders |
| `--zn-ctl` | `#8C8C8C` | `#767676` | Edges of inputs, selects, drop zones |
| `--zn-line-2` | `#BDBDBD` | `#4A4A4A` | Tag and chip edges, dashed and disabled lines |
| `--zn-line` | `#E6E6E6` | `#262626` | Hairlines |
| `--zn-backdrop` | white at 92% | black at 90% | Dialog backdrop |
| Photo fills (fixed) | `.zn-ph-0` `#F4F4F4` · `-1` `#DCDCDC` · `-2` `#B8B8B8` · `-3` `#8E8E8E` · `-4` `#5E5E5E` · `-5` `#2E2E2E` · ground `#EDEDED` | same | Placeholders only |

## Spacing, shape and shell

| Scale | Values | Rationale |
| --- | --- | --- |
| Space | 4 · 8 · 16 · 24 · 32 · 40 · 48 · 64 · 80 · 96 · 128 px (`--zn-s05…s16`) | A strict 8 px scale, 4 px the only half step; big jumps between groups |
| Page padding | 64 px desktop · 40 px 768–1199 · 20 px phone | Wide margins are the first visible sign of ma |
| Measure | 68ch reading text, 52ch lede, 44ch empty-state text | Short lines read calmly |
| Controls | 40 px default · 32 px small · 48 px large | Touch-safe default, quiet toolbars |
| Shell | rail 240 px (72 px collapsed), topbar 72 px, content max 1200 px; public header 96 px | Room above the title, never cramped |
| Grid | 12 columns, 32 px column gap, 80 px row gap | Rows are separated by space, not by lines |
| Radius / shadow | 0 / none | — |
| Motion | 0.18 s, `cubic-bezier(.2, 0, 0, 1)`, colour and a 3 px nudge only | Off with `prefers-reduced-motion` |

## Contrast (measured, WCAG relative luminance)

| Foreground | Background | Light | Dark |
| --- | --- | --- | --- |
| Ink | Page | `#000000` on `#FFFFFF` **21.00:1** | `#FFFFFF` on `#000000` **21.00:1** |
| Ink-2 | Page | `#4D4D4D` **8.45:1** | `#B3B3B3` **10.02:1** |
| Ink-3 (muted, labels) | Page | `#6B6B6B` **5.33:1** | `#949494` **6.92:1** |
| Control edge (non-text) | Page | `#8C8C8C` **3.36:1** | `#767676` **4.62:1** |
| Line-2 (decoration) | Page | `#BDBDBD` 1.88:1 — never text | `#4A4A4A` 2.37:1 — never text |
| Line (hairline) | Page | `#E6E6E6` 1.25:1 — never text | `#262626` 1.39:1 — never text |
| Text on a solid button / tag / count | Ink fill | paper on black **21.00:1** | black on white **21.00:1** |
| Inverted islands (toast, tooltip, bulk bar) | Other theme | same ratios as the other column | same ratios as the other column |
| Ink on a photo ground | `#EDEDED` | **17.94:1** | (photos keep their fills) |
| Focus outline | Page | ink **21.00:1** | white **21.00:1** |

## Icon set

**Lucide** (ISC licence, © Lucide Contributors; parts derived from Feather, MIT) — https://lucide.dev, licence:
https://github.com/lucide-icons/lucide/blob/main/LICENSE. Icons are inlined path for path with `stroke="currentColor"`
through `.zn-i`: stroke 1.5, 1.25 in the nav and at 32 px, 1 at 48 px, round caps and joins, always `aria-hidden="true"`.
The backoffice shell uses sun (Today), package (Orders), calendar (Workshops), coffee (Pieces), users (Customers), book-open
(Journal), settings-2 (Settings), log-out, menu, search, plus, arrow-right, arrow-up-right; the snippets in `components.md`
name the others.

## Verified vs assumed

**Verified** (measured or checked in this repository, October 2026):

- Every contrast ratio above, computed with the WCAG 2.x relative-luminance formula from the hex values in `tokens.css`; they
  match its header table.
- Every colour in `tokens.css` is a neutral grey (R = G = B), black or white, plus two neutral backdrops — no hue anywhere.
- Every class and token named in `README.md` and `components.md` exists in `tokens.css`.
- The screens load nothing remote and run no script (the package test suite enforces it for every system).

**From published documentation** (cited, not re-fetched for this release):

- The WCAG 2.2 success criteria named here (1.4.1, 1.4.3, 1.4.11, 2.4.7, 2.4.13, 3.3.1).
- Lucide's ISC licence and Inter's SIL OFL 1.1 licence; the OFL licences of the suggested serif web fonts.
- Tailwind CSS v4 `@theme` variables, including `--color-*: initial` to clear a default namespace.
- Which system fonts ship where (Iowan Old Style and Hiragino Mincho ProN on Apple systems, Yu Mincho on Windows and macOS);
  this may change by OS version, and Georgia is the fallback.

**Assumed** (reasonable, not checked against a primary source):

- The short glosses of ma, kanso, wabi-sabi and the ensō summarise general references (encyclopaedias, Hisamatsu's seven
  characteristics as commonly cited); they are design cues, not scholarship.
- The ensō paths are drawn for this system and only suggest a brush stroke; no specific calligrapher's work was traced.
- Muji and Dieter Rams are cited for an attitude of restraint only; no visual, logo, layout or product of theirs is reproduced,
  and Zen is not affiliated with either.
- The grey ramp's suitability for print and e-ink is inferred from its contrast, not tested on devices.
