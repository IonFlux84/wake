<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Content\Models\Page;
use Wake\Content\Models\Post;
use Wake\Content\Models\Tag;
use Wake\Import\GhostImporter;
use Wake\Livewire\Settings;

function importGhost(?string $url = null): GhostImporter
{
    return app(GhostImporter::class)->import(file_get_contents(__DIR__.'/../Fixtures/imports/ghost.json'), $url);
}

it('imports posts, pages and tags', function () {
    $importer = importGhost();

    expect($importer->counts)->toMatchArray(['posts' => 2, 'pages' => 1, 'tags' => 1]);

    $post = Post::find('posts/harbour-at-night');

    expect($post->title())->toBe('Harbour at night')
        ->and($post->isPublished())->toBeTrue()
        ->and($post->get('published'))->toBe('2025-11-02 19:30')
        ->and($post->get('tags'))->toBe(['harbour'])
        ->and($post->get('excerpt'))->toBe('Lights on the water.')
        ->and($post->get('seo.title'))->toBe('The harbour')
        ->and($post->get('imported_author'))->toBe('Hanna Harbour')
        ->and(Tag::findOrVirtual('harbour', 'en')->title())->toBe('Harbour Life')
        ->and(Page::query()->where('slug', 'about')->first()->title())->toBe('About us')
        ->and(Post::query()->where('slug', 'unfinished')->first()->isDraft())->toBeTrue();
});

it('turns Ghost cards into Wake cards', function () {
    importGhost();
    $body = Post::find('posts/harbour-at-night')->body();

    expect($body)->toContain('The **harbour** at night. [Link](https://example.org)')
        ->toContain("::: callout ⚓\nBring a *jacket*.\n:::")
        ->toContain("::: embed https://www.youtube.com/embed/abc123\n")
        ->toContain("::: divider\n:::")
        ->toContain('## Getting there')
        ->toContain('- Bus 12')
        ->toContain('*The old boat*');
});

it('downloads images from the old site', function () {
    Http::fake(['old.example.org/*' => Http::response(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='), 200, ['Content-Type' => 'image/png'])]);

    $importer = importGhost('https://old.example.org');
    $post = Post::find('posts/harbour-at-night');

    expect($importer->counts['images'])->toBe(2)
        ->and($post->get('cover'))->toBe('media/posts/harbour-at-night/harbour.jpg')
        ->and($post->body())->toContain('![A boat](media/posts/harbour-at-night/boat.jpg)');
});

it('does not import the same post twice', function () {
    importGhost();
    $second = importGhost();

    expect($second->counts['posts'])->toBe(0)->and($second->counts['skipped'])->toBe(3);
});

it('refuses files that are not Ghost exports', function () {
    app(GhostImporter::class)->import('{"hello":"world"}');
})->throws(InvalidArgumentException::class, 'not a Ghost export');

it('has an Artisan command', function () {
    $this->artisan('wake:import:ghost', ['file' => __DIR__.'/../Fixtures/imports/ghost.json'])
        ->expectsOutputToContain('Imported 2 posts, 1 pages')
        ->assertSuccessful();
});

it('imports from the settings page', function () {
    $owner = app(Accounts::class)->create(['name' => 'Dana', 'email' => 'dana@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);

    Livewire::actingAs($owner)->test(Settings::class)
        ->set('ghostFile', UploadedFile::fake()->createWithContent('ghost.json', file_get_contents(__DIR__.'/../Fixtures/imports/ghost.json')))
        ->call('importGhost')
        ->assertHasNoErrors()
        ->assertSet('importResult.counts.posts', 2);
});
