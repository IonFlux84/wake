<?php

use Wake\Content\ContentTypes;
use Wake\Content\Models\Page;
use Wake\Content\Models\Post;
use Wake\Content\Models\Tag;
use Wake\Content\Query;
use Wake\Content\Site;
use Wake\Localization\Languages;

it('returns published posts, newest first', function () {
    expect(Post::published()->get()->map->title()->all())
        ->toBe(['Second post', 'Due post', 'First post']);
});

it('treats scheduled posts with a past date as published', function () {
    expect(Post::find('posts/2026-01-15-due')->isPublished())->toBeTrue()
        ->and(Post::find('posts/2099-01-01-future')->isPublished())->toBeFalse()
        ->and(Post::find('posts/2099-01-01-future')->isScheduled())->toBeTrue();
});

it('finds drafts and scheduled posts', function () {
    expect(Post::drafts()->get()->map->title()->all())->toBe(['A draft'])
        ->and(Post::query()->scheduled()->get()->map->title()->all())->toBe(['From the future']);
});

it('filters by tag, case-insensitively', function () {
    expect(Post::published()->tag('news')->count())->toBe(2)
        ->and(Post::published()->tag('Travel')->first()->title())->toBe('First post');
});

it('filters by fields', function () {
    expect((new Query('event'))->where('location', 'Town hall')->count())->toBe(1)
        ->and(Post::query()->where('status', '!=', 'draft')->count())->toBe(4);
});

it('orders, limits and offsets', function () {
    expect(Post::published()->oldest()->limit(2)->get()->map->title()->all())->toBe(['First post', 'Due post'])
        ->and(Post::published()->latest()->offset(1)->limit(1)->first()->title())->toBe('Due post')
        ->and(Post::published()->orderBy('title')->first()->title())->toBe('Due post');
});

it('paginates', function () {
    $page = Post::published()->paginate(2, 2);

    expect($page->total())->toBe(3)->and($page->items())->toHaveCount(1);
});

it('can be looped over directly', function () {
    $titles = [];

    foreach (Post::published()->limit(1) as $post) {
        $titles[] = $post->title();
    }

    expect($titles)->toBe(['Second post']);
});

it('queries in the current language and hides missing translations', function () {
    app(Languages::class)->setCurrent('de');

    expect(Post::published()->get()->map->title()->all())->toBe(['Erster Beitrag']);
});

it('falls back to the default language when configured', function () {
    config(['wake.missing_translation' => 'fallback']);
    app(Languages::class)->setCurrent('de');

    $posts = Post::published()->get();

    expect($posts)->toHaveCount(3)
        ->and($posts->first()->isFallback())->toBeTrue()
        ->and($posts->first()->language())->toBe('en');
});

it('exposes fields as properties', function () {
    $event = (new Query('event'))->first();

    expect($event->location)->toBe('Town hall')
        ->and($event->title)->toBe('Spring fair')
        ->and($event->get('missing', 'default'))->toBe('default');
});

it('builds URLs with language prefixes', function () {
    $post = Post::find('posts/2026-01-10-first');

    expect($post->url())->toBe(url('/first'))
        ->and($post->translation('de')->url())->toBe(url('/de/erster-beitrag'));
});

it('supports dated post URLs', function () {
    config(['wake.post_url' => '{year}/{month}/{slug}']);
    app(ContentTypes::class)->flush();

    expect(Post::find('posts/2026-01-10-first')->path())->toBe('2026/01/first');
});

it('renders Markdown with media URLs and cards', function () {
    $html = (string) Post::find('posts/2026-01-10-first')->content();

    expect($html)->toContain('<strong>first</strong>')
        ->toContain('/media/')
        ->toContain('card-callout');
});

it('creates excerpts from the text', function () {
    expect(Post::find('posts/2026-02-01-second')->excerpt())->toBe('Only in English.');
});

it('knows tags, including tags without a file', function () {
    $tags = Post::find('posts/2026-01-10-first')->tags();

    expect($tags->map->title()->all())->toBe(['News', 'Travelling'])
        ->and($tags->first()->isVirtual())->toBeTrue()
        ->and(Tag::findOrVirtual('news', 'en')->posts()->count())->toBe(2);
});

it('builds the page tree', function () {
    $about = Page::find('pages/1_about');

    expect($about->children()->first()->title())->toBe('Team')
        ->and($about->children()->first()->parent()->id())->toBe('pages/1_about')
        ->and(app(Site::class)->menu()->map->title()->all())->toBe(['About', 'Blog']);
});

it('lists the tags of the site', function () {
    expect(app(Site::class)->tags()->map->slug()->all())->toBe(['news', 'travel']);
});
