<?php

namespace Wake\Content;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Wake\Localization\Languages;

/**
 * A category of posts, defined in site/categories/{key}.yml. It brings its
 * own fields (shown in the editor), its own website templates and,
 * optionally, its own addresses.
 */
class Category
{
    public const FIELD_TYPES = ['text', 'textarea', 'number', 'toggle', 'date', 'datetime', 'select', 'url', 'email'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(public readonly string $key, protected array $data) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    public function title(?string $language = null): string
    {
        return $this->translated('title', $language) ?: Str::headline($this->key);
    }

    public function description(?string $language = null): string
    {
        return $this->translated('description', $language);
    }

    /**
     * The address segment in a language, e.g. "termine" or "events".
     */
    public function slug(?string $language = null): string
    {
        return Str::slug($this->translated('slug', $language) ?: $this->key, language: $language ?? 'en');
    }

    public function url(?string $language = null): string
    {
        return app(Languages::class)->url($this->slug($language), $language);
    }

    /**
     * Name of the website templates: post-{template} for posts,
     * category-{template} for the category page.
     */
    public function template(): string
    {
        return (string) ($this->data['template'] ?? $this->key);
    }

    /**
     * Address pattern for posts of this category, or null for the site's
     * post_url. Placeholders: {category}, {slug}, {year}, {month}, {day}.
     */
    public function urlPattern(): ?string
    {
        $pattern = trim((string) ($this->data['url'] ?? ''), '/');

        return $pattern !== '' ? $pattern : null;
    }

    /**
     * "upcoming" lists posts by a date field, next first; "newest" by
     * publication date.
     */
    public function listMode(): string
    {
        return ($this->data['list'] ?? 'newest') === 'upcoming' ? 'upcoming' : 'newest';
    }

    public function dateField(): ?string
    {
        return $this->listMode() === 'upcoming' ? (string) ($this->data['date_field'] ?? 'start') : null;
    }

    public function icon(): string
    {
        return (string) ($this->data['icon'] ?? 'tag');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array
    {
        return array_filter((array) ($this->data['fields'] ?? []), fn ($field) => is_array($field));
    }

    public function label(string $field, ?string $language = null): string
    {
        $label = $this->fields()[$field]['label'] ?? null;

        return $this->pick($label, $language) ?: Str::headline($field);
    }

    /**
     * Published posts of this category: upcoming first for date categories.
     */
    public function posts(?string $language = null): Query
    {
        $query = (new Query('post'))->in($language)->published()->category($this->key);

        return $this->dateField() ? $query->upcoming($this->dateField()) : $query->latest();
    }

    /**
     * Posts whose date has passed, newest first (date categories only).
     */
    public function past(?string $language = null): Query
    {
        $query = (new Query('post'))->in($language)->published()->category($this->key);

        return $this->dateField() ? $query->past($this->dateField()) : $query->latest();
    }

    /**
     * Every occurrence of the category's events in a period, one row per
     * date, earliest first. A weekly event appears once a week.
     *
     * @return list<array{entry: Entry, start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function occurrences(CarbonInterface $from, CarbonInterface $to, ?string $language = null, int $limit = 200): array
    {
        $rows = [];

        foreach ((new Query('post'))->in($language)->published()->category($this->key)->get() as $entry) {
            foreach ($entry->recurrence()?->between($from, $to, $limit) ?? [] as $occurrence) {
                $rows[] = ['entry' => $entry, ...$occurrence];
            }
        }

        usort($rows, fn ($a, $b) => $a['start'] <=> $b['start']);

        return array_slice($rows, 0, $limit);
    }

    protected function translated(string $key, ?string $language): string
    {
        return $this->pick($this->data[$key] ?? null, $language);
    }

    /**
     * A value that is either a string or a map of language => string.
     */
    protected function pick(mixed $value, ?string $language): string
    {
        if (! is_array($value)) {
            return (string) $value;
        }

        $languages = app(Languages::class);
        $language ??= $languages->current();

        return (string) ($value[$language] ?? $value[$languages->default()] ?? $value['en'] ?? reset($value) ?: '');
    }
}
