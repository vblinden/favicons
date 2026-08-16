<?php

use App\Http\Controllers\FaviconRefreshController;
use App\Http\Controllers\FaviconShowController;
use App\Http\Controllers\LeaderboardController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::get('/leaderboard', LeaderboardController::class)->name('leaderboard');

Route::get('/i/{domain}', FaviconShowController::class)
    ->where('domain', '[A-Za-z0-9.-]+')
    ->name('favicons.show');

Route::delete('/r/{domain}', FaviconRefreshController::class)
    ->where('domain', '[A-Za-z0-9.-]+')
    ->name('favicons.refresh');
