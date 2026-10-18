---
title: Write a plugin
description: Build a complete plugin step by step, with settings, a card, translations, a stylesheet, a JSON address, a maintenance task and an event listener, then test and share it.
---

# Write a plugin

This guide builds a real plugin from an empty folder: **Opening hours**. It
shows on any page whether you are open today, offers the same answer as JSON
for other websites, and lets the site owner set the hours in the panel.

Along the way you use every common part of the plugin interface. The
[plugin reference](../reference/plugins.md) lists all methods; this page
shows how they fit together.

You need a Wake site running on your computer
([installation](../getting-started/installation.md)) and a little PHP and
Blade.

## What we build

```
site/plugins/opening-hours/
  plugin.yml                  name, description, settings
  plugin.php                  registers everything
  src/Hours.php               works out today's hours
  src/RememberToday.php       maintenance task
  views/cards/hours.blade.php the card
  lang/de.json                German texts
  assets/hours.css            the card's look
```

The finished card in a post:

```markdown
::: hours Our shop
On public holidays we open at noon.
:::
```

## 1. Create the folder and `plugin.yml`

The folder name is the plugin's key. Use lowercase letters, digits, `-` and
`_`; Wake ignores other folders. Create `site/plugins/opening-hours/plugin.yml`:

```yaml
name: Opening hours
description:
  en: Shows whether you are open today, as a card and as JSON.
  de: Zeigt, ob heute geöffnet ist, als Karte und als JSON.
version: 1.0.0
author: Jane Doe
url: https://example.org/opening-hours
options:
  hours:
    type: text
    label: { en: Opening hours, de: Öffnungszeiten }
    default: "10:00–18:00"
  closed_on:
    type: select
    label: { en: Closed every, de: Geschlossen jeden }
    options: { none: "–", monday: Monday, sunday: Sunday }
    default: sunday
  closed_dates:
    type: textarea
    label: { en: Closed on these days, de: Geschlossen an diesen Tagen }
    placeholder: "2026-12-24"
    hint: { en: One date per line., de: Ein Datum pro Zeile. }
```

Everything under `options` appears as a form under *Plugins* in the panel.
Labels and hints can be one text or one per language. The values end up in
`site/settings.yml` under `plugins.options.opening-hours`.

## 2. Write `plugin.php`

`plugin.php` returns a function. Wake calls it on every request that is not
answered from the page cache, and passes a `Wake\Plugins\Plugin` object. Start
with an empty one:

```php
<?php

use Wake\Plugins\Plugin;

return function (Plugin $plugin) {
    //
};
```

Open *Plugins* in the panel: *Opening hours* is listed and switched on, with
its three settings. If you made a typo, the panel shows the error instead,
and the rest of the site keeps working.

## 3. The logic in its own class

Wake has no autoloader for plugins, so load your classes with `require_once`.
Put a namespace of your own around them, so they cannot clash with other
plugins.

`src/Hours.php` works out today's answer from the settings. It also keeps the
answer in a small file below `storage/`, so a page view only reads that file:

```php
<?php

namespace OpeningHours;

use Carbon\CarbonImmutable;
use Wake\Plugins\Plugin;
use Wake\Support\Files;

/**
 * Works out today's opening hours from the plugin settings and keeps the
 * answer in storage/opening-hours/today.json.
 */
final class Hours
{
    public function __construct(private Plugin $plugin) {}

    /**
     * @return array{date: string, open: bool, hours: string}
     */
    public function today(): array
    {
        $cached = is_file(self::file()) ? json_decode((string) file_get_contents(self::file()), true) : null;

        if (is_array($cached) && ($cached['date'] ?? null) === CarbonImmutable::now()->toDateString()
            && ($cached['settings'] ?? null) === $this->fingerprint()) {
            unset($cached['settings']);

            return $cached;
        }

        return $this->compute();
    }

    /**
     * @return array{date: string, open: bool, hours: string}
     */
    public function compute(): array
    {
        $today = CarbonImmutable::now();
        $closedDates = preg_split('/\s+/', trim((string) $this->plugin->option('closed_dates', ''))) ?: [];
        $closed = strtolower($today->englishDayOfWeek) === $this->plugin->option('closed_on')
            || in_array($today->toDateString(), $closedDates, true);

        return [
            'date' => $today->toDateString(),
            'open' => ! $closed,
            'hours' => $closed ? '' : (string) $this->plugin->option('hours'),
        ];
    }

    /**
     * Writes today's answer, so requests only have to read a small file.
     */
    public function remember(): void
    {
        Files::write(self::file(), json_encode([...$this->compute(), 'settings' => $this->fingerprint()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function isRemembered(): bool
    {
        $cached = is_file(self::file()) ? json_decode((string) file_get_contents(self::file()), true) : null;

        return ($cached['date'] ?? null) === CarbonImmutable::now()->toDateString();
    }

    public static function forget(): void
    {
        @unlink(self::file());
    }

    public static function file(): string
    {
        return storage_path('opening-hours/today.json');
    }

    /**
     * Changes whenever someone saves the plugin settings in the panel.
     */
    private function fingerprint(): string
    {
        return md5(serialize(config('wake.plugins.options.'.$this->plugin->key(), [])));
    }
}
```

