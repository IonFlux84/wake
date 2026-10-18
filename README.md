# Wake

**Write it. Watch it travel.**

Wake is a free flat-file CMS built on Laravel. Every post is a Markdown file
you can open in any text editor, and there is no database. Analytics and consent
management are built in, so the dashboard shows what you wrote next to how it
is doing. Wake also ships its own MCP server, which lets AI assistants such as
Claude answer questions about your stats and read, draft and publish posts.

> **Status:** early development (phase 1 of 6). Nothing here is ready for a
> real website yet. See the [roadmap](docs/project/roadmap.md).

## Why Wake

- **As simple as Kirby.** No database. Content is Markdown with YAML front
  matter, one folder per entry, versionable with Git.
- **As calm as Ghost.** A focused editor and a dashboard you want to open every
  morning.
- **Analytics and consent built in.** Cookieless page view statistics by
  default, more detail only with consent, and a consent banner that treats
  "Decline" the same as "Accept".
- **Made for AI assistants.** A built-in MCP server with scoped tokens.
- **Runs on cheap shared hosting.** No SSH, no cron and no Node on the server.
- **Free.** MIT licensed. There is no paid tier and no pro version.

## Quick look

```
wake/       the Laravel application (replaced on update)
site/       your configuration, blueprints and theme
content/    your posts and pages as Markdown files
storage/    accounts, statistics, caches (never public)
public/     the document root
```

A post is a folder with one file per language:

```markdown
---
title: Hello world
status: published
published: 2026-10-17 09:00
tags: [travel]
---

The first paragraph …
```

## Getting started

```bash
git clone https://github.com/IonFlux84/wake.git my-site
cd my-site/wake
composer install
cp ../.env.example ../.env && php artisan key:generate
php artisan serve
```

Then open http://localhost:8000. The full guide is in
[docs/getting-started/installation.md](docs/getting-started/installation.md).

## Documentation

Documentation is part of every change in Wake, not something that follows
later. Start at [docs/README.md](docs/README.md).

## Contributing

Wake is built in the open for everyone. Read [CONTRIBUTING.md](CONTRIBUTING.md)
before you open a pull request, and please follow our
[Code of Conduct](CODE_OF_CONDUCT.md). Security issues go to
[SECURITY.md](SECURITY.md), not to the issue tracker.

## License

Wake is open source software licensed under the [MIT license](LICENSE).
