---
title: Add a second language
description: Turn a single-language site into a multilingual one, step by step.
---

# Add a second language

This guide adds German to an English site. It works the same for any language.

1. **Add the language** in `site/config.php`. The first language stays the
   default language and keeps its addresses without a prefix.

   ```php
   'languages' => [
       'en' => 'English',
       'de' => 'Deutsch',
   ],
   ```

2. **Translate the site title.** Copy `content/site.en.md` to
   `content/site.de.md` and translate it.

3. **Translate entries one by one.** Next to `index.en.md`, create
   `index.de.md` and give it its own `slug`:

   ```markdown
   ---
   title: Über uns
   slug: ueber-uns
   ---

   Wir sind …
   ```

   The page is now available at `/de/ueber-uns`. Images in the folder are
   shared, you don't copy them.

4. **Decide what happens with untranslated entries.** By default they don't
   exist in German (404). If you prefer to show the English version with a
   notice, set:

   ```php
   'missing_translation' => 'fallback',
   ```

5. **Add a language switcher** to your theme, if it doesn't have one. Tide
   shows one automatically. In your own theme:

   ```blade
   @foreach ($site->languages() as $code => $name)
       @if ($entry?->translation($code)?->isPublished())
           <a href="{{ $entry->translation($code)->url() }}" hreflang="{{ $code }}">{{ $name }}</a>
       @endif
   @endforeach
   ```

Wake takes care of the rest: `hreflang` links in the head, one RSS feed per
language (`/de/feed.xml`) and alternates in `sitemap.xml`.

## Related

- [Content format: languages](../reference/content-format.md#languages)
- [Configuration](../reference/configuration.md)
