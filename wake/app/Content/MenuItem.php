<?php

namespace Wake\Content;

use Illuminate\Support\Collection;

/**
 * One entry of a menu: a page or a link, maybe with children.
 */
class MenuItem
{
    /**
     * @param  Collection<int, MenuItem>  $children
     */
    public function __construct(
        protected string $title,
        protected string $url,
        protected ?Entry $page = null,
        protected ?Collection $children = null,
    ) {}

    public function title(): string
    {
        return $this->title;
    }

    public function url(): string
    {
        return $this->url;
    }

    /**
     * The page behind the item, null for links.
     */
    public function page(): ?Entry
    {
        return $this->page;
    }

    /**
     * @return Collection<int, MenuItem>
     */
    public function children(): Collection
    {
        return $this->children ?? collect();
    }

    public function isExternal(): bool
    {
        return $this->page === null && parse_url($this->url, PHP_URL_HOST) !== null
            && parse_url($this->url, PHP_URL_HOST) !== request()->getHost();
    }

    /**
     * Whether the current address is this item or below it.
     */
    public function isActive(): bool
    {
        $path = trim((string) parse_url($this->url, PHP_URL_PATH), '/');
        $current = trim(request()->path(), '/');

        return $this->page !== null && ($current === $path || ($path !== '' && str_starts_with($current.'/', $path.'/')));
    }
}
