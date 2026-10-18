---
title: Writing documentation
description: How we write the Wake docs, and what the documentation tests check.
---

# Writing documentation

Wake is for people who run a club website on a Sunday afternoon as much as for
developers. Write for the first group, and the second will be fine too.

## When to write

In the same pull request as the code. The table in
[CONTRIBUTING.md](../../CONTRIBUTING.md) lists which page belongs to which kind
of change.

## Where to write

| Folder | Kind of page | Example |
|---|---|---|
| `getting-started/` | a path from zero to a working site | Installation |
| `guides/` | one task, step by step | Add a second language |
| `reference/` | complete, exact and dry | Every setting with its default |
| `concepts/` | explanation, the "why" | How the index works without a database |
| `decisions/` | one decision per file, never edited afterwards | 0002 No database |

Don't mix them. A guide links to the reference instead of repeating it.

## How to write

- **Short sentences, active voice.** "Wake writes the file" instead of "the
  file is written".
- **Concrete examples.** Show a real file, a real path, a real command.
- **Say what the reader gets.** Start each page with one sentence about what it
  is for.
- **Name things the way the panel does.** If the button says *Publish*, the docs
  say *Publish*.
- **No filler.** Leave out "simply", "just" and "easy". If it were easy, nobody
  would read the docs.
- **Code blocks get a language** (`bash`, `php`, `yaml`, `markdown`, `blade`).

## Front matter

Every page starts with front matter, because the docs will be served by Wake
itself:

```yaml
---
title: Writing documentation
description: One sentence that also works as the search result.
---
```

## Decision records

When we decide something fundamental, we add a numbered file to
[decisions/](../decisions/README.md) using the
[template](../decisions/template.md). Records are never rewritten. If a decision
changes, a new record supersedes the old one and both link to each other.

## What the tests check

`wake/tests/Docs` runs with `composer test` and fails when

- a page has no `title` in its front matter,
- a relative link points to a file that does not exist,
- a key in `wake/config/wake.php` is missing from
  [the configuration reference](../reference/configuration.md),
- a `wake:` Artisan command is missing from [the CLI reference](../reference/cli.md).
