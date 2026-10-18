<?php

namespace Wake\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Wake\Cache\PageCache;

/**
 * Stores successful website responses in the page cache.
 */
class CachePages
{
    public function __construct(protected PageCache $cache) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->cacheable($request, $response)) {
            $this->cache->put(rawurldecode($request->getPathInfo()), (string) $response->getContent());
            $response->headers->set('X-Wake-Cache', 'miss');
        }

        return $response;
    }

    protected function cacheable(Request $request, Response $response): bool
    {
        return $this->cache->enabled()
            && $request->isMethod('GET')
            && $request->getQueryString() === null
            && ! $request->cookies->has(config('session.cookie'))
            && $response->getStatusCode() === 200
            && str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')
            && ! $response->headers->has('Set-Cookie')
            && ! $response->headers->hasCacheControlDirective('no-store');
    }
}
