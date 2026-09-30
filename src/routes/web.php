<?php

use App\Http\Controllers\Dev\ComponentGalleryController;
use App\Http\Controllers\Home\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/dev/components', ComponentGalleryController::class)->name('dev.components');
