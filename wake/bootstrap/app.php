<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Wake\Http\Middleware\CachePages;
use Wake\Http\Middleware\ClosedSite;
use Wake\Http\Middleware\PanelLocale;
use Wake\Http\Middleware\RequireTwoFactor;
use Wake\Plugins\Plugins;

// The Laravel application lives in wake/. Everything a site owner touches
// (content/, themes/, plugins/, settings/, data/, temp/, public/ and .env)
// sits one level above it,
// so an update only ever replaces this folder.
$root = dirname(__DIR__, 2);

// Installed by uploading a ZIP, there is no shell to run key:generate. On the
// very first request Wake writes .env with a fresh application key itself.
if (! is_file($root.'/.env') && is_file($root.'/.env.example') && is_writable($root)) {
    $env = (string) file_get_contents($root.'/.env.example');
    $env = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=base64:'.base64_encode(random_bytes(32)), $env);
    @file_put_contents($root.'/.env', $env, LOCK_EX);
    @chmod($root.'/.env', 0640);
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // The website comes last, because it answers every remaining address.
        then: function () {
            Route::group([], __DIR__.'/../routes/mcp.php');
            app(Plugins::class)->registerRoutes();
            Route::group([], __DIR__.'/../routes/site.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'wake.cache' => CachePages::class,
            'wake.closed' => ClosedSite::class,
            'wake.two-factor' => RequireTwoFactor::class,
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
    ->useStoragePath($root.'/temp');
