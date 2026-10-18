<?php

namespace Wake\Content;

use ArrayIterator;
use Carbon\CarbonImmutable;
use Countable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use IteratorAggregate;
use Throwable;
use Traversable;
use Wake\Localization\Languages;

/**
 * Finds entries in the index, similar to Eloquent's query builder:
 *
 *     Post::published()->tag('travel')->latest()->limit(5)->get()
 *
 * A query can be looped over directly in a template.
 *
 * @implements IteratorAggregate<int, Entry>
 */
class Query implements Countable, IteratorAggregate
{
    protected ?string $language = null;

    protected ?string $status = null;

    /** @var list<callable(array<string, mixed>, array<string, mixed>): bool> */
    protected array $filters = [];

    /** @var array{0: string, 1: string}|null */
    protected ?array $order = null;

    protected ?int $limit = null;

    protected int $offset = 0;

    protected ?bool $fallback = null;

    public function __construct(protected ?string $type = null) {}

    /**
     * Whether an entry with these fields is visible on the website now.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function isVisible(array $fields): bool
    {
        $status = $fields['status'] ?? 'published';

        if ($status === 'draft') {
            return false;
        }

        $date = $fields['published'] ?? null;

        if ($status === 'scheduled' && ($date === null || $date === '')) {
            return false;
        }

        if ($date === null || $date === '') {
            return $status === 'published';
        }

        try {
            return CarbonImmutable::parse((string) $date, config('app.timezone'))->lte(CarbonImmutable::now());
        } catch (Throwable) {
            return $status === 'published';
        }
    }

    // Language -------------------------------------------------------------

    /**
     * Query in another language than the current one.
     */
    public function in(?string $language): static
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Include entries without a translation, shown in the default language.
     * By default this follows the "missing_translation" setting.
     */
    public function withFallback(bool $fallback = true): static
    {
        $this->fallback = $fallback;

        return $this;
    }

    // Filters --------------------------------------------------------------

    public function published(): static
    {
        $this->status = 'visible';

        return $this;
    }

    public function drafts(): static
    {
        return $this->status('draft');
    }

    public function scheduled(): static
    {
        $this->status = 'scheduled';

        return $this;
    }

    /**
     * Entries of every status. That is the default.
     */
    public function any(): static
    {
        $this->status = null;

        return $this;
    }

    public function status(string $status): static
    {
        return $this->where(fn (array $fields) => ($fields['status'] ?? 'published') === $status);
    }

    public function tag(string $slug): static
    {
        $slug = Str::slug($slug);

        return $this->where(fn (array $fields) => in_array($slug, array_map(
            fn ($tag) => Str::slug((string) $tag), (array) ($fields['tags'] ?? []),
        ), true));
    }

    public function category(string $category): static
    {
        return $this->where(fn (array $fields) => ($fields['category'] ?? null) === $category);
    }

    /**
     * Entries with an occurrence today or later, the next one first. Works
     * for single and recurring events (see Recurrence).
     */
    public function upcoming(string $field = 'start'): static
    {
        $today = CarbonImmutable::now()->startOfDay();

        $this->filters[] = fn (array $fields) => static::recurrence($fields, $field)?->next($today) !== null;
        $this->order = ['@next:'.$field, 'asc'];

        return $this;
    }

