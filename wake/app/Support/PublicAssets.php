<?php

namespace Wake\Support;

/**
 * Copies files that must be public (panel styles, theme assets) into
 * public/assets/ when they changed, and returns their URL with a version.
 * Shared hosting often forbids symlinks, so a copy is the reliable way.
 */
class PublicAssets
{
    /**
     * @param  string  $source  absolute path of the original file
     * @param  string  $target  path below public/, e.g. "assets/wake/panel.css"
     */
    public static function url(string $source, string $target): string
    {
        $target = ltrim($target, '/');

        if (! is_file($source) || str_contains($target, '..')) {
            return url($target);
        }

        $mtime = filemtime($source);
        $public = public_path($target);

        if (! is_file($public) || filemtime($public) !== $mtime) {
            Files::copy($source, $public);
            @touch($public, $mtime);

            if (str_ends_with($source, '.css')) {
                self::publishReferences($source, $target);
            }
        }

        return url($target).'?v='.base_convert((string) $mtime, 10, 36);
    }

    /**
     * Fonts and images a stylesheet points to with url(…) must be public,
     * too, even when no template asks for them.
     */
    protected static function publishReferences(string $source, string $target): void
    {
        preg_match_all('/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i', (string) file_get_contents($source), $matches);

        // Stay inside the stylesheet's own asset folder (assets/themes/{name}/…).
        $root = implode('/', array_slice(explode('/', $target), 0, 3));

        foreach (array_unique($matches[2]) as $reference) {
            $path = strtok($reference, '?#');

            if ($path === false || preg_match('#^([a-z][a-z0-9+.-]*:|/)#i', $path)) {
                continue;
            }

            $resolved = self::normalize(dirname($target).'/'.$path);
            $file = realpath(dirname($source).'/'.$path);

            if ($resolved !== null && $file !== false && str_starts_with($resolved, $root.'/')) {
                self::url($file, $resolved);
            }
        }
    }

    /**
     * "a/b/../c" → "a/c"; null when the path climbs above its start.
     */
    protected static function normalize(string $path): ?string
    {
        $parts = [];

        foreach (explode('/', $path) as $part) {
            if ($part === '..') {
                if (array_pop($parts) === null) {
                    return null;
                }
            } elseif ($part !== '' && $part !== '.') {
                $parts[] = $part;
            }
        }

        return implode('/', $parts);
    }
}
