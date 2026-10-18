<?php

use Wake\Content\FrontMatter;
use Wake\Content\InvalidContentException;

it('splits front matter and body', function () {
    [$fields, $body] = FrontMatter::parse("---\ntitle: Hello\ntags: [a, b]\n---\n\nThe body.\n");

    expect($fields)->toBe(['title' => 'Hello', 'tags' => ['a', 'b']])
        ->and($body)->toBe("The body.\n");
});

it('treats a file without front matter as body only', function () {
    expect(FrontMatter::parse('Just text'))->toBe([[], 'Just text']);
});

it('keeps --- lines inside the body', function () {
    [, $body] = FrontMatter::parse("---\ntitle: A\n---\nBefore\n---\nAfter");

    expect($body)->toBe("Before\n---\nAfter");
});

it('accepts Windows line endings and a byte order mark', function () {
    [$fields, $body] = FrontMatter::parse("\u{FEFF}---\r\ntitle: Hi\r\n---\r\nText");

    expect($fields['title'])->toBe('Hi')->and($body)->toBe('Text');
});

it('reports broken YAML with the file name', function () {
    FrontMatter::parse("---\ntitle: [unclosed\n---\n", 'posts/x/content.md');
})->throws(InvalidContentException::class, 'posts/x/content.md');

it('writes files it can read back', function () {
    $source = FrontMatter::dump(['title' => 'Hello', 'tags' => ['a']], "Body text\n\n");

    expect($source)->toStartWith("---\ntitle: Hello\n")
        ->and(FrontMatter::parse($source))->toBe([['title' => 'Hello', 'tags' => ['a']], "Body text\n"]);
});

it('writes values without needless quotes and reads them back', function () {
    $fields = ['title' => 'Hello, Wake!', 'published' => '2026-10-17 09:00', 'tags' => ['news', 'Ruhr Area'],
        'seo' => ['title' => 'Colon: inside'], 'count' => '123', 'flag' => true, 'quote' => "It's", 'text' => "Line one\nLine two"];

    $source = FrontMatter::dump($fields, '');

    expect($source)->toContain("title: Hello, Wake!\n")
        ->toContain("tags: [news, Ruhr Area]\n")
        ->toContain("  title: 'Colon: inside'\n")
        ->toContain("count: '123'\n")
        ->and(FrontMatter::parse($source)[0])->toBe($fields);
});

it('writes no blank lines after the front matter when there is no text', function () {
    expect(FrontMatter::dump(['title' => 'Wake'], ''))->toBe("---\ntitle: Wake\n---\n")
        ->and(FrontMatter::dump(['title' => 'Wake'], "Hi\n\n"))->toBe("---\ntitle: Wake\n---\n\nHi\n");
});
