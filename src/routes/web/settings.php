<?php

use App\Http\Controllers\Settings\PrivacyController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

// The settings area (specs/19 §4, specs/18 §6). Profile and privacy writes are account writes,
// open to restricted accounts (specs/04 §3); the policies re-check every action.
Route::middleware(['auth', 'account.active'])->prefix('settings')->name('settings.')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/privacy', [PrivacyController::class, 'edit'])->name('privacy.edit');
    Route::middleware('throttle:global-write')->group(function (): void {
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/avatar', [ProfileController::class, 'setAvatar'])->name('profile.avatar.update');
        Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.destroy');
        Route::patch('/privacy', [PrivacyController::class, 'update'])->name('privacy.update');
    });
});
