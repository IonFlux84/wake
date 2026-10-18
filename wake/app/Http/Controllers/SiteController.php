<?php

namespace Wake\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Wake\Content\Entry;
use Wake\Content\Models\Page;
use Wake\Content\Models\Tag;
use Wake\Content\Site;
use Wake\Localization\Languages;
use Wake\Routing\Resolver;
use Wake\Theme\Theme;

/**
 * Renders the website: every address that is not the panel, a feed or a
 * media file ends up here.
 */
class SiteController
{
    public function __construct(
        protected Resolver $resolver,
        protected Languages $languages,
        protected Theme $theme,
        protected Site $site,
    ) {}

    public function show(string $path = ''): Response|RedirectResponse
    {
        [$language, $rest] = $this->resolver->split($path);
        $this->languages->setCurrent($language);

        if (config('wake.prefix_default_language') && $this->languages->prefix($language) !== explode('/', trim($path, '/'))[0]) {
            return redirect()->to($this->languages->url($rest, $language), 301);
        }

        $entry = $this->resolver->resolve($language, $rest);

        if ($rest === '' && $entry === null) {
            return $this->render(['home'], null);
        }

        $entry ??= $this->tag($language, $rest);

        if ($entry === null) {
            return $this->notFound();
        }

        $templates = $entry instanceof Page && $entry->isHome()
            ? [$entry->get('template', ''), 'home', 'page', 'default']
            : [$entry->template(), $entry->type(), 'default'];

        return $this->render($templates, $entry);
    }

    /**
     * Tags exist as soon as a published post uses them, with or without a
     * file in content/tags/.
     */
    protected function tag(string $language, string $path): ?Tag
    {
        $prefix = trim((string) config('wake.tag_url', 'tag'), '/').'/';

        if (! str_starts_with($path, $prefix) || str_contains($slug = substr($path, strlen($prefix)), '/')) {
            return null;
        }

        $tag = Tag::findOrVirtual($slug, $language);

        return $tag->slug() === $slug && $tag->posts()->exists() ? $tag : null;
    }

    public function notFound(): Response
    {
        $view = $this->theme->template('404') ?? 'wake::404';

        return response()->view($view, ['site' => $this->site, 'entry' => null], 404);
    }

    /**
     * @param  list<string>  $templates  candidates, the first that exists wins
     */
    protected function render(array $templates, ?Entry $entry): Response
    {
        $view = $this->theme->template(...array_map('strval', $templates));

        if ($view === null) {
            abort_if($entry === null, 404);

            $view = 'wake::default';
        }

        $data = ['site' => $this->site, 'entry' => $entry];

        if ($entry !== null) {
            $data[$entry->type()] = $entry;
        }

        $response = response()->view($view, $data);

        if ($entry?->isFallback()) {
            $response->header('Content-Language', $entry->language());
        }

        return $response;
    }
}
