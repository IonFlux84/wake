---
title: Templates
description: How a theme is built and what its Blade templates can use.
---

# Templates

A theme is a folder in `site/themes/`. The active one is set with the `theme`
[setting](configuration.md).

```
site/themes/tide/
  theme.yml              name, version, author
  templates/             Blade templates
    layouts/default.blade.php
    home.blade.php       the start page
    post.blade.php       a post
    page.blade.php       a page
    tag.blade.php        a tag with its posts
    event.blade.php      an entry of the custom type "event"
    default.blade.php    anything without its own template
    404.blade.php        page not found
    cards/               optional looks for cards
    snippets/            your own partials
  assets/                CSS, JavaScript, fonts, images
  lang/de.json           translations of the theme's own texts
```

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
| an entry | its `template` field → its type (`post`, `page`, `tag`, `event`) → `default` |
| not found | `404` → Wake's built-in page |

## Variables

| Variable | Available | Contents |
|---|---|---|
| `$site` | always | the website (see below) |
| `$entry` | always, `null` on a start page without `pages/home` | the current entry |
| `$post`, `$page`, `$tag`, `$event` … | matching type | the same entry under its type's name |

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
| `$site->menu()` | listed top-level pages in order |
| `$site->tags()` | all tags used by published posts, most used first |
| `$site->entries('event')` | a query for any content type |
| `$site->find('posts/2026-10-17-hello')` | one entry by its folder |

## Entries

| Method | Returns |
|---|---|
| `title()`, `slug()`, `url()`, `path()` | title and address |
| `get('field', $default)` or `$entry->field` | any front matter field, also nested: `get('seo.title')` |
| `content()` | the text as HTML |
| `body()` | the text as Markdown |
| `excerpt($words = 40)` | `excerpt` field or the start of the text |
| `readingTime()` | minutes, rounded |
| `date()` | publication date as [Carbon](https://carbon.nesbot.com) or `null` |
| `modifiedAt()` | last change of the file |
| `status()`, `isPublished()`, `isDraft()`, `isScheduled()` | publication state |
| `tags()` | the entry's tags |
| `image($name = null)` | the cover image, or the named file |
| `file('name.pdf')`, `media()` | files in the entry's folder |
| `language()`, `translations()`, `translation('de')` | languages |
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
| `listed()`, `childrenOf($id)` | page tree |
| `latest()`, `oldest()`, `orderBy('title')` | order |
| `limit(5)`, `offset(10)` | slice |
| `in('de')`, `withFallback()` | language |
| `get()`, `first()`, `count()`, `exists()` | results |
| `paginate($perPage = null)` | a Laravel paginator, page from `?page=` |

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

## The head

Put `@wakeHead` into `<head>`. It writes the title, description, canonical URL,
`hreflang` links for every translation, the RSS link, Open Graph tags and
JSON-LD for posts.

```blade
<head>
    @wakeHead
    <link rel="stylesheet" href="{{ theme_asset('tide.css') }}">
</head>
```

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
a version to the address, so browsers never keep an old file.

## Cards

Wake renders [cards](content-format.md#cards) with
`templates/cards/{name}.blade.php` of the theme if it exists, then with its
built-in look. The template receives:

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
key. Translations live in `lang/{code}.json` of the theme:

```json
{ "Older posts": "Ältere Beiträge" }
```
