---
title: "0005: Analytics and consent are part of the core"
description: Why statistics and the consent banner are not plugins.
---

# 0005: Analytics and consent are part of the core

- **Status:** accepted
- **Date:** 2026-10-17

## Context

Most site owners add Google Analytics and a third-party consent banner, which
sends visitor data elsewhere and slows the site down. Wake knows the content, so
it can show statistics where they matter: next to each post.

## Decision

Wake measures page views itself, in two levels:

1. **Without cookies**, always on: a daily rotating, salted hash of IP address,
   user agent and site. The IP address is never stored.
2. **With a cookie**, only after consent: returning visitors and sessions.

The consent manager is part of the core, treats "Decline" exactly like "Accept",
blocks external embeds until consent and keeps proof of every decision.

## Consequences

- No visitor data leaves the server.
- The beacon (`public/hit.php`) must stay tiny and must not boot Laravel.
- Wake takes on legal responsibility for sensible defaults. A legal review of
  level 1 is required before the first live site.

## Alternatives considered

- **Plausible, Matomo or Umami as recommended add-ons.** Good tools, but a
  second system to run, and they can't show stats inside the editor.
