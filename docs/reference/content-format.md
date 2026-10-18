---
title: Content format
description: How posts, pages, tags and custom content are stored in content/.
---

# Content format

Everything on your website is a Markdown file in `content/`. You can create and
edit these files with any text editor, upload them via FTP or keep them in Git.
Wake notices changes on the next page view.

```
content/
  site.en.md                     site title and description per language
  site.de.md
  posts/
    2026-10-17-hello-world/      one folder per post
      index.en.md                one file per language
      index.de.md
      cover.jpg                  media, shared by all languages
  pages/
    home/index.en.md             the start page (/)
    1_about/                     /about, listed in the menu as no. 1
      index.en.md
      1_team/index.en.md         /about/team
    imprint/index.en.md          /imprint, not in the menu
  tags/
    travel.en.md                 optional details for the tag "travel"
  events/                        a custom content type (see below)
    2026-05-01-spring-fair/index.en.md
```

## A file

```markdown
---
title: Hello world
slug: hello-world
status: published
published: 2026-10-17 09:00
tags: [travel, ruhr]
cover: cover.jpg
excerpt: Why I built my own CMS.
---

The first paragraph …
```

The part between the `---` lines is the **front matter** in
[YAML](https://yaml.org). Below it comes the text in Markdown.

### Fields every entry understands

| Field | Description |
|---|---|
| `title` | Title of the entry. Without it, Wake uses the folder name. |
| `slug` | Last part of the address. Without it, Wake uses the folder name without date or number. Each language can have its own slug. |
| `status` | `draft` (only visible in the panel), `scheduled` (visible from `published` on) or `published` (default). |
| `published` | Date and time of publication, e.g. `2026-10-17 09:00`, in the site's [time zone](configuration.md). Posts without it use the date from the folder name. |
| `tags` | List of tags. Written in any spelling, `Ruhr Area` and `ruhr-area` are the same tag. |
| `cover` | File name of the header image in the same folder, shown large at the top. |
| `thumbnail` | File name of the preview image for lists, cards and social media. Without it, the header image is used. |
| `excerpt` | Short summary for lists, feeds and search engines. Without it, Wake takes the start of the text. |
| `template` | Render this entry with another template of the theme, e.g. `template: landing`. |
| `seo.title`, `seo.description` | Override title and description for search engines. |
| `noindex` | `true` keeps the entry out of the sitemap and asks search engines not to index it. |

Any other field is available in templates as well: `location: Town hall` is
`$entry->location`.

## Folder names

| Type | Folder name | Meaning |
|---|---|---|
| Posts | `2026-10-17-hello-world` | Date (optional) and name. The date is used when `published` is missing. |
| Pages | `1_about` | Number (optional) and name. Pages with a number are *listed*, in that order. Unless `site/menus.yml` has a main menu, listed top-level pages and their listed sub-pages make up `$site->menu()`. |
| Pages | `home` | The start page, shown at `/`. |

Only folders with an `index.*.md` file count as entries. Other folders, such as
an `images/` folder inside a post, are ignored.

## Menus

`site/menus.yml` holds menus arranged in the panel (*Menus*). Wake knows
`main` and `footer`. Without `main`, the main menu follows the page tree.

```yaml
main:
  - page: pages/1_about
    children:
      - page: pages/1_about/1_team
  - page: pages/contact
    label: { en: Write to us, de: Schreib uns }
  - label: Shop
    url: https://shop.example.org
footer:
  - page: pages/imprint
```

- `page` is the folder of a page below `content/`. Wake updates it when the
  page moves in the page tree.
- `label` is optional for pages (their title is used) and required for links;
  it is a text or one text per language.
- `children` holds one level of sub-items.
- Pages that are not published are left out.
- Changes made by hand are noticed like changes to content: on the next
  uncached request, then the page cache is cleared.

See [Arrange pages and menus](../guides/pages-and-menus.md).

## Languages

Each language is one file: `index.en.md`, `index.de.md`. The codes are the ones
from the `languages` [setting](configuration.md). A single-language site may
simply use `index.md`.

- The default language has no prefix in the URL (`/about`), the others do
  (`/de/ueber-uns`).
- Give translations their own `slug` for readable addresses in every language.
- Files in languages the site doesn't have are ignored.
- Fields missing in a translation are taken from the default language. That is
  handy for values like `start` dates, which are the same in every language.

## Media

Images and other files sit next to the text files and are shared by all
languages. Refer to them by file name:

```markdown
![The harbour at night](harbour.jpg)
[Download the programme](programme.pdf)
```

Wake turns these into public URLs. Images are resized on first use to the
widths in `images.widths` and converted to WebP for browsers that support it.

### File details

Alt text, caption, credit and focus point of a file live in a small YAML file
next to it, named after the file plus `.yml`. The panel writes it under
*Media* when you click a file; you can also write it by hand.

```yaml
# content/posts/2026-10-17-harbour/harbour.jpg.yml
alt:
  en: Fishing boats in the harbour at night
  de: Fischerboote nachts im Hafen
caption: The old harbour, October 2026
credit: "Photo: Jane Doe"
focus: 30% 60%
```

- `alt` and `caption` are a text, or one text per language.
- `focus` is the most important spot of an image, from the left and from the
  top. Themes crop images around it (CSS `object-position`). Without it, the
  middle (`50% 50%`) is used.
- An image in Markdown without its own alt text (`![](harbour.jpg)`) gets the
  alt text from the details.
- Deleting the file in the panel moves its details to the trash with it.

## Cards

Cards are richer blocks inside the text. In the file they stay readable:

```markdown
::: callout 💡
Text inside the card, with **Markdown**.
:::

::: button href="/join"
Become a member
:::

::: divider
:::
```

A card starts with `:::` followed by its name and optional settings
(`key="value"` and free text), and ends with a line containing only `:::`.
Cards cannot be nested.

| Card | Settings | Result |
|---|---|---|
| `callout` | an emoji or icon | highlighted box |
| `button` | `href`, label as text | link styled as a button |
| `divider` | | horizontal line |
| `embed` | the address of a video, map or post, caption as text | two-click embed, see [consent](../concepts/consent.md#two-click-embeds) |

Any other name becomes `<div class="card card-{name}">`, and a theme can give it
its own look with a template in `templates/cards/{name}.blade.php`
(see [templates](templates.md#cards)).

## Categories

A post can have one category, given by its key:

```markdown
---
title: Summer fest
category: termine
start: 2026-07-04 15:00
location: Park
---
```

Categories are defined in `site/categories/{key}.yml` (or in the panel under
*Categories*):

```yaml
title: { de: Termine, en: Events }       # name per language
slug: { de: termine, en: events }        # address of the category page
description: { de: Was wann und wo stattfindet., en: What happens when and where. }
icon: calendar
template: event                          # post-event and category-event templates
url: '{category}/{slug}'                 # optional: addresses below the category
list: upcoming                           # newest (default) or upcoming
date_field: start                        # for "upcoming"
fields:
  start:
    type: datetime                       # text, textarea, number, toggle, date, datetime, select, url, email
    label: { de: Beginn, en: Start }
    required: true                       # needed before publishing
    translate: false                     # one value for all languages
  event_status:
    type: select
    options:
      scheduled: { de: Findet statt, en: Takes place }
      cancelled: { de: Abgesagt, en: Cancelled }
```

A field with `list: true` (type `textarea`) is edited one item per line and
stored as a YAML list.

### Recurring events

Any entry with a `start` field can repeat:

```yaml
start: 2026-11-03 19:00
end: 2026-11-03 21:00          # the duration applies to every date
repeat: weekly                 # weekly, biweekly, monthly_date, monthly_weekday, yearly
repeat_until: 2027-06-29       # optional
repeat_except: [2026-12-22, 2026-12-29]
```

`monthly_weekday` takes the weekday and its position from `start`: a start on
the second Tuesday repeats on every second Tuesday; a start on the last Tuesday
of a month repeats on every last Tuesday. `monthly_date` skips months that are
too short (no 31 February).

Values are stored in the post's front matter: dates as `2026-07-04 15:00`,
yes/no fields as `true`. Fields with `translate: false` are written in the
default language only; translations read them from there.

`url` accepts `{category}` (the category's address in the entry's language),
`{slug}`, `{year}`, `{month}` and `{day}`. Without it, posts use `post_url`.

The category page lives at the category's address, e.g. `/termine`. Pages and
posts with the same address win.

## Tags

A tag exists as soon as a published post uses it, and gets a page at
`/tag/{slug}`. A file `content/tags/{slug}.{lang}.md` is optional and adds a
nicer title, a description in its body or another slug.

## Custom content types

A blueprint in `site/blueprints/` with a `folder` creates a new content type:

```yaml
# site/blueprints/event.yml
title: Events
folder: events
url: events/{slug}
fields:
  start:    { type: datetime, required: true }
  location: { type: text }
```

Entries then live in `content/events/` exactly like posts, and are available as
`$site->entries('event')` in templates. `url` accepts the same placeholders as
`post_url`. Without `url`, the address is `{folder}/{slug}`.

The `fields` describe the form in the panel (phase 3). Blueprints named `post`,
`page` or `tag` add fields to the built-in types.
