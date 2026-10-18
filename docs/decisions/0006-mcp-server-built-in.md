---
title: "0006: A built-in MCP server"
description: Why Wake speaks the Model Context Protocol out of the box.
---

# 0006: A built-in MCP server

- **Status:** accepted
- **Date:** 2026-10-17

## Context

People increasingly ask an AI assistant instead of clicking through a dashboard:
"How did last week's post do?" or "Draft a post about Saturday's event."

## Decision

Wake ships an MCP server based on `laravel/mcp`, over Streamable HTTP at
`/wake/mcp` and over stdio via `php wake/artisan wake:mcp`. Every team member
creates their own tokens with scopes (`stats:read`, `content:read`,
`content:write`, `content:publish`).

## Consequences

- Assistants never delete content, new content always starts as a draft, and
  every change made through MCP is marked as such.
- Content returned to an assistant is marked as data, as protection against
  prompt injection.
- OAuth was postponed, because Laravel Passport needs a database (see 0002).
  Since 18 October 2026 Wake has its own file-based OAuth for MCP
  ([reference](../reference/mcp.md#oauth)).

## Alternatives considered

- **Only a REST API.** Assistants could use it, but every user would have to
  wire it up themselves.
