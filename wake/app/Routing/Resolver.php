<?php

namespace Wake\Routing;

use Wake\Content\Entry;
use Wake\Content\Index;
use Wake\Localization\Languages;

/**
 * Finds the entry for a request path. The first path segment selects the
 * language when it is a language code; the rest is looked up in the
 * index's address table.
 */
class Resolver
{
    public function __construct(protected Index $index, protected Languages $languages) {}

    /**
     * Split a path into language and the rest.
     *
     * @return array{0: string, 1: string}
     */
    public function split(string $path): array
    {
        $path = trim($path, '/');
        $first = explode('/', $path, 2)[0];

        if ($first !== '' && $this->languages->exists($first)
            && ($first !== $this->languages->default() || config('wake.prefix_default_language'))) {
            return [$first, trim(substr($path, strlen($first)), '/')];
        }

        return [$this->languages->default(), $path];
    }

    /**
     * The visible entry at a path in a language, or null.
     */
    public function resolve(string $language, string $path): ?Entry
    {
        $id = $this->index->route($language, $path);

        if ($id === null && $language !== $this->languages->default() && config('wake.missing_translation') === 'fallback') {
            $id = $this->index->route($this->languages->default(), $path);

            if ($id !== null && isset($this->index->find($id)['languages'][$language])) {
                $id = null; // the translation exists under its own slug
            }
        }

        $row = $id === null ? null : $this->index->find($id);

        if ($row === null) {
            return null;
        }

        $entry = Entry::make($row, $language);

        return $entry->isPublished() ? $entry : null;
    }
}
