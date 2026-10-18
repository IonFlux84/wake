<?php

use Illuminate\Support\Carbon;
use Wake\Content\Models\Post;
use Wake\Content\Revisions;
use Wake\Content\Writer;

it('creates a post folder as a draft', function () {
    $post = app(Writer::class)->create('post', ['title' => 'Hello, Wake!', 'tags' => 'news, Travel'], 'Body text.');

    expect($post->id())->toBe('posts/hello-wake')
        ->and($post->status())->toBe('draft')
        ->and($post->get('tags'))->toBe(['news', 'Travel'])
        ->and(file_get_contents($this->content('posts/hello-wake/content.md')))
        ->toStartWith("---\ntitle: Hello, Wake!\nstatus: draft\ntags: [news, Travel]");
});

it('creates child pages, tags and custom entries', function () {
    $writer = app(Writer::class);

    expect($writer->create('page', ['title' => 'History'], '', 'en', 'pages/about')->id())->toBe('pages/about/history')
        ->and($writer->create('tag', ['title' => 'Ruhr'])->id())->toBe('tags/ruhr')
        ->and($writer->create('event', ['title' => 'Summer fest', 'location' => 'Park'])->get('location'))->toBe('Park');
});

it('never overwrites an existing folder', function () {
    $writer = app(Writer::class);

    expect($writer->create('post', ['title' => 'Same'])->id())->toBe('posts/same')
        ->and($writer->create('post', ['title' => 'Same'])->id())->toBe('posts/same-2');
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
    $writer->update('posts/second', 'en', ['title' => 'Second, revised', 'tags' => null], 'New body.');

    $entry = Post::find('posts/second');

    expect($entry->title())->toBe('Second, revised')
        ->and($entry->get('tags'))->toBeNull()
        ->and($entry->body())->toBe("New body.\n")
        ->and(app(Revisions::class)->of('posts/second/content.md'))->toHaveCount(1);
});

it('adds a translation', function () {
    $entry = app(Writer::class)->update('posts/second', 'de', ['title' => 'Zweiter Beitrag', 'slug' => 'zweiter', 'status' => 'published'], 'Auf Deutsch.');

    expect($entry->language())->toBe('de')
        ->and($entry->translations())->toEqualCanonicalizing(['en', 'de'])
        ->and(is_file($this->content('posts/second/content.de.md')))->toBeTrue();

    $this->get('/de/zweiter')->assertOk()->assertSee('Zweiter Beitrag');
});

it('publishes now or later and unpublishes', function () {
    $writer = app(Writer::class);
    $draft = 'posts/draft';

    expect($writer->publish($draft, 'en')->isPublished())->toBeTrue()
        ->and($writer->unpublish($draft, 'en')->isDraft())->toBeTrue()
        ->and($writer->publish($draft, 'en', Carbon::now()->addDay()->toImmutable())->isScheduled())->toBeTrue();
});

it('redirects the old address after a slug change', function () {
    app(Writer::class)->update('posts/first', 'en', ['slug' => 'the-first']);

    $this->get('/first')->assertRedirect(url('/the-first'))->assertStatus(301);
    $this->get('/the-first')->assertOk();
});

it('moves deleted entries to the trash', function () {
    $target = app(Writer::class)->trash('posts/first');

    expect(is_dir($this->content('posts/first')))->toBeFalse()
        ->and(is_file($target.'/first/content.md'))->toBeTrue()
        ->and(Post::find('posts/first'))->toBeNull();
});

it('stores media in the folder of its entry of the media library with a clean name', function () {
    $source = tempnam(sys_get_temp_dir(), 'img');
    file_put_contents($source, 'x');

    $writer = app(Writer::class);
    $reference = $writer->addMedia('posts/second', 'My Photo.JPG', $source);

    expect($reference)->toBe('media/posts/second/my-photo.jpg')
        ->and(is_file($this->content('media/posts/second/my-photo.jpg')))->toBeTrue()
        ->and($writer->addMedia('posts/second', 'My Photo.JPG', $source))->toBe('media/posts/second/my-photo-2.jpg')
        ->and(Post::find('posts/second')->file('my-photo.jpg'))->not->toBeNull()
        ->and(Post::find('posts/second')->file($reference))->not->toBeNull();

    $writer->deleteMedia($reference);

    expect(Post::find('posts/second')->file('my-photo.jpg'))->toBeNull();
});
