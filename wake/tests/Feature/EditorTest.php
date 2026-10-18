<?php

use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Wake\Accounts\Account;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Content\History;
use Wake\Content\Models\Post;
use Wake\Content\Revisions;
use Wake\Content\Writer;
use Wake\Livewire\Entries;
use Wake\Livewire\EntryEditor;

function member(string $role, string $name = 'Dana'): Account
{
    return app(Accounts::class)->create(['name' => $name, 'email' => strtolower($name).'@example.com', 'password' => 'secret secret', 'role' => $role]);
}

it('lists entries with status counts and translations', function () {
    Livewire::actingAs(member(Role::Owner))->test(Entries::class, ['type' => 'post'])
        ->assertSee('First post')
        ->assertSee('A draft')
        ->call('setStatus', 'draft')
        ->assertSee('A draft')
        ->assertDontSee('Second post')
        ->set('search', 'second')
        ->call('setStatus', 'all')
        ->assertSee('Second post')
        ->assertDontSee('First post');

    expect(Entries::translationStates(Post::find('posts/second')))->toBe(['en' => 'current', 'de' => 'missing']);
});

it('opens the editor pages', function () {
    $this->actingAs(member(Role::Owner))->get('/wake/content/post/edit?id=posts/first')->assertOk()->assertSee('First post');
    $this->get('/wake/content/post/new')->assertOk();
    $this->get('/wake/posts')->assertOk();
    $this->get('/wake/content/nonsense')->assertNotFound();
});

it('creates a post on the first save, with the author', function () {
    $owner = member(Role::Owner);

    $component = Livewire::actingAs($owner)->test(EntryEditor::class, ['type' => 'post'])
        ->set('title', 'Written in the panel')
        ->set('body', "Hello **world**.\n")
        ->set('tags', 'news, panel')
        ->call('save')
        ->assertHasNoErrors();

    $post = Post::find($component->get('id'));

    expect($post->title())->toBe('Written in the panel')
        ->and($post->isDraft())->toBeTrue()
        ->and($post->get('author'))->toBe($owner->id())
        ->and($post->get('tags'))->toBe(['news', 'panel'])
        ->and($post->body())->toBe("Hello **world**.\n");
});

it('publishes, schedules and unpublishes', function () {
    $component = Livewire::actingAs(member(Role::Editor))->test(EntryEditor::class, ['type' => 'post'])
        ->set('id', 'posts/draft')->call('switchLanguage', 'en')
        ->call('publish');

    expect(Post::find('posts/draft')->isPublished())->toBeTrue();

    $component->call('unpublish');
    expect(Post::find('posts/draft')->isDraft())->toBeTrue();

    $component->set('scheduleAt', '2026-07-01T09:00')->call('publish');
    expect(Post::find('posts/draft')->isScheduled())->toBeTrue();
});

it('adds a translation from the editor', function () {
    Livewire::actingAs(member(Role::Owner))->test(EntryEditor::class, ['type' => 'post'])
        ->set('id', 'posts/second')
        ->call('switchLanguage', 'de')
        ->assertSet('title', '')
        ->set('title', 'Zweiter')
        ->set('body', 'Deutsch.')
        ->call('save');

    expect(Post::find('posts/second')->translations())->toEqualCanonicalizing(['en', 'de']);
});

it('keeps contributors to their own drafts', function () {
    $contributor = member(Role::Contributor, 'Cy');

    Livewire::actingAs($contributor)->test(EntryEditor::class, ['type' => 'post'])
        ->set('title', 'My idea')->call('save')
        ->call('publish')->assertForbidden();

    $this->actingAs($contributor)->get('/wake/content/post/edit?id=posts/first')->assertForbidden();
});

it('lets authors publish their own posts only', function () {
    $author = member(Role::Author, 'Al');

    $component = Livewire::actingAs($author)->test(EntryEditor::class, ['type' => 'post'])
        ->set('title', 'By Al')->call('save')->call('publish')->assertHasNoErrors();

    expect(Post::find($component->get('id'))->isPublished())->toBeTrue();

    $this->actingAs($author)->get('/wake/content/post/edit?id=posts/first')->assertForbidden();
});

