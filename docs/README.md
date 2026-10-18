---
title: Wake documentation
description: Where to find what, and how the documentation is organised.
---

# Wake documentation

The documentation is split by what you want to do, following the
[Diátaxis](https://diataxis.fr) framework:

| Section | Read it when you … |
|---|---|
| [Getting started](getting-started/installation.md) | want a site running for the first time |
| [Guides](guides/README.md) | have a concrete task, e.g. "add a second language" |
| [Reference](reference/README.md) | need the exact name of a setting, field or command |
| [Concepts](concepts/README.md) | want to understand how and why Wake works the way it does |
| [Decisions](decisions/README.md) | wonder why something was decided, and what was rejected |
| [Contributing](contributing/writing-docs.md) | want to improve Wake or these pages |

## Getting started

- [Installation](getting-started/installation.md)

## Guides

- [Add a second language](guides/second-language.md)

## Reference

- [Configuration](reference/configuration.md): every setting in `site/config.php`
- [Content format](reference/content-format.md): folders, files and front matter
- [Templates](reference/templates.md): what a theme can use
- [Command line](reference/cli.md): Artisan commands

## Concepts

- [Architecture](concepts/architecture.md)
- [Analytics](concepts/analytics.md)
- [Consent](concepts/consent.md)

## Project

- [Roadmap](project/roadmap.md)
- [Decision records](decisions/README.md)

## About these pages

Every page is a Markdown file with front matter, in exactly the format Wake
uses for its own content. The documentation website will be a Wake site that
reads this folder.
