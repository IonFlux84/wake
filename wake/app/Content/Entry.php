<?php

namespace Wake\Content;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use JsonSerializable;
use Wake\Localization\Languages;
use Wake\Markdown\Markdown;
use Wake\Media\Media;
use Wake\Support\Paths;

/**
 * One entry in one language: a post, a page, a tag or an entry of a custom
 * type. Fields from the front matter are available as properties:
 * $post->title, $event->location.
 *
 * @method static Query published()
 * @method static Query drafts()
 * @method static Query tag(string $slug)
 * @method static Query latest()
 * @method static Query where(string $field, mixed $operator, mixed $value = null)
 */
class Entry implements JsonSerializable
{
    /** The content type of this model. Generic entries get it from the row. */
    protected static string $type = '';

    /** @var array{0: array<string, mixed>, 1: string}|null fields and body from the file */
    protected ?array $document = null;

    protected string $language;

    /**
     * @param  array<string, mixed>  $row  the entry's row from the index
     * @param  string  $requested  the language that was asked for
     */
    public function __construct(protected array $row, protected string $requested)
    {
        $default = app(Languages::class)->default();

        $this->language = isset($row['languages'][$requested])
            ? $requested
            : (isset($row['languages'][$default]) ? $default : array_key_first($row['languages']));
    }

    /**
     * Start a query for entries of this type in the current language.
     */
    public static function query(): Query
    {
        return new Query(static::$type ?: null);
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return static::query()->$method(...$arguments);
    }

    /**
     * Find an entry by its id (the folder below content/, e.g. "posts/2026-10-17-hello").
     */
    public static function find(string $id, ?string $language = null): ?static
    {
        return static::query()->in($language)->find($id);
    }

    /**
     * Turn an index row into the right model class.
     *
     * @param  array<string, mixed>  $row
     */
    public static function make(array $row, string $language): self
    {
        $model = app(ContentTypes::class)->get($row['type'])?->model ?? self::class;

        return new $model($row, $language);
    }

    // Identity -------------------------------------------------------------

    public function id(): string
    {
        return $this->row['id'];
    }

    public function type(): string
    {
        return $this->row['type'];
    }

    public function contentType(): ContentType
    {
        return app(ContentTypes::class)->get($this->type());
    }

    /**
     * The language of the text shown. Differs from requestedLanguage() when a
     * translation is missing and the site falls back to the default language.
     */
    public function language(): string
    {
        return $this->language;
    }

    public function requestedLanguage(): string
    {
        return $this->requested;
    }

    public function isFallback(): bool
    {
        return $this->language !== $this->requested;
    }

    /**
     * A short, stable identifier used in media URLs.
     */
    public function hash(): string
    {
        return $this->row['hash'];
    }

    // Fields ---------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        return $this->row['languages'][$this->language]['fields'];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = data_get($this->fields(), $key);

        if ($value === null && $this->language !== app(Languages::class)->default()) {
            $value = data_get($this->row['languages'][app(Languages::class)->default()]['fields'] ?? [], $key);
        }

