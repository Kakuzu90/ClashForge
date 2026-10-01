<?php

use App\Http\Controllers\Profile\ProfileController;
use Illuminate\Support\Facades\Route;

// Public profiles (specs/19 §4): no auth, server-rendered. Visibility is checked per request.
// No format constraint: every name reaches the controller, so all misses get the same 404.
Route::get('/u/{username}', [ProfileController::class, 'show'])->name('profile.show');
