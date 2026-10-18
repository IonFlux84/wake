<?php

use Wake\Theme\Theme;

if (! function_exists('theme_asset')) {
    /**
     * The URL of a file in the active theme's assets/ folder.
     */
    function theme_asset(string $path): string
    {
        return app(Theme::class)->asset($path);
    }
}
