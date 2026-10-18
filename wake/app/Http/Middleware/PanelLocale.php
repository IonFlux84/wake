<?php

namespace Wake\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The panel speaks the language each team member chose for themselves.
 */
class PanelLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $language = $request->user()?->language() ?? $request->getPreferredLanguage(['en', 'de']) ?? 'en';

        app()->setLocale(in_array($language, ['en', 'de'], true) ? $language : 'en');

        return $next($request);
    }
}
