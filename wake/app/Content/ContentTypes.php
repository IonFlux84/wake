<?php

namespace Wake\Content;

use Symfony\Component\Yaml\Yaml;
use Wake\Content\Models\Page;
use Wake\Content\Models\Post;
use Wake\Content\Models\Tag;
use Wake\Support\Paths;

/**
 * All content types of the site: the built-in post, page and tag, plus every
 * blueprint in site/blueprints/ that names a folder.
 */
class ContentTypes
{
    /** @var array<string, ContentType>|null */
    protected ?array $types = null;

    /**
     * @return array<string, ContentType>
     */
    public function all(): array
    {
        return $this->types ??= $this->load();
    }

    public function get(string $name): ?ContentType
    {
        return $this->all()[$name] ?? null;
    }

    public function forFolder(string $folder): ?ContentType
    {
        foreach ($this->all() as $type) {
            if ($type->folder === $folder) {
                return $type;
            }
        }

        return null;
    }

    public function flush(): void
    {
        $this->types = null;
    }

    /**
     * @return array<string, ContentType>
     */
    protected function load(): array
    {
        $blueprints = $this->blueprints();

        $types = [
            'post' => new ContentType('post', 'posts', ContentType::FOLDERS, Post::class,
                config('wake.post_url', '{slug}'), 'Posts', $blueprints['post'] ?? []),
            'page' => new ContentType('page', 'pages', ContentType::TREE, Page::class,
                '{path}', 'Pages', $blueprints['page'] ?? []),
            'tag' => new ContentType('tag', 'tags', ContentType::FILES, Tag::class,
                trim(config('wake.tag_url', 'tag'), '/').'/{slug}', 'Tags', $blueprints['tag'] ?? []),
        ];

        foreach ($blueprints as $name => $blueprint) {
            if (isset($types[$name]) || empty($blueprint['folder'])) {
                continue;
            }

            $folder = trim($blueprint['folder'], '/');

            $types[$name] = new ContentType(
                name: $name,
                folder: $folder,
                layout: ContentType::FOLDERS,
                model: Entry::class,
                url: $blueprint['url'] ?? $folder.'/{slug}',
                title: $blueprint['title'] ?? ucfirst($folder),
                blueprint: $blueprint,
            );
        }

        return $types;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function blueprints(): array
    {
        $blueprints = [];

        foreach (glob(Paths::settings('blueprints/*.yml')) ?: [] as $file) {
            $blueprints[basename($file, '.yml')] = Yaml::parseFile($file) ?? [];
        }

        return $blueprints;
    }
}
