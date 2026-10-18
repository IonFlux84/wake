<?php

use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Wake\Http\Controllers\Api\ContentApiController;
use Wake\Http\Middleware\AuthenticateToken;
use Wake\Http\Middleware\OptionalToken;
use Wake\Mcp\WakeServer;

// The MCP server: one POST endpoint (Streamable HTTP without streaming, which
// shared hosting can serve) and a local stdio server for `wake:mcp`.

Mcp::web('wake/mcp', WakeServer::class)
    ->middleware([AuthenticateToken::class, 'throttle:60,1'])
    ->name('wake.mcp');

Mcp::local('wake', WakeServer::class);

// Read-only JSON API for headless use. Published content is public.
Route::prefix('wake/api/v1')->middleware([OptionalToken::class, 'throttle:120,1'])->name('wake.api.')->group(function () {
    Route::get('site', [ContentApiController::class, 'site'])->name('site');
    Route::get('entries', [ContentApiController::class, 'entries'])->name('entries');
    Route::get('entry', [ContentApiController::class, 'entry'])->name('entry');
    Route::get('stats', [ContentApiController::class, 'stats'])->name('stats');
});
