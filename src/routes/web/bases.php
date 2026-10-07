<?php

use App\Http\Controllers\Bases\BaseFeedController;
use Illuminate\Support\Facades\Route;

// specs/17 §6, P3-03. Landing pages (P3-10) and the detail page (P3-11) join here.
Route::get('/bases', BaseFeedController::class)->middleware('throttle:feed')->name('bases.index');
