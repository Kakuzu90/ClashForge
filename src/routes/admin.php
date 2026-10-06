<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DisputeController;
use App\Http\Controllers\Admin\FailedJobController;
use App\Http\Controllers\Admin\SanctionController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

// Staff area (specs/19 §4, FR-ADMIN-1). The middleware is defence in depth; every controller
// action still runs its Gate (specs/11 "Broken authorization").
Route::middleware(['auth', 'account.active', 'can:access-admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/audit', AuditLogController::class)->middleware('throttle:admin-search')->name('audit');
    Route::get('/users', [UserController::class, 'index'])->middleware('throttle:admin-search')->name('users.index');
    Route::get('/users/{ulid}', [UserController::class, 'show'])->whereUlid('ulid')->name('users.show');
    // The failed-job list loads on demand per class (P2-19), so the page shares the search limiter.
    Route::get('/system', SystemHealthController::class)->middleware('throttle:admin-search')->name('system');
    // Ownership disputes (P2-17): the controllers authorize `resolve-disputes` and the parties.
    Route::get('/disputes', [DisputeController::class, 'index'])->middleware('throttle:admin-search')->name('disputes.index');
    Route::get('/disputes/{ulid}', [DisputeController::class, 'show'])->whereUlid('ulid')->name('disputes.show');

    // FR-ADMIN-3. `account.active` above blocks suspended, banned and pending-deletion staff.
    Route::middleware('throttle:global-write')->whereUlid('ulid')->group(function (): void {
        Route::post('/users/{ulid}/suspension', [SanctionController::class, 'suspend'])->name('users.suspension.store');
        Route::post('/users/{ulid}/ban', [SanctionController::class, 'ban'])->name('users.ban.store');
        Route::delete('/users/{ulid}/sanction', [SanctionController::class, 'lift'])->name('users.sanction.destroy');
        Route::post('/disputes/{ulid}/decision', [DisputeController::class, 'decide'])->name('disputes.decision.store');
    });

    // Failed jobs (P2-19): the service authorizes `manage-failed-jobs` and audits each job.
    Route::middleware(['throttle:global-write', 'throttle:admin-failed-jobs'])->group(function (): void {
        Route::post('/system/failed-jobs/retry', [FailedJobController::class, 'retry'])->name('system.failed-jobs.retry');
        Route::delete('/system/failed-jobs', [FailedJobController::class, 'destroy'])->name('system.failed-jobs.destroy');
    });
});
