<?php

namespace Wake\Content\Models;

use Wake\Content\Entry;

class Page extends Entry
{
    protected static string $type = 'page';

    public function isHome(): bool
    {
        return $this->row['parent'] === null && $this->row['name'] === 'home';
    }

    /**
     * Pages with "menu: true" appear in the automatic main menu.
     */
    public function isListed(): bool
    {
        return (bool) ($this->row['menu'] ?? false);
    }

    /**
     * The position among its sibling pages ("order: 2"), null without one.
     */
    public function position(): ?int
    {
        return $this->row['order'] ?? null;
    }
}
