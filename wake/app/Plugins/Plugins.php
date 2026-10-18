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
 * Plugins in site/plugins/{folder}/:
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

        foreach (glob(Paths::site('plugins/*/plugin.php')) ?: [] as $file) {
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

    public function isEnabled(string $folder): bool
    {
        return ! in_array($folder, (array) config('wake.plugins.disabled', []), true);
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
            if (! $this->isEnabled($folder) || isset($this->booted[$folder])) {
                continue;
            }

            try {
                if (is_dir($info->path.'/views')) {
                    View::addNamespace($folder, $info->path.'/views');
                }

                if (is_dir($info->path.'/lang')) {
                    app('translator')->addJsonPath($info->path.'/lang');
                }

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
