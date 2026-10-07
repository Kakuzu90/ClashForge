<?php

use App\Http\Controllers\Search\SearchController;
use Illuminate\Support\Facades\Route;

// specs/17, P3-05. Typeahead (P3-12) joins here.
Route::get('/search', SearchController::class)->middleware('throttle:search')->name('search');
