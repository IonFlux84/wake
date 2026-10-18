<?php

use Laravel\Mcp\Facades\Mcp;
use Wake\Http\Middleware\AuthenticateToken;
use Wake\Mcp\WakeServer;

// The MCP server: one POST endpoint (Streamable HTTP without streaming, which
// shared hosting can serve) and a local stdio server for `wake:mcp`.

Mcp::web('wake/mcp', WakeServer::class)
    ->middleware([AuthenticateToken::class, 'throttle:60,1'])
    ->name('wake.mcp');

Mcp::local('wake', WakeServer::class);
