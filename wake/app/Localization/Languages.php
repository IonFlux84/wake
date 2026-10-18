<?php

namespace Wake\Localization;

/**
 * The languages of the site and the one the current request is in.
 */
class Languages
{
    protected ?string $current = null;

    /**
     * @return array<string, string> code => name, the default language first
     */
    public function all(): array
    {
        return config('wake.languages') ?: ['en' => 'English'];
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_keys($this->all());
    }

    public function default(): string
    {
        return $this->codes()[0];
    }

    public function isMultilingual(): bool
    {
        return count($this->all()) > 1;
    }

    public function exists(?string $code): bool
    {
        return $code !== null && array_key_exists($code, $this->all());
    }

    public function name(string $code): string
    {
        return $this->all()[$code] ?? $code;
    }

    public function current(): string
    {
        return $this->current ?? $this->default();
    }

    public function setCurrent(string $code): void
    {
        $this->current = $this->exists($code) ? $code : $this->default();

        app()->setLocale($this->current);
    }

    /**
     * The URL prefix of a language, without slashes ("" for no prefix).
     */
    public function prefix(string $code): string
    {
        if ($code === $this->default() && ! config('wake.prefix_default_language')) {
            return '';
        }

        return $code;
    }

    /**
     * Turn a site-relative path into an absolute URL in the given language.
     */
    public function url(string $path, ?string $code = null): string
    {
        $prefix = $this->prefix($code ?? $this->current());
        $path = trim($path, '/');

        return url('/'.trim($prefix.'/'.$path, '/'));
    }
}
