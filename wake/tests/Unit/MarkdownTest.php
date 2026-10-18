<?php

use Wake\Markdown\Cards\Card;
use Wake\Markdown\Markdown;

it('renders CommonMark and GitHub tables', function () {
    $html = app(Markdown::class)->render("# Title\n\n| a | b |\n|---|---|\n| 1 | 2 |");

    expect($html)->toContain('<h1>Title</h1>')->toContain('<table>');
});

it('renders cards with Markdown inside', function () {
    $html = app(Markdown::class)->render("::: note\nSome **bold** text.\n:::\n\nAfter.");

    expect($html)->toContain('<div class="card card-note">')
        ->toContain('<strong>bold</strong>')
        ->toContain('<p>After.</p>');
});

it('renders built-in card views', function () {
    $html = app(Markdown::class)->render("::: callout 💡\nWatch out.\n:::");

    expect($html)->toContain('class="card card-callout"')->toContain('💡')->toContain('<p>Watch out.</p>');
});

it('passes attributes to cards', function () {
    $html = app(Markdown::class)->render("::: button href=\"/join\" Join us\n:::");

    expect($html)->toContain('href="/join"')->toContain('Join us');
});

it('parses card arguments', function () {
    expect(Card::parseArguments(' href="/x" size=large Hello world '))
        ->toBe([['href' => '/x', 'size' => 'large'], 'Hello world']);
});

it('leaves a lonely ::: line alone', function () {
    expect(app(Markdown::class)->render("Text\n\n:::"))->toContain('<p>:::</p>');
});

it('blocks javascript links', function () {
    expect(app(Markdown::class)->render('[x](javascript:alert(1))'))->not->toContain('javascript:');
});

it('closes empty cards and cards written on one line, so nothing after them is swallowed', function (string $markdown) {
    $html = app(Markdown::class)->render($markdown);

    expect($html)->toContain('card-note')->toContain('<h2>After</h2>')
        ->and(substr_count($html, 'card-note'))->toBe(1)
        ->and(strpos($html, '<h2>After</h2>'))->toBeGreaterThan(strpos($html, '</div>'));
})->with([
    'empty' => ["::: note\n:::\n\n## After"],
    'one line' => ["::: note :::\n\n## After"],
    'one line with settings' => ["::: note tone=\"dark\" :::\n\n## After"],
]);

it('reads settings of a card written on one line', function () {
    $html = app(Markdown::class)->render('::: button href="/join" Join us :::');

    expect($html)->toContain('href="/join"')->toContain('Join us')->not->toContain(':::');
});
