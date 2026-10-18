---
title: "0008: The application lives in its own folder"
description: Why Laravel sits in wake/ and the site next to it.
---

# 0008: The application lives in its own folder

- **Status:** accepted
- **Date:** 2026-10-18

## Context

In a normal Laravel project, application code and site data are mixed. For a
CMS that people update by uploading a ZIP, that is dangerous: one wrong folder
and the content is gone.

## Decision

The Laravel application, including `vendor/`, lives in `wake/`. Everything that
belongs to the site owner sits next to it:

```
wake/      application, replaced on update
site/      configuration, blueprints, theme
content/   content
storage/   runtime data
public/    document root
.env       secrets (application key, mail)
```

`wake/bootstrap/app.php` points Laravel's public path, storage path and
environment file to the parent folder.

## Consequences

- An update replaces exactly one folder.
- Artisan is called as `php wake/artisan …` from the site root.
- Laravel developers find a familiar application inside `wake/`.

## Alternatives considered

- **A Composer package inside a normal Laravel app.** Clean for developers, but
  updates would require Composer, which many hosts don't offer.
