---
title: "0011: One folder per entry, one folder per responsibility"
description: Why every page and post is a folder with content.md, media live in one library, and the site is split into content, settings, themes, plugins, data and temp.
---

# 0011: One folder per entry, one folder per responsibility

- **Status:** accepted
- **Date:** 2026-10-18
- **Refines:** [0004](0004-markdown-with-yaml.md), [0008](0008-application-in-its-own-folder.md)

## Context

Until 0.1, folder names carried meaning: `1_about` put a page into the menu at
position one, `2026-10-18-lantern-walk` gave a post its date, and tags were
single files while posts were folders. Images sat next to the text of the
entry that uploaded them. `site/` mixed configuration, themes and plugins, and
`storage/` mixed things to back up (accounts, versions) with things that may
be deleted (caches).

People who open the folders by FTP found this hard to read: reordering pages
renamed folders, publishing a post could change its folder, and an image used
on three pages lived inside one of them.

## Decision

**Every entry is a folder.** Pages, posts, categories, tags and custom types,
without exception. Folder names are lowercase words joined by hyphens, with no
numbers and no dates. The folder name is the address.

```
content/
├─ website.md              site title and description (website.de.md …)
├─ pages/
│  └─ about/
│     ├─ content.md        front matter and text in the default language
│     ├─ content.de.md     other languages
│     ├─ style.css         optional design for this page only
│     └─ team/content.md   a sub-page: /about/team
├─ posts/lantern-walk/content.md
├─ categories/events/content.md
├─ tags/travel/content.md
├─ events/type.yml          a custom type: extra fields, address pattern
└─ media/                   all images and documents, folders allowed
   ├─ posts/lantern-walk/procession.jpg
   └─ posts/lantern-walk/procession.jpg.yml   alt text, caption, credit, focus
```

- **Order and menu live in the file:** `order: 2` sorts the page tree,
  `menu: true` puts a page into the automatic main menu. A hand-made menu stays
  in `settings/menus.yml`. Moving a page in the panel writes `order:`; only a
  new parent moves the folder.
- **Status and date live in the file** (`status:`, `published:`). Publishing or
  reordering never renames anything.
- **Media are one library** in `content/media/`. Content refers to files as
  `media/<path>`; a plain file name in an entry means its own media folder,
  `media/<entry id>/`, which is where the editor uploads. The library knows
  which entries use a file and warns before deleting a used one.
- **One folder per responsibility** next to `wake/`:

  | Folder | Holds | Who | Back up? |
  | --- | --- | --- | --- |
  | `content/` | texts, pages, posts, media | editors | yes, in Git |
  | `themes/` | looks | designers | yes, in Git |
  | `plugins/` | extensions | developers | yes, in Git |
  | `settings/` | `general.yml`, `config.php`, menus, redirects, roles, consent | owners and the panel | yes, in Git |
  | `data/` | accounts, statistics, versions, trash, backups, secrets | Wake | yes, not in Git |
  | `temp/` | caches, logs, sessions (Laravel's storage) | Wake | no, may be deleted |

`php wake/artisan wake:layout --apply` moves a site from the old layout.

## Consequences

- A folder listing reads like the site map; renaming a folder is the only way
  to change an address, and the panel writes a redirect when it does that.
- The page tree needs a small write into the file instead of a rename, so Git
  diffs of a reorder show `order:` lines.
- Images are no longer hidden with a draft. Their addresses carry a hash, so
  they stay hard to guess.
- Two uploads with the same name in one folder get `-2`; files overwritten by
  FTP are noticed by their modification time.
- Wake reads only the new layout. Sites from 0.1 run `wake:layout` once.

## Alternatives considered

- **Keep prefixes, document them better.** Cheapest, but the confusion came
  from the rule itself, not from missing docs.
- **Flat files (`about.md`) with optional folders.** Fewer folders, but a page
  that gets a sub-page or a `style.css` would have to move, and two shapes for
  one thing are harder to explain than one.
- **Media next to their entry, as before.** Simple for one page, but shared
  images had no obvious home and deleting a page took its images along.
