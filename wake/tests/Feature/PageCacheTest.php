<?php

use Wake\Cache\PageCache;
use Wake\Content\Index;

it('stores website pages in the page cache', function () {
    $this->get('http://localhost/first')->assertOk()->assertHeader('X-Wake-Cache', 'miss');

    expect(app(PageCache::class)->has('http_localhost/first'))->toBeTrue()
        ->and(file_get_contents(app(PageCache::class)->file('http_localhost/first')))->toContain('Post: First post')
        ->and(require storage_path('cache/pages/.rules.php'))->toMatchArray(['cookie' => 'wake_session', 'ttl' => 300]);
});

it('does not cache requests with a query string, a session cookie or errors', function () {
    $this->get('http://localhost/first?utm_source=x');
    $this->withCookie('wake_session', 'x')->get('http://localhost/about');
    $this->get('http://localhost/missing');

    $cache = app(PageCache::class);

    expect($cache->has('http_localhost/first'))->toBeFalse()
        ->and($cache->has('http_localhost/about'))->toBeFalse()
        ->and($cache->has('http_localhost/missing'))->toBeFalse();
});

it('can be switched off', function () {
    config(['wake.page_cache.enabled' => false]);

    $this->get('http://localhost/first')->assertHeaderMissing('X-Wake-Cache');

    expect(app(PageCache::class)->has('http_localhost/first'))->toBeFalse();
});

it('is cleared when content changes', function () {
    $this->get('http://localhost/first');
    $this->writeContent('posts/second/content.md', "---\ntitle: Changed\n---\n");

    app(Index::class)->refresh();

    expect(app(PageCache::class)->has('http_localhost/first'))->toBeFalse()
        ->and(is_file(storage_path('cache/pages/.rules.php')))->toBeTrue();
});

it('is served by public/index.php without booting Laravel', function () {
    $this->get('http://localhost/first');

    // A copy of the front controller next to the sandbox storage. Laravel is
    // not reachable from there, so only a cache hit can produce output.
    copy(dirname(base_path()).'/public/index.php', $this->sandbox.'/public/index.php');

    $script = '$_SERVER["REQUEST_URI"] = "/first"; $_SERVER["REQUEST_METHOD"] = "GET"; $_SERVER["HTTP_HOST"] = "localhost"; require '.var_export($this->sandbox.'/public/index.php', true).';';

    expect(shell_exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($script).' 2>&1'))->toContain('Post: First post');
});

it('keeps pages of different hosts and schemes apart', function () {
    $this->get('http://localhost/first');
    $this->get('https://www.example.org/first');

    expect(app(PageCache::class)->has('http_localhost/first'))->toBeTrue()
        ->and(app(PageCache::class)->has('https_www.example.org/first'))->toBeTrue()
        ->and(PageCache::hostKey(['HTTPS' => 'on', 'HTTP_HOST' => 'Example.org:8443']))->toBe('https_example.org_8443');
});
