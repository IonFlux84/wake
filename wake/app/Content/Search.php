<?php

namespace Wake\Content;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Wake\Localization\Languages;
use Wake\Support\Files;

/**
 * Full-text search without a database: a small inverted index per language
 * in temp/cache/search-{lang}.php (word => [entry id => score]), rebuilt
 * when the content index changes. Titles count more than tags, tags more
 * than text.
 */
class Search
{
    public function __construct(protected Index $index, protected Languages $languages) {}

    /**
     * Published entries matching all words of the query, best first.
     *
     * @return Collection<int, Entry>
     */
    public function query(string $query, ?string $language = null, int $limit = 20): Collection
    {
        $language ??= $this->languages->current();
        $words = $this->words($query);

        if ($words === []) {
            return collect();
        }

        $table = $this->table($language);
        $scores = null;

        foreach ($words as $word) {
            // Prefix search: "harb" finds "harbour".
            $hits = [];

            foreach ($table['words'] as $indexed => $ids) {
                if (str_starts_with($indexed, $word)) {
                    foreach ($ids as $id => $score) {
                        $hits[$id] = max($hits[$id] ?? 0, $score);
                    }
                }
            }

            $scores = $scores === null ? $hits : array_intersect_key($scores, $hits);

            foreach ($scores as $id => $score) {
                $scores[$id] = $score + $hits[$id];
            }
        }

        arsort($scores);

        return collect(array_keys($scores))
            ->map(fn (string $id) => ($row = $this->index->find($id)) ? Entry::make($row, $language) : null)
            ->filter(fn (?Entry $entry) => $entry?->isPublished() && $entry->type() !== 'tag')
            ->take($limit)
            ->values();
    }

    /**
     * @return array{updated: int, words: array<string, array<string, int>>}
     */
    protected function table(string $language): array
    {
        $file = storage_path("cache/search-{$language}.php");
        $table = is_file($file) ? @include $file : null;

        if (! is_array($table) || $table['updated'] !== $this->index->updatedAt()) {
            $table = $this->build($language);
            Files::writePhp($file, $table);
        }

        return $table;
    }

    /**
     * @return array{updated: int, words: array<string, array<string, int>>}
     */
    protected function build(string $language): array
    {
        $words = [];

        foreach ($this->index->entries() as $id => $row) {
            if (! isset($row['languages'][$language])) {
                continue;
            }

            $entry = Entry::make($row, $language);

            if (! $entry->isPublished()) {
                continue;
            }

            $parts = [
                3 => $entry->title(),
                2 => implode(' ', (array) $entry->get('tags', [])),
                1 => $entry->get('excerpt', '').' '.strip_tags((string) $entry->content()),
            ];

            foreach ($parts as $weight => $text) {
                foreach (array_count_values($this->words($text)) as $word => $count) {
                    $words[$word][$id] = ($words[$word][$id] ?? 0) + $weight * min($count, 5);
                }
            }
        }

        return ['updated' => $this->index->updatedAt(), 'words' => $words];
    }

    /**
     * @return list<string>
     */
    protected function words(string $text): array
    {
        $text = mb_strtolower(Str::ascii($text));
        preg_match_all('/[a-z0-9]{2,}/', $text, $matches);

        return array_values(array_filter($matches[0], fn ($w) => strlen($w) <= 40));
    }
}
