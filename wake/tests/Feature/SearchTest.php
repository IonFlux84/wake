<?php

use Wake\Content\Index;
use Wake\Content\Search;

it('finds published entries by title, tags and text', function () {
    $search = app(Search::class);

    expect($search->query('first')->map->id()->all())->toContain('posts/first')
        ->and($search->query('travel')->first()->id())->toBe('posts/first')
        ->and($search->query('engl')->first()->id())->toBe('posts/second')
        ->and($search->query('visible')->all())->toBe([])
        ->and($search->query('hello first')->count())->toBe(1)
        ->and($search->query('')->all())->toBe([]);
});

it('searches per language', function () {
    expect(app(Search::class)->query('hallo', 'de')->first()->title())->toBe('Erster Beitrag')
        ->and(app(Search::class)->query('hallo', 'en')->all())->toBe([]);
});

it('notices changed content', function () {
    app(Search::class)->query('first');
    $this->writeContent('posts/second/content.md', "---\ntitle: Lighthouse\n---\n\nTall.");
    app(Index::class)->recheck();

    expect(app(Search::class)->query('lighthouse')->count())->toBe(1);
});

it('has a search page that is not cached or indexed', function () {
    $this->get('/search?q=first')->assertOk()->assertSee('First post')->assertHeader('X-Robots-Tag', 'noindex');
});
