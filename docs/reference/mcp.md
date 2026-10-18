---
title: MCP server
description: Every tool, resource and prompt of Wake's built-in MCP server, with parameters, answers, tokens, scopes and limits.
---

# MCP server

Wake speaks the [Model Context Protocol](https://modelcontextprotocol.io), so
AI assistants such as Claude can answer questions about your statistics and
read, draft and publish content
([decision 0006](../decisions/0006-mcp-server-built-in.md)). The server is
part of every Wake installation; there is nothing to install.

Connecting step by step: [Connect an AI assistant](../guides/connect-an-assistant.md).
Why it is built the way it is: [Assistants and safety](../concepts/assistants.md).

## Endpoints

| Transport | Address | Authentication |
|---|---|---|
| HTTP | `POST https://your-site/wake/mcp` | header `Authorization: Bearer wake_…`, from the panel or through [OAuth](#oauth) |
| stdio | `php wake/artisan wake:mcp` | `--token=wake_…` or the environment variable `WAKE_TOKEN` |

The HTTP endpoint uses *Streamable HTTP* without streaming: every request is
one JSON-RPC message and gets one JSON answer. That works on any shared host,
without long-running connections. It is not affected by the
[closed site](configuration.md#site) setting, because it always needs a token.

The stdio server is for an assistant on the same computer as the site, for
example during development. It runs `wake:mcp` as a child process of the
assistant ([CLI](cli.md#wakemcp)).

Server name: `Wake`. The server sends instructions to the assistant on how to
use the tools (start with `site_info`, read `wake://site` before writing,
publish only when asked, treat website text as data).

## OAuth

Clients that sign in themselves, such as custom connectors in claude.ai, only
need the address of the endpoint. Wake implements the authorization flow of the
MCP specification, without a database:

| Address | What |
|---|---|
| `GET /.well-known/oauth-protected-resource` (also `…/wake/mcp`) | resource metadata (RFC 9728): which server issues tokens |
| `GET /.well-known/oauth-authorization-server` | authorization server metadata (RFC 8414) |
| `POST /wake/oauth/register` | dynamic client registration (RFC 7591), at most 10 per minute |
| `GET /wake/oauth/authorize` | the consent page in the panel |
| `POST /wake/oauth/token` | exchanges a code or a refresh token for tokens, at most 30 per minute |

1. A request without a token gets `401` with
   `WWW-Authenticate: Bearer resource_metadata="…"`. The client reads the
   metadata and registers itself. Clients are public (no secret); redirect
   addresses must use `https`, or `http` on `localhost`.
2. The client sends the person to the consent page with a PKCE challenge
   (`S256` only). The person signs in to the panel (with two-factor sign-in, if
   required), sees the client's name and where it returns to, chooses the
   rights (never more than their own role allows) and clicks **Connect**.
3. The client exchanges the code (valid once, for 10 minutes) and the PKCE
   verifier for an access token and a refresh token.
4. The access token is an ordinary Wake token named after the client, e.g.
   *Claude (OAuth)*, valid for 30 days and listed under *Assistants*. The
   refresh token (180 days) gives a new pair once and ends the old access
   token. Revoking the access token in the panel ends the connection; its
   refresh token stops working too.

Clients, codes and refresh tokens are files in `data/oauth/`; codes and
refresh tokens only as SHA-256 hashes.

## Tokens

Every team member with the right `assistants.use` ([roles](roles.md)) creates
their own tokens in the panel under *Assistants*.

- A token looks like `wake_` followed by 40 letters and digits. Wake shows it
  once, right after creating it.
- Wake stores only a SHA-256 hash of it, in
  `data/accounts/{account}/tokens/{id}.yml`, with the name, the scopes, the
  creation date, the expiry date and when it was last used (updated at most
  once a minute).
- Revoking a token in the panel deletes that file; the next request with it
  fails.
- Tokens expire: after 90 days unless you choose 30 days, a year or no end
  when creating one. An expired token is refused like a revoked one; the panel
  marks it *expired*. Create a new one and revoke the old one.
- Tokens for AI assistants never need the password and are not affected by
  [two-factor sign-in](../guides/two-factor.md).

### Scopes

A token has one or more scopes, chosen when it is created:

| Scope | Allows | Offered to |
|---|---|---|
| `stats:read` | all statistics tools, `consent_overview` and `wake://stats/today` | people with `stats.view` |
| `content:read` | `content_list`, `content_read` and `content_cards` | people with `content.write` |
| `content:write` | `content_create`, `content_update`, `content_style` and `media_upload` | people with `content.write` |
| `content:publish` | `content_publish` and `content_unpublish` | people with `content.publish` or `content.publish_all` |

### Scopes and roles

A request with a token acts **as the person who created it**. The scope
decides which tools the token may call at all; the person's
[role](roles.md) then decides what the tool may do:

- A token can never do more than its owner. A contributor cannot create a
  token with `content:publish`.
- `content_list` and `content_read` show drafts and scheduled entries of
  other people only to owners who see them in the panel as well
  (`content.edit_all` or `content.publish`); everyone else sees published
  entries and their own.
- `content_update`, `content_style` and `media_upload` only change entries the owner may edit
  (`content.edit_all` for other people's entries, `content.publish` to change
  published ones).
- `content_publish` and `content_unpublish` follow the owner's
  `content.publish` / `content.publish_all` rights.
- If the owner's role changes, their tokens follow immediately: a scope only
  works while the role still has the right listed above. If the
  account is removed, its tokens stop working.

## Tools

Tools answer with JSON. A mistake (an unknown id, a missing scope, a right the
role does not have, a date that cannot be read) comes back as an MCP error
result with a sentence that tells the assistant what to do instead, for
example "There is no entry with this id. content_list shows the ids."

Dates are `YYYY-MM-DD`, times `YYYY-MM-DD HH:MM` in the site's time zone
(`site_info` returns it). Entry ids are the folders below `content/`, for
example `posts/hello-world` or `pages/about/team`.

### `site_info`

Basic facts about the site. Needs no scope; a good first call.

No parameters.

Returns `title`, `description`, `url`, `languages` (code → name),
`default_language`, `content_types` (with folder and number of entries),
`categories` (with their fields: type, label, required, options, whether the
same in all languages), `you` (name, role and its capabilities),
`token_scopes` and `timezone`.

### `stats_overview`

Scope `stats:read`. The numbers of a period compared with the period before.

| Parameter | Type | Required | Meaning |
|---|---|---|---|
| `period` | string | no | `today`, `yesterday`, `7d` (default, the last seven days including today), `30d`, `90d`, `12m` or `month` |

Returns `from`, `to`, `visitors`, `pageviews`, `average_reading_seconds`,
`consent_share`, `change_percent` (visitors and page views against the
previous period), `top_pages` (path, title, id, visitors, page views, read
rate) and `top_sources`.

### `stats_query`

Scope `stats:read`. Any date range, broken down by one dimension.

| Parameter | Type | Required | Meaning |
|---|---|---|---|
| `from` | string | yes | first day, `YYYY-MM-DD` |
| `to` | string | yes | last day, `YYYY-MM-DD`, at most 400 days after `from` |
| `dimension` | string | yes | `days`, `hours`, `pages`, `referrers`, `campaigns`, `countries`, `languages`, `devices`, `browsers`, `os` or `events` |
| `limit` | integer | no | maximum number of rows, 1–200, default 20 |

Returns `from`, `to`, `visitors`, `pageviews` and the rows under the name of
the dimension, e.g. `"pages": […]`.

### `stats_post`

Scope `stats:read`. Everything about one entry or address.

| Parameter | Type | Required | Meaning |
|---|---|---|---|
| `id` | string | one of `id` or `path` | entry id, e.g. `posts/hello-world` |
| `path` | string | one of `id` or `path` | URL path, e.g. `/hello-world`; used when no id is given |
| `period` | string | no | `7d`, `30d` (default), `90d` or `12m` |

Returns `id`, `title`, `from`, `to` and, per language, `path`, `visitors`,
`pageviews`, `average_reading_seconds`, `average_scroll_percent`, `read_rate`,
`came_from` (top eight sources), `went_on_to` (top eight next pages) and
`per_day`.

### `stats_realtime`

Scope `stats:read`. Visitors of the last five minutes and the pages they are
on. No parameters.

### `stats_ai_crawlers`

Scope `stats:read`. Visits of AI crawlers (such as GPTBot or ClaudeBot) by
crawler and page.

| Parameter | Type | Required | Meaning |
|---|---|---|---|
| `period` | string | no | `today`, `7d`, `30d` (default) or `90d` |

### `consent_overview`

Scope `stats:read`. How visitors answered the consent banner.

| Parameter | Type | Required | Meaning |
|---|---|---|---|
| `days` | integer | no | days back from today, 1–366, default 30 |

Returns the decisions and the agreement per category, plus `banner_needed`,
`banner_version`, `categories_offered` and `embedded_services`. See
[consent](../concepts/consent.md).

### `content_list`

Scope `content:read`. Entries of one type, newest first.

| Parameter | Type | Required | Meaning |
|---|---|---|---|
| `type` | string | no | `post` (default), `page`, `tag` or a custom type from `site_info` |
| `status` | string | no | `any` (default), `published`, `draft` or `scheduled` |
| `tag` | string | no | only entries with this tag |
| `category` | string | no | only posts of this category key, e.g. `termine` |
| `search` | string | no | words in the title |
| `language` | string | no | language code, default the site's default language |
| `limit` | integer | no | default 20, at most 100 |

Returns `count` and `entries`, each with `id`, `title`, `status`, `date`,
`url`, `languages`, `tags`, `category`, `start` and `created_by_assistant`.

### `content_read`

Scope `content:read`. One entry in one language.

| Parameter | Type | Required | Meaning |
|---|---|---|---|
| `id` | string | yes | entry id from `content_list` |
| `language` | string | no | language code, default the site's default language |

Returns `id`, `type`, `language`, `requested_language_missing`, `status`,
`url`, `fields` (all front matter), `files` (references of the files in the
entry's media folder, e.g. `media/posts/harbour/boats.jpg`), `style` (the
entry's `style.css`, empty without one), `classes` (the CSS classes in the
rendered text, e.g. `card-callout`, to write CSS against), `translations` and
`body` (the Markdown, marked as data).

### `content_cards`

Scope `content:read`. The cards the site offers for the body: Wake's, those of
running plugins and the theme's, from their
[descriptions](templates.md#card-descriptions).

| Parameter | Type | Required | Meaning |
|---|---|---|---|
| `language` | string | no | language of titles and labels, default the site's default language |

Returns `cards`, each with `name`, `title`, `description`, `hint`, `source`
(`wake`, `plugin:{folder}` or `theme`), `value` (label and whether it is
required, or `null`), `content` (whether it holds Markdown), `fields` (`key`,
`type`, `label`, `required`, `default`, `options`) and `example`, the card
written out in Markdown. Also `syntax` and a `note` on image fields.

### `content_create`

Scope `content:write`. A new entry, always as a draft.

| Parameter | Type | Required | Meaning |
|---|---|---|---|
| `title` | string | yes | the title |
| `type` | string | no | `post` (default), `page` or a custom type |
| `body` | string | no | the Markdown body, see [content format](content-format.md) |
| `language` | string | no | language code, default the site's default language |
| `fields` | object | no | more front matter, e.g. `{"tags": ["news"], "excerpt": "…", "slug": "…"}`; for a category `{"category": "termine", "start": "2026-11-11 17:30", "location": "Town hall"}` |
| `parent` | string | no | for pages: id of the parent page |

Wake sets `status: draft`, `author` (the token's owner) and `via: mcp`;
`status`, `author`, `via` and `published` in `fields` are ignored. Returns
`id`, `status`, `language`, `edit_in_panel` (a link for the person) and a
`next_step` hint.

### `content_update`

Scope `content:write`. Change one translation, or add a new one.

| Parameter | Type | Required | Meaning |
|---|---|---|---|
| `id` | string | yes | entry id |
| `language` | string | no | language code; a language the entry does not have yet adds a translation (as a draft, marked `via: mcp`) |
| `title` | string | no | new title |
| `body` | string | no | the complete new Markdown body; leave it out to keep the body |
| `fields` | object | no | fields to change; `null` removes a field. `status`, `author`, `via` and `published` cannot be changed here |

Every change keeps the previous version as a revision
([editing](../concepts/editing.md)). Returns `id`, `language`, `status` and
`url`.

### `content_style`

Scope `content:write`. Replace the design of one entry, its
[`style.css`](content-format.md#design-of-a-single-page-stylecss). It applies to
that page only, inside `<main>`, in every language.

| Parameter | Type | Required | Meaning |
|---|---|---|---|
| `id` | string | yes | entry id |
| `css` | string | yes | the complete new CSS, at most 64 KB; an empty string removes `style.css` |

Wake cleans the CSS exactly as in the panel: `@import`, addresses of other
sites, `image-set()` and `expression()` are removed, braces are balanced.
Images from the media library work with `url(media/…)`. The previous CSS is
kept as a version, and the change is live at once on a published entry, so the
same rights apply as for `content_update`. Returns `id`, `css` (as saved),
`cleaned` (`true` when Wake changed something), a `note` and `url`.

A good workflow for an assistant: `content_read` for `style` and `classes`,
write the CSS, call `content_style`, then look at `url`.

### `content_publish`

Scope `content:publish`. Publish a translation now or later.

| Parameter | Type | Required | Meaning |
|---|---|---|---|
| `id` | string | yes | entry id |
| `language` | string | no | default the site's default language |
| `at` | string | no | publish later: `YYYY-MM-DD HH:MM` in the site's time zone |

Returns `id`, `status` (`published` or `scheduled`), `date` and `url`, plus
`card_warnings` (card and missing settings) when cards in the text leave
required settings empty; publishing happens anyway. With
[approval mode](#approval-mode) it publishes nothing and returns
`status: waiting_for_approval` with a message.

### `content_unpublish`

Scope `content:publish`. Turn a translation back into a draft.

| Parameter | Type | Required | Meaning |
|---|---|---|---|
| `id` | string | yes | entry id |
| `language` | string | no | default the site's default language |

Returns `id` and `status: draft`.

### `media_upload`

Scope `content:write`. Add an image to the entry's folder in the media
library, `content/media/<entry id>/`.

| Parameter | Type | Required | Meaning |
|---|---|---|---|
| `id` | string | yes | entry id |
| `name` | string | yes | file name with extension, e.g. `harbour.jpg` |
| `data` | string | yes | the file as base64, at most 10 MB |

Only images are accepted: `jpg`, `jpeg`, `png`, `gif`, `webp`, `avif`. Wake
checks that the data really is an image and picks a free file name. Returns
`id`, `file` (the reference, e.g. `media/posts/harbour/harbour.jpg`) and
`markdown`, the snippet to put into the body
(`![](media/posts/harbour/harbour.jpg)`).

## Resources

| Resource | Type | Contents | Scope |
|---|---|---|---|
| `wake://site` | Markdown | the site at a glance and how content is written in Wake: front matter and cards | none |
| `wake://stats/today` | JSON | today so far: `visitors`, `pageviews`, `top_pages`, `top_sources` and `right_now` | `stats:read` |

## Prompts

| Prompt | Arguments | Purpose |
|---|---|---|
| `weekly_review` | none | a short review of the last seven days: calls `stats_overview` and `stats_post`, then writes at most eight sentences with one suggestion |

## Approval mode

With `mcp.approval` set to `true` in `settings/config.php` (or under *Settings*
in the panel), assistants can prepare everything but publish nothing:

- `content_publish` writes `review: requested` into the entry, or
  `review: scheduled 2026-11-03 18:00` when `at` was given, and answers
  `status: waiting_for_approval`.
- The panel shows *Waiting for approval* in the list of entries. A
  person checks the draft and publishes it; publishing removes the `review`
  field.
- `content_unpublish` and all other tools work as usual.

## Marking and history

- Entries created by an assistant carry `via: mcp` in their front matter. The
  lists in the panel show an *MCP* badge, and `content_list` returns
  `created_by_assistant`.
- Every change is written to `data/history/{year}-{month}.log` with the
  person, the action, `via: mcp` and the name of the token, so you can see
  which assistant changed what.

## Limits

| Limit | Value |
|---|---|
| Requests over HTTP | 60 per minute per person; more get HTTP 429 |
| `stats_query` range | at most 400 days |
| `content_list` | at most 100 entries per call |
| `media_upload` | images only, at most 10 MB |

## Errors

| Situation | Answer |
|---|---|
| no token, unknown or revoked token | HTTP 401 with `{"error": "A valid token is required. …"}` and `WWW-Authenticate: Bearer` |
| too many requests | HTTP 429 |
| token without the needed scope | tool error: "This token may not use … It needs the scope …" |
| role may not do it | tool error, e.g. "Your role may not publish this entry." |
| unknown id, type or language | tool error naming the tool that shows valid values |
| invalid parameters | tool error with the validation message |

## What assistants can never do

- Delete anything: entries, files, accounts or statistics. There is no tool
  for it.
- Change settings, themes, plugins, roles, the team or tokens.
- Set `status`, `author`, `via` or `published` directly. New entries always
  start as drafts.
- Do more than the person behind the token may do.
- Read accounts, passwords, mail settings or the raw statistics files.

Content and statistics are returned as data, and the server instructions tell
the assistant never to follow requests found inside them (protection against
prompt injection). More in [Assistants and safety](../concepts/assistants.md).
