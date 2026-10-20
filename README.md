<div align="center">

<pre>
         /\
        /  \        w a k e
       /    \       ─────────────────────────────
      /      \      every post leaves a wake.
     /        \
    /          \
</pre>

**The flat-file CMS that speaks MCP.**<br>
Markdown files on the hosting you already have. Unzip it, connect the AI agent
you already use, and run your site by asking.<br>
No database. No cron. No Node on the server. Just files.

<br>

[![Tests](https://img.shields.io/github/actions/workflow/status/IonFlux84/wake/tests.yml?branch=main&style=flat-square&label=tests&logo=githubactions&logoColor=white)](https://github.com/IonFlux84/wake/actions/workflows/tests.yml)
![Version](https://img.shields.io/badge/version-1.0.0--dev-111?style=flat-square)
[![License: MIT](https://img.shields.io/badge/license-MIT-111?style=flat-square)](LICENSE)
![Database](https://img.shields.io/badge/database-none-111?style=flat-square)
![Tests](https://img.shields.io/badge/pest-413%20passing-111?style=flat-square)

![PHP](https://img.shields.io/badge/PHP-8.4%20%7C%208.5-777BB4?style=flat-square&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![Livewire](https://img.shields.io/badge/Livewire-4-4E56A6?style=flat-square&logo=livewire&logoColor=white)
![Alpine.js](https://img.shields.io/badge/Alpine.js-3-8BC0D0?style=flat-square&logo=alpinedotjs&logoColor=white)
![Tiptap](https://img.shields.io/badge/Tiptap-3-111?style=flat-square)
![Markdown](https://img.shields.io/badge/content-Markdown%20%2B%20YAML-111?style=flat-square&logo=markdown&logoColor=white)
![MCP](https://img.shields.io/badge/MCP-server%20%2B%20OAuth-111?style=flat-square)
![Pest](https://img.shields.io/badge/tested%20with-Pest%205-F472B6?style=flat-square)

<br>

[Quick start](#quick-start) ·
[Run it by asking](#run-it-by-asking) ·
[Features](#features) ·
[How it works](#how-it-works) ·
[Connect your agent](#connect-your-agent) ·
[Docs](docs/README.md) ·
[Contributing](CONTRIBUTING.md)

</div>

---

![The Wake dashboard: visitors, page views, reading time and consent share](docs/images/dashboard.jpg)

## `$ cat why.md`

Most CMSs make you choose: **simple** (files, Git, no database) or **complete**
(analytics, consent, a real editor, an API). Wake refuses to choose. And it
adds a third way to work: you can simply ask.

- **Run it by asking.** Every Wake site ships with an MCP server. Connect
  Claude, ChatGPT, Cursor, VS Code or any other agent that speaks MCP, and it
  writes, translates, schedules, restyles and reads your statistics for you.
  The panel is still there when you'd rather click.
- **As simple as Kirby.** Every page and post is a folder with a `content.md`.
  Open it in Vim, push it with Git, upload it by FTP. Wake notices.
- **As calm as Ghost.** A focused editor that writes clean Markdown, and a
  dashboard you actually want to open in the morning.
- **Analytics and consent in the box.** Your own statistics on your own
  server, a consent banner that treats *Decline* like *Accept*, and a privacy
  policy section that writes itself.
- **Runs on a 3 € web host.** Shared hosting without SSH, cron or Node is the
  target, not an afterthought.
- **Free. Really.** MIT licensed. No paid tier, no pro version, no "contact
  sales".

## `$ wake --stats`

| | |
|---|---|
| Database tables | **0** |
| Cron jobs needed | **0** |
| Node.js on the server | **no** |
| Tracking script | **1.6 KB** gzipped, readable, no cookies by itself |
| Tests | **413** Pest tests, docs checked by tests too |
| MCP tools | **16**, plus OAuth for connectors |
| Decision records | **11** ([docs/decisions](docs/decisions)) |
| Time to first page | download, upload, open `/wake` |

## Quick start

**On a web host** (the main use case):

1. Download `wake-x.y.z.zip` from the [releases](https://github.com/IonFlux84/wake/releases)
   (the first one comes with 1.0; until then build it with `scripts/build-release.sh`) and unzip it.
2. Upload everything and point the document root to `public/`
   (no access to the document root? the bundled `.htaccess` handles it).
3. Open `https://your-site/wake`. Wake writes its own `.env` with a fresh key,
   asks for your account, and creates an imprint and a privacy policy as drafts.

Full guide: [Install on a web host](docs/getting-started/hosting.md).

**On your machine:**

```bash
git clone https://github.com/IonFlux84/wake.git my-site && cd my-site
composer --working-dir=wake install
php wake/artisan serve
# website: http://localhost:8000   panel: http://localhost:8000/wake
```

Requirements: PHP 8.4+ with the usual extensions, any web server. Details in
[System requirements](docs/getting-started/requirements.md).

## Run it by asking

Unzip, connect, ask. The MCP server comes in the same ZIP, so there is nothing
extra to install. Give your agent `https://your-site/wake/mcp` and talk to your
site:

```text
  ● connected  https://your-site/wake/mcp  ·  16 tools

you   How was last week? Which post did best?
  →   stats_overview   period=7d
  →   stats_post       posts/summer-festival-2027
agent 2,318 visitors, up 12 %. "Summer festival 2027" led with 640,
      most of them from search.

you   Write a short follow-up with the photos and schedule it for Friday at 9.
  →   content_create   posts/summer-festival-recap   draft
  →   content_publish  at=2027-07-09 09:00
agent Done. The draft is scheduled for Friday, 9:00.
```

| You say | Wake's tool |
|---|---|
| *"How was last week?"* | `stats_overview` |
| *"Where did readers come from in March?"* | `stats_query` |
| *"Which AI crawlers came by this month?"* | `stats_ai_crawlers` |
| *"Draft a post about the summer festival."* | `content_create` |
| *"Add an English version of the about page."* | `content_update` |
| *"Make the headings on the about page bigger."* | `content_style` |
| *"Publish the recap on Friday at 9."* | `content_publish` |

**Your agent works for you, not instead of you.**

- **One token per agent**, with its own rights (read stats, read content,
  write drafts, publish) and an expiry date, 90 days by default.
- **Drafts first.** Everything an agent creates starts as a draft. In approval
  mode a person publishes it in the panel.
- **Nothing gets deleted.** There is no delete tool. The worst an agent can do
  is a draft you throw away.
- **You can see who did what.** Entries carry `via: mcp`, the history names the
  token, and every earlier version is one click away.

## Features

| The editor | The wake of a post | Tide, the default theme |
|---|---|---|
| ![Editor](docs/images/editor.jpg) | ![Where readers came from and went next](docs/images/wake.jpg) | ![Tide theme](docs/images/tide.jpg) |

<details open>
<summary><b>✍️ Writing</b></summary>

- Editor that reads and writes **Markdown**: one paragraph per line, clean diffs.
- **Cards** (`::: callout`, `::: button`, `::: embed` …) that stay readable as
  text. Themes and plugins describe their own cards in a small YAML file, and
  the editor turns them into forms and `/` menu entries.
- **Design per page:** a `style.css` next to `content.md`, scoped to the
  content with `@scope` and cleaned, so everyone may edit it safely.
- Translations, scheduling, versions of every save, a history of every change,
  preview links for people without an account.
- **Media library** in `content/media/` that knows which page uses which file,
  keeps images of drafts private and warns before you delete something in use.
- Page tree with drag and drop, menus, redirects, categories with their own
  fields (events with recurring dates and `.ics` included).
</details>

<details>
<summary><b>📈 Statistics</b></summary>

- Visitors, sources, campaigns, devices, reading time, read-through rate,
  live visitors and AI crawlers, per site and per post.
- **The wake:** where readers of a post came from and where they went next.
- Raw data never leaves your server, IP addresses are never stored, visitor
  hashes rotate daily.
- Privacy by default: counting starts with consent. Switch to cookieless
  counting without asking if that is allowed for your site.
</details>

<details>
<summary><b>🛡️ Consent and privacy</b></summary>

- A banner that offers *Decline* as prominently as *Accept*, with anonymous
  records of every decision.
- Two-click embeds for YouTube, Vimeo, maps and more, with "always allow".
- `::: privacy-details` puts an always current section on statistics, cookies
  and embeds into your privacy policy.
- Imprint and privacy policy are standard pages; the banner links whichever
  page or address you choose.
</details>

<details>
<summary><b>🤖 For AI agents</b></summary>

- **MCP server** over HTTP (`/wake/mcp`) and stdio (`wake:mcp`) with 16 tools:
  stats, consent, content, media, cards, design, publishing.
- Works with any MCP client: Claude, ChatGPT, Cursor, VS Code and others.
- Personal **tokens** with scopes and an expiry date, or **OAuth** for
  connectors that sign in themselves: enter the address, confirm in the panel,
  done.
- Agents never delete anything, create drafts only, and can be limited to
  *ask before publishing*.
</details>

<details>
<summary><b>🧩 For developers and designers</b></summary>

- **Themes** are Blade templates with options in `theme.yml`; Tide is the
  calm, fast default.
- **Plugins** are PHP folders with a small API: cards, field types, routes,
  maintenance tasks, `<head>` tags, events, commands, PSR-4 autoloading and
  protected public forms.
- Read-only **content API**, full-text search, import from Ghost, backups as
  ZIP, two-factor sign-in (optionally required for everyone), roles built from
  13 rights, updates from the panel.
- Documentation is part of every change: tests fail when a setting, command,
  MCP tool or decision record is undocumented.
</details>

## How it works

```text
my-site/
├── content/                 pages, posts and media: one folder per entry
│   ├── website.md           site title and description
│   ├── pages/about/
│   │   ├── content.md       front matter + Markdown, default language
│   │   ├── content.de.md    the German translation
│   │   └── style.css        optional design for this page only
│   ├── posts/hello-world/content.md
│   └── media/posts/hello-world/cover.jpg
├── themes/                  looks (Tide inside)
├── plugins/                 extensions
├── settings/                config.php, menus, redirects, roles, consent
├── data/                    accounts, statistics, versions, backups: back it up
├── temp/                    caches: delete any time
├── public/                  the document root
└── wake/                    the Laravel app, replaced on update
```

A post is just a file:

```markdown
---
title: Hello world
status: published
published: 2026-10-17 09:00
tags: [wake, markdown]
cover: media/posts/hello-world/cover.jpg
---

This post is a folder with one Markdown file per language.

::: callout 💡
Cards stay readable in the file.
:::
```

**Fast without a database.** Wake keeps an index of all front matter in one
PHP file that OPcache holds in memory, so queries are array lookups. A full
page cache answers from `public/index.php` before Laravel even boots. When
you change a file by FTP or Git, Wake notices on the next uncached request.

**Maintenance without cron.** Scheduled posts, statistics roll-ups and
clean-up run in small, time-boxed steps after a response has been sent.

The *why* behind every big choice is written down in the
[decision records](docs/decisions/README.md): no database, Laravel and
Livewire, Markdown with YAML, analytics and MCP in the core, one folder per
entry, and more.

## Connect your agent

Every agent needs the same thing: the address `https://your-site/wake/mcp`.
Agents that sign in themselves use OAuth and you confirm them in the panel.
All others get a token from the panel (*Assistants*) as the header
`Authorization: Bearer wake_…`.

```bash
# Claude Code
claude mcp add --transport http wake https://your-site/wake/mcp \
  --header "Authorization: Bearer wake_…"

# claude.ai and other connectors that use OAuth: add a custom connector
# with the address https://your-site/wake/mcp and confirm it in the panel.

# Working on the server itself? stdio works too:
php wake/artisan wake:mcp
```

Then ask things like *"Which posts did people read to the end last month?"*
or *"Draft a post about the summer fest, with an event card."* See
[Connect an assistant](docs/guides/connect-an-assistant.md) and the
[MCP reference](docs/reference/mcp.md).

## Documentation

Start at [docs/README.md](docs/README.md), or jump in:

| Getting started | Guides | Reference |
|---|---|---|
| [Requirements](docs/getting-started/requirements.md) | [Write and publish](docs/guides/write-and-publish.md) | [Content format](docs/reference/content-format.md) |
| [Install on a web host](docs/getting-started/hosting.md) | [Build a theme](docs/guides/build-a-theme.md) | [Templates](docs/reference/templates.md) |
| [Your first site](docs/getting-started/first-site.md) | [Write a plugin](docs/guides/write-a-plugin.md) | [Configuration](docs/reference/configuration.md) |
| [Updating](docs/getting-started/updating.md) | [Connect an assistant](docs/guides/connect-an-assistant.md) | [MCP server](docs/reference/mcp.md) |

## Status

Version **1.0** is feature-complete and being tested on its first real
website. Until 1.0 is tagged, `main` moves fast. See the
[roadmap](docs/project/roadmap.md) and the [changelog](CHANGELOG.md).

## Contributing

Wake is built in the open. Read [CONTRIBUTING.md](CONTRIBUTING.md) first:
docs travel with code, tests use Pest, writes are atomic, and there is never a
database. Please follow the [Code of Conduct](CODE_OF_CONDUCT.md), and report
security issues as described in [SECURITY.md](SECURITY.md), not in the issue
tracker.

```bash
composer --working-dir=wake test                 # tests, including the docs checks
wake/vendor/bin/pint --config=wake/pint.json     # code style
```

## License

[MIT](LICENSE). Use it, change it, sell websites with it. No strings attached.

<div align="center"><sub>Made in the Ruhr area. Every post leaves a wake.</sub></div>
