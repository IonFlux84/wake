<?php

namespace Wake\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Wake\Accounts\Account;
use Wake\Accounts\TwoFactor;

/**
 * With "security.require_two_factor", people without two-factor sign-in can
 * do nothing in the panel but set it up (or sign out).
 */
class RequireTwoFactor
{
    /** Routes that stay open so people can set it up. */
    protected const OPEN = ['wake.two-factor', 'wake.logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (config('wake.security.require_two_factor') && $user instanceof Account && ! TwoFactor::isEnabled($user)
            && ! in_array($request->route()?->getName(), self::OPEN, true)) {
            return redirect()->route('wake.two-factor');
        }

        return $next($request);
    }
}
