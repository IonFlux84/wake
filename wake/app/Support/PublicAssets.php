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
        }

        return url($target).'?v='.base_convert((string) $mtime, 10, 36);
    }
}
