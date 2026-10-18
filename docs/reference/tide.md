---
title: Tide
description: The default theme of Wake, what it shows and how to adjust it.
---

# Tide

Tide is the theme Wake ships with. It is calm and readable, works in light and
dark mode, loads no external fonts or scripts, and is fully translated into
English and German.

## What it shows

| Address | Content |
|---|---|
| `/` | the start page (`pages/home`) with a large card for the featured post (the newest with `featured: true`, else the newest), then all posts in a grid with pagination, and the tags |
| a post | tags, title, excerpt, date and reading time, cover, text, older and newer post, up to three related posts with the same first tag, and a reading progress line |
| a page | title, cover, text and cards for its child pages |
| `/tag/{slug}` | the tag's title and text, and its posts |
| an event | date and time, place with map link, admission, organizer, registration button, *Add to calendar* and more events |
| a category page | posts of the category; for events upcoming ones by month and past ones folded away |
| `/search` | a search form and results ([search](templates.md#search)) |
| 404 | a friendly message with a search form |

The header shows the listed top-level pages (`$site->menu()`), a search link and
the language switcher; on small screens they fold into a menu button. The footer
repeats the menu and adds the RSS feed and the cookie settings link.

## Options

Under *Themes* in the panel (or `theme_options` in `site/config.php`):

| Option | Default | Effect |
|---|---|---|
| `accent` | `#C6FF3D` | links, focus ring, buttons, reading progress |
| `show_next_events` | yes | the next events on the start page |
| `show_tags` | yes | the tags on the start page |
| `footer_text` | empty | an extra line in the footer |

## Changing Tide

Click *Duplicate* under *Themes* in the panel (or copy `site/themes/tide` by
hand), change the copy and activate it. Updates of Wake never touch `site/`. See
[templates](templates.md) for everything a theme can use.

Archivo (SIL Open Font License) is bundled in `assets/fonts/`.
