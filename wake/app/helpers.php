<?php

use Wake\Theme\Theme;

if (! function_exists('theme_option')) {
    /**
     * An option of the active theme, as set under Themes in the panel.
     */
    function theme_option(string $key, mixed $default = null): mixed
    {
        return app(Theme::class)->option($key, $default);
    }
}

if (! function_exists('theme_asset')) {
    /**
     * The URL of a file in the active theme's assets/ folder.
     */
    function theme_asset(string $path): string
    {
        return app(Theme::class)->asset($path);
    }
}
