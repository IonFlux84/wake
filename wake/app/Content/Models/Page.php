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
     * Listed pages have a number prefix (pages/1_about) and appear in menus.
     */
    public function isListed(): bool
    {
        return $this->row['num'] !== null;
    }
}
