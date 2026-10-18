---
title: Connect an AI assistant
description: Create a token, connect Claude or another assistant over MCP, and let it answer questions, draft and publish.
---

# Connect an AI assistant

Wake has a built-in [MCP server](../reference/mcp.md). Once an assistant is
connected, you can ask it things like "How was last week?" or "Write a draft
about our summer party", and it works with your site directly.

You need the right `assistants.use` (everyone has it by default, see
[roles](../reference/roles.md)) and, for the HTTP connection, a site that is
reachable over HTTPS.

## 1. Create a token

1. In the panel, open *Assistants*.
2. Give the token a name that tells you later where it is used, for example
   "Claude Code on my laptop".
3. Choose what it may do:
   - **Read statistics** (`stats:read`): enough for questions about visitors,
     posts and consent.
   - **Read content** (`content:read`): to look at posts and pages.
   - **Write drafts** (`content:write`): to create and change drafts and add
     images.
   - **Publish** (`content:publish`): to publish and unpublish. Only offered if
     your role may publish.
4. Click **Create token** and copy the token. It starts with `wake_` and is shown
   **only once**. Keep it like a password.

Use one token per assistant and device, so you can revoke one without
touching the others.

## 2. Connect the assistant

Every assistant needs the same two things:

- the address `https://your-site/wake/mcp`
- the header `Authorization: Bearer wake_…`

### Claude Code

```bash
claude mcp add --transport http wake https://your-site/wake/mcp \
  --header "Authorization: Bearer wake_…"
```

Add `--scope user` to use it in every project. `claude mcp list` shows whether
the connection works.

### Claude Desktop

Claude Desktop starts local MCP servers from its configuration file
(*Settings* → *Developer* → *Edit Config*). Use the small bridge
[`mcp-remote`](https://www.npmjs.com/package/mcp-remote), which needs Node.js,
to pass the token:

```json
{
  "mcpServers": {
    "wake": {
      "command": "npx",
      "args": [
        "mcp-remote", "https://your-site/wake/mcp",
        "--header", "Authorization:Bearer ${WAKE_TOKEN}"
      ],
      "env": { "WAKE_TOKEN": "wake_…" }
    }
  }
}
```

Restart Claude Desktop; Wake's tools appear in the tools menu.

### claude.ai and other web connectors

Custom connectors in claude.ai sign in with OAuth and cannot send a fixed
`Authorization` header. Wake does not offer OAuth yet, so connect through
Claude Code, Claude Desktop or another client that supports headers.

### Wake on the same computer (stdio)

If the site runs on the same machine as the assistant, for example while you
develop a theme, the assistant can start Wake's stdio server itself. No web
server and no HTTPS are needed:

```bash
claude mcp add wake -- php /path/to/site/wake/artisan wake:mcp --token=wake_…
```

Instead of `--token`, you can set the environment variable `WAKE_TOKEN`.
See [`wake:mcp`](../reference/cli.md#wakemcp).

### Any other MCP client

Choose the transport *Streamable HTTP* (sometimes called just *HTTP*), enter
the address and add the header. Wake answers each request with one JSON
response and does not stream, so clients that insist on Server-Sent Events
for every call may need their HTTP mode switched on.

## 3. Check the connection

Ask the assistant: **"Call site_info on Wake."** It should answer with your
site's title, its languages and the scopes of the token. If it does, the
connection works.

## 4. Ask

Statistics (`stats:read`):

- "How was last week on my site compared with the week before?"
- "Where do the readers of my latest post come from, and how far do they read?"
- "Which pages did AI crawlers visit this month?"
- "How many visitors agreed to statistics cookies in the last 30 days?"
- "Write my weekly review." (uses the `weekly_review` prompt)

Content (`content:read`, `content:write`):

- "List my drafts."
- "Write a draft post about the summer party on 12 July, with the photo I
  attached, tagged *club life*."
- "Create an event in the category *Termine*: Christmas market, 13 December
  2026, 14:00 to 19:00, at the church square."
- "Translate the post about the summer party into English."

Publishing (`content:publish`):

- "Publish the summer party post."
- "Publish it on Friday at 18:00."

Assistants always create **drafts**. They publish only when you ask, and
never delete anything. Every entry they create is marked with an *MCP* badge
in the panel, and the history shows which token changed what.

## Working with approval mode

If you want a person to check everything before it goes online, tick
*Assistants may only ask for publishing; a person approves* under
*Settings* (the setting
[`mcp.approval`](../reference/configuration.md)). Then:

1. The assistant writes and asks to publish as usual.
2. Wake marks the entry *Waiting for approval* instead of publishing it, and
   tells the assistant so.
3. You open the entry in the panel, read it, and click **Publish**.

## Revoke a token

Open *Assistants* in the panel and click **Revoke** next to the token. It
stops working immediately. The list shows when each token was last used, which
helps to find tokens nobody needs any more.

If a token may have leaked, revoke it and create a new one.

## Troubleshooting

| Symptom | Cause and fix |
|---|---|
| **401** "A valid token is required" | The header is missing, misspelled or the token was revoked. Check `Authorization: Bearer wake_…` (with a space after *Bearer*) and create a new token if needed. |
| "This token may not use … It needs the scope …" | The token lacks that scope. Create a new token with it; scopes cannot be added to an existing token. |
| "Your role may not …" | The token acts as you, and your role does not allow it, e.g. publishing other people's entries. Ask an owner or administrator. |
| **429** Too Many Requests | More than 60 requests per minute. Wait a minute; ask the assistant to fetch less at once. |
| Connection fails or certificate errors | The address must be reachable from the assistant's computer, normally over HTTPS with a valid certificate. `http://localhost` only works for an assistant on the same machine; use the stdio server there. |
| Redirect to the sign-in page | The address is wrong (for example missing `/wake/mcp`), or a proxy strips the `Authorization` header. On Apache, Wake's `.htaccess` passes it on; check custom server configurations. |
| Works in Claude Code but not in a browser tool | Browser-based clients would need CORS and OAuth, which Wake does not offer yet. Use a desktop or command-line client. |
| The site is closed | The [closed site](../reference/configuration.md#site) setting does not affect MCP; tokens keep working. |

All tools, parameters and limits are listed in the
[MCP reference](../reference/mcp.md). How Wake keeps assistants safe is
explained in [Assistants and safety](../concepts/assistants.md).
