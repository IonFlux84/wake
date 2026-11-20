<?php

use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Content\Redirects;
use Wake\Livewire\Settings;
use Wake\Support\Settings as SiteSettings;

it('is off by default', function () {
    expect(config('wake.short_links.source'))->toBeNull();

    $this->get('/a')->assertNotFound();
    $this->get('/7')->assertNotFound();
});

it('sends one letter or digit to the home page with utm parameters', function () {
    config(['wake.short_links.source' => 'heycatch']);

    $this->get('/a')->assertStatus(302)->assertRedirect(url('/').'/?utm_source=heycatch&utm_campaign=a');
    $this->get('/z')->assertStatus(302)->assertRedirect(url('/').'/?utm_source=heycatch&utm_campaign=z');
    $this->get('/0')->assertStatus(302)->assertRedirect(url('/').'/?utm_source=heycatch&utm_campaign=0');
    $this->get('/9')->assertStatus(302)->assertRedirect(url('/').'/?utm_source=heycatch&utm_campaign=9');
});

it('keeps other query parameters, but its own utm values win', function () {
    config(['wake.short_links.source' => 'heycatch']);

    $this->get('/b?ref=story&utm_source=other')
        ->assertRedirect(url('/').'/?ref=story&utm_source=heycatch&utm_campaign=b');
});

it('leaves uppercase, longer and prefixed paths alone', function () {
    config(['wake.short_links.source' => 'heycatch']);

    $this->get('/A')->assertNotFound();
    $this->get('/ab')->assertNotFound();
    $this->get('/a/b')->assertNotFound();
    $this->get('/de/a')->assertNotFound();
    $this->get('/-')->assertNotFound();
});

it('never shadows a published entry or an old address', function () {
    config(['wake.short_links.source' => 'heycatch']);

    $this->writeContent('pages/x/content.md', "---\ntitle: Page X\n---\n\nReal content.\n");
    $this->get('/x')->assertOk()->assertSee('Real content.');

    // A draft is not real content for visitors: the short link applies.
    $this->writeContent('pages/y/content.md', "---\ntitle: Page Y\nstatus: draft\n---\n");
    $this->get('/y')->assertStatus(302);

    app(Redirects::class)->add('en', 'q', '/about');
    $this->get('/q')->assertStatus(301)->assertRedirect(url('/about'));
});

it('works with the default language in the URL', function () {
    config(['wake.short_links.source' => 'heycatch', 'wake.prefix_default_language' => true]);

    $this->get('/c')->assertStatus(302)->assertRedirect(url('/en').'/?utm_source=heycatch&utm_campaign=c');
});

it('is set in the panel', function () {
    $owner = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);

    Livewire::actingAs($owner)->test(Settings::class)
        ->set('shortLinkSource', 'hey catch')->call('saveSite')->assertHasErrors('shortLinkSource')
        ->set('shortLinkSource', 'heycatch')->call('saveSite')->assertHasNoErrors();

    expect(SiteSettings::read()['short_links']['source'])->toBe('heycatch');

    Livewire::actingAs($owner)->test(Settings::class)
        ->assertSet('shortLinkSource', 'heycatch')
        ->set('shortLinkSource', '')->call('saveSite');

    expect(config('wake.short_links.source'))->toBeNull();
});
