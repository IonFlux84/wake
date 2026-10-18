<?php

namespace Wake\Theme;

use Illuminate\Support\Facades\View;
use Wake\Support\Paths;
use Wake\Support\PublicAssets;

/**
 * The active theme in site/themes/{name}:
 *
 *     templates/   Blade views, available as theme::name
 *     assets/      CSS, JavaScript, fonts and images
 *     lang/        translations of the theme's own texts (de.json, …)
 *     theme.yml    name, version, author (optional)
 *
 * Assets are copied to public/assets/themes/{name}/ when they change, because
 * the site folder itself is not public and symlinks are not reliable on
 * shared hosting.
 */
class Theme
{
    public function name(): string
    {
        return (string) config('wake.theme');
    }

    public function path(string $path = ''): string
    {
        return Paths::theme($path);
    }

    public function register(): void
    {
        View::addNamespace('theme', $this->path('templates'));

        if (is_dir($this->path('lang'))) {
            app('translator')->addJsonPath($this->path('lang'));
        }
    }

    /**
     * The first of the given templates that exists, as a view name.
     */
    public function template(string ...$names): ?string
    {
        foreach ($names as $name) {
            if ($name !== '' && View::exists("theme::{$name}")) {
                return "theme::{$name}";
            }
        }

        return null;
    }

    /**
     * The public URL of a theme asset, with a version for cache busting.
     */
    public function asset(string $path): string
    {
        $path = ltrim($path, '/');

        return PublicAssets::url($this->path('assets/'.$path), 'assets/themes/'.$this->name().'/'.$path);
    }
}
