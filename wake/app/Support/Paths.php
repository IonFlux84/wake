<?php

namespace Wake\Support;

/**
 * Where the parts of a Wake site live, one folder per responsibility (see
 * decisions 0008 and 0011):
 *
 *     content/    texts, pages, posts and media (editors)
 *     themes/     looks (designers)
 *     plugins/    extensions (developers)
 *     settings/   settings written by the panel and by people, in Git
 *     data/       data of the site: accounts, statistics, versions; back it up
 *     temp/       caches and logs; may be deleted at any time (Laravel's storage)
 *     public/     the document root
 *     wake/       the application, replaced on update
 */
class Paths
{
    public static function root(string $path = ''): string
    {
        return static::join(dirname(base_path()), $path);
    }

    public static function content(string $path = ''): string
    {
        return static::join(config('wake.paths.content'), $path);
    }

    public static function settings(string $path = ''): string
    {
        return static::join(config('wake.paths.settings'), $path);
    }

    public static function themes(string $path = ''): string
    {
        return static::join(config('wake.paths.themes'), $path);
    }

    public static function plugins(string $path = ''): string
    {
        return static::join(config('wake.paths.plugins'), $path);
    }

    public static function data(string $path = ''): string
    {
        return static::join(config('wake.paths.data'), $path);
    }

    /**
     * The active theme.
     */
    public static function theme(string $path = ''): string
    {
        return static::themes(static::join(config('wake.theme'), $path));
    }

    protected static function join(string $base, string $path): string
    {
        return $path === '' ? rtrim($base, '/') : rtrim($base, '/').'/'.ltrim($path, '/');
    }
}
