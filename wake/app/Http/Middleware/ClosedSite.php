<?php

namespace Wake\Http\Middleware;

use Closure;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpFoundation\Response;
use Wake\Content\Site;

/**
 * A closed site is only visible to signed-in team members. Everyone else
 * lands on the sign-in page, and search engines are told not to index
 * anything. The published imprint and privacy policy stay open to everyone:
 * the law wants them reachable, and the sign-in page links them. The website normally runs without sessions; only while the site
 * is closed, the session is started to know who is signed in.
 */
class ClosedSite
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('wake.closed')) {
            return $next($request);
        }

        $response = (new Pipeline(app()))
            ->send($request)
            ->through([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class])
            ->then(function (Request $request) use ($next) {
                if (! $request->user() && ! $this->isLegalPage($request)) {
                    return redirect()->guest(route('wake.login'));
                }

                return $next($request);
            });

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    protected function isLegalPage(Request $request): bool
    {
        $path = trim(rawurldecode($request->getBaseUrl().$request->getPathInfo()), '/');

        return in_array($request->getMethod(), ['GET', 'HEAD'], true) && in_array($path, app(Site::class)->legalPaths(), true);
    }
}
