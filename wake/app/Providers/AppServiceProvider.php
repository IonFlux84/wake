<?php

namespace Wake\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Wake\Accounts\Accounts;
use Wake\Accounts\AccountUserProvider;
use Wake\Cache\PageCache;
use Wake\Content\ContentChanged;
use Wake\Content\ContentTypes;
use Wake\Content\Index;
use Wake\Content\Site;
use Wake\Localization\Languages;
use Wake\Maintenance\Maintenance;
use Wake\Markdown\Markdown;
use Wake\Panel\Panel;
use Wake\Support\Paths;
use Wake\Theme\Theme;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeSiteConfig();

        foreach ([Languages::class, ContentTypes::class, Index::class, Site::class, Markdown::class,
            Theme::class, PageCache::class, Maintenance::class, Accounts::class, Panel::class] as $service) {
            $this->app->singleton($service);
        }
    }

    public function boot(): void
    {
        View::addNamespace('wake', resource_path('views'));
        Blade::anonymousComponentPath(resource_path('views/components'), 'wake');
        $this->loadJsonTranslationsFrom(base_path('lang'));
        $this->loadTranslationsFrom(base_path('lang'), 'wake');

        Auth::provider('wake', fn ($app) => new AccountUserProvider($app->make(Accounts::class)));
        $this->app->make(Theme::class)->register();

        Blade::directive('wakeHead', fn () => "<?php echo view('wake::head', ['site' => \$site ?? app(\\Wake\\Content\\Site::class), 'entry' => \$entry ?? null])->render(); ?>");

        Event::listen(ContentChanged::class, fn () => $this->app->make(PageCache::class)->flush());

        $this->app->terminating(function () {
            if (config('wake.maintenance.mode') === 'requests'
                && (! $this->app->runningInConsole() || $this->app->runningUnitTests())) {
                $this->app->make(Maintenance::class)->runIfDue();
            }

            // Servers that keep the application in memory must look at the
            // files again on the next request.
            $this->app->make(Index::class)->recheck();
        });
    }

    /**
     * Settings in site/config.php override Wake's defaults in config/wake.php.
     * A few of them map to Laravel settings.
     */
    protected function mergeSiteConfig(): void
    {
        if ($this->app->configurationIsCached()) {
            return;
        }

        $file = Paths::site('config.php');
        $site = is_file($file) ? (require $file) : [];

        if (! is_array($site)) {
            return;
        }

        $config = $this->app['config'];
        $merged = array_replace_recursive($config->get('wake', []), $site);

        // Lists are replaced as a whole, not merged item by item.
        foreach (['languages', 'images.widths'] as $key) {
            if (data_get($site, $key) !== null) {
                data_set($merged, $key, data_get($site, $key));
            }
        }

        $config->set('wake', $merged);

        if (isset($merged['timezone'])) {
            $config->set('app.timezone', $merged['timezone']);
            date_default_timezone_set($merged['timezone']);
        }

        $config->set('app.locale', array_key_first($merged['languages']));
        $config->set('app.fallback_locale', array_key_first($merged['languages']));
    }
}
