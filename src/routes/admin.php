<?php

use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

// Staff area (specs/19 §4, FR-ADMIN-1). The middleware is defence in depth; every controller
// action still runs its Gate (specs/11 "Broken authorization").
Route::middleware(['auth', 'account.active', 'can:access-admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
});
