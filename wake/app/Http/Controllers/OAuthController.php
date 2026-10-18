<?php

namespace Wake\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Wake\Accounts\Accounts;
use Wake\Accounts\Tokens;
use Wake\OAuth\OAuthStore;

/**
 * OAuth 2.1 for the MCP server, as the MCP specification describes it, so
 * that assistants like claude.ai connectors can connect without copying a
 * token: protected resource and authorization server metadata, dynamic
 * client registration, authorization code with PKCE (S256) and refresh
 * tokens. Clients are public (no secrets). The access token is a normal Wake
 * token, listed and revocable under Assistants.
 */
class OAuthController
{
    /** Access tokens from OAuth are valid this long; clients refresh them. */
    public const ACCESS_DAYS = 30;

    public function __construct(protected OAuthStore $store, protected Tokens $tokens) {}

    /** RFC 9728: which server issues tokens for the MCP endpoint. */
    public function protectedResource(): JsonResponse
    {
        return response()->json([
            'resource' => route('wake.mcp'),
            'authorization_servers' => [url('/')],
            'scopes_supported' => Tokens::SCOPES,
            'bearer_methods_supported' => ['header'],
            'resource_name' => config('app.name'),
        ]);
    }

    /** RFC 8414: the endpoints of the authorization server. */
    public function authorizationServer(): JsonResponse
    {
        return response()->json([
            'issuer' => url('/'),
            'authorization_endpoint' => route('wake.oauth.authorize'),
            'token_endpoint' => route('wake.oauth.token'),
            'registration_endpoint' => route('wake.oauth.register'),
            'scopes_supported' => Tokens::SCOPES,
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
        ]);
    }

