---
title: Your first site
description: A walk through the panel, from the first post to a site you can show people, in about twenty minutes.
---

# Your first site

You have Wake running, either [on your computer](installation.md) or
[on a web host](hosting.md), and you have created your account. This page takes
you once through everything you need for a real site.

## 1. Look around

Open `/wake`. The **dashboard** shows visitors, page views and reading time.
On a new site it is empty; it fills up as soon as people visit.

The menu on the left follows what you can do. As the owner you see
everything:

| Section | What it is for |
|---|---|
| *Posts*, *Pages*, *Media* | your content |
| *Menus*, *Categories* | how the site is organised |
| *Statistics*, *Consent* | who reads what, and the cookie banner |
| *Themes*, *Plugins*, *Assistants*, *Team*, *Backups*, *Redirects*, *Settings* | running the site |

## 2. Name your site

Open *Settings*. Under **Website** set the time zone and how post addresses
look. The site title and description live in the file `content/website.md`
(one per language):

```markdown
---
title: Heimatverein Stiepel
description: Geschichte und Gegenwart im Bochumer Süden
---
```

## 3. Write a post

*Posts* → **New post**.

1. Type the title, then the text. Formatting works with the toolbar or with
   Markdown shortcuts: `## ` for a heading, `- ` for a list, `**bold**`.
2. Drag an image into the text, or set a **header image** in the side panel.
3. Click **Publish**, or choose a date to publish it later.

Every post is a folder in `content/posts/` with a Markdown file inside. You can
open it in any text editor; Wake notices changes made outside the panel. More
in [Write and publish](../guides/write-and-publish.md).

## 4. Add pages and a menu

*Pages* → **New page**, for example "About us" and "Contact".

Pages form a tree: drag them to change the order, or onto another page to
nest them. **Add to menu order** puts a page into the main menu. The menu
updates itself; under *Menus* you can arrange it by hand and add links or a
footer menu. See [Arrange pages and menus](../guides/pages-and-menus.md).

## 5. Choose the look

*Themes* shows the installed themes. Wake comes with **Tide**. Its settings
let you pick an accent colour, show upcoming events on the home page and add
a footer text. To build your own, see
[Build a theme](../guides/build-a-theme.md).

## 6. Events, if you need them

Wake comes with a category **Events** with date, place, admission and
registration. Create a post, choose the category, fill in the date, and it
appears in the event list and in calendars. Repeating events work, too. See
[Categories and events](../guides/categories-and-events.md).

## 7. Decide about statistics and consent

Wake counts visits without cookies by default, so no banner is needed. Under
*Settings* → **Statistics** you can switch counting off or ask for consent for
more detail. Read [Analytics](../concepts/analytics.md) and
[Consent](../concepts/consent.md) before you go live; the consent page also
gives you a ready-made section for your privacy policy.

## 8. Before you go live

- [ ] Set up mail under *Settings* → **Sending mail** and send yourself a test
      mail ([Invite your team](../guides/invite-your-team.md)).
- [ ] Turn on [two-factor sign-in](../guides/two-factor.md) for your account.
- [ ] Write a [backup](../guides/backups.md) and download it.
- [ ] Still building? Switch on **Closed site** under *Settings*: visitors are
      sent to the sign-in page and search engines stay out.
- [ ] Moved to your real domain? Correct `APP_URL` in `.env`; *Settings* warns
      while it differs from the address you use.

## Next steps

- [Invite your team](../guides/invite-your-team.md) and set their
  [roles](../reference/roles.md)
- [Add a second language](../guides/second-language.md)
- [Connect an AI assistant](../guides/connect-an-assistant.md)
- [Move from Ghost](../guides/move-from-ghost.md)
