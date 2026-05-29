<?php

use App\Http\Controllers\Api\V1\NoteController;
use Illuminate\Support\Facades\Route;

// API Routes - V1
Route::prefix('v1')->group(function () {
    Route::apiResource('notes', NoteController::class)
        ->except(['store', 'update'])
        ->middleware('throttle:200,1');

    Route::apiResource('notes', NoteController::class)
    ->only(['store', 'update'])
    ->middleware('throttle:30,1');
});
