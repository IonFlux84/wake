<?php

namespace Wake\Panel;

use Illuminate\Support\HtmlString;
use Wake\Support\PublicAssets;

/**
 * Helpers for the panel's look: published assets and the accent colour.
 */
class Panel
{
    /**
     * The URL of a file in wake/resources/dist. The whole folder is
     * published together, so relative URLs inside CSS (fonts) keep working.
     */
    public function asset(string $path): string
    {
        $dist = resource_path('dist');

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dist, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative = substr($file->getPathname(), strlen($dist) + 1);

            if ($relative !== $path) {
                PublicAssets::url($file->getPathname(), 'assets/wake/'.$relative);
            }
        }

        return PublicAssets::url($dist.'/'.$path, 'assets/wake/'.$path);
    }

    /**
     * CSS variables for the configured accent colour. Text on the accent is
     * black or white, whichever reads better.
     */
    public function accentStyles(): HtmlString
    {
        $hex = ltrim((string) config('wake.panel.accent', '#C6FF3D'), '#');

        if (! preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            $hex = 'C6FF3D';
        }

        [$r, $g, $b] = array_map('hexdec', str_split($hex, 2));
        $lin = fn (int $c) => ($c /= 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $luminance = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);

        return new HtmlString(sprintf(
            '<style>:root{--accent:#%s;--on-accent:%s;--accent-soft:rgba(%d,%d,%d,.22);--glow:rgba(%d,%d,%d,.7)}</style>',
            $hex, $luminance > 0.36 ? '#0A0A0A' : '#FFFFFF', $r, $g, $b, $r, $g, $b,
        ));
    }
}
