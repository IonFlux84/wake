---
title: Write and publish
description: Create a post in the panel, add images and cards, translate it and publish it now or later.
---

# Write and publish

1. **Start a post.** Open *Posts* and click *New post*. Type a title and start
   writing. The post is saved as a draft the first time you click
   *Save draft* (or press ⌘S / Ctrl+S).

2. **Format.** Use the toolbar or Markdown shortcuts while typing: `## ` for a
   heading, `- ` for a list, `> ` for a quote, `**bold**`. Type `/` at the start
   of an empty line for blocks: headings, lists, quotes, code, a divider, an
   image, a callout, a button or an embed.

3. **Add images.** Drop an image into the text, paste it, or choose *Image* in
   the `/` menu. Wake stores it in the post's folder. Under *Files* in the side
   panel, click an image to make it the cover.

4. **Fill in the details.** The side panel has the address (slug), tags, a short
   excerpt for lists and search engines, and, under *Search engines*, a separate
   title and description. Custom content types show the fields of their
   blueprint here.

5. **Translate.** On a multilingual site, the language switcher at the top shows
   every language. A `+` marks languages without a translation. Switch, write
   the translation, save. The post list shows each language as current,
   outdated (the original changed afterwards) or missing.

6. **Publish.** Click *Publish*. To publish later, pick a date and time under
   *Later, at* first; the button turns into *Schedule*. Scheduled posts go live
   on their own, no cron needed.

Once published, *Save* becomes *Update*, and every update is live immediately.
*Unpublish* turns the post back into a draft.

## What happens in the background

- Every save keeps the previous version as a revision.
- Changing the address of a published post adds a redirect from the old one.
- *Delete* moves the post with all languages and files to `storage/trash/`.
- If someone else has the post open, the editor says so.

The details are in [Editing](../concepts/editing.md). Who may do what is listed
in [Roles](../reference/roles.md).
