<?php

use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Content\Models\Post;
use Wake\Content\Redirects;
use Wake\Http\Controllers\Panel\PreviewController;
use Wake\Livewire\EntryEditor;
use Wake\Livewire\RedirectManager;

beforeEach(function () {
    $this->owner = app(Accounts::class)->create(['name' => 'Dana', 'email' => 'dana@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);
});

it('previews drafts for the team, not for guests', function () {
    $this->get('/wake/preview?id=posts/2026-03-01-draft&language=en')->assertRedirect('/wake/login');

    $this->actingAs($this->owner)->get('/wake/preview?id=posts/2026-03-01-draft&language=en')
        ->assertOk()->assertSee('Post: A draft')->assertSee('Preview · Draft')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $this->get('/draft')->assertNotFound();
});

it('shares previews through signed links that expire', function () {
    $url = PreviewController::sharedUrl(Post::find('posts/2026-03-01-draft'));

    $this->get($url)->assertOk()->assertSee('A draft')->assertSee('link valid until');
    $this->get($url.'x')->assertForbidden();

    Carbon::setTestNow(now()->addDays(8));
    $this->get($url)->assertForbidden();
});

it('saves and opens the preview from the editor', function () {
    Livewire::withQueryParams(['id' => 'posts/2026-03-01-draft'])->actingAs($this->owner)
        ->test(EntryEditor::class, ['type' => 'post'])
        ->call('preview')->assertDispatched('wake-open')
        ->call('shareLink')->assertSet('sharedLink', fn ($link) => str_contains($link, 'signature='));
});

it('manages redirects in the panel', function () {
    Livewire::actingAs($this->owner)->test(RedirectManager::class)
        ->set('from', '/old-page')->set('to', 'posts/2026-01-10-first')->call('add')->assertHasNoErrors()
        ->set('from', '/elsewhere')->set('to', 'https://example.org')->call('add')->assertHasNoErrors()
        ->set('from', '/broken')->set('to', 'posts/missing')->call('add')->assertHasErrors('to');

    $this->get('/old-page')->assertRedirect(url('/first'));
    $this->get('/elsewhere')->assertRedirect('https://example.org');

    Livewire::actingAs($this->owner)->test(RedirectManager::class)->call('remove', 'en', 'old-page');
    expect(app(Redirects::class)->target('en', 'old-page'))->toBeNull();
});