Three things worth copying from this class:

- `$plugin->option('hours')` returns the value from the panel, or the default
  from `plugin.yml`.
- Dates use the site's time zone (`timezone` in the
  [configuration](../reference/configuration.md)), because Wake sets it for
  the whole application.
- Files are written with `Wake\Support\Files::write()`, never with
  `file_put_contents()`. It writes atomically, so a visitor never reads half a
  file. Plugin data belongs below `storage/`, which is never public.

Now load the class and share one instance. Tasks and views get it from
Laravel's container:

```php
<?php

use OpeningHours\Hours;
use Wake\Plugins\Plugin;

require_once __DIR__.'/src/Hours.php';

return function (Plugin $plugin) {
    app()->instance(Hours::class, new Hours($plugin));
};
```

## 4. A card

A card is a block in Markdown: `::: hours` starts it, a line with only `:::`
ends it. Register it with a view:

```php
$plugin->card('hours', 'opening-hours::cards.hours');
```

Views in the plugin's `views/` folder are available as `opening-hours::…`.
Create `views/cards/hours.blade.php`:

```blade
@php($today = app(\OpeningHours\Hours::class)->today())
<aside class="opening-hours {{ $today['open'] ? 'is-open' : 'is-closed' }}">
    @if ($value !== '')<strong>{{ $value }}</strong>@endif
    <p>{{ $today['open'] ? __('Open today: :hours', ['hours' => $today['hours']]) : __('Closed today') }}</p>
    @if ($content->toHtml() !== '')<div class="opening-hours-note">{{ $content }}</div>@endif
</aside>
```

A card view gets four variables:

| Variable | In `::: hours Our shop` … |
|---|---|
| `$name` | `hours` |
| `$value` | `Our shop`, the free text after the name |
| `$attributes` | settings written as `key="value"`, e.g. `::: hours compact="yes"` |
| `$content` | the Markdown between the lines, already turned into HTML |

`{{ }}` escapes everything, which is what you want for `$value` and
`$attributes`: editors and AI assistants write them. `$content` is safe HTML
from Wake's Markdown renderer, so `{{ $content }}` prints it as is.

A theme can still give the card its own look with
`templates/cards/hours.blade.php`; the theme's view wins over the plugin's.

## 5. Translations

Texts in `__()` are translated with JSON files in the plugin's `lang/`
folder. Create `lang/de.json`:

```json
{
    "Open today: :hours": "Heute geöffnet: :hours",
    "Closed today": "Heute geschlossen"
}
```

On the German pages of a site the card now reads *Heute geschlossen*. Write
the English text into your views and keep it short and unique, because all
plugins and Wake share one list of texts.

## 6. A stylesheet

Files in `assets/` are copied to `public/assets/plugins/opening-hours/` when
they change. `$plugin->asset()` returns the address with a version, so
browsers load a new file after an update. Create `assets/hours.css`:

```css
.opening-hours { border: 1px solid currentColor; border-radius: 12px; padding: 1rem 1.25rem; margin: 1.5rem 0; }
.opening-hours.is-closed { opacity: .75; }
.opening-hours p { margin: 0; }
```

and add it to the `<head>` of every page:

```php
$plugin->head(fn () => '<link rel="stylesheet" href="'.e($plugin->asset('hours.css')).'">');
```

The closure gets `$site` and `$entry` (null on lists), so you could add the
stylesheet only to pages that use the card. Escape everything you put into
the HTML with `e()`.

## 7. A JSON address

Other websites, or a sign at the door, can ask for today's answer:

```php
use Illuminate\Support\Facades\Route;

$plugin->routes(fn () => Route::get('opening-hours.json', fn () => response()->json(app(Hours::class)->today())));
```

`http://localhost:8000/opening-hours.json` now answers
`{"date":"2026-10-18","open":false,"hours":""}`.

What you should know about plugin routes:

- They come before Wake's own addresses, so a plugin can take over any path.
  Pick names that cannot clash with pages.
