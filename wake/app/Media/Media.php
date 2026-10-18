<?php

namespace Wake\Media;

use Illuminate\Support\HtmlString;
use Wake\Content\Entry;
use Wake\Localization\Languages;

/**
 * A file in an entry's folder: an image, a PDF, a download.
 *
 * Content folders are not public. Media URLs point to /media/…, where Wake
 * copies the original or creates a resized version on the first request and
 * saves it in public/media, so every later request is served directly by the
 * web server.
 */
class Media
{
    public const IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];

    /** @var array{0: int, 1: int}|false|null */
    protected array|false|null $size = null;

    public function __construct(protected Entry $entry, protected string $name, protected int $mtime) {}

    public function name(): string
    {
        return $this->name;
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
    }

    public function path(): string
    {
        return $this->entry->directory().'/'.$this->name;
    }

    public function isImage(): bool
    {
        return in_array($this->extension(), self::IMAGE_TYPES, true);
    }

    public function filesize(): int
    {
        return (int) @filesize($this->path());
    }

    public function width(): ?int
    {
        return $this->dimensions()[0] ?? null;
    }

    public function height(): ?int
    {
        return $this->dimensions()[1] ?? null;
    }

    /**
     * The URL of the original, or of a version with the given width and format
     * (the WebP version of photo.jpg is photo.jpg.webp). Widths are rounded up
     * to the next size in the "images.widths" setting.
     */
    public function url(?int $width = null, ?string $format = null): string
    {
        $variant = 'o';
        $name = $this->name;

        if ($this->isImage()) {
            $variant = $width === null ? 'o' : (string) Images::nearestWidth($width);
            $name .= $format !== null && $format !== $this->extension() ? '.'.$format : '';
        }

        return url('media/'.$this->entry->hash().'/'.base_convert((string) $this->mtime, 10, 36).'/'.$variant.'/'.rawurlencode($name));
    }

    /**
     * A srcset attribute value with every configured width up to the original's.
     */
    public function srcset(?string $format = null): string
    {
        return collect(Images::widths())
            ->filter(fn (int $w) => $this->width() === null || $w <= $this->width())
            ->map(fn (int $w) => $this->url($w, $format).' '.$w.'w')
            ->implode(', ');
    }

    /**
     * The details from the sidecar file (photo.jpg.yml).
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->entry->mediaMeta($this->name);
    }

    /**
     * The alt text in the current (or given) language, '' when none is set.
     */
    public function alt(?string $language = null): string
    {
        return $this->translated('alt', $language);
    }

    public function caption(?string $language = null): string
    {
        return $this->translated('caption', $language);
    }

    public function credit(): string
    {
        return is_scalar($this->meta()['credit'] ?? null) ? (string) $this->meta()['credit'] : '';
    }

    /**
     * The focus point as a CSS position ("30% 60%"); the middle by default.
     */
    public function focus(): string
    {
        $focus = $this->meta()['focus'] ?? null;

        return is_string($focus) && preg_match('/^(\d{1,3})% (\d{1,3})%$/', $focus, $m) && $m[1] <= 100 && $m[2] <= 100
            ? $focus : '50% 50%';
    }

    /**
     * A complete responsive <img> tag. The alt text from the file's details
     * wins over $alt, and a focus point becomes object-position.
     */
    public function img(string $alt = '', string $sizes = '100vw', array $attributes = []): HtmlString
    {
        $alt = $this->alt() !== '' ? $this->alt() : $alt;

        if ($this->focus() !== '50% 50%') {
            $attributes['style'] = trim(($attributes['style'] ?? '').';object-position:'.$this->focus(), ';');
        }

        $attributes = array_merge([
            'src' => $this->url(min(Images::widths()[2] ?? 960, $this->width() ?? PHP_INT_MAX)),
            'srcset' => $this->srcset(),
            'sizes' => $sizes,
            'alt' => $alt,
            'width' => $this->width(),
            'height' => $this->height(),
            'loading' => 'lazy',
            'decoding' => 'async',
        ], $attributes);

        $html = collect($attributes)
            ->filter(fn ($value, $key) => $key === 'alt' || ($value !== null && $value !== ''))
            ->map(fn ($value, $key) => $key.'="'.e((string) $value).'"')
            ->implode(' ');

        return new HtmlString('<img '.$html.'>');
    }

    /**
     * A <picture> with a WebP source in front of the responsive <img>, for
     * browsers that understand it.
     */
    public function picture(string $alt = '', string $sizes = '100vw', array $attributes = []): HtmlString
    {
        $img = $this->img($alt, $sizes, $attributes);

        if ($this->extension() === 'webp' || ! Images::supports('webp') || in_array($this->extension(), ['gif', 'avif'], true)) {
            return $img;
        }

        return new HtmlString('<picture><source type="image/webp" srcset="'.e($this->srcset('webp')).'" sizes="'.e($sizes).'">'.$img.'</picture>');
    }

    protected function translated(string $key, ?string $language): string
    {
        $value = $this->meta()[$key] ?? null;

        if (is_array($value)) {
            $languages = app(Languages::class);
            $value = $value[$language ?? $languages->current()] ?? $value[$languages->default()] ?? null;
        }

        return is_scalar($value) ? (string) $value : '';
    }

    public function __toString(): string
    {
        return $this->url();
    }

    /**
     * @return array{0: int, 1: int}|array{}
     */
    protected function dimensions(): array
    {
        if ($this->size === null) {
            $info = $this->isImage() ? @getimagesize($this->path()) : false;
            $this->size = $info ? [$info[0], $info[1]] : false;
        }

        return $this->size ?: [];
    }
}
