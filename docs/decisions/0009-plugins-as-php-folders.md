---
title: "0009: Plugins are PHP folders with a small interface"
description: Why Wake plugins are plain folders with a plugin.php that registers extensions, and run with full rights.
---

# 0009: Plugins are PHP folders with a small interface

- **Status:** accepted
- **Date:** 2026-10-18

## Context

Sites need things Wake will never ship: a special card, a field type, an
address that answers a form, a nightly task. Wake runs on shared hosting
without SSH or Composer, so plugins must install by copying files.

## Decision

A plugin is a folder in `site/plugins/` with a `plugin.php` that returns a
function. Wake calls it with a `Wake\Plugins\Plugin` object, which offers a
deliberately small set of extension points: cards, field types, routes,
maintenance tasks, `<head>` additions, event listeners and commands. Views,
translations, assets and panel settings follow the same conventions as themes.

Plugins run with the same rights as Wake. The panel lists them, switches them
on and off and edits their settings, but does not install them.

## Consequences

- Installing is copying a folder, by FTP or Git, like a theme.
- Wake can keep its internals free to change; only the `Plugin` methods are a
  promise to plugin authors.
- A plugin can do anything PHP can. Trust is the site owner's decision, so the
  panel says so plainly and has no one-click installation from the internet.
- A plugin that throws while starting (including syntax errors) is skipped and
  its error shown, so a broken plugin does not take the site down.

## Alternatives considered

- **Composer packages and Laravel service providers:** the most powerful, but
  needs Composer on the server or a rebuild for every plugin.
- **A sandbox or hooks only through configuration:** safer, but too weak for
  the cases above.
- **ZIP upload in the panel**, as for themes: possible later; left out for now
  because a plugin is more likely than a theme to touch data.
