---
title: Build your own theme
description: A step-by-step tutorial for a Wake theme, from an empty folder to a ZIP you can install in the panel.
---

# Build your own theme

A theme decides how your website looks. Content, menus and settings stay the
same when you switch themes. This tutorial builds a small theme called
*Harbour* step by step. Every method it uses is listed in the
[templates reference](../reference/templates.md).

You need a local Wake ([installation](../getting-started/installation.md)),
a text editor and some HTML and CSS. Themes use
[Blade](https://laravel.com/docs/blade), Laravel's template language:
`{{ $value }}` prints a value (escaped), `@if`, `@foreach` and `@include`
work as you would expect.

## 1. Start: copy Tide or begin empty

**Copy Tide** when you want to change an existing look. In the panel, open
*Themes* and click **Duplicate** next to Tide. Wake copies it to a new folder
named after the copy, for example `site/themes/tide-copy/`. Wake updates never
touch your copy.

**Begin empty** when you want to learn how a theme works, as this tutorial
does. Create the folder `site/themes/harbour/` with this layout:

```
site/themes/harbour/
  theme.yml                 name, description, options
  screenshot.jpg            optional, shown in the panel
  templates/
    layouts/default.blade.php
    home.blade.php          the start page
    post.blade.php          a post
    page.blade.php          a page
    tag.blade.php           a tag with its posts
    404.blade.php           page not found
    cards/                  optional, your own looks for cards
    snippets/               optional, your own partials
  assets/
    harbour.css
  lang/
    de.json                 translations of the theme's own texts
```

Only `templates/` is required. The folder name (`harbour`) is how Wake knows
the theme; use lowercase letters, digits and dashes.

## 2. theme.yml

```yaml
name: Harbour
description:
  en: Calm and maritime, with one accent colour.
  de: Ruhig und maritim, mit einer Akzentfarbe.
version: 1.0.0
author: Jane Doe
license: MIT

options:
  accent:
    type: color
    label: { en: Accent colour, de: Akzentfarbe }
    default: '#3D5AFE'
  show_tags:
    type: toggle
    label: { en: Tags on the start page, de: Schlagwörter auf der Startseite }
    default: true
  footer_text:
    type: text
    label: { en: Footer text, de: Text im Footer }
    placeholder: { en: e.g. a short line about the site, de: z. B. eine Zeile über die Seite }
```

Texts can be one string or one per language; the panel shows the one for its
own language. A `screenshot.jpg`, `.png` or `.webp` next to `theme.yml`
appears in the theme overview; 1200 × 750 pixels work well.

Option types are `color`, `text`, `textarea`, `toggle` and `select` (with
`options: { key: Label }`). The values appear under *Themes* in the panel as
soon as the theme is active. See [step 9](#9-theme-options).

## 3. The layout

Every page shares one layout. Create `templates/layouts/default.blade.php`:

```blade
<!doctype html>
<html lang="{{ $entry?->language() ?? $site->language() }}">
<head>
    @wakeHead
    <link rel="stylesheet" href="{{ theme_asset('harbour.css') }}">
    <style>:root { --accent: {{ theme_option('accent') }} }</style>
</head>
<body>
    <a class="skip" href="#main">{{ __('Skip to content') }}</a>

    <header class="site-header">
        <a class="site-title" href="{{ $site->url() }}">{{ $site->title() }}</a>
        <nav aria-label="{{ __('Main menu') }}">
            @foreach ($site->menu() as $item)
                <a href="{{ $item->url() }}" @if ($item->isActive()) aria-current="page" @endif>{{ $item->title() }}</a>
            @endforeach
        </nav>
    </header>

    <main id="main">
        @yield('content')
    </main>

    <footer class="site-footer">
        <nav aria-label="{{ __('Footer') }}">
            @foreach ($site->menu('footer') as $item)
                <a href="{{ $item->url() }}">{{ $item->title() }}</a>
            @endforeach
            @wakeConsentLink
        </nav>
        @if (theme_option('footer_text'))<p>{{ theme_option('footer_text') }}</p>@endif
    </footer>
</body>
</html>
```

What the three directives do:

| Directive | Why you need it |
|---|---|
| `@wakeHead` | Writes `<meta charset>`, the viewport, `<title>`, description, canonical URL, `hreflang` links, the RSS link, Open Graph tags, JSON-LD, the consent manager and the statistics script, plus anything plugins add. Do not write these tags yourself. |
| `@wakeConsentLink` | A "Cookie settings" link that opens the consent settings again. Visitors must be able to change their choice, so put it in the footer. |
| `@wakeServices` | A section for your privacy policy page that lists Wake's statistics, cookies and embedded services. Use it in a page template, or tell editors to put it on the privacy page. |

Every template gets `$site` (the website) and `$entry` (the current entry, or
`null` on the start page without `content/pages/home`). The entry is also
available under its type's name: `$post`, `$page`, `$tag`.

### Menus

`$site->menu()` is the main menu, `$site->menu('footer')` the footer menu.
Editors arrange them under *Menus* in the panel; until then the main menu
follows the page tree ([Arrange pages and menus](pages-and-menus.md)). An item
can have one level of children:

```blade
@foreach ($site->menu() as $item)
    <div class="nav-item">
        <a href="{{ $item->url() }}" @if ($item->isActive()) aria-current="page" @endif>{{ $item->title() }}</a>
        @if ($item->children()->isNotEmpty())
            <div class="nav-sub">
                @foreach ($item->children() as $child)
                    <a href="{{ $child->url() }}">{{ $child->title() }}</a>
                @endforeach
            </div>
        @endif
    </div>
@endforeach
```

The footer menu is empty until someone fills it. Tide then repeats the main
menu: `$site->menu('footer')->whenEmpty(fn () => $site->menu())`.

## 4. The start page

`templates/home.blade.php` renders `/`. It is also used for the page
`content/pages/home`, if there is one; then `$entry` is that page.

```blade
@extends('theme::layouts.default')

@section('content')
    @php($posts = $site->posts()->paginate())

    <section class="intro">
        <h1>{{ $entry?->title() ?? $site->title() }}</h1>
        @if ($entry)
            {{ $entry->content() }}
        @else
            <p>{{ $site->description() }}</p>
        @endif
    </section>

    <section class="posts">
        @foreach ($posts as $post)
            @include('theme::snippets.post-card', ['post' => $post])
        @endforeach
    </section>

    @if ($posts->hasPages())
        <nav class="pagination" aria-label="{{ __('Pages') }}">
            @if ($posts->previousPageUrl())<a href="{{ $posts->previousPageUrl() }}" rel="prev">{{ __('Newer posts') }}</a>@endif
            @if ($posts->nextPageUrl())<a href="{{ $posts->nextPageUrl() }}" rel="next">{{ __('Older posts') }}</a>@endif
        </nav>
    @endif

    @if (theme_option('show_tags'))
        <p class="tags">
            @foreach ($site->tags()->take(20) as $tag)<a href="{{ $tag->url() }}">{{ $tag->title() }}</a>@endforeach
        </p>
    @endif
@endsection
```

Refer to other files of your theme with the `theme::` prefix, as above.
`$site->posts()` is a [query](../reference/templates.md#queries): you can
filter it (`->tag('travel')`, `->where('featured', true)`, `->limit(3)`)
before looping. `paginate()` takes `per_page` posts (10 by default) and reads
the page from `?page=`.

Without a `home.blade.php`, the start page answers with 404. Every theme needs
one.

### A post card with an image

`templates/snippets/post-card.blade.php`:

```blade
<article class="card">
    @if ($post->thumbnail())
        <a href="{{ $post->url() }}" tabindex="-1" aria-hidden="true">
            {{ $post->thumbnail()->picture('', '(min-width: 960px) 33vw, 100vw') }}
        </a>
    @endif
    <h2><a href="{{ $post->url() }}">{{ $post->title() }}</a></h2>
    <p>{{ $post->excerpt(24) }}</p>
    <time datetime="{{ $post->date()?->toDateString() }}">{{ $post->date()?->locale($post->language())->isoFormat('LL') }}</time>
</article>
```

`thumbnail()` is the preview image, or else the header image. `picture()`
writes a `<picture>` with WebP and a `srcset` of resized versions, so the
browser loads only the size it needs. Its second argument, `sizes`, tells
the browser how wide the image is shown.

Cards often crop images to the same shape with CSS. Editors set a **focus
point** per image in the panel (*Media*), and `picture()` adds it as
`object-position`, so the important part stays visible:

```css
.card img { width: 100%; aspect-ratio: 3 / 2; object-fit: cover; }
```

The alt text from the file's details is used automatically. Here the image is
a decorative link next to the title, so the empty alt (`''`) is right.

## 5. Posts, pages and tags

`templates/post.blade.php`:

```blade
@extends('theme::layouts.default')

@section('content')
    <article class="post">
        <h1>{{ $post->title() }}</h1>
        <p class="meta">
            {{ $post->date()?->locale($post->language())->isoFormat('LL') }}
            · {{ trans_choice(':count min read|:count min read', $post->readingTime()) }}
        </p>

        @if ($post->image()?->isImage())
            <figure>{{ $post->image()->picture($post->title(), '100vw', ['loading' => 'eager']) }}</figure>
        @endif

        <div class="prose">{{ $post->content() }}</div>

        @if ($post->tags()->isNotEmpty())
            <p class="tags">@foreach ($post->tags() as $tag)<a href="{{ $tag->url() }}">{{ $tag->title() }}</a>@endforeach</p>
        @endif
    </article>
@endsection
```

`templates/page.blade.php` is the same without date and tags; use
`$page->children()` for a list of sub-pages. `templates/tag.blade.php` gets
the tag as `$tag`: loop over `$tag->posts()->paginate()`.

Put `class="prose"` (or any class you like) around `content()` and style the
plain HTML elements inside it: headings, paragraphs, lists, `blockquote`,
`figure`, `pre`, tables. That is all Markdown produces, plus cards
([step 7](#7-cards)).

## 6. Which template is used

Wake takes the first template that exists:

| Address | Templates, first match wins |
|---|---|
| `/` | `template` field of `pages/home` → `home` → `page` → `default` |
| a post | its `template` field → `post-{category template}` → `post` → `default` |
| a page | its `template` field → `page` → `default` |
| a tag | its `template` field → `tag` → `default` |
| an entry of a custom type, e.g. `event` | its `template` field → `event` → `default` |
| a category page | `category-{category template}` → `category` → Wake's built-in list |
| `/search?q=…` | `search` → Wake's built-in page |
| not found | `404` → Wake's built-in page |

Two ways to give single entries their own look:

- **The `template` field.** An editor writes `template: landing` into the front
  matter of a page, and Wake uses `templates/landing.blade.php` if your theme
  has it.
- **Categories.** Posts in the category *Termine* use `post-termine.blade.php`,
  and the category page `category-termine.blade.php`. A category can name
  another template in its settings (*Categories* in the panel). Tide ships
  `post-event.blade.php` and `category-event.blade.php` for events; look at
  them for dates, places and calendar links.

`default.blade.php` is a safety net for anything without its own template. If
neither exists, Wake uses a plain built-in page.

## 7. Cards

Editors write cards in Markdown:

```markdown
::: callout 💡
Members get in free.
:::
```

Wake renders a card with the first view that exists:

1. your theme's `templates/cards/{name}.blade.php`,
2. a view registered by a [plugin](../reference/plugins.md),
3. Wake's built-in look (`callout`, `button`, `divider`, `embed`),
4. otherwise a plain `<div class="card card-{name}">`.

So a theme can restyle the built-in cards and invent new ones. The view gets
`$name`, `$value` (the text after the name), `$attributes` (`key="value"`
settings) and `$content` (the inside as HTML):

```blade
{{-- templates/cards/quote.blade.php, used as ::: quote author="Jane Doe" --}}
<figure class="quote">
    <blockquote>{{ $content }}</blockquote>
    @if (! empty($attributes['author']))<figcaption>{{ $attributes['author'] }}</figcaption>@endif
</figure>
```

Leave `embed` alone unless you know what you do: Wake's version asks for
consent before it loads YouTube or Vimeo ([consent](../concepts/consent.md)).

## 8. Assets and translations

**Assets.** Put CSS, JavaScript, fonts and images into `assets/` and link them
with `theme_asset()`:

```blade
<link rel="stylesheet" href="{{ theme_asset('harbour.css') }}">
<link rel="preload" href="{{ theme_asset('fonts/inter.woff2') }}" as="font" type="font/woff2" crossorigin>
```

Wake copies changed files to `public/assets/themes/harbour/` and adds a
version to the address (`harbour.css?v=…`), so browsers never keep an old
file. In CSS, refer to other assets with relative paths
(`url(../fonts/inter.woff2)`): when Wake copies a stylesheet, it copies the
files it points to as well, as long as they stay inside `assets/`. A
`preload` link as above still helps the browser start loading early.
Host fonts yourself rather than loading them from Google: it is faster and
needs no consent.

**Translations.** Write the theme's own texts in English inside `__()` and
translate them in `lang/{code}.json`:

```json
{
    "Skip to content": "Zum Inhalt springen",
    "Main menu": "Hauptmenü",
    "Newer posts": "Neuere Beiträge",
    "Older posts": "Ältere Beiträge",
    ":count min read|:count min read": ":count Minute Lesezeit|:count Minuten Lesezeit"
}
```

The website language decides which file is used. Content itself is translated
by editors, not by the theme ([Add a second language](second-language.md)).
For a language switcher, copy `templates/snippets/languages.blade.php` from
Tide.

## 9. Theme options

The options from `theme.yml` appear under *Themes* in the panel. Templates
read them with `theme_option('name')`: the value chosen in the panel, else the
default from `theme.yml`. Values are stored in `site/settings.yml` under
`theme_options`; switching themes keeps them, but each theme only reads its
own keys.

Treat option values like any input. A colour is checked by the panel
(`#RRGGBB`), but if you write a value into CSS, check it again, as Tide does:

```blade
@php($accent = theme_option('accent'))
<style>:root { --accent: {{ preg_match('/^#[0-9a-fA-F]{6}$/', $accent) ? $accent : '#3D5AFE' }} }</style>
```

## 10. Good habits

- **Dark mode:** define colours as CSS variables and switch them in
  `@media (prefers-color-scheme: dark)`. Check the accent colour on both
  backgrounds.
- **Motion:** wrap animations in `@media (prefers-reduced-motion: no-preference)`,
  or switch them off under `reduce`.
- **Accessibility:** a skip link, `aria-current` in menus, visible focus
  styles, real headings in order, alt texts only where the image says
  something (`picture($post->title())` for a header image, `''` for a
  decorative thumbnail next to the title).
- **Images:** always pass `sizes`; use `['loading' => 'eager', 'fetchpriority' => 'high']`
  only for the first large image on a page.
- **Small phones:** test at 360 pixels width; nothing may scroll sideways.
- **No external requests** without need: no CDN fonts, no tracking. Wake's
  statistics and consent manager are already loaded by `@wakeHead`.

## 11. Try it locally

1. Select the theme: in the panel, *Themes* → **Activate** next to Harbour.
   Or set `'theme' => 'harbour'` in `site/config.php`.
2. Run `php wake/artisan serve` and open http://localhost:8000.
3. Wake caches finished pages. While signed in to the panel you always see
   fresh pages; in a private window, run `php wake/artisan wake:clear` after
   changing a template ([CLI](../reference/cli.md#wakeclear)).
4. Check the start page, a post with and without images, a page, a tag, a
   category page, `/search?q=test`, a missing address (404), both languages
   if the site has two, and a long title on a small screen.

## 12. Share it as a ZIP

Zip the theme folder:

```bash
cd site/themes && zip -r harbour.zip harbour -x '*.DS_Store'
```

Others install it under *Themes* → **Install a theme (ZIP)**. Wake checks the
ZIP before it unpacks anything:

- it must contain `templates/home.blade.php`, either at the top or inside one
  top folder;
- paths with `..`, absolute paths and backslashes are refused.

The new folder is named after `name` in `theme.yml` (as a slug), with `-2`,
`-3` … if that folder exists. A new theme is not activated automatically.

> Themes are PHP (Blade) and run with full rights, just like
> [plugins](../reference/plugins.md). Say so in your README, keep your theme
> free of surprises, and only install themes from people you trust.

## Where to go next

- [Templates reference](../reference/templates.md): every variable and method
- [Content format](../reference/content-format.md): fields, cards, categories, menus
- [Tide](../reference/tide.md): the default theme, a complete example
- [Plugins](../reference/plugins.md): when a theme is not enough
