<?php

namespace Wake\Content;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Wake\Localization\Languages;
use Wake\Support\Files;
use Wake\Support\Paths;

/**
 * All post categories, one folder each in content/categories/:
 *
 *     categories/events/content.md      title, slug, description and the settings
 *     categories/events/content.de.md   title, slug, description in German
 *
 * Titles, slugs and descriptions from the language files become maps
 * ({en: Events, de: Termine}), as the Category class expects them.
 */
class Categories
{
    /** Fields that differ per language. */
    public const TRANSLATED = ['title', 'slug', 'description'];

    /** @var array<string, Category>|null */
    protected ?array $categories = null;

    /**
     * @return array<string, Category>
     */
    public function all(): array
    {
        if ($this->categories === null) {
            $this->categories = [];

            $default = app(Languages::class)->default();

            foreach (glob(Paths::content('categories/*/content.md')) ?: [] as $file) {
                $key = basename(dirname($file));
                $data = FrontMatter::parse((string) file_get_contents($file), $file)[0];

                foreach (glob(dirname($file).'/content.*.md') ?: [] as $translation) {
                    $code = substr(basename($translation, '.md'), 8);
                    $fields = FrontMatter::parse((string) file_get_contents($translation), $translation)[0];

                    foreach (self::TRANSLATED as $field) {
                        if (isset($fields[$field])) {
                            $data[$field] = [...(is_array($data[$field] ?? null) ? $data[$field] : (isset($data[$field]) ? [$default => $data[$field]] : [])), $code => $fields[$field]];
                        }
                    }
                }

                $this->categories[$key] = new Category($key, $data);
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

        $default = app(Languages::class)->default();
        $byLanguage = [];

        foreach (self::TRANSLATED as $field) {
            if (is_array($data[$field] ?? null)) {
                foreach ($data[$field] as $code => $value) {
                    if ($code !== $default && $value !== '' && $value !== null) {
                        $byLanguage[$code][$field] = $value;
                    }
                }
                $data[$field] = $data[$field][$default] ?? null;
            }
        }

        Files::write($this->file($key), FrontMatter::dump(array_filter($data, fn ($v) => $v !== null && $v !== ''), ''));

        foreach (glob(dirname($this->file($key)).'/content.*.md') ?: [] as $old) {
            @unlink($old);
        }

        foreach ($byLanguage as $code => $fields) {
            Files::write(dirname($this->file($key))."/content.{$code}.md", FrontMatter::dump($fields, ''));
        }

        app(Index::class)->refresh();
        $this->flush();

        return $this->get($key);
    }

    /**
     * Move a category folder to the trash. Posts keep their "category" field.
     */
    public function trash(string $key): void
    {
        $target = Paths::data('trash/'.date('Y-m-d-His').'-category--'.$key);
        @mkdir(dirname($target), 0775, true);
        @rename(dirname($this->file($key)), $target);
        app(Index::class)->refresh();
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
        return Paths::content('categories/'.$key.'/content.md');
    }
}
