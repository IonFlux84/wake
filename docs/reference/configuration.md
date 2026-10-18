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

## Settings changed in the panel

Owners and administrators can change some settings under *Settings* in the
panel. Wake stores them in `site/settings.yml`, which overrides
`site/config.php`, so the panel always shows what is in effect. These are
`timezone`, `post_url`, `missing_translation`, `prefix_default_language`, the
`tracking.*` settings, `mcp.approval`, `panel.accent` and `panel.language`. Everything else is
only set in `site/config.php`.

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
| `theme_options` | `[]` | Options for the active theme. Tide knows `accent`, e.g. `['accent' => '#3D5AFE']`. See [Tide](tide.md). |

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

## Statistics

How Wake counts visits is explained in [analytics](../concepts/analytics.md).

| Setting | Default | Description |
|---|---|---|
| `tracking.enabled` | `true` | Count visits with Wake's own statistics. Also settable as `WAKE_TRACKING` in `.env`. |
| `tracking.level1` | `'always'` | Level 1 counts without cookies. `'always'` counts every visitor, `'consent'` only those who agreed to statistics in the banner. Level 2 (with a cookie) always needs consent. |
| `tracking.respect_privacy_signals` | `false` | `true` ignores visitors whose browser sends Do Not Track or Global Privacy Control. |
| `tracking.retention` | `30` | Days to keep raw data. Daily and monthly sums are kept. |
| `tracking.geo` | `storage/stats/geo/country.mmdb` | Optional IP-to-country database in MaxMind format, e.g. [DB-IP Lite](https://db-ip.com/db/lite.php) (free, CC BY 4.0). Without it, countries stay empty. |

## Assistants (MCP)

| Setting | Default | Description |
|---|---|---|
| `mcp.approval` | `false` | `true` lets assistants only ask for publishing; a person approves in the panel. See [MCP server](mcp.md#approval-mode). |

## Updates

| Setting | Default | Description |
|---|---|---|
| `update.repository` | `'IonFlux84/wake'` | GitHub repository whose releases the updater installs. See [Updating](../getting-started/updating.md). |

## Panel

| Setting | Default | Description |
|---|---|---|
| `panel.language` | `'de'` | Language of the panel: `'de'` or `'en'`. Everyone can choose their own language under *Settings → Your account*; this is the language for everyone else. The language chosen during setup is stored here. |
| `panel.accent` | `'#C6FF3D'` | The one neon colour of the panel. Text on it turns black or white by itself, so any colour stays readable. The design suggests lime, blue `#3D5AFE`, pink `#FF2D87` and orange `#FF6A1A`. |

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
