<?php

use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Livewire\Settings;
use Wake\Support\Files;
use Wake\Support\Paths;
use Wake\Support\Settings as SiteSettings;

beforeEach(function () {
    $this->owner = app(Accounts::class)->create(['name' => 'Dana', 'email' => 'dana@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);
});

it('saves site settings to settings/general.yml and the site files', function () {
    Livewire::actingAs($this->owner)->test(Settings::class)
        ->set('site.en.title', 'New Title')
        ->set('postUrl', '{year}/{slug}')
        ->set('retention', 60)
        ->set('approval', true)
        ->set('accent', '#ff2d87')
        ->call('saveSite')
        ->assertHasNoErrors();

    expect(SiteSettings::read())->toMatchArray(['post_url' => '{year}/{slug}', 'tracking' => ['enabled' => true, 'level1' => 'consent', 'respect_privacy_signals' => false, 'retention' => 60], 'mcp' => ['approval' => true], 'panel' => ['accent' => '#FF2D87', 'language' => 'en']])
        ->and(file_get_contents($this->content('website.md')))->toContain('title: New Title');

    $this->get('/2026/first')->assertOk();
});

it('validates site settings', function () {
    Livewire::actingAs($this->owner)->test(Settings::class)
        ->set('postUrl', '{year}')->set('accent', 'pink')->set('timezone', 'Mars/Base')
        ->call('saveSite')->assertHasErrors(['postUrl', 'accent', 'timezone']);
});

it('lets everyone change their own account, but only admins the site', function () {
    $author = app(Accounts::class)->create(['name' => 'Al', 'email' => 'al@example.com', 'password' => 'secret secret', 'role' => Role::Author]);

    Livewire::actingAs($author)->test(Settings::class)->assertDontSee('Save settings')
        ->set('name', 'Al Author')->set('language', 'de')
        ->set('currentPassword', 'wrong')->set('newPassword', 'a new password')
        ->call('saveAccount')->assertHasErrors('currentPassword');

    Livewire::actingAs($author)->test(Settings::class)
        ->set('name', 'Al Author')->set('currentPassword', 'secret secret')->set('newPassword', 'a new password')
        ->call('saveAccount')->assertHasNoErrors();

    expect(app(Accounts::class)->find('al')->name())->toBe('Al Author')
        ->and(Hash::check('a new password', app(Accounts::class)->find('al')->getAuthPassword()))->toBeTrue();

    Livewire::actingAs($author)->test(Settings::class)->call('saveSite')->assertForbidden();
});

it('only writes keys the panel may change', function () {
    SiteSettings::save(['paths.content' => '/etc', 'tracking.retention' => 9]);

    expect(SiteSettings::read())->toBe(['tracking' => ['retention' => 9]]);
});

it('uses German in the panel unless chosen otherwise', function () {
    config(['wake.panel.language' => 'de']);

    $this->actingAs($this->owner)->get('/wake')->assertSee('Beiträge');

    app(Accounts::class)->save($this->owner->with(['language' => 'en']));
    $this->actingAs(app(Accounts::class)->find('dana'))->get('/wake')->assertSee('Posts');
});

it('stores the setup language as the panel language of the site', function () {
    Files::deleteDirectory(Paths::data('accounts'));
    app()->forgetInstance(Accounts::class);
    app()->singleton(Accounts::class);
    auth()->logout();

    $this->post('/wake/setup', ['name' => 'Eve', 'email' => 'eve@example.com', 'password' => 'a long password', 'language' => 'de']);

    expect(SiteSettings::read()['panel']['language'])->toBe('de')
        ->and(app(Accounts::class)->find('eve')->chosenLanguage())->toBeNull();
});

it('warns when APP_URL is not the address the site is opened at', function () {
    $this->actingAs($this->owner)->get('/wake/settings')->assertOk()->assertDontSee('APP_URL');

    config(['app.url' => 'https://verein.example.org']);

    $this->actingAs($this->owner)->get('/wake/settings')->assertOk()->assertSee('APP_URL')->assertSee('verein.example.org');
});
