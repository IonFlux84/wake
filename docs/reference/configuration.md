---
title: Configuration
description: Every setting you can put in site/config.php, with its default.
---

# Configuration

Settings live in `site/config.php`, which returns a PHP array. Only write down
what you want to change, everything else keeps its default.

```php
<?php

return [
    'languages' => ['de' => 'Deutsch', 'en' => 'English'],
    'timezone' => 'Europe/Berlin',
    'post_url' => '{year}/{slug}',
];
```

Nested settings are written as nested arrays:

```php
'page_cache' => ['ttl' => 600],
```

Secrets such as the application key and mail passwords belong in `.env`, not
here, so `site/` can be committed to Git safely.

## Site

| Setting | Default | Description |
|---|---|---|
| `languages` | `['en' => 'English']` | Languages of the site, code → name. The first one is the default language. See [content format](content-format.md#languages). |
| `prefix_default_language` | `false` | `true` puts the default language in the URL too (`/en/about` instead of `/about`). Requests without the prefix are redirected. |
| `missing_translation` | `'hide'` | What happens when an entry has no translation in the requested language. `'hide'` answers with 404. `'fallback'` shows the default language with a notice and `noindex`, under the default language's slug. |
| `timezone` | `'UTC'` | Time zone for dates in your content and for scheduled publishing, e.g. `'Europe/Berlin'`. Also settable as `WAKE_TIMEZONE` in `.env`. |
| `theme` | `'tide'` | Folder in `site/themes/` that renders the website. |

## Addresses

| Setting | Default | Description |
|---|---|---|
| `post_url` | `'{slug}'` | URL pattern for posts. Placeholders: `{slug}`, `{year}`, `{month}`, `{day}`. Example: `'{year}/{slug}'` gives `/2026/hello-world`. |
| `tag_url` | `'tag'` | Segment in front of tag pages: `/tag/travel`. |
| `per_page` | `10` | Posts per page in lists (`$site->posts()->paginate()`). |

Pages always follow their folder structure (`pages/1_about/2_team` → `/about/team`),
and custom content types use the `url` of their [blueprint](content-format.md#custom-content-types).

## Images

| Setting | Default | Description |
|---|---|---|
| `images.widths` | `[320, 640, 960, 1280, 1920]` | Widths Wake may create. Other widths are rounded up to the next one, so nobody can make your server create thousands of sizes. |
| `images.quality` | `82` | JPEG, WebP and AVIF quality from 1 to 100. |

## Feed

| Setting | Default | Description |
|---|---|---|
| `feed.limit` | `20` | Number of posts in the RSS feed at `/feed.xml` (and `/de/feed.xml` per language). |

## Performance

| Setting | Default | Description |
|---|---|---|
| `index.check` | `'always'` | `'always'` compares file times on every uncached request and updates the [index](../concepts/architecture.md#the-index) when files changed via FTP or Git. `'never'` skips the check; then run `php wake/artisan wake:index` after changing files by hand. |
| `page_cache.enabled` | `true` | Serve finished pages from `storage/cache/pages` without starting Laravel. Also settable as `WAKE_PAGE_CACHE` in `.env`. |
| `page_cache.ttl` | `300` | Seconds a cached page is served before Wake looks again. Changes made via FTP and posts that become due show up after at most this long. |

## Maintenance

Wake keeps itself tidy without cron. See
[architecture](../concepts/architecture.md#maintenance-without-cron).

| Setting | Default | Description |
|---|---|---|
| `maintenance.mode` | `'requests'` | `'requests'` runs maintenance after normal page views. `'cron'` leaves it to a cron job calling `php wake/artisan schedule:run` every minute. |
| `maintenance.interval` | `60` | Run at most once per this many seconds. |
| `maintenance.budget` | `5` | Time budget per run in seconds. Unfinished work continues on the next run. |

## Paths

You rarely need these. They exist for tests and unusual hosting setups.

| Setting | Default | Description |
|---|---|---|
| `paths.site` | `site/` next to `wake/` | Folder with configuration, blueprints and themes. Environment variable `WAKE_SITE_PATH`. |
| `paths.content` | `content/` next to `wake/` | Folder with all content. Environment variable `WAKE_CONTENT_PATH`. |
