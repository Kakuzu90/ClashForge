<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetLinkRequestController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;

// Fortify's controllers with our pages (FortifyServiceProvider) and our named limiters (specs/04 §4).
// Registration and email verification are ours (FR-AUTH-1/3).

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register')->name('register.store');
    Route::get('/register/sent', [RegisterController::class, 'sent'])->name('register.sent');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    // Ours, not Fortify's: the lookup and the email run in a queued job, off the request path.
    Route::post('/forgot-password', [ResetLinkRequestController::class, 'store'])->middleware('throttle:password-reset')->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    // No limiter: the single-use 64-char token already stops guessing, and counting typos in the
    // new password against `password-reset` would lock people out of the link they just received.
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

// Re-confirmation before sensitive actions (specs/11 "CSRF"): `password.confirm` middleware sends
// here and the confirmation lasts auth.password_timeout (15 minutes).
Route::middleware('auth')->group(function (): void {
    Route::get('/confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('/confirm-password', [ConfirmablePasswordController::class, 'store'])->middleware('throttle:password-confirm')->name('password.confirm.store');
});

// Verification (FR-AUTH-3): the link works signed in or not. Opening it shows the account; the
// page's button (a POST) confirms, so a mail gateway that opens links confirms nothing. The notice
// and resend need a session.
Route::get('/email/verify/{ulid}/{hash}', [EmailVerificationController::class, 'show'])
    ->where(['ulid' => '[0-9A-Za-z]{26}', 'hash' => '[0-9a-f]{40}'])
    ->name('verification.verify');
Route::post('/email/verify/{ulid}/{hash}', [EmailVerificationController::class, 'confirm'])
    ->where(['ulid' => '[0-9A-Za-z]{26}', 'hash' => '[0-9a-f]{40}'])
    ->middleware('throttle:global-write')
    ->name('verification.confirm');
Route::get('/email/verified', [EmailVerificationController::class, 'result'])->name('verification.result');
Route::middleware('auth')->group(function (): void {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:verify-email-resend')
        ->name('verification.send');
});
