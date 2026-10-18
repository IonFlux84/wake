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

3. **Add images to the text.** Click *Image* in the toolbar: upload a new image
   or pick one the post already has, and describe it for people who cannot see
   it. Dropping or pasting an image into the text works as well, and so does
   *Image* in the `/` menu. Wake stores every image in the post's folder.

4. **Choose a header image and a preview image.** In the side panel, the
   *header image* appears large at the top of the post. The *preview image* is
   used in lists of posts and when the post is shared; without one, Wake uses
   the header image. Upload a new image or pick one from the post's files.
   You can upload before the post has a title; Wake then saves it as a draft
   called "Untitled".

5. **Fill in the details.** The side panel has the address (slug), tags, a short
   excerpt for lists and search engines, and, under *Search engines*, a separate
   title and description. Custom content types show the fields of their
   `type.yml` here.

6. **Translate.** On a multilingual site, the language switcher at the top shows
   every language. A `+` marks languages without a translation. Switch, write
   the translation, save. The post list shows each language as current,
   outdated (the original changed afterwards) or missing.

7. **Publish.** Click *Publish*. To publish later, pick a date and time under
   *Later, at* first; the button turns into *Schedule*. Scheduled posts go live
   on their own, no cron needed.

**Preview.** *Preview* saves the entry and opens it on the website in your
theme, also while it is a draft. *Share a preview link* in the side panel gives
you an address that works for seven days without an account, for example to
let someone proofread. Previews carry a "Preview" band and are hidden from
search engines.

Once published, *Save* becomes *Update*, and every update is live immediately.
*Unpublish* turns the post back into a draft.

## What happens in the background

- Every save keeps the previous version as a revision. The *Versions* tab in
  the side panel lists them and puts an earlier one back with one click.
- Changing the address of a published post adds a redirect from the old one.
- *Delete* moves the post with all languages and files to `data/trash/`.
- If someone else has the post open, the editor says so.

The details are in [Editing](../concepts/editing.md). Who may do what is listed
in [Roles](../reference/roles.md).
