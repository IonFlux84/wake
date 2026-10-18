<?php

namespace Wake\Plugins;

use Closure;
use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Wake\Maintenance\Maintenance;
use Wake\Maintenance\Task;
use Wake\Support\PublicAssets;

/**
 * What a plugin gets in its plugin.php:
 *
 *     return function (Wake\Plugins\Plugin $plugin) {
 *         $plugin->card('quote', 'quote::cards.quote');
 *         $plugin->routes(fn () => Route::get('hello', fn () => 'Hello'));
 *     };
 */
final class Plugin
{
    public function __construct(public readonly PluginInfo $info, protected Plugins $plugins) {}

    public function key(): string
    {
        return $this->info->folder;
    }

    /**
     * A file of the plugin.
     */
    public function path(string $path = ''): string
    {
        return rtrim($this->info->path.'/'.ltrim($path, '/'), '/');
    }

    /**
     * A setting of the plugin from the panel, or the default from plugin.yml.
     */
    public function option(string $key, mixed $default = null): mixed
    {
        return config('wake.plugins.options.'.$this->key().'.'.$key) ?? $this->info->options()[$key]['default'] ?? $default;
    }

    /**
     * The public URL of a file in the plugin's assets/ folder.
     */
    public function asset(string $path): string
    {
        $path = ltrim($path, '/');

        return PublicAssets::url($this->path('assets/'.$path), 'assets/plugins/'.$this->key().'/'.$path);
    }

    /**
     * A card for Markdown (::: name … :::), rendered with a view. The view
     * gets $name, $value, $attributes and $content. Themes can still
     * override it with cards/{name}.blade.php.
     */
    public function card(string $name, string $view): self
    {
        $this->plugins->cards[$name] = $view;

        return $this;
    }

    /**
     * A field type for categories. The view renders the input in the editor
     * and gets $key, $field, $model (for wire:model) and $disabled.
     *
     * @param  string|array<string, string>  $label  shown in the category editor, per language
     */
    public function field(string $type, string $view, string|array $label = ''): self
    {
        $this->plugins->fields[$type] = ['view' => $view, 'label' => $label ?: $type, 'plugin' => $this->key()];

        return $this;
    }

    /**
     * Website routes. They come before Wake's own addresses, have no session
     * (wrap them in Route::middleware('web') if they need one) and are hidden
     * while the site is closed, like the website.
     */
    public function routes(Closure $routes): self
    {
        $this->plugins->routes[] = $routes;

        return $this;
    }

    /**
     * A maintenance task (a class implementing Wake\Maintenance\Task).
     *
     * @param  class-string<Task>  $task
     */
    public function task(string $task): self
    {
        app(Maintenance::class)->extend($task);

        return $this;
    }

    /**
     * HTML for the <head> of every page: fn ($site, $entry) => '<meta …>'.
     */
    public function head(Closure $html): self
    {
        $this->plugins->heads[] = $html;

        return $this;
    }

    /**
     * Listen to an event, e.g. Wake\Content\ContentChanged.
     */
    public function on(string $event, Closure|string|array $listener): self
    {
        Event::listen($event, $listener);

        return $this;
    }

    /**
     * An Artisan command (php wake/artisan …).
     *
     * @param  class-string<Command>  $command
     */
    public function command(string $command): self
    {
        Artisan::starting(fn (Artisan $artisan) => $artisan->resolve($command));

        return $this;
    }
}
