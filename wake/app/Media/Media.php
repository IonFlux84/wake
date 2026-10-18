<?php

namespace Wake\Media;

use Illuminate\Support\HtmlString;
use Wake\Content\Entry;

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
     * A complete responsive <img> tag.
     */
    public function img(string $alt = '', string $sizes = '100vw', array $attributes = []): HtmlString
    {
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
