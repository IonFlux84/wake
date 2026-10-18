<?php

use Illuminate\Support\Carbon;
use Wake\Content\Models\Post;
use Wake\Content\Revisions;
use Wake\Content\Writer;

it('creates a dated post folder as a draft', function () {
    $post = app(Writer::class)->create('post', ['title' => 'Hello, Wake!', 'tags' => 'news, Travel'], 'Body text.');

    expect($post->id())->toBe('posts/2026-06-01-hello-wake')
        ->and($post->status())->toBe('draft')
        ->and($post->get('tags'))->toBe(['news', 'Travel'])
        ->and(file_get_contents($this->content('posts/2026-06-01-hello-wake/index.en.md')))
        ->toStartWith("---\ntitle: Hello, Wake!\nstatus: draft\ntags: [news, Travel]");
});

it('creates child pages, tags and custom entries', function () {
    $writer = app(Writer::class);

    expect($writer->create('page', ['title' => 'History'], '', 'en', 'pages/1_about')->id())->toBe('pages/1_about/history')
        ->and($writer->create('tag', ['title' => 'Ruhr'])->id())->toBe('tags/ruhr')
        ->and($writer->create('event', ['title' => 'Summer fest', 'location' => 'Park'])->get('location'))->toBe('Park');
});

it('never overwrites an existing folder', function () {
    $writer = app(Writer::class);

    expect($writer->create('post', ['title' => 'Same'])->id())->toBe('posts/2026-06-01-same')
        ->and($writer->create('post', ['title' => 'Same'])->id())->toBe('posts/2026-06-01-same-2');
});

it('refuses entries without title, unknown types and languages', function (string $type, array $fields, string $language) {
    app(Writer::class)->create($type, $fields, '', $language);
})->with([
    ['post', [], 'en'],
    ['nonsense', ['title' => 'X'], 'en'],
    ['post', ['title' => 'X'], 'fr'],
])->throws(InvalidArgumentException::class);

it('updates fields and body and keeps a revision', function () {
    $writer = app(Writer::class);
    $writer->update('posts/2026-02-01-second', 'en', ['title' => 'Second, revised', 'tags' => null], 'New body.');

    $entry = Post::find('posts/2026-02-01-second');

    expect($entry->title())->toBe('Second, revised')
        ->and($entry->get('tags'))->toBeNull()
        ->and($entry->body())->toBe("New body.\n")
        ->and(app(Revisions::class)->of('posts/2026-02-01-second/index.en.md'))->toHaveCount(1);
});

it('adds a translation', function () {
    $entry = app(Writer::class)->update('posts/2026-02-01-second', 'de', ['title' => 'Zweiter Beitrag', 'slug' => 'zweiter', 'status' => 'published'], 'Auf Deutsch.');

    expect($entry->language())->toBe('de')
        ->and($entry->translations())->toEqualCanonicalizing(['en', 'de'])
        ->and(is_file($this->content('posts/2026-02-01-second/index.de.md')))->toBeTrue();

    $this->get('/de/zweiter')->assertOk()->assertSee('Zweiter Beitrag');
});

it('publishes now or later and unpublishes', function () {
    $writer = app(Writer::class);
    $draft = 'posts/2026-03-01-draft';

    expect($writer->publish($draft, 'en')->isPublished())->toBeTrue()
        ->and($writer->unpublish($draft, 'en')->isDraft())->toBeTrue()
        ->and($writer->publish($draft, 'en', Carbon::now()->addDay()->toImmutable())->isScheduled())->toBeTrue();
});

it('redirects the old address after a slug change', function () {
    app(Writer::class)->update('posts/2026-01-10-first', 'en', ['slug' => 'the-first']);

    $this->get('/first')->assertRedirect(url('/the-first'))->assertStatus(301);
    $this->get('/the-first')->assertOk();
});

it('moves deleted entries to the trash', function () {
    $target = app(Writer::class)->trash('posts/2026-01-10-first');

    expect(is_dir($this->content('posts/2026-01-10-first')))->toBeFalse()
        ->and(is_file($target.'/2026-01-10-first/index.en.md'))->toBeTrue()
        ->and(Post::find('posts/2026-01-10-first'))->toBeNull();
});

it('stores media next to an entry with a clean name', function () {
    $source = tempnam(sys_get_temp_dir(), 'img');
    file_put_contents($source, 'x');

    $writer = app(Writer::class);
    $name = $writer->addMedia('posts/2026-02-01-second', 'My Photo.JPG', $source);

    expect($name)->toBe('my-photo.jpg')
        ->and($writer->addMedia('posts/2026-02-01-second', 'My Photo.JPG', $source))->toBe('my-photo-2.jpg')
        ->and(Post::find('posts/2026-02-01-second')->file('my-photo.jpg'))->not->toBeNull();

    $writer->deleteMedia('posts/2026-02-01-second', 'my-photo.jpg');

    expect(Post::find('posts/2026-02-01-second')->file('my-photo.jpg'))->toBeNull();
});
