<?php

use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RedirectScout\Address;
use RedirectScout\Livewire\Scout;
use RedirectScout\Scanner;
use RedirectScout\Store;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Accounts\Tokens;
use Wake\Content\Redirects;

// The plugin in plugins/redirect-scout, shipped with Wake.

function fakeOldWebsite(): void
{
    $page = fn (string $title, string $body = '') => Http::response("<html><head><title>{$title} | Old club</title></head><body>{$body}</body></html>", 200, ['Content-Type' => 'text/html; charset=utf-8']);

    Http::fake([
        'old.example.org/robots.txt' => Http::response("User-agent: *\nDisallow: /intern/\nSitemap: https://old.example.org/sitemap.xml\n"),
        'old.example.org/sitemap.xml' => Http::response('<?xml version="1.0"?><urlset><url><loc>https://old.example.org/news/first.html</loc></url><url><loc>https://old.example.org/about</loc></url></urlset>'),
        'old.example.org/news/first.html' => $page('First post', '<a href="../team/">Team</a> <a href="/files/photo.png">Photo</a> <a href="https://elsewhere.org/x">x</a> <a href="mailto:a@b.c">mail</a>'),
        'old.example.org/about' => $page('About'),
        'old.example.org/team' => $page('Our team', '<a href="/intern/secret">intern</a> <a href="/history?page=2">History</a>'),
        'old.example.org/history' => $page('Club history'),
        'old.example.org/files/photo.png' => Http::response('', 200, ['Content-Type' => 'image/png']),
        'old.example.org/*' => Http::response('Not found', 404),
        'web.archive.org/*' => Http::response(json_encode([['original'], ['http://www.old.example.org/forgotten-page'], ['http://old.example.org/style.css']])),
    ]);
}

function scoutScan(array $options = []): Store
{
    $scanner = app(Scanner::class);
    $scanner->start('https://old.example.org', ['delay' => 0, ...$options]);

    while ($scanner->running()) {
        $scanner->work(5);
    }

    $store = app(Store::class);
    $store->reload();

    return $store;
}

function scoutOwner()
{
    return app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);
}

it('turns links into paths of the old website', function () {
    $site = 'https://old.example.org';

    expect(Address::path('https://www.old.example.org/a/b/?x=1#top', $site))->toBe('a/b')
        ->and(Address::path('https://elsewhere.org/a', $site))->toBeNull()
        ->and(Address::path('/%C3%BCber-uns', $site))->toBe('über-uns')
        ->and(Address::absolute('../team/', 'https://old.example.org/news/first.html'))->toBe('https://old.example.org/team/')
        ->and(Address::absolute('mailto:a@b.c', $site))->toBeNull()
        ->and(Address::type('files/flyer.PDF'))->toBe('file')
        ->and(Address::type('news/first.html'))->toBe('page');
});

it('finds addresses in the sitemap and by following links, and respects robots.txt', function () {
    fakeOldWebsite();
    $store = scoutScan();

    expect(array_keys($store->addresses()))->toEqualCanonicalizing(['', 'news/first.html', 'about', 'team', 'files/photo.png', 'intern/secret', 'history'])
        ->and($store->address('news/first.html')['title'])->toBe('First post | Old club')
        ->and($store->address('news/first.html')['sources'])->toBe(['sitemap'])
        ->and($store->address('team')['sources'])->toBe(['link'])
        ->and($store->address('intern/secret')['note'])->toBe('robots.txt');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/intern/'));
});

it('checks every address against this site and suggests targets', function () {
    fakeOldWebsite();
    $store = scoutScan();

    expect($store->address('')['state'])->toBe('exists')
        ->and($store->address('about')['state'])->toBe('exists')
        ->and($store->address('news/first.html')['state'])->toBe('missing')
        ->and($store->address('news/first.html')['suggestion'])->toMatchArray(['target' => 'posts/first', 'reason' => 'same slug'])
        ->and($store->address('files/photo.png')['suggestion'])->toMatchArray(['target' => 'media/posts/first/photo.png', 'score' => 100]);
});

it('asks the Wayback Machine for addresses nobody links to anymore', function () {
    fakeOldWebsite();
    $store = scoutScan(['wayback' => true]);

    expect($store->address('forgotten-page'))->not->toBeNull()
        ->and($store->address('forgotten-page')['sources'])->toBe(['wayback'])
        ->and($store->address('forgotten-page')['old_status'])->toBe(404)
        ->and($store->address('style.css'))->toBeNull();
});

