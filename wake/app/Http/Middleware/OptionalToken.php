<?php

namespace Wake\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Wake\Accounts\Tokens;
use Wake\Mcp\CurrentToken;

/**
 * For the content API: published content is public, a token unlocks more.
 * A token that is given but invalid is rejected.
 */
class OptionalToken
{
    public function __construct(protected Tokens $tokens, protected CurrentToken $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() !== null) {
            $found = $this->tokens->find($request->bearerToken());

            if ($found === null) {
                return response()->json(['error' => 'Invalid token.'], 401);
            }

            Auth::setUser($found[0]);
            $this->current->set($found[1]);
        }

        $response = $next($request);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type');

        return $response;
    }
}