    /**
     * Entries whose last occurrence lies in the past, the latest first.
     * Recurring events without an end never become past.
     */
    public function past(string $field = 'start'): static
    {
        $today = CarbonImmutable::now()->startOfDay();

        $this->filters[] = function (array $fields) use ($field, $today) {
            $recurrence = static::recurrence($fields, $field);

            return $recurrence !== null && $recurrence->next($today) === null;
        };
        $this->order = ['@last:'.$field, 'desc'];

        return $this;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    protected static function recurrence(array $fields, string $field): ?Recurrence
    {
        return Recurrence::fromFields($field === 'start' ? $fields : ['start' => $fields[$field] ?? null] + $fields);
    }

    protected static function parseDate(mixed $value): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse((string) $value, config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    public function author(string $author): static
    {
        return $this->where('author', $author);
    }

    /**
     * Only children of the given page.
     */
    public function childrenOf(?string $id): static
    {
        $this->filters[] = fn (array $fields, array $row) => $row['parent'] === $id;

        return $this;
    }

    /**
     * Only entries with a number prefix (pages/1_about). These are "listed"
     * pages, e.g. for the main menu.
     */
    public function listed(): static
    {
        $this->filters[] = fn (array $fields, array $row) => $row['num'] !== null;

        return $this;
    }

    /**
     * Filter by a field: where('location', 'Bochum'), where('price', '>', 10)
     * or with a callback that receives the fields and the index row.
     */
    public function where(string|callable $field, mixed $operator = null, mixed $value = null): static
    {
        if (is_callable($field) && ! is_string($field)) {
            $this->filters[] = $field;

            return $this;
        }

        if (func_num_args() === 2) {
            [$operator, $value] = ['=', $operator];
        }

        $this->filters[] = function (array $fields) use ($field, $operator, $value) {
            $actual = data_get($fields, $field);

            return match ($operator) {
                '=', '==' => $actual == $value,
                '!=', '<>' => $actual != $value,
                '>' => $actual > $value,
                '>=' => $actual >= $value,
                '<' => $actual < $value,
                '<=' => $actual <= $value,
                'in' => in_array($actual, (array) $value),
                'contains' => is_array($actual) ? in_array($value, $actual) : str_contains((string) $actual, (string) $value),
                default => throw new InvalidArgumentException("Unknown operator {$operator}."),
            };
        };

        return $this;
    }

    // Order and limits -----------------------------------------------------

    public function latest(): static
    {
        return $this->orderBy('date', 'desc');
    }

    public function oldest(): static
    {
        return $this->orderBy('date', 'asc');
    }

    /**
     * Order by "date", "title", "num" (the number prefix of pages) or any field.
     */
    public function orderBy(string $field, string $direction = 'asc'): static
    {
        $this->order = [$field, strtolower($direction) === 'desc' ? 'desc' : 'asc'];

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = $offset;

        return $this;
    }

    // Results --------------------------------------------------------------

    /**
     * @return Collection<int, Entry>
     */
    public function get(): Collection
    {
        $rows = $this->rows();

        if ($this->offset > 0 || $this->limit !== null) {
            $rows = array_slice($rows, $this->offset, $this->limit);
        }

        return collect($rows)->map(fn (array $row) => Entry::make($row, $this->language()))->values();
    }

    public function first(): ?Entry
    {
        return (clone $this)->limit(1)->get()->first();
    }

    public function find(string $id): ?Entry
    {
        $row = app(Index::class)->find($id);

        if ($row === null || ($this->type !== null && $row['type'] !== $this->type)) {
            return null;
        }

        return Entry::make($row, $this->language());
    }

    public function count(): int
    {
        $count = count($this->rows()) - $this->offset;

        return max(0, $this->limit === null ? $count : min($count, $this->limit));
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * @return LengthAwarePaginator<int, Entry>
     */
    public function paginate(?int $perPage = null, ?int $page = null): LengthAwarePaginator
    {
        $perPage ??= (int) config('wake.per_page', 10);
        $page ??= Paginator::resolveCurrentPage();
        $rows = $this->rows();

        $items = collect(array_slice($rows, ($page - 1) * $perPage, $perPage))
            ->map(fn (array $row) => Entry::make($row, $this->language()));

        return new LengthAwarePaginator($items, count($rows), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
        ]);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->get()->all());
    }

    // Internals ------------------------------------------------------------

    protected function language(): string
    {
        return $this->language ?? app(Languages::class)->current();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function rows(): array
    {
        $index = app(Index::class);
        $languages = app(Languages::class);
        $language = $this->language();
        $default = $languages->default();
        $fallback = $this->fallback ?? config('wake.missing_translation') === 'fallback';

        $rows = $this->type === null ? $index->entries() : $index->ofType($this->type);
        $result = [];

        foreach ($rows as $row) {
            $translation = $row['languages'][$language] ?? ($fallback ? ($row['languages'][$default] ?? null) : null);

            if ($translation === null) {
                continue;
            }

            // Fields the translation leaves out (dates, places) come from the
            // default language, as in Entry::get().
            $fields = array_replace($row['languages'][$default]['fields'] ?? [], $translation['fields']);

            if ($this->status === 'visible' && ! static::isVisible($fields)) {
                continue;
            }

            if ($this->status === 'scheduled'
                && (($fields['status'] ?? null) !== 'scheduled' || static::isVisible($fields))) {
                continue;
            }

            foreach ($this->filters as $filter) {
                if (! $filter($fields, $row)) {
                    continue 2;
                }
            }

            $result[] = $row;
        }

        return $this->sort($result, $language, $default);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function sort(array $rows, string $language, string $default): array
    {
        [$field, $direction] = $this->order ?? match ($this->type) {
            'page' => ['num', 'asc'],
            'tag' => ['title', 'asc'],
            default => ['date', 'desc'],
        };

        $today = CarbonImmutable::now()->startOfDay();

        $value = function (array $row) use ($field, $language, $default, $today) {
            if (str_starts_with($field, '@')) {
                [$kind, $dateField] = explode(':', substr($field, 1), 2);
                $fields = array_replace($row['languages'][$default]['fields'] ?? [], $row['languages'][$language]['fields'] ?? []);
                $recurrence = static::recurrence($fields, $dateField);
                $occurrence = $kind === 'next' ? $recurrence?->next($today) : $recurrence?->last($today);

                return $occurrence ? $occurrence['start']->getTimestamp() : 0;
            }

            return match ($field) {
                'date' => Addresses::date($row, $language, $default)?->getTimestamp() ?? 0,
                'num' => $row['num'] ?? PHP_INT_MAX,
                default => data_get(array_replace($row['languages'][$default]['fields'] ?? [], $row['languages'][$language]['fields'] ?? []), $field)
                    ?? ($field === 'title' ? $row['name'] : null),
            };
        };

        usort($rows, function (array $a, array $b) use ($value, $direction) {
            [$va, $vb] = [$value($a), $value($b)];
            $result = is_string($va) && is_string($vb)
                ? strnatcasecmp($va, $vb)
                : ($va <=> $vb);

            return $direction === 'desc' ? -$result : $result;
        });

        return $rows;
    }
}
