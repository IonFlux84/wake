---
title: Editing
description: What Wake does when content is saved, published, moved or deleted.
---

# Editing

All changes, from the panel, the MCP server or an importer, go through one
class, `Wake\Content\Writer`. That keeps the rules the same everywhere.

## Saving

- Files are written atomically and under a lock per entry
  ([architecture](architecture.md#writing-files-safely)).
- Front matter is written the way a person would: no quotes where YAML does not
  need them, short lists inline (`tags: [news, travel]`).
- The editor writes one line per paragraph. Line breaks inside a paragraph mean
  nothing in Markdown, so this does not change the result.
- New entries get a folder named after the title (`my-post`) with a
  `content.md` inside. An existing folder is never overwritten; `-2`, `-3` …
  is added instead.
- The design of an entry (*Design* in the editor) is saved as `style.css` in
  its folder, cleaned as described in the
  [content format](../reference/content-format.md#design-of-a-single-page-stylecss).

## Revisions

Before a file is changed, its previous content is stored in
`data/versions/{hash of the path}/`. Wake keeps the last 20 versions of each
file, and every version younger than 30 days.

## Redirects

When the address of a published entry changes (a new slug, or a page moved to
another parent page), Wake writes the old address to
`settings/redirects.yml` and answers it with a permanent redirect (301). You can add and
remove redirects in the panel under *Redirects* (people with `settings.manage`)
or by hand. A target is one of five things:

```yaml
de:
  alte-seite: pages/about                 # an entry, by its id
  archiv/2019: /stiepel-frueher           # a path of this site, e.g. a category
  flyer.pdf: media/downloads/flyer.pdf    # a file of the media library
en:
  old/page: https://example.org/elsewhere # another website
  old/offer: gone                         # removed on purpose: 410 Gone
```

A redirect only answers when nothing else lives at the address: an entry, a
tag or a category page at the same path always wins. Files of the media
library get a temporary redirect (302), because their address carries the
file's version and changes with it. `gone` shows the theme's `410` template,
or its `404` page with status 410, so search engines forget the address
sooner.

To find the old addresses of a website you move to Wake, use
[Keep old addresses working](../guides/old-addresses.md).

## Trash

Deleting moves the whole entry folder, with all languages and its `style.css`, to
`data/trash/{date}-{id}/`. Nothing is deleted for good. To restore an entry,
move its folder back into `content/`. Its media stay in the media library,
where the panel lists them as unused.

## Editing at the same time

While someone has an entry open, the editor writes a short-lived note to
`temp/locks/`. Anyone else opening the entry sees who is editing it. Wake
does not block the second person, but warns that changes may overwrite each
other.
