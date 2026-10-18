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
- New posts get a folder named after the date and the title
  (`2026-10-18-my-post`). An existing folder is never overwritten; a number is
  added instead.

## Revisions

Before a file is changed, its previous content is stored in
`storage/revisions/{hash of the path}/`. Wake keeps the last 20 versions of each
file, and every version younger than 30 days.

## Redirects

When the address of a published entry changes, Wake writes the old address to
`site/redirects.yml` and answers it with a permanent redirect (301). You can add
redirects there by hand, also to other sites:

```yaml
de:
  alte-seite: pages/1_about
en:
  old/page: https://example.org/elsewhere
```

## Trash

Deleting moves the whole entry, with all languages and files, to
`storage/trash/{date}-{id}/`. Nothing is deleted for good. To restore an entry,
move its folder back into `content/`.

## Editing at the same time

While someone has an entry open, the editor writes a short-lived note to
`storage/locks/`. Anyone else opening the entry sees who is editing it. Wake
does not block the second person, but warns that changes may overwrite each
other.
