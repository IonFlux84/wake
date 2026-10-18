<?php

use Illuminate\Support\Facades\Route;

// The panel lives under /wake. These routes use sessions and CSRF protection.

Route::prefix('wake')->name('wake.')->group(function () {
    //
});
