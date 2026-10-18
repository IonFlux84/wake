<?php

namespace Wake\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Wake\Accounts\Tokens;
use Wake\Mcp\CurrentToken;

/**
 * Signs in the team member behind a Bearer token (MCP server and API).
 */
class AuthenticateToken
{
    public function __construct(protected Tokens $tokens, protected CurrentToken $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $found = $this->tokens->find((string) $request->bearerToken());

        if ($found === null) {
            // laravel/mcp adds the WWW-Authenticate header that tells OAuth
            // clients where to sign in (RFC 9728); the API gets the same one.
            return response()->json(['error' => 'A valid token is required. Create one in the panel under Assistants, or connect with OAuth.'], 401)
                ->header('WWW-Authenticate', 'Bearer resource_metadata="'.url('.well-known/oauth-protected-resource/wake/mcp').'"');
        }

        [$account, $record] = $found;

        Auth::setUser($account);
        $this->current->set($record);
        $this->tokens->touch($account, $record);

        return $next($request);
    }
}
