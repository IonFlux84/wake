<?php

namespace Wake\Content;

use Symfony\Component\Yaml\Yaml;
use Wake\Support\Files;
use Wake\Support\Paths;

/**
 * Old addresses of entries, kept in site/redirects.yml. When the slug of a
 * published entry changes, Wake remembers the old address and answers it
 * with a permanent redirect to the new one.
 *
 *     en:
 *       old-slug: posts/2026-10-17-hello-world
 *       old/page: https://example.org/elsewhere
 */
class Redirects
{
    /** @var array<string, array<string, string>>|null */
    protected ?array $map = null;

    /**
     * @return array<string, array<string, string>>
     */
    public function all(): array
    {
        if ($this->map === null) {
            $file = $this->file();
            $this->map = is_file($file) ? (Yaml::parseFile($file) ?: []) : [];
        }

        return $this->map;
    }

    /**
     * The target for an old path: an entry id or an absolute URL.
     */
    public function target(string $language, string $path): ?string
    {
        return $this->all()[$language][trim($path, '/')] ?? null;
    }

    public function add(string $language, string $path, string $target): void
    {
        $map = $this->all();
        $map[$language][trim($path, '/')] = $target;

        Files::write($this->file(), Yaml::dump($map, 3, 2));
        $this->map = $map;
    }

    public function remove(string $language, string $path): void
    {
        $map = $this->all();
        unset($map[$language][trim($path, '/')]);
        $map = array_filter($map);

        Files::write($this->file(), Yaml::dump($map, 3, 2));
        $this->map = $map;
    }

    public function file(): string
    {
        return Paths::site('redirects.yml');
    }
}
