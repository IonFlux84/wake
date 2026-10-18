<?php

namespace Wake\Support;

/**
 * Where the parts of a Wake site live. See decision 0008.
 */
class Paths
{
    public static function root(string $path = ''): string
    {
        return static::join(dirname(base_path()), $path);
    }

    public static function site(string $path = ''): string
    {
        return static::join(config('wake.paths.site'), $path);
    }

    public static function content(string $path = ''): string
    {
        return static::join(config('wake.paths.content'), $path);
    }

    public static function theme(string $path = ''): string
    {
        return static::site(static::join('themes/'.config('wake.theme'), $path));
    }

    protected static function join(string $base, string $path): string
    {
        return $path === '' ? rtrim($base, '/') : rtrim($base, '/').'/'.ltrim($path, '/');
    }
}
