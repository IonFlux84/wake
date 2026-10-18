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
