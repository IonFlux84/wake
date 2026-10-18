<?php

namespace Wake\Content;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;
use Wake\Support\Files;
use Wake\Support\Paths;

/**
 * All post categories, one file each in site/categories/.
 */
class Categories
{
    /** @var array<string, Category>|null */
    protected ?array $categories = null;

    /**
     * @return array<string, Category>
     */
    public function all(): array
    {
        if ($this->categories === null) {
            $this->categories = [];

            foreach (glob(Paths::settings('categories/*.yml')) ?: [] as $file) {
                $key = basename($file, '.yml');
                $this->categories[$key] = new Category($key, Yaml::parseFile($file) ?: []);
            }

            ksort($this->categories);
        }

        return $this->categories;
    }

    public function get(?string $key): ?Category
    {
        return $key === null ? null : ($this->all()[$key] ?? null);
    }

    /**
     * The category whose address segment in a language is $slug.
     */
    public function forSlug(string $slug, string $language): ?Category
    {
        foreach ($this->all() as $category) {
            if ($category->slug($language) === $slug) {
                return $category;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(string $key, array $data): Category
    {
        $key = Str::slug($key);

        if ($key === '') {
            throw new InvalidArgumentException('A category needs a key.');
        }

        Files::write($this->file($key), Yaml::dump($data, 5, 2));
        $this->flush();

        return $this->get($key);
    }

    /**
     * Move a category file to the trash. Posts keep their "category" field.
     */
    public function trash(string $key): void
    {
        $target = Paths::data('trash/'.date('Y-m-d-His').'-category--'.$key.'.yml');
        @mkdir(dirname($target), 0775, true);
        @rename($this->file($key), $target);
        $this->flush();
    }

    /**
     * Everything that changes addresses, for the index to notice.
     *
     * @return array<string, mixed>
     */
    public function fingerprint(): array
    {
        return array_map(fn (Category $c) => [$c->toArray()['slug'] ?? $c->key, $c->urlPattern()], $this->all());
    }

    public function flush(): void
    {
        $this->categories = null;
    }

    protected function file(string $key): string
    {
        return Paths::settings('categories/'.$key.'.yml');
    }
}
