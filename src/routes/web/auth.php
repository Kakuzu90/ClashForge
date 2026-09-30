<?php

use App\Http\Controllers\Auth\ResetLinkRequestController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;

// Fortify's controllers with our pages (FortifyServiceProvider) and our named limiters (specs/04 §4).
// Registration and email verification join in P1-08.

Route::middleware('guest')->group(function (): void {
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
