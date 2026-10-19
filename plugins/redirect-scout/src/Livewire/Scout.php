<?php

namespace RedirectScout\Livewire;

use Illuminate\View\View;
use Livewire\Component;
use RedirectScout\Redirector;
use RedirectScout\Scanner;
use RedirectScout\Store;
use Wake\Cache\PageCache;
use Wake\Content\Categories;
use Wake\Content\Query;

/**
 * The panel page: start a search of the old website, follow its progress
 * and give every address that leads nowhere a target.
 */
class Scout extends Component
{
    /** Suggestions from this score on are safe enough to take all at once. */
    public const SAFE = 90;

    public string $site = '';

    public bool $sitemap = true;

    public bool $crawl = true;

    public bool $wayback = false;

    public string $list = '';

    public string $filter = 'missing';

    public string $search = '';

    public int $page = 1;

    /** @var array<string, string> address key => target typed in */
    public array $targets = [];

    public function mount(Store $store): void
    {
        abort_unless(auth()->user()->can('settings.manage'), 403);
        $this->site = (string) $store->get('site');
        $options = (array) $store->get('options');
        $this->sitemap = (bool) ($options['sitemap'] ?? true);
        $this->crawl = (bool) ($options['crawl'] ?? true);
        $this->wayback = (bool) ($options['wayback'] ?? false);
    }

    public function start(Scanner $scanner): void
    {
        $this->validate([
            'site' => ['required', 'url:http,https', 'max:300'],
            'list' => ['nullable', 'string', 'max:200000'],
        ], ['site.url' => __('An address like https://www.example.org.')]);

        $plugin = app('redirect-scout');
        $scanner->start($this->site, [
            'sitemap' => $this->sitemap,
            'crawl' => $this->crawl,
            'wayback' => $this->wayback,
            'list' => array_values(array_filter(array_map('trim', preg_split('/\R/', $this->list)))),
            'limit' => (int) $plugin->option('limit', 2000),
            'delay' => (int) $plugin->option('delay', 250),
        ]);

        $this->list = '';
        $this->filter = 'all';
    }

    public function work(Scanner $scanner): void
    {
        $scanner->work(2.5);

        if (! $scanner->running()) {
            $this->filter = 'missing';
        }
    }

    public function stop(Scanner $scanner): void
    {
        $scanner->stop();
    }

    public function recheck(Scanner $scanner): void
    {
        $scanner->recheck();
    }

    public function clear(Store $store): void
    {
        $store->clear();
        $this->reset('targets', 'page');
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->page = 1;
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function save(Redirector $redirector, string $key): void
    {
        $this->apply($redirector, $key, (string) ($this->targets[$key] ?? ''));
    }

    public function accept(Redirector $redirector, Store $store, string $key): void
    {
        $this->apply($redirector, $key, (string) ($store->address($this->path($key))['suggestion']['target'] ?? ''));
    }

    public function gone(Redirector $redirector, string $key): void
    {
        $this->apply($redirector, $key, 'gone');
    }

    public function ignore(Redirector $redirector, string $key, bool $ignored = true): void
    {
        $redirector->ignore($this->path($key), $ignored);
    }

    public function remove(Redirector $redirector, string $key): void
    {
        $redirector->remove($this->path($key));
    }

    /**
     * Takes every suggestion that is safe enough.
     */
    public function acceptSafe(Redirector $redirector, Store $store): void
    {
        $saved = 0;

        foreach ($store->addresses() as $path => $address) {
            if (Store::filterOf($address) === 'missing' && ($address['suggestion']['score'] ?? 0) >= self::SAFE
                && $redirector->save((string) $path, $address['suggestion']['target'], flush: false) === null) {
                $saved++;
            }
        }

        app(PageCache::class)->flush();
        session()->flash('status', trans_choice(':count suggestion taken.|:count suggestions taken.', $saved));
    }

    /**
     * 410 or ignore for every address the filter and search show, e.g. all
     * spam pages under /shop/ at once.
     */
    public function bulk(Redirector $redirector, Store $store, string $action): void
    {
        $paths = array_keys($this->visible($store));

        foreach ($paths as $path) {
            $action === 'gone' ? $redirector->save((string) $path, 'gone', flush: false) : $redirector->ignore((string) $path);
        }

        app(PageCache::class)->flush();
        session()->flash('status', trans_choice(':count address done.|:count addresses done.', count($paths)));
    }

    /**
     * The addresses that the filter and the search let through.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function visible(Store $store): array
    {
        $search = mb_strtolower(trim($this->search));

        return array_filter($store->addresses(), fn (array $a) => ($this->filter === 'all' || Store::filterOf($a) === $this->filter)
            && ($search === '' || str_contains(mb_strtolower($a['path'].' '.($a['title'] ?? '')), $search)));
    }

    protected function apply(Redirector $redirector, string $key, string $target): void
    {
        $error = $redirector->save($this->path($key), $target);

        if ($error !== null) {
            $this->addError('targets.'.$key, $error);

            return;
        }

        unset($this->targets[$key]);
    }

    protected function path(string $key): string
    {
        foreach (array_keys(app(Store::class)->addresses()) as $path) {
            if (md5((string) $path) === $key) {
                return (string) $path;
            }
        }

        abort(404);
    }

    public function render(Store $store, Scanner $scanner): View
    {
        $store->reload();
        $rows = $this->visible($store);

        uasort($rows, fn (array $a, array $b) => strnatcasecmp((string) $a['path'], (string) $b['path']));

        $perPage = 50;
        $pages = max(1, (int) ceil(count($rows) / $perPage));
        $this->page = min(max(1, $this->page), $pages);

        $all = count($store->addresses());
        $checked = $all - count((array) $store->get('check', []));
        $fetched = (int) $store->get('fetched');

        return view('redirect-scout::livewire.scout', [
            'store' => $store,
            'running' => $scanner->running(),
            'phase' => $store->get('phase'),
            'counts' => $store->counts(),
            'rows' => array_slice($rows, ($this->page - 1) * $perPage, $perPage, true),
            'total' => count($rows),
            'pages' => $pages,
            'progress' => match ($store->get('phase')) {
                'fetch' => (int) round($fetched / max(1, $fetched + count((array) $store->get('fetch', []))) * 100),
                'check' => (int) round($checked / max(1, $all) * 100),
                default => 0,
            },
            'safe' => count(array_filter($store->addresses(), fn (array $a) => Store::filterOf($a) === 'missing' && ($a['suggestion']['score'] ?? 0) >= self::SAFE)),
            'entries' => (new Query)->published()->orderBy('title')->get(),
            'categories' => app(Categories::class)->all(),
        ]);
    }
}
