<?php

use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Wake\Accounts\Account;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Content\Models\Post;
use Wake\Livewire\Entries;
use Wake\Livewire\EntryEditor;

function member(Role $role, string $name = 'Dana'): Account
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

    expect(Entries::translationStates(Post::find('posts/2026-02-01-second')))->toBe(['en' => 'current', 'de' => 'missing']);
});

it('opens the editor pages', function () {
    $this->actingAs(member(Role::Owner))->get('/wake/content/post/edit?id=posts/2026-01-10-first')->assertOk()->assertSee('First post');
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
        ->set('id', 'posts/2026-03-01-draft')->call('switchLanguage', 'en')
        ->call('publish');

    expect(Post::find('posts/2026-03-01-draft')->isPublished())->toBeTrue();

    $component->call('unpublish');
    expect(Post::find('posts/2026-03-01-draft')->isDraft())->toBeTrue();

    $component->set('scheduleAt', '2026-07-01T09:00')->call('publish');
    expect(Post::find('posts/2026-03-01-draft')->isScheduled())->toBeTrue();
});

it('adds a translation from the editor', function () {
    Livewire::actingAs(member(Role::Owner))->test(EntryEditor::class, ['type' => 'post'])
        ->set('id', 'posts/2026-02-01-second')
        ->call('switchLanguage', 'de')
        ->assertSet('title', '')
        ->set('title', 'Zweiter')
        ->set('body', 'Deutsch.')
        ->call('save');

    expect(Post::find('posts/2026-02-01-second')->translations())->toEqualCanonicalizing(['en', 'de']);
});

it('keeps contributors to their own drafts', function () {
    $contributor = member(Role::Contributor, 'Cy');

    Livewire::actingAs($contributor)->test(EntryEditor::class, ['type' => 'post'])
        ->set('title', 'My idea')->call('save')
        ->call('publish')->assertForbidden();

    $this->actingAs($contributor)->get('/wake/content/post/edit?id=posts/2026-01-10-first')->assertForbidden();
});

it('lets authors publish their own posts only', function () {
    $author = member(Role::Author, 'Al');

    $component = Livewire::actingAs($author)->test(EntryEditor::class, ['type' => 'post'])
        ->set('title', 'By Al')->call('save')->call('publish')->assertHasNoErrors();

    expect(Post::find($component->get('id'))->isPublished())->toBeTrue();

    $this->actingAs($author)->get('/wake/content/post/edit?id=posts/2026-01-10-first')->assertForbidden();
});

it('uploads images into the entry folder', function () {
    $result = Livewire::actingAs(member(Role::Owner))->test(EntryEditor::class, ['type' => 'post'])
        ->set('id', 'posts/2026-02-01-second')
        ->set('upload', UploadedFile::fake()->image('Summer Party.png', 50, 50))
        ->call('storeUpload')
        ->get('upload');

    expect($result)->toBeNull()
        ->and(Post::find('posts/2026-02-01-second')->file('summer-party.png'))->not->toBeNull();
});

it('moves entries to the trash', function () {
    Livewire::actingAs(member(Role::Editor))->test(EntryEditor::class, ['type' => 'post'])
        ->set('id', 'posts/2026-02-01-second')
        ->call('trash')
        ->assertRedirect(route('wake.entries', ['type' => 'post']));

    expect(Post::find('posts/2026-02-01-second'))->toBeNull();
});

it('shows who else is editing', function () {
    $dana = member(Role::Owner);
    $eve = member(Role::Editor, 'Eve');

    Livewire::actingAs($eve)->test(EntryEditor::class, ['type' => 'post'])->set('id', 'posts/2026-01-10-first')->call('keepalive');

    Livewire::actingAs($dana)->test(EntryEditor::class, ['type' => 'post'])->set('id', 'posts/2026-01-10-first')
        ->assertSee('Eve is editing this entry right now');
});
