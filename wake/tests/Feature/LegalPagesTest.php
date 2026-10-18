<?php

use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Consent\ConsentSettings;
use Wake\Content\LegalPages;
use Wake\Content\Site;
use Wake\Content\Writer;
use Wake\Livewire\Consent;
use Wake\Livewire\Dashboard;
use Wake\Livewire\Settings;
use Wake\Support\Settings as SiteSettings;

function consentPrivacy(string $html): ?string
{
    preg_match('#<script type="application/json" id="wake-consent-config">(.*?)</script>#s', $html, $m);

    return json_decode($m[1], true)['privacy'];
}

it('creates imprint and privacy policy as drafts during setup, in every language', function () {
    $this->post('/wake/setup', ['name' => 'Dana Owner', 'email' => 'dana@example.com', 'password' => 'a long password', 'language' => 'de'])->assertRedirect('/wake');

    $imprint = file_get_contents($this->content('pages/imprint/content.md'));
    $german = file_get_contents($this->content('pages/imprint/content.de.md'));

    expect($imprint)->toContain('title: Legal notice')->toContain('status: draft')->toContain('[First name last name')
        ->and($german)->toContain('slug: impressum')->toContain('§ 5 DDG')
        ->and(file_get_contents($this->content('pages/privacy/content.de.md')))->toContain('::: privacy-details')
        ->and(app(LegalPages::class)->todo())->toBe(['imprint' => 'draft', 'privacy' => 'draft'])
        ->and(app(LegalPages::class)->ensure())->toBe([]);
});

it('offers to create missing legal pages on the dashboard', function () {
    $owner = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);

    Livewire::actingAs($owner)->test(Dashboard::class)
        ->assertSee('Your site has no imprint or privacy policy yet.')
        ->call('createLegalPages')
        ->assertSee('Imprint and privacy policy are drafts');

    expect(is_file($this->content('pages/privacy/content.md')))->toBeTrue();
});

it('links published legal pages in the footer and the banner, never drafts', function () {
    app(LegalPages::class)->ensure();

    expect(app(Site::class)->privacy())->toBeNull()
        ->and(consentPrivacy($this->get('/first')->getContent()))->toBeNull();

    app(Writer::class)->update('pages/privacy', 'en', ['status' => 'published']);
    app(Writer::class)->update('pages/privacy', 'de', ['status' => 'published']);

    expect(app(Site::class)->privacy()->url())->toBe(url('/privacy'))
        ->and(app(Site::class)->legalPages()->map->title()->all())->toBe(['Privacy policy'])
        ->and(consentPrivacy($this->get('/first')->getContent()))->toBe(url('/privacy'))
        ->and(consentPrivacy($this->get('/de/erster-beitrag')->getContent()))->toBe(url('/de/datenschutz'));

    $this->get('/privacy')->assertOk()->assertSee('Necessary cookies')->assertSee('wake_consent');

    app(ConsentSettings::class)->save(['privacy_page' => 'pages/contact']);
    expect(consentPrivacy($this->get('/first')->getContent()))->toBe(url('/contact'));

    app(ConsentSettings::class)->save(['privacy_url' => 'https://example.org/datenschutz']);
    expect(consentPrivacy($this->get('/first')->getContent()))->toBe('https://example.org/datenschutz');
});

it('lets the owner choose the legal pages and the banner link in the panel', function () {
    $owner = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);

    Livewire::actingAs($owner)->test(Settings::class)
        ->set('imprint', 'pages/contact')->set('privacyPage', 'pages/nowhere')->call('saveSite')->assertHasErrors('privacyPage')
        ->set('privacyPage', 'pages/about')->call('saveSite')->assertHasNoErrors();

    expect(SiteSettings::read()['legal'])->toBe(['imprint' => 'pages/contact', 'privacy' => 'pages/about']);

    Livewire::actingAs($owner)->test(Consent::class)
        ->assertSee('Privacy policy of the site')
        ->set('privacy', 'custom')->set('privacyUrl', 'javascript:alert(1)')->call('save')->assertHasErrors('privacyUrl')
        ->set('privacyUrl', '/datenschutz')->call('save')->assertHasNoErrors();

    expect(app(ConsentSettings::class)->privacyLink())->toBe(url('/datenschutz'));
});

it('writes the privacy details as text, without Blade left over', function () {
    $html = view('wake::services')->render();

    expect($html)->not->toContain('@if')->not->toContain('@endif')->toContain('operating system.');
});
