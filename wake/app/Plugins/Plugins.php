<?php

namespace Wake\Plugins;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use Wake\Support\Paths;

/**
 * Plugins in plugins/{folder}/:
 *
 *     plugin.yml   name, description, version, author, url, options
 *     plugin.php   returns function (Wake\Plugins\Plugin $plugin) { … }
 *     views/       Blade views, as {folder}::name
 *     lang/        translations ({language}.json)
 *     assets/      files for the website, see Plugin::asset()
 *
 * Plugins run with the same rights as Wake itself. A plugin that fails to
 * start is skipped and its error is shown in the panel.
 */
class Plugins
{
    /** @var array<string, string> card name => view */
    public array $cards = [];

    /** @var array<string, array{view: string, label: string|array<string, string>, plugin: string}> */
    public array $fields = [];

    /** @var list<\Closure> */
    public array $routes = [];

    /** @var list<\Closure> */
    public array $heads = [];

    /** @var array<string, array{plugin: string, name: string, view: string, label: string|array<string, string>, icon: string, can: string}> "{folder}.{name}" => panel page */
    public array $pages = [];

    /** @var list<class-string> MCP tools */
    public array $tools = [];

    /** @var array<string, string> folder => error message */
    protected array $errors = [];

    /** @var array<string, Plugin> */
    protected array $booted = [];

    /**
     * Every plugin folder with a plugin.php, by folder name.
     *
     * @return array<string, PluginInfo>
     */
    public function all(): array
    {
        $plugins = [];

        foreach (glob(Paths::plugins('*/plugin.php')) ?: [] as $file) {
            $path = dirname($file);
            $folder = basename($path);

            if (! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $folder)) {
                continue;
            }

            try {
                $data = is_file($path.'/plugin.yml') ? (Yaml::parseFile($path.'/plugin.yml') ?: []) : [];
            } catch (Throwable) {
                $data = [];
            }

            $plugins[$folder] = new PluginInfo($folder, $path, is_array($data) ? $data : []);
        }

        ksort($plugins);

        return $plugins;
    }

    public function get(string $folder): ?PluginInfo
    {
        return $this->all()[$folder] ?? null;
    }

    public function isEnabled(string $folder, ?PluginInfo $info = null): bool
    {
        if (in_array($folder, (array) config('wake.plugins.disabled', []), true)) {
            return false;
        }

        return in_array($folder, (array) config('wake.plugins.enabled', []), true)
            || (($info ?? $this->get($folder))?->enabledByDefault() ?? true);
    }

    public function isRunning(string $folder): bool
    {
        return isset($this->booted[$folder]);
    }

    public function error(string $folder): ?string
    {
        return $this->errors[$folder] ?? null;
    }

    /**
     * Starts all enabled plugins.
     */
    public function boot(): void
    {
        foreach ($this->all() as $folder => $info) {
            if (! $this->isEnabled($folder, $info) || isset($this->booted[$folder])) {
                continue;
            }

            try {
                if (is_dir($info->path.'/views')) {
                    View::addNamespace($folder, $info->path.'/views');
                }

                if (is_dir($info->path.'/lang')) {
                    app('translator')->addJsonPath($info->path.'/lang');
                }

                $this->autoload($info);
                $setup = (static fn (string $file) => require $file)($info->path.'/plugin.php');
                $plugin = new Plugin($info, $this);

                if (is_callable($setup)) {
                    $setup($plugin);
                }

                $this->booted[$folder] = $plugin;
            } catch (Throwable $e) {
                $this->errors[$folder] = $e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')';
                Log::warning("Plugin {$folder} could not be started: ".$e->getMessage());
            }
        }
    }

    /**
     * Makes the plugin's classes loadable: the PSR-4 folders from "autoload"
     * in plugin.yml, and a bundled vendor/autoload.php if there is one.
     */
    protected function autoload(PluginInfo $info): void
    {
        if (is_file($info->path.'/vendor/autoload.php')) {
            require_once $info->path.'/vendor/autoload.php';
        }

        $map = $info->autoload();

        if ($map === []) {
            return;
        }

        spl_autoload_register(function (string $class) use ($map) {
            foreach ($map as $prefix => $dir) {
                if (str_starts_with($class, $prefix)) {
                    $file = $dir.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

                    if (is_file($file)) {
                        require_once $file;
                    }

                    return;
                }
            }
        });
    }

    /**
     * Called while routes are loaded, before the website's catch-all route.
     */
    public function registerRoutes(): void
    {
        // Like the website itself, plugin addresses are hidden while the site is closed.
        foreach ($this->routes as $routes) {
            Route::middleware('wake.closed')->group($routes);
        }
    }

    /**
     * Called inside the panel's signed-in route group: one address per plugin page.
     */
    public function registerPanelRoutes(): void
    {
        foreach ($this->pages as $key => $page) {
            Route::get('plugin/'.$page['plugin'].'/'.$page['name'], fn () => view('wake::panel.plugin', [
                'page' => $page,
                'active' => 'plugin:'.$key,
                'title' => $this->label($page['label']),
            ]))->middleware('can:'.$page['can'])->name('plugin.'.$key);
        }
    }

    /**
     * Panel pages as menu items: [key, route, label, icon, capability].
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    public function menu(): array
    {
        return array_values(array_map(fn (string $key) => [
            'plugin:'.$key, 'wake.plugin.'.$key, $this->label($this->pages[$key]['label']), $this->pages[$key]['icon'], $this->pages[$key]['can'],
        ], array_keys($this->pages)));
    }

    /**
     * A text given as one string or one per language.
     *
     * @param  string|array<string, string>  $label
     */
    public function label(string|array $label): string
    {
        return (string) (is_array($label) ? ($label[app()->getLocale()] ?? reset($label)) : $label);
    }

    /**
     * HTML that plugins add to the <head> of a page.
     */
    public function head(mixed $site, mixed $entry): HtmlString
    {
        $html = '';

        foreach ($this->heads as $head) {
            try {
                $html .= (string) $head($site, $entry);
            } catch (Throwable $e) {
                Log::warning('A plugin failed to add to <head>: '.$e->getMessage());
            }
        }

        return new HtmlString($html);
    }
}
