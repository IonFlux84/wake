---
title: "0010: Panel and website share the Hairline design"
description: Why the panel moved from "Neon clean" to the greyscale Hairline design of getwake.dev.
---

# 0010: Panel and website share the Hairline design

- **Status:** accepted
- **Date:** 2026-10-18

## Context

The panel was designed first, as "Neon clean": white and black with one neon
accent (lime by default) and glow effects, Inter for the interface and
Newsreader for titles. The website getwake.dev was designed later, as
"Hairline": greyscale, 1px hairlines, Geist and Geist Mono, the Kelvin mark.
The owner approved Hairline and asked for the panel and the sign-in pages to
look the same.

## Decision

Panel, sign-in pages and website use one design system, Hairline:

- Greyscale only. The one highlight is inversion (black on light, white on
  dark surfaces). Red is used for errors and destructive actions only.
- Geist for the interface and titles, Geist Mono for labels, numbers, paths
  and code. Newsreader stays on the editor's writing surface, where an
  editorial typeface helps writing.
- 1px hairlines, no shadows or glow, radii of 2px (controls), 4px and 6px
  (surfaces). Charts sit in dark "windows" with a mono title bar.
- The Kelvin mark (a hull and its wake at 19.47°) is the logo everywhere.
- `panel.accent` defaults to black. A chosen colour only marks small live
  signals (published dot, main chart line), never buttons or surfaces.

## Consequences

- One look from the website to the sign-in page to the panel.
- Fewer fonts (Inter and JetBrains Mono are gone).
- Sites that chose a neon accent keep it, but only on small marks.
- The Tide theme keeps its own accent option; themes are free to look
  different.

## Alternatives considered

- **Keep Neon clean in the panel:** two visual languages for one product.
- **Newsreader for all titles:** two typefaces competing in the interface;
  the editorial feel matters most while writing.
