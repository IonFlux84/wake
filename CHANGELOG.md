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
- Full-text search per language at `/search` with a small inverted index in
  `storage/cache/`, without a database.
- Tide, the default theme, complete: featured post, grid with pagination, tags,
  related posts, older/newer navigation, reading progress, search and 404
  pages, a mobile menu, dark mode, bundled Archivo and an accent colour option
  (`theme_options.accent`).
- Automatic excerpts use paragraphs only.
- Installation without a shell: Wake writes `.env` with a fresh application
  key on the first request. A build script and a release workflow produce the
  installation ZIP with `vendor/` included.
- An `.htaccess` in the main folder for hosts that cannot use `public/` as the
  document root: every request is mapped into `public/`, and it does nothing
  when the document root is `public/` already. Tested with Apache.
- The page cache keeps pages per scheme and host.
- Updates from the panel (owner only): Wake checks GitHub only on request,
  verifies the SHA-256 checksum, swaps the `wake/` folder atomically and keeps
  the previous version as a backup in `storage/updates/`.
- README with screenshots, guides for hosting and updating.
- The panel speaks German by default. The panel language of the site is set
  under *Settings* (`panel.language`); everyone can still choose their own.
- Header image and preview image (`thumbnail`) for every entry, each with
  upload, choice from the entry's files and removal. The preview image is used
  in lists, the API and for social media, and falls back to the header image.
- An *Image* button in the editor toolbar inserts uploaded or existing images
  with a description.

- Post categories with their own fields, templates, addresses and order,
  managed in the panel under *Categories* and stored in `site/categories/`.
  The category *Termine* (events) comes with start, end, all day, venue,
  address, online link, organizer, admission, registration, contact and status.
- Events on the website: an event template in Tide with map link,
  registration and *Add to calendar*, a calendar file at `{address}.ics`,
  schema.org `Event` data, upcoming and past events on the category page and
  the next events on the start page.
- Category filter in the post list, the API (`category=`) and the MCP tools;
  `site_info` describes the categories and their fields.
- Queries: `category()`, `upcoming()` and `past()`. Fields a translation leaves
  out are taken from the default language in queries as well.

- Recurring events: weekly, every other week, monthly on the same day or the
  same weekday (2nd Tuesday, last Friday), yearly, with an end date and
  skipped dates. One post per series; every date appears in the event list,
  the event page shows the rule and the next dates, the calendar file carries
  the rule (`RRULE`, `EXDATE`), and the editor previews the dates.

- Closed site (`closed`, switched under *Settings*): only signed-in team
  members see the website, everyone else lands on the sign-in page and comes
  back to the page they wanted. `robots.txt` disallows everything, pages carry
  `noindex`, nothing is cached or counted, and the API needs a token.
- Roles with capabilities: the five built-in roles can be adjusted and reset,
  custom roles added and removed, in a matrix under *Team → Roles and rights*
  (`site/roles.yml`). Menu, pages and the MCP server follow the capabilities.

- Themes page: overview with screenshots, activate, duplicate, install from a
  ZIP, remove, and settings of the active theme declared in its `theme.yml`
  (`theme_option()` in templates). Tide offers accent colour, next events,
  tags and a footer line.
- The panel uses editorial type: Newsreader for headlines, numbers and the
  writing area, Inter for the interface. The monospace font is kept for code.

- Preview of entries in any status in the theme, from the editor, and preview
  links that work for seven days without an account (signed URLs).
- Redirects page in the panel: see, add and remove old addresses.
- File details: alt text and caption per language, credit and focus point,
  edited in the media library and stored next to the file (`photo.jpg.yml`).
  Images in Markdown without alt text take it from the details; cropped images
  keep the focus point in view.

### Fixed

- Images uploaded to a new post without a title were lost. Wake now saves the
  post as a draft called "Untitled" first.
