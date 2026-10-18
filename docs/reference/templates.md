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

## Forms

Pages are cached and run without a session, so a form on the website cannot
use Laravel's CSRF token. Wake protects public forms without one:

```blade
<form method="post" action="{{ url('contact') }}">
    @wakeForm('contact')
    <input name="email" type="email" required>
    <button>Send</button>
</form>
```

```php
Route::post('contact', ContactController::class)->middleware('wake.form:contact');
```

`@wakeForm('name')` adds a stamp signed with the application key (the form's
name and when the page was made) and a hidden honeypot field. The middleware
`wake.form:name` refuses the request with 422 when the honeypot is filled, the
stamp is forged or belongs to another form, or the form was sent within two
seconds of loading or more than seven days later; and with 429 after ten
submissions of that form from one visitor (hashed IP address) within ten
minutes. Validate the fields themselves as usual. Forms are added by
[plugins](plugins.md), which register the route.

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
| `$card` | its [description](#card-descriptions), or `null` |
| `$images` | the media files (`Wake\Media\Media`, or `null` if missing) of its `image` fields, by field |

```blade
{{-- templates/cards/callout.blade.php --}}
<aside class="callout">{{ $value }} {{ $content }}</aside>
```

### Card descriptions

A `{name}.yml` next to the card's view tells the editor and AI assistants what
the card is for and which settings it has. The editor then shows a small form
instead of the raw line and offers the card in its `/` menu; assistants read
it with the MCP tool [`content_cards`](mcp.md#content_cards).

```yaml
# templates/cards/member.yml, used as ::: member Jane Doe role="Chair" photo="jane.jpg"
title: { en: Board member, de: Vorstandsmitglied }
description:
  en: A person of the board with photo and role.
  de: Eine Person aus dem Vorstand mit Foto und Amt.
hint: 👤                      # short sign in the / menu
value:                        # the text after the name; leave out if unused
  label: { en: Name, de: Name }
  required: true
content: false                # true (default): the card holds Markdown
fields:
  role:  { type: text, label: { en: Role, de: Amt }, required: true }
  photo: { type: image, label: { en: Photo, de: Foto } }
  tone:
    type: select
    default: light
    options: { light: { en: Light, de: Hell }, dark: { en: Dark, de: Dunkel } }
```

| Key | Meaning |
|---|---|
| `title`, `description` | text, or one text per language |
| `hint` | up to three characters for the `/` menu |
| `value` | `label`, `placeholder`, `required` for the text after the name |
| `content` | `false` hides the text area of the card in the editor |
| `menu` | `false` keeps the card out of the `/` menu |
| `fields` | settings written as `key="value"`, in this order. Keys are lowercase. `type`: `text`, `url`, `image` (a file from the media library, or a name in the entry's media folder), `select` with `options`, `toggle` (written as `"true"`), `number`, `date`, `color`. Also `label`, `placeholder`, `required`, `default` |

```blade
{{-- templates/cards/member.blade.php --}}
<figure class="member member-{{ $attributes['tone'] ?? 'light' }}">
    @if ($images['photo'] ?? null){{ $images['photo']->img($value) }}@endif
    <figcaption><b>{{ $value }}</b> {{ $attributes['role'] ?? '' }}</figcaption>
</figure>
```

Required settings are marked in the editor. They never block saving; when
someone publishes an entry with a card that leaves them empty, Wake names the
card and asks first, and `content_publish` returns them as `card_warnings`.

Wake reads its own descriptions (for `callout`, `button`, `embed` and
`divider`) first, then those of running plugins (`views/cards/{name}.yml`),
then the theme's; the theme wins. A description that cannot be read is shown
on the *Themes* page in the panel. Settings a description does not know stay
in the text as they are.

## Translations

Texts of the theme itself go through Laravel's `__()` helper with English as the
key. Translations live in `lang/{code}.json` of the theme; the language of the
page decides which one is used:

```json
{ "Older posts": "Ältere Beiträge" }
```