- They follow the [closed site](../reference/configuration.md#site) setting
  by themselves: while the site is closed, only signed-in team members get an
  answer.
- They have no session and no page cache. Every call runs PHP, so keep the
  work small or read a prepared file, as `today()` does.

## 8. A maintenance task

Cards are part of the HTML, and Wake keeps whole pages in its page cache.
Right after midnight, cached pages would still show yesterday's card. A
[maintenance](../concepts/architecture.md#maintenance-without-cron) task fixes
that. Create `src/RememberToday.php`:

```php
<?php

namespace OpeningHours;

use Wake\Cache\PageCache;
use Wake\Maintenance\Deadline;
use Wake\Maintenance\Task;

/**
 * Once a day: work out the new day's hours and clear the page cache, so
 * cached pages do not keep showing yesterday's card.
 */
final class RememberToday implements Task
{
    public function __construct(private Hours $hours, private PageCache $cache) {}

    public function run(Deadline $deadline): void
    {
        if ($this->hours->isRemembered()) {
            return;
        }

        $this->hours->remember();
        $this->cache->flush();
    }
}
```

and register it:

```php
require_once __DIR__.'/src/RememberToday.php';

$plugin->task(RememberToday::class);
```

Wake creates the task through Laravel's container, so the constructor can ask
for `Hours` (the instance from step 3) and Wake services such as `PageCache`.

Tasks run at most once a minute, after a page has been sent to a visitor,
with a shared time budget of a few seconds (`maintenance.budget`). Return
quickly when there is nothing to do. For long work, check
`$deadline->expired()` in your loop and continue on the next run.

Run all tasks by hand with `php wake/artisan wake:maintenance`.

## 9. React to changes

Plugins can listen to Laravel events. Wake fires `Wake\Content\ContentChanged`
whenever content was created, changed or deleted, and then clears the page
cache. Our plugin drops its file at the same moment, so the next request
works out a fresh answer:

```php
use Wake\Content\ContentChanged;

$plugin->on(ContentChanged::class, fn () => Hours::forget());
```

Listeners run while an editor saves, so keep them fast. Leave slow work, such
as calling another server, to a task.

## The complete `plugin.php`

```php
<?php

use Illuminate\Support\Facades\Route;
use OpeningHours\Hours;
use OpeningHours\RememberToday;
use Wake\Content\ContentChanged;
use Wake\Plugins\Plugin;

// Plugins have no autoloader: load your classes yourself.
require_once __DIR__.'/src/Hours.php';
require_once __DIR__.'/src/RememberToday.php';

return function (Plugin $plugin) {
    // One shared instance for the card view, the route and the task.
    app()->instance(Hours::class, new Hours($plugin));

    $plugin->card('hours', 'opening-hours::cards.hours');

    $plugin->head(fn () => '<link rel="stylesheet" href="'.e($plugin->asset('hours.css')).'">');

    $plugin->routes(fn () => Route::get('opening-hours.json', fn () => response()->json(app(Hours::class)->today())));

    $plugin->task(RememberToday::class);

    $plugin->on(ContentChanged::class, fn () => Hours::forget());
};
```

## Test your plugin

### By hand

1. Start the local server: `php wake/artisan serve`.
2. Put the card into a post in the panel and open the post.
3. Open `/opening-hours.json`.
4. Change the settings under *Plugins* and reload.
5. Run `php wake/artisan wake:maintenance` and look at
   `storage/opening-hours/today.json`.

Signed-in team members bypass the page cache, so if a change does not show
up, open the page in a private window or run `php wake/artisan wake:clear`.

To try the card without a page:

```bash
php wake/artisan tinker --execute 'echo app(Wake\Markdown\Markdown::class)->render("::: hours\n:::");'
```

### With Pest

Wake's own tests run in a sandbox: every test gets a fresh copy of
`wake/tests/Fixtures/content` and `wake/tests/Fixtures/site`. In a local clone
of Wake, copy your plugin to `wake/tests/Fixtures/site/plugins/opening-hours`
(do not commit it) and write a test in `wake/tests/Feature`, like Wake's own
`PluginTest.php`:

```php
<?php

use Illuminate\Support\Carbon;
use Wake\Markdown\Markdown;
use Wake\Plugins\Plugins;

it('starts without errors', function () {
    expect(app(Plugins::class)->isRunning('opening-hours'))->toBeTrue()
        ->and(app(Plugins::class)->error('opening-hours'))->toBeNull();
});

it('is closed on Sundays', function () {
    Carbon::setTestNow('2026-10-18 12:00'); // a Sunday

    expect(app(Markdown::class)->render("::: hours\n:::"))->toContain('Closed today');
    $this->get('/opening-hours.json')->assertJson(['open' => false]);
});

it('follows the settings', function () {
    Carbon::setTestNow('2026-10-18 12:00');
    config(['wake.plugins.options.opening-hours' => ['closed_on' => 'none', 'hours' => '9–17']]);

    $this->get('/opening-hours.json')->assertJson(['open' => true, 'hours' => '9–17']);
});
```

Run it with `composer --working-dir=wake test -- --filter=opening`. Plugins
start when the application starts, so routes can only be tested for plugins
that are in the fixture folder from the beginning.

## Performance

- `plugin.php` runs on every request that is not answered from the page
  cache, including every request in the panel. Only register things there.
  Do not read files, query content or call other servers in it.
- Card and head views run when a page is rendered; the page cache then keeps
  the result for up to `page_cache.ttl` seconds. Cheap is good; prepared
  files are better.
- Routes always run PHP. Answer from a prepared file where you can.
- Tasks share a budget of `maintenance.budget` seconds with Wake's own tasks.
- Content queries (`Wake\Content\Query`) read Wake's index from memory and are
  fast. `$entry->content()` reads and renders a file, so avoid calling it for
  long lists.

## Security

A plugin runs with the same rights as Wake: it can read every file and every
account. Site owners trust you with that, so:

- **Escape output.** Use `{{ }}` in Blade and `e()` in PHP strings. Only print
  HTML unescaped that Wake made (`$content` in cards).
- **Validate input.** Treat card settings, query strings and form fields as
  hostile. Use `request()->validate([...])` or check values against a fixed
  list, as `closed_on` does.
- **Forms on the website.** Website pages have no session and are cached, so
  they cannot carry a CSRF token. Send public forms to a stateless route and
  protect it with `->middleware('throttle:10,1')`, validation and a honeypot
  field. Use `Route::middleware('web')` (session and CSRF protection) only
  for routes that signed-in team members use from the panel, and check their
  rights with `auth()->user()?->can('settings.manage')`.
- **Write safely.** Use `Wake\Support\Files::write()`, keep data below
  `storage/`, never in `public/` or `content/` unless it is meant to be
  public or to be content. To change content, use `Wake\Content\Writer`, which
  keeps versions and the history.
- **No secrets in `plugin.yml` defaults** and none in `site/`, which people
  commit to Git. Ask for API keys as a setting, or read them from `.env` with
  `env('OPENING_HOURS_KEY')`.
- **Keep dependencies few.** There is no Composer for plugins. If you bundle a
  library, put it in your own namespace so two plugins cannot load two
  versions of it.

## Names and versions

- The folder name is the key that site owners see in paths and settings. Keep
  it stable: renaming it loses the saved settings.
- Prefix card names, field types and routes with something from your plugin
  (`hours`, `opening-hours.json`), so they do not clash with other plugins or
  a theme.
- Use [semantic versioning](https://semver.org) in `plugin.yml`: a new major
  version when a site owner has to change something after updating.
- Say in your README which Wake version you tested with. The
  [plugin interface](../reference/plugins.md) (the methods of
  `Wake\Plugins\Plugin`) is what Wake promises to keep stable; everything else
  inside Wake may change ([decision 0009](../decisions/0009-plugins-as-php-folders.md)).

## Share your plugin

A plugin is shared as a ZIP of its folder, with the folder at the top:

```bash
cd site/plugins && zip -r opening-hours-1.0.0.zip opening-hours
```

Add to the folder:

- a `README.md`: what it does, how to install (*unzip into `site/plugins`*),
  its settings and cards, the Wake versions it works with;
- a `LICENSE` file. MIT matches Wake.

Site owners install it by copying the folder into `site/plugins` by FTP or
Git; the panel has no upload for plugins.

## When something goes wrong

- **The panel shows *Error* next to the plugin.** `plugin.php` threw an
  exception or has a syntax error. The message names the file and line. Wake
  skips the plugin until it starts again; the rest of the site keeps working.
- **The plugin is not listed.** The folder needs a `plugin.php`, and its name
  may only contain lowercase letters, digits, `-` and `_`.
- **The card shows as a plain box.** The card is not registered (plugin
  switched off or failing), or the view name is wrong. Views are
  `{folder}::path.to.view`.
- **A route answers 404.** Check that the plugin runs and that no other
  plugin registers the same path. Plugin routes come before Wake's own
  addresses, in the alphabetical order of the plugin folders.
- **A task never runs.** Maintenance runs after page views at most once a
  minute (or by cron, see `maintenance.mode`). Run it by hand with
  `php wake/artisan wake:maintenance`; errors from tasks are written to
  `storage/logs/laravel.log`.
- **Errors in views or routes** appear in `storage/logs/laravel.log`. With
  `APP_DEBUG=true` in `.env` (only on your computer), Laravel shows them in
  the browser.
