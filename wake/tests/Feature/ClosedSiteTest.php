<?php

use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Cache\PageCache;
use Wake\Livewire\Settings;
use Wake\Stats\TrackingConfig;

beforeEach(function () {
    config(['wake.closed' => true]);
    $this->owner = app(Accounts::class)->create(['name' => 'Dana', 'email' => 'dana@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);
});

it('sends visitors to the sign-in page', function () {
    $this->get('/first')->assertRedirect(route('wake.login'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get('/')->assertRedirect(route('wake.login'));
    $this->get('/feed.xml')->assertRedirect(route('wake.login'));
    $this->get('/sitemap.xml')->assertRedirect(route('wake.login'));
    $this->get('/wake/login')->assertOk()->assertSee('only visible to its team');
});

it('returns visitors to the page they wanted after signing in', function () {
    $this->get('/first');
    $this->post('/wake/login', ['email' => 'dana@example.com', 'password' => 'secret secret'])->assertRedirect(url('/first'));
});

it('shows the site to signed-in team members without letting search engines in', function () {
    $this->actingAs($this->owner)->get('/first')->assertOk()->assertSee('First post')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<meta name="robots" content="noindex">', false);
});

it('tells search engines to stay away', function () {
    $this->get('/robots.txt')->assertOk()->assertSee("Disallow: /\n", false)->assertDontSee('Sitemap');
});

it('neither caches nor counts while closed', function () {
    $this->actingAs($this->owner)->get('http://localhost/first');

    expect(app(PageCache::class)->has('http_localhost/first'))->toBeFalse()
        ->and(app(TrackingConfig::class)->values()['enabled'])->toBeFalse();
});

it('requires a token for the API', function () {
    $this->getJson('/wake/api/v1/entries')->assertUnauthorized();
});

it('is switched in the settings and clears the page cache', function () {
    config(['wake.closed' => false]);
    $this->get('http://localhost/first');
    expect(app(PageCache::class)->has('http_localhost/first'))->toBeTrue();

    Livewire::actingAs($this->owner)->test(Settings::class)->set('closed', true)->call('saveSite');

    expect(Wake\Support\Settings::read()['closed'])->toBeTrue()
        ->and(app(PageCache::class)->has('http_localhost/first'))->toBeFalse()
        ->and(is_file(storage_path('cache/pages/.rules.php')))->toBeFalse();
});
