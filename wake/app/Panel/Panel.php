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
     * --accent-dot is the version that stays visible on white (darkened until
     * it reaches 3:1), --accent-ink the one for dark surfaces (white when the
     * colour itself would not reach 3:1 there).
     */
    public function accentStyles(): HtmlString
    {
        $hex = strtoupper(ltrim((string) config('wake.panel.accent', '#0A0A0A'), '#'));

        // The old neon default counts as "not chosen".
        if (! preg_match('/^[0-9A-F]{6}$/', $hex) || $hex === 'C6FF3D') {
            $hex = '0A0A0A';
        }

        [$r, $g, $b] = array_map('hexdec', str_split($hex, 2));
        $lin = fn (float $c) => ($c /= 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $luminance = fn (float $r, float $g, float $b) => 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
        $contrast = fn (float $a, float $b) => (max($a, $b) + 0.05) / (min($a, $b) + 0.05);

        $own = $luminance($r, $g, $b);
        $ink = $contrast($own, $luminance(10, 10, 10)) >= 3 ? '#'.$hex : '#FFFFFF';

        for ($k = 1.0; $k > 0 && $contrast($luminance($r * $k, $g * $k, $b * $k), 1.0) < 3; $k -= 0.05);
        $dot = sprintf('#%02X%02X%02X', (int) round($r * max($k, 0)), (int) round($g * max($k, 0)), (int) round($b * max($k, 0)));

        return new HtmlString(sprintf('<style>:root{--accent:#%s;--accent-dot:%s;--accent-ink:%s}</style>', $hex, $dot, $ink));
    }
}
