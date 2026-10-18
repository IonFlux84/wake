---
title: "0002: No database"
description: Wake stores everything in files and needs neither MySQL nor SQLite.
---

# 0002: No database

- **Status:** accepted
- **Date:** 2026-10-17

## Context

Most small websites run on shared hosting. A database means credentials, backups,
migrations and one more thing that breaks during a move. Content in files can be
read, copied, diffed and versioned with tools everyone already has.

## Decision

Wake uses no database at all.

- Content is Markdown files in `content/`.
- Accounts, statistics, consent records and revisions are files in `storage/`.
- Laravel runs with the `file` session and cache drivers and the `sync` queue.
- Eloquent is replaced by file models with a similar API
  (`Post::published()->tag('travel')->latest()->get()`).
- Speed comes from a content index kept as a PHP array, which OPcache holds in
  memory, and from a full page cache that is served before Laravel starts.

## Consequences

- Installing is uploading files. Moving is copying a folder.
- Writes must be atomic (temporary file, then `rename()`) and locked with `flock()`.
- There is no queue worker, so long tasks run in small slices after responses.
- Laravel packages that need a database (Passport, database notifications)
  cannot be used. OAuth for the MCP server waits for a file-based solution.
- Very large sites (well above a million page views a month) are not the target.

## Alternatives considered

- **SQLite.** Still a file, but a binary one: no diffs, harder to merge, and
  locking problems on network file systems that cheap hosts like to use.
- **Optional database.** Two storage paths double the testing and the bugs.
