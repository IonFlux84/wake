---
title: Command line
description: Artisan commands that come with Wake.
---

# Command line

Wake runs completely without a command line. These commands are for developers
and for hosts with SSH. Run them from the site root:

```bash
php wake/artisan <command>
```

All Laravel commands work as usual. Wake adds the following.

<!-- One section per command. A test checks that every wake: command appears here. -->

## `wake:index`

Updates the content index. Only needed when the setting `index.check` is
`'never'` and you changed files by hand. With `--force`, every file is parsed
again, even unchanged ones.

```bash
php wake/artisan wake:index
php wake/artisan wake:index --force
```

Problems found while reading content, such as broken front matter or two
entries with the same address, are listed after the summary.

## `wake:clear`

Removes all cached pages. Wake does this by itself whenever content changes,
so you need it mainly after changing a theme.

```bash
php wake/artisan wake:clear
```

## `wake:maintenance`

Runs the maintenance tasks right away: publishes scheduled entries whose date
has passed, and later also builds statistics and cleans up old data.

```bash
php wake/artisan wake:maintenance
```

If your host offers cron, set `maintenance.mode` to `'cron'` and run Laravel's
scheduler every minute instead:

```bash
* * * * * php /path/to/site/wake/artisan schedule:run
```

## `wake:mcp`

Starts Wake's MCP server over standard input and output, for an AI assistant
running on the same machine. It needs a personal token from the panel
(*Assistants*), given as option or environment variable:

```bash
php wake/artisan wake:mcp --token=wake_…
WAKE_TOKEN=wake_… php wake/artisan wake:mcp
```

To connect Claude Code locally:

```bash
claude mcp add wake -- php /path/to/site/wake/artisan wake:mcp --token=wake_…
```

See [MCP server](mcp.md) for the tools.

## `wake:import:ghost`

Imports posts, pages and tags from a Ghost export file. With `--url`, images
are downloaded from the old site. `--language` sets the language of the
imported content.

```bash
php wake/artisan wake:import:ghost ghost-export.json --url=https://blog.example.org
```

See [Move from Ghost](../guides/move-from-ghost.md).
