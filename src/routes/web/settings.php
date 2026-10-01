<?php

use App\Http\Controllers\Settings\AccountDeletionController;
use App\Http\Controllers\Settings\EmailChangeController;
use App\Http\Controllers\Settings\EmailPreferenceController;
use App\Http\Controllers\Settings\PrivacyController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('settings')->name('settings.')->group(function (): void {
    Route::get('/notifications', [EmailPreferenceController::class, 'edit'])->name('notifications.edit');
    Route::patch('/notifications', [EmailPreferenceController::class, 'update'])->middleware('throttle:global-write')->name('notifications.update');
});

// The settings area (specs/19 §4, specs/18 §6). Profile and privacy writes are account writes,
// open to restricted accounts (specs/04 §3); the policies re-check every action.
Route::middleware(['auth', 'account.active'])->prefix('settings')->name('settings.')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/privacy', [PrivacyController::class, 'edit'])->name('privacy.edit');
    Route::get('/security', [SecurityController::class, 'edit'])->name('security.edit');
    Route::get('/danger-zone', [AccountDeletionController::class, 'edit'])->name('danger-zone.edit');
    // The link in the email-change message: it needs the account signed in on this browser, so a
    // guest signs in and comes back. Opening it changes nothing; the page's button confirms.
    Route::get('/email/confirm/{ulid}/{hash}', [EmailChangeController::class, 'show'])
        ->where(['ulid' => '[0-9A-Za-z]{26}', 'hash' => '[0-9a-f]{40}'])
        ->name('email.show');
    Route::get('/email/confirmed', [EmailChangeController::class, 'result'])->name('email.result');
    Route::middleware('throttle:global-write')->group(function (): void {
        Route::delete('/danger-zone', [AccountDeletionController::class, 'destroy'])->middleware('throttle:password-confirm')->name('danger-zone.destroy');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        // Username change (FR-PROFILE-7): the current password inline, guesses in `password-confirm`.
        Route::put('/profile/username', [ProfileController::class, 'updateUsername'])->middleware('throttle:password-confirm')->name('profile.username.update');
        Route::put('/profile/avatar', [ProfileController::class, 'setAvatar'])->name('profile.avatar.update');
        Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.destroy');
        Route::patch('/privacy', [PrivacyController::class, 'update'])->name('privacy.update');
        // Current-password guesses share the `password-confirm` bucket with the confirm page.
        Route::put('/security/password', [SecurityController::class, 'updatePassword'])->middleware('throttle:password-confirm')->name('security.password.update');
        Route::delete('/security/sessions', [SecurityController::class, 'destroyOtherSessions'])->name('security.sessions.destroy-others');
        // Email change (FR-AUTH-8): the request and a resend take the current password inline
        // (specs/11 re-confirmation), so a copied cookie cannot finish a stale change later; the
        // guesses share the `password-confirm` bucket. The `email-change` limit counts accepted
        // requests and resends in the service.
        Route::put('/security/email', [EmailChangeController::class, 'update'])->middleware('throttle:password-confirm')->name('security.email.update');
        Route::post('/security/email/resend', [EmailChangeController::class, 'resend'])->middleware('throttle:password-confirm')->name('security.email.resend');
        Route::delete('/security/email', [EmailChangeController::class, 'destroy'])->name('security.email.destroy');
        Route::post('/email/confirm/{ulid}/{hash}', [EmailChangeController::class, 'confirm'])
            ->where(['ulid' => '[0-9A-Za-z]{26}', 'hash' => '[0-9a-f]{40}'])
            ->name('email.confirm');
        Route::delete('/security/sessions/{key}', [SecurityController::class, 'destroySession'])->where('key', '[0-9a-f]{32}')->name('security.sessions.destroy');
    });
});
