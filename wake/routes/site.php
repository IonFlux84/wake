<?php

use Illuminate\Support\Facades\Route;
use Wake\Http\Controllers\FeedController;
use Wake\Http\Controllers\MediaController;
use Wake\Http\Controllers\SiteController;
use Wake\Localization\Languages;

// The public website. These routes run without sessions and cookies, which
// keeps responses identical for every visitor and therefore cacheable.

$languages = implode('|', array_map('preg_quote', app(Languages::class)->codes()));

Route::get('media/{hash}/{version}/{variant}/{name}', MediaController::class)
    ->where(['hash' => '[a-f0-9]{10}', 'version' => '[a-z0-9]+', 'name' => '[^/]+'])
    ->name('wake.media');

Route::get('feed.xml', [FeedController::class, 'feed'])->name('wake.feed');
Route::get('{language}/feed.xml', [FeedController::class, 'feed'])->where('language', $languages);
Route::get('sitemap.xml', [FeedController::class, 'sitemap'])->name('wake.sitemap');
Route::get('robots.txt', [FeedController::class, 'robots']);

Route::get('{path?}', [SiteController::class, 'show'])
    ->where('path', '(?!wake(/|$)).*')
    ->middleware('wake.cache')
    ->name('wake.site');
