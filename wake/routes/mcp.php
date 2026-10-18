<?php

use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Wake\Http\Controllers\Api\ContentApiController;
use Wake\Http\Controllers\OAuthController;
use Wake\Http\Middleware\AuthenticateToken;
use Wake\Http\Middleware\OptionalToken;
use Wake\Mcp\WakeServer;

// The MCP server: one POST endpoint (Streamable HTTP without streaming, which
// shared hosting can serve) and a local stdio server for `wake:mcp`.

Mcp::web('wake/mcp', WakeServer::class)
    ->middleware([AuthenticateToken::class, 'throttle:60,1'])
    ->name('wake.mcp');

Mcp::local('wake', WakeServer::class);

// OAuth for the MCP server (see OAuthController). The consent page is in the
// panel (routes/web.php); these endpoints run without a session.
Route::get('.well-known/oauth-protected-resource', [OAuthController::class, 'protectedResource']);
// laravel/mcp points 401 answers at this route by its name.
Route::get('.well-known/oauth-protected-resource/{path}', [OAuthController::class, 'protectedResource'])
    ->where('path', 'wake/mcp')->name('mcp.oauth.protected-resource.nested');
Route::get('.well-known/oauth-authorization-server', [OAuthController::class, 'authorizationServer']);
Route::post('wake/oauth/register', [OAuthController::class, 'register'])->middleware('throttle:10,1')->name('wake.oauth.register');
Route::post('wake/oauth/token', [OAuthController::class, 'token'])->middleware('throttle:30,1')->name('wake.oauth.token');

// Read-only JSON API for headless use. Published content is public.
Route::prefix('wake/api/v1')->middleware([OptionalToken::class, 'throttle:120,1'])->name('wake.api.')->group(function () {
    Route::get('site', [ContentApiController::class, 'site'])->name('site');
    Route::get('entries', [ContentApiController::class, 'entries'])->name('entries');
    Route::get('entry', [ContentApiController::class, 'entry'])->name('entry');
    Route::get('stats', [ContentApiController::class, 'stats'])->name('stats');
});
