---
title: Templates
description: How a theme is built and what its Blade templates can use.
---

# Templates

A theme is a folder in `themes/`. The active one is set with the `theme`
[setting](configuration.md).

```
themes/tide/
  theme.yml              name, version, author
  templates/             Blade templates
    layouts/default.blade.php
    home.blade.php       the start page (required)
    post.blade.php       a post
    post-event.blade.php a post in a category with the template "event"
    page.blade.php       a page
    tag.blade.php        a tag with its posts
    category.blade.php   a category page
    event.blade.php      an entry of the custom type "event"
    landing.blade.php    any name, chosen with the template field
    default.blade.php    anything without its own template
    search.blade.php     search results
    404.blade.php        page not found
    cards/               optional looks for cards
    snippets/            your own partials
  assets/                CSS, JavaScript, fonts, images
  lang/de.json           translations of the theme's own texts
  screenshot.jpg         optional, shown in the panel
```

Only `templates/` is required, and in it `home.blade.php`: without it the
start page answers with 404. A step-by-step tutorial is in
[Build your own theme](../guides/build-a-theme.md).

## theme.yml

```yaml
name: Harbour
description: { de: Ruhig und maritim, en: Calm and maritime }
version: 1.0.0
author: Jane Doe
options:                       # shown in the panel under Themes
  accent:
    type: color                # color, text, textarea, toggle, select
    label: { de: Akzentfarbe, en: Accent colour }
    default: '#3D5AFE'
  show_tags:
    type: toggle
    label: Show tags
    default: true
```

Templates read options with `theme_option('accent')`: the value chosen in the
panel, else the default. A `screenshot.jpg` (or `.png`, `.webp`) in the theme
folder appears in the panel's theme overview.

