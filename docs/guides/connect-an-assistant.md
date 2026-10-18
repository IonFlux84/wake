---
title: Connect an AI assistant
description: Let Claude or another assistant answer questions about your site.
---

# Connect an AI assistant

1. **Create a token.** In the panel, open *Assistants*, give the token a name
   such as "Claude on my laptop" and choose what it may do. For questions about
   statistics, *Read statistics* is enough.

2. **Copy the token.** Wake shows it once. It starts with `wake_`.

3. **Add Wake to your assistant.** For Claude Code:

   ```bash
   claude mcp add --transport http wake https://your-site/wake/mcp \
     --header "Authorization: Bearer wake_…"
   ```

   Other assistants need the same two things: the address
   `https://your-site/wake/mcp` and the header
   `Authorization: Bearer wake_…`.

   Running Wake on the same computer? Use the local server instead:

   ```bash
   claude mcp add wake -- php /path/to/site/wake/artisan wake:mcp --token=wake_…
   ```

4. **Ask.** For example: "How was last week on my site?", "Where do the
   readers of my latest post come from?" or "Write my weekly review."

To stop an assistant, revoke its token under *Assistants*. All tools are listed
in the [MCP reference](../reference/mcp.md).
