<?php

namespace Wake\Support;

/**
 * Installed by uploading a ZIP, there is no shell to run key:generate. On the
 * very first request Wake writes .env from .env.example itself: with a fresh
 * application key, and with the site's address taken from that request.
 * Runs in bootstrap/app.php, before Laravel starts.
 */
class EnvFile
{
    /**
     * @param  array<string, mixed>  $server  $_SERVER of the first request
     * @return bool whether .env was written
     */
    public static function create(string $root, array $server): bool
    {
        if (is_file($root.'/.env')) {
            self::fillAddress($root, $server);

            return false;
        }

        if (! is_file($root.'/.env.example') || ! is_writable($root)) {
            return false;
        }

        $env = (string) file_get_contents($root.'/.env.example');
        $env = (string) preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=base64:'.base64_encode(random_bytes(32)), $env);

        if ($url = self::address($server)) {
            $env = (string) preg_replace('/^APP_URL=.*$/m', 'APP_URL='.$url, $env);
        }

        @file_put_contents($root.'/.env', $env, LOCK_EX);
        @chmod($root.'/.env', 0640);

        return is_file($root.'/.env');
    }

    /**
     * When a command ran first (as on Deploy Now, where commands run after
     * every deployment), .env was written without an address and still has
     * the one from .env.example. The first request then fills in its own.
     *
     * @param  array<string, mixed>  $server
     */
    public static function fillAddress(string $root, array $server): void
    {
        $url = self::address($server);
        $example = is_file($root.'/.env.example') && preg_match('/^APP_URL=(.*)$/m', (string) file_get_contents($root.'/.env.example'), $m) ? trim($m[1]) : null;

        if ($url === null || $example === null || ! is_writable($root.'/.env')) {
            return;
        }

        $env = (string) file_get_contents($root.'/.env');

        if (preg_match('/^APP_URL=(.*)$/m', $env, $current) && trim($current[1]) === $example && $url !== $example) {
            @file_put_contents($root.'/.env', (string) preg_replace('/^APP_URL=.*$/m', 'APP_URL='.$url, $env), LOCK_EX);
        }
    }

    /**
     * "https://example.org" from the request, or null for the command line
     * and host names that are not plain.
     *
     * @param  array<string, mixed>  $server
     */
    public static function address(array $server): ?string
    {
        $host = strtolower((string) ($server['HTTP_HOST'] ?? ''));

        if (! preg_match('/^([a-z0-9-]+\.)*[a-z0-9-]+(:\d{1,5})?$|^\[[0-9a-f:]+\](:\d{1,5})?$/', $host)) {
            return null;
        }

        $https = ($server['HTTPS'] ?? '') === 'on' || ($server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            || (string) ($server['SERVER_PORT'] ?? '') === '443';

        return ($https ? 'https' : 'http').'://'.$host;
    }
}
