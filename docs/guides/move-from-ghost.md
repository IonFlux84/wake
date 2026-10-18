---
title: Move from Ghost
description: Bring posts, pages, tags and images from a Ghost site to Wake.
---

# Move from Ghost

1. **Export from Ghost.** In your Ghost admin, open *Settings → Labs* and click
   *Export your content*. You get a JSON file.

2. **Import into Wake.** In the Wake panel, open *Settings* and find *Import
   from Ghost*. Upload the file. Enter the address of your Ghost site as well
   (for example `https://blog.example.org`), and Wake copies every image into
   the folder of its post. Without the address, images keep pointing to the old
   site.

   With SSH you can do the same on the command line:

   ```bash
   php wake/artisan wake:import:ghost ghost-export.json --url=https://blog.example.org
   ```

3. **Check the result.** Wake reports how many posts, pages, tags and images it
   imported, and lists anything it skipped.

## What Wake takes over

| Ghost | Wake |
|---|---|
| posts and pages | entries in `content/posts/` and `content/pages/`, each in a folder named after its publication date |
| status, publication date, excerpt, featured flag | front matter fields `status`, `published`, `excerpt`, `featured` |
| meta title and description | `seo.title`, `seo.description` |
| public tags | `tags`; tags with a description get a file in `content/tags/` |
| feature image | `cover`, downloaded into the post folder |
| author | `imported_author` (team members are invited separately) |
| callout, button, embed and divider cards | Wake cards (`::: callout`, `::: button`, `::: embed`, `::: divider`) |
| image cards with captions | Markdown images with the caption in italics below |
| bookmark cards | plain links |

Not imported: internal tags (`#…`), newsletters sent only by mail, members and
comments. Posts whose slug exists already are skipped, so you can run the
import again safely.

## Addresses

If your Ghost site used `/{slug}/` addresses, the default `post_url` setting
(`{slug}`) keeps them working. Wake answers without the trailing slash.
