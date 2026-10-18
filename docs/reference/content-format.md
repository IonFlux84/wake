---
title: Content format
description: How posts, pages, categories, tags, custom content and media are stored in content/.
---

# Content format

Everything on your website is a Markdown file in `content/`. You can create and
edit these files with any text editor, upload them via FTP or keep them in Git.
Wake notices changes on the next page view.

**Every entry is a folder**: pages, posts, categories, tags and custom types.
Folder names are lowercase words joined by hyphens, without numbers or dates,
and the folder name is the address
([decision 0011](../decisions/0011-one-folder-per-entry.md)).

```
content/
  website.md                     site title and description (default language)
  website.de.md                  … and per further language
  pages/
    home/content.md              the start page (/)
    about/                       /about
      content.md                 front matter and text, default language
      content.de.md              the German translation
      style.css                  optional design for this page only
      team/content.md            a sub-page: /about/team
    imprint/content.md           /imprint
  posts/
    hello-world/content.md       /hello-world
  categories/
    events/content.md            a category, see below
  tags/
    travel/content.md            optional details for the tag "travel"
  events/                        a custom content type (see below)
    type.yml                     its fields and address pattern
    spring-fair/content.md
  media/                         all images and documents, see below
    posts/hello-world/cover.jpg
    documents/statutes.pdf
```

## A file

```markdown
---
title: Hello world
slug: hello-world
status: published
published: 2026-10-17 09:00
tags: [travel, ruhr]
cover: media/posts/hello-world/cover.jpg
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
| `slug` | Last part of the address. Without it, Wake uses the folder name. Each language can have its own slug. |
| `status` | `draft` (only visible in the panel), `scheduled` (visible from `published` on) or `published` (default). |
| `published` | Date and time of publication, e.g. `2026-10-17 09:00`, in the site's [time zone](configuration.md). The panel fills it in when you publish. |
| `tags` | List of tags. Written in any spelling, `Ruhr Area` and `ruhr-area` are the same tag. |
| `cover` | The header image, shown large at the top: `media/…`, or a file name in the entry's own media folder. |
| `thumbnail` | The preview image for lists, cards and social media. Without it, the header image is used. |
| `order` | Pages: position among their siblings in the page tree (1, 2, 3 …). Pages without it come after, by name. The panel writes it when you drag pages. |
| `menu` | Pages: `true` puts the page into the automatic main menu. |
| `excerpt` | Short summary for lists, feeds and search engines. Without it, Wake takes the start of the text. |
| `template` | Render this entry with another template of the theme, e.g. `template: landing`. |
| `seo.title`, `seo.description` | Override title and description for search engines. |
| `noindex` | `true` keeps the entry out of the sitemap and asks search engines not to index it. |

Any other field is available in templates as well: `location: Town hall` is
`$entry->location`.

## Folders and files

- A folder with a `content.md` (or `content.<lang>.md`) is an entry. The id of
  the entry is its path below `content/`, e.g. `pages/about/team`.
- Folder names: `a-z`, `0-9` and hyphens. Write `annual-meeting-2026` rather
  than a date in front; the date of a post is `published:`.
- `pages/home` is the start page, shown at `/`.
- Sub-folders of a page are its sub-pages. Posts, tags and custom entries have
  no sub-entries.
- The order of pages and the automatic main menu come from `order:` and
  `menu: true` in `content.md`. Unless `settings/menus.yml` has a main menu,
  top-level pages with `menu: true` and their sub-pages with `menu: true` make
  up `$site->menu()`.
- Moving a page in the panel writes `order:`; only moving it under another
  page renames the folder, and Wake then writes a redirect from the old address.
- Other files in an entry's folder are ignored, apart from `style.css`.

### Design of a single page: `style.css`

A `style.css` next to `content.md` applies to that entry only. Everyone who may
edit the text may edit it, in the editor under *Design* or by FTP. Wake loads
it only on that page and limits it with `@scope (main)` to the content, so the
header, menu and footer of the theme stay as they are. Wake cleans it on saving
and again on output:

- `@import`, `@charset` and `@namespace` are removed,
- `url()` works only for the media library: `url(media/posts/x/wave.svg)`
  becomes the address of that file; anything else becomes `none`,
- `image-set()`, `expression()`, `behavior` and `-moz-binding` are removed,
- `<` is escaped and braces are balanced, so nothing ends the `<style>` element
  or escapes the scope,
- at most 64 KB are read.

```css
/* content/pages/about/style.css */
h2 { letter-spacing: .02em; }
.callout { background: url(media/pages/about/wave.svg) no-repeat right; }
```

## Menus

`settings/menus.yml` holds menus arranged in the panel (*Menus*). Wake knows
`main` and `footer`. Without `main`, the main menu follows the page tree.

```yaml
main:
  - page: pages/about
    children:
      - page: pages/about/team
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

