---
title: Architecture
description: How Wake is put together, what happens during a request, and how it stays fast without a database.
---

# Architecture

This page explains how Wake works inside. You don't need it to run a site, but
it helps when something surprises you, and it is the starting point for
contributors.

## The folders

```
wake/       Laravel application (namespace Wake\), replaced on update
site/       config.php, blueprints/, themes/
content/    Markdown files
storage/    runtime data, never public
public/     document root: index.php, media/, assets/
.env        application key, mail credentials
```

`wake/bootstrap/app.php` tells Laravel that its public folder, storage folder
and `.env` sit next to `wake/`, not inside it. That is why an update can replace
`wake/` without touching anything that belongs to the site
([decision 0008](../decisions/0008-application-in-its-own-folder.md)).

## A request

```
browser ─► public/index.php
             │
             ├─ cached page in storage/cache/pages?  ──► send it, done (Laravel never starts)
             │
             ▼
           Laravel ─► SiteController
             │          ├─ language from the first path segment
             │          ├─ Index: address → entry
             │          ├─ theme template → HTML
             │          └─ CachePages middleware stores the HTML
             ▼
           response sent ─► maintenance (at most once a minute, max. 5 s)
```

### The page cache

`public/index.php` looks for `storage/cache/pages/{path}/index.html` before it
loads anything else. If the file exists and is younger than `page_cache.ttl`
seconds, it is sent straight away. Such a request costs about as much as
serving a static file.

Pages are cached per scheme and host (`storage/cache/pages/https_example.org/…`),
so the `http://` or `www.` version of a site never hands out links of another.
At most ten hosts are kept, so forged `Host` headers cannot fill the disk.

Pages are only cached when they are the same for everyone: `GET` requests
without a query string, without the panel's session cookie, with status 200.
The website routes don't start a session or set cookies at all.

The whole cache is thrown away whenever the index notices a change. Changes
made via FTP are noticed on the first uncached request, which is why cached
pages expire after `page_cache.ttl` seconds.

### Languages

The first path segment selects the language when it is a language code of the
site (`/de/ueber-uns`). Without one, the default language applies, unless
`prefix_default_language` is on. Every entry has its own address per language,
built from its translated `slug`.

## The index

A database answers "the five newest posts tagged *travel*" without reading
every post. Wake gets the same from its **index**: one PHP file,
`storage/cache/index.php`, that returns an array with every entry, every
language and every front matter field, but no body text.

```php
'posts/2026-10-17-hello-world' => [
    'type' => 'post',
    'name' => 'hello-world',
    'date' => '2026-10-17',
    'languages' => [
        'en' => ['file' => 'posts/…/index.en.md', 'mtime' => 1792220000, 'fields' => [...]],
        'de' => [...],
    ],
    'media' => ['cover.jpg' => 1792220000],
],
```

Because it is a PHP file, OPcache keeps it compiled in memory. Loading it takes
microseconds, and queries are plain array operations. The body of an entry is
read from its file only when a template calls `content()`.

The index also holds an **address table** per language (`'about/team' =>
'pages/1_about/1_team'`), so finding the entry for a URL is a single array
lookup.

### Noticing changes

On every uncached request (with `index.check` set to `'always'`), Wake lists
all files below `content/` with their modification times and compares the list
with the one stored in the index. Only new or changed files are parsed again.
For a site with a few thousand files this takes a few milliseconds, and thanks
to the page cache it rarely happens.

When the index changes, Wake fires the `ContentChanged` event, which clears the
page cache.

## Writing files safely

Wake never writes a file in place. `Wake\Support\Files::write()` writes to a
temporary file in the same folder and then renames it over the original, which
is atomic on every file system: readers see the old or the new version, never a
mix. Concurrent changes to the same entry are serialised with `flock()` through
`Files::lock()`.

## Maintenance without cron

Many cheap hosts offer no cron. Wake therefore uses Laravel's `terminating`
hook, which runs after the response has been sent to the visitor:

1. At most once per `maintenance.interval` seconds (a timestamp file in
   `storage/locks/`), and never twice at the same time (a non-blocking lock).
2. Each task gets a deadline (`maintenance.budget`) and stops early when it is
   reached. The rest happens on the next run.

Tasks today: publishing scheduled entries. Statistics and clean-up tasks follow
in phase 2. A scheduled entry is visible as soon as its date has passed, even
before maintenance has run; maintenance only writes `status: published` into
its file and clears the cache.

With `maintenance.mode` set to `'cron'`, the same tasks run from Laravel's
scheduler instead.

## Media

Content folders are not public, so media files get addresses below `/media`:

```
/media/{entry hash}/{version}/{width or "o"}/{file name}
/media/f5e780b058/tn3hns/960/cover.jpg
/media/f5e780b058/tn3hns/960/cover.jpg.webp
```

The first request reaches Laravel, which copies or resizes the file into
`public/media/` under exactly that path. From then on the web server serves it
directly. The version is the file's modification time, so a replaced image gets
a new address, and only widths from `images.widths` are allowed.

## Markdown

Markdown is rendered with [league/commonmark](https://commonmark.thephpleague.com)
plus GitHub's extensions. Wake adds the card syntax (`::: name … :::`) as a
block extension and rewrites relative file names in links and images to media
addresses.

## Where to find things in the code

| Folder in `wake/app/` | Contents |
|---|---|
| `Content/` | index, entries, queries, front matter, content types |
| `Content/Models/` | `Post`, `Page`, `Tag` |
| `Routing/` | address → entry |
| `Http/` | controllers and the page cache middleware |
| `Cache/` | page cache |
| `Media/` | media files and image resizing |
| `Markdown/` | Markdown rendering and cards |
| `Maintenance/` | maintenance runner and tasks |
| `Theme/` | theme templates and assets |
| `Localization/` | languages |
| `Support/` | atomic file writes, paths |
