<?php

namespace Wake\Content;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Wake\Content\Models\Page;
use Wake\Content\Models\Post;
use Wake\Content\Models\Tag;
use Wake\Localization\Languages;

/**
 * The website as a whole, available as $site in every template. Its fields
 * come from content/site.{lang}.md.
 */
class Site
{
    public function __construct(protected Index $index, protected Languages $languages) {}

    /**
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        return $this->index->site($this->language());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->fields(), $key, $default);
    }

    public function __get(string $key): mixed
    {
        return method_exists($this, $key) ? $this->$key() : $this->get($key);
    }

    public function title(): string
    {
        return (string) $this->get('title', config('app.name', 'Wake'));
    }

    public function description(): string
    {
        return (string) $this->get('description', '');
    }

    public function language(): string
    {
        return $this->languages->current();
    }

    /**
     * @return array<string, string> code => name
     */
    public function languages(): array
    {
        return $this->languages->all();
    }

    public function url(?string $language = null): string
    {
        return $this->languages->url('', $language);
    }

    public function posts(): Query
    {
        return Post::query()->published()->latest();
    }

    public function pages(): Query
    {
        return Page::query()->published()->childrenOf(null);
    }

    /**
     * A menu from site/menus.yml ("main" or "footer"). Without a stored main
     * menu, the main menu shows the listed top-level pages of the page tree,
     * in order, with their listed children.
     *
     * @return Collection<int, MenuItem>
     */
    public function menu(string $name = 'main'): Collection
    {
        return app(Menus::class)->items($name, $this->language());
    }

    /**
     * Every tag used by a published post, with the number of posts.
     *
     * @return Collection<int, Tag>
     */
    public function tags(): Collection
    {
        return Post::query()->published()->get()
            ->flatMap(fn (Entry $post) => (array) $post->get('tags', []))
            ->map(fn ($tag) => Str::slug((string) $tag))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->map(fn (int $count, string $slug) => Tag::findOrVirtual($slug, $this->language()))
            ->values();
    }

    /**
     * @return array<string, Category>
     */
    public function categories(): array
    {
        return app(Categories::class)->all();
    }

    public function category(string $key): ?Category
    {
        return app(Categories::class)->get($key);
    }

    /**
     * Entries of any type, e.g. $site->entries('event')->published().
     */
    public function entries(string $type): Query
    {
        return new Query($type);
    }

    public function find(string $id): ?Entry
    {
        return (new Query)->find($id);
    }
}
