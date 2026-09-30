<?php

use App\Http\Controllers\Account\SuspendedController;
use Illuminate\Support\Facades\Route;

// Where a suspended account lands (specs/04 §1); EnforceAccountStatus sends it here.
Route::get('/account/suspended', SuspendedController::class)->middleware('auth')->name('account.suspended');
