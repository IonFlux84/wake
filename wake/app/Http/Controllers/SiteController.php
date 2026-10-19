<?php

namespace Wake\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Wake\Content\Categories;
use Wake\Content\Category;
use Wake\Content\Entry;
use Wake\Content\Models\Page;
use Wake\Content\Models\Tag;
use Wake\Content\Query;
use Wake\Content\Redirects;
use Wake\Content\Search;
use Wake\Content\Site;
use Wake\Localization\Languages;
use Wake\Media\Media;
use Wake\Media\MediaAccess;
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

        if ($rest === 'search') {
            return $this->search($language);
        }

        if (str_ends_with($rest, '.ics')) {
            return $this->calendar($language, substr($rest, 0, -4));
        }

        $entry = $this->resolver->resolve($language, $rest);

        if ($rest === '' && $entry === null) {
            return $this->render(['home'], null);
        }

        $entry ??= $this->tag($language, $rest);

        if ($entry === null && $category = app(Categories::class)->forSlug($rest, $language)) {
            return $this->category($category);
        }

        if ($entry === null) {
            return $this->redirect($language, $rest) ?? $this->notFound();
        }

        return $this->renderEntry($entry);
    }

    /**
     * Render one entry with the theme, whatever its status. Also used by
     * the preview.
     */
    public function renderEntry(Entry $entry): Response
    {
        $this->languages->setCurrent($entry->requestedLanguage());

        $templates = $entry instanceof Page && $entry->isHome()
            ? [$entry->get('template', ''), 'home', 'page', 'default']
            : array_filter([
                $entry->get('template'),
                $entry->category() ? 'post-'.$entry->category()->template() : null,
                $entry->type(),
                'default',
            ]);

        return $this->render($templates, $entry);
    }

    /**
     * An old address (settings/redirects.yml) gets a permanent redirect to
     * an entry, a path of this site, a media file or another website, or
     * "410 Gone".
     */
    protected function redirect(string $language, string $path): Response|RedirectResponse|null
    {
        $target = app(Redirects::class)->target($language, $path);

        if ($target === null) {
            return null;
        }

        if ($target === Redirects::GONE) {
            return $this->gone();
        }

        if (preg_match('#^https?://#', $target)) {
            return redirect()->away($target, 301);
        }

        if (str_starts_with($target, '/')) {
            return trim($target, '/') === $path ? null : redirect()->to($target, 301);
        }

        if (str_starts_with($target, 'media/')) {
            $media = Media::find($target);

            // Temporary: the file's address carries its version and changes with the file.
            return $media && MediaAccess::isPublic($media->relativePath()) ? redirect()->to($media->url(), 302) : null;
        }

        $entry = (new Query)->in($language)->find($target);

        return $entry?->isPublished() ? redirect()->to($entry->url(), 301) : null;
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

    /**
     * The page of a category: category-{template}, else category.
     */
    protected function category(Category $category): Response
    {
        $view = $this->theme->template('category-'.$category->template(), 'category') ?? 'wake::category';

        return response()->view($view, ['site' => $this->site, 'entry' => null, 'category' => $category]);
    }

    /**
     * {address}.ics: a calendar file for a post with a start date.
     */
    protected function calendar(string $language, string $path): Response
    {
        $entry = $this->resolver->resolve($language, $path);
        $start = $entry?->get('start');

        abort_if($entry === null || ! $start, 404);

        return response(view('wake::calendar', ['entry' => $entry])->render())
            ->header('Content-Type', 'text/calendar; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$entry->slug().'.ics"');
    }

    /**
     * /search?q=… with the theme's search template.
     */
    protected function search(string $language): Response
    {
        $query = trim((string) request()->query('q', ''));
        $results = app(Search::class)->query($query, $language);
        $view = $this->theme->template('search') ?? 'wake::search';

        return response()->view($view, ['site' => $this->site, 'entry' => null, 'query' => $query, 'results' => $results])
            ->header('X-Robots-Tag', 'noindex');
    }

    /**
     * An address that was removed on purpose: the theme's 410, else its 404 page, with status 410.
     */
    public function gone(): Response
    {
        $view = $this->theme->template('410', '404') ?? 'wake::404';

        return response()->view($view, ['site' => $this->site, 'entry' => null], 410);
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
