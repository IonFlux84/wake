<?php

use Illuminate\Support\Facades\Route;
use Wake\Backup\Backups;
use Wake\Http\Controllers\OAuthController;
use Wake\Http\Controllers\Panel\ContentController;
use Wake\Http\Controllers\Panel\DashboardController;
use Wake\Http\Controllers\Panel\LoginController;
use Wake\Http\Controllers\Panel\PasswordController;
use Wake\Http\Controllers\Panel\PreviewController;
use Wake\Http\Controllers\Panel\SetupController;

// The panel lives under /wake. These routes use sessions and CSRF protection.

Route::prefix('wake')->name('wake.')->middleware('wake.panel')->group(function () {
    Route::get('setup', [SetupController::class, 'show'])->name('setup');
    Route::post('setup', [SetupController::class, 'store'])->middleware('throttle:10,1');

    Route::get('preview/shared', [PreviewController::class, 'shared'])->middleware('signed')->name('preview.shared');

    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'store']);
        Route::get('login/code', [LoginController::class, 'code'])->name('login.code');
        Route::post('login/code', [LoginController::class, 'verify'])->middleware('throttle:20,1');
        Route::get('password', [PasswordController::class, 'request'])->name('password.request');
        Route::post('password', [PasswordController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
        Route::get('password/{token}', [PasswordController::class, 'reset'])->name('password.reset');
        Route::post('password/reset', [PasswordController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
        Route::get('invitation/{id}/{token}', [PasswordController::class, 'invitation'])->name('invitation');
        Route::post('invitation/{id}/{token}', [PasswordController::class, 'accept'])->middleware('throttle:10,1');
    });

    Route::middleware(['auth', 'wake.two-factor'])->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
        Route::view('two-factor', 'wake::panel.two-factor')->name('two-factor');
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::view('stats', 'wake::panel.stats')->middleware('can:stats.view')->name('stats');
        Route::view('stats/journey', 'wake::panel.journey')->middleware('can:stats.view')->name('stats.journey');
        Route::view('consent', 'wake::panel.consent')->middleware('can:consent.manage')->name('consent');
        Route::view('assistants', 'wake::panel.assistants')->middleware('can:assistants.use')->name('assistants');
        Route::get('oauth/authorize', [OAuthController::class, 'authorize'])->middleware('can:assistants.use')->name('oauth.authorize');
        Route::post('oauth/authorize', [OAuthController::class, 'approve'])->middleware(['can:assistants.use', 'throttle:20,1']);
        Route::view('team', 'wake::panel.team')->middleware('can:team.manage')->name('team');
        Route::view('settings', 'wake::panel.settings')->name('settings');
        Route::view('media', 'wake::panel.media')->name('media.index');
        Route::view('categories', 'wake::panel.categories')->name('categories');
        Route::view('backups', 'wake::panel.backups')->middleware('can:settings.manage')->name('backups');
        Route::get('backups/{name}', function (string $name, Backups $backups) {
            return response()->download($backups->path($name) ?? abort(404));
        })->middleware('can:settings.manage')->name('backups.download');
        Route::view('menus', 'wake::panel.menus')->middleware('can:structure.manage')->name('menus');
        Route::view('redirects', 'wake::panel.redirects')->middleware('can:settings.manage')->name('redirects');
        Route::view('plugins', 'wake::panel.plugins')->middleware('can:settings.manage')->name('plugins');
        Route::view('themes', 'wake::panel.themes')->middleware('can:themes.manage')->name('themes');

        Route::get('posts', [ContentController::class, 'index'])->defaults('type', 'post')->name('posts');
        Route::get('pages', [ContentController::class, 'index'])->defaults('type', 'page')->name('pages');
        Route::get('preview', [PreviewController::class, 'show'])->name('preview');
        Route::get('content/{type}', [ContentController::class, 'index'])->name('entries');
        Route::get('content/{type}/new', [ContentController::class, 'edit'])->name('entries.create');
        Route::get('content/{type}/edit', [ContentController::class, 'edit'])->name('entries.edit');
    });
});
