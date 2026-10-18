<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// The Laravel application lives in wake/. Everything a site owner touches
// (site/, content/, storage/, public/ and .env) sits one level above it,
// so an update only ever replaces this folder.
$root = dirname(__DIR__, 2);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('wake/api/*') || $request->expectsJson(),
        );
    })
    ->create()
    ->useEnvironmentPath($root)
    ->usePublicPath($root.'/public')
    ->useStoragePath($root.'/storage');
