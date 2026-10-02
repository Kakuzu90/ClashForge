<?php

use App\Http\Controllers\Moderation\ReportController;
use Illuminate\Support\Facades\Route;

// The moderators' workspace, outside /admin (owner decision, 2026-10-02): moderators hold
// `view-report-queue` but not `access-admin`. Admins open it too.
Route::middleware(['auth', 'account.active', 'can:view-report-queue'])->prefix('moderation')->name('moderation.')->group(function (): void {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
});
