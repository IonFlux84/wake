# Changelog

All notable changes to Wake are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and Wake uses
[Semantic Versioning](https://semver.org) from 1.0 on.

## [Unreleased]

### Added

- Project foundation: Laravel 13 without a database, with `site/`, `content/`,
  `storage/` and `public/` next to the application folder `wake/`.
- Documentation structure, contribution guide and architecture decision records.
  Tests fail when a setting, command or decision record is undocumented, or a
  link in the docs is broken.
- Content as Markdown files with YAML front matter: posts, pages (nested, with
  number prefixes for the menu), tags and custom content types from blueprints.
- Content index in `storage/cache/index.php` that notices changes made via FTP
  or Git and only re-reads changed files.
- Queries in the style of Eloquent: `Post::published()->tag('travel')->latest()->get()`.
- Multilingual content with one file per language, translated slugs, language
  prefixes, `hreflang` links and a choice between hiding and falling back for
  missing translations.
- Themes with Blade templates, translations and automatically published assets.
  A first, simple version of the default theme Tide.
- Cards in Markdown (`::: callout … :::`) with built-in looks for callout,
  button and divider, overridable by themes.
- Media from content folders with resized versions and WebP, `srcset` and
  `<picture>` helpers.
- Full page cache served by `public/index.php` before Laravel starts.
- RSS feed per language, `sitemap.xml`, `robots.txt`, Open Graph and JSON-LD.
- Scheduled publishing and maintenance without cron.
- Artisan commands `wake:index`, `wake:clear` and `wake:maintenance`.
- Team accounts as files in `storage/accounts/` with Ghost's roles (owner,
  administrator, editor, author, contributor), a setup on the first visit to
  `/wake`, sign-in with rate limiting and "stay signed in".
- Panel layout in the "Neon clean" design with self-hosted fonts and a
  configurable accent colour (`panel.accent`), in English and German.
- Built-in analytics: a small tracking script, `public/hit.php` that records
  hits without starting Laravel and without storing IP addresses, daily
  salted visitor hashes (level 1) and an optional consent cookie (level 2),
  daily and monthly summaries, real-time visitors, reading time, scroll depth,
  campaigns, events and next pages. Raw data is deleted after 30 days.
- Consent manager: a banner that only appears when consent is needed, with
  "Decline" as prominent as "Accept", categories, anonymous consent records,
  versioned texts in `site/consent.yml`, two-click embeds (`::: embed`) with
  privacy-friendly players, consent-gated scripts and `@wakeServices` for the
  privacy policy.
- Dashboard: visitors, page views, reading time and consent share with change
  against the previous period, a chart of the last 7, 30 or 90 days, most read
  posts with read-through rate, sources, live visitors, drafts, scheduled and
  recent posts, and a sentence about what stands out today.
- Statistics page with periods from today to 12 months, a pages table with
  reading time and read-through rate, sources, campaigns, countries, devices,
  languages, browsers, events and visits of AI crawlers.
- The wake of a page: where its readers came from and what they opened next.
- Consent page in the panel: decisions of the last 30 days, agreement per
  category, latest records, embedded services and a banner editor with live
  preview per language (administrators only).
- MCP server at `/wake/mcp` and over stdio (`wake:mcp`) with personal tokens
  and scopes, tools for statistics, AI crawlers, consent and site facts, the
  resources `wake://site` and `wake://stats/today` and a weekly review prompt.
  Tokens are managed under *Assistants* in the panel.
- Writing in the panel: lists of posts, pages and custom types with status
  filters, search and translation state; an editor based on Tiptap that reads
  and writes Markdown, with cards, a slash menu, image upload, cover image,
  blueprint fields, translations, publishing now or later and statistics of
  the entry.
- Every change keeps a revision, changed addresses of published entries get
  redirects (`site/redirects.yml`), deleted entries go to `storage/trash/`, and
  the editor shows who else is editing.
- Front matter is written without needless quotes and with inline lists.
- MCP tools for content: list, read, create (always as a draft), update,
  translate, publish, unpublish and upload images, with the role of the
  token's owner, an optional approval mode (`mcp.approval`) and a history of
  every change in `storage/history/`.
- Team page for owners and administrators: invitations by mail (with the link
  shown in the panel as a fallback), roles, removing members; forgotten
  passwords by mail with tokens in the file cache.
- Settings page: site title and description per language, time zone, post
  addresses, missing translations, statistics, approval for assistants and the
  accent colour (stored in `site/settings.yml`), your own account and
  password, notes on content problems and the latest changes.
- Earlier versions of every translation in the editor, with one-click restore
  (the current text is kept), and the changes of the entry.
- Media page with every file of every entry.
- Read-only content API at `/wake/api/v1/` (site, entries, entry, stats):
  published content is public with CORS, drafts and statistics need a token.
- Import from Ghost (panel and `wake:import:ghost`): posts, pages, tags,
  feature images and images in posts, with Ghost cards turned into Wake cards.
