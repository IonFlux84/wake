<?php

use Illuminate\Http\Request;
use Wake\Cache\PageCache;
use Wake\Http\Middleware\SecurityHeaders;

it('sends security headers with the website and the panel', function () {
    foreach (['/first', '/wake/login', '/sitemap.xml', '/does-not-exist'] as $path) {
        $this->get($path)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Strict-Transport-Security')
            ->assertHeaderMissing('Content-Security-Policy');
    }
});

it('sends Strict-Transport-Security only over HTTPS', function () {
    $this->get('https://localhost/first')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    $this->get('/first', ['X-Forwarded-Proto' => 'https'])->assertHeader('Strict-Transport-Security', 'max-age=31536000');

    config(['wake.security.hsts_max_age' => 0]);
    $this->get('https://localhost/about')->assertHeaderMissing('Strict-Transport-Security')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});

it('can be switched off', function () {
    config(['wake.security.headers' => false]);

    $this->get('https://localhost/first')
        ->assertHeaderMissing('X-Content-Type-Options')
        ->assertHeaderMissing('X-Frame-Options')
        ->assertHeaderMissing('Referrer-Policy')
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('leaves headers alone that a response already set', function () {
    $response = (new SecurityHeaders)->handle(Request::create('/'), fn () => response('x')->header('X-Frame-Options', 'DENY'));

    expect($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('hands the headers to public/index.php for cached pages', function () {
    app(PageCache::class)->writeRules();

    expect(require storage_path('cache/pages/.rules.php'))->toMatchArray([
        'headers' => [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ],
        'hsts' => 'max-age=31536000',
    ]);

    config(['wake.security.headers' => false]);
    app(PageCache::class)->writeRules();

    expect(require storage_path('cache/pages/.rules.php'))->toMatchArray(['headers' => [], 'hsts' => null]);
});
