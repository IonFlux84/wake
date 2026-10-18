<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Wake\Http\Middleware\CachePages;
use Wake\Http\Middleware\PanelLocale;

// The Laravel application lives in wake/. Everything a site owner touches
// (site/, content/, storage/, public/ and .env) sits one level above it,
// so an update only ever replaces this folder.
$root = dirname(__DIR__, 2);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // The website comes last, because it answers every remaining address.
        then: fn () => Route::group([], __DIR__.'/../routes/site.php'),
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'wake.cache' => CachePages::class,
        ]);
        $middleware->appendToGroup('wake.panel', [PanelLocale::class]);
        $middleware->redirectGuestsTo(fn () => route('wake.login'));
        $middleware->redirectUsersTo(fn () => route('wake.dashboard'));
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
