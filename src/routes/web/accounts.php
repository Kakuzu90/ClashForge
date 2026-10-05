<?php

use App\Http\Controllers\Accounts\AccountController;
use App\Http\Controllers\Accounts\AccountOwnershipController;
use App\Http\Controllers\Accounts\AttachController;
use App\Http\Controllers\Accounts\VerificationController;
use Illuminate\Support\Facades\Route;

// The attach flow (specs/19 §4, specs/18 §6). Attaching and verifying are account writes, open to
// restricted accounts (specs/04 §3); the services authorize every action and keep their own
// limits (`coc-attach`, `coc-verify`). Detach and featured are account writes too (specs/04 §2).
Route::middleware(['auth', 'account.active'])->prefix('accounts')->name('accounts.')->group(function (): void {
    Route::get('/attach', [AttachController::class, 'create'])->name('attach');
    Route::get('/{ulid}/verify', [VerificationController::class, 'show'])->where('ulid', '[0-9A-Za-z]{26}')->name('verify');
    Route::get('/{ulid}/verified', [VerificationController::class, 'verified'])->where('ulid', '[0-9A-Za-z]{26}')->name('verified');

    Route::middleware('throttle:global-write')->group(function (): void {
        Route::post('/attach/preview', [AttachController::class, 'preview'])->name('attach.preview');
        Route::post('/attach', [AttachController::class, 'store'])->name('attach.store');
        Route::post('/attach/verify-tag', [AttachController::class, 'verifyTag'])->name('attach.verify-tag');
        Route::post('/{ulid}/verify', [VerificationController::class, 'verify'])->where('ulid', '[0-9A-Za-z]{26}')->name('verify.store');
        // Detach and featured (P2-14). The password is typed inline; guesses share `password-confirm`.
        Route::delete('/{ulid}', [AccountOwnershipController::class, 'destroy'])->where('ulid', '[0-9A-Za-z]{26}')->middleware('throttle:password-confirm')->name('destroy');
        Route::put('/{ulid}/featured', [AccountOwnershipController::class, 'feature'])->where('ulid', '[0-9A-Za-z]{26}')->name('featured');
    });
});

// The account page (specs/18 §6): public, the policy decides who sees which account (P2-04).
Route::get('/accounts/{ulid}', [AccountController::class, 'show'])->where('ulid', '[0-9A-Za-z]{26}')->name('accounts.show');
