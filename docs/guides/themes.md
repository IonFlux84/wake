---
title: Choose and adjust a theme
description: Switch themes, change their settings, install new ones and make your own copy.
---

# Choose and adjust a theme

Open *Themes* in the panel (owners, administrators, and anyone with
`themes.manage`).

- **Activate** another theme. Your content stays as it is; only the look
  changes. Wake clears the page cache, so visitors see the new look right away.
- **Settings** of the active theme appear below the overview, for Tide the
  accent colour, the next events and the tags on the start page, and a footer
  line. What a theme offers is up to the theme.
- **Duplicate** makes a copy in `site/themes/`. Change the copy's templates and
  styles as you like and activate it; the original stays untouched, and
  updates of Wake never change your copy.
- **Install a theme (ZIP)**: upload a ZIP with a theme folder. It needs a
  `templates/` folder with Blade templates; `theme.yml` and `screenshot.jpg`
  are optional. A new theme is not activated automatically.
- **Remove** moves an inactive theme to `storage/trash/`.

> Themes contain PHP (Blade) and run with full rights. Only install themes
> from people you trust.

How to build a theme is described in [Templates](../reference/templates.md).
