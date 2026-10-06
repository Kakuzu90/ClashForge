<?php

use App\Http\Controllers\Disputes\DisputeController;
use Illuminate\Support\Facades\Route;

// Ownership disputes, the parties' side (specs/13 §5, P2-16). Account writes, open to restricted
// accounts (specs/04 §3); the services authorize every action and keep their own limits. Another
// user's dispute is a 404, like an unknown one.
Route::middleware(['auth', 'account.active'])->prefix('disputes')->name('disputes.')->group(function (): void {
    Route::get('/create', [DisputeController::class, 'create'])->name('create');
    Route::get('/{ulid}', [DisputeController::class, 'show'])->where('ulid', '[0-9A-Za-z]{26}')->name('show');

    Route::middleware('throttle:global-write')->group(function (): void {
        Route::post('/', [DisputeController::class, 'store'])->middleware('throttle:coc-dispute-write')->name('store');
        Route::post('/{ulid}/respond', [DisputeController::class, 'respond'])->where('ulid', '[0-9A-Za-z]{26}')->middleware('throttle:coc-dispute-write')->name('respond');
        Route::post('/{ulid}/withdraw', [DisputeController::class, 'withdraw'])->where('ulid', '[0-9A-Za-z]{26}')->middleware('throttle:coc-dispute-write')->name('withdraw');
        // Giving the account up is an ownership transfer: the password is typed inline and guesses
        // share `password-confirm` (specs/11).
        Route::post('/{ulid}/release', [DisputeController::class, 'release'])->where('ulid', '[0-9A-Za-z]{26}')->middleware('throttle:password-confirm')->name('release');
    });
});
