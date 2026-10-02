<?php

use App\Http\Controllers\Accounts\AttachController;
use App\Http\Controllers\Accounts\VerificationController;
use Illuminate\Support\Facades\Route;

// The attach flow (specs/19 §4, specs/18 §6). Attaching and verifying are account writes, open to
// restricted accounts (specs/04 §3); the services authorize every action and keep their own
// limits (`coc-attach`, `coc-verify`).
Route::middleware(['auth', 'account.active'])->prefix('accounts')->name('accounts.')->group(function (): void {
    Route::get('/attach', [AttachController::class, 'create'])->name('attach');
    Route::get('/{ulid}/verify', [VerificationController::class, 'show'])->where('ulid', '[0-9A-Za-z]{26}')->name('verify');
    Route::get('/{ulid}/verified', [VerificationController::class, 'verified'])->where('ulid', '[0-9A-Za-z]{26}')->name('verified');

    Route::middleware('throttle:global-write')->group(function (): void {
        Route::post('/attach/preview', [AttachController::class, 'preview'])->name('attach.preview');
        Route::post('/attach', [AttachController::class, 'store'])->name('attach.store');
        Route::post('/attach/verify-tag', [AttachController::class, 'verifyTag'])->name('attach.verify-tag');
        Route::post('/{ulid}/verify', [VerificationController::class, 'verify'])->where('ulid', '[0-9A-Za-z]{26}')->name('verify.store');
    });
});
