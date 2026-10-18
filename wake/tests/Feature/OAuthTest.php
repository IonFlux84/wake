<?php

use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Accounts\Tokens;

beforeEach(function () {
    $this->owner = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);
    $this->verifier = str_repeat('v3rifier-', 6);
    $this->challenge = rtrim(strtr(base64_encode(hash('sha256', $this->verifier, true)), '+/', '-_'), '=');
});

function registerClient(): array
{
    return test()->postJson('/wake/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback']])
        ->assertCreated()->json();
}

function authorizeQuery(string $clientId, string $challenge, array $extra = []): array
{
    return [
        'response_type' => 'code', 'client_id' => $clientId, 'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'code_challenge' => $challenge, 'code_challenge_method' => 'S256', 'state' => 'xyz', 'scope' => 'stats:read content:read',
    ] + $extra;
}

it('tells clients where to sign in', function () {
    $this->postJson('/wake/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="'.url('.well-known/oauth-protected-resource/wake/mcp').'"');

    $this->getJson('/.well-known/oauth-protected-resource')->assertOk()->assertJson(['resource' => route('wake.mcp'), 'authorization_servers' => [url('/')]]);
    $this->getJson('/.well-known/oauth-protected-resource/wake/mcp')->assertOk();
    $this->getJson('/.well-known/oauth-authorization-server')->assertOk()
        ->assertJson(['code_challenge_methods_supported' => ['S256'], 'token_endpoint' => route('wake.oauth.token'), 'registration_endpoint' => route('wake.oauth.register')]);
});

it('refuses redirect addresses that are not https or localhost', function () {
    $this->postJson('/wake/oauth/register', ['redirect_uris' => ['http://evil.example/cb']])->assertStatus(400)->assertJson(['error' => 'invalid_redirect_uri']);
    $this->postJson('/wake/oauth/register', ['redirect_uris' => []])->assertStatus(400);
    $this->postJson('/wake/oauth/register', ['redirect_uris' => ['http://localhost:3334/callback']])->assertCreated();
});

it('connects an assistant: consent, code with PKCE, token, MCP, refresh, revoke', function () {
    $client = registerClient();
    $query = authorizeQuery($client['client_id'], $this->challenge);

    $this->get('/wake/oauth/authorize?'.http_build_query($query))->assertRedirect(route('wake.login'));

    $this->actingAs($this->owner)->get('/wake/oauth/authorize?'.http_build_query($query))
        ->assertOk()->assertSee('Connect Claude?')->assertSee('claude.ai');

    $redirect = $this->actingAs($this->owner)->post('/wake/oauth/authorize', $query + ['decision' => 'approve', 'scopes' => ['stats:read', 'content:read']])
        ->assertRedirect()->headers->get('Location');

    expect($redirect)->toStartWith('https://claude.ai/api/mcp/auth_callback?code=')->toContain('state=xyz');
    parse_str((string) parse_url($redirect, PHP_URL_QUERY), $params);

    $this->post('/wake/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $client['client_id'], 'code' => $params['code'],
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback', 'code_verifier' => 'wrong-'.$this->verifier])->assertStatus(400)->assertJson(['error' => 'invalid_grant']);

    // The failed attempt used the code up: codes work once.
    $redirect = $this->actingAs($this->owner)->post('/wake/oauth/authorize', $query + ['decision' => 'approve', 'scopes' => ['stats:read']])->headers->get('Location');
    parse_str((string) parse_url($redirect, PHP_URL_QUERY), $params);
    auth()->logout();

    $tokens = $this->post('/wake/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $client['client_id'], 'code' => $params['code'],
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback', 'code_verifier' => $this->verifier])
        ->assertOk()->assertJson(['token_type' => 'Bearer', 'scope' => 'stats:read'])->json();

    $this->withHeaders(['Authorization' => 'Bearer '.$tokens['access_token'], 'Accept' => 'application/json, text/event-stream'])
        ->postJson('/wake/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertOk()->assertSee('stats_overview');

    expect(app(Tokens::class)->of($this->owner)->first())->toMatchArray(['name' => 'Claude (OAuth)', 'client' => $client['client_id']]);

    $refreshed = $this->post('/wake/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $client['client_id'], 'refresh_token' => $tokens['refresh_token']])
        ->assertOk()->json();

    expect(app(Tokens::class)->find($tokens['access_token']))->toBeNull()
        ->and(app(Tokens::class)->find($refreshed['access_token']))->not->toBeNull();

    $this->post('/wake/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $client['client_id'], 'refresh_token' => $tokens['refresh_token']])->assertStatus(400);

    // Revoking in the panel ends the connection, refresh included.
    app(Tokens::class)->revoke($this->owner, app(Tokens::class)->find($refreshed['access_token'])[1]['id']);

    $this->post('/wake/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $client['client_id'], 'refresh_token' => $refreshed['refresh_token']])->assertStatus(400);
});

it('sends people back when they cancel, and refuses broken requests', function () {
    $client = registerClient();
    $query = authorizeQuery($client['client_id'], $this->challenge);

    $this->actingAs($this->owner)->post('/wake/oauth/authorize', $query + ['decision' => 'deny'])
        ->assertRedirect('https://claude.ai/api/mcp/auth_callback?error=access_denied&state=xyz');

    $this->actingAs($this->owner)->get('/wake/oauth/authorize?'.http_build_query(['redirect_uri' => 'https://evil.example/cb'] + $query))
        ->assertOk()->assertSee('The return address does not belong to this assistant.');

    $this->actingAs($this->owner)->get('/wake/oauth/authorize?'.http_build_query(['code_challenge_method' => 'plain'] + $query))
        ->assertOk()->assertSee('PKCE');
});

it('gives assistants only the rights of the person who connects them', function () {
    $author = app(Accounts::class)->create(['name' => 'Ali', 'email' => 'ali@example.com', 'password' => 'secret secret', 'role' => Role::Author]);
    $client = registerClient();
    $query = authorizeQuery($client['client_id'], $this->challenge, ['scope' => 'stats:read content:publish']);

    $redirect = $this->actingAs($author)->post('/wake/oauth/authorize', $query + ['decision' => 'approve', 'scopes' => ['content:read', 'content:publish', 'stats:read']])->headers->get('Location');
    parse_str((string) parse_url($redirect, PHP_URL_QUERY), $params);

    $scope = $this->post('/wake/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $client['client_id'], 'code' => $params['code'],
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback', 'code_verifier' => $this->verifier])->json('scope');

    expect(explode(' ', $scope))->toEqualCanonicalizing(array_values(array_intersect(Tokens::allowedScopes($author), ['content:read', 'content:publish', 'stats:read'])));
});
