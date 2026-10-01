<?php

use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\Notifications\UnsubscribeController;
use Illuminate\Support\Facades\Route;

Route::prefix('notifications/unsubscribe')->name('notifications.unsubscribe.')->group(function (): void {
    Route::get('/done', [UnsubscribeController::class, 'result'])->name('result');
    Route::get('/{ulid}/{hash}', [UnsubscribeController::class, 'show'])->where(['ulid' => '[0-9A-Za-z]{26}', 'hash' => '[0-9a-f]{64}'])->name('show');
    Route::post('/{ulid}/{hash}', [UnsubscribeController::class, 'store'])->where(['ulid' => '[0-9A-Za-z]{26}', 'hash' => '[0-9a-f]{64}'])->middleware('throttle:global-write')->name('store');
});

// The notification centre (FR-NOTIF-1). No `account.active`: marking your own notifications read
// stays open to restricted, suspended and pending-deletion accounts (owner decision, 2026-10-01).
Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function (): void {
    Route::get('/', [NotificationController::class, 'index'])->name('index');

    Route::middleware('throttle:global-write')->group(function (): void {
        Route::post('/read', [NotificationController::class, 'readAll'])->name('read-all');
        Route::post('/{id}/read', [NotificationController::class, 'read'])->whereUuid('id')->name('read');
    });
});
