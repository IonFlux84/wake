<?php

use Illuminate\Support\Facades\Event;
use Wake\Content\ContentChanged;
use Wake\Content\Index;

it('finds entries of every type', function () {
    $index = app(Index::class);

    expect(array_keys($index->ofType('post')))->toHaveCount(5)
        ->and($index->find('pages/1_about/1_team')['parent'])->toBe('pages/1_about')
        ->and($index->find('tags/travel')['type'])->toBe('tag')
        ->and($index->find('events/2026-05-01-fair')['type'])->toBe('event');
});

it('reads folder names, number prefixes and dates', function () {
    $index = app(Index::class);

    expect($index->find('posts/2026-01-10-first'))
        ->name->toBe('first')
        ->date->toBe('2026-01-10')
        ->and($index->find('pages/1_about'))
        ->name->toBe('about')
        ->num->toBe(1);
});

it('keeps one translation per language file', function () {
    $row = app(Index::class)->find('posts/2026-01-10-first');

    expect(array_keys($row['languages']))->toEqualCanonicalizing(['en', 'de'])
        ->and($row['languages']['de']['fields']['slug'])->toBe('erster-beitrag');
});

it('lists media next to the text files', function () {
    expect(app(Index::class)->find('posts/2026-01-10-first')['media'])->toHaveKey('photo.png');
});

it('builds an address table per language', function () {
    $index = app(Index::class);

    expect($index->route('en', 'first'))->toBe('posts/2026-01-10-first')
        ->and($index->route('de', 'erster-beitrag'))->toBe('posts/2026-01-10-first')
        ->and($index->route('en', 'about/team'))->toBe('pages/1_about/1_team')
        ->and($index->route('de', 'ueber-uns'))->toBe('pages/1_about')
        ->and($index->route('en', ''))->toBe('pages/home')
        ->and($index->route('en', 'tag/travel'))->toBe('tags/travel')
        ->and($index->route('en', 'events/fair'))->toBe('events/2026-05-01-fair');
});

it('reads site fields per language', function () {
    expect(app(Index::class)->site('de')['title'])->toBe('Testseite');
});

it('notices new, changed and deleted files', function () {
    $index = app(Index::class);
    $index->entries();

    $this->writeContent('posts/2026-04-01-new/index.en.md', "---\ntitle: New\n---\n");
    $this->writeContent('posts/2026-02-01-second/index.en.md', "---\ntitle: Renamed\n---\n");
    unlink($this->content('posts/2026-03-01-draft/index.en.md'));

    expect($index->refresh())->toBeTrue()
        ->and($index->find('posts/2026-04-01-new'))->not->toBeNull()
        ->and($index->find('posts/2026-02-01-second')['languages']['en']['fields']['title'])->toBe('Renamed')
        ->and($index->find('posts/2026-03-01-draft'))->toBeNull()
        ->and($index->refresh())->toBeFalse();
});

it('fires an event when content changed', function () {
    app(Index::class)->entries();
    Event::fake([ContentChanged::class]);

    $this->writeContent('posts/2026-02-01-second/index.en.md', "---\ntitle: Changed\n---\n");
    app(Index::class)->refresh();

    Event::assertDispatched(ContentChanged::class);
});

it('re-reads invalidated files even with the same modification time', function () {
    $index = app(Index::class);
    $index->entries();
    $file = $this->content('posts/2026-02-01-second/index.en.md');
    $mtime = filemtime($file);

    file_put_contents($file, "---\ntitle: Same second\n---\n");
    touch($file, $mtime);
    $index->invalidate([$file]);
    $index->refresh();

    expect($index->find('posts/2026-02-01-second')['languages']['en']['fields']['title'])->toBe('Same second');
});

it('survives broken front matter and reports it', function () {
    $this->writeContent('posts/2026-05-05-broken/index.en.md', "---\ntitle: [oops\n---\n");

    $index = app(Index::class);

    expect($index->find('posts/2026-05-05-broken'))->toBeNull()
        ->and($index->warnings()[0])->toContain('posts/2026-05-05-broken');
});

it('warns about two entries with the same address', function () {
    $this->writeContent('posts/2026-05-06-first/index.en.md', "---\ntitle: Copy\n---\n");

    expect(app(Index::class)->warnings())->toContain(
        'Two entries share the address /first (en): posts/2026-01-10-first and posts/2026-05-06-first.',
    );
});

it('ignores files in unknown folders and languages', function () {
    $this->writeContent('drafts/x/index.en.md', "---\ntitle: X\n---\n");
    $this->writeContent('posts/2026-02-01-second/index.fr.md', "---\ntitle: Français\n---\n");

    $index = app(Index::class);

    expect($index->find('drafts/x'))->toBeNull()
        ->and($index->find('posts/2026-02-01-second')['languages'])->not->toHaveKey('fr');
});

it('treats index.md without language as the default language', function () {
    $this->writeContent('posts/2026-05-07-plain/index.md', "---\ntitle: Plain\n---\n");

    expect(app(Index::class)->find('posts/2026-05-07-plain')['languages'])->toHaveKey('en');
});
