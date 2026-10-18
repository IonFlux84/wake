<?php

namespace Wake\Content;

use Illuminate\Support\Collection;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use Wake\Content\Models\Page;
use Wake\Support\Files;
use Wake\Support\Paths;

/**
 * The menus of the site, kept in settings/menus.yml:
 *
 *     main:
 *       - page: pages/1_about
 *         children:
 *           - page: pages/1_about/1_team
 *       - label: { en: Shop, de: Laden }
 *         url: https://shop.example.org
 *     footer:
 *       - page: pages/imprint
 *
 * Without a "main" menu in the file, the main menu follows the page tree:
 * the top-level pages with "menu: true" and their children with "menu: true",
 * in the order of their "order" field.
 */
class Menus
{
    public const NAMES = ['main', 'footer'];

    /** @var array<string, list<array<string, mixed>>>|null */
    protected ?array $data = null;

    /**
     * The stored items of every menu.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function all(): array
    {
        if ($this->data === null) {
            try {
                $data = is_file($this->file()) ? Yaml::parseFile($this->file()) : [];
            } catch (Throwable) {
                $data = [];
            }

            $this->data = is_array($data) ? array_filter($data, 'is_array') : [];
        }

        return $this->data;
    }

    public function isCustom(string $name): bool
    {
        return array_key_exists($name, $this->all());
    }

    /**
     * The items of a menu, ready for templates: unpublished pages are left out.
     *
     * @return Collection<int, MenuItem>
     */
    public function items(string $name = 'main', ?string $language = null): Collection
    {
        $pages = Page::query()->in($language)->published()->get()->keyBy(fn (Entry $page) => $page->id());

        if (! $this->isCustom($name)) {
            return $name === 'main' ? $this->fromTree($pages, null, 2) : collect();
        }

        return $this->build($this->all()[$name], $pages, $language);
    }

    /**
     * @param  list<array<string, mixed>>|null  $items  null removes the menu (the main menu follows the page tree again)
     */
    public function save(string $name, ?array $items): void
    {
        $data = $this->all();

        if ($items === null) {
            unset($data[$name]);
        } else {
            $data[$name] = array_values(array_map($this->clean(...), $items));
        }

        Files::write($this->file(), Yaml::dump($data, 6, 2));
        $this->data = $data;
        event(new ContentChanged);
    }

    /**
     * Updates page ids after pages were moved in the page tree.
     *
     * @param  callable(string): string  $rename
     */
    public function renameTargets(callable $rename): void
    {
        if ($this->all() === []) {
            return;
        }

        $walk = function (array $items) use (&$walk, $rename): array {
            return array_map(function (array $item) use ($walk, $rename) {
                if (isset($item['page'])) {
                    $item['page'] = $rename((string) $item['page']);
                }
                if (isset($item['children']) && is_array($item['children'])) {
                    $item['children'] = $walk($item['children']);
                }

                return $item;
            }, $items);
        };

        $data = array_map($walk, $this->all());

        if ($data !== $this->all()) {
            Files::write($this->file(), Yaml::dump($data, 6, 2));
            $this->data = $data;
        }
    }

    public function file(): string
    {
        return Paths::settings('menus.yml');
    }

    /**
     * @param  Collection<string, Entry>  $pages
     * @return Collection<int, MenuItem>
     */
    protected function fromTree(Collection $pages, ?string $parent, int $depth): Collection
    {
        return $pages
            ->filter(fn (Entry $page) => $page->row()['parent'] === $parent && ($page->row()['menu'] ?? false))
            ->sortBy(fn (Entry $page) => [$page->row()['order'] ?? PHP_INT_MAX, $page->row()['name']])
            ->map(fn (Entry $page) => new MenuItem(
                $page->title(), $page->url(), $page,
                $depth > 1 ? $this->fromTree($pages, $page->id(), $depth - 1) : collect(),
            ))
            ->values();
    }

    /**
     * @param  list<mixed>  $items
     * @param  Collection<string, Entry>  $pages
     * @return Collection<int, MenuItem>
     */
    protected function build(array $items, Collection $pages, ?string $language): Collection
    {
        return collect($items)->filter(fn ($item) => is_array($item))->map(function (array $item) use ($pages, $language) {
            $label = $item['label'] ?? null;
            $label = is_array($label) ? ($label[$language ?? app()->getLocale()] ?? reset($label) ?: '') : (string) $label;
            $children = $this->build(is_array($item['children'] ?? null) ? $item['children'] : [], $pages, $language);

            if (isset($item['page'])) {
                $page = $pages->get((string) $item['page']);

                return $page ? new MenuItem($label !== '' ? $label : $page->title(), $page->url(), $page, $children) : null;
            }

            return isset($item['url']) && $label !== '' ? new MenuItem($label, (string) $item['url'], null, $children) : null;
        })->filter()->values();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function clean(array $item): array
    {
        $label = $item['label'] ?? null;
        $label = is_array($label) ? array_filter(array_map(fn ($v) => trim((string) $v), $label)) : trim((string) $label);

        return array_filter([
            'page' => isset($item['page']) && $item['page'] !== '' ? (string) $item['page'] : null,
            'label' => $label === '' || $label === [] ? null : $label,
            'url' => empty($item['page']) && ! empty($item['url']) ? trim((string) $item['url']) : null,
            'children' => ! empty($item['children']) ? array_values(array_map($this->clean(...), $item['children'])) : null,
        ], fn ($v) => $v !== null);
    }
}
