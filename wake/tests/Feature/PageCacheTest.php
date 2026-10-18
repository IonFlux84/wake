<?php

use Wake\Cache\PageCache;
use Wake\Content\Index;

it('stores website pages in the page cache', function () {
    $this->get('/first')->assertOk()->assertHeader('X-Wake-Cache', 'miss');

    expect(app(PageCache::class)->has('/first'))->toBeTrue()
        ->and(file_get_contents(app(PageCache::class)->file('/first')))->toContain('Post: First post')
        ->and(require storage_path('cache/pages/.rules.php'))->toMatchArray(['cookie' => 'wake_session', 'ttl' => 300]);
});

it('does not cache requests with a query string, a session cookie or errors', function () {
    $this->get('/first?utm_source=x');
    $this->withCookie('wake_session', 'x')->get('/about');
    $this->get('/missing');

    $cache = app(PageCache::class);

    expect($cache->has('/first'))->toBeFalse()
        ->and($cache->has('/about'))->toBeFalse()
        ->and($cache->has('/missing'))->toBeFalse();
});

it('can be switched off', function () {
    config(['wake.page_cache.enabled' => false]);

    $this->get('/first')->assertHeaderMissing('X-Wake-Cache');

    expect(app(PageCache::class)->has('/first'))->toBeFalse();
});

it('is cleared when content changes', function () {
    $this->get('/first');
    $this->writeContent('posts/2026-02-01-second/index.en.md', "---\ntitle: Changed\n---\n");

    app(Index::class)->refresh();

    expect(app(PageCache::class)->has('/first'))->toBeFalse()
        ->and(is_file(storage_path('cache/pages/.rules.php')))->toBeTrue();
});

it('is served by public/index.php without booting Laravel', function () {
    $this->get('/first');

    // A copy of the front controller next to the sandbox storage. Laravel is
    // not reachable from there, so only a cache hit can produce output.
    copy(dirname(base_path()).'/public/index.php', $this->sandbox.'/public/index.php');

    $script = '$_SERVER["REQUEST_URI"] = "/first"; $_SERVER["REQUEST_METHOD"] = "GET"; require '.var_export($this->sandbox.'/public/index.php', true).';';

    expect(shell_exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($script).' 2>&1'))->toContain('Post: First post');
});
