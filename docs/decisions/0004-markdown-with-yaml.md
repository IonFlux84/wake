---
title: "0004: Content as Markdown with YAML front matter"
description: The file format for everything in content/.
---

# 0004: Content as Markdown with YAML front matter

- **Status:** accepted
- **Date:** 2026-10-17

## Context

Content has to stay readable without Wake, in any editor, in a Git diff and in
ten years.

## Decision

Every entry is a folder. Each language is one file in it (`index.en.md`,
`index.de.md`) with YAML front matter for fields and Markdown for the body.
Rich elements such as callouts or galleries are stored as fenced blocks
(`::: callout … :::`) that stay readable as plain text.

## Consequences

- Static site generators, Obsidian and plain editors can read Wake content.
- Fields are typed by blueprints, not by the file, so the format stays simple.

## Alternatives considered

- **Kirby's text format** (`Title: …` separated by `----`). Readable, but no
  other tool understands it.
- **JSON blocks** like Gutenberg or Ghost's Lexical. Precise, but unreadable.
