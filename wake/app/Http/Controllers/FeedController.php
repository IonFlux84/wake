<?php

namespace Wake\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Wake\Content\Entry;
use Wake\Content\Index;
use Wake\Content\Models\Page;
use Wake\Content\Models\Post;
use Wake\Content\Query;
use Wake\Content\Site;
use Wake\Localization\Languages;

/**
 * RSS feed per language, sitemap.xml, llms.txt and robots.txt.
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
        return response()
            ->view('wake::sitemap', ['entries' => $this->indexable(), 'languages' => $this->languages, 'updated' => $index->updatedAt()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * /llms.txt (llmstxt.org): the site and its published, indexable
     * entries as Markdown link lists, the same entries as in sitemap.xml.
     * A file public/llms.txt wins.
     */
    public function llms(): Response
    {
        $file = public_path('llms.txt');

        if (is_file($file)) {
            $text = (string) file_get_contents($file);
        } else {
            $this->languages->setCurrent($this->languages->default());
            $lines = ['# '.$this->line($this->site->title())];

            if (($description = $this->line($this->site->description())) !== '') {
                $lines[] = '';
                $lines[] = '> '.$description;
            }

            $groups = $this->indexable()->groupBy(fn (Entry $entry) => $entry->type());
            $order = ['page' => 0, 'post' => 1];
            $groups = $groups->sortBy(fn ($entries, string $type) => $order[$type] ?? 2, SORT_REGULAR);

            foreach ($groups as $entries) {
                $lines[] = '';
                $lines[] = '## '.$entries->first()->contentType()->title;
                $lines[] = '';

                foreach ($entries as $entry) {
                    $description = $this->line((string) ($entry->get('seo.description') ?: $entry->excerpt(30)));
                    $title = str_replace(['[', ']'], ['\\[', '\\]'], $this->line($entry->title()));
                    $lines[] = '- ['.$title.']('.$entry->url().')'.($description !== '' ? ': '.$description : '');
                }
            }

            $text = implode("\n", $lines)."\n";
        }

        return response($text)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Cache-Control', 'public, max-age='.(int) config('wake.page_cache.ttl', 300));
    }

    /**
     * Published entries of every language that search engines may index,
     * without the home pages (they are the language roots).
     *
     * @return Collection<int, Entry>
     */
    protected function indexable(): Collection
    {
        $entries = collect();

        foreach ($this->languages->codes() as $language) {
            $entries = $entries->merge(
                (new Query)->in($language)->published()->withFallback(false)->get()
                    ->filter(fn ($entry) => ! $entry->get('noindex'))
                    ->filter(fn ($entry) => $entry->type() !== 'page' || ! ($entry instanceof Page && $entry->isHome())),
            );
        }

        return $entries->values();
    }

    protected function line(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    public function robots(): Response
    {
        $lines = config('wake.closed')
            ? ['User-agent: *', 'Disallow: /']
            : ['User-agent: *', 'Disallow: /wake/', '', 'Sitemap: '.url('sitemap.xml')];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
