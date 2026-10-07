<?php

use App\Http\Controllers\Dev\ComponentGalleryController;
use App\Http\Controllers\Dev\LayoutPreviewController;
use App\Http\Controllers\Dev\MediaPreviewController;
use App\Http\Controllers\Home\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->middleware('throttle:feed')->name('home');

Route::get('/dev/components', ComponentGalleryController::class)->name('dev.components');
Route::get('/dev/layouts/{layout}', LayoutPreviewController::class)
    ->whereIn('layout', ['public', 'app', 'admin'])
    ->name('dev.layouts');
Route::get('/dev/media', MediaPreviewController::class)->name('dev.media');

require __DIR__.'/web/auth.php';
require __DIR__.'/web/bases.php';
require __DIR__.'/web/account.php';
require __DIR__.'/web/profile.php';
require __DIR__.'/web/accounts.php';
require __DIR__.'/web/disputes.php';
require __DIR__.'/web/settings.php';
require __DIR__.'/web/notifications.php';
require __DIR__.'/web/moderation.php';
require __DIR__.'/web/uploads.php';
