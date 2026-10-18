<?php

namespace Wake\Cache;

use Wake\Support\Files;

/**
 * Finished HTML pages in storage/cache/pages, served by public/index.php
 * before Laravel starts. Each page lives at {path}/index.html.
 */
class PageCache
{
    public function enabled(): bool
    {
        return (bool) config('wake.page_cache.enabled');
    }

    public function directory(): string
    {
        return storage_path('cache/pages');
    }

    public function file(string $path): string
    {
        $path = trim($path, '/');

        return $this->directory().($path === '' ? '' : '/'.$path).'/index.html';
    }

    public function put(string $path, string $html): void
    {
        if (! $this->enabled() || str_contains($path, '..')) {
            return;
        }

        $this->writeRules();
        Files::write($this->file($path), $html);
    }

    public function has(string $path): bool
    {
        return is_file($this->file($path));
    }

    /**
     * Throw away every cached page.
     */
    public function flush(): void
    {
        $dir = $this->directory();

        if (! is_dir($dir)) {
            return;
        }

        // Move the folder out of the way first, so no request can see a
        // half-deleted cache, then delete it at leisure.
        $trash = $dir.'.old-'.bin2hex(random_bytes(4));

        if (@rename($dir, $trash)) {
            Files::deleteDirectory($trash);
        }

        if ($this->enabled()) {
            $this->writeRules();
        }
    }

    /**
     * The rules public/index.php needs to serve cached pages on its own.
     */
    public function writeRules(): void
    {
        $file = $this->directory().'/.rules.php';
        $rules = [
            'dir' => $this->directory(),
            'cookie' => (string) config('session.cookie'),
            'ttl' => (int) config('wake.page_cache.ttl', 300),
        ];

        if (! is_file($file) || (@include $file) !== $rules) {
            Files::writePhp($file, $rules);
        }
    }
}