Templates use [Blade](https://laravel.com/docs/blade). Refer to other templates
of the theme with the `theme::` prefix:

```blade
@extends('theme::layouts.default')
@include('theme::snippets.post-card', ['post' => $post])
```

## Which template is used

| Address | Templates, first match wins |
|---|---|
| `/` | `template` field of `pages/home` → `home` → `page` → `default` |
| an entry | its `template` field → `post-{category template}` (posts with a category) → its type (`post`, `page`, `tag`, `event`) → `default` |
| a category page | `category-{category template}` → `category` → Wake's built-in list |
| `/search?q=…` | `search` → Wake's built-in page |
| not found | `404` → Wake's built-in page |

When no template matches an entry, Wake renders it with a plain built-in
page. `$entry->template()` returns the `template` field, else the type.

## Variables

| Variable | Available | Contents |
|---|---|---|
| `$site` | always | the website (see below) |
| `$entry` | always, `null` on a start page without `pages/home` | the current entry |
| `$post`, `$page`, `$tag`, `$event` … | matching type | the same entry under its type's name |
| `$category` | category pages | the [category](#categories) |
| `$query`, `$results` | `search` | the search words and the matching entries |

## Helpers

| Helper | Returns |
|---|---|
| `theme_asset('style.css')` | the public address of a file in `assets/`, see [assets](#assets) |
| `theme_option('accent', $default)` | an option of the active theme: the value from the panel, else the `default` from `theme.yml`, else `$default` |
| `__('Text')`, `trans_choice('… \| …', $n)` | the theme's own texts, see [translations](#translations) |
| `app(\Wake\Localization\Languages::class)` | languages: `all()`, `codes()`, `isMultilingual()`, `current()`, `url($path, $code)` |

## `$site`

| Method | Returns |
|---|---|
| `$site->title()`, `$site->description()` | from `content/site.{lang}.md` |
| `$site->get('field')` or `$site->field` | any field of the site file |
| `$site->url($lang = null)` | address of the start page |
| `$site->language()` | code of the current language |
| `$site->languages()` | all languages, code → name |
| `$site->posts()` | published posts, newest first (a [query](#queries)) |
| `$site->pages()` | published top-level pages |
| `$site->menu($name = 'main')` | a [menu](#menus): `main` or `footer` |
| `$site->tags()` | all tags used by published posts, most used first |
| `$site->entries('event')` | a query for any content type |
| `$site->find('posts/hello-world')` | one entry by its folder |

## Menus

`$site->menu()` returns the main menu, `$site->menu('footer')` the footer
menu, as a collection of menu items. Without a hand-made main menu, the main
menu shows the top-level pages with `menu: true`, with their sub-pages that
have `menu: true`.

| Method | Returns |
|---|---|
| `title()` | the label, or the page title |
| `url()` | the address |
| `page()` | the page entry, `null` for links |
| `children()` | the sub-items (one level) |
| `isActive()` | whether the current address is this page or below it |
| `isExternal()` | whether the link leads to another site |

```blade
@foreach ($site->menu('footer')->whenEmpty(fn () => $site->menu()) as $item)
    <a href="{{ $item->url() }}">{{ $item->title() }}</a>
@endforeach
```

## Entries

| Method | Returns |
|---|---|
| `title()`, `slug()`, `url()`, `path()` | title and address |
| `template()` | the `template` field, else the type |
| `get('field', $default)` or `$entry->field` | any front matter field, also nested: `get('seo.title')` |
| `content()` | the text as HTML |
| `body()` | the text as Markdown |
| `excerpt($words = 40)` | `excerpt` field or the start of the text |
| `readingTime()` | minutes, rounded |
| `date()` | publication date as [Carbon](https://carbon.nesbot.com) or `null` |
| `modifiedAt()` | last change of the file |
| `status()`, `isPublished()`, `isDraft()`, `isScheduled()` | publication state |
| `tags()` | the entry's tags |
| `image($name = null)` | the header image (`cover`), or the named file |
| `thumbnail()` | the preview image (`thumbnail`), or else the header image |
| `file('name.pdf')`, `media()` | files in the entry's folder |
| `language()`, `translations()`, `translation('de')`, `hasTranslation('de')` | languages: `translations()` lists the codes, `translation()` returns the entry in that language or `null` |
| `requestedLanguage()` | the language of the address, which differs from `language()` for a fallback |
| `isFallback()` | `true` when shown in the default language because a translation is missing |
| `parent()`, `children()` | page tree |
| `id()`, `type()` | folder below `content/` and content type |

Pages also have `isHome()` and `isListed()`. Tags have `posts()` and
`isVirtual()` (no file in `content/tags/`).

## Queries

Queries work like Laravel's query builder and can be looped over directly:

```blade
@foreach ($site->posts()->tag('travel')->limit(3) as $post)
    <a href="{{ $post->url() }}">{{ $post->title() }}</a>
@endforeach
```

| Method | Effect |
|---|---|
| `published()`, `drafts()`, `scheduled()`, `any()` | by status |
| `tag('travel')`, `author('dennis')` | by tag or author |
| `where('location', 'Bochum')`, `where('price', '>', 10)` | by field. Operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `in`, `contains` |
| `listed()` (pages with `menu: true`), `childrenOf($id)` | page tree, in `order` |
| `latest()`, `oldest()`, `orderBy('title')` | order |
| `limit(5)`, `offset(10)` | slice |
| `in('de')`, `withFallback()` | language |
| `get()`, `first()`, `count()`, `exists()` | results |
| `paginate($perPage = null)` | a Laravel paginator, page from `?page=` |

A paginator loops like a query and offers `hasPages()`, `currentPage()`,
`lastPage()`, `previousPageUrl()` and `nextPageUrl()`. Without an argument it
takes `per_page` entries ([configuration](configuration.md)).

In PHP code the same queries start from the models:
`Wake\Content\Models\Post::published()->latest()->get()`.

## Images

```blade
{{ $post->image()->picture($post->title(), '(min-width: 960px) 960px, 100vw') }}
```

| Method | Returns |
|---|---|
| `picture($alt, $sizes)` | `<picture>` with a WebP source and a responsive `<img>` |
| `img($alt, $sizes, $attributes)` | responsive `<img>` with `srcset`, `width`, `height`, `loading="lazy"` |
| `url($width = null, $format = null)` | address of the original or a resized version |
| `srcset($format = null)` | the `srcset` value |
| `width()`, `height()`, `isImage()`, `filesize()`, `name()` | file details |
| `alt($language = null)`, `caption($language = null)`, `credit()` | details from the file's sidecar file, `''` when not set |
| `focus()` | the focus point as a CSS position, `50% 50%` by default |
| `meta()` | everything in the sidecar file |

`img()` and `picture()` use the alt text from the details when there is one,
and add `object-position` for a focus point, so cropped images
(`object-fit: cover`) keep the important part in view. See
[file details](content-format.md#file-details).

## The head

Put `@wakeHead` into `<head>`. It writes `<meta charset>`, the viewport, the
title, description, canonical URL, `hreflang` links for every translation, the
RSS link, Open Graph tags, JSON-LD for posts and events, the consent manager,
the statistics script and anything [plugins](plugins.md) add. Do not write
these tags yourself.

Editors can adjust it per entry with front matter fields:

| Field | Effect |
|---|---|
| `seo.title` | replaces the `<title>` (default: entry title · site title) |
| `seo.description` | replaces the description (default: the excerpt) |
| `noindex: true` | adds `<meta name="robots" content="noindex">` |
| `thumbnail` / `cover` | the image for Open Graph |

```blade
<head>
    @wakeHead
    <link rel="stylesheet" href="{{ theme_asset('tide.css') }}">
</head>
```

## Categories

| Address | Templates, first match wins |
|---|---|
| a post with a category | its `template` field → `post-{template of the category}` → `post` → `default` |
| the category page | `category-{template}` → `category` → Wake's built-in list |

The category page receives `$category`:

| Method | Returns |
|---|---|
| `title()`, `description()`, `slug()`, `url()` | in the current language |
| `posts()` | published posts: upcoming first for date categories, else newest first |
| `past()` | posts whose date has passed (date categories) |
| `fields()`, `label('start')` | field definitions and labels |
| `template()`, `dateField()`, `icon()` | settings |

For recurring events, `$category->occurrences($from, $to)` returns one row
per date (`entry`, `start`, `end`), earliest first. On a post,
`$post->recurrence()` gives the rule (`isRecurring()`, `between()`, `next()`,
`last()`, `describe()`), and `$post->nextOccurrence()` the next date with
`start` and `end`. `upcoming()` and `past()` in queries take repetitions into
account; a series without end never becomes past.

On posts, `$post->category()` returns the category, and the category's fields
are available like any other field: `$post->start`, `$post->get('location')`.
In queries: `$site->posts()->category('termine')->upcoming('start')`, or
`->past('start')`. `$site->categories()` lists all categories.

Every post with a `start` field also has a calendar file at `{address}.ics`,
and `@wakeHead` adds schema.org `Event` data for it.

## Search

`/search?q=…` (and `/de/search?q=…` per language) renders the theme's
`search.blade.php` with `$query` and `$results`, a list of published entries
that contain every word of the query, best matches first. Wake keeps a small
search index per language in `temp/cache/search-{lang}.php` and rebuilds it
when content changes. Search pages are never cached and carry `noindex`.
The address `search` is therefore reserved; a page with the slug `search` is
not reachable.

## Consent and privacy

| Directive | Output |
|---|---|
| `@wakeConsentLink` | a "Cookie settings" link that opens the consent settings. Put it in the footer |
| `@wakeServices` | a section for the privacy policy listing Wake's statistics, cookies and embedded services |

`@wakeHead` already loads the consent manager and the tracking script. See
[consent](../concepts/consent.md).

## Assets

`theme_asset('tide.css')` returns the public address of a file in `assets/`.
Wake copies changed files to `public/assets/themes/{theme}/` by itself and adds
a version to the address, so browsers never keep an old file. Inside CSS, refer
to other assets with relative paths (`url(../fonts/inter.woff2)`): when Wake
copies a stylesheet, it copies the files it points to as well, as long as they
stay inside `assets/`.

## Cards

Wake renders a [card](content-format.md#cards) with the first view that
exists: the theme's `templates/cards/{name}.blade.php`, a view registered by a
[plugin](plugins.md), Wake's built-in look (`callout`, `button`, `divider`,
`embed`), else a plain `<div class="card card-{name}">`. So a theme can
restyle built-in cards and add new ones. The template receives:

| Variable | Contents |
|---|---|
| `$name` | name of the card |
| `$value` | free text after the name |
| `$attributes` | `key="value"` settings |
| `$content` | the card's content as HTML |

```blade
{{-- templates/cards/callout.blade.php --}}
<aside class="callout">{{ $value }} {{ $content }}</aside>
```

## Translations

Texts of the theme itself go through Laravel's `__()` helper with English as the
key. Translations live in `lang/{code}.json` of the theme; the language of the
page decides which one is used:

```json
{ "Older posts": "Ältere Beiträge" }
```
