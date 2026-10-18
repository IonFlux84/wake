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
    FrontMatter::parse("---\ntitle: [unclosed\n---\n", 'posts/x/index.md');
})->throws(InvalidContentException::class, 'posts/x/index.md');

it('writes files it can read back', function () {
    $source = FrontMatter::dump(['title' => 'Hello', 'tags' => ['a']], "Body text\n\n");

    expect($source)->toStartWith("---\ntitle: Hello\n")
        ->and(FrontMatter::parse($source))->toBe([['title' => 'Hello', 'tags' => ['a']], "Body text\n"]);
});
