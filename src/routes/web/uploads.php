<?php

use App\Http\Controllers\Upload\UploadController;
use Illuminate\Support\Facades\Route;

// Presigned upload flow (specs/10 §3). JSON only; the browser PUTs the bytes straight to storage.
Route::middleware(['auth', 'verified'])->prefix('uploads')->name('uploads.')->group(function () {
    // Starting an upload is a content write: restricted accounts are blocked too (specs/04 §3).
    Route::post('intent', [UploadController::class, 'intent'])
        ->middleware(['account.active:content', 'throttle:upload-intent'])
        ->name('intent');
    Route::post('{media}/complete', [UploadController::class, 'complete'])->middleware('account.active:content')->whereUlid('media')->name('complete');
    Route::get('{media}', [UploadController::class, 'show'])->whereUlid('media')->name('show');
});