Each language is one file in the entry's folder: `content.md` for the default
language and `content.<code>.md` for the others, e.g. `content.de.md`. The codes
are the ones from the `languages` [setting](configuration.md). The folder,
`style.css` and the media are shared by all languages.

- The default language has no prefix in the URL (`/about`), the others do
  (`/de/ueber-uns`).
- Give translations their own `slug` for readable addresses in every language.
- Files in languages the site doesn't have are ignored.
- Fields missing in a translation are taken from the default language. That is
  handy for values like `start` dates, which are the same in every language.

## Media

All images and documents live in one library, `content/media/`, with
sub-folders as you like (`media/documents/`, `media/summer-fest-2026/`). The
editor uploads to `media/<entry id>/`, e.g. `media/posts/harbour/`. Refer to a
file with its path starting at `media/`, or, for files in the entry's own media
folder, by its name:

```markdown
![The harbour at night](media/posts/harbour/harbour.jpg)
[Download the statutes](media/documents/statutes.pdf)
![](boats.jpg)          same as media/posts/harbour/boats.jpg in posts/harbour
```

- Uploading a name that is taken in that folder gives it `-2`.
- The media library in the panel shows which entries use a file, lists unused
  files and warns before deleting one in use. Only people who may delete every
  entry may delete a file that is used.
- Media of drafts are not hidden; their addresses carry a hash and are hard to
  guess.

Wake turns these references into public URLs. Images are resized on first use to the
widths in `images.widths` and converted to WebP for browsers that support it.

### File details

Alt text, caption, credit and focus point of a file live in a small YAML file
next to it, named after the file plus `.yml`. The panel writes it under
*Media* when you click a file; you can also write it by hand.

```yaml
# content/media/posts/harbour/harbour.jpg.yml
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

Each category is a folder in `content/categories/` (or made in the panel under
*Categories*). The folder name is its key. `content.md` holds the fields in the
default language, `content.<lang>.md` the translated title, slug and
description:

```yaml
# content/categories/termine/content.md
---
title: Events                            # name in the default language
slug: events                             # address of the category page
description: What happens when and where.
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
---
```

```yaml
# content/categories/termine/content.de.md
---
title: Termine
slug: termine
description: Was wann und wo stattfindet.
---
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
`/tag/{slug}`. A folder `content/tags/{slug}/` with a `content.md` is optional
and adds a nicer title, a description in its body or another slug.

## Custom content types

A folder in `content/` with a `type.yml` is a new content type. The folder is
where its entries live:

```yaml
# content/events/type.yml
name: event               # optional, the folder name otherwise
title: Events
url: events/{slug}
fields:
  start:    { type: datetime, required: true }
  location: { type: text }
```

Entries then live in `content/events/` exactly like posts, and are available as
`$site->entries('event')` in templates. `url` accepts the same placeholders as
`post_url`. Without `url`, the address is `{folder}/{slug}`.

The `fields` describe the form in the panel. A `type.yml` in `content/posts/`,
`content/pages/` or `content/tags/` adds fields to the built-in types.
