<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FarmerController;
use App\Http\Controllers\FarmController;
use App\Http\Controllers\SeasonController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\SyncController;

Route::prefix('v1')->group(function () {

    // ✅ Auth Routes
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    // ✅ Protected Routes
    Route::middleware('auth:sanctum')->group(function () {

        // Token refresh
        Route::post('auth/refresh', [AuthController::class, 'refresh']);

        // Farmer profile
        Route::get('farmers/profile/{farmer}', [FarmerController::class, 'show']);
        Route::put('farmers/profile/{farmer}', [FarmerController::class, 'update']);

        // Farms
        Route::get('farms', [FarmController::class, 'index']);
        Route::post('farms', [FarmController::class, 'store']);
        Route::get('farms/{farm}', [FarmController::class, 'show']);
        Route::put('farms/{farm}', [FarmController::class, 'update']);
        Route::delete('farms/{farm}', [FarmController::class, 'destroy']);

        // Notes
        Route::get('notes', [NoteController::class, 'index']);
        Route::post('notes/sync', [NoteController::class, 'sync']);

        // Seasons
        Route::get('seasons', [SeasonController::class, 'index']);
        Route::get('seasons/{month}', [SeasonController::class, 'show']);

        // Initial data package
        Route::get('sync/initial-package', [SyncController::class, 'initialPackage']);

        // (Optional) farm sync
        // Route::post('sync/farms', [SyncController::class, 'syncFarms']);
    });
});
