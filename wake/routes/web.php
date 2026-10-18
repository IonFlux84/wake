<?php

use Illuminate\Support\Facades\Route;
use Wake\Http\Controllers\Panel\DashboardController;
use Wake\Http\Controllers\Panel\LoginController;
use Wake\Http\Controllers\Panel\SetupController;

// The panel lives under /wake. These routes use sessions and CSRF protection.

Route::prefix('wake')->name('wake.')->middleware('wake.panel')->group(function () {
    Route::get('setup', [SetupController::class, 'show'])->name('setup');
    Route::post('setup', [SetupController::class, 'store'])->middleware('throttle:10,1');

    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'store']);
    });

    Route::middleware('auth')->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::view('stats', 'wake::panel.stats')->name('stats');
        Route::view('stats/journey', 'wake::panel.journey')->name('stats.journey');
        Route::view('consent', 'wake::panel.consent')->name('consent');
        Route::view('assistants', 'wake::panel.assistants')->name('assistants');
    });
});
