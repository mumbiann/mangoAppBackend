<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FarmerController;
use App\Http\Controllers\FarmController;
use App\Http\Controllers\SeasonController;
use App\Http\Controllers\NoteController;
use App\Http\Middleware\UuidAuthMiddleware;

Route::prefix('v1')->group(function () {

    // Public routes
    Route::post('farms', [FarmController::class, 'store']);
    
    Route::get('seasons/summary', [SeasonController::class, 'summary']);
    Route::get('seasons/full-details', [SeasonController::class, 'index']);
         
    Route::get('seasons/{month}', [SeasonController::class, 'show'])
         ->where('month', '[1-9]|1[0-2]');

    // ✅ Protected Routes
    Route::middleware(UuidAuthMiddleware::class)->group(function () {

        // Farms
        Route::prefix('farms')->group(function () {
            Route::get('/', [FarmController::class, 'index']);
            Route::get('{farm}', [FarmController::class, 'show']);
            Route::put('{farm}', [FarmController::class, 'update']);
            Route::delete('{farm}', [FarmController::class, 'destroy']);
            Route::get('{farm}/current-season', [FarmController::class, 'getCurrentSeason']);
        });

        // Notes
        Route::prefix('notes')->group(function () {
            Route::post('sync', [NoteController::class, 'sync']);
            Route::get('/', [NoteController::class, 'index']);
            Route::get('statistics', [NoteController::class, 'statistics']);
            Route::get('farm/{farm}', [NoteController::class, 'farmNotes']);
        });
    });
});
