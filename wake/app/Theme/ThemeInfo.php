<?php

namespace Wake\Theme;

use Illuminate\Support\Str;

/**
 * What a theme says about itself in theme.yml.
 */
class ThemeInfo
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(public readonly string $folder, public readonly string $path, protected array $data) {}

    public function name(): string
    {
        return (string) ($this->data['name'] ?? Str::headline($this->folder));
    }

    public function description(?string $locale = null): string
    {
        $value = $this->data['description'] ?? '';

        return is_array($value) ? (string) ($value[$locale ?? app()->getLocale()] ?? $value['en'] ?? reset($value)) : (string) $value;
    }

    public function version(): string
    {
        return (string) ($this->data['version'] ?? '');
    }

    public function author(): string
    {
        return (string) ($this->data['author'] ?? '');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function options(): array
    {
        return array_filter((array) ($this->data['options'] ?? []), 'is_array');
    }

    /**
     * Templates the theme provides, e.g. ["home", "post", "post-event"].
     *
     * @return list<string>
     */
    public function templates(): array
    {
        $files = glob($this->path.'/templates/*.blade.php') ?: [];

        return array_map(fn ($f) => basename($f, '.blade.php'), $files);
    }

    public function screenshot(): ?string
    {
        foreach (['screenshot.jpg', 'screenshot.png', 'screenshot.webp'] as $file) {
            if (is_file($this->path.'/'.$file)) {
                return $this->path.'/'.$file;
            }
        }

        return null;
    }
}
