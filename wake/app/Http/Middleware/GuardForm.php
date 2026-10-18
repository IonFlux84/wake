<?php

namespace Wake\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Wake\Forms\FormGuard;

/**
 * Route middleware "wake.form:{name}" for public forms, see FormGuard.
 */
class GuardForm
{
    public function handle(Request $request, Closure $next, string $form): Response
    {
        $refusal = FormGuard::refusal($request, $form);

        if ($refusal !== null) {
            abort($refusal === 'too-many' ? 429 : 422, $refusal === 'too-many'
                ? __('Too many attempts. Please try again in a few minutes.')
                : __('The form could not be sent. Please reload the page and try again.'));
        }

        return $next($request);
    }
}
