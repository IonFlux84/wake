<?php

namespace Wake\Content;

/**
 * A kind of content, such as posts or pages, and where it lives.
 */
class ContentType
{
    /** One folder per entry: posts/2026-10-17-hello/index.en.md */
    public const FOLDERS = 'folders';

    /** Nested folders, each one an entry: pages/1_about/2_team/index.en.md */
    public const TREE = 'tree';

    /** One file per entry and language: tags/travel.en.md */
    public const FILES = 'files';

    /**
     * @param  class-string<Entry>  $model
     * @param  string  $url  URL pattern with {slug}, {path}, {year}, {month}, {day}
     * @param  array<string, mixed>  $blueprint
     */
    public function __construct(
        public readonly string $name,
        public readonly string $folder,
        public readonly string $layout,
        public readonly string $model,
        public readonly string $url,
        public readonly string $title,
        public readonly array $blueprint = [],
    ) {}
}
