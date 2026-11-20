<?php

namespace Wake\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A few response headers that make the website and the panel harder to
 * misuse: no MIME sniffing, no framing by other sites, a shorter referrer
 * for other sites and, over HTTPS, Strict-Transport-Security. Headers a
 * response already carries are left alone. Cached pages get the same
 * headers from public/index.php (see PageCache::writeRules()).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (static::headers(static::isSecure($request->server->all())) as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /**
     * The headers to send (settings security.headers and security.hsts_max_age).
     *
     * @return array<string, string>
     */
    public static function headers(bool $secure): array
    {
        if (! config('wake.security.headers', true)) {
            return [];
        }

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];

        $maxAge = (int) config('wake.security.hsts_max_age', 31536000);

        if ($secure && $maxAge > 0) {
            $headers['Strict-Transport-Security'] = 'max-age='.$maxAge;
        }

        return $headers;
    }

    /**
     * HTTPS, also behind a proxy that says so. Same rule as PageCache::hostKey().
     *
     * @param  array<string, mixed>  $server
     */
    public static function isSecure(array $server): bool
    {
        return (! empty($server['HTTPS']) && $server['HTTPS'] !== 'off') || ($server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}
