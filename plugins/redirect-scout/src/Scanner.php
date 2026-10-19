<?php

namespace RedirectScout;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;
use Wake\Support\Files;

/**
 * Searches the old website in small steps, so it runs inside ordinary
 * requests on shared hosting: the panel calls work() again and again until
 * the phase is "done".
 *
 * 1. discover: robots.txt, sitemaps, the Wayback Machine, a pasted list
 * 2. fetch:    every address on the old website, following links
 * 3. check:    what this site answers for each path, plus a suggestion
 */
class Scanner
{
    /** File types nobody links to from outside; the Wayback Machine lists them by the thousand. */
    protected const ASSETS = ['css', 'js', 'mjs', 'map', 'woff', 'woff2', 'ttf', 'eot', 'otf', 'ico', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'bmp', 'json', 'xml', 'txt'];

    public function __construct(protected Store $store, protected Checker $checker, protected Suggester $suggester) {}

    /**
     * Starts a new search. Decisions about addresses found before (ignored)
     * are kept; everything else is looked at again.
     *
     * @param  array{sitemap?: bool, crawl?: bool, wayback?: bool, list?: list<string>, limit?: int, delay?: int}  $options
     */
    public function start(string $site, array $options): void
    {
        $site = rtrim($site, '/');
        $pending = [['robots']];

        if ($options['sitemap'] ?? true) {
            $pending[] = ['sitemap', $site.'/sitemap.xml'];
        }

        if ($options['wayback'] ?? false) {
            $pending[] = ['wayback'];
        }

        $before = (string) $this->store->get('site');

        if ($before !== '' && ! Address::sameHost((string) parse_url($before, PHP_URL_HOST), (string) parse_url($site, PHP_URL_HOST))) {
            $this->store->put(['addresses' => []]);
        }

        $this->store->put([
            'site' => $site,
            'phase' => 'discover',
            'options' => $options,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'pending' => $pending,
            'sitemaps' => [],
            'fetch' => [],
            'queued' => [],
            'check' => [],
            'fetched' => 0,
            'disallow' => [],
            'messages' => [],
        ]);

        $this->found('', ['home']);

        foreach ($options['list'] ?? [] as $url) {
            if (($path = Address::path(Address::absolute($url, $site.'/') ?? '', $site)) !== null) {
                $this->found($path, ['list']);
            }
        }

        $this->store->save();
    }

    /**
     * Checks every address against this site again, e.g. after new redirects or pages.
     */
    public function recheck(): void
    {
        $this->store->put(['phase' => 'check', 'check' => array_map('strval', array_keys($this->store->addresses())), 'finished_at' => null]);
        $this->store->save();
    }

    public function stop(): void
    {
        $this->store->put(['phase' => 'done', 'pending' => [], 'fetch' => [], 'check' => [], 'finished_at' => now()->toIso8601String()]);
        $this->store->save();
    }

    public function running(): bool
    {
        return in_array($this->store->get('phase'), ['discover', 'fetch', 'check'], true);
    }

    /**
     * Works for about $seconds, then saves and returns. Call again until
     * running() is false.
     */
    public function work(float $seconds = 3.0): void
    {
        // A second poll while the first still works simply returns.
        // A second poll while the first one still works simply returns.
        Files::lock('redirect-scout', function () use ($seconds) {
            $until = microtime(true) + $seconds;
            $this->store->reload();

            while ($this->running() && microtime(true) < $until) {
                match ($this->store->get('phase')) {
                    'discover' => $this->discover(),
                    'fetch' => $this->fetchNext(),
                    'check' => $this->checkNext(),
                };
            }

            $this->store->save();
        }, wait: false);
    }

    // Phase 1 ---------------------------------------------------------------

    protected function discover(): void
    {
        $pending = $this->store->get('pending', []);
        $task = array_shift($pending);
        $this->store->put(['pending' => $pending]);

        match ($task[0] ?? null) {
            'robots' => $this->robots(),
            'sitemap' => $this->sitemap($task[1]),
            'wayback' => $this->wayback(),
            default => $this->store->put(['phase' => 'fetch']),
        };
    }

