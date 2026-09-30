<?php

use App\Http\Controllers\Dev\ComponentGalleryController;
use App\Http\Controllers\Dev\LayoutPreviewController;
use App\Http\Controllers\Dev\MediaPreviewController;
use App\Http\Controllers\Home\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/dev/components', ComponentGalleryController::class)->name('dev.components');
Route::get('/dev/layouts/{layout}', LayoutPreviewController::class)
    ->whereIn('layout', ['public', 'app', 'admin'])
    ->name('dev.layouts');
Route::get('/dev/media', MediaPreviewController::class)->name('dev.media');

require __DIR__.'/web/auth.php';
require __DIR__.'/web/uploads.php';
