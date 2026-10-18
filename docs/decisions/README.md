---
title: Decision records
description: Why Wake is built the way it is, one decision per file.
---

# Decision records

Each record describes one fundamental decision: the situation, what we chose,
what it costs and what we rejected. Records are not rewritten afterwards. When a
decision changes, a new record supersedes the old one.

New record: copy [template.md](template.md), take the next number.

| # | Decision | Status |
|---|---|---|
| [0001](0001-record-decisions.md) | We record architecture decisions | accepted |
| [0002](0002-no-database.md) | No database | accepted |
| [0003](0003-laravel-and-livewire.md) | Laravel 13 with Livewire for the panel | accepted |
| [0004](0004-markdown-with-yaml.md) | Content as Markdown with YAML front matter | accepted |
| [0005](0005-analytics-and-consent-built-in.md) | Analytics and consent are part of the core | accepted |
| [0006](0006-mcp-server-built-in.md) | A built-in MCP server | accepted |
| [0007](0007-english-for-code-and-docs.md) | English for code and documentation | accepted |
| [0008](0008-application-in-its-own-folder.md) | The application lives in its own folder | accepted |
| [0009](0009-plugins-as-php-folders.md) | Plugins are PHP folders with a small interface | accepted |
