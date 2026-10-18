---
title: Content API
description: Read Wake content as JSON, for apps, other websites or a headless frontend.
---

# Content API

Wake answers JSON at `/wake/api/v1/`. Published content can be read by anyone,
just like the website itself. Drafts and statistics need a personal token with
the right scope (created under *Assistants* in the panel), sent as
`Authorization: Bearer wake_…`. All endpoints allow requests from any origin
(CORS) and are limited to 120 requests per minute.

The API only reads. Content is written in the panel or through the
[MCP server](mcp.md).

## Endpoints

### `GET /wake/api/v1/site`

Title, description, address, languages and content types.

### `GET /wake/api/v1/entries`

| Parameter | Default | Meaning |
|---|---|---|
| `type` | `post` | content type: `post`, `page`, `tag` or a custom type |
| `language` | default language | language code |
| `tag` | | only entries with this tag |
| `status` | `published` | `draft`, `scheduled` or `any` need the scope `content:read` |
| `limit` | `10` | entries per page, at most 100 |
| `page` | `1` | page |
| `body` | `0` | `1` adds `markdown` and `html` to each entry |

```json
{
  "data": [{
    "id": "posts/2026-10-17-hello-world",
    "title": "Hello world",
    "url": "https://example.org/hello-world",
    "status": "published",
    "date": "2026-10-17T09:00:00+02:00",
    "excerpt": "…",
    "tags": [{ "slug": "wake", "title": "Wake" }],
    "cover": { "url": "…", "srcset": "…", "width": 1600, "height": 900 },
    "translations": { "en": "…", "de": "…" },
    "fields": { "title": "Hello world", "…": "…" }
  }],
  "meta": { "page": 1, "pages": 3, "total": 25 }
}
```

### `GET /wake/api/v1/entry?id=…`

One entry with `markdown` and `html`. Takes `language` as well. Drafts need the
scope `content:read`; without it they answer 404.

### `GET /wake/api/v1/stats?period=7d`

The statistics report of a period (`today`, `yesterday`, `7d`, `30d`, `90d`,
`12m`, `month`). Needs the scope `stats:read`.

## Errors

Errors come back as JSON with a `message` and the usual status codes: 401 for an
invalid token, 403 for a missing scope, 404 for something that does not exist,
422 for invalid parameters, 429 for too many requests.