    protected function robots(): void
    {
        $response = $this->request($this->site().'/robots.txt');

        if ($response === null || ! $response->successful()) {
            return;
        }

        $disallow = [];
        $applies = false;

        foreach (preg_split('/\R/', $response->body()) as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line));

            if (preg_match('/^user-agent:\s*(.+)$/i', $line, $m)) {
                $applies = trim($m[1]) === '*';
            } elseif ($applies && preg_match('/^disallow:\s*(\S+)/i', $line, $m)) {
                $disallow[] = trim($m[1], '/');
            } elseif (preg_match('/^sitemap:\s*(\S+)/i', $line, $m)) {
                $this->queueSitemap($m[1]);
            }
        }

        $this->store->put(['disallow' => array_values(array_unique(array_filter($disallow, fn ($d) => $d !== '')))]);
    }

    protected function sitemap(string $url): void
    {
        $response = $this->request($url);

        if ($response === null || ! $response->successful()) {
            return;
        }

        $body = $response->body();

        if (str_ends_with(strtolower((string) parse_url($url, PHP_URL_PATH)), '.gz') && ($inflated = @gzdecode($body)) !== false) {
            $body = $inflated;
        }

        preg_match_all('#<loc>\s*(.*?)\s*</loc>#is', $body, $matches);
        $index = (bool) preg_match('/<sitemapindex/i', $body);

        foreach ($matches[1] as $loc) {
            $loc = html_entity_decode(preg_replace('/^<!\[CDATA\[(.*)\]\]>$/s', '$1', $loc), ENT_QUOTES | ENT_XML1);

            if ($index) {
                $this->queueSitemap($loc);
            } elseif (($path = Address::path($loc, $this->site())) !== null) {
                $this->found($path, ['sitemap']);
            }
        }
    }

    protected function queueSitemap(string $url): void
    {
        $seen = $this->store->get('sitemaps', []);

        if (in_array($url, $seen, true) || count($seen) >= 50 || Address::path($url, $this->site()) === null) {
            return;
        }

        $pending = $this->store->get('pending', []);
        array_unshift($pending, ['sitemap', $url]);
        $this->store->put(['pending' => $pending, 'sitemaps' => [...$seen, $url]]);
    }

    /**
     * Addresses the Internet Archive saw on the old website, also those no
     * longer linked from anywhere.
     */
    protected function wayback(): void
    {
        $host = preg_replace('/^www\./', '', (string) parse_url($this->site(), PHP_URL_HOST));
        $response = $this->request('https://web.archive.org/cdx/search/cdx?'.http_build_query([
            'url' => $host,
            'matchType' => 'domain',
            'output' => 'json',
            'fl' => 'original',
            'collapse' => 'urlkey',
            'filter' => 'statuscode:[23]..',
            'limit' => $this->limit() * 5,
        ]), 60);

        if ($response === null || ! $response->successful()) {
            $this->message('The Wayback Machine did not answer. Try again later.');

            return;
        }

        foreach (array_slice((array) $response->json(), 1) as $row) {
            $path = Address::path((string) ($row[0] ?? ''), $this->site());

            if ($path !== null && ! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::ASSETS, true)) {
                $this->found($path, ['wayback']);
            }
        }
    }

    // Phase 2 ---------------------------------------------------------------

    protected function fetchNext(): void
    {
        $queue = $this->store->get('fetch', []);

        if ($queue === [] || $this->store->get('fetched') >= $this->limit()) {
            $this->store->put(['phase' => 'check', 'fetch' => [], 'check' => array_map('strval', array_keys($this->store->addresses()))]);

            return;
        }

        $path = (string) array_shift($queue);
        $this->store->put(['fetch' => $queue, 'fetched' => $this->store->get('fetched') + 1]);

        if ($this->disallowed($path)) {
            $this->store->remember($path, ['old_status' => null, 'note' => 'robots.txt']);

            return;
        }

        $url = Address::url($path, $this->site());
        $file = Address::type($path) === 'file';
        $response = $this->request($url, 15, $file ? 'head' : 'get');

        if ($response === null) {
            $this->store->remember($path, ['old_status' => 0]);

            return;
        }

        $values = ['old_status' => $response->status()];

        if ($response->redirect() && ($location = $response->header('Location'))) {
            $values['old_location'] = $location;

            if (($next = Address::path(Address::absolute($location, $url) ?? '', $this->site())) !== null) {
                $this->found($next, ['link']);
            }
        }

        if (! $file && $response->successful() && str_contains(strtolower($response->header('Content-Type')), 'html')) {
            $html = $response->body();
            $values['title'] = $this->title($html);

            if ($this->store->get('options')['crawl'] ?? true) {
                $this->links($html, $url);
            }
        }

        $this->store->remember($path, $values);
        usleep(max(0, $this->delay()) * 1000);
    }

    protected function title(string $html): ?string
    {
        foreach (['#<h1[^>]*>(.*?)</h1>#is', '#<title[^>]*>(.*?)</title>#is'] as $pattern) {
            if (preg_match($pattern, $html, $m) && ($text = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5))) !== '') {
                return mb_substr(preg_replace('/\s+/u', ' ', $text), 0, 200);
            }
        }

        return null;
    }

    protected function links(string $html, string $url): void
    {
        if (preg_match('#<base\s[^>]*href\s*=\s*["\']([^"\']+)#i', $html, $m)) {
            $url = Address::absolute($m[1], $url) ?? $url;
        }

        preg_match_all('#<a\s[^>]*?href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))#i', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $link = $match[1] !== '' ? $match[1] : (($match[2] ?? '') !== '' ? $match[2] : ($match[3] ?? ''));

            if (($absolute = Address::absolute($link, $url)) !== null && ($path = Address::path($absolute, $this->site())) !== null) {
                $this->found($path, ['link']);
            }
        }
    }

    // Phase 3 ---------------------------------------------------------------

    protected function checkNext(): void
    {
        $queue = $this->store->get('check', []);

        if ($queue === []) {
            $this->store->put(['phase' => 'done', 'finished_at' => now()->toIso8601String()]);

            return;
        }

        $path = (string) array_shift($queue);
        $this->store->put(['check' => $queue]);
        $address = $this->store->address($path);

        if ($address === null) {
            return;
        }

        $result = $this->checker->check($path);
        $suggestion = $result['state'] === 'missing' ? $this->suggester->suggest($path, $address['title'] ?? null) : null;

        $this->store->remember($path, [...$result, 'suggestion' => $suggestion, 'checked_at' => now()->toIso8601String()]);
    }

    // Helpers ---------------------------------------------------------------

    /**
     * Remembers an address and queues it for fetching, up to the limit.
     *
     * @param  list<string>  $sources
     */
    protected function found(string $path, array $sources): void
    {
        $known = $this->store->address($path) !== null;

        if (! $known && count($this->store->addresses()) >= $this->limit()) {
            return;
        }

        $this->store->remember($path, ['url' => Address::url($path, $this->site()), 'type' => Address::type($path), 'sources' => $sources]);

        $queued = $this->store->get('queued', []);

        // Most addresses only the archive knows are gone from the old website; visiting each would double the time.
        if (! isset($queued[$path]) && $sources !== ['wayback']) {
            $fetch = $this->store->get('fetch', []);
            $fetch[] = $path;
            $queued[$path] = true;
            $this->store->put(['fetch' => $fetch, 'queued' => $queued]);
        }
    }

    protected function disallowed(string $path): bool
    {
        foreach ($this->store->get('disallow', []) as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    protected function request(string $url, int $timeout = 15, string $method = 'get'): ?Response
    {
        try {
            return Http::timeout($timeout)
                ->withUserAgent('WakeRedirectScout/1.0 (+https://getwake.dev)')
                ->withOptions(['allow_redirects' => false])
                ->{$method}($url);
        } catch (Throwable $e) {
            $this->message(parse_url($url, PHP_URL_PATH).': '.$e->getMessage());

            return null;
        }
    }

    protected function message(string $text): void
    {
        $messages = $this->store->get('messages', []);
        $this->store->put(['messages' => array_slice([...$messages, $text], -20)]);
    }

    protected function site(): string
    {
        return (string) $this->store->get('site');
    }

    protected function limit(): int
    {
        return max(1, (int) ($this->store->get('options')['limit'] ?? 2000));
    }

    protected function delay(): int
    {
        return (int) ($this->store->get('options')['delay'] ?? 250);
    }
}
