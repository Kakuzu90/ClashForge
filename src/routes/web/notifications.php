<?php

use App\Http\Controllers\Notifications\NotificationController;
use Illuminate\Support\Facades\Route;

// The notification centre (FR-NOTIF-1). No `account.active`: marking your own notifications read
// stays open to restricted, suspended and pending-deletion accounts (owner decision, 2026-10-01).
Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function (): void {
    Route::get('/', [NotificationController::class, 'index'])->name('index');

    Route::middleware('throttle:global-write')->group(function (): void {
        Route::post('/read', [NotificationController::class, 'readAll'])->name('read-all');
        Route::post('/{id}/read', [NotificationController::class, 'read'])->whereUuid('id')->name('read');
    });
});
