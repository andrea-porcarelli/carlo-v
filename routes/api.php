<?php

use App\Http\Controllers\Api\SyncController;
use App\Http\Middleware\SyncTokenAuth;
use Illuminate\Support\Facades\Route;

Route::prefix('sync')->middleware(SyncTokenAuth::class)->group(function () {
    Route::get('/status', [SyncController::class, 'status']);
    Route::get('/export/{table}', [SyncController::class, 'export']);
    Route::get('/export-media/{mediaId}', [SyncController::class, 'exportMedia']);
});
