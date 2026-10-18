<?php

use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Livewire\EntryEditor;

it('renders the home page from pages/home', function () {
    $this->get('/')->assertOk()->assertSee('Home: Welcome')->assertSee('Second post');
});

it('renders posts, pages, child pages, tags and custom types', function () {
    $this->get('/first')->assertOk()->assertSee('Post: First post');
    $this->get('/about')->assertOk()->assertSee('Page: About');
    $this->get('/about/team')->assertOk()->assertSee('Page: Team');
    $this->get('/tag/travel')->assertOk()->assertSee('Tag: Travelling')->assertSee('First post');
    $this->get('/events/fair')->assertOk()->assertSee('Entry: Spring fair (event)');
});

it('renders tags that have no file', function () {
    $this->get('/tag/news')->assertOk()->assertSee('Tag: News')->assertSee('Second post');
    $this->get('/de/tag/news')->assertOk()->assertSee('Erster Beitrag');
    $this->get('/tag/unused')->assertNotFound();
});

it('serves other languages under their prefix and slug', function () {
    $this->get('/de')->assertOk()->assertSee('Testseite');
    $this->get('/de/erster-beitrag')->assertOk()->assertSee('Post: Erster Beitrag');
    $this->get('/de/ueber-uns')->assertOk()->assertSee('Page: Über uns');
});

it('hides drafts and future posts', function () {
    $this->get('/draft')->assertNotFound()->assertSee('Not found');
    $this->get('/future')->assertNotFound();
    $this->get('/due')->assertOk();
});

it('answers 404 for missing translations by default', function () {
    $this->get('/de/second')->assertNotFound();
});

it('can fall back to the default language', function () {
    config(['wake.missing_translation' => 'fallback']);

    $this->get('/de/second')->assertOk()->assertSee('Post: Second post')->assertSee('fallback')
        ->assertSee('<meta name="robots" content="noindex">', false);

    // A translation with its own slug is not reachable under the default slug.
    $this->get('/de/first')->assertNotFound();
});

it('can put the default language in the URL', function () {
    config(['wake.prefix_default_language' => true]);

    $this->get('/first')->assertRedirect(url('/en/first'));
    $this->get('/en/first')->assertOk();
});

it('adds hreflang, canonical and Open Graph tags', function () {
    $this->get('/first')
        ->assertSee('<link rel="canonical" href="'.url('/first').'">', false)
        ->assertSee('<link rel="alternate" hreflang="de" href="'.url('/de/erster-beitrag').'">', false)
        ->assertSee('<meta property="og:type" content="article">', false)
        ->assertSee('"@context":"https://schema.org"', false);
});

it('serves an RSS feed per language', function () {
    $this->get('/feed.xml')->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
        ->assertSee('<title>First post</title>', false)
        ->assertDontSee('A draft');

    $this->get('/de/feed.xml')->assertOk()->assertSee('Erster Beitrag')->assertDontSee('Second post');
});

it('serves a sitemap with alternates', function () {
    $this->get('/sitemap.xml')->assertOk()
        ->assertSee('<loc>'.url('/first').'</loc>', false)
        ->assertSee('hreflang="de" href="'.url('/de/erster-beitrag').'"', false)
        ->assertDontSee('/draft');
});

it('serves robots.txt', function () {
    $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: '.url('sitemap.xml'));
});

it('shows changes made to files without any further step', function () {
    $this->get('/first')->assertSee('First post');

    $this->writeContent('posts/first/content.md', "---\ntitle: Edited by FTP\npublished: 2026-01-10 09:00\n---\n");

    $this->get('/first')->assertSee('Edited by FTP');
});

it('loads the design of a page only on that page, cleaned and scoped', function () {
    $owner = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);

    Livewire::withQueryParams(['id' => 'pages/about'])->actingAs($owner)
        ->test(EntryEditor::class, ['type' => 'page'])
        ->set('tab', 'design')
        ->assertSee('style.css')
        ->set('style', "@import url(https://evil.example/x.css);\nh2 { color: red; background: url(https://evil.example/p.gif) }\n.hero { background: url(media/posts/first/photo.png) }\n} body { display: none }\n</style><script>alert(1)</script>")
        ->call('saveStyle')
        ->assertHasNoErrors();

    $css = file_get_contents($this->content('pages/about/style.css'));

    expect($css)->not->toContain('@import')->not->toContain('evil.example')->not->toContain('</style')
        ->and(substr_count($css, '{'))->toBe(substr_count($css, '}'));

    $this->get('/about')->assertOk()
        ->assertSee('@scope (main)', false)
        ->assertSee('color: red', false)
        ->assertSee('/media/', false)
        ->assertDontSee('<script>alert(1)', false);

    $this->get('/contact')->assertOk()->assertDontSee('@scope (main)', false);
});
