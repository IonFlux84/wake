---
title: Plugins
description: How a plugin is built, every method a plugin can use, and an example.
---

# Plugins

A plugin adds something to Wake that it does not do by itself: a card, a field
type for categories, an address, a maintenance task. Plugins live in
`plugins/`, one folder each. To install one, copy its folder there by FTP
or Git. Under *Plugins* in the panel (people with `settings.manage`) you see
all plugins, switch them on and off and change their settings.

> Plugins run with the same rights as Wake. Only install plugins whose source
> you trust. Why it works this way: [decision 0009](../decisions/0009-plugins-as-php-folders.md).

## A plugin folder

```
plugins/opening-hours/
  plugin.yml      name, description, version, author, settings
  plugin.php      registers what the plugin adds
  src/            your classes, loaded through "autoload" in plugin.yml
  vendor/         bundled libraries; vendor/autoload.php is loaded if present
  views/          Blade views, used as opening-hours::name
  lang/           translations, de.json and so on
  assets/         CSS, JavaScript and images for the website
```

The folder name is the plugin's key: lowercase letters, digits, `-` and `_`.
Folders with other names, and folders without a `plugin.php`, are ignored.

For a step-by-step example, see [Write a plugin](../guides/write-a-plugin.md).

### `plugin.yml`

```yaml
name: Opening hours
description: { en: Shows today's opening hours., de: Zeigt die heutigen Öffnungszeiten. }
version: 1.0.0
author: Jane Doe
url: https://example.org/opening-hours
autoload:
  OpeningHours: src            # PSR-4: OpeningHours\Hours is src/Hours.php
options:
  closed_on:
    type: select
    label: { en: Closed on, de: Geschlossen am }
    options: { sunday: Sunday, monday: Monday }
    default: sunday
```

`autoload` maps namespaces to folders inside the plugin (PSR-4). Folders
outside the plugin are ignored.

