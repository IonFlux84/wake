<?php

namespace Wake\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The panel speaks the language each team member chose for themselves, or
 * the panel language of the site.
 */
class PanelLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // The account's own choice, else the panel language of the site
        // (German unless changed in the settings).
        $language = $request->user()?->language() ?? config('wake.panel.language', 'de');

        app()->setLocale(in_array($language, ['en', 'de'], true) ? $language : 'de');

        return $next($request);
    }
}
