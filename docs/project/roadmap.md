---
title: Roadmap
description: The six phases from prototype to public release.
---

# Roadmap

| Phase | Scope | Result |
|---|---|---|
| **0 · Prototype** ✓ | Interactive design prototype of dashboard, posts, editor, statistics, post journey and consent | The look is decided before code exists |
| **1 · Foundation** ✓ | Laravel without a database, file models, index, routing, languages, Blade themes, page cache, images, feeds, maintenance without cron, setup and sign-in | A multilingual website runs from Markdown files |
| **2 · Analytics & consent** ✓ | `hit.php`, beacon, raw data, daily and monthly sums, banner, categories, two-click embeds, consent proof, both tracking levels, dashboard and statistics, MCP server with tokens and statistics tools | The heart of Wake works, and you can already ask it questions |
| **3 · Writing** ✓ | Posts, pages, custom types, editor with cards, media, revisions, translations, scheduling, stats in the editor, roles, MCP content tools, approval mode, history | You can write and publish in the browser |
| **4 · Finish** ✓ | Default theme Tide, Ghost import, content API, updates from the panel, installation ZIP, complete documentation | **Wake 1.0** |
| **5 · Pilot** ← *next* | First real website: theme, import, move, live operation | Wake runs in production |
| **6 · Growth** | WordPress import, webhooks, OAuth for MCP, project website, public release | Wake is public |

Every phase ends with tests (Pest). Documentation is written alongside, not in
phase 4: phase 4 only fills the gaps.
