<?php

use App\Http\Controllers\FaviconRefreshController;
use App\Http\Controllers\FaviconShowController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\LlmsTxtController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::get('/leaderboard', LeaderboardController::class)->name('leaderboard');

Route::view('/terms', 'legal.terms')->name('legal.terms');
Route::view('/acceptable-use', 'legal.acceptable-use')->name('legal.acceptable-use');
Route::view('/privacy', 'legal.privacy')->name('legal.privacy');

Route::get('/llms.txt', LlmsTxtController::class)->name('llms.txt');

Route::get('/i/{domain}', FaviconShowController::class)
    ->where('domain', '[A-Za-z0-9.-]+')
    ->name('favicons.show');

Route::delete('/r/{domain}', FaviconRefreshController::class)
    ->where('domain', '[A-Za-z0-9.-]+')
    ->name('favicons.refresh');
