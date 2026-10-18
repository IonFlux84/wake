---
title: Configuration
description: Every setting you can put in settings/config.php, with its default.
---

# Configuration

Settings live in `settings/config.php`, which returns a PHP array. Only write down
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
panel. Wake stores them in `settings/general.yml`, which overrides
`settings/config.php`, so the panel always shows what is in effect. These are
`timezone`, `post_url`, `missing_translation`, `prefix_default_language`, the
`tracking.*` settings, `mcp.approval`, `panel.accent`, `panel.language`, `closed`, `security.require_two_factor`, `theme`, `theme_options`, `plugins.disabled` and `plugins.options`. Everything else is
only set in `settings/config.php`.

Secrets such as the application key and mail passwords belong in `.env`, not
here, so `settings/` can be committed to Git safely. Mail settings made in the
panel are stored encrypted in `data/secrets/mail.yml`.

## Site

| Setting | Default | Description |
|---|---|---|
| `closed` | `false` | Closed site: only signed-in team members see the website; everyone else is sent to the sign-in page. `robots.txt` then disallows everything, pages carry `noindex`, nothing is cached or counted, and the API needs a token. Switch it in the panel under *Settings*. |
| `security.require_two_factor` | `false` | Everyone must set up [two-factor sign-in](../guides/two-factor.md) before they can use the panel; people without it are sent to the setup after signing in. Switch it in the panel under *Settings*; you need it set up for your own account first. |
| `languages` | `['en' => 'English']` | Languages of the site, code → name. The first one is the default language. See [content format](content-format.md#languages). |
| `prefix_default_language` | `false` | `true` puts the default language in the URL too (`/en/about` instead of `/about`). Requests without the prefix are redirected. |
| `missing_translation` | `'hide'` | What happens when an entry has no translation in the requested language. `'hide'` answers with 404. `'fallback'` shows the default language with a notice and `noindex`, under the default language's slug. |
| `timezone` | `'UTC'` | Time zone for dates in your content and for scheduled publishing, e.g. `'Europe/Berlin'`. Also settable as `WAKE_TIMEZONE` in `.env`. |
| `theme` | `'tide'` | Folder in `themes/` that renders the website. |
| `theme_options` | `[]` | Options of the active theme as declared in its `theme.yml`, usually set in the panel under *Themes*. See [Tide](tide.md). |
| `plugins.disabled` | `[]` | Folders in `plugins/` that are switched off. Usually changed in the panel under *Plugins*. See [plugins](plugins.md). |
| `plugins.options` | `[]` | Settings of each plugin as declared in its `plugin.yml`, folder → option → value. Usually changed in the panel under *Plugins*. |

## Addresses

| Setting | Default | Description |
|---|---|---|
| `post_url` | `'{slug}'` | URL pattern for posts. Placeholders: `{slug}`, `{year}`, `{month}`, `{day}`. Example: `'{year}/{slug}'` gives `/2026/hello-world`. |
| `tag_url` | `'tag'` | Segment in front of tag pages: `/tag/travel`. |
| `per_page` | `10` | Posts per page in lists (`$site->posts()->paginate()`). |

Pages always follow their folder structure (`pages/about/team` → `/about/team`),
and custom content types use the `url` of their [`type.yml`](content-format.md#custom-content-types).

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
| `page_cache.enabled` | `true` | Serve finished pages from `temp/cache/pages` without starting Laravel. Also settable as `WAKE_PAGE_CACHE` in `.env`. |
| `page_cache.ttl` | `300` | Seconds a cached page is served before Wake looks again. Changes made via FTP and posts that become due show up after at most this long. |

## Statistics

How Wake counts visits is explained in [analytics](../concepts/analytics.md).

| Setting | Default | Description |
|---|---|---|
| `tracking.enabled` | `true` | Count visits with Wake's own statistics. Also settable as `WAKE_TRACKING` in `.env`. |
| `tracking.level1` | `'consent'` | Level 1 counts without cookies. `'consent'` (default) counts only those who agreed to statistics in the banner, `'always'` counts every visitor without asking (as Plausible does; check the legal situation first, see [analytics](../concepts/analytics.md)). Level 2 (with a cookie) always needs consent. |
| `tracking.respect_privacy_signals` | `false` | `true` ignores visitors whose browser sends Do Not Track or Global Privacy Control. |
| `tracking.retention` | `30` | Days to keep raw data. Daily and monthly sums are kept. |
| `tracking.geo` | `data/stats/geo/country.mmdb` | Optional IP-to-country database in MaxMind format, e.g. [DB-IP Lite](https://db-ip.com/db/lite.php) (free, CC BY 4.0). Without it, countries stay empty. |

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
| `panel.accent` | `'#0A0A0A'` | Highlight colour of the panel. The panel is greyscale; this colour only marks small live signals: the dot for published entries and the main line in the statistics. Black keeps everything grey. Set it under *Settings*; blue `#3D5AFE`, pink `#FF2D87` and orange `#FF6A1A` are offered. The old default `#C6FF3D` counts as black. |

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
| `paths.content` | `content/` next to `wake/` | Pages, posts, categories, tags and media. Environment variable `WAKE_CONTENT_PATH`. |
| `paths.settings` | `settings/` next to `wake/` | Settings written by the panel and by people: `general.yml`, `config.php`, menus, redirects, roles, consent. Environment variable `WAKE_SETTINGS_PATH`. |
| `paths.themes` | `themes/` next to `wake/` | Installed themes. Environment variable `WAKE_THEMES_PATH`. |
| `paths.plugins` | `plugins/` next to `wake/` | Installed plugins. Environment variable `WAKE_PLUGINS_PATH`. |
| `paths.data` | `data/` next to `wake/` | Data of the site: accounts, statistics, versions, trash, backups. Back it up, never delete it. Environment variable `WAKE_DATA_PATH`. Caches and logs live in `temp/`, which may be deleted at any time. |
