<?php

namespace Wake\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Wake\Accounts\Account;
use Wake\Accounts\Accounts;
use Wake\Accounts\AccountUserProvider;
use Wake\Accounts\Capabilities;
use Wake\Accounts\Roles;
use Wake\Cache\PageCache;
use Wake\Consent\ConsentSettings;
use Wake\Content\Categories;
use Wake\Content\ContentChanged;
use Wake\Content\ContentTypes;
use Wake\Content\Index;
use Wake\Content\Redirects;
use Wake\Content\Site;
use Wake\Content\Writer;
use Wake\Livewire\Assistants;
use Wake\Livewire\BackupManager;
use Wake\Livewire\CategoryManager;
use Wake\Livewire\Consent;
use Wake\Livewire\Dashboard;
use Wake\Livewire\Entries;
use Wake\Livewire\EntryEditor;
use Wake\Livewire\Journey;
use Wake\Livewire\LiveVisitors;
use Wake\Livewire\MailSettingsForm;
use Wake\Livewire\MediaLibrary;
use Wake\Livewire\MenuEditor;
use Wake\Livewire\PageTree;
use Wake\Livewire\PluginManager;
use Wake\Livewire\RedirectManager;
use Wake\Livewire\RoleManager;
use Wake\Livewire\Statistics;
use Wake\Livewire\Team;
use Wake\Livewire\ThemeManager;
use Wake\Livewire\TwoFactorSettings;
use Wake\Localization\Languages;
use Wake\Mail\MailSettings;
use Wake\Maintenance\Maintenance;
use Wake\Markdown\Markdown;
use Wake\Mcp\CurrentToken;
use Wake\Panel\Panel;
use Wake\Plugins\Plugins;
use Wake\Support\Paths;
use Wake\Support\Settings;
use Wake\Theme\Theme;
use Wake\Theme\Themes;

class AppServiceProvider extends ServiceProvider
{
    /** Livewire components of the panel, used as <livewire:wake.{name} />. */
    public const COMPONENTS = [
        'dashboard' => Dashboard::class,
        'live-visitors' => LiveVisitors::class,
        'statistics' => Statistics::class,
        'journey' => Journey::class,
        'consent' => Consent::class,
        'assistants' => Assistants::class,
        'entries' => Entries::class,
        'entry-editor' => EntryEditor::class,
        'team' => Team::class,
        'redirect-manager' => RedirectManager::class,
        'theme-manager' => ThemeManager::class,
        'role-manager' => RoleManager::class,
        'category-manager' => CategoryManager::class,
        'media-library' => MediaLibrary::class,
        'backup-manager' => BackupManager::class,
        'two-factor-settings' => TwoFactorSettings::class,
        'plugin-manager' => PluginManager::class,
        'mail-settings-form' => MailSettingsForm::class,
        'page-tree' => PageTree::class,
        'menu-editor' => MenuEditor::class,
        'settings' => \Wake\Livewire\Settings::class,
    ];

    public function register(): void
    {
        $this->mergeSiteConfig();

        foreach ([Languages::class, ContentTypes::class, Index::class, Site::class, Markdown::class,
            Theme::class, PageCache::class, ConsentSettings::class, Maintenance::class, Accounts::class, Panel::class, Writer::class, Redirects::class, CurrentToken::class, Categories::class, Roles::class, Themes::class, Plugins::class] as $service) {
            $this->app->singleton($service);
        }
    }

    public function boot(): void
    {
        View::addNamespace('wake', resource_path('views'));
        Blade::anonymousComponentPath(resource_path('views/components'), 'wake');
        $this->loadJsonTranslationsFrom(base_path('lang'));
        $this->loadTranslationsFrom(base_path('lang'), 'wake');

        // Livewire answers under /wake, so the website's catch-all route never sees it.
        Livewire::setUpdateRoute(fn ($handle) => Route::post('wake/livewire/update', $handle)->middleware(['web', 'wake.panel']));
        Livewire::setScriptRoute(fn ($handle) => Route::get('wake/livewire/livewire.js', $handle));
        foreach (self::COMPONENTS as $name => $component) {
            Livewire::component('wake.'.$name, $component);
        }

        // Capabilities of roles ("team.manage") answer Laravel's gate and "can:" middleware.
        Gate::before(function ($user, string $ability) {
            if ($user instanceof Account && array_key_exists($ability, Capabilities::all())) {
                return $user->role()->can($ability);
            }
        });

        Auth::provider('wake', fn ($app) => new AccountUserProvider($app->make(Accounts::class)));
        $this->app->make(MailSettings::class)->apply();
        $this->app->make(Theme::class)->register();
        $this->app->make(Plugins::class)->boot();

        Blade::directive('wakeConsentLink', fn () => "<?php echo view('wake::consent-link')->render(); ?>");
        Blade::directive('wakeServices', fn () => "<?php echo view('wake::services')->render(); ?>");
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
     * Settings in settings/config.php override Wake's defaults in config/wake.php.
     * A few of them map to Laravel settings.
     */
    protected function mergeSiteConfig(): void
    {
        if ($this->app->configurationIsCached()) {
            return;
        }

        $file = Paths::settings('config.php');
        $site = is_file($file) ? (require $file) : [];
        $site = is_array($site) ? $site : [];

        // Settings changed in the panel live in settings/general.yml and win
        // over settings/config.php, so the panel always shows what is in effect.
        $site = array_replace_recursive($site, Settings::read());

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