it('stops at the limit', function () {
    fakeOldWebsite();

    expect(count(scoutScan(['limit' => 3])->addresses()))->toBe(3);
});

it('saves targets from the panel and keeps the list up to date', function () {
    fakeOldWebsite();
    scoutScan();
    $owner = scoutOwner();

    $this->actingAs($owner)->get('/wake/plugin/redirect-scout/addresses')->assertOk()
        ->assertSee('Old addresses')->assertSee('/news/first.html')->assertSee('same slug');

    $component = Livewire::actingAs($owner)->test(Scout::class)
        ->call('accept', md5('news/first.html'))
        ->set('targets.'.md5('history'), '/tag/travel')->call('save', md5('history'))
        ->call('gone', md5('team'))
        ->set('targets.'.md5('intern/secret'), 'posts/nothing')->call('save', md5('intern/secret'))
        ->assertHasErrors('targets.'.md5('intern/secret'));

    $redirects = app(Redirects::class);
    expect($redirects->target('en', 'news/first.html'))->toBe('posts/first')
        ->and($redirects->target('en', 'history'))->toBe('/tag/travel')
        ->and($redirects->target('en', 'team'))->toBe('gone');

    $this->get('/news/first.html')->assertStatus(301)->assertRedirect(url('/first'));

    $component->call('ignore', md5('intern/secret'));
    $store = app(Store::class);
    $store->reload();

    expect($store->counts())->toMatchArray(['redirect' => 2, 'gone' => 1, 'ignored' => 1, 'missing' => 1]);
});

it('takes all safe suggestions at once', function () {
    fakeOldWebsite();
    scoutScan();

    Livewire::actingAs(scoutOwner())->test(Scout::class)->call('acceptSafe');

    expect(app(Redirects::class)->target('en', 'news/first.html'))->toBe('posts/first')
        ->and(app(Redirects::class)->target('en', 'files/photo.png'))->toBe('media/posts/first/photo.png')
        ->and(app(Redirects::class)->target('en', 'history'))->toBeNull();
});

it('runs the search from the panel step by step', function () {
    fakeOldWebsite();

    Livewire::actingAs(scoutOwner())->test(Scout::class)
        ->set('site', 'https://old.example.org')
        ->call('start')
        ->assertSee('Reading sitemaps')
        ->call('work')->call('work')->call('work')->call('work')
        ->assertSee('news/first.html');
});

it('keeps the page from editors', function () {
    $editor = app(Accounts::class)->create(['name' => 'Eda', 'email' => 'eda@example.com', 'password' => 'secret secret', 'role' => Role::Editor]);

    $this->actingAs($editor)->get('/wake/plugin/redirect-scout/addresses')->assertForbidden();
});

it('lets assistants list addresses and set targets', function () {
    fakeOldWebsite();
    scoutScan();
    [$token] = app(Tokens::class)->create(scoutOwner(), 'Test', ['content:read', 'content:write']);
    $call = fn (string $tool, array $arguments) => $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json, text/event-stream'])
        ->postJson('/wake/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments]]);

    $call('redirect_scout_list', [])->assertOk()->assertSee('news\/first.html', false)->assertSee('posts\/first', false);
    $call('redirect_scout_set', ['path' => '/news/first.html', 'target' => 'posts/first'])->assertOk()->assertSee('now leads to');

    expect(app(Redirects::class)->target('en', 'news/first.html'))->toBe('posts/first');
});

it('runs from the command line', function () {
    fakeOldWebsite();

    $this->artisan('redirect-scout:scan', ['site' => 'https://old.example.org'])
        ->expectsOutputToContain('Old addresses')
        ->assertSuccessful();

    expect(app(Store::class)->address('news/first.html'))->not->toBeNull();
});

it('has every text in German', function () {
    $german = json_decode(file_get_contents(base_path('../plugins/redirect-scout/lang/de.json')), true);
    $core = json_decode(file_get_contents(base_path('lang/de.json')), true);
    $missing = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('../plugins/redirect-scout'), FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() === 'php') {
            preg_match_all('/(?:__|trans_choice)\(\s*\'((?:[^\'\\\\]|\\\\.)+)\'/', file_get_contents($file->getPathname()), $matches);
            $missing = [...$missing, ...array_filter(array_map('stripslashes', $matches[1]), fn ($key) => ! isset($german[$key]) && ! isset($core[$key]))];
        }
    }

    expect(array_values(array_unique($missing)))->toBe([]);
});
