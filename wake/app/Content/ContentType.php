<?php

namespace Wake\Content;

/**
 * A kind of content, such as posts or pages, and where it lives.
 */
class ContentType
{
    /** One folder per entry: posts/hello-world/content.md */
    public const FOLDERS = 'folders';

    /** Nested folders, each one an entry: pages/about/team/content.md */
    public const TREE = 'tree';

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
