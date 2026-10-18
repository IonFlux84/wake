---
title: "0003: Laravel 13 with Livewire for the panel"
description: Why Wake is a Laravel application and the panel is server-rendered.
---

# 0003: Laravel 13 with Livewire for the panel

- **Status:** accepted
- **Date:** 2026-10-17

## Context

Wake needs routing, validation, localisation, mail, a template engine and an
ecosystem of maintained packages, including the official `laravel/mcp`.

## Decision

Wake is a Laravel 13 application on PHP 8.4 or newer. The panel is built with
Livewire 3, Alpine.js and Blade components. JavaScript is only used where it
pays off: the editor (Tiptap) and interactive charts.

## Consequences

- Contributors only need PHP to work on almost every part of Wake.
- Built assets are shipped, so a server never needs Node.
- We follow Laravel's release cycle.
- PHP 8.4 is the minimum, because Laravel 13's current dependencies
  (Symfony 8, Pest 5) require it. Common shared hosts offer 8.4.

## Alternatives considered

- **A framework-free core like Kirby.** Lighter, but we would rebuild what
  Laravel already does well.
- **An SPA panel (Vue or React).** More JavaScript to build, ship and maintain,
  and a second language for contributors.
