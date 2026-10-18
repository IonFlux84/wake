<?php

use Illuminate\Support\Facades\Event;
use Wake\Content\ContentChanged;
use Wake\Content\Index;

it('finds entries of every type', function () {
    $index = app(Index::class);

    expect(array_keys($index->ofType('post')))->toHaveCount(5)
        ->and($index->find('pages/about/team')['parent'])->toBe('pages/about')
        ->and($index->find('tags/travel')['type'])->toBe('tag')
        ->and($index->find('events/fair')['type'])->toBe('event');
});

it('reads folder names, order and menu', function () {
    $index = app(Index::class);

    expect($index->find('posts/first'))
        ->name->toBe('first')
        ->and($index->find('pages/about'))
        ->name->toBe('about')
        ->order->toBe(1)
        ->menu->toBeTrue()
        ->and($index->find('pages/contact'))
        ->menu->toBeFalse();
});

it('keeps one translation per language file', function () {
    $row = app(Index::class)->find('posts/first');

    expect(array_keys($row['languages']))->toEqualCanonicalizing(['en', 'de'])
        ->and($row['languages']['de']['fields']['slug'])->toBe('erster-beitrag');
});

it('keeps all files in one media library and knows where they are used', function () {
    $index = app(Index::class);

    expect($index->allMedia())->toHaveKey('posts/first/photo.png')
        ->and($index->mediaUsage('posts/first/photo.png'))->toBe(['posts/first']);
});

it('builds an address table per language', function () {
    $index = app(Index::class);

    expect($index->route('en', 'first'))->toBe('posts/first')
        ->and($index->route('de', 'erster-beitrag'))->toBe('posts/first')
        ->and($index->route('en', 'about/team'))->toBe('pages/about/team')
        ->and($index->route('de', 'ueber-uns'))->toBe('pages/about')
        ->and($index->route('en', ''))->toBe('pages/home')
        ->and($index->route('en', 'tag/travel'))->toBe('tags/travel')
        ->and($index->route('en', 'events/fair'))->toBe('events/fair');
});

it('reads site fields per language', function () {
    expect(app(Index::class)->site('de')['title'])->toBe('Testseite');
});

it('notices new, changed and deleted files', function () {
    $index = app(Index::class);
    $index->entries();

    $this->writeContent('posts/new/content.md', "---\ntitle: New\n---\n");
    $this->writeContent('posts/second/content.md', "---\ntitle: Renamed\n---\n");
    unlink($this->content('posts/draft/content.md'));

    expect($index->refresh())->toBeTrue()
        ->and($index->find('posts/new'))->not->toBeNull()
        ->and($index->find('posts/second')['languages']['en']['fields']['title'])->toBe('Renamed')
        ->and($index->find('posts/draft'))->toBeNull()
        ->and($index->refresh())->toBeFalse();
});

it('fires an event when content changed', function () {
    app(Index::class)->entries();
    Event::fake([ContentChanged::class]);

    $this->writeContent('posts/second/content.md', "---\ntitle: Changed\n---\n");
    app(Index::class)->refresh();

    Event::assertDispatched(ContentChanged::class);
});

it('re-reads invalidated files even with the same modification time', function () {
    $index = app(Index::class);
    $index->entries();
    $file = $this->content('posts/second/content.md');
    $mtime = filemtime($file);

    file_put_contents($file, "---\ntitle: Same second\n---\n");
    touch($file, $mtime);
    $index->invalidate([$file]);
    $index->refresh();

    expect($index->find('posts/second')['languages']['en']['fields']['title'])->toBe('Same second');
});

it('survives broken front matter and reports it', function () {
    $this->writeContent('posts/broken/content.md', "---\ntitle: [oops\n---\n");

    $index = app(Index::class);

    expect($index->find('posts/broken'))->toBeNull()
        ->and($index->warnings()[0])->toContain('posts/broken');
});

it('warns about two entries with the same address', function () {
    $this->writeContent('posts/copy/content.md', "---\ntitle: Copy\nslug: first\n---\n");

    expect(app(Index::class)->warnings())->toContain(
        'Two entries share the address /first (en): posts/copy and posts/first.',
    );
});

it('ignores files in unknown folders and languages', function () {
    $this->writeContent('drafts/x/content.md', "---\ntitle: X\n---\n");
    $this->writeContent('posts/second/content.fr.md', "---\ntitle: Français\n---\n");

    $index = app(Index::class);

    expect($index->find('drafts/x'))->toBeNull()
        ->and($index->find('posts/second')['languages'])->not->toHaveKey('fr');
});

it('treats index.md without language as the default language', function () {
    $this->writeContent('posts/plain/content.md', "---\ntitle: Plain\n---\n");

    expect(app(Index::class)->find('posts/plain')['languages'])->toHaveKey('en');
});
