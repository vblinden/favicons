<?php

use App\Http\Controllers\FaviconRefreshController;
use App\Http\Controllers\FaviconShowController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::get('/i/{domain}', FaviconShowController::class)
    ->where('domain', '[A-Za-z0-9.-]+')
    ->name('favicons.show');

Route::delete('/r/{domain}', FaviconRefreshController::class)
    ->where('domain', '[A-Za-z0-9.-]+')
    ->name('favicons.refresh');
