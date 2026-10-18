---
title: MCP server
description: Every tool, resource and prompt of Wake's built-in MCP server, and how tokens work.
---

# MCP server

Wake speaks the [Model Context Protocol](https://modelcontextprotocol.io), so
AI assistants such as Claude can work with your site
([decision 0006](../decisions/0006-mcp-server-built-in.md)).

| Transport | Address |
|---|---|
| HTTP (Streamable HTTP, no streaming) | `https://your-site/wake/mcp` with `Authorization: Bearer wake_…` |
| stdio | `php wake/artisan wake:mcp --token=wake_…` |

Connecting step by step: [Connect an AI assistant](../guides/connect-an-assistant.md).

## Tokens

Every team member creates their own tokens in the panel under *Assistants*.
A token acts as that person, never with more rights than they have, and only
with the scopes chosen for it:

| Scope | Allows |
|---|---|
| `stats:read` | all statistics and consent tools |
| `content:read` | reading posts, pages and other entries |
| `content:write` | creating and changing drafts |
| `content:publish` | publishing and unpublishing (authors and above) |

Wake stores only a SHA-256 fingerprint of a token, in
`storage/accounts/{account}/tokens/`. Revoking a token deletes that file.
Requests are limited to 60 per minute.

## Tools

| Tool | Scope | What it does |
|---|---|---|
| `site_info` | none | title, address, languages, content types with counts, the current person and the token's scopes |
| `stats_overview` | `stats:read` | visitors, page views, reading time and consent share for `today`, `yesterday`, `7d`, `30d`, `90d`, `12m` or `month`, with change against the previous period, top pages and top sources |
| `stats_query` | `stats:read` | any date range (`from`, `to`, at most 400 days) broken down by `days`, `hours`, `pages`, `referrers`, `campaigns`, `countries`, `languages`, `devices`, `browsers`, `os` or `events` |
| `stats_post` | `stats:read` | one entry (by `id`) or path: visitors, reading time, scroll depth, read-through rate, where readers came from, where they went next, visitors per day, per language |
| `stats_realtime` | `stats:read` | visitors of the last five minutes and their pages |
| `stats_ai_crawlers` | `stats:read` | visits of AI crawlers by crawler and page |
| `consent_overview` | `stats:read` | consent decisions, agreement per category, whether a banner is needed, embedded services |
| `content_list` | `content:read` | entries of a type with id, title, status, date, URL, languages and tags; filter by status, tag or title |
| `content_read` | `content:read` | one entry in one language: all fields, the Markdown body, files and translations |
| `content_create` | `content:write` | a new entry, always as a draft, marked with `via: mcp` |
| `content_update` | `content:write` | change title, body or fields of a translation, or add a translation |
| `content_publish` | `content:publish` | publish now or at a given time (`at`); with approval mode only marks the entry for review |
| `content_unpublish` | `content:publish` | turn a translation back into a draft |
| `media_upload` | `content:write` | add an image to an entry from base64 data |

Tools answer with JSON. Mistakes, such as an unknown id or a missing scope,
come back as an error message that tells the assistant what to do instead.

## Resources

| Resource | Contents |
|---|---|
| `wake://site` | the site at a glance and how content is written in Wake |
| `wake://stats/today` | today's numbers and visitors right now |

## Prompts

| Prompt | Purpose |
|---|---|
| `weekly_review` | a short review of the last seven days with one suggestion |

## Approval mode

With `mcp.approval` set to `true` in `site/config.php`, `content_publish` does
not publish. It writes `review: requested` into the entry, the panel shows
*Waiting for approval*, and a person publishes it there.

## History

Every change is written to `storage/history/{year}-{month}.log` with the
person, the action and how it was made (`panel` or `mcp` with the token's
name).

## Safety

- Content and statistics returned to an assistant are marked as data in the
  server instructions. Assistants are told never to follow requests found in
  them (protection against prompt injection).
- Assistants cannot delete anything, and new entries always start as drafts.
- A token acts with the role of its owner: a contributor's token cannot publish
  or change other people's entries.
- Status, author and publication date cannot be set through `fields`.
- Every token can be revoked at any time and shows when it was last used.
