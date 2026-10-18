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
     * CSS variables for the highlight colour. The panel is greyscale; a chosen
     * colour only marks small live signals (published dot, main chart line).
     * --accent-ink is the version that stays visible on dark surfaces.
     */
    public function accentStyles(): HtmlString
    {
        $hex = strtoupper(ltrim((string) config('wake.panel.accent', '#0A0A0A'), '#'));

        // The old neon default counts as "not chosen".
        if (! preg_match('/^[0-9A-F]{6}$/', $hex) || $hex === 'C6FF3D') {
            $hex = '0A0A0A';
        }

        [$r, $g, $b] = array_map('hexdec', str_split($hex, 2));
        $lin = fn (int $c) => ($c /= 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $luminance = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);

        return new HtmlString(sprintf(
            '<style>:root{--accent:#%s;--on-accent:%s;--accent-ink:%s}</style>',
            $hex, $luminance > 0.36 ? '#0A0A0A' : '#FFFFFF', $luminance < 0.05 ? '#FFFFFF' : '#'.$hex,
        ));
    }
}
