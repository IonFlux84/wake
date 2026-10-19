<?php

use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Content\Redirects;
use Wake\Livewire\RedirectManager;

it('leads old addresses to entries, paths of the site and other websites', function () {
    $redirects = app(Redirects::class);
    $redirects->add('en', 'old-post', 'posts/first');
    $redirects->add('en', 'old/archive', '/tag/travel');
    $redirects->add('en', 'elsewhere', 'https://example.org/page');

    $this->get('/old-post')->assertStatus(301)->assertRedirect(url('/first'));
    $this->get('/old/archive')->assertStatus(301)->assertRedirect(url('/tag/travel'));
    $this->get('/elsewhere')->assertStatus(301)->assertRedirect('https://example.org/page');
});

it('answers 410 for addresses that are gone on purpose', function () {
    app(Redirects::class)->add('en', 'old-offer', Redirects::GONE);

    $this->get('/old-offer')->assertStatus(410);
});

it('never redirects an address to itself', function () {
    app(Redirects::class)->add('en', 'loop', '/loop');

    $this->get('/loop')->assertNotFound();
});

it('tells what a target is', function () {
    expect(Redirects::kind('posts/first'))->toBe('entry')
        ->and(Redirects::kind('/blog'))->toBe('path')
        ->and(Redirects::kind('https://example.org'))->toBe('url')
        ->and(Redirects::kind('gone'))->toBe('gone');
});

it('accepts paths and "gone" as targets in the panel', function () {
    $owner = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);

    Livewire::actingAs($owner)->test(RedirectManager::class)
        ->set('from', '/old/archive')->set('to', '/tag/travel')->call('add')->assertHasNoErrors()
        ->set('from', '/old-offer')->set('to', 'gone')->call('add')->assertHasNoErrors()
        ->set('from', '/same')->set('to', '/same/')->call('add')->assertHasErrors('to')
        ->set('from', '/x')->set('to', 'posts/nothing')->call('add')->assertHasErrors('to')
        ->assertSee('Gone (410)');

    expect(app(Redirects::class)->target('en', 'old/archive'))->toBe('/tag/travel')
        ->and(app(Redirects::class)->target('en', 'old-offer'))->toBe('gone');
});

it('leads old file addresses to the media library', function () {
    app(Redirects::class)->add('en', 'uploads/photo.png', 'media/posts/first/photo.png');
    app(Redirects::class)->add('en', 'uploads/lost.png', 'media/posts/first/lost.png');

    $this->get('/uploads/photo.png')->assertStatus(302)->assertRedirectContains('/media/')->assertRedirectContains('photo.png');
    $this->get('/uploads/lost.png')->assertNotFound();
    expect(Redirects::kind('media/posts/first/photo.png'))->toBe('media');
});
