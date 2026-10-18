<?php

use Wake\Content\PageStyle;

it('removes what could load from elsewhere or escape the page', function (string $css, string $gone) {
    expect(PageStyle::clean($css))->not->toContain($gone);
})->with([
    'import' => ['@import "https://x.example/a.css"; p { color: red }', '@import'],
    'external url' => ['p { background: url("https://x.example/a.png") }', 'x.example'],
    'protocol-relative url' => ['p { background: url(//x.example/a.png) }', 'x.example'],
    'image-set' => ['p { background: image-set("https://x.example/a.png" 1x) }', 'x.example'],
    'expression' => ['p { width: expression(alert(1)) }', 'alert'],
    'binding' => ['p { -moz-binding: url(x.xml#y) }', 'binding'],
    'closing the style element' => ['p::after { content: "</style><script>" }', '</style'],
    'comment hiding a brace' => ['p { color: red } /* } */ } body { display: none }', '} body'],
]);

it('keeps braces balanced so @scope holds', function () {
    $css = PageStyle::clean('} } p { color: red } h1 { color: blue');

    expect(substr_count($css, '{'))->toBe(substr_count($css, '}'))
        ->and(PageStyle::scoped('p { color: red }'))->toStartWith('@scope (main) {')
        ->and(PageStyle::scoped(''))->toBe('');
});

it('keeps braces in strings', function () {
    expect(PageStyle::clean('p::before { content: "}" }'))->toBe('p::before { content: "}" }');
});