Options work like [theme options](templates.md): `text`, `textarea`,
`toggle`, `select` and `color`, each with `label`, `default` and optionally
`placeholder` and `hint`. Values are stored in `plugins.options` in
`settings/general.yml` ([configuration](configuration.md#site)).

### `plugin.php`

```php
<?php

use Illuminate\Support\Facades\Route;
use Wake\Plugins\Plugin;

return function (Plugin $plugin) {
    $plugin->card('hours', 'opening-hours::cards.hours');

    $plugin->routes(fn () => Route::get('hours.json', fn () => [
        'closed_on' => $plugin->option('closed_on'),
    ]));
};
```

Wake calls the function on every request that is not answered from the page
cache, so keep it quick: register things here and do the work later, when
they are used. The file may also return nothing; then Wake only loads its
views, translations and assets.

Your own classes are loaded through `autoload` in `plugin.yml`; give them a
namespace of your own. To share an object between views, routes and tasks, put it into
Laravel's container: `app()->instance(MyService::class, new MyService($plugin));`.

## What a plugin can add

| Method | Adds |
|---|---|
| `wake.form:{name}` (route middleware) | protects a public form that contains `@wakeForm('{name}')`: signed stamp, honeypot, ten submissions per visitor in ten minutes. See [forms](templates.md#forms). |
| `card($name, $view)` | a card for Markdown, `::: name … :::`. The view gets `$name`, `$value`, `$attributes` and `$content`. A theme's `cards/{name}.blade.php` still wins. A `views/cards/{name}.yml` [describes it](templates.md#card-descriptions) for the editor and assistants. See [cards](content-format.md#cards). |
| `field($type, $view, $label)` | a field type for [categories](content-format.md#categories). The view renders the input in the editor and gets `$key`, `$field`, `$model` (for `wire:model`) and `$disabled`. `$label` is a text or one per language. |
| `routes($closure)` | website routes, registered before Wake's own addresses, in the alphabetical order of the plugin folders. They have no session and no page cache. While the site is [closed](configuration.md#site), they answer only signed-in team members, like the website. Wrap them in `Route::middleware('web')` only for routes used by signed-in team members (session and CSRF protection). |
| `task($class)` | a [maintenance](../concepts/architecture.md#maintenance-without-cron) task, a class implementing `Wake\Maintenance\Task` with `run(Deadline $deadline)`. Wake creates it through Laravel's container, so the constructor can ask for services. Check `$deadline->expired()` in loops. |
| `head($closure)` | HTML for the `<head>` of every website page: `fn ($site, $entry) => '<meta …>'`. |
| `on($event, $listener)` | an event listener, e.g. for `Wake\Content\ContentChanged`. |
| `command($class)` | an Artisan command for `php wake/artisan`. |
| `page($name, $view, $label, $icon, $can)` | a page in the panel at `/wake/plugin/{folder}/{name}`, listed under *Manage* for people with the capability `$can` (default `settings.manage`). The view is shown inside the panel layout and gets `$page`. `$label` is a text or one per language; `$icon` is the name of a panel icon (default `plug`). |
| `livewire($name, $class)` | a [Livewire](https://livewire.laravel.com) component for your panel pages, used as `<livewire:{folder}.{name} />`. Check the capability again in its `mount()`: Livewire actions don't pass the page's middleware. |
| `tool($class)` | a tool for the [MCP server](mcp.md). Extend `Wake\Mcp\Tools\WakeTool`, set `$scope` and implement `run()`; the base class checks the token's scope. Check the person's capabilities yourself where the scope is not enough. |

And to help with that:

| Method | Returns |
|---|---|
| `key()` | the folder name |
| `path($file)` | the full path of a file in the plugin |
| `option($key, $default)` | a setting from the panel, or its default from `plugin.yml` |
| `asset($file)` | the public URL of a file in `assets/`, with a version for caching |
| `info` | the `plugin.yml` data: `name()`, `description()`, `version()`, `author()`, `url()`, `options()` |

Every method that adds something returns the plugin, so calls can be chained.

## Example: a panel page

```php
// plugin.php
return function (Wake\Plugins\Plugin $plugin) {
    $plugin->page('hours', 'opening-hours::panel', ['en' => 'Opening hours', 'de' => 'Öffnungszeiten'], 'calendar')
        ->livewire('editor', OpeningHours\HoursEditor::class);
};
```

```blade
{{-- views/panel.blade.php --}}
<header class="page-head"><h1 class="title">{{ __('Opening hours') }}</h1></header>
<livewire:opening-hours.editor />
```

The panel's classes (`card`, `table`, `btn`, `input`, `kpis` …) are
available, so a plugin page looks like the rest of the panel. Wake's own
plugin `plugins/redirect-scout` is a complete example with a panel page,
Livewire, MCP tools and a command.

## Views, translations and assets

| Folder | What Wake does with it |
|---|---|
| `views/` | registered as the view namespace `{folder}`: `views/cards/hours.blade.php` is `opening-hours::cards.hours` |
| `lang/` | JSON translations (`de.json`, …) added to Wake's texts, used with `__('Text')` |
| `assets/` | copied to `public/assets/plugins/{folder}/` when a file changed; `asset('file')` returns its address with a version |

Translations of all plugins, themes and Wake share one list, so pick texts
that are unlikely to mean something else elsewhere.

## Example: a field type

```php
// plugin.php
return function (Wake\Plugins\Plugin $plugin) {
    $plugin->field('colour', 'colours::field', ['en' => 'Colour', 'de' => 'Farbe']);
};
```

```blade
{{-- views/field.blade.php --}}
<input type="color" id="f-{{ $key }}" wire:model="{{ $model }}" @disabled($disabled)>
```

The value is saved in the entry's front matter like any other field.

## Useful Wake APIs for plugins

The methods of `Wake\Plugins\Plugin` are the promise Wake makes to plugin
authors. Beyond that, these classes are the ones plugins can rely on. They
are also what themes use, so they change only with a note in the changelog.

### Reading content

```php
use Wake\Content\Models\Page;
use Wake\Content\Models\Post;
use Wake\Content\Query;
use Wake\Content\Site;

$latest = Post::published()->tag('events')->latest()->limit(3)->get();
$page = Page::find('pages/about');                      // by folder, or null
$events = (new Query('post'))->published()->category('events')->upcoming()->get();
$site = app(Site::class);                                // the same as $site in templates
```

A query reads Wake's index from memory. It offers `published()`, `drafts()`,
`scheduled()`, `tag()`, `category()`, `upcoming()`, `past()`, `childrenOf()`,
`listed()`, `where()`, `latest()`, `oldest()`, `orderBy()`, `limit()`,
`offset()`, `in($language)`, `get()`, `first()`, `find()`, `count()`,
`exists()` and `paginate()`. Entries offer `id()`, `title()`, `url()`,
`date()`, `get('field')`, `content()`, `excerpt()`, `image()`, `file()`,
`category()`, `tags()`, `parent()`, `children()` and more; see
[templates](templates.md#entries) for the full list.

### Changing content

```php
use Wake\Content\Writer;

$writer = app(Writer::class);
$entry = $writer->create('post', ['title' => 'Open on Sunday', 'status' => 'draft'], 'We open at noon.');
$writer->update($entry->id(), 'en', ['title' => 'Open this Sunday']);
$writer->publish($entry->id(), 'en');
```

The writer keeps versions, the history and redirects, moves deleted entries
to the trash and clears the page cache. Do not edit files in `content/`
directly from a plugin.

### Files, paths and settings

| Class | Use |
|---|---|
| `Wake\Support\Files::write($path, $contents)` | write a file atomically (never `file_put_contents()`) |
| `Wake\Support\Files::lock($name, $callback)` | run code while holding a lock, e.g. when two requests could write the same file |
| `Wake\Support\Paths::content()`, `settings()`, `themes()`, `plugins()`, `data()`, `theme()`, `root()` | folders of the site; data you keep belongs in `Paths::data('your-plugin/…')`, caches in `storage_path('your-plugin/…')` (`temp/`) |
| `config('wake.…')` | every [setting](configuration.md), e.g. `config('wake.closed')` |
| `$plugin->option($key)` | your plugin's settings from the panel |
| `app(Wake\Cache\PageCache::class)->flush()` | throw away all cached pages, e.g. when your output changes |

### Events

| Event | Fired when |
|---|---|
| `Wake\Content\ContentChanged` | the index noticed new, changed or deleted content (from the panel, an assistant, FTP or Git); Wake clears the page cache right after |

Laravel's own events work as well, for example
`Illuminate\Auth\Events\Login` when someone signs in to the panel.

## When something goes wrong

If `plugin.php` throws an exception or has a syntax error, Wake skips the
plugin, writes the error to `temp/logs/laravel.log` and shows it in the
panel. The rest of the site keeps working. Switching the plugin off in the
panel stops Wake from loading it at all.

Plugins that are switched off stay in their folder. To remove a plugin, delete
its folder.
