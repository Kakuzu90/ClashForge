<?php

use App\Http\Controllers\Upload\UploadController;
use Illuminate\Support\Facades\Route;

// Presigned upload flow (specs/10 §3). JSON only; the browser PUTs the bytes straight to storage.
Route::middleware(['auth', 'verified'])->prefix('uploads')->name('uploads.')->group(function () {
    Route::post('intent', [UploadController::class, 'intent'])
        ->middleware('throttle:upload-intent')
        ->name('intent');
    Route::post('{media}/complete', [UploadController::class, 'complete'])->whereUlid('media')->name('complete');
    Route::get('{media}', [UploadController::class, 'show'])->whereUlid('media')->name('show');
});