    /** RFC 7591: a client registers itself and gets a client id. */
    public function register(Request $request): JsonResponse
    {
        $uris = $request->input('redirect_uris');

        if (! is_array($uris) || $uris === [] || count($uris) > 5) {
            return $this->error('invalid_redirect_uri', 'Send between one and five redirect_uris.');
        }

        foreach ($uris as $uri) {
            if (! is_string($uri) || ! self::allowedRedirect($uri)) {
                return $this->error('invalid_redirect_uri', 'Redirect URIs must use https, or http on localhost.');
            }
        }

        $client = $this->store->registerClient(mb_substr((string) $request->input('client_name', ''), 0, 100), $uris);

        return response()->json([
            'client_id' => $client['client_id'],
            'client_name' => $client['client_name'],
            'redirect_uris' => $client['redirect_uris'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'client_id_issued_at' => now()->getTimestamp(),
        ], 201);
    }

    /** The consent page in the panel. */
    public function authorize(Request $request): View
    {
        [$client, $problem] = $this->checkAuthorizeRequest($request);
        $allowed = Tokens::allowedScopes($request->user());
        $asked = array_filter(explode(' ', (string) $request->query('scope', '')));

        return view('wake::panel.oauth-authorize', [
            'client' => $client,
            'problem' => $problem,
            'host' => $client ? parse_url((string) $request->query('redirect_uri'), PHP_URL_HOST) : null,
            'allowed' => $allowed,
            'checked' => $asked === [] ? $allowed : array_values(array_intersect($allowed, $asked)),
            'params' => $request->only(['response_type', 'client_id', 'redirect_uri', 'code_challenge', 'code_challenge_method', 'state', 'scope', 'resource']),
        ]);
    }

    public function approve(Request $request): RedirectResponse|View
    {
        [$client, $problem] = $this->checkAuthorizeRequest($request);

        if ($client === null) {
            return $this->authorize($request);
        }

        $redirect = (string) $request->input('redirect_uri');
        $state = $request->filled('state') ? ['state' => (string) $request->input('state')] : [];

        if ($request->input('decision') !== 'approve') {
            return redirect()->away($this->withQuery($redirect, ['error' => 'access_denied'] + $state));
        }

        $scopes = array_values(array_intersect(Tokens::allowedScopes($request->user()), (array) $request->input('scopes', [])));

        if ($scopes === []) {
            return redirect()->away($this->withQuery($redirect, ['error' => 'invalid_scope'] + $state));
        }

        $code = $this->store->createCode([
            'client_id' => $client['client_id'],
            'account' => $request->user()->id(),
            'scopes' => $scopes,
            'redirect_uri' => $redirect,
            'challenge' => (string) $request->input('code_challenge'),
        ]);

        return redirect()->away($this->withQuery($redirect, ['code' => $code] + $state));
    }

    public function token(Request $request): JsonResponse
    {
        $client = $this->store->client((string) $request->input('client_id'));

        if ($client === null) {
            return $this->error('invalid_client', 'Unknown client_id.', 401);
        }

        return match ($request->input('grant_type')) {
            'authorization_code' => $this->exchangeCode($request, $client),
            'refresh_token' => $this->refresh($request, $client),
            default => $this->error('unsupported_grant_type', 'Use authorization_code or refresh_token.'),
        };
    }

    /**
     * @param  array<string, mixed>  $client
     */
    protected function exchangeCode(Request $request, array $client): JsonResponse
    {
        $code = $this->store->takeCode((string) $request->input('code'));
        $verifier = (string) $request->input('code_verifier');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        if ($code === null || $code['client_id'] !== $client['client_id'] || $code['redirect_uri'] !== $request->input('redirect_uri')
            || strlen($verifier) < 43 || strlen($verifier) > 128 || ! hash_equals((string) $code['challenge'], $challenge)) {
            return $this->error('invalid_grant', 'The code is invalid, used, expired or does not match.');
        }

        return $this->issue($client, (string) $code['account'], (array) $code['scopes']);
    }

    /**
     * @param  array<string, mixed>  $client
     */
    protected function refresh(Request $request, array $client): JsonResponse
    {
        $old = $this->store->takeRefresh((string) $request->input('refresh_token'));
        $account = $old ? app(Accounts::class)->find((string) $old['account']) : null;

        // A revoked access token ends the connection: its refresh token dies with it.
        if ($old === null || $old['client_id'] !== $client['client_id'] || $account === null || ! $this->tokens->exists($account, (string) $old['token_id'])) {
            return $this->error('invalid_grant', 'The refresh token is invalid, used, expired or was revoked.');
        }

        $this->tokens->revoke($account, (string) $old['token_id']);

        return $this->issue($client, $account->id(), (array) $old['scopes']);
    }

    /**
     * @param  array<string, mixed>  $client
     * @param  list<string>  $scopes
     */
    protected function issue(array $client, string $accountId, array $scopes): JsonResponse
    {
        $account = app(Accounts::class)->find($accountId);
        $scopes = $account ? array_values(array_intersect(Tokens::allowedScopes($account), $scopes)) : [];

        if ($scopes === []) {
            return $this->error('invalid_grant', 'The account no longer exists or may not use these scopes.');
        }

        [$plain, $record] = $this->tokens->create($account, $client['client_name'].' (OAuth)', $scopes, self::ACCESS_DAYS, ['client' => $client['client_id']]);
        $refresh = $this->store->createRefresh(['client_id' => $client['client_id'], 'account' => $account->id(), 'scopes' => $scopes, 'token_id' => $record['id']]);

        return response()->json([
            'access_token' => $plain,
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_DAYS * 86400,
            'refresh_token' => $refresh,
            'scope' => implode(' ', $scopes),
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * @return array{0: array<string, mixed>|null, 1: string|null} the client, or why the request is not valid
     */
    protected function checkAuthorizeRequest(Request $request): array
    {
        $client = $this->store->client((string) $request->input('client_id'));

        $problem = match (true) {
            $client === null => __('This assistant is not registered with this website.'),
            ! in_array($request->input('redirect_uri'), $client['redirect_uris'], true) => __('The return address does not belong to this assistant.'),
            $request->input('response_type') !== 'code' => __('This kind of request is not supported.'),
            $request->input('code_challenge_method') !== 'S256' || ! preg_match('/^[A-Za-z0-9_-]{43}$/', (string) $request->input('code_challenge')) => __('The request is missing its security check (PKCE).'),
            Tokens::allowedScopes($request->user()) === [] => __('Your role may not connect assistants.'),
            default => null,
        };

        return [$problem === null ? $client : null, $problem];
    }

    public static function allowedRedirect(string $uri): bool
    {
        $parts = parse_url($uri);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || isset($parts['fragment'])) {
            return false;
        }

        return $parts['scheme'] === 'https'
            || ($parts['scheme'] === 'http' && in_array($parts['host'], ['localhost', '127.0.0.1', '[::1]'], true));
    }

    /**
     * @param  array<string, string>  $query
     */
    protected function withQuery(string $uri, array $query): string
    {
        return $uri.(str_contains($uri, '?') ? '&' : '?').http_build_query($query);
    }

    protected function error(string $code, string $description, int $status = 400): JsonResponse
    {
        return response()->json(['error' => $code, 'error_description' => $description], $status)->header('Cache-Control', 'no-store');
    }
}
