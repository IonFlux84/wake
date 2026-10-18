---
title: Plugins
description: How a plugin is built, every method a plugin can use, and an example.
---

# Plugins

A plugin adds something to Wake that it does not do by itself: a card, a field
type for categories, an address, a maintenance task. Plugins live in
`site/plugins/`, one folder each. To install one, copy its folder there by FTP
or Git. Under *Plugins* in the panel (people with `settings.manage`) you see
all plugins, switch them on and off and change their settings.

> Plugins run with the same rights as Wake. Only install plugins whose source
> you trust. Why it works this way: [decision 0009](../decisions/0009-plugins-as-php-folders.md).

## A plugin folder

```
site/plugins/opening-hours/
  plugin.yml      name, description, version, author, settings
  plugin.php      registers what the plugin adds
  views/          Blade views, used as opening-hours::name
  lang/           translations, de.json and so on
  assets/         CSS, JavaScript and images for the website
```

The folder name is the plugin's key: lowercase letters, digits, `-` and `_`.

### `plugin.yml`

```yaml
name: Opening hours
description: { en: Shows today's opening hours., de: Zeigt die heutigen Öffnungszeiten. }
version: 1.0.0
author: Jane Doe
url: https://example.org/opening-hours
options:
  closed_on:
    type: select
    label: { en: Closed on, de: Geschlossen am }
    options: { sunday: Sunday, monday: Monday }
    default: sunday
```

Options work like [theme options](templates.md): `text`, `textarea`,
`toggle`, `select` and `color`, each with `label`, `default` and optionally
`placeholder` and `hint`. Values are stored in `plugins.options` in
`site/settings.yml` ([configuration](configuration.md#site)).

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

Wake calls the function on every request, so keep it quick: register things
here and do the work later, when they are used.

## What a plugin can add

| Method | Adds |
|---|---|
| `card($name, $view)` | a card for Markdown, `::: name … :::`. The view gets `$name`, `$value`, `$attributes` and `$content`. A theme's `cards/{name}.blade.php` still wins. See [cards](content-format.md#cards). |
| `field($type, $view, $label)` | a field type for [categories](content-format.md#categories). The view renders the input in the editor and gets `$key`, `$field`, `$model` (for `wire:model`) and `$disabled`. `$label` is a text or one per language. |
| `routes($closure)` | website routes, registered before Wake's own addresses. They have no session; use `Route::middleware('web')` inside if you need one. |
| `task($class)` | a [maintenance](../concepts/architecture.md#maintenance-without-cron) task, a class implementing `Wake\Maintenance\Task`. |
| `head($closure)` | HTML for the `<head>` of every website page: `fn ($site, $entry) => '<meta …>'`. |
| `on($event, $listener)` | an event listener, e.g. for `Wake\Content\ContentChanged`. |
| `command($class)` | an Artisan command for `php wake/artisan`. |

And to help with that:

| Method | Returns |
|---|---|
| `key()` | the folder name |
| `path($file)` | the full path of a file in the plugin |
| `option($key, $default)` | a setting from the panel, or its default from `plugin.yml` |
| `asset($file)` | the public URL of a file in `assets/`, with a version for caching |
| `info` | the `plugin.yml` data: `name()`, `description()`, `version()`, `author()`, `url()`, `options()` |

Every method that adds something returns the plugin, so calls can be chained.

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

## When something goes wrong

If `plugin.php` throws an exception or has a syntax error, Wake skips the
plugin, writes the error to `storage/logs/laravel.log` and shows it in the
panel. The rest of the site keeps working. Switching the plugin off in the
panel stops Wake from loading it at all.

Plugins that are switched off stay in their folder. To remove a plugin, delete
its folder.
