<?php

use Illuminate\Support\Facades\Route;
use Wake\Http\Controllers\Panel\ContentController;
use Wake\Http\Controllers\Panel\DashboardController;
use Wake\Http\Controllers\Panel\LoginController;
use Wake\Http\Controllers\Panel\PasswordController;
use Wake\Http\Controllers\Panel\SetupController;

// The panel lives under /wake. These routes use sessions and CSRF protection.

Route::prefix('wake')->name('wake.')->middleware('wake.panel')->group(function () {
    Route::get('setup', [SetupController::class, 'show'])->name('setup');
    Route::post('setup', [SetupController::class, 'store'])->middleware('throttle:10,1');

    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'store']);
        Route::get('password', [PasswordController::class, 'request'])->name('password.request');
        Route::post('password', [PasswordController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
        Route::get('password/{token}', [PasswordController::class, 'reset'])->name('password.reset');
        Route::post('password/reset', [PasswordController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
        Route::get('invitation/{id}/{token}', [PasswordController::class, 'invitation'])->name('invitation');
        Route::post('invitation/{id}/{token}', [PasswordController::class, 'accept'])->middleware('throttle:10,1');
    });

    Route::middleware('auth')->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::view('stats', 'wake::panel.stats')->name('stats');
        Route::view('stats/journey', 'wake::panel.journey')->name('stats.journey');
        Route::view('consent', 'wake::panel.consent')->name('consent');
        Route::view('assistants', 'wake::panel.assistants')->name('assistants');
        Route::view('team', 'wake::panel.team')->name('team');
        Route::view('settings', 'wake::panel.settings')->name('settings');
        Route::view('media', 'wake::panel.media')->name('media.index');

        Route::get('posts', [ContentController::class, 'index'])->defaults('type', 'post')->name('posts');
        Route::get('pages', [ContentController::class, 'index'])->defaults('type', 'page')->name('pages');
        Route::get('content/{type}', [ContentController::class, 'index'])->name('entries');
        Route::get('content/{type}/new', [ContentController::class, 'edit'])->name('entries.create');
        Route::get('content/{type}/edit', [ContentController::class, 'edit'])->name('entries.edit');
    });
});
