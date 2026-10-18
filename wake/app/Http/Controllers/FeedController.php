<?php

namespace Wake\Http\Controllers;

use Illuminate\Http\Response;
use Wake\Content\Index;
use Wake\Content\Models\Page;
use Wake\Content\Models\Post;
use Wake\Content\Query;
use Wake\Content\Site;
use Wake\Localization\Languages;

/**
 * RSS feed per language, sitemap.xml and robots.txt.
 */
class FeedController
{
    public function __construct(protected Languages $languages, protected Site $site) {}

    public function feed(?string $language = null): Response
    {
        $this->languages->setCurrent($language ?? $this->languages->default());

        $posts = Post::query()->published()->latest()->limit((int) config('wake.feed.limit', 20))->withFallback(false)->get();

        return response()
            ->view('wake::feed', ['site' => $this->site, 'posts' => $posts])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    public function sitemap(Index $index): Response
    {
        $entries = collect();

        foreach ($this->languages->codes() as $language) {
            $entries = $entries->merge(
                (new Query)->in($language)->published()->withFallback(false)->get()
                    ->filter(fn ($entry) => ! $entry->get('noindex'))
                    ->filter(fn ($entry) => $entry->type() !== 'page' || ! ($entry instanceof Page && $entry->isHome())),
            );
        }

        return response()
            ->view('wake::sitemap', ['entries' => $entries, 'languages' => $this->languages, 'updated' => $index->updatedAt()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $lines = ['User-agent: *', 'Disallow: /wake/', '', 'Sitemap: '.url('sitemap.xml')];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
