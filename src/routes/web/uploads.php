<?php

use App\Http\Controllers\Upload\UploadController;
use Illuminate\Support\Facades\Route;

// Presigned upload flow (specs/10 §3). JSON only; the browser PUTs the bytes straight to storage.
Route::middleware(['auth', 'verified'])->prefix('uploads')->name('uploads.')->group(function () {
    // Restricted accounts may still upload an avatar (a profile write); MediaPolicy decides per
    // collection, so the route gate only stops accounts that may not write at all (specs/04 §3).
    Route::post('intent', [UploadController::class, 'intent'])
        ->middleware(['account.active', 'throttle:upload-intent'])
        ->name('intent');
    Route::post('{media}/complete', [UploadController::class, 'complete'])->middleware('account.active')->whereUlid('media')->name('complete');
    Route::get('{media}', [UploadController::class, 'show'])->whereUlid('media')->name('show');
});
