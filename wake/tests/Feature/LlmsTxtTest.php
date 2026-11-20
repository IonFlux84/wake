<?php

it('describes the site for language models', function () {
    $response = $this->get('/llms.txt')->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertHeader('Cache-Control', 'max-age=300, public');

    $text = $response->getContent();

    expect($text)->toStartWith("# Test Site\n\n> A site for tests.\n\n## Pages\n\n")
        ->toContain('- [About]('.url('/about').'): About us.')
        ->toContain("## Posts\n\n")
        ->toContain('- [First post]('.url('/first').')')
        ->toContain(url('/de/erster-beitrag'))
        ->not->toContain('A draft')
        ->not->toContain('/future');

    expect(strpos($text, '## Pages'))->toBeLessThan(strpos($text, '## Posts'));
});

it('lists the same entries as the sitemap', function () {
    preg_match_all('#<loc>([^<]+)</loc>#', $this->get('/sitemap.xml')->getContent(), $sitemap);
    preg_match_all('#\]\(([^)]+)\)#', $this->get('/llms.txt')->getContent(), $llms);

    // The sitemap also lists the language roots.
    $roots = [url('/'), url('/de')];

    expect(collect($llms[1])->sort()->values()->all())
        ->toBe(collect($sitemap[1])->reject(fn ($url) => in_array($url, $roots, true))->sort()->values()->all());
});

it('uses the SEO description and leaves out entries with noindex', function () {
    $this->writeContent('pages/about/content.md', "---\ntitle: About\nseo:\n  description: Who we are.\n---\n\nAbout us.\n");
    $this->writeContent('pages/contact/content.md', "---\ntitle: Contact\nnoindex: true\n---\n\nWrite to us.\n");

    $this->get('/llms.txt')
        ->assertSee('- [About]('.url('/about').'): Who we are.', false)
        ->assertDontSee('[Contact]', false);
});

it('serves a file public/llms.txt instead', function () {
    file_put_contents(public_path('llms.txt'), "# Written by hand\n");

    $this->get('/llms.txt')->assertOk()->assertContent("# Written by hand\n");
});

it('stays closed with a closed site', function () {
    config(['wake.closed' => true]);

    $this->get('/llms.txt')->assertRedirect(route('wake.login'));
});
