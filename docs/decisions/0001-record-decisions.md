---
title: "0001: We record architecture decisions"
description: Why the decisions folder exists.
---

# 0001: We record architecture decisions

- **Status:** accepted
- **Date:** 2026-10-18

## Context

Wake will be open source. People who join later need to know why things are the
way they are, otherwise every decision gets argued again, or quietly undone.

## Decision

Every fundamental decision gets a short, numbered record in `docs/decisions/`,
written in the same pull request that implements it.

## Consequences

- Newcomers can read the reasoning instead of guessing it.
- Changing a decision means writing down why, which keeps the bar sensible.

## Alternatives considered

- **Decisions only in issues or chat.** Easy to write, impossible to find later.
