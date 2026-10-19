<?php

namespace RedirectScout;

/**
 * Turns links of the old website into paths as Wake sees them: no host, no
 * slashes at either end, no query or fragment, decoded.
 */
final class Address
{
    /** Extensions of addresses that are pages, not files. */
    public const PAGES = ['', 'html', 'htm', 'php', 'asp', 'aspx', 'shtml', 'jsp', 'cfm'];

    /**
     * The path of a URL on the old website, or null for other websites,
     * mail links and the like.
     */
    public static function path(string $url, string $site): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));
        $parts = parse_url($url);

        if ($parts === false || ! in_array(strtolower($parts['scheme'] ?? 'http'), ['http', 'https'], true)) {
            return null;
        }

        if (isset($parts['host']) && ! self::sameHost($parts['host'], (string) parse_url($site, PHP_URL_HOST))) {
            return null;
        }

        $path = rawurldecode((string) ($parts['path'] ?? ''));

        if (str_contains($path, '..') || preg_match('/[\x00-\x1f]/', $path)) {
            return null;
        }

        return trim(preg_replace('#/+#', '/', $path), '/');
    }

    /**
     * An absolute URL for a link found on a page.
     */
    public static function absolute(string $link, string $base): ?string
    {
        $link = trim(html_entity_decode($link, ENT_QUOTES | ENT_HTML5));

        if ($link === '' || str_starts_with($link, '#') || preg_match('/^(mailto|tel|javascript|data):/i', $link)) {
            return null;
        }

        if (preg_match('#^https?://#i', $link)) {
            return $link;
        }

        $root = parse_url($base, PHP_URL_SCHEME).'://'.parse_url($base, PHP_URL_HOST).(($port = parse_url($base, PHP_URL_PORT)) ? ':'.$port : '');

        if (str_starts_with($link, '//')) {
            return parse_url($base, PHP_URL_SCHEME).':'.$link;
        }

        if (str_starts_with($link, '/')) {
            return $root.$link;
        }

        $dir = preg_replace('#/[^/]*$#', '/', (string) (parse_url($base, PHP_URL_PATH) ?: '/'));
        $path = $dir.preg_replace('/[?#].*$/', '', $link);

        // Resolve ./ and ../
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '.') {
                $segments[] = $segment;
            }
        }

        return $root.'/'.ltrim(implode('/', $segments), '/');
    }

    /**
     * The URL of a path on the old website.
     */
    public static function url(string $path, string $site): string
    {
        return rtrim($site, '/').'/'.implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /**
     * "page" or "file", by the extension of the last segment.
     */
    public static function type(string $path): string
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::PAGES, true) ? 'page' : 'file';
    }

    /**
     * www.example.org and example.org are the same website.
     */
    public static function sameHost(string $a, string $b): bool
    {
        $strip = fn (string $host) => preg_replace('/^www\./', '', strtolower($host));

        return $strip($a) === $strip($b);
    }
}
