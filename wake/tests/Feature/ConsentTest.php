<?php

use Wake\Consent\ConsentSettings;
use Wake\Consent\Services;
use Wake\Content\Index;
use Wake\Markdown\Markdown;

function consentConfig(string $html): array
{
    preg_match('#<script type="application/json" id="wake-consent-config">(.*?)</script>#s', $html, $m);

    return json_decode($m[1], true);
}

it('needs no banner when there is nothing to agree to', function () {
    $config = consentConfig($this->get('/first')->getContent());

    expect($config['banner'])->toBeFalse()
        ->and($config['categories'])->toBe(['media'])
        ->and($config['texts']['decline'])->toBe('Decline');
});

it('shows the banner for the statistics cookie, in the page language', function () {
    app(ConsentSettings::class)->save(['statistics' => true]);

    $config = consentConfig($this->get('/de/erster-beitrag')->getContent());

    expect($config['banner'])->toBeTrue()
        ->and($config['categories'])->toBe(['statistics', 'media'])
        ->and($config['texts']['accept'])->toBe('Alle akzeptieren');
});

it('shows the banner when level 1 waits for consent', function () {
    config(['wake.tracking.level1' => 'consent']);

    expect(app(ConsentSettings::class)->bannerNeeded())->toBeTrue();
});

it('asks everyone again when the texts change', function () {
    $settings = app(ConsentSettings::class);
    $settings->save(['position' => 'center']);
    expect($settings->get('version'))->toBe(1);

    $settings->save(['texts' => ['en' => ['title' => 'Cookies?']]]);

    expect($settings->get('version'))->toBe(2)
        ->and($settings->texts('en')['title'])->toBe('Cookies?')
        ->and($settings->texts('en')['accept'])->toBe('Accept all');
});

it('turns embeds into two-click placeholders', function () {
    $html = app(Markdown::class)->render("::: embed https://www.youtube.com/watch?v=abc123\nA video.\n:::");

    expect($html)->toContain('data-wake-embed')
        ->toContain('data-src="https://www.youtube-nocookie.com/embed/abc123"')
        ->toContain('<figcaption>')
        ->not->toContain('<iframe');
});

it('recognises services', function (string $url, string $service, string $embed) {
    expect(Services::detect($url))->toBe($service)->and(Services::embedUrl($url))->toBe($embed);
})->with([
    ['https://youtu.be/abc', 'youtube', 'https://www.youtube-nocookie.com/embed/abc'],
    ['https://vimeo.com/12345', 'vimeo', 'https://player.vimeo.com/video/12345?dnt=1'],
    ['https://open.spotify.com/track/xyz', 'spotify', 'https://open.spotify.com/embed/track/xyz'],
    ['https://www.google.com/maps/embed?pb=1', 'maps', 'https://www.google.com/maps/embed?pb=1'],
    ['https://example.org/widget', 'other', 'https://example.org/widget'],
]);

it('lists embedded services for the privacy policy', function () {
    $this->writeContent('posts/2026-02-01-second/index.en.md', "---\ntitle: Second\n---\n\n::: embed https://vimeo.com/1\n:::\n");

    expect(app(Index::class)->services())->toBe(['vimeo'])
        ->and(view('wake::services')->render())->toContain('Vimeo')->toContain('wake_consent')->toContain('Statistics');
});

it('offers a link to reopen the settings', function () {
    expect(view('wake::consent-link')->render())->toContain('data-wake-consent-open');
});