it('uploads images into the entry folder', function () {
    $result = Livewire::actingAs(member(Role::Owner))->test(EntryEditor::class, ['type' => 'post'])
        ->set('id', 'posts/second')
        ->set('upload', UploadedFile::fake()->image('Summer Party.png', 50, 50))
        ->call('storeUpload')
        ->get('upload');

    expect($result)->toBeNull()
        ->and(Post::find('posts/second')->file('summer-party.png'))->not->toBeNull();
});

it('moves entries to the trash', function () {
    Livewire::actingAs(member(Role::Editor))->test(EntryEditor::class, ['type' => 'post'])
        ->set('id', 'posts/second')
        ->call('trash')
        ->assertRedirect(route('wake.entries', ['type' => 'post']));

    expect(Post::find('posts/second'))->toBeNull();
});

it('shows who else is editing', function () {
    $dana = member(Role::Owner);
    $eve = member(Role::Editor, 'Eve');

    Livewire::actingAs($eve)->test(EntryEditor::class, ['type' => 'post'])->set('id', 'posts/first')->call('keepalive');

    Livewire::actingAs($dana)->test(EntryEditor::class, ['type' => 'post'])->set('id', 'posts/first')
        ->assertSee('Eve is editing this entry right now');
});

it('restores an earlier version from the history tab', function () {
    $owner = member(Role::Owner);
    app(Writer::class)->update('posts/second', 'en', ['title' => 'Changed', 'extra' => 'x'], 'Changed body.');
    $revision = app(Revisions::class)->of('posts/second/content.md')[0]['id'];

    Livewire::actingAs($owner)->test(EntryEditor::class, ['type' => 'post'])
        ->set('id', 'posts/second')->set('tab', 'history')
        ->assertSee('Earlier versions')
        ->call('restore', $revision)
        ->assertSet('title', 'Second post');

    $post = Post::find('posts/second');

    expect($post->body())->toBe("Only in English.\n")
        ->and($post->get('extra'))->toBeNull()
        ->and(app(History::class)->latest(1)[0]['action'])->toBe('restore');
});

it('shows all media in the library', function () {
    $this->actingAs(member(Role::Owner))->get('/wake/media')->assertOk()->assertSee('photo.png')->assertSee('posts/first/')->assertSee('used 1 time');
});

it('keeps images uploaded to a new post without a title', function () {
    $component = Livewire::actingAs(member(Role::Owner))->test(EntryEditor::class, ['type' => 'post'])
        ->set('upload', UploadedFile::fake()->image('harbour.jpg', 40, 30))
        ->call('storeUpload');

    $post = Post::find($component->get('id'));

    expect($post)->not->toBeNull()
        ->and($post->title())->toBe('Untitled')
        ->and($post->isDraft())->toBeTrue()
        ->and($post->file('harbour.jpg'))->not->toBeNull();
});

it('sets a header image and a preview image', function () {
    $component = Livewire::withQueryParams(['id' => 'posts/second'])->actingAs(member(Role::Owner))
        ->test(EntryEditor::class, ['type' => 'post'])
        ->set('coverUpload', UploadedFile::fake()->image('Header.jpg', 80, 45));

    $post = Post::find('posts/second');
    expect($post->get('cover'))->toBe('media/posts/second/header.jpg')
        ->and($post->thumbnail()->name())->toBe('header.jpg');

    $component->set('thumbnailUpload', UploadedFile::fake()->image('square.png', 40, 40));
    expect(Post::find('posts/second')->thumbnail()->name())->toBe('square.png');

    $component->call('setImage', 'thumbnail', '');
    expect(Post::find('posts/second')->get('thumbnail'))->toBeNull();

    $component->call('setImage', 'cover', 'missing.jpg');
    expect(Post::find('posts/second')->get('cover'))->toBe('media/posts/second/header.jpg');
});

it('rejects files that are not images as header image', function () {
    Livewire::actingAs(member(Role::Owner))->test(EntryEditor::class, ['type' => 'post'])
        ->set('id', 'posts/second')
        ->set('coverUpload', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
        ->assertHasErrors('coverUpload');
});
