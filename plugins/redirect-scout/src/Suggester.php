<?php

namespace RedirectScout;

use Illuminate\Support\Str;
use Wake\Content\Categories;
use Wake\Content\Entry;
use Wake\Content\Index;
use Wake\Content\Query;
use Wake\Localization\Languages;
use Wake\Routing\Resolver;

/**
 * Proposes a target for an old address that leads nowhere, with a score
 * from 0 to 100 and the reason. It compares the last part of the address
 * with slugs and file names, and the old page's title with titles here.
 */
class Suggester
{
    /** @var list<array{target: string, label: string, slug: string, title: string, path: string}>|null */
    protected ?array $pages = null;

    /** @var array<string, list<string>>|null file name => media paths */
    protected ?array $files = null;

    public function __construct(protected Resolver $resolver, protected Languages $languages, protected Index $index) {}

    /**
     * @return array{target: string, label: string, score: int, reason: string}|null
     */
    public function suggest(string $path, ?string $title = null): ?array
    {
        $last = basename($path);

        if ($last === '' || $last === '.') {
            return null;
        }

        return Address::type($path) === 'file'
            ? $this->file($last)
            : ($this->page($path, $title) ?? $this->parent($path));
    }

    /**
     * A file of the media library with the same name.
     *
     * @return array{target: string, label: string, score: int, reason: string}|null
     */
    protected function file(string $name): ?array
    {
        $files = $this->files();
        $exact = $files[mb_strtolower($name)] ?? [];
        $similar = $files[$this->simplify($name)] ?? [];

        if ($found = $exact ?: $similar) {
            return [
                'target' => 'media/'.$found[0],
                'label' => basename($found[0]),
                'score' => ($exact ? 100 : 90) - (count($found) > 1 ? 15 : 0),
                'reason' => $exact ? 'same file name' : 'similar file name',
            ];
        }

        return null;
    }

    /**
     * An entry, tag or category with the same slug or a similar title.
     *
     * @return array{target: string, label: string, score: int, reason: string}|null
     */
    protected function page(string $path, ?string $title): ?array
    {
        $slug = Str::slug(preg_replace('/\.[a-z0-9]{2,5}$/i', '', basename($path)));
        $parents = explode('/', dirname($path));
        $title = $this->cleanTitle((string) $title);
        $best = null;

        foreach ($this->pages() as $page) {
            [$score, $reason] = match (true) {
                $slug !== '' && $page['slug'] === $slug => [count(array_intersect($parents, explode('/', $page['path']))) ? 100 : 95, 'same slug'],
                $title !== '' && Str::slug($title) === Str::slug($page['title']) => [92, 'same title'],
                default => [0, ''],
            };

            if ($score === 0 && $slug !== '' && strlen($slug) > 5) {
                similar_text($slug, $page['slug'], $percent);
                [$score, $reason] = $percent >= 85 ? [(int) round($percent * 0.9), 'similar slug'] : [0, ''];
            }

            if ($score === 0 && $title !== '' && mb_strlen($title) > 5) {
                similar_text(mb_strtolower($title), mb_strtolower($page['title']), $percent);
                [$score, $reason] = $percent >= 80 ? [(int) round($percent * 0.85), 'similar title'] : [0, ''];
            }

            if ($score > ($best['score'] ?? 0)) {
                $best = ['target' => $page['target'], 'label' => $page['title'], 'score' => $score, 'reason' => $reason];
            }
        }

        return $best;
    }

    /**
     * Nothing similar: the nearest parent address that exists here.
     *
     * @return array{target: string, label: string, score: int, reason: string}|null
     */
    protected function parent(string $path): ?array
    {
        while (($path = dirname($path)) !== '.' && $path !== '') {
            [$language, $rest] = $this->resolver->split($path);

            if ($entry = $this->resolver->resolve($language, $rest)) {
                return ['target' => $entry->id(), 'label' => $entry->title(), 'score' => 40, 'reason' => 'parent page'];
            }

            foreach ($this->pages() as $page) {
                if ($page['path'] === $path) {
                    return ['target' => $page['target'], 'label' => $page['title'], 'score' => 40, 'reason' => 'parent page'];
                }
            }
        }

        return null;
    }

    /**
     * Published entries, categories and tags of the default language.
     *
     * @return list<array{target: string, label: string, slug: string, title: string, path: string}>
     */
    protected function pages(): array
    {
        if ($this->pages !== null) {
            return $this->pages;
        }

        $language = $this->languages->default();
        $pages = [];

        foreach ((new Query)->in($language)->published()->get() as $entry) {
            /** @var Entry $entry */
            $path = $entry->path();
            $target = $entry->type() === 'tag' ? '/'.$path : $entry->id();
            $pages[] = ['target' => $target, 'label' => $entry->title(), 'slug' => basename($path) ?: $entry->slug(), 'title' => $entry->title(), 'path' => $path];
        }

        foreach (app(Categories::class)->all() as $category) {
            $path = trim((string) parse_url($category->url($language), PHP_URL_PATH), '/');
            $pages[] = ['target' => '/'.$path, 'label' => $category->title($language), 'slug' => $category->slug($language), 'title' => $category->title($language), 'path' => $path];
        }

        return $this->pages = $pages;
    }

    /**
     * Media files by lowercase name and by simplified name.
     *
     * @return array<string, list<string>>
     */
    protected function files(): array
    {
        if ($this->files !== null) {
            return $this->files;
        }

        $files = [];

        foreach (array_keys($this->index->allMedia()) as $path) {
            $files[mb_strtolower(basename($path))][] = $path;
            $simple = $this->simplify(basename($path));

            if ($simple !== mb_strtolower(basename($path))) {
                $files[$simple][] = $path;
            }
        }

        return $this->files = $files;
    }

    /**
     * "Flyer_2019 (1).PDF" and "flyer-2019-1.pdf" are the same file to a human.
     */
    protected function simplify(string $name): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return Str::slug(pathinfo($name, PATHINFO_FILENAME)).($extension !== '' ? '.'.$extension : '');
    }

    /**
     * "Our history | Example club" is "Our history".
     */
    protected function cleanTitle(string $title): string
    {
        $title = trim(html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5));

        return trim(preg_split('/\s+[|–—·»-]\s+/u', $title)[0] ?? $title);
    }
}
