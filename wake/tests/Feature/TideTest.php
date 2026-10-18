<?php

use Wake\Theme\Theme;

// Renders every template of the default theme with the fixture content.

beforeEach(function () {
    $this->copyDirectory(dirname(base_path()).'/site/themes/tide', $this->sandbox.'/site/themes/tide');
    config(['wake.theme' => 'tide', 'wake.theme_options.accent' => '#3D5AFE']);
    app(Theme::class)->register();
    app('view')->getFinder()->replaceNamespace('theme', $this->sandbox.'/site/themes/tide/templates');
});

it('renders every page type', function (string $path, string $text) {
    $this->get($path)->assertOk()->assertSee($text)->assertSee('--accent:#3D5AFE', false)->assertSee('getwake.dev');
})->with([
    'home' => ['/', 'Welcome'],
    'post' => ['/first', 'First post'],
    'translated post' => ['/de/erster-beitrag', 'Erster Beitrag'],
    'page' => ['/about', 'About'],
    'child page' => ['/about/team', 'Team'],
    'tag' => ['/tag/news', 'News'],
    'search' => ['/search?q=first', 'First post'],
]);

it('shows related posts and the 404 page', function () {
    $this->get('/second')->assertSee('More about News')->assertSee('First post');
    $this->get('/nothing-here')->assertNotFound()->assertSee('What are you looking for?');
});

it('speaks German on German pages', function () {
    $this->get('/de/erster-beitrag')->assertSee('Zum Inhalt springen')->assertSee('Minute Lesezeit');
});

it('renders events, the event list and the next events on the start page', function () {
    $this->writeContent('posts/2026-05-20-summer-fest/index.en.md', "---\ntitle: Summer fest\nslug: summer-fest\npublished: 2026-05-20 10:00\ncategory: termine\nstart: 2026-07-04 15:00\nlocation: Park\naddress: Main Street 1\nregistration_url: https://example.org/join\n---\n\nMusic.\n");

    $this->get('/events/summer-fest')->assertOk()->assertSee('Summer fest')->assertSee('Show on the map')->assertSee('Register')->assertSee('summer-fest.ics');
    $this->get('/events')->assertOk()->assertSee('July 2026');
    $this->get('/')->assertOk()->assertSee('Next Events');
});
