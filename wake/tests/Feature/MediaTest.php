<?php

use Wake\Content\Models\Post;

it('serves the original and stores a public copy', function () {
    $url = Post::find('posts/2026-01-10-first')->file('photo.png')->url();

    $this->get(parse_url($url, PHP_URL_PATH))->assertOk();

    expect(is_file(public_path(ltrim(parse_url($url, PHP_URL_PATH), '/'))))->toBeTrue();
});

it('creates resized versions and WebP', function () {
    $photo = Post::find('posts/2026-01-10-first')->file('photo.png');

    $this->get(parse_url($photo->url(320), PHP_URL_PATH))->assertOk();
    $this->get(parse_url($photo->url(320, 'webp'), PHP_URL_PATH))->assertOk()->assertHeader('Content-Type', 'image/webp');

    $path = public_path(ltrim(parse_url($photo->url(320), PHP_URL_PATH), '/'));

    expect(getimagesize($path)[0])->toBe(320)
        ->and($photo->url(300))->toBe($photo->url(320));
});

it('refuses unknown files, sizes and stale versions', function () {
    $photo = Post::find('posts/2026-01-10-first')->file('photo.png');
    $path = parse_url($photo->url(), PHP_URL_PATH);

    $this->get(str_replace('photo.png', 'other.png', $path))->assertNotFound();
    $this->get(str_replace('/o/', '/333/', $path))->assertNotFound();
    $this->get(preg_replace('#/[a-z0-9]+/o/#', '/zzz/o/', $path))->assertNotFound();
});

it('builds srcset and picture markup', function () {
    $photo = Post::find('posts/2026-01-10-first')->image();

    expect($photo->width())->toBe(800)
        ->and($photo->srcset())->toContain('320w')->toContain('640w')->not->toContain('960w')
        ->and((string) $photo->picture('Alt'))->toContain('<source type="image/webp"')->toContain('alt="Alt"');
});
