<?php

use Illuminate\Support\Facades\Route;
use Mvd81\LaravelLogreader\Http\Controllers\LogreaderController;
use Mvd81\LaravelLogreader\Http\Middleware\EnsureLogreaderEnabled;
use Mvd81\LaravelLogreader\Http\Middleware\ValidateLogreaderToken;

Route::middleware([ValidateLogreaderToken::class, EnsureLogreaderEnabled::class])
    ->prefix( 'api/v1/logreader')
    ->name('logreader.')
    ->group(function () {
        Route::get('/list', [LogreaderController::class, 'list'])->name('list');
        Route::get('/read', [LogreaderController::class, 'read'])->name('read');
        Route::get('/count', [LogreaderController::class, 'count'])->name('count');
        Route::post('/search', [LogreaderController::class, 'search'])->name('search');
    });
