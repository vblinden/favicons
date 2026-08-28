<?php

use App\Http\Controllers\FaviconBatchController;
use App\Http\Controllers\FaviconGoogleController;
use App\Http\Controllers\FaviconMetadataController;
use App\Http\Controllers\FaviconRefreshController;
use App\Http\Controllers\FaviconShowController;
use Illuminate\Support\Facades\Route;

Route::get('/i/{domain}', FaviconShowController::class)
    ->where('domain', '[A-Za-z0-9.-]+')
    ->name('favicons.show');

Route::get('/m/{domain}', FaviconMetadataController::class)
    ->where('domain', '[A-Za-z0-9.-]+')
    ->name('favicons.metadata');

Route::get('/b', FaviconBatchController::class)
    ->name('favicons.batch');

Route::get('/s2/favicons', FaviconGoogleController::class)
    ->name('favicons.google');

Route::delete('/r/{domain}', FaviconRefreshController::class)
    ->where('domain', '[A-Za-z0-9.-]+')
    ->name('favicons.refresh');
