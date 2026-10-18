<?php

namespace Wake\Consent;

/**
 * External services Wake recognises in embeds. Each one is blocked until the
 * visitor agrees, and appears in the list of services for the privacy policy.
 */
class Services
{
    /**
     * @return array<string, array{name: string, company: string, privacy: string, hosts: list<string>}>
     */
    public static function all(): array
    {
        return [
            'youtube' => ['name' => 'YouTube', 'company' => 'Google Ireland Limited', 'privacy' => 'https://policies.google.com/privacy', 'hosts' => ['youtube.com', 'youtu.be', 'youtube-nocookie.com']],
            'vimeo' => ['name' => 'Vimeo', 'company' => 'Vimeo.com, Inc.', 'privacy' => 'https://vimeo.com/privacy', 'hosts' => ['vimeo.com']],
            'maps' => ['name' => 'Google Maps', 'company' => 'Google Ireland Limited', 'privacy' => 'https://policies.google.com/privacy', 'hosts' => ['google.com/maps', 'maps.google.com', 'goo.gl/maps', 'maps.app.goo.gl']],
            'openstreetmap' => ['name' => 'OpenStreetMap', 'company' => 'OpenStreetMap Foundation', 'privacy' => 'https://osmfoundation.org/wiki/Privacy_Policy', 'hosts' => ['openstreetmap.org']],
            'spotify' => ['name' => 'Spotify', 'company' => 'Spotify AB', 'privacy' => 'https://www.spotify.com/legal/privacy-policy/', 'hosts' => ['open.spotify.com']],
            'soundcloud' => ['name' => 'SoundCloud', 'company' => 'SoundCloud Global Limited & Co. KG', 'privacy' => 'https://soundcloud.com/pages/privacy', 'hosts' => ['soundcloud.com']],
            'instagram' => ['name' => 'Instagram', 'company' => 'Meta Platforms Ireland Limited', 'privacy' => 'https://privacycenter.instagram.com/policy', 'hosts' => ['instagram.com']],
            'x' => ['name' => 'X (Twitter)', 'company' => 'X Corp.', 'privacy' => 'https://x.com/privacy', 'hosts' => ['twitter.com', 'x.com']],
            'mastodon' => ['name' => 'Mastodon', 'company' => '', 'privacy' => '', 'hosts' => []],
        ];
    }

    /**
     * Find the service behind a URL. Unknown hosts count as "other".
     */
    public static function detect(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $hostAndPath = preg_replace('/^www\./', '', $host).(string) parse_url($url, PHP_URL_PATH);

        foreach (static::all() as $key => $service) {
            foreach ($service['hosts'] as $pattern) {
                if (str_starts_with($hostAndPath, $pattern) || str_ends_with(preg_replace('/^www\./', '', $host), '.'.$pattern)) {
                    return $key;
                }
            }
        }

        return 'other';
    }

    /**
     * The address to put into the iframe, using privacy-friendlier variants
     * where a service offers one.
     */
    public static function embedUrl(string $url): string
    {
        $service = static::detect($url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $path = (string) parse_url($url, PHP_URL_PATH);

        return match ($service) {
            'youtube' => 'https://www.youtube-nocookie.com/embed/'.rawurlencode(
                $query['v'] ?? (str_contains($url, 'youtu.be/') ? trim($path, '/') : basename($path))
            ),
            'vimeo' => 'https://player.vimeo.com/video/'.rawurlencode(basename($path)).'?dnt=1',
            'spotify' => 'https://open.spotify.com/embed'.preg_replace('#^/embed#', '', $path),
            default => $url,
        };
    }

    /**
     * @return array{name: string, company: string, privacy: string, hosts: list<string>}
     */
    public static function get(string $key, string $url = ''): array
    {
        return static::all()[$key] ?? [
            'name' => strtolower((string) parse_url($url, PHP_URL_HOST)) ?: 'External content',
            'company' => '', 'privacy' => '', 'hosts' => [],
        ];
    }
}
