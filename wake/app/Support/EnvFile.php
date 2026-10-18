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
        if (is_file($root.'/.env') || ! is_file($root.'/.env.example') || ! is_writable($root)) {
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
