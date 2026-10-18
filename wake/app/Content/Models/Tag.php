<?php

namespace Wake\Content\Models;

use Illuminate\Support\Str;
use Wake\Content\Entry;
use Wake\Content\Index;
use Wake\Content\Query;

/**
 * A tag. Tags exist as soon as a post uses them. A file in content/tags/
 * (travel.en.md) is optional and adds a title, a description or a slug per
 * language.
 */
class Tag extends Entry
{
    protected static string $type = 'tag';

    public static function findOrVirtual(string $slug, string $language): self
    {
        $row = app(Index::class)->find('tags/'.$slug) ?? [
            'id' => 'tags/'.$slug,
            'type' => 'tag',
            'name' => $slug,
            'order' => null,
            'menu' => false,
            'parent' => null,
            'hash' => substr(md5('tags/'.$slug), 0, 10),
            'style' => null,
            'languages' => [$language => ['file' => null, 'mtime' => 0, 'fields' => ['title' => Str::headline($slug)]]],
        ];

        // A tag file without this language still gets a page in it.
        if (! isset($row['languages'][$language])) {
            $fields = reset($row['languages'])['fields'] ?? [];
            $row['languages'][$language] = ['file' => null, 'mtime' => 0, 'fields' => [
                'title' => $fields['title'] ?? Str::headline($slug),
            ]];
        }

        return new self($row, $language);
    }

    public function isVirtual(): bool
    {
        return $this->row['languages'][$this->language]['file'] === null;
    }

    /**
     * Published posts with this tag, newest first.
     */
    public function posts(): Query
    {
        return Post::query()->in($this->requested)->published()->tag($this->row['name'])->latest();
    }

    public function body(): string
    {
        return $this->isVirtual() ? '' : parent::body();
    }
}