        return $value ?? $default;
    }

    public function __get(string $key): mixed
    {
        return method_exists($this, $key) ? $this->$key() : $this->get($key);
    }

    public function __isset(string $key): bool
    {
        return method_exists($this, $key) || $this->get($key) !== null;
    }

    public function title(): string
    {
        return (string) ($this->fields()['title'] ?? Str::headline($this->row['name']));
    }

    public function slug(): string
    {
        return Addresses::slug($this->row, $this->language, app(Languages::class)->default());
    }

    /**
     * draft, scheduled or published
     */
    public function status(): string
    {
        $status = $this->fields()['status'] ?? 'published';

        return in_array($status, ['draft', 'scheduled', 'published'], true) ? $status : 'draft';
    }

    /**
     * Visible on the website right now. A scheduled entry becomes visible as
     * soon as its date has passed.
     */
    public function isPublished(): bool
    {
        return Query::isVisible($this->fields());
    }

    public function isDraft(): bool
    {
        return $this->status() === 'draft';
    }

    public function isScheduled(): bool
    {
        return $this->status() === 'scheduled' && ! $this->isPublished();
    }

    public function date(): ?CarbonImmutable
    {
        return Addresses::date($this->row, $this->language, app(Languages::class)->default());
    }

    public function modifiedAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp($this->row['languages'][$this->language]['mtime'], config('app.timezone'));
    }

    public function template(): string
    {
        return (string) ($this->get('template') ?: $this->type());
    }

    // Text -----------------------------------------------------------------

    /**
     * The Markdown body as written in the file.
     */
    public function body(): string
    {
        return $this->document()[1];
    }

    /**
     * The body rendered as HTML.
     */
    public function content(): HtmlString
    {
        return new HtmlString(app(Markdown::class)->render($this->body(), $this));
    }

    /**
     * The "excerpt" field, or else the start of the text.
     */
    public function excerpt(int $words = 40): string
    {
        $excerpt = $this->get('excerpt');

        if (is_string($excerpt) && $excerpt !== '') {
            return $excerpt;
        }

        // Paragraphs only: tables, code and cards make poor excerpts.
        preg_match_all('#<p>(.*?)</p>#s', (string) $this->content(), $paragraphs);
        $text = trim(preg_replace('/\s+/', ' ', strip_tags(implode(' ', $paragraphs[1]))));

        return Str::words($text, $words, ' …');
    }

    public function readingTime(int $wordsPerMinute = 230): int
    {
        return max(1, (int) round(str_word_count(strip_tags((string) $this->content())) / $wordsPerMinute));
    }

    // Addresses ------------------------------------------------------------

    /**
     * The address without language prefix and without slashes.
     */
    public function path(): string
    {
        return Addresses::path(
            $this->row, $this->language, app(Index::class)->entries(), $this->contentType(), app(Languages::class)->default(),
        );
    }

    public function url(): string
    {
        return app(Languages::class)->url($this->path(), $this->language);
    }

    // Translations ---------------------------------------------------------

    /**
     * @return list<string> language codes this entry exists in
     */
    public function translations(): array
    {
        return array_keys($this->row['languages']);
    }

    public function hasTranslation(string $language): bool
    {
        return isset($this->row['languages'][$language]);
    }

    public function translation(string $language): ?static
    {
        return $this->hasTranslation($language) ? new static($this->row, $language) : null;
    }

    // Relations ------------------------------------------------------------

    /**
     * @return Collection<int, Entry>
     */
    public function tags(): Collection
    {
        $tags = (array) $this->get('tags', []);

        return collect($tags)
            ->map(fn ($slug) => Str::slug((string) $slug))
            ->filter()
            ->unique()
            ->map(fn (string $slug) => Models\Tag::findOrVirtual($slug, $this->language))
            ->values();
    }

    /**
     * The dates of an event (start, end, repetition), or null without start.
     */
    public function recurrence(): ?Recurrence
    {
        $fields = array_replace($this->row['languages'][app(Languages::class)->default()]['fields'] ?? [], $this->fields());

        return Recurrence::fromFields($fields);
    }

    /**
     * The next occurrence that has not ended yet.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}|null
     */
    public function nextOccurrence(): ?array
    {
        return $this->recurrence()?->next(CarbonImmutable::now());
    }

    public function category(): ?Category
    {
        return app(Categories::class)->get($this->get('category'));
    }

    public function parent(): ?Entry
    {
        $row = $this->row['parent'] ? app(Index::class)->find($this->row['parent']) : null;

        return $row ? static::make($row, $this->requested) : null;
    }

    public function children(): Query
    {
        return (new Query($this->type()))->in($this->requested)->childrenOf($this->id());
    }

    // Media ----------------------------------------------------------------

    /**
     * @return Collection<string, Media>
     */
    public function media(): Collection
    {
        return collect($this->row['media'])->map(fn (int $mtime, string $name) => new Media($this, $name, $mtime));
    }

    public function file(string $name): ?Media
    {
        return isset($this->row['media'][$name]) ? new Media($this, $name, $this->row['media'][$name]) : null;
    }

    /**
     * The cover image ("cover" field), or the named image.
     */
    public function image(?string $name = null): ?Media
    {
        $name ??= $this->get('cover');

        return is_string($name) ? $this->file($name) : null;
    }

    /**
     * The preview image for lists, cards and social media: the "thumbnail"
     * field, or else the header image.
     */
    public function thumbnail(): ?Media
    {
        $name = $this->get('thumbnail');
        $file = is_string($name) ? $this->file($name) : null;

        return $file?->isImage() ? $file : $this->image();
    }

    /**
     * The folder of this entry on disk.
     */
    public function directory(): string
    {
        return Paths::content($this->contentType()->layout === ContentType::FILES ? dirname($this->id()) : $this->id());
    }

    /**
     * @return array<string, mixed>
     */
    public function row(): array
    {
        return $this->row;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id(),
            'type' => $this->type(),
            'language' => $this->language(),
            'title' => $this->title(),
            'slug' => $this->slug(),
            'status' => $this->status(),
            'date' => $this->date()?->toIso8601String(),
            'url' => $this->url(),
            'fields' => $this->fields(),
            'translations' => $this->translations(),
        ];
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    protected function document(): array
    {
        return $this->document ??= FrontMatter::parse(
            (string) @file_get_contents(Paths::content($this->row['languages'][$this->language]['file'])),
            $this->row['languages'][$this->language]['file'],
        );
    }
}
